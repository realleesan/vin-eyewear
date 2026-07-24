<?php
/**
 * product/index.php
 * Biến nhận từ ProductController::index():
 *   $products — mảng sản phẩm (id, name, category, price, image, badge)
 * Card dùng component chung .product-card (định nghĩa ở layout.css), đồng bộ với home.
 * Filter: khung UI dùng component chung _layout/filter-sidebar.php ("Vỏ"),
 * logic lọc client-side nằm ở assets/js/product.js (đọc data-category/data-price trên card).
 */

// Danh mục & kiểu sản phẩm cho filter sidebar — suy ra từ $products (không
// hard-code danh sách), chỉ map slug -> nhãn hiển thị; slug lạ thì tự sinh nhãn.
$catLabels = [
    'tron'    => 'Gọng Tròn',
    'vuong'   => 'Gọng Vuông',
    'aviator' => 'Aviator',
    'cat-eye' => 'Cat Eye',
    'rimless' => 'Không Viền',
    'sport'   => 'Thể Thao',
];

$kindLabels = [
    'kinh-ram'        => 'Kính Râm',
    'kinh-can'        => 'Kính Cận',
    'kinh-thoi-trang' => 'Kính Thời Trang',
];

$slugToLabel = static fn(string $slug): string => ucwords(str_replace('-', ' ', $slug));

$filter_categories = [];
$filter_kinds      = [];
foreach ($products as $p) {
    $cat  = $p['category'] ?? '';
    $kind = $p['type'] ?? '';
    if ($cat !== '' && !isset($filter_categories[$cat])) {
        $filter_categories[$cat] = $catLabels[$cat] ?? $slugToLabel($cat);
    }
    if ($kind !== '' && !isset($filter_kinds[$kind])) {
        $filter_kinds[$kind] = $kindLabels[$kind] ?? $slugToLabel($kind);
    }
}

$filter_type = 'product';
?>
<script src="/assets/js/product.js" defer></script>

<!-- ============================================================
     PAGE HEADER
     ============================================================ -->
<div class="page-header reveal">
    <div class="page-header-inner">
        <p class="page-eyebrow">Eyewear Collection</p>
        <h1 class="page-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Frames' ?></h1>
    </div>
</div>

<!-- ============================================================
     PRODUCT LISTING — filter sidebar (trái) + grid (phải)
     LƯU Ý: KHÔNG đặt .reveal lên section này — transform của .reveal
     biến section thành containing block, làm hỏng position:fixed
     của slide-over filter trên mobile. Chỉ reveal phần grid.
     ============================================================ -->
<section class="product-listing">
    <div class="listing-layout">

        <?php require VIEWS_PATH . '/_layout/filter-sidebar.php'; ?>

        <div class="listing-main reveal">
            <p class="listing-toolbar">
                Hiển thị <strong id="productCount"><?= count($products) ?></strong> sản phẩm
            </p>

            <div class="product-grid" id="productGrid">
                <?php foreach ($products as $card): require VIEWS_PATH . '/_layout/product-card.php'; endforeach; ?>
            </div>

            <p class="listing-empty" id="productEmpty" hidden>
                Không tìm thấy sản phẩm phù hợp. Hãy thử bỏ bớt điều kiện lọc.
            </p>
        </div>

    </div>
</section>

<!-- ============================================================
     PAGINATION — client-side: product.js render lại dải nút
     theo kết quả lọc (đổi filter về trang 1, <=1 trang thì tự ẩn).
     Markup PHP dưới chỉ là trạng thái ban đầu khi chưa có JS.
     ============================================================ -->
<?php $perPage = 8; // khop PAGE_SIZE trong assets/js/product.js ?>
<?php $totalPages = max(1, (int) ceil(count($products) / $perPage)); ?>
<nav class="pagination" id="productPagination" aria-label="Phan trang san pham"<?= $totalPages <= 1 ? ' hidden' : '' ?>>
    <span class="is-disabled">Prev</span>
    <span class="active" aria-current="page">1</span>
    <?php for ($i = 2; $i <= $totalPages; $i++): ?>
    <a href="#" data-page="<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($totalPages > 1): ?>
    <a href="#" data-page="2">Next</a>
    <?php endif; ?>
</nav>
