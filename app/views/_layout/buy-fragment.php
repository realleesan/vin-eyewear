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

/* Ba mảnh đều có chữ, mà đường thứ 2 ở trên (BaseController::buyFragment())
   KHÔNG đi qua _layout/master.php — nơi lớp ngôn ngữ vẫn được nạp. Thiếu dòng
   này thì mọi câu trong hộp thoại mua hàng đổ lỗi "gọi hàm t() chưa định
   nghĩa", và nó chỉ đổ ở đúng nhánh POST. */
require_once CORE_PATH . '/i18n.php';
?>
<?php
/* ┌─ HỘP THOẠI MUA HÀNG — ĐÃ TẮT 13/09/2026 (chỗ 5/5) ────────────────────
   │ Cùng lý do với master.php: mảnh này là câu trả lời cho cú POST của
   │ buy-flow.js, và buy-flow.js lấy .bmodal ra khỏi đó để mở/đổi bước hộp
   │ thoại. Không in .bmodal nữa thì buy-flow.js không tìm thấy gì, đúng như
   │ nó vẫn xử khi một cú thêm giỏ kết thúc mà không có hộp thoại nào — tức
   │ là chỉ thay dải báo và huy hiệu giỏ, y như "Thêm vào giỏ" xưa nay.
   │
   │ Hai mảnh còn lại (.toast và cụm giỏ) VẪN PHẢI IN: đó là toàn bộ phản
   │ hồi mà khách nhận được khi bấm "Thêm vào giỏ" mà không tải lại trang.
   └──────────────────────────────────────────────────────────────────────── */
?>
<?php // if (!empty($buyModal)): ?>
    <?php // partial('_layout/buy-modal', ['buyModal' => $buyModal]); ?>
<?php // endif; ?>

<?php
/* POPUP "CHỈ DÀNH CHO THÀNH VIÊN" — mảnh thứ tư, thêm 13/09/2026.

   buy-flow.js bắt cú bấm "Thêm vào giỏ"/"Mua ngay" và gửi bằng fetch, nên câu
   trả lời của máy chủ về đây chứ không về master.php. Thiếu khối này thì với
   người BẬT JavaScript — tức gần như tất cả — cú bấm lặng thinh: không popup,
   không dải báo, không gì cả, đọc ra đúng như nút hỏng.

   Cùng một partial với master.php, không phải bản chép: hai đường trả lời cho
   cùng một cú bấm thì phải ra cùng một popup. */
$congDangNhap = flash('cong_dang_nhap');

if ($congDangNhap !== null) {
    partial('_layout/login-gate', ['veLai' => $congDangNhap]);
}
?>

<?php if (!empty($toast)): ?>
    <?php partial('_layout/toast', ['toast' => $toast, 'toastTone' => $toastTone ?? 'ok']); ?>
<?php endif; ?>

<?php
/* Luôn in, kể cả khi giỏ không đổi: buy-flow.js chép ruột cụm này sang trang
   đang mở, nên thiếu nó thì huy hiệu đứng im ở con số cũ. */
partial('_layout/header-cart');
