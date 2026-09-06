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
}
