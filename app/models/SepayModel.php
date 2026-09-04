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

    // ========================================================================
    // ĐỐI SOÁT TAY HAI BƯỚC — X13, chốt lại 04/09/2026
    //
    // Đọc khối chú thích ở schema.sql (bảng sepay_transactions) trước khi sửa
    // bất cứ hàm nào dưới đây. Tóm tắt: tiền về mà không mang mã đơn thì ai đó
    // phải gán tay, và X13 tách việc ấy làm HAI NGƯỜI — nhân viên gán, Quản lý
    // cơ sở xác nhận. Người gán không được tự xác nhận.
    // ========================================================================

    /** Trạng thái trung gian: đã gán, đang chờ Quản lý cơ sở duyệt. */
    public const CHO_XAC_NHAN = 'cho_xac_nhan';

    /** Lý do bắt buộc dài tối thiểu bao nhiêu. Cùng mức với các luồng khác. */
    public const LY_DO_TOI_THIEU = 10;

    /** CSDL đã có bộ cột hai bước chưa (migration 2026-09-09). */
    public static function coHaiBuoc(): bool
    {
        return Database::columnExists('sepay_transactions', 'gan_boi');
    }

    /**
     * Các dòng CẦN NGƯỜI NHÌN, mới nhất trước.
     *
     * Ba nhóm, không phải một:
     *   no_order      tiền về, không đoán được đơn nào  -> chờ bước 1
     *   partial       đã khớp đơn nhưng chuyển thiếu    -> chờ người quyết
     *   cho_xac_nhan  đã gán, chờ Quản lý duyệt         -> chờ bước 2
     *
     * 'partial' nằm chung ở đây vì handle() cố tình không tự xử lý nó: khách
     * chuyển thiếu là chuyện phải có người nhìn. Nếu màn này chỉ hiện
     * 'no_order' thì những dòng ấy không xuất hiện ở đâu cả.
     */
    public static function canDoiSoat(int $limit = 100): array
    {
        if (!self::available()) {
            return [];
        }

        $cot = self::coHaiBuoc()
            ? 't.*, ng.full_name AS gan_ten, nx.full_name AS xac_nhan_ten'
            : 't.*, NULL AS gan_ten, NULL AS xac_nhan_ten';

        $join = self::coHaiBuoc()
            ? ' LEFT JOIN profiles ng ON ng.id = t.gan_boi
                LEFT JOIN profiles nx ON nx.id = t.xac_nhan_boi'
            : '';

        return Database::fetchAll(
            'SELECT ' . $cot . ', o.code AS ma_don, o.total AS don_tong,
                    o.payment_status AS don_tt_tien
               FROM sepay_transactions t
               LEFT JOIN orders o ON o.id = t.order_id'
            . $join .
            " WHERE t.applied IN ('no_order', 'partial', '" . self::CHO_XAC_NHAN . "')
                AND t.transfer_type = 'in'
              ORDER BY t.transaction_date DESC, t.created_at DESC
              LIMIT " . max(1, min(500, $limit))
        );
    }

    /** Bao nhiêu dòng đang chờ — để đeo huy hiệu lên mục điều hướng. */
    public static function demCanDoiSoat(): int
    {
        if (!self::available()) {
            return 0;
        }

        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM sepay_transactions
              WHERE applied IN ('no_order', 'partial', '" . self::CHO_XAC_NHAN . "')
                AND transfer_type = 'in'"
        );
    }

    /**
     * BƯỚC 1 — nhân viên gán một giao dịch vào đơn.
     *
     * KHÔNG ĐỘNG TỚI TIỀN CỦA ĐƠN. Chỉ ghi "người này cho rằng khoản này thuộc
     * đơn kia, vì lý do này". Đơn vẫn `unpaid` cho tới khi có người thứ hai
     * duyệt — đó là toàn bộ điểm của X13, và cũng là lý do hàm này không gọi
     * markPaid() dù nó biết thừa số tiền.
     *
     * @return array{ok: bool, error?: string, ma_don?: string}
     */
    public static function gan(string $id, string $maDon, string $lyDo, string $actorId): array
    {
        if (!self::coHaiBuoc()) {
            return ['ok' => false, 'error' =>
                'Chưa nâng cấp cơ sở dữ liệu. Chạy '
                . 'database/migrations/2026-09-09-giao-dich-chua-khop.sql rồi thử lại.'];
        }

        $gd = self::find($id);

        if ($gd === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy giao dịch.'];
        }

        if ((string) $gd['applied'] === self::CHO_XAC_NHAN) {
            return ['ok' => false, 'error' =>
                'Giao dịch này đã được gán và đang chờ Quản lý cơ sở xác nhận.'];
        }

        /* Đã áp vào đơn rồi thì không gán lại. Gán một khoản ĐÃ cộng vào đơn A
           sang đơn B nghĩa là đơn A bỗng thiếu tiền mà không ai biết — và
           đường sửa đúng cho ca đó là hoàn tiền, không phải sửa sổ. */
        if (in_array((string) $gd['applied'], ['paid', 'deposit_paid'], true)) {
            return ['ok' => false, 'error' =>
                'Giao dịch này đã được áp vào một đơn. Không gán lại được.'];
        }

        $lyDo = trim($lyDo);

        if (utf8Length($lyDo) < self::LY_DO_TOI_THIEU) {
            return ['ok' => false, 'error' =>
                'Phải ghi lý do gán, tối thiểu ' . self::LY_DO_TOI_THIEU . ' ký tự. '
                . 'Người duyệt ở bước sau chỉ có câu này để hiểu vì sao bạn cho rằng hai thứ là một.'];
        }

        $maDon = strtoupper(trim($maDon));
        $don   = $maDon !== '' ? OrderModel::firstWhere(['code' => $maDon]) : null;

        if ($don === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy đơn hàng mang mã ' . $maDon . '.'];
        }

        if ((string) $don['status'] === 'cancelled') {
            return ['ok' => false, 'error' =>
                'Đơn ' . $maDon . ' đã huỷ. Mở lại đơn trước, hoặc hoàn tiền cho khách.'];
        }

        Database::execute(
            'UPDATE sepay_transactions
                SET order_id = :oid, order_code = :ocode, applied = :tt,
                    gan_boi = :by, gan_luc = NOW(), gan_ly_do = :ly
              WHERE id = :id',
            [
                'oid'   => $don['id'],
                'ocode' => $maDon,
                'tt'    => self::CHO_XAC_NHAN,
                'by'    => $actorId,
                'ly'    => utf8Substr($lyDo, 0, 255),
                'id'    => $id,
            ]
        );

        AuditLogModel::write(
            $don['user_id'] ?? null,
            'sepay.assign',
            sprintf('Gán giao dịch %s vào đơn %s — lý do: %s',
                (string) $gd['reference_code'] ?: (string) $gd['sepay_id'],
                $maDon,
                utf8Substr($lyDo, 0, 150))
        );

        return ['ok' => true, 'ma_don' => $maDon];
    }

    /**
     * BƯỚC 2 — Quản lý cơ sở xác nhận. ĐÂY là chỗ tiền vào đơn.
     *
     * Quyền kiểm ở controller; hàm này giữ hai luật không được để lọt qua bất
     * kỳ đường gọi nào:
     *
     *   1. NGƯỜI GÁN KHÔNG TỰ XÁC NHẬN. Kiểm ở đây chứ không chỉ ở giao diện:
     *      ẩn cái nút đi thì một cú POST dựng tay vẫn đi lọt, mà đây đúng là
     *      thao tác đáng để ai đó dựng tay.
     *   2. Trạng thái tiền của đơn tính LẠI từ tổng đã nhận, không tin vào số
     *      của riêng giao dịch này — khách chuyển làm nhiều lần là chuyện
     *      thường, và cộng dồn mới ra câu trả lời đúng.
     *
     * @return array{ok: bool, error?: string, trang_thai?: string}
     */
    public static function xacNhan(string $id, string $lyDo, string $actorId): array
    {
        if (!self::coHaiBuoc()) {
            return ['ok' => false, 'error' => 'Chưa nâng cấp cơ sở dữ liệu.'];
        }

        $gd = self::find($id);

        if ($gd === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy giao dịch.'];
        }

        if ((string) $gd['applied'] !== self::CHO_XAC_NHAN) {
            return ['ok' => false, 'error' =>
                'Giao dịch này không ở trạng thái chờ xác nhận.'];
        }

        // Luật 1 — xem khối chú thích trên hàm.
        if ((string) ($gd['gan_boi'] ?? '') === $actorId) {
            return ['ok' => false, 'error' =>
                'Bạn là người đã gán giao dịch này nên không tự xác nhận được. '
                . 'X13 đòi hai người: một người gán, một người duyệt.'];
        }

        $lyDo = trim($lyDo);

        if (utf8Length($lyDo) < self::LY_DO_TOI_THIEU) {
            return ['ok' => false, 'error' =>
                'Phải ghi lý do xác nhận, tối thiểu ' . self::LY_DO_TOI_THIEU . ' ký tự.'];
        }

        $don = OrderModel::find((string) $gd['order_id']);

        if ($don === null) {
            return ['ok' => false, 'error' =>
                'Đơn gắn với giao dịch này không còn tồn tại. Gán lại vào đơn khác.'];
        }

        /* Luật 2 — cộng dồn mọi khoản ĐÃ vào đơn, kể cả khoản này (nó đã mang
           order_id từ bước 1 nên đã nằm trong tổng). */
        $daNhan = self::receivedFor((string) $don['id']);
        $tong   = (int) $don['total'];
        $coc    = (int) ($don['deposit_amount'] ?? 0);

        if ($daNhan >= $tong) {
            $trangThai = 'paid';
        } elseif ($coc > 0 && $daNhan >= $coc) {
            $trangThai = 'deposit_paid';
        } else {
            /* Vẫn chưa đủ cả cọc. Xác nhận vẫn có ý nghĩa — khoản tiền nay
               thuộc về đơn này trong sổ — nhưng KHÔNG đổi trạng thái tiền:
               đánh dấu "đã cọc" khi chưa đủ cọc là ghi sai vào đúng cột mà
               luật hoàn tiền đọc. */
            $trangThai = 'partial';
        }

        Database::transaction(static function () use ($id, $actorId, $lyDo, $trangThai, $don): void {
            Database::execute(
                'UPDATE sepay_transactions
                    SET applied = :tt, xac_nhan_boi = :by,
                        xac_nhan_luc = NOW(), xac_nhan_ly_do = :ly
                  WHERE id = :id',
                [
                    'tt' => $trangThai,
                    'by' => $actorId,
                    'ly' => utf8Substr($lyDo, 0, 255),
                    'id' => $id,
                ]
            );

            if ($trangThai === 'paid') {
                OrderModel::markPaid((string) $don['id']);
            } elseif ($trangThai === 'deposit_paid') {
                OrderModel::markDepositPaid((string) $don['id']);
            }
        });

        AuditLogModel::write(
            $don['user_id'] ?? null,
            'sepay.confirm',
            sprintf('Xác nhận gán giao dịch vào đơn %s (%s) — lý do: %s',
                (string) $don['code'],
                $trangThai,
                utf8Substr($lyDo, 0, 150))
        );

        return ['ok' => true, 'trang_thai' => $trangThai];
    }

    /**
     * BƯỚC 2, NHÁNH TỪ CHỐI — Quản lý cơ sở bác đề xuất gán.
     *
     * Trả dòng về 'no_order' để nó quay lại hàng chờ và người khác gán lại.
     * XOÁ luôn vết bước 1 khỏi các cột (`gan_*`) chứ không giữ: giữ lại thì
     * lần gán sau ghi đè và vết cũ mất theo cách khó đoán hơn. Vết đầy đủ của
     * cả hai lần vẫn nằm trong `customer_audit_logs` — đó là chỗ dành cho
     * lịch sử, còn sáu cột kia chỉ mô tả TRẠNG THÁI HIỆN TẠI.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function tuChoi(string $id, string $lyDo, string $actorId): array
    {
        if (!self::coHaiBuoc()) {
            return ['ok' => false, 'error' => 'Chưa nâng cấp cơ sở dữ liệu.'];
        }

        $gd = self::find($id);

        if ($gd === null || (string) $gd['applied'] !== self::CHO_XAC_NHAN) {
            return ['ok' => false, 'error' => 'Giao dịch này không ở trạng thái chờ xác nhận.'];
        }

        $lyDo = trim($lyDo);

        if (utf8Length($lyDo) < self::LY_DO_TOI_THIEU) {
            return ['ok' => false, 'error' =>
                'Phải ghi lý do từ chối, tối thiểu ' . self::LY_DO_TOI_THIEU . ' ký tự. '
                . 'Người gán cần biết vì sao để gán lại cho đúng.'];
        }

        $maDonCu = (string) ($gd['order_code'] ?? '');

        Database::execute(
            "UPDATE sepay_transactions
                SET applied = 'no_order', order_id = NULL, order_code = NULL,
                    gan_boi = NULL, gan_luc = NULL, gan_ly_do = NULL
              WHERE id = :id",
            ['id' => $id]
        );

        AuditLogModel::write(
            null,
            'sepay.reject',
            sprintf('Từ chối gán giao dịch vào đơn %s — lý do: %s',
                $maDonCu !== '' ? $maDonCu : '(không rõ)',
                utf8Substr($lyDo, 0, 150))
        );

        return ['ok' => true];
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
         * XÉT TRÊN TỔNG SỐ TIỀN ĐÃ VỀ, KHÔNG PHẢI TRÊN RIÊNG LẦN CHUYỂN NÀY
         *
         * Đơn đặt cọc gần như luôn được trả làm HAI lần: 30% lúc đặt, phần
         * còn lại lúc nhận. Nếu chỉ so lần chuyển hiện tại với tổng đơn thì
         * lần thứ hai (3.080.000 trên đơn 4.400.000) mãi mãi là 'partial' —
         * khách đã trả đủ tiền mà đơn vẫn treo ở "đã đặt cọc", và phải có
         * người vào bấm tay. Đúng cái việc mà SePay sinh ra để khỏi phải làm.
         *
         * Cộng dồn cũng lo luôn ca khách chia nhỏ vì hạn mức chuyển khoản
         * trong ngày.
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
            // Đã về trước đó + lần này. Lần này CHƯA nằm trong sổ (dòng sổ ghi
            // ngay dưới), nên cộng tay vào đây.
            $received = self::receivedFor((string) $order['id']) + $amount;

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

    /**
     * Tổng tiền ĐÃ VỀ cho một đơn, cộng từ sổ giao dịch.
     *
     * Chỉ đếm `transfer_type = 'in'`: tiền hoàn lại cho khách (hoặc phí trừ
     * vào tài khoản) không phải là tiền khách trả cho đơn này.
     *
     * Đếm cả dòng 'partial': đó vẫn là tiền thật đã về, chỉ là một mình nó
     * chưa đủ ngưỡng nào. Bỏ chúng ra thì hai lần chuyển thiếu cộng lại vẫn
     * mãi không thành đủ.
     */
    private static function receivedFor(string $orderId): int
    {
        return (int) Database::fetchValue(
            "SELECT COALESCE(SUM(amount), 0)
               FROM sepay_transactions
              WHERE order_id = :id AND transfer_type = 'in'",
            ['id' => $orderId]
        );
    }

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
