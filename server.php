<?php

/**
 * server.php — router script cho server built-in của PHP (chỉ dùng khi phát triển).
 *
 *     php -S localhost:8000 server.php
 *
 * BẮT BUỘC truyền file này. Chạy `php -S localhost:8000` trần sẽ PHỤC VỤ
 * NGUYÊN VĂN mọi file có thật trong thư mục dự án — kể cả .env chứa mật khẩu
 * database, database/schema.sql, và toàn bộ mã nguồn trong core/.
 * Server built-in không đọc .htaccess nên các luật chặn bên đó không có tác dụng.
 *
 * File này làm đúng việc mà .htaccess làm trên Apache:
 *   1. Chặn file nhạy cảm
 *   2. Cho qua tài nguyên tĩnh trong assets/
 *   3. Còn lại đẩy hết về index.php
 *
 * KHÔNG dùng cho production. Trên server thật, Apache + .htaccess lo phần này.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . ltrim($path, '/');

// ---------------------------------------------------------------------------
// 1. CHẶN
// ---------------------------------------------------------------------------

/** Thư mục mã nguồn — không bao giờ phục vụ trực tiếp. */
$blockedDirs = ['app', 'core', 'config', 'database', 'docs', 'scripts', 'errors', 'storage'];

/** Đuôi file không bao giờ phục vụ trực tiếp. */
$blockedExts = ['php', 'sql', 'md', 'sh', 'log', 'lock', 'example', 'ini',
                'yml', 'yaml', 'bak', 'old', 'orig', 'swp', 'dist'];

$segments  = array_values(array_filter(explode('/', $path), 'strlen'));
$firstSeg  = $segments[0] ?? '';
$basename  = basename($path);
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

$blocked =
    // Mọi file/thư mục bắt đầu bằng dấu chấm: .env, .git, .htaccess…
    str_starts_with($basename, '.')
    || $firstSeg !== '' && str_starts_with($firstSeg, '.')
    // Thư mục mã nguồn
    || in_array($firstSeg, $blockedDirs, true)
    // Đuôi file cấm — trừ index.php ở gốc, vốn là điểm vào hợp lệ
    // install.php là điểm vào hợp lệ thứ hai (cài đặt trên hosting không có
    // SSH). Nó tự bảo vệ bằng token + kiểm đã có admin chưa — xem đầu file đó.
    || (in_array($extension, $blockedExts, true)
        && $path !== '/index.php' && $path !== '/install.php');

if ($blocked) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found\n";
    return true;
}

// ---------------------------------------------------------------------------
// 2. TÀI NGUYÊN TĨNH
//
// Trả false -> server built-in tự phục vụ file, tự đặt Content-Type.
// Chỉ áp dụng cho file CÓ THẬT bên trong assets/, không nơi nào khác.
// ---------------------------------------------------------------------------

if ($firstSeg === 'assets') {
    $file = realpath(__DIR__ . $path);

    // realpath() + kiểm tiền tố chặn path traversal:
    // /assets/../../etc/passwd sẽ không lọt ra ngoài thư mục dự án.
    if ($file !== false
        && is_file($file)
        && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR)) {
        return false;
    }

    http_response_code(404);
    return true;
}

// ---------------------------------------------------------------------------
// 3. BỘ CÀI ĐẶT
//
// require thẳng, KHÔNG dùng `return false`: với file .php, "trả về tài nguyên
// nguyên trạng" có thể nghĩa là gửi MÃ NGUỒN ra trình duyệt. require thì chắc
// chắn nó được THỰC THI.
//
// Trên Apache (hosting thật) nhánh này không bao giờ chạy tới — Apache tự
// thực thi install.php vì đó là file có thật, luật rewrite chỉ áp cho đường
// dẫn không tồn tại.
// ---------------------------------------------------------------------------

if ($path === '/install.php') {
    require __DIR__ . '/install.php';
    return true;
}

// ---------------------------------------------------------------------------
// 4. CÒN LẠI -> ứng dụng
// ---------------------------------------------------------------------------

require __DIR__ . '/index.php';
