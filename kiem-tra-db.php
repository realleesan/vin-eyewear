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

/*
 * ─────────────────────────────────────────────────────────────────────────
 * LƯỚI AN TOÀN RIÊNG CHO TRANG NÀY — ĐÈ LÊN BỘ XỬ LÝ CHUNG CỦA core/App.php
 *
 * App::boot() cài một bộ bắt exception in ra errors/500.php ("Hệ thống đang
 * gặp sự cố") khi APP_DEBUG tắt — đúng cho mọi trang KHÁCH, sai hoàn toàn cho
 * trang này. Cả lý do tồn tại của file là ĐỌC ĐƯỢC MÃ LỖI; nuốt nó rồi thay
 * bằng một câu xin lỗi lịch sự là biến công cụ chẩn đoán thành thứ cần được
 * chẩn đoán.
 *
 * Đã dính thật: mở trang trên hosting ngày 26/08/2026 ra đúng trang "Hệ thống
 * đang gặp sự cố", không một chữ nào cho biết hỏng ở đâu.
 *
 * IN MESSAGE, KHÔNG IN STACK TRACE. Thông điệp của PDOException chỉ có mã lỗi
 * và câu chữ của MySQL. Còn getTraceAsString() in ra cả ĐỐI SỐ của lời gọi —
 * tức là mật khẩu CSDL nằm ở tham số thứ ba của new PDO(). Trang này có token
 * chặn, nhưng token đi trong URL nên nằm sẵn trong lịch sử trình duyệt và log
 * máy chủ; đừng đặt cược mật khẩu vào đó.
 * ─────────────────────────────────────────────────────────────────────────
 */
set_exception_handler(static function (Throwable $e): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    printf(
        "\n✗ DỪNG VÌ LỖI KHÔNG AI BẮT\n%s\n%s: %s\n   tại %s dòng %d\n",
        str_repeat('-', 62),
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
});

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
//
// function_exists() chứ không gọi thẳng: hosting chia sẻ hay tắt bớt hàm mạng
// qua disable_functions trong php.ini, và gọi một hàm đã bị tắt ném Error chứ
// không phải warning — cả trang chết ngay ở bước 1, trước khi thử được thứ
// thật sự cần biết là kết nối CSDL. Không phân giải được tên host cũng KHÔNG
// phải lỗi chí mạng: bước 2 vẫn nối thử được.
echo "1. Phân giải tên host\n";

if (!function_exists('gethostbyname')) {
    echo "   ? Hosting đã tắt gethostbyname() — bỏ qua bước này.\n\n";
} else {
    $ip = gethostbyname($host);
    echo $ip !== $host
        ? "   ✓ $host -> $ip\n\n"
        : "   ✗ Không phân giải được '$host'.\n"
        . "     => DB_HOST gõ sai. Lấy lại đúng chuỗi ở panel -> MySQL Databases.\n\n";
}

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
//
// SHOW DATABASES là quyền hosting chia sẻ HAY THU HỒI (nó để lộ tên database
// của khách khác trên cùng máy chủ). Bị từ chối thì PDO ném — mà bước này chỉ
// là tiện nghi "hết phải đoán tên", không phải phép kiểm bắt buộc. Nuốt lỗi,
// nói rõ đã bỏ qua, rồi đi tiếp xuống bước 4 — nơi câu trả lời thật nằm.
echo "3. Database mà tài khoản này đang có\n";
$mine = null;

try {
    $found = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
    $mine  = array_values(array_filter(
        $found,
        static fn ($d): bool => !in_array($d, ['information_schema', 'mysql', 'performance_schema', 'sys'], true)
    ));
} catch (PDOException $e) {
    echo "   ? Không liệt kê được [", $e->getCode(), ']: ', $e->getMessage(), "\n";
    echo "     (hosting chia sẻ hay chặn quyền này — không sao, đi tiếp bước 4.)\n\n";
}

if ($mine === null) {
    // Bỏ qua bước 3, nhảy thẳng sang mở database theo DB_NAME.
} elseif ($mine === []) {
    echo "   ✗ KHÔNG CÓ database nào.\n";
    exit("\n     => Chưa tạo database. Vào panel -> MySQL Databases -> tạo mới,\n"
       . "        rồi sửa DB_NAME trong .env cho khớp tên panel cấp.\n");
}

foreach ($mine ?? [] as $d) {
    echo '   ', ($d === $name ? '✓' : ' '), ' ', $d, ($d === $name ? '   <- khớp DB_NAME' : ''), "\n";
}

if ($mine !== null && !in_array($name, $mine, true)) {
    echo "\n   ✗ DB_NAME '$name' KHÔNG nằm trong danh sách trên.\n";
    exit("     => Sửa DB_NAME trong .env thành đúng một tên ở trên.\n");
}

// --- 4. Mở đúng database và đếm bảng --------------------------------------
echo "\n4. Bảng trong '$name'\n";
try {
    $pdo->exec('USE `' . str_replace('`', '``', $name) . '`');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Đây MỚI là lỗi chí mạng: không mở được đúng database thì không kiểm
    // được gì nữa. 1044/1049 là hai mã hay gặp và mỗi mã một nguyên nhân.
    $code = (string) $e->getCode();
    echo '   ✗ Lỗi [', $code, ']: ', $e->getMessage(), "\n";
    exit(str_contains($code, '1049')
        ? "     => DB_NAME '$name' không tồn tại. Lấy đúng tên ở panel -> MySQL Databases.\n"
        : "     => Tài khoản không có quyền trên '$name'. Đối chiếu DB_USER với database ở panel.\n");
}

if ($tables === []) {
    echo "   ✗ Database rỗng, chưa có bảng nào.\n";
    exit("     => phpMyAdmin -> chọn '$name' -> tab Import -> database/schema.sql\n");
}

echo '   ✓ ', count($tables), " bảng: ", implode(', ', array_slice($tables, 0, 8)),
     count($tables) > 8 ? ', …' : '', "\n";

$need    = ['users', 'profiles', 'user_roles', 'prescriptions', 'categories',
            'products', 'stores', 'favorites', 'appointments',
            'orders', 'order_items', 'contact_requests', 'remember_tokens',
            'password_resets'];
$missing = array_diff($need, $tables);

echo $missing === []
    ? "\nTẤT CẢ ĐỀU ỔN. Chạy install.php để tạo tài khoản quản trị, rồi XOÁ CẢ HAI FILE.\n"
    : "\n   ✗ Còn thiếu: " . implode(', ', $missing) . "\n"
    . "     => Database mới: import database/schema.sql.\n"
    . "        Database đã có dữ liệu: chạy file trong database/migrations/.\n";
