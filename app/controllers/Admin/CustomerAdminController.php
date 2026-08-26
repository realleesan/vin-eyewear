<?php

/**
 * Admin/CustomerAdminController — module KHÁCH HÀNG (/quan-tri/khach-hang).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RANH GIỚI CỦA MODULE — ĐỌC TRƯỚC KHI THÊM BẤT KỲ ACTION NÀO
 *
 * Module này SỞ HỮU:  hồ sơ · trạng thái tài khoản · sổ địa chỉ ·
 *                     đơn thuốc kính · ghi chú nội bộ
 * Module này MƯỢN:    đơn hàng · lịch hẹn · liên hệ · đánh giá
 *
 * Thứ mượn thì CHỈ HIỂN THỊ, kèm một đường dẫn sang module gốc. Ở đây KHÔNG có
 * action nào đổi trạng thái đơn, duyệt đánh giá hay xác nhận lịch hẹn — và
 * đừng thêm. Bốn module kia đã làm những việc đó; thêm chỗ thứ hai là mở đầu
 * cho hai chỗ lệch nhau, mà lệch ở dữ liệu đơn hàng thì phát hiện được lúc đã
 * giao nhầm.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * AI LÀM ĐƯỢC GÌ
 *
 *   xem danh sách · xem chi tiết         mọi tài khoản nội bộ
 *   sửa hồ sơ · địa chỉ · ghi chú        mọi tài khoản nội bộ (việc ở quầy)
 *   khoá · mở khoá · xoá · khôi phục     quản lý trở lên
 *   gửi liên kết đặt lại mật khẩu        quản lý trở lên
 *   xuất danh sách                       quản lý trở lên
 *   ĐƠN THUỐC KÍNH (kể cả CHỈ XEM)       CHỈ quản trị
 *
 * Đơn thuốc kính là dữ liệu sức khoẻ nên đứng riêng một bậc. Chặn ở HAI TẦNG
 * theo CLAUDE.md quy tắc 4: view ẩn tab, VÀ mọi action tự hỏi lại canRx().
 * Ẩn tab một mình không phải phân quyền — địa chỉ gõ tay vẫn tới được.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CustomerAdminController extends AdminController
{
    private const BASE = '/quan-tri/khach-hang';

    /**
     * Năm tab của trang chi tiết.
     *
     * Khoá là giá trị ?tab= trên địa chỉ — TIẾNG VIỆT KHÔNG DẤU theo quy ước
     * đặt tên URL của dự án.
     *
     * VÌ SAO TAB ĐI QUA ĐỊA CHỈ CHỨ KHÔNG PHẢI JAVASCRIPT: tab dựng bằng JS
     * thì mọi form bên trong sau khi POST xong sẽ quay về tab đầu tiên, và
     * người vừa sửa địa chỉ phải bấm lại vào đúng tab để xem kết quả. Đi qua
     * ?tab= thì chuyển hướng sau POST trả đúng về chỗ vừa đứng, và địa chỉ
     * cũng chia sẻ được, quay lại được, F5 được.
     */
    public const TABS = [
        'ho-so'     => 'Hồ sơ',
        'dia-chi'   => 'Địa chỉ',
        'don-thuoc' => 'Đơn thuốc kính',
        'hoat-dong' => 'Hoạt động',
        'ghi-chu'   => 'Ghi chú nội bộ',
    ];

    // ========================================================================
    // DANH SÁCH
    // ========================================================================

    public function index(): void
    {
        if (!$this->sanSang()) {
            return;
        }

        $q      = trim((string) ($_GET['q'] ?? ''));
        $filter = (string) ($_GET['status'] ?? '');

        // Giá trị lạ coi như không lọc, không phải lỗi: ?trang-thai=abc là
        // người gõ tay địa chỉ, và trả về danh sách đầy đủ dễ hiểu hơn một
        // trang lỗi.
        if (!isset(CustomerModel::FILTERS[$filter])) {
            $filter = '';
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $ket  = CustomerModel::paginateList($q, $filter, $page);

        $this->renderAdmin('admin/customers/index', [
            'pageTitle'  => 'Khách hàng — Quản trị',
            'customers'  => $ket['items'],
            'total'      => $ket['total'],
            'page'       => $ket['page'],
            'totalPages' => $ket['totalPages'],
            'q'          => $q,
            'filter'     => $filter,
            'filters'    => CustomerModel::FILTERS,
            'counts'     => CustomerModel::counts(),
            'canManage'  => $this->laQuanLy(),
        ]);
    }

    /**
     * Xuất danh sách ra file.
     *
     * CSV CHỨ KHÔNG PHẢI .XLSX — và đây là lý do, không phải sự lười.
     *
     * Sinh file xlsx thật cần PhpSpreadsheet, mà CLAUDE.md cấm thêm phụ thuộc
     * ngoài: hosting InfinityFree bản miễn phí không có SSH nên không chạy
     * được Composer. Excel mở CSV bằng một cú nháy đúp, nên cái giá phải trả
     * là không có định dạng ô — chấp nhận được cho một bảng danh sách.
     *
     * BOM UTF-8 ở đầu file là BẮT BUỘC: thiếu nó, Excel trên Windows đọc file
     * theo bảng mã hệ thống và mọi tên tiếng Việt thành ký tự loạn. Đây là lỗi
     * duy nhất người dùng sẽ gặp với file này, và nó gặp ngay ở dòng đầu tiên.
     */
    public function export(): void
    {
        if (!$this->sanSang()) {
            return;
        }

        $this->requireManager(self::BASE);

        $q      = trim((string) ($_GET['q'] ?? ''));
        $filter = (string) ($_GET['status'] ?? '');

        if (!isset(CustomerModel::FILTERS[$filter])) {
            $filter = '';
        }

        $rows = CustomerModel::exportRows($q, $filter);

        AuditLogModel::write(null, 'export', count($rows) . ' khách hàng');

        $ten = 'khach-hang-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $ten . '"');
        // Trình duyệt và proxy không được giữ lại bản sao: file này là danh
        // sách khách hàng kèm số điện thoại.
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'wb');

        fwrite($out, "\xEF\xBB\xBF");

        /* BỐN THAM SỐ, KHÔNG PHẢI HAI — dấu ngăn, dấu bọc, VÀ dấu thoát rỗng.

           PHP 8.4 phát cảnh báo Deprecated nếu không truyền $escape, vì giá
           trị mặc định của nó sắp đổi từ "\\" sang "". Ở đây cảnh báo đó
           không nằm trong log mà IN THẲNG VÀO GIỮA FILE CSV đang gửi cho
           người dùng — hai dòng HTML kẹp giữa các hàng dữ liệu, và Excel mở
           ra là hỏng. Máy chủ có display_errors tắt thì không thấy, máy phát
           triển thì thấy; đúng loại lỗi chỉ lộ ra ở một trong hai nơi.

           Truyền '' chứ không phải '\\': dấu thoát kiểu backslash không có
           trong chuẩn CSV (RFC 4180) — chuẩn ấy nhân đôi dấu nháy để thoát,
           và $enclosure đã lo việc đó. Đây cũng chính là giá trị mặc định
           tương lai của PHP. */
        $dong = static fn (array $o): bool|int => fputcsv($out, $o, ',', '"', '');

        $dong(['Họ tên', 'Email', 'Số điện thoại', 'Ngày sinh', 'Giới tính',
               'Ngày đăng ký', 'Đăng nhập gần nhất', 'Số đơn',
               'Tổng chi tiêu (đ)', 'Trạng thái']);

        foreach ($rows as $r) {
            $dong([
                $r['full_name'] ?? '',
                $r['email'] ?? '',
                /* DẤU NHÁY ĐƠN ĐẦU SỐ ĐIỆN THOẠI: Excel thấy "0912345678" là
                   một con số và cắt luôn số 0 dẫn đầu, ra "912345678". Nháy
                   đơn ép nó đọc như chữ. Xấu khi mở bằng trình soạn thảo chữ,
                   nhưng file này sinh ra để mở bằng Excel. */
                $r['phone'] !== null && $r['phone'] !== '' ? "'" . $r['phone'] : '',
                $r['date_of_birth'] ?? '',
                UserModel::GENDERS[$r['gender'] ?? ''] ?? '',
                formatDate($r['created_at'], 'd/m/Y H:i'),
                $r['last_login_at'] !== null ? formatDate($r['last_login_at'], 'd/m/Y H:i') : '',
                (int) $r['so_don'],
                (int) $r['tong_tien'],
                $r['deleted_at'] !== null
                    ? 'Đã xoá'
                    : (CustomerModel::STATUSES[$r['status']] ?? $r['status']),
            ]);
        }

        fclose($out);
        exit;
    }

    // ========================================================================
    // CHI TIẾT
    // ========================================================================

    public function show(string $id): void
    {
        if (!$this->sanSang()) {
            return;
        }

        $khach = CustomerModel::detail($id);

        if ($khach === null) {
            // Nguyên văn theo spec — xem CLAUDE.md quy tắc 3.
            flash('admin_error', 'Không tìm thấy khách hàng.');
            redirect(self::BASE);
        }

        $tab = (string) ($_GET['tab'] ?? 'ho-so');

        if (!isset(self::TABS[$tab])) {
            $tab = 'ho-so';
        }

        // Không có quyền xem đơn thuốc mà gõ tay ?tab=don-thuoc thì trả về tab
        // đầu. Im lặng đưa về chứ không báo lỗi: người dùng bình thường không
        // bao giờ tới được đây, còn người cố tình thì không cần được xác nhận
        // là tab đó có tồn tại.
        if ($tab === 'don-thuoc' && !$this->canRx()) {
            $tab = 'ho-so';
        }

        $data = [
            'pageTitle' => trim(($khach['full_name'] ?? '') . ' — Khách hàng — Quản trị'),
            'khach'     => $khach,
            'stats'     => CustomerModel::stats($id),
            'tab'       => $tab,
            'tabs'      => self::TABS,
            'canManage' => $this->laQuanLy(),
            'canRx'     => $this->canRx(),
            'genders'   => UserModel::GENDERS,
        ];

        /*
         * CHỈ NẠP DỮ LIỆU CỦA TAB ĐANG MỞ.
         *
         * Không phải để tiết kiệm truy vấn — mà vì tab Đơn thuốc PHẢI GHI VẾT
         * mỗi lần có người đọc (CLAUDE.md mục 5, dữ liệu y tế). Nạp sẵn cả năm
         * tab thì mỗi lần ai đó mở hồ sơ để xem số điện thoại cũng sinh một
         * dòng "đã xem đơn thuốc kính" — và một sổ vết đầy những lần đọc không
         * có thật thì không ai đọc nổi nó nữa, đúng lúc cần tra thì chịu.
         */
        switch ($tab) {
            case 'dia-chi':
                $data['addresses']   = AddressModel::forUser($id);
                $data['addrEditing'] = $this->diaChiDangSua($id);
                // address-picker.js đổi hai ô gõ tay thành danh sách chọn
                // tỉnh/phường. Không có nó thì vẫn gõ tay lưu được — xem đầu
                // file JS đó.
                $data['adminScripts'] = ['assets/js/address-picker.js'];
                break;

            case 'don-thuoc':
                $lichSu = PrescriptionRecordModel::forUser($id);

                $data['rxRecords']    = $lichSu;
                $data['rxDeltas']     = PrescriptionRecordModel::chenhLech($lichSu);
                $data['rxSources']    = PrescriptionRecordModel::SOURCES;
                $data['stores']       = StoreModel::active();
                $data['doneAppts']    = CustomerModel::doneAppointments($id);
                $data['rxEditing']    = $this->rxDangSua($id);
                $data['auditReady']   = AuditLogModel::available();

                AuditLogModel::write($id, 'rx.read');
                break;

            case 'hoat-dong':
                $data['activity']     = CustomerModel::activity($id);
                $data['orderStatuses']   = OrderModel::STATUSES;
                $data['paymentStatuses'] = OrderModel::PAYMENT_STATUSES;
                $data['apptStatuses']    = BookingModel::STATUSES;
                /* Nhãn của cả bốn module đều lấy từ HẰNG CỦA CHÍNH MODULE ĐÓ,
                   không gõ lại ở đây. Gõ lại nghĩa là khi bên kia đổi chữ
                   "Đang hiện" thành "Đã duyệt" thì màn hình này vẫn nói chữ cũ
                   — và người đọc hai màn hình sẽ tưởng đó là hai trạng thái. */
                $data['contactStatuses'] = ContactModel::STATUSES;
                $data['reviewStatuses']  = ReviewModel::STATUSES;
                break;

            case 'ghi-chu':
                $data['notes']      = CustomerNoteModel::forUser($id);
                $data['noteEditing'] = $this->ghiChuDangSua($id);
                break;
        }

        $this->renderAdmin('admin/customers/detail', $data);
    }

    // ========================================================================
    // HỒ SƠ
    // ========================================================================

    public function saveProfile(): void
    {
        $id = $this->batDauPost('ho-so');

        $ket = CustomerModel::saveProfile($id, $_POST);

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã lưu hồ sơ khách hàng.' : $ket['error']);

        $this->veTab($id, 'ho-so');
    }

    // ========================================================================
    // TRẠNG THÁI TÀI KHOẢN
    // ========================================================================

    public function lock(): void
    {
        $id = $this->batDauPost('ho-so');
        $this->requireManager(self::BASE . '/' . rawurlencode($id));

        $ket = CustomerModel::lock($id, (string) ($_POST['ly_do'] ?? ''), $this->userId);

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã khoá tài khoản khách hàng.' : $ket['error']);

        $this->veTab($id, 'ho-so');
    }

    public function unlock(): void
    {
        $id = $this->batDauPost('ho-so');
        $this->requireManager(self::BASE . '/' . rawurlencode($id));

        $ket = CustomerModel::unlock($id);

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã mở khoá tài khoản khách hàng.' : $ket['error']);

        $this->veTab($id, 'ho-so');
    }

    public function softDelete(): void
    {
        $id = $this->batDauPost('ho-so');
        $this->requireManager(self::BASE . '/' . rawurlencode($id));

        $ket = CustomerModel::softDelete($id, (string) ($_POST['ly_do_xoa'] ?? ''));

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            $this->veTab($id, 'ho-so');
        }

        /* Về DANH SÁCH chứ không về hồ sơ vừa xoá: cái vừa bấm là "bỏ người
           này ra khỏi danh sách khách hàng", nên đứng lại nhìn chính hồ sơ đó
           là câu trả lời sai cho việc vừa làm. Muốn khôi phục thì có tab
           "Đã xoá" ngay trên danh sách. */
        flash('admin_success', 'Đã xoá tài khoản khách hàng. Đơn hàng cũ vẫn giữ nguyên.');
        redirect(self::BASE . '?status=deleted');
    }

    public function restore(): void
    {
        $id = $this->batDauPost('ho-so');
        $this->requireManager(self::BASE . '/' . rawurlencode($id));

        $ket = CustomerModel::restore($id);

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã khôi phục tài khoản khách hàng.' : $ket['error']);

        $this->veTab($id, 'ho-so');
    }

    /**
     * Gửi email đặt lại mật khẩu.
     *
     * KHÔNG có nút "đặt mật khẩu mới" trong module này, cố ý. Nhân viên không
     * được biết mật khẩu của khách — kể cả một mật khẩu tạm do máy sinh, vì nó
     * vẫn mở được tài khoản và nó sẽ đi qua một tin nhắn hay một mẩu giấy.
     * Liên kết gửi thẳng vào hòm thư của khách thì chỉ người cầm hòm thư dùng
     * được. Chi tiết ở PasswordResetModel::issueForUser().
     */
    public function sendReset(): void
    {
        $id = $this->batDauPost('ho-so');
        $this->requireManager(self::BASE . '/' . rawurlencode($id));

        $ket = PasswordResetModel::issueForUser($id, $this->userId);

        if ($ket['ok']) {
            AuditLogModel::write($id, 'reset_email');
        }

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok']
                ? 'Đã gửi email đặt lại mật khẩu cho khách hàng.'
                : $ket['error']);

        $this->veTab($id, 'ho-so');
    }

    // ========================================================================
    // SỔ ĐỊA CHỈ
    //
    // Ba action này KHÔNG tự kiểm dữ liệu: AddressModel đã có đủ luật (tên
    // người nhận, chuẩn hoá số điện thoại, bắt buộc tỉnh và phường, trần số
    // địa chỉ mỗi người, không cho xoá địa chỉ mặc định khi còn cái khác) và
    // câu thông báo tiếng Việt của nó đã nghiệm thu ở trang tài khoản khách.
    // Cùng một sổ địa chỉ thì phải cùng một bộ luật, dù mở từ đâu.
    // ========================================================================

    public function saveAddress(): void
    {
        $id = $this->batDauPost('dia-chi');

        $diaChiId = trim((string) ($_POST['dia_chi_id'] ?? ''));

        $ket = $diaChiId !== ''
            ? AddressModel::updateOwned($diaChiId, $id, $_POST)
            : AddressModel::create($id, $_POST);

        if ($ket['ok']) {
            AuditLogModel::write($id, 'address.save');
        }

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã lưu địa chỉ.' : $ket['error']);

        $this->veTab($id, 'dia-chi');
    }

    public function deleteAddress(): void
    {
        $id = $this->batDauPost('dia-chi');

        $ket = AddressModel::deleteOwned((string) ($_POST['dia_chi_id'] ?? ''), $id);

        if ($ket['ok']) {
            AuditLogModel::write($id, 'address.delete');
        }

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã xoá địa chỉ.' : $ket['error']);

        $this->veTab($id, 'dia-chi');
    }

    public function defaultAddress(): void
    {
        $id = $this->batDauPost('dia-chi');

        AddressModel::setDefault((string) ($_POST['dia_chi_id'] ?? ''), $id);
        AuditLogModel::write($id, 'address.save', 'Đổi địa chỉ mặc định');

        flash('admin_success', 'Đã đổi địa chỉ mặc định.');
        $this->veTab($id, 'dia-chi');
    }

    // ========================================================================
    // ĐƠN THUỐC KÍNH — CHỈ QUẢN TRỊ
    // ========================================================================

    public function savePrescription(): void
    {
        $id = $this->batDauPost('don-thuoc');
        $this->chanNeuKhongXemDuocRx($id);

        $rxId = trim((string) ($_POST['rx_id'] ?? ''));

        $ket = PrescriptionRecordModel::save(
            $rxId !== '' ? $rxId : null,
            $id,
            $_POST,
            $this->userId
        );

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã lưu đơn thuốc kính.' : $ket['error']);

        $this->veTab($id, 'don-thuoc');
    }

    public function deletePrescription(): void
    {
        $id = $this->batDauPost('don-thuoc');
        $this->chanNeuKhongXemDuocRx($id);

        $ket = PrescriptionRecordModel::deleteOwned((string) ($_POST['rx_id'] ?? ''), $id);

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã xoá bản ghi đơn thuốc.' : $ket['error']);

        $this->veTab($id, 'don-thuoc');
    }

    // ========================================================================
    // GHI CHÚ NỘI BỘ
    // ========================================================================

    public function saveNote(): void
    {
        $id = $this->batDauPost('ghi-chu');

        $noteId = trim((string) ($_POST['ghi_chu_id'] ?? ''));

        $ket = CustomerNoteModel::save(
            $noteId !== '' ? $noteId : null,
            $id,
            (string) ($_POST['body'] ?? ''),
            $this->userId
        );

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã lưu ghi chú.' : $ket['error']);

        $this->veTab($id, 'ghi-chu');
    }

    public function deleteNote(): void
    {
        $id = $this->batDauPost('ghi-chu');

        $ket = CustomerNoteModel::deleteOwned((string) ($_POST['ghi_chu_id'] ?? ''), $id);

        flash($ket['ok'] ? 'admin_success' : 'admin_error',
            $ket['ok'] ? 'Đã xoá ghi chú.' : $ket['error']);

        $this->veTab($id, 'ghi-chu');
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    /**
     * Mở đầu MỌI action POST: kiểm phương thức, kiểm CSDL đã nâng cấp chưa,
     * và lấy ra id khách hợp lệ.
     *
     * Gom vào một hàm vì bốn bước này phải chạy ở CẢ MƯỜI ba action ghi. Để
     * mỗi action tự gọi bốn dòng thì chỉ cần một lần quên là có một đường ghi
     * không kiểm gì — mà không có gì báo cho ai biết. Cùng lý lẽ với việc
     * AdminController đặt requireStaff() ở constructor.
     *
     * @return string Id khách hàng
     */
    private function batDauPost(string $tab): string
    {
        $this->requirePost(self::BASE);

        if (!CustomerModel::ready()) {
            flash('admin_error', 'Cơ sở dữ liệu chưa chạy migration 2026-08-26-module-khach-hang.sql.');
            redirect(self::BASE);
        }

        $id = trim((string) ($_POST['id'] ?? ''));

        // detail() trả null cho cả id không tồn tại LẪN id của một tài khoản
        // nội bộ — xem chú thích tại hàm đó. Nhờ vậy không có đường nào từ
        // module này sửa được hồ sơ của một quản trị viên khác.
        if ($id === '' || CustomerModel::detail($id) === null) {
            flash('admin_error', 'Không tìm thấy khách hàng.');
            redirect(self::BASE);
        }

        return $id;
    }

    /**
     * Kiểm CSDL đã có các cột của module chưa.
     *
     * Trả false nghĩa là đã in xong một trang hướng dẫn — nơi gọi phải return
     * ngay. Không chuyển hướng đi đâu cả: chuyển về danh sách thì chính danh
     * sách cũng hỏng, thành vòng lặp.
     */
    private function sanSang(): bool
    {
        if (CustomerModel::ready()) {
            return true;
        }

        $this->renderAdmin('admin/customers/chua-nang-cap', [
            'pageTitle' => 'Khách hàng — Quản trị',
        ]);

        return false;
    }

    /** Quản lý hoặc quản trị. */
    private function laQuanLy(): bool
    {
        return UserModel::hasRole($this->userId, 'admin')
            || UserModel::hasRole($this->userId, 'manager');
    }

    /**
     * Chỉ 'admin' đọc được đơn thuốc kính.
     *
     * KHÔNG dùng laQuanLy(): quản lý cửa hàng điều hành được mọi việc bán
     * hàng, nhưng độ cận của khách là dữ liệu sức khoẻ và nó chỉ nên nằm trong
     * tay số người ít nhất có thể. Đây là yêu cầu nghiệp vụ, không phải một
     * bậc quyền tiện tay đặt thêm.
     */
    private function canRx(): bool
    {
        return UserModel::hasRole($this->userId, 'admin');
    }

    private function chanNeuKhongXemDuocRx(string $id): void
    {
        if ($this->canRx()) {
            return;
        }

        // GHI VẾT CẢ LẦN BỊ TỪ CHỐI. Một người không có quyền mà gửi được POST
        // tới đây thì họ đã phải tự dựng biểu mẫu — đó là thứ đáng để lại dấu,
        // chứ không phải thứ lặng lẽ chuyển hướng rồi quên.
        AuditLogModel::write($id, 'rx.read', 'BỊ TỪ CHỐI — không có vai trò quản trị');

        flash('admin_error', 'Chỉ tài khoản quản trị mới xem và sửa được đơn thuốc kính.');
        $this->veTab($id, 'ho-so');
    }

    /** Địa chỉ đang mở trong form sửa (?sua=<id>), hoặc null. */
    private function diaChiDangSua(string $userId): ?array
    {
        $id = trim((string) ($_GET['sua'] ?? ''));

        return $id !== '' ? AddressModel::findOwned($id, $userId) : null;
    }

    /** Bản ghi đơn thuốc đang mở trong form sửa (?sua=<id>), hoặc null. */
    private function rxDangSua(string $userId): ?array
    {
        $id = trim((string) ($_GET['sua'] ?? ''));

        return $id !== '' ? PrescriptionRecordModel::findOwned($id, $userId) : null;
    }

    /** Ghi chú đang mở trong form sửa (?sua=<id>), hoặc null. */
    private function ghiChuDangSua(string $userId): ?array
    {
        $id = trim((string) ($_GET['sua'] ?? ''));

        if ($id === '') {
            return null;
        }

        return CustomerNoteModel::findOwned($id, $userId);
    }

    /** Quay về đúng tab vừa đứng sau một POST. */
    private function veTab(string $id, string $tab): never
    {
        redirect(self::BASE . '/' . rawurlencode($id) . '?tab=' . rawurlencode($tab));
    }
}
