<?php

/**
 * config/eyewear.php
 *
 * Kiến thức về KÍNH MẮT NÓI CHUNG — thứ đúng với mọi bộ sưu tập, mọi mẫu, và
 * không đổi khi kho hàng đổi.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO Ở CONFIG CHỨ KHÔNG PHẢI CSDL
 *
 * Ba câu hỏi phân biệt được hai chỗ:
 *
 *   1. Nhân viên cửa hàng có bao giờ cần sửa nó không?  Không.
 *      "Số đầu trong 52□18-145 là bề rộng một bên tròng" là sự thật của ngành
 *      kính, không phải quyết định của cửa hàng này.
 *   2. Nó có khác nhau giữa các bộ sưu tập không?  Không.
 *      Cách đo gọng cũ giống hệt nhau ở bộ mùa hè và bộ đi làm.
 *   3. Sai thì hỏng cái gì?  Hỏng CÁCH ĐỌC của mọi trang cùng lúc.
 *      Ngưỡng S/M/L nằm ở đây nuôi cả huy hiệu trên bảng so sánh lẫn bảng quy
 *      đổi bên dưới nó — hai chỗ mà lệch nhau thì trang tự mâu thuẫn.
 *
 * Ngược lại, thứ THUỘC VỀ MỘT MẶT HÀNG (mẫu này nặng bao nhiêu gram, tròng
 * chiết suất mấy) nằm trong cột của `products`. Và thứ THUỘC VỀ MỘT BỘ (câu
 * chuyện, bảng màu, ưu đãi ra mắt) nằm trong cột của `collections`.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * PHẦN 'defaults' LÀ BẢN MẶC ĐỊNH, KHÔNG PHẢI BẢN DUY NHẤT
 *
 * Bảo hành, đổi trả, phụ kiện và chứng nhận giống nhau ở chín trên mười mặt
 * hàng. Chép mười lần cùng một câu vào mười dòng `products` thì sửa chính sách
 * là sửa mười chỗ, và sót một chỗ là nó mâu thuẫn với chín chỗ kia mà không có
 * gì báo.
 *
 * Nên: cột trên `products` để TRỐNG nghĩa là "theo chính sách chung", và trang
 * đọc từ đây. Chỉ điền cột khi mặt hàng đó phải nói KHÁC — ví dụ gọng cận đã
 * cắt tròng theo đơn thì không đổi trả được, dù chính sách chung cho 7 ngày.
 * ─────────────────────────────────────────────────────────────────────────────
 */

return [

    /* KHÔNG CÒN BẢNG 'sizes' Ở ĐÂY — gỡ ở FR-SP-09 cùng EyewearSpecs::sizeKey()
       và ::sizeTable().

       Nó quy tổng bề rộng gọng thành ba hạng S/M/L kèm gợi ý dáng mặt. Hạng cỡ
       ấy hứa một chuẩn chung mà ngành kính không có: 138mm là "M" ở bảng này và
       "L" ở bảng của hãng khác, nên khách mua theo chữ cái nhận về thứ không
       vừa. Ba con số milimét thật và chuỗi 52□18-145 in trên càng kính thì
       không mơ hồ như thế, và chúng vẫn còn nguyên.

       Phần gợi ý dáng mặt KHÔNG mất theo: nó nay đọc từ cột `face_shapes` của
       từng mẫu (config 'face_shapes' bên dưới), tức do người bán chọn cho đúng
       mẫu chứ không suy ra từ một con số milimét.

       ĐỪNG NHẦM VỚI 'size_classes' ở cuối file — đó là cột `size_class` do nhân
       viên tự chọn trong form sản phẩm, không hiện ra trang bán hàng, và không
       liên quan tới bảng đã gỡ này. */

    /*
     * PHÂN LOẠI KÍNH — ba nhóm, và chúng KHÔNG phải danh mục.
     *
     * `categories` trong CSDL là cây hàng hoá của cửa hàng (gọng kính, kính
     * mát, tròng kính, phụ kiện…), do nhân viên tự thêm. Ba khoá dưới đây là
     * thứ quyết định BẢNG THÔNG SỐ ĐỌC RA SAO — gọng cận thì dòng "giá" nghĩa
     * là giá chưa tròng, kính râm thì đã gồm tròng. Trộn hai khái niệm là lúc
     * nào đó sẽ có người thêm danh mục "Phụ kiện" rồi tự hỏi vì sao ngăn kéo
     * thông số của cái khăn lau lại hỏi về chiết suất.
     */
    'types' => [
        'gong-can'  => 'Gọng cận',
        'kinh-ram'  => 'Kính râm',
        'da-dung'   => 'Đa dụng',
    ],

    /*
     * DÁNG MẶT — khoá chuẩn cho cột `products.face_shapes` (CSV).
     *
     * Slug không dấu để cột lưu được và so khớp được; nhãn có dấu để in ra.
     * Bảng "gọng theo dáng mặt" trên trang bộ sưu tập DỰNG TỪ dữ liệu của
     * chính các mẫu trong bộ, không gõ tay — bộ đổi hàng là bảng đổi theo.
     */
    'face_shapes' => [
        'tron'       => 'Mặt tròn',
        'trai-xoan'  => 'Mặt trái xoan',
        'vuong'      => 'Mặt vuông',
        'chu-nhat'   => 'Mặt chữ nhật',
        'tam-giac'   => 'Mặt tam giác',
        'mat-dai'    => 'Mặt dài',
        // Thêm 2026-08-29 theo "Quản lý sản phẩm.dc.html". Bốn khoá bản vẽ
        // dùng trong form là tron · vuong · trai-xoan · tim; ba khoá còn lại ở
        // trên vẫn giữ để đọc được dữ liệu đã nhập trước đó.
        'tim'        => 'Mặt tim',
    ],

    /*
     * LỚP PHỦ VÀ TÍNH NĂNG TRÒNG — khoá chuẩn cho `products.lens_coatings`.
     *
     * Lưu CSV trong một cột chứ không dựng bảng nối. Bảng nối sẽ cho phép câu
     * "chỉ hàng có chống loá" chạy bằng SQL, nhưng bộ lọc của trang danh mục
     * không truy vấn thẳng cột — nó đi qua ProductTaxonomy đọc trên tập đã tải
     * về RAM (xem ProductModel::catalog). Nên bảng nối chưa mua thêm được gì
     * mà đã tốn một JOIN ở mọi lượt tải.
     *
     * Đổi ý thì đây là chỗ sửa: thêm bảng, giữ nguyên cột này một thời gian,
     * chép sang, rồi mới bỏ cột.
     */
    'coatings' => [
        'uv400'       => 'UV400',
        'chong-loa'   => 'Chống phản quang',
        'anh-sang-xanh' => 'Lọc ánh sáng xanh',
        'chong-tray'  => 'Chống trầy',
        'chong-nuoc'  => 'Chống bám nước',
        'chong-bui'   => 'Chống bám bụi',
    ],

    /*
     * CẤP ĐỘ TỐI CỦA TRÒNG — thang 0..4 của ISO 12312-1.
     *
     * Con số này KHÔNG phải "mức chống tia UV", một hiểu nhầm phổ biến: tròng
     * cấp 0 vẫn có thể chặn 100% UV. Nó nói lượng ánh sáng nhìn thấy bị chặn,
     * và đó là lý do cấp 4 bị cấm lái xe — không phải vì hại mắt mà vì không
     * còn nhìn rõ đèn tín hiệu.
     */
    'lens_categories' => [
        0 => 'Cấp 0 — trong suốt, dùng trong nhà',
        1 => 'Cấp 1 — hơi màu, nắng nhẹ',
        2 => 'Cấp 2 — nắng vừa',
        3 => 'Cấp 3 — nắng gắt, đi biển',
        4 => 'Cấp 4 — nắng cực gắt, KHÔNG được lái xe',
    ],

    /*
     * HƯỚNG DẪN ĐO GỌNG CŨ — ba bước, in ở khối "Chọn đúng cỡ gọng".
     *
     * Viết theo thứ tự ba con số in trong càng kính, vì đó là thứ người đọc
     * đang cầm trên tay khi đọc đoạn này. Mỗi bước nói thêm HẬU QUẢ của việc
     * chọn sai — không có phần đó thì ba dòng chỉ là chú giải ký hiệu, mà chú
     * giải thì không giúp ai quyết định.
     */
    'size_guide' => [
        [
            'title' => 'Rộng tròng — số đầu',
            'body'  => 'Bề ngang của một bên tròng, tính bằng mm. Số này quyết định '
                     . 'gọng có tràn ra ngoài đuôi mắt hay không.',
        ],
        [
            'title' => 'Cầu kính — số giữa',
            'body'  => 'Khoảng cách giữa hai tròng, đo ngang sống mũi. Sống mũi thấp '
                     . 'thì chọn số nhỏ hơn để kính không tụt.',
        ],
        [
            'title' => 'Dài càng — số cuối',
            'body'  => 'Từ bản lề tới đuôi càng. Chênh 5mm thì gọng vẫn đeo được, '
                     . 'chênh 10mm thì cấn tai.',
        ],
    ],

    /* Bảo quản — bốn điều, in ở khối "Giữ kính bền". */
    'care' => [
        'Rửa nước sạch trước khi lau. Bụi cát còn trên tròng mà lau khô là xước ngay lớp phủ.',
        'Đừng để trên táp-lô xe giữa trưa. Nhiệt làm acetate cong và lớp phân cực bong khỏi tròng.',
        'Gấp càng trái trước — bản lề được lắp theo chiều đó, gấp ngược lâu ngày làm rơ khớp.',
        'Siết ốc và cân gọng miễn phí trọn đời tại mọi cơ sở, kể cả kính mua đã lâu.',
    ],

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * DANH SÁCH CHỌN CỦA FORM THÊM/SỬA SẢN PHẨM
     *
     * Thêm 2026-08-29, đúng bộ lựa chọn trong "Quản lý sản phẩm.dc.html".
     *
     * KHOÁ LÀ THỨ LƯU XUỐNG CSDL, nhãn chỉ để hiện. Bản vẽ viết thẳng nhãn
     * tiếng Việt vào <option> không kèm value, tức là lưu chính chữ hiển thị —
     * làm thế thì đổi một chữ trong nhãn là mọi dòng cũ ngừng khớp bộ lọc.
     *
     * Khoá chọn theo ProductTaxonomy (app/services) chứ không đặt mới: bộ lọc
     * của trang bán hàng đối chiếu qua bảng đồng nghĩa ở đó, và một khoá lạ thì
     * lọc ra 0 sản phẩm mà trong khu quản trị nhìn vẫn như đã gán xong.
     * ─────────────────────────────────────────────────────────────────────────
     */

    /* HAI trạng thái xuất bản — cột `products`.`publish_status`.

       Trước đây có ba, với "Nháp" nghĩa là hàng chưa nhập đủ thông tin, tách
       khỏi "Ẩn" nghĩa là hàng làm xong nhưng chưa tới lượt bày. Chủ đầu tư đã
       bỏ "Nháp" (SRS v2.1.0, J02): cả hai đều không hiện ra trang bán hàng, và
       sự phân biệt ấy không đổi việc ai phải làm gì.

       Dữ liệu đang ở 'draft' được migration đợt 1 chuyển thành 'hidden'.

       `is_visible` vẫn đồng bộ theo cột này: visible → 1, hidden → 0. Trang bán
       hàng lọc theo `is_visible` nên không phải biết cột này. */
    'publish_statuses' => [
        'visible' => 'Hiện',
        'hidden'  => 'Ẩn',
    ],

    /* Chất liệu gọng — khoá của ProductTaxonomy::MATERIALS.
       'stainless-steel' là ĐỒNG NGHĨA của 'metal' ở đó, nên chọn nó vẫn lọc ra
       cùng nhóm "Kim loại"; giữ riêng vì thép không gỉ và kim loại nói chung là
       hai thứ người mua phân biệt được. */
    'frame_materials' => [
        'acetate'         => 'Acetate',
        'metal'           => 'Kim loại',
        'titanium'        => 'Titanium',
        'tr90'            => 'TR90',
        'stainless-steel' => 'Thép không gỉ',
    ],

    /* Kiểu viền gọng — cột `rim_type`. */
    'rim_types' => [
        'full-rim' => 'Full rim (gọng đầy)',
        'half-rim' => 'Half rim (nửa gọng)',
        'rimless'  => 'Rimless (không gọng)',
    ],

    /* Hình dáng gọng — khoá của ProductTaxonomy::SHAPES.
       Bảy mục đúng bằng bản vẽ. ProductTaxonomy còn biết thêm sáu dáng nữa
       (wayfarer, butterfly, wraparound…) — dữ liệu cũ mang khoá đó vẫn đọc và
       lọc được, chỉ là form không đề nghị chúng nữa. */
    'frame_shapes' => [
        'square'    => 'Vuông',
        'round'     => 'Tròn',
        'oval'      => 'Oval',
        'cat-eye'   => 'Mắt mèo',
        'aviator'   => 'Phi công',
        'browline'  => 'Browline',
        'geometric' => 'Đa giác',
    ],

    /* Giới tính — khoá của ProductTaxonomy::AUDIENCE.
       Bản vẽ bỏ 'kids'; khoá đó vẫn đọc được nếu có dòng cũ mang nó. */
    'genders' => [
        'unisex' => 'Unisex',
        'male'   => 'Nam',
        'female' => 'Nữ',
    ],

    /* Size tổng quát — cột `size_class`, nhân viên tự chọn trong form sản phẩm.

       KHÔNG hiện ra trang bán hàng ở đâu cả, và không còn bảng ngưỡng mm nào đi
       kèm: bảng 'sizes' quy từ bề rộng gọng đã gỡ ở FR-SP-09 (xem khối đầu
       file). Ba khoá dưới đây chỉ là ba chữ cái để lọc nội bộ. */
    'size_classes' => ['S' => 'S', 'M' => 'M', 'L' => 'L'],

    /* Loại tròng nhận đặt kèm — CSV trong cột `lens_types`. */
    'rx_lens_types' => [
        'don-trong'      => 'Đơn tròng',
        'da-trong'       => 'Đa tròng',
        'doi-mau'        => 'Đổi màu',
        'anh-sang-xanh'  => 'Chống ánh sáng xanh',
    ],

    /* Chiết suất đặt thêm được — CSV trong cột `lens_indexes`.
       Khoá là chính con số: nó vừa là giá trị vừa là nhãn, và bảng giá tròng
       (`lens_prices`) cũng khoá theo chuỗi này. */
    'rx_indexes' => ['1.56' => '1.56', '1.61' => '1.61', '1.67' => '1.67', '1.74' => '1.74'],

    /*
     * CHÍNH SÁCH CHUNG — dùng khi cột tương ứng của `products` để trống.
     * Xem khối chú thích đầu file về vì sao mặc định nằm ở đây.
     */
    'defaults' => [
        'warranty'       => '12 tháng gọng · 6 tháng lớp phủ tròng',
        'return_policy'  => 'Đổi trả trong 7 ngày nếu còn nguyên hộp và tem',
        // Ngăn bằng dấu phẩy: trang tách chuỗi này thành từng nhãn rời, nên
        // mỗi mục phải đứng được một mình — viết hoa chữ đầu như một nhãn,
        // không phải như một câu liệt kê.
        'accessories'    => 'Hộp cứng, Khăn lau sợi nhỏ, Thẻ bảo hành',
        'certifications' => 'CE',
    ],
];
