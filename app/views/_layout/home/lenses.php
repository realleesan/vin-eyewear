<?php

/**
 * _layout/home/lenses.php — cắt lắp tròng (S11).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": một khối TRẮNG bo 36px nổi trên nền
 * trang, chia hai cột. Trái là nhãn · tiêu đề · ảnh phòng máy · một dải thẻ
 * cam kết dạng viên thuốc; phải là hộp nền chìm chứa bảng giá gói tròng và
 * nút "Tư vấn chọn tròng" chạy hết bề ngang hộp.
 *
 * Bản trước xếp 4 ô cam kết thành lưới 2×2 có viền. Thiết kế đổi sang dải
 * viên thuốc chảy theo hàng, nên bỏ hẳn lưới đó.
 *
 * Bảng giá đọc từ config('taxonomy.lens_packages'), cùng nguồn với bản cũ.
 */

$packages = config('taxonomy.lens_packages') ?? [];

/*
 * Thẻ cam kết. Bản thiết kế ghi thẻ cuối là "Bảo hành 90 ngày", nhưng
 * config/policy.php và cả thẻ mô tả của chính trang này đều cam kết BẢO HÀNH
 * TRỌN ĐỜI. Giữ nguyên chữ của thiết kế sẽ thành hai lời hứa đá nhau trên
 * cùng một trang, nên ở đây lấy theo chính sách thật.
 */
$facts = [
    'Đo mắt miễn phí',
    'Nhận kính sau 60–90 phút',
    'Essilor · Zeiss · Hoya · Chemi',
    'Bảo hành trọn đời',
];
?>

<section class="hlens" data-section="s11" aria-labelledby="hlens-title">
    <div class="hlens__inner">

        <div class="hlens__main">
            <p class="eyebrow">Tròng kính chính hãng</p>
            <h2 id="hlens-title" class="section-h2 section-h2--plain">
                Cắt lắp trong ngày, tròng chính hãng
            </h2>

            <figure class="hlens__figure">
                <?php /* Ô "lab-photo" của bản thiết kế — máy đo mắt / bảng thị lực */ ?>
                <img src="<?= designImage('lab-photo', 'assets/images/showroom-exam-room.jpg') ?>"
                     alt="Quầy cắt kính tại Vin Eyewear"
                     loading="lazy" decoding="async">
            </figure>

            <ul class="hlens__facts" role="list">
                <?php foreach ($facts as $fact): ?>
                    <li class="hlens__fact"><?= e($fact) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($packages !== []): ?>
            <div class="hlens__packages">
                <h3 class="hlens__ptitle">Gói tròng phổ biến</h3>

                <ul class="lpack" role="list">
                    <?php foreach ($packages as $p): ?>
                        <li class="lpack__item">
                            <div class="lpack__text">
                                <p class="lpack__name"><?= e($p['name']) ?></p>
                                <p class="lpack__desc"><?= e($p['desc']) ?></p>
                            </div>
                            <p class="lpack__price"><?= money($p['price']) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a class="hlens__cta" href="/dat-lich">Tư vấn chọn tròng</a>
            </div>
        <?php endif; ?>
    </div>
</section>
