<?php

/**
 * order/paid.php — biên nhận thanh toán (/thanh-toan/thanh-cong?ma=…)
 *
 * Dựng theo "Thanh toan thanh cong.dc.html" (Claude Design, bản redesign
 * 2026-08-26): một cột hẹp 520px — dấu tick tròn tự vẽ, tiêu đề, thẻ biên
 * nhận có mép răng cưa, hai nút chữ (tải · chia sẻ), khối "tiếp theo", hai nút.
 *
 * MÀU và ĐỘ BO lấy theo trang chủ, không lấy con số trong bản vẽ: bản vẽ ghi
 * bo 12–16px và các mã màu riêng, còn cả site đi theo token --radius/--radius-sm
 * (6px/4px) — xem khối "ĐỘ BO GÓC" trong layout.css.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TRANG NÀY CHỈ TỚI ĐƯỢC KHI TIỀN ĐÃ VỀ THẬT
 *
 * Không phải khi khách bấm "Tôi đã chuyển khoản" — nút đó chỉ là lời họ nói.
 * Chốt nằm ở OrderController::paid; lý do đầy đủ ghi ở đó.
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CHỖ CỐ Ý KHÁC BẢN THIẾT KẾ — vì nghiệp vụ, không phải vì thẩm mỹ
 *
 * 1. ĐƠN ĐẶT CỌC. Bản vẽ chỉ có một con số "Tổng đã thanh toán". Đơn cắt tròng
 *    theo độ mới trả 30%, nên trang phải nói được cả hai vế: đã nhận bao nhiêu
 *    và còn lại bao nhiêu. Nói "đã thanh toán 4.400.000₫" cho một đơn vừa nhận
 *    1.320.000₫ là sai với cả khách lẫn sổ sách.
 *
 * 2. CÂU "TIẾP THEO" ĐỔI THEO HÌNH THỨC NHẬN HÀNG. Bản vẽ viết cứng "giao
 *    trong 2–4 ngày làm việc" — sai với đơn nhận tại cửa hàng, mà khách loại
 *    đó cần biết tới CƠ SỞ NÀO chứ không phải đợi mấy ngày.
 *
 * 3. DÒNG "SẢN PHẨM" LIỆT KÊ ĐỦ, không chỉ một món. Bản vẽ có đúng một dòng
 *    hàng; đơn thật nhiều món mà chỉ in món đầu thì biên nhận không khớp số
 *    tiền ngay bên dưới nó.
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI NÚT "TẢI BIÊN NHẬN" VÀ "CHIA SẺ" MẶC ĐỊNH BỊ ẨN
 *
 * Cả hai chỉ chạy được bằng JS (window.print, navigator.share). Nếp của dự án
 * là tắt JS thì mọi luồng vẫn đi được — mà ở đây không có đường lui nào cho
 * hai việc ấy, nên cách trung thực là KHÔNG vẽ nút ra chứ không phải vẽ một
 * nút bấm vào không có gì xảy ra. paid-receipt.js gỡ `hidden` khi nó chạy được.
 *
 * Nút "Sao chép" mã đơn thì ngược lại — luôn hiện, vì mã đã in ra dạng chữ
 * ngay cạnh: thiếu JS thì khách bôi đen chép tay, y như ở màn QR.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$deliveryLabels = ['pickup' => 'Nhận tại cửa hàng', 'shipping' => 'Giao tận nơi'];
$paymentLabels  = ['cod' => 'Thanh toán khi nhận hàng', 'bank_transfer' => 'Chuyển khoản ngân hàng'];

$delivery = $deliveryLabels[$order['delivery_method']] ?? $order['delivery_method'];
$payment  = $paymentLabels[$order['payment_method']] ?? $order['payment_method'];

/* Cách trả tiền, kèm tên ngân hàng cho đơn chuyển khoản — biên nhận phải nói
   được tiền đi bằng đường nào, đó là thứ khách đối chiếu với app ngân hàng. */
$bankName = (string) config('company.bank.name', '');

if ($order['payment_method'] === 'bank_transfer' && $bankName !== '') {
    $payment .= ' · ' . $bankName;
}

/* "Đã thanh toán lúc nào": paid_at là mốc tiền về ĐỦ và chỉ có ở đơn đã trả
   xong. Đơn mới nhận cọc chưa có mốc đó (xem OrderModel::markDepositPaid), nên
   lùi về mốc cập nhật gần nhất thay vì in một ô trống. */
$paidAt = $order['paid_at'] ?: ($order['updated_at'] ?? null);
?>

<section class="opaid">

    <!-- ══════════ CỤM MỪNG ══════════ -->
    <div class="opaid__hero">
        <span class="opaid__seal" aria-hidden="true"><?= icon('check', 'opaid__sealtick', 34) ?></span>

        <h1 class="opaid__title">
            <?= $isDeposit ? 'Đã nhận tiền cọc' : 'Thanh toán thành công' ?>
        </h1>

        <p class="opaid__lead">
            Cảm ơn bạn! Chúng tôi đã nhận được
            <strong><?= money($paidAmount) ?></strong>
            cho đơn <strong><?= e($order['code']) ?></strong>.
        </p>
    </div>

    <!-- ══════════ BIÊN NHẬN ══════════ -->
    <div class="opaid__receipt">
        <div class="opaid__card">

            <div class="opaid__head">
                <h2 class="opaid__cardtitle">Biên nhận thanh toán</h2>
                <span class="opaid__badge">
                    <?= icon('check', 'opaid__badgeico', 12) ?>
                    <?= $isDeposit ? 'Đã đặt cọc' : 'Đã thanh toán' ?>
                </span>
            </div>

            <dl class="opaid__rows">
                <dt>Mã đơn</dt>
                <dd>
                    <span class="opaid__codewrap">
                        <span class="opaid__code"><?= e($order['code']) ?></span>
                        <?php /* Dùng lại copy-btn.js — cùng nút, cùng cách báo
                                 "Đã chép ✓" như ở màn QR và thẻ đơn. Viết bản
                                 thứ ba của cùng hành vi này là cách chắc chắn
                                 để ngày sửa lời báo sẽ quên mất một chỗ. */ ?>
                        <button type="button"
                                class="opaid__copy js-copy"
                                data-copy="<?= e($order['code']) ?>">
                            <?= icon('copy', 'opaid__copyico', 11) ?>Sao chép
                        </button>
                    </span>
                </dd>

                <?php if ($paidAt !== null): ?>
                    <dt>Thời gian</dt>
                    <dd><?= e(formatDate($paidAt, 'd/m/Y · H:i')) ?></dd>
                <?php endif; ?>

                <dt>Phương thức</dt>
                <dd><?= e($payment) ?></dd>

                <dt>Sản phẩm</dt>
                <dd>
                    <?php /* ĐỦ CẢ DANH SÁCH — xem ghi chú 3 ở đầu file. */ ?>
                    <?php foreach ($items as $item): ?>
                        <span class="opaid__item">
                            <span class="notranslate" translate="no"><?= e($item['product_name']) ?></span> × <?= (int) $item['quantity'] ?>
                            <?php if (!empty($item['lens_name'])): ?>
                                <em class="opaid__lens">+ <?= e($item['lens_name']) ?></em>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </dd>
            </dl>

            <div class="opaid__total">
                <span><?= $isDeposit ? 'Tiền cọc đã nhận' : 'Tổng đã thanh toán' ?></span>
                <span class="opaid__totalnum"><?= money($paidAmount) ?></span>
            </div>

            <?php if ($isDeposit): ?>
                <?php /* Vế thứ hai của đơn đặt cọc. Thiếu nó thì khách rời trang mà
                         không biết hôm nhận kính phải cầm theo bao nhiêu. */ ?>
                <div class="opaid__rest">
                    <span>Còn lại khi nhận hàng</span>
                    <span class="opaid__restnum"><?= money($remaining) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php
        /* MÉP RĂNG CƯA — đáy thẻ xé ra như cuống biên lai giấy.
           Vẽ bằng SVG chứ không phải một dãy gradient tròn lặp lại: cần cả
           NỀN lẫn VIỀN cùng đi theo hình răng, mà background thì không tô
           được viền. preserveAspectRatio="none" cho phép kéo ngang tuỳ ý —
           răng hơi méo khi thẻ hẹp lại, và đó là đánh đổi đã biết, đổi lấy
           việc không phải tính lại số răng theo bề rộng. */
        ?>
        <svg class="opaid__tear" viewBox="0 0 520 10" preserveAspectRatio="none"
             aria-hidden="true" focusable="false">
            <path d="M0 0 H520 V2 L507 10 L494 2 L481 10 L468 2 L455 10 L442 2 L429 10 L416 2 L403 10 L390 2 L377 10 L364 2 L351 10 L338 2 L325 10 L312 2 L299 10 L286 2 L273 10 L260 2 L247 10 L234 2 L221 10 L208 2 L195 10 L182 2 L169 10 L156 2 L143 10 L130 2 L117 10 L104 2 L91 10 L78 2 L65 10 L52 2 L39 10 L26 2 L13 10 L0 2 Z"/>
        </svg>
    </div>

    <?php /* Ẩn sẵn — paid-receipt.js gỡ ra. Xem khối chú thích ở đầu file. */ ?>
    <div class="opaid__tools" data-receipt-tools hidden>
        <button type="button" class="opaid__tool" data-receipt-print>
            <?= icon('download', 'opaid__toolico', 15) ?>Tải biên nhận
        </button>
        <button type="button" class="opaid__tool" data-receipt-share>
            <?= icon('share', 'opaid__toolico', 15) ?><span data-share-label>Chia sẻ</span>
        </button>
    </div>

    <?php if (!empty($reward)): ?>
        <?php
        /* ══════════ QUÀ CHO KHÁCH CHUYỂN ĐỦ ══════════
           Đây là chỗ DUY NHẤT khách được BÁO rằng mình có quà. Mã đã nằm sẵn
           trong ví (user_vouchers) và sẽ tự hiện ở ô "Chọn mã giảm giá" lần
           mua sau — nhưng phát mà không nói thì gần như chắc chắn không ai
           dùng: khách rời trang, và không có màn nào khác kể lại chuyện này.

           In cả MÃ lẫn điều kiện: "bạn được tặng một mã" mà không nói mã gì,
           giảm bao nhiêu thì vẫn phải đi tìm. */
        ?>
        <div class="opaid__gift">
            <span class="opaid__gifttag">Quà cảm ơn</span>
            <p class="opaid__giftbody">
                Bạn đã chuyển khoản đủ nên được tặng mã
                <strong class="opaid__giftcode"><?= e($reward['code']) ?></strong>
                cho lần mua sau — <?= e($reward['condition_text'] ?: $reward['title']) ?>.
                Mã đã lưu sẵn trong tài khoản, lần đặt hàng tới chỉ cần chọn ở
                ô "Mã giảm giá".
            </p>
        </div>
    <?php endif; ?>

    <!-- ══════════ TIẾP THEO ══════════ -->
    <div class="opaid__next">
        <span class="opaid__nextico" aria-hidden="true"><?= icon('truck', '', 18) ?></span>
        <p>
            <strong>Tiếp theo:</strong>
            <?php
            /* Câu này đổi theo HAI trục — xem ghi chú 2 ở đầu file.

               Trục 1, việc cửa hàng làm: đơn đặt cọc là đơn cắt tròng theo số
               đo, và "bắt đầu mài tròng" mới là thứ vừa xảy ra nhờ tiền cọc —
               nói "chuẩn bị hàng" chung chung thì mất đúng cái khách đang chờ.
               Trục 2, cách nhận: đơn tới lấy tại cửa hàng cần biết CƠ SỞ NÀO,
               không phải đợi mấy ngày. */
            echo $isDeposit
                ? 'chúng tôi bắt đầu mài tròng theo thông số đo mắt của bạn'
                : 'chúng tôi chuẩn bị hàng';

            if ($order['delivery_method'] === 'pickup') {
                $store = trim((string) ($order['store_name'] ?? ''));

                echo ' rồi nhắn bạn tới lấy tại '
                   . e($store !== '' ? $store : 'cơ sở đã chọn')
                   . '. Nhớ mang theo mã đơn.';
            } else {
                echo ' và giao tới bạn trong 2–4 ngày làm việc.';
            }
            ?>
            Theo dõi tiến trình trong mục <a href="/tai-khoan?muc=don-hang">Đơn hàng của tôi</a>.
        </p>
    </div>

    <!-- ══════════ HAI NÚT ══════════ -->
    <div class="opaid__acts">
        <?php /* ?don= mở sẵn phần chi tiết của đúng đơn này, #<mã> cuộn tới thẻ
                 đó — cùng cách mà trang xác nhận đơn đang dùng. */ ?>
        <a class="opaid__btn"
           href="/tai-khoan?muc=don-hang&amp;don=<?= e(rawurlencode($order['code'])) ?>#<?= e($order['code']) ?>">
            Xem đơn hàng
        </a>
        <a class="opaid__btn opaid__btn--ghost" href="/san-pham">Tiếp tục mua sắm</a>
    </div>

    <p class="opaid__help">
        Cần hỗ trợ? Gọi
        <a href="<?= e(config('company.hotline_href')) ?>"><?= e(config('company.hotline')) ?></a>
        (<?= e(config('company.open_hours')) ?>) hoặc Zalo
        <a href="<?= e(config('company.channels.zalo')) ?>" target="_blank" rel="noopener"><?= e(config('company.zalo')) ?></a>.
    </p>

</section>
