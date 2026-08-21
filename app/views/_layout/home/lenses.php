<?php

/**
 * _layout/home/lenses.php — cắt lắp tròng (S11).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": hai cột chạy thẳng trên nền trang.
 * Trái là nhãn · tiêu đề · ảnh phòng máy · một dải thẻ cam kết; phải là một
 * thẻ TRẮNG viền mảnh chứa bảng giá gói tròng và nút "Tư vấn chọn tròng"
 * chạy hết bề ngang thẻ.
 *
 * Bản trước bọc cả khối trong một hộp trắng bo 36px nổi trên nền. Thiết kế
 * này bỏ hộp đó: chỉ còn cột phải là thẻ trắng, cột trái đứng thẳng trên nền
 * pearl như mọi khối khác của trang.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * GIÁ Ở ĐÂY LÀ "TỪ X₫", KHÔNG PHẢI MỘT CON SỐ CHẮC CHẮN
 *
 * Một gói chiết suất không còn một giá: giá nằm ở giao điểm (kiểu tròng × gói)
 * trong bảng `lens_prices` — cùng phôi 1.61 thì đơn tròng và đa tròng chênh
 * nhau vài triệu. Khách đứng ở trang chủ chưa chọn kiểu tròng nào, nên không
 * có ô cụ thể nào để in.
 *
 * In giá THẤP NHẤT của gói kèm chữ "Từ" là câu đúng ở mọi trường hợp. Bỏ chữ
 * "Từ" đi và in một con số là hứa cái giá mà phần lớn khách sẽ không trả.
 *
 * Gói chưa có ô nào được định giá thì không in số nào — xem LensModel::priceFrom().
 * ─────────────────────────────────────────────────────────────────────────────
 */

$packages = LensModel::packages();

/*
 * Thẻ cam kết — lấy nguyên bốn chuỗi của bản thiết kế.
 *
 * LƯU Ý VỀ CON SỐ BẢO HÀNH. Bản thiết kế nói tới ba mốc khác nhau ở ba chỗ:
 * 90 ngày ở đây (bảo hành CẮT LẮP TRÒNG), 24 tháng ở dải cam kết dưới hero (lỗi
 * nhà sản xuất), còn config/policy.php thì nêu "trọn đời" cho dịch vụ chăm sóc
 * gọng. Ba thứ đó nói về ba việc khác nhau nên không đá nhau, NHƯNG chúng là cam
 * kết kinh doanh — sửa số ở đây thì phải khớp lại với trang chính sách.
 */
$facts = [
    'Đo mắt miễn phí',
    'Nhận kính sau 60–90 phút',
    'Essilor · Zeiss · Hoya · Chemi',
    'Bảo hành 90 ngày',
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
                            <?php $from = LensModel::priceFrom($p['id']); ?>
                            <p class="lpack__price">
                                <?php if ($from === null): ?>
                                    <span class="lpack__ask">Liên hệ</span>
                                <?php else: ?>
                                    <span class="lpack__from">Từ</span> <?= money($from) ?>
                                <?php endif; ?>
                            </p>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a class="hlens__cta" href="/dat-lich">Tư vấn chọn tròng</a>
            </div>
        <?php endif; ?>
    </div>
</section>
