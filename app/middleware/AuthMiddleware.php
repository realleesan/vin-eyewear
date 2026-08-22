<?php

/**
 * AuthMiddleware — chặn truy cập theo trạng thái đăng nhập và vai trò.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ NƠI THAY THẾ ROW LEVEL SECURITY CỦA POSTGRES
 *
 * Bản Supabase để DB tự chặn: policy "staff orders", "admin products"… kiểm
 * vai trò ngay trong từng câu lệnh. MySQL không có cơ chế đó, nên mọi luật ấy
 * dồn về đây và về điều kiện user_id trong các Model.
 *
 * Hệ quả: QUÊN gọi middleware ở một controller quản trị nghĩa là trang đó mở
 * cho tất cả mọi người. Xem bảng đối chiếu ở cuối database/schema.sql.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class AuthMiddleware
{
    /** Đã thử khôi phục từ cookie "ghi nhớ" trong request này chưa. */
    private static bool $rememberChecked = false;

    /**
     * Id người đang đăng nhập, hoặc null.
     *
     * Không có phiên thì thử cookie "ghi nhớ đăng nhập" MỘT lần cho mỗi
     * request. Đây là chỗ duy nhất đọc cookie đó, nên mọi đường vào —
     * check(), requireLogin(), requireStaff() — đều tự hưởng.
     */
    public static function userId(): ?string
    {
        if (isset($_SESSION['user_id'])) {
            return $_SESSION['user_id'];
        }

        if (self::$rememberChecked) {
            return null;
        }

        self::$rememberChecked = true;

        // Không có cookie thì thôi, khỏi đụng tới cơ sở dữ liệu. Đại đa số
        // lượt truy cập là khách vãng lai không có cookie này.
        if (!isset($_COOKIE[RememberModel::COOKIE])) {
            return null;
        }

        $userId = RememberModel::consume();

        if ($userId === null) {
            return null;
        }

        // Đăng nhập lại từ cookie KHÔNG cấp phiên "mới tinh": đánh dấu
        // via_cookie để những thao tác nhạy cảm (đổi mật khẩu, đổi email) có
        // thể yêu cầu nhập lại mật khẩu nếu sau này cần siết thêm.
        session_regenerate_id(true);
        $_SESSION['user_id']    = $userId;
        $_SESSION['logged_at']  = time();
        $_SESSION['via_cookie'] = true;

        // Dọn token chết, thưa thớt thôi — 1/50 lượt khôi phục là đủ để bảng
        // không phình, mà không biến mỗi lần vào trang thành một lệnh DELETE.
        if (random_int(1, 50) === 1) {
            RememberModel::purgeExpired();
        }

        return $userId;
    }

    public static function check(): bool
    {
        return self::userId() !== null;
    }

    /**
     * Bắt buộc đăng nhập. Chưa đăng nhập thì đưa về /auth kèm địa chỉ đang
     * muốn tới, để đăng nhập xong quay lại đúng chỗ.
     *
     * $returnTo ĐỂ TRỐNG thì lấy đường dẫn hiện tại — mà currentPath() CẮT
     * QUERY STRING. Với hầu hết trang thì đúng như vậy là tốt: query của
     * /san-pham là bộ lọc, mang qua màn đăng nhập rồi trả về chỉ tổ dài dòng.
     * Nhưng có những trang mà query CHÍNH LÀ chỗ khách muốn tới — /tai-khoan
     * dùng ?muc= để chọn mục — và ở đó bỏ query đi nghĩa là đăng nhập xong họ
     * rơi vào mục mặc định chứ không phải mục vừa bấm.
     *
     * Nên nơi gọi tự khai đường quay lại khi nó biết rõ hơn. Giá trị vẫn đi
     * qua safeRedirectPath() ở đầu bên kia (xem AuthController::loginTarget),
     * nên một chuỗi dẫn ra ngoài site không bao giờ thành đích thật.
     */
    public static function requireLogin(?string $returnTo = null): string
    {
        $userId = self::userId();

        if ($userId === null) {
            redirect('/auth?redirect=' . rawurlencode($returnTo ?? currentPath()));
        }

        return $userId;
    }

    /**
     * Bắt buộc có quyền vào khu quản trị.
     *
     * Vai trò đọc lại từ DB mỗi lần, KHÔNG lấy từ session: session sống hàng
     * tuần, nên người vừa bị gỡ quyền vẫn giữ quyền tới khi tự đăng xuất.
     */
    public static function requireStaff(): string
    {
        $userId = self::requireLogin();

        if (!UserModel::isStaff($userId)) {
            // 403 chứ không 404: người dùng ĐÃ đăng nhập, nói rõ là không đủ
            // quyền thì hữu ích hơn là giả vờ trang không tồn tại.
            http_response_code(403);
            (new ErrorController())->forbidden();
            exit;
        }

        return $userId;
    }

    /**
     * Bắt buộc một vai trò cụ thể (admin, manager…).
     */
    public static function requireRole(string $role): string
    {
        $userId = self::requireLogin();

        if (!UserModel::hasRole($userId, $role)) {
            http_response_code(403);
            (new ErrorController())->forbidden();
            exit;
        }

        return $userId;
    }

    /**
     * Ghi nhận đăng nhập thành công.
     *
     * session_regenerate_id() là bắt buộc, không phải phòng xa: kẻ tấn công
     * có thể ép nạn nhân dùng một mã phiên do hắn biết trước (session
     * fixation), rồi sau khi nạn nhân đăng nhập thì dùng chính mã đó để vào.
     * Cấp mã phiên mới ngay lúc đổi quyền sẽ vô hiệu hoá mã cũ.
     */
    public static function login(string $userId, bool $remember = false): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id']    = $userId;
        $_SESSION['logged_at']  = time();
        unset($_SESSION['via_cookie']);

        if ($remember) {
            RememberModel::issue($userId);
        }
    }

    /**
     * Đăng xuất: xoá sạch dữ liệu phiên và huỷ cookie.
     */
    public static function logout(): void
    {
        // Huỷ luôn token "ghi nhớ" của máy này. Bỏ qua bước này thì bấm Đăng
        // xuất xong tải lại trang là đăng nhập lại ngay — đúng cái người dùng
        // vừa cố tránh, nhất là trên máy dùng chung.
        RememberModel::forget();

        // Giữ lại giỏ hàng: khách đăng xuất trên máy chung vẫn nên mất phiên,
        // nhưng giỏ đang chọn dở thì không có lý do gì phải xoá.
        $cart = $_SESSION['cart'] ?? null;

        $_SESSION = [];

        // Xoá cả cookie phiên, nếu không trình duyệt vẫn gửi mã cũ lên
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        session_start();
        session_regenerate_id(true);

        if ($cart !== null) {
            $_SESSION['cart'] = $cart;
        }
    }
}
