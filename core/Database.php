<?php

/**
 * core/Database.php
 *
 * Cầu nối duy nhất tới MySQL. Mọi truy vấn trong dự án đi qua đây.
 *
 * Vì sao dùng PDO chứ không phải mysqli:
 *   - Câu lệnh chuẩn bị sẵn (prepared statement) với tham số đặt tên (:slug)
 *     đọc dễ hơn dấu '?' theo thứ tự, ít sai khi câu lệnh dài.
 *   - fetch trả mảng kết hợp sẵn, không phải gọi fetch_assoc() từng dòng.
 *
 * Vì sao là singleton: mỗi request HTTP chỉ cần đúng MỘT kết nối. Mở kết nối
 * mới ở từng model sẽ đốt slot max_connections của MySQL rất nhanh khi có
 * nhiều người truy cập cùng lúc.
 */

class Database
{
    /** Kết nối dùng chung cho cả request. */
    private static ?PDO $connection = null;

    /**
     * Lấy kết nối PDO, tự mở ở lần gọi đầu tiên.
     *
     * Kết nối chỉ được mở khi thật sự có truy vấn (lazy): các trang tĩnh như
     * /chinh-sach không đụng tới DB thì không tốn một kết nối nào.
     */
    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::connect();
        }

        return self::$connection;
    }

    private static function connect(): PDO
    {
        $config = config('database');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        try {
            return new PDO($dsn, $config['user'], $config['pass'], $config['options']);
        } catch (PDOException $e) {
            // Thông điệp gốc của PDO chứa host, tên DB và đôi khi cả user.
            // Ở production tuyệt đối không để nó lọt ra trình duyệt.
            error_log('[DB] Không kết nối được cơ sở dữ liệu: ' . $e->getMessage());

            if (config('app.debug')) {
                throw $e;
            }

            throw new RuntimeException('Không kết nối được cơ sở dữ liệu.');
        }
    }

    /**
     * Chạy một câu lệnh có tham số và trả về PDOStatement.
     *
     * LUÔN truyền dữ liệu qua $params, KHÔNG nối chuỗi vào $sql:
     *     ĐÚNG : query('SELECT * FROM products WHERE slug = :slug', ['slug' => $slug])
     *     SAI  : query("SELECT * FROM products WHERE slug = '$slug'")
     * Cách sai mở đường cho SQL injection — người dùng gõ slug là
     * `x' OR '1'='1` sẽ lấy được toàn bộ bảng.
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Lấy MỘT dòng, hoặc null nếu không có.
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lấy TẤT CẢ dòng khớp điều kiện.
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Lấy giá trị ở cột đầu tiên của dòng đầu tiên — dùng cho COUNT, SUM, MAX.
     */
    public static function fetchValue(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Chạy INSERT/UPDATE/DELETE, trả về số dòng bị ảnh hưởng.
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Chạy một nhóm thao tác trong cùng transaction.
     *
     * Callback ném exception -> mọi thay đổi bị huỷ hết (rollback).
     * Cần cho những việc phải "được ăn cả, ngã về không", ví dụ đặt hàng:
     * tạo orders + chèn order_items + trừ tồn kho. Nếu trừ tồn kho lỗi giữa
     * chừng mà không rollback, hệ thống sẽ còn lại một đơn hàng không có
     * dòng sản phẩm nào.
     *
     *   Database::transaction(function () use ($data) {
     *       $orderId = OrderModel::create($data);
     *       OrderModel::addItems($orderId, $data['items']);
     *       return $orderId;
     *   });
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();

        // MySQL không hỗ trợ transaction lồng nhau. Nếu đã ở trong một
        // transaction thì chạy thẳng callback, để lớp ngoài cùng quyết định
        // commit hay rollback.
        if ($pdo->inTransaction()) {
            return $callback();
        }

        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Kiểm tra kết nối — dùng cho trang chẩn đoán và script cài đặt.
     */
    public static function isConnected(): bool
    {
        try {
            self::connection()->query('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @var array<string, bool> Nhớ kết quả trong một request, xem tableExists() */
    private static array $tableCache = [];

    /**
     * Bảng này đã tồn tại chưa?
     *
     * Có để những tính năng cần bảng MỚI không làm sập site khi người quản
     * trị chưa kịp chạy file nâng cấp trong database/migrations/. Không có
     * bảng thì tính năng đó im lặng tắt, phần còn lại của site chạy bình
     * thường — thay vì mọi trang đều lỗi 500 vì một câu SELECT hỏng.
     *
     * Kết quả được nhớ trong suốt request: một trang có thể hỏi vài lần, mà
     * cấu trúc bảng thì không đổi giữa chừng.
     */
    public static function tableExists(string $table): bool
    {
        if (isset(self::$tableCache[$table])) {
            return self::$tableCache[$table];
        }

        try {
            // information_schema thay vì SHOW TABLES LIKE: tên bảng vào được
            // tham số ràng buộc, không phải nối chuỗi vào câu lệnh.
            $n = (int) self::fetchValue(
                'SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
                ['t' => $table]
            );

            return self::$tableCache[$table] = $n > 0;
        } catch (Throwable) {
            return self::$tableCache[$table] = false;
        }
    }
}
