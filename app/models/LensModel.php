<?php

/**
 * LensModel — gói tròng kính cắt kèm khi mua gọng, và số đo mắt của khách.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DANH MỤC Ở config, GIÁ Ở CSDL
 *
 * Tròng cắt kèm hỏi khách HAI TẦNG:
 *
 *   lens_types     kiểu tròng — Đơn tròng · Hai tròng · Đa tròng · Mắt đặt
 *   lens_packages  gói vật liệu/chiết suất — 1.50, 1.56, chống sáng xanh…
 *
 * Mã, tên và mô tả của cả hai nằm trong config/taxonomy.php. Chúng là một BẢNG
 * DỊCH VỤ, không phải hàng tồn kho: không mã SKU, không trừ tồn, không ảnh,
 * không trang chi tiết — cửa hàng nhập phôi theo lô rồi mài theo đơn từng
 * khách. Ở config vì mã nguồn tham chiếu tới chúng bằng id (`order_items.lens_id`
 * chép lại mã ấy vào hoá đơn), và vì thêm một gói là một quyết định về sản phẩm
 * chứ không phải một lần chỉnh giá. Bảng `products` danh mục "trong-kinh" là
 * thứ KHÁC hẳn: đó là tròng bán RỜI, có tồn kho và biến thể riêng.
 *
 * GIÁ THÌ KHÔNG Ở ĐÓ. Nó nằm ở GIAO ĐIỂM của hai tầng, trong bảng `lens_prices`
 * của CSDL, vì hai lẽ:
 *
 *   · Một gói không có MỘT giá. Cùng phôi 1.61, đơn tròng và đa tròng chênh
 *     nhau vài triệu — không tầng nào một mình mang được con số.
 *   · Giá là thứ CỬA HÀNG sửa, không phải lập trình viên. Mười lăm ô mà mỗi
 *     lần đổi phải sửa file rồi triển khai lại là việc không ai ở cửa hàng làm
 *     được. Có bảng thì sửa ngay trên trình duyệt: /quan-tri/gia-trong.
 *
 * Xem khối "BẢNG GIÁ — KIỂU TRÒNG × GÓI CHIẾT SUẤT" ở dưới.
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
     * Bản thiết kế chỉ đổ ra dải ÂM (−0.25 → −10.00). Không đủ: độ dương là
     * số của viễn thị và lão thị, và ô này không hỏi khách bị tật gì nữa (xem
     * ghi chú "BỎ PHÂN LOẠI TẬT KHÚC XẠ" ở _layout/buy-modal.php) — nó phải
     * nhận được mọi con số in trên đơn thuốc.
     *
     * ĐỐI XỨNG HAI PHÍA, và đó là một thay đổi có chủ đích. Trước đây dải là
     * −12.00 → +8.00. Nay giao diện tách DẤU ra một bộ nút riêng và ĐỘ LỚN ra
     * một ô chọn dùng chung cho cả hai dấu; dải lệch nghĩa là chọn "+" rồi
     * chọn 10.00 sẽ ra một cặp hợp lệ trên màn hình nhưng bị diopter() loại ở
     * máy chủ — con số biến mất không một lời báo. Nới cận trên lên 12.00 rẻ
     * hơn nhiều so với việc phải giải thích chỗ đó.
     *
     * Bước 0.25 diop là bước duy nhất máy mài nhận.
     */
    public const SPH_MIN  = -12.0;
    public const SPH_MAX  = 12.0;
    public const SPH_STEP = 0.25;

    /**
     * ĐỘ TRỤ (CYL) — độ loạn, hỏi ở cả hai mắt.
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
    // KIỂU TRÒNG — Đơn tròng · Hai tròng · Đa tròng · Mắt đặt
    // ========================================================================

    /**
     * Bốn kiểu tròng khách chọn ở bước cuối, theo đúng thứ tự trong config.
     *
     * Đây là TẦNG THỨ NHẤT của việc chọn tròng: mắt nhìn qua mấy vùng. Tầng
     * thứ hai — dày mỏng và lớp phủ — là packages() ngay dưới. Lý do tách hai
     * tầng ghi ở config/taxonomy.php, khoá 'lens_types'.
     */
    public static function types(): array
    {
        return config('taxonomy.lens_types') ?? [];
    }

    /**
     * Một kiểu tròng theo id, hoặc null.
     *
     * Cùng lý do với find(): id gửi lên từ form thì sửa tay được, và một kiểu
     * tròng bịa ra sẽ đi thẳng vào tên dòng hàng trên hoá đơn.
     */
    public static function findType(?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach (self::types() as $t) {
            if ($t['id'] === $id) {
                return $t;
            }
        }

        return null;
    }

    /** Kiểu tròng này còn phải chọn tiếp một gói chiết suất không? */
    public static function typeTakesPackage(?array $type): bool
    {
        return $type !== null && !empty($type['takes_package']);
    }

    /**
     * GỘP hai tầng thành MỘT mẩu "tròng" để hiện và để ghi hoá đơn.
     *
     *     ['id' => 'blue-161', 'type_id' => 'da-trong',
     *      'name' => 'Đa tròng · Chống sáng xanh 1.61', 'price' => 1200000]
     *
     * Vì sao gộp ở đây chứ không thêm cột `lens_type` vào `order_items`: mọi
     * nơi đang in phần tròng — giỏ hàng, trang thanh toán, đơn đã đặt, khu
     * quản trị — đều đọc đúng một chuỗi tên và một con số tiền. Trả về đúng
     * hai thứ đó thì không chỗ nào phải sửa, và hoá đơn cũ vẫn đọc được
     * nguyên vẹn vì lens_name xưa nay đã là "tên chép lại lúc mua".
     *
     * "Mắt đặt" không có gói nào để chọn: trả về id = null, price = 0 và tên
     * đứng một mình. Bên gọi phân biệt được bằng chính `id === null`.
     */
    public static function combo(?string $lensId, ?string $typeId): ?array
    {
        $type = self::findType($typeId);
        $pkg  = self::find($lensId);

        // Kiểu "Mắt đặt" đứng một mình là hợp lệ; một gói chiết suất đứng
        // một mình cũng vậy — đơn cũ đặt trước bản có tầng kiểu tròng.
        if ($type === null && $pkg === null) {
            return null;
        }

        $name = implode(' · ', array_filter([
            $type['name'] ?? null,
            $pkg['name'] ?? null,
        ]));

        /*
         * GIÁ LÀ CỦA GIAO ĐIỂM, không phải của riêng gói.
         *
         * priceOf() trả null cho ba trường hợp, và cả ba đều ra "báo giá sau":
         *   · kiểu "Mắt đặt" — không có ô nào, đúng thiết kế
         *   · gói mới thêm vào config mà cửa hàng chưa nhập giá
         *   · dòng giỏ cũ có lens_id nhưng chưa có lens_type
         *
         * Nói "báo giá sau" thay vì cộng 0đ: cả ba đều là "chưa biết giá", mà
         * in "+0₫" thì khách đọc ra thành "phần tròng miễn phí".
         */
        $price = self::priceOf($type['id'] ?? null, $pkg['id'] ?? null);

        return [
            'id'        => $pkg['id'] ?? null,
            'type_id'   => $type['id'] ?? null,
            'name'      => $name,
            'price'     => (int) ($price ?? 0),
            'quoted'    => $price === null,
        ];
    }

    // ========================================================================
    // BẢNG GIÁ — KIỂU TRÒNG × GÓI CHIẾT SUẤT
    // ========================================================================

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * GIÁ NẰM TRONG CSDL, DANH MỤC NẰM TRONG config
     *
     * Cho tới bản trước, mỗi gói chiết suất mang theo một khoá `price` trong
     * config/taxonomy.php. Cách đó hỏng ở hai chỗ khi bước chọn tròng tách
     * thành hai tầng:
     *
     *   1. MỘT GÓI KHÔNG CÒN MỘT GIÁ. Đơn tròng và đa tròng cùng chọn phôi
     *      1.61 ra cùng số tiền, trong khi mài đa tròng đắt hơn nhiều lần.
     *   2. GIÁ LÀ THỨ CỬA HÀNG SỬA. Mười lăm ô mà mỗi lần đổi phải sửa file
     *      rồi triển khai lại là một việc không ai ở cửa hàng làm được.
     *
     * Nên giá xuống bảng `lens_prices`, sửa ở /quan-tri/gia-trong; còn mã, tên
     * và mô tả ở lại config vì mã nguồn tham chiếu tới chúng bằng id.
     * ─────────────────────────────────────────────────────────────────────────
     */

    /** @var array<string, array<string, int>>|null Cả bảng giá, nhớ trong request. */
    private static ?array $priceTable = null;

    /**
     * Cả bảng giá, dạng [mã kiểu][mã gói] => giá.
     *
     * Nhớ trong biến static: một lượt hiện bước "chọn gói" hỏi giá năm lần,
     * trang chủ hỏi năm lần nữa, mà cả bảng chỉ có mười lăm dòng — đọc một lần
     * rẻ hơn hẳn, và trong cùng một request thì giá không đổi.
     *
     * @return array<string, array<string, int>>
     */
    public static function priceTable(): array
    {
        if (self::$priceTable !== null) {
            return self::$priceTable;
        }

        $out = [];

        foreach (Database::fetchAll('SELECT lens_type, lens_package, price FROM lens_prices') as $r) {
            $out[$r['lens_type']][$r['lens_package']] = (int) $r['price'];
        }

        return self::$priceTable = $out;
    }

    /**
     * Giá của một ô, hoặc null khi ô đó chưa có giá.
     *
     * null KHÁC 0. 0 là "lựa chọn này không tính tiền" — cửa hàng khuyến mãi
     * được. null là "chưa có giá cho lựa chọn này". Bên gọi in "Báo giá sau"
     * cho null và "0₫" cho 0; hai câu khác hẳn nhau với người sắp trả tiền.
     */
    public static function priceOf(?string $typeId, ?string $packageId): ?int
    {
        if ($typeId === null || $packageId === null) {
            return null;
        }

        return self::priceTable()[$typeId][$packageId] ?? null;
    }

    /**
     * Giá THẤP NHẤT của một gói trên mọi kiểu tròng, hoặc null nếu chưa ô nào
     * có giá.
     *
     * Dùng cho khối "Gói tròng phổ biến" ở trang chủ, nơi khách chưa chọn kiểu
     * tròng nào nên không có ô cụ thể để in. "Từ 500.000₫" là câu đúng ở mọi
     * trường hợp; in giá của một kiểu bất kỳ rồi bỏ chữ "từ" là hứa một con số
     * mà phần lớn khách sẽ không trả.
     */
    public static function priceFrom(string $packageId): ?int
    {
        $found = [];

        foreach (self::priceTable() as $byPackage) {
            if (isset($byPackage[$packageId])) {
                $found[] = $byPackage[$packageId];
            }
        }

        return $found === [] ? null : min($found);
    }

    /**
     * Ghi lại CẢ bảng giá từ màn quản trị.
     *
     * Nhận [mã kiểu][mã gói] => giá và ghi đè toàn bộ. Xoá sạch rồi chèn lại
     * chứ không cập nhật từng ô: màn quản trị gửi lên đúng những ô nó vừa vẽ
     * ra, nên bảng sau khi lưu phải là đúng những ô đó. Cập nhật từng ô thì gỡ
     * một gói khỏi config xong, giá mồ côi của nó vẫn nằm lại trong DB và sống
     * dậy nếu mã ấy được dùng lại — với một con số không ai còn nhớ.
     *
     * Ô ĐỂ TRỐNG thì không ghi dòng nào. Đó là cách khai "chưa định giá", khác
     * hẳn với ghi số 0.
     *
     * Cả hai bước trong MỘT transaction: xoá xong mà chèn hỏng nửa chừng thì
     * cửa hàng mất bảng giá và mọi lựa chọn tròng thành "báo giá sau".
     *
     * @param array<string, array<string, string|int|null>> $rows
     */
    public static function savePriceTable(array $rows): void
    {
        $clean = [];

        /* Chỉ nhận mã CÓ THẬT trong config, và chỉ kiểu nào thật sự có bảng
           giá. Không kiểm thì một request gõ tay đặt được giá cho "Mắt đặt"
           hoặc cho một gói không tồn tại, và bảng giá mọc ra những dòng không
           màn nào vẽ, không ai xoá được. */
        foreach (self::types() as $type) {
            if (!self::typeTakesPackage($type)) {
                continue;
            }

            foreach (self::packages() as $pkg) {
                $raw = trim((string) ($rows[$type['id']][$pkg['id']] ?? ''));

                // ctype_digit chứ không (int): "abc" ép kiểu thành 0, tức là
                // một lỗi gõ lặng lẽ biến thành "gói này miễn phí".
                if ($raw === '' || !ctype_digit($raw)) {
                    continue;
                }

                $clean[] = [$type['id'], $pkg['id'], (int) $raw];
            }
        }

        Database::transaction(static function () use ($clean): void {
            Database::execute('DELETE FROM lens_prices');

            foreach ($clean as [$type, $pkg, $price]) {
                Database::execute(
                    'INSERT INTO lens_prices (lens_type, lens_package, price)
                     VALUES (:t, :p, :price)',
                    ['t' => $type, 'p' => $pkg, 'price' => $price]
                );
            }
        });

        // Bảng vừa đổi thì bộ nhớ tạm của chính request này đã sai.
        self::$priceTable = null;
    }

    // ========================================================================
    // GÓI TRÒNG
    // ========================================================================

    /** Các gói chiết suất đang bán, theo đúng thứ tự trong config. */
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

    /*
     * KHÔNG CÒN BẢNG PHÂN LOẠI TẬT KHÚC XẠ.
     *
     * Ở đây từng có hằng RX_TYPES — bốn ô radio "Cận thị · Viễn thị · Loạn
     * thị · Lão thị" hỏi trên mỗi mắt. Cửa hàng yêu cầu bỏ hẳn: người mua kính
     * bình thường KHÔNG tự xác định đúng được mình bị tật gì, nên câu hỏi đó
     * thu về một con số đoán mò rồi đặt nó cạnh những con số đo thật.
     *
     * Người có chuyên môn đọc SPH / CYL / AXIS là ra loại tật, và họ đọc trước
     * khi mài. Bỏ ô radio đi thì phiếu chỉ còn những gì đơn thuốc thật sự ghi.
     *
     * Kéo theo: eyeText() không in tên tật nữa, và "đủ hay chưa" của một mắt
     * chỉ còn tính theo ĐỘ CẦU.
     */

    /**
     * ĐỘ LỚN của độ cầu — 0.00 → 12.00, KHÔNG mang dấu.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO TÁCH DẤU RA MỘT BỘ NÚT RIÊNG
     *
     * Trước đây đây là một <select> duy nhất chạy từ +8.00 xuống −12.00: 81
     * dòng, dấu nằm lẫn trong nhãn. Cửa hàng yêu cầu cho khách CHỌN DẤU cộng
     * hoặc trừ, và yêu cầu đó đúng ở chỗ nó tách hai quyết định vốn khác nhau:
     *
     *   dấu     cận hay viễn — khách nhìn đơn thuốc là thấy ngay
     *   độ lớn  con số — thứ phải dò trong danh sách
     *
     * Gộp chung thì muốn đổi từ −2.00 sang +2.00 phải cuộn qua 16 dòng, và
     * dấu trừ ở cỡ chữ 13px trong một hàng ba ô là thứ mắt rất dễ đọc lướt qua.
     * Tách ra thì dấu là hai nút to bằng ngón tay, luôn nhìn thấy đang chọn gì.
     *
     * Nửa còn lại của cặp này là sphSign() ở dưới, và hai ô ghép lại thành một
     * con số ở CartController::buyStep(). Dải đối xứng nên mọi cặp dấu × độ
     * lớn đều hợp lệ — xem ghi chú ở hằng SPH_MAX.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Xếp TĂNG DẦN từ 0.00: đã bỏ dấu ra ngoài thì không còn lý do gì để đảo
     * ngược, và số nhỏ (đại đa số đơn thuốc) nằm ngay đầu danh sách.
     *
     * @return array<int, array{value:string, label:string}>
     */
    public static function sphMagnitudeOptions(): array
    {
        $out = [];
        $max = max(abs(self::SPH_MIN), self::SPH_MAX);

        for ($d = 0.0; $d <= $max; $d += self::SPH_STEP) {
            $v = number_format($d, 2, '.', '');
            $out[] = ['value' => $v, 'label' => $v];
        }

        return $out;
    }

    /**
     * Hai dấu của ô độ cầu.
     *
     * `value` là ký tự sẽ đứng trước con số khi ghép, nên '-' phải là dấu gạch
     * nối ASCII — đó là thứ is_numeric() hiểu. `label` mới là ký tự trừ thật
     * (U+2212) cho khớp với mọi số âm khác trên site.
     *
     * '-' đứng trước và là mặc định: cận thị là đại đa số khách.
     *
     * @return array<int, array{value:string, label:string, note:string}>
     */
    public static function sphSignOptions(): array
    {
        return [
            ['value' => '-', 'label' => '−', 'note' => 'Cận'],
            ['value' => '+', 'label' => '+', 'note' => 'Viễn'],
        ];
    }

    /**
     * Ghép dấu và độ lớn thành một con số để đưa vào diopter().
     *
     * Để trống độ lớn thì trả chuỗi rỗng — "chưa nhập", khác hẳn với 0.00.
     * Dấu lạ (gõ tay) coi như dấu trừ, đúng mặc định của giao diện.
     */
    public static function joinSph(?string $sign, ?string $magnitude): string
    {
        $mag = trim((string) $magnitude);

        if ($mag === '') {
            return '';
        }

        /*
         * GIÁ TRỊ ĐÃ MANG SẴN DẤU THÌ BỎ QUA Ô DẤU.
         *
         * Hai giao diện gửi độ cầu về theo hai dạng khác nhau:
         *   trang hồ sơ      hai ô rời — dấu ở `*_dau`, độ lớn ở `*_sph`
         *   bảng số đo       MỘT ô chọn mang sẵn dấu ("-2.00")
         *
         * Không có nhánh này thì dạng thứ hai bị nối thêm một dấu trừ nữa và
         * thành "--2.00" — một chuỗi không phải số, diopter() vứt đi, và số đo
         * khách vừa nhập biến mất không một lời báo.
         *
         * Ký tự trừ THẬT (U+2212) cũng nhận: nhãn trên giao diện dùng nó.
         *
         * str_starts_with() chứ KHÔNG phải mb_substr(): máy chủ của cửa hàng
         * không nạp extension mbstring, mọi hàm mb_* là lỗi 500 chờ sẵn — xem
         * ghi chú ở core/helpers.php, chỗ utf8Length/utf8Substr tự viết. Hàm
         * này làm việc trên byte nên so được cả tiền tố nhiều byte như '−'.
         */
        if (str_starts_with($mag, '+')
            || str_starts_with($mag, '-')
            || str_starts_with($mag, '−')) {
            $label = static::diopter($mag);

            if ($label === null) {
                return '';
            }

            /*
             * TRẢ VỀ DẤU ASCII, không phải nhãn hiển thị.
             *
             * diopter() trả ra nhãn để ĐỌC: dấu trừ thật (U+2212) và dấu cộng
             * ở đầu số dương. Chuỗi đó đi tiếp vào UserModel::savePrescription,
             * nơi có một phép ép `(float)` — và (float)"−2.00" bằng 0.0, vì
             * PHP chỉ hiểu dấu gạch nối ASCII.
             *
             * Đã xảy ra thật khi chạy thử: nhập cận −2.00, hồ sơ khúc xạ lưu
             * 0.00 — tức "không độ", một con số HỢP LỆ nên không có lỗi nào
             * nổi lên. Nhánh hai-ô ở dưới vốn trả dấu ASCII; nhánh này phải
             * trả cùng một dạng.
             */
            return ltrim(str_replace('−', '-', $label), '+');
        }

        // 0.00 không có dấu: "−0.00" là một con số không tồn tại trong đơn thuốc.
        if ((float) $mag == 0.0) {
            return '0.00';
        }

        return ($sign === '+' ? '' : '-') . $mag;
    }

    /**
     * Các mức độ trụ cho ô "Độ trụ (CYL)".
     *
     * BỎ giá trị 0.00: trụ 0 độ nghĩa là mắt đó không loạn, và cách khai điều
     * đó là ĐỂ TRỐNG ô. Có cả hai lối nói cùng một chuyện thì phiếu in ra lúc
     * ghi "/ 0.00" lúc không, và người mài phải đoán xem hai kiểu ấy có khác
     * nhau không.
     *
     * Ô này KHÔNG mang bộ nút dấu như độ cầu: trụ gần như luôn âm (quy ước
     * "trụ âm" của nhãn khoa), nên hai nút dấu ở đây chỉ thêm một cú bấm cho
     * mọi khách để phục vụ một thiểu số dùng máy in ra trụ dương. Dải vẫn chạy
     * cả hai phía trong cùng một danh sách, đã kèm sẵn dấu trong nhãn.
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

    /**
     * ĐỘ CẦU — một ô chọn DUY NHẤT mang sẵn dấu, từ +MAX xuống −MIN.
     *
     * Khác sphSignOptions()+sphMagnitudeOptions() (hai ô rời) đang dùng ở trang
     * hồ sơ. Bảng số đo trong hộp thoại mua hàng vẽ theo mẫu một-ô, và ở đó nó
     * hợp lý hơn: bốn cột SPH · CYL · TRỤC của hai mắt phải thẳng hàng nhau
     * trong một bảng, mà nhét thêm hai nút dấu vào ô đầu là cột đó rộng gấp
     * rưỡi ba cột còn lại.
     *
     * Đổi lại, dấu nằm lẫn trong 97 dòng danh sách. Bù bằng hai thứ ở giao
     * diện: nhãn cột ghi rõ "Cận (−) / Viễn (+)", và ô tóm tắt bên dưới đọc
     * ngược con số ra thành chữ ("Cận 2.00 · Loạn 0.75") để khách soát lại.
     *
     * XẾP TỪ DƯƠNG XUỐNG ÂM cho khớp bản vẽ — và cũng đúng cách đọc đơn kính.
     * 0.00 có nhãn riêng "Plano": trong đơn thuốc đó là "không có độ", một câu
     * trả lời THẬT, khác hẳn ô để trống nghĩa là chưa nhập.
     *
     * @return array<int, array{value:string, label:string}>
     */
    public static function sphSignedOptions(): array
    {
        $out = [];

        for ($d = self::SPH_MAX; $d >= self::SPH_MIN; $d -= self::SPH_STEP) {
            $v = number_format($d, 2, '.', '');

            $out[] = [
                'value' => $v,
                'label' => $d == 0.0 ? '0.00 (Plano)' : self::diopterLabel($d),
            ];
        }

        return $out;
    }

    /**
     * ĐỘ TRỤ kèm dòng 0.00 ở đầu.
     *
     * cylOptions() cố tình bỏ 0.00 đi vì ở giao diện cũ ô trống đã đủ nghĩa.
     * Bảng số đo thì cần nó: trục loạn bị KHOÁ khi chưa có độ trụ, nên khách
     * không loạn phải nói được "tôi không loạn" thay vì để trống rồi tự hỏi
     * sao ô trục bấm không được.
     *
     * @return array<int, array{value:string, label:string}>
     */
    public static function cylOptionsWithZero(): array
    {
        return array_merge(
            [['value' => '0.00', 'label' => '0.00 (Không loạn)']],
            self::cylOptions()
        );
    }

    /**
     * TÁCH một con số độ cầu đã lưu thành cặp (dấu, độ lớn) — ngược của
     * joinSph().
     *
     * Cần khi HIỆN LẠI một hồ sơ đã có: cơ sở dữ liệu lưu "-2.00" nguyên một
     * chuỗi, còn giao diện thì hỏi dấu và độ lớn bằng hai ô riêng. Không có
     * hàm này thì trang hồ sơ phải tự cắt chuỗi, và cắt sai một lần là hiện
     * cho khách thấy độ ngược dấu với thứ họ đã nhập.
     *
     * Chuỗi rỗng / không phải số -> ['-', ''] : dấu trừ là mặc định của giao
     * diện (cận thị là đại đa số khách), độ lớn để trống nghĩa là "chưa nhập".
     *
     * 0.00 giữ dấu trừ mặc định — nó không âm cũng không dương, và joinSph()
     * bỏ dấu đi khi ghép lại.
     *
     * @return array{0: string, 1: string} [dấu, độ lớn]
     */
    public static function splitSph(?string $value): array
    {
        $raw = trim((string) $value);

        if ($raw === '' || !is_numeric($raw)) {
            return ['-', ''];
        }

        $num = (float) $raw;

        return [$num > 0 ? '+' : '-', number_format(abs($num), 2, '.', '')];
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
     * KHÔNG CÒN LOẠI TẬT — CHỈ CÒN NHỮNG GÌ ĐƠN THUỐC GHI
     *
     * Bản trước in kèm tên tật ("MP Loạn thị −2.00 …") lấy từ bốn ô radio trên
     * mỗi mắt. Cửa hàng đã yêu cầu bỏ khung đó vì khách không tự xác định
     * đúng được, nên chuỗi này nay chỉ còn SPH / CYL × AXIS và ghi chú — đúng
     * bằng những gì có trên tờ đơn thuốc. Người mài đọc ba con số là ra loại
     * tật, chính xác hơn ô radio nhiều.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Mỗi mắt là một mảng: ['sph' =>, 'cyl' =>, 'axis' =>, 'note' =>].
     * Nhận mảng chứ không phải tám tham số rời — tám tham số cùng kiểu ?string
     * xếp hàng thì đảo nhầm hai cái là mài nhầm mắt, mà không có gì báo.
     *
     * ĐỦ HAY CHƯA TÍNH THEO ĐỘ CẦU. Không mắt nào có SPH thì trả null, nghĩa
     * rõ ràng là "đo tại cửa hàng" — ghi chú hay trục đứng một mình đều không
     * mài kính được. Nhưng một khi đã có ít nhất một mắt đủ số, phần khách ghi
     * cho mắt còn lại vẫn được giữ.
     *
     * @param array{sph?:?string, cyl?:?string, axis?:?string, note?:?string} $od
     * @param array{sph?:?string, cyl?:?string, axis?:?string, note?:?string} $os
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
     * Một mắt thành một mẩu chữ: "MP −2.00 / −1.25 × 180° (ghi chú)".
     *
     * Cách viết "sph / cyl × axis°" giống hệt formatSavedRx() dùng cho hồ sơ
     * khúc xạ đã lưu — hai đường vào cùng in ra một dạng, để nhân viên đọc
     * phiếu không phải nhận ra mình đang đọc kiểu nào.
     */
    private static function eyeText(string $short, array $eye): ?string
    {
        $sph  = self::diopter($eye['sph'] ?? null);
        $note = self::cleanNote($eye['note'] ?? null);

        $so = $sph;

        /*
         * TRỤ VÀ TRỤC ĐI THEO Ô ĐÃ ĐIỀN.
         *
         * Từng có điều kiện `$type === 'loan'` ở đây, hợp lý hồi giao diện chỉ
         * hiện hai ô đó cho mắt tick "Loạn thị". Ô radio ấy đã bỏ hẳn, nên nay
         * quy tắc đơn giản: điền gì giữ nấy — trừ đúng một ngoại lệ ngay dưới,
         * độ trụ 0.00 nghĩa là "không loạn" chứ không phải một số đo. diopter()
         * và axis() vẫn chặn giá trị ngoài dải, nên không có gì lọt qua.
         *
         * Vẫn giữ `$so !== null`: trụ mà không có độ cầu thì in ra thành
         * "MP / −1.25", một mẩu chữ cụt đầu không đọc được.
         */
        if ($so !== null) {
            $cyl = self::diopter($eye['cyl'] ?? null, self::CYL_MIN, self::CYL_MAX);

            /*
             * ĐỘ TRỤ 0.00 KHÔNG IN RA, VÀ TRỤC ĐI THEO NÓ.
             *
             * Bảng số đo trong hộp thoại mua hàng nay BẮT chọn độ trụ cho cả
             * hai mắt, và dòng "0.00 (Không loạn)" chính là câu trả lời của
             * người không loạn — xem nhánh 'so-do' trong
             * CartController::buyStep(). Nên từ đây phần lớn đơn sẽ mang cyl
             * "0.00", và in nguyên vào chuỗi thì mọi phiếu mài đọc thành
             * "MP −2.00 / 0.00" — một con số không nói gì thêm, đứng đúng chỗ
             * mắt người đọc quen tìm độ loạn.
             *
             * Đơn kính giấy cũng viết như vậy: không loạn thì phần trụ bỏ
             * trống, không ghi số không.
             *
             * `!= 0.0` chứ không so chuỗi: diopter() trả về nhãn đã dựng sẵn
             * ("0.00", và dấu trừ ở đây là U+2212), nên so với '0.00' là buộc
             * hàm này biết cách hàm kia định dạng.
             */
            if ($cyl !== null && (float) str_replace('−', '-', $cyl) != 0.0) {
                $so .= ' / ' . $cyl;

                $axis = self::axis($eye['axis'] ?? null);

                if ($axis !== null) {
                    $so .= ' × ' . $axis . '°';
                }
            }
        }

        if ($so === null && $note === null) {
            return null;
        }

        $text = trim($short . ' ' . (string) $so);

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

    /*
     * Ở ĐÂY TỪNG CÓ formatSavedRx() — gói hồ sơ khúc xạ đã lưu thành một chuỗi
     * để in ở bước "Số đo khúc xạ" của hộp thoại mua hàng.
     *
     * Bước đó đã bỏ (xem điểm 3 ở đầu _layout/buy-modal.php): số độ của lần đo
     * trước không còn được mang sang lượt mua mới, nên không còn nơi nào cần
     * chuỗi ấy. Hồ sơ vẫn hiện đầy đủ ở /tai-khoan?muc=do-mat, nhưng trang đó
     * dựng bảng năm cột từ chính các cột trong DB chứ không đọc chuỗi.
     */

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
