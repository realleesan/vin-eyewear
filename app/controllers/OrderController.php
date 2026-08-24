<?php

/**
 * OrderController — thanh toán và tra cứu đơn (/thanh-toan).
 *
 * Port từ src/routes/thanh-toan.tsx và components/checkout-modal.tsx.
 *
 * Bản Lovable mở checkout trong hộp thoại. Ở đây là trang riêng có URL —
 * khách quay lại được bằng nút Back, và trang xác nhận có địa chỉ để lưu lại.
 */

class OrderController extends BaseController
{
    /**
     * Ba lựa chọn thanh toán mà "Vin Eyewear Checkout.dc.html" vẽ.
     *
     * 'card' KHÔNG có trong OrderModel::PAYMENT_METHODS — cố ý. Dự án chưa nối
     * cổng thanh toán thẻ nào, nên nó hiện ra đúng hình dạng bản thiết kế nhưng
     * bị khoá, và place() từ chối nếu có ai gửi thẳng giá trị đó lên. Một đơn
     * "đã đặt" mà khách không trả tiền được là tệ hơn hẳn việc thiếu một lựa chọn.
     *
     * PHASE 1 CHỐT HAI CÁCH: COD và chuyển khoản QR. Thẻ ATM/Visa/Mastercard để
     * lại giai đoạn sau, khi làm phần phục vụ khách nước ngoài.
     *
     * Chuyển khoản KHÔNG phải "cổng thanh toán" theo nghĩa giữ tiền: khách
     * chuyển thẳng vào tài khoản cửa hàng, SePay chỉ đọc biến động số dư rồi
     * báo về để đơn tự đổi trạng thái — xem config/sepay.php.
     *
     * Bật thẻ lên sau: bỏ 'soon' => true ở đây và thêm 'card' vào
     * OrderModel::PAYMENT_METHODS, rồi nối cổng ở place().
     */
    private const PAYMENTS = [
        'cod' => [
            'name' => 'Thanh toán khi nhận hàng (COD)',
            'note' => 'Trả tiền mặt cho shipper hoặc tại quầy',
        ],
        'bank_transfer' => [
            'name' => 'Chuyển khoản ngân hàng (QR)',
            // Câu cũ ("thông tin gửi sau khi xác nhận đơn") có từ hồi chưa có
            // màn QR. Nay bấm đặt xong là sang thẳng mã QR, nên nói đúng bước
            // tiếp theo — khách chọn phương thức dựa vào việc họ sắp phải làm gì.
            'note' => 'Quét mã QR ngay ở bước sau, hoặc chuyển tay theo số tài khoản',
        ],
        'card' => [
            'name' => 'Thẻ ATM / Visa / Mastercard',
            'note' => 'Thanh toán online qua cổng bảo mật',
            'soon' => true,
        ],
    ];

    /**
     * Bắt buộc đăng nhập trước khi thanh toán.
     *
     * Đây là luật của cả luồng đặt hàng, không riêng một trang: đơn KHÔNG có
     * chủ thì khách không xem lại được trong "Đơn hàng của tôi", không theo dõi
     * được trạng thái, và trang xác nhận cũng không có chỗ nào để đẩy họ tới.
     *
     * Nói rõ lý do bằng flash chứ không lặng lẽ đá sang /auth: khách vừa bấm
     * "Thanh toán" ở giỏ hàng mà tự nhiên thấy màn hình đăng nhập thì tưởng
     * mình bị đăng xuất hoặc trang lỗi.
     */
    private static function requireCustomer(): string
    {
        /* Tài khoản nội bộ không đặt hàng bằng chính nó — xem khối "HAI KHU
           VỰC" ở đầu AuthMiddleware. Tách nhánh này ra trước customerId() vì
           customerId() trả null cho họ, mà câu "Vui lòng đăng nhập" thì sai
           hẳn với người ĐANG đăng nhập, và /auth cũng không nhận họ. */
        if (AuthMiddleware::isStaffSession()) {
            flash('admin_error',
                  'Tài khoản nội bộ không đặt hàng được. Đăng xuất rồi đăng nhập '
                  . 'bằng tài khoản khách nếu bạn cần mua hàng.');
            redirect('/quan-tri');
        }

        $userId = AuthMiddleware::customerId();

        if ($userId === null) {
            flash('auth_error', 'Vui lòng đăng nhập để tiếp tục thanh toán.');
            redirect('/auth?redirect=' . rawurlencode('/thanh-toan'));
        }

        return $userId;
    }

    public function checkout(): void
    {
        $userId = self::requireCustomer();

        // CHỌN chứ không phải cả giỏ: bản thiết kế giỏ hàng cho khách tick
        // từng dòng, và khối tóm tắt chỉ cộng những dòng đang tick. Lấy cả giỏ
        // ở đây thì con số ở hai trang lệch nhau và khách bị tính tiền cho món
        // họ đã bỏ tick.
        $cart = CartController::selectedItems();

        // Không có dòng nào được tick thì không có gì để thanh toán. Đẩy về
        // giỏ hàng thay vì hiện form trống — form trống dễ khiến khách tưởng
        // hệ thống lỗi.
        if ($cart === []) {
            /*
             * ─────────────────────────────────────────────────────────────
             * BẤM LÙI TỪ MÀN QR KHÔNG ĐƯỢC RƠI VÀO GIỎ HÀNG TRỐNG.
             *
             * Đặt đơn xong là giỏ được dọn sạch, nên bấm Lùi từ màn quét mã
             * sẽ về /thanh-toan với giỏ rỗng, và khối dưới đá tiếp về
             * /gio-hang kèm câu "Giỏ hàng đang trống". Khách vừa đặt xong một
             * đơn vài triệu đọc được đúng câu đó thì hiểu là đơn đã bay mất —
             * trong khi đơn nằm nguyên trong CSDL, chỉ chưa trả tiền.
             *
             * Còn mã đơn treo trong phiên nghĩa là có một đơn đang chờ chuyển
             * khoản. Đưa họ về đúng màn QR của nó. flash() ĐỌC LÀ TIÊU, nên
             * đặt lại ngay — transfer() còn cần nó, và khách bấm F5 ở đó cũng
             * phải trụ được.
             * ─────────────────────────────────────────────────────────────
             */
            $cho = flash('order_code');

            if ($cho !== null && $cho !== '') {
                flash('order_code', $cho);
                redirect('/thanh-toan/chuyen-khoan?ma=' . rawurlencode($cho));
            }

            flash('cart_error', CartController::items() === []
                ? 'Giỏ hàng đang trống, hãy chọn sản phẩm trước.'
                : 'Hãy tick chọn ít nhất một sản phẩm để thanh toán.');
            redirect('/gio-hang');
        }

        $products = ProductModel::findManyById(array_column($cart, 'product_id'));
        $variants = VariantModel::forProducts(array_column($cart, 'product_id'));
        $lines    = [];
        $subtotal = 0;

        foreach ($cart as $row) {
            $pid = $row['product_id'];

            if (!isset($products[$pid])) {
                continue;
            }

            $variant = null;

            foreach ($variants[$pid] ?? [] as $v) {
                if ($v['id'] === $row['variant_id']) {
                    $variant = $v;
                    break;
                }
            }

            /* Gói tròng cắt kèm — cùng cách tính với giỏ hàng
               (CartController::lines) và với lúc ghi đơn (OrderModel::place).
               Bỏ sót ở đây thì tạm tính trên trang thanh toán thấp hơn trên
               trang giỏ hàng, và khách phát hiện ra ở đúng bước cuối. */
            $lens = LensModel::combo($row['lens_id'] ?? null, $row['lens_type'] ?? null);
            $unit = VariantModel::priceOf($products[$pid], $variant) + (int) ($lens['price'] ?? 0);

            $lines[] = [
                'product'   => $products[$pid],
                'variant'   => $variant,
                'lens'      => $lens,
                'rx'        => $row['rx'] ?? null,
                'quantity'  => $row['quantity'],
                'lineTotal' => $unit * $row['quantity'],
            ];

            $subtotal += $unit * $row['quantity'];
        }

        $old = $_SESSION['_old_order'] ?? [];
        unset($_SESSION['_old_order']);

        // Phí giao hàng phụ thuộc hình thức nhận hàng khách chọn — hiện theo
        // lựa chọn đang có để con số trên màn hình khớp với lúc bấm đặt.
        $delivery = $old['deliveryMethod'] ?? 'pickup';

        // Mã giảm giá theo khách từ giỏ sang. Tính lại từ bảng `vouchers`, không
        // lấy con số nào từ session — xem ghi chú đầu CartController.
        $baseShipping = OrderModel::shippingFee($delivery, $subtotal);
        $summary      = CartController::applyVoucher($subtotal, $baseShipping);

        $this->renderView('order/checkout', [
            // Khung rút gọn: khách đang ở bước cuối, mọi liên kết điều hướng
            // đều là một lối để họ rời khỏi giỏ hàng đã điền dở.
            'bareLayout'  => true,
            'bareHeader'  => '_layout/checkout-header',
            'pageTitle'   => 'Thanh toán — Vin Eyewear',
            'metaDesc'    => 'Hoàn tất đơn hàng tại Vin Eyewear.',
            'payments'    => self::PAYMENTS,

            /*
             * Đường lùi của nút "‹" cạnh tiêu đề.
             *
             * Vào đây từ giỏ hàng thì lùi về giỏ hàng; vào bằng "Mua ngay" thì
             * lùi về đúng trang khách vừa bấm — CartController::add() đính chỗ
             * đó vào ?back=. safeRedirectPath chặn đường dẫn ra ngoài site: đây
             * là tham số trên URL, ai cũng sửa được.
             */
            'backUrl'     => safeRedirectPath($_GET['back'] ?? null, '/gio-hang'),
            'lines'       => $lines,
            'subtotal'    => $subtotal,
            'discount'    => $summary['discount'],
            'voucher'     => $summary['voucher'],
            'shippingFee' => $summary['shippingFee'],
            'threshold'   => (int) config('app.free_shipping_threshold'),

            /*
             * ĐẶT CỌC — chỉ đơn có cắt tròng theo độ mới phải cọc.
             *
             * Truyền cả TỶ LỆ lẫn CỜ có/không, để view khỏi phải tự suy luật
             * từ các dòng hàng. Số tiền thì view tự tính từ $total của chính
             * nó: tổng trên màn hình đổi theo hình thức nhận hàng khách đang
             * chọn (phí ship), nên tính sẵn ở đây sẽ lệch với con số ngay bên
             * trên nó.
             */
            'needsDeposit' => OrderModel::needsDeposit($cart),
            'depositRate'  => OrderModel::depositRate(),
            'stores'      => StoreModel::active(),
            'old'         => $old,
            'error'       => flash('order_error'),

            /*
             * Danh sách mã cho ô "Mã giảm giá" trong khối tóm tắt.
             *
             * Bản thiết kế vẽ một danh sách THẢ XUỐNG chứ không phải ô gõ mã
             * như giỏ hàng: khách bấm chọn một mã có sẵn. Nên trang này cần cả
             * danh sách, không chỉ mã đang áp.
             *
             * Số tiền giảm của TỪNG mã tính ở view, vì nó phụ thuộc tạm tính
             * và phí ship của chính đơn này — cùng một mã cho hai con số khác
             * nhau ở hai đơn khác nhau.
             */
            'vouchers'    => VoucherModel::selectable($userId),
            'voucherMsg'  => flash('cart_voucher_msg'),
            'voucherOk'   => flash('cart_voucher_ok') !== null,
            'profile'     => UserModel::profile($userId),

            /*
             * Địa chỉ mặc định trong sổ địa chỉ, để điền sẵn khối "Hình thức
             * nhận hàng". Sổ còn trống thì null và form về đúng trạng thái cũ
             * — trống.
             *
             * Ưu tiên hơn hồ sơ ở hai ô tên và điện thoại: sổ địa chỉ ghi NGƯỜI
             * NHẬN, còn hồ sơ ghi CHỦ TÀI KHOẢN. Hai thứ đó khác nhau mỗi khi
             * khách đặt hàng gửi cho người khác, và cột recipient_name tồn tại
             * chính vì thế.
             */
            'address'     => AddressModel::defaultFor($userId),
        ]);
    }

    /**
     * Nhận form đặt hàng (POST /thanh-toan/dat).
     */
    public function place(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('/thanh-toan');
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('order_error', 'Phiên làm việc đã hết hạn, vui lòng đặt lại.');
            redirect('/thanh-toan');
        }

        // Kiểm lại ở ĐÂY nữa, không chỉ ở checkout(): phiên có thể hết hạn
        // trong lúc khách điền form, và đây là chỗ duy nhất thật sự ghi đơn.
        // Thiếu dòng này thì luật "phải đăng nhập mới mua được" chỉ là một
        // gợi ý ở trang trước đó.
        $userId = self::requireCustomer();

        // Chủ đơn lấy từ PHIÊN, không bao giờ từ form: một ô hidden user_id
        // sẽ cho phép gán đơn cho tài khoản người khác.
        $data = self::formData() + ['userId' => $userId];

        if (utf8Length($data['customerName']) < 2) {
            $this->fail('Vui lòng nhập họ tên người nhận.', $data);
        }

        if (strlen(preg_replace('/\D/', '', $data['customerPhone'])) < 8) {
            $this->fail('Số điện thoại không hợp lệ.', $data);
        }

        if ($data['customerEmail'] !== '' && !filter_var($data['customerEmail'], FILTER_VALIDATE_EMAIL)) {
            $this->fail('Email không hợp lệ.', $data);
        }

        // Danh sách cho phép, không tin thẳng giá trị gửi lên: hai trường này
        // đi vào cột có ý nghĩa nghiệp vụ (tính phí ship, quy trình xử lý đơn).
        if (!in_array($data['deliveryMethod'], OrderModel::DELIVERY_METHODS, true)) {
            $this->fail('Hình thức nhận hàng không hợp lệ.', $data);
        }

        // 'card' nằm trong self::PAYMENTS (để vẽ ra) nhưng KHÔNG nằm trong
        // OrderModel::PAYMENT_METHODS, nên phép kiểm này chặn nó. Ô radio đã
        // disabled ở giao diện, nhưng disabled chỉ là thuộc tính HTML — một
        // request gửi tay không đi qua nó.
        if (!in_array($data['paymentMethod'], OrderModel::PAYMENT_METHODS, true)) {
            $this->fail('Hình thức thanh toán không hợp lệ.', $data);
        }

        if ($data['deliveryMethod'] === 'shipping') {
            // Kiểm TỪNG Ô, không kiểm chuỗi đã ghép. Bản thiết kế đánh dấu cả
            // ba ô là bắt buộc, mà đo độ dài chuỗi ghép thì "Phường Tây Hồ,
            // Hà Nội" đã đủ dài — đơn lọt qua với địa chỉ không có số nhà, và
            // shipper không giao được.
            if ($data['addressCity'] === '' || $data['addressWard'] === '') {
                $this->fail('Vui lòng chọn tỉnh/thành phố và phường/xã.', $data);
            }

            if (utf8Length($data['addressLine']) < 5) {
                $this->fail('Vui lòng nhập địa chỉ cụ thể (số nhà, tên đường).', $data);
            }

            // Giao tận nơi thì không gắn cơ sở nào — để lại sẽ khiến nhân viên
            // cơ sở đó tưởng có đơn chờ khách tới lấy.
            $data['storeId'] = null;
        } else {
            // Nhận tại cửa hàng thì BẮT BUỘC biết nhận ở đâu. Đối chiếu lại với
            // DB: giá trị đến từ form nên sửa được, và một cơ sở đã đóng thì
            // không nhận khách.
            if ($data['storeId'] === null || !StoreModel::isBookable($data['storeId'])) {
                $this->fail('Vui lòng chọn cơ sở để nhận hàng.', $data);
            }
        }

        // Mã giảm giá đi kèm dạng CHUỖI. OrderModel::place tự tra lại bảng
        // `vouchers` và tự tính số tiền — không nơi nào ngoài nó được quyết
        // định "giảm bao nhiêu", kể cả trang này.
        $data['voucherCode'] = $_SESSION['cart_voucher'] ?? '';

        $result = OrderModel::place($data, CartController::selectedItems());

        if (!$result['ok']) {
            $this->fail($result['error'], $data);
        }

        // Đặt xong thì dọn các dòng ĐÃ ĐẶT khỏi giỏ. Dòng chưa tick vẫn ở lại
        // — khách cố tình để chúng cho lần sau. Xoá sạch giỏ như trước đây sẽ
        // vứt luôn phần họ đang giữ.
        foreach (array_keys($result['items'] ?? []) as $id) {
            unset($_SESSION['cart'][$id]);
        }

        unset($_SESSION['cart_voucher']);

        /* Lượt mua kết thúc ở đây. Luồng "Mua ngay" cố ý giữ ý định sống qua
           trang thanh toán để nút Lùi mở lại bước xác nhận (xem
           CartController::add) — nhưng đơn đã đặt rồi thì không còn bước nào
           để lùi về, và dòng hàng cũng vừa bị dọn khỏi giỏ ngay trên. */
        unset($_SESSION['_buy_intent']);

        /*
         * ĐẨY ĐƠN SANG ZALO CỦA CỬA HÀNG — ngay khi đơn đã nằm trong CSDL.
         *
         * Đây là MỘT NỬA của luồng huỷ đơn, không phải một tiện ích báo tin.
         * Website cố ý không có nút "huỷ đơn": cửa hàng tự đi giao và không
         * đồng bộ trạng thái vận chuyển thời gian thực với đơn vị vận chuyển
         * nào, nên một nút huỷ trên web sẽ đổi trạng thái trong CSDL trong khi
         * hàng có thể đã nằm trên xe. Thay vào đó nhân viên gọi khách xác nhận
         * từng đơn, và khách muốn huỷ thì nhắn lại chính cuộc trò chuyện đó.
         *
         * Bỏ bước này thì việc không có nút huỷ trở thành một lỗ hổng thật:
         * đơn nằm im trong /quan-tri/don-hang chờ ai đó nhớ mở ra, còn khách
         * nhắn Zalo huỷ một đơn mà bên kia còn chưa biết là có.
         *
         * Đọc lại hàng vừa ghi thay vì gửi $data: tin báo cần TÊN cơ sở nhận
         * hàng, mà $data chỉ có id. Zalo::order() tự nuốt mọi lỗi và có hạn giờ
         * ngắn — Zalo sập cũng không được biến thành trang lỗi cho người vừa
         * đặt hàng xong. Xem khối chú thích đầu core/Zalo.php.
         */
        $saved = OrderModel::findByCode($result['code']);

        if ($saved !== null) {
            Zalo::order($saved, OrderModel::items($saved['id']));
        }

        flash('order_code', $result['code']);

        /*
         * ĐƠN CHUYỂN KHOẢN ĐI QUA MÀN HÌNH QR TRƯỚC.
         *
         * "Vin Eyewear Checkout.dc.html" có hai màn: form thanh toán, rồi màn
         * "Thanh toán QR" hiện ngay sau khi đặt — mã QR, số tài khoản, số tiền
         * và nội dung chuyển khoản. Đơn COD không qua màn này vì không có gì
         * để chuyển.
         *
         * Không thay trang xác nhận: bấm "Tôi đã chuyển khoản" ở màn QR là
         * sang /thanh-toan/hoan-tat như mọi đơn khác — xem transfer().
         */
        /* ĐƠN PHẢI CỌC CŨNG ĐI QUA MÀN QR, KỂ CẢ KHI CHỌN COD.
           Tiền cọc không trả cho shipper được: cửa hàng cần nó TRƯỚC khi mài
           tròng, mà shipper thì chỉ xuất hiện lúc giao. Nên đơn cắt tròng
           chọn COD nghĩa là "phần còn lại trả khi nhận", không phải "không
           phải chuyển gì cả" — và màn QR ở bước sau thu đúng phần cọc, xem
           order/transfer.php. */
        $needsTransfer = $data['paymentMethod'] === 'bank_transfer'
                      || (int) ($saved['deposit_amount'] ?? 0) > 0;

        redirect($needsTransfer ? '/thanh-toan/chuyen-khoan' : '/thanh-toan/hoan-tat');
    }

    /**
     * Đọc form thanh toán thành mảng dữ liệu đơn.
     *
     * Tách khỏi place() vì áp/gỡ mã giảm giá (voucher()) cũng nhận CHÍNH form
     * này — nút chọn mã nằm trong khối tóm tắt, tức là bên trong <form> đặt
     * hàng, và nó gửi kèm mọi thứ khách đã gõ. Nhờ vậy áp mã xong form không
     * bị trắng.
     *
     * KHÔNG kiểm tra gì ở đây: đây chỉ là bước đọc. Mọi phép kiểm nằm trong
     * place(), vì đó mới là nơi thật sự ghi đơn.
     */
    private static function formData(): array
    {
        return [
            'customerName'    => trim((string) ($_POST['customer_name'] ?? '')),
            'customerPhone'   => trim((string) ($_POST['customer_phone'] ?? '')),
            'customerEmail'   => trim((string) ($_POST['customer_email'] ?? '')),
            'deliveryMethod'  => (string) ($_POST['delivery_method'] ?? 'pickup'),
            // Bản thiết kế tách địa chỉ làm ba ô (tỉnh · phường · số nhà). CSDL
            // giữ một cột TEXT, nên ghép lại ở đây theo thứ tự người Việt đọc
            // địa chỉ: cụ thể nhất trước, rộng nhất sau.
            'shippingAddress' => self::joinAddress(
                $_POST['address_line'] ?? '',
                $_POST['address_ward'] ?? '',
                $_POST['address_city'] ?? ''
            ),
            // Giữ RIÊNG ba mẩu địa chỉ, không chỉ chuỗi đã ghép: form báo lỗi
            // thì phải điền lại được đúng từng ô. Tách ngược chuỗi đã ghép ra
            // ba phần là đoán mò — dấu phẩy cũng nằm trong tên đường.
            'addressLine'     => trim((string) ($_POST['address_line'] ?? '')),
            'addressWard'     => trim((string) ($_POST['address_ward'] ?? '')),
            'addressCity'     => trim((string) ($_POST['address_city'] ?? '')),
            'storeId'         => trim((string) ($_POST['store_id'] ?? '')) ?: null,
            'paymentMethod'   => (string) ($_POST['payment_method'] ?? 'cod'),
            /* Chỉ có nghĩa khi paymentMethod = bank_transfer VÀ đơn có cắt
               tròng: 'deposit' = chuyển 30%, 'full' = chuyển đủ. Mọi giá trị
               khác được OrderModel::place() hiểu là 'full' — xem chú thích ở
               đó về việc vì sao 'full' mới là mặc định an toàn. */
            'bankAmount'      => (string) ($_POST['bank_amount'] ?? 'full'),
            'note'            => trim((string) ($_POST['note'] ?? '')),
        ];
    }

    /**
     * Chọn hoặc gỡ mã giảm giá NGAY TẠI TRANG THANH TOÁN (POST /thanh-toan/ma).
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO KHÔNG DÙNG LẠI /gio-hang/ma
     *
     * Đường đó luôn quay về giỏ hàng, và nó chỉ nhận mỗi ô `code`. Bấm chọn mã
     * ở trang thanh toán mà bị đá về giỏ hàng thì khách mất hết những gì vừa
     * gõ — họ đang ở giữa một biểu mẫu dài.
     *
     * Ở đây các nút chọn mã nằm TRONG <form> đặt hàng và dùng `formaction`,
     * nên request này mang theo toàn bộ form. Cất vào `_old_order` rồi quay
     * lại /thanh-toan là khách thấy đúng những gì mình đã điền, cộng thêm mã
     * vừa chọn. Cùng cơ chế mà fail() dùng khi form có lỗi.
     * ─────────────────────────────────────────────────────────────────────
     */
    public function voucher(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            redirect('/thanh-toan');
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            flash('order_error', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            redirect('/thanh-toan');
        }

        $userId = self::requireCustomer();

        // Giữ lại những gì khách đã gõ TRƯỚC KHI làm bất cứ việc gì khác —
        // mọi nhánh bên dưới đều kết thúc bằng redirect về /thanh-toan.
        $_SESSION['_old_order'] = self::formData() + ['userId' => $userId];

        if (($_POST['act'] ?? '') === 'go') {
            unset($_SESSION['cart_voucher']);
            flash('cart_voucher_msg', 'Đã gỡ mã giảm giá.');
            flash('cart_voucher_ok', '1');
            redirect('/thanh-toan');
        }

        $code   = (string) ($_POST['code'] ?? '');
        $result = VoucherModel::evaluate($code, CartController::selectedSubtotal(), $userId);

        if (!$result['ok']) {
            // KHÔNG cất mã hỏng vào session: lần vẽ trang sau sẽ thử lại nó rồi
            // báo lỗi lần nữa, trong khi khách chẳng làm gì thêm.
            unset($_SESSION['cart_voucher']);
            flash('cart_voucher_msg', $result['error']);
            redirect('/thanh-toan');
        }

        $_SESSION['cart_voucher'] = $result['voucher']['code'];

        flash('cart_voucher_msg', sprintf(
            'Đã áp dụng mã %s: %s',
            $result['voucher']['code'],
            $result['voucher']['condition_text'] ?: $result['voucher']['title']
        ));
        flash('cart_voucher_ok', '1');

        redirect('/thanh-toan');
    }

    /**
     * Màn hình QR chuyển khoản (/thanh-toan/chuyen-khoan).
     *
     * Màn thứ hai của "Vin Eyewear Checkout.dc.html".
     *
     * ─────────────────────────────────────────────────────────────────────
     * HAI ĐƯỜNG VÀO, VÀ ĐƯỜNG THỨ HAI MỚI LÀ ĐƯỜNG HAY DÙNG
     *
     *   1. VỪA ĐẶT XONG — mã đơn đọc từ flash, không nằm trên URL.
     *   2. QUAY LẠI SAU — ?ma=<mã đơn>, từ nút trong "Đơn hàng của tôi".
     *
     * Trước bản này chỉ có đường 1, và đó là một lỗ thật: khách đóng tab, hết
     * pin, hay đơn giản là để mai chuyển — thì không còn đường nào quay lại mã
     * QR nữa. Thẻ đơn trong trang tài khoản chỉ in số tài khoản dạng chữ, mà
     * gõ tay 13 chữ số vào app ngân hàng là đúng chỗ người ta gõ sai.
     *
     * MÃ ĐƠN TRÊN URL KHÔNG PHẢI LỖ HỔNG: findByCode() nhận $userId và trả null
     * nếu đơn thuộc về người khác, nên gõ mã của người lạ vào chỉ ra trang
     * "Đơn hàng của tôi" trống. Đó cũng chính là cơ chế mà ?don= của trang tài
     * khoản đang dùng.
     * ─────────────────────────────────────────────────────────────────────
     *
     * Đặt lại flash sau khi đọc: tải lại trang (khách quét QR bằng điện thoại
     * rồi bấm F5) không được rơi ra ngoài.
     */
    public function transfer(): void
    {
        $userId = self::requireCustomer();

        /*
         * ?ma= THẮNG FLASH, KHÔNG PHẢI NGƯỢC LẠI.
         *
         * Thoạt nhìn thì ưu tiên flash có vẻ đúng hơn ("vừa đặt xong thì hiện
         * đơn vừa đặt"). Nhưng flash ở đây KHÔNG TỰ HẾT: cuối hàm nó được đặt
         * lại để khách bấm F5 trên điện thoại không rơi ra ngoài. Nghĩa là sau
         * lần đặt hàng đầu tiên, mã đơn đó nằm trong phiên gần như vĩnh viễn.
         *
         * Ưu tiên flash thì hậu quả là: khách đặt đơn A, tuần sau vào trang tài
         * khoản bấm "Quét mã QR" trên đơn B — và nhận mã QR của đơn A, mang số
         * tiền của đơn A và nội dung chuyển khoản của đơn A. Tiền vào đúng tài
         * khoản nhưng khớp nhầm đơn.
         *
         * Đọc flash TRƯỚC (để tiêu nó đi) rồi mới xét ?ma=: bấm một địa chỉ có
         * mã đơn cụ thể là một yêu cầu rõ ràng, phải được tôn trọng.
         */
        $flashed = flash('order_code');
        $asked   = trim((string) ($_GET['ma'] ?? ''));

        $fresh = $asked === '' && $flashed !== null;
        $code  = $asked !== '' ? $asked : (string) ($flashed ?? '');

        if ($code === '') {
            redirect('/tai-khoan?muc=don-hang');
        }

        $order = OrderModel::findByCode($code, $userId);

        if ($order === null) {
            redirect('/tai-khoan?muc=don-hang');
        }

        /*
         * ĐƠN KHÔNG CÒN GÌ ĐỂ TRẢ THÌ KHÔNG MỞ MÀN NÀY.
         *
         * Đã trả đủ, hoặc đã huỷ. Hiện một mã QR cho đơn như thế là mời khách
         * chuyển tiền lần thứ hai — và tiền đã vào tài khoản rồi thì việc hoàn
         * lại tốn công hơn nhiều so với việc chặn ở đây.
         *
         * Đơn 'deposit_paid' cũng dừng: phần còn lại trả khi nhận hàng, không
         * chuyển khoản tiếp.
         */
        if (($order['payment_status'] ?? 'unpaid') !== 'unpaid'
            || ($order['status'] ?? '') === 'cancelled') {
            redirect('/tai-khoan?muc=don-hang&don=' . rawurlencode($code)
                     . '#' . rawurlencode($code));
        }

        flash('order_code', $code);

        $this->renderView('order/transfer', [
            // Vẫn khung rút gọn: khách chưa trả tiền xong, đây vẫn là bước
            // cuối của luồng thanh toán.
            'bareLayout' => true,
            'bareHeader' => '_layout/checkout-header',
            'pageTitle'  => 'Thanh toán chuyển khoản — Vin Eyewear',
            'metaDesc'   => 'Quét mã QR để hoàn tất thanh toán đơn hàng.',
            'noindex'    => true,
            'order'      => $order,
            'items'      => OrderModel::items($order['id']),
            'bank'       => config('company.bank', []),

            /*
             * Nút "Tôi đã chuyển khoản" đi đâu.
             *
             * Vừa đặt xong -> trang xác nhận đơn, đúng nhịp của luồng mua.
             * Quay lại sau -> về đúng thẻ đơn trong trang tài khoản. Đẩy họ
             * sang trang "Cảm ơn bạn, đơn hàng đã được ghi nhận" cho một đơn
             * đặt từ tuần trước là nói sai chuyện vừa xảy ra.
             */
            'doneHref'   => $fresh
                ? '/thanh-toan/hoan-tat'
                : '/tai-khoan?muc=don-hang&don=' . rawurlencode($code)
                  . '#' . rawurlencode($code),

            /* Lối ra của khối "chờ mãi chưa thấy" — LUÔN là thẻ đơn trong
               trang tài khoản, không đi theo $doneHref. Câu chữ ở đó mời khách
               "xem lại ở đơn hàng của tôi", mà với đơn vừa đặt thì $doneHref
               trỏ sang /thanh-toan/hoan-tat — trang nói "đã ghi nhận đơn", không
               phải danh sách đơn. Hứa một nơi rồi mở ra nơi khác. */
            'orderHref'  => '/tai-khoan?muc=don-hang&don=' . rawurlencode($code)
                            . '#' . rawurlencode($code),
        ]);
    }

    /**
     * Máy hỏi: đơn này đã nhận được tiền chưa? (/thanh-toan/trang-thai?ma=…)
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO CÓ ĐỊA CHỈ NÀY: ĐỂ BỎ ĐƯỢC NÚT "TÔI ĐÃ CHUYỂN KHOẢN"
     *
     * Nút đó chỉ là LỜI KHÁCH NÓI. Nó không chứng minh được đồng nào đã rời
     * khỏi tài khoản của họ, nên nó không thể dẫn sang trang "Thanh toán
     * thành công" — lý do đầy đủ nằm ở khối chú thích của paid().
     *
     * Nay màn QR tự hỏi máy chủ vài giây một lần, và CHÍNH MÁY CHỦ trả lời.
     * Câu trả lời "đã trả" chỉ đến từ orders.payment_status, tức từ một
     * trong hai nguồn có thật:
     *
     *   webhook SePay khớp được giao dịch  (SepayModel::handle)
     *   nhân viên đối chiếu sao kê rồi đánh dấu ở /quan-tri/don-hang
     *
     * Nguồn thứ hai đáng kể chứ không phải đường lui: hosting hiện tại chặn
     * webhook (xem config/sepay.php), nên tới lúc này nó là nguồn DUY NHẤT
     * đang chạy. Khách vẫn được lợi — không phải ngồi bấm F5 chờ.
     *
     * TRẢ JSON KỂ CẢ KHI HỎNG. Đây là địa chỉ cho máy gọi, không phải cho
     * người xem: chuyển hướng sang trang đăng nhập ở đây sẽ khiến bên kia
     * nhận về một trang HTML và ném lỗi phân tích cú pháp. Nói thẳng
     * "thôi đừng hỏi nữa" bằng cờ `stop` thì bên kia biết đường dừng.
     * ─────────────────────────────────────────────────────────────────────
     */
    public function payStatus(): void
    {
        // customerId(): đơn hàng luôn thuộc về một tài khoản KHÁCH, nên phiên
        // nội bộ ở đây cũng vô danh như khách vãng lai.
        $userId = AuthMiddleware::customerId();
        $code   = trim((string) ($_GET['ma'] ?? ''));

        if ($userId === null || $code === '') {
            self::payJson(['paid' => false, 'stop' => true]);
        }

        // findByCode kiểm chủ sở hữu — mã của người khác trả về null.
        $order = OrderModel::findByCode($code, $userId);

        if ($order === null) {
            self::payJson(['paid' => false, 'stop' => true]);
        }

        /* Đơn đã huỷ thì không còn gì để chờ. Bảo bên kia dừng và đưa khách
           về thẻ đơn — đứng mãi trước một mã QR của đơn đã huỷ là chờ một
           việc sẽ không bao giờ xảy ra. */
        if (($order['status'] ?? '') === 'cancelled') {
            self::payJson([
                'paid' => false,
                'stop' => true,
                'href' => '/tai-khoan?muc=don-hang&don=' . rawurlencode($code)
                          . '#' . rawurlencode($code),
            ]);
        }

        $paid = ($order['payment_status'] ?? 'unpaid') !== 'unpaid';

        self::payJson([
            'paid' => $paid,
            'stop' => $paid,
            /* Cùng địa chỉ mà nút cũ trỏ tới khi tiền đã về thật. Trang đó tự
               kiểm lại payment_status lần nữa, nên kể cả có ai gọi thẳng vào
               đây cũng không mở được biên nhận cho một đơn chưa trả. */
            'href' => $paid ? '/thanh-toan/thanh-cong?ma=' . rawurlencode($code) : null,
        ]);
    }

    /**
     * Trả lời JSON rồi dừng hẳn.
     *
     * no-store là bắt buộc: câu trả lời này đổi theo thời gian, mà một tầng
     * đệm nào đó giữ lại bản "chưa trả" thì màn QR sẽ chờ mãi dù tiền đã về.
     */
    private static function payJson(array $payload): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Ghép ba ô địa chỉ của bản thiết kế thành một dòng.
     *
     * Bỏ qua ô trống thay vì để lại dấu phẩy lửng: khách ở nơi không có cấp
     * phường vẫn phải đặt được hàng, và "12 Ngõ 5, , Hà Nội" trên nhãn giao
     * hàng trông như lỗi hệ thống.
     */
    private static function joinAddress(string $line, string $ward, string $city): string
    {
        $parts = array_filter(array_map('trim', [$line, $ward, $city]), static fn ($p) => $p !== '');

        return implode(', ', $parts);
    }

    /**
     * Biên nhận thanh toán (/thanh-toan/thanh-cong?ma=<mã đơn>).
     *
     * ─────────────────────────────────────────────────────────────────────
     * "THANH TOÁN THÀNH CÔNG" LÀ MỘT LỜI KHẲNG ĐỊNH VỀ TIỀN, KHÔNG PHẢI MỘT
     * BƯỚC TRONG LUỒNG BẤM NÚT
     *
     * Chỗ dễ làm sai nhất của màn này là nối nó vào nút "Tôi đã chuyển khoản"
     * ở màn QR. Nút đó chỉ là LỜI KHÁCH NÓI — chưa ai đối chiếu sao kê, tiền
     * có thể chưa rời khỏi tài khoản của họ. Hiện "Thanh toán thành công"
     * ngay lúc đó là website tự nói dối, và cái giá phải trả rơi vào đúng chỗ
     * tệ nhất: khách yên tâm chờ hàng, cửa hàng không thấy tiền, hai bên chỉ
     * phát hiện ra sau vài ngày.
     *
     * Nên trang này CHỈ mở khi orders.payment_status đã thật sự sang 'paid'
     * hoặc 'deposit_paid' — do nhân viên đánh dấu ở /quan-tri/don-hang, hoặc
     * do webhook SePay tự khớp giao dịch (xem SepayModel::handle).
     *
     * Đơn chưa trả tiền thì bị đẩy về màn QR chứ không phải trang lỗi: khách
     * bấm nhầm vào đây gần như luôn là người đang muốn trả tiền.
     * ─────────────────────────────────────────────────────────────────────
     */
    public function paid(): void
    {
        $userId = self::requireCustomer();
        $code   = trim((string) ($_GET['ma'] ?? ''));

        if ($code === '') {
            redirect('/tai-khoan?muc=don-hang');
        }

        // findByCode kiểm chủ sở hữu — mã của người khác trả về null.
        $order = OrderModel::findByCode($code, $userId);

        if ($order === null) {
            redirect('/tai-khoan?muc=don-hang');
        }

        $status = (string) ($order['payment_status'] ?? 'unpaid');

        if ($status === 'unpaid') {
            /* Chưa nhận được tiền. Đơn đã huỷ thì không mời trả nữa — về thẻ
               đơn; còn lại thì đưa thẳng tới màn QR, đó là thứ họ đang cần. */
            redirect(($order['status'] ?? '') === 'cancelled'
                ? '/tai-khoan?muc=don-hang&don=' . rawurlencode($code) . '#' . rawurlencode($code)
                : '/thanh-toan/chuyen-khoan?ma=' . rawurlencode($code));
        }

        $total   = (int) $order['total'];
        $deposit = (int) ($order['deposit_amount'] ?? 0);

        $this->renderView('order/paid', [
            // Khung rút gọn như màn QR: đây là điểm cuối của luồng trả tiền,
            // mọi liên kết điều hướng đều là một lối để khách đi lạc khỏi nó.
            'bareLayout' => true,
            'bareHeader' => '_layout/checkout-header',
            'pageTitle'  => 'Thanh toán thành công — Vin Eyewear',
            'metaDesc'   => 'Biên nhận thanh toán đơn hàng tại Vin Eyewear.',
            'noindex'    => true,
            'order'      => $order,
            'items'      => OrderModel::items($order['id']),

            /*
             * SỐ TIỀN ĐÃ NHẬN — không phải lúc nào cũng bằng tổng đơn.
             *
             * Đơn cắt tròng theo độ mới chỉ trả 30% tiền cọc; nói "đã thanh
             * toán 4.400.000₫" cho một đơn vừa nhận 1.320.000₫ là sai với cả
             * khách lẫn sổ sách. Xem OrderModel -> khối ĐẶT CỌC.
             */
            /* Mã quà tặng khách ĐANG GIỮ và chưa dùng. Hỏi lại CSDL chứ
               không tin vào kết quả của lần phát: trang này mở lại được nhiều
               lần, và lần thứ hai thì mã đã phát từ trước. Hỏi cả với đơn đặt
               cọc — khách có thể đã nhận quà từ một đơn khác, và giấu đi thì
               họ không bao giờ biết mình đang cầm cái gì. */
            'reward'     => VoucherModel::rewardHeldBy($userId),
            'isDeposit'  => $status === 'deposit_paid' && $deposit > 0,
            'paidAmount' => $status === 'deposit_paid' && $deposit > 0 ? $deposit : $total,
            'remaining'  => $status === 'deposit_paid' && $deposit > 0 ? $total - $deposit : 0,
        ]);
    }

    /**
     * Trang xác nhận sau khi đặt hàng thành công.
     */
    public function success(): void
    {
        $userId = self::requireCustomer();
        $code   = flash('order_code');

        // Vào thẳng địa chỉ này mà không vừa đặt hàng -> không có gì để khoe
        if ($code === null) {
            redirect('/san-pham');
        }

        // Truyền $userId: mã đơn đến từ flash của chính phiên này nên không thể
        // là đơn của người khác, nhưng findByCode đã có sẵn điều kiện chủ sở
        // hữu và bỏ qua nó thì không được lợi gì.
        $order = OrderModel::findByCode($code, $userId);

        $this->renderView('order/success', [
            'pageTitle' => 'Đặt hàng thành công — Vin Eyewear',
            'order'     => $order,
            'items'     => $order !== null ? OrderModel::items($order['id']) : [],
            // Tài khoản nhận chuyển khoản — in thẳng lên trang cho đơn chuyển
            // khoản, thay vì hứa "nhân viên sẽ gọi và đọc". Chưa cấu hình thì
            // view tự quay về câu hứa đó; xem config/company.php.
            'bank'      => config('company.bank', []),
        ]);
    }

    private function fail(string $message, array $data): never
    {
        $_SESSION['_old_order'] = $data;
        flash('order_error', $message);

        redirect('/thanh-toan');
    }
}
