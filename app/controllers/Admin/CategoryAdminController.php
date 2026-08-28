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
            /*
             * ĐẾM SẢN PHẨM TRONG TỪNG DANH MỤC — thêm theo bản thiết kế
             * "Danh mục.dc.html".
             *
             * Không dùng CategoryModel::all() nữa vì con số ấy không nằm trong
             * bảng `categories`. Truy vấn con thay cho LEFT JOIN + GROUP BY:
             * bảng này có bốn tới mười dòng, nên bốn tới mười lượt đếm rẻ hơn
             * là gom nhóm cả bảng products — và câu lệnh đọc ra ngay được.
             *
             * Con số này trả lời đúng câu người ta hỏi trước khi bấm Xoá: xoá
             * danh mục thì mấy sản phẩm mất chỗ đứng?
             */
            'categories' => Database::fetchAll(
                'SELECT c.*,
                        (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
                   FROM categories c
                  ORDER BY c.sort_order ASC, c.name ASC'
            ),
            'canEdit'    => UserModel::hasRole($this->userId, 'admin')
                         || UserModel::hasRole($this->userId, 'manager'),
            'editing'    => isset($_GET['sua']) ? CategoryModel::find((string) $_GET['sua']) : null,
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id   = (string) ($_POST['id'] ?? '');
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));

        if (utf8Length($name) < 2) {
            flash('admin_error', 'Tên danh mục phải có ít nhất 2 ký tự.');
            redirect(self::BASE);
        }

        // Slug bỏ trống thì tự sinh từ tên — người nhập nội dung không cần
        // hiểu slug là gì, nhưng vẫn sửa được khi cần.
        $slug = $slug !== '' ? slugify($slug) : slugify($name);

        if ($slug === '') {
            flash('admin_error', 'Không tạo được slug từ tên này, vui lòng nhập slug thủ công.');
            redirect(self::BASE);
        }

        // Slug phải là duy nhất. Kiểm trước để báo lỗi dễ hiểu, thay vì để
        // ràng buộc UNIQUE của DB ném lỗi 1062 thô ra màn hình.
        $clash = CategoryModel::findBy('slug', $slug);
        if ($clash !== null && $clash['id'] !== $id) {
            flash('admin_error', sprintf('Slug "%s" đã được dùng cho danh mục khác.', $slug));
            redirect(self::BASE);
        }

        $data = [
            'slug'        => $slug,
            'name'        => $name,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
            'is_visible'  => isset($_POST['is_visible']) ? 1 : 0,
        ];

        if ($id !== '' && CategoryModel::exists(['id' => $id])) {
            CategoryModel::update($id, $data);
            flash('admin_success', 'Đã cập nhật danh mục.');
        } else {
            CategoryModel::insert($data);
            flash('admin_success', 'Đã thêm danh mục mới.');
        }

        redirect(self::BASE);
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
