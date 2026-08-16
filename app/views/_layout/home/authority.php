<?php

/**
 * _layout/home/authority.php — dải số liệu (S15).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": 4 con số trên nền trang, căn giữa,
 * ngăn nhau bằng đường kẻ dọc mảnh.
 *
 * Bản trước là dải nền tối kèm băng chữ thương hiệu chạy ngang; băng chữ đã
 * thành lưới logo bấm được ở _layout/home/brands.php (S13).
 */

$stats = config('taxonomy.authority_stats') ?? [];
?>

<?php if ($stats !== []): ?>
<section class="hstats" data-section="s15" aria-labelledby="hstats-title">
    <h2 id="hstats-title" class="sr-only">Vin Eyewear qua các con số</h2>

    <dl class="hstats__inner">
        <?php foreach ($stats as $s): ?>
            <div class="hstats__item">
                <dt class="hstats__value"><?= e($s['value']) ?></dt>
                <dd class="hstats__label"><?= e($s['label']) ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
</section>
<?php endif; ?>
