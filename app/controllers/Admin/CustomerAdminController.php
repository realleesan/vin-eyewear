<?php

/**
 * Admin/CustomerAdminController — module KHÁCH HÀNG (/quan-tri/khach-hang).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RANH GIỚI CỦA MODULE — ĐỌC TRƯỚC KHI THÊM BẤT KỲ ACTION NÀO
 *
 * Module này SỞ HỮU:     trạng thái tài khoản · đơn thuốc kính
 * Module này CHỈ CHO XEM: hồ sơ · sổ địa chỉ
 * Module này MƯỢN:        đơn hàng · lịch hẹn · liên hệ · đánh giá
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
 *   khoá · mở khoá · xoá · khôi phục     quản lý trở lên
 *   xuất danh sách                       quản lý trở lên
 *   ĐƠN THUỐC KÍNH (kể cả CHỈ XEM)       CHỈ quản trị
 *
 * DỮ LIỆU CỦA KHÁCH LÀ CHỈ XEM — hồ sơ và sổ địa chỉ, không ai trong khu quản
 * trị sửa được, kể cả quản trị viên.
 *
 *   · hồ sơ    — số điện thoại và email trong đó là thứ khách dùng để ĐĂNG
 *                NHẬP. Gõ nhầm một chữ số là khách mất đường vào tài khoản
 *                của chính mình. Khách tự sửa ở /tai-khoan?muc=ho-so.
 *   · địa chỉ  — đây là CHÍNH sổ dùng để giao hàng. Gõ nhầm là gói hàng tới
 *                đi sai nhà. Khách tự sửa ở /tai-khoan?muc=dia-chi.
 *
 * Điểm chung: người gõ nhầm không bao giờ thấy hậu quả, còn người chịu hậu quả
 * thì không biết vì sao. Nên module này KHÔNG có action ghi cho hai thứ đó, và
 * không có route nào trỏ tới một action như thế — ẩn form đi mà để endpoint
 * sống là đúng cái lỗi CLAUDE.md quy tắc 4 nói tới.
 *
 * ĐƠN THUỐC KÍNH THÌ NGƯỢC LẠI, VẪN SỬA ĐƯỢC: số đo là do kỹ thuật viên của
 * cửa hàng đo ra, không phải thứ khách tự nhập được ở trang tài khoản. Đổi
 * lại nó đứng sau một bậc quyền riêng và ghi vết cả lần chỉ đọc.
 *
 * GHI CHÚ NỘI BỘ ĐÃ BỎ ngày 2026-08-28 — cả mã lẫn bảng `customer_notes`
 * (migration 2026-08-28-bo-bang-ghi-chu-noi-bo.sql).
 *
 * GỬI EMAIL ĐẶT LẠI MẬT KHẨU CŨNG ĐÃ BỎ, cùng ngày. Việc giúp khách lấy lại
 * mật khẩu nay chỉ còn MỘT đường và nó có bước xác minh: /quan-tri/quen-mat-khau,
 * nơi nhân viên gọi điện cho khách rồi mới đọc liên kết. Nút cũ ở module này
 * không xác minh gì cả — mở được hồ sơ là bấm được, nên hai đường song song
 * thì đường yếu hơn quyết định mức bảo mật thật.
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
     * Bốn tab của trang chi tiết.
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
    ];

    // ========================================================================
    // DANH SÁCH
    // ========================================================================

    public function index(): void
    {
        if (!$this->sanSang()) {
            return;
        }

        /* Giá trị lạ ở ?status= coi như không lọc, không phải lỗi — xử lý
           trong duLieuDanhSach(). */
        $this->renderAdmin('admin/customers/index', $this->duLieuDanhSach());
    }

    /**
     * Dữ liệu của bảng danh sách — dùng chung cho index() và show().
     *
     * Tách ra từ 2026-08-28, khi hồ sơ khách chuyển từ TRANG RIÊNG sang HỘP
     * THOẠI nổi trên chính bảng này. Hộp thoại thì phải có bảng ở phía sau nó,
     * kể cả khi người dùng vào thẳng bằng địa chỉ /quan-tri/khach-hang/<id> —
     * nếu không thì tắt JavaScript sẽ thấy một cái hộp lơ lửng trên nền trống.
     *
     * @return array<string, mixed>
     */
    private function duLieuDanhSach(): array
    {
        $q      = trim((string) ($_GET['q'] ?? ''));
        $filter = (string) ($_GET['status'] ?? '');

        if (!isset(CustomerModel::FILTERS[$filter])) {
            $filter = '';
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $ket  = CustomerModel::paginateList($q, $filter, $page);

        return [
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
        ];
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

        /*
         * HỒ SƠ KHÁCH LÀ HỘP THOẠI NỔI TRÊN BẢNG, không phải một trang riêng.
         *
         * Vì thế payload là dữ liệu bảng CỘNG dữ liệu hồ sơ, và view dựng ra
         * chính là admin/customers/index — nó tự vẽ hộp khi thấy $khach.
         *
         * Nhờ vậy có JavaScript thì bấm "Xem chi tiết" là hộp bật lên tại chỗ
         * (admin-modal.js fetch đúng địa chỉ này rồi bóc phần .amodal ra), còn
         * tắt JavaScript thì tải lại trang và thấy hộp nằm sẵn trên bảng —
         * cùng một HTML, hai đường tới.
         */
        /* Thứ tự toán hạng của `+` có nghĩa: khoá TRÙNG thì vế TRÁI thắng. Đặt
           dữ liệu hồ sơ bên trái để `pageTitle` là tên khách chứ không phải
           "Khách hàng — Quản trị" của bảng. */
        $data = [
            'pageTitle' => trim(($khach['full_name'] ?? '') . ' — Khách hàng — Quản trị'),
            'khach'     => $khach,
            'stats'     => CustomerModel::stats($id),
            'tab'       => $tab,
            'tabs'      => self::TABS,
            'canManage' => $this->laQuanLy(),
            'canRx'     => $this->canRx(),
            'genders'   => UserModel::GENDERS,
        ] + $this->duLieuDanhSach();

        /*
         * CHỈ NẠP DỮ LIỆU CỦA TAB ĐANG MỞ.
         *
         * Không phải để tiết kiệm truy vấn — mà vì tab Đơn thuốc PHẢI GHI VẾT
         * mỗi lần có người đọc (CLAUDE.md mục 5, dữ liệu y tế). Nạp sẵn cả bốn
         * tab thì mỗi lần ai đó mở hồ sơ để xem số điện thoại cũng sinh một
         * dòng "đã xem đơn thuốc kính" — và một sổ vết đầy những lần đọc không
         * có thật thì không ai đọc nổi nó nữa, đúng lúc cần tra thì chịu.
         */
        switch ($tab) {
            case 'dia-chi':
                // CHỈ danh sách, tab này không còn form nào. Cũng vì thế không
                // nạp address-picker.js nữa: nó chỉ tồn tại để nâng hai ô gõ
                // tay tỉnh/phường trong form thêm địa chỉ, mà form đó đã bỏ.
                $data['addresses'] = AddressModel::forUser($id);
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

                /* LỊCH SỬ PHIÊN BẢN CỦA MỘT LẦN ĐO — chỉ nạp khi có ?phien-ban=.

                   Từ 04/09/2026 sửa một bản ghi sinh phiên bản mới thay vì ghi
                   đè (X21), nên mỗi lần đo có thể có nhiều bản. Bảng chính chỉ
                   hiện bản mới nhất; muốn xem đường đi thì bấm vào "Đã sửa N
                   lần" và nó quay lại đây với tham số này.

                   Nạp CÓ ĐIỀU KIỆN chứ không nạp sẵn cho mọi dòng: một khách
                   đo mười lần là mười câu truy vấn cho thứ gần như không ai
                   mở. phienBan() tự kiểm quyền sở hữu bằng user_id nên tham số
                   trên URL có bị sửa cũng không lộ hồ sơ của khách khác. */
                $xemPb = trim((string) ($_GET['phien-ban'] ?? ''));

                $data['rxPhienBan'] = $xemPb !== ''
                    ? PrescriptionRecordModel::phienBan($xemPb, $id)
                    : [];

                AuditLogModel::write($id, 'rx.read');
                break;

            case 'hoat-dong':
                $data['activity']     = CustomerModel::activity($id);
                $data['orderStatuses']   = OrderModel::STATUSES;
                $data['paymentStatuses'] = OrderModel::PAYMENT_STATUSES;
                $data['apptStatuses']    = BookingModel::STATUSES;
                /* Nhãn lấy từ HẰNG CỦA CHÍNH MODULE ĐÓ, không gõ lại ở đây.
                   Gõ lại nghĩa là khi bên kia đổi chữ "Đang hiện" thành "Đã
                   duyệt" thì màn hình này vẫn nói chữ cũ — và người đọc hai
                   màn hình sẽ tưởng đó là hai trạng thái.

                   KHÔNG có nhãn cho liên hệ: module đó bỏ hẳn trạng thái ngày
                   2026-08-26, yêu cầu chạy thẳng sang Zalo CSKH. Khối liên hệ
                   ở tab Hoạt động nay chỉ còn ngày gửi và nội dung. */
                $data['reviewStatuses']  = ReviewModel::STATUSES;
                break;
        }

        $this->renderAdmin('admin/customers/index', $data);
    }

    // ========================================================================
    // TRẠNG THÁI TÀI KHOẢN
    //
    // KHÔNG có action sửa hồ sơ ở trên — xem khối "HỒ SƠ KHÁCH LÀ CHỈ XEM" ở
    // đầu file trước khi thêm lại một cái.
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

    // ========================================================================
    // KHÔNG CÓ ACTION NÀO CHO HỒ SƠ VÀ SỔ ĐỊA CHỈ
    //
    // Cả hai là chỉ xem — đọc khối "DỮ LIỆU CỦA KHÁCH LÀ CHỈ XEM" ở đầu file
    // trước khi thêm lại. AddressModel vẫn có đủ create / updateOwned /
    // deleteOwned / setDefault và vẫn đang chạy cho đường của khách ở
    // /tai-khoan?muc=dia-chi; mở lại cho nhân viên thì gọi đúng chúng, đừng
    // chép luật sang đây — và nhớ thêm cả route lẫn vết audit.
    // ========================================================================

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
    // NỘI BỘ
    // ========================================================================

    /**
     * Mở đầu MỌI action POST: kiểm phương thức, kiểm CSDL đã nâng cấp chưa,
     * và lấy ra id khách hợp lệ.
     *
     * Gom vào một hàm vì bốn bước này phải chạy ở CẢ SÁU action ghi còn lại. Để
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

    /** Bản ghi đơn thuốc đang mở trong form sửa (?sua=<id>), hoặc null. */
    private function rxDangSua(string $userId): ?array
    {
        $id = trim((string) ($_GET['sua'] ?? ''));

        return $id !== '' ? PrescriptionRecordModel::findOwned($id, $userId) : null;
    }

    /** Quay về đúng tab vừa đứng sau một POST. */
    private function veTab(string $id, string $tab): never
    {
        redirect(self::BASE . '/' . rawurlencode($id) . '?tab=' . rawurlencode($tab));
    }
}
