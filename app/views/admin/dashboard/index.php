<?php

/**
 * admin/dashboard/index.php — tổng quan
 * Port từ src/routes/_authenticated/quan-tri/index.tsx.
 */

$cards = [
    ['label' => 'Doanh thu',        'value' => money((int) $stats['revenue']), 'note' => 'không tính đơn đã huỷ', 'url' => '/quan-tri/don-hang'],
    ['label' => 'Đơn hàng',         'value' => (int) $stats['orders'],  'note' => $stats['new_orders'] . ' đơn mới', 'url' => '/quan-tri/don-hang'],
    ['label' => 'Lịch hẹn chờ',     'value' => (int) $stats['pending_appointments'], 'note' => 'chờ xác nhận', 'url' => '/quan-tri/lich-hen'],
    ['label' => 'Liên hệ mới',      'value' => (int) $stats['new_contacts'], 'note' => 'chưa xử lý', 'url' => '/quan-tri/lien-he'],
    ['label' => 'Sản phẩm',         'value' => (int) $stats['products'], 'note' => 'đang hiển thị', 'url' => '/quan-tri/san-pham'],
    ['label' => 'Sắp hết hàng',     'value' => (int) $stats['low_stock'], 'note' => 'tồn ≤ 5', 'url' => '/quan-tri/ton-kho'],
    ['label' => 'Danh mục',         'value' => (int) $stats['categories'], 'note' => 'đang hiển thị', 'url' => '/quan-tri/danh-muc'],
    ['label' => 'Sự kiện',          'value' => (int) $stats['events'], 'note' => 'đang hiển thị', 'url' => '/quan-tri/su-kien'],
];
?>
<header class="ahead">
    <h1 class="ahead__title">Tổng quan</h1>
    <p class="ahead__lead">Số liệu tính trên toàn bộ dữ liệu hiện có.</p>
</header>

<!-- ============ THẺ SỐ LIỆU ============ -->
<ul class="astats" role="list">
    <?php foreach ($cards as $card): ?>
        <li>
            <a class="astat" href="<?= e($card['url']) ?>">
                <span class="astat__label"><?= e($card['label']) ?></span>
                <span class="astat__value"><?= e((string) $card['value']) ?></span>
                <span class="astat__note"><?= e($card['note']) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="agrid">

    <!-- ============ ĐƠN HÀNG GẦN ĐÂY ============ -->
    <section class="apanel" aria-labelledby="recent-orders">
        <div class="apanel__head">
            <h2 id="recent-orders" class="apanel__title">Đơn hàng gần đây</h2>
            <a href="/quan-tri/don-hang" class="apanel__more">Xem tất cả →</a>
        </div>

        <?php if ($recentOrders === []): ?>
            <p class="apanel__empty">Chưa có đơn hàng nào.</p>
        <?php else: ?>
            <table class="atable">
                <thead>
                    <tr>
                        <th scope="col">Mã đơn</th>
                        <th scope="col">Khách</th>
                        <th scope="col">Trạng thái</th>
                        <th scope="col">Tổng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $o): ?>
                        <tr>
                            <td><code><?= e($o['code']) ?></code></td>
                            <td><?= e($o['customer_name']) ?></td>
                            <td><span class="badge badge--<?= e($o['status']) ?>"><?= e($orderStatuses[$o['status']] ?? $o['status']) ?></span></td>
                            <td class="num"><?= money((int) $o['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- ============ LỊCH HẸN GẦN ĐÂY ============ -->
    <section class="apanel" aria-labelledby="recent-bookings">
        <div class="apanel__head">
            <h2 id="recent-bookings" class="apanel__title">Lịch hẹn gần đây</h2>
            <a href="/quan-tri/lich-hen" class="apanel__more">Xem tất cả →</a>
        </div>

        <?php if ($recentBookings === []): ?>
            <p class="apanel__empty">Chưa có lịch hẹn nào.</p>
        <?php else: ?>
            <table class="atable">
                <thead>
                    <tr>
                        <th scope="col">Ngày</th>
                        <th scope="col">Giờ</th>
                        <th scope="col">Khách</th>
                        <th scope="col">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentBookings as $a): ?>
                        <tr>
                            <td><?= e(formatDate($a['appointment_date'])) ?></td>
                            <td><?= e($a['time_slot']) ?></td>
                            <td><?= e($a['full_name']) ?></td>
                            <td><span class="badge badge--<?= e($a['status']) ?>"><?= e($bookingStatuses[$a['status']] ?? $a['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- ============ SẮP HẾT HÀNG ============ -->
    <section class="apanel apanel--wide" aria-labelledby="low-stock">
        <div class="apanel__head">
            <h2 id="low-stock" class="apanel__title">Sắp hết hàng</h2>
            <a href="/quan-tri/ton-kho" class="apanel__more">Quản lý tồn kho →</a>
        </div>

        <?php if ($lowStock === []): ?>
            <p class="apanel__empty">Không có sản phẩm nào tồn ≤ 5.</p>
        <?php else: ?>
            <table class="atable">
                <thead>
                    <tr>
                        <th scope="col">SKU</th>
                        <th scope="col">Sản phẩm</th>
                        <th scope="col">Tồn</th>
                        <th scope="col">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStock as $p): ?>
                        <tr>
                            <td><code><?= e($p['sku']) ?></code></td>
                            <td><a href="/san-pham/<?= e(rawurlencode($p['slug'])) ?>"><?= e($p['name']) ?></a></td>
                            <td class="num<?= (int) $p['stock_quantity'] === 0 ? ' is-danger' : '' ?>"><?= (int) $p['stock_quantity'] ?></td>
                            <td><span class="badge badge--<?= e($p['status']) ?>"><?= $p['status'] === 'in_stock' ? 'Còn hàng' : 'Hết hàng' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
