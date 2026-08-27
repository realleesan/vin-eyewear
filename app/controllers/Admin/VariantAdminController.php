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

    public function index(): void
    {
        $products  = ProductModel::all('name ASC');
        $productId = (string) ($_GET['sp'] ?? '');

        // Chưa chọn mặt hàng nào thì lấy cái đầu — trang trống với một ô chọn
        // duy nhất khiến người dùng tưởng chưa có dữ liệu.
        if ($productId === '' && $products !== []) {
            $productId = $products[0]['id'];
        }

        $product = $productId !== '' ? ProductModel::find($productId) : null;

        $this->renderAdmin('admin/variants/index', [
            'pageTitle' => 'Biến thể — Quản trị',
            'products'  => $products,
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
