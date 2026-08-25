<?php

/**
 * config/app.php
 *
 * Cấu hình chung của ứng dụng. Giá trị thật lấy từ .env.
 */

return [
    'name'     => 'Vin Eyewear',
    'env'      => env('APP_ENV', 'production'),

    // Mặc định TẮT gỡ lỗi. Đặt mặc định là true sẽ khiến một server thật quên
    // tạo .env vô tình phơi cả stack trace lẫn thông số kết nối DB ra ngoài.
    'debug'    => env('APP_DEBUG', false),

    'url'      => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),

    // Thời gian sống cookie phiên, tính bằng giây. Mặc định 14 ngày.
    'session_lifetime' => (int) env('SESSION_LIFETIME', 1209600),

    // Số sản phẩm mỗi trang ở danh sách sản phẩm.
    'per_page' => 12,

    // Phí giao hàng cố định (VND) khi khách chọn giao tận nơi thay vì nhận
    // tại cửa hàng. Đặt ở đây để đổi một chỗ, không rải rác trong checkout.
    'shipping_fee' => 30000,

    // Đơn từ mức này trở lên được miễn phí giao hàng.
    // Khớp quy tắc trong createOrder của bản Lovable (shop.functions.ts):
    //   subtotal < 1.000.000đ  ->  thu 30.000đ
    'free_shipping_threshold' => 1000000,

    /*
     * TỶ LỆ ĐẶT CỌC, tính theo phần trăm tổng đơn.
     *
     * CHỈ áp cho đơn có CẮT TRÒNG THEO ĐỘ. Tròng mài riêng theo số đo của một
     * người thì không bán lại cho ai khác được, nên khách phải cọc trước —
     * cho cả COD lẫn chuyển khoản. Đơn chỉ mua gọng (gọng kèm tròng demo chưa
     * cắt độ) không cọc đồng nào.
     *
     * Tính trên TỔNG ĐƠN CUỐI CÙNG (tạm tính − giảm giá + phí ship), không
     * phải trên tiền hàng: nhờ vậy "cọc + còn lại = tổng cộng" đúng bằng phép
     * cộng, và ba con số trên trang thanh toán khớp nhau mà không cần chú
     * thích nào.
     *
     * Đổi số ở đây KHÔNG đổi tiền cọc của đơn đã đặt: số tiền được chốt vào
     * `orders.deposit_amount` lúc ghi đơn — xem OrderModel::place().
     */
    'deposit_rate' => 30,

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * ĐÃ BỎ: 'time_slots' và 'booking_change_cutoff_hours' — 2026-08-25
     *
     * Ghi lại ở đây vì cả hai đều phục vụ GIẢ ĐỊNH A5 trong CLAUDE.md, mà A5
     * chưa được BA nghiệm thu. Đảo lại quyết định thì đây là chỗ đầu tiên phải
     * dựng lại, nên để trống mà không nói gì là bắt người sau tự đi tìm.
     *
     *   'time_slots' — mười một khung giờ nhận lịch: 08:00–11:00 và 14:00–20:00.
     *      Khách không chọn giờ nữa nên không còn ai đọc mảng này.
     *
     *   'booking_change_cutoff_hours' => 2 — khách tự đổi/huỷ được tới trước
     *      GIỜ hẹn 2 tiếng. Con số ấy cần một mốc chính xác tới phút để trừ đi,
     *      mà mốc đó dựng từ ngày ghép với khung giờ. Không còn khung giờ thì
     *      không còn gì để trừ: luật nay là "sang tới ngày hẹn thì gọi tổng
     *      đài", một câu không có tham số nào. Xem BookingModel::changeBlocker.
     * ─────────────────────────────────────────────────────────────────────────
     */
];
