<?php

/**
 * _layout/home/lens-spotlight.php
 * Port từ src/components/home/lens-spotlight.tsx.
 *
 * Hai cột: bốn công nghệ tròng bên trái, bảng giá gói tròng bên phải.
 */

$tech = [
    ['icon' => 'zap',      'title' => 'Chống ánh sáng xanh',      'desc' => 'Giảm mỏi mắt khi dùng máy tính, điện thoại trên 6 giờ mỗi ngày.'],
    ['icon' => 'sun',      'title' => 'Đổi màu Photochromic',     'desc' => 'Tự động sẫm màu ngoài trời, trở lại trong suốt khi vào bóng râm.'],
    ['icon' => 'scan-eye', 'title' => 'Chiết suất cao 1.61 – 1.74','desc' => 'Mỏng và nhẹ hơn tới 40%, hạn chế méo hình cho độ cao.'],
    ['icon' => 'glasses',  'title' => 'Đa tròng Progressive',     'desc' => 'Nhìn xa – trung – gần liền mạch, không đường phân cách.'],
];

$packages = config('taxonomy.lens_packages');
?>
<section class="lspot" aria-labelledby="lspot-title">
    <div class="lspot__inner">

        <div class="lspot__text">
            <p class="eyebrow">Công nghệ tròng kính</p>
            <h2 id="lspot-title" class="section-h2">Tròng kính chuyên biệt cho từng nhu cầu</h2>
            <p class="lspot__lead">
                Chọn gọng bạn thích, chúng tôi cắt tròng theo đúng đơn kính của bạn ngay tại
                cửa hàng — đo khúc xạ miễn phí trước khi lắp.
            </p>

            <div class="lspot__tech">
                <?php foreach ($tech as $t): ?>
                    <div class="ltech">
                        <?= icon($t['icon'], 'ltech__ico', 22) ?>
                        <div>
                            <h3 class="ltech__title"><?= e($t['title']) ?></h3>
                            <p class="ltech__desc"><?= e($t['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="lspot__packages">
            <h3 class="lpack__head">Gói tròng phổ biến</h3>
            <ul class="lpack" role="list">
                <?php foreach ($packages as $p): ?>
                    <li class="lpack__item">
                        <div>
                            <p class="lpack__name"><?= e($p['name']) ?></p>
                            <p class="lpack__desc"><?= e($p['desc']) ?></p>
                        </div>
                        <p class="lpack__price"><?= money($p['price']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="btn-primary btn-inline lpack__cta" href="/san-pham?category=trong-kinh">
                Xem tất cả tròng kính
            </a>
        </div>
    </div>
</section>
