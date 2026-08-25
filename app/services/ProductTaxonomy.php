<?php

/**
 * ProductTaxonomy — lớp CHUẨN HOÁ giữa dữ liệu thô và bộ lọc.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CẦN LỚP NÀY
 *
 * Ba cột `frame_shape`, `material`, `gender` là ô CHỮ TỰ DO trong trang quản
 * trị (xem admin/products/index.php), nên người nhập hàng gõ mỗi lúc một kiểu
 * và trộn cả hai thứ tiếng:
 *
 *     "Vuông (Square)"          "Square / Vuông"        "Square"
 *     "Kim loại bạc / Titanium" "Acetate + Metal"       "Bio-acetate tái chế"
 *     "Nữ / Unisex"             "unisex"
 *
 * Bộ lọc cũ so khớp NGUYÊN VĂN bằng `IN (...)` của SQL, nên ba dòng đầu ra ba
 * huy hiệu khác nhau cho cùng một dáng gọng, và ô ghi hai giá trị chỉ khớp
 * được đúng chuỗi dài y hệt — chọn "Titanium" không ra "Kim loại bạc /
 * Titanium".
 *
 * Ở đây mỗi ô được TÁCH thành nhiều mẩu rồi quy về một KHOÁ CHUẨN. Một sản
 * phẩm khớp TẤT CẢ các khoá tách ra được, không chỉ mẩu đầu tiên.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG MẨU NÀO BỊ VỨT ĐI
 *
 * Mẩu nào không có trong bảng quy đổi thì thành một lựa chọn RIÊNG mang đúng
 * chữ người nhập đã gõ, chứ không bị bỏ qua. Nhờ vậy nhập một dáng gọng lạ
 * vào kho là bộ lọc tự có thêm huy hiệu cho nó — đây là điều kiện để bỏ hẳn
 * danh sách gõ cứng mà không phải sửa file này mỗi lần kho đổi.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHOÁ CHUẨN LÀ GÌ
 *
 * Là chuỗi ngắn không dấu đi vào URL: ?shape[]=cat-eye. KHÔNG PHẢI giá trị
 * trong DB — cột `frame_shape` vẫn giữ nguyên chữ người nhập gõ. Đổi khoá ở
 * đây là làm hỏng các liên kết cũ, nên chỉ THÊM, đừng đổi tên khoá sẵn có.
 *
 * Liên kết cũ dùng nhãn tiếng Anh (/san-pham?shape=Round từ mega menu và khối
 * "chọn theo khuôn mặt" ngoài trang chủ) vẫn chạy: canonical() hạ chúng về
 * slug trước khi so, nên "Round" và "round" cùng trỏ về khoá `round`.
 */

class ProductTaxonomy
{
    /**
     * Bảng quy đổi DÁNG GỌNG.
     *
     * `syn` là các slug đồng nghĩa. So khớp theo hai bước (xem matchToken):
     * bằng nhau tuyệt đối trước, rồi mới tới "chứa nguyên một từ" — nhờ vế sau
     * mà "vuong-flatbar" vẫn về `square`.
     *
     * THỨ TỰ CÁC MỤC Ở ĐÂY KHÔNG PHẢI THỨ TỰ HIỆN RA: huy hiệu xếp theo số
     * sản phẩm giảm dần (ProductFacets::group), nên kho đổi thì cột lọc tự đổi
     * theo mà không phải sửa file này.
     */
    private const SHAPES = [
        'oval'       => ['label' => 'Oval',                  'syn' => ['oval']],
        'square'     => ['label' => 'Vuông (Square)',        'syn' => ['square', 'vuong', 'flatbar']],
        'cat-eye'    => ['label' => 'Mắt mèo (Cat-eye)',     'syn' => ['cat-eye', 'cateye', 'mat-meo']],
        'rectangle'  => ['label' => 'Chữ nhật (Rectangle)',  'syn' => ['rectangle', 'chu-nhat']],
        'round'      => ['label' => 'Tròn (Round)',          'syn' => ['round', 'tron']],
        'wraparound' => ['label' => 'Wraparound / Shield',   'syn' => ['wraparound', 'wrap-around', 'wrap', 'shield', 'curved', 'om-mat']],
        'aviator'    => ['label' => 'Phi công (Aviator)',    'syn' => ['aviator', 'phi-cong', 'pilot']],
        'butterfly'  => ['label' => 'Cánh bướm (Butterfly)', 'syn' => ['butterfly', 'canh-buom', 'buom']],
        'geometric'  => ['label' => 'Hình học / Oversized',  'syn' => ['geometric', 'hinh-hoc', 'oversized', 'qua-kho', 'hexagonal', 'luc-giac', 'da-giac']],
        'rimless'    => ['label' => 'Không viền (Rimless)',  'syn' => ['rimless', 'khong-vien', 'semi-rimless', 'ban-vien']],
        'irregular'  => ['label' => 'Nghệ thuật (Irregular)', 'syn' => ['irregular', 'artistic', 'nghe-thuat', 'bat-doi-xung', 'asymmetric']],
        'wayfarer'   => ['label' => 'Wayfarer',              'syn' => ['wayfarer']],
        'browline'   => ['label' => 'Browline',              'syn' => ['browline', 'clubmaster']],
    ];

    /**
     * Bảng quy đổi CHẤT LIỆU.
     *
     * KHÔNG có 'vang' / 'bac' / 'silver' trong nhóm kim loại, dù kho có ghi
     * "Kim loại vàng": chúng là tên MÀU, mà cột này lắm khi ghi cả màu lẫn
     * chất liệu ("Acetate vàng"). Nhận chúng làm đồng nghĩa của Metal thì mọi
     * gọng acetate màu vàng đều nhảy vào nhóm kim loại. "Kim loại vàng" vẫn
     * về đúng chỗ nhờ mẩu "kim-loai" ở trong nó.
     *
     * Titanium để RIÊNG chứ không gộp vào Metal: nó là tiêu chí người mua hỏi
     * thẳng ("có gọng titan không"), không phải một sắc thái của kim loại. Ô
     * ghi "Kim loại bạc / Titanium" khớp CẢ HAI nhóm nên không mất mát gì.
     */
    private const MATERIALS = [
        'acetate'  => ['label' => 'Acetate',           'syn' => ['acetate', 'axetat', 'bio-acetate', 'bioacetate', 'recycled-acetate']],
        'metal'    => ['label' => 'Kim loại (Metal)',  'syn' => ['metal', 'kim-loai', 'stainless-steel', 'steel', 'thep-khong-gi', 'thep', 'alloy', 'hop-kim', 'aluminium', 'aluminum', 'nhom']],
        'nylon'    => ['label' => 'Nylon & Bio-nylon', 'syn' => ['nylon', 'bio-nylon', 'bionylon']],
        'titanium' => ['label' => 'Titanium',          'syn' => ['titanium', 'titan', 'beta-titanium']],
        'brass'    => ['label' => 'Brass mạ vàng 18K', 'syn' => ['brass', 'dong-thau']],
        'tr90'     => ['label' => 'TR90',              'syn' => ['tr90', 'tr-90']],
        'ultem'    => ['label' => 'Ultem',             'syn' => ['ultem']],
        'mixed'    => ['label' => 'Mixed materials',   'syn' => ['mixed', 'mixed-materials', 'hon-hop', 'ket-hop', 'da-chat-lieu']],
    ];

    /**
     * Bảng quy đổi ĐỐI TƯỢNG.
     *
     * Cột `gender` trong DB nhận male/female/unisex/kids qua ô chọn của trang
     * quản trị, nhưng dữ liệu nhập thẳng bằng SQL có cả "Nữ / Unisex" — và
     * theo yêu cầu, món đó phải hiện ở CẢ HAI huy hiệu. split() lo phần tách,
     * bảng này lo phần quy về khoá.
     *
     * Chú ý 'nam' và 'female': khớp theo RANH GIỚI TỪ chứ không phải chuỗi con
     * (xem matchToken), nếu không thì "female" chứa "male" và mọi món hàng nữ
     * đều thành hàng nam.
     */
    private const GENDERS = [
        'male'   => ['label' => 'Nam',    'syn' => ['male', 'nam', 'men', 'man']],
        'female' => ['label' => 'Nữ',     'syn' => ['female', 'nu', 'women', 'woman', 'ladies']],
        'unisex' => ['label' => 'Unisex', 'syn' => ['unisex', 'ca-hai']],
        'kids'   => ['label' => 'Trẻ em', 'syn' => ['kids', 'kid', 'tre-em', 'children', 'junior']],
    ];

    /**
     * TÍNH NĂNG TRÒNG — nhóm lọc KHÔNG có cột riêng trong bảng products.
     *
     * Vì thế đây là nhóm duy nhất đọc bằng biểu thức chính quy thay vì bảng
     * đồng nghĩa: thông tin nằm rải trong `specs`, `description` và cả tên
     * hàng ("Tròng chống ánh sáng xanh 1.61"), dưới hàng chục cách viết —
     * "Chống UV 99,9%", "100% UVA/UVB", "UV400", "Blue Light + UV 99,9%".
     *
     * Chuỗi đem so đã qua slugify() nên chỉ còn a–z, 0–9 và dấu gạch: mẫu ở
     * đây phải viết bằng '-' chứ đừng viết '\s'.
     *
     * `(^|-)uv([0-9]|-|$)` chứ không phải 'uv' trần: 'uv' trần khớp vào giữa
     * vô số chữ tiếng Việt đã bỏ dấu.
     */
    private const LENS = [
        'uv'           => ['label' => 'Chống UV (100% UVA/UVB)', 're' => '/(^|-)uv([0-9]|-|$)|uva|uvb|cuc-tim/'],
        'blue-light'   => ['label' => 'Chống ánh sáng xanh',     're' => '/blue-?light|anh-sang-xanh/'],
        'rx'           => ['label' => 'Lắp được tròng thuốc',    're' => '/trong-thuoc|quang-hoc|optical|prescription|(^|-)rx(-|$)|lap-trong-can|lap-do/'],
        'polarized'    => ['label' => 'Phân cực (Polarized)',    're' => '/polari[sz]ed|phan-cuc/'],
        'photochromic' => ['label' => 'Đổi màu (Photochromic)',  're' => '/photochromic|doi-mau/'],
        'gradient'     => ['label' => 'Gradient (chuyển màu)',   're' => '/gradient|chuyen-mau|loang-mau/'],
        'mirror'       => ['label' => 'Tráng gương (Mirror)',    're' => '/mirror|trang-guong|phan-quang/'],
        'anti-glare'   => ['label' => 'Chống chói',              're' => '/anti-?glare|chong-choi|chong-loa|chong-phan-chieu/'],
    ];

    /** Nhãn của chip "hàng tái chế" — nhóm một lựa chọn, đi kèm Chất liệu. */
    public const ECO_LABEL = 'Chất liệu tái chế / bio';

    /**
     * Thứ tự cố định của nhóm Đối tượng.
     *
     * Nhóm DUY NHẤT không xếp theo số lượng: bốn huy hiệu này là bốn ô quen
     * mắt, đảo chỗ theo tồn kho thì mỗi lần vào trang chúng lại nằm một nơi.
     */
    public const GENDER_ORDER = ['male', 'female', 'unisex', 'kids'];

    // ========================================================================
    // ĐỌC MỘT SẢN PHẨM
    // ========================================================================

    /**
     * Toàn bộ khoá lọc của một sản phẩm.
     *
     * @return array{
     *   shape: array<string,string>, material: array<string,string>,
     *   gender: array<string,string>, lens: array<string,string>,
     *   brand: array<string,string>, collab: array<string,string>,
     *   collection: array<string,string>, eco: array<string,string>
     * }  mỗi nhóm là bảng khoá => nhãn hiển thị
     */
    public static function of(array $p): array
    {
        [$materials, $eco] = self::materials((string) ($p['material'] ?? ''));
        [$brands, $collab] = self::brands((string) ($p['brand'] ?? ''));

        return [
            'shape'      => self::canonical((string) ($p['frame_shape'] ?? ''), self::SHAPES),
            'material'   => $materials,
            'eco'        => $eco ? ['recycled' => self::ECO_LABEL] : [],
            'gender'     => self::canonical((string) ($p['gender'] ?? ''), self::GENDERS),
            'lens'       => self::lens($p),
            'brand'      => $brands,
            'collab'     => $collab,
            'collection' => self::collection($p, $collab !== []),
        ];
    }

    // ========================================================================
    // TỪNG NHÓM
    // ========================================================================

    /**
     * Quy một ô chữ tự do về các khoá chuẩn của một bảng quy đổi.
     *
     * Mẩu không tra được trong bảng thì thành lựa chọn riêng, khoá là slug của
     * chính nó và nhãn là chữ người nhập đã gõ (đã dọn khoảng trắng).
     */
    private static function canonical(string $raw, array $table): array
    {
        $out = [];

        foreach (self::split($raw) as $piece) {
            $slug = slugify($piece);

            if ($slug === '') {
                continue;
            }

            $hits = self::matchToken($slug, $table);

            if ($hits === []) {
                $out[$slug] = self::prettify($piece);
                continue;
            }

            foreach ($hits as $key) {
                $out[$key] = $table[$key]['label'];
            }
        }

        return $out;
    }

    /**
     * Chất liệu + cờ "tái chế / bio".
     *
     * Cờ tách RIÊNG khỏi nhóm chất liệu chứ không thành một chất liệu thứ
     * mười: "Bio-acetate tái chế" vẫn phải nằm dưới huy hiệu Acetate — người
     * tìm gọng acetate không quan tâm nó làm từ hạt mới hay hạt tái chế. Ai
     * quan tâm thì có chip phụ để bật thêm.
     *
     * @return array{0: array<string,string>, 1: bool}
     */
    private static function materials(string $raw): array
    {
        $eco = false;

        foreach (self::split($raw) as $piece) {
            $slug = slugify($piece);

            // '(^|-)bio' bắt cả "bio-acetate" lẫn "bionylon"; 'tai-che' và
            // 'recycled' bắt hai cách viết còn lại.
            if ($slug !== '' && preg_match('/(^|-)bio|tai-che|recycled/', $slug)) {
                $eco = true;
                break;
            }
        }

        return [self::canonical($raw, self::MATERIALS), $eco];
    }

    /**
     * Thương hiệu — và bộ sưu tập hợp tác nếu tên hãng viết dạng "A × B".
     *
     * Kho ghi hàng collab theo hai kiểu khác nhau, nên đọc cả hai:
     *
     *   brand = "Gentle Monster", collection = "maison-margiela-x-gm"
     *   brand = "Maison Margiela × Gentle Monster", collection để trống
     *
     * Kiểu thứ hai mà để nguyên thì "Gentle Monster" trong ô Thương hiệu KHÔNG
     * đếm và KHÔNG hiện những món collab của chính nó — đúng cái lỗi mà yêu
     * cầu bắt phải tránh. Nên ở đây chuỗi được tách ra: sản phẩm nằm dưới CẢ
     * hai hãng mẹ, đồng thời chuỗi đầy đủ thành một lựa chọn trong nhóm "Bộ
     * sưu tập hợp tác".
     *
     * @return array{0: array<string,string>, 1: array<string,string>}
     */
    private static function brands(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [[], []];
        }

        $parts = self::collabParts($raw);

        if ($parts === null) {
            return [[slugify($raw) => $raw], []];
        }

        $brands = [];
        foreach ($parts as $part) {
            $brands[slugify($part)] = $part;
        }

        return [$brands, [slugify($raw) => self::collabLabel($raw)]];
    }

    /**
     * Bộ sưu tập THƯỜNG (theo mùa) — cột `collection`.
     *
     * Nhãn lấy từ bảng `collections` khi slug có ở đó, để cột lọc và khối
     * lookbook ngoài trang chủ gọi cùng một bộ sưu tập bằng cùng một cái tên.
     *
     * $alreadyCollab: hàng đã được nhận là collab qua tên hãng thì không xếp
     * thêm vào nhóm bộ sưu tập theo mùa nữa.
     */
    private static function collection(array $p, bool $alreadyCollab): array
    {
        $raw = trim((string) ($p['collection'] ?? ''));

        if ($raw === '' || $alreadyCollab) {
            return [];
        }

        /* Nhãn lấy từ CSDL (CollectionModel::labels() có cache trong request),
           không còn từ config/collections.php. Bộ đã bị xoá khỏi bảng mà sản
           phẩm vẫn còn gắn slug thì rơi xuống prettify() như trước — thà hiện
           một cái tên suy từ slug còn hơn để trống ô lọc. */
        $ten = CollectionModel::labels()[$raw] ?? null;

        if ($ten !== null) {
            return [$raw => $ten];
        }

        return [slugify($raw) => self::prettify($raw)];
    }

    /**
     * Tính năng tròng, đọc từ specs + mô tả + tên hàng.
     *
     * Gộp ba nguồn vì không nguồn nào đủ một mình: `specs` là nơi ĐÚNG để ghi
     * ("Tròng kính: Chống UV 99,9%") nhưng nhiều món bỏ trống, còn mô tả thì
     * luôn có. Tên hàng vào cùng vì với tròng kính rời thì tính năng nằm ngay
     * trong tên.
     */
    private static function lens(array $p): array
    {
        $specs = $p['specs'] ?? [];
        $bag   = [(string) ($p['name'] ?? ''), (string) ($p['description'] ?? '')];

        if (is_array($specs)) {
            foreach ($specs as $label => $value) {
                if (is_scalar($value)) {
                    $bag[] = (string) $label . ' ' . (string) $value;
                }
            }
        }

        // slugify một lần cho cả đống chữ: tám biểu thức bên dưới quét lại
        // cùng chuỗi này, bỏ dấu tám lần là tám lần thừa.
        $hay = slugify(implode(' ', $bag));
        $out = [];

        foreach (self::LENS as $key => $def) {
            if (preg_match($def['re'], $hay)) {
                $out[$key] = $def['label'];
            }
        }

        return $out;
    }

    // ========================================================================
    // TIỆN ÍCH
    // ========================================================================

    /**
     * Tách một ô nhiều giá trị thành từng mẩu.
     *
     * Cắt ở '/', '+', '&', ',', '·', '|', gạch dài và chữ "và"; cắt cả ở dấu
     * ngoặc đơn để "Vuông (Square)" cho ra hai mẩu cùng trỏ về `square`.
     *
     * KHÔNG cắt ở gạch nối thường '-': "Cat-eye", "Bio-acetate", "Ray-Ban"
     * đều là MỘT giá trị, cắt ra là hỏng cả ba.
     *
     * KHÔNG cắt ở 'x' hay '×': tên hàng collab ("Maison Margiela × Gentle
     * Monster") cần giữ nguyên để brands() xử lý riêng — cắt ở đây thì nhóm
     * "Bộ sưu tập hợp tác" không còn gì để dựng.
     */
    private static function split(string $raw): array
    {
        $raw = str_replace(['·', '|', '–', '—', '(', ')', '[', ']'], '/', $raw);
        $raw = preg_replace('/\s+và\s+/u', '/', $raw) ?? $raw;

        $parts = preg_split('#[/+&,]#u', $raw) ?: [];
        $out   = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }

    /**
     * Các khoá chuẩn mà một mẩu (đã slug hoá) khớp vào.
     *
     * Ba bước:
     *   1. BẰNG NHAU — "square" -> square.
     *   2. CHỨA NGUYÊN MỘT TỪ — "vuong-flatbar" chứa "vuong" -> square.
     *   3. CỤM DÀI HƠN THẮNG — xem ngay dưới.
     *
     * Bước 2 so trên chuỗi đã kẹp gạch hai đầu ('-vuong-flatbar-' chứa
     * '-vuong-'), tức là khớp theo RANH GIỚI TỪ. Dùng str_contains trần thì
     * "female" chứa "male" và mọi món hàng nữ đều thành hàng nam.
     *
     * Bước 3 gỡ đúng một cái bẫy của tiếng Việt: "Vuông chữ nhật" nghĩa là
     * HÌNH CHỮ NHẬT, không phải "vuông và chữ nhật". Chỉ có bước 2 thì mẩu đó
     * khớp cả 'vuong' lẫn 'chu-nhat' và một gọng chữ nhật lọt vào cả nhóm
     * Vuông. Giữ lại cụm dài nhất là đủ để cụm ngắn hơn — vốn chỉ là một phần
     * của nó — không tính thành một dáng gọng riêng.
     *
     * ĐÁNH ĐỔI ĐÃ BIẾT: một mẩu ghi hai chất liệu mà KHÔNG có dấu ngăn
     * ("Acetate Metal") sẽ chỉ ra 'acetate'. Chấp nhận được vì kho luôn ngăn
     * bằng '/', '+' hay ',' — mà split() đã cắt sẵn trước khi tới đây.
     *
     * Trả về MẢNG chứ không phải một khoá: một Ô khớp nhiều nhóm là chuyện
     * bình thường và đúng ("Kim loại bạc / Titanium" là cả hai).
     */
    private static function matchToken(string $slug, array $table): array
    {
        foreach ($table as $key => $def) {
            if ($slug === $key || in_array($slug, $def['syn'], true)) {
                return [$key];
            }
        }

        $padded = '-' . $slug . '-';
        $best   = [];

        foreach ($table as $key => $def) {
            foreach (array_merge([$key], $def['syn']) as $syn) {
                if (str_contains($padded, '-' . $syn . '-')) {
                    $best[$key] = max($best[$key] ?? 0, strlen($syn));
                }
            }
        }

        if ($best === []) {
            return [];
        }

        $longest = max($best);

        return array_keys(array_filter($best, static fn (int $len) => $len === $longest));
    }

    /**
     * Chuỗi có phải tên hợp tác "A × B" không — nếu phải thì trả về các vế.
     *
     * Nhận cả '×' (dấu nhân thật) lẫn chữ 'x' đứng một mình giữa hai khoảng
     * trắng. Chữ 'x' phải có khoảng trắng hai bên: "Yvmin x Studio" là hợp
     * tác, còn "Oxford" hay "TR-x90" thì không.
     */
    private static function collabParts(string $raw): ?array
    {
        if (!preg_match('/\s(?:×|x|X|\+)\s/u', $raw)) {
            return null;
        }

        $parts = preg_split('/\s(?:×|x|X|\+)\s/u', $raw) ?: [];
        $out   = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $out[] = $part;
            }
        }

        return count($out) >= 2 ? $out : null;
    }

    /** Nhãn hợp tác — thống nhất về dấu '×' dù kho gõ 'x' hay '+'. */
    private static function collabLabel(string $raw): string
    {
        return trim((string) preg_replace('/\s(?:×|x|X|\+)\s/u', ' × ', $raw));
    }

    /**
     * Chữ hiển thị cho một giá trị KHÔNG có trong bảng quy đổi.
     *
     * Giữ nguyên chữ người nhập gõ, chỉ dồn khoảng trắng thừa. Cố tình KHÔNG
     * viết hoa hay dịch lại: đây là chữ của cửa hàng, tự sửa thành thứ khác
     * thì người quản trị gõ một đằng nhìn thấy một nẻo, không biết đường sửa.
     *
     * Riêng slug ("maison-margiela-x-gm", lấy từ cột collection) thì đổi gạch
     * thành khoảng trắng — chữ đó vốn không dành để đọc.
     */
    private static function prettify(string $raw): string
    {
        $raw = trim((string) preg_replace('/\s+/u', ' ', $raw));

        if ($raw !== '' && preg_match('/^[a-z0-9-]+$/', $raw) && str_contains($raw, '-')) {
            return ucfirst(str_replace('-', ' ', $raw));
        }

        return $raw;
    }
}
