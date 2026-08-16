<?php

/**
 * _layout/home/hero.php — hero trang chủ.
 *
 * Dựng theo "Vin Eyewear Home.dc.html": một khối bo góc lớn nền hồng phấn,
 * chia hai cột — chữ bên trái, ảnh chân dung tràn cạnh bên phải:
 *
 *   nhãn "Bộ sưu tập 2026" · tiêu đề serif hai màu · một dòng mô tả
 *   · hai nút · dải ba ảnh tròn chồng mép kèm ghi chú "10+ mẫu mới"
 *
 * KHÔNG CÒN CAROUSEL. Bản trước là băng ảnh tự trượt đọc từ config/banners.php
 * (kèm assets/js/hero.js); thiết kế này là một khối tĩnh nên toàn bộ phần đó
 * đã bỏ — không còn JS, không còn nút điều hướng, không còn trạng thái tự chạy.
 *
 * Ba ảnh tròn ở dải cuối là ẢNH TRANG TRÍ, cố ý gõ cứng chứ không lấy từ DB:
 * chúng minh hoạ cho câu "mẫu mới về", không phải lối vào sản phẩm cụ thể, nên
 * mang alt rỗng và không bọc liên kết.
 */

/*
 * Ba ảnh tròn: bản thiết kế trỏ thẳng vào uploads/1.jpg · 2.jpg · 3.jpg của
 * dự án Claude Design. designImage() tìm hero-thumb-1..3 trong
 * assets/images/home/, chưa có thì tạm dùng ảnh sản phẩm trong repo.
 */
$thumbs = [
    designImage('hero-thumb-1', 'assets/images/product-1.jpg'),
    designImage('hero-thumb-2', 'assets/images/product-2.jpg'),
    designImage('hero-thumb-3', 'assets/images/product-3.jpg'),
];
?>

<section class="hero" data-section="s01" aria-labelledby="hero-title">
    <div class="hero__inner">

        <div class="hero__text">
            <p class="hero__badge">Bộ sưu tập 2026</p>

            <h1 id="hero-title" class="hero__title">
                Kính đẹp là kính hợp với <em>chính bạn.</em>
            </h1>

            <p class="hero__lead">
                Gọng kính và kính mát tuyển chọn từ hơn 50 thương hiệu quốc tế.
                Đo mắt chuẩn phòng khám, cắt lắp trong ngày.
            </p>

            <div class="hero__actions">
                <a class="hero__btn hero__btn--solid" href="/san-pham">Mua ngay</a>
                <a class="hero__btn hero__btn--ghost" href="/dat-lich">Đặt lịch đo mắt</a>
            </div>

            <div class="hero__proof">
                <div class="hero__thumbs" aria-hidden="true">
                    <?php foreach ($thumbs as $thumb): ?>
                        <span class="hero__thumb">
                            <img src="<?= e($thumb) ?>" alt="" decoding="async">
                        </span>
                    <?php endforeach; ?>
                </div>

                <p class="hero__proof-text">
                    <strong>10+ mẫu mới</strong> vừa về trong bộ sưu tập mùa này
                </p>
            </div>
        </div>

        <?php /* fetchpriority=high: ảnh này là Largest Contentful Paint của
                 trang chủ, để trình duyệt tải trước mọi ảnh khác.

                 Ô "hero-photo" của bản thiết kế — chân dung đeo kính. Chưa tải
                 về thì dùng hero-models.jpg trong repo (hai người mẫu đeo kính,
                 nền be); tấm hero-eyewear.jpg cũ đánh đèn đỏ cam gắt, chọi hẳn
                 với nền hồng phấn của khối này nên không dùng làm dự phòng. */ ?>
        <div class="hero__media">
            <img src="<?= designImage('hero-photo', 'assets/images/hero-models.jpg') ?>" alt=""
                 fetchpriority="high" decoding="async">
        </div>
    </div>
</section>
