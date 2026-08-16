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
        $to = safeRedirectPath($raw, self::HOME_AFTER_LOGIN);

        return $to === '/' ? self::HOME_AFTER_LOGIN : $to;
    }

    /** Đích mặc định sau khi đăng nhập / đăng ký. */
    private const HOME_AFTER_LOGIN = '/tai-khoan';

    public function index(): void
    {
        // Đã đăng nhập rồi thì không có lý do xem trang này nữa
        if (AuthMiddleware::check()) {
            redirect('/tai-khoan');
        }

        $this->renderView('auth/index', [
            // Khung rút gọn: không thanh điều hướng, không chân trang đầy đủ.
            // Xem ghi chú $bare trong app/views/_layout/master.php.
            'bareLayout' => true,
            'pageTitle' => ($_GET['tab'] ?? '') === 'dang-ky'
                ? 'Tạo tài khoản — Vin Eyewear' : 'Đăng nhập — Vin Eyewear',
            'metaDesc'  => 'Đăng nhập hoặc tạo tài khoản Vin Eyewear để theo dõi đơn hàng '
                         . 'và lịch hẹn của bạn.',
            // Chỉ nhận đường dẫn nội bộ — xem ghi chú trong safeRedirectPath()
            'redirect'  => $this->loginTarget($_GET['redirect'] ?? null),
            'tab'       => ($_GET['tab'] ?? '') === 'dang-ky' ? 'dang-ky' : 'dang-nhap',
            'old'       => $_SESSION['_old_auth'] ?? [],
            'error'     => flash('auth_error'),
            'success'   => flash('auth_success'),
        ]);

        unset($_SESSION['_old_auth']);
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

        $result = UserModel::attempt($login, $password);

        if (!$result['ok']) {
            // Nhớ chuỗi đã gõ để khách không phải gõ lại — nhưng KHÔNG nhớ
            // mật khẩu, và cũng nhớ luôn trạng thái ô ghi nhớ
            $_SESSION['_old_auth'] = ['email' => $login, 'remember' => $remember];
            flash('auth_error', $result['error']);
            redirect('/auth?redirect=' . rawurlencode($to));
        }

        AuthMiddleware::login($result['id'], $remember);

        redirect($to);
    }

    // ========================================================================
    // QUÊN MẬT KHẨU
    // ========================================================================

    /** Trang nhập email hoặc số điện thoại. */
    public function forgot(): void
    {
        if (AuthMiddleware::check()) {
            redirect('/tai-khoan');
        }

        $this->renderView('auth/forgot', [
            'bareLayout' => true,
            'pageTitle' => 'Quên mật khẩu — Vin Eyewear',
            'metaDesc'  => 'Đặt lại mật khẩu tài khoản Vin Eyewear.',
            'error'     => flash('auth_error'),
            'done'      => flash('auth_done'),
            'sent'      => flash('auth_sent') !== null,
            'old'       => $_SESSION['_old_forgot'] ?? '',
        ]);

        unset($_SESSION['_old_forgot']);
    }

    public function forgotSubmit(): void
    {
        $this->requirePost('/quen-mat-khau');

        $contact = trim((string) ($_POST['contact'] ?? ''));
        $result  = PasswordResetModel::request($contact);

        if (!$result['ok']) {
            $_SESSION['_old_forgot'] = $contact;
            flash('auth_error', $result['error'] ?? 'Không xử lý được yêu cầu.');
            redirect('/quen-mat-khau');
        }

        // CỐ TÌNH không nói tài khoản có tồn tại hay không. Nếu báo "không tìm
        // thấy email này" thì trang quên mật khẩu thành công cụ dò danh sách
        // khách hàng của cửa hàng.
        if ($result['sent']) {
            flash('auth_sent', '1');
        }

        flash('auth_done', '1');
        redirect('/quen-mat-khau');
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

    public function register(): void
    {
        $this->requirePost('/auth?tab=dang-ky');

        $email    = trim((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone    = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        if (utf8Length($fullName) < 2) {
            $this->failRegister('Vui lòng nhập họ tên.', $email, $fullName, $phone);
        }

        if ($password !== $confirm) {
            $this->failRegister('Hai lần nhập mật khẩu không khớp.', $email, $fullName, $phone);
        }

        $result = UserModel::register($email, $password, $fullName, $phone);

        if (!$result['ok']) {
            $this->failRegister($result['error'], $email, $fullName, $phone);
        }

        // Đăng ký xong đăng nhập luôn — bắt khách nhập lại ngay thông tin
        // vừa gõ là thêm một bước không cần thiết.
        AuthMiddleware::login($result['id']);

        flash('account_success', 'Tạo tài khoản thành công. Chào mừng bạn đến với Vin Eyewear!');
        redirect('/tai-khoan');
    }

    public function logout(): void
    {
        $this->requirePost('/');

        AuthMiddleware::logout();

        redirect('/');
    }

    // ========================================================================
    // TÀI KHOẢN
    // ========================================================================

    /**
     * Sáu mục của trang tài khoản, khoá là giá trị ?muc=.
     *
     * Ba mục đầu gộp trong nhóm "Tài khoản của tôi" ở cột trái (bản thiết kế
     * vẽ chúng trong một menu thu gọn được); ba mục sau là mục cấp một.
     * 'lich-hen' là mục THỨ BẢY, thêm ngoài bản thiết kế — xem ghi chú đầu
     * app/views/auth/profile.php.
     */
    private const SECTIONS = [
        'ho-so'    => 'Hồ sơ của tôi',
        'dia-chi'  => 'Sổ địa chỉ',
        'mat-khau' => 'Đổi mật khẩu',
        'don-hang' => 'Đơn hàng của tôi',
        'do-mat'   => 'Thông số đo mắt',
        'uu-dai'   => 'Ưu đãi của tôi',
        'lich-hen' => 'Lịch hẹn của tôi',
    ];

    /** Mục mở sẵn khi vào /tai-khoan — đúng trạng thái đầu của bản thiết kế. */
    private const DEFAULT_SECTION = 'don-hang';

    public function profile(): void
    {
        $userId  = AuthMiddleware::requireLogin();
        $section = (string) ($_GET['muc'] ?? '');

        if (!isset(self::SECTIONS[$section])) {
            $section = self::DEFAULT_SECTION;
        }

        // Số hiện trên hai huy hiệu ở cột trái. Cột trái vẽ ở CẢ BẢY mục nên
        // hai câu đếm này chạy mọi lần — nhưng chúng là COUNT(*) có chỉ mục,
        // rẻ hơn nhiều so với việc nạp cả danh sách chỉ để đếm.
        $counts = [
            'don-hang' => OrderModel::count(['user_id' => $userId]),
            'uu-dai'   => VoucherModel::countForUser($userId),
        ];

        $this->renderView('auth/profile', [
            'pageTitle' => self::SECTIONS[$section] . ' — Vin Eyewear',
            'metaDesc'  => 'Trang tài khoản Vin Eyewear: hồ sơ, sổ địa chỉ, đơn hàng, '
                         . 'thông số đo mắt và ưu đãi của bạn.',
            'sections'  => self::SECTIONS,
            'section'   => $section,
            'counts'    => $counts,
            'profile'   => UserModel::profile($userId),
            'roles'     => UserModel::roles($userId),
            'isStaff'   => UserModel::isStaff($userId),
            'genders'   => UserModel::GENDERS,
            'success'   => flash('account_success'),
            'error'     => flash('account_error'),
        ] + $this->sectionData($section, $userId));

        // Dữ liệu form địa chỉ nhập hỏng chỉ sống đúng một lần hiện trang, y
        // như $_SESSION['_old_auth'] ở index(). Không xoá thì lần sau mở form
        // "thêm địa chỉ mới" lại thấy nguyên những gì đã gõ hỏng tuần trước.
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
            case 'dia-chi':
                return [
                    'addresses' => AddressModel::forUser($userId),
                    // ?sua=<id> mở form sửa ngay tại chỗ; id không thuộc về
                    // khách này thì findOwned trả null và form về chế độ thêm mới.
                    'editing'   => isset($_GET['sua'])
                        ? AddressModel::findOwned((string) $_GET['sua'], $userId) : null,
                    'adding'    => isset($_GET['them']),
                    'old'       => $_SESSION['_old_address'] ?? [],
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
                ];

            case 'do-mat':
                $prescription = UserModel::prescription($userId);
                // Chưa có thông số nào thì mở thẳng form nhập: hiện một thẻ
                // rỗng rồi bắt khách tự tìm ra nút sửa là thừa một bước.
                $editing = isset($_GET['sua']) || $prescription === null;

                return [
                    'prescription' => $prescription,
                    'rxValid'      => UserModel::prescriptionIsValid($prescription),
                    'editing'      => $editing,
                    'stores'       => $editing ? StoreModel::active() : [],
                ];

            case 'uu-dai':
                return ['vouchers' => VoucherModel::forUser($userId)];

            case 'lich-hen':
                return [
                    'appointments'    => BookingModel::forUser($userId),
                    'bookingStatuses' => BookingModel::STATUSES,
                ];

            default:   // ho-so, mat-khau — chỉ cần $profile, profile() đã nạp
                return [];
        }
    }

    public function updateProfile(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=ho-so');

        // 'address' KHÔNG còn trong danh sách: ô "Địa chỉ giao hàng" cũ đã
        // thành mục "Sổ địa chỉ" riêng, và profiles.address nay là bản sao do
        // AddressModel giữ. Để form hồ sơ ghi thẳng vào đó nữa là hai nguồn
        // cùng sửa một cột, cái sau đè cái trước.
        $result = UserModel::updateProfile($userId, [
            'full_name'     => trim((string) ($_POST['full_name'] ?? '')),
            'phone'         => trim((string) ($_POST['phone'] ?? '')),
            'gender'        => (string) ($_POST['gender'] ?? ''),
            'date_of_birth' => ($_POST['date_of_birth'] ?? '') !== ''
                ? (string) $_POST['date_of_birth'] : null,
        ]);

        if (!$result['ok']) {
            flash('account_error', $result['error']);
            redirect('/tai-khoan?muc=ho-so');
        }

        flash('account_success', 'Đã cập nhật hồ sơ.');
        redirect('/tai-khoan?muc=ho-so');
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
    // SỔ ĐỊA CHỈ
    // ========================================================================

    /**
     * Thêm mới hoặc sửa — một action cho cả hai, phân biệt bằng ô `id` ẩn.
     * Hai form giống hệt nhau tới từng ô nhập; tách làm hai action nghĩa là
     * hai bản sao của cùng một đoạn đọc $_POST.
     */
    public function saveAddress(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=dia-chi');

        $id    = trim((string) ($_POST['id'] ?? ''));
        $input = [
            'recipient_name' => $_POST['recipient_name'] ?? '',
            'phone'          => $_POST['phone'] ?? '',
            'line1'          => $_POST['line1'] ?? '',
            'line2'          => $_POST['line2'] ?? '',
            'is_default'     => ($_POST['is_default'] ?? '') !== '',
        ];

        $result = $id === ''
            ? AddressModel::create($userId, $input)
            : AddressModel::updateOwned($id, $userId, $input);

        if (!$result['ok']) {
            // Nhớ những gì khách vừa gõ để họ không phải nhập lại từ đầu,
            // và mở lại đúng form (thêm mới hay sửa) mà lỗi vừa xảy ra.
            $_SESSION['_old_address'] = $input;
            flash('account_error', $result['error']);
            redirect('/tai-khoan?muc=dia-chi&' . ($id === '' ? 'them=1' : 'sua=' . rawurlencode($id)));
        }

        unset($_SESSION['_old_address']);

        flash('account_success', $id === '' ? 'Đã thêm địa chỉ mới.' : 'Đã cập nhật địa chỉ.');
        redirect('/tai-khoan?muc=dia-chi');
    }

    public function deleteAddress(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=dia-chi');

        $result = AddressModel::deleteOwned((string) ($_POST['id'] ?? ''), $userId);

        flash(
            $result['ok'] ? 'account_success' : 'account_error',
            $result['ok'] ? 'Đã xoá địa chỉ.' : $result['error']
        );

        redirect('/tai-khoan?muc=dia-chi');
    }

    public function setDefaultAddress(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=dia-chi');

        $result = AddressModel::setDefault((string) ($_POST['id'] ?? ''), $userId);

        flash(
            $result['ok'] ? 'account_success' : 'account_error',
            $result['ok'] ? 'Đã đổi địa chỉ mặc định.' : $result['error']
        );

        redirect('/tai-khoan?muc=dia-chi');
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

        $added = 0;
        $skipped = 0;

        foreach (OrderModel::items($order['id']) as $line) {
            $product = $line['product_id'] === null ? null : ProductModel::find($line['product_id']);
            $qty     = (int) $line['quantity'];

            if ($product === null || (int) $product['is_visible'] !== 1
                || !ProductModel::inStock($product, $qty)) {
                $skipped++;
                continue;
            }

            $current = (int) ($_SESSION['cart'][$product['id']]['quantity'] ?? 0);
            $_SESSION['cart'][$product['id']] = ['quantity' => $current + $qty];
            $added++;
        }

        if ($added === 0) {
            flash('account_error', 'Các sản phẩm trong đơn này hiện không còn bán.');
            redirect('/tai-khoan?muc=don-hang');
        }

        flash('cart_success', $skipped === 0
            ? 'Đã thêm lại sản phẩm của đơn ' . $order['code'] . ' vào giỏ hàng.'
            : sprintf('Đã thêm %d sản phẩm vào giỏ. %d sản phẩm không còn bán nên đã bỏ qua.', $added, $skipped));

        redirect('/gio-hang');
    }

    /**
     * Tự nhập thông số đo mắt.
     *
     * Bản thiết kế vẽ mục này ở dạng CHỈ ĐỌC — "kết quả đo khúc xạ gần nhất
     * tại Vin Eyewear", tức dữ liệu do kỹ thuật viên nhập. Form tự nhập vẫn
     * giữ (nó có từ trước và là cách duy nhất để khách mang đơn thuốc từ nơi
     * khác sang), nhưng nằm ở một trạng thái riêng: /tai-khoan?muc=do-mat&sua=1.
     */
    public function updatePrescription(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=do-mat');

        UserModel::savePrescription($userId, [
            'od_sph'         => $_POST['od_sph'] ?? '',
            'od_cyl'         => $_POST['od_cyl'] ?? '',
            'od_axis'        => $_POST['od_axis'] ?? '',
            'od_va'          => $_POST['od_va'] ?? '',
            'os_sph'         => $_POST['os_sph'] ?? '',
            'os_cyl'         => $_POST['os_cyl'] ?? '',
            'os_axis'        => $_POST['os_axis'] ?? '',
            'os_va'          => $_POST['os_va'] ?? '',
            'pd'             => $_POST['pd'] ?? '',
            'measured_at'    => $_POST['measured_at'] ?? '',
            'store_id'       => $_POST['store_id'] ?? '',
            'recommendation' => $_POST['recommendation'] ?? '',
        ]);

        flash('account_success', 'Đã lưu thông số đo mắt.');
        redirect('/tai-khoan?muc=do-mat');
    }

    public function changePassword(): void
    {
        $userId = AuthMiddleware::requireLogin();
        $this->requirePost('/tai-khoan?muc=mat-khau');

        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        // Ô "nhập lại" có `required` trong HTML, nhưng thuộc tính đó chỉ là
        // gợi ý của trình duyệt — request gửi tay thì không đi qua nó.
        if ($new !== $confirm) {
            flash('account_error', 'Hai lần nhập mật khẩu mới không khớp.');
            redirect('/tai-khoan?muc=mat-khau');
        }

        $result = UserModel::changePassword(
            $userId,
            (string) ($_POST['current_password'] ?? ''),
            $new
        );

        if (!$result['ok']) {
            flash('account_error', $result['error']);
            redirect('/tai-khoan?muc=mat-khau');
        }

        // Đổi mật khẩu là đá mọi thiết bị đang "ghi nhớ đăng nhập" ra ngoài.
        // Người ta đổi mật khẩu chủ yếu vì nghi bị lộ; để cookie cũ trên máy
        // lạ vẫn vào được thì việc đổi gần như vô nghĩa. Phiên hiện tại không
        // ảnh hưởng, nên chính người vừa đổi không bị đăng xuất.
        RememberModel::forgetAllFor($userId);

        flash('account_success', 'Đã đổi mật khẩu. Các thiết bị khác đã được đăng xuất.');
        redirect('/tai-khoan?muc=mat-khau');
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

    private function failRegister(string $message, string $email, string $fullName, string $phone): never
    {
        $_SESSION['_old_auth'] = ['email' => $email, 'full_name' => $fullName, 'phone' => $phone];
        flash('auth_error', $message);

        redirect('/auth?tab=dang-ky');
    }
}
