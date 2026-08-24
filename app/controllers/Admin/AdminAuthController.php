<?php

/**
 * Admin/AdminAuthController.php — cổng đăng nhập khu quản trị.
 *
 *   GET  /quan-tri/dang-nhap            index()   màn hình
 *   POST /quan-tri/dang-nhap/xac-thuc   login()   kiểm và cho vào
 *   POST /quan-tri/dang-xuat            logout()  ra khỏi khu quản trị
 *
 * Giao diện: app/views/admin/login.php (theo "Admin Login.dc.html")
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KẾ THỪA BaseController, KHÔNG PHẢI AdminController — BẮT BUỘC PHẢI VẬY
 *
 * AdminController gọi AuthMiddleware::requireStaff() ngay trong constructor,
 * và requireStaff() đá người chưa đăng nhập về CHÍNH TRANG NÀY. Cho lớp này
 * kế thừa nó là dựng một vòng lặp chuyển hướng vô tận: vào /quan-tri/dang-nhap
 * -> chưa đăng nhập -> đá về /quan-tri/dang-nhap -> …
 *
 * Đây là controller DUY NHẤT trong thư mục Admin/ không được kế thừa
 * AdminController. Nó nằm ở đây vì thuộc về khu quản trị về mặt ý nghĩa, còn
 * về mặt quyền thì nó phải mở cho người chưa đăng nhập — đó chính là việc của
 * nó.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class AdminAuthController extends BaseController
{
    /** Đích mặc định sau khi vào được. */
    private const HOME_AFTER_LOGIN = '/quan-tri';

    /**
     * Đường quay lại sau khi đăng nhập.
     *
     * CHỈ NHẬN ĐƯỜNG TRONG KHU QUẢN TRỊ. safeRedirectPath() đã chặn đường dẫn
     * ra ngoài site, nhưng ở đây còn siết thêm một tầng: ?redirect=/tai-khoan
     * gõ tay sẽ đưa người vừa qua cổng quản trị sang trang khách hàng, một
     * kết cục vô nghĩa với cánh cửa họ vừa bước qua.
     */
    private function target(?string $raw): string
    {
        $to = safeRedirectPath($raw, self::HOME_AFTER_LOGIN);

        return str_starts_with($to, '/quan-tri') ? $to : self::HOME_AFTER_LOGIN;
    }

    public function index(): void
    {
        /*
         * ĐÃ LÀ NHÂN VIÊN ĐANG ĐĂNG NHẬP thì vào thẳng, không bày lại form.
         *
         * Chỉ bỏ qua khi người đó THẬT SỰ có quyền. Khách hàng đang đăng nhập
         * mà mò tới đây thì vẫn thấy form: họ cần một chỗ để đăng nhập lại
         * bằng tài khoản nội bộ, chứ đá thẳng sang /quan-tri chỉ để nhận 403
         * là một ngõ cụt không lối ra.
         */
        $userId = AuthMiddleware::userId();

        if ($userId !== null && UserModel::isStaff($userId)) {
            redirect($this->target($_GET['redirect'] ?? null));
        }

        $this->renderView('admin/login', [
            // Khung rút gọn, nhưng thay CẢ đầu lẫn chân trang bằng bản nền
            // tối của khu quản trị — xem $bareHeader/$bareFooter trong
            // _layout/master.php.
            'bareLayout' => true,
            'bareHeader' => '_layout/admin-login-header',
            'bareFooter' => '_layout/admin-login-footer',

            'pageTitle'  => 'Cổng quản trị — Vin Eyewear',

            /*
             * noindex CHO TRANG NÀY.
             *
             * Không có gì ở đây đáng nằm trong kết quả tìm kiếm, và một cánh
             * cửa quản trị được Google lập chỉ mục là lời mời cho mọi công cụ
             * dò mật khẩu tự động. Trang /auth của khách thì ngược lại — nó
             * là một trang bình thường của cửa hàng.
             */
            'noindex'    => true,

            'redirect'   => $this->target($_GET['redirect'] ?? null),
            'error'      => flash('admin_auth_error'),
            'old'        => $_SESSION['_old_admin_auth'] ?? [],

            /*
             * "QUÊN MẬT KHẨU?" Ở CỔNG NÀY KHÔNG DẪN ĐI ĐÂU ĐƯỢC — VÀ PHẢI
             * NÓI THẲNG NHƯ VẬY.
             *
             * Liên kết đó trước đây trỏ sang /quen-mat-khau, tức là luồng OTP
             * CỦA KHÁCH. Từ khi hai khu vực bị tách (xem khối "HAI KHU VỰC"
             * đầu AuthMiddleware), luồng ấy cố tình coi mọi email nội bộ là
             * "không khớp tài khoản nào": gõ vào cũng nhận mã, nhập mã cũng
             * báo sai, thử bao nhiêu lần cũng thế. Một cánh cửa vẽ lên tường.
             *
             * Nay bấm vào là hiện ngay tại chỗ ba đường đi thật, xếp theo thứ
             * tự nên thử. Mở bằng ?quen=1 chứ không bằng JavaScript: cổng này
             * phải chạy được cả khi JS tắt, và một tham số trên URL thì gửi
             * cho đồng nghiệp qua tin nhắn được.
             */
            'showHelp'   => isset($_GET['quen']),
        ]);

        unset($_SESSION['_old_admin_auth']);
    }

    public function login(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            redirect('/quan-tri/dang-nhap');
        }

        $to = $this->target($_POST['redirect'] ?? null);

        if (!csrfCheck($_POST['_token'] ?? null)) {
            http_response_code(419);
            $this->fail('Phiên làm việc đã hết hạn — vui lòng thử lại.', '', $to);
        }

        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $result = UserModel::attempt($email, $password);

        /*
         * ─────────────────────────────────────────────────────────────────
         * MỘT CÂU BÁO LỖI DUY NHẤT CHO CẢ HAI CA HỎNG
         *
         * Hai ca rất khác nhau đứng sau cùng một câu:
         *
         *   · sai email hoặc sai mật khẩu
         *   · đúng cả hai, nhưng tài khoản KHÔNG có quyền quản trị
         *
         * Tách ra thì câu trả lời của trang biến thành một máy tra cứu: gõ
         * thử một địa chỉ, đọc câu báo, là biết địa chỉ đó có phải tài khoản
         * nội bộ hay không — và biết luôn mật khẩu vừa gõ đúng hay sai. Danh
         * sách nhân viên của cửa hàng lộ ra bằng đúng cái form này.
         *
         * Nhập nhoè đi thì người gõ nhầm mật khẩu vẫn hiểu phải làm gì (thử
         * lại), còn người dò thì không moi được gì.
         *
         * $result['error'] của UserModel CỐ TÌNH BỎ ĐI ở đây, dù nó nói rõ
         * hơn: câu đó viết cho trang đăng nhập của khách, nơi việc phân biệt
         * "chưa có tài khoản" với "sai mật khẩu" là giúp đỡ chứ không phải rò
         * rỉ. Cổng quản trị thì ngược lại.
         * ─────────────────────────────────────────────────────────────────
         */
        $chung = 'Email hoặc mật khẩu không đúng, hoặc tài khoản không có quyền quản trị.';

        if (!$result['ok']) {
            $this->fail($chung, $email, $to);
        }

        /*
         * KIỂM QUYỀN TRƯỚC KHI ĐĂNG NHẬP, không phải sau.
         *
         * Cho vào rồi để AdminController trả 403 cũng chặn được, nhưng nó bỏ
         * lại một phiên đã đăng nhập của một tài khoản khách — người đứng ở
         * cổng quản trị bỗng thành đang-đăng-nhập với tư cách khách hàng, mà
         * họ không hề yêu cầu điều đó. Ai dùng chung máy ở cửa hàng thì đó là
         * một phiên bỏ quên.
         *
         * Không đủ quyền thì KHÔNG có phiên nào được mở.
         */
        if (!UserModel::isStaff($result['id'])) {
            $this->fail($chung, $email, $to);
        }

        AuthMiddleware::login($result['id']);

        /*
         * KHÔNG CÓ Ô "DUY TRÌ ĐĂNG NHẬP".
         *
         * Bản thiết kế không vẽ nó, và với một khu quản trị thì đó là lựa
         * chọn đúng: cookie ghi nhớ sống hai tuần trên đúng cái máy hay được
         * dùng chung ở quầy. AuthMiddleware::login() gọi không kèm $remember
         * nên phiên chết khi đóng trình duyệt.
         */
        redirect($to);
    }

    /**
     * Ra khỏi khu quản trị.
     *
     * ĐƯỜNG RIÊNG, KHÔNG DÙNG CHUNG /auth/dang-xuat CỦA KHÁCH.
     *
     * Việc bên trong đúng là y hệt — cùng gọi AuthMiddleware::logout(). Nhưng
     * để khu quản trị POST sang một địa chỉ mở đầu bằng /auth thì hai khu vực
     * vẫn dính nhau ở đúng chỗ dễ quên nhất:
     *
     *   · Đọc log máy chủ không tách được lượt đăng xuất của nhân viên với
     *     của khách — mà đó là dòng người ta tra đầu tiên khi soát một phiên
     *     đáng ngờ ở quầy.
     *   · Muốn chặn cả khu quản trị ở tầng máy chủ (giới hạn IP trong
     *     .htaccess chẳng hạn) thì luật "mọi đường /quan-tri" bỏ sót đúng cái
     *     nút Đăng xuất.
     *   · Và nếu mai này khu quản trị dời sang tên miền phụ, mỗi đường dùng
     *     chung là một chỗ phải nhớ sửa tay.
     *
     * ĐƯA VỀ CỔNG QUẢN TRỊ, không về trang chủ cửa hàng: người vừa bấm Đăng
     * xuất ở đây gần như chắc chắn sẽ đăng nhập lại bằng tài khoản khác, hoặc
     * đang bàn giao máy cho ca sau.
     */
    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            /* GET không được đăng xuất ai cả: một thẻ <img src="…"> trên
               trang bất kỳ cũng đủ để đá nhân viên ra giữa lúc đang nhập
               liệu. Đây là lý do nút Đăng xuất nằm trong <form method="post">
               chứ không phải một thẻ <a>. */
            http_response_code(405);
            redirect('/quan-tri');
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            http_response_code(419);
            redirect('/quan-tri');
        }

        /*
         * CHỈ ĐÓNG PHIÊN NỘI BỘ.
         *
         * Không có chốt này thì một phiên KHÁCH gửi tới đây cũng bị đóng —
         * thử rồi, nó đóng thật. Hại thì gần như không (đó là phiên của chính
         * người gửi, lại có token CSRF), nhưng nó phá đúng cái luật mà cả
         * commit này dựng lên: mỗi địa chỉ phục vụ ĐÚNG MỘT khu vực. Để hở
         * thì /quan-tri/dang-xuat là một đường của khu quản trị mà lại thao
         * tác được lên tài khoản khách, và người đọc mã sau này có quyền suy
         * ra rằng mấy đường /quan-tri khác cũng vậy.
         *
         * Khách rơi vào đây thì KHÔNG đụng vào phiên của họ — họ có đường ra
         * riêng ở /auth/dang-xuat — chỉ đưa về cổng quản trị, đúng nơi một
         * địa chỉ /quan-tri nên dẫn tới khi người gõ không có quyền.
         */
        if (!AuthMiddleware::isStaffSession()) {
            redirect('/quan-tri/dang-nhap');
        }

        AuthMiddleware::logout();

        redirect('/quan-tri/dang-nhap');
    }

    /**
     * Một lối ra duy nhất cho mọi ca hỏng: nhớ email đã gõ, báo lỗi, quay lại
     * cổng.
     *
     * KHÔNG BAO GIỜ nhớ mật khẩu — kể cả để điền lại cho tiện. Nó sẽ nằm
     * trong $_SESSION, tức là nằm trong một file trên đĩa máy chủ.
     */
    private function fail(string $message, string $email, string $to): never
    {
        $_SESSION['_old_admin_auth'] = ['email' => $email];

        flash('admin_auth_error', $message);
        redirect('/quan-tri/dang-nhap?redirect=' . rawurlencode($to));
    }
}
