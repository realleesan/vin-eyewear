<?php

/**
 * auth/account/don-hang.php — mục "Đơn hàng của tôi" (/tai-khoan?muc=don-hang).
 *
 * Dựng theo khối "ĐƠN HÀNG" trong "Vin Eyewear Account.dc.html" (Claude Design):
 *
 *   dải thẻ lọc theo trạng thái
 *   rồi mỗi đơn là một thẻ VIỀN MỎNG chia thành từng dải ngang:
 *     dải đầu (nền pearl)  mã · ngày · huy hiệu trạng thái
 *     dải hàng             ảnh 84px · thương hiệu/tên/phiên bản · SL + tiền
 *     dải tiến trình       5 chấm
 *     dải chi tiết         (nền pearl) hai cột: nhận hàng | tóm tắt thanh toán
 *     dải chân             cách giao · cách trả  ‖  nút hành động
 *
 * Thẻ đơn hàng KHÔNG cùng ngôn ngữ hình khối với các thẻ khác của trang tài
 * khoản: bản thiết kế vẽ những thẻ kia bo tròn lớn + đổ bóng, còn thẻ đơn thì
 * viền 1px + bo nhỏ + không bóng, và chia dải bằng nền pearl. Đây là chủ ý của
 * bản thiết kế (một hoá đơn nên trông như chứng từ, không như thẻ quảng cáo),
 * nên .acct-order tự tắt bóng và tự thêm viền chứ không sửa .acct-card.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÀU HUY HIỆU TRẠNG THÁI
 *
 * Sáu trạng thái của OrderModel::STATUSES ↔ đúng sáu cặp màu bản thiết kế khai
 * trong `Component.STATUS`. 'confirmed' xanh chàm và 'preparing' tím là HAI cặp
 * khác nhau — trước đây hai trạng thái này dùng chung một màu.
 *
 * THANH TIẾN TRÌNH: NĂM BƯỚC
 * Đúng 5 mốc như bản thiết kế. Đơn đã huỷ thì không vẽ thanh này: một đường
 * tiến trình dừng giữa chừng trông như đơn đang kẹt chứ không phải đã huỷ.
 *
 * BỐN CHỖ THÊM SO VỚI BẢN THIẾT KẾ — VÀ VÌ SAO
 *
 * 1. DÒNG "GIẢM GIÁ" trong tóm tắt thanh toán. Bản thiết kế chỉ có tạm tính +
 *    phí vận chuyển + tổng cộng vì đơn mẫu của nó không có mã giảm giá. Thiếu
 *    dòng này thì hoá đơn thật trông như tính sai. Chỉ hiện khi discount > 0.
 *
 * 2. DÒNG TỪNG SẢN PHẨM trong tóm tắt, CHỈ khi đơn có nhiều hơn một món. Bản
 *    thiết kế vẽ đơn một món nên dải hàng nói được hết; đơn nhiều món thì
 *    không, và khách không còn chỗ nào xem mình đã mua gì. Dựng bằng đúng
 *    nguyên thể "dòng tóm tắt" (nhãn trái · số phải) mà bản thiết kế đã định
 *    nghĩa ngay trong cột đó, không thêm hình khối mới.
 *
 * 3. GHI CHÚ CỦA KHÁCH dưới địa chỉ, cùng cột "Thông tin nhận hàng" — nó là
 *    một phần của việc nhận hàng. Chỉ hiện khi đơn có ghi chú.
 *
 * 4. HUY HIỆU TRẠNG THÁI TIỀN ở đầu thẻ, và KHỐI CHUYỂN KHOẢN trong cột tóm tắt
 *    khi đơn chuyển khoản còn nợ tiền. Bản thiết kế chỉ vẽ một huy hiệu — trạng
 *    thái giao vận — vì dữ liệu mẫu của nó không có trục trạng thái tiền nào.
 *    CSDL thì có (orders.payment_status), và đó là thứ khách hỏi trước nhất khi
 *    mở trang này: "tôi đã trả chưa, còn phải chuyển bao nhiêu, vào đâu".
 *    Xem OrderModel::PAYMENT_STATUSES.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$badgeTones = [
    'new'       => 'wait',
    'confirmed' => 'sure',
    'preparing' => 'prep',
    'shipping'  => 'ship',
    'completed' => 'done',
    'cancelled' => 'stop',
];

/* Vòng đời đơn, dùng để biết một đơn đã đi qua bước nào. Khớp thứ tự khai
   trong OrderModel::STATUSES, bỏ 'cancelled' vì huỷ không phải một bước tiến. */
$flow = [
    'new'       => 'Đã đặt hàng',
    'confirmed' => 'Đã xác nhận',
    'preparing' => 'Đang chuẩn bị',
    'shipping'  => 'Đang giao',
    'completed' => 'Đã nhận hàng',
];

/* Nút chính ở chân thẻ, theo trạng thái. Trạng thái không có tên ở đây thì chân
   thẻ chỉ còn nút "Xem chi tiết" — đúng như bản thiết kế để trống hai trạng
   thái 'confirmed' và 'preparing'.

   TRẠNG THÁI 'new' KHÔNG CÒN NÚT NÀO. Trước đây nó là "Xem hướng dẫn thanh
   toán" trỏ /chinh-sach#thanh-toan — một NEO CHẾT (trang chính sách chỉ có
   bao-hanh · doi-tra · do-mat · giao-hang · bao-mat), nên nút mở trang chính
   sách rồi đứng ở đầu trang. Nó lại hiện cho cả đơn COD, mà đơn COD thì không có
   hướng dẫn thanh toán nào để xem — cứ nhận hàng rồi trả tiền cho shipper.

   Việc trả tiền nay đi theo TRẠNG THÁI TIỀN chứ không theo trạng thái đơn: đơn
   chuyển khoản chưa nhận tiền có nút riêng, xem $needsTransfer bên dưới. */
$primaryLabels = [
    'shipping'  => 'Theo dõi vận chuyển',
    'completed' => 'Mua lại',
    'cancelled' => 'Mua lại',
];

/* Trạng thái mà khách còn kịp đổi ý.
   'shipping' KHÔNG có trong này: hàng đã rời cửa hàng thì việc cần làm là gọi
   hotline nói chuyện với người đang giao, không phải nhắn tin rồi chờ đọc.
   'completed' và 'cancelled' thì không còn gì để huỷ. */
$canStillCancel = ['new' => true, 'confirmed' => true, 'preparing' => true];

$deliveryLabels = ['pickup' => 'Nhận tại cửa hàng', 'shipping' => 'Giao tận nơi'];

/* HAI tên cho cùng một cách thanh toán, đúng như bản thiết kế viết: thẻ vuông
   trong phần chi tiết ghi cả chữ viết tắt (`payMethod`), còn dòng dưới chân thẻ
   thì bỏ nó đi (`footNote`) — chỗ đó là một dòng đọc nhanh, ngoặc đơn chỉ làm
   rối. */
$paymentLabels = [
    'cod'           => 'Thanh toán khi nhận hàng (COD)',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
];
$paymentShort  = [
    'cod'           => 'Thanh toán khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
];
?>

<div class="acct-head">
    <h1 class="acct-head__title">Đơn hàng của tôi</h1>
    <p class="acct-head__lead">Theo dõi trạng thái và lịch sử mua kính của bạn.</p>
</div>

<div class="acct-tabs">
    <?php
    /* Số trong ngoặc CHỈ hiện khi khác 0, đúng bản thiết kế: "Đã huỷ (0)" là
       một con số không nói gì mà vẫn chiếm chỗ trên dải. */
    ?>
    <a class="acct-tab<?= $tab === '' ? ' is-active' : '' ?>" href="/tai-khoan?muc=don-hang">
        Tất cả<?= $total > 0 ? ' (' . (int) $total . ')' : '' ?>
    </a>
    <?php foreach ($statuses as $key => $label): ?>
        <a class="acct-tab<?= $tab === $key ? ' is-active' : '' ?>"
           href="/tai-khoan?muc=don-hang&amp;loc=<?= e($key) ?>">
            <?= e($label) ?><?= !empty($tabCounts[$key]) ? ' (' . (int) $tabCounts[$key] . ')' : '' ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($orders === []): ?>
    <div class="acct-empty">
        <span class="acct-empty__ring" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#b0736a"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2.5 4h2l2.2 11h11.1l2.2-8H6"></path>
                <circle cx="8.5" cy="20" r="1.6"></circle>
                <circle cx="16.5" cy="20" r="1.6"></circle>
            </svg>
        </span>
        <span class="acct-empty__title">Chưa có đơn hàng nào</span>
        <span class="acct-empty__lead">Các đơn ở trạng thái này sẽ hiển thị tại đây.</span>
        <a class="acct-empty__cta" href="/san-pham">Khám phá sản phẩm</a>
    </div>
<?php else: ?>
    <div class="acct-list">
        <?php foreach ($orders as $o): ?>
            <?php
            $lines = $items[$o['id']] ?? [];
            $lead  = $lines[0] ?? null;          // dòng hàng in ra dải hàng
            $extra = max(0, count($lines) - 1);  // số dòng còn lại, nêu ở phiên bản
            $marks = $history[$o['id']] ?? [];
            $step  = array_search($o['status'], array_keys($flow), true);

            /* Mở/đóng bằng URL (?don=<mã>) chứ không bằng <details>: cùng lý do
               đã ghi ở đầu app/views/auth/profile.php — gửi được link tới đúng
               đơn đang hỏi, và F5 không đóng lại.

               Khối chi tiết dựng SẴN cho mọi đơn (ẩn bằng `hidden` nếu đang thu
               gọn) chứ không dựng khi ?don= khớp: dữ liệu của cả danh sách đã
               nạp từ trước — xem itemsForOrders trong AuthController — nên in
               thêm không tốn câu truy vấn nào, mà account.js mới có gì để
               bật/tắt tại chỗ, khỏi tải lại trang. Không có JS thì href bên
               dưới vẫn làm đúng việc như cũ. */
            $isOpen    = $expanded === $o['code'];
            $base      = '/tai-khoan?muc=don-hang' . ($tab !== '' ? '&amp;loc=' . e($tab) : '');
            $openHref  = $base . '&amp;don=' . e(rawurlencode($o['code'])) . '#' . e($o['code']);
            $closeHref = $base . '#' . e($o['code']);
            $detailId  = 'chi-tiet-' . e($o['code']);

            $delivery = $deliveryLabels[$o['delivery_method']] ?? $o['delivery_method'];
            $payment  = $paymentLabels[$o['payment_method']] ?? $o['payment_method'];
            $payShort = $paymentShort[$o['payment_method']] ?? $payment;

            $isPaid = ($o['payment_status'] ?? 'unpaid') === 'paid';

            /* Đơn chuyển khoản còn nợ tiền và chưa huỷ -> chân thẻ có nút riêng
               dẫn tới khối chuyển khoản, nằm ngay trong phần chi tiết của chính
               thẻ này. Bấm nút = mở phần chi tiết ra (cùng href với "Xem chi
               tiết"), nên không đi đâu khỏi trang và cũng không cần JS.

               Đơn COD KHÔNG có nút này: khách không phải làm gì trước khi nhận
               hàng cả. */
            /* Đơn CẮT TRÒNG chọn COD cũng cần chuyển khoản phần cọc — tiền
               cọc không trả cho shipper được, cửa hàng cần nó trước khi mài
               tròng. Nên điều kiện không còn chỉ là "đơn chuyển khoản". */
            $needsTransfer = !$isPaid
                && ($o['payment_method'] === 'bank_transfer'
                    || (int) ($o['deposit_amount'] ?? 0) > 0)
                && $o['payment_status'] !== 'deposit_paid'
                && $o['status'] !== 'cancelled';
            ?>
            <div class="acct-card acct-order" id="<?= e($o['code']) ?>">

                <div class="acct-order__top">
                    <div class="acct-order__id">
                        <span class="acct-order__code"><?= e($o['code']) ?></span>
                        <span class="acct-order__when">Đặt ngày <?= e(formatDate($o['created_at'])) ?></span>
                    </div>
                    <div class="acct-order__flags">
                        <?php
                        /* Huy hiệu TIỀN đứng trước huy hiệu trạng thái đơn, và có
                           dáng khác (viền thay vì nền đặc) để hai trục trạng thái
                           không tranh nhau — xem OrderModel::PAYMENT_STATUSES.

                           Đơn đã huỷ thì không nói chuyện tiền: "Chưa thanh toán"
                           trên một đơn đã huỷ đọc như còn nợ. */
                        ?>
                        <?php
                        /* BA nấc chứ không hai, từ khi có đặt cọc: đơn đã nhận
                           cọc mà vẫn in "Chưa thanh toán" là nói sai với người
                           vừa chuyển tiền. Nhãn lấy thẳng từ PAYMENT_STATUSES
                           để thêm nấc mới sau này không phải sửa view. */
                        $payState = (string) ($o['payment_status'] ?? 'unpaid');
                        $payTone  = ['paid' => 'paid', 'deposit_paid' => 'part'][$payState] ?? 'due';
                        ?>
                        <?php if ($o['status'] !== 'cancelled'): ?>
                            <span class="acct-badge acct-badge--<?= e($payTone) ?>">
                                <?= e($payStatuses[$payState] ?? 'Chưa thanh toán') ?>
                            </span>
                        <?php endif; ?>

                        <span class="acct-badge acct-badge--<?= e($badgeTones[$o['status']] ?? 'wait') ?>">
                            <?= e($statuses[$o['status']] ?? $o['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="acct-order__line">
                    <div class="acct-order__thumb">
                        <?php
                        /* images là JSON, phần tử đầu là ảnh đại diện. Sản phẩm
                           đã bị gỡ thì product_id là NULL và không có ảnh —
                           thẻ vẫn dựng, chỉ còn ô nền. */
                        $pics = $lead && $lead['images'] ? json_decode($lead['images'], true) : null;
                        $pic  = is_array($pics) ? ($pics[0] ?? null) : null;
                        ?>
                        <?php if ($pic !== null): ?>
                            <img src="<?= e(asset($pic)) ?>" alt="" width="84" height="84" loading="lazy">
                        <?php endif; ?>
                    </div>

                    <div class="acct-order__what">
                        <?php if (!empty($lead['brand'])): ?>
                            <span class="acct-order__brand"><?= e($lead['brand']) ?></span>
                        <?php endif; ?>
                        <span class="acct-order__name">
                            <?= e($lead['product_name'] ?? 'Sản phẩm đã gỡ khỏi cửa hàng') ?>
                        </span>
                        <span class="acct-order__variant">
                            <?php
                            /* Bản thiết kế in một dòng mô tả phiên bản ("Đen bóng ·
                               Tròng chống ánh sáng xanh 1.61"). CSDL không có cột
                               "phiên bản", nên ghép từ màu và chất liệu của sản
                               phẩm; đơn nhiều món thì nói luôn còn mấy món nữa. */
                            $bits = array_filter([$lead['color'] ?? null, $lead['material'] ?? null]);

                            if ($extra > 0) {
                                $bits[] = 'và ' . $extra . ' sản phẩm khác';
                            }

                            echo e($bits === [] ? $delivery : implode(' · ', $bits));
                            ?>
                        </span>
                    </div>

                    <div class="acct-order__money">
                        <span class="acct-order__qty">x<?= (int) ($lead['quantity'] ?? 1) ?></span>
                        <span class="acct-order__total"><?= money((int) $o['total']) ?></span>
                    </div>
                </div>

                <?php if ($o['status'] !== 'cancelled'): ?>
                    <ol class="acct-track">
                        <?php $i = 0; foreach ($flow as $key => $label): ?>
                            <?php
                            /* Ba trạng thái mỗi bước: 2 đã qua · 1 đang ở đây · 0 chưa tới.
                               'completed' là mốc CUỐI của vòng đời, nên bước đang
                               đứng ở đó phải là 2 (chấm đặc có dấu tick) chứ không
                               phải 1 (vòng rỗng "đang chờ") — đơn đã xong rồi thì
                               không còn gì để chờ nữa. */
                            $state = $i < $step ? 2
                                : ($i === $step ? ($o['status'] === 'completed' ? 2 : 1) : 0);
                            $at    = $marks[$key] ?? null;
                            ?>
                            <li class="acct-track__step acct-track__step--<?= $state ?>">
                                <span class="acct-track__bar" aria-hidden="true"></span>
                                <span class="acct-track__dot" aria-hidden="true">
                                    <?php if ($state === 2): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                             stroke-linejoin="round"><path d="M4 12.5l5.5 5.5L20 7"></path></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="acct-track__label"><?= e($label) ?></span>
                                <span class="acct-track__time">
                                    <?= $at !== null ? e(formatDate($at, 'd/m · H:i')) : '' ?>
                                </span>
                            </li>
                            <?php $i++; ?>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <div class="acct-order__detail" id="<?= $detailId ?>"<?= $isOpen ? '' : ' hidden' ?>>

                    <div class="acct-order__block">
                        <span class="acct-order__eyebrow">Thông tin nhận hàng</span>
                        <div class="acct-order__who">
                            <span class="acct-order__to">
                                <?= e($o['customer_name']) ?> · <?= e($o['customer_phone']) ?>
                            </span>
                            <span class="acct-order__addr">
                                <?php
                                /* Đơn giao tận nơi có địa chỉ khách; đơn nhận tại
                                   cửa hàng thì địa chỉ CẦN xem là địa chỉ cơ sở —
                                   in địa chỉ nhà khách ở đây là chỉ sai đường. */
                                if ($o['delivery_method'] === 'pickup') {
                                    echo e(trim(($o['store_name'] ?? 'Cơ sở Vin Eyewear')
                                        . (!empty($o['store_address']) ? ' · ' . $o['store_address'] : '')));
                                } else {
                                    echo e($o['shipping_address'] ?: 'Chưa có địa chỉ nhận hàng');
                                }
                                ?>
                            </span>
                        </div>

                        <?php if (!empty($o['note'])): ?>
                            <p class="acct-order__memo">Ghi chú: <?= e($o['note']) ?></p>
                        <?php endif; ?>

                        <div class="acct-order__tags">
                            <span class="acct-order__tag"><?= e($delivery) ?></span>
                            <span class="acct-order__tag"><?= e($payment) ?></span>
                            <?php /* KHÔNG còn thẻ "Đã nhận tiền <ngày>" ở đây.
                                     Dòng xanh trong cột "Tóm tắt thanh toán"
                                     (.acct-order__got) nói đúng chuyện đó và nói
                                     đủ hơn — có cả SỐ TIỀN và ĐƯỜNG tiền về, thứ
                                     mà một thẻ vuông chỉ mang ngày không có. Giữ
                                     cả hai là bắt khách đọc cùng một tin hai lần
                                     ở hai chỗ, với hai mức đầy đủ khác nhau. */ ?>
                        </div>

                    </div>

                    <div class="acct-order__block">
                        <span class="acct-order__eyebrow">Tóm tắt thanh toán</span>

                        <?php if ($extra > 0): ?>
                            <?php foreach ($lines as $ln): ?>
                                <div class="acct-order__sum">
                                    <span>
                                        <?php if (!empty($ln['slug'])): ?>
                                            <a href="/san-pham/<?= e($ln['slug']) ?>"><?= e($ln['product_name']) ?></a>
                                        <?php else: ?>
                                            <?= e($ln['product_name']) ?>
                                        <?php endif; ?>
                                        × <?= (int) $ln['quantity'] ?>
                                        <?php if (!empty($ln['lens_name']) || !empty($ln['prescription'])): ?>
                                            <?php /* Tròng cắt kèm đã nằm trong
                                                     line_total — nói tên nó ra để
                                                     con số không cao hơn giá gọng
                                                     mà không có lời giải thích. */ ?>
                                            <span class="acct-order__lens">
                                                <?php if (!empty($ln['lens_name'])): ?>
                                                    + <?= e($ln['lens_name']) ?><?= $ln['prescription'] !== null ? ' ·' : '' ?>
                                                <?php endif; ?>
                                                <?php if ($ln['prescription'] !== null): ?>
                                                    <?= e($ln['prescription']) ?>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="acct-order__num"><?= money((int) $ln['line_total']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="acct-order__sum<?= $extra > 0 ? ' acct-order__sum--split' : '' ?>">
                            <span>Tạm tính</span>
                            <span class="acct-order__num"><?= money((int) $o['subtotal']) ?></span>
                        </div>

                        <?php if ((int) $o['discount'] > 0): ?>
                            <div class="acct-order__sum">
                                <span>Giảm giá</span>
                                <span class="acct-order__num">−<?= money((int) $o['discount']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="acct-order__sum">
                            <span>Phí vận chuyển</span>
                            <?php if ((int) $o['shipping_fee'] === 0): ?>
                                <span class="acct-order__free">Miễn phí</span>
                            <?php else: ?>
                                <span class="acct-order__num"><?= money((int) $o['shipping_fee']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="acct-order__sum acct-order__sum--grand">
                            <span>Tổng cộng</span>
                            <span class="acct-order__grand"><?= money((int) $o['total']) ?></span>
                        </div>

                        <?php
                        /* ĐẶT CỌC — đơn có cắt tròng theo độ.
                           Đây là chỗ khách quay lại tra "hôm nhận kính phải cầm
                           bao nhiêu", có khi vài tuần sau khi đặt. Nên in cả hai
                           vế chứ không chỉ số cọc. */
                        $deposit = (int) ($o['deposit_amount'] ?? 0);
                        ?>
                        <?php if ($deposit > 0): ?>
                            <div class="acct-order__deposit">
                                <div class="acct-order__sum">
                                    <span>Đặt cọc <?= (int) ($o['deposit_rate'] ?? 0) ?>%</span>
                                    <span class="acct-order__num"><?= money($deposit) ?></span>
                                </div>
                                <div class="acct-order__sum">
                                    <span>Còn lại khi nhận hàng</span>
                                    <span class="acct-order__num"><?= money((int) $o['total'] - $deposit) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        /* ══════════ DÒNG XÁC NHẬN ĐÃ NHẬN TIỀN ══════════
                           Chỉ hiện khi tiền đã về thật. Cột tóm tắt bên trên
                           mới chỉ nói đơn ĐÁNG bao nhiêu; dòng này nói đã TRẢ
                           bao nhiêu, bằng đường nào, ngày nào — ba thứ khách
                           đối chiếu với app ngân hàng của họ.

                           Huy hiệu "Đã thanh toán" ở đầu thẻ không thay được
                           dòng này: nó chỉ trả lời có/chưa, không nói số tiền
                           và cũng không nói ngày. Với đơn đặt cọc thì khác
                           biệt đó là toàn bộ vấn đề — "đã thanh toán" mà mới
                           nhận 30% là hai chuyện khác nhau.

                           SỐ TIỀN ĐÃ NHẬN không phải lúc nào cũng bằng tổng
                           đơn: đơn mới nhận cọc thì đó là phần cọc. */
                        $gotAmount = $payState === 'deposit_paid' && $deposit > 0
                            ? $deposit : (int) $o['total'];

                        /* Ngày tiền về. paid_at là mốc trả ĐỦ và chỉ có ở đơn
                           đã xong; đơn mới cọc chưa có mốc đó (xem
                           OrderModel::markDepositPaid) nên lùi về mốc cập nhật
                           gần nhất thay vì bỏ trống. */
                        $gotAt = $o['paid_at'] ?: ($o['updated_at'] ?? null);
                        ?>
                        <?php if (in_array($payState, ['paid', 'deposit_paid'], true)
                                  && $o['status'] !== 'cancelled'): ?>
                            <p class="acct-order__got">
                                <span class="acct-order__gotmark" aria-hidden="true">✓</span>
                                <span>
                                    Đã nhận <strong><?= money($gotAmount) ?></strong>
                                    <?php if ($payState === 'deposit_paid' && $deposit > 0): ?>
                                        (cọc <?= (int) ($o['deposit_rate'] ?? 0) ?>%)
                                    <?php endif; ?>
                                    qua <?= e(utf8Lower($payShort)) ?><?php
                                        /* Đơn chuyển khoản: nói luôn ngân hàng nào —
                                           đó là thứ khách dò trong app của họ. */
                                        if ($o['payment_method'] === 'bank_transfer' && !empty($bank['name'])) {
                                            echo ' ' . e($bank['name']);
                                        }
                                        if ($gotAt !== null) {
                                            echo ' · ' . e(formatDate($gotAt, 'd/m'));
                                        }
                                    ?>
                                </span>
                            </p>
                        <?php endif; ?>

                    </div>

                    <?php if ($needsTransfer && !empty($bank['number'])): ?>
                        <?php
                        /* ══════════ KHỐI CHUYỂN KHOẢN ══════════
                           NẰM NGANG CẢ HAI CỘT, không còn nhét trong cột "Tóm
                           tắt thanh toán" như trước. Đây là thứ khách phải LÀM
                           chứ không phải con số để đọc, nên nó không thuộc về
                           một cột tóm tắt — và bó trong nửa bề ngang thì tên
                           chủ tài khoản dài phải xuống ba dòng.

                           Vẫn ở TRONG phần chi tiết (gấp lại được cùng thẻ):
                           danh sách nhiều đơn mà thẻ nào cũng bung khối này ra
                           thì cuộn mãi không hết. */
                        ?>
                        <div class="acct-order__pay" id="ck-<?= e($o['code']) ?>">

                            <span class="acct-order__eyebrow acct-order__pay-head">Chuyển khoản tới</span>

                            <?php
                            /* Bảng ba cột: nhãn · giá trị · nút chép.
                               NÚT CHÉP CHỈ CÓ Ở HAI DÒNG SỐ TÀI KHOẢN VÀ NỘI
                               DUNG — đó là hai thứ phải gõ lại vào app ngân
                               hàng, và cũng là hai chỗ gõ sai thì tiền đi lạc
                               hoặc không khớp được đơn. Tên ngân hàng thì khách
                               chọn trong danh sách, chép làm gì. */
                            $rows = [
                                ['Ngân hàng',    $bank['name'],   null],
                                ['Số tài khoản', $bank['number'], $bank['number']],
                                ['Chủ tài khoản', $bank['holder'], null],
                            ];
                            $payAmount = $deposit > 0 ? $deposit : (int) $o['total'];
                            ?>

                            <div class="acct-order__bank">
                                <?php foreach ($rows as [$label, $value, $copy]): ?>
                                    <span class="acct-order__bankkey"><?= e($label) ?></span>
                                    <?php /* Dòng KHÔNG có nút chép thì giá trị trải hết hai
                                             cột còn lại — để một ô rỗng ở đó thì cột nút vẫn
                                             giữ chỗ và tên chủ tài khoản dài bị ép xuống dòng. */ ?>
                                    <span class="acct-order__bankval<?= $copy === null ? ' acct-order__bankval--wide' : '' ?>"><?= e((string) $value) ?></span>
                                    <?php if ($copy !== null): ?>
                                        <button type="button" class="acct-copy js-copy" data-copy="<?= e((string) $copy) ?>">Sao chép</button>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <span class="acct-order__bankkey">Số tiền</span>
                                <span class="acct-order__bankval acct-order__bankval--wide">
                                    <?= money($payAmount) ?>
                                    <?php if ($deposit > 0): ?>
                                        <?php /* Đơn cắt tròng chỉ chuyển phần CỌC ở bước này —
                                                 chuyển cả tổng là thừa tiền và cửa hàng phải
                                                 hoàn lại. Xem order/transfer.php. */ ?>
                                        <em class="acct-order__banknote">tiền cọc <?= (int) ($o['deposit_rate'] ?? 0) ?>%</em>
                                    <?php endif; ?>
                                </span>

                                <span class="acct-order__bankkey">Nội dung</span>
                                <span class="acct-order__bankval"><?= e($o['code']) ?></span>
                                <button type="button" class="acct-copy js-copy" data-copy="<?= e($o['code']) ?>">Sao chép</button>
                            </div>

                            <div class="acct-order__payfoot">
                                <p class="acct-order__memo">
                                    Ghi đúng mã đơn ở phần nội dung để chúng tôi đối chiếu được.
                                </p>

                                <?php
                                /* LỐI VÀO MÀN QUÉT MÃ QR.
                                   Bảng trên chỉ in số tài khoản dạng chữ, và gõ tay
                                   13 chữ số vào app ngân hàng là đúng chỗ người ta
                                   gõ sai. Nút này mở lại đúng màn hình đã hiện ngay
                                   sau khi đặt đơn — có mã QR mang sẵn số tiền và
                                   nội dung chuyển khoản. Xem OrderController::transfer. */
                                ?>
                                <a class="acct-btn acct-btn--primary acct-btn--sm acct-order__qr"
                                   href="/thanh-toan/chuyen-khoan?ma=<?= e(rawurlencode($o['code'])) ?>">
                                    Quét mã QR để thanh toán
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="acct-order__foot">
                    <span class="acct-order__footnote"><?= e($delivery) ?> · <?= e($payShort) ?></span>

                    <?php
                    /* Câu cảm ơn nằm BÊN TRÁI, cạnh dòng ghi chú — không nhét
                       vào hàng nút bên phải.

                       Bản thiết kế đặt nó đúng chỗ nút "Xem thông tin chuyển
                       khoản" vừa biến mất, vì bản vẽ không có nút nào khác ở
                       chân thẻ. Ở đây thì có: "Xem biên nhận", "Đổi hoặc huỷ
                       đơn" và "Thu gọn". Thêm một câu chữ vào giữa ba nút đó
                       là hàng nút tràn xuống dòng thứ hai. */
                    ?>
                    <?php if (in_array($payState, ['paid', 'deposit_paid'], true)
                              && $o['status'] !== 'cancelled'): ?>
                        <span class="acct-order__thanks">
                            <?= $payState === 'deposit_paid'
                                ? 'Cảm ơn bạn — đã nhận tiền cọc.'
                                : 'Cảm ơn bạn — đơn hàng đã được thanh toán.' ?>
                        </span>
                    <?php endif; ?>

                    <div class="acct-order__acts">
                        <?php if ($needsTransfer && !empty($bank['number'])): ?>
                            <?php
                            /*
                                ĐI THẲNG TỚI MÀN QUÉT QR.

                                Trước đây nút này ghi "Xem thông tin chuyển khoản"
                                và chỉ mở phần chi tiết ra rồi cuộn tới khối ngân
                                hàng — tức là làm ĐÚNG việc mà nút "Xem chi tiết"
                                ngay cạnh nó đã làm, chỉ thêm một cú cuộn.

                                Cú cuộn đó từng có lý khi khối chuyển khoản nằm
                                lọt trong cột tóm tắt bên phải. Từ lần dựng lại
                                theo bản thiết kế, khối đó chiếm ngang cả hai cột
                                và có nền vàng riêng — mở chi tiết ra là thấy
                                ngay, không còn gì để cuộn tới.

                                Cái giá của việc giữ nó: khách phải bấm HAI lần
                                mới tới chỗ trả tiền (nút này mở panel, rồi nút
                                "Quét mã QR" bên trong mới đi). Nay một lần.

                                Số tài khoản dạng chữ vẫn ở trong phần chi tiết
                                cho ai muốn chép tay — mở bằng "Xem chi tiết" như
                                mọi thông tin khác của đơn.

                                (Chú thích PHP chứ không phải <!-- -->: khối này
                                nằm trong vòng lặp thẻ đơn, HTML comment sẽ được
                                gửi xuống trình duyệt một lần cho MỖI đơn.)
                            */
                            ?>
                            <a class="acct-btn acct-btn--primary acct-btn--sm"
                               href="/thanh-toan/chuyen-khoan?ma=<?= e(rawurlencode($o['code'])) ?>">
                                Quét mã QR để thanh toán
                            </a>
                        <?php elseif (isset($primaryLabels[$o['status']])): ?>
                            <?php if ($primaryLabels[$o['status']] === 'Mua lại'): ?>
                                <form method="post" action="/tai-khoan/mua-lai">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="code" value="<?= e($o['code']) ?>">
                                    <button type="submit" class="acct-btn acct-btn--primary acct-btn--sm">
                                        Mua lại
                                    </button>
                                </form>
                            <?php else: ?>
                                <a class="acct-btn acct-btn--primary acct-btn--sm" href="/lien-he">
                                    <?= e($primaryLabels[$o['status']]) ?>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php
                        /* BIÊN NHẬN — chỉ hiện khi tiền đã về thật.
                           Dùng cùng $payState đã tính ở dải đầu thẻ. Đơn đã huỷ
                           thì không mời xem biên nhận nữa, kể cả khi từng nhận
                           được tiền: chuyện hoàn tiền là việc nói qua điện
                           thoại, không phải một trang mừng công. */
                        ?>
                        <?php if (in_array($payState, ['paid', 'deposit_paid'], true)
                                  && $o['status'] !== 'cancelled'): ?>
                            <a class="acct-btn acct-btn--outline acct-btn--sm"
                               href="/thanh-toan/thanh-cong?ma=<?= e(rawurlencode($o['code'])) ?>">
                                Xem biên nhận
                            </a>
                        <?php endif; ?>

                        <?php if (isset($canStillCancel[$o['status']])): ?>
                            <?php
                            /*
                                ĐÂY LÀ CHỖ KHÁCH ĐI TÌM NÚT "HUỶ ĐƠN" — và cố ý
                                không có nút đó.

                                Cửa hàng tự đi giao, không đồng bộ trạng thái
                                vận chuyển thời gian thực với đơn vị vận chuyển
                                nào; một nút huỷ trên web sẽ đổi trạng thái
                                trong CSDL trong khi hàng có thể đã nằm trên xe,
                                và hai bên hiểu khác nhau về cùng một đơn.

                                Nhưng chỗ trống thì phải có lối đi thay thế đặt
                                đúng vào đó, chứ không phải để khách bấm quanh
                                rồi tự đoán. Nhãn nói thẳng là sang Zalo, không
                                phải một nút huỷ tại chỗ — bấm nhầm rồi mới biết
                                nó mở ứng dụng khác là một kiểu nói dối nhỏ.

                                Đơn hàng của khách đã nằm sẵn trong Zalo của cửa
                                hàng từ lúc đặt (xem Zalo::order), nên nhân viên
                                bên kia mở đúng đơn này ra được ngay.

                                SỐ ZALO IN THÀNH CHỮ TRONG CHÍNH NHÃN NÚT, không
                                chỉ nằm trong href. Bấm liên kết zalo.me trên máy
                                tính bàn không phải lúc nào cũng mở được ứng
                                dụng; khi đó khách cần ĐỌC được con số để tự tìm,
                                chứ không phải bấm vào một chỗ không phản hồi rồi
                                bỏ cuộc.
                            */
                            ?>
                            <a class="acct-btn acct-btn--quiet acct-btn--sm"
                               href="<?= e(config('company.channels.zalo')) ?>"
                               target="_blank" rel="noopener">
                                Đổi hoặc huỷ đơn — Zalo <?= e(config('company.zalo')) ?>
                            </a>
                        <?php endif; ?>

                        <a class="acct-btn acct-btn--outline acct-btn--sm acct-order__more"
                           href="<?= $isOpen ? $closeHref : $openHref ?>"
                           aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                           aria-controls="<?= $detailId ?>"
                           data-open-href="<?= $openHref ?>"
                           data-close-href="<?= $closeHref ?>">
                            <?= $isOpen ? 'Thu gọn' : 'Xem chi tiết' ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
