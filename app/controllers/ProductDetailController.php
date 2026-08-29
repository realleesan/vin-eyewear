<?php

/**
 * ProductDetailController — chi tiết sản phẩm (/san-pham/{slug}).
 *
 * Port từ src/routes/san-pham.$slug.tsx.
 */

class ProductDetailController extends BaseController
{
    /**
     * @param string $slug lấy từ route 'san-pham/{slug}' trong config/routes.php
     */
    public function show(string $slug = ''): void
    {
        $product = $slug === '' ? null : ProductModel::findVisibleBySlug($slug);

        // Slug không tồn tại (hoặc sản phẩm đã ẩn) -> 404 thật, không phải
        // trang trống. Trả 200 cho nội dung không tồn tại sẽ khiến công cụ
        // tìm kiếm lập chỉ mục một trang rỗng.
        if ($product === null) {
            $this->notFound();
            return;
        }

        // ?danh-gia=tat-ca mở toàn bộ đánh giá ngay trên trang này thay vì một
        // trang riêng: khối đánh giá đã nằm sẵn ở đây, tách trang chỉ để hiện
        // thêm vài dòng là bắt khách tải lại cả trang.
        $showAll = ($_GET['danh-gia'] ?? '') === 'tat-ca';

        $category = $product['category_id'] !== null
            ? CategoryModel::find($product['category_id'])
            : null;

        $userId = AuthMiddleware::customerId();

        $this->renderView('product/detail', [
            'pageTitle' => $product['name'] . ' — Vin Eyewear',
            'metaDesc'  => excerpt($product['description'] ?? '', 155),
            'product'   => $product,
            'category'  => $category,
            'variants'  => VariantModel::forProduct($product['id']),
            // Biến thể khách vừa chọn hỏng (thiếu, sai) — nhớ lại để chọn sẵn
            'pickedVariant' => (string) ($_GET['pa'] ?? ''),
            'related'   => ProductModel::related($product['category_id'], $product['id'], 4),
            // Chỉ lấy vài đánh giá đầu; "Xem tất cả" mở trang riêng.
            'reviews'   => ReviewModel::published($product['id'], $showAll ? null : ReviewModel::PREVIEW),
            'showAll'   => $showAll,
            // canReview quyết định hiện form viết đánh giá hay một dòng giải
            // thích vì sao chưa viết được.
            'canReview'  => ReviewModel::canReview($userId, $product['id']),
            'reviewMsg'  => flash('review_msg'),
            'reviewOk'   => flash('review_ok') !== null,
        ]);
    }

    /**
     * Khách gửi đánh giá (POST /san-pham/danh-gia).
     *
     * Mọi phép kiểm nằm trong ReviewModel::submit — kể cả "có được đánh giá
     * không". Controller chỉ lo CSRF và đường quay về.
     */
    public function review(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('/san-pham');
        }

        $slug = (string) ($_POST['slug'] ?? '');
        $back = '/san-pham/' . rawurlencode($slug) . '#danh-gia';

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('review_msg', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            redirect($back);
        }

        $product = $slug === '' ? null : ProductModel::findVisibleBySlug($slug);

        if ($product === null) {
            redirect('/san-pham');
        }

        $userId = AuthMiddleware::customerId();

        if ($userId === null) {
            flash('review_msg', 'Đăng nhập để viết đánh giá.');
            redirect($back);
        }

        $result = ReviewModel::submit(
            $userId,
            $product['id'],
            (int) ($_POST['rating'] ?? 0),
            (string) ($_POST['body'] ?? '')
        );

        if (!$result['ok']) {
            flash('review_msg', $result['error']);
            redirect($back);
        }

        // Nói rõ là chưa hiện ngay. Khách gửi xong không thấy đánh giá của
        // mình đâu sẽ tưởng bị mất, rồi gửi lại lần nữa.
        flash('review_msg', 'Cảm ơn bạn! Đánh giá sẽ hiển thị sau khi được duyệt.');
        flash('review_ok', '1');
        redirect($back);
    }

    /**
     * Ghi tên khách vào danh sách chờ hàng.
     *
     * ─────────────────────────────────────────────────────────────────────
     * NÓI ĐÚNG THỨ HỆ THỐNG LÀM ĐƯỢC
     *
     * Nút trên trang ghi "Thông báo khi có hàng", nhưng hosting hiện tại KHÔNG
     * gửi được email (xem chú thích đầu WaitlistModel). Nên câu xác nhận trả
     * về không hứa "chúng tôi sẽ gửi email cho bạn" — nó nói cửa hàng sẽ LIÊN
     * HỆ. Người báo tin là nhân viên, qua điện thoại hoặc Zalo.
     * ─────────────────────────────────────────────────────────────────────
     */
    public function waitlist(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('/san-pham');
        }

        $slug = (string) ($_POST['slug'] ?? '');
        $back = '/san-pham/' . rawurlencode($slug) . '#cho-hang';

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('waitlist_msg', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            redirect($back);
        }

        $product = $slug === '' ? null : ProductModel::findVisibleBySlug($slug);

        if ($product === null) {
            redirect('/san-pham');
        }

        /* Biến thể: chỉ nhận id THUỘC đúng mặt hàng này. Không kiểm thì gửi id
           của món khác lên là ghi một lượt chờ vào nhầm chỗ, và người ta được
           gọi vì một cái gọng họ chưa từng xem. */
        $variantId = trim((string) ($_POST['variant_id'] ?? '')) ?: null;

        if ($variantId !== null && VariantModel::findForProduct($variantId, $product['id']) === null) {
            $variantId = null;
        }

        /* Mặt hàng CÓ biến thể thì bắt buộc chọn một cái: danh sách chờ gắn
           theo biến thể, ghi NULL ở đây nghĩa là không biết khách chờ màu nào
           và tới lúc hàng về không ai gọi được cho đúng người. */
        if ($variantId === null && VariantModel::hasVariants($product['id'])) {
            flash('waitlist_msg', 'Vui lòng chọn một phương án bạn đang chờ.');
            redirect($back);
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('waitlist_msg', 'Địa chỉ email không hợp lệ.');
            redirect($back);
        }

        /* Số điện thoại: giữ đúng thứ khách gõ, chỉ bỏ khoảng trắng và dấu nối
           để đối chiếu trùng lặp cho khớp. Không ép về một định dạng chuẩn —
           nhân viên gọi bằng mắt, không phải bằng máy. */
        if ($phone !== '') {
            $phone = preg_replace('/[\s.\-()]/', '', $phone) ?? $phone;

            if (!preg_match('/^\+?\d{9,15}$/', $phone)) {
                flash('waitlist_msg', 'Số điện thoại không hợp lệ.');
                redirect($back);
            }
        }

        if ($email === '' && $phone === '') {
            flash('waitlist_msg', 'Vui lòng để lại email hoặc số điện thoại.');
            redirect($back);
        }

        $moi = WaitlistModel::dangKy(
            $product['id'],
            $variantId,
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null
        );

        /* Đăng ký lại lần hai KHÔNG phải lỗi — người ta chỉ không nhớ mình đã
           đăng ký. Nói cho họ yên tâm thay vì báo một cái lỗi họ không gây ra. */
        flash('waitlist_msg', $moi
            ? 'Đã ghi nhận. Cửa hàng sẽ liên hệ với bạn ngay khi hàng về.'
            : 'Bạn đã trong danh sách chờ của sản phẩm này rồi.');
        flash('waitlist_ok', '1');

        redirect($back);
    }

    /**
     * Trả 404 qua ErrorController để trang lỗi đồng nhất với phần còn lại.
     */
    private function notFound(): void
    {
        http_response_code(404);

        $controller = new ErrorController();
        $controller->notFound();
    }
}
