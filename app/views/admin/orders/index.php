<?php

/**
 * admin/orders/index.php — danh sách đơn hàng
 * Port từ src/routes/_authenticated/quan-tri/don-hang.tsx.
 */

$deliveryLabels = ['pickup' => 'Nhận tại cửa hàng', 'shipping' => 'Giao tận nơi'];
$paymentLabels  = ['cod' => 'COD', 'bank_transfer' => 'Chuyển khoản'];
?>
<?php
/* Cột "Tổng" kiêm luôn cột TIỀN: số tiền và việc tiền đã về hay chưa phải đọc
   cùng nhau. Xem OrderModel::PAYMENT_STATUSES về việc vì sao trạng thái tiền
   KHÔNG nằm trong ô chọn trạng thái đơn ở cột bên cạnh. */
?>
<header class="ahead">
    <h1 class="ahead__title">Đơn hàng</h1>
    <p class="ahead__lead"><?= (int) $total ?> đơn<?= $totalPages > 1 ? ' · trang ' . $page . '/' . $totalPages : '' ?></p>
</header>

<?php partial('admin/_layout/filter-tabs', [
    'base' => '/quan-tri/don-hang', 'statuses' => $statuses,
    'counts' => $counts, 'current' => $status,
]); ?>

<?php if ($orders === []): ?>
    <p class="apanel__empty">Không có đơn hàng nào khớp bộ lọc.</p>
<?php else: ?>

    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Mã đơn</th>
                    <th scope="col">Khách hàng</th>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col">Nhận / Trả</th>
                    <th scope="col">Tổng / Thanh toán</th>
                    <th scope="col">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>
                            <code><?= e($o['code']) ?></code>
                            <span class="atable__sub"><?= e(formatDate($o['created_at'], 'd/m/Y H:i')) ?></span>
                        </td>

                        <td>
                            <?= e($o['customer_name']) ?>
                            <span class="atable__sub"><?= e($o['customer_phone']) ?></span>
                            <?php if (!empty($o['shipping_address'])): ?>
                                <span class="atable__sub"><?= e(excerpt($o['shipping_address'], 48)) ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php foreach ($items[$o['id']] ?? [] as $item): ?>
                                <span class="atable__line">
                                    <?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?>
                                </span>
                                <?php if (!empty($item['lens_name']) || !empty($item['prescription'])): ?>
                                    <?php /* GÓI TRÒNG + SỐ ĐO. Đây là nơi duy nhất
                                             nhân viên đọc chúng để mài — trang khách
                                             chỉ để khách soát lại. Thiếu ở đây thì
                                             phải mở từng đơn hoặc gọi hỏi lại. */ ?>
                                    <?php if (!empty($item['lens_name'])): ?>
                                        <span class="atable__sub">+ <?= e($item['lens_name']) ?></span>
                                    <?php endif; ?>
                                    <span class="atable__rx">
                                        <?= $item['prescription'] !== null
                                            ? e($item['prescription'])
                                            : 'Chưa có số đo — đo tại cửa hàng' ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </td>

                        <td>
                            <?= e($deliveryLabels[$o['delivery_method']] ?? $o['delivery_method']) ?>
                            <?php if (!empty($o['store_name'])): ?>
                                <!-- Cơ sở nhận hàng. Thiếu dòng này thì nhân viên
                                     không biết soạn hàng ở đâu và phải gọi hỏi khách. -->
                                <span class="atable__sub"><?= e($o['store_name']) ?></span>
                            <?php endif; ?>
                            <span class="atable__sub"><?= e($paymentLabels[$o['payment_method']] ?? $o['payment_method']) ?></span>
                        </td>

                        <td class="num">
                            <?= money((int) $o['total']) ?>

                            <?php $paid = $o['payment_status'] === 'paid'; ?>
                            <span class="badge badge--<?= $paid ? 'paid' : 'unpaid' ?>">
                                <?= e($payStatuses[$o['payment_status']] ?? $o['payment_status']) ?>
                            </span>

                            <?php if ($paid && !empty($o['paid_at'])): ?>
                                <span class="atable__sub"><?= e(formatDate($o['paid_at'], 'd/m/Y H:i')) ?></span>
                            <?php endif; ?>

                            <?php
                            /* Đơn COD chưa giao thì KHÔNG có nút này: tiền chỉ về
                               khi shipper giao xong, và lúc đó changeStatus() tự
                               đánh dấu. Hiện nút ở đây chỉ mời nhân viên ghi nhận
                               một khoản tiền chưa ai thu. */
                            $canMark = !$paid
                                && ($o['payment_method'] === 'bank_transfer' || $o['status'] === 'completed');
                            ?>
                            <?php if ($canMark || $paid): ?>
                                <!-- KHÔNG dùng .astatus__save: admin.css có luật
                                     `.js .astatus__save { display: none }` để ẩn nút
                                     "Lưu" khi ô chọn trạng thái tự gửi form, mà nút
                                     này thì luôn phải bấm được. -->
                                <form method="post" action="/quan-tri/don-hang/thanh-toan" class="apay">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                                    <input type="hidden" name="paid" value="<?= $paid ? '0' : '1' ?>">
                                    <button type="submit" class="apay__btn<?= $paid ? ' apay__btn--ghost' : '' ?>">
                                        <?= $paid ? 'Gỡ đánh dấu' : 'Đã nhận tiền' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>

                        <td>
                            <!-- Đổi trạng thái tại chỗ. onchange gửi form; không có
                                 JavaScript thì vẫn còn nút "Lưu" bên dưới. -->
                            <form method="post" action="/quan-tri/don-hang/trang-thai" class="astatus">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                                <label class="sr-only" for="st-<?= e($o['id']) ?>">Trạng thái đơn <?= e($o['code']) ?></label>
                                <select id="st-<?= e($o['id']) ?>" name="status" data-autosubmit>
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $o['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="astatus__save">Lưu</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pager" aria-label="Phân trang">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php $url = '/quan-tri/don-hang?' . http_build_query(array_filter(['status' => $status, 'page' => $i])); ?>
                <?php if ($i === $page): ?>
                    <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a class="pager__link" href="<?= e($url) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
