<?php

/**
 * config/company.php
 *
 * Thông tin pháp lý, liên hệ và dữ liệu uy tín của doanh nghiệp.
 * Port từ src/lib/trust-data.ts của bản Lovable.
 *
 * NGUỒN DUY NHẤT cho hotline, email, địa chỉ — header, footer, trang liên hệ
 * và thanh liên hệ nổi đều đọc từ đây. Trước khi port, số hotline bị gõ cứng
 * rải rác trong nhiều file; đổi số là phải đi tìm từng chỗ.
 *
 * Lưu ý phân biệt với bảng `stores` trong DB: bảng đó là dữ liệu vận hành
 * (giờ mở cửa, bản đồ, còn hoạt động hay không) do admin sửa qua trang quản
 * trị. Mảng 'stores' dưới đây chỉ là chuỗi địa chỉ rút gọn để in ở chân
 * trang, không cần truy vấn DB chỉ để hiện footer.
 */

return [
    'name'         => 'CÔNG TY TNHH VIN EYEWEAR VIỆT NAM',
    'short_name'   => 'Vin Eyewear',
    'license'      => 'GPKD số 0109876543 – Sở KH&ĐT TP. Hà Nội',
    'tax_code'     => '0109876543',

    'hotline'      => '1900 6868',
    // Bỏ khoảng trắng: thuộc tính href của tel: không được chứa dấu cách
    'hotline_href' => 'tel:19006868',
    'email'        => 'cskh@vineyewear.vn',

    // Giờ mở cửa chung cho cả hai cơ sở — hiện ở cột "Liên hệ" dưới chân trang.
    // Giờ RIÊNG của từng cơ sở nằm ở cột open_hours bảng `stores`.
    'open_hours'   => '8:30 – 21:30, cả tuần',

    'stores' => [
        'Cơ sở 1: 46 Hoàng Hoa Thám, phường Tây Hồ, TP. Hà Nội',
        'Cơ sở 2: 261 Ngọc Lâm, phường Bồ Đề, TP. Hà Nội',
    ],

    // Kênh liên hệ nhanh — dùng ở thanh nổi góc màn hình
    'channels' => [
        'hotline'   => 'tel:19006868',
        'zalo'      => 'https://zalo.me/19006868',
        'messenger' => 'https://m.me/vineyewear',
    ],

    // Báo chí nhắc tới — hiện ở trang chủ. Port từ PRESS_MENTIONS (trust-data.ts).
    'press' => [
        [
            'outlet' => 'VnExpress',
            'topic'  => 'Chuyên đề chăm sóc thị lực',
            'url'    => 'https://vnexpress.net',
            'quote'  => 'Vin Eyewear đầu tư phòng khúc xạ 14 bước, giúp người dùng kiểm soát '
                      . 'độ mắt thay vì chỉ mua gọng theo cảm tính.',
        ],
        [
            'outlet' => 'Báo Đầu tư',
            'topic'  => 'Bán lẻ & tiêu dùng',
            'url'    => 'https://baodautu.vn',
            'quote'  => 'Chuỗi kính mắt Hà Nội mở rộng dịch vụ đo khám miễn phí, hợp tác cùng '
                      . 'các hãng tròng kính hàng đầu thế giới.',
        ],
        [
            'outlet' => 'Nhịp sống Kinh tế',
            'topic'  => 'Chuyển đổi số ngành optical',
            'url'    => 'https://nhipsongkinhte.vn',
            'quote'  => 'Công nghệ thử kính AR và hồ sơ khúc xạ số hoá là cách Vin Eyewear giữ '
                      . 'khách quay lại sau mỗi 12 tháng.',
        ],
    ],

    // Công nghệ tròng kính của đối tác. Port từ LENS_TECH (trust-data.ts).
    'lens_tech' => [
        [
            'brand'   => 'Essilor',
            'name'    => 'Stellest',
            'tagline' => 'Kiểm soát tiến triển độ cận cho trẻ em',
            'specs'   => ['Giảm tiến triển cận tới 67%', 'H.A.L.T 11 vành vi thấu kính', 'Chống UV & tia xanh'],
        ],
        [
            'brand'   => 'Essilor',
            'name'    => 'Crizal Rock',
            'tagline' => 'Lớp phủ chống xước & chống chói bền bậc nhất',
            'specs'   => ['Chống xước Scratch Shield', 'Chống chói ban đêm', 'Kháng bụi, dễ vệ sinh'],
        ],
        [
            'brand'   => 'Transitions',
            'name'    => 'GenS',
            'tagline' => 'Đổi màu thế hệ mới, sẫm nhanh – nhạt nhanh',
            'specs'   => ['Đổi màu trong 25 giây', 'Chặn 100% UVA/UVB', '8 tuỳ chọn màu sắc'],
        ],
        [
            'brand'   => 'Zeiss',
            'name'    => 'Visufit 1000',
            'tagline' => 'Quét khuôn mặt 3D, đo thông số lắp kính chính xác',
            'specs'   => ['9 camera quét 180°', 'Đo PD & độ cao tâm mắt', 'Thử gọng ảo tại cửa hàng'],
        ],
    ],

    'socials' => [
        ['label' => 'Facebook Vin Eyewear',  'href' => 'https://facebook.com/vineyewear',  'icon' => 'facebook'],
        ['label' => 'Instagram Vin Eyewear', 'href' => 'https://instagram.com/vineyewear', 'icon' => 'instagram'],
        ['label' => 'YouTube Vin Eyewear',   'href' => 'https://youtube.com/@vineyewear',  'icon' => 'youtube'],
        // TikTok chứ không phải Messenger: bốn icon trong "Vin Eyewear
        // Home.dc.html" là Facebook · Instagram · YouTube · TikTok.
        ['label' => 'TikTok Vin Eyewear',    'href' => 'https://tiktok.com/@vineyewear',   'icon' => 'tiktok'],
    ],
];
