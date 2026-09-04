<?php

/**
 * admin/orders/_drawer.php — HỘP THOẠI chi tiết một đơn, nổi giữa màn hình.
 *
 * TÊN LỚP CSS `aodraw`/`aodim` LÀ DẤU VẾT CŨ, đừng đọc nó thành "drawer":
 * bản đầu đúng là một ngăn kéo trượt từ mép phải, nhưng nó đã thành hộp giữa
 * màn hình từ lâu (xem .aodraw trong admin-orders.css: inset:0 + margin:auto +
 * width:min(680px,96vw) + max-height:90vh). Câu mở đầu của file này vẫn ghi
 * "ngăn kéo … trượt vào từ mép phải" cho tới 2026-08-29, và nó đủ để một lượt
 * soát kết luận sai rằng màn này lệch bản vẽ.
 *
 * Dựng theo "Đơn hàng.dc.html". Nhận qua partial():
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

/* Cùng một luật với nút trong bảng — xem chú thích ở orders/index.php.
   Tính ở ĐẦU file vì nút dùng nó nay nằm ở chân hộp, sau cả vùng cuộn. */
$canMark = !$paid
    && ($order['payment_method'] === 'bank_transfer' || $order['status'] === 'completed');
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
        <?php /* Ô CHỌN TRẠNG THÁI NẰM Ở ĐẦU HỘP, cạnh nút × — theo bản vẽ.

                 Nó là thao tác chính của cả hộp này: mở một đơn ra gần như
                 luôn là để đẩy nó sang bước tiếp theo. Để tuốt trong ruột thì
                 với đơn có bảng số đo dài, người dùng phải cuộn ngược lên tìm.

                 Form vệ tinh id "aodrawst" KHÁC form cùng đơn ở trong bảng
                 ("aost-<id>"): đơn đang mở thường cũng đang hiện trên bảng, mà
                 hai form trùng id thì thuộc tính form= của cả hai ô chọn cùng
                 trỏ về form đầu tiên — bấm Lưu ở đây lại gửi giá trị của ô
                 trong bảng. */ ?>
        <form class="aoghost" method="post" action="/quan-tri/don-hang/trang-thai" id="aodrawst">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
            <input type="hidden" name="id" value="<?= e($order['id']) ?>">
        </form>

        <div class="aodraw__acts">
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

            <?php
            /* KHÁCH ĐANG ĐỌC CHỮ GÌ — B9.

               Ô chọn bên trên luôn hiện nhãn nội bộ ("Đang giao"), nhưng với đơn
               NHẬN TẠI QUẦY thì trang tài khoản của khách hiện "Chờ khách nhận".
               Không nói ra thì nhân viên nghe khách đọc một chữ lạ qua điện
               thoại và không đối chiếu được với màn hình của mình.

               Chỉ hiện khi HAI CHỮ KHÁC NHAU — thêm một dòng lặp lại đúng chữ
               vừa đọc ở trên là nhiễu. */
            $nhanKhach = OrderModel::nhanTrangThai(
                (string) $order['status'],
                $order['delivery_method'] ?? null
            );
            ?>
            <?php if ($nhanKhach !== ($statuses[$order['status']] ?? '')): ?>
                <p class="aomolai__hint aomolai__hint--block">
                    Khách đang thấy trạng thái này là «<strong><?= e($nhanKhach) ?></strong>».
                </p>
            <?php endif; ?>

            <?php /* Ô LÝ DO CHỈ HIỆN KHI ĐƠN ĐANG Ở "ĐÃ HUỶ" — Q3.1.

                     Hiện thường trực thì nó là một ô trống bên cạnh mọi đơn,
                     và một ô bắt buộc chỉ đôi khi bắt buộc là thứ người ta học
                     cách bỏ qua. Máy chủ vẫn kiểm lại
                     (OrderAdminController::updateStatus) — ô này biến mất
                     không có nghĩa là luật biến mất. */ ?>
            <?php
            /*
             * HAI THAO TÁC ĐI NGƯỢC CHIỀU VÒNG ĐỜI, CÙNG MỘT Ô LÝ DO.
             *
             *   đơn đang 'cancelled'  -> mọi cú đổi là MỞ LẠI (Q3.1)
             *   đơn đang 'completed'  -> có thể LÙI về một nấc trước
             *
             * Cả hai đều chỉ Quản trị viên làm được và đều bắt lý do tối thiểu
             * LY_DO_TOI_THIEU ký tự, nên chúng dùng chung đúng một ô — hai ô
             * riêng cho hai luật giống hệt nhau chỉ làm người dùng phải đoán
             * xem lần này phải điền ô nào.
             *
             * Nhãn thì PHẢI khác nhau: "Lý do mở lại đơn đã huỷ" đặt trên một
             * đơn đang Hoàn tất là một câu sai.
             */
            $diNguoc = in_array((string) $order['status'], ['cancelled', 'completed'], true);
            $laHuy   = (string) $order['status'] === 'cancelled';
            ?>
            <?php if ($diNguoc): ?>
                <?php if (!empty($order['la_admin'])): ?>
                    <div class="aomolai">
                        <label class="aomolai__lb" for="aodraw-lydo">
                            <?= $laHuy ? 'Lý do mở lại đơn đã huỷ' : 'Lý do lùi đơn đã Hoàn tất' ?>
                        </label>
                        <input class="aomolai__in" type="text" id="aodraw-lydo"
                               name="ly_do" form="aodrawst" maxlength="255"
                               placeholder="<?= $laHuy
                                   ? 'Ví dụ: nhân viên bấm nhầm, khách vẫn lấy hàng'
                                   : 'Ví dụ: giao nhầm địa chỉ, hàng đang lấy về đổi lại' ?>">
                        <p class="aomolai__hint">
                            <?= $laHuy
                                ? 'Bắt buộc khi mở lại đơn, tối thiểu '
                                : 'Bắt buộc khi lùi về một nấc trước, tối thiểu ' ?>
                            <?= (int) OrderModel::LY_DO_TOI_THIEU ?> ký tự.
                            Lý do lưu cùng mốc trạng thái và vào nhật ký thao tác.
                            <?php if (!$laHuy): ?>
                                Đơn COD đã Hoàn tất thì hệ thống đã ghi nhận thu đủ tiền —
                                lùi lại không hoàn tiền, phải đối chiếu sổ bằng tay.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <p class="aomolai__hint aomolai__hint--block">
                        <?= $laHuy
                            ? 'Đơn đã huỷ — chỉ Quản trị viên mở lại được, kèm lý do (Q3.1).'
                            : 'Đơn đã Hoàn tất — chỉ Quản trị viên lùi lại được, kèm lý do.' ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            /*
             * ─────────────────────────────────────────────────────────────────
             * THẺ XÁC NHẬN HUỶ — BƯỚC HAI, DO MÁY CHỦ MỞ RA
             *
             * Ô chọn trạng thái TỰ GỬI FORM khi đổi, nên chọn "Đã huỷ" là xong
             * ngay — không có khoảnh khắc nào để đọc lại. Mà huỷ đơn là thao tác
             * duy nhất ở màn này có người thứ ba chịu hậu quả, và với đơn đã mài
             * thì hậu quả ấy là TIỀN CỦA KHÁCH (FR-25).
             *
             * Nên cú POST đầu tiên không huỷ gì cả: nó quay về đây với
             * ?xac-nhan-huy=<id> và mở tấm thẻ này. Toàn bộ bước xác nhận nằm ở
             * MÁY CHỦ, nên tắt JavaScript vẫn còn nguyên — khác hẳn một hộp
             * confirm() vốn biến mất cùng JS.
             * ─────────────────────────────────────────────────────────────────
             */
            ?>
            <?php if (!empty($order['xac_nhan_huy'])): ?>
                <div class="aohuy">
                    <p class="aohuy__tieu">Xác nhận huỷ đơn <?= e($order['code']) ?></p>

                    <?php if (!empty($order['da_mai'])): ?>
                        <p class="aohuy__canh">
                            Đơn này <strong>đã bắt đầu mài tròng</strong>. Tròng đã cắt theo số đo
                            riêng của khách nên không bán lại cho ai khác được — huỷ bây giờ thì
                            khách <strong>không còn được hoàn 100% tiền cọc</strong>
                            (<?= money((int) $order['deposit_amount']) ?>).
                        </p>
                    <?php endif; ?>

                    <form method="post" action="/quan-tri/don-hang/trang-thai" class="aohuy__form">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
                        <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <input type="hidden" name="xn_huy" value="1">

                        <label class="aomolai__lb" for="aohuy-lydo">Lý do huỷ đơn</label>
                        <input class="aomolai__in" type="text" id="aohuy-lydo"
                               name="ly_do" maxlength="255" required
                               placeholder="Ví dụ: khách báo không lấy nữa, đã gọi xác nhận">
                        <p class="aomolai__hint">
                            Bắt buộc, tối thiểu <?= (int) OrderModel::LY_DO_TOI_THIEU ?> ký tự.
                        </p>

                        <?php /* Ô TICK RIÊNG, không gộp vào nút bấm: người huỷ
                                 phải chạm vào đúng câu nói ra hệ quả về tiền,
                                 không phải lướt qua nó. */ ?>
                        <?php if (!empty($order['da_mai'])): ?>
                            <label class="aohuy__tick">
                                <input type="checkbox" name="xn_mai" value="1" required>
                                <span>Tôi hiểu khách không còn được hoàn 100% tiền cọc.</span>
                            </label>
                        <?php endif; ?>

                        <div class="aohuy__nut">
                            <button type="submit" class="aohuy__ok">Huỷ đơn này</button>
                            <a class="aohuy__thoi" href="<?= e($dongUrl) ?>">Thôi, không huỷ</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <a class="aodraw__x" href="<?= e($dongUrl) ?>" data-modal-close aria-label="Đóng">&times;</a>
        </div>
    </header>

    <div class="aodraw__body">
        <?php /* HAI THẺ CẠNH NHAU — theo bản vẽ: "Khách hàng" và "Giao nhận &
                 thanh toán". Xếp dọc thì hai nhóm này đọc thành một khối dài,
                 mà chúng trả lời hai câu khác nhau: gọi ai, và giao thế nào. */ ?>
        <div class="aodraw__grid">
            <section class="aodraw__card">
                <h2 class="aodraw__label">Khách hàng</h2>
                <p class="aodraw__name"><?= e($order['customer_name']) ?></p>
                <p class="aodraw__line"><?= e($order['customer_phone']) ?></p>
                <?php if (!empty($order['customer_email'])): ?>
                    <p class="aodraw__line"><?= e($order['customer_email']) ?></p>
                <?php endif; ?>

                <?php /* ĐỊA CHỈ NGUYÊN VẸN, KHÔNG CẮT — đây là chỗ nó được đọc
                         để ghi lên phiếu gửi hàng. Bảng ngoài kia cố tình không
                         in nó vì cắt ngắn một địa chỉ là bỏ mất đúng phần phân
                         biệt hai đơn của cùng một khách. */ ?>
                <?php if (!empty($order['shipping_address'])): ?>
                    <p class="aodraw__addr"><?= e($order['shipping_address']) ?></p>
                <?php endif; ?>
            </section>

            <section class="aodraw__card">
                <h2 class="aodraw__label">Giao nhận &amp; thanh toán</h2>
                <p class="aodraw__name">
                    <?= e($deliveryLabels[$order['delivery_method']] ?? $order['delivery_method']) ?>
                    <?php if (!empty($order['store_name'])): ?>
                        · <?= e($order['store_name']) ?>
                    <?php endif; ?>
                </p>
                <p class="aodraw__line">
                    <?= e($paymentLabels[$order['payment_method']] ?? $order['payment_method']) ?>
                    ·
                    <span class="amoney__pay amoney__pay--<?= $paid ? 'paid' : 'unpaid' ?>">
                        <?= e($payStatuses[$order['payment_status']] ?? $order['payment_status']) ?>
                    </span>
                </p>
            </section>
        </div>

        <?php
        /* ─────────────────────────────────────────────────────────────────────
           MỐC "BẮT ĐẦU MÀI" — Q2.2 · X07, chốt 04/09/2026

           Khối này CHỈ hiện với đơn có dịch vụ mài lắp tròng. Đơn mua gọng
           trần không bao giờ đi qua mốc này, và một nút vô nghĩa vẫn là một
           nút người ta sẽ bấm.

           Đặt ngay trên danh sách sản phẩm, không nhét xuống cuối: đây là mốc
           quyết định tiền cọc có hoàn hay không, tức là thứ người mở đơn ra
           cần thấy trước khi trả lời khách "huỷ thì em có được hoàn không".
           ───────────────────────────────────────────────────────────────────── */
        ?>
        <?php if (!empty($order['co_trong'])): ?>
            <section class="aodraw__sec aomai<?= !empty($order['da_mai']) ? ' aomai--on' : '' ?>">
                <h2 class="aodraw__label">Mài lắp tròng</h2>

                <?php if (empty($order['da_mai'])): ?>
                    <p class="aomai__state">
                        Chưa bắt đầu mài — huỷ bây giờ thì khách còn được hoàn 100% cọc.
                    </p>

                    <?php if (!empty($order['la_quan_ly'])): ?>
                        <form class="aomai__form" method="post"
                              action="/quan-tri/don-hang/bat-dau-mai">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
                            <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                            <button class="aomai__go" type="submit">Bắt đầu mài</button>
                        </form>
                        <p class="aomai__hint">
                            Rút lại được trong <?= (int) (OrderModel::RUT_LAI_GIAY / 60) ?> phút.
                            Sau đó phải ghi lý do.
                        </p>
                    <?php else: ?>
                        <?php /* X07: người bấm là Quản lý cơ sở trở lên. Kỹ thuật
                                 viên vẫn có vai trò riêng nhưng phạm vi của nó là
                                 hồ sơ khúc xạ (Q77.2), không phải nút này. */ ?>
                        <p class="aomai__hint">
                            Chỉ Quản lý cơ sở trở lên bấm được nút này.
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="aomai__state">
                        Đã bắt đầu mài lúc
                        <?= e(formatDate($order['mai_bat_dau_luc'], 'd/m/Y H:i')) ?>
                        — huỷ từ đây thì <strong>không hoàn 100% cọc</strong>.
                    </p>

                    <?php if (!empty($order['rut_lai_duoc'])): ?>
                        <?php /* CÒN TRONG CỬA SỔ và ĐÚNG NGƯỜI VỪA BẤM: gỡ thẳng,
                                 không hỏi lý do. Đây là đường sửa cái bấm nhầm,
                                 mà bắt viết một câu giải thích cho cái bấm nhầm
                                 vừa xảy ra ba mươi giây trước chỉ khiến người ta
                                 gõ bừa cho qua. */ ?>
                        <form class="aomai__form" method="post"
                              action="/quan-tri/don-hang/huy-mai">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
                            <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                            <button class="aomai__undo" type="submit">Rút lại</button>
                        </form>
                        <p class="aomai__hint">
                            Bấm nhầm? Rút lại được trong
                            <?= (int) (OrderModel::RUT_LAI_GIAY / 60) ?> phút đầu.
                        </p>
                    <?php elseif (!empty($order['la_quan_ly'])): ?>
                        <form class="aomai__form" method="post"
                              action="/quan-tri/don-hang/huy-mai">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="quay_lai" value="<?= e($quayLai) ?>">
                            <input type="hidden" name="id" value="<?= e($order['id']) ?>">
                            <label class="sr-only" for="aomai-lydo">Lý do đảo ngược</label>
                            <input class="aomai__in" type="text" id="aomai-lydo"
                                   name="ly_do" maxlength="255"
                                   placeholder="Lý do đảo ngược mốc mài">
                            <button class="aomai__undo" type="submit">Đảo ngược</button>
                        </form>
                        <p class="aomai__hint">
                            Quá cửa sổ rút lại nên bắt buộc ghi lý do, tối thiểu
                            <?= (int) OrderModel::LY_DO_TOI_THIEU ?> ký tự.
                        </p>
                    <?php else: ?>
                        <p class="aomai__hint">
                            Quá cửa sổ rút lại — chỉ Quản lý cơ sở trở lên đảo ngược được.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

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

            <?php
            /*
             * ─────────────────────────────────────────────────────────────────
             * MỌI CON SỐ Ở KHỐI NÀY ĐẾN TỪ OrderModel::tinhTien(), KHÔNG TỰ
             * CỘNG TRỪ TRONG VIEW
             *
             * Khối này, phiếu in và bản xuất Excel đều phải nói cùng một con số.
             * Ba nơi cùng gõ lại một công thức thì sớm muộn có một nơi quên
             * `max(0, …)` hay quên phần cọc, và hai tờ giấy in ra từ cùng một
             * đơn sẽ ghi hai số khác nhau — thứ chỉ lộ ra khi khách cầm cả hai.
             *
             * Tổng cũng ĐƯỢC TÍNH LẠI ở đó chứ không đọc thẳng `orders`.`total`;
             * lý do đầy đủ nằm trong docblock của tinhTien().
             *
             * DÒNG BẰNG 0 THÌ ẨN, TRỪ HAI DÒNG LUÔN HIỆN: Tổng cộng và Còn phải
             * thu. "Còn phải thu 0₫" không phải một ô trống mà là một câu trả
             * lời — nó nói đơn này đã thu xong. Ẩn nó đi thì người trực quầy
             * phải tự trừ nhẩm để biết còn phải đòi khách bao nhiêu, và đó đúng
             * là phép trừ mà khối này sinh ra để khỏi phải làm.
             * ─────────────────────────────────────────────────────────────────
             */
            $t = OrderModel::tinhTien($order, $order['items'] ?? []);
            ?>
            <dl class="aosum">
                <?php /* TÁCH "Tiền hàng" thành gọng + tròng. Tiền tròng nằm sẵn
                         trong `order_items`.`lens_price`, chỉ là trước nay không
                         ai cộng nó ra: khách nhìn "Tiền hàng 3.340.000₫" cho một
                         cái gọng niêm yết 2.890.000₫ thì gọi điện lên hỏi, và
                         nhân viên phải mở từng dòng hàng ra cộng tay để trả lời.

                         Dòng tròng ẩn khi đơn chỉ mua gọng — lúc đó "Tiền hàng"
                         một mình đã đúng nghĩa và không thiếu gì. */ ?>
                <div class="aosum__row">
                    <dt><?= $t['tienTrong'] > 0 ? 'Tiền gọng' : 'Tiền hàng' ?></dt>
                    <dd><?= money($t['tienGong']) ?></dd>
                </div>

                <?php if ($t['tienTrong'] > 0): ?>
                    <div class="aosum__row">
                        <dt>Tiền tròng</dt>
                        <dd><?= money($t['tienTrong']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php if ($t['giamGia'] > 0): ?>
                    <div class="aosum__row">
                        <dt>
                            Giảm giá
                            <?php /* Mã đi kèm ngay trong nhãn. Mã đã bị xoá khỏi
                                     bảng `vouchers` thì nói thẳng là đã xoá —
                                     KHÔNG giấu cả dòng đi, vì số tiền đã trừ là
                                     có thật và tổng sẽ không cộng ra được nếu
                                     thiếu nó. */ ?>
                            <span class="aosum__ma"><?= e($order['voucher_code'] ?? '') !== ''
                                ? e($order['voucher_code'])
                                : 'mã đã xoá' ?></span>
                        </dt>
                        <dd>−<?= money($t['giamGia']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php if ($t['phiShip'] > 0): ?>
                    <div class="aosum__row">
                        <dt>Phí vận chuyển</dt>
                        <dd><?= money($t['phiShip']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php /* VAT ẩn khi cửa hàng chưa xuất hoá đơn VAT (thuế suất 0
                         trong config/app.php) — hiện một dòng "VAT 0₫" trên mọi
                         đơn là dạy người đọc lướt qua nó.

                         Khi giá ĐÃ GỒM thuế thì dòng này KHÔNG cộng vào tổng, nó
                         chỉ bóc ra cho thấy phần thuế nằm trong đó — nên nhãn
                         phải nói rõ, nếu không người cộng tay sẽ ra thừa. */ ?>
                <?php if ($t['thue'] > 0): ?>
                    <div class="aosum__row">
                        <dt>VAT<?= $t['gomThue'] ? ' (đã gồm trong giá)' : '' ?></dt>
                        <dd><?= $t['gomThue'] ? '' : '+' ?><?= money($t['thue']) ?></dd>
                    </div>
                <?php endif; ?>

                <div class="aosum__row aosum__row--total">
                    <dt>Tổng cộng</dt>
                    <dd><?= money($t['tong']) ?></dd>
                </div>

                <?php if ($t['daThu'] > 0): ?>
                    <?php /* "Đã cọc" đứng SAU tổng cộng chứ không trừ vào nó: nó
                             không làm đơn rẻ đi, nó chỉ nói phần nào đã trả
                             trước. Xem chú thích `deposit_amount` trong
                             database/schema.sql. */ ?>
                    <div class="aosum__row aosum__row--dep">
                        <dt>
                            <?= (string) $order['payment_status'] === 'paid'
                                ? 'Đã thanh toán'
                                : 'Đã cọc (' . (int) $order['deposit_rate'] . '%)' ?>
                        </dt>
                        <dd>−<?= money($t['daThu']) ?></dd>
                    </div>
                <?php endif; ?>

                <?php /* LUÔN HIỆN, kể cả bằng 0 — xem khối chú thích ở trên. */ ?>
                <div class="aosum__row aosum__row--con<?= $t['conPhaiThu'] > 0 ? ' aosum__row--no' : '' ?>">
                    <dt>Còn phải thu</dt>
                    <dd><?= money($t['conPhaiThu']) ?></dd>
                </div>
            </dl>

            <?php
            /*
             * HOÁ ĐƠN TỰ MÂU THUẪN THÌ PHẢI NÓI RA, KHÔNG ĐƯỢC LẶNG LẼ CHỌN MỘT
             * TRONG HAI CON SỐ.
             *
             * `lech` khác 0 nghĩa là tổng đã lưu trên đơn không khớp với chính
             * các thành phần của nó. Nguyên nhân thật đã gặp: sửa tay trong
             * phpMyAdmin, dữ liệu nhập từ hệ thống cũ, một migration chạy dở.
             *
             * Khối này in ra con số TÍNH LẠI (đó là con số đúng theo các thành
             * phần), nên nếu im lặng thì nó khác với con số ở bảng danh sách và
             * ở trang tài khoản của khách — mà không ai biết vì sao. Dải cảnh
             * báo này là chỗ duy nhất phát hiện ra.
             */
            ?>
            <?php if ($t['lech'] !== 0): ?>
                <p class="aodraw__meta aosum__lech">
                    ⚠ Tổng lưu trên đơn là <?= money($t['tongDaLuu']) ?>, lệch
                    <?= money(abs($t['lech'])) ?> so với tổng tính lại từ các dòng ở trên.
                    Số hiển thị là số tính lại. Cần đối chiếu trước khi thu tiền.
                </p>
            <?php endif; ?>

            <?php if (!empty($order['deposit_paid_at'])): ?>
                <p class="aodraw__meta">Nhận cọc lúc <?= e(formatDate($order['deposit_paid_at'], 'd/m/Y H:i')) ?></p>
            <?php elseif ((int) $order['deposit_amount'] > 0): ?>
                <?php /* Đơn cũ nhận cọc trước migration 2026-09-11 không có mốc
                         nào truy được. Nói thẳng là chưa rõ, đừng in một mốc suy
                         ra từ updated_at — không ai phân biệt được nó với mốc
                         thật. */ ?>
                <p class="aodraw__meta">Chưa rõ mốc nhận cọc (đơn có trước khi hệ thống ghi mốc này).</p>
            <?php endif; ?>

            <?php if (!empty($order['paid_at'])): ?>
                <p class="aodraw__meta">Tiền về đủ lúc <?= e(formatDate($order['paid_at'], 'd/m/Y H:i')) ?></p>
            <?php endif; ?>

        </section>
    </div>

    <?php /* CHÂN HỘP — theo bản vẽ: nút tiền bên trái, "Đóng" bên phải.

             Nút đánh dấu tiền trước đây nằm cuối mục Thanh toán, tức là cuối
             một vùng cuộn: với đơn có bảng số đo dài thì phải cuộn hết mới
             thấy. Đưa xuống chân hộp thì nó đứng yên ngoài vùng cuộn, cạnh
             lối ra — đúng chỗ người ta tìm sau khi đọc xong.

             Chân hộp vẫn dựng cả khi không có nút tiền nào (đơn COD chưa giao
             thì chưa đánh dấu được): lúc ấy nó còn nút "Đóng", mà một lối ra
             luôn thấy được thì đáng giữ. */ ?>
    <div class="aodraw__foot">
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
        <?php else: ?>
            <span></span>
        <?php endif; ?>

        <a class="astatus__save astatus__save--ghost" href="<?= e($dongUrl) ?>" data-modal-close>Đóng</a>
    </div>
</aside>
