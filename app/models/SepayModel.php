<?php

/**
 * SepayModel — sổ giao dịch chuyển khoản do SePay báo về, và luật khớp tiền
 * vào đơn hàng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TẤT CẢ LUẬT NẰM Ở ĐÂY, KHÔNG Ở CONTROLLER
 *
 * SepayController chỉ lo phần HTTP: kiểm khoá, đọc JSON, trả 200. Việc "giao
 * dịch này thuộc đơn nào, đủ tiền chưa, có được đổi trạng thái đơn không" là
 * luật nghiệp vụ về TIỀN — nó phải nằm một chỗ, kiểm được, và không lẫn với
 * chuyện header hay mã trạng thái.
 * ─────────────────────────────────────────────────────────────────────────────
 * THỨ TỰ GHI SỔ RỒI MỚI ĐỔI ĐƠN — ĐÂY LÀ CHỖ DỄ SAI NHẤT
 *
 * SePay gửi lại tối đa 7 lần trong 5 giờ nếu không nhận được HTTP 200. Lần gửi
 * lại có thể tới SAU KHI máy chủ đã xử lý xong nhưng chết đúng lúc trả lời —
 * nên "đã xử lý chưa" không thể hỏi bằng cách nhìn trạng thái đơn.
 *
 * Vì thế record() GHI DÒNG SỔ TRƯỚC. Khoá UNIQUE trên `sepay_id` là thứ trả
 * lời câu hỏi đó: chèn được nghĩa là lần đầu, chèn trùng nghĩa là đã làm rồi
 * và lần này chỉ việc trả 200 cho SePay thôi.
 *
 * Đảo thứ tự — đổi đơn trước rồi ghi sổ — thì giữa hai bước có một khoảng chết
 * mà một lần gửi lại lọt vào đó sẽ cộng tiền lần thứ hai.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class SepayModel extends BaseModel
{
    protected static string $table = 'sepay_transactions';

    /** Bảng có tồn tại không — chưa chạy migration thì webhook tự tắt. */
    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /**
     * Đọc MÃ ĐƠN ra khỏi nội dung chuyển khoản.
     *
     * Mã đơn có dạng DH-260822-8A13 (xem generateCode). Nhưng thứ về tới đây
     * là chữ do NGÂN HÀNG ghi lại, và mỗi ngân hàng đối xử với nó một kiểu:
     * bỏ dấu gạch, hạ chữ thường, chèn thêm chữ ("CT tu 0123 DH2608228A13"),
     * hoặc dán liền vào tên người chuyển.
     *
     * Nên bỏ hết ký tự không phải chữ-số rồi mới dò. Ghép lại đúng dạng chuẩn
     * để đem đi tra bảng `orders`.
     *
     * @return string|null mã đơn dạng DH-yymmdd-XXXX, hoặc null nếu không thấy
     */
    public static function extractOrderCode(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        $flat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $content) ?? '');

        if (!preg_match('/DH(\d{6})([0-9A-F]{4})/', $flat, $m)) {
            return null;
        }

        return sprintf('DH-%s-%s', $m[1], $m[2]);
    }

    /**
     * Xử lý MỘT giao dịch SePay gửi về.
     *
     * @param array $txn payload đã json_decode
     * @return array{status:string, order_code?:string, duplicate?:bool}
     *         `status` là giá trị ghi vào cột `applied` — xem migration.
     */
    public static function handle(array $txn): array
    {
        $sepayId = (int) ($txn['id'] ?? 0);

        if ($sepayId <= 0) {
            return ['status' => 'invalid'];
        }

        $type   = (string) ($txn['transferType'] ?? 'in');
        $amount = (int) round((float) ($txn['transferAmount'] ?? 0));

        /* SePay tự tách được mã ở trường `code` với một số cấu hình; không có
           thì tự đọc từ `content`. Thử `code` trước vì nó sạch hơn. */
        $code = self::extractOrderCode((string) ($txn['code'] ?? ''))
             ?? self::extractOrderCode((string) ($txn['content'] ?? ''));

        $order = $code !== null ? OrderModel::findByCode($code) : null;

        /*
         * QUYẾT ĐỊNH LÀM GÌ — tính TRƯỚC khi ghi sổ, để dòng sổ nói đúng việc
         * sắp làm. Ba ngưỡng, xét từ cao xuống:
         *
         *   đủ tổng đơn        -> 'paid'
         *   đủ tiền cọc        -> 'deposit_paid'   (chỉ đơn có cắt tròng)
         *   ít hơn cả hai      -> 'partial'        KHÔNG đổi đơn
         *
         * ─────────────────────────────────────────────────────────────────
         * MỖI GIAO DỊCH XÉT ĐỘC LẬP — SRS v2.1.0, E08
         *
         * Trước đây hệ thống CỘNG DỒN mọi khoản đã về cho đơn rồi mới so với
         * tổng tiền, nên hai lần chuyển 30% + 70% tự động đưa đơn sang "đã
         * thanh toán". Chủ đầu tư đã bỏ cách làm đó: một giao dịch chỉ được so
         * với chính đơn ấy, không cộng với lần trước.
         *
         * HỆ QUẢ PHẢI BIẾT: khách trả cọc rồi chuyển nốt phần còn lại thì lần
         * chuyển thứ hai KHÔNG tự đẩy đơn sang "đã thanh toán".
         *
         * Nó rơi vào nhãn nào thì tuỳ số tiền của CHÍNH nó. Đơn 4.400.000đ cọc
         * 1.320.000đ: lần chuyển 3.080.000đ nhỏ hơn tổng nhưng lớn hơn cọc, nên
         * nhãn là 'deposit_paid' — và markDepositPaid() là một lệnh không làm
         * gì (đơn đã ở 'deposit_paid' từ lần chuyển đầu). Lần chuyển nhỏ hơn cả
         * cọc thì nhãn là 'partial'. Cả hai trường hợp đơn đều đứng yên và cần
         * nhân viên đối chiếu rồi bấm tay ở màn đơn hàng.
         *
         * Vì thế MỘT ĐƠN CÓ THỂ CÓ HAI DÒNG CÙNG NHÃN 'deposit_paid' trong sổ.
         * Nhãn ở đây trả lời "một mình khoản này đủ tới đâu", không trả lời
         * "đơn đang ở đâu" — chỗ trả lời câu sau là `orders.payment_status`.
         *
         * Bù lại là màn hình SỔ GIAO DỊCH NGÂN HÀNG (FR-SG trong SRS), nơi
         * nhân viên nhìn thấy mọi khoản đã về cho một đơn; trước đây bảng
         * sepay_transactions ghi vào mà không màn hình nào đọc ra.
         *
         * Đừng dựng lại phép cộng dồn ở đây mà không sửa SRS trước.
         * ─────────────────────────────────────────────────────────────────
         *
         * "partial" cố tình không đổi gì: khách chuyển thiếu là chuyện phải có
         * người nhìn, không phải chuyện để máy tự đoán ý. Dòng sổ vẫn còn nên
         * nhân viên thấy được.
         *
         * So bằng >= chứ không ==: khách chuyển dư (làm tròn lên cho chẵn) là
         * chuyện thường ngày, và từ chối một khoản tiền ĐÃ VỀ vì nó lẻ 2.000đ
         * thì đơn treo mãi không ai hiểu vì sao.
         */
        $status = 'no_order';

        if ($type !== 'in') {
            // Tiền RA khỏi tài khoản — hoàn tiền, phí, chuyển đi nơi khác.
            // Ghi vào sổ cho khớp sao kê nhưng không đụng đơn nào.
            $status = 'ignored';
        } elseif ($order !== null) {
            $total   = (int) $order['total'];
            $deposit = (int) ($order['deposit_amount'] ?? 0);
            // CHỈ lần chuyển này — không cộng với các lần trước (E08).
            $received = $amount;

            if ($received >= $total) {
                $status = 'paid';
            } elseif ($deposit > 0 && $received >= $deposit) {
                $status = 'deposit_paid';
            } else {
                $status = 'partial';
            }
        }

        /* ── GHI SỔ VÀ ĐỔI ĐƠN TRONG MỘT TRANSACTION ──────────────────────
           SNFR-06 / SW_02: "cập nhật trạng thái + trừ kho + xác nhận cọc
           trong 1 transaction".

           TRƯỚC ĐÂY HAI VIỆC NÀY LÀ HAI LỆNH RỜI, và đó là lỗ hổng tiền thật:
           ghi sổ xong, chết trước khi kịp đổi đơn (mất kết nối CSDL, hết bộ
           nhớ, PHP bị giết vì quá thời gian) thì dòng sổ đã nằm đó với
           `applied = 'paid'` trong khi đơn vẫn `unpaid`. Lần SePay gửi lại bị
           chính khoá UNIQUE `sepay_id` chặn ở ngay câu INSERT, nên hệ thống
           KHÔNG BAO GIỜ tự chữa được: tiền đã về tài khoản mà đơn đứng im,
           cho tới khi có người đọc sao kê và bấm tay.

           Bọc hai việc vào một transaction thì nhánh chết đó cuộn lại cả dòng
           sổ, khoá UNIQUE trống trở lại, và lần gửi lại của SePay chạy sạch từ
           đầu. Tính idempotent KHÔNG mất đi: lần xử lý THÀNH CÔNG mới commit
           dòng sổ, nên bản gửi lại của một giao dịch đã xong vẫn đâm vào khoá
           UNIQUE và dừng đúng như cũ.

           Thứ tự trong transaction vẫn là ghi sổ trước, đổi đơn sau — giữ
           nguyên vì khoá UNIQUE phải là thứ chặn sớm nhất. */
        try {
            Database::transaction(static function () use (
                $sepayId, $order, $code, $txn, $type, $amount, $status
            ): void {
                Database::execute(
                    'INSERT INTO sepay_transactions
                    (id, sepay_id, order_id, order_code, gateway, account_number,
                     transfer_type, amount, content, reference_code,
                     transaction_date, applied)
                 VALUES
                    (:id, :sepay_id, :order_id, :order_code, :gateway, :account_number,
                     :transfer_type, :amount, :content, :reference_code,
                     :transaction_date, :applied)',
                    [
                        'id'               => uuid(),
                        'sepay_id'         => $sepayId,
                        'order_id'         => $order['id'] ?? null,
                        'order_code'       => $code,
                        'gateway'          => self::clip($txn['gateway'] ?? null, 64),
                        'account_number'   => self::clip($txn['accountNumber'] ?? null, 64),
                        'transfer_type'    => $type === 'out' ? 'out' : 'in',
                        'amount'           => $amount,
                        'content'          => $txn['content'] ?? null,
                        'reference_code'   => self::clip($txn['referenceCode'] ?? null, 64),
                        'transaction_date' => self::date($txn['transactionDate'] ?? null),
                        'applied'          => $status,
                    ]
                );

                // ── RỒI MỚI ĐỔI ĐƠN, VẪN TRONG CÙNG TRANSACTION ──────────
                if ($order !== null && $status === 'paid') {
                    OrderModel::markPaid($order['id']);
                } elseif ($order !== null && $status === 'deposit_paid') {
                    OrderModel::markDepositPaid($order['id']);
                }
            });
        } catch (Throwable $e) {
            error_log('[SePay] Không xử lý được giao dịch #' . $sepayId . ': ' . $e->getMessage());

            /* TRÙNG HAY HỎNG THẬT? HAI CA NÀY PHẢI TRẢ LỜI KHÁC NHAU.

               Trước đây mọi lỗi ở đây đều bị coi là "trùng" và webhook trả 200
               — tức là một lỗi THẬT (mất bảng, sai kiểu cột, CSDL sập giữa
               chừng) cũng khiến SePay thôi gửi lại, và tiền về mà đơn không
               đổi. Không có gì nổ ra, không ai biết.

               Phân biệt bằng cách hỏi lại CSDL chứ không đọc mã lỗi của
               driver: mã lỗi phụ thuộc PDO/MySQL/MariaDB và cách bọc ngoại lệ,
               còn "dòng ấy đã có trong sổ chưa" thì luôn đúng. Transaction đã
               cuộn lại rồi nên câu hỏi này chỉ thấy dữ liệu đã commit thật.

               Ném tiếp khi KHÔNG phải trùng: SepayController bắt Throwable và
               trả 500, đó là tín hiệu để SePay gửi lại — lần sau có cơ hội
               thành công vì transaction đã dọn sạch dấu vết lần hỏng. */
            if (self::daGhiSo($sepayId)) {
                return ['status' => $status, 'order_code' => $code, 'duplicate' => true];
            }

            throw $e;
        }

        return ['status' => $status, 'order_code' => $code];
    }

    /**
     * Giao dịch này đã nằm trong sổ chưa?
     *
     * Chỉ dùng ở nhánh hỏng của handle() để tách "SePay gửi lại" khỏi "CSDL
     * đang hỏng". Nuốt lỗi và trả false: không tra được thì coi như CHƯA ghi,
     * để handle() ném tiếp và SePay gửi lại — thà xử lý lại một giao dịch (đã
     * có khoá UNIQUE chặn) còn hơn bỏ rơi một khoản tiền đã về.
     */
    private static function daGhiSo(int $sepayId): bool
    {
        try {
            return (int) Database::fetchValue(
                'SELECT COUNT(*) FROM sepay_transactions WHERE sepay_id = :s',
                ['s' => $sepayId]
            ) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /*
     * receivedFor() ĐÃ GỠ — SRS v2.1.0, E08.
     *
     * Hàm này cộng tổng tiền đã về cho một đơn từ sổ giao dịch, để handle() so
     * tổng đó với tổng đơn. Cách làm cộng dồn đã bỏ; mỗi giao dịch nay xét
     * độc lập, nên không còn ai gọi.
     *
     * Màn hình sổ giao dịch ngân hàng (FR-SG) sẽ cần một phép cộng tương tự để
     * HIỂN THỊ tổng đã về cho một đơn — nhưng đó là con số cho người đọc, không
     * phải điều kiện để máy tự đổi trạng thái đơn. Hai việc khác nhau.
     */

    /** Cắt về đúng độ dài cột, tránh một trường dài bất thường làm hỏng cả lệnh chèn. */
    private static function clip(mixed $value, int $len): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : utf8Substr($value, 0, $len);
    }

    /** "2024-07-02 11:08:33" -> giữ nguyên; thứ không đọc được -> null. */
    private static function date(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);

        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }

    // ========================================================================
    // SỔ ĐỐI SOÁT — FR-SG-01..07
    //
    // Phần trên file này GHI vào bảng; phần dưới đây ĐỌC nó ra, và đó là toàn
    // bộ nội dung của Quyết định E06. SRS mở đầu mục 4.2 bằng đúng câu:
    // "Hệ thống đã ghi đầy đủ mọi giao dịch nhận được từ ngân hàng, nhưng
    // không có màn hình nào đọc ra."
    // ========================================================================

    /** Năm kết quả đối soát — thứ tự này là thứ tự các viên lọc trên màn hình. */
    public const KET_QUA = [
        'paid'         => 'Đã thanh toán',
        'deposit_paid' => 'Đã đặt cọc',
        'partial'      => 'Thu một phần',
        'no_order'     => 'Không tìm thấy đơn',
        'ignored'      => 'Bỏ qua',
    ];

    /**
     * HAI KẾT QUẢ LÀ HÀNG CHỜ CÓ NGƯỜI ĐANG ĐỢI — FR-SG-03 và FR-SG-06.
     *
     * SRS gọi chúng là hai viên lọc "in đậm". Điểm chung: mỗi dòng mang một
     * khoản tiền ĐÃ VỀ tài khoản cửa hàng mà đơn của khách vẫn hiện chưa thanh
     * toán. Bốn kết quả còn lại đã xong việc — không ai phải làm gì nữa.
     */
    public const CAN_XU_LY = ['partial', 'no_order'];

    /** Bao nhiêu dòng một trang — FR-SG-01 nói rõ 20. */
    public const MOI_TRANG = 20;

    /**
     * CSDL đã có ĐỦ HAI cột dấu vết gắn tay chưa — migration đợt 7.
     *
     * HỎI CẢ HAI, không hỏi mỗi `gan_boi`. Migration thêm chúng bằng hai câu
     * ALTER rời, mà DDL của MySQL không nằm trong transaction — đứt mạng giữa
     * hai câu để lại đúng trạng thái `gan_boi` có / `gan_luc` không. Hỏi một
     * cột thì hàm này trả true, danhSach() sinh câu SELECT nhắc tới `gan_luc`,
     * và MỌI lượt mở màn đối soát chết với lỗi 1054 — hỏng đúng cái lối thoát
     * dựng ra cho tình huống chưa nâng cấp.
     */
    public static function coGanTay(): bool
    {
        return Database::columnExists(static::$table, 'gan_boi')
            && Database::columnExists(static::$table, 'gan_luc');
    }

    /**
     * Một trang của sổ giao dịch — FR-SG-01, 02, 03, 04.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * SẮP THEO `transaction_date`, KHÔNG THEO `created_at`
     *
     * Hai mốc khác nhau và người đối soát cần mốc đầu: `transaction_date` là
     * lúc NGÂN HÀNG ghi nhận, `created_at` là lúc webhook tới máy chủ. Chúng
     * lệch nhau khi SePay gửi lại, khi hàng chờ của họ bị dồn, hoặc khi máy chủ
     * vừa hồi sau một lúc sập. Người ngồi so với sao kê ngân hàng đang đọc cột
     * thứ nhất.
     *
     * COALESCE về `created_at` cho những dòng thiếu: cột cho phép NULL vì
     * payload của SePay không phải lúc nào cũng mang ngày đọc được (xem
     * self::date()). Không có COALESCE thì mấy dòng ấy rơi xuống đáy sổ vĩnh
     * viễn — đúng những dòng có gì đó bất thường.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * Ô TÌM QUÉT BA CỘT — FR-SG-04: nội dung chuyển khoản, mã đơn, số tiền.
     *
     * Ba thứ ấy là ba câu hỏi thật ở quầy: "khách bảo họ ghi nội dung là ABC",
     * "đơn DH-260906-1A2B đã nhận tiền chưa", "sáng nay có khoản 1.320.000 nào
     * về không". Gõ số thì so bằng `=`, không LIKE: tìm "1320000" mà khớp cả
     * "11320000" là trả lời sai một câu hỏi về tiền.
     *
     * @return array{rows: list<array>, total: int, totalPages: int, page: int}
     */
    public static function danhSach(string $ketQua = '', string $q = '', int $trang = 1): array
    {
        $trong = ['rows' => [], 'total' => 0, 'totalPages' => 1, 'page' => 1];

        if (!self::available()) {
            return $trong;
        }

        [$where, $params] = self::locVaTim($ketQua, $q);

        $total = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM sepay_transactions t ' . $where,
            $params
        );

        $soTrang = max(1, (int) ceil($total / self::MOI_TRANG));
        $trang   = min(max(1, $trang), $soTrang);

        /* LEFT JOIN sang `orders` để lấy trạng thái tiền và tổng tiền của đơn
           đã khớp — FR-SG-02 đòi hiện mã đơn, và mã đơn trần thì chưa trả lời
           được câu hỏi kế tiếp ("đơn ấy giờ ra sao"). LEFT vì phần lớn dòng
           'no_order' không có đơn nào, và cả những dòng có `order_code` mà đơn
           đã bị xoá — khoá ngoại là ON DELETE SET NULL.

           `p.full_name` là tên người GẮN TAY, chỉ có với dòng do người gắn.
           Cột đó có thể chưa tồn tại (chưa chạy migration đợt 7) nên câu SELECT
           dựng động — cùng lối với WaitlistModel::dangChoCho(). */
        $ganTay = self::coGanTay();
        $cotGan = $ganTay
            ? 't.gan_boi, t.gan_luc, pg.full_name AS gan_ten'
            : 'NULL AS gan_boi, NULL AS gan_luc, NULL AS gan_ten';
        $joinGan = $ganTay ? ' LEFT JOIN profiles pg ON pg.id = t.gan_boi' : '';

        $rows = Database::fetchAll(
            'SELECT t.*, ' . $cotGan . ',
                    o.payment_status, o.status AS order_status,
                    o.total AS order_total, o.deposit_amount, o.customer_name
               FROM sepay_transactions t
               LEFT JOIN orders o ON o.id = t.order_id' . $joinGan . ' '
            . $where
            . ' ORDER BY COALESCE(t.transaction_date, t.created_at) DESC, t.id DESC
                LIMIT ' . self::MOI_TRANG . ' OFFSET ' . (($trang - 1) * self::MOI_TRANG),
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'totalPages' => $soTrang, 'page' => $trang];
    }

    /**
     * Mệnh đề WHERE dùng chung cho câu đếm và câu đọc.
     *
     * MỘT HÀM CHO CẢ HAI, không chép đôi: hai câu lệnh lệch điều kiện nhau là
     * thanh phân trang nói một đằng còn bảng hiện một nẻo, và lỗi ấy chỉ lộ ra
     * ở trang cuối.
     *
     * @return array{0: string, 1: array}
     */
    private static function locVaTim(string $ketQua, string $q): array
    {
        $dieuKien = [];
        $params   = [];

        if ($ketQua !== '' && isset(self::KET_QUA[$ketQua])) {
            $dieuKien[]     = 't.applied = :kq';
            $params['kq']   = $ketQua;
        }

        $q = trim($q);

        if ($q !== '') {
            /* SỐ THÌ SO BẰNG, CHỮ THÌ SO GẦN ĐÚNG.

               `ctype_digit` sau khi bỏ dấu chấm và dấu phẩy: người ta gõ số
               tiền theo cách nó HIỆN trên màn hình ("1.320.000"), không theo
               cách nó nằm trong CSDL. Bắt họ gõ lại không dấu chấm là bắt họ
               dịch một con số họ đang nhìn thấy.

               Vẫn tìm cả ba cột trong cùng một lượt: một chuỗi toàn số cũng có
               thể là mẩu nội dung chuyển khoản. addcslashes để dấu % hay _
               trong từ khoá không thành ký tự đại diện của LIKE. */
            $so = str_replace(['.', ',', ' '], '', $q);

            $ve     = '(t.content LIKE :tim_nd OR t.order_code LIKE :tim_ma';
            $needle = '%' . addcslashes($q, '%_\\') . '%';

            $params['tim_nd'] = $needle;
            $params['tim_ma'] = $needle;

            if ($so !== '' && ctype_digit($so)) {
                $ve                .= ' OR t.amount = :tim_tien';
                $params['tim_tien'] = (int) $so;
            }

            $dieuKien[] = $ve . ')';
        }

        return [$dieuKien !== [] ? 'WHERE ' . implode(' AND ', $dieuKien) : '', $params];
    }

    /**
     * Số giao dịch theo từng kết quả — cho dải viên lọc, FR-SG-03.
     *
     * Trả về mảng có ĐỦ sáu khoá kể cả khi bảng chưa có, để màn hình không phải
     * `?? 0` ở sáu chỗ. Khoá '' là tổng.
     */
    public static function demTheoKetQua(string $q = ''): array
    {
        $dem = [''  => 0];

        foreach (array_keys(self::KET_QUA) as $k) {
            $dem[$k] = 0;
        }

        if (!self::available()) {
            return $dem;
        }

        /* CON SỐ TRÊN VIÊN LỌC TÍNH THEO TỪ KHOÁ ĐANG TÌM.

           Không thì bấm qua lại ra một viên có số mà bấm vào lại rỗng: gõ tìm
           "1320000" còn 2 dòng trong bảng, mà dải viên vẫn ghi "Tất cả 4.312".
           Màn Lịch sử thao tác đã chốt luật này và ghi rõ lý do; chép nguyên.

           Truyền '' cho tham số lọc: viên nào cũng phải đếm được số của MÌNH,
           nên câu này không được mang điều kiện `applied` của viên đang chọn. */
        [$where, $params] = self::locVaTim('', $q);

        foreach (Database::fetchAll(
            'SELECT applied, COUNT(*) AS n FROM sepay_transactions t '
            . $where . ' GROUP BY applied',
            $params
        ) as $d) {
            if (isset($dem[$d['applied']])) {
                $dem[$d['applied']] = (int) $d['n'];
            }

            $dem[''] += (int) $d['n'];
        }

        return $dem;
    }

    /**
     * Số giao dịch CHƯA XỬ LÝ — huy hiệu thanh bên, FR-SG-06.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * KHÔNG PHẢI PHÉP ĐẾM THUẦN `applied IN ('partial','no_order')`
     *
     * SRS viết đúng chữ ấy, và đọc thẳng thì con số này KHÔNG BAO GIỜ VỀ 0.
     * Lý do: một dòng 'partial' ở lại 'partial' mãi mãi — cột `applied` ghi
     * "một mình khoản này đủ tới đâu", một sự thật về quá khứ, không đổi được.
     * Khách chuyển thiếu rồi hôm sau chuyển bù, nhân viên đối chiếu xong và bấm
     * "Đã nhận đủ" ở màn đơn hàng: việc đã xong, mà dòng cũ vẫn 'partial'.
     *
     * Một huy hiệu chỉ tăng là một huy hiệu người ta học cách bỏ qua — rồi bỏ
     * qua luôn năm con số bên cạnh nó. Cùng lập luận đã ghi ở đầu
     * app/views/admin/_layout/master.php và ở huy hiệu Hàng chờ thư.
     *
     * Nên phép đếm ở đây hỏi thêm một câu: ĐƠN của dòng ấy còn đang chờ tiền
     * không. Còn thì đếm, xong rồi thì thôi.
     *
     *   no_order   luôn đếm — không có đơn nào để hỏi, và một khoản tiền không
     *              biết của ai thì luôn cần người tìm.
     *   partial    chỉ đếm khi đơn vẫn 'unpaid' VÀ chưa bị huỷ. Đơn đã huỷ thì
     *              tiền cọc đi theo đường hoàn tiền của UC-04, không phải đường
     *              này.
     *
     * Kết quả: huy hiệu về 0 khi mọi khoản đã được xử lý, và sáng lên trở lại
     * ngay khi có khoản mới rơi vào khe.
     */
    public static function demChuaXuLy(): int
    {
        if (!self::available()) {
            return 0;
        }

        return (int) Database::fetchValue(
            "SELECT COUNT(*)
               FROM sepay_transactions t
              WHERE t.applied = 'no_order'
                 OR (t.applied = 'partial' AND EXISTS (
                        SELECT 1 FROM orders o
                         WHERE o.id = t.order_id
                           AND o.payment_status = 'unpaid'
                           AND o.status <> 'cancelled'
                     ))"
        );
    }

    /**
     * Một giao dịch kèm đơn đã khớp, hoặc null.
     *
     * DỰNG SẴN `gan_boi`/`gan_luc` KHI CỘT CHƯA CÓ, đúng như danhSach() làm.
     * Thiếu bước này thì ngăn kéo đọc hai khoá không tồn tại trên mọi dòng đã
     * có đơn — tức đa số dòng — ở đúng cái trạng thái mà dải cảnh báo đầu màn
     * vừa nói với người vận hành là "vẫn dùng được".
     */
    public static function chiTiet(string $id): ?array
    {
        if (!self::available()) {
            return null;
        }

        $cotGan = self::coGanTay()
            ? 't.gan_boi, t.gan_luc'
            : 'NULL AS gan_boi, NULL AS gan_luc';

        return Database::fetchOne(
            'SELECT t.*, ' . $cotGan . ',
                    o.code AS don_ma, o.total AS order_total,
                    o.deposit_amount, o.payment_status, o.status AS order_status,
                    o.customer_name
               FROM sepay_transactions t
               LEFT JOIN orders o ON o.id = t.order_id
              WHERE t.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Đánh dấu một khoản KHÔNG PHẢI tiền khách trả — FR-SG-06 mới dùng được.
     *
     * ═════════════════════════════════════════════════════════════════════════
     * KHÔNG PHẢI MỘT NGOẠI LỆ CỦA FR-SG-07, MÀ LÀ THỨ LÀM HUY HIỆU CHẠY ĐƯỢC
     *
     * FR-SG-07 cấm SỬA và XOÁ một dòng sổ. Hàm này không đụng tới số tiền, nội
     * dung, thời điểm hay chiều tiền — nó chỉ đổi KẾT QUẢ ĐỐI SOÁT sang một
     * giá trị mà chính SRS đã liệt kê trong dải viên lọc: 'ignored' — "Bỏ qua".
     * Một viên lọc tồn tại mà không có đường nào đưa dòng vào đó là một viên
     * lọc chết.
     *
     * VÌ SAO BẮT BUỘC PHẢI CÓ: demChuaXuLy() đếm mọi dòng 'no_order', và
     * ganDon() từ chối mọi thứ không phải mã đơn thật. Nhưng tài khoản ngân
     * hàng của cửa hàng nhận đủ thứ KHÔNG PHẢI tiền hàng: lãi nhập vốn, chủ
     * nạp tiền vào, nhà cung cấp hoàn, một lượt chuyển thử. Mỗi khoản như vậy
     * ở lại 'no_order' vĩnh viễn.
     *
     * Không có hàm này thì huy hiệu có một cái sàn khác 0 và chỉ đi lên — đúng
     * cái mà cả khối chú thích ở demChuaXuLy() viện ra để đi chệch khỏi câu chữ
     * của SRS. Đi chệch mà không có đường thoát thì chẳng được gì.
     *
     * CHỈ 'no_order' MỚI BỎ QUA ĐƯỢC. Một dòng 'partial' đã gắn đúng đơn và
     * đang chờ khách chuyển nốt — bỏ qua nó là giấu một đơn thiếu tiền. Nó tự
     * rời khỏi phép đếm khi đơn được trả đủ.
     * ═════════════════════════════════════════════════════════════════════════
     */
    public static function boQua(string $id, string $lyDo, string $actorId): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Chưa có bảng sổ giao dịch trong cơ sở dữ liệu.'];
        }

        $gd = self::chiTiet($id);

        if ($gd === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy giao dịch này.'];
        }

        if ($gd['applied'] !== 'no_order') {
            return ['ok' => false, 'error' =>
                'Chỉ bỏ qua được khoản chưa tìm thấy đơn. Khoản này đang ở "'
                . (self::KET_QUA[$gd['applied']] ?? $gd['applied']) . '".'];
        }

        $lyDo = trim($lyDo);

        if ($lyDo === '') {
            return ['ok' => false, 'error' => 'Vui lòng ghi lý do bỏ qua khoản này.'];
        }

        /* ĐIỀU KIỆN NHẮC LẠI TRONG CÂU LỆNH — cùng lý do như ganDon(): giữa
           lúc đọc và lúc ghi, người khác có thể vừa gắn dòng này vào một đơn. */
        $n = Database::execute(
            "UPDATE sepay_transactions
                SET applied = 'ignored'"
            . (self::coGanTay() ? ', gan_boi = :boi, gan_luc = NOW()' : '') .
            " WHERE id = :id AND applied = 'no_order'",
            self::coGanTay() ? ['boi' => $actorId, 'id' => $id] : ['id' => $id]
        );

        if ($n === 0) {
            return ['ok' => false, 'error' =>
                'Giao dịch này vừa được người khác xử lý. Tải lại trang để xem.'];
        }

        /* VẾT VỚI user_id NULL: khoản này không thuộc đơn nào, nên không thuộc
           khách nào. LÝ DO LÀ BẮT BUỘC và nó đi vào đây — "bỏ qua" là một
           tuyên bố rằng một khoản tiền có thật không liên quan tới việc bán
           hàng, và tuyên bố đó cần người chịu trách nhiệm kèm câu giải thích. */
        AuditLogModel::write(
            null,
            'sepay.link_order',
            sprintf(
                'Bỏ qua giao dịch #%d (%s) — %s',
                (int) $gd['sepay_id'],
                money((int) $gd['amount']),
                utf8Substr($lyDo, 0, 120)
            )
        );

        return ['ok' => true];
    }

    /**
     * Mọi khoản tiền VÀO đã gắn cho một đơn — hiện ở ngăn kéo và dùng để cộng dồn.
     *
     * Chỉ `transfer_type = 'in'`: tiền RA khỏi tài khoản (hoàn tiền, phí) không
     * phải khoản khách trả, và cộng nó vào là trừ ngược tiền của họ.
     */
    public static function khoanCuaDon(string $orderId): array
    {
        if (!self::available()) {
            return [];
        }

        /* COALESCE NGAY TRONG CỘT, không chỉ trong ORDER BY. `transaction_date`
           cho phép NULL vì payload của SePay không phải lúc nào cũng mang ngày
           đọc được — và đúng những dòng ấy là dòng có gì đó bất thường. Không
           lùi về `created_at` thì bảng trong ngăn kéo in ô thời điểm TRỐNG cho
           chính chúng.

           CẢ HAI CHIỀU TIỀN, và cột `transfer_type` đi kèm để ngăn kéo vẽ dấu
           trừ: đây là bảng dùng để trả lời "đơn còn thiếu bao nhiêu", nên nó
           phải hiện đúng những khoản mà phép cộng dồn ở ganDon() đang tính —
           kể cả khoản đã hoàn lại. */
        return Database::fetchAll(
            'SELECT id, sepay_id, amount, content, transfer_type, applied,
                    COALESCE(transaction_date, created_at) AS khi
               FROM sepay_transactions
              WHERE order_id = :id
              ORDER BY COALESCE(transaction_date, created_at) ASC',
            ['id' => $orderId]
        );
    }

    /**
     * GẮN TAY một giao dịch vào một đơn — FR-SG-05.
     *
     * ═════════════════════════════════════════════════════════════════════════
     * ĐÂY LÀ NƠI DUY NHẤT TRONG HỆ THỐNG CỘNG DỒN TIỀN CỦA MỘT ĐƠN
     *
     * Webhook tự động xét TỪNG GIAO DỊCH ĐỘC LẬP — Quyết định E08 bỏ hẳn phép
     * cộng dồn, và khối chú thích trong handle() giải thích dài vì sao. Đừng
     * dựng lại nó ở đó.
     *
     * Nhưng đường NÀY thì khác, và khác ở đúng một điểm: có một con người vừa
     * nhìn vào sổ và tuyên bố khoản tiền này thuộc về đơn kia.
     *
     * Ca dùng thật: khách chuyển cọc 30% đúng nội dung (webhook khớp, đơn sang
     * 'deposit_paid'), rồi chuyển nốt 70% nhưng gõ sai mã đơn (webhook không
     * khớp được, dòng rơi vào 'no_order'). Nhân viên tìm ra và gắn khoản thứ
     * hai vào đơn. Nếu ở đây cũng xét độc lập thì 70% nhỏ hơn tổng nên đơn đứng
     * yên ở 'deposit_paid' — và nhân viên vừa làm đúng việc mà màn hình bảo họ
     * chưa xong. Họ sẽ đi bấm tay "Đã nhận đủ" ở màn đơn hàng, tức là hệ thống
     * bắt làm hai lần cùng một quyết định.
     *
     * Cộng dồn ở đây KHÔNG mâu thuẫn với E08: E08 nói về việc MÁY tự đoán từ
     * một webhook, còn đây là việc NGƯỜI chốt sau khi đã đối chiếu.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * CỘT `applied` CỦA DÒNG NÀY VẪN XÉT ĐỘC LẬP
     *
     * Nghe ngược nhưng đúng: `applied` trả lời "một mình khoản này đủ tới đâu"
     * — một sự thật về bản thân giao dịch. Trạng thái ĐƠN thì trả lời "khách đã
     * trả đủ chưa" — một sự thật về tổng. Hai câu khác nhau, và trộn chúng vào
     * một cột là làm cả sổ không đọc được nữa.
     *
     * Nên một dòng 'partial' đứng cạnh một đơn 'paid' là chuyện BÌNH THƯỜNG:
     * khoản ấy một mình không đủ, nhưng cộng với khoản trước thì đủ.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * BA THỨ BỊ TỪ CHỐI, VÀ CẢ BA ĐỀU VÌ FR-SG-07 ("chỉ đọc và gắn đơn")
     *
     *   · giao dịch ĐÃ có đơn      gắn lại là SỬA một dòng sổ tiền. Gắn nhầm
     *                              thì sửa bằng cách bấm tay ở màn đơn hàng và
     *                              để lại vết ở đó, không bằng cách viết đè lên
     *                              sổ đối soát.
     *   · giao dịch tiền RA        'ignored'. Tiền rời tài khoản không trả cho
     *                              đơn nào; gắn nó vào là cộng một khoản âm
     *                              thành khoản dương.
     *   · đơn đã huỷ               tiền của đơn đã huỷ đi đường hoàn tiền cọc
     *                              (UC-04), không đi đường này. Đẩy nó sang
     *                              'paid' là xoá mất một yêu cầu hoàn tiền.
     *
     * @param string $id      id dòng sổ
     * @param string $maDon   mã đơn nhân viên gõ vào
     * @param string $actorId người đang bấm
     * @return array{ok: bool, error?: string, code?: string, payment_status?: string}
     * ═════════════════════════════════════════════════════════════════════════
     */
    public static function ganDon(string $id, string $maDon, string $actorId): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Chưa có bảng sổ giao dịch trong cơ sở dữ liệu.'];
        }

        $gd = self::chiTiet($id);

        if ($gd === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy giao dịch này.'];
        }

        if ($gd['order_id'] !== null) {
            return ['ok' => false, 'error' => sprintf(
                'Giao dịch này đã gắn vào đơn %s rồi. Sổ đối soát chỉ gắn được một lần.',
                (string) ($gd['don_ma'] ?? $gd['order_code'] ?? '—')
            )];
        }

        if ($gd['transfer_type'] !== 'in') {
            return ['ok' => false, 'error' =>
                'Đây là giao dịch tiền ra khỏi tài khoản, không phải khoản khách trả.'];
        }

        /* CHUẨN HOÁ MÃ ĐƠN BẰNG CHÍNH extractOrderCode().

           Nhân viên gõ lại mã từ màn hình khác hoặc đọc qua điện thoại, nên nó
           tới đây ở mọi hình dạng: "dh-260906-1a2b", "DH2609061A2B", hoặc dán
           kèm khoảng trắng. Hàm ấy đã biết cách bóc mã ra khỏi một chuỗi bẩn —
           dùng lại thay vì viết một luật chuẩn hoá thứ hai sẽ lệch đi. */
        $ma  = self::extractOrderCode($maDon) ?? strtoupper(trim($maDon));
        $don = OrderModel::findByCode($ma);

        if ($don === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy đơn hàng "' . $ma . '".'];
        }

        if (($don['status'] ?? '') === 'cancelled') {
            return ['ok' => false, 'error' =>
                'Đơn ' . $ma . ' đã huỷ. Tiền của đơn đã huỷ xử lý ở màn Hoàn tiền cọc.'];
        }

        $total   = (int) $don['total'];
        $deposit = (int) ($don['deposit_amount'] ?? 0);
        $tien    = (int) $gd['amount'];

        // Nhãn của DÒNG SỔ — xét độc lập, xem khối chú thích trên.
        $nhan = $tien >= $total
            ? 'paid'
            : ($deposit > 0 && $tien >= $deposit ? 'deposit_paid' : 'partial');

        try {
            $ketQua = Database::transaction(static function () use (
                $id, $don, $ma, $nhan, $actorId
            ): array {
                /* ═════════════════════════════════════════════════════════
                   KHOÁ HÀNG ĐƠN TRƯỚC KHI ĐỌC, KHÔNG PHẢI SAU.

                   Phép cộng dồn ở dưới là một chuỗi ĐỌC-QUYẾT-GHI trên trạng
                   thái tiền của đơn, và không có dòng này thì cả chuỗi ấy chạy
                   trên một bản chụp cũ.

                   Ca hỏng, và nó không hiếm: đơn 4.400.000₫ cọc 1.320.000₫ có
                   hai khoản 'no_order' là 3.080.000₫ và 1.320.000₫. Hai nhân
                   viên gắn hai khoản cùng lúc — hoặc một nhân viên gắn đúng
                   lúc webhook của SePay xử lý khoản kia. Dưới REPEATABLE READ,
                   mỗi transaction lập bản đọc riêng và KHÔNG thấy dòng sổ
                   transaction kia chưa commit. T1 cộng ra 3.080.000, T2 cộng
                   ra 1.320.000; cả hai đều nhỏ hơn tổng nên cả hai rơi vào
                   nhánh 'đủ cọc'. Hai câu markDepositPaid() xếp hàng trên khoá
                   hàng `orders` và chạy tuần tự — nhưng QUYẾT ĐỊNH đã lấy từ
                   con số thiếu rồi.

                   Kết cục: khách trả đủ 4.400.000₫, đơn đứng ở 'deposit_paid'.
                   Và không có gì báo động: hai dòng sổ đều mang nhãn
                   'deposit_paid', không nhãn nào thuộc CAN_XU_LY, nên huy hiệu
                   im lặng và cả hai viên lọc đậm cũng không đếm chúng.

                   FOR UPDATE đẩy cả hai vào hàng đợi NGAY TỪ ĐÂY, nên
                   transaction sau đọc được dòng sổ transaction trước đã ghi.

                   ĐỌC LẠI ĐƠN chứ không tin $don đọc từ ngoài: cùng một lượt
                   khoá ấy trả về trạng thái MỚI NHẤT, nên phép kiểm "đơn đã
                   huỷ chưa" ở dưới không còn chạy trên dữ liệu cũ. Người khác
                   vừa huỷ đơn ở tab bên cạnh là ca thật.
                   ═════════════════════════════════════════════════════════ */
                $donKhoa = Database::fetchOne(
                    'SELECT id, total, deposit_amount, status, payment_status
                       FROM orders WHERE id = :id FOR UPDATE',
                    ['id' => $don['id']]
                );

                if ($donKhoa === null) {
                    return ['ok' => false, 'error' => 'Đơn hàng vừa bị xoá. Tải lại trang.'];
                }

                if ($donKhoa['status'] === 'cancelled') {
                    return ['ok' => false, 'error' =>
                        'Đơn ' . $ma . ' vừa bị huỷ. Tiền của đơn đã huỷ xử lý ở màn Hoàn tiền cọc.'];
                }

                /* ĐIỀU KIỆN `order_id IS NULL` NHẮC LẠI TRONG CÂU LỆNH.

                   Phép kiểm ở trên đọc bằng chiTiet(), và giữa lúc ấy với lúc
                   này có một khe: hai nhân viên cùng mở một dòng 'no_order' và
                   cùng bấm Gắn với hai mã đơn khác nhau. Không nhắc lại thì
                   người bấm sau ghi đè người bấm trước — im lặng, trên một dòng
                   sổ tiền. */
                $doi = Database::execute(
                    'UPDATE sepay_transactions
                        SET order_id = :oid, order_code = :ma, applied = :nhan'
                    . (self::coGanTay() ? ', gan_boi = :boi, gan_luc = NOW()' : '') .
                    ' WHERE id = :id AND order_id IS NULL',
                    self::coGanTay()
                        ? ['oid' => $don['id'], 'ma' => $ma, 'nhan' => $nhan,
                           'boi' => $actorId, 'id' => $id]
                        : ['oid' => $don['id'], 'ma' => $ma, 'nhan' => $nhan, 'id' => $id]
                );

                if ($doi === 0) {
                    return ['ok' => false, 'error' =>
                        'Giao dịch này vừa được người khác gắn. Tải lại trang để xem.'];
                }

                /* ─────────────────────────────────────────────────────────────
                   CỘNG DỒN — TRONG CÙNG TRANSACTION VÀ SAU CÂU UPDATE.

                   Sau, vì phép cộng phải nhìn thấy chính khoản vừa gắn. Cùng
                   transaction, vì "gắn xong mà chưa kịp tính lại" để lại đúng
                   cái trạng thái mà cả màn hình này sinh ra để dọn: tiền đã
                   khớp đơn mà đơn vẫn hiện chưa thanh toán.

                   `transfer_type = 'in'` — tiền ra không phải khoản khách trả.
                   Đọc lại bằng câu SUM chứ không cộng tay trong PHP: dòng vừa
                   ghi ở trên đã nằm trong transaction này, và để CSDL cộng thì
                   không có đường nào lệch. */
                /* TIỀN RA BỊ TRỪ ĐI, KHÔNG BỊ BỎ QUA.

                   Bản đầu lọc `transfer_type = 'in'` với lý "tiền ra không
                   phải khoản khách trả". Câu ấy đúng khi nói về NHÃN của một
                   dòng, nhưng sai hẳn khi nói về một SỐ DƯ — mà đây đúng là
                   một số dư.

                   handle() gắn `order_id` cho MỌI giao dịch đọc được mã đơn,
                   kể cả chiều ra. Nên một khoản hoàn 500.000₫ cho đơn (chỉnh
                   giá, thu nhầm, hoàn một phần) nằm ngay trong bảng, gắn đúng
                   đơn ấy, và bị phép cộng cũ vứt đi.

                   Ca hỏng: đơn 4.400.000₫, khách trả cọc 1.320.000₫, cửa hàng
                   hoàn lại 500.000₫ vì tính dư, rồi khách chuyển 3.080.000₫ sai
                   nội dung. Nhân viên gắn khoản cuối. Cộng kiểu cũ ra
                   4.400.000₫ → đơn thành 'đã thanh toán', trong khi cửa hàng
                   thực nhận 3.900.000₫ và khách còn nợ 500.000₫. Dòng nhật ký
                   ghi lại đúng con số sai ấy thành văn bản.

                   Phép trừ ở đây KHÔNG đụng tới nhãn của dòng tiền ra — chúng
                   vẫn là 'ignored', vẫn không phải khoản khách trả. Chỉ số dư
                   mới cần cả hai chiều. */
                $daNhan = (int) Database::fetchValue(
                    "SELECT COALESCE(SUM(
                                CASE WHEN transfer_type = 'in' THEN amount ELSE -amount END
                            ), 0)
                       FROM sepay_transactions
                      WHERE order_id = :oid",
                    ['oid' => $don['id']]
                );

                $tong = (int) $donKhoa['total'];
                $coc  = (int) ($donKhoa['deposit_amount'] ?? 0);

                if ($daNhan >= $tong) {
                    OrderModel::markPaid((string) $donKhoa['id']);
                } elseif ($coc > 0 && $daNhan >= $coc) {
                    OrderModel::markDepositPaid((string) $donKhoa['id']);
                }

                /* KHÔNG CÓ NHÁNH ELSE, và đó là chủ ý: cộng dồn vẫn chưa đủ cọc
                   thì đơn ĐỨNG YÊN ở 'unpaid'. Không có đường nào kéo một đơn
                   lùi lại — markPaid/markDepositPaid đều mang điều kiện trạng
                   thái trong câu UPDATE nên chúng chỉ tiến, không lùi.

                   Dòng sổ vẫn được gắn và vẫn hiện trong "Thu một phần", nên
                   khoản tiền không biến mất khỏi tầm mắt ai. */

                return [
                    'ok'      => true,
                    'da_nhan' => $daNhan,
                    'tong'    => $tong,
                ];
            });

            if (!$ketQua['ok']) {
                return $ketQua;
            }
        } catch (Throwable $e) {
            error_log('[SePay] Không gắn được giao dịch ' . $id . ': ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không gắn được giao dịch, vui lòng thử lại.'];
        }

        /* ĐỌC LẠI TRẠNG THÁI ĐƠN SAU KHI COMMIT, không đoán từ phép so ở trên.

           markPaid() và markDepositPaid() đều có thể là lệnh không làm gì (đơn
           đã ở trạng thái ấy rồi), và nơi gọi cần biết trạng thái THẬT để in ra
           câu thông báo. Đoán thì có ngày câu ấy nói sai về tiền. */
        $sau = OrderModel::find((string) $don['id']);

        /* VẾT KIỂM TOÁN — SNFR-11, mã 'sepay.link_order' đã có sẵn trong
           AuditLogModel::ACTIONS từ trước, nhóm "Đơn hàng và tiền".

           GHI Ở MODEL, KHÔNG Ở CONTROLLER — cùng lý lẽ đã ghi dài ở
           OrderModel::ghiVetTien(): thao tác này ra tiền, và luật "ra tiền thì
           phải có vết" không được phụ thuộc vào việc nơi gọi có nhớ hay không.

           NGOÀI TRANSACTION, sau khi commit: dòng vết nói về một việc ĐÃ XONG.
           Ghi trong transaction thì một lần cuộn lại xoá luôn vết của chính
           lần cuộn ấy — mà đó là lần đáng đọc nhất.

           user_id lấy CHỦ ĐƠN chứ không NULL: màn Lịch sử thao tác dựng liên
           kết sang hồ sơ khách từ cột ấy, và đây là một thao tác về tiền của
           một khách cụ thể. NULL với đơn của khách vãng lai — đúng, họ không
           có hồ sơ nào để gắn.

           Câu chữ mang CẢ HAI con số: số tiền của khoản vừa gắn, và tổng đã
           nhận sau khi cộng dồn. Người đọc lại vết sáu tháng sau đang hỏi "vì
           sao đơn này thành đã thanh toán" — chỉ một trong hai con số thì họ
           phải đi dựng lại phép cộng bằng tay. */
        AuditLogModel::write(
            $don['user_id'] ?? null,
            'sepay.link_order',
            sprintf(
                'Gắn giao dịch #%d (%s) vào đơn %s — tổng đã nhận %s / %s',
                (int) $gd['sepay_id'],
                money($tien),
                $ma,
                money((int) $ketQua['da_nhan']),
                money($total)
            )
        );

        return [
            'ok'             => true,
            'code'           => $ma,
            'payment_status' => (string) ($sau['payment_status'] ?? 'unpaid'),
            'da_nhan'        => $ketQua['da_nhan'],
            'tong'           => $ketQua['tong'],
        ];
    }
}
