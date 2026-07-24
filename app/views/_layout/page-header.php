<?php
/**
 * _layout/page-header.php
 * Component Page Header dùng chung — chuẩn design theo trang Product (benchmark).
 *
 * Biến nhận từ view cha (tất cả đều có mặc định an toàn):
 *   $show_page_header — bool, bật/tắt toàn bộ component (mặc định true)
 *   $page_eyebrow     — dòng mono nhỏ phía trên tiêu đề (rỗng = không render)
 *   $page_title       — tiêu đề H1 (mặc định lấy $pageTitle từ controller)
 *   $page_subtitle    — mô tả dưới tiêu đề (rỗng = không render)
 *
 * Cách dùng:
 *   <?php
 *   $page_eyebrow  = 'Eyewear Collection';
 *   $page_subtitle = '...';
 *   require VIEWS_PATH . '/_layout/page-header.php';
 *   ?>
 */

$show_page_header = $show_page_header ?? true;
$page_eyebrow     = $page_eyebrow     ?? '';
$page_title       = $page_title       ?? ($pageTitle ?? '');
$page_subtitle    = $page_subtitle    ?? '';

if (!$show_page_header) {
    return;
}
?>
<link rel="stylesheet" href="/assets/css/components/page-header.css">

<div class="page-header reveal">
    <div class="page-header-inner">
        <?php if ($page_eyebrow !== ''): ?>
        <p class="page-eyebrow"><?= htmlspecialchars($page_eyebrow) ?></p>
        <?php endif; ?>
        <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
        <?php if ($page_subtitle !== ''): ?>
        <p class="page-subtitle"><?= htmlspecialchars($page_subtitle) ?></p>
        <?php endif; ?>
    </div>
</div>
