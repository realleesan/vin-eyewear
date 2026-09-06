<?php

/**
 * RememberModel — token "ghi nhớ đăng nhập" (bảng remember_tokens).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO TÁCH LÀM HAI PHẦN
 *
 * Cookie chứa "selector:validator".
 *
 *   selector  32 ký tự, lưu NGUYÊN VĂN, có chỉ mục duy nhất. Chỉ để TRA CỨU.
 *   validator 64 ký tự, chỉ lưu BĂM sha256. Đây mới là bí mật.
 *
 * Nếu chỉ có một chuỗi rồi lưu băm của nó, muốn tra phải đọc từng dòng ra mà
 * băm lại để so — bảng vài nghìn dòng là mỗi lần vào trang quét cả bảng.
 * Nếu lưu nguyên văn cả hai, ai đọc trộm được bảng là đăng nhập được bằng
 * mọi tài khoản, y như lưu mật khẩu dạng thô.
 *
 * Cách này lấy được cả hai: tra bằng một phép so khớp có chỉ mục, mà nội dung
 * bảng thì vô dụng với kẻ đọc trộm.
 *
 * XOAY TOKEN: mỗi lần dùng cookie để đăng nhập, dòng cũ bị xoá và cấp dòng
 * mới. Ai đó chép được cookie thì cũng chỉ dùng được tới lần đăng nhập kế
 * tiếp của chủ máy — sau đó token trong tay họ đã chết.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class RememberModel extends BaseModel
{
    protected static string $table = 'remember_tokens';

    /** Tên cookie. Khác session_name để hai thứ không giẫm lên nhau. */
    public const COOKIE = 'vin_remember';

    /**
     * Hạn LƯU TRỮ của một dòng token — 10 năm. KHÔNG phải chính sách phiên.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * GHI NHỚ ĐĂNG NHẬP KHÔNG CÒN HẾT HẠN THEO THỜI GIAN — FR-TK-08
     *
     * Trước bản này là 7 ngày, lấy theo SNFR-10 cũ. Khách tích ô "ghi nhớ" rồi
     * một tuần sau vẫn bị hỏi lại mật khẩu — mà cả ý nghĩa của cái ô ấy là để
     * không bị hỏi. Người dùng đọc ra là "trang này hay đăng xuất tôi", và cách
     * họ đối phó là đặt mật khẩu dễ nhớ hơn.
     *
     * SRS v2.1.0 chốt lại: thiết bị đã tích ô giữ trạng thái đăng nhập cho tới
     * khi xảy ra MỘT TRONG BỐN việc, và cả bốn đều là hành động chứ không phải
     * thời gian:
     *
     *   · khách tự đăng xuất          AuthMiddleware::logout → forget()
     *   · khách đổi mật khẩu          UserModel::changePassword → forgetAllFor()
     *   · tài khoản bị khoá hoặc xoá  CustomerModel::lock/softDelete/khachTuXoa
     *   · quản trị viên đặt lại MK    PasswordResetModel + AccountAdminController
     *
     * Cộng thêm hai đường cắt vốn đã có và KHÔNG đổi: token xoay sau mỗi lần
     * dùng, và cả chùm bị huỷ ngay khi phát hiện dấu hiệu đánh cắp (một
     * validator sai với selector đúng — xem consume()).
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VẬY VÌ SAO CÒN MỘT CON SỐ Ở ĐÂY
     *
     * Vì `expires_at` là NOT NULL, và vì một bảng chỉ-thêm không có ai dọn sẽ
     * phình mãi: mỗi lần đăng nhập trên một máy mới là một dòng, và những máy
     * người ta không bao giờ dùng lại thì không có sự kiện nào ở trên chạm tới.
     *
     * 10 năm là RANH GIỚI DỌN RÁC, không phải một lời hứa với người dùng. Đừng
     * rút nó xuống để "tăng bảo mật" — làm thế là lặng lẽ dựng lại đúng cái hạn
     * mà yêu cầu này vừa bỏ. Muốn siết bảo mật thì thêm một đường CẮT (một sự
     * kiện), không phải một cái đồng hồ.
     *
     * Trình duyệt cũng có tiếng nói: theo chuẩn hiện hành, cookie sống tối đa
     * khoảng 400 ngày dù ta khai bao nhiêu. Nghĩa là thực tế khách vẫn phải
     * đăng nhập lại sau chừng ấy — nhưng đó là luật của trình duyệt, và ta
     * không thêm một cái hạn thứ hai chặt hơn ở phía mình.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public const HAN_LUU_TRU = 315360000;

    /**
     * Tính năng này chỉ chạy khi bảng đã có.
     *
     * Cho phép triển khai mã nguồn trước, chạy file nâng cấp cơ sở dữ liệu
     * sau, mà site không lỗi ở khoảng giữa.
     */
    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /**
     * Cấp token mới cho một tài khoản và đặt cookie.
     */
    public static function issue(string $userId): bool
    {
        if (!self::available()) {
            return false;
        }

        $selector  = bin2hex(random_bytes(16));   // 32 ký tự hex
        $validator = bin2hex(random_bytes(32));   // 64 ký tự hex

        try {
            Database::execute(
                'INSERT INTO remember_tokens (id, user_id, selector, validator, expires_at, user_agent)
                 VALUES (:id, :uid, :sel, :val, :exp, :ua)',
                [
                    'id'  => uuid(),
                    'uid' => $userId,
                    'sel' => $selector,
                    'val' => hash('sha256', $validator),
                    'exp' => date('Y-m-d H:i:s', time() + self::HAN_LUU_TRU),
                    'ua'  => utf8Substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
                ]
            );
        } catch (Throwable) {
            return false;
        }

        self::setCookie($selector . ':' . $validator, time() + self::HAN_LUU_TRU);

        return true;
    }

    /**
     * Đọc cookie, xác thực, và nếu hợp lệ thì XOAY sang token mới.
     *
     * @return string|null user_id, hoặc null nếu không có/không hợp lệ/hết hạn
     */
    public static function consume(): ?string
    {
        if (!self::available()) {
            return null;
        }

        $raw = (string) ($_COOKIE[self::COOKIE] ?? '');

        if ($raw === '' || !str_contains($raw, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $raw, 2);

        // Định dạng phải khớp trước khi đụng tới cơ sở dữ liệu
        if (preg_match('/^[a-f0-9]{32}$/', $selector) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $validator) !== 1) {
            self::clearCookie();

            return null;
        }

        try {
            $row = Database::fetchOne(
                'SELECT id, user_id, validator, expires_at
                   FROM remember_tokens
                  WHERE selector = :sel',
                ['sel' => $selector]
            );
        } catch (Throwable) {
            return null;
        }

        if ($row === null) {
            self::clearCookie();

            return null;
        }

        /* PHÉP KIỂM HẠN VẪN CÒN, và vẫn phải còn — FR-TK-08 bỏ cái HẠN NGẮN,
           không bỏ việc tôn trọng cột `expires_at`.

           Dòng mới sinh ra với hạn 10 năm nên nhánh này gần như không bao giờ
           chạy. Nhưng những dòng tạo TRƯỚC bản này mang hạn 7 ngày thật, và
           chúng phải chết đúng hạn đã ghi: một token đã hết hạn mà nay bỗng
           dùng lại được là nới quyền cho những cookie cũ đang nằm ở đâu đó. */
        if (strtotime((string) $row['expires_at']) < time()) {
            self::forgetById((string) $row['id']);
            self::clearCookie();

            return null;
        }

        // hash_equals: so sánh trong thời gian không đổi. So bằng '===' sẽ
        // dừng ở byte đầu tiên khác nhau, và chênh lệch thời gian đó đủ để
        // dò dần từng ký tự của validator.
        if (!hash_equals((string) $row['validator'], hash('sha256', $validator))) {
            // Selector đúng mà validator sai = có người đang thử token trộm.
            // Huỷ luôn dòng này: chủ máy sẽ phải đăng nhập lại, còn hơn để
            // kẻ kia tiếp tục dò.
            self::forgetById((string) $row['id']);
            self::clearCookie();

            return null;
        }

        $userId = (string) $row['user_id'];

        /* CỬA VÀO THỨ BA — cookie này không đi qua UserModel::attempt() lẫn
           ::findOrCreateGoogle() một dòng nào.

           CustomerModel::lock() và ::softDelete() đã gọi forgetAllFor() nên
           token cũ chết ngay lúc bấm nút; kiểm thêm ở đây là để phòng đường
           khác — ai đó sửa thẳng cột `status` bằng phpMyAdmin chẳng hạn, đúng
           việc đã phải làm ngày 2026-08-23 để lấy lại quyền trên hosting.

           Huỷ luôn token thay vì chỉ từ chối: để nó sống thì mỗi lượt tải
           trang lại tra cơ sở dữ liệu một lần cho một cookie không bao giờ
           dùng được nữa. */
        if (!UserModel::coTheDangNhap($userId)) {
            self::forgetById((string) $row['id']);
            self::clearCookie();

            return null;
        }

        // Xoay: dùng xong là bỏ, cấp cái mới
        self::forgetById((string) $row['id']);
        self::issue($userId);

        return $userId;
    }

    /**
     * Xoá token của phiên hiện tại (gọi khi đăng xuất).
     */
    public static function forget(): void
    {
        $raw = (string) ($_COOKIE[self::COOKIE] ?? '');

        if ($raw !== '' && str_contains($raw, ':') && self::available()) {
            [$selector] = explode(':', $raw, 2);

            if (preg_match('/^[a-f0-9]{32}$/', $selector) === 1) {
                try {
                    Database::execute('DELETE FROM remember_tokens WHERE selector = :s', ['s' => $selector]);
                } catch (Throwable) {
                    // Không xoá được thì cookie vẫn bị gỡ ở dưới; token thừa
                    // trong bảng sẽ tự hết hạn.
                }
            }
        }

        self::clearCookie();
    }

    /**
     * Xoá MỌI token của một tài khoản — dùng khi đổi mật khẩu hoặc đặt lại
     * mật khẩu. Đổi mật khẩu mà các máy khác vẫn đăng nhập được bằng cookie cũ
     * thì việc đổi gần như vô nghĩa: người dùng đổi chính là vì nghi bị lộ.
     */
    public static function forgetAllFor(string $userId): void
    {
        if (!self::available()) {
            return;
        }

        try {
            Database::execute('DELETE FROM remember_tokens WHERE user_id = :u', ['u' => $userId]);
        } catch (Throwable) {
            // bỏ qua
        }
    }

    /**
     * Dọn token đã hết hạn. Gọi thưa thớt, xem AuthMiddleware.
     */
    public static function purgeExpired(): void
    {
        if (!self::available()) {
            return;
        }

        try {
            Database::execute('DELETE FROM remember_tokens WHERE expires_at < NOW()');
        } catch (Throwable) {
            // bỏ qua
        }
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    private static function forgetById(string $id): void
    {
        try {
            Database::execute('DELETE FROM remember_tokens WHERE id = :id', ['id' => $id]);
        } catch (Throwable) {
            // bỏ qua
        }
    }

    private static function setCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            // JavaScript không đọc được: một lỗi XSS thì cũng không lấy được
            // token đăng nhập vĩnh viễn mang đi máy khác.
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[self::COOKIE] = $value;
    }

    private static function clearCookie(): void
    {
        unset($_COOKIE[self::COOKIE]);

        if (!headers_sent()) {
            setcookie(self::COOKIE, '', [
                'expires'  => time() - 42000,
                'path'     => '/',
                'domain'   => '',
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
