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
 * không ra gì. Ở đây ảnh dựng từ SePay theo đúng bốn thứ trong
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
 * ─────────────────────────────────────────────────────────────────────────────
 * SỐ TIỀN QUÉT QR KHÔNG PHẢI LÚC NÀO CŨNG LÀ TỔNG ĐƠN
 *
 * Đơn có cắt tròng theo độ chỉ thu TIỀN CỌC ở màn này; phần còn lại trả khi
 * nhận hàng. Nếu để mã QR mang số tổng thì khách quét xong chuyển thừa gấp ba
 * lần số phải trả bây giờ — và cửa hàng phải hoàn lại, đúng thứ phiền toái mà
 * cả cơ chế đặt cọc sinh ra để tránh.
 *
 * `deposit_amount` là số đã CHỐT lúc ghi đơn, không tính lại từ tỷ lệ trong
 * config — xem OrderModel::place(). 0 nghĩa là đơn không phải cọc và màn này
 * thu trọn tổng đơn như trước.
 * ─────────────────────────────────────────────────────────────────────────────
 */
$deposit = (int) ($order['deposit_amount'] ?? 0);
$isDeposit = $deposit > 0;
$due       = $isDeposit ? $deposit : $total;

/*
 * Ảnh QR — dựng bởi qr.sepay.vn.
 *
 * MỘT NHÀ CHO CẢ HAI ĐẦU. SePay vừa dựng ảnh mã QR, vừa là bên ĐỌC biến động
 * số dư của chính tài khoản này rồi báo về webhook (xem config/sepay.php). Nhờ
 * vậy lúc đối chiếu "mã QR bảo chuyển X, webhook báo về Y" không phải hỏi hai
 * nơi — và cũng không có nhà cung cấp thứ hai nào để mà chết riêng.
 *
 * Mã sinh ra vẫn theo chuẩn NAPAS nên app ngân hàng nào cũng quét được; chuẩn
 * đó là của NAPAS, không phải của một hãng nào.
 *
 * `template=qronly` = chỉ ô mã, không kèm khung logo ngân hàng — khung 210px
 * của bản thiết kế đã có viền riêng, chồng thêm khung nữa là mã bị bóp nhỏ lại
 * và điện thoại khó bắt.
 *
 * `des` LÀ MÃ ĐƠN, không phải tên khách. Đó là sợi dây duy nhất buộc dòng tiền
 * vào đơn hàng, ở cả hai đầu: nhân viên đọc sao kê, và SepayModel tự khớp.
 */
$qrSrc = !empty($bank['bin']) && !empty($bank['number'])
    ? sprintf(
        'https://qr.sepay.vn/img?acc=%s&bank=%s&amount=%d&des=%s&template=%s',
        rawurlencode((string) $bank['number']),
        rawurlencode((string) $bank['bin']),
        $due,
        rawurlencode($order['code']),
        rawurlencode((string) config('sepay.qr_template', 'qronly'))
    )
    : null;
?>

<section class="coqr">

    <div class="coqr__head">
        <?php /* Bản thiết kế cho nút này quay về FORM thanh toán. Ở đây không
                 quay lại được: đơn đã ghi vào CSDL rồi, và mở lại form là mời
                 khách đặt thêm một đơn trùng. Nên nó dẫn TỚI trang xác nhận —
                 vẫn là lối ra khỏi màn này, chỉ là đi tiếp thay vì đi lùi. */ ?>
        <?php /* Nút "‹" cũng theo $doneHref: vào từ trang tài khoản thì lùi về
                 đúng thẻ đơn đó, không phải trang cảm ơn của một đơn đã đặt từ
                 lâu. Xem OrderController::transfer. */ ?>
        <a class="coqr__back" href="<?= e($doneHref) ?>" aria-label="Quay lại đơn hàng">
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
                <?php /* KHÔNG nêu tên nhà cung cấp nào ở dòng này. Ảnh mã do
                         SePay dựng (xem $qrSrc), nhưng khách không cần biết —
                         thứ họ cần biết là "mở app ngân hàng nào cũng quét
                         được". Nêu tên một dịch vụ ở đây chỉ khiến người đọc
                         tưởng phải cài thêm app đó mới trả tiền được. */ ?>
                <span class="coqr__paysub">Mở app ngân hàng bất kỳ và quét mã</span>
            </div>

            <div class="coqr__frame">
                <?php if ($qrSrc !== null): ?>
                    <?php /* alt nói đủ để dùng được khi ảnh không tải: số tiền và
                             nội dung là hai thứ khách phải gõ tay nếu chuyển thủ công. */ ?>
                    <img src="<?= e($qrSrc) ?>" width="178" height="178"
                         alt="Mã QR chuyển khoản <?= money($due) ?>, nội dung <?= e($order['code']) ?>">
                <?php else: ?>
                    <p class="coqr__nocode">
                        Chưa có mã QR. Vui lòng chuyển khoản theo thông tin bên dưới.
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($bank['number'])): ?>
                <?php
                /* LƯỚI BA CỘT: nhãn · giá trị · nút chép.
                   NÚT CHÉP CHỈ CÓ Ở SỐ TÀI KHOẢN VÀ NỘI DUNG — đó là hai thứ
                   khách phải gõ lại vào app ngân hàng khi không quét được mã
                   (máy tính bàn, camera hỏng, app không đọc được QR), và cũng
                   là hai chỗ gõ sai thì tiền đi lạc hoặc về đúng nơi mà không
                   khớp được đơn nào.

                   Tên ngân hàng thì khách chọn trong danh sách của app, chép
                   làm gì; tên chủ tài khoản thì app tự hiện ra sau khi nhập số. */
                ?>
                <div class="coqr__bank">
                    <?php /* Dòng KHÔNG có nút chép thì giá trị trải hết hai cột còn
                             lại. Để một ô rỗng ở đó thì cột nút vẫn giữ chỗ bằng bề
                             rộng chữ "Sao chép", và tên chủ tài khoản dài bị ép
                             xuống hai dòng dù còn thừa chỗ bên phải. */ ?>
                    <span class="coqr__bankkey">Ngân hàng</span>
                    <span class="coqr__bankval coqr__bankval--wide"><?= e($bank['name']) ?></span>

                    <span class="coqr__bankkey">STK</span>
                    <span class="coqr__bankval"><?= e($bank['number']) ?></span>
                    <button type="button" class="coqr__copy js-copy"
                            data-copy="<?= e($bank['number']) ?>">Sao chép</button>

                    <span class="coqr__bankkey">Tên</span>
                    <span class="coqr__bankval coqr__bankval--wide"><?= e($bank['holder']) ?></span>

                    <span class="coqr__bankkey">Nội dung</span>
                    <span class="coqr__bankval"><?= e($order['code']) ?></span>
                    <button type="button" class="coqr__copy js-copy"
                            data-copy="<?= e($order['code']) ?>">Sao chép</button>
                </div>
            <?php else: ?>
                <?php /* Chưa cấu hình tài khoản nhận tiền — thà hứa một cuộc gọi
                         còn hơn in một số tài khoản không có thật. Cùng cách xử
                         lý với trang xác nhận đơn (order/success.php). */ ?>
                <p class="coqr__nocode">
                    Nhân viên sẽ gọi và đọc thông tin chuyển khoản trong ít phút nữa.
                </p>
            <?php endif; ?>

            <div class="coqr__amount">
                <span class="coqr__amountlabel">
                    <?= $isDeposit ? 'Đặt cọc cần chuyển ngay' : 'Số tiền cần chuyển' ?>
                </span>
                <span class="coqr__amountval"><?= money($due) ?></span>
                <?php if ($isDeposit): ?>
                    <span class="coqr__amountrest">
                        Còn lại <?= money($total - $deposit) ?> trả khi nhận hàng
                    </span>
                <?php endif; ?>
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

                <?php if ($isDeposit): ?>
                    <?php /* Chia đôi tổng đơn thành hai lần trả. Đặt NGAY DƯỚI
                             dòng "Tổng đơn" để mắt đọc được phép tính: hai số
                             này cộng lại đúng bằng số ở trên. */ ?>
                    <div class="coqr__split">
                        <div class="coqr__line">
                            <span>Đặt cọc <?= (int) ($order['deposit_rate'] ?? 0) ?>% — chuyển ngay</span>
                            <span class="coqr__linesum coqr__linesum--due"><?= money($deposit) ?></span>
                        </div>
                        <div class="coqr__line">
                            <span>Còn lại — trả khi nhận hàng</span>
                            <span class="coqr__linesum"><?= money($total - $deposit) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <a class="coqr__done" href="<?= e($doneHref) ?>">
                Tôi đã chuyển khoản <?= money($due) ?> ✓
            </a>

            <?php
            /* CÂU NÀY ĐỔI THEO VIỆC ĐANG THẬT SỰ XẢY RA, không theo bản thiết kế.
               Bản thiết kế viết "xác nhận tự động sau 1–2 phút". Câu đó chỉ
               đúng khi SePay đã bật và đang đọc biến động số dư; chưa bật thì
               nó là lời hứa không ai giữ, và khách ngồi đợi một thông báo không
               bao giờ tới. Chốt theo config('sepay.enabled') — xem config/sepay.php. */
            ?>
            <p class="coqr__note">
                <?php if (config('sepay.enabled')): ?>
                    Đơn tự động xác nhận sau 1–2 phút kể từ khi tiền về. Nhớ giữ
                    nguyên nội dung chuyển khoản <strong><?= e($order['code']) ?></strong>
                    — đó là thứ giúp chúng tôi nhận ra giao dịch của bạn.
                <?php else: ?>
                    Chúng tôi đối chiếu giao dịch và xác nhận đơn trong giờ làm việc
                    (8:30 – 21:30). Giữ lại mã đơn <strong><?= e($order['code']) ?></strong>
                    để tra cứu khi cần.
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>
