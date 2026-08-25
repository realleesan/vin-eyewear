<?php

/**
 * admin/dashboard/index.php — tổng quan
 * Port từ src/routes/_authenticated/quan-tri/index.tsx.
 */

/*
 * HAI HÀNG SỐ LIỆU, KHÔNG PHẢI TÁM THẺ BẰNG NHAU — theo "Vin Eyewear Admin.dc.html".
 *
 * Bản trước đổ cả tám con số vào một lưới auto-fill, nên ở 1440px chúng rơi
 * thành 6 + 2 lẻ: hai thẻ mồ côi ở hàng dưới, và mắt không đọc ra được thứ
 * nào quan trọng hơn thứ nào — doanh thu nằm ngang hàng với "số danh mục".
 *
 * Bản thiết kế tách làm hai tầng, và sự tách đó chính là nội dung:
 *
 *   HÀNG TRÊN — bốn thẻ lớn, đều là VIỆC ĐANG CHỜ NGƯỜI LÀM. Đơn mới, lịch
 *   hẹn chờ, liên hệ chưa xử lý: mở bảng quản trị buổi sáng là nhìn bốn ô
 *   này rồi bắt tay vào việc. Doanh thu đứng cùng hàng vì nó là thước đo của
 *   chính mấy việc đó.
 *
 *   DẢI DƯỚI — bốn con số TRẠNG THÁI KHO/NỘI DUNG. Biết để đấy, không ai
 *   "xử lý" số danh mục cả. Nó là một dải mảnh chia bốn ô, không phải bốn
 *   cái thẻ, nên không tranh chỗ với hàng trên.
 */

/* Doanh thu KHÔNG có liên kết — đúng bản thiết kế, và có lý do: ba thẻ kia
   mở ra một hàng chờ để xử lý, còn doanh thu thì không dẫn tới việc gì.
   Trỏ nó sang danh sách đơn hàng chỉ làm người ta bấm nhầm khi định xem
   đơn mới. */
$cards = [
    ['label' => 'Doanh thu',    'value' => money((int) $stats['revenue']),
     'note'  => 'không tính đơn đã huỷ', 'url' => null],
    ['label' => 'Đơn hàng mới', 'value' => (int) $stats['new_orders'],
     'note'  => 'chờ xác nhận',  'url' => '/quan-tri/don-hang'],
    ['label' => 'Lịch hẹn chờ', 'value' => (int) $stats['pending_appointments'],
     'note'  => 'chờ xác nhận',  'url' => '/quan-tri/lich-hen'],
    ['label' => 'Liên hệ mới',  'value' => (int) $stats['new_contacts'],
     'note'  => 'chưa xử lý',    'url' => '/quan-tri/lien-he'],
];

/* Dải trạng thái. 'warn' tô con số màu hổ phách — chỉ dùng cho "sắp hết
   hàng", vì đó là ô duy nhất trong dải mà một con số lớn là tin xấu. */
$facts = [
    ['value' => (int) $stats['products'],   'label' => 'sản phẩm đang hiển thị',
     'url'   => '/quan-tri/san-pham'],
    ['value' => (int) $stats['low_stock'],  'label' => 'sản phẩm sắp hết hàng',
     'url'   => '/quan-tri/ton-kho', 'warn' => true],
    ['value' => (int) $stats['categories'], 'label' => 'danh mục',
     'url'   => '/quan-tri/danh-muc'],
    ['value' => (int) $stats['events'],     'label' => 'bài sự kiện',
     'url'   => '/quan-tri/su-kien'],
];
?>
<header class="ahead">
    <h1 class="ahead__title">Tổng quan</h1>
    <?php /* Ngày hôm nay đứng cuối dòng dẫn, đúng bản thiết kế. Nó trả lời
             câu hỏi đầu tiên của người nhìn một bảng số: "số này tính tới lúc
             nào?" — nhất là khi trang được mở lại từ một tab để quên từ hôm
             qua. */ ?>
    <p class="ahead__lead">Số liệu tính trên toàn bộ dữ liệu hiện có · <?= e(date('d/m/Y')) ?></p>
</header>

<!-- ============ HÀNG TRÊN — VIỆC ĐANG CHỜ ============ -->
<ul class="astats" role="list">
    <?php foreach ($cards as $card): ?>
        <li>
            <?php /* Thẻ có liên kết dùng <a>, thẻ không có dùng <div>. Không
                     dùng <a> rỗng rồi chặn bằng CSS: nó vẫn nhận tiêu điểm bàn
                     phím và vẫn đọc lên là "liên kết" với trình đọc màn hình. */ ?>
            <?php if ($card['url'] !== null): ?>
                <a class="astat astat--link" href="<?= e($card['url']) ?>">
            <?php else: ?>
                <div class="astat">
            <?php endif; ?>

                <span class="astat__label"><?= e($card['label']) ?></span>
                <span class="astat__value"><?= e((string) $card['value']) ?></span>
                <span class="astat__note"><?= e($card['note']) ?></span>

            <?= $card['url'] !== null ? '</a>' : '</div>' ?>
        </li>
    <?php endforeach; ?>
</ul>

<!-- ============ DẢI DƯỚI — TRẠNG THÁI KHO / NỘI DUNG ============ -->
<?php /* Bản thiết kế vẽ dải này là chữ thường, không phải nút. Ở đây vẫn cho
         bấm được — bốn ô đều có một trang tương ứng, và người đọc "3 sản phẩm
         sắp hết hàng" thì việc kế tiếp gần như chắc chắn là mở trang tồn kho.
         Vẻ ngoài giữ nguyên như thiết kế: không gạch chân, không đổi màu; chỉ
         khi rê chuột mới hiện nền để lộ ra là bấm được. */ ?>
<ul class="afacts" role="list">
    <?php foreach ($facts as $fact): ?>
        <li class="afacts__cell">
            <a class="afacts__link" href="<?= e($fact['url']) ?>">
                <?php /* Tô hổ phách CHỈ KHI khác 0. "0 sản phẩm sắp hết hàng" là tin
                         tốt; sơn màu cảnh báo lên nó là dạy người đọc rằng màu ấy
                         không có nghĩa gì, và tới hôm số thật lên 5 thì họ đã quen
                         lướt qua. */ ?>
                <span class="afacts__num<?= (!empty($fact['warn']) && $fact['value'] > 0) ? ' afacts__num--warn' : '' ?>"><?= (int) $fact['value'] ?></span>
                <span class="afacts__label"><?= e($fact['label']) ?></span>
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
    <?php /* apanel--side: KHÔNG kéo cao bằng panel bên trái.
             Danh sách đơn hàng bên trái luôn dài hơn, nên để lưới tự giãn thì
             thẻ này thừa ra một mảng trắng bằng đúng phần chênh — trông như
             mất nội dung. Bản thiết kế cho nó align-self:start. */ ?>
    <section class="apanel apanel--side" aria-labelledby="recent-bookings">
        <div class="apanel__head">
            <?php /* Bản thiết kế đặt tên "Lịch hẹn sắp tới", nên DỮ LIỆU phải
                     đổi theo chứ không chỉ đổi cái tiêu đề: BookingModel
                     ::withStore() sắp xếp ngày GIẢM DẦN, tức là bày cả buổi hẹn
                     hôm qua. Một thẻ tên "sắp tới" mà liệt kê chuyện đã qua thì
                     tên ấy là nói dối, và người trực quầy sẽ chuẩn bị nhầm.
                     Truy vấn riêng nằm ở DashboardController::index(). */ ?>
            <h2 id="recent-bookings" class="apanel__title">Lịch hẹn sắp tới</h2>
            <a href="/quan-tri/lich-hen" class="apanel__more">Xem tất cả →</a>
        </div>

        <?php if ($recentBookings === []): ?>
            <p class="apanel__empty">Không có lịch hẹn nào sắp tới.</p>
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
                            <?php /* Chưa có giờ là trạng thái bình thường từ
                                     2026-08-25 — xem admin/appointments/index.php. */ ?>
                            <td><?= e($a['time_slot'] ?: 'Chưa chốt') ?></td>
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
