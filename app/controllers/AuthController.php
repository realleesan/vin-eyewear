<?php

/**
 * AuthController — đăng nhập, đăng ký, tài khoản (/auth, /tai-khoan).
 *
 * Port từ src/routes/auth.tsx và src/routes/_authenticated/tai-khoan.tsx.
 */

class AuthController extends BaseController
{
    // ========================================================================
    // ĐĂNG NHẬP / ĐĂNG KÝ
    // ========================================================================

    /**
     * Đích đến sau khi đăng nhập, đọc từ tham số `redirect`.
     *
     * ?redirect= chỉ có MỘT công dụng: khách đang muốn tới một trang cần đăng
     * nhập thì AuthMiddleware::requireLogin() đá về /auth kèm địa chỉ đó, và
     * đăng nhập xong ta trả họ về đúng việc đang dở.
     *
     * Trang chủ KHÔNG BAO GIỜ là một địa chỉ như vậy — nó công khai, không ai
     * bị chặn ở đó cả. Nên `redirect=/` chỉ có thể tới từ một liên kết dựng
     * sai, và nghĩa của nó là "đăng nhập xong đi một vòng rồi về đúng chỗ cũ",
     * tức là không tới được tài khoản. Coi nó như không có đích và dùng mặc
     * định /tai-khoan.
     *
     * Đã dính thật: icon tài khoản ở _layout/header.php từng gắn
     * '?redirect=' . currentPath() cho mọi trang, nên bấm nó ở trang chủ là
     * đăng nhập xong quay lại trang chủ. Liên kết đó đã sửa; hàm này giữ để
     * liên kết dựng sai lần sau không tái hiện đúng lỗi ấy.
     */
    private function loginTarget(?string $raw): string
    {
        return safeRedirectPath($raw, self::HOME_AFTER_LOGIN);
    }

    /**
     * Đích mặc định sau khi đăng nhập — BR-UC.USER.02-06.
     *
     * TRANG CHỦ, không phải /tai-khoan. Đặc tả chốt hai ngả và cả hai đều nằm
     * ở đây: vào màn đăng nhập từ một nghiệp vụ đòi đăng nhập thì quay lại
     * đúng nghiệp vụ đó (`redirect` do AuthMiddleware::requireLogin() gắn),
     * còn chủ động vào từ menu tài khoản thì về trang chủ.
     *
     * Bản trước mặc định /tai-khoan, và vì thế phải có thêm một nhánh riêng
     * đổi `redirect=/` thành /tai-khoan: trang chủ công khai nên không ai bị
     * chặn ở đó, một liên kết dựng sai trỏ về '/' là đưa khách đi một vòng
     * rồi về chỗ cũ. Nay '/' CHÍNH LÀ đích mặc định nên nhánh ấy không còn
     * việc gì để làm.
     */
    private const HOME_AFTER_LOGIN = '/';

    public function index(): void
    {
        /* Đã đăng nhập rồi thì không có lý do xem trang này nữa.

           Không còn ngoại lệ nào: luồng đăng ký nay kết thúc bằng một cú
           chuyển hướng theo BR-UC.USER.01-09 kèm dải toast "Đăng ký tài khoản
           thành công!", chứ không phải một màn "xong" trên chính trang này. */
        if (AuthMiddleware::check()) {
            redirect('/tai-khoan');
        }

        $isRegister = ($_GET['tab'] ?? '') === 'dang-ky';

        $this->renderView('auth/index', [
            'signup'    => self::signupView(),
            // Khung rút gọn: không thanh điều hướng, không chân trang đầy đủ.
            // Xem ghi chú $bare trong app/views/_layout/master.php.
            'bareLayout' => true,
            'pageTitle' => $isRegister
                ? 'Tạo tài khoản — Vin Eyewear' : 'Đăng nhập — Vin Eyewear',
            'metaDesc'  => 'Đăng nhập hoặc tạo tài khoản Vin Eyewear để theo dõi đơn hàng '
                         . 'và lịch hẹn của bạn.',
            /* Chỉ nhận đường dẫn nội bộ — xem ghi chú trong safeRedirectPath().
               HAI ĐÍCH MẶC ĐỊNH KHÁC NHAU, đúng BR-UC.USER.01-09: đăng nhập
               xong về /tai-khoan, còn đăng ký xong về TRANG CHỦ. */
            'redirect'  => $isRegister
                ? $this->signupTarget($_GET['redirect'] ?? null)
                : $this->loginTarget($_GET['redirect'] ?? null),
            /* Đích ĐANG MANG THEO, chưa áp mặc định nào — chuỗi rỗng nghĩa là
               khách tự vào đây chứ không bị nghiệp vụ nào đá về. Hai liên kết
               "Đã có tài khoản? / Đăng ký" dùng nó để giữ đích qua lần đổi
               tab; không có nó thì khách bị đá về /auth từ giỏ hàng, bấm sang
               tab đăng ký, và đăng ký xong lạc mất chỗ đang dở. */
            'redirectRaw' => safeRedirectPath($_GET['redirect'] ?? null, ''),
            'tab'       => $isRegister ? 'dang-ky' : 'dang-nhap',
            'old'       => $_SESSION['_old_auth'] ?? [],
            // Lỗi theo từng ô của màn đăng ký — xem signupErrors().
            'errors'    => $_SESSION['_auth_errors'] ?? [],
            'error'     => flash('auth_error'),
            'success'   => flash('auth_success'),

            /* Cờ riêng, KHÔNG dùng chung ô $error.
               Ca này không phải lỗi — mật khẩu gõ đúng, chỉ là đứng nhầm
               cổng — nên nó cần một khối riêng có chỗ đặt liên kết sang cổng
               quản trị. Nhét đường dẫn vào giữa một câu chữ đỏ thì người đọc
               phải tự bôi đen rồi chép sang thanh địa chỉ. */
            'staffGate' => flash('auth_staff_gate') !== null,
        ]);

        /* Xoá SAU khi vẽ xong, đúng nếp của flash(): hai ô này chỉ sống đúng
           một lần hiện trang, tải lại là form sạch trở lại. */
        unset($_SESSION['_old_auth'], $_SESSION['_auth_errors']);
    }

    public function login(): void
    {
        $this->requirePost('/auth');

        // Ô này nhận CẢ email lẫn số điện thoại — xem UserModel::findByLogin.
        // Tên trường vẫn là 'email' để trình quản lý mật khẩu đã lưu của
        // khách cũ tiếp tục điền đúng ô.
        $login    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = ($_POST['remember'] ?? '') !== '';
        $to       = $this->loginTarget($_POST['redirect'] ?? null);

        /* Nhớ chuỗi đã gõ để khách không phải gõ lại — nhưng KHÔNG nhớ mật
           khẩu (BR-UC.USER.02-02: không lưu mật khẩu thô ở bất kỳ bước nào),
           và nhớ luôn trạng thái ô "Ghi nhớ đăng nhập". */
        $_SESSION['_old_auth'] = ['email' => $login, 'remember' => $remember];

        /*
         * ─────────────────────────────────────────────────────────────────
         * HAI TẦNG BÁO LỖI, VÀ RANH GIỚI GIỮA CHÚNG LÀ RANH GIỚI BẢO MẬT
         *
         *   THEO TỪNG Ô (EF-01…EF-04) — những thứ nhìn vào chuỗi đã gõ là
         *   biết, không cần chạm tới CSDL: bỏ trống, số điện thoại sai định
         *   dạng, email sai định dạng. Nói thẳng và chỉ đúng ô cần sửa thì
         *   không rò rỉ gì cả, vì câu trả lời không phụ thuộc vào việc tài
         *   khoản có tồn tại hay không.
         *
         *   DẢI BANNER (EF-05…EF-07) — những thứ chỉ CSDL mới trả lời được.
         *   Ở đó BR-UC.USER.02-03 bắt phải dùng MỘT câu chung cho cả "không
         *   tìm thấy tài khoản" lẫn "sai mật khẩu", và không gắn vào ô nào:
         *   gắn câu "sai mật khẩu" vào riêng ô mật khẩu là đã nói rằng ô trên
         *   đúng, tức là địa chỉ đó CÓ tài khoản ở đây.
         * ─────────────────────────────────────────────────────────────────
         */
        $loi = [];

        if ($login === '') {
            $loi['email'] = 'Vui lòng nhập số điện thoại hoặc email.';
        } elseif (looksLikePhone($login)) {
            /* looksLikePhone() phân loại theo HÌNH DẠNG chuỗi (có '@' hay
               không), đúng cách findByLogin() chọn nhánh tra cứu — nhờ vậy
               câu báo ở đây luôn nói về cùng thứ mà máy chủ sắp đi tìm. */
            if (normalizePhone($login) === null) {
                $loi['email'] = 'Số điện thoại không hợp lệ. Vui lòng kiểm tra lại.';
            }
        } elseif (!filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $loi['email'] = 'Email không hợp lệ. Vui lòng kiểm tra lại.';
        }

        if ($password === '') {
            $loi['password'] = 'Vui lòng nhập mật khẩu.';
        }

        if ($loi !== []) {
            /* KHÔNG gọi attempt() khi đã biết chuỗi không thể khớp ai: mỗi
               lượt gọi là một lần băm mật khẩu và một vạch trong bộ đếm khoá
               tạm của LoginAttemptModel. Gõ thiếu một ô năm lần thì không nên
               bị khoá 15 phút. */
            $_SESSION['_auth_errors'] = $loi;
            redirect($this->loginBack($to));
        }

        $result = UserModel::attempt($login, $password);

        if (!$result['ok']) {
            flash('auth_error', $result['error']);
            redirect($this->loginBack($to));
        }

        /*
         * ─────────────────────────────────────────────────────────────────
         * TÀI KHOẢN NỘI BỘ KHÔNG ĐĂNG NHẬP Ở CỬA NÀY
         *
         * Đối xứng với AdminAuthController::login(), nơi tài khoản khách bị
         * từ chối. Xem khối "HAI KHU VỰC" ở đầu AuthMiddleware.
         *
         * CHẶN TRƯỚC KHI MỞ PHIÊN. Cho vào rồi để requireLogin() đá về
         * /quan-tri cũng chặn được đường tới /tai-khoan, nhưng nó bỏ lại một
         * phiên quản trị đang mở trên trang bán hàng — mở ở một cái máy mà
         * người ngồi trước đó chỉ định đăng nhập mua hàng.
         *
         * NÓI THẲNG LÝ DO, KHÔNG NHẬP NHOÈ như bên cổng quản trị.
         *
         * Câu này chỉ hiện ra SAU KHI mật khẩu đã đúng, nên nó không phải một
         * máy tra cứu: ai đọc được nó thì đã cầm sẵn mật khẩu của tài khoản
         * đó rồi, biết thêm "đây là tài khoản nội bộ" cũng không thêm gì.
         * Đổi lại, người gõ nhầm cửa biết ngay phải đi đâu thay vì đứng trước
         * câu "Thông tin đăng nhập không đúng." trong khi họ gõ đúng.
         *
         * Ở cổng quản trị thì ngược hẳn: câu báo bên đó hiện ra cả khi mật
         * khẩu SAI, nên tách bạch là rò rỉ danh sách nhân viên.
         * ─────────────────────────────────────────────────────────────────
         */
        if (UserModel::isStaff($result['id'])) {
            flash('auth_staff_gate', '1');
            redirect($this->loginBack($to));
        }

        unset($_SESSION['_old_auth'], $_SESSION['_auth_errors']);

        /* Bước 7 — phiên đăng nhập. $remember là ô "Ghi nhớ đăng nhập"
           (BR-UC.USER.02-05); AuthMiddleware::login() lo phần cookie dài hạn
           qua RememberModel. */
        AuthMiddleware::login($result['id'], $remember);

        /* Bước 8 và 9. Khoá TRUNG TÍNH 'site_success' để dải toast hiện được
           ở mọi đích của BR-UC.USER.02-06 — kể cả trang chủ, nơi không có dải
           báo riêng của khu tài khoản. Xem BaseController::toastFromFlash(). */
        flash('site_success', 'Đăng nhập thành công!');

        redirect($to);
    }

    /** Màn đăng nhập, giữ nguyên đích đến đang mang theo. */
    private function loginBack(string $to): string
    {
        return '/auth' . ($to !== '/' ? '?redirect=' . rawurlencode($to) : '');
    }

    /*
     * ═════════════════════════════════════════════════════════════════════
     * QUÊN MẬT KHẨU — BỐN CHẶNG BẰNG MÃ OTP
     *
     *   (không có)   nhập email hoặc số điện thoại   forgotSubmit()
     *   ma           nhập 6 số                       forgotVerify() · forgotResend()
     *   mat-khau     đặt mật khẩu mới                forgotFinish()
     *   xong         đổi xong, mời đăng nhập lại
     *
     * KÊNH GỬI CHỌN THEO THỨ KHÁCH GÕ, không hỏi thêm câu nào:
     *
     *   có '@'   -> mã đi bằng email
     *   là số    -> mã đi bằng Zalo
     *
     * Không bày màn "chọn phương thức" như luồng đăng ký, vì ở đây không có gì
     * để chọn: khách gõ email thì ta chỉ biết mỗi email của họ, gõ số thì chỉ
     * biết mỗi số. Bày ra hai nút mà một nút chắc chắn không gửi được là mời
     * người ta bấm vào chỗ hỏng.
     *
     * ZALO CHƯA CẮM NHÀ CUNG CẤP — mã mới chỉ ghi ra error log, xem core/Otp.php.
     * Luồng vẫn chạy đủ bốn chặng để khi cắm ZNS vào thì không phải sửa gì ở
     * đây; chỗ cắm là đúng một hàm Otp::send().
     *
     * LUỒNG NÀY VẪN LÀ NHIỀU CHẶNG — và nay là luồng DUY NHẤT như vậy: đăng ký
     * đã gộp về một màn theo UC-USER-01 (xem khối "ĐĂNG KÝ — MỘT MÀN" bên
     * dưới). Ở đây thì giữ, vì hai chặng của nó phục vụ hai việc khác hẳn nhau
     * — chứng minh quyền sở hữu, rồi mới đặt mật khẩu mới.
     * Bước đang mở nằm trên URL (?buoc=), dữ liệu dở dang nằm trong
     * $_SESSION['_forgot'], mỗi chặng là một POST thật nên tắt JavaScript vẫn
     * chạy và nút Back lùi đúng một chặng. Email/số điện thoại KHÔNG nằm trên
     * URL: nó là dữ liệu cá nhân, mà URL thì đi vào lịch sử duyệt web, vào
     * Referer gửi sang bên thứ ba, và vào log của mọi proxy trên đường.
     *
     * ĐƯỜNG NHÂN VIÊN VẪN CÒN, không đụng tới: reset()/resetSubmit() bên dưới
     * nhận liên kết có token do /quan-tri/quen-mat-khau tạo ra. Nó dành cho ca
     * khách không nhận được mã — mất số, sai email, kênh gửi hỏng.
     * ═════════════════════════════════════════════════════════════════════
     */

    /** Trạng thái luồng quên mật khẩu đang dở, hoặc null nếu chưa bắt đầu. */
    private static function forgotState(): ?array
    {
        $f = $_SESSION['_forgot'] ?? null;

        return is_array($f) && ($f['contact'] ?? '') !== '' ? $f : null;
    }

    /**
     * Bước nào ĐƯỢC PHÉP mở lúc này.
     *
     * ?buoc= gõ tay được, nên mỗi bước phải tự chứng minh nó có cơ sở. Thiếu
     * chốt này thì gõ thẳng /quen-mat-khau?buoc=mat-khau là đặt được mật khẩu
     * mới mà chưa từng nhập mã — tức là chiếm tài khoản của bất kỳ ai.
     */
    private static function forgotStep(): string
    {
        $step  = (string) ($_GET['buoc'] ?? '');
        $state = self::forgotState();

        if ($step === 'xong') {
            return !empty($_SESSION['_forgot_done']) ? 'xong' : '';
        }

        if ($state === null) {
            return '';
        }

        return match ($step) {
            'ma'       => ($state['hash'] ?? '') !== '' ? 'ma' : '',
            'mat-khau' => !empty($state['verified']) ? 'mat-khau' : 'ma',
            default    => '',
        };
    }

    /** Trang quên mật khẩu — cả bốn chặng đều vào đây. */
    public function forgot(): void
    {
        if (AuthMiddleware::check()) {
            redirect('/tai-khoan');
        }

        $step  = self::forgotStep();
        $state = self::forgotState();

        $this->renderView('auth/forgot', [
            'bareLayout' => true,
            'pageTitle' => 'Quên mật khẩu — Vin Eyewear',
            'metaDesc'  => 'Đặt lại mật khẩu tài khoản Vin Eyewear.',
            'step'      => $step,
            'error'     => flash('auth_error'),
            'notice'    => flash('auth_success'),
            'old'       => $_SESSION['_old_forgot'] ?? '',
            // KHÔNG bao giờ đưa hash hay user_id ra view: view chỉ cần thứ in
            // ra màn hình.
            'forgot'    => $state === null ? [] : [
                'display' => (string) $state['display'],
                'channel' => (string) $state['channel'],
                'sentVia' => Otp::sentVia((string) $state['channel']),
                // Còn mấy giây nữa mới được bấm "Gửi lại". 0 = bấm được ngay.
                'wait'    => max(0, (int) ($state['resend'] ?? 0) - time()),
            ],
        ]);

        unset($_SESSION['_old_forgot']);

        // Màn "xong" chỉ xem được một lần: tải lại trang là về form nhập.
        if ($step === 'xong') {
            unset($_SESSION['_forgot_done']);
        }
    }

    /** Chặng 1: nhận email hoặc số điện thoại, gửi mã. */
    public function forgotSubmit(): void
    {
        $this->requirePost('/quen-mat-khau');

        $contact = trim((string) ($_POST['contact'] ?? ''));

        if (!$this->forgotIssue($contact)) {
            $_SESSION['_old_forgot'] = $contact;
            redirect('/quen-mat-khau');
        }

        redirect('/quen-mat-khau?buoc=ma');
    }

    /** Bấm "Gửi lại" ở màn nhập mã. */
    public function forgotResend(): void
    {
        $this->requirePost('/quen-mat-khau');

        $state = self::forgotState();

        if ($state === null) {
            redirect('/quen-mat-khau');
        }

        /* CHƯA HẾT 60 GIÂY THÌ KHÔNG SINH MÃ MỚI.
           Chốt ở máy chủ chứ không chỉ ở nút bấm: đồng hồ đếm ngược nằm trong
           JavaScript, ai cũng gọi thẳng địa chỉ này được. Không có chốt thì
           một vòng lặp là bơm được vô số thư tới hộp thư của người khác — và
           khi đã cắm Zalo ZNS thì mỗi tin là tiền. */
        if (time() < (int) ($state['resend'] ?? 0)) {
            redirect('/quen-mat-khau?buoc=ma');
        }

        if (!$this->forgotIssue((string) $state['contact'])) {
            redirect('/quen-mat-khau');
        }

        redirect('/quen-mat-khau?buoc=ma');
    }

    /**
     * Sinh mã, gửi đi, cất trạng thái vào phiên. Dùng chung cho lần gửi đầu
     * và cho nút "Gửi lại" — hai nơi phải làm giống hệt nhau, tách ra để không
     * có nơi nào quên đặt lại số lần thử hay đồng hồ chờ.
     *
     * @return bool false = có lỗi, đã flash sẵn thông báo cho người gọi.
     */
    private function forgotIssue(string $contact): bool
    {
        $result = PasswordResetModel::requestOtp($contact);

        if (!$result['ok']) {
            flash('auth_error', $result['error'] ?? 'Không xử lý được yêu cầu.');

            return false;
        }

        $_SESSION['_forgot'] = [
            'contact'  => $contact,
            'channel'  => $result['channel'],
            'display'  => $result['display'],
            'user_id'  => $result['user_id'],
            'hash'     => $result['hash'],
            'expires'  => time() + Otp::TTL,
            'resend'   => time() + Otp::RESEND_AFTER,
            'tries'    => 0,
            'verified' => false,
            'email'    => '',
        ];

        /* Ở MÁY PHÁT TRIỂN thì hiện thẳng mã lên màn hình. Zalo chưa cắm nhà
           cung cấp, mà hosting hiện tại chặn luôn cả gửi mail (MAIL_DRIVER=log),
           nên không có đường nào khác để thử luồng. Chốt theo app.debug: trên
           production mã chỉ nằm trong error log. */
        if (($result['code'] ?? null) !== null) {
            flash('auth_success', 'Mã xác minh (chỉ hiện ở chế độ phát triển): ' . $result['code']);
        }

        return true;
    }

    /** Chặng 2: kiểm mã 6 số. */
    public function forgotVerify(): void
    {
        $this->requirePost('/quen-mat-khau');

        $state = self::forgotState();

        if ($state === null || ($state['hash'] ?? '') === '') {
            redirect('/quen-mat-khau');
        }

        // Sáu ô rời thành một chuỗi — giống màn nhập mã của luồng đăng ký.
        $code = preg_replace('/\D+/', '', implode('', (array) ($_POST['ma'] ?? [])));

        if (time() > (int) $state['expires']) {
            flash('auth_error', 'Mã đã hết hạn. Bấm "Gửi lại" để nhận mã mới.');
            redirect('/quen-mat-khau?buoc=ma');
        }

        /*
         * CHƯA ĐỦ SÁU CHỮ SỐ THÌ KHÔNG PHẢI MỘT LẦN DÒ MÃ — ĐỪNG TRỪ LƯỢT.
         *
         * Không có chốt này thì bấm "Xác minh" với sáu ô còn trống cũng ăn một
         * lượt: $code là chuỗi rỗng, và password_verify('', $hash) trượt như
         * mọi mã sai khác. Năm lần bấm nhầm là mất mã, phải xin lại từ đầu —
         * mà khách chưa gõ chữ số nào.
         *
         * Cùng luật, cùng câu chữ với signupCodeProblem() ở luồng đăng ký: một
         * mã OTP là đúng Otp::LENGTH chữ số ở cả hai màn.
         */
        if (preg_match('/^[0-9]{' . Otp::LENGTH . '}$/', (string) $code) !== 1) {
            flash('auth_error', sprintf('Mã xác minh gồm %d chữ số.', Otp::LENGTH));
            redirect('/quen-mat-khau?buoc=ma');
        }

        /*
         * user_id rỗng nghĩa là chuỗi khách gõ KHÔNG khớp tài khoản nào.
         *
         * Mã của yêu cầu đó chưa từng được gửi đi đâu (xem
         * PasswordResetModel::requestOtp), nên trên thực tế không ai nhập đúng
         * được. Vẫn cho vào tới đây rồi mới trượt ở nhánh "sai mã" là cố ý:
         * dừng sớm hơn — chẳng hạn báo lỗi ngay ở chặng 1 — thì màn hình hé ra
         * email nào có tài khoản ở đây, email nào không.
         */
        if ($state['user_id'] === null || !Otp::matches((string) $code, (string) $state['hash'])) {
            $state['tries'] = (int) $state['tries'] + 1;

            // Hết lượt thì bỏ hẳn yêu cầu, không chỉ báo lỗi: còn mã là còn dò.
            if ($state['tries'] >= Otp::MAX_TRIES) {
                unset($_SESSION['_forgot']);

                flash('auth_error', 'Nhập sai quá nhiều lần. Vui lòng gửi lại yêu cầu.');
                redirect('/quen-mat-khau');
            }

            $_SESSION['_forgot'] = $state;

            flash('auth_error', sprintf(
                'Mã không đúng. Bạn còn %d lần thử.',
                Otp::MAX_TRIES - $state['tries']
            ));
            redirect('/quen-mat-khau?buoc=ma');
        }

        $state['verified'] = true;
        $state['hash']     = '';   // đã dùng xong, không giữ lại làm gì

        $_SESSION['_forgot'] = $state;

        redirect('/quen-mat-khau?buoc=mat-khau');
    }

    /** Chặng 3: đặt mật khẩu mới. */
    public function forgotFinish(): void
    {
        $this->requirePost('/quen-mat-khau');

        $state = self::forgotState();

        // Kiểm lại verified NGAY TRƯỚC KHI ĐỔI, không tin vào việc bước trước
        // đã kiểm: giữa hai request, phiên có thể đã bị thay bằng thứ khác.
        if ($state === null || empty($state['verified']) || $state['user_id'] === null) {
            redirect('/quen-mat-khau');
        }

        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        if ($new !== $confirm) {
            flash('auth_error', 'Hai lần nhập mật khẩu không khớp.');
            redirect('/quen-mat-khau?buoc=mat-khau');
        }

        $result = PasswordResetModel::applyNewPassword((string) $state['user_id'], $new);

        if (!$result['ok']) {
            flash('auth_error', $result['error']);
            redirect('/quen-mat-khau?buoc=mat-khau');
        }

        /*
         * KHÔNG tự đăng nhập luôn sau khi đổi.
         *
         * Người vừa đi qua luồng này có thể đang ngồi ở máy lạ (chính vì thế
         * họ mới phải đặt lại mật khẩu). Bắt gõ mật khẩu mới một lần ở trang
         * đăng nhập vừa xác nhận họ nhớ đúng thứ vừa đặt, vừa không để lại một
         * phiên đang đăng nhập trên cái máy đó.
         */
        unset($_SESSION['_forgot']);
        $_SESSION['_forgot_done'] = true;

        redirect('/quen-mat-khau?buoc=xong');
    }

    /** Trang đặt mật khẩu mới, tới từ liên kết trong email hoặc do nhân viên gửi. */
    public function reset(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $row   = $token !== '' ? PasswordResetModel::findValid($token) : null;

        $this->renderView('auth/reset', [
            'bareLayout' => true,
            'pageTitle' => 'Đặt mật khẩu mới — Vin Eyewear',
            'metaDesc'  => 'Chọn mật khẩu mới cho tài khoản Vin Eyewear.',
            'token'     => $token,
            'valid'     => $row !== null,
            'email'     => $row['email'] ?? '',
            'error'     => flash('auth_error'),
        ]);
    }

    public function resetSubmit(): void
    {
        $this->requirePost('/dat-lai-mat-khau');

        $token   = (string) ($_POST['token'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        if ($new !== $confirm) {
            flash('auth_error', 'Hai lần nhập mật khẩu không khớp.');
            redirect('/dat-lai-mat-khau?token=' . rawurlencode($token));
        }

        $result = PasswordResetModel::complete($token, $new);

        if (!$result['ok']) {
            flash('auth_error', $result['error']);
            redirect('/dat-lai-mat-khau?token=' . rawurlencode($token));
        }

        flash('auth_success', 'Đã đổi mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.');
        redirect('/auth');
    }

    /*
     * ═════════════════════════════════════════════════════════════════════
     * ĐĂNG KÝ — MỘT MÀN, MỘT LƯỢT GỬI (UC-USER-01)
     *
     * Bản trước chia luồng thành sáu chặng nối nhau bằng ?buoc=: nhập số →
     * hỏi kênh gửi → chọn kênh → nhập mã → tạo mật khẩu → xong. Đặc tả
     * UC-USER-01 chốt lại một màn duy nhất hỏi đủ Họ tên, Số điện thoại,
     * Email (không bắt buộc), Mật khẩu, Xác nhận mật khẩu và ô tick Điều
     * khoản/Chính sách; bấm "Đăng ký" là kiểm hết một lượt rồi tạo tài khoản.
     *
     *   POST /auth/dang-ky         signupSubmit()    tạo tài khoản
     *   POST /auth/dang-ky/gui-ma  signupSendCode()  xin mã xác minh
     *
     * ─────────────────────────────────────────────────────────────────────
     * MÃ XÁC MINH Ở LẠI, NHƯNG KHÔNG CÒN LÀ MỘT CHẶNG
     *
     * BR-UC.USER.01-02 nói "Không yêu cầu OTP ở Phase 1". Gỡ hẳn khâu xác
     * minh thì ngày cắm xong Zalo phải dựng lại từ đầu, nên nó ở lại dưới
     * dạng MỘT HÀNG trong chính form đăng ký — và hàng ấy chỉ hiện khi mã
     * thật sự gửi được (Otp::bypass() đóng). Chưa cắm Zalo thì form đúng
     * nguyên văn đặc tả Phase 1: không ô mã, không bước xác minh.
     *
     * SỐ ĐIỆN THOẠI KHÔNG NẰM TRÊN URL: nó là dữ liệu cá nhân, mà URL thì đi
     * vào lịch sử duyệt web, vào Referer gửi sang bên thứ ba, và vào log của
     * mọi proxy trên đường. Trạng thái mã vì thế nằm trong
     * $_SESSION['_signup_otp'], chữ đã gõ nằm trong $_SESSION['_old_auth'],
     * và lỗi từng ô nằm trong $_SESSION['_auth_errors'] — khoá dùng chung
     * với màn đăng nhập, vì hai màn không bao giờ hiện cùng lúc.
     * ═════════════════════════════════════════════════════════════════════
     */

    /**
     * Đích đến sau khi ĐĂNG KÝ xong — BR-UC.USER.01-09.
     *
     * Khác loginTarget(): mặc định là TRANG CHỦ chứ không phải /tai-khoan.
     * Đặc tả chốt hai ngả — vào đăng ký từ một nghiệp vụ đòi đăng nhập thì
     * quay lại đúng nghiệp vụ đó (tham số `redirect` do
     * AuthMiddleware::requireLogin() gắn vào), còn chủ động vào từ menu tài
     * khoản thì về trang chủ.
     */
    private function signupTarget(?string $raw): string
    {
        return safeRedirectPath($raw, '/');
    }

    /** Yêu cầu này tới từ fetch của auth.js hay từ một cú submit thật? */
    private static function laFetch(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    /**
     * Câu báo lỗi CÓ KÈM MỘT LIÊN KẾT.
     *
     * EF-10 đòi câu "Số điện thoại này đã được đăng ký. Vui lòng đăng nhập."
     * phải đi kèm link Đăng nhập — mà một câu chữ thuần thì khách phải tự tìm
     * đường về màn đăng nhập. Giá trị trong mảng $errors vì thế được phép là
     * MỘT MẢNG thay vì một chuỗi; auth/_signup.php biết vẽ cả hai dạng.
     */
    private static function loiCoLink(string $message, string $href, string $label): array
    {
        return ['msg' => $message, 'href' => $href, 'text' => $label];
    }

    /** Trạng thái mã xác minh đang chờ, hoặc null nếu chưa xin lần nào. */
    private static function signupOtp(): ?array
    {
        $s = $_SESSION['_signup_otp'] ?? null;

        return is_array($s) && ($s['phone'] ?? '') !== '' ? $s : null;
    }

    /** Dữ liệu màn đăng ký cần in ra. Không bao giờ trả về mã hay hash. */
    private static function signupView(): array
    {
        $otp = self::signupOtp();

        return [
            // Còn mấy giây nữa mới được bấm "Gửi mã". 0 = bấm được ngay.
            'wait' => $otp === null ? 0 : max(0, (int) ($otp['resend'] ?? 0) - time()),
            // Đã xin mã lần nào chưa — quyết câu chữ dưới ô nhập mã.
            'sent' => $otp !== null && ($otp['hash'] ?? '') !== '',
        ];
    }

    /**
     * Toàn bộ phép kiểm của Main Flow bước 5 — EF-02 … EF-12.
     *
     * Trả về mảng KHOÁ THEO TÊN TRƯỜNG, rỗng nghĩa là hợp lệ. Kiểm HẾT một
     * lượt chứ không dừng ở lỗi đầu tiên: sáu ô mà mỗi lần gửi chỉ chỉ ra
     * được một chỗ sai thì khách phải gửi sáu lần mới biết hết.
     *
     * ⚠ Hàm này CÓ ghi vào phiên: signupCodeProblem() đếm số lần nhập sai mã.
     * Đó là chủ ý — đếm ở đâu khác thì hai nơi phải cùng biết một phép kiểm.
     */
    private static function signupErrors(array $in, string $password, string $confirm): array
    {
        $loi = [];

        /*
         * ĐỒNG Ý ĐIỀU KHOẢN ĐỨNG ĐẦU — BR-UC.USER.01-05, EF-12.
         *
         * Kiểm ở MÁY CHỦ, không tin `required` của form: tắt JavaScript không
         * ảnh hưởng gì tới thuộc tính ấy, nhưng gọi thẳng POST /auth/dang-ky
         * thì bỏ qua được cả form. Mà đây đúng là loại ràng buộc không được
         * phép chỉ sống ở trình duyệt — cả điểm của nó là ghi nhận một hành vi
         * có thật của người dùng.
         */
        if (empty($in['dong_y'])) {
            $loi['dong_y'] = 'Vui lòng đồng ý với Điều khoản và Chính sách để đăng ký.';
        }

        /* HỌ TÊN — BR-UC.USER.01-01, EF-02. Đã trim trước khi vào đây.

           CHỈ KIỂM RỖNG, không đặt mốc độ dài tối thiểu: đặc tả nói đúng hai
           việc là "bắt buộc" và "tự động trim". Form đặt lịch
           (BookingController::store) đòi hai ký tự, nhưng luật ấy là của nó,
           không phải của đây — chép sang là tự thêm một ràng buộc mà người
           nghiệm thu không biết. Trần 120 bên dưới thì khác: nó khớp
           maxlength="120" của ô, tức là một giới hạn của giao diện chứ không
           phải một luật nghiệp vụ mới. */
        if ($in['full_name'] === '') {
            $loi['full_name'] = 'Vui lòng nhập họ tên.';
        } elseif (utf8Length($in['full_name']) > 120) {
            /* Chặn trên khớp maxlength="120" của ô. Cột profiles.full_name rộng
               255 nên đây không phải để cứu câu INSERT, mà để một cái tên dài
               bất thường bị chặn ngay chỗ nó được gõ ra. */
            $loi['full_name'] = 'Họ tên quá dài (tối đa 120 ký tự).';
        }

        /* SỐ ĐIỆN THOẠI — BR-UC.USER.01-02 và BR-UC.USER.01-06; EF-03/04/10.
           normalizePhone() là nơi giữ luật "10 số, đầu 03/05/07/08/09" và cũng
           là hàm mà UserModel::register() dùng — một luật, một chỗ. */
        $phone = normalizePhone($in['phone']);

        if ($in['phone'] === '') {
            $loi['phone'] = 'Vui lòng nhập số điện thoại.';
        } elseif ($phone === null) {
            $loi['phone'] = 'Số điện thoại không hợp lệ. Vui lòng kiểm tra lại.';
        } elseif ((int) Database::fetchValue(
            'SELECT COUNT(*) FROM profiles WHERE phone = :p',
            ['p' => $phone]
        ) > 0) {
            $loi['phone'] = self::loiCoLink(
                'Số điện thoại này đã được đăng ký.',
                '/auth',
                'Vui lòng đăng nhập.'
            );
        }

        /* EMAIL — BR-UC.USER.01-03, EF-05/EF-11. Không bắt buộc: bỏ trống thì
           không kiểm gì cả. */
        $email = strtolower($in['email']);

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $loi['email'] = 'Email không hợp lệ. Vui lòng kiểm tra lại.';
            } elseif ((int) Database::fetchValue(
                'SELECT COUNT(*) FROM users WHERE email = :e',
                ['e' => $email]
            ) > 0) {
                $loi['email'] = self::loiCoLink(
                    'Email này đã được đăng ký. Vui lòng sử dụng Email khác hoặc',
                    '/auth',
                    'đăng nhập.'
                );
            }
        }

        /* MẬT KHẨU — BR-UC.USER.01-04, EF-06/EF-07.
           Bộ quy tắc dùng chung cho mọi màn đặt mật khẩu, xem passwordProblem()
           trong core/helpers.php. auth.js chấm xanh từng dòng ngay khi gõ,
           nhưng đó chỉ là tăng cường: tắt JavaScript, hay gọi thẳng địa chỉ
           này, thì hàm kia là thứ duy nhất còn đứng lại. */
        if ($password === '') {
            $loi['password'] = 'Vui lòng nhập mật khẩu.';
        } elseif (($yeu = passwordProblem($password)) !== null) {
            $loi['password'] = $yeu;
        }

        /* XÁC NHẬN MẬT KHẨU — EF-08/EF-09. Câu báo gắn vào ô XÁC NHẬN chứ
           không phải ô mật khẩu: ô dưới mới là ô khách cần sửa. */
        if ($confirm === '') {
            $loi['password_confirm'] = 'Vui lòng xác nhận mật khẩu.';
        } elseif ($password !== $confirm) {
            $loi['password_confirm'] = 'Mật khẩu xác nhận không khớp.';
        }

        /* MÃ XÁC MINH — chỉ bỏ qua khi số điện thoại chưa hợp lệ, vì lúc ấy
           không có gì để so mã với. Chế độ thử (Otp::bypass) KHÔNG bỏ qua phép
           kiểm này, nó chỉ nới lỏng bên trong signupCodeProblem(). */
        if (!isset($loi['phone'])) {
            $maLoi = self::signupCodeProblem((string) $phone, (string) $in['ma']);

            if ($maLoi !== null) {
                $loi['ma'] = $maLoi;
            }
        }

        return $loi;
    }

    /**
     * Mã xác minh có đúng không — null nghĩa là đúng.
     *
     * Tách riêng vì nó là phép kiểm DUY NHẤT trong nhóm có ghi ngược vào
     * phiên: mỗi lần sai là một lượt bị trừ, và hết lượt thì huỷ mã luôn chứ
     * không chỉ báo lỗi — còn mã là còn dò được.
     */
    private static function signupCodeProblem(string $phone, string $ma): ?string
    {
        $otp = self::signupOtp();

        /*
         * ─────────────────────────────────────────────────────────────────
         * HÌNH DẠNG TRƯỚC, NỘI DUNG SAU — VÀ ĐỨNG TRÊN MỌI NHÁNH BÊN DƯỚI
         *
         * Mã OTP là ĐÚNG Otp::LENGTH chữ số, không hơn không kém, không chữ
         * cái. Chốt ở đây chứ không phó mặc cho phép so hash bên dưới, vì hai
         * lý do:
         *
         *   · "abc" hay "123" mà rơi xuống Otp::matches() thì trượt, và cú
         *     trượt ấy ĂN MỘT LƯỢT trong năm lượt của MAX_TRIES. Gõ hụt một
         *     chữ số không phải một lần dò mã — trừ lượt vì nó là phạt nhầm
         *     người, và năm lần gõ hụt là mất mã, phải xin lại từ đầu.
         *   · Câu báo nói đúng việc phải sửa. "Mã xác minh không đúng. Bạn còn
         *     4 lần thử" cho một ô gõ thiếu số là câu chữ đánh lạc hướng.
         *
         * Ô RỖNG THÌ KHÔNG XÉT Ở ĐÂY: nó có câu riêng bên dưới, và ở nhánh
         * "chưa xin mã" thì câu đúng lại là 'bấm "Gửi mã"' chứ không phải
         * 'nhập mã' — thứ tự ấy phải giữ nguyên.
         *
         * TRƯỚC BYPASS, KHÔNG PHẢI SAU. Zalo chưa cắm là nới lỏng việc mã có
         * KHỚP hay không, không phải nới lỏng việc nó có phải một mã hay
         * không — bỏ chốt này vào trong bypass thì ô mã nhận cả "abc".
         * ─────────────────────────────────────────────────────────────────
         */
        if ($ma !== '' && preg_match('/^[0-9]{' . Otp::LENGTH . '}$/', $ma) !== 1) {
            return sprintf('Mã xác minh gồm %d chữ số.', Otp::LENGTH);
        }

        /*
         * ─────────────────────────────────────────────────────────────────
         * ZALO OA CHƯA CẮM: DÃY SÁU SỐ NÀO CŨNG QUA.
         *
         * Chưa khai đủ token và mã mẫu ZNS thì mã sinh ra chỉ nằm trong error
         * log — khách không có đường nào biết nó, nên nếu vẫn so mã thì luồng
         * đăng ký đứng hẳn tại đây.
         *
         * Ô RỖNG VẪN BỊ CHẶN: bấm nhầm nút "Đăng ký" thì không nên tính là đã
         * xác minh. Hạn 120 giây và số lần thử thì bỏ theo — giữ lại chỉ làm
         * khách kẹt vì một lý do khó hiểu hơn ("mã đã hết hạn" trong khi chưa
         * từng có mã nào).
         *
         * BỎ QUA CHỨ KHÔNG BỎ HẲN: ô mã vẫn ở đúng chỗ, nút "Gửi mã" vẫn sinh
         * mã thật, nên ngày cắm xong ZNS thì bypass() trả false và mọi dòng
         * bên dưới chạy đúng như đã viết — không phải dựng lại gì.
         *
         * ⚠ ĐANG MỞ THÌ BẤT KỲ AI CŨNG ĐĂNG KÝ ĐƯỢC BẰNG SỐ CỦA NGƯỜI KHÁC.
         * Điều kiện mở nằm ở Otp::bypass() / config/auth.php.
         * ─────────────────────────────────────────────────────────────────
         */
        if (Otp::bypass()) {
            if ($ma === '') {
                return 'Vui lòng nhập mã xác minh.';
            }

            error_log(sprintf(
                '[Otp] BỎ QUA xác minh khi đăng ký cho %s — Zalo OTP chưa cắm '
                . '(xem Otp::bypass()).',
                $phone
            ));

            return null;
        }

        /* Chưa xin mã, hoặc đã xin cho một SỐ KHÁC. Vế thứ hai mới là vế quan
           trọng: không có nó thì khách xin mã cho số của mình, đổi sang số
           người khác rồi dán mã cũ vào là xác minh được một số chưa hề nhận
           tin nào. */
        if ($otp === null || ($otp['hash'] ?? '') === '' || $otp['phone'] !== $phone) {
            return 'Vui lòng bấm "Gửi mã" để nhận mã xác minh cho số này.';
        }

        if ($ma === '') {
            return 'Vui lòng nhập mã xác minh.';
        }

        if (time() > (int) $otp['expires']) {
            return 'Mã đã hết hạn. Bấm "Gửi mã" để nhận mã mới.';
        }

        if (Otp::matches($ma, (string) $otp['hash'])) {
            return null;
        }

        $otp['tries'] = (int) ($otp['tries'] ?? 0) + 1;

        if ($otp['tries'] >= Otp::MAX_TRIES) {
            $otp['hash']   = '';
            $otp['resend'] = 0;
            $_SESSION['_signup_otp'] = $otp;

            return 'Nhập sai quá nhiều lần. Bấm "Gửi mã" để lấy mã mới.';
        }

        $_SESSION['_signup_otp'] = $otp;

        return sprintf(
            'Mã xác minh không đúng. Bạn còn %d lần thử.',
            Otp::MAX_TRIES - $otp['tries']
        );
    }

    /**
     * Bấm "Gửi mã" bên cạnh ô số điện thoại.
     *
     * HAI ĐƯỜNG VỀ CÙNG MỘT CHỖ:
     *   · có JavaScript — auth.js gọi ngầm và đọc JSON, khách không rời trang;
     *   · không có     — nút submit thật, máy chủ cất chữ đã gõ vào phiên rồi
     *                    trả khách về đúng form với dữ liệu còn nguyên.
     *
     * Nhận ra đường nào bằng header `X-Requested-With: fetch` — cùng quy ước
     * với catalog.js/account.js, xem khối "Ba chế độ trả mảnh" trong CLAUDE.md.
     */
    public function signupSendCode(): void
    {
        /* KHÔNG dùng requirePost() ở đây: nó trả về một cú chuyển hướng HTML,
           mà đường AJAX chỉ biết đọc JSON — khách sẽ thấy mãi câu "không gửi
           được mã" thay vì "phiên đã hết hạn, tải lại trang". Hai phép kiểm
           của nó vẫn nguyên, chỉ đổi cách trả lời. */
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('/auth?tab=dang-ky');
        }

        $to  = $this->signupTarget($_POST['redirect'] ?? null);
        $raw = trim((string) ($_POST['phone'] ?? ''));

        if (!csrfCheck($_POST['_token'] ?? null)) {
            $this->signupCodeReply(
                false,
                'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.',
                0,
                $to
            );
        }

        /* Đã đăng nhập thì không có việc gì ở luồng đăng ký. index() chặn
           đường GET, nhưng POST thì đi thẳng vào đây. */
        if (AuthMiddleware::check()) {
            $this->signupCodeReply(false, 'Bạn đang đăng nhập rồi.', 0, $to);
        }

        /* Đường KHÔNG JavaScript đi qua một cú tải lại trang, nên mọi chữ đã
           gõ phải được cất lại — trừ hai ô mật khẩu, không bao giờ cất.

           CHỈ Ở ĐƯỜNG ẤY. Đường AJAX không rời trang, chữ vẫn nằm nguyên
           trong các ô; cất thêm một bản vào phiên thì bản ấy nằm lại đó và
           lần sau mở màn ĐĂNG NHẬP là ô "Số điện thoại hoặc email" tự điền
           sẵn thứ khách gõ ở màn đăng ký. */
        if (!self::laFetch()) {
            $_SESSION['_old_auth'] = [
                'full_name' => trim((string) ($_POST['full_name'] ?? '')),
                'phone'     => $raw,
                'email'     => trim((string) ($_POST['email'] ?? '')),
                'dong_y'    => !empty($_POST['dong_y']),
            ];
        }

        $phone = normalizePhone($raw);

        if ($raw === '') {
            $this->signupCodeReply(false, 'Vui lòng nhập số điện thoại.', 0, $to);
        }

        if ($phone === null) {
            $this->signupCodeReply(false, 'Số điện thoại không hợp lệ. Vui lòng kiểm tra lại.', 0, $to);
        }

        /* Số đã có tài khoản thì dừng ngay tại đây, đừng gửi mã. Mã gửi tới
           một số không thể đăng ký được là một tin nhắn mất tiền dẫn tới một
           ngõ cụt — và EF-10 nói đúng câu cần nói. */
        if ((int) Database::fetchValue(
            'SELECT COUNT(*) FROM profiles WHERE phone = :p',
            ['p' => $phone]
        ) > 0) {
            /* Câu này đi cả đường JSON (auth.js in ra dạng chữ thuần) nên
               không kèm link được — ô số điện thoại sẽ tự có link ấy ngay khi
               khách bấm "Đăng ký". */
            $this->signupCodeReply(
                false,
                'Số điện thoại này đã được đăng ký. Vui lòng đăng nhập.',
                0,
                $to
            );
        }

        /* CHƯA HẾT 60 GIÂY THÌ KHÔNG SINH MÃ MỚI.
           Chốt ở máy chủ chứ không chỉ ở nút bấm: đồng hồ đếm ngược nằm trong
           JavaScript, ai cũng gọi thẳng địa chỉ này được. Không có chốt thì
           một vòng lặp là bơm được vô số tin nhắn tới số của người khác — và
           khi đã cắm nhà cung cấp thật thì mỗi tin là tiền. */
        /* ⚠ CHỐT THEO PHIÊN, KHÔNG THEO SỐ. Bản đầu so thêm
           `$otp['phone'] === $phone`, và đó là một cái lỗ: mỗi lần đổi số là
           một mã mới được sinh ra ngay, nên một vòng lặp A, B, A, B… bơm được
           vô số tin nhắn qua đúng một phiên — chính thứ mà khối chú thích trên
           nói là phải chặn. Cái giá của việc bỏ vế ấy: khách gõ nhầm số rồi
           sửa lại phải chờ hết 60 giây. Đó đúng là ý nghĩa của một chốt tần
           suất, và câu báo dưới đây nói rõ còn bao lâu. */
        $otp = self::signupOtp();

        if ($otp !== null && time() < (int) ($otp['resend'] ?? 0)) {
            $con = (int) ($otp['resend'] ?? 0) - time();

            $this->signupCodeReply(
                false,
                sprintf('Vui lòng chờ %d giây rồi bấm "Gửi mã" lại.', $con),
                $con,
                $to
            );
        }

        $code = Otp::generate();

        $_SESSION['_signup_otp'] = [
            'phone'   => $phone,
            'hash'    => Otp::hash($code),
            'expires' => time() + Otp::TTL,
            'resend'  => time() + Otp::RESEND_AFTER,
            'tries'   => 0,
        ];

        $daGui = Otp::send($phone, $code, 'zalo');

        /* CÂU BÁO PHẢI NÓI ĐÚNG THỨ VỪA XẢY RA.
           "Mã đã được gửi qua Zalo đến 09xx" trong khi ZNS chưa cắm là bắt
           khách ngồi chờ một tin nhắn không tồn tại — xem Otp::bypass(). */
        $cau = $daGui
            ? 'Mã xác minh đã được gửi qua Zalo đến ' . Otp::displayPhone($phone) . '.'
            : 'Zalo OTP chưa được cắm nên chưa có tin nhắn nào gửi đi. '
              . 'Gõ số bất kỳ vào ô mã để tiếp tục.';

        /* Ở MÁY PHÁT TRIỂN thì hiện thẳng mã lên màn hình, vì chưa chắc có nhà
           cung cấp nào mang nó tới tay khách — xem khối chú thích đầu
           core/Otp.php. Chốt theo app.debug: trên production nó chỉ nằm trong
           error log. */
        if (config('app.debug')) {
            $cau .= ' Mã (chỉ hiện ở chế độ phát triển): ' . $code;
        }

        $this->signupCodeReply(true, $cau, Otp::RESEND_AFTER, $to);
    }

    /**
     * Trả lời cho signupSendCode() theo đúng đường mà khách đi tới.
     *
     * Không bao giờ trả về: cả hai nhánh đều kết thúc request.
     */
    private function signupCodeReply(bool $ok, string $message, int $wait, string $to): never
    {
        if (self::laFetch()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                ['ok' => $ok, 'message' => $message, 'wait' => $wait],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        if ($ok) {
            flash('auth_success', $message);
        } else {
            // Cùng ô, cùng câu chữ với đường JavaScript: lỗi của thao tác "xin
            // mã" luôn hiện ngay dưới ô mã xác minh.
            $_SESSION['_auth_errors'] = ['ma' => $message];
        }

        redirect($this->signupBack($to));
    }

    /** Địa chỉ màn đăng ký, giữ nguyên đích đến đang mang theo. */
    private function signupBack(string $to): string
    {
        return '/auth?tab=dang-ky' . ($to !== '/' ? '&redirect=' . rawurlencode($to) : '');
    }

    /**
     * Bấm "Đăng ký" — kiểm hết một lượt rồi tạo tài khoản.
     *
     * Main Flow bước 5 → 10 của UC-USER-01 nằm gọn trong hàm này.
     */
    public function signupSubmit(): void
    {
        $this->requirePost('/auth?tab=dang-ky');

        /* Đã đăng nhập thì không có việc gì ở đây. index() chặn đường GET,
           nhưng POST thì đi thẳng vào đây — và không chặn thì một khách đang
           đăng nhập tạo được tài khoản thứ hai rồi bị chuyển sang tài khoản
           mới ấy ngay trong cùng một cú bấm. */
        if (AuthMiddleware::check()) {
            redirect('/tai-khoan');
        }

        $in = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'phone'     => trim((string) ($_POST['phone'] ?? '')),
            'email'     => trim((string) ($_POST['email'] ?? '')),
            /*
             * is_array(): form CŨ gửi mã bằng sáu ô tên `ma[]`. Một trang cũ
             * còn mở trong tab khác, hay một bookmark, vẫn POST đúng dạng ấy
             * — ép mảng sang chuỗi là một warning trên log production.
             *
             * CHỈ BÓC KHOẢNG TRẮNG, KHÔNG BÓC CHỮ CÁI. Bản trước dùng `/\D+/`,
             * tức nuốt lặng mọi thứ không phải chữ số: "12ab34" thành "1234",
             * rồi "1234" trượt phép so hash và ĂN MỘT LƯỢT thử — khách thấy
             * "Mã xác minh không đúng, còn 4 lần" cho một ô họ gõ đủ sáu ký
             * tự. Giữ nguyên thứ khách gõ thì signupCodeProblem() nói đúng
             * bệnh: "Mã xác minh gồm 6 chữ số."
             *
             * Khoảng trắng thì vẫn bóc: dán "123 456" từ tin nhắn là chuyện
             * thường, và đó không phải một lỗi đáng bắt khách sửa tay.
             */
            'ma'        => preg_replace(
                '/\s+/u',
                '',
                is_array($_POST['ma'] ?? null) ? implode('', $_POST['ma']) : (string) ($_POST['ma'] ?? '')
            ) ?? '',
            'dong_y'    => !empty($_POST['dong_y']),
        ];

        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');
        $to       = $this->signupTarget($_POST['redirect'] ?? null);

        /* Giữ lại mọi chữ đã gõ, kể cả cú tick — quay về vì hai ô mật khẩu
           lệch nhau mà mất luôn tên, số, email và cú tick là bắt làm lại bốn
           việc đã làm đúng. HAI Ô MẬT KHẨU THÌ KHÔNG: không cất mật khẩu vào
           phiên, và ô mật khẩu điền sẵn thì khách không biết mình đang gửi lại
           chuỗi nào. */
        $_SESSION['_old_auth'] = [
            'full_name' => $in['full_name'],
            'phone'     => $in['phone'],
            'email'     => $in['email'],
            'dong_y'    => $in['dong_y'],
        ];

        $loi = self::signupErrors($in, $password, $confirm);

        if ($loi !== []) {
            $_SESSION['_auth_errors'] = $loi;
            redirect($this->signupBack($to));
        }

        /*
         * TỚI ĐÂY DỮ LIỆU ĐÃ HỢP LỆ — bước 6 đến 8 của Main Flow.
         *
         * register() tự kiểm lại số điện thoại, email và mật khẩu bằng ĐÚNG
         * những hàm vừa dùng ở trên (normalizePhone, passwordProblem), rồi
         * INSERT cả ba bảng trong MỘT giao dịch: hỏng ở đâu cũng không để lại
         * tài khoản dở dang — đúng yêu cầu của EF-13.
         *
         * Phiên bản văn bản Điều khoản đi cùng và được ghi TRONG CÙNG giao dịch
         * ấy (BR-UC.USER.01-05): không có đường nào để một tài khoản ra đời mà
         * thiếu vết đồng ý.
         */
        $ket = UserModel::register(
            (string) normalizePhone($in['phone']),
            $password,
            $in['full_name'],
            $in['email'],
            (string) config('auth.consent.version', '')
        );

        if (!$ket['ok']) {
            /* Lọt tới đây nghĩa là có gì đó đổi GIỮA lúc kiểm và lúc ghi —
               hai người cùng đăng ký một số trong cùng một giây, hoặc CSDL
               trục trặc. Câu của register() vẫn là câu chính xác nhất mô tả
               việc vừa hỏng, nên dùng lại nguyên văn; nó không thuộc ô nào cụ
               thể nên đi vào dải báo chung, đúng chỗ EF-13 chỉ định. */
            flash('auth_error', $ket['error']);
            redirect($this->signupBack($to));
        }

        unset(
            $_SESSION['_old_auth'],
            $_SESSION['_auth_errors'],
            $_SESSION['_signup_otp']
        );

        /* Bước 8 — BR-UC.USER.01-08: đăng ký xong đăng nhập luôn, bắt khách
           nhập lại ngay thông tin vừa gõ là thêm một bước không cần thiết. */
        AuthMiddleware::login($ket['id']);

        /* Bước 9 và 10 — câu báo là một dải toast của khung trang khách nên nó
           đi theo khách tới bất kỳ đích nào của BR-UC.USER.01-09. Khoá
           'site_success' là khoá TRUNG TÍNH, đọc được ở mọi trang; khoá
           'account_success' chỉ hiện trong khu /tai-khoan, mà đích mặc định
           nay là trang chủ — xem BaseController::toastFromFlash(). */
        flash('site_success', 'Đăng ký tài khoản thành công!');

        redirect($to);
    }

    /*
     * ═════════════════════════════════════════════════════════════════════
     * ĐĂNG KÝ BẰNG GOOGLE — MỘT CÁCH RIÊNG, BA CHẶNG
     *
     *   1. googleStart()         bấm "Tiếp tục với Google" -> sang Google.
     *   2. googleCallback()      Google trả về. TRA, KHÔNG TẠO. Chưa có tài
     *                            khoản thì cất thông tin đã xác thực vào
     *                            phiên rồi đẩy sang chặng 3.
     *   3. googleSignup()        màn "Hoàn tất tạo tài khoản": email điền sẵn
     *      googleSignupSubmit()  và khoá, khách khai họ tên + số điện thoại
     *                            (không bắt buộc) + tick Điều khoản. Tài khoản
     *                            ra đời ở ĐÂY, không sớm hơn.
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO CÓ CHẶNG 3 — TRƯỚC ĐÂY KHÔNG CÓ
     *
     * Bản cũ tạo tài khoản ngay trong callback. Nghĩa là mọi thứ tài khoản
     * mới cần phải được thu thập TRƯỚC khi đi Google, mà chỗ duy nhất làm
     * được việc đó là form đăng ký bằng số điện thoại — nên nút "Tiếp tục với
     * Google" phải là nút submit của form ấy, để mượn ô tick Điều khoản của
     * nó. Hai cách đăng ký dính vào nhau ở đúng chỗ khó thấy nhất: bỏ ô tick
     * khỏi form kia là lặng lẽ mở một lối tạo tài khoản không có đồng ý.
     *
     * Nay mỗi cách có form riêng, ô tick riêng, phép kiểm riêng. Cờ
     * `_google_consent` và tham số $allowCreate đi theo bản cũ: chúng sinh ra
     * chỉ để bù cho việc callback được phép tạo tài khoản, mà nay nó không
     * được phép nữa.
     * ═════════════════════════════════════════════════════════════════════
     */

    /**
     * Thông tin Google đã xác thực, đang chờ khách hoàn tất hồ sơ.
     *
     * Nằm trong PHIÊN chứ không phải trên địa chỉ: `sub` là định danh vĩnh
     * viễn của một con người ở phía Google, và một chuỗi như thế đi qua thanh
     * địa chỉ là đi vào lịch sử duyệt web, vào Referer gửi sang bên thứ ba,
     * và vào log của mọi proxy trên đường — cùng lý do đã ghi ở khối
     * "ĐĂNG KÝ — MỘT MÀN" phía trên.
     */
    private static function googlePending(): ?array
    {
        $p = $_SESSION['_google_pending'] ?? null;

        return is_array($p) && ($p['sub'] ?? '') !== '' ? $p : null;
    }

    /**
     * Bấm "Tiếp tục với Google" — đẩy khách sang Google.
     *
     * ─────────────────────────────────────────────────────────────────────
     * GET, VÀ CHỈ GET — Ở CẢ HAI MÀN
     *
     * Nút này ở màn đăng ký từng là nút submit của chính form đăng ký (POST),
     * để cú bấm mang theo ô tick Điều khoản của form ấy. Nay cú tick nằm ở
     * màn "Hoàn tất tạo tài khoản" — đúng chỗ tài khoản thật sự ra đời — nên
     * nút trở lại là một thẻ <a> thường ở cả hai màn.
     *
     * Được phép là GET vì bước này KHÔNG ĐỔI GÌ CẢ: nó chỉ sinh một chuỗi
     * `state` rồi chuyển hướng. Thứ chống giả mạo của luồng OAuth là chính
     * `state` ấy — lưu trong phiên, Google trả lại nguyên văn ở bước sau, và
     * GoogleAuth::exchange() so bằng hash_equals.
     * ─────────────────────────────────────────────────────────────────────
     */
    public function googleStart(): void
    {
        if (!GoogleAuth::isConfigured()) {
            flash('auth_error', 'Đăng nhập bằng Google chưa được cấu hình.');
            redirect('/auth');
        }

        /* Nhớ khách xuất phát từ tab nào, để mọi lối thoát lỗi ở bước sau trả
           họ về đúng màn ấy — mục Giao diện của đặc tả nói lỗi Google phải
           hiện "trên màn hình đăng ký". */
        $laDangKy = ($_GET['tab'] ?? '') === 'dang-ky';
        $_SESSION['_google_tab'] = $laDangKy ? 'dang-ky' : 'dang-nhap';

        /* Đã đăng nhập rồi thì không có việc gì ở đây.
           Chỉ hỏi customerId(): đây là luồng của khu khách, và phiên quản trị
           (nếu có) nằm trong cookie khác nên không tới được trang này. Nhân
           viên đang trực mà bấm "Đăng nhập bằng Google" ở cửa hàng thì đó là
           họ đang đăng nhập tài khoản KHÁCH của mình — một việc hợp lệ, không
           phải nhầm lẫn cần chặn. */
        if (AuthMiddleware::customerId() !== null) {
            redirect('/tai-khoan');
        }

        /* Một lượt mới thì bỏ hẳn lượt dở trước đó: khách bấm Google, bỏ giữa
           chừng ở màn hoàn tất, rồi quay lại bấm bằng một tài khoản Google
           khác — không dọn thì thông tin của tài khoản cũ còn nằm đó. */
        unset($_SESSION['_google_pending']);

        /* Đích sau khi xong — BR-UC.USER.01-09. Vào từ màn đăng ký thì mặc
           định là trang chủ, vào từ màn đăng nhập thì /tai-khoan; có tham số
           `redirect` (do requireLogin() gắn) thì cả hai đều quay lại đúng
           nghiệp vụ đang dở. */
        $after = $laDangKy
            ? $this->signupTarget($_GET['redirect'] ?? null)
            : $this->loginTarget($_GET['redirect'] ?? null);

        // redirect() chỉ đặt header Location nên nhận cả địa chỉ tuyệt đối.
        redirect(GoogleAuth::authUrl(bin2hex(random_bytes(16)), $after));
    }

    /**
     * Google gọi ngược về đây kèm `code` và `state`.
     *
     * HAI NGẢ, và không ngả nào tạo tài khoản:
     *   · nối chìa   — khách đang đăng nhập, bấm "Liên kết" ở trang Hồ sơ.
     *   · mở cửa     — đăng nhập nếu `google_id` đã thuộc về ai, còn chưa thì
     *                  chuyển sang màn hoàn tất.
     */
    public function googleCallback(): void
    {
        if (!GoogleAuth::isConfigured()) {
            redirect('/auth');
        }

        $after = safeRedirectPath($_SESSION['_google_after'] ?? null, '/tai-khoan');
        unset($_SESSION['_google_after']);

        $tuDangKy = ($_SESSION['_google_tab'] ?? '') === 'dang-ky';

        /* Cờ LIÊN KẾT chỉ dùng được MỘT LẦN, và chỉ cho đúng lượt sinh ra nó —
           đọc ra rồi xoá ngay, trước cả những nhánh thoát bên dưới. Để nó sống
           qua một lượt hỏng là quyền nối của lượt này được đem dùng cho lượt
           sau, có khi là một tài khoản Google khác. Xem linkGoogle(). */
        $xinNoi = (string) ($_SESSION['_google_link'] ?? '');
        unset($_SESSION['_google_tab'], $_SESSION['_google_link']);

        /* Màn để trả khách về khi có lỗi — mục Giao diện: lỗi Google hiện
           trên chính màn khách vừa đứng.

           Lượt LIÊN KẾT thì màn ấy là trang Hồ sơ, không phải màn đăng nhập:
           khách đang đăng nhập sẵn, nên /auth chỉ đá họ sang /tai-khoan (xem
           index()) và câu báo lỗi rơi vào một trang khác hẳn chỗ họ vừa bấm.
           Đọc $xinNoi trần ở đây là ĐỦ AN TOÀN: nó chỉ chọn đường về, không
           cho phép điều gì — chỗ cho phép nối là phép so hash_equals bên
           dưới. */
        $veAuth = $xinNoi !== ''
            ? '/tai-khoan?muc=ho-so'
            : ($tuDangKy ? '/auth?tab=dang-ky' : '/auth');

        /* Khách bấm "Huỷ" ở màn hình của Google. Không phải lỗi — im lặng đưa
           họ về chỗ vừa đứng ($veAuth: màn đăng nhập, màn đăng ký, hay trang
           Hồ sơ), đừng doạ bằng một dòng đỏ. */
        if (isset($_GET['error'])) {
            redirect($veAuth);
        }

        $token = GoogleAuth::exchange(
            (string) ($_GET['code'] ?? ''),
            (string) ($_GET['state'] ?? '')
        );

        /* EF-01 — Google xác thực thất bại, hoặc kết quả trả về không hợp lệ.
           Không tài khoản nào được tạo; khách thử lại hoặc đăng ký bằng SĐT. */
        if (!$token['ok']) {
            /* ĐẶT ĐÚNG MỘT KHOÁ, THEO ĐÍCH SẮP TỚI. Màn đăng nhập đọc
               'auth_error', trang Hồ sơ đọc 'account_error' — đặt cả hai cho
               chắc thì cái không được đọc nằm lại trong phiên và bật ra ở lần
               mở trang kia, có khi vài ngày sau, không dính gì tới việc khách
               đang làm lúc đó. */
            flash($xinNoi !== '' ? 'account_error' : 'auth_error',
                  'Không thể xác thực tài khoản Google. Vui lòng thử lại.');
            error_log('[Google] Xác thực thất bại: ' . $token['error']);
            redirect($veAuth);
        }

        /*
         * ─────────────────────────────────────────────────────────────────
         * NGẢ THỨ NHẤT: NỐI CHÌA, KHÔNG PHẢI MỞ CỬA.
         *
         * Lượt này xuất phát từ nút "Liên kết" ở trang Hồ sơ, nên khách ĐÃ
         * đăng nhập rồi — không có ai để đăng nhập nữa, và tuyệt đối không
         * được rơi xuống nhánh mở cửa bên dưới: nhánh ấy tra theo `google_id`,
         * nên một tài khoản Google đang thuộc về NGƯỜI KHÁC sẽ thành một cú
         * đổi phiên im lặng — khách bấm "Liên kết" trong trang hồ sơ của mình
         * và đứng dậy với tư cách người lạ.
         *
         * Nhánh này vì thế thoát hẳn, không dùng chung một dòng nào với ngả
         * kia, kể cả hai chốt nội bộ — chúng phải nói bằng câu chữ và đích
         * đến của trang Hồ sơ, không phải của màn đăng nhập.
         * ─────────────────────────────────────────────────────────────────
         */
        if ($xinNoi !== '' && hash_equals($xinNoi, (string) ($_GET['state'] ?? ''))) {
            $veHoSo = '/tai-khoan?muc=ho-so';
            $userId = AuthMiddleware::customerId();

            /* Phiên đã tắt giữa chừng — khách đi Google lâu quá, hoặc đăng
               xuất ở một tab khác. Không đoán xem họ là ai: đưa về màn đăng
               nhập, vào lại rồi bấm "Liên kết" một lần nữa. */
            if ($userId === null) {
                flash('auth_error', 'Phiên đăng nhập đã kết thúc. Vui lòng đăng nhập lại '
                                  . 'rồi liên kết tài khoản Google.');
                redirect('/auth');
            }

            /* SRS mục 3.A — Google không áp dụng cho tài khoản nội bộ ở Giai
               đoạn 1. Hai vế, hai lỗ khác nhau: người đang đăng nhập là nhân
               viên (nối vào chính tài khoản nội bộ của họ), và tài khoản
               Google vừa xác thực mang email nội bộ (đem danh tính của một
               nhân viên gắn vào một tài khoản khách). Chặn cả hai. */
            if (UserModel::isStaff($userId) || UserModel::isStaffEmail($token['email'])) {
                flash('account_error', 'Tài khoản nội bộ chưa dùng liên kết Google ở giai '
                                     . 'đoạn này. Vui lòng liên hệ quản trị viên.');
                redirect($veHoSo);
            }

            $noi = UserModel::linkGoogle($userId, $token['sub']);

            if (!$noi['ok']) {
                flash('account_error', $noi['error']);
                redirect($veHoSo);
            }

            flash('account_success', ($noi['code'] ?? '') === 'da_noi_san'
                ? 'Tài khoản Google này đã được liên kết từ trước.'
                : 'Đã liên kết tài khoản Google. Từ nay bạn đăng nhập bằng '
                  . '"Tiếp tục với Google" cũng vào đúng tài khoản này.');

            redirect($veHoSo);
        }

        /*
         * ─────────────────────────────────────────────────────────────────
         * GOOGLE KHÔNG PHẢI ĐƯỜNG VÀO CỦA TÀI KHOẢN NỘI BỘ
         *
         * SRS mục 3.A đã chốt: "Đăng nhập bằng Google — Không áp dụng cho tài
         * khoản nội bộ ở Giai đoạn 1". Cổng /quan-tri/dang-nhap vì thế không
         * vẽ nút Google. Nhưng không có mấy dòng này thì cửa đó vẫn mở, chỉ là
         * mở ở phía bên kia: một tài khoản Google mang địa chỉ
         * admin@vineyewear.vn đăng ký ở đây là có một tài khoản khách mang
         * email nội bộ, và từ đó là một đường liên hệ giả danh cửa hàng.
         * ─────────────────────────────────────────────────────────────────
         */
        if (UserModel::isStaffEmail($token['email'])) {
            flash('auth_staff_gate', '1');
            redirect('/auth');
        }

        $tra = UserModel::googleLookup($token['sub'], $token['email']);

        /* AF-01 — đã liên kết từ trước: đăng nhập, không hỏi gì thêm. */
        if ($tra['code'] === 'login') {
            /* Lưới thứ hai: một google_id đã nối sẵn vào tài khoản nội bộ từ
               trước khi hai khu vực bị tách thì lượt vào không đi qua email,
               nên chốt bên trên không thấy nó. */
            if (UserModel::isStaff($tra['id'])) {
                flash('auth_staff_gate', '1');
                redirect('/auth');
            }

            AuthMiddleware::login($tra['id']);

            // Khoá TRUNG TÍNH 'site_success' để dải toast hiện được ở mọi đích
            // của BR-UC.USER.01-09 — kể cả trang chủ, nơi không có dải báo
            // riêng của khu tài khoản.
            flash('site_success', 'Đăng nhập thành công!');
            redirect($after);
        }

        /* AF-03 và ca tài khoản bị khoá. Cả hai đưa khách về màn ĐĂNG NHẬP:
           đó mới là chỗ họ cần tới, chứ không phải màn đăng ký. */
        if ($tra['code'] !== 'signup') {
            flash('auth_error', $tra['error']);
            redirect($tra['code'] === 'email_taken' ? '/auth' : $veAuth);
        }

        /*
         * AF-02 — chưa khớp gì cả. KHÔNG TẠO TÀI KHOẢN Ở ĐÂY.
         *
         * Cất thông tin Google vừa xác thực vào phiên rồi đưa khách sang màn
         * hoàn tất. `verified` đi theo để createFromGoogle() ghi đúng
         * `email_verified`; `name` chỉ để điền sẵn ô họ tên, khách sửa được.
         */
        $_SESSION['_google_pending'] = [
            'sub'      => (string) $token['sub'],
            'email'    => $token['email'] !== null ? strtolower(trim((string) $token['email'])) : null,
            'name'     => $token['name'] !== null ? trim((string) $token['name']) : null,
            'verified' => (bool) $token['verified'],
            'after'    => $after,
        ];

        redirect('/auth/dang-ky/google');
    }

    /**
     * Màn "Hoàn tất tạo tài khoản" sau khi chọn xong tài khoản Google.
     *
     * ─────────────────────────────────────────────────────────────────────
     * BỐN Ô, VÀ CHỈ HAI Ô LÀ BẮT BUỘC
     *
     *   Email             điền sẵn từ Google, KHOÁ. Nó là thứ nối tài khoản
     *                     này với Google; cho sửa thì khách gõ một địa chỉ
     *                     chưa ai xác minh vào cột `email_verified = 1`.
     *   Họ và tên         bắt buộc. Điền sẵn tên Google trả về, sửa được.
     *   Số điện thoại     KHÔNG bắt buộc — Google đã bảo chứng danh tính, bắt
     *                     thêm một khâu xác minh nữa là dựng lại đúng cái rào
     *                     mà cách đăng ký này sinh ra để tránh.
     *   Đồng ý Điều khoản bắt buộc — BR-UC.USER.01-05. Đây là ô tick THẬT của
     *                     cách đăng ký này, không còn mượn của form kia.
     *
     * KHÔNG CÓ Ô MẬT KHẨU: khách đăng nhập bằng Google. Muốn có mật khẩu thì
     * đi đường "Quên mật khẩu" như mọi khách khác — xem createFromGoogle().
     * ─────────────────────────────────────────────────────────────────────
     */
    public function googleSignup(): void
    {
        /* Đã đăng nhập thì màn này vô nghĩa — và nếu vừa đăng ký xong ở tab
           khác thì nó còn nguy hiểm: gửi form sẽ đòi tạo tài khoản thứ hai
           bằng đúng tài khoản Google ấy. */
        if (AuthMiddleware::customerId() !== null) {
            redirect('/tai-khoan');
        }

        $cho = self::googlePending();

        /* Vào thẳng địa chỉ này mà chưa đi qua Google. Không có gì để hoàn
           tất — trả về màn đăng ký, nơi có nút bắt đầu lại. */
        if ($cho === null) {
            redirect('/auth?tab=dang-ky');
        }

        $old    = $_SESSION['_google_old'] ?? [];
        $errors = $_SESSION['_google_errors'] ?? [];
        unset($_SESSION['_google_old'], $_SESSION['_google_errors']);

        $this->renderView('auth/google-signup', [
            'bareLayout' => true,
            'pageTitle'  => 'Hoàn tất tạo tài khoản — Vin Eyewear',
            'metaDesc'   => 'Hoàn tất tạo tài khoản Vin Eyewear bằng Google.',
            'pending'    => $cho,
            'old'        => is_array($old) ? $old : [],
            'errors'     => is_array($errors) ? $errors : [],
            'error'      => flash('auth_error'),
        ]);
    }

    /** Bấm "Đăng ký" ở màn hoàn tất — tài khoản ra đời tại đây. */
    public function googleSignupSubmit(): void
    {
        $this->requirePost('/auth/dang-ky/google');

        if (AuthMiddleware::customerId() !== null) {
            redirect('/tai-khoan');
        }

        $cho = self::googlePending();

        if ($cho === null) {
            flash('auth_error', 'Phiên đăng ký đã hết hạn. Vui lòng bấm "Tiếp tục với Google" lại.');
            redirect('/auth?tab=dang-ky');
        }

        $in = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'phone'     => trim((string) ($_POST['phone'] ?? '')),
            'dong_y'    => !empty($_POST['dong_y']),
        ];

        $loi = [];

        // BR-UC.USER.01-05, EF-12 — kiểm ở máy chủ, không tin `required`.
        if (!$in['dong_y']) {
            $loi['dong_y'] = 'Vui lòng đồng ý với Điều khoản và Chính sách để đăng ký.';
        }

        if ($in['full_name'] === '') {
            $loi['full_name'] = 'Vui lòng nhập họ tên.';
        } elseif (utf8Length($in['full_name']) > 120) {
            $loi['full_name'] = 'Họ tên quá dài (tối đa 120 ký tự).';
        }

        /* Ô SỐ ĐIỆN THOẠI ĐỂ TRỐNG LÀ HỢP LỆ, gõ sai thì không.
           "Không bắt buộc" nói về việc CÓ ĐIỀN HAY KHÔNG, không phải lời hứa
           rằng thứ điền vào sẽ được nhận bừa: một số sai lọt vào hồ sơ là
           cửa hàng gọi giao hàng vào số của người khác. */
        if ($in['phone'] !== '') {
            $phone = normalizePhone($in['phone']);

            if ($phone === null) {
                $loi['phone'] = 'Số điện thoại không hợp lệ. Vui lòng kiểm tra lại.';
            } elseif ((int) Database::fetchValue(
                'SELECT COUNT(*) FROM profiles WHERE phone = :p', ['p' => $phone]
            ) > 0) {
                $loi['phone'] = self::loiCoLink(
                    'Số điện thoại này đã được đăng ký.', '/auth', 'Đăng nhập'
                );
            }
        }

        if ($loi !== []) {
            /* Giữ lại chữ đã gõ và cú tick — quay về vì một ô sai mà mất cả
               ba là bắt làm lại những việc đã làm đúng. */
            $_SESSION['_google_old']    = $in;
            $_SESSION['_google_errors'] = $loi;
            redirect('/auth/dang-ky/google');
        }

        $ket = UserModel::createFromGoogle(
            (string) $cho['sub'],
            $cho['email'] ?? null,
            $in['full_name'],
            $in['phone'] !== '' ? $in['phone'] : null,
            !empty($cho['verified'])
        );

        if (!$ket['ok']) {
            /* Ba ca hỏng đều là ca "có người khác vừa chiếm mất" hoặc lỗi hệ
               thống — không phải thứ khách sửa được bằng cách gõ lại, nên bỏ
               phiên chờ và đưa họ về màn đăng ký với câu báo. */
            unset($_SESSION['_google_pending'], $_SESSION['_google_old'], $_SESSION['_google_errors']);

            flash('auth_error', $ket['error']);
            redirect(($ket['code'] ?? '') === 'email_taken' ? '/auth' : '/auth?tab=dang-ky');
        }

        $to = $this->signupTarget($cho['after'] ?? null);

        unset($_SESSION['_google_pending'], $_SESSION['_google_old'], $_SESSION['_google_errors']);

        // Bước 8 — BR-UC.USER.01-08: đăng ký xong đăng nhập luôn.
        AuthMiddleware::login($ket['id']);

        flash('site_success', 'Đăng ký tài khoản thành công!');
        redirect($to);
    }

    /*
     * ========================================================================
     * LIÊN KẾT GOOGLE TỪ TRANG HỒ SƠ
     *
     * findOrCreateGoogle() cố tình KHÔNG tự nối Google vào tài khoản trùng
     * email (AF-03), và câu báo của nhánh ấy chỉ khách sang đây: đăng nhập
     * bằng số điện thoại/mật khẩu trước, rồi tự tay nối Google vào.
     *
     * Chiều nối đảo ngược so với luồng đăng ký, và đó là toàn bộ lý do nó an
     * toàn: quyền vào tài khoản đã được chứng minh bằng mật khẩu TRƯỚC khi
     * Google được hỏi tới. Google ở đây không trả lời câu "ai là chủ", nó chỉ
     * cung cấp một `sub` để cất làm chìa thứ hai.
     * ========================================================================
     */

    /**
     * Bấm "Liên kết tài khoản Google" ở trang Hồ sơ — đẩy khách sang Google.
     *
     * Cùng khuôn với googleStart() nhưng để lại cờ `_google_link`, giữ chính
     * chuỗi `state` của lượt này. Cờ ấy là thứ DUY NHẤT phân biệt hai ngả ở
     * callback, và nó phải giữ `state` chứ không phải true/false: mở hai tab —
     * tab hồ sơ bấm "Liên kết", sau đó tab kia bấm "Tiếp tục với Google" —
     * thì tab sau ghi đè `_google_state`, nên callback của tab sau hợp lệ; một
     * cờ true/false sẽ theo nó vào và nối Google trong một lượt chưa hề đi qua
     * nút "Liên kết" nào.
     */
    public function linkGoogle(): void
    {
        $userId = AuthMiddleware::requireLogin('/tai-khoan?muc=ho-so');
        $this->requirePost('/tai-khoan?muc=ho-so');

        if (!GoogleAuth::isConfigured()) {
            flash('account_error', 'Đăng nhập bằng Google chưa được cấu hình.');
            redirect('/tai-khoan?muc=ho-so');
        }

        /* Hỏi lại trạng thái ngay trước khi đi, không tin cái nút vừa được
           bấm: trang có thể đã mở từ lâu, hoặc khách vừa nối xong ở tab khác.
           Đi một vòng sang Google rồi mới báo "đã liên kết rồi" là bắt người
           ta trả giá cho một câu trả lời đã có sẵn ở đây. */
        if (UserModel::googleLink($userId)['linked']) {
            flash('account_error', 'Tài khoản của bạn đã liên kết với Google rồi.');
            redirect('/tai-khoan?muc=ho-so');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['_google_link'] = $state;

        redirect(GoogleAuth::authUrl($state, '/tai-khoan?muc=ho-so'));
    }

    /**
     * Gỡ liên kết Google — POST kèm mật khẩu hiện tại.
     *
     * Vì sao phải hỏi mật khẩu cho một thao tác mà người đang đăng nhập lẽ ra
     * đủ quyền làm: xem khối chú thích của UserModel::unlinkGoogle(). Tóm
     * tắt — mật khẩu ở đây không chỉ để nhận mặt khách, nó là phép thử DUY
     * NHẤT trả lời được câu "gỡ xong còn lối nào vào không".
     */
    public function unlinkGoogle(): void
    {
        $userId = AuthMiddleware::requireLogin('/tai-khoan?muc=ho-so');
        $this->requirePost('/tai-khoan?muc=ho-so');

        $ket = UserModel::unlinkGoogle($userId, (string) ($_POST['mat_khau'] ?? ''));

        if (!$ket['ok']) {
            flash('account_error', $ket['error']);
            /* Trả về với form gỡ ĐANG MỞ: đóng nó lại thì câu báo lỗi đứng
               một mình ở đầu trang, cạnh một cái nút phải bấm lại mới thấy ô
               mật khẩu. Cùng lối với ?xoa=1 của khối Xoá tài khoản. */
            redirect('/tai-khoan?muc=ho-so&go-google=1');
        }

        flash('account_success', 'Đã gỡ liên kết tài khoản Google. Từ nay bạn đăng nhập '
                               . 'bằng số điện thoại hoặc email và mật khẩu.');
        redirect('/tai-khoan?muc=ho-so');
    }

    public function logout(): void
    {
        $this->requirePost('/');

        /* ĐÂY LÀ ĐƯỜNG RA CỦA KHÁCH, VÀ NAY CHỈ CÓ THỂ LÀ VẬY.
           Khu quản trị có đường riêng — /quan-tri/dang-xuat, xem
           AdminAuthController::logout().

           Trước đây ở đây có một nhánh "nếu là phiên nội bộ thì trả về cổng
           quản trị", làm lưới đỡ cho một tab để quên từ bản cũ. Nhánh đó nay
           vô nghĩa: request tới đường này chỉ mang cookie `vin_session`, nên
           logout() không có cách nào chạm tới phiên quản trị — nhân viên bấm
           Đăng xuất ở trang bán hàng chỉ mất phiên MUA HÀNG của họ, và vẫn
           đang đăng nhập ở /quan-tri. Đưa về trang chủ cửa hàng là đúng chỗ,
           vì đó chính là nơi họ vừa đăng xuất khỏi. */
        AuthMiddleware::logout();

        redirect('/');
    }

    // ========================================================================
    // TÀI KHOẢN
    // ========================================================================

    /**
     * Các mục của trang tài khoản, khoá là giá trị ?muc=.
     *
     * THỨ TỰ Ở ĐÂY LÀ THỨ TỰ HIỆN RA. app/views/auth/profile.php duyệt thẳng
     * mảng này chứ không vẽ tay từng mục, nên đổi thứ tự ở đây là đổi cột điều
     * hướng — không có chỗ thứ hai phải sửa theo.
     *
     * ─────────────────────────────────────────────────────────────────────
     * 'mat-khau' ĐÃ RỜI KHỎI ĐÂY — 2026-09-10, theo BR-UC.USER.05-02/03
     *
     * Đổi mật khẩu không còn là một MỤC (một URL, một lần tải trang) mà là
     * KHU VỰC THỨ BA ngay trong trang Hồ sơ, cùng trang với Thông tin cá nhân
     * và Sổ địa chỉ. Đặc tả cấm dùng tab hoặc chuyển trang giữa ba khu vực.
     *
     * File app/views/auth/account/mat-khau.php vẫn còn, nay được ho-so.php gọi
     * bằng partial(). Liên kết cũ ?muc=mat-khau KHÔNG vỡ: nó thành một giá trị
     * lạ, và profile() đưa mọi giá trị lạ về mục mặc định — chính là 'ho-so'.
     *
     * 'do-mat' (Thông số đo mắt) ĐÃ GỠ. Số đo vẫn nằm trong
     * customer_prescriptions và vẫn do kỹ thuật viên nhập ở
     * /quan-tri/khach-hang; chỉ mục tự xem/tự khai phía khách là bỏ.
     *
     * 'dia-chi' (Sổ địa chỉ) cũng không có mục riêng, nhưng vì lý do NGƯỢC
     * LẠI: sổ đã quay lại (UC-USER-05, Khu vực 2) và nằm ngay trong trang Hồ
     * sơ. Bốn tuyến `tai-khoan/dia-chi/*` ở config/routes.php phục vụ nó —
     * xem AddressModel.
     *
     * NHÃN LẤY THEO ĐÚNG CHỮ TRONG ĐẶC TẢ ("Hồ sơ cá nhân", "Đơn hàng"), vì
     * chúng vừa là nhãn trên cột điều hướng vừa là tiêu đề thẻ trình duyệt.
     * 'lich-hen' không có trong đặc tả nên giữ nguyên chữ cũ.
     */
    private const SECTIONS = [
        'ho-so'    => 'Hồ sơ cá nhân',
        'don-hang' => 'Đơn hàng',
        'lich-hen' => 'Lịch hẹn của tôi',
    ];

    /**
     * Mục mở sẵn khi vào /tai-khoan TRẦN (không kèm ?muc=).
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO LÀ 'ho-so' CHỨ KHÔNG PHẢI 'don-hang'
     *
     * Bản thiết kế vẽ trạng thái đầu là danh sách đơn hàng, và trước đây mặc
     * định bám theo đó. Nhưng địa chỉ trần này KHÔNG PHẢI chỗ người ta tới để
     * xem đơn — mọi luồng cần đơn hàng đều tự nói ra: trang xác nhận đơn, biên
     * nhận, thẻ đơn, và ngay cả mục "Đơn hàng của tôi" trong menu người dùng
     * đều trỏ '?muc=don-hang'.
     *
     * Thứ trỏ tới đây là những chỗ mang nghĩa "tài khoản của tôi":
     *
     *     menu người dùng -> "Thông tin tài khoản"
     *     trang 403       -> "Tài khoản của tôi"
     *
     * (Đăng nhập xong KHÔNG còn rơi vào đây: BR-UC.USER.02-06 đưa khách về
     * trang chủ hoặc trang họ đang dở — xem HOME_AFTER_LOGIN.)
     *
     * Để mặc định ở 'don-hang' thì mục đầu menu hứa "Thông tin tài khoản" mà
     * mở ra danh sách đơn — và nó rơi đúng vào chỗ mục NGAY DƯỚI nó đã dẫn
     * tới. Hai dòng menu liền nhau, hai nhãn khác nhau, cùng một trang.
     * ─────────────────────────────────────────────────────────────────────
     */
    private const DEFAULT_SECTION = 'ho-so';

    public function profile(): void
    {
        $section = (string) ($_GET['muc'] ?? '');
        $known   = isset(self::SECTIONS[$section]);

        /* ĐỌC ?muc= TRƯỚC KHI ĐÒI ĐĂNG NHẬP, để còn mang nó theo.
           Mặc định requireLogin() dựng đường quay lại bằng currentPath(), mà
           hàm đó cắt query string — nên bấm một liên kết tới Sổ địa chỉ lúc
           chưa đăng nhập thì đăng nhập xong lại rơi vào mục mặc định.
           Chỉ mang theo mục CÓ THẬT: ?muc= gõ tay được, và một giá trị lạ thì
           đằng nào cũng bị đẩy về mục mặc định ngay dưới đây. */
        $userId = AuthMiddleware::requireLogin($known ? '/tai-khoan?muc=' . $section : null);

        if (!$known) {
            /*
             * ─────────────────────────────────────────────────────────────────
             * LUÔN LÀ 'ho-so' — UC-USER-05, Main Flow bước 1 & 5
             *
             * Đặc tả nói thẳng: khách chọn biểu tượng cá nhân hoặc menu "Tài
             * khoản" -> hệ thống hiển thị trang "Hồ sơ cá nhân", và mục đó trên
             * cột điều hướng được highlight. Cả hai lối vào ("Thông tin tài
             * khoản" ở menu người dùng, "Tài khoản của tôi" ở trang 403) đều
             * trỏ tới /tai-khoan trần, tức đúng nhánh này.
             *
             * ⚠ CHỖ NÀY ĐÃ ĐÈ LÊN Q72 (04/09/2026) — nếu BA muốn giữ Q72 thì
             * đây là dòng phải sửa, và đặc tả UC-USER-05 phải ghi nhận nó.
             *
             * Q72 chốt: khách đã có đủ họ tên + số điện thoại xác thực
             * (UserModel::hoSoDayDu()) thì vào /tai-khoan trần sẽ rơi thẳng
             * vào 'don-hang', đỡ một cú bấm. Luật ấy mâu thuẫn trực tiếp với
             * Main Flow ở trên, nên nó tạm nghỉ:
             *
             *     $section = UserModel::hoSoDayDu($userId) ? 'don-hang' : 'ho-so';
             *
             * UserModel::hoSoDayDu() GIỮ NGUYÊN dù đây là chỗ gọi cuối cùng
             * của nó. Nó là một luật nghiệp vụ viết đúng một lần (và sẽ đổi khi
             * Zalo OTP lên), không phải mã chết theo nghĩa thường — gỡ đi rồi
             * bật lại Q72 là phải viết lại điều kiện từ đầu, ở một chỗ nào đó
             * không phải UserModel.
             *
             * ?muc= có thật thì KHÔNG đụng tới: người bấm thẳng vào một mục là
             * người đã biết mình muốn gì.
             * ─────────────────────────────────────────────────────────────────
             */
            $section = self::DEFAULT_SECTION;
        }

        /*
         * Số hiện trên huy hiệu ở cột trái. Cột trái vẽ ở CẢ NĂM mục nên hai
         * câu đếm này chạy mọi lần — nhưng chúng là COUNT(*) có chỉ mục, rẻ
         * hơn nhiều so với việc nạp cả danh sách chỉ để đếm.
         *
         * ─────────────────────────────────────────────────────────────────
         * HUY HIỆU ĐẾM VIỆC CÒN PHẢI THEO DÕI, KHÔNG ĐẾM LỊCH SỬ
         *
         * Trước đây 'don-hang' dùng OrderModel::count() trần, tức đếm MỌI đơn
         * từng đặt. Con số ấy chỉ tăng, không bao giờ giảm, nên nó không nói
         * được điều gì đáng làm — khách mua quen vài năm sẽ thấy một số hai
         * chữ số nằm đó vĩnh viễn.
         *
         * Nay cả hai đều đếm thứ còn đang chạy, và cùng biến mất khi xong
         * việc: đơn hoàn tất hoặc huỷ thì rơi khỏi countActive(); lịch đã đo,
         * đã huỷ, hoặc quá ngày thì rơi khỏi countUpcoming(). Chi tiết từng
         * vế nằm ở chú thích của hai hàm đó.
         *
         * Ba mục còn lại không có huy hiệu: hồ sơ, sổ địa chỉ và đổi mật
         * khẩu không phải hàng đợi, đếm ở đó không nói thêm được gì.
         * ─────────────────────────────────────────────────────────────────
         */
        $counts = [
            'don-hang' => OrderModel::countActive($userId),
            'lich-hen' => BookingModel::countUpcoming($userId),
        ];

        /*
         * ─────────────────────────────────────────────────────────────────────
         * EF-01 — KHÔNG TRUY XUẤT ĐƯỢC HỒ SƠ
         *
         * Hai đường dẫn tới đây: câu truy vấn ném (mất kết nối, bảng hỏng), và
         * câu truy vấn chạy được nhưng không có dòng nào — `profiles` thiếu
         * dòng của tài khoản này. Cả hai đều là "không đọc được hồ sơ" dưới mắt
         * khách, nên cả hai ra cùng một trang.
         *
         * KHÔNG chuyển hướng đi đâu cả. Đặc tả cho phép đưa khách về Đăng nhập
         * hoặc Trang chủ ở trường hợp thất bại, nhưng hai chỗ ấy dành cho phiên
         * hết hạn (BR-05, đã do AuthMiddleware::requireLogin lo ở trên). Ở đây
         * phiên vẫn tốt — đá một khách đang đăng nhập về trang chủ thì họ chỉ
         * bấm lại vào đúng chỗ vừa văng ra, và không ai đọc được câu báo lỗi.
         *
         * $profile = null đi tiếp vào view: app/views/auth/profile.php vẫn dựng
         * cột điều hướng như thường rồi in câu báo ở vùng nội dung, nên Đơn
         * hàng và Lịch hẹn vẫn vào được.
         *
         * CHI TIẾT KỸ THUẬT VÀO error_log, KHÔNG RA MÀN HÌNH — BR-UC.USER.05-05.
         * ─────────────────────────────────────────────────────────────────────
         */
        try {
            $profile = UserModel::profile($userId);
        } catch (Throwable $e) {
            error_log('[AuthController::profile] không đọc được hồ sơ ' . $userId
                . ': ' . $e->getMessage());

            $profile = null;
        }

        if ($profile === null) {
            error_log('[AuthController::profile] không có dòng profiles cho ' . $userId);
        }

        $this->renderView('auth/profile', [
            'pageTitle' => self::SECTIONS[$section] . ' — Vin Eyewear',
            'metaDesc'  => 'Trang tài khoản Vin Eyewear: hồ sơ, sổ địa chỉ, đơn hàng '
                         . 'và lịch hẹn của bạn.',
            'sections'  => self::SECTIONS,
            'section'   => $section,
            'counts'    => $counts,
            'profile'   => $profile,
            'roles'     => UserModel::roles($userId),
            'genders'   => UserModel::GENDERS,
            'success'   => flash('account_success'),
            'error'     => flash('account_error'),
            /* Hồ sơ hỏng thì KHÔNG chạy sectionData(): view không require mục
               nào cả, nên mọi câu truy vấn nó gọi đều là công cốc — và một
               trong số chúng nhiều khả năng hỏng vì cùng lý do. */
        ] + ($profile !== null ? $this->sectionData($section, $userId) : []));

        /* $_SESSION['_old_address'] không còn ai ghi vào: form địa chỉ riêng
           đã gỡ cùng sổ địa chỉ. Vẫn dọn một lần ở đây để phiên của khách
           đang mở dở lúc deploy không mang theo một khoá chết. Bỏ được dòng
           này sau khi mọi phiên cũ đã hết hạn (tối đa 24 giờ — xem
           App::startSession). */
        unset($_SESSION['_old_address']);
    }

    /**
     * Dữ liệu RIÊNG của một mục.
     *
     * Tách khỏi profile() để mỗi lần mở trang chỉ truy vấn đúng thứ đang hiện.
     * Trước đây trang tài khoản nạp một lượt hồ sơ + khúc xạ + đơn hàng + lịch
     * hẹn dù khách chỉ nhìn một khối; nay có thêm sổ địa chỉ và ưu đãi thì
     * cách cũ thành sáu truy vấn cho một mục được xem.
     */
    private function sectionData(string $section, string $userId): array
    {
        switch ($section) {
            case 'ho-so':
                /*
                 * ─────────────────────────────────────────────────────────────
                 * BA KHU VỰC CỦA TRANG HỒ SƠ CẦN GÌ
                 *
                 * Hỏi ở đây chứ không để view gọi model: mục này là mục duy
                 * nhất vẽ chúng, nên các câu hỏi không nên chạy ở hai mục còn
                 * lại.
                 *
                 * ?sua= THẮNG ?them= — và đây không phải chuyện ưu tiên tuỳ ý.
                 * Cả hai form địa chỉ đều mang khối [data-vnaddr], mà
                 * address-picker.js tìm nó bằng querySelector: MỘT khối cho cả
                 * trang. Hai form cùng mở thì cái thứ hai còn trơ hai ô gõ tay,
                 * không có lỗi nào để thấy. Xem khối đầu file JS ấy.
                 *
                 * findOwned() trả null khi mã lạ hoặc địa chỉ của người khác,
                 * và khi đó view chỉ đơn giản không mở form nào — không cần một
                 * câu báo riêng, vì đường duy nhất tới một mã lạ là gõ tay.
                 * ─────────────────────────────────────────────────────────────
                 */
                $suaDiaChi = isset($_GET['sua'])
                    ? AddressModel::findOwned((string) $_GET['sua'], $userId) : null;

                return [
                    'google'       => UserModel::googleLink($userId),
                    'addresses'    => AddressModel::forUser($userId),
                    'editing'      => $suaDiaChi,
                    'themDiaChi'   => isset($_GET['them']),
                    'nhanDiaChi'   => AddressModel::NHAN,
                    'toiDaDiaChi'  => AddressModel::TOI_DA,
                    /* Chế độ của Khu vực 1: xem (mặc định) hay sửa. Xem khối
                       "XEM TRƯỚC, BẤM MỚI SỬA" ở đầu account/ho-so.php. */
                    'suaHoSo'      => isset($_GET['sua-ho-so']),
                ];

            case 'don-hang':
                $orders = OrderModel::forUser($userId);
                $tab    = (string) ($_GET['loc'] ?? '');

                if (!isset(OrderModel::STATUSES[$tab])) {
                    $tab = '';   // '' = thẻ "Tất cả"
                }

                // LỌC TRONG PHP, không phải bằng câu SQL thứ hai: dải thẻ lọc
                // hiện số đơn của TỪNG trạng thái, nên danh sách đầy đủ đằng
                // nào cũng phải có sẵn. Lọc lại bằng SQL là đọc hai lần cùng
                // một thứ.
                $shown = $tab === ''
                    ? $orders
                    : array_values(array_filter($orders, static fn ($o) => $o['status'] === $tab));

                return [
                    'orders'    => $shown,
                    'tab'       => $tab,
                    // ?don=<mã> mở rộng đúng một thẻ đơn. Không cần kiểm mã có
                    // thật hay không: view chỉ so nó với mã của các đơn đã lọc
                    // theo user_id, mã lạ thì không thẻ nào khớp.
                    'expanded'  => (string) ($_GET['don'] ?? ''),
                    'tabCounts' => array_count_values(array_column($orders, 'status')),
                    'total'     => count($orders),
                    'items'     => OrderModel::itemsForOrders(array_column($shown, 'id')),
                    'history'   => OrderModel::historyForOrders(array_column($shown, 'id')),
                    'statuses'  => OrderModel::STATUSES,
                    // Nhãn trạng thái TIỀN. Từ khi có đặt cọc thì nó có ba nấc
                    // (chưa trả · đã cọc · đã trả đủ), nên view không tự đoán
                    // được bằng một phép so với 'paid' nữa.
                    'payStatuses' => OrderModel::PAYMENT_STATUSES,
                    // Tài khoản nhận chuyển khoản — thẻ đơn chuyển khoản chưa
                    // thanh toán in thẳng số tài khoản + mã đơn làm nội dung
                    // chuyển khoản. Xem config/company.php.
                    'bank'      => config('company.bank', []),
                ];

            case 'lich-hen':
                /*
                 * ?doi=<mã lịch> mở form đổi giờ NGAY TRONG thẻ lịch hẹn đó —
                 * cùng lối với ?sua= của sổ địa chỉ và ?don= của đơn hàng, nên
                 * gửi link được và F5 không mất chỗ.
                 *
                 * findOwned trả null khi mã lạ hoặc lịch của người khác, và khi
                 * đó view chỉ đơn giản không mở form nào.
                 */
                $editing = isset($_GET['doi'])
                    ? BookingModel::findOwned((string) $_GET['doi'], $userId) : null;

                /* Không còn $slotDate / $freeSlots: form đổi lịch chỉ hỏi NGÀY
                   nên không phải hỏi máy chủ giờ trống của ngày nào cả — xem
                   khối chú thích đầu app/views/auth/account/_doi-lich.php. */
                $appointments = BookingModel::forUser($userId);

                /*
                 * "Vì sao lịch này không sửa được nữa", tính SẴN cho từng lịch.
                 *
                 * Dựng ở đây chứ không để view gọi BookingModel::changeBlocker():
                 * view của trang này không gọi model ở đâu khác, và quan trọng hơn
                 * — đây đúng là hàm mà cancelOwned/rescheduleOwned gọi lại trước
                 * khi ghi, nên nút hiện ra và phép kiểm lúc ghi không thể lệch.
                 */
                $blockers = [];

                foreach ($appointments as $appointment) {
                    $blockers[$appointment['code']] = BookingModel::changeBlocker($appointment);
                }

                return [
                    'appointments'    => $appointments,
                    'bookingStatuses' => BookingModel::STATUSES,
                    'blockers'        => $blockers,
                    'editing'         => $editing,
                ];

            default:
                /* Không còn mục nào rơi vào đây từ khi 'mat-khau' rời SECTIONS,
                   nhưng nhánh này phải ở lại: switch không có default thì thêm
                   một mục mới mà quên viết case là một cảnh báo "undefined
                   variable" trong view, không phải một lỗi đọc ra được. */
                return [];
        }
    }

    /**
     * Lưu Khu vực 1 — Thông tin cá nhân.
     *
     * KHÔNG CÒN NHẬN Ô ĐỊA CHỈ NÀO. Tới 2026-09-10 form này ôm cả năm cột địa
     * chỉ; nay địa chỉ là một SỔ riêng ngay dưới (UC-USER-05, Khu vực 2) với
     * bốn tuyến của riêng nó. UserModel::updateProfile() vẫn nhận năm cột ấy,
     * nhưng người gọi duy nhất còn lại là AddressModel::dongBoHoSo().
     *
     * Thêm một ô địa chỉ trở lại đây là dựng hai nguồn sự thật cho cùng một dữ
     * liệu: cái lưu sau ghi đè cái lưu trước, âm thầm, và khách không có cách
     * nào biết nơi nhận hàng của mình vừa đổi.
     */
    public function updateProfile(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost(self::VE_HO_SO . '#thong-tin');

        /* EMAIL TRƯỚC, và dừng lại nếu nó hỏng.
           Nó nằm ở bảng `users` nên là một lệnh ghi riêng — xem
           UserModel::updateEmail(). Chạy trước vì đây là lỗi hay gặp nhất của
           form này (địa chỉ đã thuộc về tài khoản khác), và ghi hồ sơ xong rồi
           mới báo lỗi email thì màn hình nói "không lưu được" trong khi bốn ô
           kia đã lưu rồi.

           Lỗi thì quay lại với ?sua-ho-so=1: form phải còn MỞ để khách sửa
           đúng cái ô vừa bị chê. Về chế độ xem là bắt họ bấm "Chỉnh sửa" lần
           nữa rồi gõ lại từ đầu. */
        $email = UserModel::updateEmail($userId, (string) ($_POST['email'] ?? ''));

        if (!$email['ok']) {
            flash('account_error', $email['error']);
            redirect(self::VE_HO_SO . '&sua-ho-so=1#thong-tin');
        }

        $result = UserModel::updateProfile($userId, [
            'full_name'     => trim((string) ($_POST['full_name'] ?? '')),
            'phone'         => trim((string) ($_POST['phone'] ?? '')),
            'gender'        => (string) ($_POST['gender'] ?? ''),
            'date_of_birth' => ($_POST['date_of_birth'] ?? '') !== ''
                ? (string) $_POST['date_of_birth'] : null,
        ]);

        if (!$result['ok']) {
            flash('account_error', $result['error']);
            redirect(self::VE_HO_SO . '&sua-ho-so=1#thong-tin');
        }

        /* Lưu xong về CHẾ ĐỘ XEM (không kèm ?sua-ho-so): khách vừa xong việc,
           và thứ họ muốn thấy tiếp theo là thông tin mới đã vào đúng chỗ. */
        flash('account_success', 'Đã cập nhật hồ sơ.');
        redirect(self::VE_HO_SO . '#thong-tin');
    }

    // ========================================================================
    // SỔ ĐỊA CHỈ — UC-USER-05, Khu vực 2
    //
    // Bốn hàm, một hình dạng: lấy tham số -> gọi AddressModel -> flash kết quả
    // -> quay về #so-dia-chi. Mọi luật (đúng chủ, đủ trường, ai thay chân địa
    // chỉ mặc định vừa xoá, đồng bộ sang `profiles`) nằm trong model, không có
    // vế nào ở đây — xem app/models/AddressModel.php.
    //
    // CẢ BỐN ĐỀU LÀ POST. GET thì một thẻ <img src="/tai-khoan/dia-chi/xoa?id=…">
    // trên trang bất kỳ cũng xoá được địa chỉ của khách đang đăng nhập; ô
    // _token chặn nốt đường POST giả. Xem requirePost().
    // ========================================================================

    /** Đường quay về trang Hồ sơ. Một hằng vì mười mấy chỗ dưới đây dùng nó. */
    private const VE_HO_SO = '/tai-khoan?muc=ho-so';

    public function addAddress(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost(self::VE_HO_SO . '#so-dia-chi');

        $result = AddressModel::them($userId, $_POST);

        if (!$result['ok']) {
            flash('account_error', $result['error']);
            /* Mở lại form THÊM, không về danh sách: dữ liệu khách vừa gõ mất
               rồi (form này không giữ giá trị cũ), nhưng ít nhất họ không phải
               tìm lại nút "Thêm địa chỉ mới" trước khi gõ lại. */
            redirect(self::VE_HO_SO . '&them=1#them-dia-chi');
        }

        flash('account_success', 'Đã thêm địa chỉ vào sổ.');
        redirect(self::VE_HO_SO . '#so-dia-chi');
    }

    public function updateAddress(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost(self::VE_HO_SO . '#so-dia-chi');

        $id     = (string) ($_POST['id'] ?? '');
        $result = AddressModel::sua($id, $userId, $_POST);

        if (!$result['ok']) {
            flash('account_error', $result['error']);
            redirect(self::VE_HO_SO . '&sua=' . rawurlencode($id) . '#sua-dia-chi');
        }

        flash('account_success', 'Đã cập nhật địa chỉ.');
        redirect(self::VE_HO_SO . '#so-dia-chi');
    }

    public function deleteAddress(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost(self::VE_HO_SO . '#so-dia-chi');

        $result = AddressModel::xoa((string) ($_POST['id'] ?? ''), $userId);

        if (!$result['ok']) {
            flash('account_error', $result['error']);
        } else {
            flash('account_success', 'Đã xoá địa chỉ khỏi sổ.');
        }

        redirect(self::VE_HO_SO . '#so-dia-chi');
    }

    public function setDefaultAddress(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost(self::VE_HO_SO . '#so-dia-chi');

        $result = AddressModel::datMacDinh((string) ($_POST['id'] ?? ''), $userId);

        if (!$result['ok']) {
            flash('account_error', $result['error']);
        } else {
            flash('account_success',
                'Đã đặt địa chỉ mặc định. Địa chỉ này sẽ được điền sẵn khi bạn đặt hàng.');
        }

        redirect(self::VE_HO_SO . '#so-dia-chi');
    }

    /**
     * Đổi ảnh đại diện — nút "Chọn ảnh" trong mục Hồ sơ.
     *
     * Toàn bộ phần kiểm tra file nằm trong AvatarStorage; ở đây chỉ còn thứ
     * tự các bước: cất ảnh mới -> ghi CSDL -> xoá ảnh cũ. Xoá SAU CÙNG, vì
     * xoá trước mà bước ghi CSDL hỏng thì khách mất ảnh cũ lẫn ảnh mới.
     */
    public function updateAvatar(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=ho-so');

        $stored = AvatarStorage::store($_FILES['avatar'] ?? []);

        if (!$stored['ok']) {
            flash('account_error', $stored['error']);
            redirect('/tai-khoan?muc=ho-so');
        }

        $old    = UserModel::profile($userId)['avatar_path'] ?? null;
        $result = UserModel::updateProfile($userId, ['avatar_path' => $stored['path']]);

        if (!$result['ok']) {
            AvatarStorage::remove($stored['path']);
            flash('account_error', $result['error']);
            redirect('/tai-khoan?muc=ho-so');
        }

        AvatarStorage::remove($old);

        flash('account_success', 'Đã cập nhật ảnh đại diện.');
        redirect('/tai-khoan?muc=ho-so');
    }

    // ========================================================================
    // LỊCH HẸN — KHÁCH TỰ ĐỔI / HUỶ
    //
    // Mọi luật (đúng chủ, trạng thái nào được sửa, hạn trước giờ hẹn, khung giờ
    // còn trống) nằm trong BookingModel. Hai hàm dưới đây chỉ lấy tham số, gọi
    // model, rồi nói lại kết quả — xem khối "KHÁCH TỰ ĐỔI / HUỶ LỊCH" ở đó.
    // ========================================================================

    public function cancelBooking(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=lich-hen');

        $code   = (string) ($_POST['code'] ?? '');
        $result = BookingModel::cancelOwned($code, $userId);

        /* KHÔNG ĐẨY ZALO nữa — SRS v2.1.0, G12. Cửa hàng theo dõi lịch hẹn
           bằng huy hiệu trên thanh bên khu quản trị. */

        flash(
            $result['ok'] ? 'account_success' : 'account_error',
            $result['ok']
                ? 'Đã huỷ lịch hẹn.'
                : $result['error']
        );

        redirect('/tai-khoan?muc=lich-hen');
    }

    /**
     * Khách tự huỷ đơn của mình (POST /tai-khoan/don-hang/huy) — UC-02.
     *
     * Mọi phép kiểm nằm ở OrderModel::khachHuy(): view đã ẩn nút với đơn không
     * đủ điều kiện, nhưng mã đơn nằm ngay trong HTML của trang đó và một tab mở
     * từ trước khi nhân viên đổi trạng thái vẫn còn nút cũ (E3 của UC-02).
     */
    public function cancelOrder(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=don-hang');

        $code = (string) ($_POST['code'] ?? '');

        $ket = OrderModel::khachHuy(
            $code,
            $userId,
            (string) ($_POST['ly_do'] ?? ''),
            (string) ($_POST['ly_do_khac'] ?? '')
        );

        if (!$ket['ok']) {
            /* MỞ LẠI ĐÚNG HỘP XÁC NHẬN VỪA BỊ TỪ CHỐI — `&huy=<mã>`.

               Không có tham số này thì $moHuy sai với mọi thẻ đơn, hộp xác nhận
               đóng lại, và khách đọc một dòng đỏ ở đầu trang mà không còn thấy
               cái form nó nói về. Lỗi hay gặp nhất ở đây là chọn "Lý do khác"
               rồi bỏ trống ô ghi rõ — tức khách phải tìm lại đơn, bấm lại Huỷ
               đơn, chọn lại lý do, chỉ để sửa một ô.

               Cùng lối với saveAddress (`?sua=`) và rescheduleBooking (`?doi=`). */
            flash('account_error', $ket['error'] ?? 'Không huỷ được đơn hàng.');
            redirect('/tai-khoan?muc=don-hang&huy=' . rawurlencode($code));
        }

        /* NÓI RÕ CHUYỆN TIỀN NGAY TRONG CÂU BÁO THÀNH CÔNG.

           Khách vừa huỷ một đơn đã đặt cọc thì câu hỏi tiếp theo trong đầu họ
           là "tiền của tôi thì sao". Bắt họ đi tìm câu trả lời ở một trang khác
           là để họ gọi điện cho cửa hàng — đúng việc mà cả ca dùng này sinh ra
           để khỏi phải làm. */
        flash('account_success', $ket['refund'] !== null
            ? 'Đã huỷ đơn ' . ($ket['code'] ?? '') . '. Cửa hàng sẽ xem xét hoàn tiền cọc '
              . 'và liên hệ lại với bạn.'
            : 'Đã huỷ đơn ' . ($ket['code'] ?? '') . '.');

        redirect('/tai-khoan?muc=don-hang');
    }

    /**
     * Khách tự yêu cầu xoá tài khoản (POST /tai-khoan/xoa) — UC-01.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * BA Ô PHẢI ĐỦ: MẬT KHẨU, TÍCH ĐỒNG Ý, VÀ (KHI CÓ LỊCH HẸN) XÁC NHẬN LẦN HAI
     *
     * Ô tích đồng ý kiểm Ở ĐÂY chứ không chỉ bằng `required` trong HTML: một cú
     * POST dựng tay không đi qua trình duyệt. Với một thao tác không lùi lại
     * được bằng một cú bấm thì lớp chặn ở máy chủ là lớp duy nhất đáng tin.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function deleteAccount(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=ho-so');

        if (($_POST['dong_y'] ?? '') !== '1') {
            flash('account_error', 'Vui lòng tích ô xác nhận trước khi xoá tài khoản.');
            redirect('/tai-khoan?muc=ho-so&xoa=1');
        }

        $ket = CustomerModel::khachTuXoa(
            $userId,
            (string) ($_POST['mat_khau'] ?? ''),
            ($_POST['xac_nhan_lich'] ?? '') === '1'
        );

        if (!$ket['ok']) {
            /* CẢNH BÁO KHÁC TỪ CHỐI.
 
               `canhBao` là ca lịch hẹn sắp tới (E4): khách vẫn xoá được, chỉ
               cần bấm thêm một lần. Mở lại form kèm cờ `?canh-bao=1` để nó vẽ
               nút xác nhận lần hai thay vì chỉ in một dòng đỏ rồi thôi. */
            if (isset($ket['canhBao'])) {
                flash('account_error', $ket['canhBao']);
                redirect('/tai-khoan?muc=ho-so&xoa=1&canh-bao=1');
            }

            flash('account_error', $ket['error'] ?? 'Không xoá được tài khoản.');
            redirect('/tai-khoan?muc=ho-so&xoa=1');
        }

        /* CHẤM DỨT PHIÊN NGAY.
 
           Tài khoản đã ở trạng thái xoá mềm nên requireLogin() sẽ chặn ở lượt
           bấm sau, nhưng để phiên sống thêm một nhịp nghĩa là khách bấm Xoá
           xong vẫn thấy trang tài khoản của mình — đúng thứ làm người ta tưởng
           thao tác chưa chạy và bấm lại. */
        AuthMiddleware::logout();

        /* DẢI BÁO TOÀN SITE, không phải một khoá tự đặt.

           Bản đầu dùng flash('success', …) — một khoá KHÔNG CÓ AI ĐỌC: khung
           trang chỉ lấy dải báo qua BaseController::toastFromFlash(). Khách bấm
           một thao tác không lùi lại được rồi bị đưa về trang chủ đã đăng xuất,
           không một chữ nào xác nhận nó đã chạy — đúng thứ làm người ta tưởng
           hỏng rồi bấm lại (mà lần này họ không đăng nhập được nữa).

           'site_success' chứ không 'cart_success': dòng này không dính gì tới
           giỏ hàng, và toastFromFlash() nay đọc khoá trung tính ấy trước. */
        flash('site_success', 'Đã tiếp nhận yêu cầu xoá tài khoản. Cảm ơn bạn đã dùng Vin Eyewear.');
        redirect('/');
    }

    public function rescheduleBooking(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=lich-hen');

        $code   = (string) ($_POST['code'] ?? '');
        $result = BookingModel::rescheduleOwned(
            $code,
            $userId,
            (string) ($_POST['date'] ?? '')
        );

        if ($result['ok']) {
            /* KHÔNG ĐẨY ZALO nữa — SRS v2.1.0, G12. */
            flash('account_success', 'Đã đổi ngày hẹn. Cửa hàng sẽ gọi xác nhận lại.');
            redirect('/tai-khoan?muc=lich-hen');
        }

        /*
         * Lỗi thì MỞ LẠI form ở đúng lịch đó (?doi=<mã>), chứ không đẩy về danh
         * sách: khách vừa chọn dở, đóng form lại là bắt họ bắt đầu từ đầu.
         * Chuyển hướng chứ không render tại chỗ để F5 không gửi lại POST.
         *
         * Không mang theo &ngay= nữa — tham số đó từng dùng để máy chủ dựng lại
         * danh sách giờ trống của ngày khách đang xem, mà nay form không có
         * danh sách nào để dựng.
         */
        flash('account_error', $result['error']);

        redirect('/tai-khoan?muc=lich-hen&doi=' . rawurlencode($code));
    }

    // ========================================================================
    // MUA LẠI
    // ========================================================================

    /**
     * Nút "Mua lại" trên đơn đã hoàn tất hoặc đã huỷ.
     *
     * Đổ lại các dòng hàng của đơn cũ vào giỏ rồi đưa khách sang trang giỏ
     * hàng để họ tự xem lại trước khi đặt — KHÔNG đặt đơn mới ngay. Giá và
     * tồn kho có thể đã khác hẳn so với lần mua trước.
     *
     * Sản phẩm nào đã bị gỡ hoặc hết hàng thì bỏ qua và nói rõ số lượng bỏ
     * qua, chứ không im lặng: khách bấm "Mua lại" mà giỏ ra ít hơn kỳ vọng
     * cần biết vì sao.
     */
    public function reorder(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=don-hang');

        $order = OrderModel::findByCode((string) ($_POST['code'] ?? ''), $userId);

        // findByCode cho lọt đơn khách vãng lai (user_id NULL). Trang tài khoản
        // thì không: chỉ đơn của CHÍNH tài khoản đang đăng nhập.
        if ($order === null || $order['user_id'] !== $userId) {
            flash('account_error', 'Không tìm thấy đơn hàng.');
            redirect('/tai-khoan?muc=don-hang');
        }

        /* ─────────────────────────────────────────────────────────────────────
           MUA LẠI PHẢI DỰNG LẠI ĐÚNG DÒNG HÀNG CŨ — FR-HS-10

           Bản trước ghi vào giỏ đúng một khoá: ['quantity' => n], dưới khoá là
           product_id trần. Ba thứ mất trắng mỗi lần bấm "Mua lại":

             · TICK. Dòng giỏ không có 'selected' nên lines() đọc ra false —
               khách bấm Mua lại, sang giỏ, và không món nào được chọn. Nút
               Thanh toán không làm gì cả, và không có chữ nào nói vì sao.
             · PHƯƠNG ÁN màu/cỡ. Khoá là product_id trần nên hai biến thể khác
               nhau của cùng mặt hàng đè lên nhau, và biến thể đã mua thì biến
               mất — khách mua lại chiếc gọng đen size 52 và nhận về "mặc định".
             · TRÒNG. lens_id, kiểu tròng và số đo đều không được chép, nên một
               đơn kính cận mua lại thành một cái gọng không tròng.

           Nay dựng đúng khuôn dòng giỏ mà CartController::add() sinh ra, và
           dùng chính CartController::key() để tính khoá — chép luật khoá sang
           đây là tạo ra chỗ cho hai bên lệch nhau.

           BIẾN THỂ KHÔNG CÒN BÁN thì bỏ QUA CẢ DÒNG, không lùi về sản phẩm
           trần: khách chọn màu đó vì họ muốn màu đó, và lặng lẽ đổi sang màu
           khác là một cách làm sai đơn mà không ai kịp nhận ra.
           ───────────────────────────────────────────────────────────────────── */
        $added   = 0;
        $skipped = 0;
        $mattrong = 0;

        foreach (OrderModel::items($order['id']) as $line) {
            $product = $line['product_id'] === null ? null : ProductModel::find($line['product_id']);
            $qty     = (int) $line['quantity'];

            if ($product === null || (int) $product['is_visible'] !== 1
                || !ProductModel::inStock($product, $qty)) {
                $skipped++;
                continue;
            }

            $variantId = $line['variant_id'] ?? null;
            $variant   = null;

            if ($variantId !== null) {
                $variant = VariantModel::findForProduct($variantId, $product['id']);

                if ($variant === null || (int) $variant['is_active'] !== 1) {
                    $skipped++;
                    continue;
                }
            }

            /* TỒN KHO CỦA ĐÚNG THỨ SẼ BÁN, không phải của mặt hàng cha.

               VariantModel::reserve() trừ kho của BIẾN THỂ và không đụng tới
               `products`.`stock_quantity`, nên con số ở mặt hàng cha đứng yên
               ở bất kỳ giá trị nào nhân viên gõ. Kiểm mỗi nó thì một phương
               án đã hết hàng vẫn lọt vào giỏ, được TICK SẴN, cộng vào tổng
               tiền, và khách chỉ bị chặn ở bước đặt hàng.

               VariantModel::inStock() hỏi đúng chỗ — cùng hàm mà
               CartController::add() dùng. */
            if (!VariantModel::inStock($product, $variant, $qty)) {
                $skipped++;
                continue;
            }

            /* PHẦN TRÒNG — chỉ dựng lại khi biết ĐỦ kiểu và gói.

               Giá tròng nằm ở giao điểm kiểu × gói, nên thiếu một vế là không
               tra được giá. Đơn đặt TRƯỚC đợt 5 không có cột `lens_type`, và
               với chúng thì bỏ phần tròng ra rồi NÓI cho khách biết còn hơn
               đoán một kiểu tròng rồi mài sai. */
            $lensId   = $line['lens_id'] ?? null;
            $lensType = $line['lens_type'] ?? null;
            $rx       = $line['prescription'] ?? null;

            if ($lensId !== null && $lensType === null) {
                $lensId = null;
                $rx     = null;
                $mattrong++;
            }

            $key = CartController::key(
                (string) $product['id'],
                $variantId,
                $lensId,
                $rx,
                $lensType
            );

            $_SESSION['cart_seq'] = (int) ($_SESSION['cart_seq'] ?? 0) + 1;

            /* CHẶN TRẦN SỐ LƯỢNG. Bấm "Mua lại" bốn lần trên một đơn 3 chiếc
               mà không chặn thì dòng giỏ thành 12 chiếc trên một kho còn 5 —
               và khách chỉ biết ở bước đặt hàng. Cùng trần mà giỏ hàng dùng. */
            $sanCo = (int) ($_SESSION['cart'][$key]['quantity'] ?? 0);
            $tran  = VariantModel::stockOf($product, $variant);
            $soMoi = $tran > 0 ? min($sanCo + $qty, $tran) : $sanCo + $qty;
            $lensPk = $lensId !== null || $lensType !== null
                ? LensModel::combo($lensId, $lensType)
                : null;

            $_SESSION['cart'][$key] = [
                'product_id' => $product['id'],
                'variant_id' => $variantId,
                'quantity'   => $soMoi,
                'added_seq'  => $_SESSION['cart_seq'],
                // Tick sẵn — khách vừa chủ động bấm "Mua lại" cho cả đơn này.
                'selected'   => true,
                'lens_id'    => $lensId,
                'lens_type'  => $lensType,
                'rx'         => $rx,
                'gia_luc_them' => VariantModel::priceOf($product, $variant)
                                  + (int) ($lensPk['price'] ?? 0),
            ];

            $added++;
        }

        if ($added === 0) {
            flash('account_error', 'Các sản phẩm trong đơn này hiện không còn bán.');
            redirect('/tai-khoan?muc=don-hang');
        }

        $cau = $skipped === 0
            ? 'Đã thêm lại sản phẩm của đơn ' . $order['code'] . ' vào giỏ hàng.'
            : sprintf('Đã thêm %d sản phẩm vào giỏ. %d sản phẩm không còn bán nên đã bỏ qua.', $added, $skipped);

        /* NÓI RA phần tròng bị bỏ, đừng để khách phát hiện ở bước thanh toán.
           Chỉ xảy ra với đơn đặt trước đợt 5 (chưa có cột `lens_type`), nên câu
           này sẽ tự hết theo thời gian. */
        if ($mattrong > 0) {
            $cau .= sprintf(
                ' Riêng %d sản phẩm có cắt tròng: vui lòng chọn lại kiểu tròng và số đo trong giỏ.',
                $mattrong
            );
        }

        flash('cart_success', $cau);

        redirect('/gio-hang');
    }


    /**
     * Đổi mật khẩu — Khu vực 3 của trang Hồ sơ.
     *
     * Mọi đường quay về nay là '?muc=ho-so#doi-mat-khau', không còn
     * '?muc=mat-khau': khu vực này nằm trong trang Hồ sơ (BR-UC.USER.05-03),
     * và cái neo là thứ đưa khách trở lại đúng chỗ trên một trang dài.
     */
    public function changePassword(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost(self::VE_HO_SO . '#doi-mat-khau');

        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        // Ô "nhập lại" có `required` trong HTML, nhưng thuộc tính đó chỉ là
        // gợi ý của trình duyệt — request gửi tay thì không đi qua nó.
        if ($new !== $confirm) {
            flash('account_error', 'Hai lần nhập mật khẩu mới không khớp.');
            redirect(self::VE_HO_SO . '#doi-mat-khau');
        }

        $result = UserModel::changePassword(
            $userId,
            (string) ($_POST['current_password'] ?? ''),
            $new
        );

        if (!$result['ok']) {
            flash('account_error', $result['error']);
            redirect(self::VE_HO_SO . '#doi-mat-khau');
        }

        // Đổi mật khẩu là đá mọi thiết bị đang "ghi nhớ đăng nhập" ra ngoài.
        // Người ta đổi mật khẩu chủ yếu vì nghi bị lộ; để cookie cũ trên máy
        // lạ vẫn vào được thì việc đổi gần như vô nghĩa. Phiên hiện tại không
        // ảnh hưởng, nên chính người vừa đổi không bị đăng xuất.
        RememberModel::forgetAllFor($userId);

        flash('account_success', 'Đã đổi mật khẩu. Các thiết bị khác đã được đăng xuất.');
        redirect(self::VE_HO_SO . '#doi-mat-khau');
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    private function requirePost(string $fallback): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect($fallback);
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('auth_error', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            flash('account_error', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            redirect($fallback);
        }
    }

}
