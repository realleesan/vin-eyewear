<?php

/**
 * _layout/home/hero.php — hero trang chủ (S01).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": hai cột TRÀN CẠNH, không còn khối bo
 * góc nổi trên nền như bản trước.
 *
 *   trái  nhãn "Bộ sưu tập 2026" · tiêu đề serif hai màu · mô tả · hai nút
 *         · liên kết AR · dải ưu đãi kèm ĐỒNG HỒ ĐẾM NGƯỢC · bộ điều khiển
 *         băng ảnh (số thứ tự, hai mũi tên, ba vạch tiến độ)
 *   phải  băng ba ảnh trượt ngang, kèm thẻ chú thích nền sẫm ở mép trái dưới
 *   dưới  dải cam kết nền nâu sẫm chạy hết bề ngang
 *
 * BĂNG ẢNH TRỞ LẠI. Bản thiết kế trước là khối tĩnh nên phần JS trượt ảnh đã
 * bỏ; bản này có ba ảnh trượt ngang nên nó quay lại — nằm trong
 * assets/js/home.js, một file cho cả trang chủ.
 *
 * BĂNG TỰ CHẠY, và chạy nhanh (theo yêu cầu) — khác bản thiết kế, vốn đứng yên
 * chờ bấm mũi tên. Nhịp khai bằng data-autoplay ngay trên .hero__media bên
 * dưới. Băng dừng lại khi con trỏ hoặc tiêu điểm bàn phím ở trong hero: ảnh tự
 * trôi đi giữa lúc người ta đang nhìn là một cách gây bực.
 *
 * KHÔNG CÓ JS THÌ VẪN ĐỌC ĐƯỢC: ảnh đầu tiên hiện sẵn, hai mũi tên và ba vạch
 * chỉ là điều khiển phụ.
 *
 * DẢI ƯU ĐÃI CÓ ĐỒNG HỒ ĐẾM NGƯỢC ĐÃ BỎ (2026-08-26) cùng với cả tính năng
 * sự kiện: nó lấy bài ưu đãi đang chạy từ EventModel::currentPromo() rồi trỏ
 * sang /su-kien/{slug}, mà cả hai thứ đó không còn. Muốn dựng lại một dải đếm
 * ngược cho khuyến mãi thì phải có nguồn dữ liệu riêng, không phải bảng bài viết.
 */

/*
 * Ba ảnh của băng. Ô "hero-photo · hero-slide-2 · hero-slide-3" trong bản
 * thiết kế; chưa tải ảnh thiết kế về thì dùng ảnh có sẵn trong repo.
 */
$slides = [
    [
        'image'   => designImage('hero-photo', 'assets/images/hero-models.jpg'),
        'alt'     => 'Khách hàng thử gọng kính tại Vin Eyewear',
        'caption' => 'Đo khúc xạ chuẩn phòng khám · Hà Nội',
    ],
    [
        'image'   => designImage('hero-slide-2', 'assets/images/showroom-frames.jpg'),
        'alt'     => 'Kệ trưng bày kính mát tại cửa hàng',
        'caption' => 'Bộ sưu tập kính mát 2026 · Polarized UV400',
    ],
    [
        'image'   => designImage('hero-slide-3', 'assets/images/hero-eyewear.jpg'),
        'alt'     => 'Gọng titan siêu nhẹ vừa lên kệ',
        'caption' => 'Gọng titan siêu nhẹ 9 gram · Vừa lên kệ',
    ],
];

/*
 * Dải cam kết dưới hero. Bốn mục, icon vẽ thẳng ở đây chứ không qua core/icons.php:
 * ba trong bốn hình (gọng kính, khiên, xe tải) không có trong bộ icon chung.
 */
$trust = [
    [
        'label' => 'Đo khúc xạ miễn phí',
        'path'  => '<circle cx="6.5" cy="12" r="4"/><circle cx="17.5" cy="12" r="4"/><path d="M10.5 12h3"/>',
    ],
    [
        'label' => 'Đổi trả trong 7 ngày',
        'path'  => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>',
    ],
    [
        // Bản thiết kế ghi "Bảo hành 24 tháng". Trang chính sách thì nêu CẢ HAI
        // mốc: trọn đời cho dịch vụ chăm sóc (nắn gọng, thay ốc, vệ sinh) và 24
        // tháng cho lỗi nhà sản xuất — xem config/policy.php. Dải này lấy con số
        // của bản thiết kế; đổi sang "trọn đời" thì phải đổi cả bốn nhãn cho
        // cùng một giọng, không sửa lẻ một chỗ.
        'label' => 'Bảo hành 24 tháng',
        'path'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    ],
    [
        'label' => 'Giao nhanh toàn quốc',
        'path'  => '<path d="M1 3h13v13H1zM14 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="17.5" cy="18.5" r="2"/>',
    ],
];
?>

<section class="hero" data-section="s01" aria-labelledby="hero-title">
    <div class="hero__inner">

        <div class="hero__text">
            <p class="hero__eyebrow">
                <span class="hero__eyebrow-rule" aria-hidden="true"></span>
                Bộ sưu tập 2026
            </p>

            <h1 id="hero-title" class="hero__title">
                Nhìn rõ hơn,<br><em>tự tin hơn.</em>
            </h1>

            <p class="hero__lead">
                Gọng titanium &amp; acetate chính hãng, đo khúc xạ miễn phí cùng
                chuyên viên trước khi bạn chốt đơn.
            </p>

            <div class="hero__actions">
                <a class="hero__btn hero__btn--solid" href="/san-pham">Khám Phá Bộ Sưu Tập</a>
                <a class="hero__btn hero__btn--ghost" href="/dat-lich">Đặt Lịch Đo Mắt Miễn Phí</a>
            </div>

            <?php /* Cùng cờ với thanh điều hướng — xem ghi chú đầu config/ar.php.
                     Tính năng còn tắt thì không mời người ta bấm vào. */ ?>
            <?php if (config('ar.nav_enabled')): ?>
                <a class="hero__ar" href="/thu-ar">Hoặc thử kính ảo bằng camera (AR) →</a>
            <?php endif; ?>

            <?php /* Bộ điều khiển băng ảnh. Ẩn khi chỉ có một ảnh — hai mũi tên
                     không làm gì là một lời hứa suông. */ ?>
            <?php if (count($slides) > 1): ?>
                <div class="hero__nav">
                    <p class="hero__counter">
                        <span data-hero-index>01</span> / <?= str_pad((string) count($slides), 2, '0', STR_PAD_LEFT) ?>
                    </p>

                    <div class="hero__arrows">
                        <button type="button" class="hero__arrow" data-hero="prev" aria-label="Ảnh trước">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M19 12H5M11 6l-6 6 6 6"/>
                            </svg>
                        </button>
                        <button type="button" class="hero__arrow" data-hero="next" aria-label="Ảnh sau">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="M5 12h14M13 6l6 6-6 6"/>
                            </svg>
                        </button>
                    </div>

                    <div class="hero__bars" aria-hidden="true">
                        <?php foreach ($slides as $i => $slide): ?>
                            <span class="hero__bar<?= $i === 0 ? ' is-on' : '' ?>"></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php /* data-autoplay: số mili-giây giữa hai lần tự đổi ảnh. Có thuộc
                 tính này thì assets/js/home.js bật chế độ tự chạy; bỏ đi là băng
                 chỉ đổi khi bấm mũi tên (đúng bản thiết kế — nó KHÔNG tự chạy).
                 Cùng quy ước với data-autoplay của khối đánh giá.

                 2000ms là CỐ Ý NHANH theo yêu cầu. Trừ 0.55s chuyển động ở
                 .hero__track thì mỗi ảnh đứng yên khoảng 1.45 giây. Muốn chậm
                 lại thì tăng đúng con số này, không cần sửa JavaScript.

                 Máy đặt "giảm chuyển động" (prefers-reduced-motion) thì home.js
                 KHÔNG bật tự chạy, dù có thuộc tính này.

                 BẤM VÀO ẢNH LÀ SANG ẢNH SAU: home.js gắn thẳng vào thẻ này, ở
                 đây không cần thuộc tính nào. Nó cũng tự gắn lớp .is-clickable
                 để đổi con trỏ, nên đừng in sẵn lớp đó trong view. */ ?>
        <?php /* aria-live=off: chú thích đổi theo ảnh, đọc lại mỗi lần đổi chỉ
                 làm phiền — nhất là khi băng tự chạy. */ ?>
        <div class="hero__media" data-hero-slider data-autoplay="2000">
            <div class="hero__track">
                <?php foreach ($slides as $i => $slide): ?>
                    <figure class="hero__slide" data-caption="<?= e($slide['caption']) ?>">
                        <?php /* fetchpriority=high cho tấm đầu: đây là Largest
                                 Contentful Paint của trang chủ. */ ?>
                        <img src="<?= e($slide['image']) ?>" alt="<?= e($slide['alt']) ?>"
                             <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                    </figure>
                <?php endforeach; ?>
            </div>

            <p class="hero__caption" data-hero-caption><?= e($slides[0]['caption']) ?></p>
        </div>
    </div>

    <ul class="hero__trust" role="list">
        <?php foreach ($trust as $i => $item): ?>
            <?php if ($i > 0): ?>
                <li class="hero__trust-sep" aria-hidden="true"></li>
            <?php endif; ?>
            <li class="hero__trust-item">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?= $item['path'] ?></svg>
                <?= e($item['label']) ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
