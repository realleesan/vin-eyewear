<?php

/**
 * config/lang.php — gom các bảng dịch lại cho config() đọc được.
 *
 * config() nạp theo tên file và tra bằng dấu chấm, nên t() lấy chuỗi qua
 * config('lang.en.nav.home'). Mỗi ngôn ngữ vẫn là MỘT file riêng trong
 * config/lang/ — thêm ngôn ngữ chỉ cần thêm file rồi thêm một dòng ở đây,
 * và khai thêm mã đó vào Lang::CODES (core/helpers.php).
 */

return [
    'vi' => require __DIR__ . '/lang/vi.php',
    'en' => require __DIR__ . '/lang/en.php',
];
