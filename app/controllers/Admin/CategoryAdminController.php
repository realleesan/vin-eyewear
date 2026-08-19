<?php

/**
 * CategoryAdminController — danh mục (/quan-tri/danh-muc).
 *
 * Port từ src/routes/_authenticated/quan-tri/danh-muc.tsx
 * và components/admin-category-form.tsx.
 *
 * Sửa dữ liệu catalog chỉ dành cho admin/manager — khớp policy gốc
 * "admin categories". Nhân viên bán hàng (staff) xem được nhưng không sửa.
 */

class CategoryAdminController extends AdminController
{
    private const BASE = '/quan-tri/danh-muc';

    public function index(): void
    {
        $this->renderAdmin('admin/categories/index', [
            'pageTitle'  => 'Danh mục — Quản trị',
            'categories' => CategoryModel::all('sort_order ASC, name ASC'),
            'canEdit'    => UserModel::hasRole($this->userId, 'admin')
                         || UserModel::hasRole($this->userId, 'manager'),
            'editing'    => isset($_GET['sua']) ? CategoryModel::find((string) $_GET['sua']) : null,
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $ajax = $this->isJsonRequest();
        $id   = (string) ($_POST['id'] ?? '');
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));

        if (utf8Length($name) < 2) {
            $message = 'Tên danh mục phải có ít nhất 2 ký tự.';
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect(self::BASE);
        }

        $slug = $slug !== '' ? slugify($slug) : slugify($name);

        if ($slug === '') {
            $message = 'Không tạo được slug từ tên này, vui lòng nhập slug thủ công.';
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect(self::BASE);
        }

        $clash = CategoryModel::findBy('slug', $slug);
        if ($clash !== null && $clash['id'] !== $id) {
            $message = sprintf('Slug "%s" đã được dùng cho danh mục khác.', $slug);
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect(self::BASE);
        }

        $data = [
            'slug'        => $slug,
            'name'        => $name,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
            'is_visible'  => isset($_POST['is_visible']) ? 1 : 0,
        ];

        try {
            if ($id !== '' && CategoryModel::exists(['id' => $id])) {
                CategoryModel::update($id, $data);
                $message = 'Đã cập nhật danh mục.';
            } else {
                CategoryModel::insert($data);
                $message = 'Đã thêm danh mục mới.';
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

        // Danh mục còn sản phẩm thì không cho xoá. Khoá ngoại đặt
        // ON DELETE SET NULL nên DB sẽ cho xoá và bỏ rơi sản phẩm ở trạng
        // thái không phân loại — im lặng và khó phát hiện. Chặn ở đây rõ hơn.
        $count = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM products WHERE category_id = :id',
            ['id' => $id]
        );

        if ($count > 0) {
            flash('admin_error', sprintf(
                'Không xoá được: còn %d sản phẩm thuộc danh mục này. Hãy chuyển chúng sang danh mục khác trước.',
                $count
            ));
            redirect(self::BASE);
        }

        CategoryModel::delete($id);

        flash('admin_success', 'Đã xoá danh mục.');
        redirect(self::BASE);
    }
}
