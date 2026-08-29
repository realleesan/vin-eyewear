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
    /**
     * Trần TUYỆT ĐỐI cho một dòng giỏ — lưới an toàn, KHÔNG phải luật bán hàng.
     *
     * ─────────────────────────────────────────────────────────────────────
     * TRƯỚC ĐÂY CON SỐ NÀY LÀ 20, VÀ NÓ LÀ LUẬT
     *
     * Hồi đó tồn kho chưa được đối chiếu ở đâu cả, nên 20 là cách duy nhất
     * chặn một đơn vô lý. Nay tồn kho đã được kiểm ở cả ba chỗ — lúc thêm vào
     * giỏ (VariantModel::inStock), lúc đổi số lượng (setQuantity) và lúc ghi
     * đơn (VariantModel::reserve) — nên giữ trần 20 chỉ còn một tác dụng: cấm
     * khách mua 25 cái của mặt hàng đang còn 60 cái trong kho.
     *
     * GIỚI HẠN THẬT BÂY GIỜ LÀ TỒN KHO. Con số dưới đây chỉ chặn giá trị vô
     * nghĩa gửi thẳng lên máy chủ (ô số lượng sửa tay được), để một request
     * xin 900 triệu cái không phải đi qua truy vấn tồn kho mới bị từ chối.
     * ─────────────────────────────────────────────────────────────────────
     */
    private const ABS_MAX_QTY = 999;

    // ========================================================================
    // HIỂN THỊ
    // ========================================================================

    public function index(): void
    {
        $lines    = self::lines();
        $subtotal = 0;
        /*
         * ĐẾM SỐ MÓN ĐANG TICK, KHÔNG CỘNG SỐ LƯỢNG.
         *
         * Trước đây cộng số lượng, nên một giỏ có đúng một dòng ba chiếc hiện
         * "1 sản phẩm trong giỏ của bạn" ở đầu trang và "Tạm tính (3 sản
         * phẩm)" ở cột bên — hai câu cãi nhau trong cùng một khung nhìn, và
         * khách phải tự đoán câu nào đang nói thật.
         *
         * Chốt theo cách hiểu của cửa hàng: một sản phẩm có thể có nhiều số
         * lượng. Số lượng đã hiện sẵn trong ô của từng dòng rồi. Huy hiệu giỏ
         * hàng trên thanh nav cũng đếm như vậy.
         */
        $picked = 0;   // số MÓN đang tick

        foreach ($lines as $line) {
            if ($line['selected']) {
                $subtotal += $line['lineTotal'];
                $picked++;
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
            // Trần tuyệt đối, để ô <input type=number> có một `max` hợp lệ khi
            // tồn kho lớn hơn nó. Giới hạn THẬT của từng dòng là 'stock' trong
            // lines() — xem cart/index.php.
            'maxQty'      => self::ABS_MAX_QTY,
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
        $qty       = max(1, min(self::ABS_MAX_QTY, (int) ($_POST['quantity'] ?? 1)));
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

        /*
         * CHỈ CHẶN KHI KHÔNG CÒN GÌ ĐỂ THÊM — hết hàng, ngừng bán, hoặc tồn 0.
         *
         * TRƯỚC ĐÂY chỗ này hỏi `inStock($product, $variant, $qty)`, tức là
         * "có đủ ĐÚNG SỐ KHÁCH XIN không", và thiếu thì từ chối sạch cả cú
         * bấm. Hệ quả là cùng một ý muốn lại nhận hai kết quả khác nhau:
         *
         *   tồn 3, xin thẳng 5      -> không thêm gì, báo lỗi
         *   tồn 3, xin 2 rồi xin 2  -> âm thầm kẹp về 3
         *
         * Cửa hàng đã chốt MỘT cách cho cả hai đường: luôn kẹp về tồn kho và
         * nói ra. Khách vẫn mua được phần cửa hàng có, không phải bấm lại lần
         * nữa chỉ để đổi một con số mà chính trang đã biết.
         *
         * Phép kẹp nằm ở khối "KẸP THEO TỒN KHO" phía dưới — chỗ duy nhất biết
         * trong giỏ đã có sẵn mấy cái của đúng dòng này.
         */
        if (!VariantModel::inStock($product, $variant, 1)) {
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
                'rx'         => null,   // chuỗi số đo đã gói (để hiển thị)
                'rx_raw'     => null,   // đúng thứ khách đã chọn (để điền lại)
                'lens_type'  => null,   // kiểu tròng: đơn/hai/đa tròng, mắt đặt
                'lens_id'    => null,   // gói chiết suất (Mắt đặt thì null)
            ];

            $this->buyStepDone(self::stepUrl($back, $product['id'], null));
        }

        // ── Tròng cắt kèm ────────────────────────────────────────────────
        $lens     = null;
        $lensType = null;
        $rx       = null;

        if ($mode === 'trong') {
            /* GÓI TRÒNG chỉ áp cho gọng và kính mát. Tròng rời và kính áp tròng
               đi qua đúng nhánh này (cũng lấy số đo), nhưng bản thân chúng ĐÃ LÀ
               tròng — cộng thêm một gói tròng nữa là bán hai cặp tròng cho một
               đơn và tính tiền cả hai. */
            if (LensModel::takesLensPackage($product)) {
                /* KIỂU TRÒNG đọc từ ý định chứ không từ $_POST: nó được chốt ở
                   bước "Chọn loại tròng kính" và bước xác nhận không hỏi lại.
                   Tra lại qua findType() vì session cũng chỉ giữ id. */
                $lensType = LensModel::findType(
                    $_SESSION['_buy_intent']['lens_type'] ?? null
                );

                if ($lensType === null) {
                    flash('cart_error', 'Vui lòng chọn loại tròng kính.');
                    redirect($back . (str_contains($back, '?') ? '&' : '?')
                        . 'mua=' . rawurlencode($product['id']) . '&buoc=kieu-trong');
                }

                /* "Mắt đặt" dừng ở đây: không có bảng giá sẵn nào để chọn tiếp,
                   cửa hàng báo giá sau khi xem thông số. Đòi một gói chiết suất
                   cho nó là đòi một thứ bước trước cố ý không hỏi. */
                if (LensModel::typeTakesPackage($lensType)) {
                    // Tra lại gói từ bảng giá, không tin tên và giá gửi lên. Đây là
                    // chỗ duy nhất quyết định phần tròng đáng bao nhiêu tiền.
                    $lens = LensModel::find(trim((string) ($_POST['lens'] ?? '')));

                    if ($lens === null) {
                        flash('cart_error', 'Vui lòng chọn một gói tròng kính.');
                        redirect($back . (str_contains($back, '?') ? '&' : '?')
                            . 'mua=' . rawurlencode($product['id']) . '&buoc=trong');
                    }
                }
            }

            /* Số đo đã được gói thành chuỗi ở bước "Nhập số đo khúc xạ"
               (buyStep) và nằm trong ý định, không đọc lại từng ô ở đây: bước
               xác nhận chỉ còn việc chốt số lượng, và bắt nó mang theo bốn ô
               ẩn nữa là mời người ta sửa tay.

               null gần như không còn xảy ra: bước số đo nay bắt điền đủ mới
               cho đi tiếp — xem nhánh 'so-do' của buyStep(). Vẫn đọc bằng ??
               chứ không đòi hỏi, vì một lượt mua bắt đầu từ trước khi có luật
               đó có thể còn đang treo trong phiên của ai đó, và chặn nó ở đây
               là làm hỏng một giỏ hàng đang dở vì một chuyện không phải lỗi
               của khách. */
            $rx = $_SESSION['_buy_intent']['rx'] ?? null;
        }

        // Khoá gồm cả gói tròng và số đo: cùng một chiếc gọng mua trần và mua
        // kèm tròng là hai món khác giá, và hai chiếc cùng gói tròng nhưng
        // khác độ là hai sản phẩm khác nhau — gộp chung dòng là mài sai một cái.
        $key = self::key(
            $product['id'],
            $variantId,
            $lens['id'] ?? null,
            $rx,
            $lensType['id'] ?? null
        );

        /*
         * CHỐT LẠI LẦN THỨ HAI THÌ HOÀN LẠI LẦN TRƯỚC, KHÔNG CỘNG DỒN.
         *
         * Luồng "Mua ngay" nay GIỮ ý định sống qua trang thanh toán, để khách
         * bấm Lùi từ đó quay lại đúng bước "Xác nhận sản phẩm" (xem chỗ chuyển
         * hướng cuối hàm). Nhưng quay lại được thì cũng chốt lại được — và
         * chốt lại mà cứ cộng thêm thì một chiếc gọng thành hai, ba, bốn chiếc
         * theo số lần khách đổi ý.
         *
         * done_key/done_prev ghi lại dòng mà CHÍNH lượt mua này đã tạo và số
         * lượng của dòng đó TRƯỚC lượt mua. Hoàn về con số đó rồi mới cộng lần
         * mới: khách quay lại đổi thành 3 chiếc thì giỏ có 3, không phải 4. Đổi
         * cả phương án (quay về bước 1 chọn "cắt tròng") cũng đúng — dòng cũ
         * lùi về nguyên trạng, dòng mới là một khoá khác.
         *
         * Hàng khách đã tự bỏ vào giỏ TỪ TRƯỚC không hề bị đụng tới: done_prev
         * chính là phần đó.
         */
        $done = $_SESSION['_buy_intent']['done_key'] ?? null;

        if ($done !== null && isset($_SESSION['cart'][$done])) {
            $prev = (int) ($_SESSION['_buy_intent']['done_prev'] ?? 0);

            if ($prev > 0) {
                $_SESSION['cart'][$done]['quantity'] = $prev;
            } else {
                unset($_SESSION['cart'][$done]);
            }
        }

        $current = (int) ($_SESSION['cart'][$key]['quantity'] ?? 0);

        /*
         * KẸP THEO TỒN KHO, không chỉ theo trần tuyệt đối.
         *
         * Phép kiểm inStock() ở đầu hàm chỉ hỏi "có đủ $qty cái không" — nó
         * không biết trong giỏ đã có sẵn mấy cái của đúng dòng này. Tồn 5, bấm
         * "thêm 3" hai lần thì mỗi lần đều qua được, và giỏ thành 6.
         *
         * Trước đây trần 20 che mất chuyện đó với hàng tồn nhiều; từ khi giới
         * hạn thật là tồn kho thì nó lộ ra ở mọi mặt hàng.
         *
         * KẸP THÌ PHẢI NÓI RA. Bản trước kẹp im lặng, lý do khi đó là "dòng
         * giỏ hiện ngay số thật nên không có gì bị giấu" — nhưng "Thêm vào
         * giỏ" nay giữ khách Ở LẠI trang sản phẩm và chỉ hiện một dải báo, nên
         * họ không nhìn thấy dòng giỏ nào cả. Không nói thì họ tưởng đã có 5
         * cái trong giỏ, và chỉ biết sự thật ở bước thanh toán.
         */
        $stock   = VariantModel::stockOf($product, $variant);
        $muon    = $current + $qty;                       // số khách muốn có trong giỏ
        $new     = min(self::ABS_MAX_QTY, $stock, $muon);

        /* Dải báo "ok" chứ không phải "err": món ĐÃ vào giỏ, chỉ ít hơn số
           xin. Tô đỏ một việc vừa làm xong là nói sai chuyện đã xảy ra.
           flash() ghi sau sẽ đè lên câu này, nên nó phải đứng SAU dòng
           'Đã thêm vào giỏ hàng!' ở cuối hàm — xem chỗ đó. */
        $daKep = $new < $muon;

        /*
         * SỐ THỨ TỰ THÊM VÀO — để bảng xổ ở thanh nav biết "5 món mới nhất".
         *
         * Dùng bộ đếm tăng dần chứ không phải time(): hai lần thêm trong cùng
         * một giây là chuyện thường (bấm nhanh, hoặc thêm rồi quay lại đổi số
         * lượng), và khi đó dấu thời gian bằng nhau thì thứ tự trở về ngẫu
         * nhiên theo cách PHP sắp mảng. Bộ đếm thì không bao giờ hoà.
         *
         * GÁN LẠI mỗi lần thêm, kể cả khi dòng đã có sẵn: thêm nữa cùng một
         * món nghĩa là khách vừa đụng tới nó, nên nó phải nhảy lên đầu danh
         * sách "mới nhất" — đúng như Shopee làm.
         */
        $_SESSION['cart_seq'] = (int) ($_SESSION['cart_seq'] ?? 0) + 1;

        // Món vừa bỏ vào giỏ luôn được tick sẵn: khách vừa chủ động thêm nó,
        // bắt họ tick lại một lần nữa là thừa.
        $_SESSION['cart'][$key] = [
            'product_id' => $product['id'],
            'variant_id' => $variantId,
            'quantity'   => $new,
            'added_seq'  => $_SESSION['cart_seq'],
            'selected'   => true,
            'lens_id'    => $lens['id'] ?? null,
            'lens_type'  => $lensType['id'] ?? null,
            'rx'         => $rx,
        ];

        $buyNow = ($_POST['action'] ?? '') === 'buy';

        /*
         * Ý ĐỊNH MUA SỐNG TỚI KHI NÀO?
         *
         * "Thêm vào giỏ" kết thúc ngay tại đây: hàng đã vào giỏ, hộp thoại
         * đóng, còn lại là một dải báo. Xoá ý định — để lại thì lần sau mở hộp
         * thoại sẽ mang theo số lượng của lần mua trước, và bấm Lùi về trang
         * cũ sẽ dựng lại một hộp thoại của việc đã xong.
         *
         * "Mua ngay" thì CHƯA xong: khách đang đứng ở trang thanh toán, giữa
         * chừng một lượt mua. Giữ ý định để nút Lùi (cả nút của trình duyệt
         * lẫn nút "‹" trên trang thanh toán) đưa họ về đúng bước "Xác nhận sản
         * phẩm" chứ không rơi thẳng ra trang chủ trắng trơn.
         *
         * Ý định đó được xoá khi đơn được đặt xong — xem OrderController::place().
         */
        if ($buyNow && isset($_SESSION['_buy_intent'])) {
            $_SESSION['_buy_intent']['done_key']  = $key;
            $_SESSION['_buy_intent']['done_prev'] = $current;
        } else {
            unset($_SESSION['_buy_intent']);
        }

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
        /* Kẹp theo tồn thì nói luôn còn bao nhiêu — câu này thay hẳn câu
           "Đã thêm vào giỏ hàng!", không nối thêm: hai vế trong một dải báo ở
           màn hẹp là ba dòng chữ, mà vế quan trọng lại nằm sau. */
        flash('cart_success', $daKep
            ? sprintf('"%s" chỉ còn %d sản phẩm. Đã thêm tối đa vào giỏ hàng.', $product['name'], $stock)
            : 'Đã thêm vào giỏ hàng!');

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
        /* ?back= trỏ về BƯỚC TRƯỚC, tức bước "Xác nhận sản phẩm" — không phải
           trang khách vừa rời. Lùi từ trang thanh toán là lùi một bước trong
           lượt mua, chứ không phải bỏ cả lượt mua để về trang chủ.

           Không có tham số này thì nút lùi ở đó chỉ biết chỉ về /gio-hang —
           mà luồng "Mua ngay" không đi qua giỏ hàng lần nào. */
        redirect($buyNow
            ? '/thanh-toan?back=' . rawurlencode(self::stepUrl($back, $product['id'], 'xac-nhan'))
            : $back);
    }

    /**
     * Một bước của hộp thoại "Chọn hình thức mua" (POST /gio-hang/chon).
     *
     * ─────────────────────────────────────────────────────────────────────
     * NĂM BƯỚC, MỖI BƯỚC MỘT LẦN GỬI FORM
     *
     *   (không có)  Chọn hình thức mua      chỉ gọng · hay gọng + cắt tròng
     *   trong       Chọn loại tròng kính    năm gói trong config/taxonomy.php
     *   khuc-xa     Số đo khúc xạ           dùng hồ sơ đã lưu · hay nhập mới
     *   so-do       Nhập số đo khúc xạ      loại tật + độ hai mắt
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
                    $intent['lens_id']   = null;
                    $intent['lens_type'] = null;
                    $intent['rx']        = null;
                    $intent['rx_raw']    = null;
                }

                /*
                 * ─────────────────────────────────────────────────────────────
                 * THỨ TỰ: NHẬP ĐỘ TRƯỚC, CHỌN TRÒNG SAU CÙNG.
                 *
                 * Bản trước làm ngược — chọn gói tròng ngay sau "Mua gọng +
                 * cắt tròng", rồi mới tới màn nhập độ, theo bản thiết kế
                 * "Vin Eyewear.dc.html" và theo lý "hỏi câu dễ trước".
                 *
                 * Cửa hàng yêu cầu đảo lại, và họ có lý hơn: gói tròng chọn
                 * đúng hay sai PHỤ THUỘC VÀO ĐỘ. Mô tả từng gói nói thẳng theo
                 * dải độ ("dưới −4.00", "trên −6.00"), nên hỏi nó trước là bắt
                 * khách chọn khi chưa có dữ kiện, rồi phải quay lui đổi lại
                 * sau khi nhập độ xong. Đưa số đo lên đầu thì mọi câu hỏi còn
                 * lại đều trả lời được bằng thứ đã có trên màn hình.
                 *
                 * Luồng đầy đủ của nhánh cắt tròng:
                 *
                 *     hình thức → số đo → kiểu tròng → gói chiết suất → xác nhận
                 *                          └ "Mắt đặt" ─────────────────┘
                 *
                 * Mặt hàng KHÔNG có gói tròng (tròng rời, kính áp tròng) thì
                 * không có tròng nào để chọn — số đo xong là sang xác nhận.
                 * ─────────────────────────────────────────────────────────────
                 */
                $next = $combo ? 'so-do' : 'xac-nhan';
                break;

            // ── Bước 2: số đo gõ tay ─────────────────────────────────────
            case 'so-do':
                /*
                 * MỖI MẮT MỘT BỘ: độ cầu (dấu + độ lớn), độ trụ, trục, ghi chú.
                 *
                 * KHÔNG còn ô "loại tật" — xem ghi chú trong LensModel, chỗ
                 * hằng RX_TYPES từng đứng.
                 *
                 * Độ cầu tới đây là MỘT ô chọn mang sẵn dấu ("-2.00") — bảng
                 * số đo vẽ như vậy, xem ghi chú trong _layout/buy-modal.php.
                 *
                 * Vẫn đi qua LensModel::joinSph() dù không còn ô dấu nào để
                 * ghép: hàm đó là nơi DUY NHẤT chuẩn hoá một con số độ cầu, và
                 * nó nhận cả hai dạng (một ô mang dấu, hoặc hai ô rời như ở
                 * trang hồ sơ). Đọc thẳng $_POST vào là mở đường cho hai giao
                 * diện lưu ra hai định dạng khác nhau.
                 *
                 * Phần còn lại gửi thô sang model: LensModel lo toàn bộ việc
                 * kiểm dải và cắt ghi chú. Kiểm ở đây nữa thì thành hai nơi
                 * cùng định nghĩa "thế nào là số đo hợp lệ", và hai nơi đó sẽ
                 * lệch nhau vào lần sửa thứ ba.
                 */
                $eye = static fn (string $side): array => [
                    'sph'  => LensModel::joinSph(null, $_POST[$side] ?? null),
                    'cyl'  => $_POST[$side . '_cyl'] ?? null,
                    'axis' => $_POST[$side . '_axis'] ?? null,
                    'note' => $_POST[$side . '_note'] ?? null,
                ];

                /*
                 * ─────────────────────────────────────────────────────────
                 * GIỮ RIÊNG THỨ KHÁCH ĐÃ CHỌN, KHÔNG CHỈ CHUỖI ĐÃ GÓI
                 *
                 * $intent['rx'] là câu chữ để HIỂN THỊ ("MP −2.00 / −0.75 ×
                 * 180° (hay mỏi khi đọc lâu)"). Nó gộp, làm tròn và chèn dấu
                 * hiển thị, nên KHÔNG tách ngược ra sáu ô chọn được.
                 *
                 * Mà tách ngược là đúng việc phải làm khi khách bấm Lùi từ
                 * bước sau về đây: hộp thoại được JS nạp lại từ máy chủ (xem
                 * khối popstate trong buy-flow.js), tức bảng được VẼ LẠI TỪ
                 * ĐẦU. Máy chủ không nhớ gì thì bảng hiện ra trắng trơn, và
                 * khách vừa gõ sáu ô xong phải gõ lại từ đầu chỉ vì muốn xem
                 * lại bước trước.
                 *
                 * Nên cất thêm ĐÚNG NGUYÊN VĂN những gì đã gửi lên. Không
                 * chuẩn hoá, không kiểm tra: đây không phải dữ liệu để tính
                 * tiền hay ghi đơn — thứ đó là $intent['rx'], đã đi qua
                 * LensModel rồi. Đây chỉ là để điền lại đúng ô khách đã chọn.
                 * ─────────────────────────────────────────────────────────
                 */
                $intent['rx_raw'] = [];

                foreach (['od', 'os'] as $side) {
                    $intent['rx_raw'][$side] = [
                        'sph'  => trim((string) ($_POST[$side] ?? '')),
                        'cyl'  => trim((string) ($_POST[$side . '_cyl'] ?? '')),
                        'axis' => trim((string) ($_POST[$side . '_axis'] ?? '')),
                        'note' => trim((string) ($_POST[$side . '_note'] ?? '')),
                    ];
                }

                /*
                 * ─────────────────────────────────────────────────────────
                 * PHẢI ĐIỀN ĐỦ MỚI ĐI TIẾP ĐƯỢC.
                 *
                 * Bản trước cho bỏ trống cả bảng: lý là "phần lớn khách không
                 * nhớ số đo, cửa hàng đo lại miễn phí". Cửa hàng đã đổi ý và
                 * họ có lý — một đơn "cắt tròng theo độ" mà không mang theo
                 * độ nào thì mọi bước sau nó đều đang hỏi về một thứ không
                 * tồn tại: kiểu tròng và gói chiết suất đều được mô tả theo
                 * DẢI ĐỘ. Ai chưa biết độ của mình thì đi lối "Đặt lịch đo
                 * mắt miễn phí" ngay dưới nút, chứ không đi tiếp bằng một
                 * bảng trắng.
                 *
                 * BA Ô, KHÔNG PHẢI MỘT. Độ cầu và độ trụ bắt buộc cho cả hai
                 * mắt — "không loạn" là một câu trả lời THẬT và có sẵn dòng
                 * 0.00 để chọn, khác hẳn ô để trống nghĩa là chưa nhập. Trục
                 * chỉ bắt buộc khi độ trụ khác 0: trục của một mắt không loạn
                 * là con số vô nghĩa, và ô đó đang bị khoá đúng lúc ấy.
                 *
                 * Ghi chú thì không — nó vẫn là "không bắt buộc" như nhãn ghi.
                 *
                 * KIỂM Ở ĐÂY DÙ FORM ĐÃ CÓ `required`: thuộc tính đó là của
                 * trình duyệt, mà bước này nhận POST thẳng — tắt JS, sửa DOM,
                 * hay gửi tay bằng curl đều đi vòng qua nó được.
                 *
                 * Lỗi thì QUAY LẠI CHÍNH BƯỚC NÀY, và $intent['rx_raw'] ở
                 * trên đã cất đúng những ô khách vừa chọn — bảng vẽ lại vẫn
                 * đầy số, chỉ thiếu đúng ô còn trống. Không cất trước khi
                 * kiểm thì mỗi lần thiếu một ô là mất cả năm ô kia.
                 * ─────────────────────────────────────────────────────────
                 */
                $thieu = [];

                foreach (['od' => 'mắt phải', 'os' => 'mắt trái'] as $side => $ten) {
                    $o = $intent['rx_raw'][$side];

                    if ($o['sph'] === '' || $o['cyl'] === '') {
                        $thieu[] = 'độ ' . $ten;
                        continue;
                    }

                    /* CÓ JS thì gần như không ai tới được đây: buy-rx.js mở ô
                       trục ngay khi khách chọn độ trụ, và `required` chặn cú
                       bấm ngay tại chỗ. Nhánh này là cho lượt KHÔNG có JS —
                       ô trục lúc đó khoá suốt nên không gửi gì lên. Bảng vẽ
                       lại lần này mở sẵn nó (xem $hasCyl trong
                       _layout/buy-modal.php), nên họ chọn được ở lượt sau. */
                    if ((float) $o['cyl'] !== 0.0 && $o['axis'] === '') {
                        $thieu[] = 'trục ' . $ten;
                    }
                }

                if ($thieu !== []) {
                    $_SESSION['_buy_intent'] = $intent;

                    flash('cart_error', 'Vui lòng chọn đủ: ' . implode(', ', $thieu) . '.');
                    $this->buyStepDone(self::stepUrl($back, $intent['product_id'], 'so-do'));
                }

                $od = $eye('od');
                $os = $eye('os');

                $intent['rx'] = LensModel::formatRx($od, $os);

                /* LẦN NHẬP ĐẦU TIÊN thì dựng luôn hồ sơ khúc xạ cho khách đang
                   đăng nhập — cửa hàng có ngay bản ghi để tư vấn thay vì chờ
                   khách tự vào trang tài khoản khai lại. Đã có hồ sơ rồi thì
                   hàm này không đụng vào: lý do đầy đủ ở UserModel.

                   BỌC TRY/CATCH VÌ ĐÂY LÀ VIỆC PHỤ, KHÔNG PHẢI VIỆC CHÍNH.

                   Việc chính của bước này là ghi số đo vào ý định mua hàng —
                   xong ở dòng trên rồi. Dựng sẵn hồ sơ tài khoản chỉ là tiện
                   thể. Để nó ném ra ngoài thì một cột thiếu trong bảng
                   `prescriptions` sẽ CHẶN CẢ ĐƠN HÀNG, mà khách không hiểu vì
                   sao: hộp thoại gọi bước này bằng fetch, gặp 500 thì
                   buy-flow.js đưa trình duyệt tới /gio-hang/chon, địa chỉ đó
                   chỉ nhận POST nên lại đá về /gio-hang. Khách bấm "Xác nhận
                   độ kính" và thấy mình đứng ở giỏ hàng, không một lời giải
                   thích. Đúng như vậy đã xảy ra trên hosting ngày 2026-08-22,
                   khi migration 2026-08-21-kinh-dang-deo.sql chưa được chạy ở
                   đó nên năm cột wear_* không tồn tại.

                   Nuốt lỗi ở đây là ĐÚNG chứ không phải giấu: mất hồ sơ dựng
                   sẵn thì khách vẫn khai được ở trang tài khoản, còn mất đơn
                   hàng thì không lấy lại được. Vẫn ghi log để còn biết. */
                try {
                    UserModel::seedPrescription(AuthMiddleware::customerId(), $od, $os);
                } catch (Throwable $e) {
                    error_log('seedPrescription: ' . $e->getMessage());
                }

                // Đã có độ rồi mới bàn tới tròng — xem ghi chú ở 'hinh-thuc'.
                $next = LensModel::takesLensPackage($product) ? 'kieu-trong' : 'xac-nhan';
                break;

            // ── Bước 3: kiểu tròng (đơn · hai · đa · mắt đặt) ────────────
            case 'kieu-trong':
                $type = LensModel::findType(trim((string) ($_POST['kieu'] ?? '')));

                if ($type === null) {
                    flash('cart_error', 'Vui lòng chọn loại tròng kính.');
                    $this->buyStepDone(self::stepUrl($back, $intent['product_id'], 'kieu-trong'));
                }

                $intent['lens_type'] = $type['id'];

                /* "Mắt đặt" không đi tiếp sang bảng giá: tròng đặt riêng theo
                   đơn thì chưa có giá nào để chọn, cửa hàng báo sau khi xem
                   thông số. Bỏ luôn gói của lần chọn trước, nếu khách vừa quay
                   lui đổi từ "Đa tròng" sang "Mắt đặt" — để lại thì đơn mang
                   theo một khoản tiền tròng của kiểu đã bị thay. */
                if (!LensModel::typeTakesPackage($type)) {
                    $intent['lens_id'] = null;
                    $next = 'xac-nhan';
                    break;
                }

                $next = 'trong';
                break;

            // ── Bước 4: chọn gói chiết suất ──────────────────────────────
            case 'trong':
                $lens = LensModel::find(trim((string) ($_POST['lens'] ?? '')));

                if ($lens === null) {
                    flash('cart_error', 'Vui lòng chọn một gói tròng kính.');
                    $this->buyStepDone(self::stepUrl($back, $intent['product_id'], 'trong'));
                }

                $intent['lens_id'] = $lens['id'];
                $next = 'xac-nhan';
                break;

            // ── Bước 5: chỉnh số lượng ───────────────────────────────────
            case 'so-luong':
                $qty = (int) ($intent['quantity'] ?? 1);
                $intent['quantity'] = ($_POST['act'] ?? '') === 'tang'
                    ? min(self::ABS_MAX_QTY, $qty + 1)
                    : max(1, $qty - 1);
                $next = 'xac-nhan';
                break;

            default:
                // Tên bước lạ — đưa về đầu luồng thay vì đoán ý.
                $next = null;
        }

        $_SESSION['_buy_intent'] = $intent;

        $this->buyStepDone(self::stepUrl($back, $intent['product_id'], $next));
    }

    /**
     * Địa chỉ của một bước trong hộp thoại mua hàng.
     *
     * Hộp thoại vẽ ĐÈ LÊN trang khách đang đứng, nên địa chỉ luôn là trang đó
     * cộng thêm ?mua= (và &buoc= từ bước thứ hai trở đi). Nhờ vậy nút Back của
     * trình duyệt lùi đúng một bước, và đóng hộp thoại là về lại trang cũ
     * nguyên vẹn cả bộ lọc.
     */
    /**
     * Kết thúc một bước của hộp thoại: trả mảnh nếu gọi bằng fetch, không thì
     * chuyển hướng như cũ.
     *
     * MỘT CHỖ DUY NHẤT quyết định điều đó, để bốn lối ra của luồng mua không
     * thể lệch nhau — thêm bước thứ sáu sau này chỉ việc gọi hàm này.
     *
     * Trình duyệt KHÔNG có JavaScript vẫn đi đường cũ (302 rồi GET), nên luồng
     * mua không phụ thuộc vào fetch. Xem BaseController::buyFragment().
     */
    private function buyStepDone(string $url): never
    {
        if (($_SERVER['HTTP_X_BUY_FLOW'] ?? '') === '1') {
            $this->buyFragment($url);
        }

        redirect($url);
    }

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
                self::setQuantity($id, $row, min(self::ABS_MAX_QTY, $qty + 1));
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

                self::setQuantity($id, $row, min(self::ABS_MAX_QTY, $direct));
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
        $result   = VoucherModel::evaluate($code, $subtotal, AuthMiddleware::customerId());

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
                    // Ba khoá này đi thẳng tới OrderModel::place để vào hoá
                    // đơn. Giỏ hàng cũ (trước bản có hộp thoại) không có chúng
                    // -> null, và mọi nơi đọc đều hiểu là "mua trần".
                    'lens_id'    => $row['lens_id'] ?? null,
                    'lens_type'  => $row['lens_type'] ?? null,
                    'rx'         => $row['rx'] ?? null,
                    /* Số thứ tự thêm vào — recent() sắp theo nó. PHẢI chép ra
                       đây: hàm này dựng một mảng MỚI với danh sách khoá cố
                       định, nên khoá nào quên là mất hẳn ở mọi nơi đọc giỏ
                       hàng qua items(). Giỏ cũ chưa có nó -> 0, tức xuống
                       cuối danh sách "mới nhất", đúng chỗ. */
                    'added_seq'  => (int) ($row['added_seq'] ?? 0),
                ];
            }
        }

        return $out;
    }

    /**
     * Vài món MỚI THÊM GẦN ĐÂY NHẤT, đã ghép với sản phẩm trong CSDL.
     *
     * Dùng cho bảng xổ khi rê vào giỏ hàng ở thanh nav.
     *
     * ─────────────────────────────────────────────────────────────────────
     * CHỈ TRA CỨU ĐÚNG NHỮNG DÒNG SẼ HIỆN RA
     *
     * Bảng xổ nằm trong header, tức nó dựng ở MỌI trang của site. Trước khi
     * có nó, header không chạm tới cơ sở dữ liệu lần nào — giỏ hàng nằm gọn
     * trong $_SESSION. Nay phải có tên, ảnh và giá, nên không tránh được một
     * lượt truy vấn.
     *
     * Bù lại bằng cách CẮT TRƯỚC, TRA SAU: sắp theo số thứ tự thêm vào, lấy
     * đúng $limit dòng, rồi mới hỏi CSDL về chừng ấy sản phẩm. Giỏ 30 món
     * cũng chỉ tra 5. Giỏ rỗng thì không có câu truy vấn nào.
     *
     * KHÔNG dọn dòng chết ở đây (sản phẩm đã gỡ khỏi catalog) — chỉ bỏ qua
     * khi hiện. Việc dọn là của lines() ở trang giỏ hàng, và một hàm chỉ để
     * VẼ thì không nên có tác dụng phụ lên phiên của khách.
     * ─────────────────────────────────────────────────────────────────────
     *
     * @return array{lines: array<int, array>, shown: int, more: int}
     */
    public static function recent(int $limit = 5): array
    {
        $cart = self::items();

        if ($cart === []) {
            return ['lines' => [], 'shown' => 0, 'more' => 0];
        }

        /* Mới nhất lên đầu. Dòng của giỏ cũ (thêm trước khi có added_seq) coi
           như số 0, tức rơi xuống cuối — đúng chỗ của chúng. */
        uasort($cart, static function (array $a, array $b): int {
            return ($b['added_seq'] ?? 0) <=> ($a['added_seq'] ?? 0);
        });

        $tong  = count($cart);
        $dau   = array_slice($cart, 0, max(1, $limit), true);

        $products = ProductModel::findManyById(array_column($dau, 'product_id'));
        $variants = VariantModel::forProducts(array_column($dau, 'product_id'));
        $lines    = [];

        foreach ($dau as $key => $row) {
            $pid = $row['product_id'];

            if (!isset($products[$pid])) {
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
            }

            $lens = LensModel::combo($row['lens_id'] ?? null, $row['lens_type'] ?? null);

            $lines[] = [
                'key'      => $key,
                'name'     => $product['name'],
                'slug'     => $product['slug'],
                /* Bản NHỎ: ô này hiện ở 40px, ảnh gốc 800×800 nặng gấp
                   mười lăm lần mà không thêm một chi tiết nào nhìn thấy
                   được. Không có bản nhỏ thì thumb() tự lui về ảnh gốc —
                   xem ProductModel::thumb(). */
                'image'    => ProductModel::thumb($product),
                'quantity' => (int) $row['quantity'],
                // Giá MỘT chiếc đã gồm tròng, giống dòng trong trang giỏ hàng.
                'price'    => VariantModel::priceOf($product, $variant)
                              + (int) ($lens['price'] ?? 0),
            ];
        }

        return [
            'lines' => $lines,
            'shown' => count($lines),
            /* Đếm trên TỔNG số dòng, không trên số dòng vẽ được: một món đã bị
               gỡ khỏi catalog vẫn đang nằm trong giỏ khách, và nói "còn 2 món
               nữa" rồi mở giỏ ra thấy 3 thì thà đừng nói. */
            'more'  => max(0, $tong - count($lines)),
        ];
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
     *     <product_id>[:<variant_id>][~<kiểu tròng>][#<lens_id>][@<mã băm số đo>]
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
     *
     * SỐ ĐO ĐỨNG ĐỘC LẬP VỚI GÓI TRÒNG. Trước đây nó chỉ được nối vào khoá khi
     * đã có `lens_id`, và hệ quả là mọi mặt hàng KHÔNG cắt gói tròng — kính áp
     * tròng, tròng rời — gộp mọi số đo về chung một dòng: mua một hộp cho mắt
     * phải −2.00 rồi một hộp cho mắt trái −3.25 thì giỏ hiện "×2" của một độ
     * duy nhất. Kiểu "Mắt đặt" (không có gói chiết suất) cũng sẽ rơi đúng vào
     * đó. Nay ba mảnh — kiểu tròng, gói, số đo — mỗi mảnh vào khoá một cách
     * độc lập.
     */
    private static function key(
        string $productId,
        ?string $variantId,
        ?string $lensId = null,
        ?string $rx = null,
        ?string $lensType = null
    ): string {
        $key = $variantId === null ? $productId : $productId . ':' . $variantId;

        if ($lensType !== null) {
            $key .= '~' . $lensType;
        }

        if ($lensId !== null) {
            $key .= '#' . $lensId;
        }

        if ($rx !== null) {
            $key .= '@' . substr(md5($rx), 0, 8);
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

            /* Tròng cắt kèm — kiểu tròng GỘP với gói chiết suất thành một
               mẩu. Tra lại từ bảng giá ở mỗi lần hiện trang, đúng như giá
               gọng: session chỉ nhớ ID, không bao giờ nhớ tiền. Cả hai bị gỡ
               khỏi config thì lui về "mua trần" thay vì bỏ cả dòng — chiếc
               gọng vẫn bán được, chỉ là không còn lựa chọn tròng đó nữa. */
            $lens = LensModel::combo($row['lens_id'] ?? null, $row['lens_type'] ?? null);

            $unit    = VariantModel::priceOf($product, $variant) + (int) ($lens['price'] ?? 0);
            $lineQty = min($row['quantity'], self::ABS_MAX_QTY);

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
                'canAdd'    => $lineQty < self::ABS_MAX_QTY
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
                       * min($row['quantity'], self::ABS_MAX_QTY);
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

        $result = VoucherModel::evaluate($code, $subtotal, $userId ?? AuthMiddleware::customerId());

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
