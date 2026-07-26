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
$show_breadcrumb = true;
$breadcrumb_items = [
    ['label' => 'Trang chủ', 'url' => '/'],
    ['label' => 'Sản phẩm', 'url' => '/product'],
    ['label' => $product['name']],
];
$show_page_header = true;
$page_eyebrow = 'Vin Eyewear · Handcrafted Collection';
$show_cta = true;
$cta_buttons = [
    ['label' => 'Thử AR', 'url' => '/ar', 'style' => 'primary'],
    ['label' => 'Liên hệ tư vấn', 'url' => '/contact', 'style' => 'ghost'],
];
$cta_note = 'Thử kính trực tuyến bằng camera — không cần tới cửa hàng.';
$show_pusher = true;
?>

<!-- ============================================================
     SECTION 1 â€” GALLERY + THÃ”NG TIN Sáº¢N PHáº¨M
     ============================================================ -->
<section class="pd-main">

    <!-- Cá»™t trÃ¡i: áº£nh lá»›n + thumbnail -->
    <div class="pd-gallery">
        <div class="pd-stage">
            <img
                id="pd-main-img"
                src="<?= htmlspecialchars($product['gallery'][0]) ?>"
                alt="<?= htmlspecialchars($product['name']) ?>"
            >
            <?php if (!empty($product['badge'])): ?>
            <span class="badge <?= $product['badge'] === 'Má»›i' ? 'badge-dark' : '' ?>"><?= htmlspecialchars($product['badge']) ?></span>
            <?php endif; ?>
        </div>

        <div class="pd-thumbs">
            <?php foreach ($product['gallery'] as $i => $img): ?>
            <button
                type="button"
                class="pd-thumb <?= $i === 0 ? 'active' : '' ?>"
                data-full="<?= htmlspecialchars($img) ?>"
                aria-label="Xem áº£nh <?= $i + 1 ?>"
            >
                <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy">
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Cá»™t pháº£i: tÃªn, giÃ¡, mÃ´ táº£ -->
    <div class="pd-info">
        <h1 class="pd-name"><?= htmlspecialchars($product['name']) ?></h1>
        <p class="pd-price"><?= number_format($product['price'], 0, ',', '.') ?> &#8363;</p>

        <p class="pd-desc"><?= htmlspecialchars($product['description']) ?></p>

        <!-- ThÃ´ng sá»‘ ká»¹ thuáº­t -->
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
     SECTION 2 â€” Sáº¢N PHáº¨M Gá»¢I Ã (dÃ¹ng component .product-grid á»Ÿ layout.css)
     ============================================================ -->
<section class="pd-related reveal">
    <div class="pd-related-header">
        <h2 class="pd-related-title">CÃ³ thá»ƒ báº¡n thÃ­ch</h2>
        <a href="/product" class="pd-related-link">Xem táº¥t cáº£</a>
    </div>

    <div class="product-grid">
        <?php foreach ($related as $card): require VIEWS_PATH . '/_layout/product-card.php'; endforeach; ?>
    </div>
</section>
