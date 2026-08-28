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

        /*
         * Ô TÌM — thêm theo bản thiết kế "Tồn kho.dc.html".
         *
         * Trang này bày TOÀN BỘ sản phẩm, sắp theo tồn thấp nhất trước, và
         * không phân trang. Với vài chục dòng thì cuộn là được; với vài trăm
         * thì việc "sửa tồn cho đúng cái SKU vừa nhập" thành ra dò mắt cả
         * trang. Ô tìm là đường tắt cho đúng thao tác ấy, nên nó tìm cả tên,
         * SKU lẫn thương hiệu — ba thứ mà người cầm thùng hàng có trong tay.
         *
         * Khác cách tìm của trang Sản phẩm (tách từ, khớp mọi từ): ở đây
         * người ta gõ gần như luôn là một mẩu mã SKU, nên một LIKE là đủ và
         * đỡ hẳn một vòng dựng câu lệnh.
         */
        $q = trim((string) ($_GET['q'] ?? ''));

        $dieuKien = match ($filter) {
            'low' => ['stock_quantity > 0 AND stock_quantity <= ' . self::LOW],
            'out' => ['stock_quantity <= 0'],
            default => [],
        };

        /* Điều kiện tìm dùng cho CẢ danh sách lẫn ba con số trên dải viên lọc:
           gõ "titan" mà viên "Sắp hết" vẫn đếm toàn kho thì con số ấy nói về
           một danh sách người dùng không nhìn thấy. */
        $locTim  = '';
        $thamSo  = [];

        if ($q !== '') {
            $locTim = '(name LIKE :tim_name OR sku LIKE :tim_sku OR brand LIKE :tim_brand)';
            $needle = '%' . addcslashes($q, '%_\\') . '%';
            $thamSo = ['tim_name' => $needle, 'tim_sku' => $needle, 'tim_brand' => $needle];
            $dieuKien[] = $locTim;
        }

        $where    = $dieuKien !== [] ? 'WHERE ' . implode(' AND ', $dieuKien) : '';
        $whereDem = $locTim !== '' ? 'WHERE ' . $locTim : '';

        $this->renderAdmin('admin/inventory/index', [
            'pageTitle' => 'Tồn kho — Quản trị',
            'products'  => Database::fetchAll(
                "SELECT id, slug, sku, name, brand, stock_quantity, status, price
                   FROM products
                   {$where}
                  ORDER BY stock_quantity ASC, name ASC",
                $thamSo
            ),
            'filter'    => $filter,
            'q'         => $q,
            'low'       => self::LOW,
            // Bí danh KHÔNG được đặt là `out` hay `low`: cả hai là từ khoá
            // dành riêng của MySQL (out dùng cho tham số stored procedure),
            // đặt vậy thì câu lệnh lỗi cú pháp 1064.
            'counts'    => Database::fetchOne(
                'SELECT
                    COUNT(*)                                                       AS total,
                    SUM(stock_quantity > 0 AND stock_quantity <= ' . self::LOW . ') AS low_stock,
                    SUM(stock_quantity <= 0)                                       AS out_stock
                   FROM products ' . $whereDem,
                $thamSo
            ),
            'adminScripts' => ['assets/js/admin-inventory.js'],
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

        /* Giữ nguyên bộ lọc VÀ từ khoá đang xem để người nhập hàng không bị đá
           về đầu danh sách sau mỗi lần sửa một dòng.

           Từ khoá cũng phải giữ, không chỉ bộ lọc: thao tác thật là gõ một mẩu
           SKU, sửa tồn cho hai ba dòng hiện ra, rồi gõ mẩu tiếp theo. Mất từ
           khoá sau dòng đầu tiên là phải gõ lại cho mỗi dòng. */
        $tham = array_filter([
            'loc' => (string) ($_POST['loc'] ?? ''),
            'q'   => trim((string) ($_POST['q'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        redirect('/quan-tri/ton-kho' . ($tham !== [] ? '?' . http_build_query($tham) : ''));
    }
}
