<?php

/**
 * _layout/home/cta.php — kêu gọi hành động cuối trang (S14b).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": chia đôi màn hình — nửa trái nền
 * crimson chứa chữ và hai nút, nửa phải là ảnh cửa hàng tràn cạnh.
 *
 * Bản trước là một khối nền tối căn giữa. Kiểu chia đôi này khiến khối cuối
 * trang không lặp lại dáng của khối "đo mắt" ngay phía trên nó.
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
