<?php

/**
 * InventoryAdminController — tồn kho (/quan-tri/ton-kho).
 *
 * Port từ src/routes/_authenticated/quan-tri/ton-kho.tsx.
 *
 * Tách khỏi trang Sản phẩm vì đây là việc thao tác hằng ngày (nhập hàng,
 * kiểm kê) chứ không phải sửa thông tin sản phẩm.
 */

class InventoryAdminController extends AdminController
{
    /** Ngưỡng cảnh báo sắp hết hàng. */
    private const LOW = 5;

    public function index(): void
    {
        $filter = (string) ($_GET['loc'] ?? '');

        $where = match ($filter) {
            'low' => 'WHERE stock_quantity > 0 AND stock_quantity <= ' . self::LOW,
            'out' => 'WHERE stock_quantity <= 0',
            default => '',
        };

        $this->renderAdmin('admin/inventory/index', [
            'pageTitle' => 'Tồn kho — Quản trị',
            'products'  => Database::fetchAll(
                "SELECT id, slug, sku, name, brand, stock_quantity, status, price
                   FROM products
                   {$where}
                  ORDER BY stock_quantity ASC, name ASC"
            ),
            'filter'    => $filter,
            'low'       => self::LOW,
            // Bí danh KHÔNG được đặt là `out` hay `low`: cả hai là từ khoá
            // dành riêng của MySQL (out dùng cho tham số stored procedure),
            // đặt vậy thì câu lệnh lỗi cú pháp 1064.
            'counts'    => Database::fetchOne(
                'SELECT
                    COUNT(*)                                                       AS total,
                    SUM(stock_quantity > 0 AND stock_quantity <= ' . self::LOW . ') AS low_stock,
                    SUM(stock_quantity <= 0)                                       AS out_stock
                   FROM products'
            ),
        ]);
    }

    /**
     * Đặt lại số tồn cho một sản phẩm.
     *
     * Chỉ admin/manager: đây là con số ảnh hưởng trực tiếp tới việc bán hàng,
     * nhân viên bán hàng (staff) không nên tự sửa.
     */
    public function updateStock(): void
    {
        $this->requirePost('/quan-tri/ton-kho');
        $this->requireManager('/quan-tri/ton-kho');

        $id  = (string) ($_POST['id'] ?? '');
        $qty = max(0, (int) ($_POST['stock_quantity'] ?? 0));

        if (!ProductModel::exists(['id' => $id])) {
            flash('admin_error', 'Không tìm thấy sản phẩm.');
            redirect('/quan-tri/ton-kho');
        }

        // Đồng bộ luôn cột status: để tồn 0 mà status vẫn 'in_stock' thì
        // trang bán hàng vẫn cho thêm vào giỏ rồi mới báo lỗi lúc đặt.
        ProductModel::update($id, [
            'stock_quantity' => $qty,
            'status'         => $qty > 0 ? 'in_stock' : 'out_of_stock',
        ]);

        flash('admin_success', 'Đã cập nhật tồn kho.');

        // Giữ nguyên bộ lọc đang xem để người nhập hàng không bị đá về đầu
        // danh sách sau mỗi lần sửa một dòng.
        $loc = (string) ($_POST['loc'] ?? '');
        redirect('/quan-tri/ton-kho' . ($loc !== '' ? '?loc=' . rawurlencode($loc) : ''));
    }
}
