<?php

/**
 * FavoriteController — bật/tắt dấu trang trên một mặt hàng.
 *
 * MỘT HÀNH ĐỘNG, MỘT ĐƯỜNG: POST /yeu-thich. Nút trên trang là một cái công
 * tắc nên máy chủ cũng là một cái công tắc — lý do đầy đủ ở khối chú thích của
 * FavoriteModel::batTat().
 *
 * Danh sách đã lưu có HAI cửa, cùng một nội dung:
 *   /yeu-thich              WishlistController@index — mở được khi CHƯA đăng nhập
 *   /tai-khoan?muc=da-luu   một mục của trang tài khoản, AuthController::profile()
 * Cả hai đọc qua Wishlist nên không có bản chép thứ hai của luật nào cả.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO POST CHỨ KHÔNG PHẢI MỘT LIÊN KẾT
 *
 * Nó ĐỔI DỮ LIỆU. Một <a href="/yeu-thich?id=..."> thì mọi trình duyệt, trình
 * duyệt đọc trước (prefetch) và con bọ tìm kiếm đều có quyền bấm hộ khách —
 * nghĩa là dấu trang tự bật tự tắt mà không ai chạm vào. Cùng lý do với nút
 * đăng xuất, xem chú thích ở auth/profile.php.
 */

class FavoriteController extends BaseController
{
    /**
     * Bật/tắt dấu trang rồi quay về đúng chỗ khách vừa bấm.
     */
    public function toggle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            redirect('/san-pham');
        }

        $slug = trim((string) ($_POST['slug'] ?? ''));

        /* Nơi quay về đến từ ô ẩn `back`, tức do người dùng kiểm soát —
           safeRedirectPath() chặn đường dẫn ra ngoài. Mặc định là trang chi
           tiết của chính mặt hàng vừa bấm; nút ở mục "Đã lưu" gửi kèm
           '/tai-khoan?muc=da-luu' để khách ở lại danh sách. */
        $back = safeRedirectPath(
            $_POST['back'] ?? null,
            $slug === '' ? '/san-pham' : '/san-pham/' . rawurlencode($slug)
        );

        if (!csrfCheck($_POST['_token'] ?? null)) {
            http_response_code(419);
            flash('site_error', 'Phiên làm việc đã hết hạn — vui lòng tải lại trang rồi bấm lại.');
            redirect($back);
        }

        /* ┌─ KHÔNG CÒN BẮT ĐĂNG NHẬP — 13/09/2026 ────────────────────────────
           │ Theo yêu cầu chủ dự án: "Wishlist (yêu thích) không cần đăng nhập
           │ vẫn thêm được, nhưng khi thêm sản phẩm (vào giỏ) cần phải đăng
           │ nhập." Hai việc đổi ngược chiều nhau trong cùng một lượt sửa: chốt
           │ đăng nhập rời KHỎI đây và mọc ra ở CartController::add().
           │
           │ Bản cũ gọi AuthMiddleware::requireLogin($back) ngay chỗ này, và lý
           │ do khi ấy đúng: cột `favorites.user_id` là NOT NULL nên một POST
           │ dựng tay sẽ đổ lỗi CSDL. Lý do ấy KHÔNG mất đi — nó chuyển chỗ:
           │ Wishlist::batTat() chỉ chạm tới bảng khi có người đăng nhập, còn
           │ khách vãng lai thì ghi vào $_SESSION. Không đường nào còn INSERT
           │ một user_id rỗng nữa.
           │
           │ ⚠ ĐỪNG thêm lại requireLogin() ở đây "cho chắc": làm thế là khách
           │   vãng lai bấm trái tim liền bị ném sang trang đăng nhập — đúng
           │   cái trải nghiệm mà lượt sửa này bỏ đi.
           └──────────────────────────────────────────────────────────────────── */

        $product = $slug === '' ? null : ProductModel::findVisibleBySlug($slug);

        if ($product === null) {
            flash('site_error', 'Không tìm thấy sản phẩm này.');
            redirect($back);
        }

        /* BẢNG CHƯA CÓ THÌ NÓI THẲNG, ĐỪNG ĐỔ 500 VÀO MẶT KHÁCH — cùng khuôn
           với nút "Thông báo khi có hàng", xem ProductDetailController::waitlist.
           Mã lên hosting tự động còn migration thì bấm tay, nên quãng lệch giữa
           hai thứ là chuyện bình thường. */
        if (!Wishlist::available()) {
            flash('site_error', 'Tính năng lưu sản phẩm đang tạm ngưng. Vui lòng thử lại sau.');
            redirect($back);
        }

        $dangLuu = Wishlist::batTat($product['id']);

        flash('site_success', $dangLuu
            ? 'Đã lưu “' . $product['name'] . '” vào danh sách của bạn.'
            : 'Đã bỏ “' . $product['name'] . '” khỏi danh sách đã lưu.');

        redirect($back);
    }
}
