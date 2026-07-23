<?php
/**
 * _layout/product-card.php
 * Component thẻ sản phẩm dùng chung: home/index, product/index, product/detail.
 *
 * Biến cần có trong scope trước khi require:
 *   $card — mảng sản phẩm (name, price, image, badge)
 *
 * Cách dùng (LƯU Ý: require, KHÔNG require_once — nằm trong vòng lặp):
 *   <?php foreach ($products as $card): require VIEWS_PATH . '/_layout/product-card.php'; endforeach; ?>
 *
 * MOCKUP: mọi thẻ đều trỏ về /product/detail (1 trang detail dùng chung).
 * Khi có DB, chỉ cần đổi href ở đây là cả 3 trang cùng cập nhật.
 */
?>
<a href="/product/detail" class="product-card">
    <div class="card-img">
        <img
            src="<?= htmlspecialchars($card['image']) ?>"
            alt="<?= htmlspecialchars($card['name']) ?>"
            loading="lazy"
        >
        <div class="card-overlay">
            <span class="quick-shop-btn">Quick Shop</span>
        </div>
        <?php if (!empty($card['badge'])): ?>
        <span class="badge <?= $card['badge'] === 'Mới' ? 'badge-dark' : '' ?>"><?= htmlspecialchars($card['badge']) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-info">
        <div class="card-name"><?= htmlspecialchars($card['name']) ?></div>
        <div class="card-price"><?= number_format($card['price'], 0, ',', '.') ?> &#8363;</div>
    </div>
</a>
