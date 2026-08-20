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

    /*
     * Lọc theo dáng gọng.
     *
     * Giá trị là KHOÁ CHUẨN của ProductTaxonomy, không phải chữ trong cột
     * `frame_shape`: kho ghi "Vuông (Square)", "Square / Vuông" và "Square"
     * lẫn lộn, mà cả ba đều quy về 'square'.
     *
     * Chữ hoa vẫn chạy ('Square' cũng ra 'square' sau khi slug hoá), nên các
     * liên kết cũ đã có người lưu không gãy — nhưng viết thẳng khoá chuẩn ở
     * đây thì đọc file này là biết ngay bộ lọc nhận cái gì.
     */
    'frame_styles' => [
        ['label' => 'Oval',                 'search' => ['shape' => 'oval']],
        ['label' => 'Vuông (Square)',       'search' => ['shape' => 'square']],
        ['label' => 'Mắt mèo (Cat-eye)',    'search' => ['shape' => 'cat-eye']],
        ['label' => 'Chữ nhật (Rectangle)', 'search' => ['shape' => 'rectangle']],
        ['label' => 'Tròn (Round)',         'search' => ['shape' => 'round']],
        ['label' => 'Phi công (Aviator)',   'search' => ['shape' => 'aviator']],
        ['label' => 'Hình học (Geometric)', 'search' => ['shape' => 'geometric']],
    ],

    // Lọc ĐÚNG CỘT `material`, không dùng tìm kiếm toàn văn.
    //
    // ĐÃ SỬA TỪ 'q': chú thích cũ ở đây nói dùng q để "khớp gần đúng" chuỗi
    // chất liệu — nhưng ProductModel::buildFilter() cho q tìm trong name,
    // brand và sku, KHÔNG tìm trong material. Nên cả năm liên kết này luôn
    // trả về 0 sản phẩm, ở cả mega menu lẫn mọi chỗ khác gọi tới chúng.
    //
    // Giá trị nay là KHOÁ CHUẨN của ProductTaxonomy (app/services), không còn
    // là chữ nguyên văn trong cột: kho ghi "Kim loại bạc / Titanium" thì khoá
    // 'titanium' vẫn lọc ra nó, còn so nguyên văn thì không.
    //
    // ĐÃ BỎ 'Thép không gỉ' (Stainless Steel): không có sản phẩm nào, và cũng
    // không phải chất liệu cửa hàng nhập. Một liên kết dẫn tới lưới rỗng tệ
    // hơn là không có nó.
    'materials' => [
        ['label' => 'Acetate',        'search' => ['material' => 'acetate']],
        ['label' => 'Kim loại',       'search' => ['material' => 'metal']],
        ['label' => 'Titanium',       'search' => ['material' => 'titanium']],
        ['label' => 'Nylon',          'search' => ['material' => 'nylon']],
    ],

    // Tính năng tròng — nay là một NHÓM LỌC THẬT (?lens[]=...), không còn phải
    // mượn ô tìm kiếm.
    //
    // Trước đây năm mục này lọc bằng 'q', mà q chỉ tìm trong name/brand/sku:
    // một gọng ghi "Chống ánh sáng xanh" trong `specs` không bao giờ khớp, nên
    // cả cột "Tròng kính" của mega menu dẫn tới lưới gần rỗng. ProductTaxonomy
    // đọc tính năng ra từ specs + mô tả + tên, nên khoá dưới đây khớp đúng thứ
    // người dùng trông đợi.
    //
    // Khoá phải có trong ProductTaxonomy::LENS — thêm mục ở đây mà không thêm
    // ở đó thì liên kết lọc ra 0 sản phẩm.
    'lens_functions' => [
        ['label' => 'Chống ánh sáng xanh',     'search' => ['lens' => 'blue-light']],
        ['label' => 'Chống UV (UVA/UVB)',      'search' => ['lens' => 'uv']],
        ['label' => 'Đổi màu (Photochromic)',  'search' => ['lens' => 'photochromic']],
        ['label' => 'Phân cực (Polarized)',    'search' => ['lens' => 'polarized']],
        ['label' => 'Lắp được tròng thuốc',    'search' => ['lens' => 'rx']],
    ],

    // Khớp cột gender của bảng products
    'audiences' => [
        ['label' => 'Nam',     'search' => ['gender' => 'male']],
        ['label' => 'Nữ',      'search' => ['gender' => 'female']],
        ['label' => 'Unisex',  'search' => ['gender' => 'unisex']],
        ['label' => 'Trẻ em',  'search' => ['gender' => 'kids']],
    ],

    /*
     * 'top_brands' đã bỏ khỏi đây.
     *
     * Đó là một danh sách GÕ CỨNG tám cái tên — Essilor, Zeiss, Nikon, Chemi,
     * Oakley, Lindberg — mà cửa hàng không bán một sản phẩm nào; Ray-Ban là
     * cái tên duy nhất còn trong kho. Không view nào đọc nó nữa (khối logo
     * thương hiệu ở trang chủ đã gỡ), nên nó chỉ còn là một danh sách chờ ai
     * đó dùng lại rồi dựng ra một bộ lọc trỏ vào chỗ trống.
     *
     * Cần danh sách thương hiệu thì lấy từ DỮ LIỆU: cột lọc trang /san-pham
     * dựng nó bằng ProductFacets, luôn khớp hàng đang bán và kèm số lượng.
     */

    // Gói tròng kính khách cắt kèm khi mua gọng — dùng ở trang chi tiết
    // sản phẩm và bước thanh toán.
    /*
     * BẢNG GIÁ CẮT TRÒNG — lấy nguyên năm mục của "Vin Eyewear Product.dc.html"
     * (bước "Chọn loại tròng kính" trong hộp thoại mua hàng).
     *
     * Dùng ở hai nơi:
     *   _layout/home/lenses.php    "Gói tròng phổ biến" ở trang chủ
     *   _layout/buy-modal.php      bước chọn tròng khi mua gọng
     *
     * `desc` cố ý nói theo DẢI ĐỘ chứ không theo tính năng: khách đứng ở bước
     * này vừa nhập xong số đo ở bước trước, nên câu hỏi trong đầu họ là "độ của
     * mình thì chọn cái nào", không phải "chiết suất 1.61 nghĩa là gì".
     *
     * Đọc qua LensModel::packages() — đừng gọi thẳng config() ở view, vì nơi đó
     * còn lo cả việc tra id (LensModel::find) khi form gửi lên.
     */
    'lens_packages' => [
        [
            'id'    => 'clear-150',
            'name'  => 'Tròng trắng 1.50',
            'desc'  => 'Phù hợp độ cận/viễn nhẹ đến trung bình (dưới -4.00)',
            'price' => 500000,
        ],
        [
            'id'    => 'clear-156',
            'name'  => 'Tròng trắng 1.56',
            'desc'  => 'Mỏng hơn, phù hợp cận trung bình (-4.00 → -6.00)',
            'price' => 700000,
        ],
        [
            'id'    => 'blue-161',
            'name'  => 'Chống sáng xanh 1.61',
            'desc'  => 'Bảo vệ mắt khi làm việc máy tính nhiều giờ',
            'price' => 1200000,
        ],
        [
            'id'    => 'blue-167',
            'name'  => 'Chống sáng xanh 1.67',
            'desc'  => 'Siêu mỏng, thẩm mỹ cao, cận nặng (trên -6.00)',
            'price' => 1800000,
        ],
        [
            'id'    => 'photo-156',
            'name'  => 'Đổi màu Photochromic 1.56',
            'desc'  => 'Tự điều chỉnh theo ánh sáng, tiện dùng trong/ngoài trời',
            'price' => 2500000,
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
     * Zeiss) làm một, chỉ để chạy chữ ngang. Khối logo thương hiệu về sau bị
     * gỡ khỏi trang chủ nên cả danh sách lẫn config/brands.php đều đã xoá.
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
        // ─────────────────────────────────────────────────────────────────
        // SÁU MỤC DƯỚI ĐÂY LÀ NỘI DUNG MẪU — thay bằng đánh giá thật khi có.
        //
        // Khối đánh giá ở trang chủ hiện 5 thẻ một khung nhìn và TỰ CHẠY.
        // Băng chỉ trượt được khi số đánh giá NHIỀU HƠN số thẻ nhìn thấy:
        // đúng 5 mục thì nó đứng im và hai mũi tên tự ẩn (xem
        // _layout/home/reviews.php). Mười mục cho ra 6 vị trí dừng, đủ để
        // vòng chạy không thành một cái lắc qua lắc lại.
        //
        // Bớt xuống dưới 6 thì nhớ hạ số thẻ mỗi khung nhìn ở .rcard trong
        // components/home-sections.css, nếu không khối này thành tĩnh.
        // ─────────────────────────────────────────────────────────────────
        [
            'name'   => 'Vũ Hải Yến',
            'store'  => 'CS1 · 261 Ngọc Lâm',
            'rating' => 5,
            'text'   => 'Cận 7 độ mà tròng 1.74 vẫn mỏng nhẹ. Tư vấn đúng nhu cầu, '
                      . 'không chèo kéo gói đắt.',
        ],
        [
            'name'   => 'Đỗ Minh Châu',
            'store'  => 'CS2 · 46 Hoàng Hoa Thám',
            'rating' => 5,
            'text'   => 'Đưa con đi đo mắt học đường, quy trình nhanh mà kỹ. '
                      . 'Có hồ sơ theo dõi độ cận từng đợt.',
        ],
        [
            'name'   => 'Hoàng Nam Trung',
            'store'  => 'CS1 · 261 Ngọc Lâm',
            'rating' => 5,
            'text'   => 'Gọng cũ gãy càng, mang ra được thay và chỉnh lại trong 20 phút. '
                      . 'Không tính thêm đồng nào.',
        ],
        [
            'name'   => 'Bùi Thanh Vân',
            'store'  => 'CS2 · 46 Hoàng Hoa Thám',
            'rating' => 4,
            'text'   => 'Mẫu mình thích hết size, nhân viên gọi lại sau ba ngày khi có hàng. '
                      . 'Giữ đúng hẹn.',
        ],
        [
            'name'   => 'Ngô Gia Bảo',
            'store'  => 'CS1 · 261 Ngọc Lâm',
            'rating' => 5,
            'text'   => 'Đo xong được in phiếu ghi rõ độ từng mắt và khoảng cách đồng tử. '
                      . 'Mang đi đâu cũng dùng được.',
        ],
        [
            'name'   => 'Trịnh Khánh Linh',
            'store'  => 'CS2 · 46 Hoàng Hoa Thám',
            'rating' => 5,
            'text'   => 'Tròng đổi màu đúng như tư vấn, ra nắng sẫm nhanh. '
                      . 'Đeo lái xe buổi trưa dễ chịu hẳn.',
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
