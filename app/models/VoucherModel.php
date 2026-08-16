<?php

/**
 * VoucherModel — mã ưu đãi (/tai-khoan?muc=uu-dai).
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

    /**
     * Ưu đãi CÒN DÙNG ĐƯỢC của một khách.
     *
     * Ba điều kiện lọc, thiếu cái nào cũng ra mã không dùng được:
     *   uv.used_at IS NULL   khách chưa dùng
     *   v.is_active = 1      chương trình chưa bị quản trị tắt
     *   v.expires_at >= hôm nay (hoặc NULL = không hạn)
     *
     * So sánh bằng CURDATE() chứ không phải NOW(): `expires_at` là kiểu DATE,
     * mã hết hạn 31/08 phải dùng được tới hết ngày 31/08.
     */
    public static function forUser(string $userId): array
    {
        return Database::fetchAll(
            'SELECT v.*, uv.granted_at
               FROM user_vouchers uv
               JOIN vouchers v ON v.id = uv.voucher_id
              WHERE uv.user_id = :uid
                AND uv.used_at IS NULL
                AND v.is_active = 1
                AND (v.expires_at IS NULL OR v.expires_at >= CURDATE())
              ORDER BY v.expires_at IS NULL, v.expires_at ASC, v.title ASC',
            ['uid' => $userId]
        );
    }

    /**
     * Số ưu đãi còn dùng được — cho huy hiệu đếm ở cột trái.
     *
     * Đếm bằng câu SQL riêng chứ không count(forUser()): cột trái hiện trên
     * MỌI mục của trang tài khoản, nên câu này chạy cả khi khách đang xem đơn
     * hàng và không cần tới nội dung từng mã.
     */
    public static function countForUser(string $userId): int
    {
        $row = Database::fetchOne(
            'SELECT COUNT(*) AS n
               FROM user_vouchers uv
               JOIN vouchers v ON v.id = uv.voucher_id
              WHERE uv.user_id = :uid
                AND uv.used_at IS NULL
                AND v.is_active = 1
                AND (v.expires_at IS NULL OR v.expires_at >= CURDATE())',
            ['uid' => $userId]
        );

        return (int) ($row['n'] ?? 0);
    }

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
    // ÁP MÃ Ở GIỎ HÀNG
    // ========================================================================

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
     * Phát một mã cho một khách.
     *
     * INSERT IGNORE thay vì kiểm tra tồn tại rồi rẽ nhánh: khoá chính ghép
     * (user_id, voucher_id) đã cấm trùng, nên phát lại lần hai là câu lệnh
     * không làm gì chứ không phải lỗi.
     */
    public static function grant(string $userId, string $voucherId): void
    {
        Database::execute(
            'INSERT IGNORE INTO user_vouchers (user_id, voucher_id) VALUES (:uid, :vid)',
            ['uid' => $userId, 'vid' => $voucherId]
        );
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
