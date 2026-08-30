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
    public const GROUPS = ['shape', 'material', 'eco', 'brand', 'collab', 'collection', 'lens', 'gender', 'price'];

    /**
     * Nhóm chọn-nhiều — tất cả trừ 'price'.
     *
     * Khoảng giá đi chung bộ máy với các nhóm khác (xem attach) để nó cũng
     * được đếm động và cũng theo luật VÀ/HOẶC, nhưng giao diện của nó là một ô
     * chọn xổ xuống chọn-một, nên controller chỉ nhận đúng một giá trị — và
     * trên URL nó là ?price=2 chứ không phải price[]=2.
     */
    public const MULTI = ['shape', 'material', 'eco', 'brand', 'collab', 'collection', 'lens', 'gender'];

    /**
     * Gắn bảng khoá lọc vào từng sản phẩm, một lần cho cả request.
     *
     * Khoá '_facets' có gạch dưới đứng đầu để không đụng tên cột nào của bảng
     * products — view nào duyệt hết các cột (thẻ sản phẩm không, nhưng trang
     * quản trị có) sẽ bỏ qua nó.
     */
    public static function attach(array $products, array $priceRanges = []): array
    {
        foreach ($products as $i => $p) {
            $facets          = ProductTaxonomy::of($p);
            $facets['price'] = self::priceKey($p, $priceRanges);

            $products[$i]['_facets'] = $facets;
        }

        return self::pruneCollabBrands($products);
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
    public static function group(array $products, array $selected, string $group, array $order = []): array
    {
        $labels = [];
        $counts = [];
        $totals = [];

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
                $labels[$key] ??= $label;
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

        if ($order !== []) {
            $rank = array_flip($order);

            usort($options, static fn ($a, $b) =>
                ($rank[$a['key']] ?? PHP_INT_MAX) <=> ($rank[$b['key']] ?? PHP_INT_MAX)
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
