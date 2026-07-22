<?php
/**
 * home/index.php
 * Trang chủ dựng theo layout Moscot (xem screencapture-moscot-*.png).
 *
 * Biến nhận từ HomeController::index():
 *   $hero, $bestseller, $tiles, $optical,
 *   $craftsmanship, $stories, $booking, $visit
 *   (các biến $feature, $heritage, $tints vẫn được controller cấp nhưng
 *    section tương ứng đã gỡ khỏi trang chủ.)
 *
 * Thẻ sản phẩm ở trang chủ dùng component riêng .frame-card (ảnh nền xám,
 * chữ căn giữa, hàng chấm màu) — khác .product-card của trang /product.
 *
 * Thứ tự section: hero -> best seller (trên cùng), nhóm sản phẩm (tiles,
 * optical) -> craft -> nội dung (stories) -> ghé cửa hàng (booking, visit)
 * -> join (footer).
 */

/** Render 1 hàng chấm màu cho thẻ sản phẩm */
$renderSwatches = static function (array $colors): void {
    if (empty($colors)) {
        return;
    }
    echo '<div class="frame-swatches">';
    foreach ($colors as $hex) {
        printf(
            '<span class="frame-swatch" style="background:%s"></span>',
            htmlspecialchars($hex)
        );
    }
    echo '</div>';
};

/** Render 1 thẻ sản phẩm kiểu trang chủ */
$renderFrameCard = static function (array $p) use ($renderSwatches): void { ?>
    <a href="/product/detail" class="frame-card">
        <div class="frame-card__img">
            <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
            <?php if (!empty($p['badge'])): ?>
            <span class="frame-card__badge"><?= htmlspecialchars($p['badge']) ?></span>
            <?php endif; ?>
        </div>
        <h3 class="frame-card__name"><?= htmlspecialchars($p['name']) ?></h3>
        <p class="frame-card__price"><?= number_format($p['price'], 0, ',', '.') ?> &#8363;</p>
        <?php $renderSwatches($p['colors'] ?? []); ?>
    </a>
<?php }; ?>

<?php
/** Render icon SVG cho huy hiệu cam kết (Booking) theo khóa 'icon' */
$renderCommitIcon = static function (string $key): void {
    $icons = [
        'seal'   => '<circle cx="12" cy="9.5" r="6"/><path d="M9.6 9.5l1.7 1.7 3.1-3.3"/><path d="M8 14.2l-1.4 6 5.4-2.9 5.4 2.9-1.4-6"/>',
        'uv'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8"/>',
        'shield' => '<path d="M12 3l7 2.6v5.1c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V5.6z"/><path d="M9 12l2 2 4-4.2"/>',
        'return' => '<path d="M4.5 9.5a8 8 0 0 1 13-2.6L20 9"/><path d="M20 4.5V9h-4.5"/><path d="M19.5 14.5a8 8 0 0 1-13 2.6L4 15"/><path d="M4 19.5V15h4.5"/>',
    ];
    echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($icons[$key] ?? '') . '</svg>';
};
?>

<!-- ============================================================
     SECTION 1 — HERO (ảnh full-bleed + CTA)
     ============================================================ -->
<section class="hero">
    <img class="hero__img" src="<?= htmlspecialchars($hero['image']) ?>" alt="<?= htmlspecialchars($hero['alt']) ?>">
    <a href="<?= htmlspecialchars($hero['cta']['link']) ?>" class="btn-yellow hero__cta"><?= htmlspecialchars($hero['cta']['label']) ?></a>
    <div class="hero__dots" aria-hidden="true">
        <span class="is-active"></span><span></span><span></span>
    </div>
</section>

<!-- ============================================================
     SECTION 2 — BEST SELLER (carousel sản phẩm bán chạy)
     ============================================================ -->
<section class="bestseller reveal">
    <h2 class="section-heading">
        <img class="section-heading__icon" src="<?= htmlspecialchars($bestseller['icon']) ?>" alt="" loading="lazy">
        <span><?= htmlspecialchars($bestseller['title']) ?></span>
    </h2>

    <p class="section-desc"><?= htmlspecialchars($bestseller['desc']) ?></p>

    <div class="carousel">
        <button type="button" class="carousel__btn carousel__btn--prev" data-dir="prev" aria-label="Sản phẩm trước">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M20 12H5"/><path d="M11 6l-6 6 6 6"/>
            </svg>
        </button>

        <div class="frame-track" id="bestsellerTrack">
            <?php foreach ($bestseller['products'] as $p) { $renderFrameCard($p); } ?>
        </div>

        <button type="button" class="carousel__btn carousel__btn--next" data-dir="next" aria-label="Sản phẩm sau">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M4 12h15"/><path d="M13 6l6 6-6 6"/>
            </svg>
        </button>
    </div>

    <div class="section-cta">
        <a href="/product" class="btn-yellow">Xem tất cả gọng</a>
    </div>
</section>

<!-- ============================================================
     SECTION 3 — 2 TILE DANH MỤC (Kính cận / Kính thời trang)
     ============================================================ -->
<section class="tiles reveal">
    <?php foreach ($tiles as $tile): ?>
    <a href="<?= htmlspecialchars($tile['link']) ?>" class="tile">
        <img class="tile__img" src="<?= htmlspecialchars($tile['image']) ?>" alt="<?= htmlspecialchars($tile['title']) ?>" loading="lazy">
        <div class="tile__body">
            <h3 class="tile__title"><?= htmlspecialchars($tile['title']) ?></h3>
            <span class="btn-yellow tile__btn">Xem bộ sưu tập</span>
        </div>
    </a>
    <?php endforeach; ?>
</section>

<!-- ============================================================
     SECTION 4 — THE OPTICAL SHOP (thương hiệu + lưới sản phẩm)
     ============================================================ -->
<section class="optical reveal">
    <h2 class="section-heading">
        <span><?= htmlspecialchars($optical['title']) ?></span>
        <img class="section-heading__icon" src="<?= htmlspecialchars($optical['icon']) ?>" alt="" loading="lazy">
    </h2>

    <?php foreach ($optical['desc'] as $para): ?>
    <p class="section-desc"><?= htmlspecialchars($para) ?></p>
    <?php endforeach; ?>

    <div class="frame-grid">
        <?php foreach ($optical['products'] as $p) { $renderFrameCard($p); } ?>
    </div>

    <div class="section-cta">
        <a href="/product" class="btn-yellow">Xem tất cả gọng</a>
    </div>
</section>

<!-- ============================================================
     SECTION 5 — DẢI ẢNH CRAFTSMANSHIP
     ============================================================ -->
<section class="craft reveal">
    <img src="<?= htmlspecialchars($craftsmanship['image']) ?>" alt="<?= htmlspecialchars($craftsmanship['alt']) ?>" loading="lazy">
</section>

<!-- ============================================================
     SECTION 6 — BOOKING (mời đo mắt tại cửa hàng + cam kết & cảm nhận)
     ============================================================ -->
<section class="booking reveal">

    <!-- (1) Mời đo mắt & thử kính tại cửa hàng -->
    <div class="booking-visit">
        <div class="booking-visit__info">
            <span class="booking-visit__label"><?= htmlspecialchars($booking['visit']['label']) ?></span>
            <h2 class="booking-visit__title"><?= htmlspecialchars($booking['visit']['title']) ?></h2>
            <p class="booking-visit__desc"><?= htmlspecialchars($booking['visit']['desc']) ?></p>
            <a href="<?= htmlspecialchars($booking['visit']['cta']['link']) ?>" class="btn-yellow"><?= htmlspecialchars($booking['visit']['cta']['label']) ?></a>
        </div>
        <div class="booking-visit__media">
            <img src="<?= htmlspecialchars($booking['visit']['image']) ?>" alt="Đo mắt và thử kính tại cửa hàng Vin Eyewear" loading="lazy">
        </div>
    </div>

    <!-- (2) Cam kết chất lượng + Cảm nhận khách hàng -->
    <div class="booking-trust">
        <ul class="commit-list" role="list">
            <?php foreach ($booking['commitments'] as $c): ?>
            <li class="commit">
                <span class="commit__icon"><?php $renderCommitIcon($c['icon']); ?></span>
                <span class="commit__label"><?= htmlspecialchars($c['label']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>

        <h2 class="section-heading"><span><?= htmlspecialchars($booking['reviewsTitle']) ?></span></h2>

        <div class="reviews">
            <?php foreach ($booking['reviews'] as $r):
                $parts   = preg_split('/\s+/', trim($r['name']));
                $initial = mb_strtoupper(mb_substr(end($parts), 0, 1));
            ?>
            <figure class="review">
                <div class="review__stars" aria-label="<?= (int) $r['rating'] ?> trên 5 sao"><?= str_repeat('&#9733;', (int) $r['rating']) ?></div>
                <blockquote class="review__quote"><?= htmlspecialchars($r['quote']) ?></blockquote>
                <figcaption class="review__author">
                    <span class="review__avatar" aria-hidden="true"><?= htmlspecialchars($initial) ?></span>
                    <span class="review__name"><?= htmlspecialchars($r['name']) ?></span>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     SECTION 7 — VISIT US IN SHOP
     ============================================================ -->
<section class="visit reveal">
    <div class="visit__info">
        <h2 class="visit__title"><?= htmlspecialchars($visit['title']) ?></h2>
        <p class="visit__desc"><?= htmlspecialchars($visit['desc']) ?></p>
        <div class="visit__ctas">
            <?php foreach ($visit['ctas'] as $cta): ?>
            <a
                href="<?= htmlspecialchars($cta['link']) ?>"
                class="<?= $cta['style'] === 'yellow' ? 'btn-yellow' : 'btn-black' ?>"
            ><?= htmlspecialchars($cta['label']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="visit__media">
        <img src="<?= htmlspecialchars($visit['image']) ?>" alt="Cửa hàng Vin Eyewear" loading="lazy">
    </div>
</section>

<!-- SECTION 8 — JOIN THE FAMILY: nằm trong _layout/footer.php
     (dải đen trên footer, hiện ở mọi trang — giống Moscot) -->
