<?php

/**
 * _layout/home/style-guide.php — chọn theo khuôn mặt (S06).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": tiêu đề căn giữa, dưới là 3 thẻ —
 * ảnh bo hình vòm (tròn hẳn hai góc trên), tên và ghi chú nằm BÊN DƯỚI ảnh,
 * căn giữa. Rê chuột thì cả thẻ nhấc lên.
 *
 * Bản trước đặt hộp chữ nền đặc đè lên đáy ảnh. Thiết kế bỏ hẳn lối đó: chữ
 * ra ngoài ảnh nên không còn phụ thuộc vào vùng sáng tối của từng tấm.
 *
 * Mỗi mục trỏ thẳng vào bộ lọc dáng gọng có thật trong catalog, để bấm vào
 * là ra hàng chứ không phải một trang giới thiệu suông.
 */

$styles = [
    [
        'name'  => 'Năng động',
        'note'  => 'Mặt tròn · gọng vuông, browline',
        'url'   => '/san-pham?shape=Square',
        'image' => designImage('style-1', 'assets/images/product-1.jpg'),
    ],
    [
        'name'  => 'Thanh lịch',
        'note'  => 'Mặt vuông · gọng oval, kim loại mảnh',
        'url'   => '/san-pham?shape=Aviator',
        'image' => designImage('style-2', 'assets/images/product-3.jpg'),
    ],
    [
        'name'  => 'Cổ điển trở lại',
        'note'  => 'Mặt trái xoan · gọng tròn, acetate dày',
        'url'   => '/san-pham?shape=Round',
        'image' => designImage('style-3', 'assets/images/product-2.jpg'),
    ],
];
?>

<section class="hstyle" data-section="s06" aria-labelledby="hstyle-title">
    <div class="hstyle__inner">

        <div class="hstyle__head">
            <p class="eyebrow">Tư vấn phong cách</p>
            <h2 id="hstyle-title" class="section-h2 section-h2--plain hstyle__title">
                Chọn theo khuôn mặt, không chỉ theo thông số
            </h2>
            <p class="hstyle__lead">
                Mỗi khuôn mặt hợp một dòng gọng khác nhau. Chọn điểm bắt đầu của bạn.
            </p>
        </div>

        <ul class="hstyle__grid" role="list">
            <?php foreach ($styles as $s): ?>
                <li class="scard">
                    <a class="scard__link" href="<?= e($s['url']) ?>">
                        <span class="scard__media">
                            <img src="<?= e($s['image']) ?>" alt=""
                                 loading="lazy" decoding="async">
                        </span>

                        <span class="scard__body">
                            <span class="scard__name"><?= e($s['name']) ?></span>
                            <span class="scard__note"><?= e($s['note']) ?></span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
