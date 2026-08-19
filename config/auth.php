<?php

/**
 * config/auth.php — đăng nhập bằng tài khoản bên ngoài.
 *
 * Giá trị thật nằm trong .env, file này chỉ mô tả cấu trúc nên commit được.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LẤY HAI GIÁ TRỊ NÀY Ở ĐÂU
 *
 *   1. console.cloud.google.com -> tạo project (hoặc chọn project có sẵn)
 *   2. "APIs & Services" -> "OAuth consent screen": chọn External, điền tên
 *      ứng dụng và email liên hệ. Đang ở chế độ Testing thì CHỈ những tài
 *      khoản khai trong mục "Test users" đăng nhập được — nhớ bấm Publish khi
 *      muốn mở cho khách thật.
 *   3. "Credentials" -> Create credentials -> OAuth client ID -> Web application
 *   4. "Authorized redirect URIs" phải khớp TỪNG KÝ TỰ với giá trị 'redirect'
 *      bên dưới, kể cả http/https và có hay không dấu / ở cuối:
 *
 *          https://vreyewear.gt.tc/auth/google/callback
 *
 *      Chạy ở máy thì thêm một URI nữa cho địa chỉ local. Google cho khai
 *      nhiều URI trong cùng một client, không cần tạo client thứ hai.
 *   5. Chép Client ID và Client secret vào .env.
 *
 * Chưa điền thì nút "Tiếp tục với Google" KHÔNG hiện ra — xem
 * GoogleAuth::isConfigured(). Thà không có nút còn hơn có nút bấm vào ra trang
 * lỗi của Google.
 */

return [
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),

        /*
         * Dựng từ APP_URL chứ không khai riêng một biến nữa: hai giá trị cùng
         * nói về một địa chỉ mà tách làm hai chỗ thì sớm muộn cũng lệch, và
         * lệch ở đây thì Google từ chối với đúng một dòng "redirect_uri_mismatch"
         * không nói rõ bên nào sai.
         */
        'redirect'      => rtrim((string) env('APP_URL', ''), '/') . '/auth/google/callback',
    ],
];
