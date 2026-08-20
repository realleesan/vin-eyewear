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
 *   Khách tự làm  -> mã OTP 6 số gửi thẳng cho khách, requestOtp() bên dưới.
 *                    Kênh chọn theo thứ khách gõ vào ô:
 *                        có '@'  -> email
 *                        là số   -> Zalo
 *                    Xác minh xong thì đặt mật khẩu ngay trong cùng phiên,
 *                    không sinh token nào cả.
 *
 *   Nhân viên      -> issueByStaff() tạo LIÊN KẾT có token cho những ca khách
 *                    không nhận được mã (mất số, sai email, kênh gửi hỏng).
 *                    Vào bằng /dat-lai-mat-khau?token=… — mở được bằng GET,
 *                    không cần phiên nào, nên gửi qua Zalo/điện thoại được.
 *
 * VÌ SAO KHÁCH DÙNG OTP CHỨ KHÔNG PHẢI LIÊN KẾT: liên kết chỉ đi được qua
 * email. Phần lớn tài khoản ở đây đăng ký bằng số điện thoại và email là NULL
 * (xem migration 2026-08-19-dang-ky-khong-email-va-google), nên với họ đường
 * liên kết không tồn tại. Mã 6 số thì kênh nào cũng chở được — email hôm nay,
 * Zalo ZNS khi cắm xong nhà cung cấp.
 *
 * Ở nhánh nhân viên KHÔNG tạo token sẵn lúc khách vừa yêu cầu. Một token còn
 * hạn nằm trong cơ sở dữ liệu mà chưa ai xác minh danh tính người yêu cầu là
 * một chìa khoá bỏ ngỏ: chỉ cần rò rỉ bảng, hoặc một nhân viên tò mò, là đổi
 * được mật khẩu của khách. Token chỉ sinh ra đúng lúc có người chịu trách
 * nhiệm cho nó.
 *
 * MÃ OTP KHÔNG CẤT VÀO BẢNG NÀY. Nó sống trong $_SESSION của chính người đang
 * yêu cầu, đã băm — cùng lối với luồng đăng ký, xem core/Otp.php. Bảng chỉ giữ
 * phần cần cho việc khác: đếm số lần yêu cầu (chống spam) và hàng chờ cho nhân
 * viên. Cất thêm mã vào đây chỉ làm dài thêm danh sách thứ bị lộ khi rò rỉ.
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
     * Khách bấm "Gửi yêu cầu" ở /quen-mat-khau.
     *
     * Sinh mã OTP, gửi qua kênh hợp với thứ khách vừa gõ, và trả về trạng thái
     * để controller cất vào phiên. KHÔNG tự đụng $_SESSION: model chạy được cả
     * trong script CLI, mà ở đó không có phiên nào.
     *
     * LUÔN TRẢ VỀ NHƯ NHAU DÙ TÀI KHOẢN CÓ TỒN TẠI HAY KHÔNG — kể cả khi không
     * tìm thấy ai, hàm vẫn sinh mã, vẫn trả 'ok', để màn nhập mã hiện lên y
     * hệt. Báo "không tìm thấy email này" sẽ biến trang quên mật khẩu thành
     * công cụ dò xem ai là khách hàng của cửa hàng. Mã của một yêu cầu không
     * khớp ai thì không được gửi đi đâu cả, nên không ai nhập đúng nó được.
     *
     * @return array{
     *     ok: bool, error?: string, channel?: string, display?: string,
     *     user_id?: ?string, hash?: string, sent?: bool, code?: ?string
     * }
     *     code chỉ có giá trị khi app.debug bật — xem chú thích ở nơi gọi.
     */
    public static function requestOtp(string $contact): array
    {
        if (!self::available()) {
            return ['ok' => false,
                    'error' => 'Chức năng đặt lại mật khẩu chưa được bật. Vui lòng gọi hotline.'];
        }

        $contact = trim($contact);

        if ($contact === '' || utf8Length($contact) > 255) {
            return ['ok' => false, 'error' => 'Vui lòng nhập email hoặc số điện thoại.'];
        }

        $channel = self::channelOf($contact);

        if ($channel === null) {
            return ['ok' => false,
                    'error' => 'Chưa nhận ra email hay số điện thoại. Kiểm tra lại giúp bạn nhé.'];
        }

        // Chặn gửi liên tục: vừa để khỏi làm phiền chủ hộp thư hay chủ số điện
        // thoại, vừa để không ai dùng site này làm máy gửi thư rác. Đếm theo
        // chuỗi khách gõ chứ không theo tài khoản, vì chuỗi không khớp ai thì
        // không có tài khoản nào để mà đếm.
        if (self::tooMany($contact)) {
            return ['ok' => false,
                    'error' => 'Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau một giờ.'];
        }

        $user   = UserModel::findByLogin($contact);
        $userId = $user !== null ? (string) $user['id'] : null;
        $code   = Otp::generate();

        // Ghi nhận MỌI yêu cầu, kể cả yêu cầu không khớp tài khoản nào: nhân
        // viên nhìn hàng chờ sẽ thấy ngay là khách gõ nhầm, và đó cũng là thứ
        // tooMany() đếm.
        $requestId = self::record($contact, $userId, 'pending');

        $sent = false;

        if ($user !== null) {
            $sent = $channel === 'email'
                ? self::mailOtp((string) $user['email'], $code, (string) ($user['full_name'] ?? ''))
                // Zalo ZNS CHƯA CẮM: hàm này mới chỉ ghi mã ra error log.
                // Chỗ cắm nhà cung cấp là Otp::send(), xem core/Otp.php.
                : Otp::send((string) ($user['phone'] ?? $contact), $code, 'zalo');

            if ($sent) {
                Database::execute(
                    "UPDATE password_resets SET status = 'sent' WHERE id = :id",
                    ['id' => $requestId]
                );
            }
        }

        return [
            'ok'      => true,
            'channel' => $channel,
            'display' => self::maskContact($contact, $channel),
            'user_id' => $userId,
            'hash'    => Otp::hash($code),
            'sent'    => $sent,
            // Ở máy phát triển thì controller in mã ra màn hình — chưa có nhà
            // cung cấp nào chở mã tới tay khách, mà hosting hiện tại cũng chặn
            // luôn cả gửi mail (xem MAIL_DRIVER trong .env).
            'code'    => config('app.debug') ? $code : null,
        ];
    }

    /**
     * Khách gõ email hay số điện thoại? null = không ra thứ nào cả.
     *
     * Nhận diện theo dấu '@' chứ không phải "thử số trước rồi thử email":
     * looksLikePhone() nhận cả chuỗi có dấu cách và dấu gạch, nên một địa chỉ
     * gõ vội cũng lọt qua nó được. Có '@' thì chắc chắn không phải số.
     */
    public static function channelOf(string $contact): ?string
    {
        $contact = trim($contact);

        if (str_contains($contact, '@')) {
            return filter_var($contact, FILTER_VALIDATE_EMAIL) !== false ? 'email' : null;
        }

        return normalizePhone($contact) !== null ? 'zalo' : null;
    }

    /**
     * Chuỗi in trên màn nhập mã: "ng***an@gmail.com" · "(+84) 912 *** 678".
     *
     * Che bớt vì màn đó mở được mà không cần đăng nhập: ai mượn máy người khác
     * cũng đọc được nguyên địa chỉ nếu in đủ. Chừa hai đầu để chính chủ vẫn
     * nhận ra mình gõ đúng chỗ chưa.
     */
    public static function maskContact(string $contact, string $channel): string
    {
        if ($channel !== 'email') {
            return Otp::displayPhone($contact);
        }

        [$name, $domain] = array_pad(explode('@', trim($contact), 2), 2, '');

        // Tên ngắn thì chỉ chừa ký tự đầu: chừa cả đuôi nữa mà tên chỉ có một
        // hai chữ thì phần che chẳng che được gì — "a***a" cho địa chỉ a@… vừa
        // lộ hết vừa làm người đọc tưởng tên dài hơn thực tế.
        if (strlen($name) <= 3) {
            return substr($name, 0, 1) . '***@' . $domain;
        }

        return substr($name, 0, 2) . '***' . substr($name, -1) . '@' . $domain;
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

        return self::applyNewPassword((string) $row['user_id'], $newPassword);
    }

    /**
     * Đặt mật khẩu mới cho một tài khoản đã được xác minh danh tính.
     *
     * Dùng chung cho CẢ HAI đường vào — token của nhân viên (complete() ở
     * trên) và mã OTP khách tự nhập (AuthController::forgotFinish). Việc phải
     * làm giống hệt nhau ở hai nơi, mà mỗi việc bỏ sót đều là một lỗ hổng:
     * quên đóng yêu cầu cũ là để lại chìa khoá dùng được lần hai, quên thu hồi
     * "ghi nhớ đăng nhập" là kẻ đã chiếm được máy vẫn ở nguyên trong tài khoản
     * dù chính chủ vừa đổi mật khẩu.
     *
     * NGƯỜI GỌI CHỊU TRÁCH NHIỆM XÁC MINH. Hàm này không hỏi lại token hay mã —
     * đưa $userId vào là nó đổi. Đừng gọi từ chỗ chưa kiểm gì.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function applyNewPassword(string $userId, string $newPassword): array
    {
        $problem = passwordProblem($newPassword);

        if ($problem !== null) {
            return ['ok' => false, 'error' => $problem];
        }

        Database::transaction(static function () use ($userId, $newPassword): void {
            Database::execute(
                'UPDATE users SET password_hash = :h WHERE id = :id',
                ['h' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => $userId]
            );

            // Mọi yêu cầu còn treo của chính người này đều hết giá trị: đã đặt
            // lại được rồi thì các liên kết cũ không còn lý do tồn tại.
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

    private static function tooMany(string $contact): bool
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM password_resets
              WHERE contact = :c AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
            ['c' => $contact]
        ) >= self::MAX_PER_HOUR;
    }

    /**
     * Gửi mã qua email. Trả về true CHỈ KHI thư thật sự có đường ra ngoài.
     *
     * MAIL_DRIVER='log' không phải là gửi được: nó chỉ ghi thư vào storage/mail
     * để người phát triển xem lại. Trả true cho nó thì yêu cầu bị đánh dấu
     * 'sent' và biến mất khỏi hàng chờ ở /quan-tri/quen-mat-khau — khách không
     * nhận được gì mà cũng không còn ai biết để gọi lại cho họ. Hosting hiện
     * tại đúng là đang ở tình trạng đó: InfinityFree bản miễn phí chặn cả hàm
     * mail() lẫn cổng SMTP đi ra.
     *
     * Vẫn GỌI Mailer::send ở máy phát triển dù driver là 'log', vì đó là cách
     * duy nhất xem lại nội dung thư. Trên production thì không: ghi mã xuống
     * đĩa mà chẳng ai đọc chỉ tổ để lại một bản sao của thứ cần giữ kín.
     */
    private static function mailOtp(string $to, string $code, string $name): bool
    {
        $deliverable = Mailer::canDeliver();

        if (!$deliverable && !config('app.debug')) {
            return false;
        }

        $sent = Mailer::send(
            $to,
            'Mã đặt lại mật khẩu Vin Eyewear',
            self::otpEmailHtml($code, $name),
            self::otpEmailText($code)
        );

        return $deliverable && $sent;
    }

    /**
     * Thư chở mã OTP.
     *
     * MÃ NẰM NGAY TRONG PHẦN NHÌN THẤY, cỡ chữ lớn, giãn ký tự — người đọc
     * trên điện thoại phải chép được bằng mắt, không phải bấm vào đâu cả.
     * Không có nút, không có liên kết: thư đặt lại mật khẩu mà có nút bấm là
     * thứ khách bị dạy phải bấm, và đó đúng là thói quen mà lừa đảo khai thác.
     */
    private static function otpEmailHtml(string $code, string $name): string
    {
        $hello = $name !== '' ? 'Chào ' . e($name) . ',' : 'Chào bạn,';
        $mins  = (int) (Otp::TTL / 60);
        $code  = e($code);

        return <<<HTML
        <div style="font-family:system-ui,-apple-system,'Segoe UI',sans-serif;
                    font-size:15px;line-height:1.7;color:#1a1214;max-width:520px">
          <p style="font-family:Georgia,serif;font-size:22px;margin:0 0 18px">Vin Eyewear</p>
          <p>{$hello}</p>
          <p>Mã xác minh để đặt lại mật khẩu của bạn là:</p>
          <p style="margin:24px 0;font-size:34px;font-weight:700;letter-spacing:10px;
                    color:#801a20">{$code}</p>
          <p style="color:#5c4f52;font-size:13px">
            Mã có hiệu lực trong {$mins} phút và chỉ dùng được một lần.<br>
            Nếu bạn không yêu cầu việc này, hãy bỏ qua email — mật khẩu hiện tại
            của bạn không thay đổi. Đừng đưa mã cho bất kỳ ai, kể cả người tự
            xưng là nhân viên Vin Eyewear.
          </p>
        </div>
        HTML;
    }

    /**
     * Bản chữ thuần đi kèm.
     *
     * Mailer::send() tự rút chữ từ HTML được, nhưng ở đây phần quan trọng nhất
     * là sáu chữ số nằm trong một thẻ có màu và giãn cách — rút tự động dễ ra
     * chuỗi dính liền khó đọc. Sáu số thì viết tay một dòng là xong.
     */
    private static function otpEmailText(string $code): string
    {
        $mins = (int) (Otp::TTL / 60);

        return "Vin Eyewear\n\n"
             . "Mã xác minh để đặt lại mật khẩu của bạn là: {$code}\n\n"
             . "Mã có hiệu lực trong {$mins} phút và chỉ dùng được một lần.\n"
             . "Nếu bạn không yêu cầu việc này, hãy bỏ qua email.\n"
             . "Đừng đưa mã cho bất kỳ ai, kể cả người tự xưng là nhân viên Vin Eyewear.\n";
    }
}
