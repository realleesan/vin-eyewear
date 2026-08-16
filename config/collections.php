<?php

/**
 * config/collections.php — bộ sưu tập hiển thị ở trang chủ (S09).
 *
 * Đây là nội dung THỜI TRANG THEO MÙA, đổi vài lần một năm nên để ở config:
 * sửa một file là đổi được cả khối, không phải đụng DB hay deploy lại view.
 *
 * 'slug'  khớp giá trị cột products.collection. Link dựng thành
 *         /san-pham?collection=<slug>, dùng lại đúng ProductController hiện
 *         có — không cần controller hay model riêng cho bộ sưu tập.
 *
 * 'image' ảnh lookbook: NGƯỜI ĐEO KÍNH, không phải ảnh sản phẩm nền trắng.
 *         File chưa có cũng không sao — partial tự dùng 'image_sample' bên
 *         dưới, là ảnh sẵn có trong kho, để bố cục dựng được ngay hôm nay.
 *         TODO(data): thay bằng ảnh chụp thật rồi bỏ 'image_sample'.
 *         Xem assets/images/collections/README.md.
 */

return [
    [
        'slug'         => 'nang-he',
        'name'         => 'Nắng hè',
        'tagline'      => 'Kính mát phân cực, dáng phi công và mắt mèo cho những ngày chói nhất.',
        'image'        => 'assets/images/collections/nang-he.jpg',
        'image_sample' => 'assets/images/showroom-storefront.jpg',
    ],
    [
        'slug'         => 'nhe-ca-ngay',
        'name'         => 'Nhẹ cả ngày',
        'tagline'      => 'Gọng titanium và tròng lọc ánh sáng xanh cho tám tiếng trước màn hình.',
        'image'        => 'assets/images/collections/nhe-ca-ngay.jpg',
        'image_sample' => 'assets/images/hero-eyewear.jpg',
    ],
    [
        'slug'         => 'co-dien-tro-lai',
        'name'         => 'Cổ điển trở lại',
        'tagline'      => 'Acetate bản dày, dáng tròn và vuông — kiểu gọng không bao giờ cũ.',
        'image'        => 'assets/images/collections/co-dien-tro-lai.jpg',
        'image_sample' => 'assets/images/showroom-frames.jpg',
    ],
];
