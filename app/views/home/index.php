<?php
/**
 * home/index.php
 * Biến nhận từ HomeController::index():
 *   $slides      — mảng slide cho hero carousel (image, title, subtitle, cta_text, cta_href)
 *   $bestSellers — mảng sản phẩm bán chạy (id, name, price, image, badge)
 */
?>

<!-- ============================================================
     HERO CAROUSEL
     ============================================================ -->
<section class="hero">
    <div class="carousel" id="heroCarousel">

        <!-- Track chứa các slide -->
        <div class="carousel__track" id="carouselTrack">
            <?php foreach ($slides as $i => $slide): ?>
            <div
                class="carousel__slide <?= $i === 0 ? 'is-active' : '' ?>"
                style="background-image: url('<?= htmlspecialchars($slide['image']) ?>')"
                aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>"
            >
                <div class="carousel__overlay"></div>
                <div class="container">
                    <div class="carousel__content">
                        <h2 class="carousel__title"><?= htmlspecialchars($slide['title']) ?></h2>
                        <p class="carousel__subtitle"><?= htmlspecialchars($slide['subtitle']) ?></p>
                        <a href="<?= htmlspecialchars($slide['cta_href']) ?>" class="btn btn-primary carousel__cta">
                            <?= htmlspecialchars($slide['cta_text']) ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Prev / Next -->
        <button class="carousel__btn carousel__btn--prev" id="carouselPrev" aria-label="Slide trước">&#8249;</button>
        <button class="carousel__btn carousel__btn--next" id="carouselNext" aria-label="Slide sau">&#8250;</button>

        <!-- Dots -->
        <div class="carousel__dots" id="carouselDots">
            <?php foreach ($slides as $i => $slide): ?>
            <button
                class="carousel__dot <?= $i === 0 ? 'is-active' : '' ?>"
                data-index="<?= $i ?>"
                aria-label="Chuyển đến slide <?= $i + 1 ?>"
                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
            ></button>
            <?php endforeach; ?>
        </div>

    </div>
</section>


<!-- ============================================================
     BEST SELLER
     ============================================================ -->
<section class="best-seller" aria-label="Sản phẩm bán chạy">
    <div class="container">

        <div class="section-header">
            <h2 class="section-title">Sản Phẩm Bán Chạy</h2>
            <p class="section-subtitle">Những mẫu gọng kính được yêu thích nhất tại Vin Eyewear</p>
        </div>

        <div class="product-grid">
            <?php foreach ($bestSellers as $product): ?>
            <article class="product-card">
                <!-- Ảnh -->
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

                    <!-- Overlay actions -->
                    <div class="product-card__actions">
                        <a href="/product/<?= $product['id'] ?>" class="btn btn-primary product-card__btn">
                            Xem chi tiết
                        </a>
                        <a href="/ar?id=<?= $product['id'] ?>" class="btn btn-outline product-card__btn product-card__btn--ar">
                            Thử AR
                        </a>
                    </div>
                </div>

                <!-- Info -->
                <div class="product-card__info">
                    <h3 class="product-card__name">
                        <a href="/product/<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                    </h3>
                    <p class="product-card__price">
                        <?= number_format($product['price'], 0, ',', '.') ?> &#8363;
                    </p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="best-seller__footer">
            <a href="/product" class="btn btn-outline">Xem tất cả sản phẩm</a>
        </div>

    </div>
</section>
