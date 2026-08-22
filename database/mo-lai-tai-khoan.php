<?php

/**
 * database/mo-lai-tai-khoan.php
 *
 * Mở lại tài khoản khách đã tự yêu cầu xoá, và tra xem ai đang bị khoá.
 *
 *     php database/mo-lai-tai-khoan.php --list
 *         Liệt kê mọi tài khoản đang ở trạng thái đã xoá, kèm ngày và lý do.
 *
 *     php database/mo-lai-tai-khoan.php <email hoặc số điện thoại>
 *         Mở lại tài khoản đó. Toàn bộ dữ liệu cũ trở lại nguyên vẹn — đơn
 *         hàng, lịch hẹn, sổ địa chỉ, thông số đo mắt — vì không có gì từng
 *         bị xoá đi (xem khối "XOÁ TÀI KHOẢN" trong app/models/UserModel.php).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO LÀ SCRIPT DÒNG LỆNH CHỨ KHÔNG PHẢI MỘT MÀN TRONG /quan-tri
 *
 * Khu quản trị hiện chưa có màn "khách hàng" nào cả — không có chỗ nào liệt kê
 * người dùng, nên dựng một trang chỉ để mở khoá tài khoản là dựng nửa cái màn
 * quản lý khách hàng theo lối rẽ ngang. Khi nào màn đó ra đời thì nút "mở lại"
 * thuộc về nó, và nó gọi đúng UserModel::restore() mà file này đang gọi.
 *
 * Trong lúc chờ, cửa hàng vẫn phải có ĐƯỜNG NÀO ĐÓ để giữ lời hứa in trên
 * trang xoá tài khoản ("gọi hotline, nhân viên sẽ khôi phục cho bạn"). Một
 * lời hứa không có cách nào thực hiện thì tệ hơn là không hứa.
 * ─────────────────────────────────────────────────────────────────────────────
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

$arg = $argv[1] ?? '';

if ($arg === '' || $arg === '--help' || $arg === '-h') {
    fwrite(STDERR, "Cách dùng:\n"
                 . "  php database/mo-lai-tai-khoan.php --list\n"
                 . "  php database/mo-lai-tai-khoan.php <email hoặc số điện thoại>\n");
    exit(1);
}

// ---------------------------------------------------------------- liệt kê
if ($arg === '--list') {
    $rows = Database::fetchAll(
        'SELECT u.id, u.email, u.deleted_at, u.deletion_reason, p.full_name, p.phone
           FROM users u
           LEFT JOIN profiles p ON p.id = u.id
          WHERE u.deleted_at IS NOT NULL
          ORDER BY u.deleted_at DESC'
    );

    if ($rows === []) {
        echo "Không có tài khoản nào đang ở trạng thái đã xoá.\n";
        exit(0);
    }

    echo count($rows) . " tài khoản đã yêu cầu xoá:\n\n";

    foreach ($rows as $r) {
        echo '  ' . ($r['full_name'] ?: '(chưa có tên)') . "\n";
        echo '    liên hệ : ' . ($r['phone'] ?: '—') . ' · ' . ($r['email'] ?: '—') . "\n";
        echo '    xoá lúc : ' . $r['deleted_at'] . "\n";
        echo '    lý do   : ' . ($r['deletion_reason'] ?: '(không nêu)') . "\n\n";
    }

    exit(0);
}

// ---------------------------------------------------------------- mở lại
// includeDeleted = true: đây đúng là chỗ DUY NHẤT cần tìm ra một tài khoản đã
// khoá bằng email/số điện thoại. Mặc định của findByLogin() bỏ qua chúng.
$user = UserModel::findByLogin($arg, true);

if ($user === null) {
    fwrite(STDERR, "Không tìm thấy tài khoản nào khớp: {$arg}\n");
    exit(1);
}

$result = UserModel::restore((string) $user['id']);

if (!$result['ok']) {
    fwrite(STDERR, 'Không mở lại được: ' . $result['error'] . "\n");
    exit(1);
}

echo 'Đã mở lại tài khoản: ' . ($user['full_name'] ?: $user['id']) . "\n";
echo "Khách đăng nhập lại được ngay bằng mật khẩu cũ.\n";
echo "Lý do xoá cũ vẫn được giữ trong cơ sở dữ liệu để đối chiếu về sau.\n";
