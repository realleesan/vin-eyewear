<?php

/**
 * order/checkout.php — thanh toán (/thanh-toan)
 *
 * Dựng theo "Vin Eyewear Checkout.dc.html" (Claude Design):
 *
 *   khung rút gọn (logo + "Thanh toán an toàn") — xem _layout/checkout-header.php
 *   → breadcrumb nhỏ + tiêu đề
 *   → hai cột: BA thẻ bước đánh số | tóm tắt 400px dính theo cuộn
 *
 * CSS: assets/css/checkout.css (+ cart.css cho khối .csum*, dùng chung với giỏ hàng)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG MỘT DÒNG JAVASCRIPT NÀO
 *
 * Bản thiết kế đổi giữa "Giao tận nơi" và "Nhận tại cửa hàng" bằng state trình
 * duyệt, và ẩn/hiện khối tương ứng. Ở đây cả hai khối luôn có trong DOM, và ô
 * radio thật + CSS `:has()` lo phần ẩn hiện.
 *
 * Vì sao không ẩn bằng JS: máy chủ mới là nơi quyết định trường nào bắt buộc
 * (xem OrderController::place). Nếu khối địa chỉ chỉ tồn tại khi JS chạy, thì
 * tắt JS là không đặt được hàng. Còn `:has()` chỉ ẩn về mặt hình ảnh — dữ liệu
 * vẫn gửi lên đủ, và server tự bỏ qua phần không dùng.
 *
 * BA THẺ NHƯNG MỘT <form>
 * Bản thiết kế vẽ ba thẻ trắng rời nhau. Chúng nằm trong CÙNG một <form> —
 * đây là một biểu mẫu duy nhất gửi một lần, không phải ba bước có nút "tiếp theo".
 *
 * Ô "Mã giảm giá" trong khối tóm tắt cũng nằm trong form đó, và các nút chọn mã
 * dùng `formaction="/thanh-toan/ma"`. Nhờ vậy bấm chọn mã là gửi kèm TOÀN BỘ
 * những gì khách đã gõ, server cất lại rồi trả về — form không bị trắng. Danh
 * sách thả xuống mở bằng <details>, vẫn không một dòng JavaScript nào.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$old = $old ?? [];
/* Giảm giá đi theo mã khách đã áp ở giỏ hàng. Con số do controller tính lại
   từ bảng `vouchers`, view chỉ cộng trừ để hiện — xem CartController::applyVoucher. */
$total = max(0, $subtotal - $discount) + $shippingFee;

/*
 * ĐIỀN SẴN — thứ tự ưu tiên, cao xuống thấp:
 *
 *   1. $old       dữ liệu khách vừa gõ, khi form quay lại vì báo lỗi.
 *                 Luôn thắng: không ai muốn gõ lại thứ mình vừa gõ.
 *   2. $address   địa chỉ MẶC ĐỊNH trong sổ địa chỉ (/tai-khoan?muc=dia-chi).
 *   3. $profile   hồ sơ tài khoản — họ tên, SỐ ĐIỆN THOẠI và email. Đây là
 *                 lưới đỡ cuối: sổ địa chỉ trống (khách chưa từng lưu địa chỉ
 *                 nào) thì hai ô tên và điện thoại vẫn phải có sẵn chữ, vì
 *                 tài khoản nào cũng đã khai số lúc đăng ký. Email thì chỉ có
 *                 ở đây, sổ địa chỉ không giữ email.
 *
 * Vì sao sổ địa chỉ đứng trên hồ sơ ở hai ô tên và điện thoại: xem ghi chú ở
 * OrderController::checkout().
 */
$fill = static fn (string $key, ?string $fallback = null): string =>
    (string) ($old[$key] ?? $fallback ?? '');

/*
 * ─────────────────────────────────────────────────────────────────────────
 * CHUỖI RỖNG CŨNG LÀ "CHƯA CÓ" — dùng cho hai ô BẮT BUỘC (tên, điện thoại).
 *
 * `??` chỉ bắt NULL, mà `addresses.recipient_name` và `addresses.phone` là
 * cột NOT NULL: một địa chỉ lưu thiếu số nằm trong CSDL dưới dạng '' chứ
 * không phải NULL, và chuỗi rỗng đó THẮNG ở `??` — tài khoản có sẵn số điện
 * thoại trong hồ sơ mà ô vẫn hiện ra trống. Cùng chuyện đó với $old: form
 * quay lại vì lỗi ở ô khác thì $old['customerPhone'] = '' cũng đè mất số của
 * hồ sơ, và khách phải tự gõ lại thứ hệ thống đã biết.
 *
 * Nên ở đây lấy giá trị ĐẦU TIÊN THỰC SỰ CÓ CHỮ, không phải giá trị đầu tiên
 * khác NULL.
 *
 * CHỈ ÁP CHO Ô BẮT BUỘC. Ô email để nguyên $fill: nó không bắt buộc, nên xoá
 * trắng là một lựa chọn có chủ ý — điền lại giúp là giành quyền quyết định
 * của khách.
 * ─────────────────────────────────────────────────────────────────────────
 */
$fillCo = static function (string $key, ?string ...$nguon) use ($old): string {
    array_unshift($nguon, $old[$key] ?? null);

    foreach ($nguon as $gt) {
        if ($gt !== null && trim($gt) !== '') {
            return trim($gt);
        }
    }

    return '';
};

$address = $address ?? null;

/* Điền sẵn từ địa chỉ mặc định trong sổ. Trước đây sổ gộp phường/xã và
   tỉnh/thành vào một cột nên chỗ này phải đoán ngược bằng splitArea(); nay sổ
   lưu hai cột riêng (xem migration 2026-08-19-so-dia-chi-tach-phuong-tinh)
   nên lấy thẳng, không còn phép đoán nào. */
$addressWard = (string) ($address['ward_name'] ?? '');
$addressCity = (string) ($address['province_name'] ?? '');

/*
 * MÃ HÀNH CHÍNH CHỈ ĐI KÈM KHI Ô CHỮ ĐANG HIỆN ĐÚNG TÊN CỦA SỔ ĐỊA CHỈ.
 *
 * $fill() ưu tiên thứ khách vừa gõ hỏng ($old) hơn địa chỉ mặc định. Nếu cứ
 * đổ mã của sổ vào trong khi ô chữ đang hiện thứ khách gõ, cụm chọn sẽ dò theo
 * MÃ trước, chọn trúng tỉnh của sổ, rồi ghi đè luôn cái tên khách vừa gõ — mất
 * dữ liệu ngay trước mắt họ. Lệch thì bỏ mã đi, để cụm dò theo tên.
 */
$cityShown = $fill('addressCity', $addressCity);
$wardShown = $fill('addressWard', $addressWard);

$cityCode = $cityShown === $addressCity ? (string) ($address['province_code'] ?? '') : '';
$wardCode = $wardShown === $addressWard ? (string) ($address['ward_code'] ?? '') : '';

$delivery = $old['deliveryMethod'] ?? 'shipping';
$payment  = $old['paymentMethod'] ?? 'cod';

/* Chuyển khoản: cọc 30% hay chuyển đủ. Mặc định 'full' — vế cửa hàng khuyến
   khích và cũng là vế được tặng mã. Xem OrderModel::place(). */
$bankAmount = ($old['bankAmount'] ?? 'full') === 'deposit' ? 'deposit' : 'full';

/* Mã đang được tặng cho khách chuyển đủ 100%, hoặc null nếu cửa hàng chưa bật
   mã nào. Tra MỘT LẦN ở đây vì trang này nhắc tới nó ở HAI chỗ cách nhau khá
   xa: thẻ "Chuyển khoản ngân hàng" ở bước 3, và vế "Chuyển đủ 100%" trong
   khối tóm tắt. Hai lần gọi là hai câu truy vấn cho cùng một câu trả lời.

   KHÔNG phụ thuộc vào việc khách đã từng chuyển đủ hay chưa: đây là LỜI MỜI,
   phải hiện với cả người mua lần đầu — họ mới là người cần biết nhất. Mã đã
   nằm trong ví thì hiện ở chỗ khác (xem VoucherModel::rewardHeldBy). */
$reward = VoucherModel::reward();
$storeId  = $old['storeId'] ?? '';
?>

<section class="checkout">

    <div class="cohead">
        <?php /* Hai mắt xích, đúng bản thiết kế: giỏ hàng → thanh toán. Đây
                 vừa là đường lùi vừa là thước đo "còn mấy bước nữa" — khách
                 đang ở giữa một biểu mẫu dài thì cần cả hai. */ ?>
        <nav class="cohead__crumbs" aria-label="Đường dẫn">
            <a href="/gio-hang">Giỏ hàng</a>
            <span class="cohead__sep" aria-hidden="true">/</span>
            <span class="cohead__here" aria-current="page">Thanh toán</span>
        </nav>
        <?php /* NÚT LÙI cạnh tiêu đề, cùng dáng với nút "‹" của hộp thoại mua
                 hàng. Dải mắt xích ngay trên cũng là một đường lùi, nhưng nó
                 LUÔN chỉ về giỏ hàng — mà luồng "Mua ngay" không đi qua giỏ
                 hàng lần nào, nên với họ mắt xích đó dẫn tới một trang lạ.
                 Nút này chỉ về đúng chỗ vừa rời; xem $backUrl ở
                 OrderController::checkout(). */ ?>
        <div class="cohead__row">
            <a class="cohead__back" href="<?= e($backUrl ?? '/gio-hang') ?>" aria-label="Quay lại">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 18l-6-6 6-6"></path>
                </svg>
            </a>
            <h1 class="cohead__title">Hoàn tất đơn hàng</h1>
        </div>
    </div>

    <?php if ($error !== null): ?>
        <p class="cart__flash cart__flash--err" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="cogrid" method="post" action="/thanh-toan/dat">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <?php /* NÚT GỬI MẶC ĐỊNH — vô hình, nhưng phải có.
                 Bấm Enter trong một ô nhập sẽ kích hoạt nút submit ĐẦU TIÊN của
                 form. Các nút chọn mã giảm giá (formaction="/thanh-toan/ma")
                 đứng trước nút "Đặt hàng" trong DOM, nên thiếu nút này thì gõ
                 xong bấm Enter là… áp một mã giảm giá.
                 Không dùng `hidden` hay display:none: nút không được vẽ ra thì
                 trình duyệt bỏ qua nó khi tìm nút gửi mặc định. */ ?>
        <button type="submit" class="cofallback" tabindex="-1" aria-hidden="true">Đặt hàng</button>

        <!-- ══════════ CỘT TRÁI: BA BƯỚC ══════════ -->
        <div class="costeps">

            <!-- ── 1. Người nhận ── -->
            <section class="costep" aria-labelledby="co-b1">
                <div class="costep__head">
                    <span class="costep__num" aria-hidden="true">1</span>
                    <h2 class="costep__title" id="co-b1">Thông tin người nhận</h2>
                </div>

                <div class="cofield__row">
                    <label class="cofield">
                        <span class="cofield__label">Họ và tên *</span>
                        <input class="cofield__input" type="text" name="customer_name" required
                               minlength="2" maxlength="120" autocomplete="name"
                               placeholder="Nguyễn Văn A"
                               value="<?= e($fillCo('customerName', $address['recipient_name'] ?? null, $profile['full_name'] ?? null)) ?>">
                    </label>

                    <label class="cofield">
                        <span class="cofield__label">Số điện thoại *</span>
                        <input class="cofield__input" type="tel" name="customer_phone" required
                               autocomplete="tel" inputmode="tel" placeholder="09xx xxx xxx"
                               value="<?= e($fillCo('customerPhone', $address['phone'] ?? null, $profile['phone'] ?? null)) ?>">
                    </label>
                </div>

                <label class="cofield">
                    <span class="cofield__label">
                        Email <em>(để nhận xác nhận đơn)</em>
                    </span>
                    <input class="cofield__input" type="email" name="customer_email"
                           autocomplete="email" placeholder="ban@email.com"
                           value="<?= e($fill('customerEmail', $profile['email'] ?? null)) ?>">
                </label>
            </section>

            <!-- ── 2. Nhận hàng ── -->
            <section class="costep copick" aria-labelledby="co-b2">
                <div class="costep__head">
                    <span class="costep__num" aria-hidden="true">2</span>
                    <h2 class="costep__title" id="co-b2">Hình thức nhận hàng</h2>
                </div>

                <div class="cocards cocards--2" role="radiogroup" aria-labelledby="co-b2">
                    <?php
                    $methods = [
                        'shipping' => ['Giao tận nơi', 'Toàn quốc · đồng kiểm khi nhận'],
                        'pickup'   => ['Nhận tại cửa hàng', 'Miễn phí · sẵn sàng sau 30–60 phút'],
                    ];
                    foreach ($methods as $value => [$label, $note]):
                    ?>
                        <label class="cocard">
                            <input type="radio" name="delivery_method" value="<?= e($value) ?>"
                                   required <?= $delivery === $value ? 'checked' : '' ?>>
                            <span class="cocard__dot" aria-hidden="true"></span>
                            <span class="cocard__body">
                                <span class="cocard__name"><?= e($label) ?></span>
                                <span class="cocard__note"><?= e($note) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Ẩn/hiện bằng CSS :has() theo ô radio ở trên — không JS.
                     Xem ghi chú đầu file về việc vì sao không ẩn bằng JS. -->
                <div class="copick__ship">
                    <?php
                    /*
                     * Hai ô này được assets/js/address-picker.js nâng thành danh
                     * sách chọn (provinces.open-api.vn) — cùng cụm với sổ địa
                     * chỉ, để hai nơi không sinh ra hai kiểu viết cho cùng một
                     * địa chỉ. Máy chủ vẫn in ra ô gõ tay: thiếu JS hay API chết
                     * thì khách gõ như trước, xem chú thích đầu file JS đó.
                     *
                     * Hai ô mã KHÔNG có `name` nên không gửi lên — đơn hàng chỉ
                     * lưu chữ. Chúng chỉ để cụm chọn dò đúng mục khi điền sẵn từ
                     * địa chỉ mặc định trong sổ.
                     */
                    ?>
                    <div class="cofield__row" data-vnaddr>
                        <label class="cofield">
                            <span class="cofield__label">Tỉnh / Thành phố *</span>
                            <input class="cofield__input" type="text" name="address_city"
                                   maxlength="80" autocomplete="address-level1" placeholder="Hà Nội"
                                   data-vnaddr-field="province"
                                   value="<?= e($cityShown) ?>">
                        </label>

                        <label class="cofield">
                            <span class="cofield__label">Phường / Xã *</span>
                            <input class="cofield__input" type="text" name="address_ward"
                                   maxlength="80" autocomplete="address-level2" placeholder="Phường Tây Hồ"
                                   data-vnaddr-field="ward"
                                   value="<?= e($wardShown) ?>">
                        </label>

                        <input type="hidden" data-vnaddr-code="province" value="<?= e($cityCode) ?>">
                        <input type="hidden" data-vnaddr-code="ward" value="<?= e($wardCode) ?>">
                    </div>

                    <label class="cofield">
                        <span class="cofield__label">Địa chỉ cụ thể *</span>
                        <input class="cofield__input" type="text" name="address_line"
                               maxlength="160" autocomplete="address-line1"
                               placeholder="Số nhà, tên đường…"
                               value="<?= e($fill('addressLine', $address['line1'] ?? null)) ?>">
                    </label>
                </div>

                <div class="copick__store">
                    <span class="cofield__label">Chọn cơ sở</span>

                    <?php if ($stores === []): ?>
                        <p class="copick__none">
                            Hiện chưa có cơ sở nào mở cửa. Vui lòng chọn "Giao tận nơi".
                        </p>
                    <?php else: ?>
                        <div class="cocards" role="radiogroup" aria-label="Chọn cơ sở nhận hàng">
                            <?php foreach ($stores as $i => $st): ?>
                                <label class="cocard">
                                    <!-- Cơ sở đầu tiên được chọn sẵn: bản thiết kế
                                         cũng vẽ vậy, và một danh sách radio không
                                         có lựa chọn nào là bước thừa cho khách. -->
                                    <input type="radio" name="store_id" value="<?= e($st['id']) ?>"
                                           <?= ($storeId !== '' ? $storeId === $st['id'] : $i === 0) ? 'checked' : '' ?>>
                                    <span class="cocard__dot" aria-hidden="true"></span>
                                    <span class="cocard__body">
                                        <span class="cocard__name"><?= e($st['name']) ?></span>
                                        <span class="cocard__note"><?= e($st['address']) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ── 3. Thanh toán ── -->
            <section class="costep" aria-labelledby="co-b3">
                <div class="costep__head">
                    <span class="costep__num" aria-hidden="true">3</span>
                    <h2 class="costep__title" id="co-b3">Phương thức thanh toán</h2>
                </div>

                <div class="cocards" role="radiogroup" aria-labelledby="co-b3">
                    <?php foreach ($payments as $value => $pm): ?>
                        <?php $soon = !empty($pm['soon']); ?>
                        <label class="cocard<?= $soon ? ' is-soon' : '' ?>">
                            <!-- Khoá lại chứ không bỏ đi: bản thiết kế có ba lựa
                                 chọn, mà một cái bấm được rồi không trả tiền được
                                 thì tệ hơn hẳn. place() cũng từ chối giá trị này
                                 nếu ai gửi thẳng lên. -->
                            <input type="radio" name="payment_method" value="<?= e($value) ?>"
                                   required <?= $soon ? 'disabled' : '' ?>
                                   <?= (!$soon && $payment === $value) ? 'checked' : '' ?>>
                            <span class="cocard__dot" aria-hidden="true"></span>
                            <span class="cocard__body">
                                <span class="cocard__name">
                                    <?= e($pm['name']) ?>
                                    <?php
                                    /* NHÃN QUÀ NGAY Ở ĐÂY, không chỉ trong khối
                                       tóm tắt bên phải.

                                       Khối bên phải chỉ hiện SAU KHI khách đã
                                       chọn chuyển khoản — mà mặc định đang là
                                       COD, nên họ không bao giờ thấy lời mời và
                                       cũng chẳng có lý do gì để bấm sang xem.
                                       Con gà và quả trứng.

                                       Đặt ở thẻ phương thức thì lời mời nằm
                                       đúng chỗ khách đang cân nhắc, và hiện với
                                       MỌI khách kể cả người chưa mua bao giờ. */
                                    ?>
                                    <?php if ($value === 'bank_transfer' && $reward !== null): ?>
                                        <span class="cocard__gift">+ Tặng mã giảm giá</span>
                                    <?php endif; ?>
                                </span>
                                <span class="cocard__note">
                                    <?= e($pm['note']) ?>
                                    <?php if ($value === 'bank_transfer' && $reward !== null): ?>
                                        · chuyển đủ 100% được tặng mã
                                        <strong><?= e($reward['code']) ?></strong>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <?php if ($soon): ?>
                                <span class="cocard__soon">Sắp có</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label class="cofield">
                    <span class="cofield__label">
                        Ghi chú <em>(không bắt buộc)</em>
                    </span>
                    <textarea class="cofield__input cofield__area" name="note" rows="3" maxlength="500"
                              placeholder="Ví dụ: giao giờ hành chính, gọi trước khi giao…"><?= e($fill('note')) ?></textarea>
                </label>
            </section>
        </div>

        <!-- ══════════ CỘT PHẢI: TÓM TẮT ══════════ -->
        <aside class="csum" aria-labelledby="co-sum">
            <div class="csum__card">
                <h2 id="co-sum" class="csum__title">Đơn hàng của bạn</h2>

                <div class="coitems">
                    <?php foreach ($lines as $line): ?>
                        <?php
                        $p = $line['product'];
                        $variant = implode(' · ', array_filter([$p['color'] ?? null, $p['material'] ?? null]));
                        ?>
                        <div class="coitem">
                            <span class="coitem__thumb">
                                <img src="<?= e(ProductModel::image($p)) ?>" alt=""
                                     width="60" height="60" loading="lazy" decoding="async">
                            </span>
                            <span class="coitem__body">
                                <span class="coitem__name notranslate" translate="no"><?= e($p['name']) ?></span>
                                <span class="coitem__meta">
                                    <?= $variant !== '' ? e($variant) . ' · ' : '' ?>x<?= (int) $line['quantity'] ?>
                                </span>
                                <?php if ($line['lens'] !== null || $line['rx'] !== null): ?>
                                    <?php /* Tròng cắt kèm + số đo. Đây là màn hình
                                             CUỐI trước khi khách trả tiền, nên nó
                                             phải in đủ những gì sẽ được mài — sai
                                             một con số là một cặp tròng bỏ đi. */ ?>
                                    <?php if ($line['lens'] !== null): ?>
                                        <span class="coitem__lens">
                                            + <?= e($line['lens']['name']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="coitem__rx">
                                        <?= $line['rx'] !== null
                                            ? e($line['rx'])
                                            : 'Đo tại cửa hàng' ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <span class="coitem__price"><?= money($line['lineTotal']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="csum__rule"></div>

                <?php /* ══════ MÃ GIẢM GIÁ ══════
                         Bản thiết kế vẽ một DANH SÁCH THẢ XUỐNG, không phải ô gõ
                         mã như giỏ hàng — khách bấm chọn trong những mã đang chạy.

                         Mở/đóng bằng <details>, không JavaScript. Mỗi mã là một
                         nút submit `formaction` trỏ sang /thanh-toan/ma, kèm
                         `formnovalidate` — không có nó, bấm chọn mã khi chưa điền
                         họ tên sẽ bị trình duyệt chặn lại kèm bong bóng "Vui lòng
                         điền vào trường này", trong khi việc đang làm chẳng liên
                         quan gì tới ô đó. */ ?>
                <div class="covou">
                    <span class="cofield__label" id="co-vou">Mã giảm giá</span>

                    <?php if ($voucher !== null): ?>
                        <div class="covou__on">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M8.5 12.5l2.5 2.5 4.5-5"></path>
                            </svg>
                            <span class="covou__onbody">
                                <span class="covou__oncode"><?= e($voucher['code']) ?></span>
                                <span class="covou__onnote">
                                    <?= e($voucher['title']) ?><?= $discount > 0 ? ' · −' . money($discount) : '' ?>
                                </span>
                            </span>
                            <button type="submit" class="covou__off" name="act" value="go"
                                    formaction="/thanh-toan/ma" formnovalidate
                                    aria-label="Bỏ mã <?= e($voucher['code']) ?>">✕</button>
                        </div>
                    <?php else: ?>
                        <?php
                        /* ─────────────────────────────────────────────────
                           Ô GÕ MÃ LUÔN CÓ, KỂ CẢ KHI DANH SÁCH TRỐNG.

                           Trang này trước đây chỉ có ô CHỌN. Danh sách trống
                           thì nó nói "chưa có mã nào đang chạy" rồi hết —
                           khách cầm một mã trên tay (nhận qua Zalo, in trên
                           phiếu bảo hành, bạn bè cho) không có chỗ nào để gõ,
                           trong khi trang giỏ hàng thì có. Hai màn của cùng
                           một luồng mà làm được hai việc khác nhau.

                           Gửi tới ĐÚNG địa chỉ mà các nút chọn mã đang dùng
                           (/thanh-toan/ma, ô `code`), nên không có thêm nhánh
                           xử lý nào ở phía máy chủ — chỉ là một đường vào nữa
                           cho cùng một việc.

                           formnovalidate như các nút chọn mã: form bọc ngoài
                           là form ĐẶT HÀNG, và bấm "Áp dụng" khi chưa điền họ
                           tên không được phép biến thành bong bóng "Vui lòng
                           điền vào trường này" ở một ô chẳng liên quan.
                           ───────────────────────────────────────────────── */
                        ?>
                        <div class="covou__type">
                            <label class="sr-only" for="co-ma">Mã giảm giá</label>
                            <input class="covou__input" type="text" id="co-ma" name="code"
                                   maxlength="40" autocomplete="off" spellcheck="false"
                                   placeholder="Nhập mã giảm giá">
                            <button type="submit" class="covou__apply"
                                    formaction="/thanh-toan/ma" formnovalidate>Áp dụng</button>
                        </div>

                        <?php if ($vouchers === []): ?>
                            <p class="covou__none">Hiện chưa có mã nào đang chạy để chọn sẵn.</p>
                        <?php else: ?>
                        <details class="covou__pick">
                            <summary class="covou__toggle" aria-describedby="co-vou">
                                Chọn mã giảm giá…
                                <svg class="covou__chev" width="13" height="13" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <path d="M6 9l6 6 6-6"></path>
                                </svg>
                            </summary>

                            <div class="covou__menu">
                                <?php foreach ($vouchers as $v): ?>
                                    <?php
                                    /* Số tiền giảm tính TẠI ĐÂY, cho đúng đơn này: cùng một
                                       mã "giảm 10%" cho hai con số khác nhau ở hai đơn khác
                                       nhau, và mã miễn ship không giảm được gì khi phí ship
                                       đã bằng 0 (nhận tại cửa hàng, hoặc đơn đã vượt ngưỡng). */
                                    $calc = VoucherModel::apply($v, $subtotal, $shippingFee);
                                    $cut  = $calc['freeShipping'] ? $shippingFee : $calc['discount'];
                                    /* Chưa đủ điều kiện thì khoá lại chứ không giấu đi: dòng
                                       "Đơn tối thiểu …" là lời mời mua thêm. Bấm được rồi bị
                                       server từ chối mới là thứ nên tránh. */
                                    $ok = $subtotal >= (int) $v['min_order'];
                                    ?>
                                    <button type="submit" class="covou__item<?= $ok ? '' : ' is-off' ?>"
                                            name="code" value="<?= e($v['code']) ?>"
                                            formaction="/thanh-toan/ma" formnovalidate
                                            <?= $ok ? '' : 'disabled' ?>>
                                        <span class="covou__itembody">
                                            <span class="covou__code"><?= e($v['code']) ?></span>
                                            <span class="covou__desc"><?= e($v['title']) ?></span>
                                            <?php if ((int) $v['min_order'] > 0): ?>
                                                <span class="covou__min">
                                                    Đơn tối thiểu <?= money((int) $v['min_order']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="covou__cut">
                                            <?= $cut > 0 ? '−' . money($cut) : '—' ?>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($voucherMsg !== null): ?>
                        <span class="covou__msg<?= $voucherOk ? ' is-ok' : ' is-err' ?>"
                              role="<?= $voucherOk ? 'status' : 'alert' ?>"><?= e($voucherMsg) ?></span>
                    <?php endif; ?>
                </div>

                <div class="csum__rule"></div>

                <div class="csum__row">
                    <span>Tạm tính</span>
                    <span class="csum__val"><?= money($subtotal) ?></span>
                </div>

                <?php if ($voucher !== null): ?>
                    <!-- Chỉ hiện khi có mã, nhưng BẮT BUỘC hiện khi có: thiếu
                         dòng này thì tạm tính + phí giao hàng không ra tổng cộng. -->
                    <div class="csum__row">
                        <span>Giảm giá (<?= e($voucher['code']) ?>)</span>
                        <span class="csum__val csum__val--cut">−<?= money($discount) ?></span>
                    </div>
                <?php endif; ?>

                <div class="csum__row">
                    <span>Phí giao hàng</span>
                    <span class="csum__val<?= $shippingFee === 0 ? ' csum__val--free' : '' ?>">
                        <?= $shippingFee > 0 ? money($shippingFee) : 'Miễn phí' ?>
                    </span>
                </div>

                <div class="csum__rule"></div>

                <div class="csum__grand">
                    <span>Tổng cộng</span>
                    <span class="csum__grand-num"><?= money($total) ?></span>
                </div>

                <?php
                /* ══════════ ĐẶT CỌC ══════════
                   Hai khối, hiện đúng một — theo phương thức thanh toán đang
                   chọn. Trang này không tải lại khi khách đổi ô radio nên máy
                   chủ in cả hai, assets/js/checkout-deposit.js đổi khối nào
                   hiện. Không có JS thì khối đúng với phương thức ĐANG chọn
                   lúc vẽ trang vẫn hiện, và máy chủ mới là nơi chốt số tiền.

                   HAI KHỐI CÓ PHẠM VI KHÁC NHAU, đây là chỗ dễ đọc nhầm nhất:

                     COD          chỉ hiện khi đơn CÓ MÀI TRÒNG. Gọng trần trả
                                  hết khi nhận như mọi shop khác.
                     chuyển khoản hiện với MỌI đơn — khách tự chọn cọc 30% hay
                                  chuyển đủ, kể cả đơn chỉ mua gọng.

                   Cọc tính trên TỔNG (đã trừ mã giảm giá, đã cộng phí ship),
                   nên cọc + còn lại luôn đúng bằng dòng "Tổng cộng" ngay trên
                   — khách tự kiểm được, không cần tin. */
                $deposit = OrderModel::depositFor($total, $depositRate);
                ?>

                <?php if ($needsDeposit): ?>
                    <div class="csum__deposit" data-deposit-block="cod"
                         <?= $payment === 'cod' ? '' : 'hidden' ?>>
                        <p class="csum__deposit-why">
                            Đơn có cắt tròng theo độ nên cần đặt cọc trước
                            <strong><?= (int) $depositRate ?>%</strong>. Cửa hàng bắt đầu
                            mài tròng ngay khi nhận được cọc.
                        </p>

                        <div class="csum__row csum__row--deposit">
                            <span>Đặt cọc trước (<?= (int) $depositRate ?>%)</span>
                            <span class="csum__val csum__val--deposit"><?= money($deposit) ?></span>
                        </div>

                        <div class="csum__row">
                            <span>Còn lại khi nhận hàng</span>
                            <span class="csum__val"><?= money($total - $deposit) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                /* Hai ô radio THẬT, không phải hai nút <button>: bàn phím và
                   trình đọc màn hình phải biết cái nào đang chọn, và giá trị
                   phải đi kèm form đặt hàng.

                   Số tiền in thẳng vào từng lựa chọn. "Cọc 30%" đứng một mình
                   không trả lời được câu khách đang hỏi — rốt cuộc tôi phải
                   chuyển bao nhiêu — mà đó mới là thứ họ cần để chọn. */
                ?>
                <div class="csum__deposit" data-deposit-block="bank_transfer"
                     <?= $payment === 'bank_transfer' ? '' : 'hidden' ?>>

                    <?php if (!$needsDeposit): ?>
                        <?php
                        /* GỌNG TRẦN KHÔNG CÓ LỰA CHỌN CỌC. Tiền cọc sinh ra cho
                           tròng mài theo số đo — thứ khách đổi ý thì cửa hàng
                           không bán lại được. Gọng có sẵn thì không có rủi ro
                           đó, nên đơn này chỉ hai đường: COD, hoặc chuyển đủ.
                           Xem OrderModel::place(). */
                        ?>
                        <p class="csum__deposit-why">
                            Chuyển khoản thì thanh toán <strong>đủ 100%</strong>.
                            <?php if ($reward !== null): ?>
                                Được tặng mã <strong><?= e($reward['code']) ?></strong>
                                cho lần mua sau — <?= e($reward['condition_text'] ?: $reward['title']) ?>.
                            <?php else: ?>
                                Nhận hàng không phải trả thêm.
                            <?php endif; ?>
                        </p>

                        <div class="csum__row csum__row--deposit">
                            <span>Cần chuyển</span>
                            <span class="csum__val csum__val--deposit"><?= money($total) ?></span>
                        </div>
                    <?php else: ?>
                    <p class="csum__deposit-why">Chọn số tiền chuyển khoản lần này:</p>

                    <div class="ckopt" role="radiogroup" aria-label="Số tiền chuyển khoản">
                        <label class="ckopt__item">
                            <input type="radio" name="bank_amount" value="full"
                                   <?= $bankAmount === 'full' ? 'checked' : '' ?>>
                            <span class="ckopt__dot" aria-hidden="true"></span>
                            <span class="ckopt__body">
                                <span class="ckopt__top">
                                    <span class="ckopt__name">
                                        Chuyển đủ 100%
                                        <?php if ($reward !== null): ?>
                                            <?php /* NHÃN QUÀ nằm ngay cạnh tên lựa chọn, không
                                                     chỉ trong dòng ghi chú. Ghi chú là chữ nhỏ
                                                     màu nhạt — mắt lướt qua khi đang so hai con
                                                     số. Đây là LÝ DO để chọn vế này, nên nó phải
                                                     nằm ở tầng khách đọc trước. */ ?>
                                            <span class="ckopt__gift">+ Tặng mã giảm giá</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="ckopt__num"><?= money($total) ?></span>
                                </span>
                                <span class="ckopt__note">
                                    <?php if ($reward !== null): ?>
                                        <?php /* Nói RÕ mã gì và giảm bao nhiêu. "Tặng mã giảm
                                                 giá" đứng một mình là một lời hứa mơ hồ, mà
                                                 khách đang phải quyết định trả gấp ba lần số
                                                 tiền của vế kia. */ ?>
                                        Mã <strong><?= e($reward['code']) ?></strong> dùng cho lần
                                        mua sau — <?= e($reward['condition_text'] ?: $reward['title']) ?>.
                                    <?php else: ?>
                                        <?php /* KHÔNG hứa quà khi cửa hàng chưa bật mã nào. Hứa
                                                 rồi không có là mất lòng tin đắt hơn nhiều so
                                                 với việc thiếu một dòng quảng cáo. */ ?>
                                        Trả một lần, nhận hàng không phải trả thêm.
                                    <?php endif; ?>
                                </span>
                            </span>
                        </label>

                        <label class="ckopt__item">
                            <input type="radio" name="bank_amount" value="deposit"
                                   <?= $bankAmount === 'deposit' ? 'checked' : '' ?>>
                            <span class="ckopt__dot" aria-hidden="true"></span>
                            <span class="ckopt__body">
                                <span class="ckopt__top">
                                    <span class="ckopt__name">Đặt cọc <?= (int) $depositRate ?>%</span>
                                    <span class="ckopt__num"><?= money($deposit) ?></span>
                                </span>
                                <span class="ckopt__note">
                                    Còn <?= money($total - $deposit) ?> trả khi nhận hàng.
                                </span>
                            </span>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>

                <?php /* Nhãn đổi theo hình thức thanh toán, đúng bản thiết kế: đơn
                         COD dừng ở "Đặt hàng", đơn chuyển khoản còn một bước quét
                         mã QR nữa nên nói trước điều đó.

                         Đọc $payment (giá trị đang chọn lúc VẼ trang) chứ không
                         theo dõi ô radio: đổi nhãn theo thời gian thực cần JS, mà
                         trang này cố ý không có. Nhãn sai một nhịp thì cùng lắm là
                         thừa hai chữ — bước QR vẫn hiện đúng sau khi đặt. */ ?>
                <?php
                /* Nhãn nút: "Đặt hàng" trơn CHỈ khi sau đó không còn bước trả
                   tiền nào — tức COD và đơn không phải cọc. Mọi ca còn lại
                   (chuyển khoản bất kỳ, hoặc COD đơn cắt tròng phải cọc) đều
                   đi tiếp qua màn QR, nên nhãn phải nói trước điều đó.

                   data-cta-* để checkout-deposit.js đổi nhãn khi khách bấm ô
                   radio khác. Không có JS thì nhãn đúng với phương thức ĐANG
                   chọn lúc vẽ trang, sai một nhịp nếu khách đổi ý — cùng lắm
                   thừa hai chữ, và bước QR vẫn hiện đúng sau khi đặt. */
                ?>
                <button type="submit" class="csum__cta csum__cta--btn"
                        data-cta
                        data-cta-plain="Đặt hàng"
                        data-cta-pay="Đặt hàng &amp; Thanh toán">
                    <?= $payment === 'cod' && !$needsDeposit
                        ? 'Đặt hàng' : 'Đặt hàng &amp; Thanh toán' ?>
                </button>
                <a class="csum__more" href="/gio-hang">← Quay lại giỏ hàng</a>
            </div>

            <ul class="cotrust" role="list">
                <li>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Bảo hành gọng 12 tháng, tròng 90 ngày</span>
                </li>
                <li>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                        <path d="M3 3v5h5"></path>
                    </svg>
                    <span>Đổi trả trong 7 ngày nếu lỗi nhà sản xuất</span>
                </li>
                <li>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.13.96.36 1.9.7 2.8a2 2 0 0 1-.45 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.27a2 2 0 0 1 2.1-.45c.9.34 1.84.57 2.8.7a2 2 0 0 1 1.7 2z"></path>
                    </svg>
                    <span>Hỗ trợ <?= e(config('company.hotline')) ?> · 8:30 – 21:30 cả tuần</span>
                </li>
            </ul>
        </aside>
    </form>
</section>
