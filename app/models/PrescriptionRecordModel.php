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

    /**
     * Lý do sửa phải dài tối thiểu bao nhiêu ký tự.
     *
     * 10 là con số BA chốt (X21). Nó vừa đủ để chặn "ok", "sua", "." — ba thứ
     * người ta gõ khi bị bắt điền một ô mà không thấy nó để làm gì — và vẫn
     * ngắn hơn một câu ngắn nhất còn có nghĩa: "Nhập nhầm trục".
     */
    public const LY_DO_TOI_THIEU = 10;

    /**
     * MIỀN GIÁ TRỊ SỐ ĐO — Q63.1, Q63.3, Q63.4, Q63.7, chốt 04/09/2026.
     *
     * Mỗi cặp là [nhỏ nhất, lớn nhất]. Đây là ràng buộc NGHIỆP VỤ, không phải
     * ràng buộc kiểu dữ liệu: DECIMAL(4,2) chứa được tới 99.99, nhưng một mắt
     * -45 điốp thì không tồn tại — nó là dấu chấm gõ sai chỗ, và con số đó đi
     * thẳng tới máy mài tròng nếu không ai chặn.
     *
     * CYL để [-6, 0] theo đúng Q63.1. Người nhập vẫn gõ được số dương: Q63.2
     * chốt nhận cả hai dấu rồi tự quy về âm khi lưu, vì hai bệnh viện ghi hai
     * kiểu và bắt kỹ thuật viên tự đổi dấu là bắt họ làm phép tính giữa lúc
     * đang nhập liệu.
     */
    private const MIEN = [
        'sph'        => [-20.0, 20.0],
        'cyl'        => [-6.0, 0.0],
        'axis'       => [0, 180],
        'pd_mat'     => [20.0, 40.0],   // Q63.4 — PD từng mắt
        'pd_hai_mat' => [30.0, 90.0],   // cột `pd` di sản
        'add'        => [0.0, 3.5],     // Q63.7 — độ cộng lão thị
        'seg'        => [10.0, 40.0],   // Q63.7 — chiều cao tâm tròng
        'va'         => [0.0, 2.0],     // thị lực đã quy về thập phân
    ];

    /**
     * Bước nhảy của các trường điốp — 0.25.
     *
     * Q63.1 và Q63.7 đều chốt bước 0.25. Tròng kính chỉ được mài theo nấc đó,
     * nên -2.30 không phải một con số "gần đúng" mà là một con số KHÔNG MÀI
     * ĐƯỢC: người nhận đơn sẽ tự làm tròn, và không ai biết họ tròn lên hay
     * tròn xuống.
     */
    private const BUOC = 0.25;

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

        /* CHƯA NÂNG CẤP CSDL THÌ CHẠY CÂU CŨ.

           columnExists() có nhớ đệm nên đây không phải một lượt hỏi thêm mỗi
           lần gọi. Lùi về câu cũ chứ không ném lỗi: cùng lối phòng thủ đã cứu
           trang chủ hồi 2026-08-22, khi một câu SELECT nhắc tới cột chưa có
           làm trắng cả site. */
        if (!self::coPhienBan()) {
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

        /*
         * MỘT DÒNG CHO MỖI LẦN ĐO, KHÔNG PHẢI MỘT DÒNG CHO MỖI PHIÊN BẢN.
         *
         * Từ 04/09/2026 sửa một bản ghi sinh ra phiên bản mới thay vì ghi đè
         * (X21). Nếu màn hình liệt kê thẳng mọi dòng thì một lần đo bị sửa hai
         * lần hiện thành ba dòng cùng ngày, cùng số gần giống nhau — và người
         * đọc phải tự đoán dòng nào đang có hiệu lực. Đó đúng là kiểu nhầm mà
         * mô hình chỉ-thêm sinh ra nếu không ai lọc.
         *
         * Câu con lấy phien_ban lớn nhất của từng nhóm rồi nối ngược lại, thay
         * vì GROUP BY thẳng: GROUP BY trả về một dòng nhưng các cột khác lấy
         * tuỳ ý bản nào trong nhóm (ONLY_FULL_GROUP_BY tắt) — tức số đo hiển
         * thị có thể là của phiên bản cũ trong khi ngày lại của phiên bản mới.
         *
         * `so_phien_ban` đi kèm để view biết có nên hiện nút "Xem phiên bản
         * trước" hay không, khỏi phải hỏi thêm một câu cho mỗi dòng.
         */
        return Database::fetchAll(
            'SELECT c.*, s.name AS store_name,
                    a.code AS appointment_code, a.appointment_date,
                    ap.full_name AS author_name,
                    m.so_phien_ban
               FROM customer_prescriptions c
               JOIN (
                     SELECT ban_goc_id,
                            MAX(phien_ban) AS pb_max,
                            COUNT(*)       AS so_phien_ban
                       FROM customer_prescriptions
                      WHERE user_id = :uid
                      GROUP BY ban_goc_id
                    ) m ON m.ban_goc_id = c.ban_goc_id AND m.pb_max = c.phien_ban
               LEFT JOIN stores s        ON s.id  = c.store_id
               LEFT JOIN appointments a  ON a.id  = c.appointment_id
               LEFT JOIN profiles ap     ON ap.id = c.created_by
              WHERE c.user_id = :uid2
              ORDER BY c.measured_at DESC, c.created_at DESC',
            ['uid' => $userId, 'uid2' => $userId]
        );
    }

    /**
     * MỌI PHIÊN BẢN của một lần đo, cũ nhất trước.
     *
     * Cũ nhất TRƯỚC chứ không phải sau, khác mọi danh sách khác trong module:
     * đây là thứ người ta đọc để hiểu "đã sửa gì, vì sao", và câu chuyện đó
     * chỉ đọc xuôi mới hiểu được.
     *
     * Nhận ban_goc_id chứ không nhận id một phiên bản bất kỳ, để nơi gọi không
     * phải quan tâm mình đang cầm phiên bản nào.
     */
    public static function phienBan(string $banGocId, string $userId): array
    {
        if (!self::available() || !self::coPhienBan()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT c.*, ap.full_name AS author_name, s.name AS store_name
               FROM customer_prescriptions c
               LEFT JOIN profiles ap ON ap.id = c.created_by
               LEFT JOIN stores s    ON s.id  = c.store_id
              WHERE c.user_id = :uid AND c.ban_goc_id = :goc
              ORDER BY c.phien_ban ASC',
            ['uid' => $userId, 'goc' => $banGocId]
        );
    }

    /**
     * CSDL đã có bộ cột phiên bản chưa.
     *
     * Hỏi đúng MỘT cột đại diện chứ không hỏi cả mười hai: chúng đi cùng nhau
     * trong một file migration, nên có cột này thì có cả bộ. Hỏi từng cột là
     * mười hai lượt tra information_schema cho một câu trả lời duy nhất.
     */
    public static function coPhienBan(): bool
    {
        return Database::columnExists('customer_prescriptions', 'ban_goc_id');
    }

    /**
     * CSDL đã có cột `nguoi_duoc_do` chưa (X24).
     *
     * Cột này đến ở một file migration KHÁC với bộ cột phiên bản, nên nó cần
     * phép hỏi riêng. Gộp vào coPhienBan() thì trên một máy đã chạy migration
     * 04/09 mà chưa chạy 06/09, câu INSERT sẽ nhắc tới một cột chưa tồn tại và
     * ném 1054 đúng lúc kỹ thuật viên bấm Lưu.
     */
    public static function coNguoiDuocDo(): bool
    {
        return Database::columnExists('customer_prescriptions', 'nguoi_duoc_do');
    }

    /**
     * Tên hiển thị cho một bản ghi — X24.
     *
     * Cột trống nghĩa là CHÍNH CHỦ, nên nơi hiển thị lùi về tên tài khoản chứ
     * không in dấu gạch. Gom vào đây thay vì để mỗi view tự lùi: mỗi chỗ tự lùi
     * là mỗi chỗ có cơ hội quên, và cái quên đó biến "mẹ" thành "—" trên đúng
     * cái bảng mà cột này sinh ra để làm rõ.
     */
    public static function tenNguoiDuocDo(array $ban, ?string $tenChu = null): string
    {
        $nguoi = trim((string) ($ban['nguoi_duoc_do'] ?? ''));

        if ($nguoi !== '') {
            return $nguoi;
        }

        return ($tenChu !== null && trim($tenChu) !== '') ? trim($tenChu) : 'Chính chủ';
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

        /* CHỈ TRỪ TRONG CÙNG MỘT NGƯỜI — X24.

           Trước 06/09/2026 dòng này là `$lichSu[$i + 1]`, tức bản kề dưới bất
           kể của ai. Từ khi có cột `nguoi_duoc_do`, một tài khoản có thể chứa
           số đo của mẹ và của hai đứa con, và bản kề dưới rất hay là của người
           khác. Trừ hai người cho nhau ra một con số trông y hệt một con số
           thật: "P -1.50 sau 0 tháng" đọc như mắt xấu đi trong một buổi chiều.

           Khoá nhóm dùng đúng giá trị thô của cột (NULL = chính chủ, gộp về
           chuỗi rỗng) chứ không dùng tenNguoiDuocDo(): hàm kia lùi về tên chủ
           tài khoản để HIỂN THỊ, và nếu ai đó gõ đúng tên chủ vào ô người được
           đo thì hai nhóm khác nhau sẽ bị gộp làm một. */
        $ai = static function (array $ban): string {
            return trim((string) ($ban['nguoi_duoc_do'] ?? ''));
        };

        for ($i = 0; $i < $n; $i++) {
            $truoc = null;

            for ($j = $i + 1; $j < $n; $j++) {
                if ($ai($lichSu[$j]) === $ai($lichSu[$i])) {
                    $truoc = $lichSu[$j];
                    break;
                }
            }

            // Không tìm được bản cũ hơn của CÙNG người — không có gì để so.
            // Đó là dữ liệu thiếu chứ không phải chênh lệch bằng 0, và hai thứ
            // đó phải hiện khác nhau.
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

        $coPhienBan = self::coPhienBan();

        /*
         * ─────────────────────────────────────────────────────────────────────
         * SỬA KHÔNG CÒN LÀ UPDATE — X21 = A, chốt 04/09/2026.
         *
         * Trước đây nhánh này chạy `UPDATE ... WHERE id = ...`, tức là số cũ
         * biến mất không dấu vết. Với dữ liệu y tế thì đó là mất bằng chứng:
         * khách khiếu nại "các anh cắt sai độ" mà bản ghi đã được sửa thành số
         * đúng thì không còn gì để đối chiếu, và vết trong customer_audit_logs
         * chỉ nói "đã sửa" chứ không nói sửa từ đâu sang đâu.
         *
         * Nay nó CHÈN một phiên bản mới trong cùng nhóm lần đo. Bản cũ nằm
         * nguyên chỗ cũ và vẫn đọc lại được qua phienBan().
         *
         * LÝ DO SỬA LÀ BẮT BUỘC, tối thiểu 10 ký tự. Không phải để làm khó:
         * một chuỗi phiên bản không kèm lý do thì sáu tháng sau chính người
         * sửa cũng không nhớ vì sao có hai con số khác nhau cho cùng một ngày
         * đo, và cả cơ chế phiên bản trở thành vô dụng.
         * ─────────────────────────────────────────────────────────────────────
         */
        if ($id !== null) {
            $cu = self::findOwned($id, $userId);

            if ($cu === null) {
                return ['ok' => false, 'error' => 'Không tìm thấy bản ghi đơn thuốc.'];
            }

            /* CHƯA NÂNG CẤP CSDL THÌ TỪ CHỐI SỬA, không lặng lẽ quay về UPDATE.

               Quay về UPDATE nghĩa là trên một máy chưa chạy migration, thao
               tác sửa vẫn ghi đè y như cũ — và không ai biết, vì màn hình
               không khác gì. Thà chặn hẳn và nói rõ phải chạy migration: đọc
               vẫn bình thường, chỉ mất tạm quyền sửa. */
            if (!$coPhienBan) {
                return ['ok' => false, 'error' =>
                    'Chưa nâng cấp cơ sở dữ liệu nên chưa sửa được hồ sơ khúc xạ. '
                    . 'Chạy database/migrations/2026-09-04-ho-so-khuc-xa-chi-them.sql rồi thử lại.'];
            }

            $lyDo = trim((string) ($input['ly_do'] ?? ''));

            if (utf8Length($lyDo) < self::LY_DO_TOI_THIEU) {
                return ['ok' => false, 'error' =>
                    'Phải ghi lý do sửa, tối thiểu ' . self::LY_DO_TOI_THIEU . ' ký tự. '
                    . 'Bản ghi cũ được giữ lại nên lý do là thứ duy nhất giải thích vì sao có hai con số.'];
            }

            /* Nhóm của bản đang sửa. Bản ghi cũ (tạo trước migration) đã được
               câu UPDATE trong file migration gán ban_goc_id = id, nên nhánh
               ?? chỉ còn là lưới an toàn cho dòng nào lọt qua. */
            $goc = (string) ($cu['ban_goc_id'] ?? '') !== '' ? (string) $cu['ban_goc_id'] : $id;

            $pbMax = (int) Database::fetchValue(
                'SELECT MAX(phien_ban) FROM customer_prescriptions
                  WHERE user_id = :uid AND ban_goc_id = :goc',
                ['uid' => $userId, 'goc' => $goc]
            );

            $moi = self::chen($userId, $actorId, $gia, [
                'ban_goc_id' => $goc,
                'phien_ban'  => $pbMax + 1,
                'ly_do'      => utf8Substr($lyDo, 0, 255),
            ]);

            self::mirrorLatest($userId);
            AuditLogModel::write($userId, 'rx.update',
                'Bản ghi đo ngày ' . formatDate($gia['measured_at'])
                . ' — phiên bản ' . ($pbMax + 1));

            return ['ok' => true, 'id' => $moi];
        }

        $moi = self::chen($userId, $actorId, $gia, [
            /* Bản đầu tiên TỰ TRỎ VÀO CHÍNH NÓ. Để NULL thì câu lọc "phiên bản
               mới nhất của từng nhóm" bỏ sót đúng những bản chưa từng bị sửa —
               tức đại đa số. */
            'ban_goc_id' => null,
            'phien_ban'  => 1,
            'ly_do'      => null,
        ]);

        self::mirrorLatest($userId);
        AuditLogModel::write($userId, 'rx.create',
            'Bản ghi đo ngày ' . formatDate($gia['measured_at']));

        return ['ok' => true, 'id' => $moi];
    }

    /**
     * Chèn một dòng — dùng chung cho cả "đo mới" lẫn "phiên bản mới".
     *
     * Gom vào một chỗ vì hai đường chỉ khác nhau đúng ba cột. Để hai câu INSERT
     * song song thì lần thêm cột số đo tiếp theo phải nhớ sửa cả hai, và quên
     * một bên là số mới lặng lẽ không được lưu ở đúng một trong hai đường.
     *
     * @param array $meta ban_goc_id · phien_ban · ly_do
     */
    private static function chen(string $userId, string $actorId, array $gia, array $meta): string
    {
        $moi = uuid();
        $ban = $gia + ['id' => $moi, 'user_id' => $userId, 'created_by' => $actorId];

        if (self::coPhienBan()) {
            $ban['ban_goc_id'] = $meta['ban_goc_id'] ?? $moi;
            $ban['phien_ban']  = $meta['phien_ban'];
            $ban['ly_do']      = $meta['ly_do'];
        }

        $cot = array_keys($ban);

        Database::execute(
            'INSERT INTO customer_prescriptions (`' . implode('`, `', $cot) . '`)'
            . ' VALUES (:' . implode(', :', $cot) . ')',
            $ban
        );

        return $moi;
    }

    /**
     * KHÔNG CÒN ĐƯỜNG XOÁ CHO NHÂN VIÊN — X21 = A, chốt 04/09/2026.
     *
     * Hàm vẫn ở đây thay vì bị xoá đi, và luôn trả lỗi. Lý do: route
     * /quan-tri/khach-hang/don-thuoc/xoa đã bị gỡ trong cùng lần sửa này, nhưng
     * một liên kết cũ còn nằm trong tab đang mở của ai đó, hay một bản view
     * chưa kịp nạp lại, vẫn có thể gọi tới. Để hàm biến mất thì đó là lỗi 500
     * khó đọc; để nó trả một câu tiếng Việt thì người dùng biết chuyện gì xảy
     * ra và vì sao.
     *
     * BA đã cân nhắc và chọn phương án chặt nhất trong ba: không xoá cứng,
     * không xoá mềm, không có cả nút. Sai sót được xử lý bằng ĐÍNH CHÍNH —
     * sửa một bản ghi nay sinh phiên bản mới kèm lý do, và đó chính là đường
     * thay thế cho việc xoá.
     *
     * Bản ghi khúc xạ chỉ thật sự biến mất trong đúng hai trường hợp, cả hai
     * đều KHÔNG đi qua đây:
     *   1. Khách yêu cầu xoá tài khoản và đã hết ân hạn (X25 = A) — lúc đó
     *      khoá ngoại fk_cpres_user ON DELETE CASCADE dọn theo.
     *   2. Khách tự xoá bản ghi do CHÍNH MÌNH tự khai — xem xoaBanTuKhai().
     *
     * @return array{ok:bool, error?:string}
     */
    public static function deleteOwned(string $id, string $userId): array
    {
        return ['ok' => false, 'error' =>
            'Hồ sơ khúc xạ không xoá được. Nếu số đo sai, hãy mở bản ghi và sửa — '
            . 'hệ thống lưu thành phiên bản mới kèm lý do, bản cũ vẫn tra lại được.'];
    }

    /**
     * Khách tự xoá bản ghi do CHÍNH MÌNH tự khai.
     *
     * Ngoại lệ duy nhất của luật "không xoá", và nó có lý: bản nguồn
     * 'customer' không phải kết quả đo của cửa hàng — nó là thứ khách gõ vào
     * trang tài khoản của mình. Giữ lại một con số khách đã rút lại, chỉ vì
     * nguyên tắc lưu vết dựng ra cho dữ liệu do cửa hàng tạo, là bắt khách
     * chịu một quy định không nói về họ. Nghị định 13 cũng đứng về phía này.
     *
     * BA KIỂM Ở CẢ HAI TẦNG. Nút chỉ hiện trên bản của chính khách, nhưng nút
     * là HTML: điều kiện thật nằm ở ba mệnh đề WHERE dưới đây, và cả ba đều
     * phải đúng — đúng người, đúng bản ghi, và nguồn phải là 'customer'.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function xoaBanTuKhai(string $id, string $userId): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Không tìm thấy bản ghi đơn thuốc.'];
        }

        $ban = Database::fetchOne(
            "SELECT * FROM customer_prescriptions
              WHERE id = :id AND user_id = :uid AND source = 'customer'",
            ['id' => $id, 'uid' => $userId]
        );

        if ($ban === null) {
            return ['ok' => false, 'error' =>
                'Chỉ xoá được số đo do chính bạn tự khai. Số đo do cửa hàng đo được giữ lại '
                . 'để đối chiếu khi bảo hành hoặc khi cắt tròng lần sau.'];
        }

        /* XOÁ CẢ NHÓM PHIÊN BẢN, không chỉ dòng được trỏ tới. Xoá một phiên
           bản giữa chuỗi để lại một lịch sử thủng lỗ mà không ai giải thích
           được — mà bản tự khai thì cả nhóm đều của chính khách. */
        $goc = (string) ($ban['ban_goc_id'] ?? '') !== '' ? (string) $ban['ban_goc_id'] : $id;

        if (self::coPhienBan()) {
            Database::execute(
                "DELETE FROM customer_prescriptions
                  WHERE user_id = :uid AND ban_goc_id = :goc AND source = 'customer'",
                ['uid' => $userId, 'goc' => $goc]
            );
        } else {
            Database::execute(
                "DELETE FROM customer_prescriptions
                  WHERE id = :id AND user_id = :uid AND source = 'customer'",
                ['id' => $id, 'uid' => $userId]
            );
        }

        self::mirrorLatest($userId);
        AuditLogModel::write($userId, 'rx.delete',
            'Khách tự xoá số đo tự khai ngày ' . formatDate((string) $ban['measured_at']));

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

        /* ĐỘ CẦU (SPH) — Q63.1 chốt -20.00 .. +20.00, bước 0.25.

           Trước 04/09/2026 khoảng này là -30..+30 và không kiểm bước nhảy. Nới
           rộng như vậy không cứu được ai: đơn ngoài -20 điốp gần như chắc chắn
           là dấu chấm gõ sai chỗ ("2.50" thành "250"), và một con số như thế đi
           thẳng tới máy mài tròng. */
        foreach (['od_sph', 'os_sph'] as $c) {
            $ket = self::doSo($in[$c] ?? '', self::MIEN['sph'], 'Độ cầu (SPH)', true);

            if (isset($ket['error'])) {
                return ['ok' => false, 'error' => $ket['error']];
            }

            $gia[$c] = $ket['gia'] !== null ? number_format($ket['gia'], 2, '.', '') : null;
        }

        /* ĐỘ TRỤ (CYL) — Q63.1 chốt -6.00 .. 0.00; Q63.2 chốt NHẬN CẢ HAI DẤU
           rồi tự quy về âm khi lưu.

           Quy về âm chứ không từ chối số dương: hai bệnh viện ghi hai kiểu
           (ký hiệu trụ âm và ký hiệu trụ dương), và bắt kỹ thuật viên tự đổi
           dấu giữa lúc đang nhập liệu là chỗ sinh lỗi — đổi dấu mà quên đổi
           trục là ra một cặp tròng sai hoàn toàn. Máy làm phép đó không quên.

           LƯU Ý: quy đổi ở đây CHỈ đổi dấu, KHÔNG chuyển đổi ký hiệu (phép
           chuyển thật còn phải cộng trụ vào cầu và xoay trục 90°). Cửa hàng
           ghi trụ âm nên đây là thứ đủ dùng; nếu sau này nhận toa trụ dương
           thật thì phải làm phép chuyển đầy đủ, không phải nới hàm này. */
        foreach (['od_cyl', 'os_cyl'] as $c) {
            $ket = self::doSo($in[$c] ?? '', [-6.0, 6.0], 'Độ trụ (CYL)', true);

            if (isset($ket['error'])) {
                return ['ok' => false, 'error' => $ket['error']];
            }

            $gia[$c] = $ket['gia'] !== null
                ? number_format(-abs($ket['gia']), 2, '.', '')
                : null;
        }

        // TRỤC LOẠN THỊ: 0–180 độ, số nguyên. Trục 181 không tồn tại — nửa
        // vòng tròn là đủ mô tả mọi hướng, vì trục 190 chính là trục 10.
        foreach (['od_axis', 'os_axis'] as $c) {
            $raw = trim((string) ($in[$c] ?? ''));

            if ($raw === '') {
                $gia[$c] = null;
                continue;
            }

            if (!ctype_digit($raw) || (int) $raw > self::MIEN['axis'][1]) {
                return ['ok' => false, 'error' => 'Trục (AXIS) phải là số nguyên từ 0 đến 180.'];
            }

            $gia[$c] = (int) $raw;
        }

        /* CÓ TRỤ THÌ BẮT BUỘC CÓ TRỤC — Q63.3.

           Độ trụ nói mắt loạn bao nhiêu, trục nói loạn theo hướng nào. Thiếu
           trục thì con số trụ không mài được: người nhận đơn hoặc phải gọi
           lại hỏi, hoặc tự đoán — và đoán sai trục là cặp kính gây chóng mặt.

           Chiều ngược lại KHÔNG chặn: có trục mà không có trụ là dữ liệu thừa,
           vô hại, và đôi khi là kỹ thuật viên nhập trước phần này. */
        foreach ([['od_cyl', 'od_axis', 'mắt phải'], ['os_cyl', 'os_axis', 'mắt trái']] as [$cCyl, $cAxis, $ten]) {
            if ($gia[$cCyl] !== null && (float) $gia[$cCyl] != 0.0 && $gia[$cAxis] === null) {
                return ['ok' => false, 'error' =>
                    'Đã nhập độ trụ cho ' . $ten . ' thì phải nhập cả trục (AXIS).'];
            }
        }

        /* THỊ LỰC — Q63.6: nhập và hiển thị dạng 10/10, lưu chuẩn hoá thập phân.

           GIỮ CẢ HAI. Cột VARCHAR giữ nguyên văn người gõ vì "10/10" và "1.0"
           không đọc giống nhau trong mắt người xem bệnh án; cột DECIMAL để so
           sánh hai lần đo và vẽ được đường thị lực theo thời gian. */
        foreach ([['od_va', 'od_va_num'], ['os_va', 'os_va_num']] as [$cText, $cNum]) {
            $raw = trim((string) ($in[$cText] ?? ''));

            if ($raw === '') {
                $gia[$cText] = null;
                $gia[$cNum]  = null;
                continue;
            }

            $so = self::thiLucSangSo($raw);

            if ($so === null) {
                return ['ok' => false, 'error' =>
                    'Thị lực ghi dạng 10/10, 8/10 hoặc số thập phân như 0.8.'];
            }

            if ($so < self::MIEN['va'][0] || $so > self::MIEN['va'][1]) {
                return ['ok' => false, 'error' => 'Thị lực quy đổi phải nằm trong khoảng 0 đến 2,0.'];
            }

            $gia[$cText] = utf8Substr($raw, 0, 16);
            $gia[$cNum]  = number_format($so, 2, '.', '');
        }

        /* PD TỪNG MẮT — Q63.4, 20–40 mm mỗi bên.

           Cột `pd` (hai mắt, 30–90 mm) vẫn nhận được để bản ghi cũ sửa lại
           không mất số, nhưng KHÔNG tự suy ra hai cột mắt từ nó: PD hai mắt
           hiếm khi cân bằng, chia đôi là bịa một con số y tế trông y hệt số
           đo thật. Lý do đầy đủ ghi ở schema.sql ngay tại cột `pd`. */
        foreach ([['pd_od', 'PD mắt phải'], ['pd_os', 'PD mắt trái']] as [$c, $ten]) {
            $ket = self::doSo($in[$c] ?? '', self::MIEN['pd_mat'], $ten . ' (mm)', false);

            if (isset($ket['error'])) {
                return ['ok' => false, 'error' => $ket['error']];
            }

            $gia[$c] = $ket['gia'] !== null ? number_format($ket['gia'], 1, '.', '') : null;
        }

        $ket = self::doSo($in['pd'] ?? '', self::MIEN['pd_hai_mat'], 'Khoảng cách đồng tử (PD)', false);

        if (isset($ket['error'])) {
            return ['ok' => false, 'error' => $ket['error']];
        }

        $gia['pd'] = $ket['gia'] !== null ? number_format($ket['gia'], 1, '.', '') : null;

        // ĐỘ CỘNG (ADD) — Q63.7, 0.00–3.50 bước 0.25. Chỉ khách lão thị mới có.
        foreach ([['od_add', 'Độ cộng mắt phải'], ['os_add', 'Độ cộng mắt trái']] as [$c, $ten]) {
            $ket = self::doSo($in[$c] ?? '', self::MIEN['add'], $ten, true);

            if (isset($ket['error'])) {
                return ['ok' => false, 'error' => $ket['error']];
            }

            $gia[$c] = $ket['gia'] !== null ? number_format($ket['gia'], 2, '.', '') : null;
        }

        /* CHIỀU CAO TÂM TRÒNG — Q63.7. Không có con số nào trong phiếu chốt,
           nên 10–40 mm là NGƯỠNG DO NHÓM PHÁT TRIỂN ĐẶT: nó bao trọn mọi khổ
           gọng đang bán và chỉ để chặn lỗi gõ, không phải một quy tắc nghiệp
           vụ. Nếu cửa hàng gặp gọng ngoài khoảng này thì nới ở đây, không phải
           bỏ hẳn phép kiểm. */
        foreach ([['od_seg_height', 'Chiều cao tâm tròng mắt phải'],
                  ['os_seg_height', 'Chiều cao tâm tròng mắt trái']] as [$c, $ten]) {
            $ket = self::doSo($in[$c] ?? '', self::MIEN['seg'], $ten . ' (mm)', false);

            if (isset($ket['error'])) {
                return ['ok' => false, 'error' => $ket['error']];
            }

            $gia[$c] = $ket['gia'] !== null ? number_format($ket['gia'], 1, '.', '') : null;
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

        /* GHI CHÚ KỸ THUẬT VIÊN — NỘI BỘ, khách không đọc được.

           Tách khỏi `note` vì Q65.3 cho khách xem lại lịch sử đo của mình. Một
           ô ghi chú duy nhất nghĩa là nhận định chuyên môn ("nghi ngờ đục thuỷ
           tinh thể, khuyên đi khám") hiện thẳng cho khách — thứ không nên đọc
           mà không có người giải thích bên cạnh. */
        $tech = trim((string) ($in['tech_note'] ?? ''));
        $gia['tech_note'] = $tech !== '' ? utf8Substr($tech, 0, 500) : null;

        /* NGƯỜI ĐƯỢC ĐO — X24.

           Để TRỐNG nghĩa là chính chủ tài khoản, và đó là mặc định đúng với
           gần hết dữ liệu. Không tự điền tên chủ vào đây: một bản sao họ tên
           nằm trong bảng y tế sẽ lệch ngay lần khách đổi tên đầu tiên, mà lúc
           đó không ai phân được dòng nào là tên thật. Nơi hiển thị tự lùi về
           tên chủ tài khoản khi cột trống — xem tenNguoiDuocDo(). */
        $nguoi = trim((string) ($in['nguoi_duoc_do'] ?? ''));

        if ($nguoi !== '' && utf8Length($nguoi) < 2) {
            return ['ok' => false, 'error' =>
                'Tên người được đo quá ngắn. Bỏ trống nếu là chính chủ tài khoản.'];
        }

        $gia['nguoi_duoc_do'] = $nguoi !== '' ? utf8Substr($nguoi, 0, 120) : null;

        /* CỘT MỚI CHỈ GỬI KHI CSDL ĐÃ CÓ CHÚNG.

           Chưa chạy migration mà câu INSERT nhắc tới `pd_od` là lỗi 1054, và
           lỗi đó rơi đúng vào lúc kỹ thuật viên bấm Lưu sau khi đã nhập xong
           một hồ sơ. Bỏ các khoá này ra thì bản ghi vẫn lưu được với đúng bộ
           cột cũ — mất mấy trường mới, không mất cả thao tác. */
        if (!self::coPhienBan()) {
            foreach (['pd_od', 'pd_os', 'od_add', 'os_add', 'od_seg_height',
                      'os_seg_height', 'od_va_num', 'os_va_num', 'tech_note'] as $c) {
                unset($gia[$c]);
            }
        }

        // Cùng lý lẽ, nhưng cột này đến ở migration 06/09 nên hỏi riêng.
        if (!self::coNguoiDuocDo()) {
            unset($gia['nguoi_duoc_do']);
        }

        return ['ok' => true, 'values' => $gia];
    }

    /**
     * Đọc một ô số: chuẩn hoá dấu phẩy, kiểm miền, kiểm bước nhảy 0.25.
     *
     * Gom vào một chỗ vì mười hai ô số đo đều cần đúng bốn phép này, và viết
     * lại mười hai lần thì sớm muộn có một ô quên kiểm bước nhảy — mà ô quên
     * ấy không báo gì cả, nó chỉ lặng lẽ nhận -2.30.
     *
     * @param  array{0:float,1:float} $mien  [nhỏ nhất, lớn nhất]
     * @param  bool                   $buoc  có bắt bước 0.25 không
     * @return array{gia?:?float, error?:string}
     */
    private static function doSo(mixed $raw, array $mien, string $ten, bool $buoc): array
    {
        // Dấu phẩy thập phân: bàn phím tiếng Việt và Excel đều cho ra "-2,25".
        // Đổi thành dấu chấm chứ đừng từ chối — người gõ không sai, chỉ là gõ
        // theo thói quen bản địa.
        $raw = str_replace(',', '.', trim((string) $raw));

        if ($raw === '') {
            return ['gia' => null];
        }

        if (!is_numeric($raw)) {
            return ['error' => $ten . ' phải là số.'];
        }

        $so = (float) $raw;

        if ($so < $mien[0] || $so > $mien[1]) {
            return ['error' => sprintf(
                '%s phải nằm trong khoảng %s đến %s.',
                $ten,
                rtrim(rtrim(number_format($mien[0], 2, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($mien[1], 2, '.', ''), '0'), '.')
            )];
        }

        /* BƯỚC 0.25 — so trên số ĐÃ NHÂN 4 rồi làm tròn, không so trực tiếp.

           Số thực trong máy tính không chính xác tuyệt đối: fmod(-2.25, 0.25)
           có thể ra 0.2499999999 thay vì 0. So kiểu đó thì một con số hợp lệ
           thỉnh thoảng bị từ chối, và lỗi đó không tái hiện được theo ý muốn —
           loại lỗi tệ nhất để đi tìm. */
        if ($buoc) {
            $nhan = $so / self::BUOC;

            if (abs($nhan - round($nhan)) > 0.001) {
                return ['error' => $ten . ' phải là bội của 0.25. Ví dụ: -2.25 · -2.50 · -2.75'];
            }
        }

        return ['gia' => $so];
    }

    /**
     * "10/10" · "8/10" · "0.8" -> 1.00 · 0.80 · 0.80. Không đọc được thì null.
     *
     * Nhận cả hai cách viết vì cả hai đều đang được dùng thật: phiếu đo của
     * cửa hàng ghi phân số, còn máy đo tự động in ra số thập phân. Bắt người
     * nhập tự quy đổi là thêm một phép tính vào giữa lúc họ đang gõ số liệu.
     */
    private static function thiLucSangSo(string $raw): ?float
    {
        $raw = str_replace(',', '.', trim($raw));

        if (preg_match('#^([0-9]+(?:\\.[0-9]+)?)\\s*/\\s*([0-9]+(?:\\.[0-9]+)?)$#', $raw, $m)) {
            $mau = (float) $m[2];

            return $mau > 0 ? (float) $m[1] / $mau : null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }
}
