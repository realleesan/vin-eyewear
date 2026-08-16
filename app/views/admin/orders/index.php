<?php

/**
 * admin/orders/index.php — danh sách đơn hàng
 * Port từ src/routes/_authenticated/quan-tri/don-hang.tsx.
 */

$deliveryLabels = ['pickup' => 'Nhận tại cửa hàng', 'shipping' => 'Giao tận nơi'];
$paymentLabels  = ['cod' => 'COD', 'bank_transfer' => 'Chuyển khoản'];
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
                    <th scope="col">Tổng</th>
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

                        <td class="num"><?= money((int) $o['total']) ?></td>

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
