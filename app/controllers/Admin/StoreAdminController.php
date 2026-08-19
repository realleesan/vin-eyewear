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

        $ajax = $this->isJsonRequest();
        $id      = (string) ($_POST['id'] ?? '');
        $code    = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $name    = trim((string) ($_POST['name'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if (!preg_match('/^[A-Z0-9_]{2,40}$/', $code)) {
            $message = 'Mã cơ sở chỉ gồm chữ IN HOA, số và gạch dưới (2–40 ký tự).';
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect(self::BASE);
        }

        if (utf8Length($name) < 2 || utf8Length($address) < 5) {
            $message = 'Vui lòng nhập tên và địa chỉ đầy đủ.';
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect(self::BASE);
        }

        $clash = StoreModel::findBy('code', $code);
        if ($clash !== null && $clash['id'] !== $id) {
            $message = sprintf('Mã "%s" đã được dùng cho cơ sở khác.', $code);
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect(self::BASE);
        }

        $mapUrl = trim((string) ($_POST['map_url'] ?? ''));

        if ($mapUrl !== '') {
            $host = parse_url($mapUrl, PHP_URL_HOST) ?? '';
            if (!preg_match('/(^|\.)google\.com$/', $host)) {
                $message = 'Địa chỉ bản đồ phải là liên kết nhúng của Google Maps.';
                if ($ajax) {
                    $this->jsonReply(false, $message);
                }
                flash('admin_error', $message);
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

        try {
            if ($id !== '' && StoreModel::exists(['id' => $id])) {
                StoreModel::update($id, $data);
                $message = 'Đã cập nhật cơ sở.';
            } else {
                StoreModel::insert($data);
                $message = 'Đã thêm cơ sở mới.';
            }

            if ($ajax) {
                $this->jsonReply(true, $message, self::BASE);
            }

            flash('admin_success', $message);
            redirect(self::BASE);
        } catch (Throwable $e) {
            $message = 'Lỗi khi lưu dữ liệu: ' . $e->getMessage();
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect(self::BASE);
        }
    }

    private function isJsonRequest(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xRequested = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $xRequested === 'xmlhttprequest';
    }

    private function jsonReply(bool $success, string $message, ?string $redirect = null): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'redirect' => $redirect,
        ], JSON_UNESCAPED_UNICODE);
        exit;
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
}
