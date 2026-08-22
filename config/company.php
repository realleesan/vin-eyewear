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

    /*
     * SỐ ZALO CỦA CỬA HÀNG — dạng ĐỌC ĐƯỢC, để in ra màn hình.
     *
     * TÁCH KHỎI HOTLINE, và không suy ra từ nó được: 1900 6868 là đầu số tổng
     * đài, mà đầu số 1900 thì KHÔNG đăng ký Zalo được. Trước bản này liên kết
     * Zalo dựng thẳng từ hotline (zalo.me/19006868) nên nó dẫn vào một trang
     * trống — hỏng ở cả thanh liên hệ nổi, trang xác nhận đơn lẫn nút "đổi/huỷ
     * đơn" trong trang tài khoản.
     *
     * In số ra CHỮ chứ không chỉ để trong liên kết: bấm nút Zalo trên máy tính
     * bàn không phải lúc nào cũng mở được ứng dụng, và khi đó khách cần đọc
     * được con số để tự tìm.
     *
     * KHÁC với 'shop_phone' trong config/zalo.php: số đó là ĐỊA CHỈ NHẬN THÔNG
     * BÁO NỘI BỘ (đơn mới, lịch hẹn), có thể là số máy trực. Hai số hôm nay
     * trùng nhau, nhưng phải khai riêng — xem ghi chú đầu config/zalo.php.
     */
    'zalo'         => '0366 599 711',

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
        'branch'  => 'VCB Thăng Long – PGD Thuỵ Khuê',
        'number'  => '1007128686',
        /*
         * TÊN CHỦ TÀI KHOẢN PHẢI KHỚP TỪNG CHỮ VỚI TÊN NGÂN HÀNG GHI.
         *
         * Đây là tài khoản HỘ KINH DOANH, không phải tài khoản công ty — trước
         * bản này ô này ghi "CONG TY TNHH VIN EYEWEAR VIET NAM" trong khi ngân
         * hàng ghi tên hộ kinh doanh.
         *
         * Vì sao lệch một cái tên lại đáng sửa: mã QR chỉ mang SỐ tài khoản,
         * còn TÊN thì app ngân hàng tự tra từ phía ngân hàng rồi hiện ra. Nên
         * khách quét mã sẽ thấy web ghi một tên, app hiện một tên khác — đúng
         * giây họ sắp bấm "Chuyển". Tiền vẫn tới đúng nơi, nhưng phần lớn người
         * ta sẽ dừng lại vì tưởng bị lừa.
         *
         * IN HOA, KHÔNG DẤU: chép đúng cách ngân hàng hiển thị.
         */
        'holder'  => 'HO KINH DOANH PHAM TIEN MANH',

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

    /* Kênh liên hệ nhanh — thanh nổi góc màn hình, nút "đổi/huỷ đơn" trong
       trang tài khoản, và dòng liên hệ ở trang xác nhận đơn.

       Địa chỉ Zalo phải khớp số ở 'zalo' bên trên, chỉ khác cách viết (bỏ dấu
       cách). Cùng lối với cặp hotline / hotline_href. */
    'channels' => [
        'hotline'   => 'tel:19006868',
        'zalo'      => 'https://zalo.me/0366599711',
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
