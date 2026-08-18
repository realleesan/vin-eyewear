<?php

/**
 * order/transfer.php — thanh toán QR (/thanh-toan/chuyen-khoan)
 *
 * Màn THỨ HAI của "Vin Eyewear Checkout.dc.html" (Claude Design):
 *
 *   khung rút gọn (logo + "Thanh toán an toàn")
 *   → nút quay lại + tiêu đề + mã đơn
 *   → hai cột: thẻ QR 400px | tóm tắt đơn + nút xác nhận
 *
 * CSS: assets/css/checkout.css (bộ lớp .coqr*)
 *
 * Chỉ tới được ngay sau khi vừa đặt một đơn CHUYỂN KHOẢN — mã đơn đọc từ flash
 * chứ không nằm trên URL, nên không ai dò được đơn của người khác. Xem
 * OrderController::transfer().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÃ QR LÀ THẬT, KHÔNG PHẢI HÌNH TRANG TRÍ
 *
 * Bản thiết kế vẽ một ô vuông QR bằng SVG — nó chỉ là hình minh hoạ, quét
 * không ra gì. Ở đây ảnh dựng từ dịch vụ VietQR theo đúng bốn thứ trong
 * config/company.php + đơn hàng này: mã ngân hàng, số tài khoản, SỐ TIỀN và
 * NỘI DUNG. Khách quét là app ngân hàng điền sẵn cả bốn.
 *
 * Nội dung chuyển khoản LUÔN là mã đơn — đó là khoá đối chiếu sao kê, xem ghi
 * chú ở khối 'bank' trong config/company.php.
 *
 * Chưa cấu hình `bin` hoặc `number` thì khối QR tự nói "chưa có mã" và khách
 * vẫn thấy đủ số tài khoản bên dưới để chuyển tay. Thà vậy còn hơn hiện một mã
 * QR trỏ sai nơi nhận tiền.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$total = (int) $order['total'];

/*
 * Ảnh QR. `qr_only` là bản chỉ có ô mã, không kèm khung logo ngân hàng — khung
 * 210px của bản thiết kế đã có viền riêng, chồng thêm khung nữa là mã bị bóp
 * nhỏ lại và điện thoại khó bắt.
 */
$qrSrc = !empty($bank['bin']) && !empty($bank['number'])
    ? sprintf(
        'https://img.vietqr.io/image/%s-%s-qr_only.png?amount=%d&addInfo=%s&accountName=%s',
        rawurlencode((string) $bank['bin']),
        rawurlencode((string) $bank['number']),
        $total,
        rawurlencode($order['code']),
        rawurlencode((string) ($bank['holder'] ?? ''))
    )
    : null;
?>

<section class="coqr">

    <div class="coqr__head">
        <?php /* Bản thiết kế cho nút này quay về FORM thanh toán. Ở đây không
                 quay lại được: đơn đã ghi vào CSDL rồi, và mở lại form là mời
                 khách đặt thêm một đơn trùng. Nên nó dẫn TỚI trang xác nhận —
                 vẫn là lối ra khỏi màn này, chỉ là đi tiếp thay vì đi lùi. */ ?>
        <a class="coqr__back" href="/thanh-toan/hoan-tat" aria-label="Xem xác nhận đơn hàng">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 18l-6-6 6-6"></path>
            </svg>
        </a>

        <div class="coqr__titles">
            <h1 class="coqr__title">Thanh toán</h1>
            <span class="coqr__code">#<?= e($order['code']) ?></span>
        </div>
    </div>

    <div class="coqr__grid">

        <!-- ══════════ CỘT TRÁI: MÃ QR ══════════ -->
        <div class="coqr__card coqr__pay">
            <div class="coqr__payhead">
                <span class="coqr__paytitle">Quét QR để thanh toán</span>
                <span class="coqr__paysub">App ngân hàng hỗ trợ VietQR</span>
            </div>

            <div class="coqr__frame">
                <?php if ($qrSrc !== null): ?>
                    <?php /* alt nói đủ để dùng được khi ảnh không tải: số tiền và
                             nội dung là hai thứ khách phải gõ tay nếu chuyển thủ công. */ ?>
                    <img src="<?= e($qrSrc) ?>" width="178" height="178"
                         alt="Mã QR chuyển khoản <?= money($total) ?>, nội dung <?= e($order['code']) ?>">
                <?php else: ?>
                    <p class="coqr__nocode">
                        Chưa có mã QR. Vui lòng chuyển khoản theo thông tin bên dưới.
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($bank['number'])): ?>
                <dl class="coqr__bank">
                    <div>
                        <dt>Ngân hàng:</dt>
                        <dd><?= e($bank['name']) ?></dd>
                    </div>
                    <div>
                        <dt>STK:</dt>
                        <dd><?= e($bank['number']) ?></dd>
                    </div>
                    <div>
                        <dt>Tên:</dt>
                        <dd><?= e($bank['holder']) ?></dd>
                    </div>
                    <div>
                        <dt>Nội dung:</dt>
                        <dd><?= e($order['code']) ?></dd>
                    </div>
                </dl>
            <?php else: ?>
                <?php /* Chưa cấu hình tài khoản nhận tiền — thà hứa một cuộc gọi
                         còn hơn in một số tài khoản không có thật. Cùng cách xử
                         lý với trang xác nhận đơn (order/success.php). */ ?>
                <p class="coqr__nocode">
                    Nhân viên sẽ gọi và đọc thông tin chuyển khoản trong ít phút nữa.
                </p>
            <?php endif; ?>

            <div class="coqr__amount">
                <span class="coqr__amountlabel">Số tiền cần chuyển</span>
                <span class="coqr__amountval"><?= money($total) ?></span>
            </div>
        </div>

        <!-- ══════════ CỘT PHẢI: ĐƠN HÀNG ══════════ -->
        <div class="coqr__side">

            <div class="coqr__card coqr__order">
                <h2 class="coqr__ordertitle">Đơn hàng #<?= e($order['code']) ?></h2>

                <?php foreach ($items as $item): ?>
                    <div class="coqr__line">
                        <span>
                            <?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?>
                            <?php if (!empty($item['lens_name'])): ?>
                                <?php /* Tròng cắt kèm đã nằm trong line_total. Nói tên
                                         nó ra để khách không thấy con số cao hơn giá
                                         gọng mà không hiểu vì sao — đây là màn hình
                                         họ đang chuẩn bị chuyển tiền. */ ?>
                                <span class="coqr__lens">+ <?= e($item['lens_name']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="coqr__linesum"><?= money((int) $item['line_total']) ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if ((int) $order['discount'] > 0): ?>
                    <?php /* Bản thiết kế không có dòng này vì đơn mẫu không có mã
                             giảm giá. Thiếu nó thì cộng các dòng hàng không ra
                             "Tổng đơn", và khách đang chuẩn bị chuyển tiền là lúc
                             tệ nhất để họ nghi hoá đơn tính sai. */ ?>
                    <div class="coqr__line">
                        <span>Giảm giá</span>
                        <span class="coqr__linesum">−<?= money((int) $order['discount']) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ((int) $order['shipping_fee'] > 0): ?>
                    <div class="coqr__line">
                        <span>Phí giao hàng</span>
                        <span class="coqr__linesum"><?= money((int) $order['shipping_fee']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="coqr__total">
                    <span class="coqr__totallabel">Tổng đơn</span>
                    <span class="coqr__totalval"><?= money($total) ?></span>
                </div>
            </div>

            <a class="coqr__done" href="/thanh-toan/hoan-tat">
                Tôi đã chuyển khoản <?= money($total) ?> ✓
            </a>

            <?php /* Bản thiết kế viết "xác nhận tự động sau 1–2 phút". Dự án chưa
                     nối cổng đối soát nào, nên câu đó sẽ là một lời hứa không ai
                     giữ — khách ngồi đợi một thông báo không bao giờ tới. Nói
                     đúng việc đang xảy ra: người thật đối chiếu sao kê. */ ?>
            <p class="coqr__note">
                Chúng tôi đối chiếu giao dịch và xác nhận đơn trong giờ làm việc
                (8:30 – 21:30). Giữ lại mã đơn <strong><?= e($order['code']) ?></strong>
                để tra cứu khi cần.
            </p>
        </div>
    </div>
</section>
