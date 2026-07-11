<?php
/**
 * product/index.php
 * Biến nhận từ ProductController::index():
 *   $products    — mảng 12 sản phẩm tĩnh
 *   $categories  — mảng danh mục filter
 *   $priceRanges — mảng mức giá filter
 */
?>

<!-- ============================================================
     PAGE HEADER
     ============================================================ -->
<section class="page-hero">
    <div class="container">
        <h1 class="page-hero__title">Bộ Sưu Tập Kính Mắt</h1>
        <p class="page-hero__subtitle">Khám phá hàng trăm mẫu gọng kính cao cấp — phong cách, chất lượng, chuẩn thị lực</p>
    </div>
</section>


<!-- ============================================================
     FILTER BAR
     ============================================================ -->
<section class="filter-bar" aria-label="Bộ lọc sản phẩm">
    <div class="container">
        <div class="filter-bar__inner">

            <!-- Filter danh mục -->
            <div class="filter-group" role="group" aria-label="Lọc theo danh mục">
                <span class="filter-group__label">Danh mục:</span>
                <div class="filter-chips" id="categoryFilter">
                    <?php foreach ($categories as $key => $label): ?>
                    <button
                        class="filter-chip <?= $key === 'all' ? 'is-active' : '' ?>"
                        data-filter-cat="<?= htmlspecialchars($key) ?>"
                        aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"
                    >
                        <?= htmlspecialchars($label) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Filter giá -->
            <div class="filter-group" role="group" aria-label="Lọc theo giá">
                <span class="filter-group__label">Giá:</span>
                <div class="filter-chips" id="priceFilter">
                    <?php foreach ($priceRanges as $key => $label): ?>
                    <button
                        class="filter-chip <?= $key === 'all' ? 'is-active' : '' ?>"
                        data-filter-price="<?= htmlspecialchars($key) ?>"
                        aria-pressed="<?= $key === 'all' ? 'true' : 'false' ?>"
                    >
                        <?= htmlspecialchars($label) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Đếm kết quả -->
            <p class="filter-count">
                Hiển thị <span id="productCount"><?= count($products) ?></span> sản phẩm
            </p>

        </div>
    </div>
</section>


<!-- ============================================================
     PRODUCT GRID
     ============================================================ -->
<section class="product-listing" aria-label="Danh sách sản phẩm">
    <div class="container">

        <div class="product-grid" id="productGrid">
            <?php foreach ($products as $product): ?>
            <article
                class="product-card"
                data-category="<?= htmlspecialchars($product['category']) ?>"
                data-price="<?= $product['price'] ?>"
            >
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
                        <?= number_format($product['price'], 0, ',', '.') ?> ₫
                    </p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Empty state khi filter không có kết quả -->
        <div class="product-empty" id="productEmpty" hidden>
            <p>Không tìm thấy sản phẩm phù hợp. Hãy thử bộ lọc khác.</p>
        </div>

    </div>
</section>