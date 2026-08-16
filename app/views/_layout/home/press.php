<?php

/**
 * _layout/home/press.php
 * Port từ src/components/home/press-section.tsx.
 *
 * Bản React dùng framer-motion để hiện dần từng thẻ. Ở đây dùng class
 * .reveal cùng biến --d (độ trễ) — cùng cơ chế IntersectionObserver dùng
 * chung ở master.php, không kéo thêm thư viện animation nào.
 */

$press = config('company.press');
?>
<section class="press" aria-labelledby="press-title">
    <div class="press__inner">
        <p class="eyebrow">Truyền thông</p>
        <h2 id="press-title" class="section-h2">Báo chí nói về Vin Eyewear</h2>

        <div class="press__grid">
            <?php foreach ($press as $i => $item): ?>
                <a class="press-card reveal"
                   style="--d: <?= $i * 80 ?>ms"
                   href="<?= e($item['url']) ?>"
                   target="_blank" rel="noreferrer noopener">

                    <span class="press-card__outlet">
                        <?= icon('newspaper', '', 16) ?>
                        <span><?= e($item['outlet']) ?></span>
                    </span>

                    <span class="press-card__topic"><?= e($item['topic']) ?></span>

                    <?= icon('quote', 'press-card__quote', 20) ?>

                    <p class="press-card__text">&ldquo;<?= e($item['quote']) ?>&rdquo;</p>

                    <span class="press-card__more">Đọc bài viết →</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
