<?php
/**
 * product/index.php
 * Biến nhận từ ProductController::index():
 *   $products — mảng sản phẩm (id, name, category, price, image, badge)
 * Card dùng component chung .product-card (định nghĩa ở layout.css), đồng bộ với home.
 */
?>
<link rel="stylesheet" href="/assets/css/product.css">

<!-- ============================================================
     PAGE HEADER
     ============================================================ -->
<div class="page-header reveal">
    <div class="page-header-inner">
        <p class="page-eyebrow">Eyewear Collection</p>
        <h1 class="page-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Frames' ?></h1>
        <p class="page-subtitle">
            Over a century of optical expertise from the Lower East Side.
        </p>
    </div>
</div>

<!-- ============================================================
     PRODUCT GRID
     ============================================================ -->
<section class="product-listing reveal">
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
        <div class="product-card">
            <div class="card-img">
                <img
                    src="<?= htmlspecialchars($product['image']) ?>"
                    alt="<?= htmlspecialchars($product['name']) ?>"
                    loading="lazy"
                >
                <div class="card-overlay">
                    <button class="quick-shop-btn">Quick Shop</button>
                </div>
                <?php if (!empty($product['badge'])): ?>
                <span class="badge <?= $product['badge'] === 'Mới' ? 'badge-dark' : '' ?>"><?= htmlspecialchars($product['badge']) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-info">
                <div class="card-name"><?= htmlspecialchars($product['name']) ?></div>
                <div class="card-price"><?= number_format($product['price'], 0, ',', '.') ?> &#8363;</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================================================
     PAGINATION (tĩnh — chưa có phân trang từ controller)
     ============================================================ -->
<div class="pagination">
    <span class="active">1</span>
    <a href="#">2</a>
    <a href="#">3</a>
    <a href="#">Next</a>
</div>
