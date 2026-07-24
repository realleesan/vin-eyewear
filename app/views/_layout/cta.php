<?php
/**
 * _layout/cta.php
 * Component CTA dùng chung — chuẩn design theo khối CTA trang Product Detail
 * (trước đây là .pd-actions, .pd-btn-primary/ghost, .pd-note inline trong
 * product/detail.php): cặp nút primary (đen, hover vàng) + ghost (viền,
 * hover đen) và dòng note mono.
 *
 * Biến nhận từ view cha (tất cả đều có mặc định an toàn):
 *   $show_cta    — bool, bật/tắt toàn bộ component (mặc định true)
 *   $cta_buttons — mảng nút, mỗi nút:
 *                    ['label' => 'Thử AR', 'url' => '/ar', 'style' => 'primary'|'ghost']
 *                  Mặc định = cặp nút benchmark của trang Product (Thử AR + Liên hệ).
 *   $cta_note    — dòng ghi chú mono nhỏ dưới nút (rỗng = không render)
 *
 * Cách dùng:
 *   <?php
 *   $cta_buttons = [
 *       ['label' => 'Thử AR', 'url' => '/ar', 'style' => 'primary'],
 *       ['label' => 'Liên hệ tư vấn', 'url' => '/contact', 'style' => 'ghost'],
 *   ];
 *   $cta_note = '...';
 *   require VIEWS_PATH . '/_layout/cta.php';
 *   ?>
 */

$show_cta = $show_cta ?? true;
$cta_note = $cta_note ?? '';

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

<div class="cta-actions">
    <?php foreach ($cta_buttons as $btn): ?>
    <a
        href="<?= htmlspecialchars($btn['url'] ?? '/') ?>"
        class="<?= ($btn['style'] ?? 'primary') === 'ghost' ? 'cta-btn-ghost' : 'cta-btn-primary' ?>"
    ><?= htmlspecialchars($btn['label'] ?? '') ?></a>
    <?php endforeach; ?>
</div>
<?php if ($cta_note !== ''): ?>
<p class="cta-note"><?= htmlspecialchars($cta_note) ?></p>
<?php endif; ?>
