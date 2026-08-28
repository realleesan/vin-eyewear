<?php

/**
 * admin/dashboard/index.php — tổng quan
 * Dựng theo "Tổng quan.dc.html" (Claude Design).
 */

/*
 * BA TẦNG, KHÔNG PHẢI MỘT ĐỐNG THẺ BẰNG NHAU — theo bản thiết kế.
 *
 * Bản đầu tiên đổ cả tám con số vào một lưới auto-fill, nên ở 1440px chúng
 * rơi thành 6 + 2 lẻ: hai thẻ mồ côi ở hàng dưới, và mắt không đọc ra được
 * thứ nào quan trọng hơn thứ nào — doanh thu nằm ngang hàng với "số danh mục".
 *
 * Bản thiết kế tách làm ba tầng, và sự tách đó chính là nội dung:
 *
 *   HÀNG TRÊN — bốn thẻ lớn. Thẻ đầu NỀN TỐI là KẾT QUẢ (tiền), ba thẻ nền
 *   sáng còn lại là VIỆC ĐANG CHỜ NGƯỜI LÀM. Mở bảng quản trị buổi sáng là
 *   nhìn hàng này rồi bắt tay vào việc. Vì sao thẻ tiền đảo nền chứ không tô
 *   chữ đỏ như trước: xem .astat--money trong admin.css.
 *
 *   DẢI GIỮA — ba con số TRẠNG THÁI KHO/NỘI DUNG. Biết để đấy, không ai
 *   "xử lý" số danh mục cả. Thẻ thấp hơn, bo nhỏ hơn một nấc, nên không
 *   tranh chỗ với hàng trên.
 *
 *   KHỐI DƯỚI — ba danh sách thật, chia hai cột 1.55 / 1.
 */

/* Doanh thu KHÔNG có liên kết — đúng bản thiết kế, và có lý do: ba thẻ kia
   mở ra một hàng chờ để xử lý, còn doanh thu thì không dẫn tới việc gì.
   Trỏ nó sang danh sách đơn hàng chỉ làm người ta bấm nhầm khi định xem
   đơn mới. Bù lại nó là thẻ DUY NHẤT không có dòng "→" ở chân. */

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * THẺ TIỀN MANG HAI CON SỐ, KHÔNG PHẢI MỘT
 *
 * DOANH THU là tiền đã về đủ. TẠM THU là tiền cọc đang giữ của đơn mới trả
 * 30% — có thật trong tài khoản, nhưng chưa phải doanh thu vì hàng chưa giao
 * và đơn còn huỷ được.
 *
 * VÌ SAO KHÔNG TÁCH THÀNH THẺ THỨ NĂM: .astats là lưới CỐ ĐỊNH 4 cột (xem
 * admin.css). Thẻ thứ năm rơi xuống hàng dưới một mình, và đó đúng là lỗi bố
 * cục mà bản thiết kế đã sửa khi gom tám con số thành 4 + 3.
 *
 * Cho tạm thu làm dòng phụ CÙNG THẺ lại hợp nghĩa hơn là một thẻ riêng: người
 * đọc cần thấy hai con số CẠNH NHAU mới hiểu ra ranh giới giữa chúng. Tách ra
 * hai đầu bảng thì chúng thành hai chỉ số không liên quan.
 *
 * HIỆN CẢ KHI BẰNG 0, cố ý. "Tạm thu 0 ₫" nói một điều có ích: không còn đơn
 * nào đang nợ phần còn lại. Ẩn đi thì thẻ đổi hình dạng theo dữ liệu, và người
 * dùng không học được là có hai loại tiền khác nhau.
 *
 * DÒNG TẠM THU KHÔNG CÒN GHI CHÚ PHỤ ("2 đơn mới trả cọc") — bản thiết kế bỏ.
 * Thẻ này đã dày hơn ba thẻ bên cạnh vì có thêm cả một dòng kẻ; thêm dòng thứ
 * năm nữa thì nó cao hơn hẳn và kéo cả hàng giãn theo. Con số đơn cọc vẫn đọc
 * được ở /quan-tri/don-hang, nơi lọc được theo trạng thái tiền.
 * ─────────────────────────────────────────────────────────────────────────────
 */
$soDonDaThu = (int) ($tien['so_don_da_thu'] ?? 0);

$cards = [
    ['label' => 'Doanh thu',
     'value' => money((int) ($tien['doanh_thu'] ?? 0)),
     /* Ghi chú nói rõ ĐIỀU KIỆN, không phải một câu xã giao. Người nhìn một
        con số tiền câu đầu tiên hỏi là "gồm những gì" — và khi có mốc thì câu
        thứ hai là "từ bao giờ". Trả lời cả hai ngay tại chỗ.

        Đếm 0 đơn KHÔNG in ra "từ 0 đơn": đó là câu đọc lên đã thấy sai. Nó là
        trạng thái riêng, và với bảng vừa đặt mốc thì đây là trạng thái BÌNH
        THƯỜNG chứ không phải hỏng — chữ nghĩa phải nói ra điều đó. */
     'note'  => $soDonDaThu > 0
         ? 'từ ' . $soDonDaThu . ' đơn đã thu đủ tiền'
         : ($mocThongKe !== null
             ? 'chưa có đơn nào thu đủ tiền từ mốc này'
             : 'chưa có đơn nào thu đủ tiền'),
     'url'   => null,
     'class' => 'astat--money',
     'extra' => [
         'label' => 'Tạm thu (tiền cọc)',
         'value' => money((int) ($tien['tam_thu'] ?? 0)),
     ]],

    /* 'cta' — dòng "→" ở chân thẻ. Nó nói cú bấm DẪN TỚI VIỆC GÌ, không lặp
       lại tên thẻ: "Liên hệ chưa đẩy" bấm vào ra đâu thì không ai đoán được,
       "Đẩy sang Zalo" mới là câu trả lời. */
    ['label' => 'Đơn hàng mới', 'value' => (int) $stats['new_orders'],
     'note'  => 'chờ xác nhận', 'url' => '/quan-tri/don-hang',
     'cta'   => 'Xử lý ngay →'],
    ['label' => 'Lịch hẹn chờ', 'value' => (int) $stats['pending_appointments'],
     'note'  => 'chờ xác nhận', 'url' => '/quan-tri/lich-hen',
     'cta'   => 'Xem lịch hẹn →'],
    /* Ô này từng là "Liên hệ mới · chưa xử lý", đọc cột `contact_requests`.`status`
       — bỏ ngày 2026-08-26 cùng cả cột đó. Yêu cầu liên hệ nay chạy thẳng sang
       Zalo CSKH lúc khách bấm gửi, nên "chưa xử lý" không còn nghĩa gì: không
       ai xử lý nó trong bảng quản trị nữa.

       Thứ thay vào là một con số ĐÁNG LO HƠN HẲN: yêu cầu chưa tới được Zalo.
       Nó phải luôn bằng 0; khác 0 nghĩa là ZNS đang hỏng và có người thật đang
       chờ gọi lại mà CSKH chưa biết. */
    ['label' => 'Liên hệ chưa đẩy', 'value' => (int) $stats['contacts_chua_day'],
     'note'  => 'chưa tới Zalo CSKH', 'url' => '/quan-tri/lien-he',
     'cta'   => 'Đẩy sang Zalo →'],
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
    /* Ô thứ tư từng là "bài sự kiện" -> /quan-tri/su-kien, bỏ 2026-08-26 cùng
       tính năng sự kiện. Dải nay còn BA ô; .afacts là flex nên thêm hay bớt ô
       không phải chỉnh lưới, chúng tự chia đều. */
];
?>
<?php /* ahead--row: tiêu đề bên trái, ngày hôm nay bên phải — đúng bản thiết
         kế. Ngày đứng cuối dòng dẫn vì nó trả lời câu hỏi đầu tiên của người
         nhìn một bảng số: "số này tính tới lúc nào?" — nhất là khi trang được
         mở lại từ một tab để quên từ hôm qua. */ ?>
<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Tổng quan</h1>
        <?php /* DÒNG NÀY PHẢI NÓI RA MỐC ĐANG ÁP.

                 Không có nó thì đặt STATS_SINCE trong .env xong chẳng có cách
                 nào biết nó đã ăn chưa — con số tụt xuống, mà tụt vì mốc hay
                 vì tính sai thì nhìn không ra. */ ?>
        <p class="ahead__lead">
            <?php if ($mocThongKe !== null): ?>
                Tiền tính từ <strong><?= e(formatDate($mocThongKe, 'd/m/Y')) ?></strong>, không tính đơn đã huỷ
            <?php else: ?>
                Số liệu trên toàn bộ dữ liệu, không tính đơn đã huỷ
            <?php endif; ?>
        </p>
    </div>
    <p class="ahead__today">Hôm nay · <?= e(date('d/m/Y')) ?></p>
</header>

<!-- ============ HÀNG TRÊN — TIỀN + VIỆC ĐANG CHỜ ============ -->
<ul class="astats" role="list">
    <?php foreach ($cards as $card): ?>
        <li>
            <?php /* Thẻ có liên kết dùng <a>, thẻ không có dùng <div>. Không
                     dùng <a> rỗng rồi chặn bằng CSS: nó vẫn nhận tiêu điểm bàn
                     phím và vẫn đọc lên là "liên kết" với trình đọc màn hình. */ ?>
            <?php if ($card['url'] !== null): ?>
                <a class="astat astat--link" href="<?= e($card['url']) ?>">
            <?php else: ?>
                <div class="astat <?= e($card['class'] ?? '') ?>">
            <?php endif; ?>

                <span class="astat__label"><?= e($card['label']) ?></span>
                <span class="astat__value"><?= e((string) $card['value']) ?></span>
                <span class="astat__note"><?= e($card['note']) ?></span>

                <?php /* Dòng phụ chỉ thẻ tiền mới có. Ngăn bằng một đường kẻ
                         mảnh chứ không bằng khoảng trắng: hai con số cùng đơn
                         vị nghìn đồng đứng sát nhau mà không có ranh giới thì
                         mắt đọc thành một cụm. */ ?>
                <?php if (!empty($card['extra'])): ?>
                    <span class="astat__extra">
                        <span class="astat__extra-label"><?= e($card['extra']['label']) ?></span>
                        <span class="astat__extra-value"><?= e($card['extra']['value']) ?></span>
                    </span>
                <?php endif; ?>

                <?php if (!empty($card['cta'])): ?>
                    <span class="astat__cta"><?= e($card['cta']) ?></span>
                <?php endif; ?>

            <?= $card['url'] !== null ? '</a>' : '</div>' ?>
        </li>
    <?php endforeach; ?>
</ul>

<!-- ============ DẢI GIỮA — TRẠNG THÁI KHO / NỘI DUNG ============ -->
<?php /* Bản thiết kế vẽ dải này là chữ thường, không phải nút. Ở đây vẫn cho
         bấm được — ba ô đều có một trang tương ứng, và người đọc "4 sản phẩm
         sắp hết hàng" thì việc kế tiếp gần như chắc chắn là mở trang tồn kho.
         Vẻ ngoài giữ nguyên như thiết kế: không gạch chân, không đổi màu; chỉ
         khi rê chuột mới sáng viền để lộ ra là bấm được. */ ?>
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
    <?php /* DANH SÁCH, KHÔNG PHẢI BẢNG — xem khối ghi chú ở .alist trong
             admin.css. Bốn cột trong một thẻ rộng 600px thì tên khách bị cắt
             trong khi cột trạng thái thừa khoảng trắng. */ ?>
    <section class="apanel" aria-labelledby="recent-orders">
        <div class="apanel__head">
            <h2 id="recent-orders" class="apanel__title">Đơn hàng gần đây</h2>
            <a href="/quan-tri/don-hang" class="apanel__more">Xem tất cả →</a>
        </div>

        <?php if ($recentOrders === []): ?>
            <p class="apanel__empty">Chưa có đơn hàng nào.</p>
        <?php else: ?>
            <ul class="alist" role="list">
                <?php foreach ($recentOrders as $o): ?>
                    <li class="alist__row">
                        <div class="alist__main">
                            <span class="alist__code"><?= e($o['code']) ?></span>
                            <span class="alist__name"><?= e($o['customer_name']) ?></span>
                        </div>
                        <div class="alist__side">
                            <span class="badge badge--<?= e($o['status']) ?>"><?= e($orderStatuses[$o['status']] ?? $o['status']) ?></span>
                            <span class="alist__num"><?= money((int) $o['total']) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php /* Chân thẻ trả lời câu hỏi mà mọi danh sách bị cắt ngắn đều
                     gây ra: mấy dòng này là mấy dòng đầu, hay là tất cả những
                     gì đang có? Con số bên phải là TỔNG SỐ ĐƠN, không phải số
                     đơn mới — "xem tất cả" thì phải là tất cả. */ ?>
            <div class="apanel__foot">
                <span class="apanel__count">Đang hiện <?= count($recentOrders) ?> đơn mới nhất</span>
                <a href="/quan-tri/don-hang" class="apanel__more">Xem tất cả <?= (int) $stats['orders_total'] ?> đơn →</a>
            </div>
        <?php endif; ?>
    </section>

    <?php /* Cột phải gộp hai thẻ vào MỘT ô lưới — xem .agrid__col trong
             admin.css. Để chúng làm hai ô riêng thì lưới chia đôi chiều cao
             của cột trái cho hai thẻ, cả hai đều thừa ra một mảng trắng. */ ?>
    <div class="agrid__col">

        <!-- ============ LỊCH HẸN SẮP TỚI ============ -->
        <?php /* Thẻ này VẪN LÀ BẢNG: ba cột ngắn đều nhau, trong đó cột ngày là
                 dãy số cần xếp thẳng hàng để so — việc mà bảng làm tốt hơn
                 danh sách. */ ?>
        <section class="apanel" aria-labelledby="recent-bookings">
            <?php /* head--plain: ngay dưới là dòng tiêu đề cột đã có nền ngà
                     riêng, thêm đường kẻ nữa thành hai vạch sát nhau. */ ?>
            <div class="apanel__head apanel__head--plain">
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
                            <th scope="col">Khách</th>
                            <th scope="col">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $a): ?>
                            <tr>
                                <td><?= e(formatDate($a['appointment_date'])) ?></td>
                                <td><?= e($a['full_name']) ?></td>
                                <td><span class="badge badge--<?= e($a['status']) ?>"><?= e($bookingStatuses[$a['status']] ?? $a['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="apanel__foot">
                    <span class="apanel__count"><?= count($recentBookings) ?> lịch gần nhất</span>
                    <a href="/quan-tri/lich-hen" class="apanel__more">Xem tất cả <?= (int) $stats['upcoming_appointments'] ?> lịch →</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- ============ SẮP HẾT HÀNG ============ -->
        <section class="apanel" aria-labelledby="low-stock">
            <div class="apanel__head">
                <h2 id="low-stock" class="apanel__title">Sắp hết hàng</h2>
                <a href="/quan-tri/ton-kho" class="apanel__more">Quản lý tồn kho →</a>
            </div>

            <?php if ($lowStock === []): ?>
                <p class="apanel__empty">Không có sản phẩm nào tồn ≤ 5.</p>
            <?php else: ?>
                <ul class="alist" role="list">
                    <?php foreach ($lowStock as $p): ?>
                        <?php
                        /* NHÃN ĐỌC CỘT `status`, KHÔNG TỰ SUY TỪ SỐ TỒN.
                           Một sản phẩm còn 1 cái trong kho vẫn có thể đã bị tắt
                           bán (hàng lỗi, hàng giữ cho khách đặt riêng) — lúc đó
                           `status` là 'out_of_stock' dù số tồn khác 0, và người
                           trực quầy cần thấy đúng điều đó chứ không thấy "Sắp
                           hết" rồi hứa với khách.

                           Chỉ có HAI nhãn ở thẻ này: cả danh sách đã lọc sẵn
                           tồn ≤ 5, nên "Còn hàng" là câu vô nghĩa ở đây. */
                        $conBan = ($p['status'] ?? '') !== 'out_of_stock';
                        ?>
                        <li class="alist__row">
                            <div class="alist__main">
                                <?php /* Trỏ về TỒN KHO, không về trang bán hàng của sản
                                         phẩm. Người đọc thẻ "Sắp hết hàng" đang định
                                         sửa số tồn, không định xem ảnh sản phẩm. */ ?>
                                <a class="alist__name alist__name--lead" href="/quan-tri/ton-kho"><?= e($p['name']) ?></a>
                                <span class="alist__code"><?= e($p['sku']) ?></span>
                            </div>
                            <div class="alist__side">
                                <span class="alist__num alist__num--tight<?= (int) $p['stock_quantity'] === 0 ? ' alist__num--danger' : '' ?>"><?= (int) $p['stock_quantity'] ?></span>
                                <span class="badge badge--<?= $conBan ? 'low_stock' : 'out_of_stock' ?>"><?= $conBan ? 'Sắp hết' : 'Hết hàng' ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="apanel__foot">
                    <span class="apanel__count">Ưu tiên tồn thấp nhất</span>
                    <a href="/quan-tri/ton-kho" class="apanel__more">Xem tất cả →</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
