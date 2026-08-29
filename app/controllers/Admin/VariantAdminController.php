<?php

/**
 * VariantAdminController — biến thể sản phẩm (/quan-tri/bien-the).
 *
 * Không có màn hình này thì biến thể chỉ tạo được bằng SQL tay, và khối "Chiết
 * suất — chọn theo độ cận" ở trang sản phẩm sẽ không bao giờ hiện với mặt hàng
 * nào ngoài dữ liệu mẫu.
 *
 * Là trang RIÊNG chứ không nhét vào form sửa sản phẩm: một mặt hàng có nhiều
 * biến thể, mà form sản phẩm đã dài sẵn. Chọn mặt hàng bằng ?sp=<id>.
 *
 * Giới hạn ở admin/manager giống các màn hình catalog khác — biến thể mang giá
 * và tồn kho, không phải nội dung.
 */

class VariantAdminController extends AdminController
{
    private const BASE = '/quan-tri/bien-the';

    /**
     * Số mặt hàng nhiều nhất đổ vào ô chọn một lượt.
     *
     * Không phải phân trang — ô chọn không lật trang được. Đây là cái trần để
     * ô chọn không phình theo danh mục, còn đường đi tới mặt hàng thứ 51 là ô
     * tìm ngay bên cạnh.
     */
    private const TRAN_CHON = 50;

    public function index(): void
    {
        /*
         * ─────────────────────────────────────────────────────────────────────
         * Ô CHỌN MẶT HÀNG: TÌM RỒI CHỌN, KHÔNG ĐỔ CẢ DANH MỤC
         *
         * Bản cũ gọi ProductModel::all('name ASC') — tức là SELECT * trên bảng
         * `products`, 72 CỘT cho MỌI mặt hàng, mỗi lần mở trang. Trong khi ô
         * chọn chỉ hiển thị hai thứ: tên và mã. Cửa hàng vài trăm mặt hàng thì
         * đó là vài trăm dòng đầy đủ (kể cả mô tả dài) đi qua CSDL, qua PHP,
         * rồi thành vài trăm thẻ <option> trong HTML — để người dùng cuộn tìm
         * bằng mắt.
         *
         * Nay: chỉ ba cột, có ô tìm, và tối đa TRAN_CHON dòng.
         *
         * Ô tìm là một ô GET trong chính form đổi mặt hàng, nên không có JS
         * vẫn chạy: gõ rồi Enter là trang tải lại với danh sách đã lọc.
         * ─────────────────────────────────────────────────────────────────────
         */
        $tim = trim((string) ($_GET['tim'] ?? ''));

        $where  = '';
        $thamSo = [];

        if ($tim !== '') {
            // addcslashes để '%' và '_' người dùng gõ vào được hiểu là ký tự
            // thật, không phải ký tự đại diện của LIKE — cùng cách làm ở
            // InventoryAdminController::index().
            $where  = 'WHERE (name LIKE :t1 OR sku LIKE :t2 OR brand LIKE :t3)';
            $needle = '%' . addcslashes($tim, '%_\\') . '%';
            $thamSo = ['t1' => $needle, 't2' => $needle, 't3' => $needle];
        }

        $products = Database::fetchAll(
            "SELECT id, name, sku FROM products {$where}
              ORDER BY name ASC LIMIT " . self::TRAN_CHON,
            $thamSo
        );

        // Tổng số mặt hàng KHỚP ô tìm, để nói thẳng khi danh sách bị cắt bớt.
        $tongSp = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM products {$where}",
            $thamSo
        );

        $productId = (string) ($_GET['sp'] ?? '');

        // Chưa chọn mặt hàng nào thì lấy cái đầu — trang trống với một ô chọn
        // duy nhất khiến người dùng tưởng chưa có dữ liệu.
        if ($productId === '' && $products !== []) {
            $productId = $products[0]['id'];
        }

        $product = $productId !== '' ? ProductModel::find($productId) : null;

        /*
         * MẶT HÀNG ĐANG XEM LUÔN CÓ TRONG Ô CHỌN, kể cả khi nó rơi ngoài trần
         * hoặc không khớp ô tìm.
         *
         * Thiếu bước này thì gõ tìm một chữ khác là ô chọn nhảy sang mặt hàng
         * đầu danh sách trong khi bảng bên dưới vẫn là biến thể của mặt hàng
         * cũ — hai thứ nói hai chuyện, và cú bấm "Xem" kế tiếp sẽ đổi mặt hàng
         * ngoài ý muốn.
         */
        $daGhim = false;

        if ($product !== null && !in_array($product['id'], array_column($products, 'id'), true)) {
            array_unshift($products, [
                'id'   => $product['id'],
                'name' => $product['name'],
                'sku'  => $product['sku'],
            ]);

            $daGhim = true;
        }

        $this->renderAdmin('admin/variants/index', [
            'pageTitle' => 'Biến thể — Quản trị',
            'products'  => $products,
            'tim'       => $tim,
            'tongSp'    => $tongSp,
            /* Ô chọn có nhiều hơn số khớp đúng một dòng: mặt hàng đang xem.
               View phải nói ra, không thì "15 mặt hàng khớp" mà đếm được 16
               dòng là một con số sai trước mắt người dùng. */
            'daGhim'    => $daGhim,
            'tranChon'  => self::TRAN_CHON,
            'product'   => $product,
            'variants'  => $product !== null ? VariantModel::allForProduct($product['id']) : [],
            'canEdit'   => UserModel::hasRole($this->userId, 'admin')
                        || UserModel::hasRole($this->userId, 'manager'),
            'editing'   => isset($_GET['sua']) ? VariantModel::find((string) $_GET['sua']) : null,
            // Hai cột phối màu thêm ngày 2026-08-27; chưa chạy nâng cấp thì
            // hai ô nhập tự ẩn — xem save().
            'hasSwatch' => Database::columnExists('product_variants', 'swatch_hex'),
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id        = (string) ($_POST['id'] ?? '');
        $productId = (string) ($_POST['product_id'] ?? '');
        $back      = self::BASE . '?sp=' . rawurlencode($productId);

        if (!ProductModel::exists(['id' => $productId])) {
            flash('admin_error', 'Không tìm thấy sản phẩm.');
            redirect(self::BASE);
        }

        $label = trim((string) ($_POST['label'] ?? ''));

        if (utf8Length($label) < 1 || utf8Length($label) > 60) {
            flash('admin_error', 'Nhãn phương án phải từ 1 đến 60 ký tự (vd: 1.61, Đen bóng).');
            redirect($back);
        }

        // Hai biến thể cùng nhãn trong một mặt hàng là lỗi nhập liệu. CSDL đã
        // có khoá UNIQUE chặn, nhưng bắt ở đây để báo lỗi đọc được thay vì để
        // lỗi 1062 thô lọt ra.
        $clash = Database::fetchOne(
            'SELECT id FROM product_variants WHERE product_id = :pid AND label = :label',
            ['pid' => $productId, 'label' => $label]
        );

        if ($clash !== null && $clash['id'] !== $id) {
            flash('admin_error', sprintf('Phương án "%s" đã có trong sản phẩm này.', $label));
            redirect($back);
        }

        $stock = max(0, (int) ($_POST['stock_quantity'] ?? 0));

        $data = [
            'product_id'     => $productId,
            'label'          => $label,
            'note'           => trim((string) ($_POST['note'] ?? '')) ?: null,
            // Cho phép ÂM: phương án rẻ hơn bản gốc. (int) tự lo dấu trừ.
            'price_delta'    => (int) ($_POST['price_delta'] ?? 0),
            'stock_quantity' => $stock,
            'position'       => max(0, (int) ($_POST['position'] ?? 0)),
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        ];

        /*
         * PHỐI MÀU — hai cột chỉ có nghĩa với phương án MÀU.
         *
         * Phương án chiết suất tròng hay cỡ thì để trống, và ngăn kéo thông số
         * trên trang bộ sưu tập chỉ vẽ ô màu cho biến thể nào CÓ mã màu (xem
         * collection/_drawer.php). Nên không có phép kiểm "phải điền" nào ở
         * đây — trống là một câu trả lời hợp lệ.
         *
         * Mã màu sai dạng thì về NULL chứ không lưu nguyên: giá trị này đi
         * thẳng vào thuộc tính style của thẻ in ra. View kiểm lại lần nữa,
         * nhưng chặn từ lúc ghi thì cột không bao giờ chứa thứ phải đi kiểm.
         */
        if (Database::columnExists('product_variants', 'swatch_hex')) {
            $ma = trim((string) ($_POST['swatch_hex'] ?? ''));

            $data['swatch_hex'] = preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $ma) ? $ma : null;
            $data['image']      = trim((string) ($_POST['image'] ?? '')) ?: null;
        }

        // Giá bán sau khi cộng chênh lệch không được âm — âm thì hoá đơn thành
        // khoản cửa hàng nợ khách.
        $product = ProductModel::find($productId);

        if ((int) $product['price'] + $data['price_delta'] < 0) {
            flash('admin_error', sprintf(
                'Chênh lệch quá lớn: giá bán sẽ âm. Giá gốc là %s.',
                money((int) $product['price'])
            ));
            redirect($back);
        }

        if ($id !== '' && VariantModel::exists(['id' => $id])) {
            VariantModel::update($id, $data);
            flash('admin_success', sprintf('Đã cập nhật phương án %s.', $label));
        } else {
            VariantModel::insert($data);
            flash('admin_success', sprintf('Đã thêm phương án %s.', $label));
        }

        redirect($back);
    }

    public function delete(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id      = (string) ($_POST['id'] ?? '');
        $variant = VariantModel::find($id);

        if ($variant === null) {
            flash('admin_error', 'Không tìm thấy phương án.');
            redirect(self::BASE);
        }

        $back = self::BASE . '?sp=' . rawurlencode($variant['product_id']);

        // order_items.variant_id là SET NULL nên xoá KHÔNG gây lỗi CSDL — nó
        // lặng lẽ cắt đứt liên kết giữa dòng hàng cũ và phương án đã bán.
        // variant_label trong order_items vẫn còn nên hoá đơn đọc được, nhưng
        // thống kê "phương án nào bán chạy" thì mất dấu.
        $used = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM order_items WHERE variant_id = :id',
            ['id' => $id]
        );

        if ($used > 0) {
            flash('admin_error', sprintf(
                'Không xoá được: đã có %d dòng hàng bán phương án này. Hãy bỏ tick "đang bán" thay vì xoá.',
                $used
            ));
            redirect($back);
        }

        VariantModel::delete($id);

        flash('admin_success', sprintf('Đã xoá phương án %s.', $variant['label']));
        redirect($back);
    }
}
