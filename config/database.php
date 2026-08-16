<?php

/**
 * config/database.php
 *
 * Thông số kết nối MySQL. Giá trị thật lấy từ .env — file này chỉ mô tả
 * cấu trúc và giá trị mặc định, nên commit lên git được.
 */

return [
    'host'    => env('DB_HOST', '127.0.0.1'),
    'port'    => env('DB_PORT', '3306'),
    'name'    => env('DB_NAME', 'vin_eyewear'),
    'user'    => env('DB_USER', 'root'),
    'pass'    => env('DB_PASS', ''),

    // utf8mb4 (không phải 'utf8') mới lưu được đầy đủ 4 byte: emoji và một số
    // ký tự hiếm. 'utf8' của MySQL chỉ 3 byte và sẽ cắt cụt chúng.
    'charset' => 'utf8mb4',

    'options' => [
        // Lỗi SQL ném exception thay vì trả về false lặng lẽ. Không có dòng
        // này, một câu lệnh sai cú pháp sẽ đi tiếp và gây lỗi khó truy ở chỗ khác.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        // fetch() trả mảng kết hợp (['name' => ...]), không kèm bản sao đánh
        // số ([0] => ...) như mặc định BOTH — đỡ tốn bộ nhớ một nửa.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // false = dùng prepared statement THẬT của MySQL, không để PDO tự ghép
        // chuỗi ở phía PHP. Server tách hẳn câu lệnh khỏi dữ liệu, nên dữ liệu
        // người dùng không có đường nào trở thành cú pháp SQL.
        PDO::ATTR_EMULATE_PREPARES   => false,

        // Mỗi request mở và đóng kết nối riêng.
        //
        // Bật persistent sẽ tiết kiệm được bước bắt tay TCP, nhưng kết nối tái
        // dùng mang theo trạng thái của request trước: transaction chưa commit
        // khi script chết giữa chừng, biến phiên, khoá bảng. Với site này chi
        // phí mở kết nối là không đáng kể nên không đánh đổi.
        PDO::ATTR_PERSISTENT         => false,
    ],
];
