<?php
/**
 * _layout/product-card.php
 * Component thẻ sản phẩm dùng chung: home/index, product/index, product/detail.
 *
 * Biến cần có trong scope trước khi require:
 *   $card — mảng sản phẩm (name, price, image, image2, badge)
 *           image2 (tuy chon): anh goc nhin khac, hien khi hover.
 *
 * Cách dùng (LƯU Ý: require, KHÔNG require_once — nằm trong vòng lặp):
 *   <?php foreach ($products as $card): require VIEWS_PATH . '/_layout/product-card.php'; endforeach; ?>
 *
 * MOCKUP: mọi thẻ đều trỏ về /product/detail (1 trang detail dùng chung).
 * Khi có DB, chỉ cần đổi href ở đây là cả 3 trang cùng cập nhật.
 *
 * data-category / data-kind / data-price: phục vụ filter client-side ở trang
 * /product (assets/js/product.js đọc các attribute này để ẩn/hiện card).
 */
?>
<a
    href="/product/detail"
    class="product-card"
    data-category="<?= htmlspecialchars($card['category'] ?? '') ?>"
    data-kind="<?= htmlspecialchars($card['type'] ?? '') ?>"
    data-price="<?= (int) ($card['price'] ?? 0) ?>"
>
    <div class="card-img">
        <img
            src="<?= htmlspecialchars($card['image']) ?>"
            alt="<?= htmlspecialchars($card['name']) ?>"
            loading="lazy"
        >
        <?php if (!empty($card['image2'])): ?>
        <img
            class="card-img-hover"
            src="<?= htmlspecialchars($card['image2']) ?>"
            alt="<?= htmlspecialchars($card['name']) ?> - goc nhin khac"
            loading="lazy"
        >
        <?php endif; ?>
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
