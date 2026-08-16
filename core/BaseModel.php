<?php

/**
 * core/BaseModel.php
 *
 * Lớp cha của mọi model. Gom các thao tác CRUD lặp đi lặp lại để model con
 * chỉ còn phải viết những truy vấn thật sự riêng của nó.
 *
 * Cách dùng: model con khai báo tên bảng rồi kế thừa.
 *
 *     class StoreModel extends BaseModel
 *     {
 *         protected static string $table = 'stores';
 *
 *         public static function active(): array
 *         {
 *             return static::where(['is_active' => 1], 'name ASC');
 *         }
 *     }
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * QUY TẮC AN TOÀN — đọc trước khi thêm phương thức mới
 *
 * Giá trị luôn đi qua tham số của prepared statement, không bao giờ nối chuỗi.
 * Nhưng TÊN CỘT thì không thể tham số hoá — SQL không cho phép `ORDER BY ?`.
 * Vì vậy mọi chỗ nhận tên cột từ bên ngoài (khoá của mảng $conditions, chuỗi
 * $orderBy) đều phải đi qua assertSafeIdentifier() / assertSafeOrderBy().
 * Bỏ qua bước đó là mở thẳng cửa cho SQL injection.
 * ─────────────────────────────────────────────────────────────────────────────
 */

abstract class BaseModel
{
    /** Tên bảng — model con BẮT BUỘC khai báo. */
    protected static string $table = '';

    /** Tên cột khoá chính. */
    protected static string $primaryKey = 'id';

    // ========================================================================
    // ĐỌC
    // ========================================================================

    /**
     * Tìm một bản ghi theo khoá chính.
     */
    public static function find(string $id): ?array
    {
        return Database::fetchOne(
            sprintf('SELECT * FROM `%s` WHERE `%s` = :id LIMIT 1', static::$table, static::$primaryKey),
            ['id' => $id]
        );
    }

    /**
     * Tìm một bản ghi theo cột bất kỳ, thường dùng với slug hoặc code.
     */
    public static function findBy(string $column, mixed $value): ?array
    {
        static::assertSafeIdentifier($column);

        return Database::fetchOne(
            sprintf('SELECT * FROM `%s` WHERE `%s` = :value LIMIT 1', static::$table, $column),
            ['value' => $value]
        );
    }

    /**
     * Lấy toàn bộ bản ghi.
     */
    public static function all(?string $orderBy = null, ?int $limit = null): array
    {
        $sql = sprintf('SELECT * FROM `%s`', static::$table);

        if ($orderBy !== null) {
            static::assertSafeOrderBy($orderBy);
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit !== null) {
            // Ép sang int rồi nhúng thẳng: MySQL không nhận tham số buộc
            // (bound parameter) ở vị trí LIMIT khi tắt EMULATE_PREPARES.
            // (int) đảm bảo chỉ có chữ số lọt vào câu lệnh.
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return Database::fetchAll($sql);
    }

    /**
     * Lọc theo nhiều cột nối bằng AND.
     *
     *     UserModel::where(['status' => 'new', 'store_id' => $id], 'created_at DESC')
     *
     * Giá trị null được dịch thành `IS NULL` chứ không phải `= NULL` —
     * trong SQL, `cột = NULL` luôn cho kết quả rỗng, kể cả khi cột đúng là NULL.
     */
    public static function where(array $conditions, ?string $orderBy = null, ?int $limit = null): array
    {
        [$clause, $params] = static::buildWhere($conditions);

        $sql = sprintf('SELECT * FROM `%s`%s', static::$table, $clause);

        if ($orderBy !== null) {
            static::assertSafeOrderBy($orderBy);
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return Database::fetchAll($sql, $params);
    }

    /**
     * Lấy bản ghi đầu tiên khớp điều kiện, hoặc null.
     */
    public static function firstWhere(array $conditions, ?string $orderBy = null): ?array
    {
        $rows = static::where($conditions, $orderBy, 1);

        return $rows[0] ?? null;
    }

    /**
     * Đếm số bản ghi khớp điều kiện.
     */
    public static function count(array $conditions = []): int
    {
        [$clause, $params] = static::buildWhere($conditions);

        return (int) Database::fetchValue(
            sprintf('SELECT COUNT(*) FROM `%s`%s', static::$table, $clause),
            $params
        );
    }

    /**
     * Kiểm tra tồn tại — nhẹ hơn find() vì không kéo cả dòng về.
     */
    public static function exists(array $conditions): bool
    {
        return static::count($conditions) > 0;
    }

    // ========================================================================
    // GHI
    // ========================================================================

    /**
     * Chèn một bản ghi, trả về id của nó.
     *
     * Nếu $data chưa có khoá chính, hàm tự sinh UUID v4. Trả id ra ngay giúp
     * gọi tiếp các bảng con (order_items...) trong cùng transaction.
     */
    public static function insert(array $data): string
    {
        if (empty($data[static::$primaryKey])) {
            $data[static::$primaryKey] = uuid();
        }

        $columns = array_keys($data);
        static::assertSafeIdentifiers($columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            static::$table,
            implode(', ', array_map(static fn ($c) => "`$c`", $columns)),
            implode(', ', array_map(static fn ($c) => ":$c", $columns))
        );

        Database::execute($sql, $data);

        return (string) $data[static::$primaryKey];
    }

    /**
     * Cập nhật một bản ghi theo khoá chính, trả về số dòng bị ảnh hưởng.
     *
     * Lưu ý: MySQL trả 0 khi giá trị mới trùng hệt giá trị cũ (không có gì để
     * ghi). Đừng coi 0 là "không tìm thấy bản ghi" — muốn biết điều đó thì
     * kiểm tra bằng exists() trước.
     */
    public static function update(string $id, array $data): int
    {
        if ($data === []) {
            return 0;
        }

        $columns = array_keys($data);
        static::assertSafeIdentifiers($columns);

        $assignments = implode(', ', array_map(static fn ($c) => "`$c` = :$c", $columns));

        // Đặt tên tham số khoá chính là :__pk để chắc chắn không đụng tên một
        // cột đang được cập nhật (ví dụ khi $data có chứa chính cột 'id').
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :__pk',
            static::$table,
            $assignments,
            static::$primaryKey
        );

        return Database::execute($sql, $data + ['__pk' => $id]);
    }

    /**
     * Xoá một bản ghi theo khoá chính.
     */
    public static function delete(string $id): int
    {
        return Database::execute(
            sprintf('DELETE FROM `%s` WHERE `%s` = :id', static::$table, static::$primaryKey),
            ['id' => $id]
        );
    }

    // ========================================================================
    // PHÂN TRANG
    // ========================================================================

    /**
     * Lấy một trang dữ liệu kèm thông tin phân trang.
     *
     * Trả về:
     *   ['items' => [...], 'total' => 57, 'page' => 2,
     *    'perPage' => 12, 'totalPages' => 5]
     *
     * View chỉ cần đọc totalPages để vẽ dãy số trang, không phải tự tính.
     */
    public static function paginate(
        array $conditions = [],
        int $page = 1,
        int $perPage = 12,
        ?string $orderBy = null
    ): array {
        // Trang < 1 xảy ra khi người dùng sửa tay ?page=0 hoặc ?page=-3
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $total      = static::count($conditions);
        $totalPages = (int) ceil($total / $perPage);
        $offset     = ($page - 1) * $perPage;

        [$clause, $params] = static::buildWhere($conditions);

        $sql = sprintf('SELECT * FROM `%s`%s', static::$table, $clause);

        if ($orderBy !== null) {
            static::assertSafeOrderBy($orderBy);
            $sql .= ' ORDER BY ' . $orderBy;
        }

        $sql .= sprintf(' LIMIT %d OFFSET %d', $perPage, $offset);

        return [
            'items'      => Database::fetchAll($sql, $params),
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    /**
     * Dựng mệnh đề WHERE từ mảng ['cột' => giá trị].
     *
     * Trả về [chuỗi WHERE (có khoảng trắng đầu, rỗng nếu không điều kiện),
     *         mảng tham số].
     */
    protected static function buildWhere(array $conditions): array
    {
        if ($conditions === []) {
            return ['', []];
        }

        static::assertSafeIdentifiers(array_keys($conditions));

        $parts  = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            if ($value === null) {
                // `cột = NULL` không bao giờ đúng trong SQL ba trạng thái
                $parts[] = "`$column` IS NULL";
                continue;
            }

            if (is_array($value)) {
                // IN (...) — mỗi phần tử một tham số riêng, không nối chuỗi.
                // Mảng rỗng thì dùng `1 = 0` để trả về không kết quả:
                // `IN ()` là lỗi cú pháp trong MySQL.
                if ($value === []) {
                    $parts[] = '1 = 0';
                    continue;
                }

                $placeholders = [];
                foreach (array_values($value) as $i => $item) {
                    $key                = "{$column}_{$i}";
                    $placeholders[]     = ":$key";
                    $params[$key]       = $item;
                }
                $parts[] = "`$column` IN (" . implode(', ', $placeholders) . ')';
                continue;
            }

            $parts[]          = "`$column` = :$column";
            $params[$column]  = $value;
        }

        return [' WHERE ' . implode(' AND ', $parts), $params];
    }

    /**
     * Chặn tên cột không hợp lệ.
     *
     * Chỉ cho phép chữ cái, chữ số và dấu gạch dưới — đúng tập ký tự của tên
     * cột trong dự án này. Bất cứ thứ gì khác (dấu cách, nháy, dấu chấm phẩy)
     * đều là dấu hiệu chuỗi đến từ đầu vào người dùng, phải dừng ngay.
     */
    protected static function assertSafeIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException("Tên cột không hợp lệ: {$identifier}");
        }
    }

    protected static function assertSafeIdentifiers(array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            static::assertSafeIdentifier((string) $identifier);
        }
    }

    /**
     * Kiểm tra mệnh đề ORDER BY.
     *
     * Chấp nhận dạng "cột", "cột ASC", "cột DESC", nhiều cột cách nhau bởi dấu
     * phẩy. Không chấp nhận hàm, biểu thức hay câu lệnh con — ORDER BY là chỗ
     * hay bị bỏ sót nhất vì trông có vẻ vô hại, nhưng
     * `?sort=(SELECT ...)` thì vẫn chạy được nếu không chặn.
     */
    protected static function assertSafeOrderBy(string $orderBy): void
    {
        foreach (explode(',', $orderBy) as $part) {
            $part = trim($part);

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\s+(ASC|DESC))?$/i', $part) !== 1) {
                throw new InvalidArgumentException("Mệnh đề ORDER BY không hợp lệ: {$orderBy}");
            }
        }
    }
}
