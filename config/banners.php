<?php

/**
 * config/banners.php — các slide của banner trang chủ (S03).
 *
 * Đổi chiến dịch thì sửa file này, không phải đụng vào view. Thêm/bớt một
 * phần tử là carousel tự có thêm/bớt một slide, chấm tròn và nút điều hướng
 * đi theo — không có con số nào gõ cứng ở JS hay CSS.
 *
 * Mỗi slide:
 *   image   ảnh nền, phủ kín khung (object-fit: cover). Ảnh NGANG, tối thiểu
 *           1600px chiều rộng. Chữ nằm CHÍNH GIỮA khung nên chọn ảnh có vùng
 *           giữa tương đối lặng — mặt người hay hoạ tiết rối ở giữa sẽ nuốt chữ.
 *   eyebrow KHÔNG hiện trên banner (bố cục mẫu chỉ có tiêu đề · mô tả · nút).
 *           Dùng làm nhãn cho chấm tròn: "Tới banner 2: Ưu đãi tháng này" —
 *           trình đọc màn hình cần biết mỗi chấm dẫn tới nội dung gì.
 *   title   dòng đầu của tiêu đề
 *   accent  dòng sau (bỏ trống nếu chỉ muốn một dòng)
 *   lead    một câu mô tả, đặt dưới tiêu đề
 *   cta     nút chính  ['label' => ..., 'url' => ...]
 */

return [
    [
        'image'   => '/assets/images/hero-eyewear.jpg',
        'eyebrow' => 'Bộ sưu tập Thu Đông 2026',
        'title'   => 'Kính đẹp là kính',
        'accent'  => 'hợp với chính bạn.',
        'lead'    => 'Gọng kính và kính mát tuyển chọn từ hơn 50 thương hiệu quốc tế.',
        'cta'     => ['label' => 'Mua ngay',       'url' => '/san-pham'],
    ],
    [
        'image'   => '/assets/images/showroom-frames.jpg',
        'eyebrow' => 'Ưu đãi tháng này',
        'title'   => 'Giảm đến 30%',
        'accent'  => 'khi cắt kèm tròng.',
        'lead'    => 'Áp dụng cho tròng chống ánh sáng xanh, đổi màu và chiết suất cao 1.67 – 1.74.',
        'cta'     => ['label' => 'Xem ưu đãi',   'url' => '/su-kien'],
    ],
    [
        'image'   => '/assets/images/store-interior.jpg',
        'eyebrow' => 'Dịch vụ tại cửa hàng',
        'title'   => 'Đo mắt miễn phí,',
        'accent'  => 'lắp kính trong ngày.',
        'lead'    => 'Kỹ thuật viên khúc xạ đo theo quy trình chuẩn phòng khám, nhận kính sau 60–90 phút.',
        'cta'     => ['label' => 'Đặt lịch đo mắt', 'url' => '/dat-lich'],
    ],
];
