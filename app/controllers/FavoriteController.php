<?php

/**
 * FavoriteController — bật/tắt dấu trang trên một mặt hàng.
 *
 * MỘT HÀNH ĐỘNG, MỘT ĐƯỜNG: POST /yeu-thich. Nút trên trang là một cái công
 * tắc nên máy chủ cũng là một cái công tắc — lý do đầy đủ ở khối chú thích của
 * FavoriteModel::batTat().
 *
 * Danh sách đã lưu KHÔNG có controller riêng: nó là một mục của trang tài
 * khoản (/tai-khoan?muc=da-luu), do AuthController::profile() dựng cùng bốn
 * mục kia.
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

        /* PHẢI ĐĂNG NHẬP, và chốt Ở ĐÂY chứ không chỉ ở view.

           View đã thay nút bằng lời mời đăng nhập, nhưng đó là chuyện của
           trình duyệt: một POST dựng tay không đi qua view nào cả, mà cột
           user_id thì NOT NULL — không chặn ở đây là nhận một lỗi CSDL thay vì
           một câu tiếng Việt.

           requireLogin() tự chuyển hướng sang /auth kèm đường quay lại, nên
           đăng nhập xong khách về đúng trang sản phẩm họ đang xem. */
        $userId = AuthMiddleware::requireLogin($back);

        $product = $slug === '' ? null : ProductModel::findVisibleBySlug($slug);

        if ($product === null) {
            flash('site_error', 'Không tìm thấy sản phẩm này.');
            redirect($back);
        }

        /* BẢNG CHƯA CÓ THÌ NÓI THẲNG, ĐỪNG ĐỔ 500 VÀO MẶT KHÁCH — cùng khuôn
           với nút "Thông báo khi có hàng", xem ProductDetailController::waitlist.
           Mã lên hosting tự động còn migration thì bấm tay, nên quãng lệch giữa
           hai thứ là chuyện bình thường. */
        if (!FavoriteModel::available()) {
            flash('site_error', 'Tính năng lưu sản phẩm đang tạm ngưng. Vui lòng thử lại sau.');
            redirect($back);
        }

        $dangLuu = FavoriteModel::batTat($product['id'], $userId);

        flash('site_success', $dangLuu
            ? 'Đã lưu “' . $product['name'] . '” vào danh sách của bạn.'
            : 'Đã bỏ “' . $product['name'] . '” khỏi danh sách đã lưu.');

        redirect($back);
    }
}
