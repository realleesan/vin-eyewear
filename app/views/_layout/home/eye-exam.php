<?php

/**
 * _layout/home/eye-exam.php — dịch vụ đo mắt (S14).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": dải nền nâu sẫm CHẠY HẾT BỀ NGANG
 * trang, chia hai cột. Trái là ảnh cửa hàng bo 6px; phải là nhãn · tiêu đề
 * · 5 bước quy trình (số thứ tự — tên bước — ghi chú căn phải, ngăn nhau bằng
 * đường kẻ mờ) · nút "Đặt lịch đo mắt" viền mảnh.
 *
 * Bản trước là một khối bo 36px có lề hai bên. Thiết kế này cho dải tối tràn
 * sát mép màn hình, cùng cách với dải cam kết dưới hero và khối CTA cuối trang.
 */

$steps = [
    ['n' => '01', 'name' => t('home.exam.s1'), 'note' => t('home.exam.s1n')],
    ['n' => '02', 'name' => t('home.exam.s2'), 'note' => t('home.exam.s2n')],
    ['n' => '03', 'name' => t('home.exam.s3'), 'note' => t('home.exam.s3n')],
    ['n' => '04', 'name' => t('home.exam.s4'), 'note' => t('home.exam.s4n')],
    ['n' => '05', 'name' => t('home.exam.s5'), 'note' => t('home.exam.s5n')],
];
?>

<section class="hexam" data-section="s14" aria-labelledby="hexam-title">
    <div class="hexam__inner">

        <figure class="hexam__figure">
            <?php /* Ô "store-photo" của bản thiết kế — không gian cửa hàng */ ?>
            <img src="<?= designImage('store-photo', 'assets/images/showroom-frames.jpg') ?>"
                 alt="<?= e(t('home.exam.alt')) ?>"
                 width="1600" height="900"
                 loading="lazy" decoding="async">
        </figure>

        <div class="hexam__text">
            <p class="eyebrow hexam__eyebrow"><?= e(t('home.exam.eyebrow')) ?></p>
            <h2 id="hexam-title" class="section-h2 section-h2--plain hexam__title"><?= e(t('home.exam.title')) ?></h2>

            <ol class="hexam__steps">
                <?php foreach ($steps as $step): ?>
                    <li>
                        <span class="hexam__num"><?= e($step['n']) ?></span>
                        <span class="hexam__name"><?= e($step['name']) ?></span>
                        <span class="hexam__note"><?= e($step['note']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <a class="hexam__cta" href="/dat-lich"><?= e(t('cta.book')) ?></a>
        </div>
    </div>
</section>
