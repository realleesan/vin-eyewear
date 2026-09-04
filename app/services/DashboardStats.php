<?php

/**
 * DashboardStats — mọi con số của trang Tổng quan (/quan-tri).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO TÁCH RA KHỎI CONTROLLER
 *
 * Trước bản này DashboardController vừa đọc request vừa viết SQL vừa quyết định
 * nghiệp vụ, và nó dài 230 dòng cho tám con số không lọc được gì. Nay mỗi con số
 * phải nhân thêm hai trục — KỲ và CƠ SỞ — nên cùng một mệnh đề WHERE bị lặp ở
 * bảy chỗ. Bảy bản chép của một mệnh đề lọc thì sớm muộn có một bản quên áp
 * phạm vi cơ sở, và cái quên đó không gây lỗi: nó chỉ lặng lẽ cho nhân viên cơ
 * sở này thấy doanh thu của cơ sở kia.
 *
 * Nên mệnh đề dựng ĐÚNG MỘT LẦN ở locDon() / locLich() và mọi truy vấn đi qua
 * đó. Lớp này không chạm HTTP (đúng nếp app/services/): controller đọc $_GET rồi
 * đưa vào, lớp này chỉ nhận mảng và trả mảng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA ĐỊNH NGHĨA PHẢI ĐỌC TRƯỚC KHI SỬA BẤT KỲ TRUY VẤN NÀO DƯỚI ĐÂY
 *
 * 1. DOANH THU = tiền ĐÃ VỀ ĐỦ (`payment_status = 'paid'`), không tính đơn đã
 *    huỷ. Giữ nguyên định nghĩa đã có từ trước, và giữ NHẤT QUÁN ở mọi ô: lợi
 *    nhuận gộp, giá trị đơn trung bình, top sản phẩm và biểu đồ đều đọc đúng
 *    tập đơn ấy. Tiền cọc 30% nằm riêng ở ô "Tạm thu" vì hàng chưa giao và đơn
 *    còn huỷ được — cộng nó vào là ghi nhận một khoản bán hàng chưa xảy ra.
 *
 * 2. MỘT ĐƠN THUỘC VỀ KỲ NÀO — theo `created_at`, tức NGÀY KHÁCH ĐẶT, không
 *    phải ngày tiền về (`paid_at`).
 *
 *    Đơn đặt 30/08 mà trả tiền 02/09 được tính vào tháng 8. Đó là một lựa chọn,
 *    và lựa chọn ngược lại cũng có lý. Chọn `created_at` vì ba lẽ:
 *      · mọi ô khác trên trang (tỉ lệ huỷ, giá trị đơn trung bình, số đơn) đều
 *        chỉ có một mốc duy nhất là ngày đặt — để doanh thu chạy trên một trục
 *        thời gian khác thì hai con số cạnh nhau nói về hai tập đơn khác nhau;
 *      · bộ lọc ngày ở trang Đơn hàng (OrderModel::DATE_RANGES) đã dùng
 *        `created_at`, nên bấm từ Tổng quan sang Đơn hàng vẫn ra cùng tập;
 *      · `paid_at` để trống với đơn cũ có trước khi cột ấy ra đời.
 *
 *    HỆ QUẢ PHẢI NÓI RA TRÊN MÀN HÌNH: doanh thu của một kỳ vừa đóng còn nhích
 *    lên được vài hôm sau, khi mấy đơn cuối kỳ thu đủ tiền. Dòng dẫn dưới tiêu
 *    đề trang ghi rõ điều này.
 *
 * 3. LỊCH HẸN thuộc kỳ theo `appointment_date` — ngày khách ĐƯỢC HẸN ĐẾN, không
 *    phải ngày khách bấm đặt. Câu hỏi "kỳ này bao nhiêu khách không đến" hỏi về
 *    những buổi lẽ ra diễn ra trong kỳ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NGÀY THÁNG TÍNH Ở PHP, KHÔNG DÙNG CURDATE() / NOW() CỦA MYSQL
 *
 * PHP đã đặt múi giờ Asia/Ho_Chi_Minh (core/App.php). Máy chủ MySQL thì không
 * hứa gì: trên hosting miễn phí nó thường chạy UTC. Trộn hai đồng hồ vào một
 * trang thì "Hôm nay" của thẻ doanh thu và "quá 24 giờ" của khối cần xử lý lệch
 * nhau bảy tiếng — và lệch đúng vào buổi tối, giờ đông khách nhất.
 *
 * Nên mọi mốc thời gian dựng bằng DateTimeImmutable rồi đi vào truy vấn qua
 * THAM SỐ RÀNG BUỘC. Không có CURDATE() nào trong file này.
 */

class DashboardStats
{
    /**
     * Các kỳ chọn được. Khoá đi thẳng vào URL nên viết tiếng Việt không dấu,
     * gạch nối — cùng quy ước với mọi đường dẫn khác của dự án.
     */
    public const KY = [
        'hom-nay'   => 'Hôm nay',
        '7n'        => '7 ngày qua',
        '30n'       => '30 ngày qua',
        'thang-nay' => 'Tháng này',
        'tuy-chon'  => 'Tuỳ chọn',
    ];

    /** Kỳ mặc định khi mở trang không kèm tham số. */
    public const KY_MAC_DINH = '30n';

    /**
     * Giá trị ?co-so= cho nhóm đơn KHÔNG thuộc cơ sở nào.
     *
     * `orders.store_id` chỉ có nghĩa với đơn nhận tại cửa hàng; đơn giao tận nơi
     * để NULL. Không có mục này thì chọn từng cơ sở rồi cộng lại sẽ THIẾU đúng
     * số đơn giao hàng, mà trên màn hình không có gì nói ra chỗ thiếu ấy.
     */
    public const CO_SO_GIAO = 'giao-tan-noi';

    /**
     * Trần độ dài kỳ tuỳ chọn, tính bằng ngày.
     *
     * Không phải để "bảo vệ máy chủ" — mấy câu SUM này quét chỉ mục ngày, một
     * năm hay ba năm cũng vậy. Nó chặn ?tu=1970-01-01: biểu đồ khi ấy có hai
     * vạn cột và trang treo ở khâu dựng HTML chứ không phải ở CSDL.
     */
    public const TOI_DA_NGAY = 366;

    /**
     * Quá bao nhiêu ngày thì biểu đồ gộp theo TUẦN thay vì theo ngày.
     *
     * 62 ngày ≈ hai tháng. Trên khổ điện thoại hẹp nhất (360px), 62 cột còn
     * khoảng 4px mỗi cột — vẫn đọc được hình dáng. Quá số đó thì các cột mảnh
     * hơn đường kẻ và biểu đồ thành một mảng xám.
     */
    private const NGAY_TOI_DA_THEO_NGAY = 62;

    /** Việc tồn quá bao nhiêu giờ thì vào khối "Cần xử lý gấp". */
    public const NGUONG_TON_DONG_GIO = 24;

    /** Số dòng tối đa liệt kê trong mỗi hàng đợi của khối "Cần xử lý gấp". */
    private const CAN_XU_LY_TOI_DA = 5;

    // ========================================================================
    // KỲ
    // ========================================================================

    /**
     * Giải mã kỳ từ tham số URL.
     *
     * MỌI GIÁ TRỊ LẠ ĐỀU RƠI VỀ KỲ MẶC ĐỊNH, không trang lỗi nào. Đường dẫn của
     * trang này được dán vào Zalo nhóm và bị cắt cụt trên đường đi; một mốc ngày
     * hỏng phải cho ra bảng mặc định kèm ô chọn hiện đúng "30 ngày qua", để
     * người mở thấy ngay là mình đang xem gì.
     *
     * @param  array<string, mixed> $get thường là $_GET
     * @return array{ma:string, nhan:string, tu:string, den:string, soNgay:int,
     *               tuTruoc:string, denTruoc:string, biKepBoiMoc:bool,
     *               biCatNgan:bool, moc:?string}
     */
    public static function ky(array $get, ?string $moc = null): array
    {
        $ma = (string) ($get['ky'] ?? self::KY_MAC_DINH);

        if (!isset(self::KY[$ma])) {
            $ma = self::KY_MAC_DINH;
        }

        $homNay = new DateTimeImmutable('today');
        $biCat  = false;

        if ($ma === 'tuy-chon') {
            /*
             * GIẢI MÃ `den` TRƯỚC, RỒI MỚI `tu` — thứ tự này là cả nội dung.
             *
             * Nếu mỗi ô tự rơi về một mặc định riêng (den -> hôm nay, tu -> hôm
             * nay trừ 29) thì một ô hỏng và một ô tốt sinh ra một khoảng vô
             * nghĩa. Ví dụ thật: ?tu=2026-02-31 (ngày không tồn tại, khách gõ
             * nhầm) & ?den=2026-03-05. `tu` rơi về hôm nay trừ 29, tức là SAU
             * `den` những mấy tháng, phép đổi chỗ bên dưới liền biến nó thành
             * một kỳ 155 ngày mà người dùng không hề yêu cầu — và trang vẫn hiện
             * bình thường, không có gì báo là đã hiểu sai.
             *
             * Neo `tu` vào `den` thì ô hỏng chỉ làm mất đúng phần nó mang: kết
             * quả là 30 ngày tính lùi từ ngày kết thúc mà người dùng gõ đúng.
             */
            $den = self::ngay((string) ($get['den'] ?? '')) ?? $homNay;
            $tu  = self::ngay((string) ($get['tu'] ?? ''))  ?? $den->modify('-29 days');

            /* Gõ ngược đầu đuôi thì ĐỔI CHỖ chứ không báo lỗi: người ta gõ tay
               vào hai ô ngày cạnh nhau, và ý muốn thì rõ ràng. Trả về một kỳ
               rỗng kèm câu "ngày bắt đầu phải trước ngày kết thúc" chỉ bắt họ
               làm lại đúng việc mà máy vừa hiểu được. */
            if ($tu > $den) {
                [$tu, $den] = [$den, $tu];
            }

            // Quá trần thì giữ ĐẦU BÊN PHẢI: người xem quan tâm tới gần đây.
            if ((int) $tu->diff($den)->days + 1 > self::TOI_DA_NGAY) {
                $tu    = $den->modify('-' . (self::TOI_DA_NGAY - 1) . ' days');
                $biCat = true;
            }
        } else {
            $den = $homNay;
            $tu  = match ($ma) {
                'hom-nay'   => $homNay,
                '7n'        => $homNay->modify('-6 days'),
                'thang-nay' => $homNay->modify('first day of this month'),
                default     => $homNay->modify('-29 days'),
            };
        }

        /*
         * MỐC STATS_SINCE VẪN LÀ MỘT CÁI SÀN, KỂ CẢ KHI ĐÃ CÓ BỘ LỌC KỲ.
         *
         * Mốc ấy sinh ra để bắt đầu đếm lại từ 0 mà không xoá dòng nào — thường
         * là để bỏ qua dữ liệu chạy thử trước ngày khai trương. Nếu bộ lọc kỳ
         * mới đè lên nó thì chọn "30 ngày qua" là dữ liệu chạy thử sống dậy, và
         * người đặt mốc không hiểu vì sao. Nên kỳ bị KẸP LẠI ở mốc, và dòng dẫn
         * trên màn hình nói ra là nó đã bị kẹp.
         */
        $biKep = false;

        if ($moc !== null) {
            $mocNgay = new DateTimeImmutable(substr($moc, 0, 10));

            if ($tu < $mocNgay) {
                $tu    = $mocNgay;
                $biKep = true;
            }

            // Cả kỳ nằm trước mốc: kẹp lại thành một kỳ rỗng chứ không đảo ngược.
            if ($den < $tu) {
                $den = $tu;
            }
        }

        $soNgay = (int) $tu->diff($den)->days + 1;

        /* KỲ LIỀN TRƯỚC = CÙNG SỐ NGÀY, dán sát ngay trước kỳ đang xem.
           Không phải "tháng trước" hay "tuần trước" theo lịch: so 30 ngày qua
           với tháng 8 đủ 31 ngày là so một con số với một con số dài hơn nó một
           ngày, và phần trăm sinh ra từ đó không nói lên điều gì. */
        $denTruoc = $tu->modify('-1 day');
        $tuTruoc  = $denTruoc->modify('-' . ($soNgay - 1) . ' days');

        return [
            'ma'          => $ma,
            'nhan'        => self::KY[$ma],
            'tu'          => $tu->format('Y-m-d'),
            'den'         => $den->format('Y-m-d'),
            'soNgay'      => $soNgay,
            'tuTruoc'     => $tuTruoc->format('Y-m-d'),
            'denTruoc'    => $denTruoc->format('Y-m-d'),
            'biKepBoiMoc' => $biKep,
            'biCatNgan'   => $biCat,
            'moc'         => $moc,
        ];
    }

    /** 'YYYY-MM-DD' hợp lệ -> DateTimeImmutable, còn lại -> null. */
    private static function ngay(string $raw): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return null;
        }

        $d = DateTimeImmutable::createFromFormat('Y-m-d', $raw);

        // "2026-02-31" khớp biểu thức trên nhưng không phải một ngày có thật;
        // createFromFormat cuộn nó sang 03/03 và trả về cảnh báo. Bắt ở đây.
        $loi = DateTimeImmutable::getLastErrors();

        if ($d === false || ($loi !== false && ($loi['warning_count'] ?? 0) > 0)) {
            return null;
        }

        return $d->setTime(0, 0);
    }

    // ========================================================================
    // MỆNH ĐỀ LỌC DÙNG CHUNG
    // ========================================================================

    /**
     * Mệnh đề lọc cho bảng `orders` (bí danh `o`).
     *
     * @param  array{tu:string, den:string} $ky
     * @param  string     $coSo   '' = mọi cơ sở · CO_SO_GIAO · id cơ sở
     * @param  string[]|null $phamVi phạm vi quyền của người đăng nhập
     * @return array{0:string, 1:array<string, string>} [mệnh đề WHERE, tham số]
     */
    private static function locDon(
        array $ky,
        string $coSo,
        ?array $phamVi,
        string $tienTo = ''
    ): array {
        /* $tienTo cho phép DÙNG HAI KỲ TRONG MỘT CÂU LỆNH (kỳ này và kỳ liền
           trước gộp làm một lượt quét). Thiếu nó thì hai lần gọi cùng đặt tên
           tham số :tu và bản thứ hai đè lên bản thứ nhất — PDO không báo gì, chỉ
           là phần trăm so sánh luôn ra 0%. */
        $dieuKien = [
            'o.created_at >= :' . $tienTo . 'tu',
            'o.created_at <  :' . $tienTo . 'den',
        ];

        $params = [
            $tienTo . 'tu'  => $ky['tu'] . ' 00:00:00',
            // Cận trên là 00:00:00 của HÔM SAU — xem hetNgay().
            $tienTo . 'den' => self::hetNgay($ky['den']),
        ];

        [$loc, $locParams] = self::locCoSo($coSo, $phamVi, 'o.store_id', $tienTo);

        if ($loc !== null) {
            $dieuKien[] = $loc;
            $params    += $locParams;
        }

        return ['WHERE ' . implode(' AND ', $dieuKien), $params];
    }

    /**
     * Mệnh đề cơ sở dùng chung cho đơn hàng, lịch hẹn và khối cần xử lý.
     *
     * HAI THỨ CÙNG NÓI VỀ CƠ SỞ, ĐỪNG NHẦM — cùng lối phân biệt đã ghi ở đầu
     * StaffStoreModel:
     *   $phamVi  RÀNG BUỘC QUYỀN, máy chủ áp, người dùng không gỡ được.
     *   $coSo    BỘ LỌC, người dùng chọn trên màn hình.
     * Cả hai cùng áp, và ô lọc KHÔNG BAO GIỜ nới được phạm vi.
     *
     * @return array{0:?string, 1:array<string, string>}
     */
    private static function locCoSo(
        string $coSo,
        ?array $phamVi,
        string $cot,
        string $tienTo = ''
    ): array {
        $dieuKien = [];
        $params   = [];

        [$loc, $locParams] = StaffStoreModel::menhDe($phamVi, $cot, $tienTo . 'pv');

        if ($loc !== null) {
            $dieuKien[] = $loc;
            $params    += $locParams;
        }

        if ($coSo === self::CO_SO_GIAO) {
            $dieuKien[] = $cot . ' IS NULL';
        } elseif ($coSo !== '') {
            $dieuKien[]                = $cot . ' = :' . $tienTo . 'co_so';
            $params[$tienTo . 'co_so'] = $coSo;
        }

        return [
            $dieuKien === [] ? null : '(' . implode(' AND ', $dieuKien) . ')',
            $params,
        ];
    }

    // ========================================================================
    // CHỈ SỐ
    // ========================================================================

    /**
     * Doanh thu, tạm thu, số đơn, tỉ lệ huỷ — cho CẢ kỳ đang xem lẫn kỳ liền
     * trước, trong MỘT lượt quét bảng.
     *
     * Hai kỳ đi chung một câu lệnh chứ không hai câu: chúng đọc cùng một bảng
     * với hai khoảng ngày liền kề nhau, nên gộp lại là một lần quét chỉ mục
     * thay vì hai. Trang này là trang mở nhiều nhất khu quản trị.
     *
     * @return array{doanhThu:int, tamThu:int, soDonThu:int, tongDon:int,
     *               donHuy:int, doanhThuTruoc:int, soDonThuTruoc:int}
     */
    public static function tien(array $ky, string $coSo, ?array $phamVi): array
    {
        /*
         * ─────────────────────────────────────────────────────────────────────
         * HAI KỲ TRONG MỘT LƯỢT QUÉT, VÀ MỖI THAM SỐ CHỈ XUẤT HIỆN ĐÚNG MỘT LẦN
         *
         * Kỳ trước dán SÁT ngay trước kỳ này, nên hợp của chúng là một khoảng
         * liền mạch [tuTruoc, den] và chỉ cần MỘT lằn ranh ở giữa để tách:
         * created_at >= :ntu là kỳ này, còn lại là kỳ trước. Nhờ vậy cận trên
         * của kỳ trước không phải là một tham số riêng — nó CHÍNH LÀ :ntu.
         *
         * VÌ SAO PHẢI ĐI QUA BẢNG DẪN XUẤT thay vì lặp lại điều kiện trong từng
         * CASE: dự án chạy PDO với ATTR_EMULATE_PREPARES = false (xem
         * config/database.php). Ở chế độ đó, dùng LẠI một tham số đã đặt tên
         * trong cùng một câu lệnh làm PDO ném SQLSTATE[HY093] "Invalid parameter
         * number" — không phải cảnh báo, mà là lỗi chí mạng ngay khi mở trang.
         * Bảng dẫn xuất tính cờ `ky_nay` MỘT lần cho mỗi dòng, rồi năm biểu thức
         * tổng hợp bên ngoài đọc lại cái cờ ấy.
         *
         * CHỈ ĐƠN CHƯA HUỶ MỚI VÀO DOANH THU — kể cả đơn đã trả tiền rồi mới
         * huỷ, vì tiền ấy phải hoàn lại. Nhưng đơn huỷ VẪN nằm trong mẫu số của
         * tỉ lệ huỷ, nên nó không bị loại ở WHERE mà loại trong từng CASE: hai
         * câu hỏi khác nhau đọc cùng một tập dòng.
         * ─────────────────────────────────────────────────────────────────────
         */
        $congThuc = static fn (string $co, string $ten): string => "
            COALESCE(SUM(CASE WHEN {$co} AND status <> 'cancelled'
                              AND payment_status = 'paid'
                         THEN total END), 0)                          AS {$ten}_doanh_thu,
            COUNT(CASE WHEN {$co} AND status <> 'cancelled'
                       AND payment_status = 'paid' THEN 1 END)        AS {$ten}_so_don_thu,
            COALESCE(SUM(CASE WHEN {$co} AND status <> 'cancelled'
                              AND payment_status = 'deposit_paid'
                         THEN deposit_amount END), 0)                 AS {$ten}_tam_thu,
            COUNT(CASE WHEN {$co} THEN 1 END)                         AS {$ten}_tong_don,
            COUNT(CASE WHEN {$co} AND status = 'cancelled' THEN 1 END) AS {$ten}_don_huy";

        $params = [
            'ttu'  => $ky['tuTruoc'] . ' 00:00:00',
            'ntu'  => $ky['tu'] . ' 00:00:00',
            // Cận trên là 00:00:00 của HÔM SAU — xem hetNgay().
            'nden' => self::hetNgay($ky['den']),
        ];

        [$loc, $locParams] = self::locCoSo($coSo, $phamVi, 'o.store_id');

        $row = Database::fetchOne(
            'SELECT ' . $congThuc('ky_nay = 1', 'nay') . ', '
                      . $congThuc('ky_nay = 0', 'truoc') . '
               FROM (
                   SELECT o.status, o.payment_status, o.total, o.deposit_amount,
                          (o.created_at >= :ntu) AS ky_nay
                     FROM orders o
                    WHERE o.created_at >= :ttu AND o.created_at < :nden'
                    . ($loc === null ? '' : ' AND ' . $loc) . '
               ) AS d',
            $params + $locParams
        ) ?? [];

        return [
            'doanhThu'      => (int) ($row['nay_doanh_thu'] ?? 0),
            'tamThu'        => (int) ($row['nay_tam_thu'] ?? 0),
            'soDonThu'      => (int) ($row['nay_so_don_thu'] ?? 0),
            'tongDon'       => (int) ($row['nay_tong_don'] ?? 0),
            'donHuy'        => (int) ($row['nay_don_huy'] ?? 0),
            'doanhThuTruoc' => (int) ($row['truoc_doanh_thu'] ?? 0),
            'soDonThuTruoc' => (int) ($row['truoc_so_don_thu'] ?? 0),
        ];
    }

    /** Cận trên của một ngày, dạng '00:00:00 của hôm sau' — xem chú thích trên. */
    private static function hetNgay(string $ngay): string
    {
        return (new DateTimeImmutable($ngay))->modify('+1 day')->format('Y-m-d')
            . ' 00:00:00';
    }

    /**
     * Giá vốn của kỳ, kèm ĐỘ PHỦ.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO PHẢI TRẢ VỀ ĐỘ PHỦ, KHÔNG CHỈ TRẢ VỀ MỘT CON SỐ
     *
     * `products.cost_price` cho phép để trống, và phần lớn kho hiện đang trống.
     * Một dòng hàng không có giá vốn thì phần đóng góp của nó vào giá vốn là 0,
     * tức là lợi nhuận gộp CAO HƠN sự thật — sai đúng theo hướng nguy hiểm nhất
     * là luôn đẹp hơn thực tế.
     *
     * Không có cách nào tự đoán ra con số thiếu, nên thứ duy nhất làm được là
     * NÓI RA: "tính trên 12/40 dòng hàng có giá vốn". Người đọc thấy 12/40 sẽ
     * hiểu ngay con số kia chưa dùng được, và biết việc cần làm là đi điền giá
     * vốn — điều mà một con số trần trụi không bao giờ nói được.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * HAI GIỚI HẠN NỮA, ĐÃ CÂN NHẮC VÀ CHẤP NHẬN
     *
     * · Giá vốn là giá vốn HIỆN TẠI, không phải giá vốn lúc bán. Sửa giá vốn
     *   hôm nay thì lợi nhuận của tháng trước đổi theo. Cách sửa triệt để là
     *   chép giá vốn vào `order_items` lúc đặt hàng, y như `product_name` và
     *   `unit_price` đã làm — nhưng nó đụng vào OrderModel::place(), tức là đụng
     *   vào luồng mua hàng đang chạy thật, nên để thành một việc riêng.
     * · Tiền TRÒNG nằm trong `unit_price` (xem chú thích cột `lens_price` ở
     *   schema) nhưng tròng KHÔNG có giá vốn ở đâu cả. Phần doanh thu ấy vào
     *   lợi nhuận gộp mà không bị trừ đồng nào.
     *
     * Cả hai đều được nói ra trên màn hình, không giấu trong mã.
     *
     * @return array{giaVon:int, soDong:int, soDongCoVon:int}
     */
    public static function giaVon(array $ky, string $coSo, ?array $phamVi): array
    {
        [$loc, $params] = self::locDon($ky, $coSo, $phamVi);

        /* LEFT JOIN chứ không JOIN: `order_items.product_id` là SET NULL, nên
           sản phẩm bị xoá khỏi danh mục để lại dòng hàng mồ côi. JOIN thường
           vứt luôn dòng đó khỏi mẫu số và độ phủ hiện ra đẹp hơn sự thật. */
        $row = Database::fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN p.cost_price IS NOT NULL
                             THEN p.cost_price * oi.quantity END), 0)   AS gia_von,
                COUNT(*)                                                AS so_dong,
                COUNT(CASE WHEN p.cost_price IS NOT NULL THEN 1 END)    AS so_dong_co_von
               FROM order_items oi
               JOIN orders o        ON o.id = oi.order_id
               LEFT JOIN products p ON p.id = oi.product_id
             {$loc}
               AND o.status <> 'cancelled'
               AND o.payment_status = 'paid'",
            $params
        ) ?? [];

        return [
            'giaVon'      => (int) ($row['gia_von'] ?? 0),
            'soDong'      => (int) ($row['so_dong'] ?? 0),
            'soDongCoVon' => (int) ($row['so_dong_co_von'] ?? 0),
        ];
    }

    /**
     * Lịch hẹn của kỳ — tổng, đã chốt kết quả, và số khách không đến.
     *
     * MẪU SỐ CỦA TỈ LỆ KHÔNG ĐẾN LÀ "BUỔI ĐÃ CHỐT KẾT QUẢ", tức
     * done + no_show. Không phải tổng số lịch, vì:
     *   · lịch ĐÃ HUỶ không phải một cơ hội để khách không đến — khách đã báo
     *     trước, và cộng nó vào mẫu số làm tỉ lệ tụt xuống một cách vô nghĩa;
     *   · lịch còn 'pending'/'confirmed' của những ngày đã qua là việc NHÂN VIÊN
     *     chưa chốt, không phải việc khách. Đưa vào mẫu số thì tỉ lệ không đến
     *     phụ thuộc vào việc ai đó có bấm nút hay không.
     *
     * Số lịch quá hạn chưa chốt vẫn trả về riêng, và màn hình nói ra nó — vì nó
     * chính là thứ làm tỉ lệ này chưa đáng tin.
     *
     * @return array{tong:int, daChot:int, khongDen:int, quaHanChuaChot:int}
     */
    public static function lichHen(array $ky, string $coSo, ?array $phamVi): array
    {
        /* Cơ sở "Giao tận nơi" không có lịch hẹn nào: `appointments.store_id`
           là NOT NULL. Trả về một mảng rỗng thay vì chạy một câu lệnh chắc chắn
           cho 0 — và view dùng chính con số 0 tổng này để in "không áp dụng"
           thay vì "0%". */
        if ($coSo === self::CO_SO_GIAO) {
            return ['tong' => 0, 'daChot' => 0, 'khongDen' => 0, 'quaHanChuaChot' => 0];
        }

        $dieuKien = ['a.appointment_date >= :tu', 'a.appointment_date <= :den'];
        $params   = ['tu' => $ky['tu'], 'den' => $ky['den']];

        [$loc, $locParams] = self::locCoSo($coSo, $phamVi, 'a.store_id');

        if ($loc !== null) {
            $dieuKien[] = $loc;
            $params    += $locParams;
        }

        $params['hom_nay'] = (new DateTimeImmutable('today'))->format('Y-m-d');

        $row = Database::fetchOne(
            "SELECT
                COUNT(*)                                                   AS tong,
                COUNT(CASE WHEN a.status IN ('done', 'no_show') THEN 1 END) AS da_chot,
                COUNT(CASE WHEN a.status = 'no_show' THEN 1 END)            AS khong_den,
                COUNT(CASE WHEN a.status IN ('pending', 'confirmed')
                            AND a.appointment_date < :hom_nay THEN 1 END)   AS qua_han
               FROM appointments a
              WHERE " . implode(' AND ', $dieuKien),
            $params
        ) ?? [];

        return [
            'tong'           => (int) ($row['tong'] ?? 0),
            'daChot'         => (int) ($row['da_chot'] ?? 0),
            'khongDen'       => (int) ($row['khong_den'] ?? 0),
            'quaHanChuaChot' => (int) ($row['qua_han'] ?? 0),
        ];
    }

    /**
     * Top 5 sản phẩm theo DOANH THU trong kỳ, kèm số lượng bán.
     *
     * Xếp theo tiền chứ không theo số lượng — hai bảng xếp hạng ấy khác hẳn nhau
     * ở một cửa hàng kính: khăn lau và nước rửa kính luôn đứng đầu về số lượng
     * mà không đóng góp gì đáng kể vào doanh thu.
     *
     * @return list<array{id:?string, ten:string, doanhThu:int, soLuong:int}>
     */
    public static function topSanPham(array $ky, string $coSo, ?array $phamVi): array
    {
        [$loc, $params] = self::locDon($ky, $coSo, $phamVi);

        /* GOM THEO ID, nhưng dòng mồ côi (product_id NULL, sản phẩm đã bị xoá
           khỏi danh mục) thì gom theo TÊN. Gom cả đống mồ côi vào một dòng thì
           màn hình hiện "Sản phẩm đã xoá — 12.400.000₫" ghép từ năm mặt hàng
           khác nhau, một con số không trả lời được câu hỏi nào.

           Biểu thức CASE là hằng '' với mọi dòng còn sản phẩm, nên nó không chia
           nhỏ nhóm nào khác — kể cả khi mặt hàng đã đổi tên sau lúc bán. */
        return array_map(
            static fn (array $r): array => [
                'id'       => $r['product_id'],
                'ten'      => (string) $r['ten'],
                'doanhThu' => (int) $r['doanh_thu'],
                'soLuong'  => (int) $r['so_luong'],
            ],
            Database::fetchAll(
                "SELECT oi.product_id,
                        MAX(oi.product_name)  AS ten,
                        SUM(oi.line_total)    AS doanh_thu,
                        SUM(oi.quantity)      AS so_luong
                   FROM order_items oi
                   JOIN orders o ON o.id = oi.order_id
                 {$loc}
                   AND o.status <> 'cancelled'
                   AND o.payment_status = 'paid'
                  GROUP BY oi.product_id,
                           CASE WHEN oi.product_id IS NULL THEN oi.product_name ELSE '' END
                  ORDER BY doanh_thu DESC
                  LIMIT 5",
                $params
            )
        );
    }

    /**
     * Doanh thu từng cột của biểu đồ.
     *
     * MỘT CÂU LỆNH GOM THEO NGÀY, rồi PHP điền các ngày trống và gộp thành tuần
     * nếu kỳ quá dài. Không gom theo tuần ở SQL: YEARWEEK() có năm chế độ đầu
     * tuần khác nhau và chế độ mặc định đổi theo cấu hình máy chủ, nên cùng một
     * dữ liệu cho ra hai biểu đồ khác nhau trên máy dev và trên hosting.
     *
     * NGÀY KHÔNG CÓ ĐƠN NÀO VẪN PHẢI CÓ MỘT CỘT CAO 0. Bỏ nó đi thì trục hoành
     * co lại và một tuần nghỉ Tết trông y hệt một tuần bán đều.
     *
     * @return array{cot:list<array{nhan:string, day:string, tien:int}>, theoTuan:bool}
     */
    public static function theoNgay(array $ky, string $coSo, ?array $phamVi): array
    {
        [$loc, $params] = self::locDon($ky, $coSo, $phamVi);

        $theoNgay = [];

        foreach (Database::fetchAll(
            "SELECT DATE(o.created_at) AS ngay,
                    COALESCE(SUM(CASE WHEN o.payment_status = 'paid'
                                 THEN o.total END), 0) AS tien
               FROM orders o
             {$loc}
               AND o.status <> 'cancelled'
              GROUP BY DATE(o.created_at)",
            $params
        ) as $row) {
            $theoNgay[(string) $row['ngay']] = (int) $row['tien'];
        }

        $tu       = new DateTimeImmutable($ky['tu']);
        $den      = new DateTimeImmutable($ky['den']);
        $theoTuan = $ky['soNgay'] > self::NGAY_TOI_DA_THEO_NGAY;

        $cot   = [];
        $ngay  = $tu;
        $moc   = null;
        $gop   = 0;
        $dauKy = null;

        while ($ngay <= $den) {
            $khoa = $ngay->format('Y-m-d');
            $tien = $theoNgay[$khoa] ?? 0;

            if (!$theoTuan) {
                $cot[] = [
                    'nhan' => $ngay->format('d/m'),
                    'day'  => $khoa,
                    'tien' => $tien,
                ];
            } else {
                // Gộp bảy ngày một cột, đếm từ ĐẦU KỲ chứ không theo thứ trong
                // tuần: cột đầu và cột cuối khi ấy luôn đủ bảy ngày như nhau.
                if ($dauKy === null) {
                    $dauKy = $ngay;
                    $moc   = $ngay;
                    $gop   = 0;
                }

                $gop += $tien;

                if ((int) $dauKy->diff($ngay)->days % 7 === 6 || $ngay == $den) {
                    $cot[] = [
                        'nhan' => $moc->format('d/m'),
                        'day'  => $moc->format('Y-m-d') . '…' . $khoa,
                        'tien' => $gop,
                    ];
                    $dauKy = $ngay->modify('+1 day');
                    $moc   = $dauKy;
                    $gop   = 0;
                }
            }

            $ngay = $ngay->modify('+1 day');
        }

        return ['cot' => $cot, 'theoTuan' => $theoTuan];
    }

    // ========================================================================
    // CẦN XỬ LÝ GẤP
    // ========================================================================

    /**
     * Bốn hàng đợi đang tồn đọng — KHÔNG theo kỳ đang xem.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO KHỐI NÀY KHÔNG ĂN THEO BỘ LỌC KỲ
     *
     * "Đơn Mới quá 24 giờ" là câu hỏi về HIỆN TẠI: đơn nào đang để khách chờ.
     * Cho nó chạy theo kỳ thì chọn "Hôm nay" là khối rỗng — trong khi 32 đơn cũ
     * vẫn nằm đó, và chúng mới đúng là thứ cần xử lý gấp. Một khối tên "cần xử
     * lý gấp" mà giấu bớt việc theo bộ lọc thì tên ấy là nói dối.
     *
     * BỘ LỌC CƠ SỞ THÌ VẪN ĂN, với hai hàng đợi có cột cơ sở (đơn và lịch).
     * Sản phẩm và yêu cầu liên hệ không thuộc cơ sở nào — kho là một, và
     * `contact_requests` không có cột cơ sở — nên hai hàng ấy luôn là toàn hệ
     * thống. Màn hình phải nói ra điều đó, nếu không người chọn một cơ sở sẽ
     * tưởng bốn hàng đều đã lọc.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array<string, array{tong:int, dong:list<array<string,mixed>>}>
     */
    public static function canXuLy(string $coSo, ?array $phamVi): array
    {
        $nguong = (new DateTimeImmutable())
            ->modify('-' . self::NGUONG_TON_DONG_GIO . ' hours')
            ->format('Y-m-d H:i:s');
        $homNay = (new DateTimeImmutable('today'))->format('Y-m-d');
        $gioiHan = self::CAN_XU_LY_TOI_DA;

        // ── Đơn ở trạng thái Mới quá ngưỡng ────────────────────────────────
        [$locDon, $paramsDon] = self::locCoSo($coSo, $phamVi, 'o.store_id', 'd');
        $whereDon = "o.status = 'new' AND o.created_at < :nguong"
            . ($locDon === null ? '' : ' AND ' . $locDon);

        $donMoi = [
            'tong' => (int) Database::fetchValue(
                "SELECT COUNT(*) FROM orders o WHERE {$whereDon}",
                ['nguong' => $nguong] + $paramsDon
            ),
            'dong' => Database::fetchAll(
                "SELECT o.id, o.code, o.customer_name, o.total, o.created_at
                   FROM orders o
                  WHERE {$whereDon}
                  ORDER BY o.created_at ASC
                  LIMIT {$gioiHan}",
                ['nguong' => $nguong] + $paramsDon
            ),
        ];

        // ── Lịch hẹn đã qua ngày mà vẫn Chờ xác nhận ───────────────────────
        if ($coSo === self::CO_SO_GIAO) {
            // Không có lịch hẹn nào không thuộc cơ sở — xem lichHen().
            $lichQuaHan = ['tong' => 0, 'dong' => []];
        } else {
            [$locLich, $paramsLich] = self::locCoSo($coSo, $phamVi, 'a.store_id', 'l');
            $whereLich = "a.status = 'pending' AND a.appointment_date < :hom_nay"
                . ($locLich === null ? '' : ' AND ' . $locLich);

            $lichQuaHan = [
                'tong' => (int) Database::fetchValue(
                    "SELECT COUNT(*) FROM appointments a WHERE {$whereLich}",
                    ['hom_nay' => $homNay] + $paramsLich
                ),
                'dong' => Database::fetchAll(
                    "SELECT a.id, a.code, a.full_name, a.appointment_date, s.name AS store_name
                       FROM appointments a
                       JOIN stores s ON s.id = a.store_id
                      WHERE {$whereLich}
                      ORDER BY a.appointment_date ASC
                      LIMIT {$gioiHan}",
                    ['hom_nay' => $homNay] + $paramsLich
                ),
            ];
        }

        /*
         * ── Hết hàng mà vẫn bày bán ────────────────────────────────────────
         *
         * `allow_backorder = 1` KHÔNG phải một lỗi cần sửa: đó là mặt hàng cửa
         * hàng cố ý cho đặt trước khi hết kho (gọng nhập theo lô, khách chờ được).
         * Xếp nó vào "cần xử lý gấp" là dạy người dùng bỏ qua cả khối.
         */
        $hetHang = [
            'tong' => (int) Database::fetchValue(
                'SELECT COUNT(*) FROM products
                  WHERE stock_quantity <= 0 AND is_visible = 1 AND allow_backorder = 0'
            ),
            'dong' => Database::fetchAll(
                "SELECT id, name, sku, stock_quantity
                   FROM products
                  WHERE stock_quantity <= 0 AND is_visible = 1 AND allow_backorder = 0
                  ORDER BY name ASC
                  LIMIT {$gioiHan}"
            ),
        ];

        /*
         * ── Yêu cầu liên hệ chưa tới được Zalo CSKH ────────────────────────
         *
         * ⚠ ĐÂY KHÔNG PHẢI "CHƯA XỬ LÝ" THEO NGHĨA CŨ. Cột `status` của
         * `contact_requests` (Mới -> Đang xử lý -> Đã xử lý) đã bị bỏ ngày
         * 2026-08-26 cùng migration 2026-08-27-bo-cot-status-lien-he.sql: yêu
         * cầu nay chạy thẳng sang Zalo CSKH lúc khách bấm gửi, không còn hàng
         * chờ nào trong bảng quản trị để "xử lý".
         *
         * Thứ còn lại — và là thứ đáng lo hơn hẳn — là yêu cầu KHÔNG ĐẨY ĐƯỢC
         * sang Zalo. Nó phải luôn bằng 0; khác 0 nghĩa là ZNS đang hỏng và có
         * người thật đang chờ gọi lại mà CSKH chưa biết. Quá 24 giờ thì gần như
         * chắc chắn không phải trục trặc mạng nhất thời.
         *
         * Cột này chỉ có từ migration 2026-08-26 nên phải hỏi trước khi đọc —
         * cùng lối phòng thủ đã có ở DashboardController bản cũ.
         */
        if (Database::columnExists('contact_requests', 'zalo_sent_at')) {
            $lienHe = [
                'tong' => (int) Database::fetchValue(
                    'SELECT COUNT(*) FROM contact_requests
                      WHERE zalo_sent_at IS NULL AND created_at < :nguong',
                    ['nguong' => $nguong]
                ),
                'dong' => Database::fetchAll(
                    "SELECT id, full_name, phone, created_at
                       FROM contact_requests
                      WHERE zalo_sent_at IS NULL AND created_at < :nguong
                      ORDER BY created_at ASC
                      LIMIT {$gioiHan}",
                    ['nguong' => $nguong]
                ),
            ];
        } else {
            $lienHe = ['tong' => 0, 'dong' => [], 'chuaNangCap' => true];
        }

        return [
            'donMoi'     => $donMoi,
            'lichQuaHan' => $lichQuaHan,
            'hetHang'    => $hetHang,
            'lienHe'     => $lienHe,
        ];
    }

    // ========================================================================
    // TIỆN ÍCH
    // ========================================================================

    /**
     * Phần trăm thay đổi so với kỳ trước, hoặc null khi KHÔNG SO ĐƯỢC.
     *
     * Kỳ trước bằng 0 thì trả null chứ không trả +100% hay +∞: chia cho 0 không
     * có kết quả, và in ra "+100%" ở đó là bịa một con số rồi trình bày như thể
     * nó đo được cái gì. View in dấu "—" kèm lời giải thích.
     */
    public static function phanTram(int $nay, int $truoc): ?float
    {
        if ($truoc === 0) {
            return null;
        }

        return (($nay - $truoc) / $truoc) * 100;
    }

    /**
     * Cơ sở người này CHỌN ĐƯỢC trong ô lọc.
     *
     * Liệt kê đủ mọi cơ sở rồi chặn ở truy vấn cũng an toàn, nhưng nó mời người
     * ta chọn một thứ luôn trả về rỗng — và họ sẽ báo đó là lỗi. Cùng cách làm
     * với ô lọc cơ sở ở màn Lịch hẹn.
     *
     * @return list<array{id:string, name:string}>
     */
    public static function coSoChonDuoc(?array $phamVi): array
    {
        if ($phamVi === []) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT id, name FROM stores WHERE is_active = 1 ORDER BY name ASC'
        );

        if ($phamVi === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $s): bool => in_array((string) $s['id'], $phamVi, true)
        ));
    }
}
