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
