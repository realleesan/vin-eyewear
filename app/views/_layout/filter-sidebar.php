<?php
/**
 * _layout/filter-sidebar.php
 * Component Filter Sidebar dùng chung — phần "Vỏ": khung UI + toggle slide-over mobile.
 *
 * Biến nhận từ view cha (tất cả đều có mặc định an toàn):
 *   $show_filter_sidebar — bool, bật/tắt toàn bộ component (mặc định true)
 *   $filter_type         — 'product' | 'category' | 'event' (mặc định 'product'),
 *                          quyết định nhóm input nào được render
 *   $filter_categories   — [slug => label] dáng gọng cho trang product,
 *                          view cha suy ra từ dữ liệu backend, KHÔNG hard-code ở đây
 *   $filter_kinds        — [slug => label] kiểu sản phẩm (kính râm/cận/thời trang),
 *                          view cha suy ra từ dữ liệu backend
 *   $filter_price_ranges — [range => label], key PHẢI khớp matchPrice()
 *                          trong assets/js/product.js
 *
 * Data attribute trên chip (product.js đọc):
 *   data-filter-kind  — kiểu sản phẩm (KHÔNG dùng data-filter-type,
 *                       attr đó đã là marker $filter_type trên <aside>)
 *   data-filter-cat   — dáng gọng
 *   data-filter-price — khoảng giá
 *
 * Cách dùng:
 *   <?php $filter_type = 'product'; require VIEWS_PATH . '/_layout/filter-sidebar.php'; ?>
 *
 * Phân định "Vỏ"/"Ruột": file này chỉ dựng khung + toggle. Data & logic lọc
 * của Category/Event do thành viên phụ trách tự đổ vào khối TODO tương ứng.
 * Logic lọc sản phẩm nằm ở assets/js/product.js (đọc chip [data-filter-cat]/[data-filter-price]).
 */

$show_filter_sidebar = $show_filter_sidebar ?? true;
$filter_type         = $filter_type         ?? 'product';
$filter_categories   = $filter_categories   ?? [];
$filter_kinds        = $filter_kinds        ?? [];

// Khoảng giá mặc định — key phải khớp matchPrice() trong assets/js/product.js
// (nhãn viết gọn "triệu" cho sidebar compact)
$filter_price_ranges = $filter_price_ranges ?? [
    'all'   => 'Tất cả',
    '0-1'   => 'Dưới 1 triệu',
    '1-1.5' => '1 – 1,5 triệu',
    '1.5-2' => '1,5 – 2 triệu',
    '2-3'   => '2 – 3 triệu',
    '3+'    => 'Trên 3 triệu',
];

if (!$show_filter_sidebar) {
    return;
}
?>
<link rel="stylesheet" href="/assets/css/components/filter-sidebar.css">
<script src="/assets/js/components/filter-sidebar.js" defer></script>

<!-- Nút mở filter — chỉ hiện trên mobile (≤900px, xem filter-sidebar.css) -->
<button
    type="button"
    class="filter-toggle"
    id="filterToggle"
    aria-expanded="false"
    aria-controls="filterSidebar"
>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4"/></svg>
    Bộ lọc
</button>

<!-- Overlay nền mờ khi slide-over mở (mobile) -->
<div class="filter-overlay" id="filterOverlay"></div>

<aside class="filter-sidebar" id="filterSidebar" data-filter-type="<?= htmlspecialchars($filter_type) ?>">

    <div class="filter-sidebar__head">
        <span class="filter-sidebar__title">Bộ lọc</span>
        <button type="button" class="filter-sidebar__close" id="filterClose" aria-label="Đóng bộ lọc">&times;</button>
    </div>

    <?php if ($filter_type === 'product'): ?>
    <?php if (!empty($filter_kinds)): ?>
    <!-- ── NHÓM: KIỂU SẢN PHẨM (data từ $filter_kinds, view cha truyền vào) ── -->
    <div class="filter-group">
        <h3 class="filter-group__title">Kiểu sản phẩm</h3>
        <div class="filter-group__options">
            <button type="button" class="filter-chip is-active" data-filter-kind="all" aria-pressed="true">Tất cả</button>
            <?php foreach ($filter_kinds as $slug => $label): ?>
            <button type="button" class="filter-chip" data-filter-kind="<?= htmlspecialchars($slug) ?>" aria-pressed="false">
                <?= htmlspecialchars($label) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── NHÓM: DÁNG GỌNG (data từ $filter_categories, view cha truyền vào) ── -->
    <div class="filter-group">
        <h3 class="filter-group__title">Dáng gọng</h3>
        <div class="filter-group__options">
            <button type="button" class="filter-chip is-active" data-filter-cat="all" aria-pressed="true">Tất cả</button>
            <?php foreach ($filter_categories as $slug => $label): ?>
            <button type="button" class="filter-chip" data-filter-cat="<?= htmlspecialchars($slug) ?>" aria-pressed="false">
                <?= htmlspecialchars($label) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── NHÓM: KHOẢNG GIÁ ── -->
    <div class="filter-group">
        <h3 class="filter-group__title">Khoảng giá</h3>
        <div class="filter-group__options">
            <?php foreach ($filter_price_ranges as $range => $label): ?>
            <button
                type="button"
                class="filter-chip<?= $range === 'all' ? ' is-active' : '' ?>"
                data-filter-price="<?= htmlspecialchars($range) ?>"
                aria-pressed="<?= $range === 'all' ? 'true' : 'false' ?>"
            ><?= htmlspecialchars($label) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($filter_type === 'category'): ?>
    <!-- ── "RUỘT" CATEGORY — thành viên phụ trách Category tự đổ nhóm input vào đây.
         Giữ đúng markup .filter-group / .filter-chip để ăn style + toggle sẵn có. ── -->
    <div class="filter-group">
        <h3 class="filter-group__title">Danh mục</h3>
        <div class="filter-group__options">
            <!-- TODO(Category): render chip từ dữ liệu backend, KHÔNG hard-code -->
        </div>
    </div>
    <?php endif; ?>

    <?php if ($filter_type === 'event'): ?>
    <!-- ── "RUỘT" EVENT — thành viên phụ trách Event tự đổ nhóm input vào đây.
         Giữ đúng markup .filter-group / .filter-chip để ăn style + toggle sẵn có. ── -->
    <div class="filter-group">
        <h3 class="filter-group__title">Sự kiện</h3>
        <div class="filter-group__options">
            <!-- TODO(Event): render chip từ dữ liệu backend, KHÔNG hard-code -->
        </div>
    </div>
    <?php endif; ?>

</aside>
