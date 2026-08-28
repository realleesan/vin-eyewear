<?php

/**
 * admin/orders/_drawer.php — ngăn kéo chi tiết một đơn, trượt vào từ mép phải.
 *
 * Dựng theo "Tab Đơn hàng.dc.html". Nhận qua partial():
 *   $order          — dòng `orders` kèm `store_name` và `items`
 *   $statuses       — [khoá => nhãn] trạng thái giao vận
 *   $payStatuses    — [khoá => nhãn] trạng thái tiền
 *   $quayLai        — địa chỉ quay về sau khi POST (giữ nguyên ?xem=)
 *   $dongUrl        — địa chỉ khi ĐÓNG ngăn kéo (chính trang này, bỏ ?xem=)
 *   $deliveryLabels · $paymentLabels
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ MỘT TRANG, KHÔNG PHẢI MỘT HỘP THOẠI
 *
 * Ngăn kéo hiện ra vì địa chỉ có ?xem=<id>, nên nút ✕ và lớp nền mờ đều là
 * thẻ <a> trỏ về chính trang này khi bỏ tham số ấy — không phải nút gọi
 * JavaScript. Tắt JS thì đóng mở vẫn chạy, chỉ là mỗi lần tải lại trang.
 *
 * Cái giá phải trả: mở một đơn là một lượt truy vấn. Đổi lại, chi tiết đơn có
 * đường dẫn riêng để gửi cho nhau, và bấm "quay lại" của trình duyệt làm đúng
 * việc người dùng chờ đợi. Với một bảng 20 dòng thì đó là món hời.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$paid = $order['payment_status'] === 'paid';
?>
<a class="aodim" href="<?= e($dongUrl) ?>" data-modal-close aria-label="Đóng chi tiết đơn hàng"></a>

<aside class="aodraw" aria-label="Chi tiết đơn <?= e($order['code']) ?>">
    <header class="aodraw__head">
        <div>
            <?php /* "Đơn DH-…" chứ không phải mã trần — theo bản thiết kế. Từ khi
                     đây là hộp thoại giữa màn hình chứ không còn là ngăn kéo dán
                     vào mép, nhan đề phải tự nói ra nó là nhan đề của cái gì. */ ?>
            <h2 class="aodraw__code">Đơn <?= e($order['code']) ?></h2>
            <p class="aodraw__when">Đặt lúc <?= e(formatDate($order['created_at'], 'd/m/Y H:i')) ?></p>
        </div>
        <a class="aodraw__x" href="<?= e($dongUrl) ?>" data-modal-close aria-label="Đóng">&times;</a>
    </header>

    <div class="aodraw__body">
        <div class="aodraw__state">
            <?php /* Form vệ tinh riêng của ngăn kéo. Id KHÁC form cùng đơn ở
                     trong bảng ("aost-<id>"): đơn đang mở thường cũng đang hiện
                     trên bảng, và hai form trùng id thì thuộc tính form= của cả
                     hai ô chọn cùng trỏ về form đầu tiên — bấm Lưu ở ngăn kéo
                     lại gửi giá trị của ô trong bảng. */ ?>
            <form class="aoghost" method="post" action="/quan-tri/don-hang/trang-thai" id="aodrawst">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
                <input type="hidden" name="id" value="<?= e($order['id']) ?>">
            </form>

            <div class="astatus">
                <label class="sr-only" for="aodraw-st">Trạng thái đơn <?= e($order['code']) ?></label>
                <select class="astatus__pick astatus__pick--lg astatus__pick--<?= e($order['status']) ?>"
                        id="aodraw-st" name="status" form="aodrawst"
                        data-status-pick
                        data-ma="<?= e($order['code']) ?>"
                        data-cu="<?= e($statuses[$order['status']] ?? $order['status']) ?>">
                    <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $order['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" form="aodrawst" class="aosave">Lưu</button>
            </div>

            <span class="aodraw__paid amoney__pay amoney__pay--<?= $paid ? 'paid' : 'unpaid' ?>">
                <?= e($payStatuses[$order['payment_status']] ?? $order['payment_status']) ?>
            </span>
        </div>

        <section class="aodraw__sec">
            <h2 class="aodraw__label">Khách hàng</h2>
            <p class="aodraw__name"><?= e($order['customer_name']) ?></p>
            <p class="aodraw__line"><?= e($order['customer_phone']) ?></p>
            <?php if (!empty($order['customer_email'])): ?>
                <p class="aodraw__line"><?= e($order['customer_email']) ?></p>
            <?php endif; ?>

            <?php /* ĐỊA CHỈ NGUYÊN VẸN, KHÔNG CẮT — đây là chỗ nó được đọc để
                     ghi lên phiếu gửi hàng. Bảng ngoài kia cố tình không in nó
                     vì cắt ngắn một địa chỉ là bỏ mất đúng phần phân biệt hai
                     đơn của cùng một khách. */ ?>
            <?php if (!empty($order['shipping_address'])): ?>
                <p class="aodraw__addr"><?= e($order['shipping_address']) ?></p>
            <?php endif; ?>

            <p class="aodraw__meta">
                <?= e($deliveryLabels[$order['delivery_method']] ?? $order['delivery_method']) ?>
                <?php if (!empty($order['store_name'])): ?>
                    · <?= e($order['store_name']) ?>
                <?php endif; ?>
                · <?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?>
            </p>
        </section>

        <?php if (!empty($order['note'])): ?>
            <section class="aodraw__sec">
                <h2 class="aodraw__label">Ghi chú của khách</h2>
                <p class="aodraw__note"><?= nl2br(e($order['note'])) ?></p>
            </section>
        <?php endif; ?>

        <section class="aodraw__sec">
            <h2 class="aodraw__label">Sản phẩm</h2>

            <ul class="aoline" role="list">
                <?php foreach ($order['items'] as $item): ?>
                    <li class="aoline__item">
                        <p class="aoline__top">
                            <span><?= e($item['product_name']) ?></span>
                            <span class="aoline__qty">× <?= (int) $item['quantity'] ?></span>
                        </p>

                        <?php if (!empty($item['variant_label'])): ?>
                            <p class="aoline__sub"><?= e($item['variant_label']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($item['lens_name'])): ?>
                            <p class="aoline__sub">+ <?= e($item['lens_name']) ?></p>
                        <?php endif; ?>

                        <?php
                        /* BẢNG SỐ ĐO — bốn cột đúng như bản thiết kế.
                           Đây là thứ nhân viên đối chiếu ngay trước khi mài
                           tròng, nên nó được cả một cái bảng chứ không phải một
                           dòng chữ: mắt so hai hàng số theo cột thì bắt được
                           ngay chỗ hai mắt lệch nhau.

                           Bóc không ra (chuỗi cũ, hoặc ai đó sửa tay trong
                           CSDL) thì in nguyên văn — số đo là dữ liệu y tế,
                           không được im lặng bỏ đi. Xem LensModel::parseRx(). */
                        $mat = LensModel::parseRx($item['prescription'] ?? null);
                        ?>

                        <?php if ($mat !== []): ?>
                            <table class="aorxtb">
                                <thead>
                                    <tr>
                                        <th scope="col">Mắt</th>
                                        <th scope="col">Cầu (SPH)</th>
                                        <th scope="col">Loạn (CYL)</th>
                                        <th scope="col">Trục (AXIS)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mat as $m): ?>
                                        <tr>
                                            <th scope="row"><?= e($m['eye']) ?></th>
                                            <td><?= e($m['sph'] ?? '—') ?></td>
                                            <td><?= e($m['cyl'] ?? '—') ?></td>
                                            <td><?= e($m['axis'] ?? '—') ?></td>
                                        </tr>
                                        <?php if ($m['note'] !== null): ?>
                                            <tr class="aorxtb__note">
                                                <td colspan="4"><?= e($m['eye']) ?>: <?= e($m['note']) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif (!empty($item['prescription'])): ?>
                            <p class="aoline__rx"><?= e($item['prescription']) ?></p>
                        <?php elseif (!empty($item['lens_name'])): ?>
                            <p class="aoline__rx">Chưa có số đo — đo tại cửa hàng</p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="aodraw__sec">
            <h2 class="aodraw__label">Thanh toán</h2>

            <?php /* Bản thiết kế chỉ vẽ một dòng "Tổng cộng". Ở đây có thêm ba
                     dòng phụ vì đơn thật có phí giao hàng, mã giảm giá và tiền
                     cọc — mà "tổng 3.890.000₫" không giải thích được vì sao nó
                     khác tổng tiền hàng, và đó đúng là câu khách gọi điện lên
                     hỏi. Dòng nào bằng 0 thì không in. */ ?>
            <dl class="aosum">
                <div class="aosum__row">
                    <dt>Tiền hàng</dt>
                    <dd><?= money((int) $order['subtotal']) ?></dd>
                </div>

                <?php if ((int) $order['shipping_fee'] > 0): ?>
                    <div class="aosum__row">
                        <dt>Phí giao hàng</dt>
                        <dd><?= money((int) $order['shipping_fee']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php if ((int) $order['discount'] > 0): ?>
                    <div class="aosum__row">
                        <dt>Giảm giá</dt>
                        <dd>−<?= money((int) $order['discount']) ?></dd>
                    </div>
                <?php endif; ?>

                <div class="aosum__row aosum__row--total">
                    <dt>Tổng cộng</dt>
                    <dd><?= money((int) $order['total']) ?></dd>
                </div>

                <?php if ((int) $order['deposit_amount'] > 0): ?>
                    <?php /* Tiền cọc đứng SAU tổng cộng chứ không trừ vào nó:
                             nó không làm đơn rẻ đi, nó chỉ nói phần nào đã trả
                             trước. Xem khối chú thích `deposit_amount` trong
                             database/schema.sql. */ ?>
                    <div class="aosum__row aosum__row--dep">
                        <dt>Đặt cọc (<?= (int) $order['deposit_rate'] ?>%)</dt>
                        <dd><?= money((int) $order['deposit_amount']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if (!empty($order['paid_at'])): ?>
                <p class="aodraw__meta">Tiền về lúc <?= e(formatDate($order['paid_at'], 'd/m/Y H:i')) ?></p>
            <?php endif; ?>

            <?php
            // Cùng một luật với nút trong bảng — xem chú thích ở orders/index.php.
            $canMark = !$paid
                && ($order['payment_method'] === 'bank_transfer' || $order['status'] === 'completed');
            ?>
            <?php if ($canMark || $paid): ?>
                <form method="post" action="/quan-tri/don-hang/thanh-toan">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
                    <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                    <input type="hidden" name="paid" value="<?= $paid ? '0' : '1' ?>">
                    <button type="submit" class="aodraw__pay<?= $paid ? ' aodraw__pay--ghost' : '' ?>">
                        <?= $paid ? 'Gỡ đánh dấu đã thanh toán' : 'Đã nhận tiền' ?>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</aside>
