<?php

/**
 * _layout/toast.php — dải báo "Đã thêm … vào giỏ hàng" (và lời báo lỗi).
 *
 * Nhận qua partial(): $toast (chuỗi) và $toastTone ('ok' | 'err').
 * BaseController::renderView đọc chúng ra từ flash.
 *
 * Tách riêng vì master.php in nó ở HAI đường: trang đầy đủ, và chế độ mảnh
 * trả lời buy-flow.js — xem khối chú thích đầu master.php.
 *
 * role="status" để trình đọc màn hình đọc lên mà không cắt ngang việc đang
 * làm (aria-live "polite", đúng với một lời xác nhận); lời báo LỖI thì
 * role="alert" vì nó nói rằng việc khách vừa làm đã không xảy ra.
 *
 * Tự mờ đi sau vài giây bằng CSS animation, không phải setTimeout — xem
 * .toast trong components/ui.css.
 */

$tone = $toastTone ?? 'ok';
?>
<p class="toast toast--<?= e($tone) ?>"
   role="<?= $tone === 'err' ? 'alert' : 'status' ?>"><?= e($toast) ?></p>
