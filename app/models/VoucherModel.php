<?php

/**
 * VoucherModel — mã giảm giá.
 *
 * Dùng ở ô nhập mã trong giỏ hàng / thanh toán, và ở trang quản trị mã.
 * KHÔNG còn phục vụ trang tài khoản: mục "Ưu đãi của tôi" đã gỡ.
 *
 * Dựng theo mục "Ưu đãi của tôi" của "Vin Eyewear Account.dc.html".
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI BẢNG, KHÔNG PHẢI MỘT
 *
 *   vouchers       định nghĩa chương trình khuyến mãi — dùng chung cho mọi khách
 *   user_vouchers  chương trình nào đã phát cho ai, và khách đã dùng chưa
 *
 * Xem ghi chú trong schema.sql để biết vì sao không gộp làm một.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class VoucherModel extends BaseModel
{
    protected static string $table = 'vouchers';

    /** Kiểu giảm giá. Khoá dùng trong cột `discount_type`. */
    public const TYPES = [
        'percent'  => 'Giảm theo phần trăm',
        'amount'   => 'Giảm số tiền',
        'shipping' => 'Miễn phí vận chuyển',
    ];

    /* forUser() và countForUser() ĐÃ BỎ.
       Hai hàm đó chỉ phục vụ mục "Ưu đãi của tôi" trong trang tài khoản, mà
       mục đó đã gỡ. Phần còn lại của model vẫn chạy: evaluate() / apply() /
       consume() cho ô nhập mã ở giỏ hàng và bước thanh toán, adminList() /
       grantToAll() / markUsed() cho trang quản trị mã giảm giá. */

    // ========================================================================
    // KHU QUẢN TRỊ
    // ========================================================================

    /**
     * Toàn bộ mã, kèm hai con số mà người quản trị cần thấy trước khi xoá:
     *
     *   order_count   số ĐƠN đã dùng mã này — đơn đã phát sinh, xoá mã là mất
     *                 dấu vết trên hoá đơn cũ.
     *   holder_count  số khách đã được PHÁT mã (chỉ có nghĩa với mã riêng).
     *
     * Hai câu con thay vì hai JOIN: JOIN cả hai bảng cùng lúc sẽ nhân dòng lên
     * và đếm sai cả hai (một mã phát cho 5 người, dùng ở 3 đơn -> 15 dòng).
     */
    public static function adminList(): array
    {
        return Database::fetchAll(
            'SELECT v.*,
                    (SELECT COUNT(*) FROM orders o WHERE o.voucher_id = v.id)        AS order_count,
                    (SELECT COUNT(*) FROM user_vouchers uv WHERE uv.voucher_id = v.id) AS holder_count
               FROM vouchers v
              ORDER BY v.is_active DESC, v.created_at DESC'
        );
    }

    /**
     * Phát một mã cho MỌI tài khoản khách hiện có.
     *
     * INSERT ... SELECT chứ không phải vòng lặp PHP: một câu lệnh cho vài nghìn
     * khách, và IGNORE lo phần người đã được phát rồi (khoá chính ghép chặn trùng).
     *
     * @return int số người vừa được phát thêm
     */
    public static function grantToAll(string $voucherId): int
    {
        return Database::execute(
            'INSERT IGNORE INTO user_vouchers (user_id, voucher_id)
             SELECT u.id, :vid FROM users u',
            ['vid' => $voucherId]
        );
    }

    // ========================================================================
    // MÃ QUÀ TẶNG CHO KHÁCH CHUYỂN KHOẢN ĐỦ
    //
    // Đơn có cắt tròng được chọn: chuyển 30% tiền cọc, hoặc chuyển đủ 100%.
    // Cửa hàng khuyến khích vế thứ hai bằng một mã giảm giá cho lần mua sau.
    //
    // "Mã nào đang làm quà tặng" là một cờ trên chính bảng `vouchers`
    // (is_reward), không phải một thiết lập riêng — xem migration
    // 2026-08-22-ma-thuong-chuyen-du.sql.
    // ========================================================================

    /**
     * Mã đang được dùng làm quà tặng, hoặc null.
     *
     * Lọc luôn theo hạn và theo lượt: một mã hết hạn mà vẫn được tặng thì
     * khách nhận được một lời hứa không tiêu được — tệ hơn là không tặng gì.
     * Nhờ vậy cửa hàng chỉ cần đặt `expires_at`, không phải nhớ vào tắt cờ.
     */
    public static function reward(): ?array
    {
        /* ─────────────────────────────────────────────────────────────────
           CHƯA CHẠY MIGRATION THÌ COI NHƯ KHÔNG CÓ QUÀ, ĐỪNG ĐỔ TRANG.

           Hai hàm đọc is_reward được gọi từ TRANG THANH TOÁN và TRANG BIÊN
           NHẬN — hai trang mà một lỗi 500 nghĩa là khách không đặt được hàng
           hoặc không thấy được đơn vừa trả tiền.

           Mã lên máy chủ trước migration là chuyện ĐÃ XẢY RA ở dự án này:
           workflow deploy chạy tự động khi push, còn migration thì phải có
           người vào phpMyAdmin dán tay. Giữa hai mốc đó, cột `is_reward`
           chưa tồn tại và câu SELECT này trả SQLSTATE[42S22].

           Nuốt lỗi ở đây là ĐÚNG chứ không phải giấu: mất quà tặng thì khách
           vẫn mua hàng bình thường, mất trang thanh toán thì không.
           ───────────────────────────────────────────────────────────────── */
        try {
            return Database::fetchOne(
                'SELECT * FROM vouchers
                  WHERE is_reward = 1
                    AND is_active = 1
                    AND (expires_at IS NULL OR expires_at >= CURDATE())
                    AND (max_uses IS NULL OR used_count < max_uses)
                  LIMIT 1'
            );
        } catch (Throwable $e) {
            error_log('[reward] Không đọc được mã quà tặng: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Chỉ MỘT mã được làm quà tặng. Gọi trước khi bật cờ cho một mã.
     */
    public static function clearRewardFlag(?string $exceptId = null): void
    {
        Database::execute(
            'UPDATE vouchers SET is_reward = 0
              WHERE is_reward = 1' . ($exceptId === null ? '' : ' AND id <> :id'),
            $exceptId === null ? [] : ['id' => $exceptId]
        );
    }

    /**
     * Phát một mã cho MỘT khách.
     *
     * ─────────────────────────────────────────────────────────────────────
     * PHÁT LẠI ĐƯỢC, VÀ ĐÓ LÀ CHỦ Ý
     *
     * Khoá chính của `user_vouchers` là (user_id, voucher_id), nên mỗi khách
     * giữ được đúng MỘT bản của mỗi mã. Khách chuyển khoản đủ lần thứ hai mà
     * lần trước đã tiêu mất quà rồi thì INSERT trần sẽ trượt khoá và họ không
     * nhận được gì — im lặng, không ai biết.
     *
     * ON DUPLICATE KEY nạp lại: xoá used_at và dời granted_at. Nghĩa là mỗi
     * lần chuyển đủ đều có quà, nhưng KHÔNG cộng dồn — giữ tối đa một mã chưa
     * dùng tại một thời điểm. Cộng dồn thì phải đổi khoá chính của bảng, và
     * "mười mã cùng loại trong ví" cũng không phải thứ cửa hàng muốn.
     *
     * KHÔNG đụng nếu khách ĐANG giữ một bản chưa dùng: điều kiện used_at IS
     * NOT NULL ở mệnh đề UPDATE. Không có nó thì mỗi lần gọi lại sẽ dời
     * granted_at của một mã đang nằm sẵn trong ví, chẳng để làm gì.
     * ─────────────────────────────────────────────────────────────────────
     *
     * @return bool khách có nhận được gì trong lần gọi này không
     */
    public static function grantTo(string $userId, string $voucherId): bool
    {
        return Database::execute(
            'INSERT INTO user_vouchers (user_id, voucher_id, granted_at)
             VALUES (:uid, :vid, NOW())
             ON DUPLICATE KEY UPDATE
                 granted_at = IF(used_at IS NOT NULL, NOW(), granted_at),
                 used_at    = NULL',
            ['uid' => $userId, 'vid' => $voucherId]
        ) > 0;
    }

    /**
     * Mã quà tặng khách đang giữ mà CHƯA dùng, hoặc null.
     *
     * Dùng ở trang biên nhận để nói "bạn vừa được tặng mã X". Hỏi lại CSDL
     * chứ không tin vào kết quả của grantTo(): trang biên nhận mở được nhiều
     * lần, và lần thứ hai thì grantTo() đã chạy từ trước.
     */
    public static function rewardHeldBy(string $userId): ?array
    {
        try {
            return Database::fetchOne(
                'SELECT v.* FROM vouchers v
                   JOIN user_vouchers uv ON uv.voucher_id = v.id
                  WHERE uv.user_id = :uid
                    AND uv.used_at IS NULL
                    AND v.is_reward = 1
                    AND v.is_active = 1
                    AND (v.expires_at IS NULL OR v.expires_at >= CURDATE())
                  LIMIT 1',
                ['uid' => $userId]
            );
        } catch (Throwable $e) {
            /* Cùng lý do với reward() ngay trên: chưa chạy migration thì
               trang biên nhận vẫn phải mở được. */
            error_log('[reward] Không đọc được ví mã của khách: ' . $e->getMessage());

            return null;
        }
    }

    // ========================================================================
    // ÁP MÃ Ở GIỎ HÀNG
    // ========================================================================

    /**
     * Danh sách mã khách CHỌN ĐƯỢC, cho ô "Mã giảm giá" ở trang thanh toán.
     *
     * "Vin Eyewear Checkout.dc.html" không vẽ ô gõ mã như giỏ hàng, mà vẽ một
     * danh sách thả xuống: khách bấm chọn một mã trong đó. Muốn vẽ được danh
     * sách thì phải có danh sách — evaluate() chỉ trả lời cho MỘT mã đã biết.
     *
     * Lọc ngay trong câu SQL, không lọc bằng PHP sau khi lấy hết: bảng này sẽ
     * dài ra theo từng đợt khuyến mãi, mà một trang thanh toán chỉ cần vài mã
     * đang chạy.
     *
     * KHÔNG lọc theo `min_order` ở đây. Mã chưa đủ điều kiện vẫn hiện, kèm
     * dòng "Đơn tối thiểu …" — đó là thông tin bán hàng (mua thêm chút nữa là
     * được giảm), giấu đi thì khách không biết mình đang bỏ lỡ cái gì.
     * evaluate() vẫn là nơi chặn thật khi khách bấm chọn.
     *
     * @param  string|null $userId null = khách vãng lai, chỉ thấy mã công khai
     * @return array<int, array<string, mixed>>
     */
    public static function selectable(?string $userId = null): array
    {
        // Mã RIÊNG chỉ hiện cho đúng người được phát và chưa dùng. Khách vãng
        // lai không có dòng nào trong user_vouchers nên nhánh này bỏ hẳn — bớt
        // một câu con vô nghĩa.
        $ownClause = $userId === null
            ? ''
            : ' OR v.id IN (SELECT uv.voucher_id
                              FROM user_vouchers uv
                             WHERE uv.user_id = :uid AND uv.used_at IS NULL)';

        return Database::fetchAll(
            'SELECT v.*
               FROM vouchers v
              WHERE v.is_active = 1
                AND (v.expires_at IS NULL OR v.expires_at >= CURDATE())
                AND (v.max_uses IS NULL OR v.used_count < v.max_uses)
                AND (v.is_public = 1' . $ownClause . ')
              ORDER BY v.min_order ASC, v.created_at DESC',
            $userId === null ? [] : ['uid' => $userId]
        );
    }

    /**
     * Tra một mã khách vừa gõ và kiểm xem có dùng được không.
     *
     * ─────────────────────────────────────────────────────────────────────
     * HÀM NÀY LÀ NƠI DUY NHẤT QUYẾT ĐỊNH "MÃ CÓ DÙNG ĐƯỢC KHÔNG"
     *
     * Giỏ hàng gọi nó để hiện số tiền giảm, và OrderModel::place() gọi LẠI
     * nó ngay trước khi ghi đơn. Gọi hai lần là cố ý: giữa lúc khách áp mã và
     * lúc bấm đặt hàng, mã có thể hết hạn, bị tắt, hoặc hết lượt. Session chỉ
     * nhớ CHUỖI mã khách đã gõ, không bao giờ nhớ số tiền đã giảm — nhớ số
     * tiền là mở đường cho việc sửa session để tự cho mình giảm giá.
     * ─────────────────────────────────────────────────────────────────────
     *
     * @param  string      $code     mã khách gõ (không phân biệt hoa thường)
     * @param  int         $subtotal tạm tính của các dòng ĐANG CHỌN
     * @param  string|null $userId   null = khách vãng lai
     * @return array{ok:bool, error?:string, voucher?:array}
     */
    public static function evaluate(string $code, int $subtotal, ?string $userId = null): array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return ['ok' => false, 'error' => 'Vui lòng nhập mã giảm giá.'];
        }

        $voucher = static::findBy('code', $code);

        // Thông điệp GIỐNG NHAU cho "không tồn tại" và "đã tắt": phân biệt hai
        // trường hợp biến ô nhập mã thành công cụ dò xem cửa hàng đang có
        // những chương trình nào chưa công bố.
        if ($voucher === null || (int) $voucher['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'Mã không hợp lệ hoặc đã hết hạn.'];
        }

        if ($voucher['expires_at'] !== null && $voucher['expires_at'] < date('Y-m-d')) {
            return ['ok' => false, 'error' => 'Mã không hợp lệ hoặc đã hết hạn.'];
        }

        if ($voucher['max_uses'] !== null
            && (int) $voucher['used_count'] >= (int) $voucher['max_uses']) {
            return ['ok' => false, 'error' => 'Mã đã hết lượt sử dụng.'];
        }

        // Mã riêng: phải đã được phát cho đúng người này, và chưa dùng.
        if ((int) $voucher['is_public'] !== 1) {
            if ($userId === null) {
                return ['ok' => false, 'error' => 'Mã này chỉ dùng được khi đã đăng nhập.'];
            }

            $held = Database::fetchOne(
                'SELECT used_at FROM user_vouchers WHERE user_id = :uid AND voucher_id = :vid',
                ['uid' => $userId, 'vid' => $voucher['id']]
            );

            if ($held === null) {
                return ['ok' => false, 'error' => 'Mã không hợp lệ hoặc đã hết hạn.'];
            }

            if ($held['used_at'] !== null) {
                return ['ok' => false, 'error' => 'Bạn đã dùng mã này rồi.'];
            }
        }

        // Điều kiện đơn tối thiểu kiểm SAU CÙNG trong nhóm kiểm mã: đây là lỗi
        // khách sửa được (mua thêm), nên thông điệp phải nói rõ còn thiếu bao nhiêu.
        if ($subtotal < (int) $voucher['min_order']) {
            return [
                'ok'    => false,
                'error' => sprintf(
                    'Mã áp dụng cho đơn từ %s. Đơn của bạn còn thiếu %s.',
                    money((int) $voucher['min_order']),
                    money((int) $voucher['min_order'] - $subtotal)
                ),
            ];
        }

        return ['ok' => true, 'voucher' => $voucher];
    }

    /**
     * Số tiền giảm và việc miễn phí ship của một mã ĐÃ hợp lệ.
     *
     * Tách khỏi evaluate() vì phí ship phụ thuộc hình thức nhận hàng, mà giỏ
     * hàng chưa biết khách sẽ chọn nhận tại cửa hàng hay giao tận nơi — nên
     * cùng một mã cho ra hai kết quả ở hai trang. evaluate() trả lời "mã có
     * dùng được không", hàm này trả lời "giảm bao nhiêu, trong hoàn cảnh nào".
     *
     * @return array{discount:int, freeShipping:bool}
     */
    public static function apply(array $voucher, int $subtotal, int $shippingFee): array
    {
        $value = (int) $voucher['discount_value'];

        switch ($voucher['discount_type']) {
            case 'percent':
                $discount = (int) floor($subtotal * $value / 100);

                // max_discount chỉ có nghĩa với 'percent' — chặn trần số tiền
                if ($voucher['max_discount'] !== null) {
                    $discount = min($discount, (int) $voucher['max_discount']);
                }

                return ['discount' => min($discount, $subtotal), 'freeShipping' => false];

            case 'amount':
                // Không bao giờ giảm quá tạm tính: total âm là hoá đơn vô nghĩa
                // và sẽ thành số tiền cửa hàng nợ khách.
                return ['discount' => min($value, $subtotal), 'freeShipping' => false];

            case 'shipping':
                // Mã miễn ship KHÔNG ghi vào cột discount — nó tác động lên phí
                // vận chuyển. Ghi vào discount thì subtotal + ship − discount
                // vẫn ra đúng total, nhưng dòng "Phí vận chuyển" trên hoá đơn
                // lại hiện một khoản khách không hề phải trả.
                return ['discount' => 0, 'freeShipping' => $shippingFee > 0];
        }

        return ['discount' => 0, 'freeShipping' => false];
    }

    /**
     * Ghi nhận một lần dùng, gọi khi đơn đã đặt thành công.
     *
     * Phải nằm trong CÙNG transaction với việc tạo đơn — xem OrderModel::place.
     * Tách rời thì một lỗi ở giữa để lại mã đã trừ lượt mà không có đơn nào.
     */
    public static function consume(string $voucherId, ?string $userId): void
    {
        Database::execute(
            'UPDATE vouchers SET used_count = used_count + 1 WHERE id = :id',
            ['id' => $voucherId]
        );

        if ($userId !== null) {
            // Mã công khai thì khách không có dòng nào ở đây, câu này sửa 0
            // dòng — đúng như mong đợi, không phải lỗi.
            self::markUsed($userId, $voucherId);
        }
    }

    /**
     * Đánh dấu đã dùng.
     *
     * Điều kiện `used_at IS NULL` nằm trong chính câu UPDATE chứ không kiểm
     * trước bằng SELECT: hai request đồng thời của cùng một khách sẽ cùng đọc
     * ra NULL và cùng cho là mã còn dùng được. Ở đây câu thứ hai sửa 0 dòng.
     *
     * @return bool true nếu lần gọi này là lần đánh dấu thành công
     */
    public static function markUsed(string $userId, string $voucherId): bool
    {
        return Database::execute(
            'UPDATE user_vouchers SET used_at = NOW()
              WHERE user_id = :uid AND voucher_id = :vid AND used_at IS NULL',
            ['uid' => $userId, 'vid' => $voucherId]
        ) > 0;
    }
}
