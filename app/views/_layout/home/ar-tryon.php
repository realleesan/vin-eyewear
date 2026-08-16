<?php

/**
 * _layout/home/ar-tryon.php — S07 thử kính AR.
 *
 * Đặt ngay sau lưới danh mục, trước lưới sản phẩm.
 *
 * Trước đây tính năng AR đã dựng xong nhưng cả trang chủ chỉ có đúng một nút
 * nhỏ trong hero — với một shop kính thời trang thì đây là lợi thế lớn nhất,
 * nên nó được một khối riêng nền tối chiếm trọn bề ngang.
 *
 * Tiêu đề nói LỢI ÍCH ("soi thử trước khi mua"), không nói công nghệ ("AR",
 * "FaceLandmarker") — khách quan tâm việc đeo lên trông thế nào, không quan
 * tâm thư viện nào vẽ ra nó.
 */

// Đếm từ DB: mẫu nào gắn với sản phẩm đã ẩn thì trang AR cũng bỏ, không kể
$arCount = ProductModel::countArReady();

/* Vài ảnh gọng PNG nền trong của chính trang AR, dùng làm hình minh hoạ —
   không thêm ảnh mới nào để trang chủ khỏi nặng thêm. */
$thumbs = array_slice(
    array_column(config('ar.frames') ?? [], 'image'),
    0,
    3
);
?>

<section class="hartry" data-section="s07" aria-labelledby="hartry-title">
    <div class="hartry__inner">

        <div class="hartry__text">
            <p class="eyebrow hartry__eyebrow">Thử trực tuyến</p>

            <h2 id="hartry-title" class="section-h2 hartry__title">
                Soi thử trước khi mua
            </h2>

            <p class="hartry__lead">
                Bật camera, gọng kính tự bám theo khuôn mặt bạn — nghiêng đầu, đổi mẫu,
                đổi màu và xem ngay mình hợp dáng nào trước khi tới cửa hàng.
            </p>

            <?php if ($arCount > 0): ?>
                <p class="hartry__count">
                    <strong><?= $arCount ?></strong> mẫu gọng đang thử được
                </p>
            <?php endif; ?>

            <div class="hartry__actions">
                <a href="/thu-ar" class="btn-primary btn-inline btn-lg">Thử kính ngay</a>
                <a href="/san-pham" class="btn-ghost btn-inline btn-lg">Xem tất cả gọng</a>
            </div>

            <p class="hartry__privacy">
                <?= icon('shield', 'hartry__ico', 14) ?>
                Hình ảnh xử lý ngay trên máy bạn, không gửi đi đâu và không lưu lại.
            </p>
        </div>

        <?php if ($thumbs !== []): ?>
            <!-- Trang trí: aria-hidden vì mấy ảnh gọng này không thêm thông tin
                 gì cho người dùng trình đọc màn hình — nội dung đã nằm ở cột chữ. -->
            <ul class="hartry__frames" role="list" aria-hidden="true">
                <?php foreach ($thumbs as $i => $src): ?>
                    <li style="--d: <?= $i * 90 ?>ms">
                        <img src="<?= e($src) ?>" alt=""
                             width="320" height="160" loading="lazy" decoding="async">
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
