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

/*
 * Ở ĐÂY TỪNG CÓ randomPassword() — một bản sao y hệt của
 * UserModel::randomPassword().
 *
 * Bỏ đi khi khu quản trị có thêm màn "đặt lại mật khẩu cho nhân viên": lúc đó
 * thành BA nơi cấp mật khẩu do hệ thống sinh (script này, màn kia, và
 * UserModel), và hai định nghĩa rời sẽ lệch nhau vào lần ai đó đổi độ dài ở
 * một chỗ. Nay cả ba gọi cùng một hàm.
 */

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

        $password = UserModel::randomPassword();

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
        $password = UserModel::randomPassword();

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

        /*
         * CẢNH BÁO, KHÔNG PHẢI LỜI CHÚC MỪNG.
         *
         * Từ khi hai khu vực bị tách (xem khối "HAI KHU VỰC" ở đầu
         * AuthMiddleware, và mục 3.A.3 của SRS), một tài khoản có vai trò nội
         * bộ KHÔNG còn đăng nhập được ở /auth nữa. Cấp vai trò cho một tài
         * khoản khách đang dùng là ĐÓNG luôn tài khoản mua hàng của người đó:
         * đơn hàng cũ vẫn nằm trong cơ sở dữ liệu nhưng chính chủ không mở
         * được trang nào để xem.
         *
         * In ra ở đây vì đây là nơi DUY NHẤT việc ấy xảy ra, và người chạy
         * lệnh thường không nghĩ tới hệ quả đó — họ chỉ định "cho bạn này
         * quyền vào trang quản trị".
         */
        $donHang = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM `orders` WHERE `user_id` = :id',
            ['id' => $userId]
        );

        echo "  ⚠ Tài khoản này nay là TÀI KHOẢN NỘI BỘ, nên không đăng nhập\n";
        echo "    được ở cổng khách hàng (/auth) nữa — chỉ vào /quan-tri.\n";

        if ($donHang > 0) {
            echo "    Nó đang có {$donHang} đơn hàng. Chủ tài khoản sẽ không tự xem\n";
            echo "    lại được nữa; muốn tiếp tục mua hàng thì cần một tài khoản\n";
            echo "    khách RIÊNG, bằng email hoặc số điện thoại khác.\n";
        }

        echo "\n";
    } else {
        echo "\n• Tài khoản {$email} vốn đã có vai trò '{$role}'. Không có gì thay đổi.\n\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\n  ✗ Lỗi: " . $e->getMessage() . "\n\n");
    exit(1);
}
