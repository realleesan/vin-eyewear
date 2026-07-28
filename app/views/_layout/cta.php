<?php
/**
 * _layout/cta.php
 * Component CTA dùng chung — dải nền đen full-width ngay trên footer
 * (thế chỗ dải đen mà section .join cũ để lại, giữ nhịp đen -> vàng).
 * Trước đây component chỉ render 2 nút trơ giữa nền trang; nay là một
 * section có eyebrow + tiêu đề serif + mô tả bên trái, nút bên phải.
 *
 * Biến nhận từ view cha (tất cả đều có mặc định an toàn):
 *   $show_cta     — bool, bật/tắt toàn bộ component (mặc định true)
 *   $cta_eyebrow  — dòng mono nhỏ trên tiêu đề (rỗng = không render)
 *   $cta_title    — tiêu đề H2 của khối (rỗng = không render)
 *   $cta_desc     — mô tả dưới tiêu đề (rỗng = không render)
 *   $cta_buttons  — mảng nút, mỗi nút:
 *                     ['label' => 'Thử AR', 'url' => '/ar', 'style' => 'primary'|'ghost']
 *   $cta_note     — dòng ghi chú mono nhỏ dưới nút (rỗng = không render)
 *
 * Cách dùng:
 *   <?php
 *   $show_cta    = true;
 *   $cta_title   = 'Thử kính trước khi mua';
 *   $cta_desc    = '...';
 *   $cta_buttons = [
 *       ['label' => 'Thử AR', 'url' => '/ar', 'style' => 'primary'],
 *       ['label' => 'Liên hệ tư vấn', 'url' => '/contact', 'style' => 'ghost'],
 *   ];
 *   ?>
 */

$show_cta    = $show_cta    ?? true;
$cta_eyebrow = $cta_eyebrow ?? 'Vin Eyewear · AR Fitting';
$cta_title   = $cta_title   ?? 'Thử kính trước khi mua';
$cta_desc    = $cta_desc    ?? 'Bật camera, đeo thử mọi gọng kính ngay tại nhà. Ưng mắt rồi hãy tới cửa hàng.';
$cta_note    = $cta_note    ?? '';

// Mặc định = CTA benchmark trang Product; route thật, không dùng '#'
$cta_buttons = $cta_buttons ?? [
    ['label' => 'Thử AR', 'url' => '/ar', 'style' => 'primary'],
    ['label' => 'Liên hệ tư vấn', 'url' => '/contact', 'style' => 'ghost'],
];

if (!$show_cta || empty($cta_buttons)) {
    return;
}
?>
<link rel="stylesheet" href="/assets/css/components/cta.css">

<section class="cta-band">
    <div class="cta-band__inner">

        <div class="cta-band__text">
            <?php if ($cta_eyebrow !== ''): ?>
            <p class="cta-eyebrow"><?= htmlspecialchars($cta_eyebrow) ?></p>
            <?php endif; ?>
            <?php if ($cta_title !== ''): ?>
            <h2 class="cta-title"><?= htmlspecialchars($cta_title) ?></h2>
            <?php endif; ?>
            <?php if ($cta_desc !== ''): ?>
            <p class="cta-desc"><?= htmlspecialchars($cta_desc) ?></p>
            <?php endif; ?>
        </div>

        <div class="cta-band__actions">
            <div class="cta-actions">
                <?php foreach ($cta_buttons as $btn): ?>
                <a
                    href="<?= htmlspecialchars($btn['url'] ?? '/') ?>"
                    class="cta-btn <?= ($btn['style'] ?? 'primary') === 'ghost' ? 'cta-btn--ghost' : 'cta-btn--primary' ?>"
                ><?= htmlspecialchars($btn['label'] ?? '') ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($cta_note !== ''): ?>
            <p class="cta-note"><?= htmlspecialchars($cta_note) ?></p>
            <?php endif; ?>
        </div>

    </div>
</section>
