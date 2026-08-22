<?php

/**
 * ProductModel — catalog sản phẩm.
 *
 * Port từ getCatalog / getProductBySlug (src/lib/shop.functions.ts) và phần
 * lọc trong src/routes/san-pham.index.tsx.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÁC BẢN LOVABLE: LỌC Ở ĐÂU
 *
 * Bản Lovable tải TOÀN BỘ catalog về trình duyệt rồi lọc, sắp xếp, phân trang
 * bằng JavaScript. Cách đó chấp nhận được với 6 sản phẩm mẫu, nhưng tới vài
 * trăm sản phẩm thì mỗi lượt vào trang phải tải cả kho hàng về máy khách.
 *
 * Bản này lọc bằng SQL: chỉ những dòng thật sự hiển thị mới rời khỏi database.
 * Ngữ nghĩa lọc giữ nguyên hệt bản gốc (xem từng ghi chú ở buildFilter).
 *
 * MỘT NGOẠI LỆ CÓ CHỦ Ý: catalog() — trang danh sách sản phẩm — kéo cả tập nền
 * về PHP rồi mới lọc. Không phải quên, mà vì ba cột phân loại là chữ tự do ghi
 * lẫn tiếng Việt lẫn tiếng Anh và một ô chứa nhiều giá trị, thứ mà `IN (...)`
 * không so nổi. Lý do đầy đủ và giới hạn của cách làm nằm ngay trên hàm đó.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Thay cho policy "public products" của Postgres: mọi hàm dành cho trang công
 * khai đều kèm is_visible = 1.
 */

class ProductModel extends BaseModel
{
    protected static string $table = 'products';

    /**
     * Số sản phẩm mỗi trang.
     *
     * 12 KHÔNG PHẢI SỐ TUỲ Ý — nó là bội chung của cả ba bề rộng lưới ở trang
     * danh mục (assets/css/category.css → .catgrid):
     *
     *   ≥ 901px  3 cột  ->  12 / 3 = 4 hàng chẵn
     *   ≤ 900px  2 cột  ->  12 / 2 = 6 hàng chẵn
     *   ≤ 560px  1 cột  ->  12 hàng
     *
     * Nhờ vậy hàng cuối của mọi trang luôn ĐẦY, không bao giờ còn một hai thẻ
     * lẻ nằm trơ giữa khoảng trống. Kho có bao nhiêu hàng cũng vậy: đủ 12 là
     * sang trang 2, không có chuyện lưới dài ra mãi.
     *
     * ĐỔI SỐ NÀY THÌ PHẢI CHỌN BỘI CHUNG CỦA 3 VÀ 2 — 6, 12, 18, 24, 30…
     * Chọn 10 chẳng hạn thì lưới 3 cột có hàng cuối chỉ một thẻ.
     */
    public const PER_PAGE = 12;

    /**
     * Các kiểu sắp xếp được phép.
     *
     * Danh sách CHO PHÉP, không phải danh sách cấm: giá trị ?sort= đến từ URL
     * do người dùng kiểm soát và được nhúng thẳng vào ORDER BY (SQL không cho
     * tham số hoá tên cột). Chỉ những khoá có ở đây mới đi tiếp.
     */
    private const SORTS = [
        'price-asc'  => 'price ASC',
        'price-desc' => 'price DESC',
        'rating'     => 'rating DESC, review_count DESC',
        'newest'     => 'created_at DESC',

        // "Bán chạy" trong ô sắp xếp của "Vin Eyewear Category.dc.html".
        // Bảng products chưa đếm số lượng đã bán, nên xếp theo thứ tự tốt nhất
        // hiện có: hàng được đánh dấu nổi bật lên trước, rồi tới điểm đánh giá
        // và số lượt đánh giá — cùng cách mà trang chủ chọn hàng "bán chạy".
        // TODO(data): thêm cột `sold_count` rồi đổi vế này thành `sold_count DESC`.
        'popular'    => 'is_featured DESC, rating DESC, review_count DESC',
    ];

    // ========================================================================
    // ĐỌC
    // ========================================================================

    /**
     * Lọc, sắp xếp và phân trang.
     *
     * @param array $filters q, category, brand, shape, material, gender, max, sort
     * @return array ['items'=>[], 'total'=>int, 'page'=>int, 'perPage'=>int, 'totalPages'=>int]
     */
    public static function filter(array $filters = [], int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?? self::PER_PAGE;
        $page    = max(1, $page);

        [$where, $params] = self::buildFilter($filters);

        $total = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM products p {$where}",
            $params
        );

        $sortKey = $filters['sort'] ?? 'newest';
        $orderBy = self::SORTS[$sortKey] ?? self::SORTS['newest'];

        $offset = ($page - 1) * $perPage;

        $rows = Database::fetchAll(
            "SELECT p.* FROM products p {$where}
              ORDER BY {$orderBy}
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'      => array_map([self::class, 'decode'], $rows),
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Dựng mệnh đề WHERE từ tham số lọc trên URL.
     *
     * Ngữ nghĩa khớp bản Lovable (san-pham.index.tsx):
     *   q        — tìm trong name + brand + sku
     *   category — theo SLUG danh mục, không phải id
     *   brand / shape / material / gender — khớp chính xác
     *   max      — giá <= max
     */
    private static function buildFilter(array $f): array
    {
        $conds  = ['p.is_visible = 1'];
        $params = [];

        // Tìm kiếm tự do.
        //
        // Collation utf8mb4_unicode_ci khiến LIKE bỏ qua cả HOA/thường lẫn
        // DẤU, nên gõ "kinh mat" vẫn ra "Kính mát" — tiện hơn hẳn bản
        // JavaScript vốn chỉ hạ chữ thường rồi so khớp nguyên văn.
        if (!empty($f['q'])) {
            // TÁCH TỪ rồi bắt khớp TẤT CẢ các từ, mỗi từ khớp ở bất kỳ đâu.
            //
            // Trước đây cả câu được ghép thành một chuỗi liền: gõ "gọng titan"
            // sinh ra LIKE '%gọng titan%', mà tên sản phẩm là "Gọng kính Titan
            // Vin T01" — hai từ cách nhau bởi chữ "kính" nên không khớp, và
            // người dùng nhận về 0 kết quả cho một truy vấn hoàn toàn hợp lý.
            //
            // Nay mỗi từ là một điều kiện riêng nối bằng AND, trong mỗi điều
            // kiện thì OR qua tên / thương hiệu / SKU. "gọng titan" thành
            // (khớp "gọng") AND (khớp "titan") — thứ tự và khoảng cách giữa
            // các từ không còn quan trọng.
            //
            // Giới hạn 6 từ: mỗi từ thêm 3 điều kiện LIKE, người gõ cả một câu
            // dài không nên biến thành câu truy vấn hàng chục phép quét bảng.
            $words = preg_split('/\s+/u', trim((string) $f['q']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $words = array_slice($words, 0, 6);

            foreach ($words as $i => $word) {
                // Ba tham số RIÊNG cho cùng một giá trị, không dùng lại một tên.
                //
                // Bắt buộc phải vậy: dự án tắt EMULATE_PREPARES nên đây là
                // prepared statement thật của MySQL, mà MySQL ánh xạ tham số
                // theo VỊ TRÍ. Viết `LIKE :q OR ... LIKE :q` sẽ khiến PDO chỉ
                // gửi một giá trị cho ba chỗ cần và ném "Invalid parameter number".
                $conds[] = sprintf(
                    '(p.name LIKE :q%1$d_name OR p.brand LIKE :q%1$d_brand OR p.sku LIKE :q%1$d_sku)',
                    $i
                );

                // addcslashes thoát % và _ để người dùng gõ "50%" không biến
                // thành ký tự đại diện khớp mọi thứ
                $needle = '%' . addcslashes($word, '%_\\') . '%';

                $params["q{$i}_name"]  = $needle;
                $params["q{$i}_brand"] = $needle;
                $params["q{$i}_sku"]   = $needle;
            }
        }

        if (!empty($f['category'])) {
            $conds[] = 'p.category_id = (SELECT id FROM categories WHERE slug = :category)';
            $params['category'] = $f['category'];
        }

        /*
         * Bốn tiêu chí phân loại — nhận CẢ chuỗi đơn LẪN mảng.
         *
         * Bản thiết kế "Vin Eyewear Category.dc.html" cho chọn nhiều giá trị
         * trong cùng một nhóm (Vuông + Tròn), nên ở đây là `IN (...)` chứ
         * không còn `= :key`. Chuỗi đơn vẫn chạy y như cũ, nên các liên kết
         * sẵn có (/san-pham?shape=Round từ trang chủ, ?brand=... từ mega menu)
         * không phải sửa.
         *
         * Nhiều giá trị TRONG một nhóm là HOẶC; giữa các nhóm là VÀ — giống
         * hệt phép lọc trong bản thiết kế.
         */
        foreach (['brand' => 'brand', 'shape' => 'frame_shape',
                  'material' => 'material', 'gender' => 'gender'] as $key => $column) {
            $values = self::scalarList($f[$key] ?? null);

            if ($values === []) {
                continue;
            }

            $holders = [];
            foreach ($values as $i => $value) {
                // Tên tham số riêng cho từng giá trị: dự án tắt EMULATE_PREPARES
                // nên MySQL ánh xạ tham số theo VỊ TRÍ, dùng lại một tên cho
                // nhiều chỗ sẽ ném "Invalid parameter number".
                $name = "{$key}_{$i}";
                $holders[] = ":{$name}";
                $params[$name] = $value;
            }

            $conds[] = "p.`{$column}` IN (" . implode(', ', $holders) . ')';
        }

        // Bộ sưu tập (S09). Chỉ thêm điều kiện khi cột đã tồn tại: chưa chạy
        // database/migrations/2026-08-15-bo-suu-tap.sql mà có ai mở link
        // /san-pham?collection=... thì câu lệnh sẽ đổ "Unknown column" và cả
        // trang danh sách sập, thay vì chỉ bỏ qua một bộ lọc.
        if (!empty($f['collection']) && self::hasCollectionColumn()) {
            $conds[] = 'p.collection = :collection';
            $params['collection'] = $f['collection'];
        }

        if (!empty($f['max']) && is_numeric($f['max'])) {
            $conds[] = 'p.price <= :max';
            $params['max'] = (int) $f['max'];
        }

        // Chặn dưới của khoảng giá. Ô "Khoảng giá" trong bản thiết kế là bốn
        // khoảng có hai đầu, không phải một thanh trượt chỉ có chặn trên.
        if (!empty($f['min']) && is_numeric($f['min'])) {
            $conds[] = 'p.price >= :min';
            $params['min'] = (int) $f['min'];
        }

        return [' WHERE ' . implode(' AND ', $conds), $params];
    }

    /**
     * Chuẩn hoá một tham số lọc thành danh sách chuỗi không rỗng, không trùng.
     *
     * Nhận cả `?brand=Ray-Ban` lẫn `?brand[]=Ray-Ban&brand[]=Gucci`. Bỏ qua
     * mọi phần tử không phải giá trị vô hướng: `?brand[][]=x` cho ra mảng lồng
     * mảng, ép (string) một mảng sẽ ném lỗi chứ không phải chỉ ra chuỗi lạ.
     *
     * Cắt còn 20 giá trị: mỗi giá trị là một tham số trong mệnh đề IN, mà
     * không có lý do thật nào để lọc quá 20 thương hiệu cùng lúc — phần dư chỉ
     * có thể đến từ một URL dựng bằng tay để bơm phồng câu truy vấn.
     */
    private static function scalarList(mixed $raw): array
    {
        $items  = is_array($raw) ? $raw : [$raw];
        $values = [];

        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $value = trim((string) $item);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_slice(array_values(array_unique($values)), 0, 20);
    }

    /**
     * Bảng products đã có cột `collection` chưa?
     *
     * Hỏi MySQL một lần rồi nhớ trong biến static — bộ lọc gọi hàm này mỗi
     * lần dựng câu WHERE, mà một trang danh sách chạy tới hai câu (đếm tổng
     * và lấy trang hiện tại).
     */
    private static function hasCollectionColumn(): bool
    {
        static $has = null;

        if ($has === null) {
            try {
                $has = Database::fetchOne("SHOW COLUMNS FROM products LIKE 'collection'") !== null;
            } catch (Throwable $e) {
                $has = false;
            }
        }

        return $has;
    }

    /**
     * Trang danh sách sản phẩm: lọc + đếm + sắp xếp + phân trang, một lượt.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO KHÔNG LỌC BẰNG SQL NHƯ filter()
     *
     * filter() so khớp NGUYÊN VĂN giá trị trong cột (`frame_shape IN (...)`).
     * Cách đó chỉ đúng khi kho ghi mỗi dáng gọng bằng đúng một chuỗi — mà kho
     * này thì không: ba cột phân loại là ô chữ tự do, một ô ghi được nhiều giá
     * trị ("Kim loại bạc / Titanium"), và nhóm "Tính năng tròng" còn không có
     * cột nào cả (đọc ra từ specs + mô tả). Không câu WHERE nào làm được việc
     * đó mà không phải dựng thêm bảng chỉ mục.
     *
     * Nên: SQL lo phần nó làm tốt (hàng đang hiện, danh mục, từ khoá — đều là
     * so khớp thẳng và có chỉ mục), rồi PHP lo phần phân loại đã chuẩn hoá.
     *
     * ĐÁNH ĐỔI, VÀ VÌ SAO CHẤP NHẬN ĐƯỢC: bước này kéo cả tập nền về RAM. Với
     * catalog vài chục tới vài nghìn gọng kính thì đó là vài trăm KB và vài
     * mili giây. Nhưng nó KHÔNG mở rộng vô hạn — hôm nào kho lên hàng chục
     * nghìn dòng thì phải chuyển sang bảng chỉ mục (product_facets: product_id,
     * group, key) dựng lại lúc lưu hàng, chứ đừng nới thêm ở đây.
     *
     * Và cũng KHÔNG có cách nào rẻ hơn cho SỐ ĐẾM ĐỘNG: mỗi con số bên cạnh
     * mỗi lựa chọn là một phép đếm trên tập nền với một bộ điều kiện khác
     * nhau. Làm bằng SQL là vài chục câu COUNT cho mỗi lần tải trang.
     *
     * @param array $f  q, category + các nhóm trong ProductFacets::GROUPS
     * @return array{items:array, total:int, page:int, perPage:int,
     *               totalPages:int, groups:array, hasPrices:bool}
     */
    public static function catalog(array $f, array $priceRanges, int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?? self::PER_PAGE;
        $page    = max(1, $page);

        // 1. Tập nền — chỉ hai tiêu chí "bối cảnh trang" đi vào SQL.
        //
        //    Danh mục và từ khoá KHÔNG phải nhóm lọc trong cột bên trái: chúng
        //    quyết định trang này đang nói về cái gì, nên mọi con số của bộ
        //    lọc đều phải tính bên trong chúng. Bốn nhóm huy hiệu thì ngược
        //    lại — số đếm của chúng phải nhìn thấy nhau (xem ProductFacets).
        [$where, $params] = self::buildFilter([
            'q'        => $f['q'] ?? '',
            'category' => $f['category'] ?? '',
        ]);

        $rows = Database::fetchAll("SELECT p.* FROM products p {$where}", $params);
        $all  = ProductFacets::attach(array_map([self::class, 'decode'], $rows), $priceRanges);

        // 2. Tiêu chí đang bật, đã dọn về mảng khoá chuẩn
        $selected = [];
        foreach (ProductFacets::GROUPS as $group) {
            $selected[$group] = self::scalarList($f[$group] ?? null);
        }

        // 3. Danh sách lựa chọn + số đếm — dựng TRƯỚC khi lọc, vì mỗi nhóm
        //    đếm với một bộ điều kiện khác nhau (bỏ qua chính nó).
        $groups = [];
        foreach (ProductFacets::GROUPS as $group) {
            $order = match ($group) {
                'gender' => ProductTaxonomy::GENDER_ORDER,
                'price'  => array_map('strval', array_keys($priceRanges)),
                default  => [],
            };

            $groups[$group] = ProductFacets::group($all, $selected, $group, $order);
        }

        // 4. Kết quả thật
        $items = ProductFacets::apply($all, $selected);
        $total = count($items);

        self::sortCatalog($items, (string) ($f['sort'] ?? 'newest'));

        return [
            'items'      => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
            'groups'     => $groups,
            'hasPrices'  => ProductFacets::hasPrices($all),
        ];
    }

    /**
     * Sắp xếp trong PHP, giữ ĐÚNG ngữ nghĩa của self::SORTS.
     *
     * Hai bảng phải nói cùng một thứ: filter() (trang tìm kiếm) vẫn sắp bằng
     * SQL, catalog() sắp ở đây. Đổi luật ở một chỗ mà quên chỗ kia thì cùng
     * một lựa chọn "Bán chạy" cho ra hai thứ tự khác nhau tuỳ người dùng vào
     * từ đường nào.
     */
    private static function sortCatalog(array &$items, string $sort): void
    {
        $num = static fn (array $p, string $k): float => (float) ($p[$k] ?? 0);

        $by = match ($sort) {
            'price-asc'  => static fn ($a, $b) => $num($a, 'price') <=> $num($b, 'price'),
            'price-desc' => static fn ($a, $b) => $num($b, 'price') <=> $num($a, 'price'),
            'rating'     => static fn ($a, $b) => $num($b, 'rating') <=> $num($a, 'rating')
                                               ?: $num($b, 'review_count') <=> $num($a, 'review_count'),
            'popular'    => static fn ($a, $b) => $num($b, 'is_featured') <=> $num($a, 'is_featured')
                                               ?: $num($b, 'rating') <=> $num($a, 'rating')
                                               ?: $num($b, 'review_count') <=> $num($a, 'review_count'),
            default      => null,
        };

        // 'newest' — created_at giảm dần. So bằng strcmp chứ không strtotime:
        // cột là DATETIME 'Y-m-d H:i:s', thứ tự chuỗi trùng khít thứ tự thời
        // gian, mà không phải dựng 57 đối tượng ngày tháng.
        $by ??= static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

        // usort của PHP không ổn định trước 8.0 và vẫn không hứa gì về hai
        // phần tử bằng nhau; chốt bằng slug để hai lần tải trang cùng bộ lọc
        // không bao giờ cho ra hai thứ tự khác nhau (phân trang sẽ nhân đôi
        // hoặc nuốt mất sản phẩm nếu thứ tự đổi giữa trang 1 và trang 2).
        usort($items, static fn ($a, $b) => $by($a, $b)
            ?: strcmp((string) ($a['slug'] ?? ''), (string) ($b['slug'] ?? '')));
    }

    public static function findVisibleBySlug(string $slug): ?array
    {
        $row = static::firstWhere(['slug' => $slug, 'is_visible' => 1]);

        return $row === null ? null : self::decode($row);
    }

    /**
     * Sản phẩm cùng danh mục, dùng ở cuối trang chi tiết.
     *
     * Danh mục NULL thì trả rỗng thay vì gom hết sản phẩm chưa phân loại —
     * `category_id = NULL` không khớp gì trong SQL, nhưng nói rõ ý đồ ở đây
     * để người đọc không tưởng là thiếu sót.
     */
    public static function related(?string $categoryId, string $excludeId, int $limit = 4): array
    {
        if ($categoryId === null) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT * FROM products
              WHERE is_visible = 1
                AND category_id = :cat
                AND id <> :id
              ORDER BY is_featured DESC, created_at DESC
              LIMIT ' . max(1, $limit),
            ['cat' => $categoryId, 'id' => $excludeId]
        );

        return array_map([self::class, 'decode'], $rows);
    }

    /**
     * Sản phẩm nổi bật cho trang chủ.
     */
    public static function featured(int $limit = 8): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM products
              WHERE is_visible = 1
                AND is_featured = 1
              ORDER BY created_at DESC
              LIMIT ' . max(1, $limit)
        );

        return array_map([self::class, 'decode'], $rows);
    }

    /**
     * Hàng mới về.
     */
    public static function newest(int $limit = 8): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM products
              WHERE is_visible = 1
              ORDER BY created_at DESC
              LIMIT ' . max(1, $limit)
        );

        return array_map([self::class, 'decode'], $rows);
    }

    // ========================================================================
    // THỬ KÍNH AR
    //
    // Tập gọng AR KHÔNG nằm ở cột products.ar_model_url (cột đó chưa dùng tới)
    // mà ở config/ar.php: mỗi mẫu cần một ảnh PNG nền trong chụp chính diện,
    // nên chỉ vài mẫu có, không phải cả catalog. Trường 'slug' nối mẫu AR với
    // sản phẩm thật — hai hàm dưới đều đi từ danh sách đó.
    // ========================================================================

    /**
     * Slug các mẫu có thể thử AR.
     *
     * Nhớ kết quả lại: thẻ sản phẩm gọi hàm này một lần cho MỖI thẻ, mà một
     * trang danh sách có tới 12 thẻ.
     */
    public static function arSlugs(): array
    {
        static $slugs = null;

        if ($slugs === null) {
            $slugs = array_column(config('ar.frames') ?? [], 'slug');
        }

        return $slugs;
    }

    /**
     * Số mẫu gọng đang thử AR được — chỉ đếm mẫu mà sản phẩm còn hiện.
     *
     * Đúng phép lọc ArController dùng khi dựng danh sách: mẫu nào gắn với sản
     * phẩm đã ẩn hoặc đã xoá thì trang AR bỏ qua, nên con số quảng cáo ở trang
     * chủ cũng phải bỏ theo — nếu không sẽ hứa 6 mẫu mà vào chỉ thấy 4.
     */
    public static function countArReady(): int
    {
        $slugs = static::arSlugs();

        if ($slugs === []) {
            return 0;
        }

        $holders = implode(', ', array_fill(0, count($slugs), '?'));

        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM products WHERE is_visible = 1 AND slug IN ({$holders})",
            $slugs
        );
    }

    /**
     * Lấy nhiều sản phẩm theo id — dùng khi dựng lại giỏ hàng từ session.
     *
     * Trả về mảng đánh khoá theo id để chỗ gọi tra thẳng, không phải duyệt lại.
     */
    public static function findManyById(array $ids): array
    {
        $ids = array_values(array_filter($ids, 'is_string'));

        if ($ids === []) {
            return [];
        }

        $rows = static::where(['id' => $ids, 'is_visible' => 1]);
        $out  = [];

        foreach ($rows as $row) {
            $out[$row['id']] = self::decode($row);
        }

        return $out;
    }

    // ========================================================================
    // TRÌNH BÀY
    // ========================================================================

    /**
     * Giải mã các cột JSON thành mảng PHP.
     *
     * PDO trả cột JSON của MySQL dưới dạng CHUỖI, không tự giải mã. Không có
     * bước này thì view phải json_decode ở từng chỗ dùng, và quên một chỗ là
     * lỗi "không thể duyệt chuỗi" hiện ra giữa trang.
     */
    private static function decode(array $row): array
    {
        foreach (['images', 'specs'] as $column) {
            if (isset($row[$column]) && is_string($row[$column])) {
                $row[$column] = json_decode($row[$column], true) ?? [];
            } elseif (!isset($row[$column])) {
                $row[$column] = [];
            }
        }

        return $row;
    }

    /**
     * Ảnh đại diện. Trả ảnh dự phòng khi sản phẩm chưa có ảnh nào, để bố cục
     * lưới không bị thủng một ô trống.
     */
    public static function image(array $product): string
    {
        return $product['images'][0] ?? '/assets/images/product-1.jpg';
    }

    /**
     * Đường dẫn ẢNH NHỎ của một sản phẩm — dùng cho ô 40–48px.
     *
     * ─────────────────────────────────────────────────────────────────────
     * CÓ THÌ DÙNG, KHÔNG CÓ THÌ TRẢ ẢNH GỐC
     *
     * Ảnh nhỏ do tools/thumbnails.php sinh sẵn và đi theo git. Nhưng cột
     * `images` cho phép dán ĐƯỜNG DẪN BẤT KỲ — nhân viên vừa thêm một ảnh
     * mới mà chưa ai chạy lại script thì chưa có bản nhỏ, và một ảnh trỏ ra
     * miền khác thì không bao giờ có.
     *
     * Cả hai trường hợp đều lui về ảnh gốc: nặng hơn, nhưng vẫn HIỆN. Ô ảnh
     * vỡ trong bảng xổ giỏ hàng tệ hơn nhiều so với việc tải thừa 30KB.
     *
     * is_file() ở đây rẻ: PHP có bộ nhớ đệm stat, và bảng xổ chỉ hỏi 5 lần.
     * ─────────────────────────────────────────────────────────────────────
     */
    public static function thumb(array $product): string
    {
        return self::thumbOf(self::image($product));
    }

    /**
     * Bản nhỏ của một đường dẫn ảnh bất kỳ. Tách riêng để chỗ nào có sẵn
     * đường dẫn (không có cả mảng sản phẩm) vẫn dùng được.
     */
    public static function thumbOf(string $src): string
    {
        $goc = '/assets/images/';

        // Đường dẫn ngoài (http…), hoặc thư mục khác — không có bản nhỏ.
        if (!str_starts_with($src, $goc)) {
            return $src;
        }

        $rel = substr($src, strlen($goc));

        // Đã là ảnh nhỏ rồi thì thôi, đừng lồng thumbs/thumbs/.
        if (str_starts_with($rel, 'thumbs/')) {
            return $src;
        }

        $nho = $goc . 'thumbs/' . $rel;

        return is_file(ROOT_PATH . $nho) ? $nho : $src;
    }

    /**
     * Sản phẩm này có ảnh thật chưa?
     *
     * image() ở trên luôn trả về MỘT đường dẫn, và khi thiếu ảnh thì nó mượn
     * product-1.jpg — tức là ảnh của một mặt hàng khác. Ở lưới danh mục thì
     * chuyện đó thành nói dối người xem: họ thấy một cặp gọng và tưởng đó là
     * món đang đọc. Nơi nào cần phân biệt "chưa có ảnh" với "có ảnh" thì hỏi
     * hàm này trước rồi tự vẽ ô trống, đừng gọi thẳng image().
     *
     * Hay gặp ngay sau khi thêm hàng trong trang quản trị mà chưa kịp tải ảnh.
     */
    public static function hasImage(array $product): bool
    {
        $first = $product['images'][0] ?? '';

        return is_string($first) && trim($first) !== '';
    }

    /**
     * Còn bán được hay không. Kiểm CẢ trạng thái lẫn số lượng tồn: một sản
     * phẩm có status 'in_stock' nhưng tồn 0 vẫn là hết hàng.
     */
    public static function inStock(array $product, int $quantity = 1): bool
    {
        return ($product['status'] ?? '') === 'in_stock'
            && (int) ($product['stock_quantity'] ?? 0) >= $quantity;
    }
}
