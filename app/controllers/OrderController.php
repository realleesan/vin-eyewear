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

    public function checkout(): void
    {
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

            $unit = VariantModel::priceOf($products[$pid], $variant);

            $lines[] = [
                'product'   => $products[$pid],
                'variant'   => $variant,
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
            'lines'       => $lines,
            'subtotal'    => $subtotal,
            'discount'    => $summary['discount'],
            'voucher'     => $summary['voucher'],
            'shippingFee' => $summary['shippingFee'],
            'threshold'   => (int) config('app.free_shipping_threshold'),
            'stores'      => StoreModel::active(),
            'old'         => $old,
            'error'       => flash('order_error'),
            'profile'     => isset($_SESSION['user_id'])
                ? UserModel::profile($_SESSION['user_id']) : null,
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

        $data = [
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
            'userId'          => $_SESSION['user_id'] ?? null,
        ];

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

        flash('order_code', $result['code']);
        redirect('/thanh-toan/hoan-tat');
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
        $code = flash('order_code');

        // Vào thẳng địa chỉ này mà không vừa đặt hàng -> không có gì để khoe
        if ($code === null) {
            redirect('/san-pham');
        }

        $order = OrderModel::findByCode($code);

        $this->renderView('order/success', [
            'pageTitle' => 'Đặt hàng thành công — Vin Eyewear',
            'order'     => $order,
            'items'     => $order !== null ? OrderModel::items($order['id']) : [],
            'company'   => config('company'),
        ]);
    }

    private function fail(string $message, array $data): never
    {
        $_SESSION['_old_order'] = $data;
        flash('order_error', $message);

        redirect('/thanh-toan');
    }
}
