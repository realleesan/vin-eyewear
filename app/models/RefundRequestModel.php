<?php

/**
 * app/models/RefundRequestModel.php — yêu cầu hoàn tiền cọc.
 *
 * SRS v2.1.0, UC-04 · FR-DH-14.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ SỔ QUYẾT ĐỊNH, KHÔNG PHẢI CỔNG THANH TOÁN — BR-DH-14.5
 *
 * Lớp này tính ra số tiền phải hoàn, lưu quyết định của người duyệt, và ghi
 * nhận thời điểm tiền đã trả lại khách. Nó KHÔNG chuyển tiền và không có đường
 * nào chuyển được: việc ấy làm ở ngân hàng, rồi Quản trị viên quay lại bấm
 * "Đã hoàn tiền".
 *
 * Đừng thêm cột số tài khoản hay móc nối cổng thanh toán vào đây. Một cột như
 * thế sẽ khiến người đọc tin rằng hệ thống tự chuyển được, và khi đó không ai
 * đi chuyển tay nữa.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CÔNG THỨC — BR-DH-14.1
 *
 *   Huỷ TRƯỚC khi bấm mốc bắt đầu mài  →  hoàn TOÀN BỘ số tiền đã nhận.
 *   Huỷ SAU khi bấm mốc                →  cửa hàng giữ đúng phần tiền tròng,
 *                                          hoàn phần còn dư:
 *
 *       tiền hoàn = tiền ĐÃ NHẬN của đơn − tiền tròng của đơn   (chặn sàn ở 0)
 *
 * Ví dụ BR-DH-14.2: đơn 4.400.000đ gồm gọng 3.600.000đ và tròng 800.000đ, cọc
 * 30% là 1.320.000đ, khách mới chuyển đúng phần cọc. Huỷ trước mốc mài hoàn
 * 1.320.000đ; huỷ sau mốc mài hoàn 1.320.000 − 800.000 = 520.000đ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * "ĐÃ NHẬN" KHÔNG PHẢI LÀ `orders`.`deposit_amount` — ĐỌC KỸ CHỖ NÀY
 *
 * `orders`.`deposit_amount` là số tiền cọc PHẢI trả, chốt ngay lúc đặt đơn
 * (30% của tổng). Nó KHÔNG nói tiền đã về hay chưa, và cũng không nói về bao
 * nhiêu. Cột trả lời việc đó là `payment_status`:
 *
 *   'deposit_paid'  cửa hàng đang giữ  deposit_amount
 *   'paid'          cửa hàng đang giữ  total          ← chỗ dễ sai nhất
 *
 * Khách chuyển khoản thường chuyển ĐỦ MỘT LẦN cho tiện, và SePay đối soát thấy
 * đủ tổng thì đẩy thẳng đơn sang 'paid' (xem SepayModel). Nếu ở đây cứ đọc
 * `deposit_amount` thì một đơn 4.400.000đ đã trả đủ, khi huỷ, sẽ được ghi sổ là
 * "cửa hàng nợ khách 1.320.000đ" — và 3.080.000đ còn lại không nằm ở đâu trong
 * hệ thống cả. Không ai phát hiện ra cho tới khi khách gọi.
 *
 * Vì thế cột của bảng này tên là `received_amount`, không phải `deposit_amount`.
 * Tên khác nhau là cố ý: nó là hàng rào duy nhất ngăn người sửa sau này chép
 * nhầm một lần nữa.
 *
 * LỖI TỪ PHÍA CỬA HÀNG thì hoàn 100% số đã nhận bất kể đã mài hay chưa
 * (BR-DH-14.3) — nhưng đó là lựa chọn của người duyệt, không phải thứ hệ thống
 * tự đoán, nên nó nằm ở duyet() chứ không ở công thức.
 *
 * TIỀN TRÒNG LỚN HƠN TIỀN ĐÃ NHẬN thì hoàn 0, và hệ thống KHÔNG đòi khách trả
 * thêm (E1 của UC-04). Cửa hàng chịu phần chênh — đó là quyết định kinh doanh
 * đã chốt, không phải sơ suất của công thức.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CON SỐ ĐỀU CHÉP LẠI, KHÔNG ĐỌC LẠI
 *
 * `received_amount`, `lens_amount`, `suggested_amount` và cả cờ `lens_started`
 * đều chụp tại thời điểm tạo yêu cầu. Giá tròng sửa được trong khu quản trị và
 * mốc mài gỡ được, nên một yêu cầu duyệt tháng trước phải giải thích được bằng
 * con số của tháng trước. Cùng lý lẽ với `order_items.unit_price`.
 */

class RefundRequestModel extends BaseModel
{
    protected static string $table = 'refund_requests';

    /**
     * Vòng đời. Tách 'approved' khỏi 'refunded' là CỐ Ý.
     *
     * Duyệt là quyết định trong hệ thống; chuyển tiền là việc làm ở ngân hàng.
     * Hai thời điểm khác nhau, và khoảng giữa hai mốc đó chính là lúc khách
     * đang chờ tiền — gộp làm một thì không ai trả lời được câu "đã duyệt
     * nhưng chưa chuyển thì có mấy đơn".
     */
    public const STATUSES = [
        'pending'  => 'Chờ duyệt',
        'approved' => 'Đã duyệt, chờ chuyển tiền',
        'refunded' => 'Đã hoàn tiền',
        'rejected' => 'Từ chối hoàn',
    ];

    /** Lý do bắt buộc dài tối thiểu bao nhiêu ký tự. Cùng mức với OrderModel. */
    public const LY_DO_TOI_THIEU = 10;

    /**
     * CSDL đã có bảng chưa.
     *
     * Cùng lối phòng thủ với AuditLogModel::available() và SepayModel: một máy
     * chủ chưa chạy migration không được phép biến thành trang lỗi. Ở đây hậu
     * quả của việc thiếu bảng là mất một yêu cầu hoàn tiền — đáng tiếc nhưng
     * còn hơn chặn cả thao tác huỷ đơn của khách.
     */
    public static function available(): bool
    {
        return Database::tableExists('refund_requests');
    }

    /**
     * Số tiền cửa hàng ĐANG GIỮ của một đơn.
     *
     * Đọc `payment_status` chứ không đọc `deposit_amount` — lý do đầy đủ ở
     * khối "ĐÃ NHẬN KHÔNG PHẢI LÀ deposit_amount" đầu file. Tóm tắt:
     * 'deposit_paid' là đang giữ phần cọc, 'paid' là đang giữ cả tổng đơn.
     *
     * Mọi trạng thái tiền khác trả 0: chưa nhận đồng nào thì không có gì để
     * hoàn.
     */
    public static function daNhan(array $don): int
    {
        return match ((string) ($don['payment_status'] ?? 'unpaid')) {
            'paid'         => (int) ($don['total'] ?? 0),
            'deposit_paid' => (int) ($don['deposit_amount'] ?? 0),
            default        => 0,
        };
    }

    /**
     * Số tiền đề nghị hoàn cho một đơn — BR-DH-14.1.
     *
     * Nhận sẵn bản ghi đơn để khỏi đọc lại; nơi gọi luôn vừa đọc nó xong.
     *
     * @return array{received:int, lens:int, suggested:int, lens_started:bool}
     */
    public static function tinh(array $don): array
    {
        $daNhan = self::daNhan($don);

        /* ĐÃ BẤM MỐC MÀI CHƯA — cột `mai_bat_dau_luc`.

           Đây là thứ quyết định công thức đi nhánh nào, nên đọc thẳng cột chứ
           không hỏi qua OrderModel::daBatDauMai(): hàm đó nhận bản ghi đơn và
           trả bool, nhưng ở đây ta cần chính giá trị thô để chép vào cột
           `lens_started` của yêu cầu. */
        $daMai = trim((string) ($don['mai_bat_dau_luc'] ?? '')) !== '';

        $tienTrong = $daMai ? self::tienTrong((string) $don['id']) : 0;

        return [
            'received'     => $daNhan,
            'lens'         => $tienTrong,
            // max(0, …) là mệnh đề "chặn sàn ở 0" của BR-DH-14.1, và cũng là
            // E1: tiền tròng ≥ tiền đã nhận thì hoàn 0, không đòi khách trả thêm.
            'suggested'    => max(0, $daNhan - $tienTrong),
            'lens_started' => $daMai,
        ];
    }

    /**
     * Tổng tiền TRÒNG của một đơn.
     *
     * Đọc từ `order_items` chứ không tính lại từ bảng giá: bảng giá sửa được
     * hằng tháng, còn dòng hàng đã chép giá tại thời điểm đặt. Một đơn từ tháng
     * trước phải hoàn theo giá tháng trước.
     *
     * `lens_price` là phần tròng của dòng hàng; dòng chỉ mua gọng để NULL.
     */
    private static function tienTrong(string $orderId): int
    {
        return (int) Database::fetchValue(
            'SELECT COALESCE(SUM(lens_price * quantity), 0)
               FROM order_items
              WHERE order_id = :id AND lens_id IS NOT NULL AND lens_id <> ""',
            ['id' => $orderId]
        );
    }

    /**
     * Tạo yêu cầu hoàn tiền cho một đơn vừa bị huỷ.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * KHÔNG NÉM, KHÔNG CHẶN — BR-HS-13.4 và FR-EM-06 cùng một tinh thần
     *
     * Hàm này chạy NGAY SAU khi đơn đã chuyển sang Đã huỷ và hàng đã về kho.
     * Việc chính đã xong. Nếu tạo yêu cầu hoàn tiền hỏng — bảng chưa có, CSDL
     * trục trặc — thì việc đúng là ghi log và đi tiếp, không phải cuộn ngược
     * một thao tác huỷ mà khách đã thấy thành công.
     *
     * Cái giá: một đơn có cọc bị huỷ mà không có yêu cầu hoàn tiền. Màn quản
     * trị có câu đối chiếu tìm đúng những đơn như thế (xem donCocChuaCoYeuCau).
     *
     * BỎ QUA ĐƠN CHƯA NHẬN ĐỒNG NÀO. Điều kiện trước của UC-04 là "đơn có tiền
     * đã được ghi nhận là đã nhận" — chưa nhận tiền thì không có gì để hoàn, và
     * tạo một yêu cầu 0đ chỉ làm hàng chờ duyệt đầy những dòng không ai phải
     * làm gì.
     *
     * Ngưỡng là daNhan() chứ KHÔNG phải `deposit_amount > 0`: đơn chỉ mua gọng
     * để `deposit_amount` = 0 (gọng không cần cọc), nhưng nếu khách đã chuyển
     * khoản đủ tiền thì cửa hàng vẫn đang giữ trọn số tiền ấy và vẫn phải hoàn.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return string|null id yêu cầu vừa tạo, hoặc null nếu không tạo
     */
    public static function taoChoDonHuy(array $don): ?string
    {
        if (!self::available()) {
            error_log('RefundRequestModel: chưa có bảng refund_requests, bỏ qua đơn '
                . (string) ($don['code'] ?? $don['id'] ?? '?'));

            return null;
        }

        if (self::daNhan($don) <= 0) {
            return null;
        }

        $orderId = (string) $don['id'];

        // Khoá duy nhất trên `order_id` đã chặn ở tầng CSDL; hỏi trước ở đây để
        // trả về êm thay vì ném một ngoại lệ trùng khoá vào giữa luồng huỷ đơn.
        if (self::exists(['order_id' => $orderId])) {
            return null;
        }

        $so = self::tinh($don);
        $id = uuid();

        try {
            Database::execute(
                'INSERT INTO refund_requests
                    (id, order_id, received_amount, lens_amount, suggested_amount,
                     lens_started, status)
                 VALUES
                    (:id, :order_id, :coc, :trong, :de_nghi, :da_mai, :tt)',
                [
                    'id'       => $id,
                    'order_id' => $orderId,
                    'coc'      => $so['received'],
                    'trong'    => $so['lens'],
                    'de_nghi'  => $so['suggested'],
                    'da_mai'   => $so['lens_started'] ? 1 : 0,
                    'tt'       => 'pending',
                ]
            );
        } catch (Throwable $e) {
            error_log('RefundRequestModel::taoChoDonHuy: ' . $e->getMessage());

            return null;
        }

        return $id;
    }

    /**
     * Một yêu cầu kèm thông tin đơn và khách — cho màn duyệt.
     */
    public static function chiTiet(string $id): ?array
    {
        if (!self::available()) {
            return null;
        }

        return Database::fetchOne(
            /* `o.user_id` để nhật ký thao tác gắn được vết vào ĐÚNG KHÁCH.

               AuditLogModel join `profiles` theo `user_id`, và màn Lịch sử
               thao tác dựng liên kết tới hồ sơ khách từ chính cột đó. Ghi vết
               chi tiền với user_id NULL nghĩa là ba dòng quan trọng nhất về
               tiền của một khách lại là ba dòng duy nhất không tra ngược được
               về họ. NULL với đơn của khách vãng lai — đúng, họ không có hồ sơ. */
            'SELECT r.*, o.code AS order_code, o.total AS order_total,
                    o.user_id AS order_user_id,
                    o.customer_name, o.customer_phone, o.cancelled_by,
                    p.full_name AS decided_by_name
               FROM refund_requests r
               JOIN orders o ON o.id = r.order_id
               LEFT JOIN profiles p ON p.id = r.decided_by
              WHERE r.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Danh sách cho màn quản trị, lọc theo trạng thái.
     *
     * @param string $status '' = mọi trạng thái
     */
    public static function danhSach(string $status = '', int $limit = 100): array
    {
        if (!self::available()) {
            return [];
        }

        $where  = '';
        $params = [];

        if ($status !== '' && isset(self::STATUSES[$status])) {
            $where            = ' WHERE r.status = :tt';
            $params['tt']     = $status;
        }

        /* SẮP CHỜ DUYỆT LÊN ĐẦU rồi mới tới thời gian.

           Đây là hàng chờ có người đang đợi tiền ở đầu bên kia, nên thứ tự
           đúng là "việc phải làm trước, việc đã xong sau" — không phải thứ tự
           thời gian thuần, vốn đẩy những yêu cầu cũ chưa duyệt xuống dưới đống
           yêu cầu mới đã hoàn. */
        return Database::fetchAll(
            "SELECT r.*, o.code AS order_code, o.customer_name, o.customer_phone
               FROM refund_requests r
               JOIN orders o ON o.id = r.order_id"
            . $where
            . " ORDER BY FIELD(r.status, 'pending', 'approved', 'refunded', 'rejected'),
                        r.created_at DESC
               LIMIT " . max(1, $limit),
            $params
        );
    }

    /** Số yêu cầu theo từng trạng thái, cho dải viên lọc và huy hiệu thanh bên. */
    public static function demTheoTrangThai(): array
    {
        $dem = ['' => 0];

        foreach (array_keys(self::STATUSES) as $k) {
            $dem[$k] = 0;
        }

        if (!self::available()) {
            return $dem;
        }

        foreach (Database::fetchAll(
            'SELECT status, COUNT(*) AS n FROM refund_requests GROUP BY status'
        ) as $dong) {
            if (isset($dem[$dong['status']])) {
                $dem[$dong['status']] = (int) $dong['n'];
            }

            $dem[''] += (int) $dong['n'];
        }

        return $dem;
    }

    /**
     * Duyệt một yêu cầu — BR-DH-14.4, chỉ Quản trị viên (chặn ở controller).
     *
     * @param bool   $loiCuaHang tích ô "lỗi cửa hàng" (A1) → hoàn 100% cọc
     * @param string $soTien     '' = duyệt theo số đề nghị
     */
    public static function duyet(
        string $id,
        string $actorId,
        bool $loiCuaHang,
        string $soTien,
        string $lyDo
    ): array {
        $yc = self::chiTiet($id);

        if ($yc === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy yêu cầu hoàn tiền.'];
        }

        if ($yc['status'] !== 'pending') {
            return ['ok' => false, 'error' =>
                'Yêu cầu này đã được xử lý (' . (self::STATUSES[$yc['status']] ?? $yc['status']) . ').'];
        }

        $deNghi = (int) $yc['suggested_amount'];
        $daNhan = (int) $yc['received_amount'];

        /* LỖI CỬA HÀNG PHỦ QUYẾT MỌI THỨ — BR-DH-14.3.

           Đặt TRƯỚC phần đọc số nhập tay: tích ô này nghĩa là hoàn toàn bộ số
           đã nhận, và nếu người duyệt vừa tích ô vừa gõ một số khác thì con số
           đúng là toàn bộ số đã nhận. Đọc ngược thứ tự thì ô tích thành trang
           trí. */
        if ($loiCuaHang) {
            $chot = $daNhan;
        } else {
            $chot = self::docSoTien($soTien, $deNghi);

            /* TỪ CHỐI SỐ KHÔNG ĐỌC ĐƯỢC, KHÔNG ÉP VỀ 0.

               Trước đây chỗ này là (int) preg_replace('/\D+/', '', $soTien),
               và nó nuốt mọi thứ: "-500" thành 500, còn "abc" hay một cú chạm
               nhầm phím thành 0. Với một yêu cầu mà số đề nghị vốn đã là 0
               (tiền tròng ≥ tiền đã nhận — ca E1), số 0 vô tình ấy BẰNG số đề
               nghị, nên phép đòi lý do ở dưới cũng không chặn: yêu cầu được
               duyệt 0đ, im lặng, không một dòng giải thích.

               Gõ sai thì phải nghe nói là gõ sai. */
            if ($chot === null) {
                return ['ok' => false, 'error' =>
                    'Số tiền hoàn phải là một con số. Ví dụ: 520000 hoặc 520.000.'];
            }
        }

        if ($chot < 0 || $chot > $daNhan) {
            return ['ok' => false, 'error' =>
                'Số tiền hoàn phải nằm trong khoảng 0 đến ' . money($daNhan)
                . ' (số tiền cửa hàng đã nhận của đơn này).'];
        }

        $lyDo = trim($lyDo);

        /* LỆCH SỐ ĐỀ NGHỊ THÌ BẮT BUỘC GHI LÝ DO — BR-DH-14.6.

           Không phải để làm khó: hệ thống đã tính ra một con số theo chính sách
           đã chốt, nên một con số KHÁC là một quyết định của con người, và sáu
           tháng sau người đọc sổ cần biết vì sao. Tích ô "lỗi cửa hàng" cũng là
           lệch, nhưng nó tự giải thích nên không đòi thêm chữ. */
        if (!$loiCuaHang && $chot !== $deNghi && utf8Length($lyDo) < self::LY_DO_TOI_THIEU) {
            return ['ok' => false, 'error' =>
                'Duyệt số khác số đề nghị thì phải ghi lý do, tối thiểu '
                . self::LY_DO_TOI_THIEU . ' ký tự.'];
        }

        /* ĐẾM DÒNG ĐÃ GHI, KHÔNG TIN PHÉP KIỂM Ở TRÊN.

           Giữa lúc chiTiet() đọc và lúc câu này chạy, một quản trị viên khác
           có thể vừa duyệt xong chính yêu cầu đó với một con số khác. Mệnh đề
           AND status = 'pending' làm câu lệnh khớp 0 dòng — đúng — nhưng nếu
           cứ trả về ok thì nơi gọi sẽ ghi nhật ký "Duyệt hoàn <số của tôi>" và
           báo thành công, trong khi sổ đang giữ <số của người kia>. Người đọc
           nhật ký rồi đi chuyển khoản sẽ chuyển sai số.

           Cùng lối với OrderModel::markPaid() và ::markDepositPaid(). */
        $n = Database::execute(
            "UPDATE refund_requests
                SET status = 'approved', approved_amount = :so, shop_fault = :loi,
                    decision_note = :ghi, decided_by = :boi, decided_at = NOW()
              WHERE id = :id AND status = 'pending'",
            [
                'so'  => $chot,
                'loi' => $loiCuaHang ? 1 : 0,
                'ghi' => $lyDo !== '' ? utf8Substr($lyDo, 0, 500) : null,
                'boi' => $actorId,
                'id'  => $id,
            ]
        );

        if ($n === 0) {
            return ['ok' => false, 'error' =>
                'Yêu cầu này vừa được người khác xử lý. Tải lại trang để xem số đã chốt.'];
        }

        return ['ok' => true, 'amount' => $chot];
    }

    /**
     * Đọc số tiền người duyệt gõ tay — trả null nếu KHÔNG đọc được.
     *
     * Chấp nhận đúng những gì một người thật gõ vào ô tiền: chữ số, dấu chấm
     * và dấu phẩy phân nhóm, khoảng trắng, và ký hiệu đồng ở cuối. Bỏ trống
     * nghĩa là "duyệt theo số đề nghị" nên trả thẳng $macDinh.
     *
     * Mọi thứ khác trả null để nơi gọi báo lỗi — xem khối chú thích ở duyet().
     */
    private static function docSoTien(string $tho, int $macDinh): ?int
    {
        $tho = trim($tho);

        if ($tho === '') {
            return $macDinh;
        }

        // Bỏ dấu phân nhóm, khoảng trắng và ký hiệu tiền tệ; giữ nguyên phần còn lại.
        $so = preg_replace('/[.,\s]|đ|₫|VND|vnd/u', '', $tho);

        return ($so !== '' && ctype_digit($so)) ? (int) $so : null;
    }

    /** Từ chối hoàn — luôn bắt buộc lý do, vì khách sẽ hỏi. */
    public static function tuChoi(string $id, string $actorId, string $lyDo): array
    {
        $yc = self::chiTiet($id);

        if ($yc === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy yêu cầu hoàn tiền.'];
        }

        if ($yc['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'Yêu cầu này đã được xử lý.'];
        }

        $lyDo = trim($lyDo);

        if (utf8Length($lyDo) < self::LY_DO_TOI_THIEU) {
            return ['ok' => false, 'error' =>
                'Từ chối hoàn tiền thì phải ghi lý do, tối thiểu '
                . self::LY_DO_TOI_THIEU . ' ký tự.'];
        }

        /* KHÔNG ghi approved_amount = 0.

           Trước đây câu này đặt 0 vào đó, và màn hình đọc "đã duyệt hoàn 0đ"
           cho một yêu cầu bị TỪ CHỐI — hai chuyện khác hẳn nhau. NULL nghĩa là
           "chưa từng có số nào được chốt", đúng với việc từ chối. */
        $n = Database::execute(
            "UPDATE refund_requests
                SET status = 'rejected', approved_amount = NULL, decision_note = :ghi,
                    decided_by = :boi, decided_at = NOW()
              WHERE id = :id AND status = 'pending'",
            ['ghi' => utf8Substr($lyDo, 0, 500), 'boi' => $actorId, 'id' => $id]
        );

        if ($n === 0) {
            return ['ok' => false, 'error' =>
                'Yêu cầu này vừa được người khác xử lý. Tải lại trang để xem kết quả.'];
        }

        return ['ok' => true];
    }

    /**
     * Ghi nhận tiền ĐÃ trả lại khách — bước 4 của UC-04.
     *
     * `$ngay` do người bấm nhập, KHÔNG lấy NOW(): chuyển khoản làm ở ngân hàng
     * có thể trước lúc bấm vài giờ hoặc vài ngày, và đối soát sau này đọc cột
     * này chứ không đọc `updated_at`.
     */
    public static function danhDauDaHoan(
        string $id,
        string $actorId,
        string $ngay,
        string $ghiChu
    ): array {
        $yc = self::chiTiet($id);

        if ($yc === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy yêu cầu hoàn tiền.'];
        }

        if ($yc['status'] !== 'approved') {
            return ['ok' => false, 'error' =>
                'Chỉ đánh dấu đã hoàn được cho yêu cầu ĐÃ DUYỆT. Yêu cầu này đang ở "'
                . (self::STATUSES[$yc['status']] ?? $yc['status']) . '".'];
        }

        $ngay = trim($ngay);

        /* DẤU CHẤM THAN TRONG '!Y-m-d' LÀ THỨ QUAN TRỌNG NHẤT Ở HÀM NÀY.

           Không có nó, createFromFormat() lấp giờ-phút-giây bằng ĐỒNG HỒ HIỆN
           TẠI. Ngày hôm nay khi ấy thành "hôm nay 14:37:12", lớn hơn
           new DateTime('today') vốn là "hôm nay 00:00:00" — và phép chặn ngày
           tương lai ngay dưới sẽ từ chối MỌI lần bấm trong ngày, trừ đúng giây
           đầu tiên sau nửa đêm. Tức là bước 4 của UC-04 không chạy được, và
           cách duy nhất người dùng tìm ra là ghi lùi sang hôm qua — làm hỏng
           đúng cột dùng để đối soát.

           '!' đặt mọi trường không có trong định dạng về 0. */
        $d   = DateTime::createFromFormat('!Y-m-d', $ngay);
        $loi = DateTime::getLastErrors();

        // getLastErrors() trả mảng ở PHP 8.1 và false ở 8.2+ khi không có lỗi.
        if ($d === false || ($loi !== false && ($loi['warning_count'] ?? 0) > 0)) {
            return ['ok' => false, 'error' => 'Ngày hoàn tiền không hợp lệ.'];
        }

        // Ngày ở tương lai là gõ nhầm: tiền chưa chuyển thì chưa bấm được nút này.
        if ($d > new DateTime('today')) {
            return ['ok' => false, 'error' => 'Ngày hoàn tiền không được ở tương lai.'];
        }

        $ghiChu = trim($ghiChu);

        // Đếm dòng đã ghi — lý do đầy đủ ở duyet(). Ở đây hậu quả của việc bỏ
        // qua là HAI dòng nhật ký "đã hoàn tiền" cho một lần chuyển khoản.
        $n = Database::execute(
            "UPDATE refund_requests
                SET status = 'refunded', refunded_on = :ngay, refund_note = :ghi,
                    updated_at = NOW()
              WHERE id = :id AND status = 'approved'",
            [
                'ngay' => $ngay,
                'ghi'  => $ghiChu !== '' ? utf8Substr($ghiChu, 0, 500) : null,
                'id'   => $id,
            ]
        );

        if ($n === 0) {
            return ['ok' => false, 'error' =>
                'Yêu cầu này vừa được người khác đánh dấu đã hoàn. Tải lại trang để xem.'];
        }

        return ['ok' => true];
    }

    /**
     * Đơn đã huỷ, CÓ cọc đã nhận, mà KHÔNG có yêu cầu hoàn tiền nào.
     *
     * Đây là câu đối chiếu cho lỗ hổng mà taoChoDonHuy() cố ý để lại: nó không
     * chặn luồng huỷ đơn khi tạo yêu cầu hỏng, nên phải có chỗ tìm ra những đơn
     * rơi qua khe đó. Màn quản trị hiện con số này; khác 0 nghĩa là có khách
     * đang chờ tiền mà hàng chờ duyệt không biết.
     */
    public static function donCocChuaCoYeuCau(): array
    {
        if (!self::available()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT o.id, o.code, o.customer_name, o.customer_phone,
                    o.total, o.deposit_amount, o.payment_status, o.updated_at
               FROM orders o
              WHERE ' . self::DIEU_KIEN_SOT . '
              ORDER BY o.updated_at DESC
              LIMIT 50'
        );
    }

    /**
     * ĐẾM những đơn ấy — dùng cho dải cảnh báo.
     *
     * Câu riêng chứ không count() mảng ở trên: mảng ấy có LIMIT 50, nên đếm nó
     * là in ra "50" mãi mãi cho tới khi tồn đọng rơi xuống dưới 50. Một con số
     * đứng yên trong khi việc dồn lên là con số tệ hơn không có.
     */
    public static function demSot(): int
    {
        if (!self::available()) {
            return 0;
        }

        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM orders o WHERE ' . self::DIEU_KIEN_SOT
        );
    }

    /**
     * Mệnh đề chung của hai câu trên — sửa một chỗ, không hai.
     *
     * "Đã huỷ, cửa hàng đang giữ tiền, mà không có yêu cầu hoàn tiền nào."
     * Không kèm `deposit_amount > 0`: đơn chỉ mua gọng không cần cọc nhưng vẫn
     * trả đủ được, và khi ấy cửa hàng vẫn đang giữ trọn số tiền — cùng lý lẽ
     * với ngưỡng ở taoChoDonHuy().
     */
    private const DIEU_KIEN_SOT = "o.status = 'cancelled'
                AND o.payment_status IN ('deposit_paid', 'paid')
                AND NOT EXISTS (SELECT 1 FROM refund_requests r
                                 WHERE r.order_id = o.id)";
}
