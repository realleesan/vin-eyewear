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
     * MỘT ưu đãi đang chạy, cho dải đếm ngược ở hero trang chủ.
     *
     * Cùng điều kiện "còn hạn" với listing(), chỉ khác chỗ sắp xếp: tin ưu
     * đãi được ưu tiên trước, vì hero hứa "ưu đãi" chứ không phải "sự kiện" —
     * đếm ngược tới một buổi workshop thì cái đồng hồ nói sai về thứ nó đếm.
     * Không còn gì trong hạn thì trả null và hero tự ẩn cả dải đó đi.
     */
    public static function currentPromo(): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM events
              WHERE is_visible = 1
                AND COALESCE(ends_at, starts_at) >= NOW()
              ORDER BY (category = 'TIN ƯU ĐÃI') DESC, starts_at ASC
              LIMIT 1"
        );
    }

    /**
     * Tìm bài viết / sự kiện theo từ khoá — dùng cho trang tìm kiếm chung.
     *
     * TÁCH TỪ rồi bắt khớp TẤT CẢ các từ, mỗi từ khớp ở bất kỳ đâu. Cùng cách
     * với ProductModel::buildFilter(), và cùng lý do: ghép cả câu thành một
     * chuỗi liền thì gõ "workshop gọng" không ra "Workshop: Chọn gọng theo
     * khuôn mặt", vì giữa hai từ còn chữ "Chọn".
     *
     * Collation utf8mb4_unicode_ci khiến LIKE bỏ qua cả HOA/thường lẫn DẤU,
     * nên gõ "su kien" vẫn ra "Sự kiện".
     *
     * Tìm trong title + excerpt + content: tiêu đề thôi thì quá hẹp — người ta
     * hay nhớ một chi tiết trong bài chứ không nhớ tên bài.
     */
    public static function search(string $q, int $limit = 6): array
    {
        $words = preg_split('/\s+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_slice($words, 0, 6);

        if ($words === []) {
            return [];
        }

        $conds  = ['is_visible = 1'];
        $params = [];

        foreach ($words as $i => $word) {
            // Ba tham số RIÊNG cho cùng một giá trị: dự án tắt EMULATE_PREPARES
            // nên MySQL ánh xạ tham số theo VỊ TRÍ, dùng lại một tên cho ba chỗ
            // sẽ ném "Invalid parameter number".
            $conds[] = sprintf(
                '(title LIKE :q%1$d_t OR excerpt LIKE :q%1$d_e OR content LIKE :q%1$d_c)',
                $i
            );

            // addcslashes thoát % và _ để người gõ "50%" không biến nó thành
            // ký tự đại diện khớp mọi thứ.
            $needle = '%' . addcslashes($word, '%_\\') . '%';

            $params["q{$i}_t"] = $needle;
            $params["q{$i}_e"] = $needle;
            $params["q{$i}_c"] = $needle;
        }

        return Database::fetchAll(
            'SELECT * FROM events WHERE ' . implode(' AND ', $conds)
            . ' ORDER BY starts_at DESC LIMIT ' . max(1, $limit),
            $params
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
     * Một trang bài viết, mới trước cũ sau.
     *
     * "Vin Eyewear News.dc.html" chia trang 9 bài một lần. Trước đây trang này
     * đổ hết ra một lượt — chấp nhận được với 9 sự kiện, nhưng cửa hàng đăng
     * đều thì vài năm nữa là một trang vài trăm thẻ.
     *
     * @return array{items:array, total:int, page:int, totalPages:int}
     */
    public static function paginateVisible(
        string $category,
        int $page,
        int $perPage,
        bool $leadExtra = false
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $conditions = ['is_visible' => 1];

        if ($category !== '') {
            $conditions['category'] = $category;
        }

        $total = static::count($conditions);

        /*
         * TRANG 1 LẤY THÊM MỘT BÀI khi $leadExtra bật.
         *
         * Bài đầu danh sách bị EventController tách ra làm THẺ LỚN nổi bật,
         * nên nếu trang 1 chỉ lấy đúng $perPage thì cái lưới bên dưới còn
         * $perPage − 1 thẻ: với perPage = 9 thì lưới 3 cột ra 8 thẻ, tức hai
         * hàng đủ và một hàng cụt hai ô. Lấy dư một bài thì thẻ lớn có phần
         * của nó mà lưới vẫn đủ 3×3.
         *
         * Các trang sau KHÔNG có thẻ lớn nên vẫn $perPage, nhưng offset phải
         * cộng thêm bài dư đó — thiếu là bài thứ 10 hiện hai lần.
         */
        $firstPage = $leadExtra ? $perPage + 1 : $perPage;

        $totalPages = $total <= $firstPage
            ? 1
            : 1 + (int) ceil(($total - $firstPage) / $perPage);

        // Trang vượt quá số trang có thật (sửa tay ?page=99) -> về trang cuối,
        // không trả danh sách rỗng khiến khách tưởng chưa có bài nào.
        $page = min($page, max(1, $totalPages));

        if ($page === 1) {
            $limit  = $firstPage;
            $offset = 0;
        } else {
            $limit  = $perPage;
            $offset = $firstPage + ($page - 2) * $perPage;
        }

        [$clause, $params] = static::buildWhere($conditions);

        $items = Database::fetchAll(
            'SELECT * FROM events' . $clause
            . ' ORDER BY starts_at DESC, created_at DESC'
            . sprintf(' LIMIT %d OFFSET %d', $limit, $offset),
            $params
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => max(1, $totalPages),
        ];
    }
}
