<?php

/**
 * order/success.php — xác nhận đặt hàng thành công
 *
 * Chỉ tới được sau khi vừa đặt hàng (OrderController::success đọc flash rồi
 * xoá). Tải lại trang sẽ bị đẩy về /san-pham — mã đơn không nằm trên URL nên
 * không ai dò được đơn của người khác.
 */

$deliveryLabels = [
    'pickup'   => 'Nhận tại cửa hàng',
    'shipping' => 'Giao tận nơi',
];

$paymentLabels = [
    'cod'           => 'Thanh toán khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
];

/*
 * KHÔNG đầu trang, KHÔNG breadcrumb.
 *
 * Đây là điểm cuối của luồng thanh toán. Trang không tới được bằng cách duyệt
 * (chỉ tới sau khi vừa đặt hàng, tải lại là bị đẩy về /san-pham), nên một
 * đường dẫn "Trang chủ / Đặt hàng thành công" chẳng định vị cho ai và cũng
 * không có gì để Google lập chỉ mục.
 *
 * <h1> của trang là dòng trong phiếu xác nhận màu xanh ngay dưới đây — thứ
 * người ta thật sự đang tìm khi mở trang này.
 */
?>

<section class="osuccess">
    <div class="osuccess__inner">

        <?php if ($order === null): ?>
            <p class="alert alert--err">Không tìm thấy đơn hàng.</p>
        <?php else: ?>

            <div class="bdone" role="status">
                <?= icon('check', 'bdone__ico', 32) ?>
                <h1 class="bdone__title">Đơn hàng đã được ghi nhận</h1>
                <p class="bdone__code">Mã đơn: <strong><?= e($order['code']) ?></strong></p>
                <p class="bdone__note">
                    Chúng tôi sẽ gọi xác nhận trong vòng 30 phút (giờ làm việc).
                    Giữ lại mã đơn để tra cứu khi cần.
                </p>
            </div>

            <div class="osummary">
                <dl class="edetail__facts">
                    <div>
                        <dt>Người nhận</dt>
                        <dd><?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?></dd>
                    </div>
                    <div>
                        <dt>Hình thức nhận</dt>
                        <dd>
                            <?= e($deliveryLabels[$order['delivery_method']] ?? $order['delivery_method']) ?>
                            <?php if (!empty($order['store_name'])): ?>
                                <!-- Khách nhận tại cửa hàng cần biết tới ĐÂU lấy hàng.
                                     Trang này là thứ họ chụp màn hình lại. -->
                                <br><strong><?= e($order['store_name']) ?></strong>
                                <?php if (!empty($order['store_address'])): ?>
                                    <br><?= e($order['store_address']) ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Thanh toán</dt>
                        <dd><?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?></dd>
                    </div>
                </dl>

                <?php if (!empty($order['shipping_address'])): ?>
                    <p class="osummary__addr">
                        <?= icon('map-pin', '', 16) ?> <?= e($order['shipping_address']) ?>
                    </p>
                <?php endif; ?>

                <table class="otable">
                    <caption class="sr-only">Chi tiết đơn hàng <?= e($order['code']) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">Sản phẩm</th>
                            <th scope="col">SL</th>
                            <th scope="col">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['product_name']) ?></td>
                                <td><?= (int) $item['quantity'] ?></td>
                                <td><?= money((int) $item['line_total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="2">Tạm tính</th>
                            <td><?= money((int) $order['subtotal']) ?></td>
                        </tr>
                        <?php if ((int) $order['discount'] > 0): ?>
                            <!-- Chỉ hiện khi có giảm giá, nhưng BẮT BUỘC phải hiện khi có:
                                 thiếu dòng này thì tạm tính + phí giao hàng không ra tổng cộng,
                                 và hoá đơn trông như tính sai. -->
                            <tr>
                                <th scope="row" colspan="2">Giảm giá</th>
                                <td>−<?= money((int) $order['discount']) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th scope="row" colspan="2">Phí giao hàng</th>
                            <td><?= (int) $order['shipping_fee'] > 0 ? money((int) $order['shipping_fee']) : 'Miễn phí' ?></td>
                        </tr>
                        <tr class="otable__total">
                            <th scope="row" colspan="2">Tổng cộng</th>
                            <td><?= money((int) $order['total']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="edetail__cta">
                <a href="/san-pham" class="btn-primary btn-inline btn-lg">Tiếp tục mua sắm</a>
                <a href="<?= e($company['hotline_href']) ?>" class="btn-outline btn-inline btn-lg">
                    Gọi <?= e($company['hotline']) ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
