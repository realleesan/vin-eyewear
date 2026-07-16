<?php
/**
 * home/index.php
 * Trang chủ dựng theo layout Moscot (xem screencapture-moscot-*.png).
 *
 * Biến nhận từ HomeController::index():
 *   $hero, $sunnies, $tiles, $feature, $heritage,
 *   $tints, $optical, $craftsmanship, $stories, $visit
 *
 * Thẻ sản phẩm ở trang chủ dùng component riêng .frame-card (ảnh nền xám,
 * chữ căn giữa, hàng chấm màu) — khác .product-card của trang /product.
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
     SECTION 2 — SIGNATURE SUNNIES (tab + 4 thẻ)
     ============================================================ -->
<section class="sunnies reveal">
    <h2 class="section-heading">
        <img class="section-heading__icon" src="<?= htmlspecialchars($sunnies['icon']) ?>" alt="" loading="lazy">
        <span><?= htmlspecialchars($sunnies['title']) ?></span>
    </h2>

    <div class="tabs" role="tablist">
        <?php foreach ($sunnies['tabs'] as $i => $tab): ?>
        <button
            type="button"
            class="tab <?= $i === 0 ? 'is-active' : '' ?>"
            role="tab"
            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
            aria-controls="tabpanel-<?= htmlspecialchars($tab['id']) ?>"
            data-tab="<?= htmlspecialchars($tab['id']) ?>"
        ><?= htmlspecialchars($tab['label']) ?></button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($sunnies['tabs'] as $i => $tab): ?>
    <div
        class="tabpanel <?= $i === 0 ? 'is-active' : '' ?>"
        id="tabpanel-<?= htmlspecialchars($tab['id']) ?>"
        role="tabpanel"
        data-panel="<?= htmlspecialchars($tab['id']) ?>"
        <?= $i === 0 ? '' : 'hidden' ?>
    >
        <p class="section-desc"><?= htmlspecialchars($tab['desc']) ?></p>
        <div class="frame-grid">
            <?php foreach ($tab['products'] as $p) { $renderFrameCard($p); } ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="section-cta">
        <a href="/product" class="btn-yellow">Xem tất cả gọng</a>
    </div>
</section>

<!-- ============================================================
     SECTION 3 — 2 TILE DANH MỤC
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
     SECTION 4 — FEATURE (thông tin trái + gallery phải)
     ============================================================ -->
<section class="feature reveal">
    <div class="feature__info">
        <h2 class="feature__name"><?= htmlspecialchars($feature['name']) ?></h2>
        <img class="feature__thumb" src="<?= htmlspecialchars($feature['thumb']) ?>" alt="" loading="lazy">
        <p class="feature__price"><?= number_format($feature['price'], 0, ',', '.') ?> &#8363;</p>
        <p class="feature__rating">
            <span class="stars" aria-hidden="true"><?= str_repeat('&#9733;', $feature['rating']) ?></span>
            <span class="reviews"><?= (int) $feature['reviews'] ?> đánh giá</span>
        </p>
        <p class="feature__desc"><?= htmlspecialchars($feature['desc']) ?></p>
        <a href="<?= htmlspecialchars($feature['cta']['link']) ?>" class="btn-yellow"><?= htmlspecialchars($feature['cta']['label']) ?></a>
    </div>

    <div class="feature__gallery">
        <div class="feature__stage">
            <img id="featureImg" src="<?= htmlspecialchars($feature['gallery'][0]) ?>" alt="<?= htmlspecialchars($feature['name']) ?>" loading="lazy">
        </div>
        <div class="feature__thumbs">
            <?php foreach ($feature['gallery'] as $i => $img): ?>
            <button
                type="button"
                class="feature__thumb-btn <?= $i === 0 ? 'is-active' : '' ?>"
                data-full="<?= htmlspecialchars($img) ?>"
                aria-label="Xem ảnh <?= $i + 1 ?>"
            >
                <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy">
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================
     SECTION 5 — HERITAGE (ảnh B&W + panel vàng)
     ============================================================ -->
<section class="heritage reveal">
    <div class="heritage__media">
        <img src="<?= htmlspecialchars($heritage['image']) ?>" alt="Cửa hàng kính đầu thế kỷ 20" loading="lazy">
    </div>
    <div class="heritage__panel">
        <div class="heritage__badge">
            <span class="heritage__badge-top">VIN EYEWEAR</span>
            <span class="heritage__badge-eye" aria-hidden="true">&#9673;</span>
            <span class="heritage__badge-bottom">OPTICIANS</span>
        </div>
        <blockquote class="heritage__quote">&ldquo;<?= htmlspecialchars($heritage['quote']) ?>&rdquo;</blockquote>
        <p class="heritage__author"><?= htmlspecialchars($heritage['author']) ?></p>
        <p class="heritage__role"><?= htmlspecialchars($heritage['role']) ?></p>
        <a href="<?= htmlspecialchars($heritage['cta']['link']) ?>" class="btn-black"><?= htmlspecialchars($heritage['cta']['label']) ?></a>
    </div>
</section>

<!-- ============================================================
     SECTION 6 — CUSTOM MADE TINTS (panel xám + accordion)
     ============================================================ -->
<section class="tints reveal">
    <div class="tints__info">
        <h2 class="tints__title">
            <?= htmlspecialchars($tints['title']) ?>
            <span class="arrow" aria-hidden="true">&#8599;</span>
        </h2>
        <p class="tints__body">
            <?= htmlspecialchars($tints['body']) ?>
            Ghé <a href="<?= htmlspecialchars($tints['link']['href']) ?>"><?= htmlspecialchars($tints['link']['label']) ?></a>
            để tự tay pha màu cho chiếc gọng bạn thích.
        </p>
        <a href="<?= htmlspecialchars($tints['cta']['link']) ?>" class="btn-yellow"><?= htmlspecialchars($tints['cta']['label']) ?></a>

        <ul class="tints__rows">
            <?php foreach ($tints['rows'] as $row): ?>
            <li>
                <a href="<?= htmlspecialchars($row['link']) ?>">
                    <?= htmlspecialchars($row['label']) ?>
                    <span class="arrow" aria-hidden="true">&#8599;</span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="tints__media">
        <img src="<?= htmlspecialchars($tints['image']) ?>" alt="Tròng kính pha màu thủ công" loading="lazy">
    </div>
</section>

<!-- ============================================================
     SECTION 7 — THE OPTICAL SHOP
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
     SECTION 8 — DẢI ẢNH CRAFTSMANSHIP
     ============================================================ -->
<section class="craft reveal">
    <img src="<?= htmlspecialchars($craftsmanship['image']) ?>" alt="<?= htmlspecialchars($craftsmanship['alt']) ?>" loading="lazy">
</section>

<!-- ============================================================
     SECTION 9 — STORIES
     ============================================================ -->
<section class="stories reveal">
    <h2 class="section-heading"><span><?= htmlspecialchars($stories['title']) ?></span></h2>
    <p class="section-desc"><?= htmlspecialchars($stories['desc']) ?></p>

    <div class="stories__grid">
        <?php foreach ($stories['items'] as $item): ?>
        <article class="story">
            <a href="<?= htmlspecialchars($item['link']) ?>" class="story__img">
                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy">
            </a>
            <h3 class="story__title"><?= htmlspecialchars($item['title']) ?></h3>
            <p class="story__body"><?= htmlspecialchars($item['body']) ?></p>
            <a href="<?= htmlspecialchars($item['link']) ?>" class="story__link">Đọc tiếp</a>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============================================================
     SECTION 10 — VISIT US IN SHOP
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

<!-- SECTION 11 — JOIN THE FAMILY: nằm trong _layout/footer.php
     (dải đen trên footer, hiện ở mọi trang — giống Moscot) -->
