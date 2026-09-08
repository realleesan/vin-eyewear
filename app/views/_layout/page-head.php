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

<?php
/*
 * ─────────────────────────────────────────────────────────────────────────────
 * DỰNG THEO ĐẦU TRANG CON CỦA FURNISH
 *
 * `about.html` và `contact.html` mở đầu bằng ĐÚNG một khối, giống hệt nhau tới
 * từng lớp:
 *
 *     <section class="py-lg-8 py-5 text-center">
 *       <div class="container"><div class="row justify-content-center">
 *         <div class="col-lg-6">
 *           <h1 class="display-5 mb-3">…</h1>
 *           <p class="text-muted lead">…</p>
 *
 * Ba đặc điểm, và cả ba đều là chủ ý:
 *   · CĂN GIỮA — không phải căn trái như bản cũ
 *   · CỘT HẸP (6/12) — câu dẫn xuống dòng sớm, tạo hình khối gọn ở giữa trang
 *   · KHÔNG có nền màu, không viền, không thẻ — chỉ chữ trên nền trắng, tách
 *     khỏi nội dung bên dưới bằng khoảng trống
 *
 * ĐƯỜNG DẪN (breadcrumb) thì Furnish KHÔNG có, vì nó chỉ có năm trang phẳng.
 * Site này có /san-pham/{slug} và /bo-suu-tap/{slug} nằm sâu hai tầng, nên bỏ
 * breadcrumb là bỏ một lối quay ra. Giữ lại, và dựng bằng chính ngôn ngữ của
 * theme: chữ rất nhỏ, IN HOA, màu phụ, nằm CĂN GIỮA phía trên tiêu đề.
 * ─────────────────────────────────────────────────────────────────────────────
 */
?>
<section class="<?= $classes ?>">
    <div class="pagehead__inner">

        <?php if ($head_crumbs !== []): ?>
            <nav class="pagehead__crumbs" aria-label="<?= e(t('co.crumbs')) ?>">
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
                <?php if ($hasBadge): ?>
                    <span class="pagehead__badge"><?= e($head_badge) ?></span>
                <?php endif; ?>

                <?php if ($hasTitle): ?>
                    <h1 class="pagehead__title"><?= e($head_title) ?></h1>
                <?php endif; ?>

                <?php if ($hasLead): ?>
                    <p class="pagehead__lead"><?= e($head_lead) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
