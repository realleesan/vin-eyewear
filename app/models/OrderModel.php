<?php

/**
 * OrderModel — đơn hàng.
 *
 * Port từ createOrder trong src/lib/shop.functions.ts.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT CHỖ CỐ Ý LÀM KHÁC BẢN GỐC: TRỪ TỒN KHO
 *
 * Bản Lovable KIỂM tồn kho trước khi tạo đơn nhưng không bao giờ TRỪ đi.
 * Hệ quả: stock_quantity đứng yên mãi mãi, nên phép kiểm ấy không có tác
 * dụng — bán được vô hạn một sản phẩm chỉ còn 1 cái trong kho.
 *
 * Ở đây trừ tồn kho ngay trong cùng transaction với việc tạo đơn, và dùng
 * câu UPDATE có điều kiện để hai người mua cùng lúc không cùng lấy được món
 * cuối cùng (xem ghi chú tại VariantModel::reserve).
 * ─────────────────────────────────────────────────────────────────────────────
 */

class OrderModel extends BaseModel
{
    protected static string $table = 'orders';

    public const DELIVERY_METHODS = ['pickup', 'shipping'];
    public const PAYMENT_METHODS  = ['cod', 'bank_transfer'];

    /** Trạng thái đơn, theo thứ tự vòng đời. */
    public const STATUSES = [
        'new'       => 'Mới',
        'confirmed' => 'Đã xác nhận',
        'preparing' => 'Đang chuẩn bị',
        'shipping'  => 'Đang giao',
        'completed' => 'Hoàn tất',
        'cancelled' => 'Đã huỷ',
    ];

    /**
     * Trạng thái TIỀN — không liên quan tới STATUSES ở trên.
     *
     * STATUSES là vòng đời giao vận (đã xác nhận → đang giao → hoàn tất);
     * cột này chỉ trả lời một câu: tiền đã về hay chưa. Hai trục đó độc lập,
     * và mỗi cách thanh toán đi theo một thứ tự khác nhau:
     *
     *   COD           giao xong -> mới thu được tiền   (completed rồi mới paid)
     *   Chuyển khoản  thu tiền  -> mới nên giao        (paid rồi mới shipping)
     *
     * Còn HAI giá trị vì hiện chỉ có COD và chuyển khoản đối chiếu tay. Cột
     * trong CSDL là VARCHAR nên lúc nối cổng thanh toán, thêm 'pending' (khách
     * đã bấm trả, cổng chưa xác nhận) hoặc 'refunded' chỉ là thêm vào mảng này.
     */
    public const PAYMENT_STATUSES = [
        'unpaid'       => 'Chưa thanh toán',
        // Nấc GIỮA, chỉ có ở đơn cắt tròng theo độ: khách đã chuyển 30% và cửa
        // hàng bắt đầu mài tròng được, nhưng đơn vẫn còn nợ phần còn lại. Thêm
        // được mà không phải ALTER TABLE đúng như cột VARCHAR đã tính trước.
        'deposit_paid' => 'Đã đặt cọc',
        'paid'         => 'Đã thanh toán',
    ];

    /**
     * Khoảng NGÀY TẠO ĐƠN dùng cho bộ lọc ở khu quản trị.
     *
     * Khai ở model chứ không ở controller vì chính câu SQL trong paginateAdmin() đọc nó:
     * để hai nơi cùng giữ một danh sách khoá thì sớm muộn view hiện một lựa
     * chọn mà truy vấn không hiểu, và người dùng bấm vào thấy "tất cả".
     *
     * Khoá '' luôn là "không lọc" — cùng nếp với $status.
     */
    public const DATE_RANGES = [
        ''      => 'Tất cả ngày',
        'today' => 'Hôm nay',
        '7d'    => '7 ngày qua',
        '30d'   => '30 ngày qua',
    ];

    // ========================================================================
    // TẠO ĐƠN
    // ========================================================================

    /**
     * Tạo đơn hàng từ giỏ.
     *
     * @param array $data customerName, customerPhone, customerEmail, deliveryMethod,
     *                    shippingAddress, paymentMethod, note, userId, voucherCode
     * @param array $cart [product_id => số lượng] — CHỈ các dòng khách đã tick
     *
     * @return array ['ok'=>true,'code'=>...,'total'=>...,'items'=>...] | ['ok'=>false,'error'=>...]
     */
    public static function place(array $data, array $cart): array
    {
        if ($cart === []) {
            return ['ok' => false, 'error' => 'Giỏ hàng đang trống.'];
        }

        try {
            return Database::transaction(static function () use ($data, $cart): array {

                // Khoá các dòng sản phẩm liên quan tới hết transaction.
                //
                // FOR UPDATE bắt các phiên khác phải đợi ở đây thay vì cùng
                // đọc một con số tồn kho rồi cùng tưởng là còn hàng. Không có
                // nó, hai đơn đặt đồng thời đều thấy "còn 1" và đều được nhận.
                $ids  = array_values(array_unique(array_column($cart, 'product_id')));
                $ph   = [];
                $args = [];
                foreach ($ids as $i => $id) {
                    $ph[]           = ":id{$i}";
                    $args["id{$i}"] = $id;
                }

                $rows = Database::fetchAll(
                    'SELECT id, name, price, stock_quantity, status, is_visible
                       FROM products
                      WHERE id IN (' . implode(', ', $ph) . ')
                      FOR UPDATE',
                    $args
                );

                $byId = [];
                foreach ($rows as $row) {
                    $byId[$row['id']] = $row;
                }

                // Khoá luôn các dòng biến thể liên quan — tồn kho của chúng
                // nằm ở bảng khác, khoá products không che được chúng.
                $variantIds = array_values(array_filter(array_column($cart, 'variant_id')));
                $byVariant  = [];

                if ($variantIds !== []) {
                    $vph = [];
                    $vargs = [];
                    foreach ($variantIds as $i => $vid) {
                        $vph[]            = ":vid{$i}";
                        $vargs["vid{$i}"] = $vid;
                    }

                    foreach (Database::fetchAll(
                        'SELECT id, product_id, label, price_delta, stock_quantity, is_active
                           FROM product_variants
                          WHERE id IN (' . implode(', ', $vph) . ')
                          FOR UPDATE',
                        $vargs
                    ) as $row) {
                        $byVariant[$row['id']] = $row;
                    }
                }

                $lines    = [];
                $subtotal = 0;

                foreach ($cart as $row) {
                    $quantity = (int) $row['quantity'];
                    $product  = $byId[$row['product_id']] ?? null;

                    if ($product === null || (int) $product['is_visible'] !== 1) {
                        throw new RuntimeException('Sản phẩm không còn khả dụng.');
                    }

                    $variant = null;

                    if ($row['variant_id'] !== null) {
                        $variant = $byVariant[$row['variant_id']] ?? null;

                        // Kiểm biến thể có THUỘC đúng mặt hàng không, ngay cả ở
                        // đây: giỏ hàng nằm trong session, mà session sửa được
                        // thì mọi thứ đọc từ nó đều phải kiểm lại.
                        if ($variant === null
                            || $variant['product_id'] !== $product['id']
                            || (int) $variant['is_active'] !== 1) {
                            throw new RuntimeException('Phương án bạn chọn không còn khả dụng.');
                        }
                    }

                    $stock = $variant !== null
                        ? (int) $variant['stock_quantity']
                        : (int) $product['stock_quantity'];

                    if ($product['status'] !== 'in_stock' || $stock < $quantity) {
                        throw new RuntimeException(sprintf('Sản phẩm "%s" không đủ tồn kho.', $product['name']));
                    }

                    /* Tròng cắt kèm — KIỂU tròng (đơn/hai/đa/mắt đặt) gộp với
                       GÓI chiết suất thành một mẩu tên + một con số tiền. Tra
                       LẠI từ bảng giá ngay tại đây — giỏ hàng nằm trong session
                       và chỉ nhớ hai id, đúng như nó chỉ nhớ id sản phẩm. Nhận
                       giá từ session nghĩa là cho khách tự đặt giá phần tròng.

                       "Mắt đặt" trả về id = null và price = 0: hoá đơn ghi tên
                       kiểu tròng, còn tiền tròng cửa hàng báo sau khi xem thông
                       số — xem LensModel::combo(). */
                    $lens = LensModel::combo($row['lens_id'] ?? null, $row['lens_type'] ?? null);

                    /* Tiền tròng CỘNG VÀO unit_price chứ không thành một dòng
                       riêng, để line_total = unit_price × quantity giữ nguyên
                       nghĩa ở mọi nơi đang đọc bảng order_items. Cột lens_price
                       chỉ để tách ra khi cần in "gọng + tròng" — xem ghi chú ở
                       schema.sql. */
                    $lensPrice = (int) ($lens['price'] ?? 0);

                    /* GỌI VariantModel::priceOf, KHÔNG chép lại công thức.
                       Chỗ này từng tự cộng `price` với `price_delta` — đúng
                       kết quả ở thời điểm viết, nhưng là bản chép thứ hai của
                       một phép tính tiền. Nó lệch ngay khi giá bán có thêm
                       luật: khuyến mãi có hạn thêm 2026-08-29 làm giỏ hàng
                       hiện giá giảm trong khi hoá đơn vẫn ghi giá thường —
                       thu sai tiền mà không có lỗi nào báo. */
                    $unit = VariantModel::priceOf($product, $variant) + $lensPrice;

                    // Chép lại tên, NHÃN BIẾN THỂ, TÊN GÓI TRÒNG và giá tại
                    // thời điểm mua — đơn cũ không được đổi theo khi sản phẩm
                    // đổi giá, đổi tên hay bị gỡ.
                    $lines[] = [
                        'product_id'    => $product['id'],
                        'variant_id'    => $variant['id'] ?? null,
                        'variant_label' => $variant['label'] ?? null,
                        'lens_id'       => $lens['id'] ?? null,
                        'lens_name'     => $lens['name'] ?? null,
                        'lens_price'    => $lensPrice,
                        // null = khách chưa biết độ, đo tại cửa hàng
                        'prescription'  => $row['rx'] ?? null,
                        'product_name'  => $product['name'],
                        'unit_price'    => $unit,
                        'quantity'      => $quantity,
                        'line_total'    => $unit * $quantity,
                    ];

                    $subtotal += $unit * $quantity;
                }

                $shippingFee = self::shippingFee($data['deliveryMethod'] ?? 'pickup', $subtotal);

                // ── MÃ GIẢM GIÁ ────────────────────────────────────────────
                // Tra LẠI từ bảng `vouchers` ngay tại đây, bên trong transaction
                // đã khoá các dòng sản phẩm. Không nhận số tiền nào từ nơi gọi:
                // giỏ hàng và trang thanh toán đều chỉ chuyển xuống CHUỖI mã.
                //
                // Kiểm lại lần cuối là bắt buộc chứ không thừa — giữa lúc khách
                // áp mã và lúc bấm đặt, mã có thể hết hạn, bị tắt, hết lượt,
                // hoặc khách đã bỏ tick bớt hàng khiến đơn tụt dưới mức tối thiểu.
                $voucher     = null;
                $discount    = 0;
                $voucherCode = trim((string) ($data['voucherCode'] ?? ''));

                if ($voucherCode !== '') {
                    $check = VoucherModel::evaluate($voucherCode, $subtotal, $data['userId'] ?? null);

                    if ($check['ok']) {
                        $voucher = $check['voucher'];
                        $applied = VoucherModel::apply($voucher, $subtotal, $shippingFee);
                        $discount = $applied['discount'];

                        if ($applied['freeShipping']) {
                            $shippingFee = 0;
                        }
                    }
                    // Mã hỏng thì đơn vẫn đi tiếp với giá đầy đủ. Chặn cả đơn
                    // lại vì một mã hết hạn là phạt khách cho lỗi của cửa hàng.
                }

                $total   = max(0, $subtotal - $discount) + $shippingFee;

                /* ĐẶT CỌC — chốt số tiền NGAY TẠI ĐÂY, trong cùng transaction
                   với đơn. Từ giây này trở đi nó là con số đã thoả thuận: đổi
                   'deposit_rate' trong config về sau không được phép làm đơn
                   này đổi số. Xem depositFor().

                   HAI ĐIỀU KIỆN, PHẢI ĐỦ CẢ HAI:
                     có mài tròng theo độ   -> needsDeposit()
                     trả tiền khi nhận hàng -> COD

                   Đơn chuyển khoản không cọc: tiền về đủ trước khi cửa hàng
                   làm gì cả. Xem khối chú thích ở needsDeposit().

                   Tính trên $total, tức SAU khi trừ mã giảm giá và ĐÃ CỘNG
                   phí ship — đúng số cuối cùng khách phải trả, không phải
                   tạm tính. */
                $laCod = ($data['paymentMethod'] ?? '') === 'cod';

                /* CHUYỂN KHOẢN THÌ KHÁCH TỰ CHỌN. 'deposit' = chuyển 30% rồi
                   trả nốt khi nhận; 'full' = chuyển đủ một lần và được tặng
                   mã giảm giá cho lần sau (xem grantFullPaymentReward).

                   Mặc định là 'full' khi giá trị gửi lên lạ: đó là vế cửa
                   hàng khuyến khích, và nó cũng là vế AN TOÀN — đoán nhầm
                   thành 'full' thì khách thấy số tiền lớn hơn và có thể đổi
                   ý; đoán nhầm thành 'deposit' thì cửa hàng mài tròng trước
                   khi có đủ tiền, đúng thứ tiền cọc sinh ra để tránh. */
                $ckCoc = !$laCod && ($data['bankAmount'] ?? '') === 'deposit';

                /* ─────────────────────────────────────────────────────────
                   TIỀN CỌC CHỈ TỒN TẠI Ở ĐƠN CÓ MÀI TRÒNG.
                
                   Đó là gốc rễ của cả cơ chế: tròng mài theo số đo của một
                   người, khách đổi ý thì cửa hàng ôm cặp tròng không bán lại
                   được. Gọng trần không có rủi ro đó — hàng có sẵn, ai mua
                   cũng vừa — nên nó bán như mọi shop khác:
                
                     gọng trần    COD trả hết khi nhận, hoặc chuyển khoản đủ
                                  100%. Không có lựa chọn cọc nào cả.
                
                     có mài tròng COD thì buộc cọc 30%; chuyển khoản thì khách
                                  TỰ CHỌN cọc 30% hay chuyển đủ.
                
                   Vì thế needsDeposit($cart) là điều kiện CHUNG cho cả hai
                   nhánh, không riêng nhánh COD. Bỏ nó ở nhánh chuyển khoản
                   nghĩa là cửa hàng giao một cặp gọng khi mới nhận 30% —
                   không khác gì COD nhưng phức tạp hơn, và phải thu nốt 70%
                   lúc giao mà không có cơ chế nào cho việc đó.
                   ───────────────────────────────────────────────────────── */
                $depositRate = (self::needsDeposit($cart) && ($laCod || $ckCoc))
                    ? self::depositRate()
                    : 0;
                $depositAmount = self::depositFor($total, $depositRate);

                $orderId = uuid();
                $code    = generateCode('DH');

                Database::execute(
                    'INSERT INTO orders
                        (id, code, user_id, customer_name, customer_phone, customer_email,
                         shipping_address, delivery_method, store_id, payment_method, note,
                         subtotal, shipping_fee, discount, voucher_id, total,
                         deposit_amount, deposit_rate)
                     VALUES
                        (:id, :code, :user_id, :customer_name, :customer_phone, :customer_email,
                         :shipping_address, :delivery_method, :store_id, :payment_method, :note,
                         :subtotal, :shipping_fee, :discount, :voucher_id, :total,
                         :deposit_amount, :deposit_rate)',
                    [
                        'id'               => $orderId,
                        'code'             => $code,
                        'user_id'          => $data['userId'] ?? null,
                        'customer_name'    => $data['customerName'],
                        'customer_phone'   => $data['customerPhone'],
                        'customer_email'   => $data['customerEmail'] ?: null,
                        // Nhận tại cửa hàng thì không lưu địa chỉ giao —
                        // giữ lại chỉ tổ gây nhầm cho người soạn hàng.
                        'shipping_address' => ($data['deliveryMethod'] ?? '') === 'shipping'
                            ? ($data['shippingAddress'] ?: null) : null,
                        'delivery_method'  => $data['deliveryMethod'],
                        // Chỉ đơn nhận tại cửa hàng mới gắn cơ sở — nơi gọi đã
                        // ép về null cho đơn giao tận nơi.
                        'store_id'         => $data['storeId'] ?? null,
                        'payment_method'   => $data['paymentMethod'],
                        'note'             => $data['note'] ?: null,
                        'subtotal'         => $subtotal,
                        'shipping_fee'     => $shippingFee,
                        'discount'         => $discount,
                        'voucher_id'       => $voucher['id'] ?? null,
                        'total'            => $total,
                        'deposit_amount'   => $depositAmount,
                        'deposit_rate'     => $depositRate,
                    ]
                );

                if ($voucher !== null) {
                    // Trong CÙNG transaction với đơn: tách rời thì một lỗi ở
                    // giữa để lại mã đã trừ lượt mà không có đơn nào.
                    VoucherModel::consume($voucher['id'], $data['userId'] ?? null);
                }

                foreach ($lines as $line) {
                    Database::execute(
                        'INSERT INTO order_items
                            (id, order_id, product_id, variant_id, variant_label,
                             lens_id, lens_name, lens_price, prescription,
                             product_name, unit_price, quantity, line_total)
                         VALUES (:id, :order_id, :product_id, :variant_id, :variant_label,
                                 :lens_id, :lens_name, :lens_price, :prescription,
                                 :product_name, :unit_price, :quantity, :line_total)',
                        ['id' => uuid(), 'order_id' => $orderId] + $line
                    );

                    // Trừ tồn kho của ĐÚNG chỗ đã bán: biến thể nếu có, không
                    // thì mặt hàng. Trừ nhầm chỗ nghĩa là bán được vô hạn một
                    // phương án đã hết hàng.
                    if (!VariantModel::reserve($line['variant_id'], $line['product_id'], $line['quantity'])) {
                        throw new RuntimeException(
                            sprintf('Sản phẩm "%s" vừa hết hàng.', $line['product_name'])
                        );
                    }
                }

                // Bước ĐẦU của thanh tiến trình trong trang tài khoản. Ghi ở
                // đây, trong cùng transaction với đơn: đơn nào cũng phải có ít
                // nhất một mốc thời gian, không thì thanh tiến trình trống.
                self::logStatus($orderId, 'new');

                // 'items' để nơi gọi biết đúng những dòng nào đã thành đơn mà
                // dọn khỏi giỏ — dòng khách chưa tick phải được giữ lại.
                return ['ok' => true, 'code' => $code, 'total' => $total, 'items' => $cart];
            });
        } catch (PDOException $e) {
            /*
             * PHẢI ĐỨNG TRƯỚC RuntimeException — PDOException KẾ THỪA TỪ NÓ.
             *
             * Thiếu nhánh này thì mọi lỗi cơ sở dữ liệu rơi vào nhánh "lỗi
             * nghiệp vụ" ngay dưới, và câu getMessage() của PDO được in NGUYÊN
             * VĂN lên trang thanh toán cho khách đọc:
             *
             *   SQLSTATE[42S22]: Column not found: 1054 Unknown column
             *   'deposit_amount' in 'INSERT INTO'
             *
             * Đã xảy ra thật, trên production, khi cột chưa được thêm. Hai cái
             * hại: khách nhận một câu tiếng Anh không hiểu gì thay vì lời xin
             * lỗi, và người ngoài đọc được tên bảng, tên cột của hệ thống.
             *
             * Nhánh này KHÔNG bị APP_DEBUG chặn: chuỗi lỗi đi ra bằng đường
             * "thông điệp cho khách", không phải bằng bộ bắt lỗi chung ở
             * core/App.php. Nên tắt debug trên production cũng không cứu được —
             * phải chặn ở đúng đây.
             */
            error_log('[OrderModel] Lỗi CSDL khi tạo đơn: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không tạo được đơn hàng, vui lòng thử lại.'];
        } catch (RuntimeException $e) {
            // Lỗi nghiệp vụ — thông điệp viết cho khách đọc ("Sản phẩm đã hết
            // hàng", "Vui lòng chọn cơ sở"…). Chỉ những câu do CHÍNH mã nguồn
            // này ném ra mới tới được đây, xem nhánh PDOException ở trên.
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            error_log('[OrderModel] Không tạo được đơn: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không tạo được đơn hàng, vui lòng thử lại.'];
        }
    }

    /*
     * reserveStock() ĐÃ CHUYỂN sang VariantModel::reserve().
     *
     * Từ khi có biến thể, tồn kho nằm ở HAI bảng và việc trừ phải biết trừ chỗ
     * nào. Gộp cả hai đường vào một hàm để không có chỗ nào trừ nhầm bảng —
     * trừ nhầm nghĩa là bán được vô hạn một phương án đã hết hàng.
     */

    /**
     * Phí giao hàng. Khớp quy tắc trong createOrder của bản Lovable:
     * chỉ thu khi giao tận nơi VÀ đơn dưới ngưỡng miễn phí.
     */
    public static function shippingFee(string $deliveryMethod, int $subtotal): int
    {
        if ($deliveryMethod !== 'shipping') {
            return 0;
        }

        return $subtotal < (int) config('app.free_shipping_threshold')
            ? (int) config('app.shipping_fee')
            : 0;
    }

    // ========================================================================
    // ĐẶT CỌC
    //
    // Cửa hàng chia luồng mua làm hai, và ranh giới là CÓ CẮT TRÒNG THEO ĐỘ
    // HAY KHÔNG:
    //
    //   chỉ mua gọng     gọng đi kèm tròng demo chưa cắt độ. Hàng có sẵn, ai
    //                    mua cũng vừa -> bán bình thường, KHÔNG cọc.
    //   gọng + cắt tròng tròng mài riêng theo số đo của một người, khách đổi
    //                    ý thì không bán lại cho ai khác được -> cọc 30%.
    //
    // CỌC CHỈ ÁP CHO COD. Cửa hàng chốt như vậy (2026-08-22), và nó hợp lý:
    //
    //   COD + cắt tròng    khách trả tiền lúc nhận hàng, nhưng tròng đã mài
    //                      theo độ của họ từ trước. Không cọc thì khách đổi ý
    //                      là cửa hàng ôm trọn cặp tròng không bán lại được
    //                      cho ai -> cọc 30% để hai bên cùng có ràng buộc.
    //
    //   Chuyển khoản QR    tiền về đủ 100% TRƯỚC khi cửa hàng làm gì cả.
    //                      Không còn rủi ro nào để bảo hiểm, nên đòi cọc 30%
    //                      rồi lại đòi nốt 70% chỉ là bắt khách chuyển khoản
    //                      hai lần cho cùng một đơn.
    //
    // Vì thế needsDeposit() KHÔNG đủ để quyết định — nó chỉ trả lời "đơn này
    // có mài tròng không". Vế phương thức thanh toán nằm ở place().
    // ========================================================================

    /**
     * Đơn này có phải đặt cọc không.
     *
     * $cart là các dòng giỏ ĐÃ TICK (CartController::selectedItems). Chỉ cần
     * MỘT dòng có cắt tròng là cả đơn phải cọc: cửa hàng mài tròng cho đơn đó
     * ngay khi nhận cọc, không tách được ra thành hai đơn nửa-cọc nửa-không.
     */
    public static function needsDeposit(array $cart): bool
    {
        foreach ($cart as $row) {
            if (self::lineNeedsDeposit($row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Một dòng giỏ có cắt tròng theo độ không.
     *
     * Chốt ở `lens_type`, KHÔNG ở `lens_id`: "Mắt đặt" là một kiểu tròng hợp
     * lệ nhưng chưa có gói chiết suất nào (cửa hàng báo giá sau khi xem thông
     * số), nên lens_id của nó là null. Chốt nhầm ở lens_id thì đúng loại đơn
     * khó làm nhất lại là loại duy nhất thoát khỏi việc đặt cọc.
     *
     * CartController::add() chỉ điền hai khoá này khi khách đi qua nhánh
     * mode=trong; nhánh mode=gong để cả hai null.
     */
    public static function lineNeedsDeposit(array $row): bool
    {
        return ($row['lens_type'] ?? null) !== null;
    }

    /** Tỷ lệ cọc đang áp, tính theo phần trăm. Kẹp về 0–100 để một giá trị gõ nhầm trong config không sinh ra số tiền vô nghĩa. */
    public static function depositRate(): int
    {
        return max(0, min(100, (int) config('app.deposit_rate', 0)));
    }

    /**
     * Tiền cọc của một đơn.
     *
     * LÀM TRÒN LÊN (ceil) chứ không xuống: phần lẻ rơi vào tiền cọc thì "còn
     * lại khi nhận hàng" là số nhỏ hơn, và cả hai vế vẫn cộng đúng bằng tổng.
     * Làm tròn xuống thì cửa hàng thu thiếu vài đồng trên mỗi đơn — không đáng
     * kể về tiền, nhưng đối chiếu sổ sách thì lệch là lệch.
     *
     * $rate truyền vào chứ không tự đọc config: place() phải dùng ĐÚNG tỷ lệ
     * mà nó vừa chốt, còn màn hiển thị thì gọi kèm depositRate(). Một hàm đọc
     * config bên trong sẽ khiến hai chỗ có thể lệch nhau nếu config đổi giữa
     * chừng.
     */
    public static function depositFor(int $total, int $rate): int
    {
        if ($rate <= 0 || $total <= 0) {
            return 0;
        }

        // Không vượt quá tổng đơn, kể cả khi ai đó đặt tỷ lệ 100.
        return (int) min($total, ceil($total * $rate / 100));
    }

    // ========================================================================
    // ĐỌC
    // ========================================================================

    /**
     * Đơn của MỘT khách. Thay cho policy "own orders read" của Postgres —
     * điều kiện user_id là thứ duy nhất ngăn khách này xem đơn khách khác.
     */
    /**
     * Số đơn CÒN ĐANG CHẠY của một khách — con số trên huy hiệu ở cột trái.
     *
     * ─────────────────────────────────────────────────────────────────────
     * HUY HIỆU LÀ VIỆC CÒN PHẢI THEO DÕI, KHÔNG PHẢI TỔNG SỐ ĐƠN TỪNG ĐẶT
     *
     * Trước đây nó đếm tất, kể cả đơn đã hoàn tất và đã huỷ. Nghĩa là khách
     * mua quen ba năm mở trang tài khoản ra thấy số 47 nằm cạnh "Đơn hàng của
     * tôi" — một con số chỉ tăng, không bao giờ giảm, và không nói được điều
     * gì đáng làm. Huy hiệu kiểu đó người ta học cách phớt lờ sau vài lần.
     *
     * Hai trạng thái CUỐI ĐƯỜNG bị loại: 'completed' (hàng đã tới tay) và
     * 'cancelled' (đơn không còn). Bốn trạng thái còn lại đều là đơn khách
     * còn phải chờ hoặc còn phải làm gì đó, tức đáng để đếm.
     *
     * Không lọc theo payment_status: một đơn COD chưa trả tiền nhưng đang
     * giao vẫn là đơn đang chạy, mà một đơn đã trả đủ rồi vẫn phải chờ giao.
     * Trục tiền và trục giao vận độc lập nhau — xem khối chú thích ở
     * PAYMENT_STATUSES.
     * ─────────────────────────────────────────────────────────────────────
     */
    public static function countActive(string $userId): int
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM orders
              WHERE user_id = :uid
                AND status NOT IN (\'completed\', \'cancelled\')',
            ['uid' => $userId]
        );
    }

    public static function forUser(string $userId): array
    {
        // Kèm tên cơ sở: thẻ đơn trong trang tài khoản phải nói rõ khách tới
        // đâu lấy hàng. LEFT JOIN vì đơn giao tận nơi không có cơ sở nào.
        return Database::fetchAll(
            'SELECT o.*, s.name AS store_name, s.address AS store_address
               FROM orders o
               LEFT JOIN stores s ON s.id = o.store_id
              WHERE o.user_id = :uid
              ORDER BY o.created_at DESC',
            ['uid' => $userId]
        );
    }

    /**
     * Tra cứu theo mã đơn. Chỉ trả về khi mã KHỚP và (nếu có đăng nhập) đúng
     * chủ đơn — mã đơn có phần ngẫu nhiên nên không đoán được, nhưng vẫn kiểm
     * chủ sở hữu khi biết.
     */
    public static function findByCode(string $code, ?string $userId = null): ?array
    {
        $order = Database::fetchOne(
            'SELECT o.*, s.name AS store_name, s.address AS store_address
               FROM orders o
               LEFT JOIN stores s ON s.id = o.store_id
              WHERE o.code = :code',
            ['code' => $code]
        );

        if ($order === null) {
            return null;
        }

        if ($userId !== null && $order['user_id'] !== null && $order['user_id'] !== $userId) {
            return null;
        }

        return $order;
    }

    /**
     * Các dòng hàng của một đơn.
     */
    public static function items(string $orderId): array
    {
        // `images` LEFT JOIN từ products, không lưu trong order_items: trang xác
        // nhận đơn in ảnh sản phẩm trên từng dòng hàng. Cùng lý do đã ghi ở
        // itemsForOrders() bên dưới — ảnh là dữ liệu trình bày, còn tên và giá
        // thì chép cứng vào order_items lúc đặt hàng. Sản phẩm bị gỡ thì
        // product_id thành NULL, dòng hàng mất ảnh nhưng hoá đơn vẫn nguyên.
        return Database::fetchAll(
            'SELECT oi.*, p.brand, p.slug, p.images
               FROM order_items oi
               LEFT JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id = :id',
            ['id' => $orderId]
        );
    }

    /**
     * Dòng hàng của NHIỀU đơn cùng lúc, gom theo order_id.
     *
     * Trang tài khoản in ảnh, thương hiệu và tên sản phẩm trên từng thẻ đơn.
     * Gọi items() trong vòng lặp là N+1 truy vấn — 20 đơn thành 21 câu SQL.
     * Một câu IN(...) đổi lấy toàn bộ.
     *
     * `brand` và `images` LEFT JOIN từ products chứ không lưu trong order_items:
     * đó là dữ liệu trình bày, không phải dữ liệu hoá đơn. Tên và giá thì ngược
     * lại — chúng được chép cứng vào order_items lúc đặt hàng (xem schema.sql).
     * Sản phẩm bị gỡ thì product_id thành NULL và thẻ đơn mất ảnh, nhưng tên
     * và giá trong hoá đơn vẫn nguyên.
     *
     * @param  array $orderIds
     * @return array [order_id => [dòng hàng, ...]]
     */
    public static function itemsForOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        // Tham số đánh số thay vì nối chuỗi id vào SQL: id đến từ dữ liệu đã
        // đọc ra, nhưng quy tắc "không bao giờ nối biến vào câu lệnh" không có
        // ngoại lệ nào đáng nhớ.
        $placeholders = [];
        $params       = [];

        foreach (array_values($orderIds) as $i => $id) {
            $placeholders[] = ':id' . $i;
            $params['id' . $i] = $id;
        }

        $rows = Database::fetchAll(
            'SELECT oi.*, p.brand, p.slug, p.images, p.color, p.material
               FROM order_items oi
               LEFT JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['order_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * Mốc thời gian của từng trạng thái, cho NHIỀU đơn cùng lúc.
     *
     * Trả về [order_id => [trạng thái => thời điểm ĐẦU TIÊN đơn vào trạng thái
     * đó]]. Lấy lần ĐẦU chứ không lần cuối: nhân viên lỡ tay bấm lùi rồi bấm
     * tiến lại thì mốc "đã xác nhận" vẫn phải là lần xác nhận thật, không phải
     * lần sửa nhầm.
     *
     * Một câu cho cả danh sách, cùng lý do đã ghi ở itemsForOrders().
     *
     * @param  array $orderIds
     */
    public static function historyForOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $placeholders = [];
        $params       = [];

        foreach (array_values($orderIds) as $i => $id) {
            $placeholders[] = ':id' . $i;
            $params['id' . $i] = $id;
        }

        $rows = Database::fetchAll(
            'SELECT order_id, status, MIN(created_at) AS at
               FROM order_status_history
              WHERE order_id IN (' . implode(', ', $placeholders) . ')
              GROUP BY order_id, status',
            $params
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['order_id']][$row['status']] = $row['at'];
        }

        return $grouped;
    }

    /**
     * Ghi một mốc trạng thái.
     *
     * KHÔNG tự UPDATE cột orders.status — nơi gọi làm việc đó. Tách ra để việc
     * ghi lịch sử nằm gọn trong cùng transaction với thao tác đã gây ra nó.
     */
    public static function logStatus(string $orderId, string $status, ?string $changedBy = null): void
    {
        Database::execute(
            'INSERT INTO order_status_history (id, order_id, status, changed_by)
             VALUES (:id, :order_id, :status, :changed_by)',
            [
                'id'         => uuid(),
                'order_id'   => $orderId,
                'status'     => $status,
                'changed_by' => $changedBy,
            ]
        );
    }

    // ========================================================================
    // ĐỔI TRẠNG THÁI — GIAO VẬN VÀ TIỀN
    //
    // Ba hàm dưới đây là CỬA DUY NHẤT để đổi hai trục trạng thái của đơn. Đừng
    // gọi update(['status' => …]) hay update(['payment_status' => …]) trực tiếp:
    // mỗi lần đổi đều kéo theo một việc khác phải làm cùng (ghi lịch sử, đóng
    // mốc tiền), và những việc đó nằm trong này.
    //
    // Khi nối cổng thanh toán (SePay…), webhook chỉ cần gọi markPaid() — mọi
    // luật về tiền đã nằm sẵn ở đây, không phải viết lại ở lớp webhook.
    // ========================================================================

    /**
     * Đổi trạng thái giao vận của đơn.
     *
     * Gộp ba việc vào một transaction:
     *   1. đổi cột `status`
     *   2. ghi một mốc vào `order_status_history` — thanh tiến trình trong trang
     *      tài khoản đọc bảng này để lấy giờ của từng bước, nên một bản ghi
     *      thiếu là một mốc trống VĨNH VIỄN
     *   3. đơn COD chuyển sang 'completed' thì đánh dấu ĐÃ THU TIỀN luôn
     *
     * Việc thứ 3 không phải tiện tay làm thêm: với COD, "giao xong" và "thu được
     * tiền" là CÙNG một hành động của shipper. Bắt nhân viên bấm hai nút cho một
     * việc thì sổ tiền sẽ đầy đơn completed mà vẫn 'unpaid', và con số đó thành
     * vô nghĩa. Đơn chuyển khoản thì KHÔNG suy luận gì — tiền về hay chưa chỉ
     * sao kê biết.
     */
    public static function changeStatus(string $id, string $status, ?string $changedBy = null): void
    {
        Database::transaction(static function () use ($id, $status, $changedBy): void {
            /*
             * ĐỌC TRẠNG THÁI CŨ TRƯỚC KHI GHI ĐÈ.
             *
             * Việc hoàn kho phụ thuộc vào CHIỀU đi chứ không phải vào trạng
             * thái đích: chỉ lần ĐẦU sang 'cancelled' mới trả hàng về kho. Đọc
             * sau khi update thì không còn phân biệt được "vừa huỷ" với "đã
             * huỷ từ trước, nhân viên bấm lại nút Lưu" — và bấm lại là chuyện
             * xảy ra thường xuyên.
             */
            $truoc = (string) (self::find($id)['status'] ?? '');

            self::update($id, ['status' => $status]);
            self::logStatus($id, $status, $changedBy);

            /*
             * ─────────────────────────────────────────────────────────────
             * TỒN KHO ĐI THEO TRẠNG THÁI, HAI CHIỀU
             *
             * Đặt hàng TRỪ kho ngay trong transaction tạo đơn (xem place()),
             * nhưng huỷ đơn thì trước nay không trả lại — kể cả nhân viên huỷ
             * trong khu quản trị. Mỗi đơn huỷ là một lần kho ghi thiếu vĩnh
             * viễn: hàng còn trên kệ mà hệ thống coi như đã bán.
             *
             * Đặt ở ĐÂY chứ không ở controller vì đây là cửa duy nhất đổi
             * `status` (xem khối chú thích ngay trên hàm này). Nhét vào
             * controller thì đường nào gọi thẳng model sẽ lặng lẽ bỏ qua.
             *
             * PHẢI CÓ CẢ CHIỀU NGƯỢC LẠI. Nhân viên bấm nhầm 'Đã huỷ' rồi
             * chuyển về 'Đang chuẩn bị' là chuyện có thật; chỉ cộng mà không
             * trừ lại thì kho phình lên đúng bằng số lần bấm nhầm.
             *
             * Trừ lại dùng chính reserve(), nên nếu trong lúc đơn đang huỷ mà
             * hàng đã bán hết cho người khác thì nó từ chối và tồn kho đứng
             * yên ở 0 thay vì rơi xuống số âm. Ghi một dòng vào log để người
             * trực còn biết mà đối chiếu — đây là ca hiếm nhưng im lặng bỏ qua
             * thì không ai phát hiện.
             * ─────────────────────────────────────────────────────────────
             */
            $huyMoi   = $status === 'cancelled' && $truoc !== 'cancelled';
            $moHuy    = $truoc === 'cancelled' && $status !== 'cancelled';

            if ($huyMoi || $moHuy) {
                foreach (self::items($id) as $dong) {
                    $sl  = (int) ($dong['quantity'] ?? 0);
                    $spId = (string) ($dong['product_id'] ?? '');

                    /* product_id thành NULL khi sản phẩm đã bị gỡ khỏi danh mục
                       (khoá ngoại ON DELETE SET NULL — xem items()). Dòng hàng
                       vẫn nằm trong hoá đơn để đơn cũ đọc được, nhưng không còn
                       kho nào để trả về. Bỏ qua, không phải lỗi. */
                    if ($sl <= 0 || $spId === '') {
                        continue;
                    }

                    if ($huyMoi) {
                        VariantModel::release($dong['variant_id'] ?? null, $spId, $sl);
                        continue;
                    }

                    $ok = VariantModel::reserve($dong['variant_id'] ?? null, $spId, $sl);

                    if (!$ok) {
                        error_log(sprintf(
                            '[OrderModel] Mở lại đơn %s: không trừ được %d của sản phẩm %s — kho không đủ.',
                            $id,
                            $sl,
                            $spId
                        ));
                    }
                }
            }

            if ($status !== 'completed') {
                return;
            }

            $order = self::find($id);

            if ($order !== null
                && $order['payment_method'] === 'cod'
                && $order['payment_status'] === 'unpaid'
            ) {
                self::markPaid($id);
            }
        });
    }

    /**
     * Đánh dấu đơn đã nhận được ĐỦ tiền.
     *
     * Điều kiện là `payment_status <> 'paid'`, KHÔNG phải `= 'unpaid'`. Hai
     * chuyện khác nhau kể từ khi có đặt cọc:
     *
     *   · Đơn cọc gần như luôn trả làm hai lần. Lần thứ hai phải nâng được đơn
     *     từ 'deposit_paid' lên 'paid'; chốt ở 'unpaid' thì nó mắc kẹt ở nấc
     *     giữa dù khách đã trả xong.
     *   · Vẫn KHÔNG dịch `paid_at` khi gọi lại trên đơn đã 'paid' — webhook của
     *     SePay gửi lại cùng một giao dịch tối đa 7 lần, và mốc tiền về không
     *     được nhảy theo mỗi lần gửi lại. Đó mới là tính chất cần giữ.
     *
     * @return bool có đổi gì không (false = đơn đã 'paid' từ trước, hoặc mã sai)
     */
    public static function markPaid(string $id): bool
    {
        $doi = Database::execute(
            "UPDATE orders
                SET payment_status = 'paid', paid_at = NOW()
              WHERE id = :id AND payment_status <> 'paid'",
            ['id' => $id]
        ) > 0;

        if ($doi) {
            self::grantFullPaymentReward($id);
        }

        return $doi;
    }

    /**
     * Tặng mã giảm giá cho khách đã CHUYỂN KHOẢN ĐỦ 100%.
     *
     * ─────────────────────────────────────────────────────────────────────
     * ĐẶT TRONG markPaid() CHỨ KHÔNG Ở TỪNG NƠI GỌI
     *
     * Ba đường đưa một đơn sang 'paid': webhook SePay khớp giao dịch, nhân
     * viên đánh dấu ở /quan-tri/don-hang, và đơn COD được đánh dấu đã giao.
     * Rải phép tặng quà ra ba chỗ thì thêm đường thứ tư sau này là quên một
     * chỗ, và khách mất quà đã được hứa mà không ai biết — không có lỗi nào
     * nổ ra cả.
     *
     * Chỉ chạy khi markPaid() THẬT SỰ đổi trạng thái, nên webhook SePay gửi
     * lại cùng một giao dịch bảy lần cũng chỉ tặng một lần.
     *
     * BA ĐIỀU KIỆN, thiếu một là không tặng:
     *   chuyển khoản    COD trả đủ khi nhận hàng không phải thứ đang khuyến
     *                   khích; quà là để bù cho việc trả tiền TRƯỚC.
     *   deposit = 0     tức khách chọn "chuyển đủ" ngay từ đầu. Đơn chọn cọc
     *                   rồi trả nốt phần còn lại vẫn về 'paid', nhưng cửa
     *                   hàng đã phải mài tròng trước khi có đủ tiền.
     *   có tài khoản    mã riêng phát qua user_vouchers; khách vãng lai không
     *                   có chỗ nào để nhận.
     *
     * NUỐT LỖI CÓ CHỦ Ý: quà là việc phụ, tiền đã về mới là việc chính. Bảng
     * `vouchers` thiếu cột is_reward (chưa chạy migration) mà để ném ra ngoài
     * thì webhook SePay nhận 500, SePay coi là thất bại và gửi lại — trong
     * khi đơn ĐÃ được đánh dấu trả đủ ở câu trên. Ghi log để còn biết.
     * ─────────────────────────────────────────────────────────────────────
     */
    private static function grantFullPaymentReward(string $id): void
    {
        try {
            $order = self::find($id);

            if ($order === null
                || $order['payment_method'] !== 'bank_transfer'
                || (int) ($order['deposit_amount'] ?? 0) !== 0
                || empty($order['user_id'])
            ) {
                return;
            }

            $reward = VoucherModel::reward();

            if ($reward === null) {
                return;   // cửa hàng chưa bật mã quà tặng nào
            }

            VoucherModel::grantTo((string) $order['user_id'], (string) $reward['id']);
        } catch (Throwable $e) {
            error_log('[reward] Không tặng được mã cho đơn ' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * Đánh dấu đơn đã nhận đủ TIỀN CỌC (chưa phải toàn bộ).
     *
     * Chỉ đi từ 'unpaid' sang 'deposit_paid' — một chiều, và điều kiện nằm
     * trong chính câu UPDATE. Nhờ vậy webhook của SePay gửi lại cùng một giao
     * dịch mười lần cũng không hạ một đơn ĐÃ trả đủ ('paid') xuống lại thành
     * mới-đặt-cọc. Cùng tính chất với markPaid() ngay trên.
     *
     * KHÔNG chạm `paid_at`: cột đó là mốc "tiền về ĐỦ", dùng cho sổ sách. Đặt
     * nó ở đây thì một đơn mới cọc 30% trông như đã thanh toán xong khi nhìn
     * bằng cột thời gian. Thời điểm nhận cọc nằm ở `sepay_transactions`.
     *
     * @return bool có đổi gì không
     */
    public static function markDepositPaid(string $id): bool
    {
        return Database::execute(
            "UPDATE orders
                SET payment_status = 'deposit_paid'
              WHERE id = :id AND payment_status = 'unpaid'",
            ['id' => $id]
        ) > 0;
    }

    /**
     * Gỡ đánh dấu đã thanh toán — dành cho lúc bấm nhầm.
     *
     * Xoá luôn `paid_at`: giữ lại một mốc "tiền về" trên đơn đang 'unpaid' thì
     * lần sau đọc sổ không biết tin cột nào.
     */
    public static function markUnpaid(string $id): bool
    {
        return Database::execute(
            "UPDATE orders
                SET payment_status = 'unpaid', paid_at = NULL
              WHERE id = :id AND payment_status <> 'unpaid'",
            ['id' => $id]
        ) > 0;
    }

    /**
     * Danh sách cho khu quản trị: lọc theo trạng thái, theo từ khoá và theo
     * khoảng ngày.
     *
     * KHÔNG dùng static::paginate() được: hàm đó chạy `SELECT * FROM orders`
     * nên không kèm được tên cơ sở, mà nhân viên cần đúng cột đó để biết soạn
     * hàng ở đâu. Giữ nguyên hình dạng mảng trả về của paginate() để nơi gọi
     * và view không phải đổi gì.
     *
     * @param string $status khoá trong STATUSES, '' = mọi trạng thái
     * @param string $q      mã đơn / tên khách / số điện thoại, '' = không tìm
     * @param string $range  khoá trong DATE_RANGES, '' = mọi ngày
     */
    public static function paginateAdmin(
        string $status = '',
        int $page = 1,
        int $perPage = 20,
        string $q = '',
        string $range = ''
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $dieuKien = [];
        $params   = [];

        if ($status !== '') {
            $dieuKien[]       = 'o.status = :status';
            $params['status'] = $status;
        }

        $q = trim($q);

        if ($q !== '') {
            /*
             * BA CỘT, MỘT Ô TÌM.
             *
             * Nhân viên cầm điện thoại nghe khách đọc thứ gì thì gõ thứ đó:
             * lúc là mã đơn trong tin nhắn, lúc là tên, lúc là số điện thoại
             * hiện trên màn hình cuộc gọi. Bắt chọn "tìm theo cột nào" trước
             * là bắt họ trả lời một câu hỏi mà chính ô tìm trả lời được.
             *
             * Không đụng tới `shipping_address`: gõ "Hà Nội" mà ra ba trăm đơn
             * thì ô tìm thành vô dụng đúng lúc cần nó nhất.
             */
            /* BA THAM SỐ RIÊNG cho cùng một giá trị, không phải một `:q` dùng
               ba lần. PDO ở chế độ prepare thật (dự án tắt emulate) đếm tham
               số theo vị trí, nên một tên lặp lại ném thẳng
               "SQLSTATE[HY093]: Invalid parameter number". */
            $dieuKien[] = '(o.code LIKE :q1 OR o.customer_name LIKE :q2 OR o.customer_phone LIKE :q3)';

            $mau = self::likeChua($q);
            $params['q1'] = $mau;
            $params['q2'] = $mau;
            $params['q3'] = $mau;
        }

        /*
         * MỐC NGÀY GHÉP THẲNG VÀO SQL, KHÔNG QUA THAM SỐ.
         *
         * Đây là ngoại lệ duy nhất trong hàm, và nó an toàn vì match() chỉ trả
         * về được một trong ba chuỗi HẰNG viết ngay tại đây — giá trị người
         * dùng gửi lên không đi tiếp được quá dòng này. Dùng tham số thì phải
         * tự tính ngày trong PHP, tức là múi giờ của PHP và của MySQL phải
         * khớp nhau; ở hosting miễn phí thì không có gì bảo đảm chuyện đó.
         *
         * '7 ngày qua' = hôm nay + 6 ngày trước đó, tức bảy ngày lịch, không
         * phải "168 giờ tính ngược từ bây giờ".
         */
        $moc = match ($range) {
            'today' => 'CURDATE()',
            '7d'    => 'DATE_SUB(CURDATE(), INTERVAL 6 DAY)',
            '30d'   => 'DATE_SUB(CURDATE(), INTERVAL 29 DAY)',
            default => null,
        };

        if ($moc !== null) {
            $dieuKien[] = 'o.created_at >= ' . $moc;
        }

        $where = $dieuKien === [] ? '' : ' WHERE ' . implode(' AND ', $dieuKien);

        // Không dùng static::count() được nữa: nó chỉ ghép được điều kiện
        // "cột = giá trị", còn ở đây có LIKE và có mốc ngày.
        $total = (int) Database::fetchValue('SELECT COUNT(*) FROM orders o' . $where, $params);

        /*
         * ÍT NHẤT LÀ MỘT TRANG, kể cả khi không có đơn nào.
         *
         * Bản cũ trả 0 và view in ra "trang 1/0". Và vì `page` không bị chặn
         * trên, gõ ?page=99 rồi bấm một nút đổi trạng thái là quay về đúng
         * trang 99 trống trơn — nhân viên đọc ra là "thao tác vừa rồi làm mất
         * hết đơn".
         */
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $perPage;

        $items = Database::fetchAll(
            'SELECT o.*, s.name AS store_name
               FROM orders o
               LEFT JOIN stores s ON s.id = o.store_id'
            . $where .
            ' ORDER BY o.created_at DESC'
            . sprintf(' LIMIT %d OFFSET %d', $perPage, $offset),
            $params
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Gói một từ khoá thành mẫu LIKE '%…%' đã vô hiệu hoá ký tự đại diện.
     *
     * Khách đặt tên sản phẩm có dấu gạch dưới, và mã đơn thì không — nhưng
     * '%' và '_' người ta gõ nhầm vào ô tìm thì có. Để nguyên, '_' khớp MỌI ký
     * tự và '%' khớp mọi thứ: gõ đúng một dấu '%' ra toàn bộ bảng đơn hàng.
     *
     * Dấu '\' phải thoát TRƯỚC hai dấu kia, không thì chính những dấu '\' vừa
     * thêm vào lại bị thoát thêm lần nữa.
     */
    private static function likeChua(string $tuKhoa): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tuKhoa) . '%';
    }

    /**
     * Số đơn theo từng trạng thái, cho dải viên lọc ở đầu trang.
     *
     * KHÔNG ăn theo ô tìm và bộ lọc ngày — cố ý. Con số cạnh tên viên lọc trả
     * lời câu "bấm vào đây thì có gì", nên nó phải là số đơn của TOÀN bảng.
     * Cho nó co theo từ khoá đang gõ thì mọi con số đổi mỗi lần gõ thêm một
     * chữ, và không con số nào còn nói được điều gì.
     *
     * @return array [khoá trạng thái => số đơn], khoá '' là tổng
     */
    public static function statusCounts(): array
    {
        $counts = ['' => 0];

        foreach (array_keys(self::STATUSES) as $key) {
            $counts[$key] = 0;
        }

        foreach (Database::fetchAll('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
            // Trạng thái lạ (dữ liệu cũ, hoặc ai đó sửa tay trong CSDL) vẫn
            // được cộng vào TỔNG nhưng không tạo thêm viên lọc nào.
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['n'];
            }

            $counts[''] += (int) $row['n'];
        }

        return $counts;
    }
}
