<?php

/**
 * _layout/home/trust-bar.php
 * Port từ src/components/home/trust-bar.tsx.
 *
 * Dải 4 cam kết dịch vụ, đặt ngay dưới hero trang chủ.
 */

$items = [
    ['icon' => 'truck',    'title' => 'Giao hàng toàn quốc',           'desc' => 'Miễn phí đơn từ 1.000.000đ'],
    ['icon' => 'eye',      'title' => 'Đo khám mắt miễn phí',          'desc' => 'Máy đo khúc xạ tự động'],
    ['icon' => 'shield',   'title' => 'Bảo hành 24 tháng',             'desc' => 'Chính hãng, đổi trả 7 ngày'],
    ['icon' => 'sparkles', 'title' => 'Vệ sinh & nắn chỉnh trọn đời',  'desc' => 'Miễn phí tại cả 2 cơ sở'],
];
?>
<section class="trust-bar" aria-label="Cam kết dịch vụ">
    <ul class="trust-bar__list" role="list">
        <?php foreach ($items as $item): ?>
            <li class="trust-item">
                <?= icon($item['icon'], 'trust-item__ico', 16) ?>
                <div class="trust-item__body">
                    <p class="trust-item__title"><?= e($item['title']) ?></p>
                    <p class="trust-item__desc"><?= e($item['desc']) ?></p>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
