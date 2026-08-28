<?php

/**
 * admin/orders/index.php — danh sách đơn hàng.
 *
 * Dựng theo "Tab Đơn hàng.dc.html" (Claude Design). So với bản trước, bản
 * thiết kế thêm bốn thứ: ô tìm + lọc theo ngày, cột tick để làm hàng loạt,
 * thanh hoàn tác, và ngăn kéo chi tiết bên phải.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CÁC FORM CỦA TỪNG DÒNG NẰM NGOÀI BẢNG
 *
 * Mỗi dòng cần ba form độc lập: tick chọn (thuộc form hàng loạt bao cả bảng),
 * đổi trạng thái, và ghi nhận tiền. HTML CẤM lồng <form> trong <form>, nên
 * không có cách nào đặt cả ba vào trong ô của bảng.
 *
 * Lối thoát là thuộc tính `form=` của HTML5: một ô nhập nằm ở đâu cũng được,
 * miễn trỏ đúng id của form nó thuộc về. Nên các form "vệ tinh" của từng dòng
 * đặt hết xuống cuối trang, còn ô chọn và nút bấm ở lại đúng chỗ bản thiết kế
 * vẽ chúng.
 *
 * Đã thử hai cách khác và bỏ:
 *   · một form bao tất cả, phân việc bằng nút — ô chọn trạng thái của 20 dòng
 *     cùng tên `status` thì cú gửi nào cũng mang theo 20 giá trị;
 *   · dựng form bằng JavaScript lúc bấm — tắt JS là mất cả bảng.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$deliveryLabels = ['pickup' => 'Nhận tại cửa hàng', 'shipping' => 'Giao tận nơi'];
$paymentLabels  = ['cod' => 'COD', 'bank_transfer' => 'Chuyển khoản'];

/* Bộ lọc hiện hành, để mọi đường dẫn sinh ra trên trang này (viên lọc, phân
   trang, nút mở ngăn kéo) đều giữ nguyên chỗ người dùng đang đứng. */
$locHienTai = array_filter([
    'status' => $status,
    'q'      => $q,
    'ngay'   => $range,
]);

$duongDanTrang = static function (int $so) use ($locHienTai): string {
    return '/quan-tri/don-hang?' . http_build_query($locHienTai + ($so > 1 ? ['page' => $so] : []));
};
?>
<?php /* Ô LỌC NGÀY VÀ Ô TÌM NẰM NGAY TRÊN DÒNG TIÊU ĐỀ, không phải một dải
         riêng bên dưới — theo bản thiết kế. Chúng cao 36px; cho chúng một dòng
         của riêng mình là mất 48px đầu trang trên MỌI lần mở, kể cả những lần
         không định lọc gì. Dòng tiêu đề thì lúc nào cũng có và bên phải nó
         đang trống.

         Dòng dẫn nay đếm ĐƠN MỚI CHỜ XÁC NHẬN thay vì số trang: số trang đã
         chuyển xuống chân bảng, nơi người ta thật sự cần nó (lúc bấm sang
         trang khác), còn đầu trang thì câu hỏi là "hôm nay có gì phải làm". */ ?>
<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Đơn hàng</h1>
        <p class="ahead__lead">
            <?= (int) $total ?> đơn<?php if ((int) ($counts['new'] ?? 0) > 0): ?>
                · <?= (int) $counts['new'] ?> đơn mới chờ xác nhận
            <?php endif; ?>
        </p>
    </div>

    <?php /* GET chứ không POST: bộ lọc phải nằm trên địa chỉ để bấm F5, bấm
             quay lại, hay gửi đường dẫn cho đồng nghiệp đều ra đúng danh sách
             ấy. Không mang `page` theo — tìm từ khoá mới thì phải về trang 1,
             chứ không phải trang 3 của kết quả cũ. */ ?>
    <form class="aofind" method="get" action="/quan-tri/don-hang" role="search">
        <?php if ($status !== ''): ?>
            <input type="hidden" name="status" value="<?= e($status) ?>">
        <?php endif; ?>

        <label class="sr-only" for="aoNgay">Lọc theo ngày tạo đơn</label>
        <select class="aofind__range" id="aoNgay" name="ngay" data-autosubmit>
            <?php foreach ($ranges as $key => $label): ?>
                <option value="<?= e((string) $key) ?>"<?= $range === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="sr-only" for="aoQ">Tìm đơn hàng</label>
        <input class="aofind__input" id="aoQ" type="search" name="q" value="<?= e($q) ?>"
               placeholder="Tìm mã đơn, tên khách, SĐT…">

        <?php /* KHÔNG dùng .astatus__save: admin.css ẩn lớp đó trong mọi form
                 có ô chọn tự gửi, mà form này có một cái (ô lọc ngày). Nút
                 "Tìm" thì luôn phải bấm được — gõ từ khoá xong không có nút
                 nào để bấm là một ô tìm hỏng. */ ?>
        <button class="aofind__go" type="submit">Tìm</button>

        <?php if ($q !== '' || $range !== ''): ?>
            <a class="aofind__clear" href="/quan-tri/don-hang<?= $status !== '' ? '?status=' . e($status) : '' ?>">Xoá lọc</a>
        <?php endif; ?>
    </form>
</header>

<?php
/* THANH CÔNG CỤ DÍNH ĐẦU MÀN HÌNH.

   Bảng đơn dài 20 dòng, và thao tác thường gặp nhất là "lọc rồi cuộn tìm".
   Cuộn xuống mà dải viên lọc trôi mất thì đổi bộ lọc phải cuộn ngược lên —
   bản thiết kế dính nó lại đúng vì thế. Xem .aotools trong admin-orders.css. */
?>
<div class="aotools">
    <?php partial('admin/_layout/filter-tabs', [
        'base' => '/quan-tri/don-hang', 'statuses' => $statuses,
        'counts' => $counts, 'current' => $status,
        // Giữ từ khoá và khoảng ngày khi bấm sang viên lọc khác — không có nó,
        // gõ "0915" rồi bấm "Đang giao" là mất luôn từ vừa gõ.
        'keep' => ['q' => $q, 'ngay' => $range],
    ]); ?>
</div>


<?php if ($orders === []): ?>
    <?php /* Câu chữ khác nhau theo lý do rỗng: bảng chưa có đơn nào là chuyện
             bình thường của cửa hàng mới mở, còn "lọc xong không thấy gì" thì
             người dùng cần biết là bộ lọc đang chặn chứ không phải mất dữ
             liệu. */ ?>
    <p class="apanel__empty">
        <?= $status !== '' || $q !== '' || $range !== ''
            ? 'Không có đơn nào khớp bộ lọc.'
            : 'Chưa có đơn hàng nào.' ?>
    </p>
<?php else: ?>

    <form class="aoform" method="post" action="/quan-tri/don-hang/hang-loat">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">

        <div class="atable-wrap">
            <table class="atable aotable">
                <thead>
                    <tr>
                        <th class="aotable__tick" scope="col">
                            <?php
                            /* Ô "chọn tất cả" LÀ THỨ DUY NHẤT TRÊN TRANG NÀY
                               CẦN JAVASCRIPT — nó chỉ có một việc là tick 20 ô
                               khác, mà HTML thuần không làm được.

                               Nên nó ra đời ở trạng thái `hidden` và chỉ
                               admin-orders.js mở ra. Đúng nếp đã ghi ở
                               assets/js/admin.js: thà không có gì còn hơn để
                               lại một ô bấm vào không làm gì. */
                            ?>
                            <input class="aopick aopick--all" type="checkbox" id="aoAll" hidden
                                   aria-label="Chọn tất cả đơn trên trang">
                        </th>
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
                        <?php
                        $paid   = $o['payment_status'] === 'paid';
                        $dangMo = ($drawer['id'] ?? null) === $o['id'];
                        // ?xem= chồng lên bộ lọc hiện tại, không thay nó
                        $urlXem = '/quan-tri/don-hang?' . http_build_query(
                            $locHienTai + ($page > 1 ? ['page' => $page] : []) + ['xem' => $o['id']]
                        );
                        ?>
                        <tr class="aorow<?= $dangMo ? ' is-open' : '' ?>" data-open="<?= e($urlXem) ?>">
                            <td class="aotable__tick">
                                <input class="aopick" type="checkbox" name="ids[]" value="<?= e($o['id']) ?>"
                                       aria-label="Chọn đơn <?= e($o['code']) ?>">
                            </td>

                            <td>
                                <?php /* Mã đơn là ĐƯỜNG DẪN THẬT tới ngăn kéo chi
                                         tiết. Cả dòng cũng bấm được (JS chuyển
                                         hướng tới đúng địa chỉ này), nhưng cái
                                         bấm được khi tắt JS phải là một thẻ <a>
                                         thật — và nó cũng là thứ mở được bằng
                                         bàn phím. */ ?>
                                <a class="aocode" href="<?= e($urlXem) ?>"><?= e($o['code']) ?></a>
                                <span class="atable__sub aowhen"><?= e(formatDate($o['created_at'], 'd/m/Y H:i')) ?></span>
                            </td>

                            <td>
                                <span class="aoname"><?= e($o['customer_name']) ?></span>
                                <span class="atable__sub"><?= e($o['customer_phone']) ?></span>
                            </td>

                            <td class="aoitems">
                                <?php /* ĐỊA CHỈ GIAO KHÔNG CÒN Ở ĐÂY — nó chuyển
                                         xuống ngăn kéo, theo bản thiết kế. Cắt
                                         một địa chỉ xuống 48 ký tự thì phần bị
                                         cắt luôn là số nhà và tên xã, tức phần
                                         duy nhất phân biệt hai đơn của cùng một
                                         khách. Đọc nguyên vẹn trong ngăn kéo
                                         hơn là đọc dở dang trong bảng. */ ?>
                                <?php foreach ($items[$o['id']] ?? [] as $item): ?>
                                    <span class="atable__line">
                                        <?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?>
                                    </span>

                                    <?php if (!empty($item['lens_name'])): ?>
                                        <?php /* VIÊN NHÃN "Kèm đơn kính" chứ không phải dấu
                                                 cộng dẫn đầu — theo bản thiết kế.

                                                 Trong một cột dày ba tới bốn dòng chữ nhỏ,
                                                 "+ Hai tròng · Tròng trắng 1.50" đọc lướt
                                                 không khác gì dòng tên sản phẩm ngay trên
                                                 nó. Mà đây là dấu hiệu DUY NHẤT cho biết
                                                 đơn này phải qua khâu mài tròng, tức là
                                                 khâu duy nhất có thể làm sai số đo của một
                                                 người thật. Nó phải bắt mắt được.

                                                 Sắc tím là màu riêng của "đơn kính" trong
                                                 bản thiết kế, không dùng lại ở đâu khác —
                                                 nên nó không lẫn với sáu màu trạng thái
                                                 đơn ở cột bên cạnh. */ ?>
                                        <span class="aolens">
                                            <span class="aolens__tag">Kèm đơn kính</span>
                                            <span class="aolens__name"><?= e($item['lens_name']) ?></span>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($item['lens_name']) || !empty($item['prescription'])): ?>
                                        <?php
                                        /* GÓI TRÒNG + SỐ ĐO. Đây là nơi duy nhất
                                           nhân viên đọc chúng để mài — trang khách
                                           chỉ để khách soát lại. Thiếu ở đây thì
                                           phải mở từng đơn hoặc gọi hỏi lại.

                                           Bản thiết kế xếp mỗi mắt một dòng, mở
                                           đầu bằng viên "MP"/"MT": chuỗi gộp cũ
                                           ("MP −2.00 / −1.25 × 180° · MT …") đọc
                                           trong một ô hẹp là phải dò mắt tìm dấu
                                           chấm giữa để biết chỗ nào sang mắt kia.

                                           Bóc không ra thì in nguyên văn chuỗi
                                           gốc — số đo là dữ liệu y tế, không
                                           được im lặng bỏ đi. */
                                        $mat = LensModel::parseRx($item['prescription'] ?? null);
                                        ?>
                                        <?php if ($mat !== []): ?>
                                            <?php foreach ($mat as $m): ?>
                                                <span class="aorx">
                                                    <span class="aorx__eye"><?= e($m['abbr']) ?></span>
                                                    <span class="aorx__num">
                                                        Cầu <?= e($m['sph'] ?? '—') ?><?php
                                                        if ($m['cyl'] !== null): ?> · Loạn <?= e($m['cyl']) ?><?php endif;
                                                        if ($m['axis'] !== null): ?> · Trục <?= e($m['axis']) ?><?php endif;
                                                        ?>
                                                    </span>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="atable__rx">
                                                <?= !empty($item['prescription'])
                                                    ? e($item['prescription'])
                                                    : 'Chưa có số đo — đo tại cửa hàng' ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>

                            <td class="aship">
                                <?php /* Cách NHẬN hàng dẫn đầu, cách TRẢ tiền lặng hẳn
                                         bên dưới: cái đầu quyết định việc phải làm tiếp
                                         (soạn hàng gửi đi hay để dành ở quầy), cái sau
                                         chỉ là ghi chú đi kèm. Xem .aship trong
                                         assets/css/admin.css. */ ?>
                                <span class="aship__how">
                                    <?= e($deliveryLabels[$o['delivery_method']] ?? $o['delivery_method']) ?>
                                </span>
                                <?php if (!empty($o['store_name'])): ?>
                                    <!-- Cơ sở nhận hàng. Thiếu dòng này thì nhân viên
                                         không biết soạn hàng ở đâu và phải gọi hỏi khách. -->
                                    <span class="atable__sub"><?= e($o['store_name']) ?></span>
                                <?php endif; ?>
                                <span class="aship__pay">
                                    <?= e($paymentLabels[$o['payment_method']] ?? $o['payment_method']) ?>
                                </span>
                            </td>

                            <td class="amoney">
                                <?php
                                /*
                                 * TRẠNG THÁI TIỀN LÀ CHẤM TRÒN + CHỮ, KHÔNG PHẢI VIÊN NHÃN.
                                 *
                                 * Trước đây chỗ này là .badge--paid/.badge--unpaid —
                                 * một viên viền rỗng nằm sát viên nhãn trạng thái ĐƠN ở
                                 * cột bên. Hai viên cùng cỡ, cùng độ bo, nói về hai
                                 * chuyện khác hẳn nhau: mắt phải đọc chữ mới biết viên
                                 * nào là tiền, viên nào là đơn.
                                 *
                                 * Đơn giữ viên nhãn, tiền xuống thành dòng chữ có chấm
                                 * màu dẫn đầu. Khác dáng thì không phải đọc mới phân
                                 * biệt được. Xem .amoney__pay trong admin.css.
                                 */
                                ?>
                                <span class="amoney__inner">
                                    <span class="amoney__total"><?= money((int) $o['total']) ?></span>

                                    <span class="amoney__pay amoney__pay--<?= $paid ? 'paid' : 'unpaid' ?>">
                                        <?= e($payStatuses[$o['payment_status']] ?? $o['payment_status']) ?>
                                    </span>

                                    <?php if ($paid && !empty($o['paid_at'])): ?>
                                        <?php /* Giờ tiền về — thứ nhân viên đối chiếu với
                                                 sao kê ngân hàng. */ ?>
                                        <span class="amoney__when"><?= e(formatDate($o['paid_at'], 'd/m/Y H:i')) ?></span>
                                    <?php endif; ?>
                                </span>

                                <?php
                                /* Đơn COD chưa giao thì KHÔNG có nút này: tiền chỉ về
                                   khi shipper giao xong, và lúc đó changeStatus() tự
                                   đánh dấu. Hiện nút ở đây chỉ mời nhân viên ghi nhận
                                   một khoản tiền chưa ai thu. */
                                $canMark = !$paid
                                    && ($o['payment_method'] === 'bank_transfer' || $o['status'] === 'completed');
                                ?>
                                <?php if ($canMark || $paid): ?>
                                    <?php /* <div> chứ không <span>: .apay trong admin.css
                                             có margin-top, mà margin dọc thì không ăn vào
                                             thẻ inline. Trước đây lớp này đeo trên một
                                             <form>, nên chuyện đó không lộ ra. */ ?>
                                    <div class="apay">
                                        <?php /* form= trỏ tới form vệ tinh ở cuối trang —
                                                 xem khối chú thích đầu file. */ ?>
                                        <button type="submit" form="aopay-<?= e($o['id']) ?>"
                                                class="apay__btn<?= $paid ? ' apay__btn--ghost' : '' ?>">
                                            <?= $paid ? 'Gỡ đánh dấu' : 'Đã nhận tiền' ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="astatus">
                                    <label class="sr-only" for="st-<?= e($o['id']) ?>">Trạng thái đơn <?= e($o['code']) ?></label>
                                    <?php /* data-cu / data-ma nuôi câu hỏi lại của
                                             admin-orders.js ("DH-…: «Mới» → «Đã
                                             giao»?"). Không có JS thì không có
                                             câu hỏi, nhưng vẫn còn thanh hoàn tác
                                             sau khi đổi — xem cuối file. */ ?>
                                    <select class="astatus__pick astatus__pick--<?= e($o['status']) ?>"
                                            id="st-<?= e($o['id']) ?>" name="status"
                                            form="aost-<?= e($o['id']) ?>"
                                            data-status-pick
                                            data-ma="<?= e($o['code']) ?>"
                                            data-cu="<?= e($statuses[$o['status']] ?? $o['status']) ?>">
                                        <?php foreach ($statuses as $key => $label): ?>
                                            <option value="<?= e($key) ?>"<?= $o['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php /* Lớp RIÊNG, không dùng .astatus__save: luật ẩn
                                             nút Lưu trong admin.css tìm ô chọn NẰM TRONG
                                             form (`form:has(select[data-autosubmit])`), mà
                                             ô chọn ở đây nằm ngoài form của nó. Luật ẩn
                                             tương ứng ở admin-orders.css. */ ?>
                                    <button type="submit" form="aost-<?= e($o['id']) ?>" class="aosave">Lưu</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            /* CHÂN BẢNG NẰM TRONG KHUNG, KHÔNG PHẢI DƯỚI KHUNG — theo bản
               thiết kế.

               Nó thuộc về cái bảng chứ không thuộc về trang: "đang hiện 8 /
               33 đơn" chỉ có nghĩa khi đọc cùng mấy dòng ngay trên nó. Thả ra
               ngoài, trên nền ngà, thì nó trôi lửng giữa bảng và thanh hàng
               loạt và đọc ra như một câu chú thích của cả trang.

               Nằm trong .atable-wrap nghĩa là nó CUỘN NGANG cùng bảng — cũng
               đúng bản thiết kế (min-width bằng bảng, xem .aofoot trong
               admin-orders.css). Nếu để nó đứng yên trong khi bảng trượt thì
               ở màn hẹp con số phân trang rời khỏi cột nó đang nói về. */
            ?>
            <div class="aofoot">
                <p class="aofoot__count">
                    Đang hiện <?= count($orders) ?> / <?= (int) $total ?> đơn
                    <?php if ($totalPages > 1): ?>· trang <?= (int) $page ?>/<?= (int) $totalPages ?><?php endif; ?>
                </p>

                <?php if ($totalPages > 1): ?>
                    <nav class="pager" aria-label="Phân trang">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
                            <?php else: ?>
                                <a class="pager__link" href="<?= e($duongDanTrang($i)) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>

        <?php
        /* THANH HÀNG LOẠT — HIỆN KHI CÓ Ô ĐƯỢC TICK, KHÔNG CẦN JAVASCRIPT.

           `:has()` làm được đúng việc ấy bằng CSS thuần (xem admin-orders.css).
           Và luật viết theo chiều ẨN chứ không theo chiều HIỆN là cố ý: trình
           duyệt không hiểu :has() sẽ bỏ nguyên luật đó, thanh này hiện suốt —
           xấu hơn nhưng vẫn bấm được, và bấm khi chưa tick gì thì controller
           trả về "Chưa chọn đơn hàng nào.". Viết theo chiều ngược lại thì ở
           đúng những trình duyệt ấy thanh không bao giờ hiện ra. */
        ?>
        <div class="aobulk">
            <?php /* Không có JS thì con số không đếm được — câu chữ mặc định phải
                     đọc trôi cả khi thiếu nó. admin-orders.js thay bằng
                     "N đơn đã chọn". */ ?>
            <span class="aobulk__count" data-bulk-count>Đơn đã chọn</span>

            <label class="sr-only" for="aoBulkStatus">Chuyển trạng thái các đơn đã chọn</label>
            <select class="aobulk__sel" id="aoBulkStatus" name="status">
                <option value="">Chuyển trạng thái…</option>
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="aobulk__go" type="submit" name="act" value="trang-thai">Áp dụng</button>

            <?php /* Chỉ có chiều ĐÁNH DẤU, không có chiều gỡ — gỡ hàng loạt là
                     xoá sạch mốc tiền về của một loạt đơn, thứ không dựng lại
                     được. Xem OrderAdminController::bulkPayment(). */ ?>
            <button class="aobulk__pay" type="submit" name="act" value="thanh-toan"
                    data-confirm="Ghi nhận đã nhận đủ tiền cho các đơn đã chọn?"
                    data-confirm-title="Ghi nhận thanh toán?"
                    data-confirm-ok="Ghi nhận">Đã nhận tiền</button>

            <button class="aobulk__clear" type="reset">Bỏ chọn</button>
        </div>
    </form>

    <?php
    /* CÁC FORM VỆ TINH của từng dòng — lý do chúng ở đây, xem đầu file.
       Chúng không hiện gì cả; mọi nút và ô chọn của chúng nằm trong bảng. */
    ?>
    <?php foreach ($orders as $o): ?>
        <form class="aoghost" method="post" action="/quan-tri/don-hang/trang-thai" id="aost-<?= e($o['id']) ?>">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
            <input type="hidden" name="id" value="<?= e($o['id']) ?>">
        </form>

        <?php if ($o['payment_status'] === 'paid'
            || $o['payment_method'] === 'bank_transfer'
            || $o['status'] === 'completed'): ?>
            <form class="aoghost" method="post" action="/quan-tri/don-hang/thanh-toan" id="aopay-<?= e($o['id']) ?>">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
                <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                <input type="hidden" name="paid" value="<?= $o['payment_status'] === 'paid' ? '0' : '1' ?>">
            </form>
        <?php endif; ?>
    <?php endforeach; ?>

<?php endif; ?>

<?php if ($undo !== null): ?>
    <?php
    /* THANH HOÀN TÁC — đường lùi cho thao tác vừa làm.
       Nó là một FORM THẬT chứ không phải một nút JavaScript: đổi trạng thái đã
       ghi vào CSDL và đã ghi một mốc vào order_status_history, nên lùi lại
       cũng phải là một thao tác ghi có vết. Xem OrderAdminController::undoStatus().

       Trạng thái cũ đi kèm ngay trong form, không nằm trong session — lý do
       đầy đủ ở chú thích của hàm ấy. */
    ?>
    <form class="aoundo" method="post" action="/quan-tri/don-hang/hoan-tac">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
        <?php foreach ($undo['truoc'] as $id => $cu): ?>
            <input type="hidden" name="truoc[<?= e((string) $id) ?>]" value="<?= e((string) $cu) ?>">
        <?php endforeach; ?>

        <span class="aoundo__msg"><?= e((string) ($undo['msg'] ?? 'Đã đổi trạng thái')) ?></span>
        <button class="aoundo__go" type="submit">Hoàn tác</button>
    </form>
<?php endif; ?>

<?php if ($drawer !== null): ?>
    <?php partial('admin/orders/_drawer', [
        'order'          => $drawer,
        'statuses'       => $statuses,
        'payStatuses'    => $payStatuses,
        'quayLai'        => $quayLai,
        'deliveryLabels' => $deliveryLabels,
        'paymentLabels'  => $paymentLabels,
        // Địa chỉ khi ĐÓNG ngăn kéo: đúng trang này, đúng bộ lọc, bỏ ?xem=
        'dongUrl'        => currentUrlWithout(['xem']),
    ]); ?>
<?php endif; ?>
