<?php
/**
 * home/index.php
 * Biến nhận từ HomeController::index():
 *   $slides      — mảng dữ liệu banner carousel
 *   $bestSellers — mảng 8 sản phẩm nổi bật
 */
?>

<!-- ============================================================
     SECTION 1: HERO CAROUSEL
     ============================================================ -->
<section class="hero" aria-label="Banner trang chủ">
    <div class="carousel" id="heroCarousel">

        <!-- Slides -->
        <div class="carousel__track" id="carouselTrack">
            <?php foreach ($slides as $i => $slide): ?>
            <div
                class="carousel__slide <?= $i === 0 ? 'is-active' : '' ?>"
                aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>"
                style="background-image: url('<?= htmlspecialchars($slide['image']) ?>')"
            >
                <div class="carousel__overlay"></div>
                <div class="carousel__content container">
                    <h1 class="carousel__title"><?= htmlspecialchars($slide['title']) ?></h1>
                    <p class="carousel__subtitle"><?= htmlspecialchars($slide['subtitle']) ?></p>
                    <a href="<?= htmlspecialchars($slide['cta_href']) ?>" class="btn btn-primary carousel__cta">
                        <?= htmlspecialchars($slide['cta_text']) ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Điều hướng prev / next -->
        <button class="carousel__btn carousel__btn--prev" id="carouselPrev" aria-label="Slide trước">&#8592;</button>
        <button class="carousel__btn carousel__btn--next" id="carouselNext" aria-label="Slide tiếp theo">&#8594;</button>

        <!-- Dots -->
        <div class="carousel__dots" id="carouselDots" role="tablist" aria-label="Chọn slide">
            <?php foreach ($slides as $i => $_): ?>
            <button
                class="carousel__dot <?= $i === 0 ? 'is-active' : '' ?>"
                role="tab"
                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                aria-label="Slide <?= $i + 1 ?>"
                data-index="<?= $i ?>"
            ></button>
            <?php endforeach; ?>
        </div>

    </div>
</section>


<!-- ============================================================
     SECTION 2: BEST SELLER GRID
     ============================================================ -->
<section class="best-seller" aria-labelledby="bestSellerHeading">
    <div class="container">

        <div class="section-header">
            <h2 class="section-title" id="bestSellerHeading">Sản Phẩm Nổi Bật</h2>
            <p class="section-subtitle">Những gọng kính được yêu thích nhất tại Vin Eyewear</p>
        </div>

        <div class="product-grid">
            <?php foreach ($bestSellers as $product): ?>
            <article class="product-card">

                <!-- Ảnh sản phẩm -->
                <div class="product-card__image-wrap">
                    <img
                        src="<?= htmlspecialchars($product['image']) ?>"
                        alt="<?= htmlspecialchars($product['name']) ?>"
                        class="product-card__image"
                        loading="lazy"
                    >
                    <?php if (!empty($product['badge'])): ?>
                    <span class="product-card__badge"><?= htmlspecialchars($product['badge']) ?></span>
                    <?php endif; ?>

                    <!-- Overlay action -->
                    <div class="product-card__actions">
                        <a href="/product/<?= $product['id'] ?>" class="btn btn-primary product-card__btn">
                            Xem chi tiết
                        </a>
                    </div>
                </div>

                <!-- Thông tin sản phẩm -->
                <div class="product-card__info">
                    <h3 class="product-card__name">
                        <a href="/product/<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                    </h3>
                    <p class="product-card__price">
                        <?= number_format($product['price'], 0, ',', '.') ?> ₫
                    </p>
                </div>

            </article>
            <?php endforeach; ?>
        </div>

        <!-- CTA xem thêm -->
        <div class="best-seller__footer">
            <a href="/product" class="btn btn-outline">Xem tất cả sản phẩm</a>
        </div>

    </div>
</section>