<?php

/**
 * _layout/home/cta.php — kêu gọi hành động cuối trang (S14b).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": chia đôi màn hình, tràn sát hai mép —
 * nửa trái nền nâu sẫm chứa chữ và hai nút, nửa phải là ảnh cửa hàng.
 *
 * Nền NÂU SẪM chứ không phải crimson như bản trước: bản thiết kế dùng đúng
 * một sắc tối (#3b1219) cho cả ba dải tối của trang — cam kết dưới hero, đo
 * mắt và khối này.
 */
?>

<section class="hcta" data-section="s14b" aria-labelledby="hcta-title">
    <div class="hcta__text">
        <h2 id="hcta-title" class="hcta__title">Sẵn sàng tìm chiếc kính của bạn?</h2>
        <p class="hcta__lead">
            Mua online giao tận nơi, hoặc ghé cửa hàng để được đo mắt và thử kính trực tiếp.
        </p>
        <div class="hcta__actions">
            <a class="hcta__btn hcta__btn--solid" href="/san-pham">Mua ngay</a>
            <a class="hcta__btn hcta__btn--ghost" href="/lien-he">Tìm cửa hàng</a>
        </div>
    </div>

    <div class="hcta__media">
        <?php /* Ô "cta-photo" của bản thiết kế — kệ trưng bày kính */ ?>
        <img src="<?= designImage('cta-photo', 'assets/images/store-interior.jpg') ?>"
             alt="Không gian cửa hàng Vin Eyewear"
             loading="lazy" decoding="async">
    </div>
</section>
