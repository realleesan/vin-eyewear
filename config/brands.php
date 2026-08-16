<?php

/**
 * config/brands.php — thương hiệu hiển thị ở lưới logo trang chủ (S13).
 *
 * TÁCH HAI NHÓM. Trước đây taxonomy.partner_brands gộp chung Ray-Ban, Oakley
 * (hãng GỌNG) với Essilor, Zeiss (hãng TRÒNG) vào một băng chữ chạy — người
 * xem không phân biệt được đâu là hãng làm gọng, đâu là hãng làm tròng, mà
 * đó lại đúng là hai quyết định mua khác nhau.
 *
 * 'name'  phải khớp CHÍNH XÁC giá trị cột products.brand thì link lọc mới ra
 *         hàng — link dựng thành /san-pham?brand=<name>.
 * 'logo'  đường dẫn tương đối từ gốc dự án. File CHƯA CÓ cũng không sao:
 *         partial tự đổ về chữ (wordmark) khi không tìm thấy file, thả file
 *         SVG/PNG nền trong vào assets/images/brands/ là logo hiện lên, không
 *         phải sửa code. Xem assets/images/brands/README.md.
 */

return [

    // Hãng gọng & kính mát — nhóm bán chính của một shop kính thời trang
    'frames' => [
        ['name' => 'Ray-Ban',  'logo' => 'assets/images/brands/ray-ban.svg'],
        ['name' => 'Oakley',   'logo' => 'assets/images/brands/oakley.svg'],
        ['name' => 'Lindberg', 'logo' => 'assets/images/brands/lindberg.svg'],
        ['name' => 'Bolon',    'logo' => 'assets/images/brands/bolon.svg'],
        ['name' => 'Gucci',    'logo' => 'assets/images/brands/gucci.svg'],
    ],

    // Hãng tròng kính
    'lenses' => [
        ['name' => 'Essilor',     'logo' => 'assets/images/brands/essilor.svg'],
        ['name' => 'Zeiss',       'logo' => 'assets/images/brands/zeiss.svg'],
        ['name' => 'Nikon',       'logo' => 'assets/images/brands/nikon.svg'],
        ['name' => 'Hoya',        'logo' => 'assets/images/brands/hoya.svg'],
        ['name' => 'Chemi',       'logo' => 'assets/images/brands/chemi.svg'],
        ['name' => 'Transitions', 'logo' => 'assets/images/brands/transitions.svg'],
    ],
];
