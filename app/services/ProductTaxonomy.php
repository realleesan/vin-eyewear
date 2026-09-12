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
    /**
     * Bảng quy đổi MÀU GỌNG.
     *
     * Nguồn màu là biến thể (`product_variants.color`) — mỗi gọng có mấy phương
     * án màu thì bấy nhiêu mẩu chữ do người nhập hàng gõ: "Đen nhám", "Black",
     * "Đen bóng", "Nâu havana". Không gộp thì bộ lọc mọc ra bốn dòng cho hai
     * màu, và cột Màu gọng dài gấp ba những cột khác.
     *
     * Chỉ gom những sắc độ CÙNG MỘT MÀU: "Đen nhám" và "Đen bóng" về `den`, vì
     * người tìm gọng đen không phân biệt độ bóng ở bước lọc. Nhưng "Vàng gold"
     * và "Bạc" thì KHÔNG gộp thành "kim loại" — đó là hai màu khác nhau, gộp là
     * lấy mất một lựa chọn có thật.
     *
     * Mẩu nào không khớp bảng này vẫn thành một lựa chọn RIÊNG mang đúng chữ đã
     * gõ — xem canonical(). Nhờ vậy nhập một màu lạ là bộ lọc tự có, không phải
     * sửa file này.
     */
    /*
     * ─────────────────────────────────────────────────────────────────────────
     * 'hex' — MÀU MẶC ĐỊNH ĐỂ VẼ CHẤM MÀU TRÊN THẺ SẢN PHẨM
     *
     * Cột `product_variants.swatch_hex` vẫn là thứ ĐÈ LÊN bảng này: cửa hàng
     * gõ mã riêng cho một phối màu cụ thể (nâu havana vân đồi mồi khác hẳn nâu
     * trơn) thì mã ấy thắng. Bảng đây chỉ là câu trả lời khi ô ấy để trống.
     *
     * ⚠ CÓ BẢNG NÀY LÀ CÓ CHẤM MÀU MÀ KHÔNG PHẢI GÕ GÌ. Trước 12/09/2026 thẻ
     * chỉ vẽ chấm khi có swatch_hex, mà khu quản trị KHÔNG có ô nhập màu nào
     * cả (chỉ có ô mã hex) — nên trên thực tế không mặt hàng nào có chấm màu.
     * Cửa hàng gõ "Đen" vào ô Màu là xong: chấm hiện ra, và bộ lọc "Màu gọng"
     * cũng có dữ liệu để lọc.
     *
     * Trị số chọn theo cách MẮT đọc ra trên nền trắng, không phải theo tên màu
     * chuẩn CSS. Hai luật, và cả hai đều đã bắt được lỗi thật:
     *
     *   · KHÔNG mã nào sáng quá (độ sáng cảm nhận > 240): 'trang' là #ededed
     *     chứ không #fff — trắng tinh trên thẻ nền trắng là một lỗ tròn vô
     *     hình, vòng viền mờ của .oa-swatch không cứu nổi.
     *   · 'trang' và 'trong-suot' phải KHÁC NHAU nhìn thấy được: trong suốt
     *     ngả xanh xám (#dfe6ea), đúng cách acetate trong đọc ra dưới ánh
     *     sáng. Cùng một sắc xám thì hai chấm cạnh nhau trông như lỗi lặp.
     * ─────────────────────────────────────────────────────────────────────────
     */
    private const COLORS = [
        'den'        => ['label' => 'Đen',        'hex' => '#1b1b1b', 'syn' => ['den', 'black', 'den-nham', 'den-bong', 'matte-black', 'den-mo']],
        'trang'      => ['label' => 'Trắng',      'hex' => '#ededed', 'syn' => ['trang', 'white', 'trang-sua', 'ivory', 'nga']],
        'xam'        => ['label' => 'Xám',        'hex' => '#8b8b8b', 'syn' => ['xam', 'gray', 'grey', 'ghi', 'xam-khoi']],
        'bac'        => ['label' => 'Bạc',        'hex' => '#c6c8ca', 'syn' => ['bac', 'silver', 'ma-bac']],
        'vang-gold'  => ['label' => 'Vàng gold',  'hex' => '#c9962f', 'syn' => ['vang', 'gold', 'vang-gold', 'ma-vang', 'gold-plated', 'champagne']],
        'nau'        => ['label' => 'Nâu',        'hex' => '#6b4423', 'syn' => ['nau', 'brown', 'havana', 'nau-havana', 'tortoise', 'doi-moi', 'nau-tra']],
        'hong'       => ['label' => 'Hồng',       'hex' => '#e59db0', 'syn' => ['hong', 'pink', 'hong-pastel', 'rose', 'vang-hong', 'rose-gold']],
        'do'         => ['label' => 'Đỏ',         'hex' => '#9b1c24', 'syn' => ['do', 'red', 'do-do', 'burgundy', 'do-ruou']],
        'cam'        => ['label' => 'Cam',        'hex' => '#d4732a', 'syn' => ['cam', 'orange']],
        'vang-chanh' => ['label' => 'Vàng chanh', 'hex' => '#e3c218', 'syn' => ['vang-chanh', 'yellow']],
        'xanh-la'    => ['label' => 'Xanh lá',    'hex' => '#3f7a4d', 'syn' => ['xanh-la', 'green', 'luc', 'xanh-reu']],
        'xanh-duong' => ['label' => 'Xanh dương', 'hex' => '#22355c', 'syn' => ['xanh-duong', 'blue', 'navy', 'xanh-navy', 'xanh-bien']],
        'tim'        => ['label' => 'Tím',        'hex' => '#6b4f8a', 'syn' => ['tim', 'purple', 'violet']],
        'trong-suot' => ['label' => 'Trong suốt', 'hex' => '#dfe6ea', 'syn' => ['trong-suot', 'clear', 'transparent', 'trong', 'crystal']],
        'nhieu-mau'  => ['label' => 'Nhiều màu',  'hex' => '#9a9a9a', 'syn' => ['nhieu-mau', 'multicolor', 'multicolour', 'multi', 'phoi-mau']],
    ];

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
     * Thứ tự cố định của nhóm Giới tính (cột lọc gọi tên này từ 2026-08-30;
     * trước đó là "Đối tượng").
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
     *   collection: array<string,string>, eco: array<string,string>,
     *   lens_type: array<string,string>, lens_index: array<string,string>,
     *   lens_coat: array<string,string>, lens_color: array<string,string>
     * }  mỗi nhóm là bảng khoá => nhãn hiển thị
     */
    public static function of(array $p): array
    {
        [$materials, $eco] = self::materials((string) ($p['material'] ?? ''));
        [$brands, $collab] = self::brands((string) ($p['brand'] ?? ''));

        return [
            'shape'      => self::canonical((string) ($p['frame_shape'] ?? ''), self::SHAPES, 'shape'),
            'material'   => $materials,
            'eco'        => $eco ? ['recycled' => self::ECO_LABEL] : [],
            'gender'     => self::canonical((string) ($p['gender'] ?? ''), self::GENDERS, 'gender'),
            'lens'       => self::lens($p),
            'brand'      => $brands,
            'collab'     => $collab,
            'collection' => self::collection($p, $collab !== []),

            /*
             * BỐN NHÓM CỦA TRANG TRÒNG KÍNH (2026-08-30).
             *
             * Khác hẳn tám nhóm trên ở CHỖ LẤY DỮ LIỆU: tám nhóm kia đọc một ô
             * chữ do người nhập gõ rồi quy về khoá chuẩn bằng bảng đồng nghĩa
             * (hoặc, với 'lens', bằng biểu thức chính quy quét cả mô tả). Bốn
             * nhóm này đọc CSV KHOÁ CÓ SẴN từ các cột lens_*, vì form nhập hàng
             * nay bắt chọn từ danh sách chứ không cho gõ tự do — xem
             * LensOptionModel và /quan-tri/thuoc-tinh-trong.
             *
             * Nghĩa là chúng KHÔNG đoán gì cả. Sản phẩm chưa được tick thì vắng
             * mặt khỏi nhóm, và cách chữa là mở sản phẩm ra tick — không phải
             * sửa một biểu thức chính quy rồi mong nó đoán trúng hơn.
             */
            'lens_type'  => self::tuOption((string) ($p['lens_types'] ?? ''), 'loai-trong'),
            'lens_index' => self::tuOption((string) ($p['lens_indexes'] ?? ''), 'chiet-suat'),
            'lens_coat'  => self::lopPhu($p),
            'lens_color' => self::tuOption((string) ($p['lens_color'] ?? ''), 'mau-trong'),
        ];
    }

    /**
     * MÀU GỌNG của một mặt hàng — gom từ biến thể, thiếu thì lùi về cột `color`.
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO LẤY TỪ BIẾN THỂ CHỨ KHÔNG PHẢI CỘT `products.color`
     *
     * Một gọng bán ra mấy màu là mấy DÒNG trong product_variants, mỗi dòng một
     * mã hàng và một mức tồn riêng. Cột `products.color` chỉ ghi được một chuỗi
     * cho cả mặt hàng, nên lọc theo nó là lọc theo "màu của tấm ảnh bìa" — khách
     * chọn "Nâu" sẽ không thấy cái gọng có bán màu nâu chỉ vì ảnh bìa chụp bản
     * màu đen.
     *
     * ─────────────────────────────────────────────────────────────────────
     * NHƯNG CỘT CŨ VẪN LÀ LƯỚI ĐỠ, VÀ ĐỪNG GỠ NÓ
     *
     * Mặt hàng chưa khai biến thể nào thì rơi về `products.color`. Không có vế
     * này thì mọi gọng chưa nhập biến thể sẽ KHÔNG có màu nào cả — tức là biến
     * mất khỏi lưới ngay khi khách bấm một màu bất kỳ. Hỏng âm thầm, và chỉ lộ
     * ra khi có người khiếu nại rằng hàng của họ không ai tìm thấy.
     *
     * @param array<int,array<string,mixed>> $bienThe các dòng product_variants
     * @return array<string,string>
     */
    public static function colors(array $p, array $bienThe): array
    {
        $tho = [];

        foreach ($bienThe as $bt) {
            $mau = trim((string) ($bt['color'] ?? ''));

            if ($mau !== '') {
                $tho[] = $mau;
            }
        }

        /* Biến thể có nhưng KHÔNG dòng nào điền màu (phương án cỡ, phương án
           chiết suất tròng) cũng tính là "chưa khai màu" — vẫn lùi về cột cũ. */
        if ($tho === []) {
            $tho[] = (string) ($p['color'] ?? '');
        }

        return self::canonical(implode(' / ', $tho), self::COLORS, 'color');
    }

    /**
     * Tên màu người nhập gõ  =>  mã màu để vẽ chấm. Không nhận ra thì null.
     *
     * Dùng ở thẻ sản phẩm khi biến thể KHÔNG có `swatch_hex` riêng. Nhờ vậy
     * cửa hàng chỉ cần gõ "Đen" hay "Havana" vào ô Màu là có chấm màu, không
     * phải đi tra mã hex.
     *
     * KHÔNG đi qua canonical(): hàm đó có thể trả về một khoá do CỬA HÀNG tự
     * đặt qua bảng đè (/quan-tri/tieu-chi-loc) — khoá ấy không có trong
     * COLORS nên cũng không có mã màu nào. Ở đây chỉ cần biết chữ vừa gõ có
     * rơi vào một trong mười lăm màu chuẩn hay không; rơi thì vẽ, không rơi
     * thì thôi (thà thiếu một chấm còn hơn vẽ sai màu hàng).
     *
     * Mỗi khoá so một lần và nhớ lại: một trang lưới gọi hàm này vài trăm
     * lượt (mỗi biến thể một lượt) trên cùng dăm chuỗi.
     */
    public static function colorHex(string $raw): ?string
    {
        static $nho = [];

        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (array_key_exists($raw, $nho)) {
            return $nho[$raw];
        }

        $slug = slugify($raw);
        $ma   = null;

        foreach (self::COLORS as $khoa => $c) {
            if ($slug === $khoa || in_array($slug, $c['syn'], true)) {
                $ma = $c['hex'];
                break;
            }
        }

        /* Chưa trúng thì thử theo TỪ: "Nâu havana bóng" không khớp nguyên
           chuỗi, nhưng "nau" và "havana" đều nằm trong bảng. Lấy từ khớp đầu
           tiên theo thứ tự chữ xuất hiện, không phải theo thứ tự bảng — người
           gõ đặt màu chính lên trước. */
        if ($ma === null) {
            foreach (explode('-', $slug) as $tu) {
                foreach (self::COLORS as $khoa => $c) {
                    if ($tu === $khoa || in_array($tu, $c['syn'], true)) {
                        $ma = $c['hex'];
                        break 2;
                    }
                }
            }
        }

        return $nho[$raw] = $ma;
    }

    /**
     * CSV khoá => bảng khoá + nhãn, đối chiếu với một nhóm của `lens_options`.
     *
     * Khoá lạ (mục bị xoá tay khỏi bảng, hoặc chữ gõ tay từ trước khi có danh
     * sách) bị BỎ QUA chứ không in nguyên văn: một bộ lọc mọc thêm dòng "Xám
     * khói" viết hoa bên cạnh dòng "Xám khói" viết thường là đúng thứ mà danh
     * sách chuẩn sinh ra để tránh.
     *
     * Nhãn lấy từ labels() nên GỒM CẢ mục đang ẩn — hàng cũ vẫn còn khoá đó, in
     * ra "xam-khoi" thay vì "Xám khói" thì trông như dữ liệu hỏng. Việc rút mục
     * ẩn khỏi bộ lọc là của tầng trên.
     *
     * @return array<string,string>
     */
    private static function tuOption(string $csv, string $nhom): array
    {
        $csv = trim($csv);

        if ($csv === '') {
            return [];
        }

        $nhan = LensOptionModel::labels($nhom);
        $out  = [];

        foreach (preg_split('/\s*,\s*/', $csv, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $khoa) {
            if (isset($nhan[$khoa])) {
                $out[$khoa] = $nhan[$khoa];
            }
        }

        return $out;
    }

    /**
     * Tính năng / lớp phủ — BA NGUỒN GỘP LẠI, và đó là chỗ tinh tế nhất.
     *
     *   1. cột `lens_coatings`  — CSV khoá, do form nhập hàng ghi. Nguồn THẬT.
     *   2. ba cột 0/1 sẵn có    — is_uv400, is_polarized, is_photochromic.
     *   3. cột `lens_types`     — nhưng CHỈ những khoá thuộc nhóm 'lop-phu'.
     *
     * Vì sao cần nguồn 1 và 2: `lens_coatings` mới có ô nhập từ 2026-08-30 nên
     * nó RỖNG ở toàn bộ hàng đã nhập trước đó, trong khi ba ô tick kia dùng đã
     * lâu và đang mang thông tin thật. Chỉ đọc nguồn 1 thì ngày bật bộ lọc lên,
     * mọi hàng cũ biến mất khỏi nhóm này cùng một lúc.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO CẦN NGUỒN 3 — MỘT LỜI HỨA TÔI ĐÃ GHI VÀO TÀI LIỆU MÀ MÃ KHÔNG GIỮ
     *
     * Cột `lens_types` từng được form nhập hàng ghi bằng danh sách
     * config/eyewear.php 'rx_lens_types', mà danh sách ấy TRỘN hai tính năng
     * vào một nhóm nói về loại: don-trong · da-trong · doi-mau · anh-sang-xanh.
     *
     * Nhóm 'loai-trong' mới chỉ nhận hai khoá đầu, nên hai khoá sau bị
     * tuOption() bỏ qua — và chúng KHÔNG tự sang nhóm lớp phủ, vì nhóm ấy đọc
     * một cột khác. Nghĩa là mọi sản phẩm cũ được tick "Đổi màu" hay "Chống ánh
     * sáng xanh" sẽ mất hẳn thông tin đó khỏi bộ lọc.
     *
     * Chú thích trong database/schema.sql và trong file migration đã hứa ngược
     * lại ("hàng đã nhập không mất bộ lọc, chỉ hiện ở đúng nhóm hơn"). Đoạn
     * dưới đây làm cho lời hứa ấy thành sự thật thay vì đi sửa tài liệu cho
     * khớp một hành vi kém hơn.
     *
     * Lọc theo NHÓM chứ không liệt kê hai khoá: cửa hàng có thể thêm khoá vào
     * 'lop-phu' bất cứ lúc nào, và một danh sách gõ cứng ở đây sẽ lạc hậu ngay.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Cả ba nguồn ánh xạ về ĐÚNG khoá của nhóm 'lop-phu', nên sản phẩm vừa tick
     * is_uv400 vừa có 'uv400' trong CSV chỉ ra MỘT mục — mảng có khoá, không
     * cộng dồn.
     *
     * @return array<string,string>
     */
    private static function lopPhu(array $p): array
    {
        $out  = self::tuOption((string) ($p['lens_coatings'] ?? ''), 'lop-phu');
        $nhan = LensOptionModel::labels('lop-phu');

        // Nguồn 3: khoá lớp phủ lạc trong cột `lens_types` của hàng cũ.
        $out += self::tuOption((string) ($p['lens_types'] ?? ''), 'lop-phu');

        $co = [
            'uv400'    => !empty($p['is_uv400']),
            'phan-cuc' => !empty($p['is_polarized']),
            'doi-mau'  => !empty($p['is_photochromic']),
        ];

        foreach ($co as $khoa => $bat) {
            /* Chỉ thêm khi khoá ấy CÓ trong danh sách quản trị: cửa hàng đổi
               khoá 'phan-cuc' đi thì ô tick is_polarized không được phép tự
               dựng lại một mục không còn tên. */
            if ($bat && isset($nhan[$khoa])) {
                $out[$khoa] = $nhan[$khoa];
            }
        }

        return $out;
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
    private static function canonical(string $raw, array $table, string $nhom = ''): array
    {
        /* ĐỒNG NGHĨA CỦA CỬA HÀNG ĐỨNG TRƯỚC bảng gõ cứng: khai "pantos" cho
           mục X ở /quan-tri/tieu-chi-loc thì chữ ấy về X, kể cả khi bảng cứng
           cũng nhận ra nó. Cửa hàng biết hàng của mình rõ hơn bảng mặc định.
           Nhóm rỗng = nơi gọi không muốn đè (dùng cho bảng phụ). */
        $cuaHang = $nhom !== '' ? FilterOverrideModel::synonymMap($nhom) : [];

        $out = [];

        foreach (self::split($raw) as $piece) {
            $slug = slugify($piece);

            if ($slug === '') {
                continue;
            }

            if (isset($cuaHang[$slug])) {
                $khoa       = $cuaHang[$slug];
                $out[$khoa] = $table[$khoa]['label'] ?? self::prettify($piece);
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

        return [self::canonical($raw, self::MATERIALS, 'material'), $eco];
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
