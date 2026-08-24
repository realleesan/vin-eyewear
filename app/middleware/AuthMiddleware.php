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
 * HAI KHU VỰC, HAI LOẠI TÀI KHOẢN, KHÔNG BẮC CẦU
 *
 * Một tài khoản chỉ thuộc về ĐÚNG MỘT khu vực, quyết định bởi vai trò trong
 * bảng user_roles:
 *
 *   vai trò nội bộ (staff · manager · admin)  ->  CHỈ khu quản trị  /quan-tri
 *   vai trò khách  (customer, hoặc không có)  ->  CHỈ khu khách     /tai-khoan
 *
 * admin@vineyewear.vn KHÔNG đăng nhập được ở /auth, và tài khoản khách KHÔNG
 * đăng nhập được ở /quan-tri/dang-nhap. Hai chiều đều bị chặn ở TẦNG MÁY CHỦ,
 * không phải bằng cách giấu nút.
 *
 * VÌ SAO PHẢI TÁCH, chứ không để "ai cũng vào được cả hai":
 *
 *   · Mật khẩu mở khu quản trị mà đặt lại được qua luồng "Quên mật khẩu" của
 *     khách (email/OTP) thì cửa sau bên khách chính là cửa trước bên quản trị.
 *     Xem PasswordResetModel::requestOtp.
 *   · Cookie "ghi nhớ đăng nhập" sống 30 ngày là thứ hợp lý cho khách mua
 *     hàng, và là thứ không nên tồn tại cho một tài khoản mở được kho hàng
 *     và dữ liệu đơn thuốc kính. Cổng quản trị cố tình không cấp nó.
 *   · Đăng nhập bằng Google: SRS mục 3.A ghi rõ "Không áp dụng cho tài khoản
 *     nội bộ". Không chặn thì ai chiếm được hộp thư nội bộ là vào thẳng.
 *
 * BA CỬA VÀO, dùng đúng cửa:
 *
 *   userId()      danh tính thô của phiên, CHƯA phân khu. Chỉ dùng ở nơi tự
 *                 kiểm vai trò ngay sau đó (cổng quản trị, chính lớp này).
 *   customerId()  id khách hàng — trả null nếu phiên là tài khoản nội bộ.
 *                 Mọi mã phía cửa hàng dùng cái này.
 *   requireStaff() cửa của khu quản trị.
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

        /*
         * COOKIE GHI NHỚ LÀ CƠ CHẾ CỦA RIÊNG KHU KHÁCH HÀNG.
         *
         * Cổng quản trị không bao giờ cấp token này — AdminAuthController
         * ::login() gọi login() không kèm $remember, có ghi rõ lý do ở đó.
         * Nên một token trỏ tới tài khoản nội bộ chỉ có thể là di sản: cấp
         * hồi tài khoản đó còn đăng nhập được ở /auth, trước khi hai khu vực
         * bị tách.
         *
         * Huỷ SẠCH token của tài khoản ấy chứ không chỉ bỏ qua lượt này:
         * consume() vừa xoay token xong, bỏ qua thôi thì lần sau vào trang
         * lại đúng cảnh này. Người đó đăng nhập lại ở /quan-tri/dang-nhap.
         */
        if (UserModel::isStaff($userId)) {
            RememberModel::forgetAllFor($userId);
            RememberModel::forget();

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

    /**
     * Id KHÁCH HÀNG đang đăng nhập, hoặc null.
     *
     * KHÁC userId(): tài khoản nội bộ trả về null, tức là với toàn bộ mã phía
     * cửa hàng thì một người đang mở khu quản trị chỉ là khách vãng lai. Đó
     * là điều đúng — họ không có giỏ hàng, không có đơn hàng, không có lịch
     * hẹn ở tư cách ấy, và một đơn hàng gắn nhầm vào tài khoản admin là dữ
     * liệu bẩn không ai gỡ ra được.
     *
     * Vai trò đọc lại từ DB mỗi lượt chứ không cất vào phiên: cùng lý do đã
     * ghi ở requireStaff().
     */
    public static function customerId(): ?string
    {
        $userId = self::userId();

        if ($userId === null || UserModel::isStaff($userId)) {
            return null;
        }

        return $userId;
    }

    /**
     * Phiên hiện tại có phải tài khoản nội bộ không.
     *
     * Dùng cho GIAO DIỆN — ví dụ icon tài khoản ở header trỏ về /quan-tri
     * thay vì /tai-khoan. Không được dùng thay cho requireStaff(): đây là câu
     * hỏi "hiện ra cái gì", không phải "cho vào hay không".
     */
    public static function isStaffSession(): bool
    {
        $userId = self::userId();

        return $userId !== null && UserModel::isStaff($userId);
    }

    /**
     * Có KHÁCH HÀNG nào đang đăng nhập không.
     *
     * Tài khoản nội bộ KHÔNG tính — xem customerId().
     */
    public static function check(): bool
    {
        return self::customerId() !== null;
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
     *
     * ĐÂY LÀ CỬA CỦA KHU KHÁCH HÀNG. Tài khoản nội bộ không đi qua được, kể
     * cả khi phiên của họ hoàn toàn hợp lệ — xem khối "HAI KHU VỰC" đầu file.
     */
    public static function requireLogin(?string $returnTo = null): string
    {
        $userId = self::userId();

        if ($userId === null) {
            redirect('/auth?redirect=' . rawurlencode($returnTo ?? currentPath()));
        }

        /*
         * TÀI KHOẢN NỘI BỘ ĐI NHẦM SANG KHU KHÁCH.
         *
         * Đưa về /quan-tri kèm một dòng giải thích, KHÔNG trả 403 và cũng
         * không đá sang /auth:
         *
         *   · 403 nói "bạn không đủ quyền", mà sự thật ngược lại — họ thừa
         *     quyền, chỉ là đứng nhầm cửa. Câu đó làm người đọc đi tìm xem
         *     mình thiếu quyền gì.
         *   · Đá sang /auth thì họ thấy form đăng nhập trong khi ĐANG đăng
         *     nhập, và có gõ đúng mật khẩu nội bộ vào đó cũng bị từ chối —
         *     một vòng tròn không có lối ra.
         *
         * Muốn mua hàng bằng tài khoản riêng thì đăng xuất rồi đăng nhập lại
         * bằng tài khoản khách; đó là hai tài khoản khác nhau, đúng như luật.
         */
        if (UserModel::isStaff($userId)) {
            flash('admin_error',
                  'Tài khoản nội bộ không dùng được khu vực tài khoản khách hàng. '
                  . 'Đăng xuất rồi đăng nhập bằng tài khoản khách nếu bạn cần mua hàng.');
            redirect('/quan-tri');
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
        $userId = self::userId();

        /*
         * CHƯA ĐĂNG NHẬP THÌ VỀ CỔNG QUẢN TRỊ, KHÔNG PHẢI /auth.
         *
         * Trước đây dòng này gọi requireLogin(), tức là ném nhân viên sang
         * trang đăng nhập của KHÁCH: nền be, nút "Tạo tài khoản", nút "Đăng
         * nhập bằng Google". Không có gì trên màn hình đó nói rằng họ đang
         * bước vào khu quản trị, và cái nút tạo tài khoản thì gợi ý sai hẳn —
         * tài khoản quản trị không tự đăng ký được.
         *
         * Nay có cửa riêng: /quan-tri/dang-nhap, dựng theo "Admin Login.dc.html".
         *
         * MANG THEO CẢ QUERY STRING, khác requireLogin() vốn cắt nó bằng
         * currentPath(). Trong khu quản trị thì query THƯỜNG LÀ chỗ khách
         * muốn tới: /quan-tri/don-hang?trang-thai=cho-xac-nhan là một tab
         * riêng, /quan-tri/san-pham?sua=<id> là một biểu mẫu đang mở. Cắt đi
         * là đăng nhập xong rơi về danh sách trống, phải tìm lại từ đầu.
         *
         * Vẫn đi qua safeRedirectPath() ở đầu bên kia — xem
         * AdminAuthController::target(), nơi còn siết thêm là chỉ nhận đường
         * dẫn nằm trong /quan-tri.
         */
        if ($userId === null) {
            redirect('/quan-tri/dang-nhap?redirect=' . rawurlencode(
                currentUrlWithout([])
            ));
        }

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
     *
     * KHÔNG đi qua requireLogin() nữa: hàm đó nay là cửa của khu KHÁCH và đá
     * mọi tài khoản nội bộ về /quan-tri, nên requireRole('admin') gọi qua nó
     * sẽ chặn đúng người mà nó định cho vào. Bẫy đó chưa nổ vì hiện chưa nơi
     * nào gọi requireRole(); sửa luôn để nơi gọi đầu tiên không phải là người
     * phát hiện ra.
     */
    public static function requireRole(string $role): string
    {
        $userId = self::userId();

        if ($userId === null) {
            redirect('/quan-tri/dang-nhap?redirect=' . rawurlencode(currentUrlWithout([])));
        }

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
