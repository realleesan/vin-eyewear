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
            /* Lời của chính thao tác vừa rồi (áp mã / gỡ mã) được ưu tiên;
               không có thì mới tới lý do mã bị tự gỡ ở lần vẽ này. Hai thứ
               không bao giờ cùng lúc: gỡ tay thì đã xoá mã trước khi tới đây. */
            'voucherMsg'  => flash('cart_voucher_msg') ?? $summary['dropped'],
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
     *
     * ─────────────────────────────────────────────────────────────────────
     * GỌNG KÍNH VÀ KÍNH MÁT KHÔNG VÀO THẲNG GIỎ
     *
     * Hai danh mục đó bán được theo hai kiểu — mua trần, hoặc cắt kèm tròng
     * theo số đo mắt — và giá chênh nhau tới vài triệu. Nên lần bấm đầu tiên
     * không thêm gì cả: nó cất ý định vào phiên rồi đá về đúng trang khách
     * đang đứng, kèm ?mua=<id>, và _layout/buy-modal.php vẽ hộp thoại "Chọn
     * hình thức mua" ngay trên trang đó.
     *
     * Hộp thoại gửi lại chính đường này, lần này có `mode`:
     *   mode=gong   thêm trần, đúng như trước khi có hộp thoại
     *   mode=trong  kèm một gói tròng + số đo mắt
     *
     * Không có JS nào trong luồng này. Hộp thoại là HTML do máy chủ vẽ ra,
     * nút ✕ là một liên kết. Tắt JS vẫn mua được — xem ghi chú đầu
     * _layout/buy-modal.php.
     * ─────────────────────────────────────────────────────────────────────
     */
    public function add(): void
    {
        $this->requirePost();

        $id        = (string) ($_POST['product_id'] ?? '');
        $variantId = trim((string) ($_POST['variant_id'] ?? '')) ?: null;
        $qty       = max(1, min(self::MAX_QTY, (int) ($_POST['quantity'] ?? 1)));
        $mode      = (string) ($_POST['mode'] ?? '');

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

        // Nơi quay về sau khi chọn xong. Đến từ ô ẩn `back` của form, tức là
        // do người dùng kiểm soát — safeRedirectPath chặn đường dẫn ra ngoài.
        $back = safeRedirectPath(
            $_POST['back'] ?? null,
            '/san-pham/' . rawurlencode($product['slug'])
        );

        // ── Chưa qua hộp thoại -> mở hộp thoại thay vì thêm thẳng vào giỏ ──
        //
        // MỌI mặt hàng đều đi qua đây, không riêng gọng và kính mát. Trước đây
        // chỉ hai danh mục đó bị chặn, nên bấm "Mua ngay" một hộp kính áp tròng
        // là nhảy thẳng sang trang thanh toán — khách chưa kịp thấy mình vừa
        // mua gì, mua mấy cái.
        //
        // Khác nhau ở chỗ hộp thoại MỞ Ở BƯỚC NÀO, không phải ở chỗ có mở hay
        // không: gọng và kính mát bắt đầu từ "Chọn hình thức mua", còn tròng
        // rời và kính áp tròng vào thẳng "Xác nhận sản phẩm" — hỏi một hộp
        // kính áp tròng "chỉ mua gọng hay cắt thêm tròng?" là câu vô nghĩa.
        if ($mode === '') {
            // Cất Ý ĐỊNH chứ không nhét vào URL: số lượng, phương án và việc
            // khách bấm "Mua ngay" hay "Thêm vào giỏ" đều phải sống qua hai
            // bước của hộp thoại, mà nhồi hết vào query thì địa chỉ dài dòng
            // và sửa tay được.
            $_SESSION['_buy_intent'] = [
                'product_id' => $product['id'],
                'variant_id' => $variantId,
                'quantity'   => $qty,
                'action'     => ($_POST['action'] ?? '') === 'buy' ? 'buy' : 'add',
                'back'       => $back,
                // Bốn khoá dưới do buyStep() điền dần qua từng bước của hộp
                // thoại. Khai sẵn ở đây để mọi nơi đọc chúng không phải nhớ
                // rằng chúng có thể chưa tồn tại.
                //
                'mode'       => null,   // 'frame' | 'combo'
                'rx_type'    => null,   // khoá trong LensModel::RX_TYPES
                'rx'         => null,   // chuỗi số đo đã gói
                'lens_id'    => null,
            ];

            redirect(self::stepUrl($back, $product['id'], null));
        }

        // ── Tròng cắt kèm ────────────────────────────────────────────────
        $lens = null;
        $rx   = null;

        if ($mode === 'trong') {
            /* GÓI TRÒNG chỉ áp cho gọng và kính mát. Tròng rời và kính áp tròng
               đi qua đúng nhánh này (cũng lấy số đo), nhưng bản thân chúng ĐÃ LÀ
               tròng — cộng thêm một gói tròng nữa là bán hai cặp tròng cho một
               đơn và tính tiền cả hai. */
            if (LensModel::takesLensPackage($product)) {
                // Tra lại gói từ bảng giá, không tin tên và giá gửi lên. Đây là
                // chỗ duy nhất quyết định phần tròng đáng bao nhiêu tiền.
                $lens = LensModel::find(trim((string) ($_POST['lens'] ?? '')));

                if ($lens === null) {
                    flash('cart_error', 'Vui lòng chọn một gói tròng kính.');
                    redirect($back . (str_contains($back, '?') ? '&' : '?')
                        . 'mua=' . rawurlencode($product['id']) . '&buoc=trong');
                }
            }

            /* Số đo đã được gói thành chuỗi ở bước "Nhập số đo khúc xạ"
               (buyStep) và nằm trong ý định, không đọc lại từng ô ở đây: bước
               xác nhận chỉ còn việc chốt số lượng, và bắt nó mang theo bốn ô
               ẩn nữa là mời người ta sửa tay.

               null = khách bỏ qua bước số đo. Đó là một lựa chọn hợp lệ chứ
               không phải form điền thiếu — phần lớn người mua kính không nhớ
               số đo của mình, và cửa hàng đo lại miễn phí. */
            $rx = $_SESSION['_buy_intent']['rx'] ?? null;
        }

        // Khoá gồm cả gói tròng và số đo: cùng một chiếc gọng mua trần và mua
        // kèm tròng là hai món khác giá, và hai chiếc cùng gói tròng nhưng
        // khác độ là hai sản phẩm khác nhau — gộp chung dòng là mài sai một cái.
        $key     = self::key($product['id'], $variantId, $lens['id'] ?? null, $rx);
        $current = (int) ($_SESSION['cart'][$key]['quantity'] ?? 0);
        $new     = min(self::MAX_QTY, $current + $qty);

        // Món vừa bỏ vào giỏ luôn được tick sẵn: khách vừa chủ động thêm nó,
        // bắt họ tick lại một lần nữa là thừa.
        $_SESSION['cart'][$key] = [
            'product_id' => $product['id'],
            'variant_id' => $variantId,
            'quantity'   => $new,
            'selected'   => true,
            'lens_id'    => $lens['id'] ?? null,
            'rx'         => $rx,
        ];

        // Ý định đã dùng xong. Để lại thì lần sau mở hộp thoại sẽ mang theo
        // số lượng của lần mua trước.
        unset($_SESSION['_buy_intent']);

        $buyNow = ($_POST['action'] ?? '') === 'buy';

        /*
         * "MUA NGAY" CHỈ MUA ĐÚNG MÓN VỪA CHỌN.
         *
         * Trang thanh toán lấy hàng qua CartController::selectedItems(), tức là
         * MỌI dòng đang tick trong giỏ. Nên khách đã có sẵn ba món tick trong
         * giỏ mà bấm "Mua ngay" một chiếc gọng thì hoá đơn hiện ra bốn món —
         * họ chỉ định mua một.
         *
         * Bỏ tick những dòng còn lại thay vì dựng một "giỏ mua ngay" riêng:
         * cả trang thanh toán, mã giảm giá lẫn OrderModel::place() đều đọc
         * chung một chỗ, thêm một nguồn hàng thứ hai là ba nơi đó phải biết về
         * nó. Hàng KHÔNG mất đi — vẫn nằm nguyên trong giỏ, chỉ là chưa tick;
         * khách tick lại lúc nào cũng được ở /gio-hang.
         */
        if ($buyNow) {
            foreach ($_SESSION['cart'] as $k => $_) {
                $_SESSION['cart'][$k]['selected'] = ($k === $key);
            }
        }

        /* MỘT DÒNG, KHÔNG KÈM TÊN HÀNG.
           Bản trước ghi 'Đã thêm "Gọng kính Titan Vin T01" kèm Chống ánh sáng
           xanh vào giỏ hàng.' — đúng hơn, nhưng dài và ở màn hẹp thì dải báo
           xuống ba dòng. Khách vừa bấm nút trên đúng cái thẻ đó nên tên hàng
           không phải thứ họ cần đọc lại; cái họ cần biết là việc đã xong. */
        flash('cart_success', 'Đã thêm vào giỏ hàng!');

        /*
         * "MUA NGAY" đi thẳng tới thanh toán. "THÊM VÀO GIỎ" thì Ở LẠI ĐÚNG
         * TRANG KHÁCH ĐANG ĐỨNG, kèm một dải báo — không đá sang /gio-hang nữa.
         *
         * Đây là điểm khác của luồng trong "Trang chi tiết sản phẩm.dc.html":
         * thêm vào giỏ là một việc PHỤ, làm xong thì khách còn xem tiếp và mua
         * thêm. Ném họ sang giỏ hàng là kết thúc phiên duyệt hộ họ, và muốn mua
         * món thứ hai thì phải bấm quay lại.
         *
         * Huy hiệu số trên biểu tượng giỏ ở đầu trang tự cập nhật — nó đọc
         * CartController::count() ở mỗi lần vẽ trang, nên chỉ cần trang được
         * vẽ lại là con số đúng.
         */
        /* ?back= để trang thanh toán có đường lùi về ĐÚNG chỗ khách vừa rời.
           Không có nó thì nút lùi ở đó chỉ biết chỉ về /gio-hang — mà luồng
           "Mua ngay" không đi qua giỏ hàng lần nào. */
        redirect($buyNow
            ? '/thanh-toan?back=' . rawurlencode($back)
            : $back);
    }

    /**
     * Một bước của hộp thoại "Chọn hình thức mua" (POST /gio-hang/chon).
     *
     * ─────────────────────────────────────────────────────────────────────
     * NĂM BƯỚC, MỖI BƯỚC MỘT LẦN GỬI FORM
     *
     *   (không có)  Chọn hình thức mua      chỉ gọng · hay gọng + cắt tròng
     *   khuc-xa     Số đo khúc xạ           dùng hồ sơ đã lưu · hay nhập mới
     *   so-do       Nhập số đo khúc xạ      loại tật + độ hai mắt
     *   trong       Chọn loại tròng kính    năm gói trong config/taxonomy.php
     *   xac-nhan    Xác nhận sản phẩm       số lượng + tổng tiền
     *
     * Dựng theo luồng của "Vin Eyewear Product.dc.html". Bản thiết kế giữ cả
     * năm bước trong state trình duyệt; ở đây chúng nằm trong
     * $_SESSION['_buy_intent'] và mỗi bước là một POST thật.
     *
     * Vì sao không nhét vào URL: số đo mắt là dữ liệu sức khoẻ. Nó không nên
     * nằm trên thanh địa chỉ, trong lịch sử duyệt web, hay trong Referer gửi
     * sang bên thứ ba.
     *
     * KHÔNG kiểm tính hợp lệ ở đây ngoài việc chặn giá trị lạ. Bước cuối gửi
     * sang add(), và đó mới là nơi tra lại giá tròng và tồn kho — hàm này chỉ
     * ghi lại khách đã chọn gì.
     * ─────────────────────────────────────────────────────────────────────
     */
    public function buyStep(): void
    {
        $this->requirePost();

        $intent = $_SESSION['_buy_intent'] ?? null;

        // Không có ý định nào đang treo (phiên hết hạn, hoặc ai đó gửi thẳng
        // lên đây). Không có gì để bước tiếp — về giỏ hàng.
        if ($intent === null || empty($intent['product_id'])) {
            redirect('/gio-hang');
        }

        // Cần dòng sản phẩm để biết nhánh "theo số đo" của nó có phải chọn
        // thêm một gói tròng rời không — xem LensModel::takesLensPackage().
        // Mặt hàng vừa bị gỡ khỏi cửa hàng thì không còn gì để mua tiếp.
        $product = ProductModel::find($intent['product_id']);

        if ($product === null || (int) $product['is_visible'] !== 1) {
            unset($_SESSION['_buy_intent']);
            flash('cart_error', 'Sản phẩm không còn khả dụng.');
            redirect(safeRedirectPath($intent['back'] ?? null, '/gio-hang'));
        }

        $back = safeRedirectPath($intent['back'] ?? null, '/gio-hang');
        $next = null;

        switch ((string) ($_POST['buoc'] ?? '')) {

            // ── Bước 1: chỉ mua gọng, hay mua kèm tròng ──────────────────
            case 'hinh-thuc':
                $combo = ($_POST['che_do'] ?? '') === 'combo';
                $intent['mode'] = $combo ? 'combo' : 'frame';

                if (!$combo) {
                    // Mua trần thì không còn gì để hỏi — bỏ luôn phần tròng
                    // của lần chọn trước, nếu khách vừa quay lui đổi ý.
                    $intent['lens_id'] = null;
                    $intent['rx']      = null;
                    $intent['rx_type'] = null;
                }

                $next = $combo ? 'khuc-xa' : 'xac-nhan';
                break;

            // ── Bước 2: dùng hồ sơ khúc xạ đã lưu ────────────────────────
            case 'khuc-xa':
                // Đọc LẠI từ DB chứ không nhận số đo gửi lên: đây là hồ sơ
                // sức khoẻ của chính khách, và bản trong DB là bản đúng.
                $userId = AuthMiddleware::userId();
                $saved  = $userId === null ? null : UserModel::prescription($userId);

                $intent['rx']      = LensModel::formatSavedRx($saved);
                $intent['rx_type'] = null;
                $next = LensModel::takesLensPackage($product) ? 'trong' : 'xac-nhan';
                break;

            // ── Bước 3: số đo gõ tay ─────────────────────────────────────
            case 'so-do':
                $type = (string) ($_POST['loai'] ?? '');
                $intent['rx_type'] = isset(LensModel::RX_TYPES[$type]) ? $type : null;
                $intent['rx'] = LensModel::formatRx(
                    $intent['rx_type'],
                    $_POST['od'] ?? null,
                    $_POST['os'] ?? null
                );
                // Mặt hàng đã là tròng thì không chọn thêm gói tròng nào nữa
                $next = LensModel::takesLensPackage($product) ? 'trong' : 'xac-nhan';
                break;

            // ── Bước 4: chọn gói tròng ───────────────────────────────────
            case 'trong':
                $lens = LensModel::find(trim((string) ($_POST['lens'] ?? '')));

                if ($lens === null) {
                    flash('cart_error', 'Vui lòng chọn một gói tròng kính.');
                    redirect(self::stepUrl($back, $intent['product_id'], 'trong'));
                }

                $intent['lens_id'] = $lens['id'];
                $next = 'xac-nhan';
                break;

            // ── Bước 5: chỉnh số lượng ───────────────────────────────────
            case 'so-luong':
                $qty = (int) ($intent['quantity'] ?? 1);
                $intent['quantity'] = ($_POST['act'] ?? '') === 'tang'
                    ? min(self::MAX_QTY, $qty + 1)
                    : max(1, $qty - 1);
                $next = 'xac-nhan';
                break;

            default:
                // Tên bước lạ — đưa về đầu luồng thay vì đoán ý.
                $next = null;
        }

        $_SESSION['_buy_intent'] = $intent;

        redirect(self::stepUrl($back, $intent['product_id'], $next));
    }

    /**
     * Địa chỉ của một bước trong hộp thoại mua hàng.
     *
     * Hộp thoại vẽ ĐÈ LÊN trang khách đang đứng, nên địa chỉ luôn là trang đó
     * cộng thêm ?mua= (và &buoc= từ bước thứ hai trở đi). Nhờ vậy nút Back của
     * trình duyệt lùi đúng một bước, và đóng hộp thoại là về lại trang cũ
     * nguyên vẹn cả bộ lọc.
     */
    private static function stepUrl(string $back, string $productId, ?string $step): string
    {
        $url = $back . (str_contains($back, '?') ? '&' : '?')
             . 'mua=' . rawurlencode($productId);

        return $step === null ? $url : $url . '&buoc=' . rawurlencode($step);
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
            /* Nói ra thay vì lặng lẽ quay về. Trường hợp thật hay gặp: khách
               mở giỏ ở hai tab, xoá một món ở tab này rồi bấm ± cho đúng món
               đó ở tab kia. Im lặng thì trông như cái nút hỏng. */
            flash('cart_error', 'Sản phẩm không còn trong giỏ hàng.');
            redirect('/gio-hang');
        }

        $row = $_SESSION['cart'][$id];
        $qty = max(1, (int) ($row['quantity'] ?? 1));

        switch ((string) ($_POST['act'] ?? '')) {
            case 'tang':
                self::setQuantity($id, $row, min(self::MAX_QTY, $qty + 1));
                break;

            case 'giam':
                /* Sàn là 1, không phải 0. Muốn bỏ món ra khỏi giỏ thì ở số 1
                   nút "−" đổi thành nút xoá có hỏi lại — xem cart/index.php.
                   Tự xoá ở số 0 nghĩa là bấm nhanh một cái là mất món mà
                   không ai hỏi. Giảm số lượng KHÔNG cần tra tồn kho: ít hơn
                   thì luôn hợp lệ. */
                $_SESSION['cart'][$id]['quantity'] = max(1, $qty - 1);
                break;

            case 'chon':
                $_SESSION['cart'][$id]['selected'] = empty($row['selected']);
                break;

            case 'xoa':
                unset($_SESSION['cart'][$id]);
                flash('cart_success', 'Đã xoá sản phẩm khỏi giỏ hàng.');
                break;

            default:
                // Ô nhập số lượng trực tiếp (không có JS thì đây là đường dự
                // phòng, và trình đọc màn hình dùng nó thay cho hai nút ±).
                //
                // FILTER_VALIDATE_INT chứ không phải (int): ép kiểu biến "7.9"
                // thành 7 và "abc" thành 0 mà không ai biết là đã bị đổi. Số
                // lượng phải là SỐ NGUYÊN DƯƠNG, nên thứ không phải vậy thì
                // từ chối và nói ra.
                $direct = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT);

                if ($direct === false || $direct < 1) {
                    flash('cart_error', 'Số lượng phải là số nguyên lớn hơn 0.');
                    break;
                }

                self::setQuantity($id, $row, min(self::MAX_QTY, $direct));
        }

        redirect('/gio-hang');
    }

    /**
     * Đặt số lượng mới cho một dòng, SAU KHI đối chiếu tồn kho.
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO CHẶN Ở ĐÂY CHỨ KHÔNG CHỈ Ở GIAO DIỆN
     *
     * Trang giỏ hàng có tắt sẵn nút "+" khi đã chạm tồn kho, nhưng đó chỉ là
     * thứ nhìn thấy được: thuộc tính disabled sửa được bằng công cụ nhà phát
     * triển, và ô nhập số lượng thì gửi thẳng số nào cũng được. Trước bản này
     * máy chủ nhận tuốt — đo được: tồn kho 2 mà bấm "+" ba lần vẫn lên 4, gõ
     * thẳng 15 cũng vào, và sản phẩm đã chuyển sang hết hàng vẫn tăng được.
     *
     * Tồn kho THẬT vẫn chỉ bị trừ lúc đặt hàng (VariantModel::reserve chạy
     * một câu UPDATE có điều kiện, hai người mua món cuối cùng không cùng lấy
     * được). Chốt ở đây không thay thế chỗ đó — nó chỉ để khách biết ngay lúc
     * bấm, thay vì đi hết trang thanh toán mới bị chặn.
     * ─────────────────────────────────────────────────────────────────────
     */
    private static function setQuantity(string $key, array $row, int $want): void
    {
        $product = ProductModel::find($row['product_id']);

        if ($product === null || (int) ($product['is_visible'] ?? 0) !== 1) {
            flash('cart_error', 'Sản phẩm không còn khả dụng.');
            return;
        }

        $variant = null;

        if (($row['variant_id'] ?? null) !== null) {
            $variant = VariantModel::findForProduct($row['variant_id'], $product['id']);

            if ($variant === null) {
                flash('cart_error', 'Phương án bạn chọn không còn khả dụng.');
                return;
            }
        }

        // Hết hàng hoặc ngừng kinh doanh -> không cho tăng. Giữ nguyên số cũ
        // chứ không hạ xuống: giỏ là thứ của khách, tự ý sửa số của họ là
        // chuyện khác hẳn với việc từ chối một lần bấm.
        if (($product['status'] ?? '') !== 'in_stock') {
            flash('cart_error', 'Sản phẩm đã hết hàng hoặc ngừng kinh doanh.');
            return;
        }

        $stock = VariantModel::stockOf($product, $variant);

        if ($want > $stock) {
            flash('cart_error', sprintf(
                'Số lượng sản phẩm không đủ. Sản phẩm chỉ còn %d sản phẩm.',
                $stock
            ));
            return;
        }

        $_SESSION['cart'][$key]['quantity'] = $want;
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
                    // Hai khoá này đi thẳng tới OrderModel::place để vào hoá
                    // đơn. Giỏ hàng cũ (trước bản có hộp thoại) không có chúng
                    // -> null, và mọi nơi đọc đều hiểu là "mua trần".
                    'lens_id'    => $row['lens_id'] ?? null,
                    'rx'         => $row['rx'] ?? null,
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
     *     <product_id>[:<variant_id>][#<lens_id>[@<mã băm số đo>]]
     *
     * Mặt hàng không có biến thể và không cắt tròng giữ nguyên khoá là
     * product_id, nên giỏ hàng của khách đang mở dở từ trước bản nâng cấp này
     * vẫn đọc được.
     *
     * SỐ ĐO nằm trong khoá dưới dạng băm ngắn, không phải nguyên văn: hai
     * chiếc cùng gói tròng nhưng khác độ là hai sản phẩm khác nhau, gộp một
     * dòng ×2 là mài sai một chiếc. Băm thay vì chuỗi gốc chỉ để khoá không
     * phình ra và không chứa ký tự lạ — nội dung thật vẫn nằm trong dòng, khoá
     * không bao giờ bị tách ngược để lấy dữ liệu.
     */
    private static function key(
        string $productId,
        ?string $variantId,
        ?string $lensId = null,
        ?string $rx = null
    ): string {
        $key = $variantId === null ? $productId : $productId . ':' . $variantId;

        if ($lensId !== null) {
            $key .= '#' . $lensId;

            if ($rx !== null) {
                $key .= '@' . substr(md5($rx), 0, 8);
            }
        }

        return $key;
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

            /* Gói tròng cắt kèm. Tra lại từ bảng giá ở mỗi lần hiện trang,
               đúng như giá gọng: session chỉ nhớ ID, không bao giờ nhớ tiền.
               Gói bị gỡ khỏi config thì lui về "mua trần" thay vì bỏ cả dòng —
               chiếc gọng vẫn bán được, chỉ là không còn gói tròng đó nữa. */
            $lens = LensModel::find($row['lens_id'] ?? null);

            $unit    = VariantModel::priceOf($product, $variant) + (int) ($lens['price'] ?? 0);
            $lineQty = min($row['quantity'], self::MAX_QTY);

            $lines[] = [
                'key'       => $key,
                'product'   => $product,
                'variant'   => $variant,
                'lens'      => $lens,
                'rx'        => $row['rx'] ?? null,
                'quantity'  => $lineQty,
                'unitPrice' => $unit,
                'lineTotal' => $unit * $lineQty,
                'selected'  => !empty($_SESSION['cart'][$key]['selected']),
                // Tồn kho có thể đã tụt kể từ lúc bỏ vào giỏ
                'available' => VariantModel::inStock($product, $variant, $lineQty),
                'stock'     => VariantModel::stockOf($product, $variant),
                /* Còn tăng được một cái nữa không — để trang tắt sẵn nút "+".
                   Tính ở đây chứ không để view tự so `stock > quantity`: điều
                   kiện thật còn gồm cả products.status, mà một chỗ so thiếu
                   là nút lại mời khách bấm vào chỗ máy chủ sẽ từ chối. */
                'canAdd'    => $lineQty < self::MAX_QTY
                               && VariantModel::inStock($product, $variant, $lineQty + 1),
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
            /* Lý do mã vừa bị GỠ, hoặc null khi không có gì bị gỡ.
               Mã được đánh giá lại ở MỖI lần vẽ trang, nên nó tự rụng khi giỏ
               đổi — xoá bớt một món là tạm tính tụt xuống dưới đơn tối thiểu.
               Không mang lý do ra ngoài thì tiền giảm biến mất không một lời
               nào, và khách chỉ thấy tổng tiền tự nhiên tăng lên. */
            'dropped'     => null,
        ];

        if ($code === '' || $subtotal <= 0) {
            return $none;
        }

        $result = VoucherModel::evaluate($code, $subtotal, $userId ?? ($_SESSION['user_id'] ?? null));

        if (!$result['ok']) {
            unset($_SESSION['cart_voucher']);

            // evaluate() đã soạn sẵn câu nói rõ thiếu bao nhiêu tiền nữa thì
            // đủ đơn tối thiểu — chuyển nguyên văn ra thay vì tự viết lại.
            return ['dropped' => $result['error']] + $none;
        }

        $applied = VoucherModel::apply($result['voucher'], $subtotal, $shippingFee);
        $ship    = $applied['freeShipping'] ? 0 : $shippingFee;

        return [
            'discount'    => $applied['discount'],
            'shippingFee' => $ship,
            'total'       => max(0, $subtotal - $applied['discount']) + $ship,
            'voucher'     => $result['voucher'],
            'dropped'     => null,
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
        /*
         * Hỏng thì trả khách về ĐÚNG TRANG HỌ ĐANG ĐỨNG, không ném sang giỏ hàng.
         *
         * Trước đây mọi lỗi ở đây đều đổ về /gio-hang. Với luồng hộp thoại mua
         * hàng thì đó là một cái bẫy chẩn đoán: bảng "Chọn hình thức mua" hiện
         * ra bình thường (nó là GET, không cần token), nhưng cú bấm sau đó bị
         * từ chối và khách bị đá sang giỏ hàng — TRÔNG Y HỆT hành vi cũ trước
         * khi có hộp thoại. Không ai đoán được là token đã hết hạn.
         *
         * Token hết hạn là chuyện có thật và hay gặp: mở tab trang sản phẩm từ
         * hôm qua, hôm nay mới bấm mua.
         */
        $back = safeRedirectPath($_POST['back'] ?? null, '/gio-hang');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            redirect('/gio-hang');
        }

        if (!csrfCheck($_POST['_token'] ?? null)) {
            http_response_code(419);
            flash('cart_error', 'Phiên làm việc đã hết hạn — vui lòng tải lại trang rồi bấm lại.');
            redirect($back);
        }
    }
}
