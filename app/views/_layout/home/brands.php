<?php

/**
 * _layout/home/brands.php — S13 lưới logo thương hiệu.
 *
 * Thay cho băng chữ chạy cũ ở authority.php: chữ chạy không bấm được, tức là
 * bỏ phí toàn bộ giá trị điều hướng của phần này — với kính thời trang thì
 * thương hiệu chính là cách khách chọn hàng.
 *
 * Hai nhóm tách riêng (gọng / tròng) đọc từ config/brands.php.
 *
 * Logo: có file thì hiện ảnh, chưa có thì hiện tên dạng chữ. Nhờ vậy khối này
 * dùng được ngay hôm nay, và ngày mai thả file SVG vào assets/images/brands/
 * là logo thật hiện lên mà không phải sửa view.
 */

$groups = [
    ['title' => 'Thương hiệu gọng & kính mát', 'items' => config('brands.frames')],
    ['title' => 'Thương hiệu tròng kính',      'items' => config('brands.lenses')],
];

/** Ô một thương hiệu: logo (hoặc chữ) + link tới trang lọc theo hãng. */
$brandTile = static function (array $brand): void {
    $url = '/san-pham?' . http_build_query(['brand' => $brand['name']]);

    // is_file() thay cho việc gõ cứng danh sách logo đã có: thêm/bớt file
    // trong thư mục là khối tự đổi, không ai phải nhớ sửa thêm chỗ nào.
    $logo = $brand['logo'] ?? '';
    $hasLogo = $logo !== '' && is_file(ROOT_PATH . '/' . ltrim($logo, '/'));
    ?>
    <li class="brand-tile">
        <a href="<?= e($url) ?>" aria-label="Xem sản phẩm <?= e($brand['name']) ?>">
            <?php if ($hasLogo): ?>
                <img class="brand-tile__logo" src="<?= e(asset($logo)) ?>"
                     alt="<?= e($brand['name']) ?>"
                     height="40" loading="lazy" decoding="async">
            <?php else: ?>
                <!-- Chưa có file logo -> tên hãng dạng chữ.
                     aria-hidden vì <a> đã mang aria-label đầy đủ; để cả hai
                     thì trình đọc màn hình đọc tên hãng hai lần. -->
                <span class="brand-tile__word" aria-hidden="true"><?= e($brand['name']) ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php
};
?>

<section class="hbrands" data-section="s13" aria-labelledby="hbrands-title">
    <div class="hbrands__inner">

        <div class="hbrands__head">
            <p class="eyebrow">Thương hiệu chính hãng</p>
            <h2 id="hbrands-title" class="section-h2">Những hãng chúng tôi phân phối</h2>
        </div>

        <?php foreach ($groups as $group): ?>
            <div class="hbrands__group">
                <p class="micro-label hbrands__gtitle"><?= e($group['title']) ?></p>
                <ul class="hbrands__grid" role="list">
                    <?php foreach ($group['items'] as $brand): ?>
                        <?php $brandTile($brand); ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</section>
