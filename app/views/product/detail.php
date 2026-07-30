<?php
/**
 * product/detail.php
 * Biến nhận từ ProductDetailController::index():
 *   $product — sản phẩm (name, price, image, badge, description, specs[], gallery[])
 *   $related — 4 sản phẩm gợi ý
 *
 * Sản phẩm hiển thị lấy theo ?id= trên URL (xem ProductDetailController).
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
$cta_eyebrow = 'Vin Eyewear · AR Fitting';
$cta_title = 'Đeo thử gọng này ngay bây giờ';
$cta_desc = 'Bật camera, xem gọng kính lên khuôn mặt bạn trong vài giây. Ưng mắt rồi hãy tới cửa hàng.';
$cta_buttons = [
    ['label' => 'Thử AR', 'url' => '/ar', 'style' => 'primary'],
    ['label' => 'Liên hệ tư vấn', 'url' => '/contact', 'style' => 'ghost'],
];
$cta_note = 'Không cần cài ứng dụng.';
$show_pusher = true;
?>

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

    <!-- Cột phải: tên, giá, mô tả -->
    <div class="pd-info">
        <h1 class="pd-name"><?= htmlspecialchars($product['name']) ?></h1>
        <p class="pd-price"><?= number_format($product['price'], 0, ',', '.') ?> &#8363;</p>

        <p class="pd-desc"><?= htmlspecialchars($product['description']) ?></p>

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
     SECTION 2 — SẢN PHẨM GỢI Ý
     Dùng đúng component lưới của trang Sản phẩm: .product-grid +
     modifier .product-grid--boxed (layout.css). KHÔNG viết CSS card riêng cho .pd-related.
     ============================================================ -->
<section class="pd-related reveal">
    <div class="pd-related-header">
        <h2 class="pd-related-title">Có thể bạn thích</h2>
        <a href="/product" class="pd-related-link">Xem tất cả</a>
    </div>

    <div class="product-grid product-grid--boxed" id="pdRelatedGrid">
        <?php foreach ($related as $card): require VIEWS_PATH . '/_layout/product-card.php'; endforeach; ?>
    </div>
</section>