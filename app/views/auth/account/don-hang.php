<?php

/**
 * auth/account/don-hang.php — tab "Đơn hàng" (/tai-khoan?muc=don-hang).
 *
 * Màn "Purchases" của "Ho So Nguoi Dung.dc.html". Bản vẽ chỉ có trạng thái
 * CHƯA CÓ ĐƠN: tiêu đề, một câu cách xa bên dưới, nút TIẾP TỤC MUA SẮM.
 *
 * CÓ ĐƠN thì mỗi đơn là một THẺ LỊCH HẸN của chính bản vẽ (màn Appointments):
 *
 *   ĐƠN ĐÃ ĐẶT                                              3
 *   ┌───────────────────────────────────────────────────────┐
 *   │ Tên sản phẩm +1                          ( ĐANG GIAO ) │
 *   │ VE-123 · Đặt ngày 13/09/2026              XEM CHI TIẾT │
 *   │ 1.200.000đ · Chưa thanh toán (xám)             HUỶ ĐƠN │
 *   ├───────────────────────────────────────────────────────┤  ?don=<mã>
 *   │ Nhận hàng · Sản phẩm · Thanh toán · Chuyển khoản        │
 *   └───────────────────────────────────────────────────────┘
 *
 * Thanh tiến trình năm chấm, huy hiệu màu và dải lọc trạng thái của bản cũ ĐÃ
 * GỠ. CHỨC NĂNG thì giữ đủ: số tài khoản + nút QR cho đơn còn nợ tiền, huỷ đơn
 * (UC-02), mua lại, biên nhận, lối Zalo cho đơn không tự huỷ được.
 *
 * MỞ/ĐÓNG CHI TIẾT BẰNG URL (?don=<mã>): gửi link tới đúng đơn được, F5 không
 * đóng lại. Khối chi tiết dựng SẴN cho mọi đơn (ẩn bằng `hidden`) — dữ liệu đã
 * nạp cả rồi, và account.js nhờ vậy bật/tắt tại chỗ không tải lại trang.
 *
 * Hộp xác nhận huỷ đơn mở bằng ?huy=<mã>, ngay trong thẻ đó.
 */

$deliveryLabels = ['pickup' => 'Nhận tại cửa hàng', 'shipping' => 'Giao tận nơi'];

$paymentLabels = [
    'cod'           => 'Thanh toán khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
];

/* Vòng đời đơn, theo thứ tự — chỉ để in các mốc đã qua trong phần chi tiết.
   Bỏ 'cancelled' vì huỷ không phải một bước tiến. */
$flow = ['new', 'confirmed', 'preparing', 'shipping', 'completed'];

$chevron = '<svg class="acct-field__chev" width="16" height="16" viewBox="0 0 16 16" fill="none"'
         . ' stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">'
         . '<path d="M3 6l5 5 5-5"></path></svg>';
?>

<?php if (isset($_GET['da-chuyen'])): ?>
    <?php /* FR-TT-05 — tới đây từ nút "Tôi đã chuyển khoản" ở trang QR. Cố ý KHÔNG
             nói "đã nhận được tiền": chỉ sao kê ngân hàng trả lời được câu đó.
             Là tham số GET chứ không phải flash, để F5 vẫn còn câu này. */ ?>
    <p class="acct-flash acct-flash--ok" role="status">
        Đơn của bạn đã được ghi nhận. Chúng tôi đang chờ tiền về và sẽ báo lại ngay.
    </p>
<?php endif; ?>

<h1 class="acct-title">Đơn hàng</h1>

<?php if ($orders === []): ?>

    <div class="acct-empty">
        <p class="acct-empty__text">Bạn chưa có lịch sử mua hàng.</p>
        <a class="acct-btn acct-empty__btn" href="/san-pham/gong-kinh">Tiếp tục mua sắm</a>
    </div>

<?php else: ?>

<section class="acct-sec acct-sec--first" aria-labelledby="dh-tieu-de">
    <div class="acct-head">
        <h2 class="acct-head__label" id="dh-tieu-de">Đơn đã đặt</h2>
        <span class="acct-mute"><?= count($orders) ?></span>
    </div>

    <div class="acct-cards">
        <?php foreach ($orders as $o): ?>
            <?php
            $lines = $items[$o['id']] ?? [];
            $lead  = $lines[0] ?? null;
            $extra = max(0, count($lines) - 1);
            $marks = $history[$o['id']] ?? [];

            $isOpen    = $expanded === $o['code'];
            $base      = '/tai-khoan?muc=don-hang';
            $openHref  = $base . '&amp;don=' . e(rawurlencode($o['code'])) . '#' . e($o['code']);
            $closeHref = $base . '#' . e($o['code']);
            $detailId  = 'chi-tiet-' . e($o['code']);

            $daHuy    = $o['status'] === 'cancelled';
            $delivery = $deliveryLabels[$o['delivery_method']] ?? $o['delivery_method'];
            $payment  = $paymentLabels[$o['payment_method']] ?? $o['payment_method'];
            $payState = (string) ($o['payment_status'] ?? 'unpaid');
            $daTra    = in_array($payState, ['paid', 'deposit_paid'], true) && !$daHuy;
            $deposit  = (int) ($o['deposit_amount'] ?? 0);

            /* CÒN PHẢI CHUYỂN KHOẢN: đơn chuyển khoản chưa nhận tiền, HOẶC đơn cắt
               tròng chọn COD — tiền cọc không trả cho shipper được, cửa hàng cần
               nó trước khi mài tròng. Đơn COD thường thì không: khách không phải
               làm gì trước khi nhận hàng. */
            $needsTransfer = !$daTra && !$daHuy
                && ($o['payment_method'] === 'bank_transfer' || $deposit > 0)
                && !empty($bank['number']);

            /* Huỷ được hay không do OrderModel::khachHuyDuoc() trả lời: null là
               được, một câu là lý do từ chối. Nó biết cả thứ một danh sách trạng
               thái không biết — đơn "Đang chuẩn bị" mà đã bấm mốc mài thì tròng
               đã cắt theo số đo riêng của khách. Máy chủ kiểm lại y hệt. */
            $chanHuy = OrderModel::khachHuyDuoc($o);
            $moHuy   = $chanHuy === null && ($_GET['huy'] ?? '') === $o['code'];
            $conChay = !in_array($o['status'], ['completed', 'cancelled'], true);
            ?>
            <article class="acct-card" id="<?= e($o['code']) ?>">

                <div class="acct-card__body">
                    <span class="acct-card__lead notranslate" translate="no"><?= e($lead['product_name'] ?? 'Sản phẩm đã gỡ khỏi cửa hàng') ?><?= $extra > 0 ? ' +' . $extra : '' ?></span>
                    <span><?= e($o['code']) ?> · Đặt ngày <?= e(formatDate($o['created_at'])) ?></span>
                    <?php /* Đơn đã huỷ không nói chuyện tiền: "Chưa thanh toán" trên
                             một đơn đã huỷ đọc như còn nợ. */ ?>
                    <span class="acct-mute"><?= money((int) $o['total']) ?><?= $daHuy ? '' : ' · ' . e($payStatuses[$payState] ?? 'Chưa thanh toán') ?></span>
                </div>

                <div class="acct-card__acts">
                    <?php /* Nhãn qua OrderModel::nhanTrangThai() — đơn nhận tại quầy
                             đọc là "Sẵn sàng tại cửa hàng", không phải "Đang giao"
                             (B9). Ngăn kéo đơn của nhân viên gọi cùng hàm này. */ ?>
                    <span class="acct-pill"><?= e(OrderModel::nhanTrangThai($o['status'], $o['delivery_method'] ?? null)) ?></span>

                    <a class="acct-act" data-more
                       href="<?= $isOpen ? $closeHref : $openHref ?>"
                       aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                       aria-controls="<?= $detailId ?>"
                       data-open-href="<?= $openHref ?>"
                       data-close-href="<?= $closeHref ?>"><?= $isOpen ? 'Thu gọn' : 'Xem chi tiết' ?></a>

                    <?php if ($needsTransfer): ?>
                        <a class="acct-act" href="<?= $base ?>&amp;don=<?= e(rawurlencode($o['code'])) ?>#ck-<?= e($o['code']) ?>">Thanh toán</a>
                    <?php endif; ?>

                    <?php if ($daTra): ?>
                        <?php /* Biên nhận chỉ mở khi tiền đã về thật — xem OrderController::paid. */ ?>
                        <a class="acct-act" href="/thanh-toan/thanh-cong?ma=<?= e(rawurlencode($o['code'])) ?>">Xem biên nhận</a>
                    <?php endif; ?>

                    <?php if ($o['status'] === 'shipping'): ?>
                        <?php /* Đơn nhận tại quầy không có ai đang giao — việc của
                                 khách là ra cửa hàng, nên mời xem địa chỉ (B9). */ ?>
                        <a class="acct-act" href="/lien-he"><?= ($o['delivery_method'] ?? '') === 'pickup' ? 'Xem địa chỉ cửa hàng' : 'Theo dõi vận chuyển' ?></a>
                    <?php endif; ?>

                    <?php if (!$conChay): ?>
                        <form method="post" action="/tai-khoan/mua-lai">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="code" value="<?= e($o['code']) ?>">
                            <button type="submit" class="acct-act">Mua lại</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($chanHuy === null): ?>
                        <a class="acct-act acct-act--mute"
                           href="<?= $base ?>&amp;huy=<?= e(rawurlencode($o['code'])) ?>#<?= e($o['code']) ?>">Huỷ đơn</a>
                    <?php elseif ($conChay): ?>
                        <?php /* Đơn đã qua mốc tự huỷ (đang giao, hoặc đã bấm mốc mài):
                                 đây là chỗ khách đi tìm nút "Huỷ đơn", nên đặt lối
                                 thay thế đúng vào đó. Số Zalo in thành chữ — liên kết
                                 zalo.me trên máy tính bàn không phải lúc nào cũng mở
                                 được ứng dụng, khách cần ĐỌC được số để tự tìm. */ ?>
                        <a class="acct-act acct-act--mute" href="<?= e(config('company.channels.zalo')) ?>"
                           target="_blank" rel="noopener">Đổi hoặc huỷ qua Zalo <?= e(config('company.zalo')) ?></a>
                    <?php endif; ?>
                </div>

                <div class="acct-card__more" id="<?= $detailId ?>"<?= $isOpen ? '' : ' hidden' ?>>

                    <div class="acct-part">
                        <h3 class="acct-cap">Nhận hàng</h3>
                        <span><?= e($o['customer_name']) ?></span>
                        <span><?= e(groupPhone($o['customer_phone'])) ?></span>
                        <span>
                            <?php
                            /* Đơn nhận tại cửa hàng thì địa chỉ CẦN xem là địa chỉ
                               cơ sở — in địa chỉ nhà khách ở đây là chỉ sai đường. */
                            if ($o['delivery_method'] === 'pickup') {
                                echo e(trim(($o['store_name'] ?? 'Cơ sở Vin Eyewear')
                                    . (!empty($o['store_address']) ? ' · ' . $o['store_address'] : '')));
                            } else {
                                echo e($o['shipping_address'] ?: 'Chưa có địa chỉ nhận hàng');
                            }
                            ?>
                        </span>
                        <?php if (!empty($o['note'])): ?>
                            <span class="acct-mute">Ghi chú: <?= e($o['note']) ?></span>
                        <?php endif; ?>
                        <span class="acct-mute"><?= e($delivery) ?> · <?= e($payment) ?></span>
                    </div>

                    <div class="acct-part">
                        <h3 class="acct-cap">Sản phẩm</h3>
                        <?php foreach ($lines as $ln): ?>
                            <div class="acct-sum">
                                <span>
                                    <?php if (!empty($ln['slug'])): ?>
                                        <a class="notranslate" translate="no" href="/san-pham/<?= e($ln['slug']) ?>"><?= e($ln['product_name']) ?></a>
                                    <?php else: ?>
                                        <span class="notranslate" translate="no"><?= e($ln['product_name']) ?></span>
                                    <?php endif; ?>
                                    × <?= (int) $ln['quantity'] ?>
                                    <?php if (!empty($ln['lens_name']) || !empty($ln['prescription'])): ?>
                                        <?php /* Tròng cắt kèm đã nằm trong line_total — nói
                                                 tên nó ra để con số cao hơn giá gọng có lời
                                                 giải thích. */ ?>
                                        <span class="acct-sum__sub">
                                            <?= e(implode(' · ', array_filter([
                                                !empty($ln['lens_name']) ? '+ ' . $ln['lens_name'] : null,
                                                $ln['prescription'] ?? null,
                                            ]))) ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                                <span class="acct-sum__num"><?= money((int) $ln['line_total']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="acct-part">
                        <h3 class="acct-cap">Thanh toán</h3>

                        <div class="acct-sum">
                            <span>Tạm tính</span>
                            <span class="acct-sum__num"><?= money((int) $o['subtotal']) ?></span>
                        </div>

                        <?php if ((int) $o['discount'] > 0): ?>
                            <div class="acct-sum">
                                <span>Giảm giá</span>
                                <span class="acct-sum__num">−<?= money((int) $o['discount']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="acct-sum">
                            <span>Phí vận chuyển</span>
                            <span class="acct-sum__num"><?= (int) $o['shipping_fee'] === 0 ? 'Miễn phí' : money((int) $o['shipping_fee']) ?></span>
                        </div>

                        <div class="acct-sum acct-sum--total">
                            <span>Tổng cộng</span>
                            <span class="acct-sum__num"><?= money((int) $o['total']) ?></span>
                        </div>

                        <?php if ($deposit > 0): ?>
                            <?php /* Đơn cắt tròng theo độ: khách quay lại đây tra "hôm
                                     nhận kính phải cầm bao nhiêu", nên in cả hai vế. */ ?>
                            <div class="acct-sum">
                                <span>Đặt cọc <?= (int) ($o['deposit_rate'] ?? 0) ?>%</span>
                                <span class="acct-sum__num"><?= money($deposit) ?></span>
                            </div>
                            <div class="acct-sum">
                                <span>Còn lại khi nhận hàng</span>
                                <span class="acct-sum__num"><?= money((int) $o['total'] - $deposit) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($daTra): ?>
                            <?php
                            /* ĐÃ TRẢ bao nhiêu, bằng đường nào, ngày nào — ba thứ khách
                               đối chiếu với app ngân hàng. Đơn mới nhận cọc thì số đã
                               nhận là phần cọc; paid_at chỉ có khi trả đủ nên lùi về
                               mốc cập nhật gần nhất. */
                            $gotAmount = $payState === 'deposit_paid' && $deposit > 0 ? $deposit : (int) $o['total'];
                            $gotAt     = $o['paid_at'] ?: ($o['updated_at'] ?? null);
                            $gotVia    = utf8Lower($payment)
                                . ($o['payment_method'] === 'bank_transfer' && !empty($bank['name']) ? ' ' . $bank['name'] : '');
                            ?>
                            <span class="acct-mute">
                                Đã nhận <?= money($gotAmount) ?><?= $payState === 'deposit_paid' && $deposit > 0 ? ' (cọc ' . (int) ($o['deposit_rate'] ?? 0) . '%)' : '' ?>
                                qua <?= e($gotVia) ?><?= $gotAt !== null ? ' · ' . e(formatDate($gotAt, 'd/m/Y')) : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($needsTransfer): ?>
                        <?php
                        /* KHỐI CHUYỂN KHOẢN — thứ khách phải LÀM, không phải con số để
                           đọc, nên nó là một khung viền đen riêng. SỐ TIỀN đứng ở dòng
                           đầu: đó là con số khách gõ vào app và quyết định chuyển đúng
                           hay sai. NÚT CHÉP chỉ ở số tài khoản và nội dung — hai thứ gõ
                           sai thì tiền đi lạc hoặc không khớp được đơn. */
                        $payAmount = $deposit > 0 ? $deposit : (int) $o['total'];
                        $fields = [
                            ['Ngân hàng',             $bank['name'] ?? '',   null],
                            ['Chủ tài khoản',         $bank['holder'] ?? '', null],
                            ['Số tài khoản',          $bank['number'],       $bank['number']],
                            ['Nội dung chuyển khoản', $o['code'],            $o['code']],
                        ];
                        ?>
                        <div class="acct-pay" id="ck-<?= e($o['code']) ?>">
                            <div class="acct-head">
                                <h3 class="acct-head__label"><?= $deposit > 0 ? 'Chờ đặt cọc ' . (int) ($o['deposit_rate'] ?? 0) . '%' : 'Chờ thanh toán' ?></h3>
                                <span class="acct-pay__num"><?= money($payAmount) ?></span>
                            </div>

                            <div class="acct-grid2">
                                <?php foreach ($fields as [$label, $value, $copy]): ?>
                                    <div class="acct-kv">
                                        <span class="acct-cap"><?= e($label) ?></span>
                                        <span class="acct-kv__val">
                                            <span><?= e((string) $value) ?></span>
                                            <?php if ($copy !== null): ?>
                                                <button type="button" class="acct-act js-copy"
                                                        data-copy="<?= e((string) $copy) ?>">Sao chép</button>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <p class="acct-note">
                                Ghi đúng mã đơn <?= e($o['code']) ?> ở phần nội dung để chúng tôi đối chiếu được.
                            </p>

                            <?php /* Mã QR mang sẵn số tiền và nội dung — gõ tay 10 chữ số
                                     vào app ngân hàng là đúng chỗ người ta gõ sai. */ ?>
                            <a class="acct-btn acct-btn--solid"
                               href="/thanh-toan/chuyen-khoan?ma=<?= e(rawurlencode($o['code'])) ?>">Quét mã QR để thanh toán</a>
                        </div>
                    <?php endif; ?>

                    <?php if (!$daHuy && $marks !== []): ?>
                        <div class="acct-part">
                            <h3 class="acct-cap">Tiến trình</h3>
                            <?php foreach ($flow as $buoc): ?>
                                <?php if (isset($marks[$buoc])): ?>
                                    <div class="acct-sum">
                                        <span><?= e(OrderModel::nhanTrangThai($buoc, $o['delivery_method'] ?? null)) ?></span>
                                        <span class="acct-sum__num acct-mute"><?= e(formatDate($marks[$buoc], 'd/m/Y · H:i')) ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($moHuy): ?>
                    <?php
                    /* HỘP XÁC NHẬN HUỶ ĐƠN — bước 2 và 3 của UC-02. NÓI RÕ CHUYỆN
                       TIỀN TRƯỚC KHI HỎI. Số tiền là số cửa hàng ĐANG GIỮ (cùng phép
                       tính với sổ hoàn tiền), không phải deposit_amount — khách trả
                       đủ một lần thì cửa hàng giữ cả tổng đơn. */
                    $daNhan = RefundRequestModel::daNhan($o);
                    ?>
                    <form class="acct-card__more" method="post" action="/tai-khoan/don-hang/huy">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="code" value="<?= e($o['code']) ?>">

                        <h3 class="acct-label">Huỷ đơn <?= e($o['code']) ?>?</h3>

                        <p class="acct-note">
                            <?php if ($daNhan > 0): ?>
                                Cửa hàng đã nhận <?= e(money($daNhan)) ?> của đơn này. Sau khi huỷ, cửa hàng
                                sẽ xem xét hoàn lại và liên hệ với bạn. Số tiền hoàn phụ thuộc việc tròng đã
                                được cắt theo số đo của bạn hay chưa.
                            <?php else: ?>
                                Đơn này chưa phát sinh khoản thanh toán nào, nên huỷ không ảnh hưởng gì tới
                                tiền của bạn.
                            <?php endif; ?>
                        </p>

                        <label class="acct-field">
                            <span class="acct-field__cap">Lý do huỷ đơn</span>
                            <select class="acct-field__ctl" name="ly_do" required data-cancel-reason>
                                <option value="">Chọn lý do</option>
                                <?php foreach (OrderModel::LY_DO_HUY as $ma => $nhan): ?>
                                    <option value="<?= e($ma) ?>"><?= e($nhan) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $chevron ?>
                        </label>

                        <?php /* Ô tự do LUÔN HIỆN: không có JS thì khách chọn "Lý do
                                 khác" vẫn phải gõ được. Máy chủ chỉ đọc nó khi mã lý do
                                 là 'khac'. */ ?>
                        <label class="acct-field">
                            <span class="acct-field__cap">Ghi rõ hơn (bắt buộc nếu chọn "Lý do khác")</span>
                            <input class="acct-field__ctl" type="text" name="ly_do_khac" maxlength="200"
                                   placeholder="Ví dụ: đặt nhầm màu gọng">
                        </label>

                        <div class="acct-pair">
                            <a class="acct-btn" href="<?= $base ?>#<?= e($o['code']) ?>">Không huỷ nữa</a>
                            <button type="submit" class="acct-btn acct-btn--solid acct-btn--save">Xác nhận huỷ đơn</button>
                        </div>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php endif; ?>
