<?php
/**
 * _layout/pusher.php
 * Component Pusher (Scroll to Top) dùng chung TOÀN SITE — nhúng 1 lần từ master.php.
 *
 * Biến nhận (mặc định an toàn):
 *   $show_pusher — bool, bật/tắt component (mặc định true)
 *
 * Hoạt động: nút ẩn khi ở đầu trang, tự hiện khi cuộn quá ngưỡng,
 * bấm thì cuộn mượt về đầu trang. Logic ở assets/js/pusher.js,
 * trạng thái hiện/ẩn bằng class .is-visible (assets/css/pusher.css).
 */

$show_pusher = $show_pusher ?? true;

if (!$show_pusher) {
    return;
}
?>
<link rel="stylesheet" href="/assets/css/pusher.css">
<script src="/assets/js/pusher.js" defer></script>

<button type="button" class="pusher" id="pusherBtn" aria-label="Cuộn lên đầu trang" title="Lên đầu trang">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>
