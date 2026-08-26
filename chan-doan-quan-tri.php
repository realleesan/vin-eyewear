<?php

/**
 * chan-doan-quan-tri.php — TÌM NGUYÊN NHÂN LỖI 500 CỦA KHU QUẢN TRỊ.
 * XOÁ NGAY SAU KHI XEM XONG.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CẦN FILE NÀY THAY VÌ BẬT APP_DEBUG
 *
 * Bật APP_DEBUG=true trên máy chủ thật là phơi toàn bộ vết gọi hàm — kèm đường
 * dẫn tuyệt đối và có khi cả giá trị biến — ra cho BẤT KỲ AI mở trang, suốt
 * thời gian nó còn bật. File này làm đúng một việc hẹp hơn: chạy lại từng mảnh
 * mà khu quản trị phụ thuộc, bắt lỗi, rồi in ra CHỈ thông điệp lỗi. Và nó đòi
 * token, nên người ngoài không mở được.
 *
 * Dùng chung INSTALL_TOKEN với install.php và kiem-tra-db.php — không đẻ thêm
 * một bí mật nữa để rồi quên nó ở đâu.
 * ─────────────────────────────────────────────────────────────────────────────
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
    exit("Sai token.\nMở lại với ?token=<INSTALL_TOKEN trong .env>\n");
}

$in = static function (string $nhan, $gia_tri): void {
    echo str_pad($nhan, 46, '.'), ' ', $gia_tri, PHP_EOL;
};

echo "=== MÔI TRƯỜNG ===\n";
$in('PHP', PHP_VERSION);
$in('Tối thiểu dự án cần', '8.1 (PHP_VERSION_ID >= 80100)');
$in('Đạt yêu cầu', PHP_VERSION_ID >= 80100 ? 'CÓ' : '*** KHÔNG — ĐÂY LÀ NGUYÊN NHÂN ***');

echo "\n=== FILE ĐÃ LÊN ĐỦ CHƯA (lớp + phương thức) ===\n";
/* Mỗi dòng là một mắt xích mà renderAdmin() phụ thuộc. Thiếu bất kỳ cái nào là
   "Call to undefined method" và MỌI trang quản trị trả 500 — kể cả trang không
   liên quan gì tới mắt xích đó. Đây là dạng hỏng của một lần deploy FTP lên
   thiếu file: file cũ vẫn nằm đó nên trang bán hàng chạy bình thường. */
foreach ([
    ['Database',       'columnExists'],
    ['Database',       'tableExists'],
    ['ContactModel',   'countChuaDayZalo'],
    ['ContactModel',   'countNew'],
    ['CustomerModel',  'ready'],
    ['UserModel',      'coTheDangNhap'],
    ['AuditLogModel',  'write'],
    ['Zalo',           'contact'],
    ['PasswordResetModel', 'countPending'],
    ['ReviewModel',    'countPending'],
] as [$lop, $ham]) {
    $co = class_exists($lop) && method_exists($lop, $ham);
    $in($lop . '::' . $ham . '()', $co ? 'có' : '*** THIẾU ***');
}

echo "\n=== CỘT CỦA contact_requests ===\n";
try {
    $cot = Database::fetchAll(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_requests'
          ORDER BY ORDINAL_POSITION"
    );
    echo '  ', implode(', ', array_column($cot, 'COLUMN_NAME')), PHP_EOL;
    $ten = array_column($cot, 'COLUMN_NAME');
    $in('  có cột `status`',       in_array('status', $ten, true) ? 'CÓ' : 'không');
    $in('  có cột `zalo_sent_at`', in_array('zalo_sent_at', $ten, true) ? 'CÓ' : 'không');
} catch (Throwable $e) {
    echo '  LỖI: ', $e->getMessage(), PHP_EOL;
}

echo "\n=== CỘT CỦA users (module Khách hàng) ===\n";
try {
    $cot = Database::fetchAll(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
            AND COLUMN_NAME IN ('status','locked_reason','locked_at','locked_by','deleted_at')"
    );
    $in('  số cột trạng thái tài khoản', count($cot) . '/5');
} catch (Throwable $e) {
    echo '  LỖI: ', $e->getMessage(), PHP_EOL;
}

echo "\n=== BA BẢNG CỦA MODULE KHÁCH HÀNG ===\n";
foreach (['customer_prescriptions', 'customer_notes', 'customer_audit_logs'] as $b) {
    $in('  ' . $b, Database::tableExists($b) ? 'có' : 'CHƯA TẠO');
}

echo "\n=== CHẠY THỬ ĐÚNG NHỮNG GÌ renderAdmin() GỌI ===\n";
/* Đây mới là phần trả lời câu hỏi. Mỗi lượt gọi được bọc riêng, nên cái nào
   ném ra thì ta biết ĐÍCH DANH cái đó — thay vì chỉ thấy một trang 500 câm. */
$buoc = [
    'hàng chờ đơn + lịch hẹn' => static fn () => Database::fetchOne(
        "SELECT (SELECT COUNT(*) FROM orders WHERE status = 'new') AS a,
                (SELECT COUNT(*) FROM appointments WHERE status = 'pending') AS b"
    ),
    'ContactModel (huy hiệu liên hệ)' => static fn () => method_exists('ContactModel', 'countChuaDayZalo')
        ? ContactModel::countChuaDayZalo()
        : ContactModel::countNew(),
    'PasswordResetModel::countPending' => static fn () => PasswordResetModel::countPending(),
    'ReviewModel::countPending'        => static fn () => ReviewModel::countPending(),
    'truy vấn trang Tổng quan'         => static fn () => Database::fetchOne(
        "SELECT (SELECT COUNT(*) FROM products WHERE is_visible = 1) AS a,
                (SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled') AS b"
    ),
];

$hong = 0;

foreach ($buoc as $ten => $chay) {
    try {
        $chay();
        $in($ten, 'OK');
    } catch (Throwable $e) {
        $hong++;
        echo str_pad($ten, 46, '.'), " *** HỎNG ***\n";
        echo '      ', get_class($e), ': ', $e->getMessage(), PHP_EOL;
        echo '      tại ', $e->getFile(), ':', $e->getLine(), PHP_EOL;
    }
}

echo "\n", $hong === 0
    ? "Không mảnh nào ở trên hỏng. Lỗi nằm ngoài renderAdmin() — gửi kết quả này\nrồi bật APP_DEBUG=true đúng một phút để lấy vết gọi hàm đầy đủ.\n"
    : "Đã tìm ra {$hong} chỗ hỏng — xem dòng *** HỎNG *** bên trên.\n";

echo "\nXOÁ FILE NÀY SAU KHI XEM XONG.\n";
