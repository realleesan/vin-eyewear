<?php

/**
 * LensOptionModel — bốn danh sách lựa chọn của thuộc tính tròng kính.
 *
 * Bảng `lens_options` giữ cả bốn nhóm: loại tròng · chiết suất · màu tròng ·
 * tính năng/lớp phủ. Cửa hàng sửa chúng ở /quan-tri/thuoc-tinh-trong.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỖI NHÓM DÙNG Ở BA CHỖ, VÀ BA CHỖ ẤY CẦN BA THỨ KHÁC NHAU
 *
 *   form nhập hàng   cần CẢ mục đang ẩn (hàng cũ còn gắn khoá đó, không in ra
 *                    thì người sửa hàng tưởng ô trống)          -> all()
 *   bộ lọc khách xem cần mục ĐANG HIỆN, đúng thứ tự cửa hàng xếp -> visible()
 *   in một khoá ra chữ  cần bảng tra khoá => nhãn                -> labels()
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CÒN ĐƯỜNG LÙI VỀ config, GIỐNG LensModel::packages()
 *
 * Deploy của dự án là FTP đẩy file, còn CSDL thì nâng cấp bằng tay ở
 * phpMyAdmin — hai việc rời nhau, và đã có ngày chúng lệch pha thật
 * (26/08/2026: CSDL đi trước mã một bước, mọi trang quản trị trả 500).
 *
 * Lần này lệch theo chiều ngược lại: mã lên trước, bảng chưa có. Hàm này chạy
 * trên TRANG DANH MỤC và trong FORM NHẬP HÀNG, nên để nó ném lỗi 1146 nghĩa là
 * cả hai chỗ đó trắng xoá cho tới khi có người mở phpMyAdmin.
 *
 * Rơi về mảng trong config thì bộ lọc vẫn có đúng những mục như hôm qua —
 * không ai ngoài kia nhận ra điều gì. Khu quản trị thì có nhận ra: màn thuộc
 * tính tự nói bảng chưa dựng và chỉ cách chạy file nâng cấp (xem editable()).
 *
 * tableExists() tự nhớ kết quả nên không tốn thêm truy vấn nào mỗi lượt.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LensOptionModel extends BaseModel
{
    protected static string $table = 'lens_options';

    /**
     * Bốn nhóm hợp lệ => nhãn hiển thị ở khu quản trị.
     *
     * CSDL không tự chặn được `group_key` lạ (một bảng cho cả bốn nhóm, xem
     * chú thích trong database/schema.sql), nên đây là chỗ DUY NHẤT chặn.
     * Thêm nhóm thứ năm chỉ cần thêm một dòng ở đây và một dòng ở CỘT bên dưới.
     */
    public const GROUPS = [
        'loai-trong' => 'Loại tròng',
        'chiet-suat' => 'Chiết suất',
        'lop-phu'    => 'Tính năng / lớp phủ',
        'mau-trong'  => 'Màu tròng',
    ];

    /**
     * Nhóm nào đọc/ghi vào cột nào của `products`.
     *
     * 'csv' = cột chứa nhiều khoá cách nhau bởi dấu phẩy (chọn nhiều)
     * 'one' = cột chứa đúng một khoá (chọn một)
     *
     * Bảng này là chỗ nối DUY NHẤT giữa bốn danh sách và bảng products. Rải
     * tên cột ra khắp form nhập hàng, ProductTaxonomy và controller lọc thì
     * đổi một cột là ba nơi phải nhớ sửa.
     */
    public const COLUMNS = [
        'loai-trong' => ['column' => 'lens_types',    'multi' => true],
        'chiet-suat' => ['column' => 'lens_indexes',  'multi' => true],
        'lop-phu'    => ['column' => 'lens_coatings', 'multi' => true],
        'mau-trong'  => ['column' => 'lens_color',    'multi' => false],
    ];

    /** @var array<string, array<int, array<string,mixed>>>|null nhớ theo nhóm */
    private static ?array $cache = null;

    /**
     * Quên bảng nhớ. Mọi hàm GHI bên dưới đều gọi.
     *
     * Trong một request web thì gần như thừa — màn quản trị ghi xong là
     * redirect, request sau nạp lại từ đầu. Nhưng nó KHÔNG thừa ở hai chỗ:
     * script CLI (nhập liệu, kiểm thử) đọc rồi ghi rồi đọc lại trong cùng một
     * tiến trình, và bất kỳ ai sau này gộp hai thao tác vào một request. Đã
     * đâm phải khi kiểm: thêm một mục xong đọc lại vẫn ra danh sách cũ.
     */
    private static function quenCache(): void
    {
        self::$cache = null;
    }

    /**
     * Bảng đã dựng chưa. Màn quản trị hỏi câu này để biết nên vẽ form sửa hay
     * vẽ hướng dẫn chạy file nâng cấp.
     */
    public static function editable(): bool
    {
        return Database::tableExists('lens_options');
    }

    /**
     * TẤT CẢ lựa chọn của một nhóm, kể cả mục đang ẩn.
     *
     * TÊN LÀ ofGroup() CHỨ KHÔNG PHẢI all(): BaseModel đã có all() với chữ ký
     * khác (all(?string $orderBy, ?int $limit)), đè lên nó là lỗi fatal ngay
     * lúc nạp lớp — đã đâm phải một lần khi viết file này.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function ofGroup(string $group): array
    {
        if (!isset(self::GROUPS[$group])) {
            return [];
        }

        if (self::$cache === null) {
            self::$cache = self::napTatCa();
        }

        return self::$cache[$group] ?? [];
    }

    /** Chỉ những mục đang hiện — dùng cho bộ lọc phía khách. */
    public static function visible(string $group): array
    {
        return array_values(array_filter(
            self::ofGroup($group),
            static fn (array $o) => !empty($o['is_visible'])
        ));
    }

    /**
     * Bảng tra khoá => nhãn của một nhóm, GỒM CẢ mục đang ẩn.
     *
     * Gồm cả mục ẩn là có chủ ý: hàng cũ vẫn mang khoá đó, và in ra "xam-khoi"
     * thay vì "Xám khói" ở bảng thông số thì trông như dữ liệu hỏng.
     *
     * @return array<string,string>
     */
    public static function labels(string $group): array
    {
        $out = [];

        foreach (self::ofGroup($group) as $o) {
            $out[(string) $o['option_key']] = (string) $o['label'];
        }

        return $out;
    }

    /**
     * Nạp CẢ BỐN NHÓM trong MỘT truy vấn rồi tự chia.
     *
     * Trang danh mục hỏi cả bốn nhóm trong cùng một lượt tải; bốn truy vấn cho
     * bốn bảng vài chục dòng là ba lượt đi lại thừa.
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    private static function napTatCa(): array
    {
        if (!self::editable()) {
            return self::tuConfig();
        }

        /* PHẢI CÓ `id`: màn quản trị dựng liên kết Sửa và hai nút ↑↓ từ nó.
           Bản đầu liệt kê thiếu cột này và cả trang gãy ở dòng đầu tiên với
           "Undefined array key id" — bắt được lúc chạy thử, không phải lúc
           đọc lại. */
        $rows = Database::fetchAll(
            'SELECT id, group_key, option_key, label, note, sort_order, is_visible
               FROM lens_options
              ORDER BY group_key, sort_order, label'
        );

        $out = array_fill_keys(array_keys(self::GROUPS), []);

        foreach ($rows as $r) {
            $g = (string) $r['group_key'];

            // Nhóm lạ trong CSDL (ai đó thêm tay) thì bỏ qua, đừng để nó nổi
            // lên giao diện dưới một tiêu đề không có trong GROUPS.
            if (!isset($out[$g])) {
                continue;
            }

            $out[$g][] = $r;
        }

        return $out;
    }

    /**
     * Đường lùi khi chưa có bảng: dựng lại đúng bốn danh sách từ config.
     *
     * KHÔNG có nhóm 'mau-trong' vì config chưa bao giờ có danh sách màu —
     * `lens_color` xưa nay là ô chữ tự do. Nhóm ấy trả rỗng, và bộ lọc tự bỏ
     * qua nhóm rỗng, nên chưa chạy migration thì trang tròng kính thiếu đúng
     * một ô lọc chứ không hỏng.
     */
    private static function tuConfig(): array
    {
        $out = array_fill_keys(array_keys(self::GROUPS), []);
        $stt = 0;

        foreach ((array) config('taxonomy.lens_types', []) as $t) {
            $out['loai-trong'][] = self::gia((string) $t['id'], (string) $t['name'], $stt += 10, (string) ($t['desc'] ?? ''));
        }

        $stt = 0;
        foreach ((array) config('eyewear.rx_indexes', []) as $key => $label) {
            $out['chiet-suat'][] = self::gia((string) $key, (string) $label, $stt += 10);
        }

        $stt = 0;
        foreach ((array) config('eyewear.coatings', []) as $key => $label) {
            $out['lop-phu'][] = self::gia((string) $key, (string) $label, $stt += 10);
        }

        return $out;
    }

    // ========================================================================
    // GHI — chỉ màn /quan-tri/thuoc-tinh-trong gọi tới
    // ========================================================================

    /**
     * Một lựa chọn theo id, kể cả đang ẩn. null nếu không có.
     *
     * Đọc THẲNG bảng chứ không lọc trong self::$cache: màn quản trị vừa ghi
     * xong là đọc lại ngay, mà cache thì chụp từ đầu request.
     */
    public static function findRow(string $id): ?array
    {
        if (!self::editable()) {
            return null;
        }

        return Database::fetchOne('SELECT * FROM lens_options WHERE id = :id', [':id' => $id]);
    }

    /** Một lựa chọn theo cặp (nhóm, khoá) — dùng để chặn khoá trùng. */
    public static function findByKey(string $group, string $key): ?array
    {
        if (!self::editable()) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM lens_options WHERE group_key = :g AND option_key = :k',
            [':g' => $group, ':k' => $key]
        );
    }

    /**
     * Thứ tự cho mục thêm mới: xuống CUỐI nhóm.
     *
     * Không hỏi người dùng đứng thứ mấy — form không có ô thứ tự. Cuối danh
     * sách là chỗ duy nhất an toàn: chen vào giữa là đổi thứ tự bộ lọc mà
     * khách đang nhìn, vì một mục vừa thêm và chưa gắn hàng nào.
     */
    public static function nextSort(string $group): int
    {
        $max = (int) Database::fetchValue(
            'SELECT COALESCE(MAX(sort_order), 0) FROM lens_options WHERE group_key = :g',
            [':g' => $group]
        );

        return $max + 10;
    }

    public static function create(string $group, string $key, string $label, ?string $note): void
    {
        Database::execute(
            'INSERT INTO lens_options (group_key, option_key, label, note, sort_order)
             VALUES (:g, :k, :l, :n, :s)',
            [':g' => $group, ':k' => $key, ':l' => $label, ':n' => $note, ':s' => self::nextSort($group)]
        );

        self::quenCache();
    }

    /**
     * Sửa NHÃN và ghi chú. KHÔNG đụng tới option_key, và đó là điều kiện của
     * cả cơ chế: khoá ấy nằm trong CSV của mọi sản phẩm đã gắn mục này, đổi nó
     * là làm mồ côi toàn bộ số hàng đó — chúng giữ khoá cũ rồi biến mất khỏi
     * bộ lọc mà không báo gì. Xem chú thích ở database/schema.sql.
     */
    public static function updateLabel(string $id, string $label, ?string $note): void
    {
        Database::execute(
            'UPDATE lens_options SET label = :l, note = :n WHERE id = :id',
            [':l' => $label, ':n' => $note, ':id' => $id]
        );

        self::quenCache();
    }

    /** Ẩn / hiện. Thay cho xoá — xem chú thích ở schema.sql. */
    public static function setVisible(string $id, bool $hien): void
    {
        Database::execute(
            'UPDATE lens_options SET is_visible = :v WHERE id = :id',
            [':v' => $hien ? 1 : 0, ':id' => $id]
        );

        self::quenCache();
    }

    /**
     * Đổi chỗ một mục với mục liền kề trong CÙNG nhóm.
     *
     * Hoán vị hai sort_order chứ không cộng/trừ một hằng số: hai mục có thể
     * cách nhau 10 hoặc cách nhau 3 (sau vài lần chèn), nên cộng trừ sẽ có lúc
     * nhảy qua đầu nhau và có lúc không nhúc nhích.
     */
    public static function move(string $id, string $huong): void
    {
        $row = self::findRow($id);

        if ($row === null) {
            return;
        }

        /*
          * HAI TÊN THAM SỐ CHO CÙNG MỘT GIÁ TRỊ (:s1 và :s2), không phải một
          * :s dùng hai lần.
          *
          * PDO của dự án tắt chế độ giả lập prepare, và ở chế độ thật thì mỗi
          * tên chỉ được xuất hiện ĐÚNG MỘT LẦN trong câu lệnh — dùng lại là
          * lỗi HY093 "Invalid parameter number" ngay lúc execute. Đã đâm phải
          * khi viết hàm này.
          *
          * Điều kiện có hai vế vì sort_order KHÔNG duy nhất: hai mục cùng số
          * thì phải lấy id ra phân xử, nếu không nút ↑↓ sẽ không nhúc nhích
          * đúng ở cặp đó.
          */
        $ke = Database::fetchOne(
            $huong === 'len'
                ? 'SELECT * FROM lens_options
                     WHERE group_key = :g AND (sort_order < :s1 OR (sort_order = :s2 AND id < :id))
                     ORDER BY sort_order DESC, id DESC LIMIT 1'
                : 'SELECT * FROM lens_options
                     WHERE group_key = :g AND (sort_order > :s1 OR (sort_order = :s2 AND id > :id))
                     ORDER BY sort_order ASC, id ASC LIMIT 1',
            [
                ':g'  => $row['group_key'],
                ':s1' => $row['sort_order'],
                ':s2' => $row['sort_order'],
                ':id' => $id,
            ]
        );

        // Đã ở đầu (hoặc cuối) nhóm — không có gì để đổi chỗ, im lặng bỏ qua.
        if ($ke === null) {
            return;
        }

        Database::transaction(static function () use ($row, $ke): void {
            Database::execute('UPDATE lens_options SET sort_order = :s WHERE id = :id',
                [':s' => $ke['sort_order'],  ':id' => $row['id']]);
            Database::execute('UPDATE lens_options SET sort_order = :s WHERE id = :id',
                [':s' => $row['sort_order'], ':id' => $ke['id']]);
        });

        self::quenCache();
    }

    /**
     * Số sản phẩm đang gắn một khoá — hiện ở màn quản trị để người sửa biết
     * hậu quả trước khi ẩn một mục.
     *
     * LIKE trên CSV chứ không bảng nối: xem lý do ở chú thích 'coatings' trong
     * config/eyewear.php. Bọc cả cột lẫn mẫu bằng dấu phẩy để 'uv' không khớp
     * nhầm vào 'uv400'.
     */
    public static function usageCount(string $group, string $key): int
    {
        $cot = self::COLUMNS[$group]['column'] ?? null;

        if ($cot === null) {
            return 0;
        }

        /* $cot đi thẳng vào câu SQL vì tên cột KHÔNG ràng buộc tham số được.
           Nó chỉ có thể là một trong bốn giá trị của hằng COLUMNS ngay trên,
           nên đã an toàn — gọi assertSafeIdentifier() vẫn là đúng nếp của dự
           án: ngày ai đó cho hằng ấy nhận giá trị từ nơi khác, chỗ này ném lỗi
           thay vì lặng lẽ nối chuỗi. */
        self::assertSafeIdentifier($cot);

        if (empty(self::COLUMNS[$group]['multi'])) {
            return (int) Database::fetchValue(
                "SELECT COUNT(*) FROM products WHERE `$cot` = :k",
                [':k' => $key]
            );
        }

        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM products
              WHERE CONCAT(',', REPLACE(`$cot`, ' ', ''), ',') LIKE :k",
            [':k' => '%,' . $key . ',%']
        );
    }

    /** Một hàng giả cùng hình dạng với hàng thật, cho đường lùi ở trên. */
    private static function gia(string $key, string $label, int $sort, string $note = ''): array
    {
        return [
            /* `id` rỗng: hàng giả không có bản ghi nào để sửa. Vẫn phải CÓ
               khoá này để hình dạng khớp hàng thật — thiếu nó thì mọi chỗ đọc
               $o['id'] ném warning ở đúng lúc CSDL chưa nâng cấp, tức là lúc
               người ta đang bối rối nhất. */
            'id'         => '',
            'group_key'  => '',
            'option_key' => $key,
            'label'      => $label,
            'note'       => $note === '' ? null : $note,
            'sort_order' => $sort,
            'is_visible' => 1,
        ];
    }
}
