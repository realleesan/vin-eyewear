<?php

/**
 * StoreAdminController — cơ sở cửa hàng (/quan-tri/co-so).
 *
 * Port từ src/routes/_authenticated/quan-tri/co-so.tsx
 * và components/admin-store-form.tsx.
 *
 * Khớp policy "admin stores" (migration 20260811054139): chỉ admin/manager
 * được thêm/sửa/xoá.
 */

class StoreAdminController extends AdminController
{
    private const BASE = '/quan-tri/co-so';

    public function index(): void
    {
        $this->renderAdmin('admin/stores/index', [
            'pageTitle' => 'Cơ sở — Quản trị',
            'stores'    => StoreModel::all('code ASC'),
            'canEdit'   => UserModel::hasRole($this->userId, 'admin')
                        || UserModel::hasRole($this->userId, 'manager'),
            'editing'   => isset($_GET['sua']) ? StoreModel::find((string) $_GET['sua']) : null,
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id      = (string) ($_POST['id'] ?? '');
        $code    = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $name    = trim((string) ($_POST['name'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if (!preg_match('/^[A-Z0-9_]{2,40}$/', $code)) {
            flash('admin_error', 'Mã cơ sở chỉ gồm chữ IN HOA, số và gạch dưới (2–40 ký tự).');
            redirect(self::BASE);
        }

        if (utf8Length($name) < 2 || utf8Length($address) < 5) {
            flash('admin_error', 'Vui lòng nhập tên và địa chỉ đầy đủ.');
            redirect(self::BASE);
        }

        $clash = StoreModel::findBy('code', $code);
        if ($clash !== null && $clash['id'] !== $id) {
            flash('admin_error', sprintf('Mã "%s" đã được dùng cho cơ sở khác.', $code));
            redirect(self::BASE);
        }

        $mapUrl = trim((string) ($_POST['map_url'] ?? ''));

        // Bản đồ nhúng bằng <iframe>, nên chỉ nhận địa chỉ Google Maps.
        // Không kiểm thì người có quyền quản trị nhúng được trang bất kỳ vào
        // site — kể cả trang giả mạo form đăng nhập.
        if ($mapUrl !== '') {
            $host = parse_url($mapUrl, PHP_URL_HOST) ?? '';
            if (!preg_match('/(^|\.)google\.com$/', $host)) {
                flash('admin_error', 'Địa chỉ bản đồ phải là liên kết nhúng của Google Maps.');
                redirect(self::BASE);
            }
        }

        $data = [
            'code'       => $code,
            'name'       => $name,
            'address'    => $address,
            'phone'      => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'open_hours' => trim((string) ($_POST['open_hours'] ?? '')) ?: null,
            'map_url'    => $mapUrl ?: null,
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id !== '' && StoreModel::exists(['id' => $id])) {
            StoreModel::update($id, $data);
            flash('admin_success', 'Đã cập nhật cơ sở.');
        } else {
            StoreModel::insert($data);
            flash('admin_success', 'Đã thêm cơ sở mới.');
        }

        redirect(self::BASE);
    }

    public function delete(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id = (string) ($_POST['id'] ?? '');

        // Khoá ngoại appointments.store_id KHÔNG có ON DELETE, nên DB sẽ từ
        // chối xoá cơ sở còn lịch hẹn. Kiểm trước để báo lỗi dễ hiểu và gợi ý
        // cách xử lý, thay vì để lỗi 1451 thô lọt ra.
        $count = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM appointments WHERE store_id = :id',
            ['id' => $id]
        );

        if ($count > 0) {
            flash('admin_error', sprintf(
                'Không xoá được: còn %d lịch hẹn tại cơ sở này. Hãy tắt "đang hoạt động" thay vì xoá.',
                $count
            ));
            redirect(self::BASE);
        }

        StoreModel::delete($id);

        flash('admin_success', 'Đã xoá cơ sở.');
        redirect(self::BASE);
    }

    /**
     * Tạm đóng hoặc mở lại một cơ sở (POST .../hoat-dong).
     *
     * KHÔNG phải xoá: cơ sở tạm đóng vẫn còn trong bảng, lịch hẹn đã đặt ở đó
     * vẫn tra được, chỉ là nó biến mất khỏi bước "chọn nơi hẹn" của khách. Đó
     * đúng là việc cần làm khi một cửa hàng sửa chữa vài tuần — và là lý do
     * nút này đứng cạnh nút Xoá chứ không thay nó.
     */
    public function toggle(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id    = (string) ($_POST['id'] ?? '');
        $store = StoreModel::find($id);

        if ($store === null) {
            flash('admin_error', 'Không tìm thấy cơ sở.');
            redirect(self::BASE);
        }

        $mo = (int) $store['is_active'] !== 1;
        StoreModel::update($id, ['is_active' => $mo ? 1 : 0]);

        flash(
            'admin_success',
            $mo
                ? sprintf('Đã mở lại %s — khách đặt hẹn được ở đây.', $store['name'])
                : sprintf('Đã tạm đóng %s — khách không đặt hẹn được ở đây nữa.', $store['name'])
        );

        redirect(self::BASE);
    }
}
