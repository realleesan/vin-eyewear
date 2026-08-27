<?php

/**
 * AppointmentAdminController — lịch hẹn (/quan-tri/lich-hen).
 *
 * Port từ src/routes/_authenticated/quan-tri/lich-hen.tsx.
 */

class AppointmentAdminController extends AdminController
{
    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');

        if ($status !== '' && !isset(BookingModel::STATUSES[$status])) {
            $status = '';
        }

        $this->renderAdmin('admin/appointments/index', [
            'pageTitle'    => 'Lịch hẹn — Quản trị',
            'appointments' => BookingModel::withStore($status, 200),
            'status'       => $status,
            'statuses'     => BookingModel::STATUSES,
            /* Hai danh sách khác nhau, cố ý: `statuses` là NHÃN của cả bốn
               trạng thái (dải viên lọc và viên nhãn cần đủ bốn), còn
               `staffStatuses` là hai thứ ô chọn được phép đặt. Xem khối chú
               thích của BookingModel::STAFF_STATUSES. */
            'staffStatuses' => BookingModel::STAFF_STATUSES,
            'counts'       => $this->statusCounts(),
        ]);
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
        $this->requirePost('/quan-tri/lich-hen');

        $id     = (string) ($_POST['id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');

        if (!in_array($status, BookingModel::STAFF_STATUSES, true)) {
            flash('admin_error', 'Trạng thái không hợp lệ.');
            redirect('/quan-tri/lich-hen');
        }

        if (!BookingModel::exists(['id' => $id])) {
            flash('admin_error', 'Không tìm thấy lịch hẹn.');
            redirect('/quan-tri/lich-hen');
        }

        BookingModel::update($id, ['status' => $status]);

        // Không còn khung giờ nào để "trả lại": cửa hàng đã bỏ giới hạn số
        // người trên một khung — xem khối chú thích đầu BookingModel.
        flash('admin_success', 'Đã cập nhật trạng thái lịch hẹn.');
        redirect('/quan-tri/lich-hen');
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
        $this->requirePost('/quan-tri/lich-hen');

        $id = (string) ($_POST['id'] ?? '');

        if (!BookingModel::exists(['id' => $id])) {
            flash('admin_error', 'Không tìm thấy lịch hẹn.');
            redirect('/quan-tri/lich-hen');
        }

        BookingModel::update($id, ['status' => 'cancelled']);

        flash('admin_success', 'Đã huỷ lịch hẹn.');
        redirect('/quan-tri/lich-hen');
    }

    private function statusCounts(): array
    {
        $rows   = Database::fetchAll('SELECT status, COUNT(*) AS n FROM appointments GROUP BY status');
        $counts = ['' => 0];

        foreach (array_keys(BookingModel::STATUSES) as $key) {
            $counts[$key] = 0;
        }

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
            $counts['']            += (int) $row['n'];
        }

        return $counts;
    }
}
