<?php

/**
 * kiem-tra-db.php — CHẨN ĐOÁN KẾT NỐI MYSQL. XOÁ NGAY SAU KHI XEM XONG.
 *
 * install.php chỉ nói được "không kết nối được" vì core/Database.php cố ý
 * nuốt thông điệp gốc của PDO (nó chứa host, tên DB, đôi khi cả user — không
 * để lọt ra trình duyệt ở production).
 *
 * File này mở kết nối THỦ CÔNG để lấy đúng MÃ LỖI của MySQL, vì mỗi mã chỉ
 * đích danh một nguyên nhân khác nhau:
 *
 *     2002 / 2005  không tới được máy chủ  -> DB_HOST sai
 *     1045         máy chủ trả lời nhưng từ chối -> DB_USER / DB_PASS sai
 *     1049         user đúng, database không tồn tại -> DB_NAME sai hoặc chưa tạo
 *
 * Mẹo then chốt: thử kết nối LẦN MỘT không kèm tên database. Qua được bước đó
 * nghĩa là host + user + mật khẩu đều đúng, và khi ấy SHOW DATABASES in ra
 * đúng tên database thật mà tài khoản này có — hết phải đoán.
 *
 * KHÔNG in mật khẩu, chỉ in độ dài.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

define('ROOT_PATH',   __DIR__);
define('APP_PATH',    ROOT_PATH . '/app');
define('CORE_PATH',   ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VIEWS_PATH',  APP_PATH . '/views');

require_once CORE_PATH . '/App.php';
App::boot();

$expected = (string) env('INSTALL_TOKEN', '');
$given    = (string) ($_GET['token'] ?? '');

if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    exit("Thiếu hoặc sai token. Mở kèm ?token=<INSTALL_TOKEN trong .env>\n");
}

$host = (string) config('database.host');
$port = (string) config('database.port');
$name = (string) config('database.name');
$user = (string) config('database.user');
$pass = (string) config('database.pass');

echo "GIÁ TRỊ ĐANG DÙNG (đọc từ .env trên hosting)\n";
echo str_repeat('-', 62), "\n";
printf("DB_HOST  %s\n", $host);
printf("DB_PORT  %s\n", $port);
printf("DB_NAME  %s\n", $name);
printf("DB_USER  %s\n", $user);
printf("DB_PASS  (%d ký tự)\n\n", strlen($pass));

// --- 1. Tên host có phân giải được từ chính máy chủ này không? -------------
$ip = gethostbyname($host);
echo "1. Phân giải tên host\n";
echo $ip !== $host
    ? "   ✓ $host -> $ip\n\n"
    : "   ✗ Không phân giải được '$host'.\n"
    . "     => DB_HOST gõ sai. Lấy lại đúng chuỗi ở panel -> MySQL Databases.\n\n";

// --- 2. Kết nối KHÔNG kèm tên database ------------------------------------
echo "2. Kết nối tới máy chủ MySQL (chưa chọn database)\n";
$pdo = null;
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
    );
    echo "   ✓ Đăng nhập được. Host + user + mật khẩu ĐỀU ĐÚNG.\n\n";
} catch (PDOException $e) {
    $code = (string) $e->getCode();
    echo "   ✗ Lỗi [$code]: ", $e->getMessage(), "\n";
    echo match (true) {
        str_contains($code, '2002'), str_contains($code, '2005')
            => "     => Không tới được máy chủ: DB_HOST sai.\n",
        str_contains($code, '1045')
            => "     => Máy chủ trả lời nhưng từ chối: DB_USER hoặc DB_PASS sai.\n"
             . "        Trên InfinityFree, DB_PASS chính là mật khẩu TÀI KHOẢN HOSTING.\n",
        default => "     => Xem mã lỗi ở đầu file này để tra nguyên nhân.\n",
    };
    exit("\nDừng ở đây — sửa xong tải lại trang.\n");
}

// --- 3. Tài khoản này thật sự có những database nào? ----------------------
echo "3. Database mà tài khoản này đang có\n";
$found = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
$mine  = array_values(array_filter(
    $found,
    static fn ($d): bool => !in_array($d, ['information_schema', 'mysql', 'performance_schema', 'sys'], true)
));

if ($mine === []) {
    echo "   ✗ KHÔNG CÓ database nào.\n";
    exit("\n     => Chưa tạo database. Vào panel -> MySQL Databases -> tạo mới,\n"
       . "        rồi sửa DB_NAME trong .env cho khớp tên panel cấp.\n");
}

foreach ($mine as $d) {
    echo '   ', ($d === $name ? '✓' : ' '), ' ', $d, ($d === $name ? '   <- khớp DB_NAME' : ''), "\n";
}

if (!in_array($name, $mine, true)) {
    echo "\n   ✗ DB_NAME '$name' KHÔNG nằm trong danh sách trên.\n";
    exit("     => Sửa DB_NAME trong .env thành đúng một tên ở trên.\n");
}

// --- 4. Mở đúng database và đếm bảng --------------------------------------
echo "\n4. Bảng trong '$name'\n";
$pdo->exec('USE `' . str_replace('`', '``', $name) . '`');
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

if ($tables === []) {
    echo "   ✗ Database rỗng, chưa có bảng nào.\n";
    exit("     => phpMyAdmin -> chọn '$name' -> tab Import -> database/schema.sql\n");
}

echo '   ✓ ', count($tables), " bảng: ", implode(', ', array_slice($tables, 0, 8)),
     count($tables) > 8 ? ', …' : '', "\n";

$need    = ['users', 'profiles', 'user_roles', 'prescriptions', 'categories',
            'products', 'events', 'stores', 'favorites', 'appointments',
            'orders', 'order_items', 'contact_requests', 'remember_tokens',
            'password_resets'];
$missing = array_diff($need, $tables);

echo $missing === []
    ? "\nTẤT CẢ ĐỀU ỔN. Chạy install.php để tạo tài khoản quản trị, rồi XOÁ CẢ HAI FILE.\n"
    : "\n   ✗ Còn thiếu: " . implode(', ', $missing) . "\n"
    . "     => Database mới: import database/schema.sql.\n"
    . "        Database đã có dữ liệu: chạy file trong database/migrations/.\n";
