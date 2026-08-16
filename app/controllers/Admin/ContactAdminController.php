<?php

/**
 * ContactAdminController — yêu cầu liên hệ (/quan-tri/lien-he).
 *
 * Port từ src/routes/_authenticated/quan-tri/lien-he.tsx.
 */

class ContactAdminController extends AdminController
{
    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');

        if ($status !== '' && !isset(ContactModel::STATUSES[$status])) {
            $status = '';
        }

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = ContactModel::paginateAdmin($status, $page, 20);

        $this->renderAdmin('admin/contacts/index', [
            'pageTitle'  => 'Liên hệ — Quản trị',
            'contacts'   => $result['items'],
            'total'      => $result['total'],
            'page'       => $result['page'],
            'totalPages' => $result['totalPages'],
            'status'     => $status,
            'statuses'   => ContactModel::STATUSES,
            'counts'     => $this->statusCounts(),
        ]);
    }

    public function updateStatus(): void
    {
        $this->requirePost('/quan-tri/lien-he');

        $id     = (string) ($_POST['id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');

        if (!isset(ContactModel::STATUSES[$status])) {
            flash('admin_error', 'Trạng thái không hợp lệ.');
            redirect('/quan-tri/lien-he');
        }

        if (!ContactModel::exists(['id' => $id])) {
            flash('admin_error', 'Không tìm thấy yêu cầu liên hệ.');
            redirect('/quan-tri/lien-he');
        }

        ContactModel::update($id, ['status' => $status]);

        flash('admin_success', 'Đã cập nhật trạng thái.');
        redirect('/quan-tri/lien-he');
    }

    private function statusCounts(): array
    {
        $rows   = Database::fetchAll('SELECT status, COUNT(*) AS n FROM contact_requests GROUP BY status');
        $counts = ['' => 0];

        foreach (array_keys(ContactModel::STATUSES) as $key) {
            $counts[$key] = 0;
        }

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
            $counts['']            += (int) $row['n'];
        }

        return $counts;
    }
}
