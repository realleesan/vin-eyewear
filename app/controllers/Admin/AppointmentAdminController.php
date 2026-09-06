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
        $counts = BookingModel::statusCounts($q, $coSo);
        $tong   = (int) ($counts[$status] ?? 0);

        $soTrang = max(1, (int) ceil($tong / self::PER_PAGE));

        /* Kẹp vào dải hợp lệ thay vì trả trang rỗng: ?page=99 hay ?page=abc đều
           là địa chỉ sửa tay hoặc một liên kết cũ sau khi lịch cũ bị lọc đi, mà
           một bảng trống không nói được điều đó. */
        $trang  = min(max(1, (int) ($_GET['page'] ?? 1)), $soTrang);
        $offset = ($trang - 1) * self::PER_PAGE;

        $this->renderAdmin('admin/appointments/index', [
            'pageTitle'    => 'Lịch hẹn — Quản trị',
            'appointments' => BookingModel::withStore($status, self::PER_PAGE, $q, $coSo, $offset),
            'total'        => $tong,
            'page'         => $trang,
            'totalPages'   => $soTrang,
            'status'       => $status,
            'q'            => $q,
            'coSo'         => $coSo,
            /* Ô lọc liệt kê MỌI cơ sở — phạm vi cơ sở đã gỡ (SRS v2.1.0, K06). */
            'stores'       => StoreModel::all('name ASC'),
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

        if (!$this->coLichHen($id, $ve)) {
            return;
        }

        BookingModel::update($id, ['status' => $status]);

        AuditLogModel::write(
            BookingModel::chuLich($id),
            'booking.status',
            'Đổi trạng thái lịch hẹn sang ' . (BookingModel::STATUSES[$status] ?? $status)
        );

        /* Thư xác nhận — FR-EM-03. CHỈ mốc 'confirmed'.

           STAFF_STATUSES có hai giá trị, và 'done' không sinh thư: khách vừa
           rời cửa hàng sau buổi khám, một lá thư báo "buổi hẹn đã hoàn tất"
           không nói với họ điều gì họ chưa biết. 'confirmed' thì ngược lại —
           đó là câu trả lời cho việc họ đặt lịch rồi ngồi đợi cửa hàng gọi. */
        if ($status === 'confirmed') {
            $lich = BookingModel::find($id);

            if ($lich !== null) {
                EmailEvents::lichHen($lich, 'xac_nhan');
            }
        }

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

        if (!$this->coLichHen($id, $ve)) {
            return;
        }

        $chu = BookingModel::chuLich($id);

        BookingModel::update($id, ['status' => 'cancelled']);

        AuditLogModel::write($chu, 'booking.cancel', 'Huỷ lịch hẹn');

        /* Thư báo huỷ — FR-EM-03, và ở đường NÀY nó quan trọng hơn hẳn đường
           khách tự huỷ: khách không hề biết buổi hẹn của mình vừa mất nếu
           không có ai nói. Họ vẫn sẽ tới cửa hàng vào đúng ngày ấy. */
        $lich = BookingModel::find($id);

        if ($lich !== null) {
            EmailEvents::lichHen($lich, 'huy');
        }

        flash('admin_success', 'Đã huỷ lịch hẹn.');
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

        if (!$this->coLichHen($id, $ve)) {
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

        /* KHÔNG ĐẨY ZALO nữa — SRS v2.1.0, G12. Cửa hàng theo dõi lịch hẹn
           bằng huy hiệu trên thanh bên khu quản trị. */

        flash('admin_success', 'Đã dời lịch hẹn sang ngày mới.');
        redirect($ve);
    }

    /**
     * Chặn thao tác ghi lên một lịch hẹn KHÔNG TỒN TẠI.
     *
     * Gộp phép kiểm vào một chỗ: ba action ghi đều cần đúng một câu hỏi, và ba
     * chỗ tự hỏi là ba cơ hội quên.
     *
     * Trước đây hàm này còn kiểm phạm vi cơ sở; phạm vi đã gỡ theo SRS v2.1.0
     * (K06) nên chỉ còn phép kiểm tồn tại.
     */
    private function coLichHen(string $id, string $ve): bool
    {
        if ($id !== '' && BookingModel::exists(['id' => $id])) {
            return true;
        }

        flash('admin_error', 'Không tìm thấy lịch hẹn.');
        redirect($ve);
    }

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * KHÔNG CÒN TẠO LỊCH HẸN Ở KHU QUẢN TRỊ — SRS v2.1.0, G10
     *
     * Trước đây lớp này có action store() cho nhân viên tạo lịch hộ khách gọi
     * điện, kèm một hộp thoại "Tạo lịch hẹn" trong view và một hàm
     * coSoChonDuoc() lọc ô chọn cơ sở theo phạm vi.
     *
     * Chủ đầu tư đã bỏ đường vào này: khách gọi điện thì nhân viên hướng dẫn
     * khách tự đặt trên web. Giữ đúng MỘT đường tạo lịch (BookingController)
     * cũng có nghĩa là chỉ còn một bộ luật về ngày hợp lệ và một cách sinh mã.
     *
     * Đừng dựng lại action này mà không sửa SRS trước.
     * ─────────────────────────────────────────────────────────────────────────
     */
}
