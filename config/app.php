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

    // Các khung giờ nhận lịch hẹn — dùng chung cho form đặt lịch và trang
    // quản trị, nên chỉ khai báo một lần tại đây.
    'time_slots' => [
        '08:00', '09:00', '10:00', '11:00',
        '14:00', '15:00', '16:00', '17:00',
        '18:00', '19:00', '20:00',
    ],
];
