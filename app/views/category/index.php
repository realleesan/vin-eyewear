<link rel="stylesheet" href="/assets/css/category.css">
<script src="/assets/js/category.js" defer></script>

<?php
$breadcrumb_items = [
    ['label' => 'Trang chủ', 'url' => '/'],
    ['label' => 'Danh mục'],
];
$show_breadcrumb = true;
require_once APP_PATH . '/views/_layout/breadcrumb.php';
?>

<section class="page-header">
    <div class="container">
        <div class="header-content">
            <span class="header-label">COLLECTION</span>
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="header-tagline">Khám phá bộ sưu tập kính mắt đẳng cấp</p>
        </div>
    </div>
</section>

<section class="category-section">
    <div class="container">
        <div class="category-layout">
            <!-- Filter Sidebar -->
            <?php
            $filter_type = 'category';
            require_once APP_PATH . '/views/_layout/filter-sidebar.php';
            ?>

            <!-- Products Grid -->
            <div class="category-main">
                <div class="products-grid">
                    <!-- Product 1 -->
                    <article class="product-card">
                        <div class="product-image">
                            <img src="https://cdn.shopify.com/s/files/1/2403/8187/files/plotz-color-gold-pos-1.jpg?v=1765207556&width=800" alt="CLASSIC ROUND GOLD">
                            <span class="product-badge">BÁN CHẠY</span>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">CLASSIC ROUND GOLD</h3>
                            <span class="product-price label-mono">1.250.000₫</span>
                        </div>
                    </article>

                    <!-- Product 2 -->
                    <article class="product-card">
                        <div class="product-image">
                            <img src="https://cdn.shopify.com/s/files/1/2403/8187/files/mingle-color-silver-pos-1.jpg?v=1730403716&width=800" alt="AVIATOR SILVER">
                            <span class="product-badge">MỚI</span>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">AVIATOR SILVER</h3>
                            <span class="product-price label-mono">990.000₫</span>
                        </div>
                    </article>

                    <!-- Product 3 -->
                    <article class="product-card">
                        <div class="product-image">
                            <img src="https://cdn.shopify.com/s/files/1/2403/8187/files/lemtosh-color-tortoise-pos-1_51a51dc4-f52a-4ebf-ae8c-53394cb8720c.jpg?v=1705433402&width=800" alt="SQUARE TORTOISE">
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">SQUARE TORTOISE</h3>
                            <span class="product-price label-mono">1.450.000₫</span>
                        </div>
                    </article>

                    <!-- Product 4 -->
                    <article class="product-card">
                        <div class="product-image">
                            <img src="https://cdn.shopify.com/s/files/1/2403/8187/files/miltzen-color-black-pos-1.jpg?v=1705433402&width=800" alt="MILTZEN BLACK">
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">MILTZEN BLACK</h3>
                            <span class="product-price label-mono">1.100.000₫</span>
                        </div>
                    </article>

                    <!-- Product 5 -->
                    <article class="product-card">
                        <div class="product-image">
                            <img src="https://cdn.shopify.com/s/files/1/2403/8187/files/lamont-color-crystal-pos-1.jpg?v=1705433402&width=800" alt="LAMONT CRYSTAL">
                            <span class="product-badge">HOT</span>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">LAMONT CRYSTAL</h3>
                            <span class="product-price label-mono">1.350.000₫</span>
                        </div>
                    </article>

                    <!-- Product 6 -->
                    <article class="product-card">
                        <div class="product-image">
                            <img src="https://cdn.shopify.com/s/files/1/2403/8187/files/neyman-color-havana-pos-1.jpg?v=1705433402&width=800" alt="NEYMAN HAVANA">
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">NEYMAN HAVANA</h3>
                            <span class="product-price label-mono">1.550.000₫</span>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </div>
</section>
