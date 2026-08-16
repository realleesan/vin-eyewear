<?php

/**
 * config/taxonomy.php
 *
 * Dữ liệu phân loại đa chiều cho mega menu và các widget trang chủ.
 * Port từ src/lib/taxonomy.ts của bản Lovable.
 *
 * Vì sao để ở config chứ không phải bảng trong DB: đây là các LÁT CẮT
 * điều hướng, không phải dữ liệu nghiệp vụ. Mỗi mục chỉ là một bộ tham số
 * lọc trỏ về /san-pham — thêm bớt là việc của người làm nội dung sửa file,
 * không cần trang quản trị. Danh mục thật (categories) vẫn nằm trong DB.
 *
 * Mỗi mục: ['label' => nhãn hiển thị, 'search' => tham số query trỏ tới]
 *   ['label' => 'Vuông (Square)', 'search' => ['shape' => 'Square']]
 *       -> /san-pham?shape=Square
 */

return [

    // Lọc theo cột frame_shape của bảng products
    'frame_styles' => [
        ['label' => 'Vuông (Square)',     'search' => ['shape' => 'Square']],
        ['label' => 'Tròn (Round)',       'search' => ['shape' => 'Round']],
        ['label' => 'Mắt mèo (Cat-eye)',  'search' => ['shape' => 'Cat-eye']],
        ['label' => 'Phi công (Aviator)', 'search' => ['shape' => 'Aviator']],
        ['label' => 'Hình học (Geometric)', 'search' => ['shape' => 'Geometric']],
        ['label' => 'Oval',               'search' => ['shape' => 'Oval']],
        ['label' => 'Wayfarer',           'search' => ['shape' => 'Wayfarer']],
    ],

    // Dùng tìm kiếm toàn văn (q) thay vì lọc cột material: chuỗi chất liệu
    // trong DB không chuẩn hoá tuyệt đối ("Polycarbonate 1.61" chứa "1.61"),
    // nên khớp gần đúng cho kết quả sát ý người dùng hơn.
    'materials' => [
        ['label' => 'Titanium',        'search' => ['q' => 'titanium']],
        ['label' => 'Acetate',         'search' => ['q' => 'acetate']],
        ['label' => 'TR90',            'search' => ['q' => 'tr90']],
        ['label' => 'Thép không gỉ',   'search' => ['q' => 'stainless']],
        ['label' => 'Ultem',           'search' => ['q' => 'ultem']],
    ],

    'lens_functions' => [
        ['label' => 'Chống ánh sáng xanh',        'search' => ['q' => 'blue']],
        ['label' => 'Đổi màu (Photochromic)',     'search' => ['q' => 'photochromic']],
        ['label' => 'Chiết suất cao 1.56 – 1.74', 'search' => ['q' => 'index']],
        ['label' => 'Chống chói (Anti-glare)',    'search' => ['q' => 'anti-glare']],
        ['label' => 'Đa tròng (Progressive)',     'search' => ['q' => 'progressive']],
    ],

    // Khớp cột gender của bảng products
    'audiences' => [
        ['label' => 'Nam',     'search' => ['gender' => 'male']],
        ['label' => 'Nữ',      'search' => ['gender' => 'female']],
        ['label' => 'Unisex',  'search' => ['gender' => 'unisex']],
        ['label' => 'Trẻ em',  'search' => ['gender' => 'kids']],
    ],

    'top_brands' => [
        'Ray-Ban', 'Essilor', 'Zeiss', 'Nikon',
        'Chemi', 'Oakley', 'Gucci', 'Lindberg',
    ],

    // Gói tròng kính khách cắt kèm khi mua gọng — dùng ở trang chi tiết
    // sản phẩm và bước thanh toán.
    'lens_packages' => [
        [
            'id'    => 'blue-156',
            'name'  => 'Tròng chống ánh sáng xanh 1.56',
            'desc'  => 'Phù hợp độ nhẹ, làm việc máy tính nhiều.',
            'price' => 450000,
        ],
        [
            'id'    => 'photochromic',
            'name'  => 'Tròng đổi màu Photochromic',
            'desc'  => 'Tự động sẫm màu ngoài trời, chống UV400.',
            'price' => 1250000,
        ],
        [
            'id'    => 'index-167',
            'name'  => 'Tròng chiết suất cao 1.67',
            'desc'  => 'Mỏng nhẹ cho độ cao trên 4.00.',
            'price' => 1650000,
        ],
        [
            'id'    => 'progressive',
            'name'  => 'Tròng đa tròng Progressive',
            'desc'  => 'Nhìn xa – trung – gần trên một tròng kính.',
            'price' => 2900000,
        ],
    ],

    // Số liệu uy tín — dải nền tối ở trang chủ. Port từ AUTHORITY_STATS.
    'authority_stats' => [
        ['value' => '10+',      'label' => 'Năm kinh nghiệm khúc xạ'],
        ['value' => '100.000+', 'label' => 'Khách hàng đã tin chọn'],
        ['value' => '50+',      'label' => 'Thương hiệu toàn cầu'],
        ['value' => '4.9/5',    'label' => 'Điểm đánh giá Google'],
    ],

    /*
     * 'partner_brands' đã bỏ khỏi đây (S13).
     *
     * Danh sách cũ trộn hãng gọng (Ray-Ban, Oakley) với hãng tròng (Essilor,
     * Zeiss) làm một, chỉ để chạy chữ ngang. Nay tách hai nhóm và chuyển sang
     * config/brands.php — nơi đó còn giữ cả đường dẫn file logo.
     */

    // Đánh giá Google hiển thị ở trang chủ. Port từ GOOGLE_REVIEWS (taxonomy.ts).
    'google_reviews' => [
        [
            'name'   => 'Nguyễn Thu Hà',
            'store'  => 'CS1 · 261 Ngọc Lâm',
            'rating' => 5,
            'text'   => 'Kỹ thuật viên đo rất kỹ, giải thích từng chỉ số. Gọng titanium nhẹ, '
                      . 'đeo cả ngày không đau tai.',
        ],
        [
            'name'   => 'Trần Minh Quân',
            'store'  => 'CS2 · 46 Hoàng Hoa Thám',
            'rating' => 5,
            'text'   => 'Cắt tròng chống ánh sáng xanh sau 45 phút là xong. Nhân viên nắn chỉnh '
                      . 'lại gọng miễn phí.',
        ],
        [
            'name'   => 'Lê Phương Anh',
            'store'  => 'CS1 · 261 Ngọc Lâm',
            'rating' => 5,
            'text'   => 'Được thử hơn 10 mẫu, tư vấn theo dáng mặt rất hợp. Giá tốt hơn hẳn '
                      . 'mấy chỗ mình từng mua.',
        ],
        [
            'name'   => 'Phạm Đức Long',
            'store'  => 'CS2 · 46 Hoàng Hoa Thám',
            'rating' => 4,
            'text'   => 'Không gian đẹp, quy trình chuyên nghiệp. Đặt lịch online nhanh, '
                      . 'đến là được đo ngay.',
        ],
    ],

    // Gợi ý dáng gọng theo khuôn mặt — widget trang chủ
    'face_shapes' => [
        ['id' => 'oval',    'label' => 'Trái xoan',  'hint' => 'Cân đối, hợp hầu hết dáng gọng.',        'recommend' => ['Wayfarer', 'Aviator', 'Square']],
        ['id' => 'round',   'label' => 'Tròn',       'hint' => 'Cần dáng gọng góc cạnh để tạo nét.',     'recommend' => ['Square', 'Geometric', 'Wayfarer']],
        ['id' => 'square',  'label' => 'Vuông',      'hint' => 'Ưu tiên đường bo mềm mại.',              'recommend' => ['Round', 'Oval', 'Aviator']],
        ['id' => 'heart',   'label' => 'Trái tim',   'hint' => 'Gọng nhẹ phần trên, mở rộng phần dưới.', 'recommend' => ['Aviator', 'Oval', 'Round']],
        ['id' => 'long',    'label' => 'Dài',        'hint' => 'Gọng bản to giúp cân tỉ lệ.',            'recommend' => ['Wayfarer', 'Cat-eye', 'Square']],
        ['id' => 'diamond', 'label' => 'Kim cương',  'hint' => 'Nhấn ở đường viền trên của gọng.',       'recommend' => ['Cat-eye', 'Oval', 'Round']],
    ],
];
