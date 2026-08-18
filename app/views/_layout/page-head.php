<?php

/**
 * _layout/page-head.php — đầu trang, BA PHẦN RỜI NHAU.
 *
 * Dựng theo "Vin Eyewear Category.dc.html" và "Vin Eyewear Contact.dc.html":
 * hai bản thiết kế vẽ khối này GIỐNG HỆT NHAU tới từng con số (khối bo 36px
 * nền #f2e4dc, lề 8px/32px, đệm 56px 72px 60px, breadcrumb rồi tiêu đề 46px
 * bên trái với đoạn mô tả căn đáy bên phải) — chỉ khác chữ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG PHẢI MỘT KHỐI BẬT/TẮT NGUYÊN CỤC
 *
 * Ba phần dưới đây độc lập, mỗi trang lấy đúng phần mình cần; thiếu tham số
 * nào thì phần đó KHÔNG in ra (không để lại khoảng trắng thừa):
 *
 *   $head_crumbs — mảng [['label' => …, 'url' => …], …], các bậc SAU "Trang
 *                  chủ". Bậc cuối thường không có 'url' — nó là trang hiện tại.
 *                  Cần khi trang nằm sâu trong cây điều hướng và người dùng có
 *                  thể vào thẳng từ Google: nó vừa định vị vừa có giá trị SEO.
 *
 *   $head_title  — tiêu đề <h1>. CHỈ truyền khi thân trang chưa tự có <h1>.
 *                  Trang chi tiết sản phẩm đã lấy tên sản phẩm làm <h1> nên nó
 *                  chỉ truyền breadcrumb — thêm dải tiêu đề nữa là nói hai lần
 *                  và đẩy ảnh sản phẩm xuống dưới màn hình đầu tiên.
 *
 *   $head_lead   — đoạn mô tả bên phải. Chỉ truyền khi THẬT SỰ có gì để nói.
 *                  Một câu viết cho có chỉ tốn màn hình.
 *
 *   $head_badge  — nhãn tròn nhỏ trên tiêu đề (bản bài viết).
 *
 * Không có tiêu đề lẫn mô tả thì khối nền hồng cũng không còn lý do tồn tại:
 * component tự bỏ nền, chỉ để lại hàng breadcrumb (`.pagehead--bare`).
 * ─────────────────────────────────────────────────────────────────────────────
 */

$head_crumbs = $head_crumbs ?? [];
$head_title  = $head_title  ?? null;
$head_lead   = $head_lead   ?? null;
$head_badge  = $head_badge  ?? null;

$hasTitle = $head_title !== null && $head_title !== '';
$hasLead  = $head_lead  !== null && $head_lead  !== '';
$hasBadge = $head_badge !== null && $head_badge !== '';

/* Gọi mà không truyền gì cả gần như luôn là nhầm — im lặng in ra một khối
   rỗng thì rất khó lần ra, nên thoát hẳn. */
if ($head_crumbs === [] && !$hasTitle && !$hasLead) {
    return;
}

$classes = 'pagehead';
if ($hasBadge) {
    $classes .= ' pagehead--article';
}
if (!$hasTitle && !$hasLead) {
    $classes .= ' pagehead--bare';
}
?>

<section class="<?= $classes ?>">
    <div class="pagehead__inner">

        <?php if ($head_crumbs !== []): ?>
            <nav class="pagehead__crumbs" aria-label="Đường dẫn">
                <a href="/"><?= e(t('nav.home')) ?></a>
                <?php foreach ($head_crumbs as $crumb): ?>
                    <span class="pagehead__sep" aria-hidden="true">/</span>
                    <?php if (!empty($crumb['url'])): ?>
                        <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                    <?php else: ?>
                        <span class="pagehead__here" aria-current="page"><?= e($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <?php if ($hasTitle || $hasLead): ?>
            <div class="pagehead__row">
                <?php if ($hasTitle): ?>
                    <div class="pagehead__main">
                        <?php if ($hasBadge): ?>
                            <span class="pagehead__badge"><?= e($head_badge) ?></span>
                        <?php endif; ?>
                        <h1 class="pagehead__title"><?= e($head_title) ?></h1>
                    </div>
                <?php endif; ?>

                <?php if ($hasLead): ?>
                    <p class="pagehead__lead"><?= e($head_lead) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
