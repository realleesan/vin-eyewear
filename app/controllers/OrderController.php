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
     * cổng thanh toán nào, nên nó hiện ra đúng hình dạng bản thiết kế nhưng bị
     * khoá, và place() từ chối nếu có ai gửi thẳng giá trị đó lên. Một đơn
     * "đã đặt" mà khách không trả tiền được là tệ hơn hẳn việc thiếu một lựa chọn.
     *
     * Bật lên sau: bỏ 'soon' => true ở đây và thêm 'card' vào
     * OrderModel::PAYMENT_METHODS, rồi nối cổng ở place().
     */
    private const PAYMENTS = [
        'cod' => [
            'name' => 'Thanh toán khi nhận hàng (COD)',
            'note' => 'Trả tiền mặt cho shipper hoặc tại quầy',
        ],
        'bank_transfer' => [
            'name' => 'Chuyển khoản ngân hàng',
            'note' => 'Thông tin chuyển khoản gửi sau khi xác nhận đơn',
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
        $userId = AuthMiddleware::userId();

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
        redirect($data['paymentMethod'] === 'bank_transfer'
            ? '/thanh-toan/chuyen-khoan'
            : '/thanh-toan/hoan-tat');
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
     * Màn thứ hai của "Vin Eyewear Checkout.dc.html". Chỉ tới được ngay sau khi
     * vừa đặt một đơn CHUYỂN KHOẢN — mã đơn đọc từ flash, không nằm trên URL,
     * nên không ai dò được đơn của người khác.
     *
     * Đặt lại flash sau khi đọc, vì hai lý do: tải lại trang (khách quét QR
     * bằng điện thoại rồi bấm F5) không được rơi ra ngoài, và nút "Tôi đã
     * chuyển khoản" dẫn sang /thanh-toan/hoan-tat — trang đó đọc đúng flash này.
     */
    public function transfer(): void
    {
        $userId = self::requireCustomer();
        $code   = flash('order_code');

        if ($code === null) {
            redirect('/san-pham');
        }

        $order = OrderModel::findByCode($code, $userId);

        if ($order === null) {
            redirect('/san-pham');
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
        ]);
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
