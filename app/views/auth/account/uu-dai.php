<?php

/**
 * auth/account/uu-dai.php — mục "Ưu đãi của tôi" (/tai-khoan?muc=uu-dai).
 *
 * Bản thiết kế: lưới hai cột, mỗi thẻ gồm ô vuông mã giảm bên trái, tên và
 * điều kiện ở giữa, nút "Dùng ngay" bên phải.
 */
?>

<div class="acct-head">
    <h1 class="acct-head__title">Ưu đãi của tôi</h1>
    <p class="acct-head__lead">Mã giảm giá bạn có thể dùng khi thanh toán.</p>
</div>

<?php if ($vouchers === []): ?>
    <div class="acct-empty">
        <span class="acct-empty__ring" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#b0736a"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="8" width="18" height="4" rx="1"></rect>
                <path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8M12 8v13"></path>
            </svg>
        </span>
        <span class="acct-empty__title">Chưa có ưu đãi nào</span>
        <span class="acct-empty__lead">Mã giảm giá dành cho bạn sẽ hiển thị tại đây.</span>
        <a class="acct-empty__cta" href="/san-pham">Khám phá sản phẩm</a>
    </div>
<?php else: ?>
    <div class="acct-vouchers">
        <?php foreach ($vouchers as $v): ?>
            <div class="acct-card acct-voucher">
                <span class="acct-voucher__tag" aria-hidden="true"><?= e($v['tag']) ?></span>

                <div class="acct-voucher__what">
                    <span class="acct-voucher__title"><?= e($v['title']) ?></span>
                    <?php if (!empty($v['condition_text'])): ?>
                        <span class="acct-voucher__cond"><?= e($v['condition_text']) ?></span>
                    <?php endif; ?>
                    <span class="acct-voucher__exp">
                        <?php if (!empty($v['expires_at'])): ?>
                            HSD: <?= e(formatDate($v['expires_at'])) ?>
                        <?php else: ?>
                            Không giới hạn thời gian
                        <?php endif; ?>
                        · Mã <?= e($v['code']) ?>
                    </span>
                </div>

                <!-- "Dùng ngay" đưa sang trang sản phẩm chứ không tự áp mã: mã
                     chỉ có nghĩa khi giỏ đã có hàng, và giỏ hàng của khách lúc
                     này có thể đang trống. -->
                <a class="acct-btn acct-btn--primary acct-btn--sm" href="/san-pham">Dùng ngay</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
