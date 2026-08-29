<?php

/**
 * EyewearSpecs — đọc các cột thông số kính của một mặt hàng thành thứ in được.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI THỨ Ở ĐÂY LÀ SUY RA, KHÔNG PHẢI LƯU TRỮ
 *
 * Lớp này không chạm CSDL. Nó nhận một dòng `products` đã đọc sẵn rồi trả về
 * chuỗi hiển thị, khoá cỡ, các nhóm dòng thông số. Lý do tách khỏi model: đây
 * là những phép chỉ có nghĩa khi ĐEM IN, và chúng phải cho ra cùng một kết quả
 * ở bảng so sánh, ngăn kéo thông số và trang chi tiết sản phẩm.
 *
 * Sáu thứ CỐ Ý không có cột trong CSDL và được tính ở đây — xem khối chú thích
 * trong migrations/2026-08-27-bo-suu-tap-khung-ba-lop.sql. Nguyên tắc: một
 * con số suy ra được thì đừng lưu bản sao, vì bản sao sẽ có ngày lệch.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CỘT TRỐNG THÌ BỎ HẲN DÒNG, KHÔNG IN DẤU GẠCH
 *
 * Mọi hàm dựng nhóm dòng ở đây đều lọc bỏ giá trị rỗng. Kho hàng thật thì
 * chín trên mười mặt hàng chỉ nhập vài cột, nên in "—" cho phần còn lại sẽ
 * cho ra một bảng dài toàn dấu gạch — trông như dữ liệu hỏng chứ không phải
 * như thông tin chưa có. Cùng luật mà bảng thông số ở product/detail.php đang
 * dùng, kể cả mẹo coi "-", "–", "N/A" là rỗng.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class EyewearSpecs
{
    /**
     * Giá trị này có đáng in ra không?
     *
     * Bắt cả dấu gạch và "N/A" vì nhân viên gõ chúng vào ô không áp dụng —
     * tròng kính thì không có dáng gọng. In nguyên dấu gạch ra bảng trông như
     * dữ liệu bị thiếu chứ không phải cố ý.
     */
    private static function co(mixed $v): bool
    {
        if ($v === null) {
            return false;
        }

        $s = trim((string) $v);

        return $s !== '' && !in_array($s, ['-', '–', '—', 'N/A', 'n/a', '0'], true);
    }

    /** Bỏ mọi cặp có giá trị rỗng khỏi bảng nhãn => giá trị. */
    private static function gon(array $rows): array
    {
        return array_filter($rows, static fn ($v) => self::co($v));
    }

    // ========================================================================
    // SUY RA
    // ========================================================================

    /**
     * Chuẩn ghi kích thước: "52□18-145".
     *
     * Ký tự □ là chuẩn của ngành (nó vẽ hình cầu kính giữa hai tròng), không
     * phải ký tự lỗi font — đừng "sửa" thành dấu gạch.
     *
     * Thiếu một trong ba số thì trả RỖNG chứ không ghép phần có: "52□18-" đọc
     * như một con số bị cắt mất, và người mua sẽ tưởng trang hỏng chứ không
     * hiểu là cửa hàng chưa đo.
     */
    public static function size(array $p): string
    {
        $rong  = (int) ($p['lens_width_mm'] ?? 0);
        $cau   = (int) ($p['bridge_mm'] ?? 0);
        $cang  = (int) ($p['temple_mm'] ?? 0);

        if ($rong <= 0 || $cau <= 0 || $cang <= 0) {
            return '';
        }

        return $rong . '□' . $cau . '-' . $cang;
    }

    /**
     * Cỡ S / M / L, hoặc null.
     *
     * Quy từ TỔNG BỀ RỘNG GỌNG chứ không từ bề rộng tròng — lý do đầy đủ ở
     * config/eyewear.php. Ngoài dải thì trả null: gọng thể thao ôm mặt rộng
     * 160mm mà gọi là "cỡ L" thì người mua theo cỡ L nhận về thứ không giống
     * mấy mẫu L còn lại.
     */
    public static function sizeKey(array $p): ?string
    {
        $rong = (int) ($p['frame_width_mm'] ?? 0);

        if ($rong <= 0) {
            return null;
        }

        foreach ((array) config('eyewear.sizes') as $khoa => $dai) {
            if ($rong >= (int) $dai['min'] && $rong <= (int) $dai['max']) {
                return (string) $khoa;
            }
        }

        return null;
    }

    /** Nhãn phân loại ("Kính râm"), hoặc rỗng. */
    public static function typeLabel(array $p): string
    {
        $khoa  = trim((string) ($p['eyewear_type'] ?? ''));
        $bang  = (array) config('eyewear.types');

        return (string) ($bang[$khoa] ?? '');
    }

    /**
     * Tách một cột CSV thành mảng khoá => nhãn, theo bảng quy đổi trong config.
     *
     * Khoá lạ (nhân viên gõ tay thứ chưa có trong bảng) vẫn được giữ, nhãn là
     * chính chuỗi đó: giấu đi thì người nhập không bao giờ biết mình gõ sai,
     * và dữ liệu cứ thế lệch dần khỏi bảng quy đổi.
     */
    private static function csv(?string $raw, string $bang): array
    {
        $table = (array) config('eyewear.' . $bang);
        $out   = [];

        foreach (preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $khoa) {
            $khoa       = slugify($khoa) ?: $khoa;
            $out[$khoa] = $table[$khoa] ?? $khoa;
        }

        return $out;
    }

    /** @return array<string,string> khoá => nhãn dáng mặt hợp với mẫu này */
    public static function faceShapes(array $p): array
    {
        return self::csv($p['face_shapes'] ?? null, 'face_shapes');
    }

    /** @return array<string,string> khoá => nhãn lớp phủ tròng */
    public static function coatings(array $p): array
    {
        return self::csv($p['lens_coatings'] ?? null, 'coatings');
    }

    /**
     * Chính sách áp cho mặt hàng này: cột riêng nếu có, không thì bản chung.
     *
     * Đây là chỗ DUY NHẤT được phép quyết định điều đó. Rải phép `?:` này ra
     * mỗi view là cách để một ngày nào đó trang bộ sưu tập nói "đổi trả 7
     * ngày" còn trang sản phẩm nói "không đổi trả" cho cùng một mặt hàng.
     */
    public static function policy(array $p, string $key): string
    {
        $rieng = trim((string) ($p[$key] ?? ''));

        if ($rieng !== '') {
            return $rieng;
        }

        return (string) (((array) config('eyewear.defaults'))[$key] ?? '');
    }

    // ========================================================================
    // NHÓM DÒNG CHO NGĂN KÉO THÔNG SỐ
    // ========================================================================

    /** @return array<string,string> Gọng */
    public static function frameRows(array $p): array
    {
        return self::gon([
            'Kiểu dáng'    => $p['frame_shape'] ?? null,
            'Chất liệu'    => $p['material'] ?? null,
            'Hoàn thiện'   => $p['frame_finish'] ?? null,
            'Bản lề'       => $p['hinge_type'] ?? null,
            'Đệm mũi'      => $p['nose_pad'] ?? null,
            'Trọng lượng'  => self::co($p['weight_g'] ?? null) ? $p['weight_g'] . ' g' : null,
        ]);
    }

    /** @return array<string,string> Kích thước */
    public static function sizeRows(array $p): array
    {
        $khoaCo = self::sizeKey($p);
        $mat    = self::faceShapes($p);

        return self::gon([
            'Tổng rộng gọng'  => self::co($p['frame_width_mm'] ?? null) ? $p['frame_width_mm'] . ' mm' : null,
            'Chiều cao tròng' => self::co($p['lens_height_mm'] ?? null) ? $p['lens_height_mm'] . ' mm' : null,
            'Quy đổi'         => $khoaCo !== null ? 'Cỡ ' . $khoaCo : null,
            'Gợi ý dáng mặt'  => $mat === [] ? null : implode(', ', $mat),
        ]);
    }

    /**
     * @return array<string,string> Tròng kính
     *
     * Phân cực và đổi màu in cả khi KHÔNG có ("Không"), khác với mọi dòng còn
     * lại. Chúng là câu hỏi người mua kính râm hỏi trước tiên, và bỏ trống
     * dòng đó thì im lặng bị đọc thành "chắc là có" — cột mặc định 0 nên im
     * lặng ở đây gần như luôn nghĩa là không.
     */
    public static function lensRows(array $p): array
    {
        $phu  = self::coatings($p);
        $cap  = $p['lens_category'] ?? null;
        $bang = (array) config('eyewear.lens_categories');

        $dau = self::gon([
            'Chất liệu'  => $p['lens_material'] ?? null,
            'Chiết suất' => self::co($p['lens_index'] ?? null)
                                ? number_format((float) $p['lens_index'], 2, '.', '') : null,
            'Lớp phủ'    => $phu === [] ? null : implode(' · ', $phu),
        ]);

        $cuoi = self::gon([
            'Truyền sáng VLT' => $p['lens_vlt'] ?? null,
            'Cấp độ tối'      => $cap !== null && $cap !== '' ? ($bang[(int) $cap] ?? null) : null,
            'Base curve'      => $p['base_curve'] ?? null,
            'Lắp độ'          => $p['rx_note'] ?? (!empty($p['rx_ready']) ? 'Lắp được độ' : null),
        ]);

        /*
         * CHƯA AI NHẬP GÌ VỀ TRÒNG THÌ IM LẶNG HẲN.
         *
         * Hai cột `is_polarized` và `is_photochromic` mặc định 0, nên nếu cứ
         * in chúng ra thì một mặt hàng chưa được nhập liệu vẫn khoe hai dòng
         * "Phân cực: Không · Đổi màu: Không" — hai câu khẳng định mà không ai
         * kiểm, về đúng thứ người mua kính râm hỏi đầu tiên.
         */
        if ($dau === [] && $cuoi === [] && empty($p['is_polarized']) && empty($p['is_photochromic'])) {
            return [];
        }

        /*
         * Ngược lại, khi ĐÃ có dữ liệu tròng thì hai dòng ấy in cả khi là
         * "Không" — khác mọi dòng còn lại của lớp này. Bỏ trống chúng giữa một
         * bảng đầy thông số thì im lặng bị đọc thành "chắc là có".
         *
         * Ghép ba mảng theo đúng thứ tự đọc thay vì chèn vào giữa một mảng đã
         * dựng xong: chèn thì phải bám vào một nhãn làm mốc, mà nhãn đó có thể
         * không tồn tại — và khi ấy hai dòng quan trọng nhất rơi xuống cuối.
         */
        return $dau + [
            'Phân cực' => !empty($p['is_polarized']) ? 'Có' : 'Không',
            'Đổi màu'  => !empty($p['is_photochromic']) ? 'Có' : 'Không',
        ] + $cuoi;
    }

    /** @return array<string,string> Giá và ưu đãi */
    public static function priceRows(array $p, ?string $uuDai = null): array
    {
        $laGong = ($p['eyewear_type'] ?? '') === 'gong-can';

        return self::gon([
            // Nhãn đổi theo phân loại: gọng cận bán RỜI tròng, kính râm thì
            // giá đã gồm tròng sẵn. Một nhãn dùng chung cho cả hai là chỗ
            // khách hiểu nhầm rồi tới cửa hàng mới biết phải trả thêm.
            /* ProductPricing chứ không phải cột `price` trần: bảng thông số
               nằm ngay dưới nút mua, hai con số lệch nhau là chỗ khách hỏi
               ngay. */
            ($laGong ? 'Giá gọng (chưa tròng)' : 'Giá lẻ (đã gồm tròng)')
                             => money(ProductPricing::giaBan($p)),
            'Kèm tròng đổi độ' => self::co($p['price_with_lens'] ?? null)
                                    ? money((int) $p['price_with_lens']) : null,
            'Ưu đãi ra mắt'  => $uuDai,
            'Bảo hành'       => self::policy($p, 'warranty'),
            'Đổi trả'        => self::policy($p, 'return_policy'),
        ]);
    }

    /**
     * Phụ kiện đi kèm và chứng nhận, gộp thành MỘT dãy nhãn.
     *
     * Hai thứ khác loại nhưng cùng một vai trò trên trang: chúng là những thứ
     * người mua liếc qua để yên tâm, không phải để đọc kỹ. Tách thành hai dãy
     * chỉ tốn thêm một tiêu đề mà không ai đọc.
     *
     * @return string[]
     */
    public static function chips(array $p): array
    {
        $ra = [];

        foreach (['accessories', 'certifications'] as $key) {
            foreach (preg_split('/\s*,\s*/', self::policy($p, $key), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $mau) {
                $ra[] = trim($mau);
            }
        }

        if (self::co($p['barcode'] ?? null)) {
            $ra[] = (string) $p['barcode'];
        }

        return array_values(array_unique(array_filter($ra)));
    }

    // ========================================================================
    // BẢNG DỰNG TỪ CẢ MỘT BỘ
    // ========================================================================

    /**
     * Bảng quy đổi cỡ, kèm những mẫu THẬT của bộ rơi vào từng cỡ.
     *
     * Dựng từ dữ liệu chứ không gõ tay: bộ thay hàng là bảng đổi theo, và
     * không bao giờ có chuyện bảng mời người ta xem một mẫu đã hết.
     *
     * Cỡ nào không có mẫu nào thì VẪN in ra, chỉ bỏ trống cột mẫu. Giấu đi thì
     * người đọc mất mốc so sánh — biết bộ này không có cỡ S cũng là một thông
     * tin, và là thông tin họ cần trước khi đặt.
     *
     * @param  array $products các mẫu của bộ
     * @return array<int, array{key:string, range:string, faces:string, models:array}>
     */
    public static function sizeTable(array $products): array
    {
        $ra = [];

        foreach ((array) config('eyewear.sizes') as $khoa => $dai) {
            $mau = [];

            foreach ($products as $p) {
                if (self::sizeKey($p) === $khoa) {
                    $mau[] = ['name' => (string) $p['name'], 'slug' => (string) $p['slug']];
                }
            }

            $ra[] = [
                'key'    => (string) $khoa,
                'range'  => $dai['min'] . ' – ' . $dai['max'] . ' mm',
                'faces'  => (string) $dai['faces'],
                'models' => $mau,
            ];
        }

        return $ra;
    }

    /**
     * Dáng mặt nào hợp với mẫu nào trong bộ này.
     *
     * Chỉ trả về dáng mặt CÓ ÍT NHẤT MỘT mẫu — khác hẳn sizeTable ở trên, và
     * cố ý. Bảng cỡ có đúng ba dòng nên một dòng trống vẫn đọc được như "bộ
     * này không có cỡ S"; bảng dáng mặt có sáu dòng, mà một bộ sáu mẫu thì
     * thường chỉ chạm hai ba dáng, nên in đủ sáu là bốn dòng trống liên tiếp.
     *
     * @return array<int, array{label:string, models:array}>
     */
    public static function faceTable(array $products): array
    {
        $gom = [];

        foreach ($products as $p) {
            foreach (self::faceShapes($p) as $khoa => $nhan) {
                $gom[$khoa]['label'] = $nhan;
                $gom[$khoa]['models'][] = ['name' => (string) $p['name'], 'slug' => (string) $p['slug']];
            }
        }

        // Sắp theo thứ tự khai trong config, không theo thứ tự gặp: hai bộ
        // khác nhau mà cùng có "mặt tròn" thì nó phải nằm cùng một chỗ.
        $ra = [];
        foreach ((array) config('eyewear.face_shapes') as $khoa => $nhan) {
            if (isset($gom[$khoa])) {
                $ra[] = ['label' => $gom[$khoa]['label'], 'models' => $gom[$khoa]['models']];
                unset($gom[$khoa]);
            }
        }

        // Khoá lạ (không có trong config) xếp cuối, vẫn in ra — xem csv().
        foreach ($gom as $con) {
            $ra[] = ['label' => $con['label'], 'models' => $con['models']];
        }

        return $ra;
    }
}
