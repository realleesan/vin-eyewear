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

    /** Nhớ trong bao lâu (giây) — 30 ngày. */
    public const LIFETIME = 2592000;

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
                    'exp' => date('Y-m-d H:i:s', time() + self::LIFETIME),
                    'ua'  => utf8Substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
                ]
            );
        } catch (Throwable) {
            return false;
        }

        self::setCookie($selector . ':' . $validator, time() + self::LIFETIME);

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
