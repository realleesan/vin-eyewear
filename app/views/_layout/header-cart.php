<?php

/**
 * _layout/header-cart.php — cụm GIỎ HÀNG trên thanh nav (huy hiệu + bảng xổ).
 *
 * Tách khỏi _layout/header.php vì có HAI nơi cần in nó:
 *
 *   1. header.php — như mọi khi, một phần của thanh nav;
 *   2. master.php ở CHẾ ĐỘ MẢNH — trả lời cú bấm "Mua ngay"/"Thêm vào giỏ"
 *      của assets/js/buy-flow.js. Xem khối chú thích đầu master.php.
 *
 * Không nhận tham số: số lượng đọc thẳng từ session, nên hai nơi gọi không
 * thể lệch nhau. Giỏ hàng nằm ở $_SESSION nên đây chưa phải một câu truy vấn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * data-cart LÀ MỐC CHO buy-flow.js
 *
 * Thêm hàng xong nó cập nhật huy hiệu và ruột bảng xổ mà không tải lại trang.
 * Nó chỉ thay RUỘT của hai chỗ đó, KHÔNG thay thẻ [data-hpop-trigger] —
 * header.js gắn sự kiện thẳng lên thẻ ấy, thay cả thẻ bọc [data-hpop] là mất
 * luôn cái bảng xổ: header.js đọc danh sách [data-hpop] MỘT LẦN lúc tải trang
 * và giữ luôn tham chiếu đó. (Riêng thẻ mở của giỏ hàng là <a> nên header.js
 * bỏ qua — nó chỉ gắn sự kiện cho <button>.)
 */

$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
?>
<div class="hpop" data-hpop data-cart>
    <a href="/gio-hang" class="hpop__trigger header-action"
       data-hpop-trigger
       aria-label="<?= e(t('action.cart')) ?>, <?= (int) $cartCount ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M5 8h14l-1.2 12H6.2L5 8z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
            <path d="M8.5 8V6.5a3.5 3.5 0 017 0V8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <!-- Thiết kế hiện huy hiệu cả khi giỏ trống (số 0). Ở đây chỉ
             hiện khi có hàng: một chấm đỏ báo "0" là báo động giả. -->
        <?php if ($cartCount > 0): ?>
            <span class="header-action__badge" aria-hidden="true"><?= (int) $cartCount ?></span>
        <?php endif; ?>
    </a>

    <div class="hpop__panel">
        <p class="hpop__head"><?= e(t('action.cart')) ?></p>
        <p class="hpop__note">
            <?= $cartCount > 0
                ? e(sprintf(t('pop.cart_count'), (int) $cartCount))
                : e(t('pop.cart_empty')) ?>
        </p>
        <ul class="hpop__list" role="list">
            <?php if ($cartCount > 0): ?>
                <li><a class="hpop__item" href="/gio-hang"><?= e(t('pop.cart_view')) ?></a></li>
                <li><a class="hpop__item" href="/thanh-toan"><?= e(t('pop.checkout')) ?></a></li>
            <?php else: ?>
                <li><a class="hpop__item" href="/san-pham"><?= e(t('pop.shop')) ?></a></li>
            <?php endif; ?>
        </ul>
    </div>
</div>
