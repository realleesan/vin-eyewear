<?php

/**
 * install.php — cài đặt qua trình duyệt.
 *
 * Dành cho hosting KHÔNG có SSH (InfinityFree, cPanel free…), nơi không chạy
 * được `php database/make-admin.php`.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA LỚP BẢO VỆ — file này tạo tài khoản quản trị, nên phải kín
 *
 *   1. Cần INSTALL_TOKEN trong .env, và phải truyền đúng qua URL.
 *      Không đặt token thì file tự từ chối chạy.
 *   2. Đã có tài khoản admin thì DỪNG. Không thể dùng file này để lén thêm
 *      một admin thứ hai sau khi site đã chạy.
 *   3. So token bằng hash_equals() — so bằng '==' sẽ dừng ở ký tự đầu khác
 *      nhau, để lộ độ dài phần trùng khớp qua thời gian phản hồi.
 *
 * XOÁ FILE NÀY sau khi cài xong. Nó in ra hướng dẫn nhắc việc đó.
 * ─────────────────────────────────────────────────────────────────────────────
 */

define('ROOT_PATH',   __DIR__);
define('APP_PATH',    ROOT_PATH . '/app');
define('CORE_PATH',   ROOT_PATH . '/core');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VIEWS_PATH',  APP_PATH . '/views');

require_once CORE_PATH . '/App.php';
App::boot();

/** Kết quả từng bước, in ra cuối trang. */
$checks = [];
$fatal  = null;
$result = null;

function step(string $label, bool $ok, string $detail = ''): bool
{
    global $checks;
    $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
    return $ok;
}

// ---------------------------------------------------------------------------
// LỚP 1 — token
// ---------------------------------------------------------------------------
$expected = (string) env('INSTALL_TOKEN', '');
$given    = (string) ($_GET['token'] ?? '');

if ($expected === '') {
    $fatal = 'Chưa đặt INSTALL_TOKEN trong file .env. Hãy thêm một dòng '
           . 'INSTALL_TOKEN=<chuỗi ngẫu nhiên bạn tự đặt> rồi tải .env lên lại.';
} elseif (!hash_equals($expected, $given)) {
    $fatal = 'Thiếu hoặc sai token. Mở lại trang này kèm ?token=<giá trị INSTALL_TOKEN trong .env>';
}

// ---------------------------------------------------------------------------
// KIỂM TRA MÔI TRƯỜNG
// ---------------------------------------------------------------------------
if ($fatal === null) {
    step(
        'PHP ' . PHP_VERSION,
        PHP_VERSION_ID >= 80100,
        PHP_VERSION_ID >= 80100 ? '' : 'Cần PHP 8.1 trở lên. Đổi phiên bản PHP trong bảng điều khiển hosting.'
    );

    step(
        'Extension pdo_mysql',
        extension_loaded('pdo_mysql'),
        extension_loaded('pdo_mysql') ? '' : 'Hosting chưa bật pdo_mysql — không kết nối được MySQL.'
    );

    $connected = Database::isConnected();
    step(
        'Kết nối cơ sở dữ liệu',
        $connected,
        $connected ? sprintf('%s @ %s', config('database.name'), config('database.host'))
                   : 'Sai thông số trong .env, hoặc hosting chưa cho kết nối. '
                   . 'Kiểm tra DB_HOST / DB_NAME / DB_USER / DB_PASS.'
    );

    if (!$connected) {
        $fatal = 'Chưa kết nối được cơ sở dữ liệu. Sửa .env rồi tải lại trang này.';
    }
}

// ---------------------------------------------------------------------------
// KIỂM TRA SCHEMA
// ---------------------------------------------------------------------------
$expectedTables = [
    'users', 'profiles', 'user_roles', 'prescriptions',
    'categories', 'products', 'stores',
    'favorites', 'appointments', 'orders', 'order_items', 'contact_requests',
    // Thêm ở bản 2026-08-14 (ghi nhớ đăng nhập / quên mật khẩu). Cơ sở dữ
    // liệu cũ thiếu hai bảng này -> chạy file trong database/migrations/.
    'remember_tokens', 'password_resets',
];

if ($fatal === null) {
    $present = array_map(
        static fn (array $r): string => (string) reset($r),
        Database::fetchAll('SHOW TABLES')
    );

    $missing = array_diff($expectedTables, $present);

    step(
        sprintf('Bảng dữ liệu (%d/%d)', count($expectedTables) - count($missing), count($expectedTables)),
        $missing === [],
        $missing === [] ? '' : 'Còn thiếu: ' . implode(', ', $missing)
    );

    if ($missing !== []) {
        $fatal = 'Thiếu bảng. Cơ sở dữ liệu MỚI: phpMyAdmin → tab Import → '
               . 'database/schema.sql. Cơ sở dữ liệu ĐÃ CÓ DỮ LIỆU: đừng import lại '
               . '(file đó xoá sạch bảng), hãy chạy file trong database/migrations/.';
    }
}

// ---------------------------------------------------------------------------
// LỚP 2 — đã có admin thì dừng
// ---------------------------------------------------------------------------
if ($fatal === null) {
    $adminCount = (int) Database::fetchValue(
        "SELECT COUNT(*) FROM user_roles WHERE role = 'admin'"
    );

    if ($adminCount > 0) {
        step('Tài khoản quản trị', true, 'Đã có ' . $adminCount . ' tài khoản admin.');
        $fatal = 'Site đã được cài đặt xong. Hãy XOÁ file install.php khỏi hosting. '
               . 'Quên mật khẩu thì đặt lại bằng ?token=…&action=reset-password';
    }
}

// ---------------------------------------------------------------------------
// TẠO TÀI KHOẢN QUẢN TRỊ
// ---------------------------------------------------------------------------
if ($fatal === null) {
    $email = trim((string) ($_GET['email'] ?? 'admin@vineyewear.vn'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fatal = 'Email không hợp lệ: ' . $email;
    } else {
        // random_int() dùng nguồn ngẫu nhiên của hệ điều hành và phân bố đều —
        // không dùng rand()/mt_rand() vốn đoán được nếu biết seed.
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';
        for ($i = 0; $i < 20; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        try {
            $userId = uuid();

            Database::transaction(static function () use ($userId, $email, $password): void {
                Database::execute(
                    'INSERT INTO users (id, email, password_hash, email_verified)
                     VALUES (:id, :email, :hash, 1)',
                    ['id' => $userId, 'email' => $email,
                     'hash' => password_hash($password, PASSWORD_DEFAULT)]
                );
                Database::execute(
                    'INSERT INTO profiles (id, full_name) VALUES (:id, :name)',
                    ['id' => $userId, 'name' => 'Quản trị viên']
                );
                Database::execute(
                    'INSERT INTO user_roles (id, user_id, role) VALUES (:id, :uid, :role)',
                    ['id' => uuid(), 'uid' => $userId, 'role' => 'admin']
                );
            });

            step('Tạo tài khoản quản trị', true);
            $result = ['email' => $email, 'password' => $password];
        } catch (Throwable $e) {
            step('Tạo tài khoản quản trị', false, $e->getMessage());
            $fatal = 'Không tạo được tài khoản quản trị.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Cài đặt — Vin Eyewear</title>
    <style>
        *,*::before,*::after { box-sizing: border-box; }
        body {
            margin: 0; padding: 40px 20px;
            background: #faf6f2; color: #1a1214;
            font: 16px/1.7 system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .box { max-width: 680px; margin-inline: auto; }
        h1 { font-size: 28px; margin: 0 0 4px; }
        .sub { color: #5c4f52; margin: 0 0 28px; }
        ul { list-style: none; padding: 0; margin: 0 0 24px; border: 1px solid #c5bcb8; background: #fff; }
        li { display: flex; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #ede8e3; }
        li:last-child { border-bottom: 0; }
        .ico { flex-shrink: 0; font-weight: 700; }
        .ok .ico   { color: #27ae60; }
        .bad .ico  { color: #c0392b; }
        .detail { display: block; font-size: 14px; color: #5c4f52; }
        .msg { padding: 14px 16px; border-left: 3px solid; margin-bottom: 24px; }
        .msg--err { border-color: #c0392b; background: #fbeae8; }
        .msg--ok  { border-color: #27ae60; background: #e9f7ef; }
        .cred { padding: 20px; border: 2px solid #801a20; background: #fff; }
        .cred dt { font-size: 12px; text-transform: uppercase; letter-spacing: .12em; color: #5c4f52; }
        .cred dd { margin: 2px 0 14px; font: 600 18px/1.4 ui-monospace, "JetBrains Mono", monospace; word-break: break-all; }
        code { background: #ede8e3; padding: 2px 6px; font-size: 14px; }
        a { color: #801a20; }
    </style>
</head>
<body>
<div class="box">

    <h1>Cài đặt Vin Eyewear</h1>
    <p class="sub">Trang này chỉ dùng một lần, cho hosting không có SSH.</p>

    <?php if ($checks !== []): ?>
        <ul>
            <?php foreach ($checks as $c): ?>
                <li class="<?= $c['ok'] ? 'ok' : 'bad' ?>">
                    <span class="ico"><?= $c['ok'] ? '✓' : '✗' ?></span>
                    <span>
                        <?= e($c['label']) ?>
                        <?php if ($c['detail'] !== ''): ?>
                            <span class="detail"><?= e($c['detail']) ?></span>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($fatal !== null): ?>
        <p class="msg msg--err"><?= e($fatal) ?></p>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <p class="msg msg--ok">Cài đặt xong. Mật khẩu dưới đây <strong>chỉ hiện một lần</strong> —
            chép ngay vào trình quản lý mật khẩu.</p>

        <dl class="cred">
            <dt>Email đăng nhập</dt>
            <dd><?= e($result['email']) ?></dd>
            <dt>Mật khẩu</dt>
            <dd><?= e($result['password']) ?></dd>
        </dl>

        <p style="margin-top:24px">
            <strong>Việc cần làm ngay:</strong>
        </p>
        <ol>
            <li>Chép mật khẩu ở trên.</li>
            <li><strong>Xoá file <code>install.php</code></strong> khỏi hosting.</li>
            <li>Đăng nhập tại <a href="/auth">/auth</a> rồi vào <a href="/quan-tri">/quan-tri</a>.</li>
        </ol>
    <?php endif; ?>

</div>
</body>
</html>
