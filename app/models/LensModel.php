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

    /** Ghi chú của MỘT mắt dài tối đa bấy nhiêu ký tự — xem cleanNote(). */
    public const NOTE_MAX = 60;

    /**
     * Gói lựa chọn của bước "Nhập số đo khúc xạ" thành MỘT chuỗi để lưu và in.
     *
     *     "Cận thị · MP −2.00 (hay mỏi khi đọc) · MT −2.25"
     *
     * Vì sao một chuỗi chứ không phải mấy cột riêng: số đo là thứ NHÂN VIÊN
     * ĐỌC rồi nhập vào máy mài, không phải thứ hệ thống tính toán. Không truy
     * vấn nào cần lọc theo độ cận, nên tách cột chỉ làm phình bảng. Ghi chú đi
     * theo đúng lối đó: nó cũng là thứ để người đọc, và nó gắn với MỘT MẮT nên
     * phải nằm cạnh con số của mắt đó chứ không dồn xuống một ô chung cuối form
     * — dồn chung thì đọc xong vẫn phải đoán ghi chú nói về mắt nào.
     *
     * Trả null khi không có gì để nói — null nghĩa rõ ràng là "đo tại cửa
     * hàng", còn chuỗi rỗng thì không.
     *
     * ĐỦ HAY CHƯA TÍNH THEO SỐ ĐO, KHÔNG TÍNH GHI CHÚ. Ghi chú không mài kính
     * được: hai ô ghi chú kín mít mà không chọn độ nào thì vẫn là "đo tại cửa
     * hàng". Nhưng một khi phần số đo đã đủ dùng thì ghi chú của mắt còn lại
     * vẫn được giữ, kể cả mắt đó không chọn độ ("MT (mắt này không cần độ)") —
     * đó thường lại là ghi chú đáng đọc nhất.
     */
    public static function formatRx(
        ?string $type,
        ?string $od,
        ?string $os,
        ?string $odNote = null,
        ?string $osNote = null
    ): ?string {
        $typeName = $type !== null && isset(self::RX_TYPES[$type])
            ? self::RX_TYPES[$type][0]
            : null;

        $odLabel = self::diopter($od);
        $osLabel = self::diopter($os);
        $odNote  = self::cleanNote($odNote);
        $osNote  = self::cleanNote($osNote);

        // Chỉ mỗi tên tật, không con số nào -> chưa đủ để mài. Coi như trống.
        $doCount = ($typeName !== null ? 1 : 0)
                 + ($odLabel !== null ? 1 : 0)
                 + ($osLabel !== null ? 1 : 0);

        if ($doCount < 2) {
            return null;
        }

        $eye = static fn (string $short, ?string $label, ?string $note): ?string => match (true) {
            $label !== null && $note !== null => $short . ' ' . $label . ' (' . $note . ')',
            $label !== null                   => $short . ' ' . $label,
            $note  !== null                   => $short . ' (' . $note . ')',
            default                           => null,
        };

        $bits = array_filter([
            $typeName,
            $eye('MP', $odLabel, $odNote),
            $eye('MT', $osLabel, $osNote),
        ]);

        return implode(' · ', $bits);
    }

    /**
     * Dọn một ghi chú khách gõ tay trước khi ghép vào chuỗi số đo.
     *
     * Ba việc, mỗi việc vì một lý do riêng:
     *
     *   · Bỏ ký tự xuống dòng và ký tự điều khiển — chuỗi này in ra trên MỘT
     *     dòng ở giỏ hàng, trang xác nhận và phiếu của cửa hàng; một ký tự
     *     xuống dòng lọt vào là vỡ cả ba chỗ.
     *   · Bỏ '·', '(' và ')' — đó là dấu phân cách và dấu bọc của chính định
     *     dạng này. Để nguyên thì một ghi chú như "mắt (trái) yếu hơn" đọc ra
     *     thành thứ không phân tích ngược được nữa.
     *   · Cắt còn NOTE_MAX ký tự — cột `prescription` có trần, và một ghi chú
     *     dài hơn dòng chữ trên phiếu thì người mài cũng không đọc.
     *
     * Cắt bằng utf8Substr chứ không phải substr: substr đếm byte, cắt giữa một
     * chữ tiếng Việt có dấu sẽ để lại nửa ký tự hỏng.
     */
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
    private static function diopter(?string $raw): ?string
    {
        $raw = trim(str_replace(['−', ','], ['-', '.'], (string) $raw));

        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }

        $val = (float) $raw;

        if ($val < self::SPH_MIN || $val > self::SPH_MAX) {
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
