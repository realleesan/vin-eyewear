<?php
/**
 * _layout/breadcrumb.php
 * Component Breadcrumb dùng chung — chuẩn design theo breadcrumb trang
 * Product Detail (trước đây là .pd-breadcrumb inline trong product/detail.php).
 *
 * Biến nhận từ view cha (tất cả đều có mặc định an toàn):
 *   $show_breadcrumb  — bool, bật/tắt toàn bộ component (mặc định true)
 *   $breadcrumb_items — mảng các mục theo thứ tự cha -> con, mỗi mục:
 *                         ['label' => 'Trang chủ', 'url' => '/']
 *                       Mục KHÔNG có 'url' (thường là mục cuối) = trang hiện tại,
 *                       render <span class="current">, KHÔNG tự link chính nó.
 *                       Mảng rỗng = không render gì (an toàn khi thiếu data).
 *
 * Cách dùng:
 *   <?php
 *   $breadcrumb_items = [
 *       ['label' => 'Trang chủ', 'url' => '/'],
 *       ['label' => 'Sản phẩm', 'url' => '/product'],
 *       ['label' => $product['name']],   // trang hien tai — khong co url
 *   ];
 *   require VIEWS_PATH . '/_layout/breadcrumb.php';
 *   ?>
 */

$show_breadcrumb  = $show_breadcrumb  ?? true;
$breadcrumb_items = $breadcrumb_items ?? [];

if (!$show_breadcrumb || empty($breadcrumb_items)) {
    return;
}
?>
<link rel="stylesheet" href="/assets/css/components/breadcrumb.css">

<nav class="breadcrumb" aria-label="Breadcrumb">
    <?php $first = true; ?>
    <?php foreach ($breadcrumb_items as $item): ?>
        <?php if (!$first): ?><span aria-hidden="true">/</span><?php endif; $first = false; ?>
        <?php if (!empty($item['url'])): ?>
        <a href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label'] ?? '') ?></a>
        <?php else: ?>
        <span class="current"><?= htmlspecialchars($item['label'] ?? '') ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
