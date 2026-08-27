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
    /* countNew() đã BỎ ngày 2026-08-26 cùng cột `contact_requests`.`status`:
       trang liên hệ không còn hàng chờ ba nấc, chỉ còn "đã đẩy Zalo chưa".
       Danh sách này quên gỡ nó theo, nên bản chẩn đoán in "*** THIẾU ***" cho
       một hàm KHÔNG CÒN TỒN TẠI THEO Ý ĐỒ — và người đang dò lỗi thì mất công
       đi tìm một thứ vốn không có. Một bản chẩn đoán báo động giả là bản chẩn
       đoán tệ hơn không có. */
    ['ContactModel',   'countChuaDayZalo'],
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
    /* Không còn nhánh dự phòng gọi countNew(): hàm đó đã bỏ, và gọi tới một
       hàm không tồn tại thì chính bản chẩn đoán sẽ chết giữa chừng. */
    'ContactModel (huy hiệu liên hệ)' => static fn () => ContactModel::countChuaDayZalo(),
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

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * KHUNG THÔNG TIN BỘ SƯU TẬP — HỎI HAI ĐƯỜNG, RỒI SO HAI CÂU TRẢ LỜI
 *
 * Khối này thêm ngày 2026-08-28 sau một ca mất cả buổi: nhóm ô "Nội dung trang
 * chi tiết" trong khu quản trị không chịu hiện ra, mà chạy migration bao nhiêu
 * lần cũng thế.
 *
 * Nguyên nhân có thể là MỘT TRONG HAI, và nhìn từ trình duyệt thì chúng giống
 * hệt nhau:
 *
 *   1. migration CHƯA CHẠY      -> cột chưa có thật, ẩn ô là đúng
 *   2. không đọc được information_schema (hosting dùng chung hay cắt quyền
 *      này) -> cột CÓ nhưng Database::columnExists() không thấy, và tính năng
 *      bị ẩn vĩnh viễn
 *
 * Nên ở đây hỏi cả hai đường cho cùng một cột: information_schema, và SHOW
 * COLUMNS (chỉ cần quyền trên chính bảng ấy). Hai câu trả lời KHÁC NHAU là dấu
 * hiệu của tình huống 2 — và cũng là lý do Database::columnExists() nay có
 * nhánh dự phòng.
 * ─────────────────────────────────────────────────────────────────────────────
 */
echo "\n=== KHUNG THÔNG TIN BỘ SƯU TẬP ===\n";

$hoiHaiDuong = static function (string $bang, string $cot) use ($in): void {
    try {
        $a = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
            ['t' => $bang, 'c' => $cot]
        ) > 0 ? 'CÓ' : 'không';
    } catch (Throwable $e) {
        $a = 'LỖI: ' . $e->getMessage();
    }

    try {
        $b = Database::fetchOne(sprintf(
            'SHOW COLUMNS FROM `%s` LIKE %s',
            $bang,
            Database::connection()->quote($cot)
        )) !== null ? 'CÓ' : 'không';
    } catch (Throwable $e) {
        $b = 'LỖI: ' . $e->getMessage();
    }

    $c = Database::columnExists($bang, $cot) ? 'CÓ' : 'không';

    $canh = ($a !== $b) ? '   <<< HAI ĐƯỜNG TRẢ LỜI KHÁC NHAU' : '';
    $in("  {$bang}.{$cot}", "info_schema={$a} · SHOW={$b} · columnExists={$c}{$canh}");
};

// Mỗi migration một cột đại diện — cả loạt cột trong một file cùng ra đời.
$hoiHaiDuong('collections', 'story');        // 2026-08-27-bo-suu-tap-trang-chi-tiet
$hoiHaiDuong('collections', 'season_code');  // 2026-08-27-bo-suu-tap-khung-ba-lop
$hoiHaiDuong('products',    'eyewear_type'); // (cùng file trên)
$hoiHaiDuong('product_variants', 'swatch_hex');

foreach (['collection_faqs', 'site_texts'] as $bang) {
    try {
        $a = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t",
            ['t' => $bang]
        ) > 0 ? 'CÓ' : 'không';
    } catch (Throwable $e) {
        $a = 'LỖI: ' . $e->getMessage();
    }

    try {
        $b = Database::fetchOne('SHOW TABLES LIKE ' . Database::connection()->quote($bang)) !== null ? 'CÓ' : 'không';
    } catch (Throwable $e) {
        $b = 'LỖI: ' . $e->getMessage();
    }

    $in("  bảng {$bang}", "info_schema={$a} · SHOW={$b} · tableExists="
        . (Database::tableExists($bang) ? 'CÓ' : 'không')
        . (($a !== $b) ? '   <<< HAI ĐƯỜNG TRẢ LỜI KHÁC NHAU' : ''));
}

echo "\n  Đọc thế nào:\n";
echo "    · tất cả 'không'          -> migration chưa chạy. Chạy database/migrate.sh.\n";
echo "    · info_schema khác SHOW   -> hosting cắt quyền information_schema.\n";
echo "                                 Database::columnExists() đã có nhánh dự phòng\n";
echo "                                 từ 2026-08-28; nếu vẫn hỏng thì mã trên máy\n";
echo "                                 chủ còn cũ, deploy lại.\n";
echo "    · tất cả 'CÓ'             -> dữ liệu đủ, lỗi nằm chỗ khác.\n";

echo "\nXOÁ FILE NÀY SAU KHI XEM XONG.\n";
