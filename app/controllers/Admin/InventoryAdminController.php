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
    /**
     * Ngưỡng "sắp hết" MẶC ĐỊNH, dùng khi mặt hàng không đặt riêng.
     *
     * Từ 2026-08-29 mỗi mặt hàng đặt được ngưỡng của mình ở ô "Ngưỡng cảnh báo
     * hết hàng" trong form sản phẩm (cột `low_stock_at`). Ba cái gọng bán một
     * tháng vài chiếc và cái kính râm bán chạy nhất mùa hè không thể cùng một
     * mốc: đặt 5 cho cả hai thì hoặc là báo động giả suốt, hoặc là biết tin
     * khi đã hết hàng thật.
     *
     * Cột ấy đã có ô nhập từ lâu nhưng KHÔNG AI ĐỌC — màn này vẫn so với hằng
     * số. Nay COALESCE(low_stock_at, LOW): mặt hàng nào để trống thì rơi về
     * con số chung, nên không phải đi điền lại cho cả kho.
     */
    private const LOW = 5;

    /* Biểu thức ngưỡng chuyển sang ProductModel::nguongSapHetSql() — nó phải
       HỎI cột `low_stock_at` có tồn tại không, mà hằng số thì tính lúc biên
       dịch nên không hỏi được. Máy chưa chạy migration là không có cột, và ba
       câu lệnh dưới đây đổ lỗi 1054. Xem khối chú thích ở hàm ấy. */

    /**
     * Số dòng mỗi trang.
     *
     * 20 như trang Đơn hàng và trang Sản phẩm, không phải 10 như bản vẽ đề
     * nghị: ba bảng này người ta lướt bằng cùng một thói quen, và một bảng
     * nhảy trang sớm hơn hai bảng kia chỉ làm người dùng đếm nhầm mình đang
     * ở đâu. Bản vẽ để con số này thành tham số chỉnh được (soDongMoiTrang,
     * 5..30) — tức là chính nó cũng coi đây là chuyện chọn, không phải quy
     * tắc.
     */
    private const PER_PAGE = 20;

    public function index(): void
    {
        $filter = (string) ($_GET['loc'] ?? '');

        /*
         * Ô TÌM — thêm theo bản thiết kế "Tồn kho.dc.html".
         *
         * Trang này sắp theo tồn thấp nhất trước và chia 20 dòng một trang.
         * Nhưng "sửa tồn cho đúng cái SKU vừa nhập" thì lật trang không giải
         * quyết được — người cầm thùng hàng biết mã, không biết nó nằm trang
         * mấy. Ô tìm là đường tắt cho đúng thao tác ấy, nên nó tìm cả tên,
         * SKU lẫn thương hiệu — ba thứ có sẵn trên thùng.
         *
         * Khác cách tìm của trang Sản phẩm (tách từ, khớp mọi từ): ở đây
         * người ta gõ gần như luôn là một mẩu mã SKU, nên một LIKE là đủ và
         * đỡ hẳn một vòng dựng câu lệnh.
         */
        $q = trim((string) ($_GET['q'] ?? ''));

        $dieuKien = match ($filter) {
            'low' => ['stock_quantity > 0 AND stock_quantity <= ' . ProductModel::nguongSapHetSql()],
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

        // Bí danh KHÔNG được đặt là `out` hay `low`: cả hai là từ khoá dành
        // riêng của MySQL (out dùng cho tham số stored procedure), đặt vậy thì
        // câu lệnh lỗi cú pháp 1064.
        $counts = Database::fetchOne(
            'SELECT
                COUNT(*)                                                       AS total,
                SUM(stock_quantity > 0 AND stock_quantity <= ' . ProductModel::nguongSapHetSql() . ') AS low_stock,
                SUM(stock_quantity <= 0)                                       AS out_stock
               FROM products ' . $whereDem,
            $thamSo
        );

        /*
         * TỔNG SỐ DÒNG CỦA BỘ LỌC ĐANG XEM — lấy lại từ chính ba con số trên
         * dải viên, không hỏi thêm một câu COUNT nữa.
         *
         * Ba con số ấy đã được đếm với đúng điều kiện tìm ($whereDem) và đúng
         * ba nhóm tồn kho, tức là chúng CHÍNH LÀ tổng số dòng của từng viên.
         * Hỏi lại bằng một câu COUNT riêng vừa thừa một lượt đi CSDL, vừa mở
         * đường cho hai con số lệch nhau — viên lọc nói "12" mà chân bảng nói
         * "trang 1/2 · 15 sản phẩm" thì không biết tin cái nào.
         */
        $tong = (int) match ($filter) {
            'low' => $counts['low_stock'] ?? 0,
            'out' => $counts['out_stock'] ?? 0,
            default => $counts['total'] ?? 0,
        };

        $soTrang = max(1, (int) ceil($tong / self::PER_PAGE));

        /* Kẹp vào dải hợp lệ thay vì trả trang rỗng: ?page=99 hay ?page=abc
           đều là địa chỉ sửa tay hoặc một liên kết cũ sau khi kho vơi đi, và
           một bảng trống không nói được điều đó. */
        $trang  = min(max(1, (int) ($_GET['page'] ?? 1)), $soTrang);
        $offset = ($trang - 1) * self::PER_PAGE;

        $this->renderAdmin('admin/inventory/index', [
            'pageTitle' => 'Tồn kho — Quản trị',
            /* LIMIT/OFFSET ghép thẳng, KHÔNG qua tham số ràng buộc: dự án tắt
               EMULATE_PREPARES, và MySQL không nhận tham số ở vị trí LIMIT khi
               dùng prepared statement thật. An toàn vì cả hai đều là số nguyên
               do chính hàm này tính ra, không phải chuỗi từ người dùng — cùng
               cách làm với ProductAdminController::index. */
            'products'  => Database::fetchAll(
                "SELECT id, slug, sku, name, brand, stock_quantity, status, price
                   FROM products
                   {$where}
                  ORDER BY stock_quantity ASC, name ASC
                  LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
                $thamSo
            ),
            'filter'    => $filter,
            'q'         => $q,
            'low'       => self::LOW,
            'counts'    => $counts,
            'total'      => $tong,
            'page'       => $trang,
            'totalPages' => $soTrang,
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

        /* Giữ nguyên bộ lọc, TỪ KHOÁ và SỐ TRANG đang xem để người nhập hàng
           không bị đá về đầu danh sách sau mỗi lần sửa một dòng.

           Từ khoá cũng phải giữ, không chỉ bộ lọc: thao tác thật là gõ một mẩu
           SKU, sửa tồn cho hai ba dòng hiện ra, rồi gõ mẩu tiếp theo. Mất từ
           khoá sau dòng đầu tiên là phải gõ lại cho mỗi dòng.

           Số trang thì thêm từ 2026-08-29, cùng lúc với phân trang. Lưu ý là
           DÒNG VỪA SỬA CÓ THỂ BIẾN MẤT khỏi trang: bảng sắp theo tồn thấp nhất
           trước, nên nâng tồn từ 2 lên 40 là đẩy nó về tận trang cuối. Đó là
           hệ quả của cách sắp xếp chứ không phải lỗi, và quay về trang cũ vẫn
           đúng hơn là quay về trang 1 — người ta đang làm dở những dòng khác
           ở đây. */
        $tham = array_filter([
            'loc'  => (string) ($_POST['loc'] ?? ''),
            'q'    => trim((string) ($_POST['q'] ?? '')),
            // Trang 1 không cần nằm trên địa chỉ: ?page=1 và không có ?page là
            // cùng một chỗ, mà địa chỉ ngắn thì dễ đọc và dễ gửi cho nhau hơn.
            'page' => ($tr = max(1, (int) ($_POST['page'] ?? 1))) > 1 ? (string) $tr : '',
        ], static fn (string $v): bool => $v !== '');

        redirect('/quan-tri/ton-kho' . ($tham !== [] ? '?' . http_build_query($tham) : ''));
    }
}
