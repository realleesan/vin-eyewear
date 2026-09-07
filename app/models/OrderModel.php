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
     * NHÃN TRẠNG THÁI KHÁCH ĐỌC ĐƯỢC — Quyết định B9.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * MỘT TRẠNG THÁI, HAI CÁCH GỌI, TUỲ HÌNH THỨC NHẬN HÀNG
     *
     * B9 chốt: đơn giao tận nơi và đơn nhận tại quầy DÙNG CHUNG trạng thái
     * 'shipping', nhưng hiện ra hai nhãn khác nhau. Lý do đơn giản: với đơn
     * nhận tại quầy thì không có ai đang giao gì cả — kính nằm trên quầy chờ
     * khách tới lấy.
     *
     * Trước 09/09/2026 cả hai cùng in "Đang giao", và trang tài khoản còn mời
     * khách bấm nút "Theo dõi vận chuyển". Khách đặt nhận tại Cầu Giấy ngồi
     * nhà đợi shipper, trong khi nhân viên nhìn cùng đơn đó chỉ thấy ô chọn
     * ghi "Đang giao" nên không đoán được khách đang đọc gì.
     *
     * ĐỂ Ở MODEL, KHÔNG ĐỂ Ở VIEW. Hai màn hình — trang tài khoản của khách và
     * ngăn kéo đơn của nhân viên — phải nói cùng một chữ, và cách duy nhất bảo
     * đảm điều đó là chúng cùng gọi một hàm. Chép luật sang view thứ hai là
     * tạo ra chỗ để hai bên lệch nhau lần nữa.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function nhanTrangThai(string $status, ?string $deliveryMethod): string
    {
        if ($status === 'shipping' && $deliveryMethod === 'pickup') {
            /* "SẴN SÀNG TẠI CỬA HÀNG", không phải "Chờ khách nhận" — FR-QT-05.

               Hai câu nói cùng một sự việc nhưng đặt trách nhiệm ở hai phía.
               "Chờ khách nhận" đọc như một lời trách: cửa hàng xong việc rồi,
               còn khách thì chưa tới. "Sẵn sàng tại cửa hàng" nói đúng thứ
               khách cần biết — hàng có ở đó rồi, tới lúc nào cũng lấy được. */
            return 'Sẵn sàng tại cửa hàng';
        }

        return self::STATUSES[$status] ?? $status;
    }

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
            $ket = Database::transaction(static function () use ($data, $cart): array {

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
                    /* `cost_price` — GIÁ VỐN, chép vào dòng đơn — FR-DH-13.
                       Đọc ngay trong câu đã khoá dòng, không hỏi lại sau: giá
                       vốn phải là con số đúng tại đúng khoảnh khắc bán. */
                    'SELECT id, name, price, cost_price, stock_quantity, status, is_visible
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

                    /*
                     * SỐ LƯỢNG PHẢI DƯƠNG — chốt này từng KHÔNG có, và đó là lỗ.
                     *
                     * Phép kiểm tồn kho ngay dưới hỏi `$stock < $quantity`. Với
                     * một số ÂM thì câu đó luôn sai, nên nó cho qua; rồi
                     * VariantModel::reserve() chạy `stock_quantity - (-5)` và
                     * TỒN KHO TĂNG LÊN. Đo được trước khi vá: đặt một đơn với
                     * quantity = -5 trên mặt hàng còn 5 cái trả về ok = true và
                     * kho nhảy lên 10.
                     *
                     * Đường web hiện không tới được đây với số âm — CartController
                     * ép max(1, …) lúc thêm và từ chối số < 1 lúc sửa. Nhưng đây
                     * đúng là tầng nhận việc kiểm lại mọi thứ đọc từ session, mà
                     * session thì sửa được; dựa vào tầng trên đã lọc sạch là bỏ
                     * đúng cái lưới cuối cùng.
                     *
                     * Số 0 cũng chặn ở đây. Trước bản này nó vẫn bị từ chối,
                     * nhưng bằng một đường vòng: reserve() trừ đi 0 nên không
                     * dòng nào đổi, MySQL trả về 0 dòng bị ảnh hưởng, và khách
                     * nhận câu "Sản phẩm vừa hết hàng." — sai sự thật, hàng còn
                     * nguyên.
                     */
                    if ($quantity < 1) {
                        throw new RuntimeException(sprintf(
                            'Số lượng của "%s" phải là số nguyên lớn hơn 0.',
                            $product['name']
                        ));
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

                    /* Tròng cắt kèm — KIỂU tròng (đơn/hai/đa) gộp với GÓI
                       chiết suất thành một mẩu tên + một con số tiền. Tra LẠI
                       từ bảng giá ngay tại đây — giỏ hàng nằm trong session và
                       chỉ nhớ hai id, đúng như nó chỉ nhớ id sản phẩm. Nhận
                       giá từ session nghĩa là cho khách tự đặt giá phần tròng.

                       Đơn mới LUÔN có cả kiểu lẫn gói: từ SRS DR-MD-07 mọi kiểu
                       tròng đang bán đều có bảng giá, nên không còn lối nào ghi
                       một dòng tròng giá 0đ "báo giá sau" — xem
                       LensModel::combo(). */
                    $lensType = LensModel::findType($row['lens_type'] ?? null);
                    $lens     = LensModel::combo($row['lens_id'] ?? null, $row['lens_type'] ?? null);

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
                    /* ─────────────────────────────────────────────────────
                       GIÁ VỐN CHÉP TẠI THỜI ĐIỂM BÁN — FR-DH-13

                       Hồ sơ sản phẩm có `cost_price`, nhưng nó là giá vốn HÔM
                       NAY. Nhập lô mới với giá khác là mọi đơn cũ đổi lợi nhuận
                       theo — tức không đơn nào tính lại được. SRS nói thẳng:
                       *"phải làm ngay dù chưa có báo cáo, vì lịch sử đã trôi
                       qua thì không dựng lại được."*

                       Cùng lý lẽ đã chép `product_name`, `unit_price`,
                       `variant_label` và `lens_name` vào dòng đơn.

                       LÀ GIÁ VỐN CỦA GỌNG, MỖI ĐƠN VỊ. Không gồm tiền tròng:
                       gói tròng không có cột giá vốn nào (bảng giá chỉ có giá
                       bán), và biến thể cũng không — chênh giá bán theo màu/cỡ
                       không kéo theo chênh giá nhập. Đọc con số này để tính lãi
                       thì nhớ nhân với `quantity` và nhớ rằng phần tròng chưa
                       trừ.

                       NULL khi sản phẩm chưa điền giá vốn. Không ép về 0: 0 là
                       "nhập không mất tiền", còn NULL là "không biết" — hai
                       điều rất khác nhau trong một bảng tính lãi. */
                    $lines[] = [
                        'product_id'    => $product['id'],
                        'cost_price'    => $product['cost_price'] !== null
                            ? (int) $product['cost_price'] : null,
                        'variant_id'    => $variant['id'] ?? null,
                        'variant_label' => $variant['label'] ?? null,
                        'lens_id'       => $lens['id'] ?? null,
                        /* KIỂU TRÒNG, chép riêng — thêm ở đợt 5 cho FR-HS-10.

                           LensModel::combo() cố ý gộp kiểu + gói thành một
                           chuỗi tên, và chú thích ở đó giải thích vì sao: mọi
                           nơi in phần tròng chỉ cần một tên và một con số.

                           Nhưng MUA LẠI thì cần dựng lại lựa chọn, không phải
                           in nó — và giá nằm ở giao điểm kiểu × gói, nên một
                           mình `lens_id` không tra được giá. Không có cột này
                           thì nút "Mua lại" hoặc bỏ mất phần tròng, hoặc phải
                           đoán kiểu bằng cách tách ngược chuỗi `lens_name` —
                           một chuỗi hiển thị, đổi lúc nào cũng được.

                           NULL với đơn đặt trước đợt 5; reorder() xử lý riêng. */
                        'lens_type'     => $lensType['id'] ?? null,
                        'lens_name'     => $lens['name'] ?? null,
                        'lens_price'    => $lensPrice,
                        // null = khách chưa biết độ, đo tại cửa hàng
                        'prescription'  => $row['rx'] ?? null,
                        /* HỒ SƠ ĐO MẮT NGUỒN — UC-03 bước 5, thêm ở đợt 7.

                           KHÁC HẲN cột `prescription` ngay trên, và hai cột
                           lệch nhau là chuyện bình thường:

                             prescription      SỐ ĐO đã chốt của dòng hàng này.
                                               Bản chụp, đi xuống phiếu mài, đọc
                                               được kể cả khi hồ sơ nguồn bị xoá.
                             prescription_id   khách LẤY SỐ ẤY TỪ ĐÂU. NULL khi
                                               họ gõ tay.

                           Luồng A1 của UC-03 cho phép chọn hồ sơ rồi sửa vài ô,
                           và CartController bỏ mã nguồn đi đúng lúc ấy — nên
                           một dòng có mã nguồn thì số đo của nó ĐÚNG là số của
                           hồ sơ ấy. Xem khối chú thích ở nhánh 'so-do'. */
                        'prescription_id' => $row['rx_ho_so'] ?? null,
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
                   trả nốt khi nhận; 'full' = chuyển đủ một lần.

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

                /* Cột `cost_price` đến ở migration đợt 5. Máy chưa nâng cấp thì
                   bỏ nó khỏi câu INSERT thay vì ném lỗi 1054 vào giữa một giao
                   dịch đặt hàng — mất một con số kế toán còn hơn mất cả cái đơn.
                   Hỏi MỘT lần trước vòng lặp: columnExists() có cache riêng,
                   nhưng gọi nó cho từng dòng hàng vẫn là thói quen xấu. */
                $coGiaVon   = self::coGiaVon();
                $coKieuTrong = self::coKieuTrong();
                $coHoSoNguon = self::coHoSoNguon();

                foreach ($lines as $line) {
                    if (!$coGiaVon) {
                        unset($line['cost_price']);
                    }

                    if (!$coHoSoNguon) {
                        unset($line['prescription_id']);
                    }

                    if (!$coKieuTrong) {
                        unset($line['lens_type']);
                    }

                    $cot = array_keys($line);
                    $ten = implode(', ', $cot);
                    $val = ':' . implode(', :', $cot);

                    Database::execute(
                        'INSERT INTO order_items (id, order_id, ' . $ten . ')
                         VALUES (:id, :order_id, ' . $val . ')',
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
                return ['ok' => true, 'id' => $orderId, 'code' => $code,
                        'total' => $total, 'items' => $cart];
            });

            /* THƯ "ĐÃ NHẬN ĐƠN" — FR-EM-01, mốc thứ nhất.

               NGOÀI transaction, sau khi nó đã commit: transaction ở đây trừ
               kho và ghi hoá đơn, và một lá thư không được phép đứng trong
               đường đó (FR-EM-06). Đọc lại bản ghi vì $data là dữ liệu form,
               chưa có mã đơn lẫn tổng tiền đã chốt. */
            if (($ket['ok'] ?? false) && isset($ket['id'])) {
                $don = self::find((string) $ket['id']);

                if ($don !== null) {
                    EmailEvents::donHang($don, 'tao');
                }
            }

            return $ket;
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
     * Chốt ở `lens_type`, KHÔNG ở `lens_id`. Hai id nay luôn đi cùng nhau
     * (SRS DR-MD-07: mọi kiểu tròng đều có bảng giá), nên hai cách chốt cho
     * cùng kết quả — nhưng `lens_type` mới là thứ NÓI RA rằng dòng này có mài
     * tròng theo độ, còn `lens_id` chỉ là gói vật liệu. Giữ nguyên chỗ chốt
     * này: thêm một kiểu tròng không có gói (như "Mắt đặt" đã gỡ) thì chốt ở
     * lens_id sẽ để đúng loại đơn khó làm nhất thoát khỏi việc đặt cọc.
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
     * ─────────────────────────────────────────────────────────────────────────
     * BA TRẠNG THÁI KHÁCH TỰ HUỶ ĐƯỢC — SRS v2.1.0, BR-HS-13.1
     *
     * Mới · Đã xác nhận · Đang chuẩn bị. Từ "Đang giao" trở đi phải liên hệ
     * cửa hàng: hàng có thể đã nằm trên xe, và website không đồng bộ trạng thái
     * vận chuyển thời gian thực với ai cả — một nút huỷ ở đó đổi trạng thái
     * trong CSDL trong khi hàng vẫn đang đi, và hai bên hiểu khác nhau về cùng
     * một đơn.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public const KHACH_HUY_DUOC = ['new', 'confirmed', 'preparing'];

    /**
     * Lý do huỷ khách chọn — BR-HS-13.5, bắt buộc chọn một.
     *
     * DANH SÁCH CÓ SẴN chứ không phải ô chữ tự do: cửa hàng cần đếm được vì sao
     * khách bỏ đơn, mà một cột chữ tự do thì không đếm được gì. Mục "khác" mở
     * ô nhập tay để không ép người ta chọn bừa một lý do sai.
     */
    public const LY_DO_HUY = [
        'doi-y'       => 'Đổi ý, không mua nữa',
        'sai-thong-so'=> 'Đặt nhầm thông số',
        'tim-duoc-re' => 'Tìm được nơi khác phù hợp hơn',
        'cho-lau'     => 'Chờ lâu quá',
        'khac'        => 'Lý do khác',
    ];

    /**
     * Khách có tự huỷ được đơn này không — trả null nếu ĐƯỢC, hoặc câu từ chối.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * MỐC BẮT ĐẦU MÀI PHỦ QUYẾT TRẠNG THÁI — BR-HS-13.2
     *
     * Đây là phần dễ bỏ sót nhất của cả ca dùng. Đơn vẫn ở "Đang chuẩn bị" —
     * tức nằm trong ba trạng thái cho phép — nhưng nếu đã bấm mốc bắt đầu mài
     * thì phôi tròng ĐÃ ĐƯỢC CẮT THEO SỐ ĐO CỦA CHÍNH KHÁCH NÀY. Vật tư ấy
     * không bán lại cho ai được, nên nó không còn là chuyện đổi ý nữa.
     *
     * Kiểm cả hai điều kiện, và kiểm mốc mài TRƯỚC: câu từ chối vì đã mài nói
     * đúng lý do thật, còn câu từ chối vì trạng thái thì sai và khách sẽ gọi
     * điện hỏi lại.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function khachHuyDuoc(array $order): ?string
    {
        if (self::daBatDauMai($order)) {
            return 'Đơn này đã bắt đầu cắt tròng theo số đo của bạn nên không tự huỷ được. '
                 . 'Vui lòng liên hệ cửa hàng để được hỗ trợ.';
        }

        $tt = (string) ($order['status'] ?? '');

        if (!in_array($tt, self::KHACH_HUY_DUOC, true)) {
            return 'Đơn đang ở trạng thái «' . (self::STATUSES[$tt] ?? $tt)
                 . '» nên không tự huỷ được. Vui lòng liên hệ cửa hàng.';
        }

        return null;
    }

    /**
     * Khách tự huỷ đơn của mình — UC-02.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * KIỂM LẠI ĐIỀU KIỆN Ở ĐÂY, KHÔNG TIN VIEW
     *
     * View đã ẩn nút huỷ với đơn không đủ điều kiện, nhưng mã đơn nằm ngay
     * trong HTML của chính trang đó, và một tab mở từ trước khi nhân viên đổi
     * trạng thái vẫn còn nút cũ (E3 của UC-02). Đọc lại bản ghi và hỏi lại
     * khachHuyDuoc() là thứ duy nhất chặn thật.
     *
     * BỌC TRONG TRANSACTION cùng với việc hoàn kho: nửa vời ở đây nghĩa là đơn
     * đã huỷ mà hàng chưa về kho, hoặc ngược lại. changeStatus() lo cả hai và
     * đã tự bọc transaction — nên ở đây chỉ cần gọi nó chứ không dựng thêm một
     * lớp nữa.
     *
     * YÊU CẦU HOÀN TIỀN TẠO NGOÀI transaction đó, và CỐ Ý không chặn: xem khối
     * chú thích ở RefundRequestModel::taoChoDonHuy(). Việc huỷ đã xong và
     * khách đã thấy nó xong; một trục trặc ở bước ghi sổ hoàn tiền không được
     * phép cuộn ngược điều đó.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array{ok:bool, error?:string, refund?:?string, code?:string}
     */
    public static function khachHuy(
        string $code,
        string $userId,
        string $lyDoMa,
        string $lyDoKhac
    ): array {
        $order = self::findByCode($code, $userId);

        if ($order === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy đơn hàng này.'];
        }

        /* ĐƠN CỦA KHÁCH VÃNG LAI KHÔNG TỰ HUỶ ĐƯỢC.

           findByCode() cho qua khi `user_id` là NULL — cố ý, để trang xác nhận
           đơn mở được bằng mã. Nhưng "mở xem được" khác "huỷ được": ai có mã
           đơn cũng huỷ được thì mã đơn thành một cái nút phá hoại. */
        if (($order['user_id'] ?? null) !== $userId) {
            return ['ok' => false, 'error' => 'Đơn này không thuộc tài khoản của bạn.'];
        }

        $chan = self::khachHuyDuoc($order);

        if ($chan !== null) {
            return ['ok' => false, 'error' => $chan];
        }

        if (!isset(self::LY_DO_HUY[$lyDoMa])) {
            return ['ok' => false, 'error' => 'Vui lòng chọn lý do huỷ đơn.'];
        }

        $lyDo = self::LY_DO_HUY[$lyDoMa];

        if ($lyDoMa === 'khac') {
            $lyDoKhac = trim($lyDoKhac);

            if ($lyDoKhac === '') {
                return ['ok' => false, 'error' => 'Chọn "Lý do khác" thì vui lòng ghi rõ lý do.'];
            }

            $lyDo = utf8Substr($lyDoKhac, 0, 200);
        }

        $id = (string) $order['id'];

        /* MỌI LUẬT ĐI KÈM VIỆC HUỶ NẰM TRONG changeStatus():

             · ghi `order_status_history` (thanh tiến trình của khách đọc bảng đó)
             · ghi vết kiểm toán 'order.cancel'
             · hoàn kho, đúng một lần
             · ghi `cancelled_by` — tham số thứ năm, 'customer' thay cho mặc định
             · tạo yêu cầu hoàn tiền nếu cửa hàng đang giữ tiền của đơn

           Ở đây KHÔNG ghi thêm vết 'order.cancel' nào nữa. Bản đầu có, và mỗi
           lần khách huỷ một đơn thì nhật ký hiện "Huỷ đơn hàng" hai lần cho một
           thao tác — dòng thứ hai còn mang actor_id NULL vì đường của khách
           không có phiên quản trị. Lý do huỷ đã nằm trong dòng do changeStatus
           ghi, kèm cả trạng thái trước và sau. */
        self::changeStatus($id, 'cancelled', $userId, $lyDo, 'customer');

        /* CÓ SINH RA YÊU CẦU HOÀN TIỀN KHÔNG — hỏi lại sổ, không đoán.

           Nơi gọi dùng câu trả lời này để quyết định có nói chuyện tiền trong
           dòng báo thành công hay không, và câu ấy phải đúng: hứa hoàn tiền cho
           một đơn chưa trả đồng nào thì khách sẽ chờ một khoản không tồn tại,
           còn im lặng về một đơn đã cọc thì họ gọi điện hỏi — đúng cuộc gọi mà
           cả ca dùng này sinh ra để khỏi phải nhận. */
        $refund = RefundRequestModel::available()
            ? RefundRequestModel::firstWhere(['order_id' => $id])
            : null;

        return [
            'ok'     => true,
            'refund' => $refund !== null ? (string) $refund['id'] : null,
            'code'   => (string) $order['code'],
        ];
    }

    // ========================================================================
    // TỰ HUỶ ĐƠN CHUYỂN KHOẢN QUÁ HẠN — FR-TT-11
    // ========================================================================

    /** Bao nhiêu giờ kể từ lúc đặt thì đơn chưa trả tiền bị huỷ. */
    public const QUA_HAN_SAU_GIO = 24;

    /** Hai lượt quét cách nhau tối thiểu bao nhiêu giây. */
    private const QUET_CACH_NHAU = 600;

    /** Một lượt quét huỷ tối đa bao nhiêu đơn. */
    private const QUET_TOI_DA = 20;

    /**
     * Quét và huỷ đơn chuyển khoản quá hạn chưa thanh toán.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * BÁM THEO LƯỢT TRUY CẬP, KHÔNG THEO ĐỒNG HỒ — SRS nói đích danh
     *
     * Hosting là InfinityFree gói miễn phí: không SSH, không cron, không tiến
     * trình chạy nền. Cách duy nhất để một việc định kỳ xảy ra là mượn một lượt
     * truy cập nào đó của khách. Đổi lại, "24 giờ" ở đây là "24 giờ VÀ có người
     * ghé trang sau đó" — với một cửa hàng đang bán thì chênh lệch tính bằng
     * phút, và đơn quá hạn thêm vài phút không hại ai.
     *
     * BA CÁI PHANH, vì hàm này chạy trên lượt duyệt trang của một người thật:
     *
     *   1. Giãn cách 10 phút, chốt bằng mtime của một tệp trong storage/ —
     *      KHÔNG bằng CSDL. Hàm này chạy trước router ở mọi lượt GET, kể cả
     *      trang tĩnh; hỏi CSDL để biết "đã tới giờ chưa" là mở một kết nối ở
     *      gần như mọi lượt, phá đúng tính lười mà core/Database.php dựng ra.
     *   2. Trần 20 đơn mỗi lượt. Ngày đầu bật tính năng có thể có hàng trăm đơn
     *      cũ đủ điều kiện; huỷ hết trong một lượt là một người xui xẻo phải
     *      chờ hàng trăm transaction chạy xong mới thấy trang.
     *   3. Nuốt mọi ngoại lệ. Đây là việc dọn dẹp đi nhờ; nó không được phép
     *      làm hỏng trang của người đang chở nó.
     *
     * GHI MỐC TRƯỚC KHI LÀM, không phải sau: hai lượt truy cập gần nhau cùng
     * vào được đây thì lượt thứ hai phải thấy mốc mới ngay, kể cả khi lượt đầu
     * còn đang huỷ dở.
     *
     * VẾ "BÁO KHÁCH TRƯỚC 2 GIỜ" của FR-TT-11 làm ở canhBaoSapQuaHan(), gọi ở
     * cuối hàm này. Nó KHÔNG nằm trong vòng lặp huỷ — hai tập đơn rời nhau.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return int số đơn đã huỷ
     */
    public static function quetDonQuaHan(): int
    {
        try {
            /* ─────────────────────────────────────────────────────────────
               PHANH ĐẦU TIÊN LÀ MỘT TỆP, KHÔNG PHẢI CƠ SỞ DỮ LIỆU

               Hàm này chạy TRƯỚC router ở mọi lượt GET, kể cả những trang xưa
               nay không đụng tới CSDL: /chinh-sach, /gioi-thieu, và cả trang
               404. core/Database.php mở kết nối theo kiểu lười chính vì thế —
               "các trang tĩnh không đụng tới DB thì không tốn một kết nối
               nào".

               Hỏi `app_settings` để biết đã tới lượt quét chưa là phá đúng
               tính chất đó: 999 trên 1000 lượt sẽ mở một kết nối, chạy hai câu
               lệnh, rồi kết luận "chưa tới giờ". Và khi MySQL trục trặc thì
               mọi trang tĩnh cùng đứng chờ hết thời gian kết nối.

               Một tệp mtime trả lời cùng câu hỏi mà không rời đĩa cục bộ. Dự
               án đã có đúng khuôn mẫu này ở storage/sepay/nhip-keo.json.

               KHÔNG GHI ĐƯỢC storage/ (hosting đặt chỉ đọc) thì THÔI QUÉT, chứ
               không lùi về CSDL: không có phanh nghĩa là mọi lượt truy cập đều
               quét, và cái giá đó lớn hơn hẳn việc đơn quá hạn nằm lại. Ghi
               error_log một lần để người vận hành thấy.
               ───────────────────────────────────────────────────────────── */
            $tep = ROOT_PATH . '/storage/quet';

            if (!is_dir($tep) && !@mkdir($tep, 0770, true) && !is_dir($tep)) {
                error_log('OrderModel::quetDonQuaHan: không tạo được ' . $tep);

                return 0;
            }

            $tep .= '/don-qua-han.txt';

            if (is_file($tep) && time() - (int) @filemtime($tep) < self::QUET_CACH_NHAU) {
                return 0;
            }

            /* CHẠM TỆP TRƯỚC KHI LÀM. Hai lượt truy cập gần nhau cùng lọt qua
               phép so mtime thì lượt sau phải thấy mốc mới ngay, kể cả khi lượt
               đầu còn đang huỷ dở. Cả hai cùng quét cũng không hại — changeStatus()
               khoá dòng bằng FOR UPDATE nên không đơn nào bị hoàn kho hai lần —
               nhưng không nên để xảy ra. */
            if (@file_put_contents($tep, (string) time(), LOCK_EX) === false) {
                error_log('OrderModel::quetDonQuaHan: không ghi được ' . $tep);

                return 0;
            }

            /* CHỈ ĐƠN CHƯA NHẬN ĐỒNG NÀO. 'deposit_paid' KHÔNG nằm trong đây:
               khách đã chuyển cọc là đã cam kết, và huỷ đơn của họ vì chưa trả
               nốt là một quyết định của con người, không phải của đồng hồ. */
            $dons = Database::fetchAll(
                "SELECT id, code FROM orders
                  WHERE payment_method = 'bank_transfer'
                    AND status = 'new'
                    AND payment_status = 'unpaid'
                    AND created_at < (NOW() - INTERVAL " . self::QUA_HAN_SAU_GIO . " HOUR)
                  ORDER BY created_at ASC
                  LIMIT " . self::QUET_TOI_DA
            );

            foreach ($dons as $don) {
                // 'system' vào cột `cancelled_by` — phân biệt với khách tự huỷ
                // và nhân viên huỷ. changeStatus() lo hoàn kho và ghi vết.
                self::changeStatus(
                    (string) $don['id'],
                    'cancelled',
                    null,
                    'Quá hạn thanh toán ' . self::QUA_HAN_SAU_GIO . ' giờ',
                    'system'
                );

            }

            // Vế thứ hai của FR-TT-11 — xem hàm ngay dưới.
            self::canhBaoSapQuaHan();

            return count($dons);
        } catch (Throwable $e) {
            error_log('OrderModel::quetDonQuaHan: ' . $e->getMessage());

            return 0;
        }
    }

    /** Báo trước bao nhiêu giờ so với mốc tự huỷ. */
    private const BAO_TRUOC_GIO = 2;

    /**
     * Thư nhắc những đơn SẮP bị huỷ vì quá hạn — FR-TT-11 vế thứ hai.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * MỘT CÂU QUÉT RIÊNG, KHÔNG PHẢI MỘT DÒNG TRONG VÒNG LẶP HUỶ
     *
     * Thư này gửi HAI GIỜ TRƯỚC khi đơn bị huỷ, nên nó nói về những đơn mà
     * vòng lặp trên KHÔNG chạm tới: đơn 22–24 giờ tuổi, vẫn còn sống, vẫn còn
     * kịp trả tiền. Hai tập hợp rời nhau hoàn toàn.
     *
     * ĐI NHỜ ĐÚNG CÁI PHANH CỦA VÒNG LẶP HUỶ — gọi từ trong quetDonQuaHan(),
     * sau khi tệp mốc đã được chạm. Nghĩa là không có tệp thứ hai để quản, và
     * hai việc cùng nhịp 10 phút. Sai số 10 phút trên một lời báo trước 2 giờ
     * là thứ không ai đo được.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * "HAI GIỜ" KHÔNG PHẢI LÚC NÀO CŨNG ĐỦ HAI GIỜ, VÀ ĐÓ LÀ GIỚI HẠN THẬT
     *
     * Việc định kỳ ở đây đi nhờ lượt truy cập. Một đêm không ai vào trang thì
     * đơn 22 giờ tuổi lúc nửa đêm có thể mãi tới 23 giờ 50 tuổi mới nhận được
     * thư — và mười phút sau thì bị huỷ. Lá thư vẫn đúng (đơn CHƯA bị huỷ lúc
     * gửi), chỉ là lời báo trước ngắn hơn hứa hẹn.
     *
     * Không có cách sửa nào trong khuôn khổ hosting không cron. Đổi lại, khoá
     * chống trùng theo id đơn bảo đảm mỗi đơn nhận đúng một thư, nên đường
     * hỏng tệ nhất là thư tới muộn — không phải khách bị làm phiền nhiều lần.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return int số thư đã xếp hàng
     */
    private static function canhBaoSapQuaHan(): int
    {
        if (!EmailQueueModel::available()) {
            return 0;
        }

        $tu  = self::QUA_HAN_SAU_GIO - self::BAO_TRUOC_GIO;

        /* CÙNG BỘ ĐIỀU KIỆN với câu quét huỷ, chỉ khác cửa sổ thời gian. Phải
           cùng: báo cho một đơn mà vòng lặp kia sẽ không bao giờ huỷ là một
           lời doạ suông, và đó là cách nhanh nhất để khách mất tin vào thư của
           cửa hàng. */
        /* ĐỊA CHỈ NHẬN LẤY NGAY TRONG CÂU QUÉT, không gọi lại từng đơn một.

           EmailEvents::donHang() lùi về email tài khoản khi `customer_email`
           rỗng (đơn đặt trước ngày form thanh toán hỏi email), và nó làm việc
           ấy bằng một truy vấn cho MỖI đơn. Ở đường nóng thì được — mỗi lần
           đúng một đơn. Ở đây thì tới hai mươi đơn một lượt, trên lượt duyệt
           trang của một người thật. Một LEFT JOIN trả lời cùng câu hỏi cho cả
           lô. */
        $dons = Database::fetchAll(
            "SELECT o.id, o.code, o.customer_name, o.user_id, o.total,
                    COALESCE(NULLIF(o.customer_email, ''), u.email) AS email_nhan
               FROM orders o
               LEFT JOIN users u ON u.id = o.user_id
              WHERE o.payment_method = 'bank_transfer'
                AND o.status = 'new'
                AND o.payment_status = 'unpaid'
                AND o.created_at <  (NOW() - INTERVAL " . $tu . " HOUR)
                AND o.created_at >= (NOW() - INTERVAL " . self::QUA_HAN_SAU_GIO . " HOUR)
              ORDER BY o.created_at ASC
              LIMIT " . self::QUET_TOI_DA
        );

        $n = 0;

        foreach ($dons as $don) {
            $id = EmailQueueModel::xepHang(
                'don.sap_qua_han',
                trim((string) ($don['email_nhan'] ?? '')) ?: null,
                [
                    'ten_khach' => (string) ($don['customer_name'] ?? 'bạn'),
                    'ma_don'    => (string) $don['code'],
                    'tong_tien' => money((int) ($don['total'] ?? 0)),
                    'so_gio'    => (string) self::BAO_TRUOC_GIO,
                    'link_don'  => rtrim((string) config('app.url', ''), '/')
                        . '/tai-khoan?muc=don-hang&don=' . rawurlencode((string) $don['code']),
                ],
                // Một đơn một lá, mãi mãi — kể cả khi hai lượt quét cùng thấy nó.
                'don.sap_qua_han:' . $don['id'],
                null,
                $don['user_id'] ?? null,
                'order',
                (string) $don['id']
            );

            if ($id !== null) {
                $n++;
            }
        }

        return $n;
    }

    /** CSDL đã có cột `order_items`.`cost_price` chưa — migration đợt 5. */
    public static function coGiaVon(): bool
    {
        return Database::columnExists('order_items', 'cost_price');
    }

    /** CSDL đã có cột `order_items`.`prescription_id` chưa — migration đợt 7. */
    public static function coHoSoNguon(): bool
    {
        return Database::columnExists('order_items', 'prescription_id');
    }

    /** CSDL đã có cột `order_items`.`lens_type` chưa — migration đợt 5. */
    public static function coKieuTrong(): bool
    {
        return Database::columnExists('order_items', 'lens_type');
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
        /* HỒ SƠ ĐO MẮT NGUỒN — UC-03 bước 5: *"để sau này tra được đơn này
           cắt theo hồ sơ nào."* Câu ấy chỉ thành sự thật khi có màn hình đọc
           ra; cột không ai đọc thì chỉ là một cột.

           JOIN CÓ ĐIỀU KIỆN vì cột `prescription_id` chỉ có sau migration đợt
           7 — nhắc tới nó trên máy chưa nâng cấp là lỗi 1054 làm trắng cả màn
           đơn hàng. Cùng lối phòng thủ với WaitlistModel::dangChoCho().

           LẤY NGÀY ĐO VÀ NGUỒN, không lấy lại số đo: số đã nằm sẵn trong cột
           `prescription` của chính dòng hàng, và đó mới là số đã dùng để mài.
           Thứ hồ sơ nguồn trả lời thêm là "số ấy từ đâu ra" — do kỹ thuật viên
           đo hôm nào, hay khách tự khai. */
        $coHoSo = self::coHoSoNguon() && Database::tableExists('customer_prescriptions');

        $cot  = $coHoSo
            ? ', cp.measured_at AS hs_ngay, cp.source AS hs_nguon'
            : ', NULL AS hs_ngay, NULL AS hs_nguon';
        $join = $coHoSo
            ? ' LEFT JOIN customer_prescriptions cp ON cp.id = oi.prescription_id'
            : '';

        return Database::fetchAll(
            'SELECT oi.*, p.brand, p.slug, p.images' . $cot . '
               FROM order_items oi
               LEFT JOIN products p ON p.id = oi.product_id' . $join . '
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
    public static function logStatus(
        string $orderId,
        string $status,
        ?string $changedBy = null,
        ?string $lyDo = null
    ): void {
        $ban = [
            'id'         => uuid(),
            'order_id'   => $orderId,
            'status'     => $status,
            'changed_by' => $changedBy,
        ];

        /* CHƯA CHẠY MIGRATION THÌ BỎ LÝ DO, ĐỪNG BỎ CẢ MỐC.

           Nhắc tới một cột chưa có là lỗi 1054, mà câu này nằm trong cùng
           transaction với việc đổi trạng thái — tức là trên một máy chưa nâng
           cấp, mọi thao tác đổi trạng thái đơn sẽ đổ. Mất một dòng lý do thì
           chỉ mất chú thích; mất cả transaction thì mất việc. */
        if ($lyDo !== null && $lyDo !== '' && self::coLyDoTrangThai()) {
            $ban['ly_do'] = utf8Substr($lyDo, 0, 255);
        }

        $cot = array_keys($ban);

        Database::execute(
            'INSERT INTO order_status_history (`' . implode('`, `', $cot) . '`)'
            . ' VALUES (:' . implode(', :', $cot) . ')',
            $ban
        );
    }

    /** CSDL đã có cột lý do trên bảng lịch sử trạng thái chưa. */
    public static function coLyDoTrangThai(): bool
    {
        return Database::columnExists('order_status_history', 'ly_do');
    }

    /** CSDL đã có bộ cột mốc mài chưa (migration 2026-09-07). */
    public static function coMocMai(): bool
    {
        return Database::columnExists('orders', 'mai_bat_dau_luc');
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
    public static function changeStatus(
        string $id,
        string $status,
        ?string $changedBy = null,
        ?string $lyDo = null,
        string $nguonHuy = 'staff'
    ): void {
        /* "VỪA HUỶ LẦN ĐẦU" — cờ mang ra NGOÀI transaction.

           Việc ghi sổ hoàn tiền cố ý chạy ngoài: nó không được phép cuộn ngược
           một thao tác huỷ đã xong (xem RefundRequestModel::taoChoDonHuy).
           Nhưng nó chỉ được chạy đúng MỘT lần cho mỗi đơn, và câu trả lời "lần
           này có phải lần đầu không" chỉ bên trong transaction mới biết. */
        $vuaHuy = false;

        /* "TRẠNG THÁI CÓ THẬT SỰ ĐỔI KHÔNG" — mang ra ngoài để phần gửi thư ở
           cuối hàm đọc được. Bên trong transaction nó là $truoc !== $status. */
        $daDoi = false;
        $don   = null;

        Database::transaction(static function () use (
            $id, $status, $changedBy, $lyDo, $nguonHuy, &$vuaHuy, &$daDoi
        ): void {
            /*
             * ĐỌC TRẠNG THÁI CŨ TRƯỚC KHI GHI ĐÈ, VÀ KHOÁ DÒNG LẠI.
             *
             * Việc hoàn kho phụ thuộc vào CHIỀU đi chứ không phải vào trạng
             * thái đích: chỉ lần ĐẦU sang 'cancelled' mới trả hàng về kho. Đọc
             * sau khi update thì không còn phân biệt được "vừa huỷ" với "đã
             * huỷ từ trước, nhân viên bấm lại nút Lưu" — và bấm lại là chuyện
             * xảy ra thường xuyên.
             *
             * ─────────────────────────────────────────────────────────────
             * FOR UPDATE — thêm ở đợt 4, và không phải để phòng xa
             *
             * Từ UC-02, khách tự huỷ được đơn. Nghĩa là từ nay CÓ HAI NGƯỜI
             * khác nhau, ở hai phiên khác nhau, cùng bấm huỷ một đơn được:
             * khách bấm trên trang tài khoản đúng lúc nhân viên chọn "Đã huỷ"
             * trong khu quản trị vì khách vừa gọi điện. Trước đợt 4 chuyện đó
             * không xảy ra được — chỉ nhân viên mới huỷ được, và hai lần bấm
             * của cùng một người thì PHP đã xếp hàng sẵn bằng khoá file phiên.
             *
             * Không có FOR UPDATE, cả hai đọc thấy 'preparing', cả hai thấy
             * "chưa huỷ", và cả hai chạy vòng hoàn kho: một đơn 2 gọng trả về
             * kho 4 chiếc. Không lỗi, không dòng log nào, và số tồn kho sai
             * vĩnh viễn cho tới lần kiểm kê tay.
             *
             * FOR UPDATE bắt người thứ hai chờ tới khi người thứ nhất commit
             * rồi mới đọc — và khi ấy đọc thấy 'cancelled' nên bỏ qua đúng.
             * ─────────────────────────────────────────────────────────────
             */
            $donCu = Database::fetchOne(
                'SELECT * FROM orders WHERE id = :id FOR UPDATE',
                ['id' => $id]
            );
            $truoc = (string) ($donCu['status'] ?? '');

            self::update($id, ['status' => $status]);
            self::logStatus($id, $status, $changedBy, $lyDo);

            /* VẾT ĐỔI TRẠNG THÁI — SNFR-11 gọi đích danh "huỷ đơn hàng".

               `order_status_history` (do logStatus ghi) đã lưu chuỗi trạng
               thái, nhưng nó là sổ NGHIỆP VỤ của riêng đơn: không có IP, và
               người đọc nó là nhân viên xử lý đơn. SNFR-11 đòi một vết KIỂM
               TOÁN đọc được cùng chỗ với các thao tác nhạy cảm khác, lọc được
               theo người thực hiện. Hai bảng, hai mục đích — giữ cả hai.

               Chỉ ghi khi trạng thái THẬT SỰ đổi: nhân viên bấm Lưu lại đúng
               trạng thái cũ là chuyện xảy ra suốt, và một bảng vết đầy dòng
               "không đổi gì" thì không ai đọc nữa.

               Huỷ đơn tách riêng thành order.cancel: đây là hành động duy nhất
               trong nhóm này làm mất doanh thu và trả hàng về kho, nên nó đáng
               có bộ lọc riêng ở màn xem vết. */
            $daDoi = $truoc !== $status;

            if ($truoc !== $status) {
                /* KHÁCH TỰ HUỶ CÓ MÃ RIÊNG — FR-NK-07.

                   Trước đợt 5 mọi lần huỷ đều ghi 'order.cancel', nên câu hỏi
                   "tháng này bao nhiêu đơn khách tự bỏ" — câu duy nhất trong
                   nhóm này nói lên điều gì đó về sản phẩm và về giá — không lọc
                   ra được. Nguồn huỷ đã có sẵn ở tham số, chỉ là chưa ai dùng.

                   'system' (tự huỷ quá hạn) vẫn ghi 'order.cancel': nó là hành
                   vi của cửa hàng, chỉ khác là do đồng hồ chứ không do người. */
                $maHuy = $nguonHuy === 'customer' ? 'order.cancel_customer' : 'order.cancel';

                self::ghiVetTien(
                    $id,
                    $status === 'cancelled' ? $maHuy : 'order.status',
                    sprintf(
                        'Trạng thái %s -> %s',
                        self::STATUSES[$truoc] ?? ($truoc !== '' ? $truoc : '(chưa có)'),
                        self::STATUSES[$status] ?? $status
                    ) . ($lyDo !== null && $lyDo !== '' ? ' — lý do: ' . utf8Substr($lyDo, 0, 180) : ''),
                    $donCu,
                    /* Tự huỷ đơn quá hạn chạy bám theo lượt truy cập của một
                       người bất kỳ — thường là chính nhân viên đang mở khu quản
                       trị. Không đánh dấu thì vết ghi tên họ cho một việc họ
                       không làm. Xem AuditLogModel::write(). */
                    $status === 'cancelled' && $nguonHuy === 'system'
                );
            }

            /*
             * ─────────────────────────────────────────────────────────────
             * TỒN KHO ĐI THEO TRẠNG THÁI — MỘT CHIỀU
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
             * HOÀN KHO NAY LÀ MỘT CHIỀU — SRS v2.1.0, F10.
             *
             * Trước đây có cả chiều ngược lại: nhân viên mở lại đơn đã huỷ thì
             * hệ thống trừ kho lần nữa. Chức năng mở lại đơn đã gỡ, nên 'Đã
             * huỷ' trở lại là trạng thái KẾT THÚC và không có đường nào rời
             * khỏi nó — xem OrderAdminController::updateStatus().
             *
             * Giữ chỗ này chỉ còn nhánh cộng. Nếu sau này ai đó mở lại đường
             * kia thì phải dựng lại cả nhánh trừ, nếu không kho sẽ phình lên
             * đúng bằng số lần mở lại.
             * ─────────────────────────────────────────────────────────────
             */
            if ($status === 'cancelled' && $truoc !== 'cancelled') {
                $vuaHuy = true;

                /* AI HUỶ — cột riêng, không suy ra từ `changed_by`.

                   `changed_by` mang id tài khoản. Khi khách tự huỷ, id ấy chính
                   là khách; nhưng khi NHÂN VIÊN huỷ hộ một khách gọi điện thì id
                   là của nhân viên, và không có gì phân biệt với việc nhân viên
                   tự quyết huỷ. Cột này trả lời thẳng câu hỏi nghiệp vụ.

                   Đặt ở ĐÂY chứ không ở khachHuy(): đây là cửa duy nhất đổi
                   `status`, nên mọi đường huỷ — khách, nhân viên, và sau này là
                   đường tự huỷ đơn quá hạn — đều đi qua và đều được ghi nguồn.
                   Để ở nơi gọi thì đường nào quên gọi sẽ lặng lẽ ghi NULL.

                   Bọc trong columnExists vì cột đến ở migration đợt 4: máy chưa
                   nâng cấp thì mất một dòng ngữ cảnh, không mất cả thao tác huỷ. */
                if (Database::columnExists('orders', 'cancelled_by')) {
                    Database::execute(
                        'UPDATE orders SET cancelled_by = :nguon WHERE id = :id',
                        ['nguon' => $nguonHuy, 'id' => $id]
                    );
                }

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

                    VariantModel::release($dong['variant_id'] ?? null, $spId, $sl);
                }
            }

            if ($status !== 'completed') {
                return;
            }

            $order = self::find($id);

            /*
             * ─────────────────────────────────────────────────────────────────
             * ĐIỀU KIỆN LÀ "CHƯA TRẢ ĐỦ", KHÔNG PHẢI "CHƯA TRẢ GÌ" — sửa 09/09
             *
             * Trước đó chốt ở `=== 'unpaid'`, tức bỏ sót đúng nấc giữa
             * 'deposit_paid'. Chính docblock của markPaid() ngay dưới đã cảnh
             * báo điều này, và bản thân câu UPDATE trong markPaid() dùng
             * `payment_status <> 'paid'` — nên hai chỗ nói hai luật khác nhau.
             *
             * Kịch bản đã dựng lại được: đơn COD 4.400.000đ có cắt tròng, khách
             * chuyển cọc 1.320.000đ nên đơn ở 'deposit_paid'. Shipper giao hàng
             * và thu nốt 3.080.000đ tiền mặt. Nhân viên chuyển đơn sang "Hoàn
             * tất" — nhánh này không chạy, đơn nằm mãi ở 'deposit_paid'.
             *
             * Khách mở trang tài khoản thấy huy hiệu "Đã đặt cọc" và câu "Cảm
             * ơn bạn — đã nhận tiền cọc" trên một đơn đã giao xong và đã trả
             * đủ. Bảng tổng quan thì vẫn xếp 3.080.000đ ấy vào cột chưa thu.
             * ─────────────────────────────────────────────────────────────────
             */
            if ($order !== null
                && $order['payment_method'] === 'cod'
                && $order['payment_status'] !== 'paid'
            ) {
                self::markPaid($id);
            }
        });

        /*
         * ─────────────────────────────────────────────────────────────────────
         * SỔ HOÀN TIỀN — NGOÀI transaction, và ở ĐÂY chứ không ở khachHuy()
         *
         * Đặt ở nơi gọi thì chỉ đường khách tự huỷ mới sinh ra yêu cầu hoàn
         * tiền. Nhưng đường huỷ đông hơn hẳn lại là nhân viên huỷ trong khu
         * quản trị — và nó bao trọn cả nhánh mà công thức BR-DH-14.1 sinh ra
         * để xử lý: khách gọi điện xin huỷ SAU khi đã bấm mốc mài. Khách không
         * tự huỷ được ca đó (khachHuyDuoc() chặn, đúng), nên nếu chỉ móc vào
         * khachHuy() thì `lens_amount`, cờ `lens_started` và cả phép trừ tiền
         * tròng đều là mã chết: mọi yêu cầu trong sổ đều là "huỷ trước khi
         * mài", và mọi lần hoàn có trừ tiền tròng đều làm tay ngoài hệ thống.
         *
         * Đặt ở đây thì mọi đường huỷ đều đi qua, vì đây là cửa duy nhất đổi
         * `status` (xem khối chú thích trên hàm).
         *
         * NGOÀI transaction, và KHÔNG chặn: xem RefundRequestModel::taoChoDonHuy().
         * Việc huỷ đã xong và người bấm đã thấy nó xong; một trục trặc ở bước
         * ghi sổ không được phép cuộn ngược điều đó. Đơn rơi qua khe ấy tìm lại
         * được ở dải cảnh báo màn Hoàn tiền cọc (RefundRequestModel::demSot).
         *
         * Đọc LẠI bản ghi: vừa có thể đánh dấu đã thu tiền ở nhánh COD trên, và
         * công thức đọc `payment_status` cùng `mai_bat_dau_luc`.
         * ─────────────────────────────────────────────────────────────────────
         */
        if ($vuaHuy) {
            $don = self::find($id);

            if ($don !== null) {
                RefundRequestModel::taoChoDonHuy($don);
            }
        }

        /*
         * ─────────────────────────────────────────────────────────────────────
         * THƯ BÁO MỐC ĐƠN HÀNG — FR-EM-01
         *
         * NGOÀI transaction, cùng chỗ với sổ hoàn tiền và vì cùng lý do
         * (FR-EM-06): việc đổi trạng thái đã xong và người bấm đã thấy nó xong;
         * một trục trặc ở bước soạn thư không được phép cuộn ngược điều đó.
         * xepHang() tự bọc try/catch nên không đường nào ném ra tới đây.
         *
         * CHỈ KHI TRẠNG THÁI THẬT SỰ ĐỔI. Nhân viên bấm Lưu lại đúng trạng thái
         * cũ là chuyện xảy ra suốt, và khách không nên nhận một lá thư cho một
         * việc không xảy ra. Khoá chống trùng ở EmailEvents là lưới thứ hai.
         *
         * ĐỌC LẠI bản ghi: nhánh COD ở trên vừa có thể đánh dấu đã thu tiền, và
         * thư "hoàn tất" in tổng tiền.
         * ─────────────────────────────────────────────────────────────────────
         */
        if ($daDoi) {
            $moc = match ($status) {
                'confirmed' => 'xac_nhan',
                'shipping'  => 'giao',
                'completed' => 'hoan_tat',
                'cancelled' => 'huy',
                default     => null,
            };

            if ($moc !== null) {
                $don ??= self::find($id);

                if ($don !== null) {
                    EmailEvents::donHang($don, $moc, $lyDo);
                }
            }
        }
    }

    // ========================================================================
    // MỐC "BẮT ĐẦU MÀI" — TRỤC THỨ BA
    //
    // Đơn hàng có ba trục độc lập, không phải hai:
    //
    //   status          đơn ĐANG ở đâu trong vòng giao vận
    //   payment_status  tiền đã về tới đâu
    //   mai_bat_dau_luc tròng đã bắt đầu cắt CHƯA — và một khi đã, thì mãi mãi
    //
    // Trục thứ ba là trục quyết định TIỀN HOÀN (Q52.1, Q56.2, FR-25). Nó không
    // gộp được vào `status` vì trạng thái đi tiếp: đơn mài xong sang "Đang
    // giao" thì `status` không còn nói gì về việc đã mài, trong khi tròng đã
    // cắt và vật tư đã mất. Chi tiết trong khối chú thích ở schema.sql.
    //
    // BAO NHIÊU tiền được giữ lại khi huỷ sau mốc này thì X10 CHƯA CHỐT. Ở đây
    // chỉ trả lời câu đã chốt: còn đủ điều kiện hoàn 100% hay không.
    // ========================================================================

    /**
     * Cửa sổ RÚT LẠI cho chính người vừa bấm — Q2.2, Q3.2.
     *
     * SRS nói "cửa sổ ngắn" mà không cho con số. 5 phút là lựa chọn của nhóm
     * phát triển, ghi lại để BA xác nhận hoặc đổi: đủ dài cho người nhận ra
     * mình bấm nhầm dòng ngay tại quầy, đủ ngắn để không ai coi nó là đường
     * sửa đơn thông thường. Quá hạn thì còn đúng một lối — Quản lý cơ sở đảo
     * ngược kèm lý do.
     */
    public const RUT_LAI_GIAY = 300;

    /** Lý do bắt buộc dài tối thiểu bao nhiêu ký tự. Cùng mức với hồ sơ khúc xạ. */
    public const LY_DO_TOI_THIEU = 10;

    /**
     * Đơn này CÓ dịch vụ mài lắp tròng theo độ không.
     *
     * `lens_id` khác NULL nghĩa là dòng hàng ấy có gói tròng đi kèm. Đơn chỉ
     * mua gọng thì không bao giờ đi qua mốc mài, nên nút "Bắt đầu mài" không
     * được hiện ra ở đó — một nút vô nghĩa vẫn là một nút người ta sẽ bấm.
     */
    public static function coTrong(string $orderId): bool
    {
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM order_items
              WHERE order_id = :id AND lens_id IS NOT NULL AND lens_id <> ""',
            ['id' => $orderId]
        ) > 0;
    }

    /** Đã bấm "Bắt đầu mài" chưa. Nhận sẵn bản ghi đơn để khỏi đọc lại. */
    public static function daBatDauMai(array $order): bool
    {
        return trim((string) ($order['mai_bat_dau_luc'] ?? '')) !== '';
    }

    /**
     * Còn đủ điều kiện HOÀN 100% nếu huỷ bây giờ — Q52.1.
     *
     * Chỉ trả lời câu đã chốt. Huỷ sau mốc thì giữ lại BAO NHIÊU là X10, chưa
     * gỡ được, nên hàm này cố tình không trả về số tiền: một con số bịa ở đây
     * sẽ đi thẳng vào màn hình nhân viên và thành cam kết với khách.
     */
    public static function hoanCoc100(array $order): bool
    {
        return !self::daBatDauMai($order);
    }

    /**
     * Bấm "Bắt đầu mài" — mọi Nhân viên (SRS v2.1.0). Quyền kiểm ở controller.
     *
     * ĐẶT MỐC, KHÔNG ĐỔI TRẠNG THÁI. Trạng thái giao vận vẫn do ô chọn trên
     * bảng điều khiển; gộp hai việc vào một nút thì người bấm không còn phân
     * biệt được mình đang khai báo "đã cắt tròng" hay "đã chuẩn bị xong hàng",
     * mà hai điều đó có hệ quả tiền khác hẳn nhau.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function batDauMai(string $id, string $actorId): array
    {
        if (!self::coMocMai()) {
            return ['ok' => false, 'error' =>
                'Chưa nâng cấp cơ sở dữ liệu. Chạy '
                . 'database/migrations/2026-09-07-moc-mai-trong-va-ly-do.sql rồi thử lại.'];
        }

        $don = self::find($id);

        if ($don === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy đơn hàng.'];
        }

        if (self::daBatDauMai($don)) {
            return ['ok' => false, 'error' => 'Đơn này đã bắt đầu mài rồi.'];
        }

        if (!self::coTrong($id)) {
            return ['ok' => false, 'error' =>
                'Đơn này không có dịch vụ mài lắp tròng nên không có mốc bắt đầu mài.'];
        }

        /* KHÔNG BẮT ĐẦU MÀI MỘT ĐƠN ĐÃ HUỶ. Không phải để giữ luật cho đẹp:
           đơn huỷ đã trả hàng về kho, mà bấm nút này là cam kết đã cắt vật tư
           thật — hai điều đó cùng đúng thì sổ kho sai và không ai biết. */
        if ($don['status'] === 'cancelled') {
            return ['ok' => false, 'error' =>
                'Đơn đang ở trạng thái Đã huỷ. Mở lại đơn trước khi bắt đầu mài.'];
        }

        Database::transaction(static function () use ($id, $actorId, $don): void {
            self::update($id, [
                'mai_bat_dau_luc' => date('Y-m-d H:i:s'),
                'mai_bat_dau_boi' => $actorId,
            ]);

            self::ghiVetTien($id, 'order.lens_start',
                'Bắt đầu mài tròng — từ đây huỷ đơn không còn hoàn 100% cọc', $don);
        });

        return ['ok' => true];
    }

    /**
     * Đảo ngược mốc mài.
     *
     * Hai đường tới đây, và chúng khác nhau ở chỗ AI được đi:
     *
     *   trong cửa sổ RUT_LAI_GIAY   chính người vừa bấm, không cần lý do
     *   quá cửa sổ                  CHỈ Quản trị viên, BẮT BUỘC ghi lý do
     *
     * Cả hai phép kiểm ấy nằm ở controller vì chúng cần biết ai đang đăng nhập.
     * Hàm này chỉ giữ một luật: quá cửa sổ mà không có lý do thì từ chối — để
     * một đường gọi mới trong tương lai không lặng lẽ bỏ qua Q2.2.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function daoMai(string $id, string $actorId, ?string $lyDo = null): array
    {
        if (!self::coMocMai()) {
            return ['ok' => false, 'error' => 'Chưa nâng cấp cơ sở dữ liệu.'];
        }

        $don = self::find($id);

        if ($don === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy đơn hàng.'];
        }

        if (!self::daBatDauMai($don)) {
            return ['ok' => false, 'error' => 'Đơn này chưa bắt đầu mài.'];
        }

        $lyDo    = trim((string) $lyDo);
        $trongCs = self::trongCuaSoRutLai($don, $actorId);

        if (!$trongCs && utf8Length($lyDo) < self::LY_DO_TOI_THIEU) {
            return ['ok' => false, 'error' =>
                'Đã quá cửa sổ rút lại nên phải ghi lý do, tối thiểu '
                . self::LY_DO_TOI_THIEU . ' ký tự.'];
        }

        Database::transaction(static function () use ($id, $don, $lyDo, $trongCs): void {
            self::update($id, ['mai_bat_dau_luc' => null, 'mai_bat_dau_boi' => null]);

            self::ghiVetTien($id, 'order.lens_undo',
                ($trongCs ? 'Rút lại mốc bắt đầu mài (trong cửa sổ)' : 'Đảo ngược mốc bắt đầu mài')
                . ($lyDo !== '' ? ' — lý do: ' . utf8Substr($lyDo, 0, 180) : ''),
                $don);
        });

        return ['ok' => true];
    }

    /**
     * Còn trong cửa sổ rút lại VÀ đúng người đã bấm — Q2.2.
     *
     * Hai điều kiện, không phải một. Chỉ kiểm thời gian thì đồng nghiệp ngồi
     * cạnh gỡ được mốc của người khác mà không phải ghi lý do gì, tức là cái
     * cửa sổ ngắn trở thành một lỗ hổng ngắn.
     */
    public static function trongCuaSoRutLai(array $order, string $actorId): bool
    {
        $luc = trim((string) ($order['mai_bat_dau_luc'] ?? ''));

        if ($luc === '' || (string) ($order['mai_bat_dau_boi'] ?? '') !== $actorId) {
            return false;
        }

        $moc = strtotime($luc);

        return $moc !== false && (time() - $moc) <= self::RUT_LAI_GIAY;
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
            /* TỪNG GỌI grantFullPaymentReward($id) Ở ĐÂY — bỏ 2026-09-06.
               Mã quà tặng tự động cho khách chuyển đủ 100% không có trong SRS:
               mục 3.2.9.5 chỉ cho phép người tạo/sửa/phát mã, không có phần
               thưởng tự gắn vào luồng thanh toán. Xem migration
               2026-09-06-dot-8-go-ma-qua-tang.sql. */
            self::ghiVetTien($id, 'payment.paid', 'Đánh dấu đã thanh toán đủ');

            /* THƯ BÁO ĐÃ NHẬN ĐỦ TIỀN — FR-EM-02.

               Trong nhánh $doi nên webhook SePay gửi lại bảy lần cũng chỉ một
               thư; khoá chống trùng ở EmailEvents là lưới thứ hai. */
            $don = self::find($id);

            if ($don !== null) {
                EmailEvents::tien($don, 'du', (int) ($don['total'] ?? 0));
            }
        }

        return $doi;
    }

    /**
     * Ghi vết một thao tác tiền lên đơn — SNFR-11.
     *
     * ĐẶT TRONG MODEL, KHÔNG Ở CONTROLLER: có ba đường đưa một đơn sang
     * 'paid' (webhook SePay, nhân viên bấm ở /quan-tri/don-hang, đơn COD đánh
     * dấu đã giao).
     * Rải lời gọi ra ba chỗ thì đường thứ tư thêm sau này sẽ thiếu vết, và
     * không có lỗi nào nổ ra để ai biết.
     *
     * Chỉ chạy khi trạng thái THẬT SỰ đổi (nơi gọi đã lọc), nên webhook gửi
     * lại bảy lần cũng chỉ một dòng vết.
     *
     * actor_id do AuditLogModel::write() tự lấy từ phiên quản trị: nhân viên
     * bấm thì có tên, webhook SePay chạy thì NULL — và NULL ở đây đọc đúng
     * nghĩa "hệ thống tự làm", không phải thiếu dữ liệu.
     *
     * NUỐT LỖI: write() đã tự nuốt và error_log. Không để một bảng vết thiếu
     * làm hỏng việc ghi nhận tiền — tiền mới là việc chính.
     */
    private static function ghiVetTien(
        string $id,
        string $action,
        string $moTa,
        ?array $order = null,
        bool $heThong = false
    ): void {
        // Nhận sẵn bản ghi đơn khi nơi gọi đã đọc rồi. changeStatus() vừa
        // find() ở ngay trên, và các thao tác hàng loạt chạy hàm này tối đa
        // BULK_MAX lần — mỗi lần một SELECT * thừa là thấy được trên đồng hồ.
        $order ??= self::find($id);

        AuditLogModel::write(
            $order['user_id'] ?? null,
            $action,
            sprintf('%s — đơn %s', $moTa, (string) ($order['code'] ?? $id)),
            $heThong
        );
    }

    /*
     * ĐÃ BỎ: grantFullPaymentReward() — 2026-09-06, đợt 8.
     *
     * Hàm này tự phát một mã giảm giá cho khách chọn chuyển khoản đủ 100%,
     * gọi từ markPaid(). SRS KHÔNG có một chữ nào về việc ấy: tìm "tặng",
     * "thưởng", "quà" trong toàn bộ tài liệu ra 0 kết quả, và mục 3.2.9.5
     * (Quản lý khuyến mãi) chỉ cho phép tạo/sửa/bật-tắt mã và phát mã theo
     * quyết định của NGƯỜI, không phải phần thưởng tự gắn vào luồng tiền.
     *
     * Nó cũng là một khoản chi ngoài sổ: giảm doanh thu các đơn sau mà không
     * mục nào trong báo cáo giải thích được vì sao.
     *
     * Gỡ cùng lượt: VoucherModel::reward() · clearRewardFlag() · grantTo() ·
     * rewardHeldBy(), ô tick trong khu quản trị, hai màn hiển thị lời mời, và
     * cột `vouchers.is_reward`. Xem migration 2026-09-06-dot-8-go-ma-qua-tang.sql.
     */

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
        $doi = Database::execute(
            "UPDATE orders
                SET payment_status = 'deposit_paid'
              WHERE id = :id AND payment_status = 'unpaid'",
            ['id' => $id]
        ) > 0;

        // SNFR-11 gọi đích danh "cập nhật trạng thái cọc" — xem ghiVetTien().
        if ($doi) {
            self::ghiVetTien($id, 'payment.deposit', 'Ghi nhận tiền cọc 30%');

            // Thư báo đã nhận cọc — FR-EM-02.
            $don = self::find($id);

            if ($don !== null) {
                EmailEvents::tien($don, 'coc', (int) ($don['deposit_amount'] ?? 0));
            }
        }

        return $doi;
    }

    /**
     * Gỡ đánh dấu đã thanh toán — dành cho lúc bấm nhầm.
     *
     * Xoá luôn `paid_at`: giữ lại một mốc "tiền về" trên đơn đang 'unpaid' thì
     * lần sau đọc sổ không biết tin cột nào.
     */
    public static function markUnpaid(string $id): bool
    {
        $doi = Database::execute(
            "UPDATE orders
                SET payment_status = 'unpaid', paid_at = NULL
              WHERE id = :id AND payment_status <> 'unpaid'",
            ['id' => $id]
        ) > 0;

        /* Gỡ đánh dấu thanh toán là thao tác CẦN VẾT NHẤT trong ba cái: nó
           lùi một khoản tiền đã ghi nhận về không. "Bấm nhầm" và "cố tình" ở
           đây trông giống hệt nhau trên bảng đơn, chỉ vết mới phân biệt được. */
        if ($doi) {
            self::ghiVetTien($id, 'payment.unpaid', 'Gỡ đánh dấu đã thanh toán');
        }

        return $doi;
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

        /* Không còn mệnh đề phạm vi cơ sở ở đây — SRS v2.1.0, K06. Mọi nhân
           viên thấy toàn bộ đơn hàng; xem khối chú thích ở core/AdminController. */

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
        /* Đếm trên TOÀN bảng — không còn phạm vi cơ sở (SRS v2.1.0, K06). */
        $where  = '';
        $params = [];

        $counts = ['' => 0];

        foreach (array_keys(self::STATUSES) as $key) {
            $counts[$key] = 0;
        }

        foreach (Database::fetchAll(
            'SELECT status, COUNT(*) AS n FROM orders' . $where . ' GROUP BY status',
            $params
        ) as $row) {
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
