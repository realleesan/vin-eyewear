<?php

/**
 * EventModel — sự kiện, ưu đãi, tin tức.
 *
 * Port từ getEvents / getEventBySlug trong src/lib/shop.functions.ts.
 *
 * Thay cho policy "public events" của Postgres: mọi hàm dành cho trang công
 * khai đều lọc is_visible = 1. Khu quản trị dùng all() để thấy cả bản nháp.
 */

class EventModel extends BaseModel
{
    protected static string $table = 'events';

    /**
     * Toàn bộ sự kiện đang hiển thị, sắp xếp theo thời gian bắt đầu.
     *
     * @param string $order 'ASC' cho lịch sắp tới, 'DESC' cho tin mới nhất
     */
    public static function visible(string $order = 'ASC'): array
    {
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        return static::where(['is_visible' => 1], "starts_at {$order}");
    }

    /**
     * Sự kiện CHƯA kết thúc, dùng cho khối "sắp diễn ra".
     *
     * Điều kiện đọc là: sự kiện còn hạn nếu ends_at chưa qua, HOẶC (với sự
     * kiện chỉ có một mốc) starts_at chưa qua. COALESCE gộp hai trường hợp
     * đó lại — thiếu nó thì mọi sự kiện một-ngày (ends_at NULL) đều bị loại,
     * vì `NULL >= NOW()` không bao giờ đúng.
     */
    public static function upcoming(int $limit = 3): array
    {
        return Database::fetchAll(
            'SELECT * FROM events
              WHERE is_visible = 1
                AND COALESCE(ends_at, starts_at) >= NOW()
              ORDER BY starts_at ASC
              LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Sự kiện đã kết thúc — trang danh sách hiện ở nhóm dưới.
     */
    public static function past(int $limit = 12): array
    {
        return Database::fetchAll(
            'SELECT * FROM events
              WHERE is_visible = 1
                AND COALESCE(ends_at, starts_at) < NOW()
              ORDER BY starts_at DESC
              LIMIT ' . max(1, $limit)
        );
    }

    public static function findVisibleBySlug(string $slug): ?array
    {
        return static::firstWhere(['slug' => $slug, 'is_visible' => 1]);
    }

    /**
     * Các sự kiện khác để gợi ý ở cuối trang chi tiết.
     */
    public static function others(string $excludeId, int $limit = 3): array
    {
        return Database::fetchAll(
            'SELECT * FROM events
              WHERE is_visible = 1
                AND id <> :id
              ORDER BY starts_at DESC
              LIMIT ' . max(1, $limit),
            ['id' => $excludeId]
        );
    }

    /**
     * Danh sách nhóm sự kiện có thật trong dữ liệu (SỰ KIỆN, TIN ƯU ĐÃI…),
     * dùng dựng bộ lọc ở trang danh sách.
     *
     * Lấy từ dữ liệu thay vì khai cứng: người quản trị thêm nhóm mới là bộ
     * lọc tự có, không phải sửa code.
     */
    public static function categories(): array
    {
        $rows = Database::fetchAll(
            "SELECT DISTINCT category
               FROM events
              WHERE is_visible = 1
                AND category IS NOT NULL
                AND category <> ''
              ORDER BY category"
        );

        return array_column($rows, 'category');
    }

    /**
     * Lọc theo nhóm. Chuỗi rỗng nghĩa là không lọc.
     */
    public static function byCategory(string $category = '', string $order = 'ASC'): array
    {
        if ($category === '') {
            return static::visible($order);
        }

        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        return static::where(
            ['is_visible' => 1, 'category' => $category],
            "starts_at {$order}"
        );
    }

    /**
     * Một trang bài viết, mới trước cũ sau.
     *
     * "Vin Eyewear News.dc.html" chia trang 9 bài một lần. Trước đây trang này
     * đổ hết ra một lượt — chấp nhận được với 9 sự kiện, nhưng cửa hàng đăng
     * đều thì vài năm nữa là một trang vài trăm thẻ.
     *
     * @return array{items:array, total:int, page:int, totalPages:int}
     */
    public static function paginateVisible(string $category, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $conditions = ['is_visible' => 1];

        if ($category !== '') {
            $conditions['category'] = $category;
        }

        $total      = static::count($conditions);
        $totalPages = max(1, (int) ceil($total / $perPage));

        // Trang vượt quá số trang có thật (sửa tay ?page=99) -> về trang cuối,
        // không trả danh sách rỗng khiến khách tưởng chưa có bài nào.
        $page   = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        [$clause, $params] = static::buildWhere($conditions);

        $items = Database::fetchAll(
            'SELECT * FROM events' . $clause
            . ' ORDER BY starts_at DESC, created_at DESC'
            . sprintf(' LIMIT %d OFFSET %d', $perPage, $offset),
            $params
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'totalPages' => $totalPages];
    }
}
