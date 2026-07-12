<?php
/**
 * home/index.php
 * Biến nhận từ HomeController::index():
 *   $bestSellers — mảng sản phẩm bán chạy (id, name, price, image, badge)
 *
 * Nạp CSS riêng của trang tại đây vì master chỉ nạp global + layout.
 */
?>
<link rel="stylesheet" href="/assets/css/home.css">

<!-- ============================================================
     SECTION 1 — HERO BANNER
     ============================================================ -->
<section class="hero">
    <div class="hero-content">
        <p class="hero-eyebrow">The Original &middot; Heritage Eyewear</p>
        <h1>Frames with a <em>Soul</em> of Their Own.</h1>
        <p class="hero-sub">Kính mắt cao cấp chế tác thủ công — nơi di sản gặp gỡ công nghệ AR &amp; AI. Tìm gọng kính kể câu chuyện của riêng bạn.</p>
        <div class="hero-actions">
            <a href="/product" class="btn-primary">Shop Eyeglasses</a>
            <a href="/ar" class="btn-ghost">Try On With AR</a>
        </div>
    </div>
    <div class="hero-aside">
        <div class="frames-icon" aria-hidden="true">
            <span class="lens"></span>
            <span class="bridge"></span>
            <span class="lens"></span>
        </div>
    </div>
</section>

<!-- ============================================================
     SECTION 2 — TICKER TAPE
     ============================================================ -->
<div class="ticker">
    <div class="ticker-track">
        <!-- Nhóm item (lặp 2 lần để cuộn liền mạch) -->
        <span>Eyeglasses</span><span class="dot">&#9733;</span>
        <span>Sunglasses</span><span class="dot">&#9733;</span>
        <span>Custom Made Tints&trade;</span><span class="dot">&#9733;</span>
        <span>Free Worldwide Shipping</span><span class="dot">&#9733;</span>
        <span>NYC Since 1915</span><span class="dot">&#9733;</span>

        <span>Eyeglasses</span><span class="dot">&#9733;</span>
        <span>Sunglasses</span><span class="dot">&#9733;</span>
        <span>Custom Made Tints&trade;</span><span class="dot">&#9733;</span>
        <span>Free Worldwide Shipping</span><span class="dot">&#9733;</span>
        <span>NYC Since 1915</span><span class="dot">&#9733;</span>
    </div>
</div>

<!-- ============================================================
     SECTION 3 — BEST SELLERS (dùng PHP loop từ controller)
     ============================================================ -->
<section class="products-section reveal">
    <div class="section-header">
        <h2 class="section-title">Best Sellers</h2>
        <a href="/product" class="section-link">View All Frames</a>
    </div>
    <div class="product-grid">
        <?php foreach ($bestSellers as $product): ?>
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
     SECTION 4 — QUOTE
     ============================================================ -->
<section class="quote-section reveal">
    <span class="quote-mark">&ldquo;</span>
    <blockquote class="quote-text">
        Bạn không chỉ đang đeo một chiếc kính khi chọn Vin Eyewear — bạn đang khoác lên mình
        một lát cắt của lịch sử, của tinh thần phố thị.
    </blockquote>
    <div class="quote-attr">
        <strong>Vin Eyewear</strong> &middot; Heritage Since 1915
    </div>
</section>
