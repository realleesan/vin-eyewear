<?php

/**
 * config/zalo.php
 *
 * Đẩy thông báo lịch hẹn qua Zalo — xem core/Zalo.php để biết luồng và giới hạn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TÍNH NĂNG NÀY CHƯA GỬI ĐƯỢC TIN THẬT
 *
 * Zalo không có API kiểu "gửi tin tới một số điện thoại" dùng ngay. Muốn gửi
 * thật cần Official Account đã xác thực, một mẫu tin ZNS được Zalo duyệt, và
 * access_token của OA (token hết hạn theo giờ, phải làm mới bằng refresh_token).
 * Cả ba đều là việc đăng ký với Zalo, không phải việc viết mã.
 *
 * Chưa cấu hình thì thông báo chỉ ghi ra error log; lịch hẹn vẫn đặt bình
 * thường. Chỗ cắm là đúng một hàm — Zalo::send().
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * VÌ SAO SỐ CỬA HÀNG Ở ĐÂY MÀ KHÔNG Ở config/company.php: company.php là thông
 * tin CÔNG KHAI, in ra chân trang và trang liên hệ. Số này là ĐỊA CHỈ NHẬN
 * THÔNG BÁO NỘI BỘ — nó có thể là số của máy trực, không nhất thiết là số khách
 * nhìn thấy. Trộn hai thứ thì đổi số trực một cái là đổi luôn số in trên web.
 */

return [
    /*
     * Số Zalo của cửa hàng — nơi nhận mọi thông báo lịch hẹn.
     *
     * Nhận đủ dạng "0366 599 711", "+84366599711", "84366599711":
     * Zalo::normalize() đưa hết về 0xxxxxxxxx qua normalizePhone().
     *
     * Muốn TẮT hẳn việc báo cho cửa hàng thì xoá con số mặc định ngay dưới đây,
     * không phải để trống biến trong .env: env() quy ước một biến rỗng nghĩa là
     * "không khai" và trả về đúng giá trị mặc định này. Khi không còn số nào,
     * Zalo::shopPhone() ghi một dòng vào error log rồi thôi — thà vậy còn hơn
     * lặng lẽ gửi vào hư không.
     */
    'shop_phone' => env('ZALO_SHOP_PHONE', '0366599711'),

    /*
     * Có gửi cho CHÍNH KHÁCH nữa không?
     *
     * Yêu cầu của cửa hàng ghi "và gửi cả cho Zalo của khách hàng nếu được" —
     * chữ "nếu được" là có lý do: ZNS gửi cho khách tính phí theo từng tin và
     * cần một mẫu riêng đã duyệt, khác mẫu gửi nội bộ. Nên mặc định TẮT, bật
     * khi đã đăng ký xong mẫu đó và chấp nhận chi phí.
     *
     * Bật khi chưa cắm nhà cung cấp cũng không hại gì: tin chỉ đi vào log.
     */
    'notify_customer' => (bool) env('ZALO_NOTIFY_CUSTOMER', false),

    /*
     * access_token của Official Account.
     *
     * ⚠️ ĐỂ TRONG .env, KHÔNG BAO GIỜ COMMIT. Token này gửi được tin dưới danh
     * nghĩa cửa hàng.
     *
     * Token ZNS hết hạn sau ít giờ và phải làm mới bằng refresh_token, nên khi
     * cắm thật thì .env KHÔNG còn là chỗ đủ: cần một chỗ ghi được token mới
     * (một bảng, hoặc một file trong storage/). Ghi ra đây để lần sửa ấy không
     * bắt đầu bằng việc phát hiện lại điều này.
     */
    'access_token' => env('ZALO_OA_ACCESS_TOKEN', ''),

    /*
     * Mã mẫu tin ZNS đã được Zalo duyệt.
     *
     * Một mẫu cho tin báo nội bộ (gửi cửa hàng), một cho tin gửi khách nếu bật
     * 'notify_customer'. Tên tham số trong mẫu do Zalo duyệt quyết định, nên
     * phần dựng thân tin trong Zalo::send() phải viết cùng lúc với việc đăng ký
     * mẫu — đừng đoán trước.
     */
    'template_shop'     => env('ZALO_ZNS_TEMPLATE_SHOP', ''),
    'template_customer' => env('ZALO_ZNS_TEMPLATE_CUSTOMER', ''),
];
