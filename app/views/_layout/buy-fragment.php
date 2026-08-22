<?php

/**
 * _layout/buy-fragment.php — BA MẢNH mà assets/js/buy-flow.js lấy về.
 *
 *   .bmodal          hộp thoại mua hàng ở bước hiện tại
 *   .toast           dải báo "Đã thêm … vào giỏ"
 *   [data-cart]      cụm giỏ hàng trên thanh nav (huy hiệu + bảng xổ)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO TÁCH RA MỘT FILE RIÊNG
 *
 * Có HAI nơi in ba mảnh này, và chúng phải in giống hệt nhau:
 *
 *   1. _layout/master.php khi thấy header X-Buy-Flow — đường cũ, dùng cho
 *      những lượt GET vào một trang đã có sẵn ?mua= trên URL (bấm Lùi, F5).
 *   2. BaseController::buyFragment() — đường mới, trả lời THẲNG cú POST mà
 *      không qua bước chuyển hướng. Xem chú thích ở đó.
 *
 * Để hai nơi tự in lấy thì thêm một mảnh thứ tư sau này là sửa hai chỗ, và
 * quên một chỗ nghĩa là nửa số lượt bấm thiếu mảnh đó — một lỗi chỉ hiện ra ở
 * đúng vài đường đi, rất khó lần.
 * ─────────────────────────────────────────────────────────────────────────────
 */
?>
<?php if (!empty($buyModal)): ?>
    <?php partial('_layout/buy-modal', ['buyModal' => $buyModal]); ?>
<?php endif; ?>

<?php if (!empty($toast)): ?>
    <?php partial('_layout/toast', ['toast' => $toast, 'toastTone' => $toastTone ?? 'ok']); ?>
<?php endif; ?>

<?php
/* Luôn in, kể cả khi giỏ không đổi: buy-flow.js chép ruột cụm này sang trang
   đang mở, nên thiếu nó thì huy hiệu đứng im ở con số cũ. */
partial('_layout/header-cart');
