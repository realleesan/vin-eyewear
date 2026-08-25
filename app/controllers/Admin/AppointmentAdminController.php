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
            'counts'       => $this->statusCounts(),
        ]);
    }

    public function updateStatus(): void
    {
        $this->requirePost('/quan-tri/lich-hen');

        $id     = (string) ($_POST['id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');

        if (!isset(BookingModel::STATUSES[$status])) {
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
     * Nhân viên chốt giờ hẹn sau cuộc gọi xác nhận (POST /quan-tri/lich-hen/gio).
     *
     * KHÔNG gọi requireManager(): đây là việc của người trực quầy, cùng hạng
     * với đổi trạng thái lịch ngay bên trên. Policy gốc cho staff xử lý lịch
     * hẹn; bắt phải là manager mới điền được giờ vừa hẹn qua điện thoại thì
     * chính người gọi lại không ghi được.
     *
     * Không đẩy thông báo Zalo: tin Zalo tồn tại để BÁO NHÂN VIÊN có lịch mới
     * (xem core/Zalo.php), mà ở đây chính nhân viên là người vừa thao tác.
     * Khách thì đã biết giờ — họ vừa nghe qua điện thoại xong.
     */
    public function updateTime(): void
    {
        $this->requirePost('/quan-tri/lich-hen');

        $result = BookingModel::setTimeSlot(
            (string) ($_POST['id'] ?? ''),
            (string) ($_POST['time_slot'] ?? '')
        );

        if (!$result['ok']) {
            flash('admin_error', $result['error']);
            redirect('/quan-tri/lich-hen');
        }

        flash(
            'admin_success',
            $result['slot'] === ''
                ? 'Đã xoá giờ hẹn.'
                : 'Đã chốt giờ hẹn ' . $result['slot'] . '.'
        );

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
