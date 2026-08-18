<?php

/**
 * order/success.php — xác nhận đặt hàng thành công (/thanh-toan/hoan-tat)
 *
 * Dựng theo "Vin Eyewear Order Complete.dc.html" (Claude Design), nhưng lấy MÀU
 * và ĐỘ BO của trang chủ — xem khối chú thích đầu assets/css/order-complete.css.
 *
 * Chỉ tới được sau khi vừa đặt hàng (OrderController::success đọc flash rồi
 * xoá). Tải lại trang sẽ bị đẩy về /san-pham — mã đơn không nằm trên URL nên
 * không ai dò được đơn của người khác.
 *
 * Mua hàng BẮT BUỘC đăng nhập (OrderController::requireCustomer), nên đơn ở đây
 * luôn có chủ và luôn xem lại được trong "Đơn hàng của tôi".
 *
 * KHÔNG tự chuyển trang. Trang này là hoá đơn khách đang đọc — mã đơn, địa chỉ
 * cơ sở tới lấy hàng, số tiền phải chuẩn bị — và thường là đang chụp màn hình.
 * Hai nút ở cuối để khách tự chọn: sang xem đơn, hay đi mua tiếp.
 *
 * KHÔNG đầu trang, KHÔNG breadcrumb: đây là điểm cuối của luồng thanh toán,
 * không tới được bằng cách duyệt và không có gì để Google lập chỉ mục. <h1> của
 * trang là câu cảm ơn trong cụm mừng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CHỖ KHÁC BẢN THIẾT KẾ — VÀ VÌ SAO
 *
 * 1. Ô "THÔNG TIN GIAO HÀNG" đổi theo hình thức nhận. Bản thiết kế viết cứng cho
 *    đơn giao tận nơi + COD: địa chỉ khách, "Dự kiến 2–4 ngày làm việc", "Chuẩn
 *    bị đủ … khi nhận". Đơn NHẬN TẠI CỬA HÀNG thì địa chỉ cần in là địa chỉ CƠ
 *    SỞ (khách tới lấy hàng cần biết tới đâu — trang này là thứ họ chụp màn hình
 *    lại) và không có ngày giao nào; đơn chuyển khoản thì không phải chuẩn bị
 *    tiền mặt.
 *
 * 2. DÒNG "GIẢM GIÁ" trong cụm cộng tiền. Bản thiết kế chỉ có tạm tính + phí
 *    giao hàng + tổng cộng vì đơn mẫu không có mã giảm giá. Thiếu dòng này thì
 *    hai số trên không cộng ra số dưới, và hoá đơn trông như tính sai.
 *
 * 3. "TẠM TÍNH" hiện thêm khi đơn CÓ GIẢM GIÁ, không chỉ khi đơn nhiều dòng
 *    hàng như bản thiết kế ghi. Ẩn nó đi vì trùng số với dòng hàng duy nhất là
 *    đúng, nhưng đơn một món có giảm giá mà ẩn thì thành "Giảm giá −50.000₫"
 *    treo lơ lửng, không nói trừ vào đâu.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$deliveryLabels = [
    'pickup'   => 'Nhận tại cửa hàng',
    'shipping' => 'Giao tận nơi',
];

$paymentLabels = [
    'cod'           => 'Thanh toán khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
];

/* Đường dẫn tới đúng đơn vừa đặt trong trang tài khoản: ?don= mở sẵn phần chi
   tiết, #<mã> cuộn tới thẻ đó — xem app/views/auth/account/don-hang.php. */
$mineHref = $order !== null
    ? '/tai-khoan?muc=don-hang&don=' . rawurlencode($order['code']) . '#' . $order['code']
    : '/tai-khoan?muc=don-hang';
?>

<section class="ocomp">

    <?php if ($order === null): ?>
        <p class="alert alert--err">Không tìm thấy đơn hàng.</p>
    <?php else: ?>

        <?php
        $isPickup = $order['delivery_method'] === 'pickup';
        $delivery = $deliveryLabels[$order['delivery_method']] ?? $order['delivery_method'];
        $payment  = $paymentLabels[$order['payment_method']] ?? $order['payment_method'];

        // Số lượng SẢN PHẨM, không phải số dòng hàng: đơn một dòng ×3 là ba
        // chiếc kính, và "1 sản phẩm" ở đầu thẻ sẽ nói ngược lại cái ×3 ngay
        // bên dưới.
        $units = array_sum(array_map(static fn ($i) => (int) $i['quantity'], $items));
        ?>

        <!-- ══════════ CỤM MỪNG ══════════ -->
        <div class="ocomp__hero">
            <span class="ocomp__seal" aria-hidden="true"><?= icon('check', '', 32) ?></span>

            <h1 class="ocomp__title">Cảm ơn bạn. Đơn hàng đã được ghi nhận.</h1>

            <p class="ocomp__code">
                <span class="ocomp__codelabel">Mã đơn</span>
                <span class="ocomp__codeval"><?= e($order['code']) ?></span>

                <!-- Nút sao chép và chữ "Đã chép" đều ẩn sẵn: cả hai chỉ có
                     nghĩa khi order-success.js chạy được. -->
                <button type="button" class="ocomp__copy" data-copy="<?= e($order['code']) ?>"
                        title="Sao chép mã đơn" aria-label="Sao chép mã đơn" hidden>
                    <?= icon('copy', '', 15) ?>
                </button>
                <span class="ocomp__copied" role="status" hidden>Đã chép</span>
            </p>

            <p class="ocomp__lead">
                Chúng tôi sẽ gọi xác nhận trong vòng 30 phút (giờ làm việc).
                Giữ lại mã đơn để tra cứu khi cần.
            </p>

        </div>

        <!-- ══════════ HAI Ô THÔNG TIN ══════════ -->
        <div class="ocomp__card ocomp__facts">
            <div class="ocomp__fact">
                <span class="ocomp__label">
                    <?= $isPickup ? 'Thông tin nhận hàng' : 'Thông tin giao hàng' ?>
                </span>
                <span class="ocomp__factval">
                    <?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?>
                </span>

                <p class="ocomp__addr">
                    <?php if ($isPickup): ?>
                        <strong><?= e($order['store_name'] ?? 'Cơ sở Vin Eyewear') ?></strong>
                        <?php if (!empty($order['store_address'])): ?>
                            <br><?= e($order['store_address']) ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?= e($order['shipping_address'] ?: 'Chưa có địa chỉ nhận hàng') ?>
                    <?php endif; ?>
                </p>

                <p class="ocomp__how">
                    <?= e($delivery) ?> ·
                    <?= $isPickup ? 'Cửa hàng nhắn khi hàng đã sẵn' : 'Dự kiến 2–4 ngày làm việc' ?>
                </p>
            </div>

            <div class="ocomp__fact">
                <span class="ocomp__label">Thanh toán</span>
                <span class="ocomp__factval"><?= e($payment) ?></span>

                <?php if ($order['payment_method'] === 'cod'): ?>
                    <p class="ocomp__factsub">
                        Chuẩn bị đủ <?= money((int) $order['total']) ?> khi nhận
                    </p>
                <?php elseif (empty($bank['number'])): ?>
                    <!-- Chưa cấu hình tài khoản nhận tiền (config/company.php).
                         Thà nói "sẽ gọi" còn hơn in một số tài khoản không có. -->
                    <p class="ocomp__factsub">
                        Nhân viên sẽ gọi và đọc thông tin chuyển khoản
                    </p>
                <?php else: ?>
                    <?php
                    /* IN THẲNG SỐ TÀI KHOẢN, không hứa "gửi sau khi xác nhận".
                       Khách đặt đơn lúc 11 giờ đêm vẫn chuyển được ngay, và đơn
                       về tiền sớm hơn một cuộc gọi.

                       NỘI DUNG CHUYỂN KHOẢN = MÃ ĐƠN. Đây là khoá đối chiếu: nhân
                       viên đọc sao kê là ra đúng đơn, và cổng thanh toán sau này
                       cũng tự khớp bằng đúng chuỗi đó. */
                    ?>
                    <dl class="ocomp__bank">
                        <div>
                            <dt>Ngân hàng</dt>
                            <dd><?= e($bank['name']) ?></dd>
                        </div>
                        <div>
                            <dt>Số tài khoản</dt>
                            <dd><strong><?= e($bank['number']) ?></strong></dd>
                        </div>
                        <div>
                            <dt>Chủ tài khoản</dt>
                            <dd><?= e($bank['holder']) ?></dd>
                        </div>
                        <div>
                            <dt>Số tiền</dt>
                            <dd><strong><?= money((int) $order['total']) ?></strong></dd>
                        </div>
                        <div>
                            <dt>Nội dung</dt>
                            <dd><strong><?= e($order['code']) ?></strong></dd>
                        </div>
                    </dl>
                    <p class="ocomp__factsub">
                        Ghi đúng mã đơn ở phần nội dung để chúng tôi đối chiếu được.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ══════════ THẺ ĐƠN HÀNG ══════════ -->
        <div class="ocomp__card ocomp__card--order">
            <div class="ocomp__orderhead">
                <h2 class="ocomp__ordertitle">Đơn hàng của bạn</h2>
                <span class="ocomp__ordercount"><?= (int) $units ?> sản phẩm</span>
            </div>

            <ul class="ocomp__items">
                <?php foreach ($items as $item): ?>
                    <?php
                    /* images là JSON, phần tử đầu là ảnh đại diện. Sản phẩm đã bị
                       gỡ khỏi cửa hàng thì product_id là NULL và không có ảnh —
                       dòng hàng vẫn dựng, ô ảnh về hình gọng kính như bản thiết
                       kế vẽ cho ô trống. */
                    $pics = !empty($item['images']) ? json_decode($item['images'], true) : null;
                    $pic  = is_array($pics) ? ($pics[0] ?? null) : null;
                    ?>
                    <li class="ocomp__item">
                        <div class="ocomp__itemmain">
                            <span class="ocomp__thumb">
                                <?php if ($pic !== null): ?>
                                    <img src="<?= e(asset($pic)) ?>" alt="" width="64" height="64" loading="lazy">
                                <?php else: ?>
                                    <?= icon('glasses', '', 30) ?>
                                <?php endif; ?>
                            </span>
                            <div>
                                <span class="ocomp__itemname"><?= e($item['product_name']) ?></span>
                                <?php if (!empty($item['lens_name']) || !empty($item['prescription'])): ?>
                                    <?php /* Tròng cắt kèm + số đo. Trang này là thứ
                                             khách CHỤP MÀN HÌNH lại, và là thứ nhân
                                             viên đối chiếu khi giao — số đo phải có
                                             mặt ở đây, không chỉ trong khu quản trị. */ ?>
                                    <?php if (!empty($item['lens_name'])): ?>
                                        <p class="ocomp__itemlens">
                                            + <?= e($item['lens_name']) ?>
                                            <?php if ((int) $item['lens_price'] > 0): ?>
                                                (<?= money((int) $item['lens_price']) ?>)
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="ocomp__itemrx">
                                        Số đo: <?= $item['prescription'] !== null
                                            ? e($item['prescription'])
                                            : 'đo tại cửa hàng' ?>
                                    </p>
                                <?php endif; ?>
                                <p class="ocomp__itemqty">Số lượng: <?= (int) $item['quantity'] ?></p>
                            </div>
                        </div>
                        <span class="ocomp__itemsum"><?= money((int) $item['line_total']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="ocomp__sums">
                <?php
                /* Đơn một dòng hàng, không giảm giá: "Tạm tính" trùng đúng con số
                   vừa in ở dòng hàng ngay trên, nên bản thiết kế ẩn nó đi. Có
                   giảm giá thì phải hiện lại — xem ghi chú 4 ở đầu file. */
                $showSubtotal = count($items) > 1 || (int) $order['discount'] > 0;
                ?>
                <?php if ($showSubtotal): ?>
                    <div class="ocomp__sum">
                        <span>Tạm tính</span>
                        <span><?= money((int) $order['subtotal']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ((int) $order['discount'] > 0): ?>
                    <div class="ocomp__sum">
                        <span>Giảm giá</span>
                        <span>−<?= money((int) $order['discount']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="ocomp__sum">
                    <span>Phí giao hàng</span>
                    <span>
                        <?= (int) $order['shipping_fee'] > 0
                            ? money((int) $order['shipping_fee']) : 'Miễn phí' ?>
                    </span>
                </div>

                <div class="ocomp__sum ocomp__sum--grand">
                    <span class="ocomp__grandlabel">Tổng cộng</span>
                    <span class="ocomp__grandval"><?= money((int) $order['total']) ?></span>
                </div>
            </div>
        </div>

        <!-- ══════════ HÀNG NÚT ══════════ -->
        <div class="ocomp__acts">
            <!-- ?don=<mã> mở sẵn phần chi tiết của đúng đơn vừa đặt -->
            <a href="<?= e($mineHref) ?>" class="btn-primary btn-inline btn-lg">
                Xem đơn hàng của tôi
            </a>
            <a href="/san-pham" class="btn-outline btn-inline btn-lg">Tiếp tục mua sắm</a>
        </div>
    <?php endif; ?>
</section>
