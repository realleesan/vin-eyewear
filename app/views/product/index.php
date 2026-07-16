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
        <?php foreach ($products as $card): require VIEWS_PATH . '/_layout/product-card.php'; endforeach; ?>
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
