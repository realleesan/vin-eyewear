<?php
/**
 * product/detail.php
 * Biến nhận từ ProductDetailController::index():
 *   $product — sản phẩm (name, price, image, badge, description, specs[], gallery[])
 *   $related — 4 sản phẩm gợi ý
 *
 * MOCKUP: đây là trang detail DÙNG CHUNG — mọi thẻ sản phẩm đều trỏ về đây.
 * CSS/JS riêng của trang do master.php nạp theo $viewName.
 */
?>

<!-- ============================================================
     BREADCRUMB
     ============================================================ -->
<nav class="pd-breadcrumb" aria-label="Breadcrumb">
    <a href="/">Trang chủ</a>
    <span aria-hidden="true">/</span>
    <a href="/product">Sản phẩm</a>
    <span aria-hidden="true">/</span>
    <span class="current"><?= htmlspecialchars($product['name']) ?></span>
</nav>

<!-- ============================================================
     SECTION 1 — GALLERY + THÔNG TIN SẢN PHẨM
     ============================================================ -->
<section class="pd-main">

    <!-- Cột trái: ảnh lớn + thumbnail -->
    <div class="pd-gallery">
        <div class="pd-stage">
            <img
                id="pd-main-img"
                src="<?= htmlspecialchars($product['gallery'][0]) ?>"
                alt="<?= htmlspecialchars($product['name']) ?>"
            >
            <?php if (!empty($product['badge'])): ?>
            <span class="badge <?= $product['badge'] === 'Mới' ? 'badge-dark' : '' ?>"><?= htmlspecialchars($product['badge']) ?></span>
            <?php endif; ?>
        </div>

        <div class="pd-thumbs">
            <?php foreach ($product['gallery'] as $i => $img): ?>
            <button
                type="button"
                class="pd-thumb <?= $i === 0 ? 'active' : '' ?>"
                data-full="<?= htmlspecialchars($img) ?>"
                aria-label="Xem ảnh <?= $i + 1 ?>"
            >
                <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy">
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Cột phải: tên, giá, mô tả, CTA -->
    <div class="pd-info">
        <p class="pd-eyebrow">Vin Eyewear &middot; Handcrafted Collection</p>
        <h1 class="pd-name"><?= htmlspecialchars($product['name']) ?></h1>
        <p class="pd-price"><?= number_format($product['price'], 0, ',', '.') ?> &#8363;</p>

        <p class="pd-desc"><?= htmlspecialchars($product['description']) ?></p>

        <div class="pd-actions">
            <a href="/ar" class="pd-btn-primary">Thử AR</a>
            <a href="/contact" class="pd-btn-ghost">Liên hệ tư vấn</a>
        </div>

        <p class="pd-note">Thử kính trực tuyến bằng camera — không cần tới cửa hàng.</p>

        <!-- Thông số kỹ thuật -->
        <dl class="pd-specs">
            <?php foreach ($product['specs'] as $label => $value): ?>
            <div class="pd-spec-row">
                <dt><?= htmlspecialchars($label) ?></dt>
                <dd><?= htmlspecialchars($value) ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
    </div>

</section>

<!-- ============================================================
     SECTION 2 — SẢN PHẨM GỢI Ý (dùng component .product-grid ở layout.css)
     ============================================================ -->
<section class="pd-related reveal">
    <div class="pd-related-header">
        <h2 class="pd-related-title">Có thể bạn thích</h2>
        <a href="/product" class="pd-related-link">Xem tất cả</a>
    </div>

    <div class="product-grid">
        <?php foreach ($related as $card): require VIEWS_PATH . '/_layout/product-card.php'; endforeach; ?>
    </div>
</section>
