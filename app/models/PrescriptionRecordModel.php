<?php

/**
 * PrescriptionRecordModel — lịch sử đơn thuốc kính (`customer_prescriptions`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI BẢNG, MỘT NGUỒN CHÂN LÝ
 *
 *   customer_prescriptions   NHIỀU dòng một khách, không bao giờ ghi đè.
 *                            ĐÂY LÀ NGUỒN CHÂN LÝ.
 *   prescriptions            MỘT dòng một khách. Từ 2026-08-26 nó chỉ còn là
 *                            BẢN SAO của bản ghi mới nhất bên trên.
 *
 * Vì sao không dẹp bảng cũ đi: bốn chỗ đang đọc nó đều nằm trên luồng mua hàng
 * (CartController bước 'so-do', UserModel::seedPrescription, trang
 * /tai-khoan/do-mat, hộp mua hàng). Luồng đó đã gãy đúng một lần vì bảng này —
 * ngày 2026-08-22, khi hosting chưa chạy migration nên năm cột wear_* không
 * tồn tại; khách bấm "Xác nhận độ kính" rồi thấy mình đứng ở giỏ hàng, không
 * một lời giải thích. Không đem luồng mua hàng ra đổi lược đồ lần nữa chỉ để
 * lấy một tính năng của khu quản trị.
 *
 * Nếp "bảng chính + một bản sao để bên kia đọc" đã có sẵn trong dự án:
 * `addresses` -> `profiles`.`address`, xem AddressModel::syncProfileAddress().
 * ─────────────────────────────────────────────────────────────────────────────
 */

class PrescriptionRecordModel extends BaseModel
{
    protected static string $table = 'customer_prescriptions';

    /**
     * Nguồn số đo. CLAUDE.md điểm A1 — hai nguồn này KHÔNG ĐƯỢC TRỘN.
     *
     * Nhãn nói rõ AI đo chứ không chỉ đo Ở ĐÂU: người đọc bảng cần biết con số
     * này đáng tin tới mức nào trước khi đem đi mài tròng.
     */
    public const SOURCES = [
        'store'    => 'Kỹ thuật viên đo',
        'customer' => 'Khách tự khai',
        'external' => 'Toa từ nơi khác',
    ];

    /**
     * Đơn thuốc còn hiệu lực bao nhiêu tháng.
     *
     * TRỎ SANG HẰNG CỦA UserModel chứ không gõ lại số 12. Trang tài khoản của
     * khách đọc mốc đó, màn hình này cũng đọc mốc đó — gõ hai lần thì có ngày
     * một bên đổi thành 6 và khách sẽ thấy "Còn hiệu lực" trong khi nhân viên
     * nhìn cùng một đơn thuốc và thấy "Cần đo lại".
     */
    public const HIEU_LUC_THANG = UserModel::PRESCRIPTION_VALID_MONTHS;

    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    // ========================================================================
    // ĐỌC
    // ========================================================================

    /** Lịch sử của một khách, mới nhất trước. */
    public static function forUser(string $userId): array
    {
        if (!self::available()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT c.*, s.name AS store_name,
                    a.code AS appointment_code, a.appointment_date,
                    ap.full_name AS author_name
               FROM customer_prescriptions c
               LEFT JOIN stores s        ON s.id  = c.store_id
               LEFT JOIN appointments a  ON a.id  = c.appointment_id
               LEFT JOIN profiles ap     ON ap.id = c.created_by
              WHERE c.user_id = :uid
              ORDER BY c.measured_at DESC, c.created_at DESC',
            ['uid' => $userId]
        );
    }

    /** Một bản ghi, nhưng CHỈ khi nó thuộc về đúng khách đang mở. */
    public static function findOwned(string $id, string $userId): ?array
    {
        if (!self::available()) {
            return null;
        }

        /* Kiểm quyền sở hữu NGAY TRONG CÂU LỆNH, không phải bằng một if ở
           controller. Id bản ghi đi qua form dưới dạng hidden input, tức là
           sửa được — mà không có điều kiện user_id ở đây thì sửa một ký tự là
           ghi đè số đo của khách khác. */
        return Database::fetchOne(
            'SELECT * FROM customer_prescriptions WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );
    }

    /**
     * Bản ghi mới nhất — thứ mọi nơi khác nên đọc khi hỏi "độ hiện tại".
     */
    public static function latest(string $userId): ?array
    {
        if (!self::available()) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM customer_prescriptions
              WHERE user_id = :uid
              ORDER BY measured_at DESC, created_at DESC
              LIMIT 1',
            ['uid' => $userId]
        );
    }

    /**
     * Đơn đo trong vòng HIEU_LUC_THANG tháng thì còn dùng được.
     *
     * Gọi thẳng UserModel::prescriptionIsValid() — nó chỉ cần khoá
     * 'measured_at', mà bản ghi ở bảng này có đúng khoá đó. Viết lại phép so
     * ngày ở đây là tạo phiên bản thứ hai của cùng một luật.
     */
    public static function conHieuLuc(?array $ban): bool
    {
        return UserModel::prescriptionIsValid($ban);
    }

    /**
     * Chênh lệch cầu (SPH) so với bản ghi liền trước, theo từng mắt.
     *
     * Đây là câu hỏi thật của người bán kính: "độ có tăng không, tăng bao
     * nhiêu, trong bao lâu". Bảng số trần không trả lời được — mắt phải tự trừ
     * hai dòng cách nhau vài centimet trên màn hình, và người ta trừ sai.
     *
     * @param array $lichSu Kết quả forUser(), tức đã sắp mới nhất trước
     * @return array id bản ghi => ['od' => ?float, 'os' => ?float, 'thang' => ?int]
     */
    public static function chenhLech(array $lichSu): array
    {
        $ket = [];
        $n   = count($lichSu);

        for ($i = 0; $i < $n; $i++) {
            // Bản cuối cùng của mảng là bản CŨ NHẤT — không có gì trước nó để
            // so, nên nó không có chênh lệch. Đó là dữ liệu thiếu chứ không
            // phải chênh lệch bằng 0, và hai thứ đó phải hiện khác nhau.
            $truoc = $lichSu[$i + 1] ?? null;

            if ($truoc === null) {
                continue;
            }

            $nay = $lichSu[$i];

            $hieu = static function (?string $a, ?string $b): ?float {
                // 0.00 là một giá trị hợp lệ (mắt không cận), nên phải phân
                // biệt nó với ô bỏ trống. So với null chứ đừng dùng empty().
                return ($a === null || $b === null) ? null : round((float) $a - (float) $b, 2);
            };

            $thang = null;

            if (!empty($nay['measured_at']) && !empty($truoc['measured_at'])) {
                $t1 = new DateTime((string) $truoc['measured_at']);
                $t2 = new DateTime((string) $nay['measured_at']);
                $kc = $t1->diff($t2);
                $thang = $kc->y * 12 + $kc->m;
            }

            $ket[$nay['id']] = [
                'od'    => $hieu($nay['od_sph'] ?? null, $truoc['od_sph'] ?? null),
                'os'    => $hieu($nay['os_sph'] ?? null, $truoc['os_sph'] ?? null),
                'thang' => $thang,
            ];
        }

        return $ket;
    }

    // ========================================================================
    // GHI
    // ========================================================================

    /**
     * Thêm mới hoặc sửa một bản ghi.
     *
     * @param string|null $id null = thêm mới
     * @return array{ok:bool, error?:string, id?:string}
     */
    public static function save(?string $id, string $userId, array $input, string $actorId): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' =>
                'Bảng customer_prescriptions chưa tồn tại. Chạy database/migrations/2026-08-26-module-khach-hang.sql.'];
        }

        $sach = self::validate($input, $userId);

        if (!$sach['ok']) {
            return $sach;
        }

        $gia = $sach['values'];

        if ($id !== null) {
            if (self::findOwned($id, $userId) === null) {
                return ['ok' => false, 'error' => 'Không tìm thấy bản ghi đơn thuốc.'];
            }

            $cot = [];

            foreach (array_keys($gia) as $c) {
                $cot[] = "`{$c}` = :{$c}";
            }

            Database::execute(
                'UPDATE customer_prescriptions SET ' . implode(', ', $cot) . ' WHERE id = :__id',
                $gia + ['__id' => $id]
            );

            self::mirrorLatest($userId);
            AuditLogModel::write($userId, 'rx.update',
                'Bản ghi đo ngày ' . formatDate($gia['measured_at']));

            return ['ok' => true, 'id' => $id];
        }

        $moi = uuid();

        Database::execute(
            'INSERT INTO customer_prescriptions
                 (id, user_id, appointment_id, source,
                  od_sph, od_cyl, od_axis, od_va,
                  os_sph, os_cyl, os_axis, os_va,
                  pd, measured_at, store_id, note, created_by)
             VALUES
                 (:id, :user_id, :appointment_id, :source,
                  :od_sph, :od_cyl, :od_axis, :od_va,
                  :os_sph, :os_cyl, :os_axis, :os_va,
                  :pd, :measured_at, :store_id, :note, :created_by)',
            $gia + ['id' => $moi, 'user_id' => $userId, 'created_by' => $actorId]
        );

        self::mirrorLatest($userId);
        AuditLogModel::write($userId, 'rx.create',
            'Bản ghi đo ngày ' . formatDate($gia['measured_at']));

        return ['ok' => true, 'id' => $moi];
    }

    /** @return array{ok:bool, error?:string} */
    public static function deleteOwned(string $id, string $userId): array
    {
        $ban = self::findOwned($id, $userId);

        if ($ban === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy bản ghi đơn thuốc.'];
        }

        Database::execute('DELETE FROM customer_prescriptions WHERE id = :id', ['id' => $id]);

        self::mirrorLatest($userId);
        AuditLogModel::write($userId, 'rx.delete',
            'Bản ghi đo ngày ' . formatDate((string) $ban['measured_at']));

        return ['ok' => true];
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    /**
     * Chép bản ghi MỚI NHẤT sang bảng `prescriptions` để phía khách đọc.
     *
     * KHÔNG ĐỤNG NĂM CỘT wear_* — chúng mô tả cặp kính khách ĐANG ĐEO, do
     * chính khách khai ở /tai-khoan/do-mat, và không phải kết quả của một lần
     * đo nào. Câu ON DUPLICATE KEY UPDATE dưới đây chỉ liệt kê các cột số đo,
     * nên phần khách tự khai sống sót qua mọi lần nhân viên nhập số mới.
     *
     * KHÔNG XOÁ dòng `prescriptions` khi lịch sử rỗng, cũng vì lẽ đó: xoá đi
     * là mất luôn wear_* mà không lấy lại được, trong khi thứ vừa bị xoá chỉ
     * là một bản ghi đo.
     */
    private static function mirrorLatest(string $userId): void
    {
        $moi = self::latest($userId);

        if ($moi === null) {
            return;
        }

        try {
            Database::execute(
                'INSERT INTO prescriptions
                     (user_id, od_sph, od_cyl, od_axis, od_va,
                      os_sph, os_cyl, os_axis, os_va,
                      pd, measured_at, store_id, recommendation)
                 VALUES
                     (:user_id, :od_sph, :od_cyl, :od_axis, :od_va,
                      :os_sph, :os_cyl, :os_axis, :os_va,
                      :pd, :measured_at, :store_id, :note)
                 ON DUPLICATE KEY UPDATE
                      od_sph = VALUES(od_sph), od_cyl = VALUES(od_cyl),
                      od_axis = VALUES(od_axis), od_va = VALUES(od_va),
                      os_sph = VALUES(os_sph), os_cyl = VALUES(os_cyl),
                      os_axis = VALUES(os_axis), os_va = VALUES(os_va),
                      pd = VALUES(pd), measured_at = VALUES(measured_at),
                      store_id = VALUES(store_id), recommendation = VALUES(recommendation)',
                [
                    'user_id'     => $userId,
                    'od_sph'      => $moi['od_sph'],
                    'od_cyl'      => $moi['od_cyl'],
                    'od_axis'     => $moi['od_axis'],
                    'od_va'       => $moi['od_va'],
                    'os_sph'      => $moi['os_sph'],
                    'os_cyl'      => $moi['os_cyl'],
                    'os_axis'     => $moi['os_axis'],
                    'os_va'       => $moi['os_va'],
                    'pd'          => $moi['pd'],
                    'measured_at' => $moi['measured_at'],
                    'store_id'    => $moi['store_id'],
                    'note'        => $moi['note'],
                ]
            );
        } catch (Throwable $e) {
            /* NUỐT LỖI Ở ĐÂY LÀ ĐÚNG, và đây là lý do.

               Việc chính vừa xong rồi: bản ghi đã nằm trong bảng lịch sử —
               bảng nguồn chân lý. Chép sang bản sao chỉ là để trang tài khoản
               của khách hiện số mới. Để nó ném ra ngoài thì một cột thiếu
               trong `prescriptions` (máy chưa chạy migration cũ) sẽ làm nhân
               viên KHÔNG NHẬP ĐƯỢC SỐ ĐO, dù chỗ cần ghi đã ghi xong.

               Cùng cách xử lý và cùng lý lẽ với UserModel::seedPrescription()
               ở CartController — xem chú thích dài tại đó. */
            error_log('PrescriptionRecordModel::mirrorLatest: ' . $e->getMessage());
        }
    }

    /**
     * Kiểm dữ liệu nhập.
     *
     * @return array{ok:bool, error?:string, values?:array}
     */
    private static function validate(array $in, string $userId): array
    {
        $ngay = trim((string) ($in['measured_at'] ?? ''));

        if ($ngay === '') {
            return ['ok' => false, 'error' => 'Phải nhập ngày đo.'];
        }

        $d   = DateTime::createFromFormat('Y-m-d', $ngay);
        $loi = DateTime::getLastErrors();

        if ($d === false || ($loi !== false && ($loi['warning_count'] ?? 0) > 0)) {
            return ['ok' => false, 'error' => 'Ngày đo không hợp lệ.'];
        }

        if ($d > new DateTime('today')) {
            return ['ok' => false, 'error' => 'Ngày đo không được ở tương lai.'];
        }

        $source = (string) ($in['source'] ?? 'store');

        if (!isset(self::SOURCES[$source])) {
            return ['ok' => false, 'error' => 'Nguồn số đo không hợp lệ.'];
        }

        $gia = ['source' => $source, 'measured_at' => $ngay];

        /* SPH VÀ CYL: -30.00 .. +30.00.
           Cột là DECIMAL(4,2) nên MySQL chỉ chứa tới 99.99 — nhưng giới hạn
           thật nằm ở nhãn khoa chứ không ở kiểu dữ liệu. Ngoài khoảng này gần
           như chắc chắn là gõ nhầm dấu chấm ("2.50" thành "250"), và một con
           số như thế đi thẳng tới máy mài tròng. */
        foreach (['od_sph', 'od_cyl', 'os_sph', 'os_cyl'] as $c) {
            $raw = trim((string) ($in[$c] ?? ''));

            if ($raw === '') {
                $gia[$c] = null;
                continue;
            }

            // Dấu phẩy thập phân: bàn phím tiếng Việt và Excel đều cho ra
            // "-2,25". Đổi thành dấu chấm chứ đừng từ chối — người gõ không
            // sai, chỉ là gõ theo thói quen bản địa.
            $raw = str_replace(',', '.', $raw);

            if (!is_numeric($raw)) {
                return ['ok' => false, 'error' => 'Giá trị SPH/CYL phải là số. Ví dụ: -2.25'];
            }

            $so = (float) $raw;

            if ($so < -30 || $so > 30) {
                return ['ok' => false, 'error' => 'Giá trị SPH/CYL phải nằm trong khoảng -30.00 đến +30.00.'];
            }

            $gia[$c] = number_format($so, 2, '.', '');
        }

        // TRỤC LOẠN THỊ: 0–180 độ. Trục 181 không tồn tại — nửa vòng tròn là
        // đủ mô tả mọi hướng của một trục, vì trục 190 chính là trục 10.
        foreach (['od_axis', 'os_axis'] as $c) {
            $raw = trim((string) ($in[$c] ?? ''));

            if ($raw === '') {
                $gia[$c] = null;
                continue;
            }

            if (!ctype_digit($raw) || (int) $raw > 180) {
                return ['ok' => false, 'error' => 'Trục (AXIS) phải là số nguyên từ 0 đến 180.'];
            }

            $gia[$c] = (int) $raw;
        }

        foreach (['od_va', 'os_va'] as $c) {
            // Thị lực ghi dạng phân số "10/10" nên là CHUỖI, không phải số.
            $raw     = trim((string) ($in[$c] ?? ''));
            $gia[$c] = $raw !== '' ? utf8Substr($raw, 0, 16) : null;
        }

        $pd = str_replace(',', '.', trim((string) ($in['pd'] ?? '')));

        if ($pd === '') {
            $gia['pd'] = null;
        } elseif (!is_numeric($pd) || (float) $pd < 30 || (float) $pd > 90) {
            // 30–90mm bao trọn từ trẻ nhỏ tới người lớn khổ mặt lớn. Ngoài
            // khoảng đó là gõ nhầm đơn vị (cm thay vì mm) hoặc gõ nhầm ô.
            return ['ok' => false, 'error' => 'Khoảng cách đồng tử (PD) phải từ 30 đến 90 mm.'];
        } else {
            $gia['pd'] = number_format((float) $pd, 1, '.', '');
        }

        // Cơ sở: chuỗi rỗng thành NULL. Khoá ngoại không nhận '' và sẽ đổ lỗi
        // 1452 — một lỗi 500 khó đọc cho thứ đáng ra chỉ là "không chọn".
        $store = trim((string) ($in['store_id'] ?? ''));
        $gia['store_id'] = $store !== '' ? $store : null;

        /* LỊCH HẸN GẮN KÈM PHẢI LÀ LỊCH CỦA CHÍNH KHÁCH NÀY, VÀ PHẢI ĐÃ XONG.

           Ô chọn chỉ liệt kê lịch hợp lệ, nhưng ô chọn là HTML và HTML sửa
           được bằng công cụ nhà phát triển. Kiểm lại ở đây vì đây là chỗ duy
           nhất kẻ gửi dữ liệu không sửa được. */
        $lich = trim((string) ($in['appointment_id'] ?? ''));

        if ($lich === '') {
            $gia['appointment_id'] = null;
        } else {
            $hopLe = Database::fetchValue(
                "SELECT COUNT(*) FROM appointments
                  WHERE id = :id AND user_id = :uid AND status = 'done'",
                ['id' => $lich, 'uid' => $userId]
            );

            if ((int) $hopLe === 0) {
                return ['ok' => false, 'error' =>
                    'Lịch hẹn không hợp lệ — chỉ gắn được lịch đã hoàn tất của chính khách này.'];
            }

            $gia['appointment_id'] = $lich;
        }

        $note = trim((string) ($in['note'] ?? ''));
        $gia['note'] = $note !== '' ? utf8Substr($note, 0, 255) : null;

        return ['ok' => true, 'values' => $gia];
    }
}
