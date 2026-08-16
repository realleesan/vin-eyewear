<?php

/**
 * _layout/home/eye-exam.php — dịch vụ đo mắt (S14).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": khối nền nâu sẫm bo 36px, chia hai
 * cột. Trái là ảnh cửa hàng bo hình vòm; phải là nhãn · tiêu đề · 5 bước quy
 * trình (số thứ tự — tên bước — ghi chú căn phải, ngăn nhau bằng đường kẻ mờ)
 * · nút "Đặt lịch đo mắt" viền mảnh.
 */

$steps = [
    ['n' => '01', 'name' => 'Tiếp nhận',            'note' => '2 phút'],
    ['n' => '02', 'name' => 'Đo khúc xạ tự động',   'note' => 'Thiết bị chuẩn phòng khám'],
    ['n' => '03', 'name' => 'Thử tròng',            'note' => 'Điều chỉnh theo độ thực tế'],
    ['n' => '04', 'name' => 'Tư vấn tròng kính',    'note' => 'Theo độ và nhu cầu'],
    ['n' => '05', 'name' => 'Lắp ráp, chỉnh gọng',  'note' => '60–90 phút'],
];
?>

<section class="hexam" data-section="s14" aria-labelledby="hexam-title">
    <div class="hexam__inner">

        <figure class="hexam__figure">
            <?php /* Ô "store-photo" của bản thiết kế — không gian cửa hàng */ ?>
            <img src="<?= designImage('store-photo', 'assets/images/showroom-frames.jpg') ?>"
                 alt="Không gian cửa hàng Vin Eyewear"
                 loading="lazy" decoding="async">
        </figure>

        <div class="hexam__text">
            <p class="eyebrow hexam__eyebrow">Dịch vụ tại cửa hàng</p>
            <h2 id="hexam-title" class="section-h2 section-h2--plain hexam__title">
                Đo mắt chuẩn phòng khám, miễn phí
            </h2>

            <ol class="hexam__steps">
                <?php foreach ($steps as $step): ?>
                    <li>
                        <span class="hexam__num"><?= e($step['n']) ?></span>
                        <span class="hexam__name"><?= e($step['name']) ?></span>
                        <span class="hexam__note"><?= e($step['note']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <a class="hexam__cta" href="/dat-lich">Đặt lịch đo mắt</a>
        </div>
    </div>
</section>
