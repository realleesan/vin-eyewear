<?php
// CSS & JS riêng của trang PRODUCT
?>
<link rel="stylesheet" href="/assets/css/product.css">

<!-- ===================== PAGE HEADER ===================== -->
<section class="page-hero">
    <div class="container">
        <h1><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Sản phẩm' ?></h1>
        <p>Khám phá bộ sưu tập gọng kính cao cấp đa dạng phong cách</p>
    </div>
</section>

<!-- ===================== FILTER BAR ===================== -->
<section class="product-filter-bar">
    <div class="container">
        <div class="filter-group">
            <span class="filter-label">Danh mục:</span>
            <button class="filter-btn active" data-filter="all">Tất cả</button>
            <button class="filter-btn" data-filter="gong-can">Gọng cận</button>
            <button class="filter-btn" data-filter="gong-mat">Gọng mát</button>
            <button class="filter-btn" data-filter="gong-doc">Gọng đọc sách</button>
        </div>
        <div class="filter-group">
            <span class="filter-label">Sắp xếp:</span>
            <select class="filter-select">
                <option value="default">Mặc định</option>
                <option value="price-asc">Giá tăng dần</option>
                <option value="price-desc">Giá giảm dần</option>
                <option value="new">Mới nhất</option>
            </select>
        </div>
    </div>
</section>

<!-- ===================== PRODUCT GRID ===================== -->
<section class="products-section">
    <div class="container">

        <?php
        // Dữ liệu tĩnh 12 sản phẩm (Phase 1 – không dùng DB)
        $products = [
            ['name' => 'Classic Aviator Gold',   'price' => '1.500.000₫', 'cat' => 'gong-mat',  'tag' => 'Bán chạy', 'img' => ''],
            ['name' => 'Modern Square Black',    'price' => '2.000.000₫', 'cat' => 'gong-can',  'tag' => 'Mới',      'img' => ''],
            ['name' => 'Premium Titanium Slim',  'price' => '3.500.000₫', 'cat' => 'gong-can',  'tag' => 'Cao cấp',  'img' => ''],
            ['name' => 'Sport Shield Pro',       'price' => '1.800.000₫', 'cat' => 'gong-mat',  'tag' => 'Hot',      'img' => ''],
            ['name' => 'Vintage Round Rose',     'price' => '1.200.000₫', 'cat' => 'gong-mat',  'tag' => '',         'img' => ''],
            ['name' => 'Cat Eye Crystal Blue',   'price' => '2.200.000₫', 'cat' => 'gong-can',  'tag' => 'Mới',      'img' => ''],
            ['name' => 'Half-rim Slim Silver',   'price' => '1.650.000₫', 'cat' => 'gong-doc',  'tag' => '',         'img' => ''],
            ['name' => 'Oversized Bold Brown',   'price' => '1.900.000₫', 'cat' => 'gong-mat',  'tag' => 'Hot',      'img' => ''],
            ['name' => 'Wire Frame Minimalist',  'price' => '1.100.000₫', 'cat' => 'gong-doc',  'tag' => '',         'img' => ''],
            ['name' => 'Hexagon Acetate Green',  'price' => '2.400.000₫', 'cat' => 'gong-can',  'tag' => 'Mới',      'img' => ''],
            ['name' => 'Butterfly Fashion Pink', 'price' => '1.750.000₫', 'cat' => 'gong-mat',  'tag' => '',         'img' => ''],
            ['name' => 'Browline Classic Tort',  'price' => '1.300.000₫', 'cat' => 'gong-can',  'tag' => 'Bán chạy', 'img' => ''],
        ];
        ?>

        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $p): ?>
            <div class="product-card" data-cat="<?= $p['cat'] ?>">

                <?php if ($p['tag']): ?>
                <span class="product-badge"><?= $p['tag'] ?></span>
                <?php endif; ?>

                <div class="product-image">
                    <?php if ($p['img']): ?>
                        <img src="<?= $p['img'] ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php else: ?>
                        <!-- Placeholder ảnh gọng kính – sẽ thay bằng ảnh thật -->
                        <svg viewBox="0 0 280 200" xmlns="http://www.w3.org/2000/svg" width="100%">
                            <rect width="280" height="200" fill="#f0ede6"/>
                            <g stroke="#c9a96e" stroke-width="4" fill="none">
                                <ellipse cx="90"  cy="100" rx="60" ry="44"/>
                                <ellipse cx="190" cy="100" rx="60" ry="44"/>
                                <line x1="150" y1="100" x2="130" y2="100"/>
                                <line x1="30"  y1="86"  x2="10"  y2="80"/>
                                <line x1="250" y1="86"  x2="270" y2="80"/>
                            </g>
                        </svg>
                    <?php endif; ?>

                    <div class="product-overlay">
                        <a href="/ar" class="btn-ar">Thử kính AR</a>
                    </div>
                </div>

                <div class="product-info">
                    <h3><?= htmlspecialchars($p['name']) ?></h3>
                    <p class="price"><?= $p['price'] ?></p>
                    <a href="#" class="btn btn-primary btn-sm">Xem chi tiết</a>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <!-- Thông báo khi filter không có kết quả -->
        <div class="no-results" id="noResults" style="display:none;">
            <p>Không tìm thấy sản phẩm phù hợp.</p>
        </div>

    </div>
</section>

<script src="/assets/js/product.js" defer></script>
