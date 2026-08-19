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
        ]);
    }

    public function save(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $ajax = $this->isJsonRequest();
        $id        = (string) ($_POST['id'] ?? '');
        $productId = (string) ($_POST['product_id'] ?? '');
        $back      = self::BASE . '?sp=' . rawurlencode($productId);

        if (!ProductModel::exists(['id' => $productId])) {
            $message = 'Không tìm thấy sản phẩm.';
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect(self::BASE);
        }

        $label = trim((string) ($_POST['label'] ?? ''));

        if (utf8Length($label) < 1 || utf8Length($label) > 60) {
            $message = 'Nhãn phương án phải từ 1 đến 60 ký tự (vd: 1.61, Đen bóng).';
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect($back);
        }

        $clash = Database::fetchOne(
            'SELECT id FROM product_variants WHERE product_id = :pid AND label = :label',
            ['pid' => $productId, 'label' => $label]
        );

        if ($clash !== null && $clash['id'] !== $id) {
            $message = sprintf('Phương án "%s" đã có trong sản phẩm này.', $label);
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect($back);
        }

        $stock = max(0, (int) ($_POST['stock_quantity'] ?? 0));

        $data = [
            'product_id'     => $productId,
            'label'          => $label,
            'note'           => trim((string) ($_POST['note'] ?? '')) ?: null,
            'price_delta'    => (int) ($_POST['price_delta'] ?? 0),
            'stock_quantity' => $stock,
            'position'       => max(0, (int) ($_POST['position'] ?? 0)),
            'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        ];

        $product = ProductModel::find($productId);

        if ((int) $product['price'] + $data['price_delta'] < 0) {
            $message = sprintf('Chênh lệch quá lớn: giá bán sẽ âm. Giá gốc là %s.', money((int) $product['price']));
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect($back);
        }

        try {
            if ($id !== '' && VariantModel::exists(['id' => $id])) {
                VariantModel::update($id, $data);
                $message = sprintf('Đã cập nhật phương án %s.', $label);
            } else {
                VariantModel::insert($data);
                $message = sprintf('Đã thêm phương án %s.', $label);
            }

            if ($ajax) {
                $this->jsonReply(true, $message, $back);
            }

            flash('admin_success', $message);
            redirect($back);
        } catch (Throwable $e) {
            $message = 'Lỗi khi lưu dữ liệu: ' . $e->getMessage();
            if ($ajax) {
                $this->jsonReply(false, $message);
            }
            flash('admin_error', $message);
            redirect($back);
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
