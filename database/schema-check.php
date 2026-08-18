<?php

/**
 * database/schema-check.php — đối chiếu CSDL đang chạy với database/schema.sql.
 *
 *     php database/schema-check.php
 *
 * KHÔNG cần sudo, KHÔNG đụng gì vào dữ liệu: chỉ đọc, chạy bằng chính tài
 * khoản MySQL của ứng dụng trong .env.
 *
 * Vì sao cần: schema.sql là bản GỘP, luôn mô tả cấu trúc mới nhất. Nhưng
 * setup.sh chỉ nạp nó khi database còn trống — máy đã cài từ bản cũ rồi
 * `git pull` sẽ thiếu bảng và cột mà không có gì báo. Nó chỉ lộ ra khi ai đó
 * mở đúng trang chạm tới chỗ thiếu và nhận về 500.
 *
 * Thiếu thứ gì thì chạy:  sudo bash database/migrate.sh
 *
 * Mã thoát: 0 = khớp, 1 = lệch. Dùng được trong CI hoặc script triển khai.
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');

require ROOT_PATH . '/core/helpers.php';
require ROOT_PATH . '/core/Database.php';

$schemaFile = ROOT_PATH . '/database/schema.sql';

if (!is_file($schemaFile)) {
    fwrite(STDERR, "✗ Không thấy database/schema.sql\n");
    exit(1);
}

/*
 * Đọc cấu trúc MONG MUỐN từ schema.sql.
 *
 * Cắt theo `CREATE TABLE ... ) ENGINE` rồi nhặt các dòng mở đầu bằng `tên`
 * kèm một chữ cái — đó là dòng khai cột. Dòng khai khoá (PRIMARY KEY, KEY,
 * UNIQUE KEY, CONSTRAINT) không khớp mẫu đó nên tự bị loại, và ở đây cũng
 * không cần đối chiếu khoá: cột thiếu mới là thứ làm câu truy vấn đổ lỗi.
 */
preg_match_all('/CREATE TABLE `([a-z_]+)` \((.*?)\n\) ENGINE/s', file_get_contents($schemaFile), $matches, PREG_SET_ORDER);

$want = [];
foreach ($matches as $table) {
    $columns = [];
    foreach (explode("\n", $table[2]) as $line) {
        if (preg_match('/^\s+`([a-z_]+)`\s+[A-Za-z]/', $line, $col)) {
            $columns[] = $col[1];
        }
    }
    $want[$table[1]] = $columns;
}

if ($want === []) {
    fwrite(STDERR, "✗ Không đọc được bảng nào từ schema.sql\n");
    exit(1);
}

// Cấu trúc THẬT
try {
    $live = array_map('current', Database::fetchAll('SHOW TABLES'));
} catch (Throwable $e) {
    fwrite(STDERR, "✗ Không kết nối được CSDL: " . $e->getMessage() . "\n");
    exit(1);
}

$problems = 0;

printf("Bảng: schema.sql khai %d · CSDL có %d\n\n", count($want), count($live));

/*
 * Sổ ghi của database/migrate.sh.
 *
 * In ra ĐẦU TIÊN vì nó trả lời câu hỏi hay gặp nhất khi có gì đó hỏng:
 * "migrate đã chạy chưa?". Không có bảng này nghĩa là migrate.sh chưa từng
 * chạy ở chế độ áp thật — `--status` chỉ liệt kê chứ không tạo sổ, và
 * setup.sh thì không đụng tới migration.
 */
echo "SỔ MIGRATION (database/migrate.sh)\n";

if (!in_array('schema_migrations', $live, true)) {
    echo "  · chưa có bảng schema_migrations → migrate.sh CHƯA chạy lần nào\n\n";
} else {
    $applied = Database::fetchAll('SELECT filename, applied_at FROM `schema_migrations` ORDER BY applied_at, filename');

    if ($applied === []) {
        echo "  · sổ rỗng\n";
    }

    foreach ($applied as $row) {
        printf("  · %-52s %s\n", $row['filename'], $row['applied_at']);
    }

    $files   = array_map('basename', glob(ROOT_PATH . '/database/migrations/*.sql') ?: []);
    $pending = array_diff($files, array_column($applied, 'filename'));

    foreach ($pending as $file) {
        printf("  · %-52s CHƯA ÁP\n", $file);
    }

    echo "\n";
}

$missingTables = array_diff(array_keys($want), $live);

if ($missingTables !== []) {
    $problems += count($missingTables);
    echo "BẢNG THIẾU HẲN\n";
    foreach ($missingTables as $table) {
        echo "  · {$table}\n";
    }
    echo "\n";
}

$columnProblems = [];

foreach ($want as $table => $columns) {
    if (!in_array($table, $live, true)) {
        continue;
    }

    $liveColumns = array_column(Database::fetchAll("SHOW COLUMNS FROM `{$table}`"), 'Field');
    $missing     = array_diff($columns, $liveColumns);

    if ($missing !== []) {
        $columnProblems[$table] = $missing;
        $problems += count($missing);
    }
}

if ($columnProblems !== []) {
    echo "CỘT THIẾU\n";
    foreach ($columnProblems as $table => $missing) {
        printf("  %-22s %s\n", $table, implode(', ', $missing));
    }
    echo "\n";
}

/*
 * Bảng có trong CSDL mà schema.sql không khai. KHÔNG tính là lỗi: có thể là
 * bảng của công cụ (schema_migrations do database/migrate.sh tạo) hoặc bảng
 * cũ chưa dọn. Vẫn in ra để người đọc biết mình đang nhìn cái gì.
 */
$extra = array_diff($live, array_keys($want), ['schema_migrations']);

if ($extra !== []) {
    echo "BẢNG NGOÀI SCHEMA (không phải lỗi)\n";
    foreach ($extra as $table) {
        echo "  · {$table}\n";
    }
    echo "\n";
}

if ($problems === 0) {
    echo "✓ CSDL khớp schema.sql.\n";
    exit(0);
}

printf("✗ Lệch %d chỗ. Chạy:  sudo bash database/migrate.sh\n", $problems);
exit(1);
