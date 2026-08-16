<?php

/**
 * EventAdminController — sự kiện (/quan-tri/su-kien).
 *
 * Port từ src/routes/_authenticated/quan-tri/su-kien.tsx
 * và components/admin-event-form.tsx.
 */

class EventAdminController extends AdminController
{
    private const BASE = '/quan-tri/su-kien';

    public function index(): void
    {
        $this->renderAdmin('admin/events/index', [
            'pageTitle'  => 'Sự kiện — Quản trị',
            'events'     => EventModel::all('starts_at DESC'),
            'categories' => EventModel::categories(),
            'canEdit'    => UserModel::hasRole($this->userId, 'admin')
                         || UserModel::hasRole($this->userId, 'manager'),
            'editing'    => isset($_GET['sua']) ? EventModel::find((string) $_GET['sua']) : null,
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id    = (string) ($_POST['id'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug  = trim((string) ($_POST['slug'] ?? ''));

        if (utf8Length($title) < 4) {
            flash('admin_error', 'Tiêu đề phải có ít nhất 4 ký tự.');
            redirect(self::BASE);
        }

        $slug = $slug !== '' ? slugify($slug) : slugify($title);

        if ($slug === '') {
            flash('admin_error', 'Không tạo được slug từ tiêu đề này, vui lòng nhập slug thủ công.');
            redirect(self::BASE);
        }

        $clash = EventModel::findBy('slug', $slug);
        if ($clash !== null && $clash['id'] !== $id) {
            flash('admin_error', sprintf('Slug "%s" đã được dùng cho sự kiện khác.', $slug));
            redirect(self::BASE);
        }

        $startsAt = $this->toDateTime($_POST['starts_at'] ?? '');
        $endsAt   = $this->toDateTime($_POST['ends_at'] ?? '');

        // Kết thúc không được trước khi bắt đầu — nếu lọt, dateRange() sẽ in
        // ra khoảng thời gian ngược và upcoming() phân loại sai.
        if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
            flash('admin_error', 'Thời gian kết thúc phải sau thời gian bắt đầu.');
            redirect(self::BASE);
        }

        $data = [
            'slug'        => $slug,
            'title'       => $title,
            'category'    => trim((string) ($_POST['category'] ?? '')) ?: null,
            'excerpt'     => trim((string) ($_POST['excerpt'] ?? '')) ?: null,
            'content'     => trim((string) ($_POST['content'] ?? '')) ?: null,
            'cover_image' => trim((string) ($_POST['cover_image'] ?? '')) ?: null,
            'location'    => trim((string) ($_POST['location'] ?? '')) ?: null,
            'starts_at'   => $startsAt,
            'ends_at'     => $endsAt,
            'is_visible'  => isset($_POST['is_visible']) ? 1 : 0,
        ];

        if ($id !== '' && EventModel::exists(['id' => $id])) {
            EventModel::update($id, $data);
            flash('admin_success', 'Đã cập nhật sự kiện.');
        } else {
            EventModel::insert($data);
            flash('admin_success', 'Đã thêm sự kiện mới.');
        }

        redirect(self::BASE);
    }

    public function delete(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        EventModel::delete((string) ($_POST['id'] ?? ''));

        flash('admin_success', 'Đã xoá sự kiện.');
        redirect(self::BASE);
    }

    /**
     * Đổi giá trị <input type="datetime-local"> sang định dạng DATETIME.
     *
     * Trình duyệt gửi "2026-09-05T10:00"; MySQL cần "2026-09-05 10:00:00".
     * Trả null khi để trống — cột cho phép NULL với sự kiện chỉ có một mốc.
     */
    private function toDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime(str_replace('T', ' ', $value));

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
