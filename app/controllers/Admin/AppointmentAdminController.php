<?php

/**
 * AppointmentAdminController — lịch hẹn (/quan-tri/lich-hen).
 *
 * Port từ src/routes/_authenticated/quan-tri/lich-hen.tsx.
 */

class AppointmentAdminController extends AdminController
{
    /**
     * Số dòng mỗi trang.
     *
     * 20 như Đơn hàng, Sản phẩm và Tồn kho. Ba bảng ấy người ta lướt bằng cùng
     * một thói quen, và một bảng nhảy trang sớm hơn ba bảng kia chỉ làm người
     * dùng đếm nhầm mình đang ở đâu.
     */
    private const PER_PAGE = 20;

    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');

        if ($status !== '' && !isset(BookingModel::STATUSES[$status])) {
            $status = '';
        }

        /*
         * Ô TÌM VÀ Ô LỌC CƠ SỞ — thêm theo bản thiết kế "Lịch hẹn.dc.html".
         *
         * Trang này bày 200 lịch gần nhất, không phân trang. Hai câu hỏi có
         * thật ở quầy mà dải viên lọc theo trạng thái không trả lời được:
         * "khách vừa gọi tên X, lịch của họ hôm nào?" và "hôm nay cơ sở Tây
         * Hồ có ai đến?". Một ô gõ và một ô chọn là đủ cho cả hai.
         *
         * Mã cơ sở KHÔNG kiểm ở đây mà để nguyên chuỗi đi vào tham số ràng
         * buộc: gõ bậy trên thanh địa chỉ thì truy vấn trả 0 dòng và danh
         * sách rỗng — đúng thứ nên xảy ra, và rẻ hơn một lượt truy vấn chỉ để
         * hỏi xem cơ sở ấy có thật không.
         */
        $q     = trim((string) ($_GET['q'] ?? ''));
        $coSo  = trim((string) ($_GET['co-so'] ?? ''));

        /*
         * ─────────────────────────────────────────────────────────────────────
         * PHÂN TRANG — trước đây là một con số 200 cắt cứng
         *
         * Bản cũ gọi withStore($status, 200, …) rồi in hết ra. Nó hỏng theo
         * kiểu tệ nhất: quá 200 buổi hẹn thì những buổi CŨ NHẤT lặng lẽ biến
         * mất khỏi màn hình, không có "trang 2" nào để đi tiếp và cũng không
         * có gì trên trang nói rằng còn thứ chưa hiện. Lịch hẹn thì mỗi lượt
         * khách đặt là một dòng và không bao giờ vơi đi.
         *
         * TỔNG SỐ DÒNG LẤY TỪ DẢI VIÊN LỌC, không hỏi thêm một câu COUNT.
         * BookingModel::statusCounts() đã đếm đúng phạm vi ô tìm và ô cơ sở
         * đang bật, và có sẵn khoá cho từng trạng thái cộng khoá '' là tổng —
         * tức là nó CHÍNH LÀ tổng số dòng của viên đang chọn. Hỏi lại bằng một
         * câu COUNT riêng vừa thừa một lượt đi CSDL, vừa mở đường cho hai con
         * số lệch nhau. Cùng cách làm với trang Tồn kho.
         * ─────────────────────────────────────────────────────────────────────
         */
        /* Phạm vi cơ sở áp cho CẢ bộ đếm lẫn danh sách — xem
           StaffStoreModel và BookingModel::locWithStore(). */
        $phamVi = $this->phamViCoSo();
        $counts = BookingModel::statusCounts($q, $coSo, $phamVi);
        $tong   = (int) ($counts[$status] ?? 0);

        $soTrang = max(1, (int) ceil($tong / self::PER_PAGE));

        /* Kẹp vào dải hợp lệ thay vì trả trang rỗng: ?page=99 hay ?page=abc đều
           là địa chỉ sửa tay hoặc một liên kết cũ sau khi lịch cũ bị lọc đi, mà
           một bảng trống không nói được điều đó. */
        $trang  = min(max(1, (int) ($_GET['page'] ?? 1)), $soTrang);
        $offset = ($trang - 1) * self::PER_PAGE;

        $this->renderAdmin('admin/appointments/index', [
            'pageTitle'    => 'Lịch hẹn — Quản trị',
            'appointments' => BookingModel::withStore($status, self::PER_PAGE, $q, $coSo, $offset, $phamVi),
            'total'        => $tong,
            'page'         => $trang,
            'totalPages'   => $soTrang,
            'status'       => $status,
            'q'            => $q,
            'coSo'         => $coSo,
            /* Ô lọc CHỈ liệt kê cơ sở người này thuộc về. Liệt kê đủ hai cơ
               sở rồi chặn ở truy vấn cũng an toàn, nhưng nó mời người ta chọn
               một thứ luôn trả về rỗng — và họ sẽ báo đó là lỗi. */
            'stores'       => $this->coSoChonDuoc(),
            'gioiHanCoSo'  => $this->biGioiHanCoSo(),
            // Ô chọn dịch vụ của hộp "Tạo lịch hẹn" — dùng chung danh sách với
            // trang đặt lịch của khách, xem BookingModel::SERVICES.
            'services'     => BookingModel::SERVICES,
            'statuses'     => BookingModel::STATUSES,
            /* Hai danh sách khác nhau, cố ý: `statuses` là NHÃN của cả bốn
               trạng thái (dải viên lọc và viên nhãn cần đủ bốn), còn
               `staffStatuses` là hai thứ ô chọn được phép đặt. Xem khối chú
               thích của BookingModel::STAFF_STATUSES. */
            'staffStatuses' => BookingModel::STAFF_STATUSES,
            /* Đếm TRONG PHẠM VI ô tìm và ô cơ sở — xem BookingModel::statusCounts().
               Trước đây phép đếm nằm ngay trong controller này và luôn đếm cả
               bảng; chuyển vào model để nó dùng chung đúng mệnh đề WHERE với
               truy vấn danh sách, thay vì hai bản chép sớm muộn lệch nhau.
               Từ 2026-08-29 nó còn là nguồn của tổng số dòng cho phân trang. */
            'counts'       => $counts,
        ]);
    }

    /**
     * Địa chỉ danh sách GIỮ NGUYÊN CHỖ NGƯỜI DÙNG ĐANG ĐỨNG.
     *
     * Mọi thao tác trên trang này (đổi trạng thái, huỷ) đều là POST rồi chuyển
     * hướng thật. Trước khi có phân trang, chuyển về '/quan-tri/lich-hen' trần
     * chỉ mất bộ lọc — khó chịu nhưng còn tìm lại được. Có phân trang rồi thì
     * xác nhận một lịch ở trang 4 là bị ném về đầu danh sách, và muốn xác nhận
     * cái kế bên phải lật lại bốn trang.
     *
     * Bốn tham số lấy từ chính form vừa gửi (ô ẩn trong view), không đoán lại
     * từ Referer: Referer có thể bị trình duyệt hay proxy cắt, còn ô ẩn thì
     * luôn đi kèm.
     *
     * Trang 1 không nằm trên địa chỉ — ?page=1 và không có ?page là cùng một
     * chỗ, mà địa chỉ ngắn thì dễ đọc và dễ gửi cho nhau hơn.
     */
    private function veDanhSach(): string
    {
        $tham = array_filter([
            'status' => (string) ($_POST['status_loc'] ?? ''),
            'co-so'  => (string) ($_POST['co_so_loc'] ?? ''),
            'q'      => (string) ($_POST['q_loc'] ?? ''),
            'page'   => ($tr = max(1, (int) ($_POST['page'] ?? 1))) > 1 ? (string) $tr : '',
        ], static fn (string $v): bool => $v !== '');

        return '/quan-tri/lich-hen' . ($tham !== [] ? '?' . http_build_query($tham) : '');
    }

    /**
     * Chuyển lịch hẹn tới bước tiếp theo trong vòng đời.
     *
     * CHỈ NHẬN HAI GIÁ TRỊ trong BookingModel::STAFF_STATUSES, không phải cả
     * bốn của STATUSES. Kiểm ở đây chứ không chỉ ở chỗ dựng ô chọn: bớt hai
     * thẻ <option> đi là chuyện của GIAO DIỆN, còn ai gửi thẳng POST vẫn đặt
     * được 'pending' hay 'cancelled' như thường. Đúng nếp CLAUDE.md mục 4 —
     * ẩn nút không phải là phân quyền.
     *
     * Huỷ lịch đi bằng cancel() ngay dưới, không qua đường này.
     */
    public function updateStatus(): void
    {
        $ve = $this->veDanhSach();

        $this->requirePost($ve);

        $id     = (string) ($_POST['id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');

        if (!in_array($status, BookingModel::STAFF_STATUSES, true)) {
            flash('admin_error', 'Trạng thái không hợp lệ.');
            redirect($ve);
        }

        if (!$this->trongPhamVi($id, $ve)) {
            return;
        }

        BookingModel::update($id, ['status' => $status]);

        AuditLogModel::write(
            BookingModel::chuLich($id),
            'booking.status',
            'Đổi trạng thái lịch hẹn sang ' . (BookingModel::STATUSES[$status] ?? $status)
        );

        /* Không còn khung giờ nào để "trả lại": cửa hàng đã bỏ giới hạn số
           người trên một khung — xem khối chú thích đầu BookingModel.

           LƯU Ý dòng vừa sửa CÓ THỂ RỜI KHỎI TRANG ĐANG XEM: nếu đang đứng ở
           một viên lọc trạng thái thì đổi trạng thái là đẩy nó sang viên khác.
           Đó là hệ quả của việc lọc, không phải lỗi — và quay về đúng trang cũ
           vẫn đúng hơn quay về trang 1, vì người ta đang làm dở những dòng
           khác ở đây. */
        flash('admin_success', 'Đã cập nhật trạng thái lịch hẹn.');
        redirect($ve);
    }

    /**
     * Huỷ một lịch hẹn (POST /quan-tri/lich-hen/huy).
     *
     * ĐƯỜNG RIÊNG, KHÔNG PHẢI MỘT GIÁ TRỊ CỦA Ô CHỌN. Huỷ là ngã rẽ ra khỏi
     * vòng đời chứ không phải một bước tiến tới, và nó là thao tác duy nhất
     * trên trang này mà người thứ ba — khách hàng — chịu hậu quả. Để nó lẫn
     * trong danh sách xổ xuống thì trượt tay một nấc là mất buổi hẹn của
     * khách, và ô chọn tự gửi form nên không có bước nào để dừng lại.
     *
     * Nút gọi đường này có hỏi lại (data-confirm + onsubmit dự phòng).
     *
     * KHÔNG kiểm trạng thái hiện tại: huỷ một lịch đã huỷ thì kết quả vẫn đúng
     * là "đã huỷ", báo lỗi ở đó chỉ làm người bấm hai lần tưởng mình làm sai.
     */
    public function cancel(): void
    {
        $ve = $this->veDanhSach();

        $this->requirePost($ve);

        $id = (string) ($_POST['id'] ?? '');

        if (!$this->trongPhamVi($id, $ve)) {
            return;
        }

        $chu = BookingModel::chuLich($id);

        BookingModel::update($id, ['status' => 'cancelled']);

        AuditLogModel::write($chu, 'booking.cancel', 'Huỷ lịch hẹn');

        flash('admin_success', 'Đã huỷ lịch hẹn.');
        redirect($ve);
    }

    /**
     * Ghi nhận KHÁCH KHÔNG ĐẾN (POST /quan-tri/lich-hen/khong-den).
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ĐƯỜNG RIÊNG, KHÔNG PHẢI MỘT GIÁ TRỊ THÊM VÀO Ô CHỌN TRẠNG THÁI
     *
     * Cùng lý lẽ đã dùng cho nút "Huỷ lịch" ngay trên. Ô chọn ở cột trạng thái
     * TỰ GỬI FORM khi đổi (data-autosubmit), nên trượt tay một nấc là xong —
     * không có bước nào để dừng lại. Với hai bước tiến tới bình thường
     * (Đã xác nhận / Đã hoàn tất) thì bấm nhầm sửa lại được ngay; còn "Khách
     * không đến" là một lời ghi vào sổ về hành vi của một người thật, và nó đi
     * thẳng vào tỉ lệ trên bảng Tổng quan.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * CHỈ BUỔI HẸN ĐÃ QUA MỚI GHI ĐƯỢC — KIỂM Ở ĐÂY, KHÔNG CHỈ ẨN NÚT
     *
     * "Khách không đến" của một buổi hẹn ngày mai là câu vô nghĩa, và nó ghi đè
     * lên một lịch còn đang chờ phục vụ. View đã ẩn nút với lịch chưa tới ngày,
     * nhưng ẩn nút KHÔNG PHẢI là phân quyền: một cú POST dựng tay vẫn tới được
     * đây. Đây là chỗ luật ấy được cưỡng chế.
     *
     * Lịch ĐÃ HUỶ cũng không ghi được: khách đã báo trước thì họ không "không
     * đến", và cho ghi đè sẽ làm tỉ lệ trên bảng Tổng quan đếm cả những buổi
     * đã được huỷ đúng cách.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function markNoShow(): void
    {
        $ve = $this->veDanhSach();

        $this->requirePost($ve);

        $id = (string) ($_POST['id'] ?? '');

        if (!$this->trongPhamVi($id, $ve)) {
            return;
        }

        $lich = BookingModel::find($id);

        if ($lich === null) {
            flash('admin_error', 'Không tìm thấy lịch hẹn.');
            redirect($ve);
        }

        if ((string) $lich['appointment_date'] >= date('Y-m-d')) {
            flash('admin_error',
                'Chỉ ghi nhận khách không đến sau khi buổi hẹn đã qua ngày.');
            redirect($ve);
        }

        if ((string) $lich['status'] === 'cancelled') {
            flash('admin_error',
                'Lịch này đã huỷ nên không tính là khách không đến.');
            redirect($ve);
        }

        BookingModel::update($id, ['status' => 'no_show']);

        /* GHI VẾT — CLAUDE.md mục 4, và SNFR-11 với nhóm lịch hẹn. Mã riêng chứ
           không dùng chung 'booking.status': hai chiều có hệ quả khác nhau và
           lọc riêng được là cần. Đổi trạng thái bình thường chỉ dịch một buổi
           hẹn tới bước sau; ghi nhận không đến là kết luận về hành vi của một
           người thật, và nó nuôi một con số trên bảng Tổng quan. */
        AuditLogModel::write(
            BookingModel::chuLich($id),
            'booking.no_show',
            'Ghi nhận khách không đến — lịch ' . (string) $lich['code']
        );

        flash('admin_success', 'Đã ghi nhận khách không đến.');
        redirect($ve);
    }

    /**
     * ĐỔI NGÀY một lịch hẹn (POST /quan-tri/lich-hen/doi-ngay) — X19.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO ĐƯỜNG NÀY PHẢI TỒN TẠI
     *
     * Khách tự đổi được ngày, nhưng chỉ tới HẾT NGÀY HÔM TRƯỚC ngày hẹn — quá
     * hạn thì hệ thống bảo họ "gọi tổng đài để đổi hoặc huỷ". Tổng đài chính
     * là người ngồi ở màn hình này. Cho tới 08/09/2026 họ không có nút nào để
     * làm đúng cái việc khách vừa được bảo là gọi để làm; cách duy nhất là huỷ
     * rồi tạo lại, tức là mất mã lịch, mất ghi chú của khách và mất luôn dấu
     * vết rằng đây là cùng một buổi hẹn được dời.
     *
     * CHỈ NGÀY. Đổi cơ sở hoặc dịch vụ thì huỷ rồi đặt lại — X19 nói rõ, và
     * BookingModel::rescheduleAdmin() giải thích vì sao.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function reschedule(): void
    {
        $ve = $this->veDanhSach();

        $this->requirePost($ve);

        $id = (string) ($_POST['id'] ?? '');

        if (!$this->trongPhamVi($id, $ve)) {
            return;
        }

        $ket = BookingModel::rescheduleAdmin($id, trim((string) ($_POST['appointment_date'] ?? '')));

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            redirect($ve);
        }

        $lich = BookingModel::find($id);

        AuditLogModel::write(
            $lich['user_id'] ?? null,
            'booking.reschedule',
            sprintf(
                'Dời lịch hẹn %s: %s -> %s',
                (string) ($lich['code'] ?? $id),
                formatDate($ket['truoc']),
                formatDate((string) ($lich['appointment_date'] ?? ''))
            )
        );

        /* BÁO CHO KHÁCH — cùng lý lẽ với đường khách tự đổi: tin Zalo cũ nay
           ghi sai ngày, và một tin sai còn tệ hơn không có tin nào. Lịch tạo ở
           quầy cho khách vãng lai vẫn có số điện thoại nên vẫn gửi được. */
        if ($lich !== null) {
            Zalo::appointment($lich, 'rescheduled');
        }

        flash('admin_success', 'Đã dời lịch hẹn sang ngày mới.');
        redirect($ve);
    }

    /**
     * Chặn thao tác ghi lên lịch hẹn NGOÀI phạm vi cơ sở của người bấm.
     *
     * Gộp cả phép kiểm tồn tại vào đây và trả về false thay vì ném: ba action
     * đều cần đúng một câu hỏi, và ba chỗ tự hỏi là ba cơ hội quên — mà cái
     * quên đó không gây lỗi, nó chỉ lặng lẽ cho người ta sửa dữ liệu của cơ sở
     * khác. Xem BookingModel::trongPhamVi().
     *
     * MỘT CÂU BÁO CHO CẢ HAI TRƯỜNG HỢP (không tồn tại · ngoài phạm vi): trả
     * lời khác nhau là nói cho người dò biết id nào có thật.
     */
    private function trongPhamVi(string $id, string $ve): bool
    {
        if ($id !== '' && BookingModel::trongPhamVi($id, $this->phamViCoSo())) {
            return true;
        }

        flash('admin_error', 'Không tìm thấy lịch hẹn trong phạm vi cơ sở của bạn.');
        redirect($ve);
    }

    /**
     * Tạo một lịch hẹn ngay trong khu quản trị (POST /quan-tri/lich-hen/tao).
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO KHU QUẢN TRỊ CẦN TẠO ĐƯỢC LỊCH
     *
     * Phần lớn lịch tới từ trang đặt lịch của khách. Nhưng có hai đường vào
     * khác, và cả hai đều có thật ở một cửa hàng kính: khách GỌI ĐIỆN đặt, và
     * khách ĐANG ĐỨNG Ở QUẦY hẹn quay lại lấy kính hôm sau. Không tạo được ở
     * đây thì nhân viên hoặc ghi ra giấy, hoặc tự vào trang khách đặt hộ bằng
     * số điện thoại của chính mình — cách thứ hai làm hỏng cả cột `phone` lẫn
     * mọi thống kê sau này.
     *
     * DÙNG LẠI BookingModel::create(), không viết INSERT riêng: hàm đó đã chặn
     * ngày quá khứ và cơ sở không nhận khách, và nó sinh mã lịch theo đúng một
     * cách. Một đường ghi thứ hai là một bộ luật thứ hai sẽ lệch dần.
     *
     * `userId` để NULL: lịch này không thuộc tài khoản nào cả. Gắn bừa vào tài
     * khoản nhân viên thì nó hiện trong trang "Lịch hẹn của tôi" của người đó.
     * ─────────────────────────────────────────────────────────────────────────
     */
    /**
     * Danh sách cơ sở hiện trong ô lọc — đã cắt theo phạm vi của người xem.
     */
    private function coSoChonDuoc(): array
    {
        $tatCa  = StoreModel::all('name ASC');
        $phamVi = $this->phamViCoSo();

        if ($phamVi === null) {
            return $tatCa;
        }

        return array_values(array_filter(
            $tatCa,
            static fn (array $s): bool => in_array($s['id'], $phamVi, true)
        ));
    }

    public function store(): void
    {
        $this->requirePost('/quan-tri/lich-hen');

        $ten   = trim((string) ($_POST['full_name'] ?? ''));
        $sdt   = trim((string) ($_POST['phone'] ?? ''));
        $ngay  = trim((string) ($_POST['appointment_date'] ?? ''));
        $coSo  = (string) ($_POST['store_id'] ?? '');
        $dv    = (string) ($_POST['service_type'] ?? '');
        $ghi   = trim((string) ($_POST['note'] ?? ''));

        /* Mở lại hộp thoại khi thiếu ô — nếu chỉ đá về danh sách thì người
           dùng mất hết những gì vừa gõ và không biết ô nào sai. */
        $quayLaiHop = '/quan-tri/lich-hen?them=1';

        if ($ten === '' || $sdt === '' || $ngay === '') {
            flash('admin_error', 'Vui lòng nhập tên khách, số điện thoại và ngày hẹn.');
            redirect($quayLaiHop);
        }

        /* Kiểm dịch vụ có trong danh sách: ô chọn chỉ bày bốn giá trị, nhưng ai
           gửi thẳng POST vẫn ghi được chuỗi bất kỳ vào `service_type` — mà cột
           đó là thứ nhân viên đọc để chuẩn bị máy đo. */
        if (!in_array($dv, BookingModel::SERVICES, true)) {
            flash('admin_error', 'Dịch vụ không hợp lệ.');
            redirect($quayLaiHop);
        }

        /*
         * ─────────────────────────────────────────────────────────────────────
         * TẠO LỊCH CŨNG PHẢI TRONG PHẠM VI CƠ SỞ — bổ sung 09/09/2026
         *
         * Ba thao tác ghi kia (đổi trạng thái, huỷ, dời ngày) đã siết từ 09/09,
         * nhưng TẠO thì không. Ô chọn cơ sở trong hộp thoại có lọc theo phạm vi
         * (coSoChonDuoc), nhưng đó là HTML — một cú POST đặt store_id của cơ sở
         * khác đi lọt thẳng.
         *
         * Lỗi này KHÔNG BAO GIỜ TỰ LỘ RA, và đó là chỗ nguy hiểm: tạo xong thì
         * chính người tạo cũng không nhìn thấy lịch ấy nữa (danh sách có lọc
         * phạm vi), nên quầy bên kia nhận một buổi hẹn không ai biết từ đâu tới
         * và không ai nhận là mình đã tạo.
         * ─────────────────────────────────────────────────────────────────────
         */
        $phamVi = $this->phamViCoSo();

        if ($phamVi !== null && !in_array($coSo, $phamVi, true)) {
            flash('admin_error', 'Bạn chỉ tạo được lịch hẹn cho cơ sở mình phụ trách.');
            redirect($quayLaiHop);
        }

        $ket = BookingModel::create([
            'userId'      => null,
            'storeId'     => $coSo,
            'date'        => $ngay,
            'serviceType' => $dv,
            'fullName'    => $ten,
            'phone'       => $sdt,
            'note'        => $ghi,
        ]);

        if (!$ket['ok']) {
            flash('admin_error', $ket['error']);
            redirect($quayLaiHop);
        }

        /* Lịch tạo ở quầy vẫn để 'pending' như lịch khách tự đặt — BookingModel
           đặt mặc định đó. Nghe hơi thừa (nhân viên vừa nói chuyện với khách
           xong), nhưng "đã xác nhận" ở đây nghĩa là ĐÃ GỌI LẠI CHỐT GIỜ, mà
           việc ấy chưa xảy ra. Người tạo bấm thêm một cái nếu muốn. */
        flash('admin_success', sprintf('Đã tạo lịch hẹn %s cho %s.', $ket['code'], $ten));
        redirect('/quan-tri/lich-hen');
    }
}
