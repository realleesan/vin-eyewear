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
 *   3. $profile   hồ sơ tài khoản — chỉ còn lo ô email, vì sổ địa chỉ không
 *                 giữ email.
 *
 * Vì sao sổ địa chỉ đứng trên hồ sơ ở hai ô tên và điện thoại: xem ghi chú ở
 * OrderController::checkout().
 */
$fill = static fn (string $key, ?string $fallback = null): string =>
    (string) ($old[$key] ?? $fallback ?? '');

$address = $address ?? null;

/* Điền sẵn từ địa chỉ mặc định trong sổ. Trước đây sổ gộp phường/xã và
   tỉnh/thành vào một cột nên chỗ này phải đoán ngược bằng splitArea(); nay sổ
   lưu hai cột riêng (xem migration 2026-08-19-so-dia-chi-tach-phuong-tinh)
   nên lấy thẳng, không còn phép đoán nào. */
$addressWard = (string) ($address['ward_name'] ?? '');
$addressCity = (string) ($address['province_name'] ?? '');

$delivery = $old['deliveryMethod'] ?? 'shipping';
$payment  = $old['paymentMethod'] ?? 'cod';
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
        <h1 class="cohead__title">Hoàn tất đơn hàng</h1>
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
                               value="<?= e($fill('customerName', $address['recipient_name'] ?? $profile['full_name'] ?? null)) ?>">
                    </label>

                    <label class="cofield">
                        <span class="cofield__label">Số điện thoại *</span>
                        <input class="cofield__input" type="tel" name="customer_phone" required
                               autocomplete="tel" inputmode="tel" placeholder="09xx xxx xxx"
                               value="<?= e($fill('customerPhone', $address['phone'] ?? $profile['phone'] ?? null)) ?>">
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
                    <div class="cofield__row">
                        <label class="cofield">
                            <span class="cofield__label">Tỉnh / Thành phố *</span>
                            <input class="cofield__input" type="text" name="address_city"
                                   maxlength="80" autocomplete="address-level1" placeholder="Hà Nội"
                                   value="<?= e($fill('addressCity', $addressCity)) ?>">
                        </label>

                        <label class="cofield">
                            <span class="cofield__label">Phường / Xã *</span>
                            <input class="cofield__input" type="text" name="address_ward"
                                   maxlength="80" autocomplete="address-level2" placeholder="Phường Tây Hồ"
                                   value="<?= e($fill('addressWard', $addressWard)) ?>">
                        </label>
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
                                <span class="cocard__name"><?= e($pm['name']) ?></span>
                                <span class="cocard__note"><?= e($pm['note']) ?></span>
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
                                <span class="coitem__name"><?= e($p['name']) ?></span>
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
                    <?php elseif ($vouchers === []): ?>
                        <p class="covou__none">Hiện chưa có mã giảm giá nào đang chạy.</p>
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

                <?php /* Nhãn đổi theo hình thức thanh toán, đúng bản thiết kế: đơn
                         COD dừng ở "Đặt hàng", đơn chuyển khoản còn một bước quét
                         mã QR nữa nên nói trước điều đó.

                         Đọc $payment (giá trị đang chọn lúc VẼ trang) chứ không
                         theo dõi ô radio: đổi nhãn theo thời gian thực cần JS, mà
                         trang này cố ý không có. Nhãn sai một nhịp thì cùng lắm là
                         thừa hai chữ — bước QR vẫn hiện đúng sau khi đặt. */ ?>
                <button type="submit" class="csum__cta csum__cta--btn">
                    <?= $payment === 'cod' ? 'Đặt hàng' : 'Đặt hàng &amp; Thanh toán' ?>
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
