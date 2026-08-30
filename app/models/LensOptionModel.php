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

        $rows = Database::fetchAll(
            'SELECT group_key, option_key, label, note, sort_order, is_visible
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

    /** Một hàng giả cùng hình dạng với hàng thật, cho đường lùi ở trên. */
    private static function gia(string $key, string $label, int $sort, string $note = ''): array
    {
        return [
            'group_key'  => '',
            'option_key' => $key,
            'label'      => $label,
            'note'       => $note === '' ? null : $note,
            'sort_order' => $sort,
            'is_visible' => 1,
        ];
    }
}
