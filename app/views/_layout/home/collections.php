<?php

/**
 * _layout/home/collections.php — S09 bộ sưu tập / lookbook.
 *
 * Với kính thời trang, đây là kênh bán chính chứ không phải khối trang trí:
 * khách mua theo "trông thế nào" trước khi mua theo thông số. Ba thẻ ảnh lớn,
 * mỗi thẻ dẫn thẳng sang trang lọc theo bộ sưu tập.
 *
 * Nội dung ở config/collections.php. Đường lọc dùng lại ProductController
 * (/san-pham?collection=<slug>) — không có controller hay model riêng.
 */

$collections = config('collections') ?? [];
?>

<?php if ($collections !== []): ?>
<section class="hcoll" data-section="s09" aria-labelledby="hcoll-title">
    <div class="hcoll__inner">

        <div class="section-head">
            <div>
                <p class="eyebrow">Bộ sưu tập</p>
                <h2 id="hcoll-title" class="section-h2">Chọn theo phong cách, không chỉ theo thông số</h2>
            </div>
            <a href="/san-pham" class="btn-secondary">Xem tất cả</a>
        </div>

        <ul class="hcoll__grid" role="list">
            <?php foreach ($collections as $i => $c): ?>
                <?php
                /* Ảnh thật chưa có thì dùng ảnh mẫu trong kho, để bố cục dựng
                   được ngay. Xem assets/images/collections/README.md. */
                $img = $c['image'] ?? '';
                if ($img === '' || !is_file(ROOT_PATH . '/' . ltrim($img, '/'))) {
                    $img = $c['image_sample'] ?? '';
                }

                $url = '/san-pham?' . http_build_query(['collection' => $c['slug']]);
                ?>
                <li class="ccard reveal" style="--d: <?= $i * 90 ?>ms">
                    <a class="ccard__link" href="<?= e($url) ?>">
                        <?php if ($img !== ''): ?>
                            <img class="ccard__img" src="<?= e(asset($img)) ?>" alt=""
                                 width="800" height="1000"
                                 <?= $i === 0 ? '' : 'loading="lazy"' ?> decoding="async">
                        <?php endif; ?>

                        <span class="ccard__body">
                            <span class="ccard__name"><?= e($c['name']) ?></span>
                            <span class="ccard__tagline"><?= e($c['tagline']) ?></span>
                            <span class="ccard__more">
                                Xem bộ sưu tập
                                <?= icon('arrow-right', '', 14) ?>
                            </span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
