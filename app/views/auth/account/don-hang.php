<?php

/**
 * auth/account/don-hang.php — mục "Đơn hàng của tôi" (/tai-khoan?muc=don-hang).
 *
 * Bản thiết kế: dải thẻ lọc theo trạng thái, rồi mỗi đơn là một thẻ gồm
 * đầu thẻ (mã · ngày · huy hiệu trạng thái) → dòng sản phẩm → thanh tiến
 * trình → chân thẻ (nút hành động).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÀU HUY HIỆU TRẠNG THÁI
 *
 * Bản thiết kế đặt tên 5 trạng thái, OrderModel::STATUSES có 6. Bốn cặp màu
 * khớp thẳng; hai trạng thái 'confirmed' và 'preparing' cùng ứng với một
 * trạng thái duy nhất của bản thiết kế ("Đang chuẩn bị hàng") nên dùng chung
 * sắc chàm của nó. Chúng là hai bước liền nhau trong cùng một giai đoạn, và
 * chữ trên huy hiệu vẫn nói rõ đang ở bước nào.
 *
 * THANH TIẾN TRÌNH: NĂM BƯỚC, KHÔNG PHẢI BỐN
 * Bản thiết kế vẽ 4 chấm với dữ liệu mẫu 4 bước; vòng đời thật có 5 mốc.
 * Khối chấm là một danh sách lặp nên số lượng đi theo dữ liệu — 5 chấm dựng
 * bằng đúng thành phần ấy. Đơn đã huỷ thì không vẽ thanh này: một đường tiến
 * trình dừng giữa chừng trông như đơn đang kẹt chứ không phải đã huỷ.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$badgeTones = [
    'new'       => 'wait',
    'confirmed' => 'prep',
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

/* Nút chính ở chân thẻ, theo trạng thái. null = chỉ có nút "Xem chi tiết". */
$primaryLabels = [
    'new'       => 'Xem hướng dẫn thanh toán',
    'shipping'  => 'Theo dõi vận chuyển',
    'completed' => 'Mua lại',
    'cancelled' => 'Mua lại',
];

$deliveryLabels = ['pickup' => 'Nhận tại cửa hàng', 'shipping' => 'Giao tận nơi'];
$paymentLabels  = ['cod' => 'Thanh toán khi nhận hàng', 'bank_transfer' => 'Chuyển khoản ngân hàng'];
?>

<div class="acct-head">
    <h1 class="acct-head__title">Đơn hàng của tôi</h1>
    <p class="acct-head__lead">Theo dõi trạng thái và lịch sử mua kính của bạn.</p>
</div>

<div class="acct-tabs">
    <a class="acct-tab<?= $tab === '' ? ' is-active' : '' ?>" href="/tai-khoan?muc=don-hang">
        Tất cả (<?= (int) $total ?>)
    </a>
    <?php foreach ($statuses as $key => $label): ?>
        <a class="acct-tab<?= $tab === $key ? ' is-active' : '' ?>"
           href="/tai-khoan?muc=don-hang&amp;loc=<?= e($key) ?>">
            <?= e($label) ?><?= isset($tabCounts[$key]) ? ' (' . (int) $tabCounts[$key] . ')' : '' ?>
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
            $lead  = $lines[0] ?? null;          // dòng hàng in ra ngoài thẻ
            $extra = max(0, count($lines) - 1);  // số dòng còn lại, nêu ở "Xem chi tiết"
            $marks = $history[$o['id']] ?? [];
            $step  = array_search($o['status'], array_keys($flow), true);
            ?>
            <div class="acct-card acct-order" id="<?= e($o['code']) ?>">

                <div class="acct-order__top">
                    <div class="acct-order__id">
                        <span class="acct-order__code"><?= e($o['code']) ?></span>
                        <span class="acct-order__when">Đặt ngày <?= e(formatDate($o['created_at'])) ?></span>
                    </div>
                    <span class="acct-badge acct-badge--<?= e($badgeTones[$o['status']] ?? 'wait') ?>">
                        <?= e($statuses[$o['status']] ?? $o['status']) ?>
                    </span>
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
                            <img src="<?= e(asset($pic)) ?>" alt="" width="88" height="88" loading="lazy">
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

                            echo e($bits === [] ? $deliveryLabels[$o['delivery_method']] ?? '' : implode(' · ', $bits));
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

                <?php
                /* "Xem chi tiết" mở ngay tại chỗ chứ không sang trang khác —
                   bản thiết kế không vẽ trang chi tiết đơn nào, và mọi thứ cần
                   xem đều vừa trong thẻ này.

                   Mở/đóng bằng URL (?don=<mã>) chứ không bằng <details>: cùng
                   lý do đã ghi ở đầu app/views/auth/profile.php — gửi được
                   link tới đúng đơn đang hỏi, và F5 không đóng lại. */
                $isOpen = $expanded === $o['code'];
                $base   = '/tai-khoan?muc=don-hang' . ($tab !== '' ? '&amp;loc=' . e($tab) : '');
                ?>
                <div class="acct-order__foot">
                    <?php if (isset($primaryLabels[$o['status']])): ?>
                        <?php if ($primaryLabels[$o['status']] === 'Mua lại'): ?>
                            <form method="post" action="/tai-khoan/mua-lai">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="code" value="<?= e($o['code']) ?>">
                                <button type="submit" class="acct-btn acct-btn--primary acct-btn--sm">
                                    Mua lại
                                </button>
                            </form>
                        <?php elseif ($o['status'] === 'new'): ?>
                            <a class="acct-btn acct-btn--primary acct-btn--sm" href="/chinh-sach#thanh-toan">
                                <?= e($primaryLabels[$o['status']]) ?>
                            </a>
                        <?php else: ?>
                            <a class="acct-btn acct-btn--primary acct-btn--sm" href="/lien-he">
                                <?= e($primaryLabels[$o['status']]) ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <a class="acct-btn acct-btn--outline acct-btn--sm"
                       href="<?= $isOpen ? $base . '#' . e($o['code']) : $base . '&amp;don=' . e(rawurlencode($o['code'])) . '#' . e($o['code']) ?>"
                       aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                        <?= $isOpen ? 'Thu gọn' : 'Xem chi tiết' ?>
                    </a>
                </div>

                <?php if ($isOpen): ?>
                    <div class="acct-order__detail">
                        <table class="acct-order__items">
                            <caption class="sr-only">Các sản phẩm trong đơn <?= e($o['code']) ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col">Sản phẩm</th>
                                    <th scope="col">Đơn giá</th>
                                    <th scope="col">SL</th>
                                    <th scope="col">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lines as $ln): ?>
                                    <tr>
                                        <th scope="row">
                                            <?php if (!empty($ln['slug'])): ?>
                                                <a href="/san-pham/<?= e($ln['slug']) ?>"><?= e($ln['product_name']) ?></a>
                                            <?php else: ?>
                                                <?= e($ln['product_name']) ?>
                                            <?php endif; ?>
                                        </th>
                                        <td><?= money((int) $ln['unit_price']) ?></td>
                                        <td><?= (int) $ln['quantity'] ?></td>
                                        <td><?= money((int) $ln['line_total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row" colspan="3">Tạm tính</th>
                                    <td><?= money((int) $o['subtotal']) ?></td>
                                </tr>
                                <?php if ((int) $o['discount'] > 0): ?>
                                    <!-- Thiếu dòng này thì tạm tính + phí vận chuyển không
                                         ra tổng cộng, và hoá đơn trông như tính sai. -->
                                    <tr>
                                        <th scope="row" colspan="3">Giảm giá</th>
                                        <td>−<?= money((int) $o['discount']) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <th scope="row" colspan="3">Phí vận chuyển</th>
                                    <td><?= (int) $o['shipping_fee'] === 0 ? 'Miễn phí' : money((int) $o['shipping_fee']) ?></td>
                                </tr>
                                <tr class="acct-order__grand">
                                    <th scope="row" colspan="3">Tổng cộng</th>
                                    <td><?= money((int) $o['total']) ?></td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="acct-chips">
                            <span class="acct-chip">
                                <?= e($deliveryLabels[$o['delivery_method']] ?? $o['delivery_method']) ?><?php
                                    if (!empty($o['store_name'])): ?>: <?= e($o['store_name']) ?><?php endif; ?>
                            </span>
                            <span class="acct-chip"><?= e($paymentLabels[$o['payment_method']] ?? $o['payment_method']) ?></span>
                            <?php if (!empty($o['shipping_address'])): ?>
                                <span class="acct-chip">Giao tới: <?= e($o['shipping_address']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($o['note'])): ?>
                            <p class="acct-order__note">Ghi chú: <?= e($o['note']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
