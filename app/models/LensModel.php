<?php

/**
 * LensModel — gói tròng kính cắt kèm khi mua gọng, và số đo mắt của khách.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DỮ LIỆU NẰM Ở config/taxonomy.php, KHÔNG PHẢI BẢNG TRONG DB
 *
 * Bốn gói tròng (1.56 chống ánh sáng xanh · đổi màu · 1.67 · đa tròng) là một
 * BẢNG GIÁ DỊCH VỤ, không phải hàng tồn kho: không có mã SKU, không trừ tồn,
 * không ảnh, không trang chi tiết. Cửa hàng nhập phôi tròng theo lô và mài theo
 * đơn kính từng khách.
 *
 * Vì thế nó ở config — người làm giá sửa một file, không cần trang quản trị và
 * không cần migration. Bảng `products` danh mục "trong-kinh" là thứ KHÁC: đó là
 * tròng bán RỜI, có tồn kho và biến thể chiết suất riêng.
 *
 * Class này vẫn đặt tên *Model và nằm cùng thư mục để nơi gọi không phải nhớ
 * gói tròng đến từ đâu — đổi sang bảng DB sau này chỉ phải sửa trong đây.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class LensModel
{
    /**
     * Danh mục có bán kèm GÓI TRÒNG rời (bảng giá trong config/taxonomy.php).
     *
     * MỌI mặt hàng đều đi qua hộp thoại "Chọn hình thức mua" — cái đó không
     * còn phụ thuộc danh mục nữa. Danh sách này chỉ trả lời một câu hẹp hơn:
     * nhánh "mua kèm cắt tròng" của mặt hàng này có phải chọn thêm một gói
     * tròng rời không?
     *
     * Gọng và kính mát thì CÓ — tròng là món thứ hai, lắp vào chúng.
     * Tròng rời và kính áp tròng thì KHÔNG — bản thân chúng đã là tròng, cộng
     * thêm một gói tròng nữa là bán hai cặp tròng cho một đơn và tính tiền cả
     * hai. Nhánh đó của chúng chỉ lấy số đo rồi sang bước xác nhận.
     */
    public const LENS_PACKAGE_CATEGORIES = ['gong-kinh', 'kinh-mat'];

    /**
     * Chữ trong hộp thoại, đổi theo loại hàng.
     *
     * Bản mẫu viết cứng "gọng". Dùng nguyên chữ đó cho một hộp kính áp tròng
     * thì hai lựa chọn đọc thành "Chỉ mua gọng" cho món không ai gọi là gọng.
     *
     * Mỗi mục: [tên món, ghi chú lựa chọn "chỉ mua", nhãn lựa chọn "theo số đo",
     *           ghi chú lựa chọn đó]
     */
    private const WORDING = [
        'gong-kinh' => [
            'gọng',
            'Mua gọng không kèm tròng kính',
            'Mua gọng + cắt tròng',
            'Chọn tròng kính theo số đo của bạn',
        ],
        'kinh-mat' => [
            'kính mát',
            'Mua kính mát không kèm tròng cận',
            'Mua kính mát + cắt tròng cận',
            'Chọn tròng kính theo số đo của bạn',
        ],
        'trong-kinh' => [
            'tròng',
            'Mua tròng chưa mài, lắp sau',
            'Mua tròng + mài theo độ',
            'Mài đúng số đo khúc xạ của bạn',
        ],
        'kinh-ap-trong' => [
            'kính áp tròng',
            'Mua hộp không độ (0.00)',
            'Mua theo độ của tôi',
            'Chọn hộp đúng số đo khúc xạ của bạn',
        ],
    ];

    /**
     * Dải độ cầu (SPH) của ô chọn ở bước "Nhập số đo khúc xạ".
     *
     * Bản thiết kế chỉ đổ ra dải ÂM (−0.25 → −10.00), nhưng ngay phía trên nó
     * lại cho chọn "Viễn thị" và "Lão thị" — hai tật có độ DƯƠNG. Đi theo bản
     * thiết kế đúng từng chữ ở đây nghĩa là hai lựa chọn trong bốn lựa chọn
     * không nhập được số đo của chính nó.
     *
     * Nên dải ở đây chạy cả hai phía. Bước 0.25 diop là bước duy nhất máy mài
     * nhận.
     */
    public const SPH_MIN  = -12.0;
    public const SPH_MAX  = 8.0;
    public const SPH_STEP = 0.25;

    /**
     * ĐỘ TRỤ (CYL) — chỉ hỏi khi mắt đó chọn "Loạn thị".
     *
     * Dải hẹp hơn độ cầu nhiều: loạn trên 6 diop là hiếm và gần như luôn đi
     * kèm bệnh lý giác mạc, những ca đó cửa hàng phải đo tại chỗ chứ không
     * nhận số khách tự gõ.
     *
     * Chạy cả hai phía dấu vì đơn thuốc ghi theo hai quy ước tương đương —
     * "trụ âm" (nhãn khoa hay dùng) và "trụ dương" (một số máy đo in ra). Ép
     * về một phía là bắt khách tự quy đổi, mà quy đổi sai thì mài sai.
     */
    public const CYL_MIN  = -6.0;
    public const CYL_MAX  = 6.0;
    public const CYL_STEP = 0.25;

    /**
     * TRỤC (AXIS) — góc đặt trụ loạn, tính bằng độ.
     *
     * 0..180 chứ không phải 0..360: trục là một ĐƯỜNG THẲNG chứ không phải
     * một hướng, nên 20° và 200° là cùng một trục.
     *
     * BƯỚC 1 ĐỘ, không làm tròn về 5 hay 10. Lệch trục 5 độ đã đủ làm người
     * đeo thấy nhoè và mỏi, mà đơn thuốc thì luôn ghi số nguyên chính xác —
     * làm tròn ở đây là tự tay hỏng một con số vốn đã đúng.
     */
    public const AXIS_MIN  = 0;
    public const AXIS_MAX  = 180;
    public const AXIS_STEP = 1;

    /** Nhớ slug đã tra, để một request thêm hàng không hỏi DB hai lần. */
    private static array $slugCache = [];

    /**
     * Slug danh mục của một mặt hàng.
     *
     * ProductModel trả về dòng `products` nguyên bản — có `category_id` chứ
     * không có slug. Tra thêm ở đây thay vì JOIN sẵn trong ProductModel: chỉ
     * luồng mua hàng cần tới nó, còn mọi trang khác thì không, và thêm một cột
     * vào mọi câu SELECT sản phẩm để phục vụ một chỗ là đắt hơn.
     */
    public static function categorySlug(array $product): ?string
    {
        // Dòng đã kèm sẵn slug (view nào đó JOIN rồi) thì dùng luôn
        if (!empty($product['category_slug'])) {
            return (string) $product['category_slug'];
        }

        $id = $product['category_id'] ?? null;

        if (empty($id)) {
            return null;
        }

        if (!array_key_exists($id, self::$slugCache)) {
            $row = Database::fetchOne('SELECT slug FROM categories WHERE id = :id', ['id' => $id]);
            self::$slugCache[$id] = $row['slug'] ?? null;
        }

        return self::$slugCache[$id];
    }

    /**
     * Nhánh "theo số đo" của mặt hàng này có phải chọn thêm một gói tròng rời không?
     *
     * KHÔNG còn hàm isFittable(): mọi mặt hàng nay đều mở hộp thoại "Chọn hình
     * thức mua", nên câu hỏi "có hỏi hay không" không còn nữa. Câu duy nhất còn
     * lại là câu hẹp này — xem ghi chú ở LENS_PACKAGE_CATEGORIES.
     */
    public static function takesLensPackage(array $product): bool
    {
        $slug = self::categorySlug($product);

        return $slug !== null && in_array($slug, self::LENS_PACKAGE_CATEGORIES, true);
    }

    /**
     * Chữ dùng trong hộp thoại cho một mặt hàng.
     *
     * Danh mục lạ (thêm sau này qua trang quản trị) rơi vào giá trị lui về —
     * nói chung chung nhưng vẫn đọc được, hơn là một hộp thoại thiếu chữ.
     *
     * @return array{noun:string, plainNote:string, rxName:string, rxNote:string}
     */
    public static function wording(array $product): array
    {
        [$noun, $plain, $rxName, $rxNote] = self::WORDING[self::categorySlug($product) ?? '']
            ?? ['sản phẩm', 'Mua như hàng có sẵn', 'Mua theo số đo của tôi', 'Nhập số đo khúc xạ của bạn'];

        return [
            'noun'      => $noun,
            'plainNote' => $plain,
            'rxName'    => $rxName,
            'rxNote'    => $rxNote,
        ];
    }

    // ========================================================================
    // GÓI TRÒNG
    // ========================================================================

    /** Bốn gói tròng đang bán, theo đúng thứ tự trong config. */
    public static function packages(): array
    {
        return config('taxonomy.lens_packages') ?? [];
    }

    /**
     * Một gói theo id, hoặc null.
     *
     * MỌI nơi nhận id gói từ form đều phải đi qua đây. Tin thẳng id gửi lên
     * nghĩa là cho phép đặt tên và GIÁ tuỳ ý cho phần tròng.
     */
    public static function find(?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach (self::packages() as $p) {
            if ($p['id'] === $id) {
                return $p;
            }
        }

        return null;
    }

    // ========================================================================
    // SỐ ĐO MẮT
    // ========================================================================

    /**
     * Bốn loại tật khúc xạ của bước "Nhập số đo khúc xạ", theo bản thiết kế.
     *
     * Lưu vào đơn cùng với độ hai mắt: cùng một con số −2.00 nhưng là cận hay
     * là loạn thì mài ra hai tròng khác nhau, và người mài không đoán được từ
     * riêng con số.
     */
    public const RX_TYPES = [
        'can'  => ['Cận thị',  'Nhìn xa mờ, nhìn gần rõ'],
        'vien' => ['Viễn thị', 'Nhìn gần mờ, nhìn xa rõ hơn'],
        'loan' => ['Loạn thị', 'Nhìn mờ hoặc méo ở mọi khoảng cách'],
        'lao'  => ['Lão thị',  'Khó nhìn gần, thường trên 40 tuổi'],
    ];

    /**
     * Các mốc độ cho ô <select>, từ viễn nặng xuống cận nặng.
     *
     * Xếp từ DƯƠNG xuống ÂM chứ không ngược lại: 0.00 nằm giữa danh sách, và
     * người cận (đại đa số khách) cuộn xuống — cùng chiều với cách đơn thuốc
     * khúc xạ in ra.
     *
     * @return array<int, array{value:string, label:string}>
     */
    public static function sphOptions(): array
    {
        $out = [];

        for ($d = self::SPH_MAX; $d >= self::SPH_MIN; $d -= self::SPH_STEP) {
            // -0.0 là một giá trị thật của float và in ra thành "−0.00"
            $d = $d == 0.0 ? 0.0 : $d;

            $out[] = [
                // Giá trị gửi lên dùng dấu gạch nối ASCII cho dễ so khớp;
                // chỉ NHÃN mới dùng ký tự trừ thật.
                'value' => number_format($d, 2, '.', ''),
                'label' => self::diopterLabel($d),
            ];
        }

        return $out;
    }

    /**
     * Các mức độ trụ cho ô "Độ trụ (CYL)".
     *
     * BỎ giá trị 0.00: ô này chỉ hiện khi mắt đó đã chọn "Loạn thị", mà loạn
     * thị 0 độ thì không phải loạn thị. Để nguyên 0.00 trong danh sách là mời
     * người ta chọn một thứ vô nghĩa rồi phải đoán xem nó có ý gì.
     */
    public static function cylOptions(): array
    {
        $out = [];

        for ($d = self::CYL_MAX; $d >= self::CYL_MIN; $d -= self::CYL_STEP) {
            $d = $d == 0.0 ? 0.0 : $d;

            if ($d == 0.0) {
                continue;
            }

            $out[] = [
                'value' => number_format($d, 2, '.', ''),
                'label' => self::diopterLabel($d),
            ];
        }

        return $out;
    }

    /** Các mức trục cho ô "Trục (AXIS°)": 0°, 1°, … 180°. */
    public static function axisOptions(): array
    {
        $out = [];

        for ($a = self::AXIS_MIN; $a <= self::AXIS_MAX; $a += self::AXIS_STEP) {
            $out[] = [
                'value' => (string) $a,
                'label' => $a . '°',
            ];
        }

        return $out;
    }

    /** Ghi chú của MỘT mắt dài tối đa bấy nhiêu ký tự — xem cleanNote(). */
    public const NOTE_MAX = 60;

    /**
     * Gói số đo của bước "Nhập số đo khúc xạ" thành MỘT chuỗi để lưu và in.
     *
     *     "MP Loạn thị −2.00 / −1.25 × 180° · MT Cận thị −2.25 (hay mỏi)"
     *
     * Vì sao một chuỗi chứ không phải mấy cột riêng: số đo là thứ NHÂN VIÊN
     * ĐỌC rồi nhập vào máy mài, không phải thứ hệ thống tính toán. Không truy
     * vấn nào cần lọc theo độ cận, nên tách cột chỉ làm phình bảng.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * LOẠI TẬT THEO TỪNG MẮT, KHÔNG PHẢI MỘT LOẠI CHUNG CHO CẢ ĐƠN
     *
     * Bản trước hỏi một lần "loại tật khúc xạ" rồi áp cho cả hai mắt. Thực tế
     * hai mắt hay khác nhau — cận một bên, loạn bên kia là chuyện thường — và
     * khi đó khách buộc phải chọn một loại sai cho một trong hai mắt.
     *
     * Kéo theo: CYL và AXIS chỉ có nghĩa với mắt CHỌN LOẠN THỊ. Mắt cận thuần
     * mà mang theo trục 180° là số rác, nên hàm này BỎ hẳn cyl/axis của mắt
     * không loạn thay vì tin vào việc giao diện đã ẩn hai ô đó — giao diện ẩn
     * bằng JavaScript, mà request thì gửi tay được.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Mỗi mắt là một mảng: ['type' =>, 'sph' =>, 'cyl' =>, 'axis' =>, 'note' =>].
     * Nhận mảng chứ không phải mười tham số rời — mười tham số cùng kiểu ?string
     * xếp hàng thì đảo nhầm hai cái là mài nhầm mắt, mà không có gì báo.
     *
     * ĐỦ HAY CHƯA TÍNH THEO ĐỘ CẦU. Không mắt nào có SPH thì trả null, nghĩa
     * rõ ràng là "đo tại cửa hàng" — ghi chú, loại tật hay trục đứng một mình
     * đều không mài kính được. Nhưng một khi đã có ít nhất một mắt đủ số, phần
     * khách ghi cho mắt còn lại vẫn được giữ.
     *
     * @param array{type?:?string, sph?:?string, cyl?:?string, axis?:?string, note?:?string} $od
     * @param array{type?:?string, sph?:?string, cyl?:?string, axis?:?string, note?:?string} $os
     */
    public static function formatRx(array $od, array $os): ?string
    {
        $mp = self::eyeText('MP', $od);
        $mt = self::eyeText('MT', $os);

        if (self::diopter($od['sph'] ?? null) === null
            && self::diopter($os['sph'] ?? null) === null) {
            return null;
        }

        return implode(' · ', array_filter([$mp, $mt]));
    }

    /**
     * Một mắt thành một mẩu chữ: "MP Loạn thị −2.00 / −1.25 × 180° (ghi chú)".
     *
     * Cách viết "sph / cyl × axis°" giống hệt formatSavedRx() dùng cho hồ sơ
     * khúc xạ đã lưu — hai đường vào cùng in ra một dạng, để nhân viên đọc
     * phiếu không phải nhận ra mình đang đọc kiểu nào.
     */
    private static function eyeText(string $short, array $eye): ?string
    {
        $type = (string) ($eye['type'] ?? '');
        $name = isset(self::RX_TYPES[$type]) ? self::RX_TYPES[$type][0] : null;
        $sph  = self::diopter($eye['sph'] ?? null);
        $note = self::cleanNote($eye['note'] ?? null);

        $so = $sph;

        // Trụ và trục chỉ đi kèm mắt loạn thị — xem chú thích ở formatRx().
        if ($so !== null && $type === 'loan') {
            $cyl = self::diopter($eye['cyl'] ?? null, self::CYL_MIN, self::CYL_MAX);

            if ($cyl !== null) {
                $so .= ' / ' . $cyl;

                $axis = self::axis($eye['axis'] ?? null);

                if ($axis !== null) {
                    $so .= ' × ' . $axis . '°';
                }
            }
        }

        // Tên tật đứng một mình (chưa chọn độ) vẫn đáng in: nó cho nhân viên
        // biết khách tự nhận mình bị gì trước khi đo.
        $bits = array_filter([$name, $so]);

        if ($bits === [] && $note === null) {
            return null;
        }

        $text = trim($short . ' ' . implode(' ', $bits));

        return $note === null ? $text : $text . ' (' . $note . ')';
    }

    /** Trục loạn: số nguyên 0..180, ngoài dải thì bỏ. */
    private static function axis(?string $raw): ?int
    {
        $raw = trim((string) $raw);

        if ($raw === '' || !ctype_digit(ltrim($raw, '-'))) {
            return null;
        }

        $val = (int) $raw;

        return $val >= self::AXIS_MIN && $val <= self::AXIS_MAX ? $val : null;
    }

    private static function cleanNote(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $note = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw) ?? '';
        $note = str_replace(['·', '(', ')'], ' ', $note);
        $note = trim(preg_replace('/\s+/u', ' ', $note) ?? '');

        if ($note === '') {
            return null;
        }

        return utf8Length($note) > self::NOTE_MAX
            ? rtrim(utf8Substr($note, 0, self::NOTE_MAX - 1)) . '…'
            : $note;
    }

    /**
     * Gói hồ sơ khúc xạ ĐÃ LƯU của khách (bảng `prescriptions`) thành cùng
     * dạng chuỗi.
     *
     * In đủ cả trụ, trục và khoảng cách đồng tử — khác với số đo gõ tay ở bước
     * trên, vốn chỉ hỏi độ cầu như bản thiết kế vẽ. Hồ sơ đã có sẵn những con
     * số đó rồi thì vứt đi mới là lạ: người mài dùng được hết.
     */
    public static function formatSavedRx(?array $rx): ?string
    {
        if ($rx === null) {
            return null;
        }

        $eye = static function (?string $sph, ?string $cyl, ?string $axis): ?string {
            $s = self::diopter($sph);

            if ($s === null) {
                return null;
            }

            $c = self::diopter($cyl);

            if ($c !== null) {
                $s .= ' / ' . $c;

                if ($axis !== null && $axis !== '') {
                    $s .= ' × ' . (int) $axis . '°';
                }
            }

            return $s;
        };

        $bits = [];
        $od   = $eye($rx['od_sph'] ?? null, $rx['od_cyl'] ?? null, $rx['od_axis'] ?? null);
        $os   = $eye($rx['os_sph'] ?? null, $rx['os_cyl'] ?? null, $rx['os_axis'] ?? null);

        if ($od !== null) { $bits[] = 'MP ' . $od; }
        if ($os !== null) { $bits[] = 'MT ' . $os; }

        if (($rx['pd'] ?? null) !== null && $rx['pd'] !== '') {
            $bits[] = 'PD ' . (int) round((float) $rx['pd']);
        }

        return $bits === [] ? null : implode(' · ', $bits);
    }

    /**
     * Một ô độ -> nhãn đã chuẩn hoá ("-2" -> "−2.00"), hoặc null nếu để trống.
     *
     * Luôn hai chữ số thập phân vì độ kính đi theo bước 0.25 — "−2" và "−2.00"
     * là một, nhưng in ra khác nhau thì người đọc phải dừng lại nghĩ.
     */
    private static function diopter(?string $raw, ?float $min = null, ?float $max = null): ?string
    {
        $raw = trim(str_replace(['−', ','], ['-', '.'], (string) $raw));

        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        $val = (float) $raw;

        // Mặc định là dải ĐỘ CẦU; độ trụ có dải hẹp hơn nên truyền vào riêng.
        // Kiểm dải ở đây chứ không tin <select>: giá trị gửi lên sửa tay được,
        // và một con số ngoài dải lọt xuống phiếu là một cặp tròng mài hỏng.
        if ($val < ($min ?? self::SPH_MIN) || $val > ($max ?? self::SPH_MAX)) {
            return null;
        }

        // Làm tròn về bước 0.25 — máy mài không nhận số lẻ hơn thế
        return self::diopterLabel(round($val * 4) / 4);
    }

    /**
     * Dấu trừ dùng ký tự trừ THẬT (U+2212), giống mọi con số âm khác trên site.
     * Ô <select> chỉ gửi lên dấu gạch nối ASCII, nên đổi ở đây.
     */
    private static function diopterLabel(float $val): string
    {
        return ($val > 0 ? '+' : ($val < 0 ? '−' : '')) . number_format(abs($val), 2, '.', '');
    }
}
