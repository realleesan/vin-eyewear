<?php

/**
 * Cấu hình gửi email.
 *
 * driver:
 *   log   ghi ra storage/mail/ thay vì gửi — mặc định khi phát triển
 *   mail  dùng hàm mail() của PHP (cần hosting có sendmail)
 *   smtp  nối tới máy chủ SMTP bên ngoài
 *
 * LƯU Ý VỀ INFINITYFREE (bản miễn phí): chặn cả hàm mail() lẫn cổng SMTP ra
 * ngoài. Trên hosting đó không chế độ nào gửi được, và luồng quên mật khẩu
 * sẽ tự chuyển sang đường nhân viên xử lý tay — xem app/views/admin/resets/.
 */

return [
    'driver'       => env('MAIL_DRIVER', 'log'),

    'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@vineyewear.vn'),
    'from_name'    => env('MAIL_FROM_NAME', 'Vin Eyewear'),

    // Chỉ dùng khi driver = smtp
    'host'         => env('MAIL_HOST', ''),
    'port'         => (int) env('MAIL_PORT', 587),
    'username'     => env('MAIL_USERNAME', ''),
    'password'     => env('MAIL_PASSWORD', ''),
    // tls = STARTTLS (cổng 587) · ssl = mã hoá ngay (cổng 465) · '' = không mã hoá
    'encryption'   => env('MAIL_ENCRYPTION', 'tls'),
];
