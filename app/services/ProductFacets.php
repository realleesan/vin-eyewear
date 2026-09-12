<?php

/**
 * ProductFacets — dựng danh sách lựa chọn của cột lọc, kèm SỐ ĐẾM ĐỘNG.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐẾM THEO KIỂU "BỎ QUA CHÍNH NHÓM MÌNH"
 *
 * Con số bên cạnh mỗi lựa chọn = số sản phẩm còn lại nếu bật thêm lựa chọn đó,
 * tính với MỌI NHÓM KHÁC đang bật NHƯNG BỎ QUA nhóm chứa nó.
 *
 * Vì sao không đếm với cả nhóm của mình: trong một nhóm các lựa chọn là HOẶC,
 * nên khi đã chọn "Acetate" thì mọi chất liệu khác đều cho ra 0 — cả cột lọc
 * tắt ngóm ngay sau cú bấm đầu tiên, không còn đường bấm tiếp để mở rộng ra
 * "Acetate hoặc Metal". Bỏ qua nhóm mình thì con số đọc đúng nghĩa "bấm vào
 * đây sẽ thêm chừng này món".
 *
 * Đây cũng là lý do bản trước GÕ CỨNG số đếm ở nhóm Thương hiệu: đếm động mà
 * đếm cả nhóm mình thì số nhảy loạn, nên người ta chọn đếm trên cả kho cho
 * yên. Cách trên giải quyết được gốc rễ, nên số đếm nay động thật.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LỰA CHỌN 0 SẢN PHẨM KHÔNG BAO GIỜ ĐƯỢC IN RA
 *
 * Danh sách lựa chọn dựng TỪ dữ liệu nên mỗi lựa chọn luôn có ít nhất một sản
 * phẩm khi chưa lọc gì. Khi đang lọc, mục nào rơi về 0 thì vẫn in ra nhưng bị
 * làm mờ và bỏ liên kết (`disabled`) — giấu hẳn sẽ khiến cột lọc co giãn mỗi
 * lần bấm, và người dùng mất dấu tiêu chí mình vừa nhìn thấy. Mục ĐANG BẬT thì
 * luôn còn liên kết, nếu không sẽ không có cách nào tắt nó đi.
 */

class ProductFacets
{
    /**
     * Các nhóm lọc theo phân loại, và tên tham số của chúng trên URL.
     *
     * 'brand' và 'gender' giữ NGUYÊN TÊN CŨ vì đã có liên kết ngoài trỏ vào
     * (mega menu, khối "chọn theo khuôn mặt" ở trang chủ). 'shape', 'material'
     * cũng vậy. Ba tên còn lại là mới.
     */
    public const GROUPS = [
        'shape', 'material', 'eco', 'brand', 'collab', 'collection', 'lens', 'gender', 'price',
        // Màu gọng (12/09/2026) — gom từ product_variants.color, xem attach().
        'color',
        // Bốn nhóm chỉ dùng ở trang /san-pham/trong-kinh (2026-08-30). Vẫn khai
        // chung một danh sách vì tầng đếm động không phân biệt trang nào — nhóm
        // nào không có hàng thì tự vắng mặt. Xem cột lọc trong product/index.php.
        'lens_type', 'lens_index', 'lens_coat', 'lens_color',
    ];

    /**
     * Nhóm chọn-nhiều — tất cả trừ 'price'.
     *
     * Khoảng giá đi chung bộ máy với các nhóm khác (xem attach) để nó cũng
     * được đếm động và cũng theo luật VÀ/HOẶC, nhưng giao diện của nó là một ô
     * chọn xổ xuống chọn-một, nên controller chỉ nhận đúng một giá trị — và
     * trên URL nó là ?price=2 chứ không phải price[]=2.
     */
    public const MULTI = [
        'shape', 'material', 'eco', 'brand', 'collab', 'collection', 'lens', 'gender', 'color',
        'lens_type', 'lens_index', 'lens_coat', 'lens_color',
    ];

    /**
     * Gắn bảng khoá lọc vào từng sản phẩm, một lần cho cả request.
     *
     * Khoá '_facets' có gạch dưới đứng đầu để không đụng tên cột nào của bảng
     * products — view nào duyệt hết các cột (thẻ sản phẩm không, nhưng trang
     * quản trị có) sẽ bỏ qua nó.
     */
    public static function attach(array $products, array $priceRanges = []): array
    {
        /*
         * MÀU GỌNG ĐI MỘT ĐƯỜNG RIÊNG, vì nó là nhóm DUY NHẤT không đọc được
         * từ một dòng `products`: màu nằm ở các dòng product_variants của mặt
         * hàng đó. ProductTaxonomy::of() chỉ nhận một dòng sản phẩm nên không
         * với tới chúng.
         *
         * MỘT câu truy vấn cho CẢ TẬP, không phải một câu mỗi món:
         * forProducts() nhận cả danh sách id và gom kết quả theo product_id.
         * Gọi trong vòng lặp là N+1 câu lệnh cho một trang danh mục có thể có
         * vài trăm món.
         */
        $bienThe = $products === []
            ? []
            : VariantModel::forProducts(array_column($products, 'id'));

        foreach ($products as $i => $p) {
            $facets          = ProductTaxonomy::of($p);
            $facets['price'] = self::priceKey($p, $priceRanges);
            $facets['color'] = ProductTaxonomy::colors($p, $bienThe[$p['id']] ?? []);

            $products[$i]['_facets'] = self::deLen($facets);
        }

        return self::pruneCollabBrands($products);
    }

    /**
     * Áp bảng đè của cửa hàng lên bảng facet vừa rút ra được.
     *
     * ═════════════════════════════════════════════════════════════════════
     * LÀM ĐÚNG HAI VIỆC, VÀ CỐ Ý KHÔNG LÀM VIỆC THỨ BA
     *
     *   GỘP   merge_into: mục A chuyển thành mục B. Sau bước này sản phẩm
     *         đếm dưới B, lọc ?shape[]=B ra nó, và A không còn tồn tại.
     *   ĐỔI TÊN  label: chỉ thay chữ hiện ra, khoá giữ nguyên nên mọi liên
     *         kết cũ vẫn trúng.
     *
     * KHÔNG lọc mục bị ẩn ở đây. `is_visible = 0` nghĩa là "thôi bày ra như
     * một lựa chọn", KHÔNG phải "sản phẩm này mất thuộc tính đó". Gỡ khỏi
     * facet là địa chỉ ?shape[]=… cũ mà khách đã lưu bỗng trả về lưới rỗng,
     * và mega menu trỏ vào một tiêu chí không còn khớp món nào. Việc ẩn thuộc
     * về tầng dựng DANH SÁCH LỰA CHỌN — xem ProductModel::catalog().
     *
     * ═════════════════════════════════════════════════════════════════════
     * GỘP CHỈ ĐI MỘT CHẶNG
     *
     * A -> B -> C thì A về B, không về C. Đi tiếp nhiều chặng là mở cửa cho
     * vòng lặp vô hạn (A -> B, B -> A) và cho những dây gộp dài mà không ai
     * còn hình dung nổi kết quả. Muốn cả ba về C thì khai thẳng A -> C và
     * B -> C; màn quản trị nói rõ điều đó.
     */
    private static function deLen(array $facets): array
    {
        foreach ($facets as $nhom => $muc) {
            if (!is_array($muc) || $muc === [] || !isset(FilterOverrideModel::GROUPS[$nhom])) {
                continue;
            }

            $de = FilterOverrideModel::forGroup($nhom);

            if ($de === []) {
                continue;
            }

            $moi = [];

            foreach ($muc as $khoa => $nhan) {
                $gop = $de[$khoa]['merge_into'] ?? null;

                /* Gộp vào một khoá KHÔNG tồn tại trong bảng đè vẫn hợp lệ:
                   đích có thể là một tiêu chí máy tự rút ra (gộp "Pantos tròn"
                   về "pantos"). Chỉ chặn gộp vào chính mình — một dòng như thế
                   là lỗi nhập liệu và nó sẽ làm mục biến mất. */
                if ($gop !== null && $gop !== $khoa) {
                    $khoa = $gop;
                    $nhan = $de[$gop]['label'] ?? ($muc[$gop] ?? $nhan);
                }

                $moi[$khoa] = $de[$khoa]['label'] ?? $nhan;
            }

            $facets[$nhom] = $moi;
        }

        return $facets;
    }

    /**
     * Giữ nhóm Thương hiệu chỉ gồm những HÃNG KÍNH cửa hàng thật sự bán.
     *
     * ProductTaxonomy tách "Gentle Monster × Jennie" thành hai vế và xếp món
     * đó dưới CẢ HAI — cần thiết, vì nếu không thì chọn "Gentle Monster"
     * không ra hàng collab của chính Gentle Monster. Nhưng để nguyên thì vế
     * kia cũng leo vào danh sách thương hiệu, và cột lọc mọc thêm "Jennie",
     * "Bratz", "D'heygere", "Liberty" — tên người và nhãn thời trang, không
     * phải hãng kính nào cửa hàng nhập về. Bấm vào ra đúng một món, mà món đó
     * đã nằm sẵn trong nhóm "Bộ sưu tập hợp tác" ngay bên dưới.
     *
     * Phân biệt bằng DỮ LIỆU chứ không bằng danh sách gõ cứng: một cái tên là
     * hãng kính nếu nó còn đứng MỘT MÌNH ở ô thương hiệu của ít nhất một món
     * khác. Gentle Monster có hàng bán riêng nên đủ tư cách; Jennie thì chỉ
     * xuất hiện trong đúng một cái tên collab nên không.
     *
     * Nhờ vậy nhập thêm một hãng mới là bộ lọc tự có, còn nhập thêm một món
     * collab thì không đẻ ra thương hiệu rác — không phải sửa file này.
     *
     * NGOẠI LỆ: món collab mà KHÔNG vế nào là hãng có bán riêng (cửa hàng chỉ
     * nhập đúng một món của cặp đó) thì giữ nguyên cả hai vế. Cắt sạch sẽ
     * khiến món ấy không thuộc thương hiệu nào và biến mất khỏi mọi phép lọc
     * theo hãng — mất hàng còn tệ hơn thừa một huy hiệu.
     */
    private static function pruneCollabBrands(array $products): array
    {
        // Lượt 1 — những cái tên đứng một mình ở ô thương hiệu.
        $houses = [];

        foreach ($products as $p) {
            if (($p['_facets']['collab'] ?? []) !== []) {
                continue;
            }

            foreach (array_keys($p['_facets']['brand'] ?? []) as $key) {
                $houses[$key] = true;
            }
        }

        // Lượt 2 — hàng collab chỉ giữ lại các vế có trong danh sách trên.
        foreach ($products as $i => $p) {
            if (($p['_facets']['collab'] ?? []) === []) {
                continue;
            }

            $kept = array_intersect_key($p['_facets']['brand'] ?? [], $houses);

            if ($kept !== []) {
                $products[$i]['_facets']['brand'] = $kept;
            }
        }

        return $products;
    }

    /**
     * Khoảng giá mà một sản phẩm rơi vào — dưới dạng một nhóm lọc như mọi
     * nhóm khác, để nó dùng chung phép đếm động và luật VÀ/HOẶC ở trên.
     *
     * Hàng CHƯA CÓ GIÁ (price = 0, mặc định của cột) trả về rỗng: nó không
     * thuộc khoảng nào nên chọn khoảng giá là nó rụng, còn không chọn thì vẫn
     * hiện — cùng cách xử lý với mọi trường bỏ trống khác.
     *
     * Khoá là CHỈ SỐ của khoảng dưới dạng chuỗi, khớp ?price=2 trên URL.
     */
    private static function priceKey(array $p, array $ranges): array
    {
        $price = (int) ($p['price'] ?? 0);

        if ($price <= 0) {
            return [];
        }

        foreach ($ranges as $i => $range) {
            if ($price >= $range['min'] && ($range['max'] === null || $price < $range['max'])) {
                return [(string) $i => (string) $range['label']];
            }
        }

        return [];
    }

    /**
     * Sản phẩm có khớp một bộ tiêu chí không.
     *
     * Trong cùng nhóm: HOẶC. Giữa các nhóm: VÀ.
     *
     * $skip là nhóm được bỏ qua khi đếm (xem chú thích đầu file). Truyền null
     * khi lọc thật.
     *
     * Sản phẩm để trống một trường ("Chưa xác minh") thì nhóm đó rỗng, nên nó
     * KHÔNG khớp bất kỳ lựa chọn nào của nhóm — đúng yêu cầu: không dựng huy
     * hiệu "Chưa xác minh", chỉ đơn giản là lọc trường đó thì nó rụng, còn
     * không lọc gì thì nó vẫn hiện.
     */
    public static function matches(array $product, array $selected, ?string $skip = null): bool
    {
        foreach ($selected as $group => $values) {
            if ($values === [] || $group === $skip) {
                continue;
            }

            $have = $product['_facets'][$group] ?? [];

            if (array_intersect($values, array_keys($have)) === []) {
                return false;
            }
        }

        return true;
    }

    /** Lọc cả danh sách. */
    public static function apply(array $products, array $selected): array
    {
        return array_values(array_filter(
            $products,
            static fn (array $p) => self::matches($p, $selected)
        ));
    }

    /**
     * Danh sách lựa chọn của MỘT nhóm.
     *
     * @param  array $order  Thứ tự khoá muốn ép (nhóm Giới tính). Rỗng =
     *                       xếp theo số sản phẩm giảm dần, hoà thì theo nhãn.
     * @return array<int, array{key:string,label:string,count:int,total:int,on:bool}>
     */
    public static function group(
        array $products,
        array $selected,
        string $group,
        array $order = [],
        array $seed = []
    ): array {
        /*
         * ─────────────────────────────────────────────────────────────────────
         * $seed — MỤC LUÔN CÓ MẶT DÙ KHO CHƯA CÓ HÀNG NÀO (2026-08-30)
         *
         * Mặc định danh sách lựa chọn dựng TỪ HÀNG ĐANG BÁN: nhóm nào không sản
         * phẩm nào có giá trị thì rỗng, và cột lọc tự bỏ qua nó. Đúng cho tám
         * nhóm dựng từ dữ liệu người nhập gõ tự do — bày một tiêu chí chưa món
         * nào có là mời người ta bấm vào một lưới rỗng.
         *
         * BỐN NHÓM TRÒNG KÍNH thì ngược lại, vì danh sách của chúng do CỬA HÀNG
         * KHAI ở /quan-tri/thuoc-tinh-trong. Khai xong mà bộ lọc vẫn trống cho
         * tới khi có người đi tick từng sản phẩm là một khoảng im lặng dài, và
         * nó trông y như hỏng — đã xảy ra thật ngay ngày đầu bật tính năng.
         *
         * Seed đưa cả danh sách khai vào, mỗi mục count = 0. Mục không có hàng
         * vì thế hiện MỜ (xem $dead/$tat ở product/index.php) chứ không biến
         * mất — đúng luật đang áp cho mọi nhóm khác, chỉ khác ở chỗ mục ấy
         * chưa từng có hàng thay vì vừa bị một tiêu chí khác loại ra.
         *
         * Nhãn của hàng thật ĐÈ LÊN nhãn seed: cùng một khoá thì lấy cách viết
         * gặp trong kho, giữ nguyên nếp "nhãn theo cách viết gặp đầu tiên" ở
         * ngay dưới.
         * ─────────────────────────────────────────────────────────────────────
         */
        $labels = [];
        $counts = [];
        $totals = [];

        foreach ($seed as $key => $label) {
            $labels[$key] = $label;
            $counts[$key] = 0;
            $totals[$key] = 0;
        }

        /* Khoá nào đã lấy nhãn từ hàng thật — xem chú thích ở vòng lặp dưới. */
        $daDeNhan = [];

        foreach ($products as $p) {
            $have = $p['_facets'][$group] ?? [];

            if ($have === []) {
                continue;
            }

            // Đếm với mọi nhóm khác đang bật, trừ chính nhóm này
            $counted = self::matches($p, $selected, $group);

            foreach ($have as $key => $label) {
                // Nhãn lấy theo cách viết GẶP ĐẦU TIÊN. Hai sản phẩm ghi
                // "Ray-Ban" và "RAY-BAN" cho ra cùng một khoá; in cả hai dòng
                // thì cột lọc có hai thương hiệu y hệt nhau, mỗi dòng một nửa
                // số hàng.
                /* Hàng thật ĐÈ nhãn seed đúng một lần: seed đã đặt sẵn khoá
                   nên ??= sẽ không bao giờ ghi, và bộ lọc mãi mang nhãn của
                   bảng quản trị kể cả khi kho viết khác. Dùng cờ riêng thay
                   vì bỏ ??=, để vẫn giữ nếp "lấy cách viết gặp ĐẦU TIÊN". */
                if (!isset($daDeNhan[$key])) {
                    $labels[$key]    = $label;
                    $daDeNhan[$key]  = true;
                }

                $counts[$key] ??= 0;
                $totals[$key] = ($totals[$key] ?? 0) + 1;

                if ($counted) {
                    $counts[$key]++;
                }
            }
        }

        /*
         * SO SÁNH BẰNG CHUỖI Ở CẢ HAI VẾ, và đây là chỗ đã có lỗi thật.
         *
         * $key đến từ khoá của mảng $labels, mà PHP TỰ ÉP khoá dạng số về int:
         * nhóm "Khoảng giá" đánh số các mốc 0..5 nên $key ở đó là int, trong
         * khi $selected['price'] là ['2'] — chuỗi, vì controller dựng nó từ
         * tham số URL. in_array(2, ['2'], true) trả false.
         *
         * Hậu quả trước 2026-08-30: khoảng giá LỌC đúng nhưng không bao giờ
         * được đánh dấu là đang chọn. Phép lọc chạy qua matches() dùng
         * array_intersect() — hàm đó so bằng chuỗi nên không dính lỗi, và
         * chính vì thế lỗi sống lâu: lưới ra đúng kết quả, chỉ có giao diện
         * nói sai. Dãy nút tròn cũ thì chỉ mất một chấm đỏ; đổi sang ô chọn xổ
         * xuống là nó lộ hẳn — ô luôn hiện "Tất cả mức giá" trong lúc đang lọc.
         *
         * Ép chuỗi cả hai vế chứ không bỏ cờ `true`: so lỏng thì '0' == 'abc'
         * ra true ở vài phiên bản PHP cũ, và khoá của các nhóm khác đều là
         * chuỗi do người nhập gõ.
         */
        $on      = array_map('strval', $selected[$group] ?? []);
        $options = [];

        foreach ($labels as $key => $label) {
            $options[] = [
                'key'   => $key,
                'label' => $label,
                'count' => $counts[$key],
                'total' => $totals[$key],
                'on'    => in_array((string) $key, $on, true),
            ];
        }

        /*
         * ═════════════════════════════════════════════════════════════════════
         * TUỲ BIẾN CỦA CỬA HÀNG ÁP Ở ĐÂY — MỘT CHỖ CHO CẢ BA TRANG
         *
         * Ẩn và ghim vốn nằm trong ProductModel::catalog(). Nhưng trang chi
         * tiết bộ sưu tập gọi THẲNG group() chứ không qua catalog(), nên nó
         * không nhận được gì cả: cửa hàng ẩn "Oval" ở /quan-tri/tieu-chi-loc
         * thì trang gọng kính nghe lời, trang bộ sưu tập vẫn bày ra.
         *
         * ⚠ ĐỪNG chuyển hai khối này ngược lên tầng gọi. Mọi danh sách lựa
         * chọn của cả site đều đi qua đúng hàm này — đây là chỗ duy nhất áp
         * một lần mà không trang nào bị bỏ quên.
         * ═════════════════════════════════════════════════════════════════════
         */
        $de = FilterOverrideModel::forGroup($group);

        if ($de !== []) {
            /*
             * THỨ TỰ CỬA HÀNG XẾP TAY ĐỨNG TRƯỚC thứ tự máy tính theo số lượng.
             *
             * Chỉ mục CÓ sort_order > 0 mới được ghim; mục để 0 (mặc định) rơi
             * xuống dưới và vẫn xếp theo số hàng như trước. Nhờ vậy cửa hàng
             * ghim ba màu bán chạy lên đầu mà không phải xếp tay cả ba chục
             * mục còn lại.
             */
            $ghim = [];

            foreach ($de as $khoa => $o) {
                if ($o['sort_order'] > 0) {
                    $ghim[$khoa] = $o['sort_order'];
                }
            }

            if ($ghim !== []) {
                asort($ghim);

                /*
                 * array_unique GIỮ LẦN XUẤT HIỆN ĐẦU — và đó là cả điểm.
                 *
                 * ⚠ Bản trước chỉ array_merge. Với nhóm đã có $order sẵn
                 * (Giới tính, bốn nhóm tròng) thì khoá vừa ghim NẰM HAI LẦN
                 * trong mảng, mà array_flip() bên dưới lấy lần CUỐI — nên thứ
                 * hạng của nó thành thứ hạng cũ và cú ghim không có tác dụng
                 * gì. Ghim "Nữ" xong vẫn thấy "Nam" đứng trước.
                 */
                $order = array_values(array_unique(array_merge(array_keys($ghim), $order)));
            }

            /*
             * MỤC BỊ ẨN RỜI KHỎI DANH SÁCH LỰA CHỌN — nhưng KHÔNG rời khỏi
             * facet của sản phẩm (xem deLen() ở trên). Nghĩa là: thôi bày ra
             * để bấm, mà địa chỉ ?shape[]=… cũ vẫn lọc đúng.
             *
             * MỤC ĐANG BẬT thì KHÔNG ẩn: khách đang đứng trên một tiêu chí mà
             * nó biến mất khỏi cột lọc thì không còn cách nào tắt nó đi ngoài
             * việc tự sửa thanh địa chỉ.
             */
            $options = array_values(array_filter(
                $options,
                static fn (array $o): bool =>
                    !empty($o['on']) || ($de[(string) $o['key']]['is_visible'] ?? true)
            ));
        }

        if ($order !== []) {
            $rank = array_flip($order);

            /*
             * MỤC KHÔNG NẰM TRONG $order VẪN XẾP THEO SỐ HÀNG, không theo vần.
             *
             * ⚠ Bản trước hoà thì so nhãn. Hậu quả chỉ lộ ra từ khi có tính
             * năng ghim: ghim MỘT thương hiệu lên đầu là $order khác rỗng, và
             * ba chục hãng còn lại lập tức nhảy từ "nhiều hàng trước" sang xếp
             * theo bảng chữ cái — ghim một mục mà xáo trộn cả cột.
             */
            usort($options, static fn ($a, $b) =>
                (($rank[$a['key']] ?? PHP_INT_MAX) <=> ($rank[$b['key']] ?? PHP_INT_MAX))
                ?: ($b['total'] <=> $a['total'])
                ?: strcmp($a['label'], $b['label']));

            return $options;
        }

        /*
         * Nhiều trước, ít sau — thương hiệu chiếm nửa kho phải nằm đầu danh
         * sách chứ không lẫn giữa những hãng chỉ có một món. Hoà thì xếp theo
         * nhãn để thứ tự không đổi giữa hai lần tải trang (usort của PHP
         * không ổn định).
         *
         * XẾP MỘT LẦN, THEO SỐ ĐẾM CHƯA LỌC: dùng 'count' thì mỗi lần bấm một
         * huy hiệu là cả danh sách nhảy chỗ ngay dưới con trỏ. 'total' là số
         * sản phẩm mang khoá đó trên toàn kho, không đổi theo thao tác.
         */
        usort($options, static fn ($a, $b) =>
            $b['total'] <=> $a['total'] ?: strcmp($a['label'], $b['label']));

        return $options;
    }

    /**
     * Kho này đã có giá chưa?
     *
     * Nhóm "Khoảng giá" tự ẩn khi câu trả lời là chưa: bốn mốc giá đứng im
     * không lọc ra được gì chỉ làm người dùng bấm rồi tưởng trang hỏng. Nhập
     * giá cho một sản phẩm là nhóm tự hiện lại, không phải sửa code.
     */
    public static function hasPrices(array $products): bool
    {
        foreach ($products as $p) {
            if ((int) ($p['price'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }
}
