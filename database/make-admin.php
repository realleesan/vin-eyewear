<?php

/**
 * database/make-admin.php
 *
 * Quản lý tài khoản có quyền quản trị.
 *
 *     php database/make-admin.php <email> [admin|manager|staff]
 *         Cấp vai trò cho tài khoản. Chưa có thì tạo mới kèm mật khẩu ngẫu nhiên.
 *         Tài khoản đã có: CHỈ thêm vai trò, KHÔNG đụng tới mật khẩu.
 *
 *     php database/make-admin.php --reset-password <email>
 *         Đặt lại mật khẩu thành chuỗi ngẫu nhiên mới.
 *         Đây là cách phục hồi khi quên mật khẩu admin — KHÔNG chạy lại
 *         setup.sh, vì script đó nạp lại schema và sẽ xoá sạch dữ liệu.
 *
 * Mật khẩu luôn do script sinh và in ra ĐÚNG MỘT LẦN, không bao giờ nhận qua
 * tham số dòng lệnh: mọi tiến trình trên máy đọc được đối số qua `ps`, và nó
 * còn nằm lại trong lịch sử shell.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('ROOT_PATH',   dirname(__DIR__));
define('APP_PATH',    ROOT_PATH . '/app');
define('CORE_PATH',   ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VIEWS_PATH',  APP_PATH . '/views');

require_once CORE_PATH . '/App.php';
App::boot();

const ALLOWED_ROLES = ['admin', 'manager', 'staff'];

/**
 * Sinh mật khẩu ngẫu nhiên chỉ gồm chữ và số.
 *
 * random_bytes() lấy từ nguồn ngẫu nhiên của hệ điều hành. Không dùng rand()
 * hay mt_rand(): chúng sinh dãy đoán được nếu biết seed.
 *
 * Bỏ ký tự đặc biệt để mật khẩu chép tay qua điện thoại hay đọc qua điện
 * thoại không bị nhầm — độ dài 20 ký tự chữ-số đã đủ mạnh (~119 bit).
 */
function randomPassword(int $length = 20): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max      = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        // random_int() phân bố đều, không lệch như `random_bytes() % 62`
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

/**
 * In mật khẩu trong khung viền, kèm cảnh báo chỉ hiện một lần.
 */
function printCredentials(string $email, string $password): void
{
    echo "\n";
    echo "  ┌──────────────────────────────────────────────────────────────┐\n";
    echo "  │  CHỈ HIỆN MỘT LẦN DUY NHẤT — CHÉP LẠI NGAY                   │\n";
    echo "  ├──────────────────────────────────────────────────────────────┤\n";
    printf("  │  Email     : %-47s │\n", $email);
    printf("  │  Mật khẩu  : %-47s │\n", $password);
    echo "  └──────────────────────────────────────────────────────────────┘\n\n";
    echo "  Script không lưu mật khẩu ở bất cứ đâu. Mất thì đặt lại bằng:\n";
    echo "      php database/make-admin.php --reset-password {$email}\n\n";
}

function usage(): never
{
    fwrite(STDERR, <<<TXT

    Cách dùng:
      php database/make-admin.php <email> [admin|manager|staff]
      php database/make-admin.php --reset-password <email>


    TXT);
    exit(1);
}

// ---------------------------------------------------------------------------
// Phân tích tham số
// ---------------------------------------------------------------------------
$args      = array_slice($argv, 1);
$resetMode = false;

if (($key = array_search('--reset-password', $args, true)) !== false) {
    $resetMode = true;
    unset($args[$key]);
    $args = array_values($args);
}

$email = $args[0] ?? null;
$role  = $args[1] ?? 'admin';

if ($email === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "\n  Email không hợp lệ" . ($email === null ? ' (thiếu tham số)' : ": {$email}") . "\n");
    usage();
}

if (!$resetMode && !in_array($role, ALLOWED_ROLES, true)) {
    fwrite(STDERR, "\n  Vai trò không hợp lệ: {$role}. Chọn: " . implode(', ', ALLOWED_ROLES) . "\n");
    usage();
}

// ---------------------------------------------------------------------------
// Thực thi
// ---------------------------------------------------------------------------
try {
    $existing = Database::fetchOne(
        'SELECT `id` FROM `users` WHERE `email` = :email',
        ['email' => $email]
    );

    // ----- Chế độ đặt lại mật khẩu -----
    if ($resetMode) {
        if ($existing === null) {
            fwrite(STDERR, "\n  ✗ Không tìm thấy tài khoản {$email}.\n"
                . "    Bỏ --reset-password để tạo tài khoản mới.\n\n");
            exit(1);
        }

        $password = randomPassword();

        Database::execute(
            'UPDATE `users` SET `password_hash` = :hash WHERE `id` = :id',
            ['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $existing['id']]
        );

        echo "\n✓ Đã đặt lại mật khẩu cho {$email}.\n";
        echo "  Vai trò và toàn bộ dữ liệu khác giữ nguyên.\n";
        printCredentials($email, $password);
        exit(0);
    }

    // ----- Tạo mới, hoặc cấp thêm vai trò -----
    if ($existing !== null) {
        $userId   = $existing['id'];
        $password = null;
    } else {
        $userId   = uuid();
        $password = randomPassword();

        // Transaction: tài khoản không có hồ sơ đi kèm là trạng thái hỏng —
        // hoặc cả hai cùng được tạo, hoặc không gì cả.
        Database::transaction(static function () use ($userId, $email, $password): void {
            Database::execute(
                'INSERT INTO `users` (`id`, `email`, `password_hash`, `email_verified`)
                 VALUES (:id, :email, :hash, 1)',
                [
                    'id'    => $userId,
                    'email' => $email,
                    'hash'  => password_hash($password, PASSWORD_DEFAULT),
                ]
            );

            Database::execute(
                'INSERT INTO `profiles` (`id`, `full_name`) VALUES (:id, :name)',
                ['id' => $userId, 'name' => 'Quản trị viên']
            );
        });
    }

    // INSERT IGNORE: chạy lại với cùng email và vai trò thì không báo lỗi,
    // nhờ ràng buộc UNIQUE (user_id, role).
    $added = Database::execute(
        'INSERT IGNORE INTO `user_roles` (`id`, `user_id`, `role`) VALUES (:id, :user_id, :role)',
        ['id' => uuid(), 'user_id' => $userId, 'role' => $role]
    );

    if ($password !== null) {
        echo "\n✓ Đã tạo tài khoản mới với vai trò '{$role}'.\n";
        printCredentials($email, $password);
    } elseif ($added > 0) {
        echo "\n✓ Đã cấp vai trò '{$role}' cho tài khoản có sẵn {$email}.\n";
        echo "  Mật khẩu giữ nguyên, không thay đổi.\n\n";
    } else {
        echo "\n• Tài khoản {$email} vốn đã có vai trò '{$role}'. Không có gì thay đổi.\n\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\n  ✗ Lỗi: " . $e->getMessage() . "\n\n");
    exit(1);
}
