<?php

/**
 * _layout/home/lens-partners.php
 * Port từ src/components/home/lens-partners.tsx.
 *
 * Bốn công nghệ tròng kính của đối tác. Nút "Xem tròng kính" trỏ về
 * /san-pham?category=trong-kinh — khớp slug danh mục trong schema.sql.
 */

$lensTech = config('company.lens_tech');
?>
<section class="lens-partners" aria-labelledby="lens-title">
    <div class="lens-partners__inner">

        <div class="section-head">
            <div>
                <p class="eyebrow">Đối tác tròng kính chính hãng</p>
                <h2 id="lens-title" class="section-h2 section-h2--narrow">
                    Công nghệ tròng kính cao cấp tại Vin Eyewear
                </h2>
            </div>
            <a href="/san-pham?category=trong-kinh" class="btn-secondary">Xem tròng kính</a>
        </div>

        <div class="lens-partners__grid">
            <?php foreach ($lensTech as $i => $lens): ?>
                <article class="lens-card reveal" style="--d: <?= $i * 70 ?>ms">
                    <span class="lens-card__brand"><?= e($lens['brand']) ?></span>

                    <h3 class="lens-card__name">
                        <?= e($lens['name']) ?>
                        <?= icon('badge-check', 'lens-card__badge', 16) ?>
                    </h3>

                    <p class="lens-card__tagline"><?= e($lens['tagline']) ?></p>

                    <ul class="lens-card__specs" role="list">
                        <?php foreach ($lens['specs'] as $spec): ?>
                            <li>
                                <?= icon('check', 'lens-card__check', 16) ?>
                                <span><?= e($spec) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
