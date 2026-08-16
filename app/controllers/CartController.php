<?php

/**
 * CartController — giỏ hàng (/gio-hang).
 *
 * Port từ src/lib/cart.tsx và src/routes/gio-hang.tsx.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * GIỎ HÀNG NẰM TRONG SESSION, KHÔNG PHẢI TRONG DATABASE
 *
 * Bản Lovable giữ giỏ trong localStorage của trình duyệt. Bản PHP giữ trong
 * session phía server. Cả hai đều KHÔNG lưu vào DB cho tới lúc đặt hàng —
 * giỏ hàng bỏ dở không phải dữ liệu nghiệp vụ, lưu vào bảng chỉ tổ rác.
 *
 * Session chỉ giữ ID, SỐ LƯỢNG và TRẠNG THÁI TICK. Giá và tên luôn đọc lại từ
 * DB ở mỗi lần hiển thị: nếu lưu cả giá vào session, khách để giỏ qua đêm rồi
 * thanh toán sẽ trả theo giá cũ, hoặc tệ hơn — kẻ tấn công sửa được giá trong
 * phiên của chính mình.
 *
 * Mã giảm giá cũng vậy: session chỉ nhớ CHUỖI mã khách đã gõ
 * ($_SESSION['cart_voucher']), không bao giờ nhớ số tiền đã giảm. Số tiền được
 * tính lại từ bảng `vouchers` ở mỗi lần hiện trang và một lần nữa lúc đặt hàng.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * KHOÁ CỦA MỘT DÒNG GIỎ HÀNG = SẢN PHẨM + BIẾN THỂ
 *
 * Từ khi có biến thể (chiết suất tròng, màu gọng…), một mặt hàng có thể nằm
 * trong giỏ nhiều lần với những phương án khác nhau. Nên khoá của $_SESSION
 * ['cart'] KHÔNG còn là product_id, mà là "product_id:variant_id" (hoặc chỉ
 * product_id khi mặt hàng không có biến thể).
 *
 * Mỗi dòng vẫn lưu product_id và variant_id RIÊNG chứ không tách ngược từ
 * khoá: khoá là thứ để tra cứu, tách chuỗi ra để lấy dữ liệu là mời lỗi vào nhà.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * TICK CHỌN TỪNG DÒNG
 *
 * "Vin Eyewear Cart.dc.html" cho khách tick từng sản phẩm, và khối tóm tắt chỉ
 * cộng những dòng ĐANG TICK. Trang thanh toán vì thế cũng chỉ được lấy các
 * dòng đang tick — xem selectedItems(). Nếu thanh toán vẫn lấy cả giỏ thì con
 * số ở hai trang lệch nhau, và khách bị tính tiền cho món họ đã bỏ tick.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CartController extends BaseController
{
    /** Số lượng tối đa cho một dòng, khớp giới hạn của bản Lovable. */
    private const MAX_QTY = 20;

    // ========================================================================
    // HIỂN THỊ
    // ========================================================================

    public function index(): void
    {
        $lines    = self::lines();
        $subtotal = 0;
        $picked   = 0;   // số SẢN PHẨM đang tick (cộng cả số lượng)

        foreach ($lines as $line) {
            if ($line['selected']) {
                $subtotal += $line['lineTotal'];
                $picked   += $line['quantity'];
            }
        }

        $threshold   = (int) config('app.free_shipping_threshold');
        $shippingFee = ($subtotal > 0 && $subtotal < $threshold) ? (int) config('app.shipping_fee') : 0;
        $summary     = self::applyVoucher($subtotal, $shippingFee);

        $this->renderView('cart/index', [
            'pageTitle'   => 'Giỏ hàng — Vin Eyewear',
            'metaDesc'    => 'Xem lại sản phẩm trong giỏ hàng trước khi đặt.',
            'lines'       => $lines,
            'allSelected' => $lines !== [] && !self::hasUnselected($lines),
            'picked'      => $picked,
            'subtotal'    => $subtotal,
            'discount'    => $summary['discount'],
            'shippingFee' => $summary['shippingFee'],
            'threshold'   => $threshold,
            'total'       => $summary['total'],
            'voucher'     => $summary['voucher'],
            'voucherCode' => $_SESSION['cart_voucher'] ?? '',
            'voucherMsg'  => flash('cart_voucher_msg'),
            'voucherOk'   => flash('cart_voucher_ok') !== null,
            'maxQty'      => self::MAX_QTY,
            'success'     => flash('cart_success'),
            'error'       => flash('cart_error'),
        ]);
    }

    // ========================================================================
    // THAY ĐỔI
    // ========================================================================

    /**
     * Thêm sản phẩm vào giỏ. Nhận POST từ thẻ sản phẩm và trang chi tiết.
     */
    public function add(): void
    {
        $this->requirePost();

        $id        = (string) ($_POST['product_id'] ?? '');
        $variantId = trim((string) ($_POST['variant_id'] ?? '')) ?: null;
        $qty       = max(1, min(self::MAX_QTY, (int) ($_POST['quantity'] ?? 1)));

        // Đối chiếu lại với DB: product_id đến từ form, người dùng sửa được.
        $product = $id === '' ? null : ProductModel::find($id);

        if ($product === null || (int) $product['is_visible'] !== 1) {
            flash('cart_error', 'Sản phẩm không còn khả dụng.');
            redirect('/gio-hang');
        }

        $product  = ProductModel::findVisibleBySlug($product['slug']);
        $variants = VariantModel::forProduct($product['id']);
        $variant  = null;

        if ($variants !== []) {
            // Mặt hàng CÓ biến thể thì bắt buộc chọn một cái. Cho qua với
            // variant rỗng nghĩa là bán một thứ không xác định: giá nào, trừ
            // tồn kho của phương án nào?
            if ($variantId === null) {
                flash('cart_error', 'Vui lòng chọn một phương án trước khi thêm vào giỏ.');
                redirect('/san-pham/' . rawurlencode($product['slug']));
            }

            // findForProduct kiểm luôn biến thể có THUỘC mặt hàng này không —
            // không kiểm thì gửi id biến thể của món khác lên là mua được
            // với giá của món này.
            $variant = VariantModel::findForProduct($variantId, $product['id']);

            if ($variant === null) {
                flash('cart_error', 'Phương án bạn chọn không còn khả dụng.');
                redirect('/san-pham/' . rawurlencode($product['slug']));
            }
        } else {
            // Mặt hàng KHÔNG có biến thể — bỏ qua giá trị gửi lên, đừng tin nó.
            $variantId = null;
        }

        if (!VariantModel::inStock($product, $variant, $qty)) {
            flash('cart_error', sprintf('"%s" không đủ tồn kho.', $product['name']));
            redirect('/gio-hang');
        }

        $key     = self::key($product['id'], $variantId);
        $current = (int) ($_SESSION['cart'][$key]['quantity'] ?? 0);
        $new     = min(self::MAX_QTY, $current + $qty);

        // Món vừa bỏ vào giỏ luôn được tick sẵn: khách vừa chủ động thêm nó,
        // bắt họ tick lại một lần nữa là thừa.
        $_SESSION['cart'][$key] = [
            'product_id' => $product['id'],
            'variant_id' => $variantId,
            'quantity'   => $new,
            'selected'   => true,
        ];

        flash('cart_success', sprintf('Đã thêm "%s" vào giỏ hàng.', $product['name']));

        // Nút "Mua ngay" đi thẳng tới thanh toán; "Thêm vào giỏ" ở lại giỏ hàng
        redirect(($_POST['action'] ?? '') === 'buy' ? '/thanh-toan' : '/gio-hang');
    }

    /**
     * Mọi thao tác trên MỘT dòng giỏ hàng.
     *
     * Một action cho bốn nút (tick · − · + · thùng rác) vì bản thiết kế đặt cả
     * bốn trong cùng một hàng, và HTML không cho lồng <form> vào nhau. Gộp lại
     * thì mỗi dòng chỉ cần đúng một <form>, phân biệt bằng nút nào được bấm:
     *
     *     <button name="act" value="tang">+</button>
     *
     * Trình duyệt chỉ gửi lên `name`/`value` của nút ĐÃ BẤM, nên $_POST['act']
     * luôn nói đúng khách vừa làm gì.
     */
    public function update(): void
    {
        $this->requirePost();

        // 'key' chứ không phải product_id: một mặt hàng có thể nằm trong giỏ
        // nhiều lần với các biến thể khác nhau — xem ghi chú đầu file.
        $id = (string) ($_POST['key'] ?? '');

        if ($id === '' || !isset($_SESSION['cart'][$id])) {
            redirect('/gio-hang');
        }

        $row = $_SESSION['cart'][$id];
        $qty = max(1, (int) ($row['quantity'] ?? 1));

        switch ((string) ($_POST['act'] ?? '')) {
            case 'tang':
                $_SESSION['cart'][$id]['quantity'] = min(self::MAX_QTY, $qty + 1);
                break;

            case 'giam':
                // Sàn là 1, không phải 0: nút "−" giảm số lượng, muốn bỏ món ra
                // khỏi giỏ thì có nút thùng rác ngay bên cạnh. Để nó tự xoá ở
                // số 0 nghĩa là bấm nhanh một cái là mất món mà không hỏi.
                $_SESSION['cart'][$id]['quantity'] = max(1, $qty - 1);
                break;

            case 'chon':
                $_SESSION['cart'][$id]['selected'] = empty($row['selected']);
                break;

            case 'xoa':
                unset($_SESSION['cart'][$id]);
                break;

            default:
                // Ô nhập số lượng trực tiếp (không có JS thì đây là đường dự
                // phòng, và trình đọc màn hình dùng nó thay cho hai nút ±).
                $direct = (int) ($_POST['quantity'] ?? 0);

                if ($direct > 0) {
                    $_SESSION['cart'][$id]['quantity'] = min(self::MAX_QTY, $direct);
                }
        }

        redirect('/gio-hang');
    }

    /** Tick hoặc bỏ tick TẤT CẢ, theo trạng thái hiện tại. */
    public function toggleAll(): void
    {
        $this->requirePost();

        // Còn dòng nào chưa tick -> tick hết. Đã tick hết -> bỏ tick hết.
        // Đúng hành vi của nút "Chọn tất cả" trong bản thiết kế.
        $select = self::hasUnselected(self::lines());

        foreach (array_keys($_SESSION['cart'] ?? []) as $id) {
            $_SESSION['cart'][$id]['selected'] = $select;
        }

        redirect('/gio-hang');
    }

    /** Nút "Xoá mục đã chọn". */
    public function removeSelected(): void
    {
        $this->requirePost();

        $removed = 0;

        foreach ($_SESSION['cart'] ?? [] as $id => $row) {
            if (!empty($row['selected'])) {
                unset($_SESSION['cart'][$id]);
                $removed++;
            }
        }

        flash(
            $removed > 0 ? 'cart_success' : 'cart_error',
            $removed > 0
                ? sprintf('Đã xoá %d sản phẩm khỏi giỏ hàng.', $removed)
                : 'Chưa chọn sản phẩm nào để xoá.'
        );

        redirect('/gio-hang');
    }

    /**
     * Ô "Mã giảm giá": áp mã mới, hoặc gỡ mã đang có.
     *
     * Session chỉ nhớ CHUỖI mã. Số tiền giảm được tính lại ở mỗi lần hiện
     * trang, và một lần nữa lúc đặt hàng — xem ghi chú đầu file.
     */
    public function voucher(): void
    {
        $this->requirePost();

        if (($_POST['act'] ?? '') === 'go') {
            unset($_SESSION['cart_voucher']);
            flash('cart_voucher_msg', 'Đã gỡ mã giảm giá.');
            flash('cart_voucher_ok', '1');
            redirect('/gio-hang');
        }

        $code     = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $subtotal = self::selectedSubtotal();
        $result   = VoucherModel::evaluate($code, $subtotal, $_SESSION['user_id'] ?? null);

        if (!$result['ok']) {
            // KHÔNG lưu mã hỏng vào session: lần hiện trang sau sẽ thử lại nó
            // rồi báo lỗi lần nữa, trong khi khách chẳng làm gì thêm.
            unset($_SESSION['cart_voucher']);
            flash('cart_voucher_msg', $result['error']);
            redirect('/gio-hang');
        }

        $_SESSION['cart_voucher'] = $result['voucher']['code'];

        flash('cart_voucher_msg', sprintf(
            'Đã áp dụng mã %s: %s',
            $result['voucher']['code'],
            $result['voucher']['condition_text'] ?: $result['voucher']['title']
        ));
        flash('cart_voucher_ok', '1');

        redirect('/gio-hang');
    }

    public function clear(): void
    {
        $this->requirePost();

        unset($_SESSION['cart'], $_SESSION['cart_voucher']);

        redirect('/gio-hang');
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    /**
     * Giỏ hàng dạng [product_id => số lượng] — TẤT CẢ các dòng, kể cả chưa tick.
     *
     * Dùng cho huy hiệu đếm trên header và cho add(). Trang thanh toán thì
     * KHÔNG dùng hàm này — xem selectedItems().
     */
    public static function items(): array
    {
        $out = [];

        foreach ($_SESSION['cart'] ?? [] as $key => $row) {
            $qty = (int) ($row['quantity'] ?? 0);

            if ($qty > 0) {
                $out[(string) $key] = [
                    'product_id' => (string) ($row['product_id'] ?? $key),
                    'variant_id' => $row['variant_id'] ?? null,
                    'quantity'   => $qty,
                ];
            }
        }

        return $out;
    }

    /** Tổng số món trong giỏ — huy hiệu trên header. */
    public static function count(): int
    {
        $n = 0;

        foreach (self::items() as $row) {
            $n += $row['quantity'];
        }

        return $n;
    }

    /**
     * Khoá của một dòng giỏ hàng.
     *
     * Mặt hàng không có biến thể giữ nguyên khoá là product_id, nên giỏ hàng
     * của khách đang mở dở từ trước bản nâng cấp này vẫn đọc được.
     */
    private static function key(string $productId, ?string $variantId): string
    {
        return $variantId === null ? $productId : $productId . ':' . $variantId;
    }

    /**
     * Chỉ các dòng ĐANG TICK, dạng [product_id => số lượng].
     *
     * Đây là hàm mà trang thanh toán và OrderModel::place phải dùng. Dùng
     * items() ở đó sẽ tính tiền cả những món khách đã bỏ tick.
     */
    public static function selectedItems(): array
    {
        $out = [];

        foreach (self::items() as $key => $row) {
            if (!empty($_SESSION['cart'][$key]['selected'])) {
                $out[$key] = $row;
            }
        }

        return $out;
    }

    /**
     * Các dòng giỏ hàng đã ghép với sản phẩm trong DB.
     *
     * Gọi ở cả index() và toggleAll(), nên tách ra thay vì chép đôi. Nó cũng
     * là chỗ DUY NHẤT dọn khỏi giỏ những sản phẩm đã bị gỡ khỏi catalog.
     */
    private static function lines(): array
    {
        $cart = self::items();

        if ($cart === []) {
            return [];
        }

        $products = ProductModel::findManyById(array_column($cart, 'product_id'));
        $variants = VariantModel::forProducts(array_column($cart, 'product_id'));
        $lines    = [];

        foreach ($cart as $key => $row) {
            $pid = $row['product_id'];

            // Sản phẩm đã bị gỡ khỏi catalog từ lúc khách bỏ vào giỏ ->
            // bỏ khỏi giỏ luôn, không hiện dòng trống không giá.
            if (!isset($products[$pid])) {
                unset($_SESSION['cart'][$key]);
                continue;
            }

            $product = $products[$pid];
            $variant = null;

            if ($row['variant_id'] !== null) {
                foreach ($variants[$pid] ?? [] as $v) {
                    if ($v['id'] === $row['variant_id']) {
                        $variant = $v;
                        break;
                    }
                }

                // Biến thể đã bị tắt hoặc xoá kể từ lúc bỏ vào giỏ. Bỏ dòng
                // ra thay vì bán theo giá gốc — giá gốc là của một phương án
                // khác với thứ khách đã chọn.
                if ($variant === null) {
                    unset($_SESSION['cart'][$key]);
                    continue;
                }
            }

            $unit    = VariantModel::priceOf($product, $variant);
            $lineQty = min($row['quantity'], self::MAX_QTY);

            $lines[] = [
                'key'       => $key,
                'product'   => $product,
                'variant'   => $variant,
                'quantity'  => $lineQty,
                'unitPrice' => $unit,
                'lineTotal' => $unit * $lineQty,
                'selected'  => !empty($_SESSION['cart'][$key]['selected']),
                // Tồn kho có thể đã tụt kể từ lúc bỏ vào giỏ
                'available' => VariantModel::inStock($product, $variant, $lineQty),
                'stock'     => VariantModel::stockOf($product, $variant),
            ];
        }

        return $lines;
    }

    /** Có dòng nào chưa tick không? Quyết định hành vi của nút "Chọn tất cả". */
    private static function hasUnselected(array $lines): bool
    {
        foreach ($lines as $line) {
            if (!$line['selected']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tạm tính của các dòng đang tick, đọc lại giá từ DB.
     *
     * Dùng khi áp mã giảm giá — điều kiện "đơn tối thiểu" phải tính trên con
     * số thật, không phải trên số mà trình duyệt gửi lên.
     */
    public static function selectedSubtotal(): int
    {
        $cart = self::selectedItems();

        if ($cart === []) {
            return 0;
        }

        $products = ProductModel::findManyById(array_column($cart, 'product_id'));
        $variants = VariantModel::forProducts(array_column($cart, 'product_id'));
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

            $subtotal += VariantModel::priceOf($products[$pid], $variant)
                       * min($row['quantity'], self::MAX_QTY);
        }

        return $subtotal;
    }

    /**
     * Áp mã đang lưu trong session lên một cặp (tạm tính, phí ship).
     *
     * Trả về cả `voucher` (null nếu không có/không còn hợp lệ) để giao diện
     * biết có hiện nút "gỡ mã" hay không. Mã hết hiệu lực giữa chừng thì bị
     * gỡ khỏi session ngay tại đây — để lại thì khách thấy nó mãi mà không
     * bao giờ được giảm.
     *
     * @return array{discount:int, shippingFee:int, total:int, voucher:?array}
     */
    public static function applyVoucher(int $subtotal, int $shippingFee, ?string $userId = null): array
    {
        $code = $_SESSION['cart_voucher'] ?? '';
        $none = [
            'discount'    => 0,
            'shippingFee' => $shippingFee,
            'total'       => $subtotal + $shippingFee,
            'voucher'     => null,
        ];

        if ($code === '' || $subtotal <= 0) {
            return $none;
        }

        $result = VoucherModel::evaluate($code, $subtotal, $userId ?? ($_SESSION['user_id'] ?? null));

        if (!$result['ok']) {
            unset($_SESSION['cart_voucher']);

            return $none;
        }

        $applied = VoucherModel::apply($result['voucher'], $subtotal, $shippingFee);
        $ship    = $applied['freeShipping'] ? 0 : $shippingFee;

        return [
            'discount'    => $applied['discount'],
            'shippingFee' => $ship,
            'total'       => max(0, $subtotal - $applied['discount']) + $ship,
            'voucher'     => $result['voucher'],
        ];
    }

    /**
     * Chặn mọi thao tác đổi giỏ nếu không phải POST kèm token CSRF hợp lệ.
     *
     * Thiếu bước này, một trang khác chỉ cần nhúng <img src="/gio-hang/xoa">
     * là xoá được giỏ hàng của khách đang mở site.
     */
    private function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            redirect('/gio-hang');
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            http_response_code(419);
            flash('cart_error', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
            redirect('/gio-hang');
        }
    }
}
