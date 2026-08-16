<?php

/**
 * PasswordResetModel — quên mật khẩu (bảng password_resets).
 *
 * Dùng cùng khuôn selector/validator như RememberModel; lý do tách đôi đã ghi
 * kỹ ở đầu file đó.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI ĐƯỜNG ĐẶT LẠI, MỘT BẢNG
 *
 *   Gửi được mail   -> tạo token ngay, gửi liên kết, status = 'sent'
 *   Không gửi được  -> chỉ GHI NHẬN yêu cầu, status = 'pending'
 *                      Nhân viên thấy trong /quan-tri/quen-mat-khau, gọi điện
 *                      xác minh, rồi mới bấm tạo liên kết.
 *
 * Ở nhánh 'pending' KHÔNG tạo token sẵn. Một token còn hạn nằm trong cơ sở dữ
 * liệu mà chưa ai xác minh danh tính người yêu cầu là một chìa khoá bỏ ngỏ:
 * chỉ cần rò rỉ bảng, hoặc một nhân viên tò mò, là đổi được mật khẩu của
 * khách. Token chỉ sinh ra đúng lúc có người chịu trách nhiệm cho nó.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class PasswordResetModel extends BaseModel
{
    protected static string $table = 'password_resets';

    /** Liên kết sống bao lâu (giây) — 60 phút. */
    public const LIFETIME = 3600;

    /** Số yêu cầu tối đa cho một chuỗi liên hệ trong một giờ. */
    private const MAX_PER_HOUR = 5;

    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /**
     * Khách gửi yêu cầu đặt lại.
     *
     * LUÔN trả về cùng một kết quả dù tài khoản có tồn tại hay không — nếu
     * báo "không tìm thấy email này" thì trang quên mật khẩu trở thành công cụ
     * dò xem ai có tài khoản ở đây.
     *
     * @return array{ok:bool, sent:bool, error?:string}
     */
    public static function request(string $contact): array
    {
        if (!self::available()) {
            return ['ok' => false, 'sent' => false,
                    'error' => 'Chức năng đặt lại mật khẩu chưa được bật. Vui lòng gọi hotline.'];
        }

        $contact = trim($contact);

        if ($contact === '' || utf8Length($contact) > 255) {
            return ['ok' => false, 'sent' => false, 'error' => 'Vui lòng nhập email hoặc số điện thoại.'];
        }

        // Chặn gửi liên tục: vừa để khỏi làm phiền chủ hộp thư, vừa để không
        // ai dùng site này làm máy gửi thư rác.
        if (self::tooMany($contact)) {
            return ['ok' => false, 'sent' => false,
                    'error' => 'Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau một giờ.'];
        }

        $user = UserModel::findByLogin($contact);

        // Không có tài khoản: vẫn ghi nhận (user_id = NULL) rồi trả về như
        // thành công. Nhân viên nhìn vào sẽ thấy ngay là khách gõ nhầm.
        if ($user === null) {
            self::record($contact, null, 'pending');

            return ['ok' => true, 'sent' => false];
        }

        $email = (string) $user['email'];

        if (!Mailer::canDeliver()) {
            self::record($contact, (string) $user['id'], 'pending');

            return ['ok' => true, 'sent' => false];
        }

        $id    = self::record($contact, (string) $user['id'], 'pending');
        $link  = self::attachToken($id);

        $sent = Mailer::send(
            $email,
            'Đặt lại mật khẩu Vin Eyewear',
            self::emailHtml($link, (string) ($user['full_name'] ?? ''))
        );

        if (!$sent) {
            // Gửi hụt: gỡ token đi, trả yêu cầu về hàng chờ để nhân viên xử lý.
            // Để token còn sống mà không ai nhận được liên kết là vô ích và
            // chỉ làm dài thêm thời gian tồn tại của một chìa khoá.
            self::detachToken($id);

            return ['ok' => true, 'sent' => false];
        }

        Database::execute("UPDATE password_resets SET status = 'sent' WHERE id = :id", ['id' => $id]);

        return ['ok' => true, 'sent' => true];
    }

    /**
     * Nhân viên tạo liên kết cho một yêu cầu đang chờ.
     *
     * @return array{ok:bool, link?:string, error?:string}
     */
    public static function issueByStaff(string $requestId, string $staffId): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Bảng password_resets chưa tồn tại.'];
        }

        $row = static::find($requestId);

        if ($row === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy yêu cầu.'];
        }

        if ($row['user_id'] === null) {
            return ['ok' => false, 'error' =>
                'Yêu cầu này không khớp tài khoản nào — khách có thể đã gõ nhầm email/số điện thoại.'];
        }

        if ($row['status'] === 'used') {
            return ['ok' => false, 'error' => 'Yêu cầu này đã được dùng để đổi mật khẩu.'];
        }

        $link = self::attachToken($requestId);

        Database::execute(
            "UPDATE password_resets SET status = 'sent', handled_by = :by WHERE id = :id",
            ['by' => $staffId, 'id' => $requestId]
        );

        return ['ok' => true, 'link' => $link];
    }

    /**
     * Tra token từ chuỗi trong URL.
     *
     * @return array|null Dòng password_resets kèm 'email' của chủ tài khoản
     */
    public static function findValid(string $token): ?array
    {
        if (!self::available() || !str_contains($token, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $token, 2);

        if (preg_match('/^[a-f0-9]{32}$/', $selector) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $validator) !== 1) {
            return null;
        }

        $row = Database::fetchOne(
            'SELECT r.*, u.email
               FROM password_resets r
               JOIN users u ON u.id = r.user_id
              WHERE r.selector = :sel',
            ['sel' => $selector]
        );

        if ($row === null || $row['status'] === 'used' || $row['validator'] === null) {
            return null;
        }

        if ($row['expires_at'] === null || strtotime((string) $row['expires_at']) < time()) {
            return null;
        }

        if (!hash_equals((string) $row['validator'], hash('sha256', $validator))) {
            return null;
        }

        return $row;
    }

    /**
     * Đổi mật khẩu bằng token và đánh dấu đã dùng.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function complete(string $token, string $newPassword): array
    {
        $row = self::findValid($token);

        if ($row === null) {
            return ['ok' => false, 'error' =>
                'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu lại.'];
        }

        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'error' => 'Mật khẩu mới phải từ 8 ký tự.'];
        }

        $userId = (string) $row['user_id'];

        Database::transaction(static function () use ($userId, $newPassword, $row): void {
            Database::execute(
                'UPDATE users SET password_hash = :h WHERE id = :id',
                ['h' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]
            );

            Database::execute(
                "UPDATE password_resets SET status = 'used', used_at = NOW() WHERE id = :id",
                ['id' => $row['id']]
            );

            // Mọi yêu cầu khác còn treo của chính người này cũng hết giá trị:
            // đã đặt lại được rồi thì các liên kết cũ không còn lý do tồn tại.
            Database::execute(
                "UPDATE password_resets
                    SET status = 'used', used_at = NOW()
                  WHERE user_id = :u AND status <> 'used'",
                ['u' => $userId]
            );
        });

        // Đá mọi thiết bị đang "ghi nhớ đăng nhập" của tài khoản này.
        RememberModel::forgetAllFor($userId);

        return ['ok' => true];
    }

    /**
     * Danh sách yêu cầu cho trang quản trị.
     */
    public static function pending(int $limit = 100): array
    {
        if (!self::available()) {
            return [];
        }

        return Database::fetchAll(
            "SELECT r.*, u.email, p.full_name, p.phone
               FROM password_resets r
               LEFT JOIN users    u ON u.id = r.user_id
               LEFT JOIN profiles p ON p.id = r.user_id
              WHERE r.status <> 'used'
              ORDER BY r.created_at DESC
              LIMIT " . max(1, min(500, $limit))
        );
    }

    public static function countPending(): int
    {
        if (!self::available()) {
            return 0;
        }

        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM password_resets WHERE status = 'pending'"
        );
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    private static function record(string $contact, ?string $userId, string $status): string
    {
        $id = uuid();

        Database::execute(
            'INSERT INTO password_resets (id, user_id, contact, status)
             VALUES (:id, :uid, :contact, :status)',
            ['id' => $id, 'uid' => $userId, 'contact' => $contact, 'status' => $status]
        );

        return $id;
    }

    /** Sinh token cho một yêu cầu, trả về liên kết đầy đủ. */
    private static function attachToken(string $id): string
    {
        $selector  = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        Database::execute(
            'UPDATE password_resets
                SET selector = :sel, validator = :val, expires_at = :exp
              WHERE id = :id',
            [
                'sel' => $selector,
                'val' => hash('sha256', $validator),
                'exp' => date('Y-m-d H:i:s', time() + self::LIFETIME),
                'id'  => $id,
            ]
        );

        return rtrim((string) config('app.url'), '/')
             . '/dat-lai-mat-khau?token=' . rawurlencode($selector . ':' . $validator);
    }

    private static function detachToken(string $id): void
    {
        Database::execute(
            "UPDATE password_resets
                SET selector = NULL, validator = NULL, expires_at = NULL, status = 'pending'
              WHERE id = :id",
            ['id' => $id]
        );
    }

    private static function tooMany(string $contact): bool
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM password_resets
              WHERE contact = :c AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            ['c' => $contact]
        ) >= self::MAX_PER_HOUR;
    }

    private static function emailHtml(string $link, string $name): string
    {
        $hello = $name !== '' ? 'Chào ' . e($name) . ',' : 'Chào bạn,';
        $mins  = (int) (self::LIFETIME / 60);

        return <<<HTML
        <div style="font-family:system-ui,-apple-system,'Segoe UI',sans-serif;
                    font-size:15px;line-height:1.7;color:#1a1214;max-width:520px">
          <p style="font-family:Georgia,serif;font-size:22px;margin:0 0 18px">Vin Eyewear</p>
          <p>{$hello}</p>
          <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.
             Bấm vào nút dưới đây để chọn mật khẩu mới:</p>
          <p style="margin:24px 0">
            <a href="{$link}"
               style="display:inline-block;padding:13px 26px;background:#801a20;
                      color:#faf6f2;text-decoration:none;font-weight:600">
              Đặt lại mật khẩu
            </a>
          </p>
          <p style="color:#5c4f52;font-size:13px">
            Liên kết có hiệu lực trong {$mins} phút và chỉ dùng được một lần.<br>
            Nếu bạn không yêu cầu việc này, hãy bỏ qua email — mật khẩu hiện tại
            của bạn không thay đổi.
          </p>
          <p style="color:#5c4f52;font-size:13px">
            Nút không bấm được? Chép đường dẫn này vào trình duyệt:<br>
            <span style="word-break:break-all">{$link}</span>
          </p>
        </div>
        HTML;
    }
}
