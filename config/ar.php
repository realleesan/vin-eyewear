<?php

/**
 * config/ar.php
 *
 * Dữ liệu cho trang thử kính AR (/thu-ar).
 * Port từ src/lib/ar-frames.ts và các hằng trong src/components/ar/ar-tryon.tsx.
 *
 * Gọng AR là một tập RIÊNG, không phải toàn bộ catalog: mỗi mẫu cần một ảnh
 * PNG nền trong suốt chụp chính diện để chồng lên khuôn mặt. Trường 'slug'
 * nối mẫu AR với sản phẩm thật trong bảng products, để nút "Mua ngay" dẫn
 * đúng trang chi tiết và lấy được giá hiện hành.
 */

return [

    // ------------------------------------------------------------------
    // HIỆN "THỬ KÍNH ẢO" TRÊN ĐIỀU HƯỚNG?
    //
    // Tính năng còn đang làm dở, nên mục này TẠM ẨN. Cờ này chi phối CẢ BA
    // chỗ có liên kết tới /thu-ar:
    //   - thanh điều hướng desktop  (_layout/header.php)
    //   - menu trượt mobile         (_layout/header.php)
    //   - cột "Về Vin Eyewear"      (_layout/footer.php)
    //
    // Xong tính năng thì đổi đúng dòng này thành true, không phải sửa chỗ nào
    // khác. Ẩn bằng cách KHÔNG in ra HTML chứ không phải display:none — mục
    // ẩn bằng CSS thì trình đọc màn hình vẫn đọc, Google vẫn lập chỉ mục, và
    // người dùng bàn phím vẫn Tab vào được một liên kết họ không nhìn thấy.
    //
    // Đường /thu-ar VẪN vào thẳng được khi cờ tắt — để còn phát triển và xem
    // thử. Cờ chỉ quyết định có dẫn khách tới đó hay không.
    // ------------------------------------------------------------------
    'nav_enabled' => false,

    // ------------------------------------------------------------------
    // GỌNG THỬ
    //
    // 'anchor' là tỉ lệ ngang giữa hai bản lề trên ảnh PNG (0..1). Dùng để
    // quy đổi khoảng cách hai mắt đo được sang bề rộng gọng cần vẽ. Ảnh nào
    // gọng chiếm gần trọn bề ngang thì anchor gần 1.
    // ------------------------------------------------------------------
    'frames' => [
        [
            'id'        => 'ar-titanium',
            'slug'      => 'gong-kinh-titan-vin-t01',
            'name'      => 'Gọng Titanium VE-T210',
            'brand'     => 'Vin Eyewear',
            'material'  => 'Titanium',
            'size'      => '52-18-140',
            'shape'     => 'Square',
            'image'     => '/assets/images/ar/frame-titanium.png',
            'anchor'    => 0.92,
        ],
        [
            'id'        => 'ar-acetate',
            'slug'      => 'gong-kinh-acetate-vin-a02',
            'name'      => 'Gọng Acetate VE-A305',
            'brand'     => 'Vin Eyewear',
            'material'  => 'Acetate',
            'size'      => '50-20-145',
            'shape'     => 'Round',
            'image'     => '/assets/images/ar/frame-acetate.png',
            'anchor'    => 0.92,
        ],
        [
            'id'        => 'ar-rayban',
            'slug'      => 'kinh-mat-polarized-vin-s03',
            'name'      => 'Ray-Ban Aviator RB3025',
            'brand'     => 'Ray-Ban',
            'material'  => 'Kim loại',
            'size'      => '58-14-135',
            'shape'     => 'Aviator',
            'image'     => '/assets/images/ar/frame-aviator.png',
            'anchor'    => 0.94,
        ],
        [
            'id'        => 'ar-bolon',
            'slug'      => 'kinh-mat-mat-meo-vin-s04',
            'name'      => 'Bolon Cat-eye BL5062',
            'brand'     => 'Bolon',
            'material'  => 'Acetate',
            'size'      => '54-17-142',
            'shape'     => 'Cat-eye',
            'image'     => '/assets/images/ar/frame-cateye.png',
            'anchor'    => 0.92,
        ],
    ],

    // ------------------------------------------------------------------
    // MÀU GỌNG — đổi màu bằng CSS filter trên chính ảnh PNG.
    //
    // Cách này không cần ảnh riêng cho từng màu (4 gọng × 5 màu = 20 file),
    // đổi lại màu chỉ gần đúng chứ không chính xác tuyệt đối.
    // ------------------------------------------------------------------
    'colors' => [
        ['id' => 'origin',  'label' => 'Nguyên bản', 'filter' => 'none', 'swatch' => '#8b8b8b'],
        ['id' => 'black',   'label' => 'Đen nhám',   'filter' => 'grayscale(1) brightness(0.5)', 'swatch' => '#1a1214'],
        ['id' => 'gold',    'label' => 'Vàng gold',  'filter' => 'sepia(1) saturate(2.4) hue-rotate(-12deg)', 'swatch' => '#c9962f'],
        ['id' => 'crimson', 'label' => 'Đỏ đô',      'filter' => 'sepia(1) saturate(4) hue-rotate(-32deg) brightness(0.85)', 'swatch' => '#801a20'],
        ['id' => 'blue',    'label' => 'Xanh navy',  'filter' => 'sepia(1) saturate(3) hue-rotate(165deg)', 'swatch' => '#22355c'],
    ],

    // ------------------------------------------------------------------
    // HIỆU ỨNG TRÒNG — filter áp lên khung hình camera, mô phỏng cảm giác
    // nhìn qua từng loại tròng.
    // ------------------------------------------------------------------
    'lens_effects' => [
        ['id' => 'clear',  'label' => 'Tròng trong suốt',     'desc' => 'Sắc nét và tự nhiên.',                       'filter' => 'brightness(1)'],
        ['id' => 'blue',   'label' => 'Chống ánh sáng xanh',  'desc' => 'Giảm chói màn hình, dễ chịu khi dùng máy tính.', 'filter' => 'contrast(1.05) saturate(1.05)'],
        ['id' => 'tinted', 'label' => 'Tròng mát',            'desc' => 'Giảm sáng, vẻ ngoài thời thượng.',            'filter' => 'brightness(0.92) saturate(1.2)'],
    ],

    // Cỡ gọng — hệ số nhân lên bề rộng tính tự động từ khoảng cách hai mắt
    'sizes' => [
        ['id' => 'S', 'label' => 'Nhỏ',        'scale' => 0.92],
        ['id' => 'M', 'label' => 'Trung bình', 'scale' => 1.00],
        ['id' => 'L', 'label' => 'Lớn',        'scale' => 1.08],
    ],

    // Lời khuyên theo dáng khuôn mặt đoán được
    'face_advice' => [
        'Round'  => 'Gọng vuông, góc cạnh sẽ tạo sự cân đối và nổi bật.',
        'Oval'   => 'Gọng chữ nhật hoặc phi công sẽ tôn chiều dài khuôn mặt.',
        'Square' => 'Gọng tròn hoặc mềm mại giúp làm dịu các đường góc.',
        'Heart'  => 'Gọng mắt mèo hoặc viền nhẹ giúp cân bằng trán và cằm.',
    ],

    // ------------------------------------------------------------------
    // THƯ VIỆN NHẬN DIỆN KHUÔN MẶT
    //
    // Ghim phiên bản cụ thể, không dùng 'latest': bản mới có thể đổi API và
    // làm hỏng trang mà không ai chạm vào code.
    //
    // Model ~3,7MB nên CHỈ tải khi người dùng bấm bật camera — xem
    // assets/js/ar-tryon.js, hàm loadDetector().
    // ------------------------------------------------------------------
    'vision' => [
        'bundle' => 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.18/vision_bundle.mjs',
        'wasm'   => 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.18/wasm',
        'model'  => 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task',
    ],
];
