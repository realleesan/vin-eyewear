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
            <?php if ($order['status'] === 'cancelled'): ?>
                <?php if (!empty($order['la_admin'])): ?>
                    <div class="aomolai">
                        <label class="aomolai__lb" for="aodraw-lydo">
                            Lý do mở lại đơn đã huỷ
                        </label>
                        <input class="aomolai__in" type="text" id="aodraw-lydo"
                               name="ly_do" form="aodrawst" maxlength="255"
                               placeholder="Ví dụ: nhân viên bấm nhầm, khách vẫn lấy hàng">
                        <p class="aomolai__hint">
                            Bắt buộc, tối thiểu <?= (int) OrderModel::LY_DO_TOI_THIEU ?> ký tự.
                            Lý do lưu cùng mốc trạng thái và vào nhật ký thao tác.
                        </p>
                    </div>
                <?php else: ?>
                    <p class="aomolai__hint aomolai__hint--block">
                        Đơn đã huỷ — chỉ Quản trị viên mở lại được, kèm lý do (Q3.1).
                    </p>
                <?php endif; ?>
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
