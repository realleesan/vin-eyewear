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

    /*
     * TÀI KHOẢN NHẬN CHUYỂN KHOẢN
     *
     * ⚠️ SỐ LIỆU MẪU — PHẢI THAY BẰNG TÀI KHOẢN THẬT TRƯỚC KHI CHẠY THẬT.
     * Để trống 'number' thì trang xác nhận đơn và thẻ đơn hàng tự ẩn khối chuyển
     * khoản đi và quay về câu "nhân viên sẽ gọi và đọc thông tin" — thà vậy còn
     * hơn hiện một số tài khoản sai.
     *
     * NỘI DUNG CHUYỂN KHOẢN LUÔN LÀ MÃ ĐƠN, không phải tên khách. Đây là quy ước
     * để việc đối chiếu sao kê làm được: nhân viên đọc nội dung là ra đúng đơn.
     * Nó cũng chính là thứ cổng thanh toán (SePay…) dùng để tự khớp giao dịch
     * với đơn sau này, nên đừng đổi sang gì khác.
     */
    'bank' => [
        'name'    => 'Vietcombank',
        'branch'  => 'CN Tây Hồ, Hà Nội',
        'number'  => '0491000153708',
        'holder'  => 'CONG TY TNHH VIN EYEWEAR VIET NAM',

        /*
         * MÃ NGÂN HÀNG THEO CHUẨN NAPAS (BIN) — dùng để dựng ảnh mã QR ở màn
         * "Thanh toán QR" (/thanh-toan/chuyen-khoan).
         *
         * 970436 = Vietcombank. Đổi ngân hàng thì phải đổi CẢ 'name' và mã này;
         * tra bảng mã tại https://qr.sepay.vn/banks.json (cột `bin`).
         *
         * ⚠️ NGÂN HÀNG PHẢI CÓ `supported: true` TRONG BẢNG ĐÓ. SePay chỉ đọc
         * được biến động số dư của những ngân hàng nó kết nối; chọn ngân hàng
         * ngoài danh sách thì mã QR vẫn hiện nhưng đơn không bao giờ tự xác
         * nhận, và không có gì báo cho ai biết vì sao.
         *
         * Để TRỐNG thì màn QR tự bỏ ảnh mã đi và chỉ in số tài khoản — thà vậy
         * còn hơn hiện một mã QR trỏ sai nơi nhận tiền.
         */
        'bin'     => '970436',
    ],

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
