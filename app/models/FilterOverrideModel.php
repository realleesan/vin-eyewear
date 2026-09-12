<?php

/**
 * FilterOverrideModel — tuỳ biến tiêu chí lọc do cửa hàng đặt.
 *
 * Bảng `filter_overrides`. Cửa hàng sửa ở /quan-tri/tieu-chi-loc.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * BẢNG ĐÈ, KHÔNG PHẢI DANH SÁCH CHỦ
 *
 * Tiêu chí của Kiểu dáng · Chất liệu · Giới tính · Màu gọng không do ai khai:
 * ProductTaxonomy RÚT chúng ra từ chữ người nhập hàng gõ. Nhập một gọng
 * "Pantos" là bộ lọc tự có mục "Pantos".
 *
 * Bảng này chỉ nói: mục ĐÃ CÓ ấy hiện tên gì, gộp vào đâu, có ẩn không, xếp
 * thứ mấy. Thiếu một dòng thì mọi thứ chạy y như trước khi có bảng — khác hẳn
 * một bảng danh sách chủ, nơi thiếu dòng nghĩa là sản phẩm biến mất khỏi bộ
 * lọc. Xem khối chú thích đầu file migration.
 *
 * ⚠ ĐỪNG biến nó thành danh sách chủ. Cả tính an toàn của tính năng này nằm ở
 * chỗ bảng rỗng = site chạy nguyên như cũ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CÒN ĐƯỜNG LÙI KHI BẢNG CHƯA DỰNG
 *
 * Deploy của dự án là FTP đẩy file, còn CSDL nâng cấp tay ở phpMyAdmin — hai
 * việc rời nhau và đã có ngày lệch pha thật (xem LensOptionModel). Mã lên
 * trước mà bảng chưa có thì forGroup() trả mảng rỗng, tức là "không đè gì
 * cả" — trang danh mục chạy bình thường, không ai ngoài kia nhận ra.
 *
 * Database::tableExists() tự nhớ kết quả nên không tốn truy vấn mỗi lượt.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class FilterOverrideModel extends BaseModel
{
    protected static string $table = 'filter_overrides';

    /**
     * Nhóm được phép tuỳ biến => nhãn ở khu quản trị.
     *
     * CSDL không chặn được `group_key` lạ (một bảng cho mọi nhóm), nên đây là
     * chỗ DUY NHẤT chặn — cùng lối với LensOptionModel::GROUPS.
     *
     * BỐN NHÓM TRÒNG KÍNH KHÔNG CÓ Ở ĐÂY, và đó là chủ ý: chúng đã có danh
     * sách chủ riêng ở `lens_options` với đủ sửa nhãn, ẩn, xếp thứ tự. Cho
     * chúng thêm một lớp đè nữa là hai chỗ cùng quyết một cái nhãn.
     */
    public const GROUPS = [
        'color'      => 'Màu gọng',
        'shape'      => 'Kiểu dáng',
        'material'   => 'Chất liệu',
        'gender'     => 'Giới tính',
        'brand'      => 'Thương hiệu',
        'collab'     => 'Bộ sưu tập hợp tác',
        'eco'        => 'Chất liệu tái chế',
    ];

    /** Bảng đã dựng chưa — chưa thì mọi hàm dưới đây trả về "không đè gì". */
    public static function available(): bool
    {
        return Database::tableExists('filter_overrides');
    }

    /**
     * Toàn bộ dòng đè của một nhóm, khoá theo `option_key`.
     *
     * Nhớ kết quả trong một request: trang danh mục hỏi cùng một nhóm nhiều
     * lần (một lần lúc gắn facet, một lần lúc dựng danh sách lựa chọn).
     *
     * @return array<string, array{label:?string, merge_into:?string,
     *                             synonyms:array<int,string>, is_visible:bool,
     *                             sort_order:int}>
     */
    public static function forGroup(string $group): array
    {
        static $nho = [];

        if (isset($nho[$group])) {
            return $nho[$group];
        }

        if (!isset(self::GROUPS[$group]) || !self::available()) {
            return $nho[$group] = [];
        }

        $out = [];

        foreach (Database::fetchAll(
            'SELECT * FROM filter_overrides WHERE group_key = :g ORDER BY sort_order ASC, id ASC',
            ['g' => $group]
        ) as $r) {
            $nhan = trim((string) ($r['label'] ?? ''));
            $gop  = trim((string) ($r['merge_into'] ?? ''));

            $out[(string) $r['option_key']] = [
                // Chuỗi RỖNG cũng coi như "không đè": ô nhãn để trống ở form
                // quản trị gửi lên '' chứ không phải NULL, mà ý người dùng khi
                // xoá trắng ô ấy là "trả về tên mặc định".
                'label'      => $nhan !== '' ? $nhan : null,
                'merge_into' => $gop !== '' ? $gop : null,
                'synonyms'   => self::tachSyn((string) ($r['synonyms'] ?? '')),
                'is_visible' => (int) ($r['is_visible'] ?? 1) === 1,
                'sort_order' => (int) ($r['sort_order'] ?? 0),
            ];
        }

        return $nho[$group] = $out;
    }

    /**
     * Bảng tra "slug người nhập gõ" => "khoá chuẩn", dựng từ cột `synonyms`.
     *
     * Đây là thứ làm cho việc THÊM tiêu chí có tác dụng thật: thêm một dòng
     * không kèm đồng nghĩa nào thì nó không khớp món hàng nào, và một mục đếm
     * 0 mờ tịt thì thêm cũng như không.
     *
     * @return array<string, string>
     */
    public static function synonymMap(string $group): array
    {
        $map = [];

        foreach (self::forGroup($group) as $khoa => $o) {
            foreach ($o['synonyms'] as $syn) {
                $map[$syn] = $khoa;
            }
        }

        return $map;
    }

    /** 'pantos, pantos-tron' => ['pantos', 'pantos-tron'] */
    private static function tachSyn(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }

        $ra = [];

        foreach (explode(',', $csv) as $mau) {
            $slug = slugify(trim($mau));

            if ($slug !== '') {
                $ra[] = $slug;
            }
        }

        return array_values(array_unique($ra));
    }

    // ========================================================================
    // GHI — chỉ màn /quan-tri/tieu-chi-loc gọi tới
    // ========================================================================

    /** Bảng đã dựng chưa — màn quản trị hỏi để biết có vẽ form hay không. */
    public static function editable(): bool
    {
        return self::available();
    }

    /** Mọi dòng đè của một nhóm, kể cả dòng đang ẩn. */
    public static function ofGroup(string $group): array
    {
        if (!isset(self::GROUPS[$group]) || !self::available()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT * FROM filter_overrides WHERE group_key = :g ORDER BY sort_order ASC, id ASC',
            ['g' => $group]
        );
    }

    public static function findRow(string $id): ?array
    {
        if ($id === '' || !self::available()) {
            return null;
        }

        return Database::fetchOne('SELECT * FROM filter_overrides WHERE id = :id', ['id' => $id]);
    }

    public static function findByKey(string $group, string $key): ?array
    {
        if (!self::available()) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM filter_overrides WHERE group_key = :g AND option_key = :k',
            ['g' => $group, 'k' => $key]
        );
    }

    /**
     * Thêm hoặc sửa một dòng đè.
     *
     * Một hàm cho cả hai vì màn quản trị không phân biệt: cửa hàng bấm "Lưu"
     * trên một tiêu chí CHƯA có dòng đè thì đó là thêm, có rồi thì là sửa —
     * mà từ phía họ vẫn chỉ là "đặt lại tên cho mục này".
     */
    public static function save(
        string $group,
        string $key,
        ?string $label,
        ?string $mergeInto,
        ?string $synonyms
    ): void {
        if (!isset(self::GROUPS[$group]) || $key === '' || !self::available()) {
            return;
        }

        $co = self::findByKey($group, $key);

        if ($co !== null) {
            Database::execute(
                'UPDATE filter_overrides
                    SET label = :l, merge_into = :m, synonyms = :s
                  WHERE id = :id',
                ['l' => $label, 'm' => $mergeInto, 's' => $synonyms, 'id' => $co['id']]
            );

            return;
        }

        Database::execute(
            'INSERT INTO filter_overrides (id, group_key, option_key, label, merge_into, synonyms)
             VALUES (:id, :g, :k, :l, :m, :s)',
            ['id' => uuid(), 'g' => $group, 'k' => $key,
             'l' => $label, 'm' => $mergeInto, 's' => $synonyms]
        );
    }

    public static function setVisible(string $id, bool $hien): void
    {
        if (!self::available()) {
            return;
        }

        Database::execute(
            'UPDATE filter_overrides SET is_visible = :v WHERE id = :id',
            ['v' => $hien ? 1 : 0, 'id' => $id]
        );
    }

    /**
     * XOÁ THẬT, và ở bảng này thì xoá là an toàn.
     *
     * Khác hẳn `lens_options`, nơi xoá một dòng làm mồ côi mọi sản phẩm còn
     * mang khoá đó. Bảng này không giữ dữ liệu gốc nào: xoá một dòng đè chỉ
     * là bỏ phần tuỳ biến, tiêu chí quay về tên máy tự dựng và hàng hoá không
     * suy suyển. Vì thế màn quản trị cho nút Xoá thật, không phải nút Ẩn.
     */
    public static function remove(string $id): void
    {
        if (!self::available()) {
            return;
        }

        Database::execute('DELETE FROM filter_overrides WHERE id = :id', ['id' => $id]);
    }

    /** Đổi chỗ với mục liền kề trong cùng nhóm. */
    public static function move(string $id, string $huong): void
    {
        $row = self::findRow($id);

        if ($row === null) {
            return;
        }

        /*
         * ĐÁNH SỐ LẠI CHỈ NHỮNG DÒNG ĐÃ GHIM, KHÔNG PHẢI CẢ NHÓM.
         *
         * `sort_order > 0` nghĩa là "đã ghim" — ProductModel::catalog() kéo
         * đúng những dòng ấy lên đầu bộ lọc. Một dòng đè sinh ra vì lý do khác
         * (đổi tên, gộp, ẩn) mang số 0 và phải NẰM YÊN ở thứ tự tự nhiên.
         *
         * ⚠ Bản đầu đánh số lại CẢ NHÓM. Hậu quả: cửa hàng đổi tên ba mục rồi
         * bấm ↑ trên mục thứ tư là cả bốn nhảy lên đầu bộ lọc, dù ba mục kia
         * họ chưa hề đụng tới thứ tự. Đừng gộp hai danh sách này lại.
         */
        $ghim = [];

        foreach (self::ofGroup((string) $row['group_key']) as $r) {
            if ((int) $r['sort_order'] > 0) {
                $ghim[] = $r;
            }
        }

        /* Bấm ↑ trên một mục CHƯA ghim = ghim nó vào cuối khối đã ghim, rồi
           mới nhích một bậc. Không có bước này thì nút ↑ đầu tiên của mỗi mục
           không làm gì cả, và người bấm tưởng nút hỏng. */
        $i = null;

        foreach ($ghim as $n => $r) {
            if ($r['id'] === $row['id']) {
                $i = $n;
                break;
            }
        }

        if ($i === null) {
            $ghim[] = $row;
            $i      = count($ghim) - 1;
        }

        $j = $huong === 'len' ? $i - 1 : $i + 1;

        /* Đã ở mép rồi thì chỉ ghim (nếu vừa thêm vào), không đổi chỗ. */
        if (isset($ghim[$j])) {
            [$ghim[$i], $ghim[$j]] = [$ghim[$j], $ghim[$i]];
        }

        foreach ($ghim as $n => $r) {
            Database::execute(
                'UPDATE filter_overrides SET sort_order = :s WHERE id = :id',
                ['s' => ($n + 1) * 10, 'id' => $r['id']]
            );
        }
    }
}
