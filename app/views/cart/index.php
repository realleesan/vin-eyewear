<?php

/**
 * cart/index.php — giỏ hàng (/gio-hang)
 *
 * Dựng theo "Vin Eyewear Cart.dc.html" (Claude Design):
 *
 *   tiêu đề trần (không khối nền tối, không breadcrumb)
 *   → hai cột: danh sách dòng hàng | khối tóm tắt 380px dính theo cuộn
 *   thanh công cụ "Chọn tất cả" nằm trên cùng của cột trái
 *
 * CSS: assets/css/cart.css
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG MỘT DÒNG JAVASCRIPT NÀO
 *
 * Bản thiết kế giữ trạng thái tick, số lượng và mã giảm giá trong state của
 * trình duyệt. Ở đây tất cả nằm trong session phía server, và mỗi nút là một
 * nút gửi <form> thật — tick, dấu −, dấu +, thùng rác, áp mã.
 *
 * Lý do không phải là "ngại viết JS": mọi thao tác ở trang này đều ĐỔI TRẠNG
 * THÁI, nên đằng nào cũng phải là POST kèm token CSRF. Làm bằng form thì trang
 * chạy đúng cả khi JS hỏng, và không có đường nào để giá tiền bị sửa ở client.
 *
 * MỘT <form> CHO MỖI DÒNG, BỐN NÚT BÊN TRONG
 * HTML không cho lồng <form> vào nhau, mà bản thiết kế đặt cả bốn nút trên
 * cùng một hàng. Nên mỗi dòng là một form duy nhất, phân biệt bằng nút nào
 * được bấm (`name="act"`) — xem CartController::update().
 * ─────────────────────────────────────────────────────────────────────────────
 */

$count = count($lines);
?>

<section class="cart">

    <div class="cart__head">
        <h1 class="cart__title">Giỏ hàng</h1>
        <p class="cart__lead">
            <?= $count > 0
                ? $count . ' sản phẩm trong giỏ của bạn'
                : 'Chưa có sản phẩm nào' ?>
        </p>
    </div>

    <?php if ($success !== null): ?>
        <p class="cart__flash cart__flash--ok" role="status"><?= e($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="cart__flash cart__flash--err" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($lines === []): ?>

        <div class="cart__empty">
            <span class="cart__empty-ring" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#b0736a"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2.5 4h2l2.2 11h11.1l2.2-8H6"></path>
                    <circle cx="8.5" cy="20" r="1.6"></circle>
                    <circle cx="16.5" cy="20" r="1.6"></circle>
                </svg>
            </span>
            <span class="cart__empty-title">Giỏ hàng của bạn đang trống</span>
            <a class="cart__empty-cta" href="/san-pham">Tiếp tục mua sắm</a>
        </div>

    <?php else: ?>

        <div class="cart__grid">

            <!-- ══════════ CỘT DÒNG HÀNG ══════════ -->
            <div class="cart__lines">

                <div class="cbar">
                    <form method="post" action="/gio-hang/chon-tat-ca">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <button type="submit" class="cbar__all">
                            <!-- Ô tick là một <span> chứ không phải <input
                                 type=checkbox>: nó KHÔNG lưu trạng thái ở client,
                                 mà gửi lên server rồi vẽ lại theo session. Dùng
                                 checkbox thật ở đây sẽ có một khoảnh khắc ô đã
                                 tick nhưng tổng tiền chưa đổi. aria-pressed nói
                                 đúng trạng thái đó cho trình đọc màn hình. -->
                            <span class="cbox<?= $allSelected ? ' is-on' : '' ?>" aria-hidden="true">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 12.5l5.5 5.5L20 7"></path>
                                </svg>
                            </span>
                            <span class="cbar__label">Chọn tất cả (<?= $count ?>)</span>
                        </button>
                    </form>

                    <?php
                    /* MỘT câu hỏi, hai đường dùng. data-confirm cho hộp thoại
                       trên trang; onsubmit là lớp dự phòng khi không có JS —
                       confirm-dialog.js gỡ nó ra khi đã sẵn sàng thay thế.
                       Cùng một biến nên hai đường không thể lệch chữ. */
                    $hoiXoaChon = 'Xoá các sản phẩm đã chọn khỏi giỏ hàng?';
                    ?>
                    <form method="post" action="/gio-hang/xoa-chon"
                          data-confirm="<?= e($hoiXoaChon) ?>"
                          data-confirm-title="Xoá mục đã chọn?"
                          data-confirm-ok="Xoá"
                          onsubmit="return confirm('<?= e($hoiXoaChon) ?>')">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <button type="submit" class="cbar__wipe">Xoá mục đã chọn</button>
                    </form>
                </div>

                <?php foreach ($lines as $line): ?>
                    <?php
                    $p    = $line['product'];
                    $slug = '/san-pham/' . rawurlencode($p['slug']);

                    /* GỌI TÊN MÓN SẮP XOÁ. Câu cũ ("sản phẩm này") đúng với
                       hộp confirm() của trình duyệt — nó bật lên ngay dưới con
                       trỏ nên "này" là rõ. Hộp thoại trên trang thì nằm giữa
                       màn hình, che mất chính cái dòng vừa bấm, và giỏ có năm
                       dòng thì "này" không còn chỉ vào đâu cả. */
                    $hoiXoaMon = sprintf('Bỏ “%s” khỏi giỏ hàng?', $p['name']);
                    /* Dòng mô tả phiên bản. Ưu tiên NHÃN BIẾN THỂ khách đã chọn
                       (vd "Chiết suất 1.61") — đó mới là thứ phân biệt hai dòng
                       cùng một mặt hàng trong giỏ. Không có biến thể thì ghép
                       từ màu và chất liệu như trước. */
                    $variant = $line['variant'] !== null
                        ? $line['variant']['label'] . ($line['variant']['note'] ? ' · ' . $line['variant']['note'] : '')
                        : implode(' · ', array_filter([$p['color'] ?? null, $p['material'] ?? null]));
                    ?>
                    <form class="citem<?= $line['available'] ? '' : ' is-out' ?>"
                          method="post" action="/gio-hang/sua">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <!-- 'key' chứ không phải product_id: cùng một mặt hàng có
                             thể nằm trong giỏ nhiều lần với biến thể khác nhau. -->
                        <input type="hidden" name="key" value="<?= e($line['key']) ?>">

                        <button type="submit" name="act" value="chon" class="citem__pick"
                                aria-pressed="<?= $line['selected'] ? 'true' : 'false' ?>">
                            <span class="cbox<?= $line['selected'] ? ' is-on' : '' ?>" aria-hidden="true">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 12.5l5.5 5.5L20 7"></path>
                                </svg>
                            </span>
                            <span class="sr-only">Chọn <?= e($p['name']) ?></span>
                        </button>

                        <a class="citem__thumb" href="<?= e($slug) ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= e(ProductModel::image($p)) ?>" alt=""
                                 width="96" height="96" loading="lazy" decoding="async">
                        </a>

                        <div class="citem__body">
                            <span class="citem__brand"><?= e($p['brand'] ?? 'Vin Eyewear') ?></span>
                            <h2 class="citem__name"><a href="<?= e($slug) ?>"><?= e($p['name']) ?></a></h2>
                            <?php if ($variant !== ''): ?>
                                <span class="citem__variant"><?= e($variant) ?></span>
                            <?php endif; ?>

                            <?php if ($line['lens'] !== null || $line['rx'] !== null): ?>
                                <?php /* Tròng cắt kèm. Hiện thành một dòng RIÊNG có
                                         nền, không gộp vào dòng phiên bản: nó là
                                         phần lớn nhất của khoản chênh giá, và số đo
                                         mắt là thứ khách phải soát lại được trước
                                         khi đặt. Xem CartController::add(). */ ?>
                                <span class="clens">
                                    <?php if ($line['lens'] !== null): ?>
                                        <span class="clens__name">
                                            + <?= e($line['lens']['name']) ?>
                                            <span class="clens__price">
                                                <?php /* "Mắt đặt" chưa có giá — cửa hàng báo sau
                                                         khi xem thông số. In "0₫" ở đây thì khách
                                                         đọc ra thành "phần tròng miễn phí". */ ?>
                                                <?= !empty($line['lens']['quoted'])
                                                    ? 'Báo giá sau'
                                                    : money((int) $line['lens']['price']) ?>
                                            </span>
                                        </span>
                                    <?php endif; ?>
                                    <?php /* Số đo hiện KỂ CẢ khi không có gói tròng kèm:
                                             tròng rời cũng đi qua nhánh "theo số đo"
                                             nhưng không cộng thêm gói nào, mà con số vẫn
                                             là thứ quyết định hàng giao ra. */ ?>
                                    <span class="clens__rx">
                                        <?= $line['rx'] !== null
                                            ? e($line['rx'])
                                            : 'Chưa có số đo — đo tại cửa hàng' ?>
                                    </span>
                                </span>
                            <?php endif; ?>

                            <span class="citem__unit"><?= money($line['unitPrice']) ?></span>

                            <?php if (!$line['available']): ?>
                                <span class="citem__warn">
                                    Không đủ tồn kho — còn <?= (int) $line['stock'] ?> sản phẩm.
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="cstep">
                            <?php
                            /* Ở SỐ 1, NÚT "−" LÀ NÚT XOÁ CÓ HỎI LẠI.
                               Trước đây nó bị tắt, nên khách muốn bỏ món ra
                               phải đi tìm biểu tượng thùng rác ở đầu kia dòng.
                               Giảm xuống 0 nghĩa là bỏ món đi — cứ hỏi thẳng
                               câu đó, rồi mới xoá. */
                            ?>
                            <?php if ($line['quantity'] <= 1): ?>
                                <button type="submit" name="act" value="xoa" class="cstep__btn"
                                        data-confirm="<?= e($hoiXoaMon) ?>"
                                        data-confirm-title="Xoá sản phẩm?"
                                        data-confirm-ok="Xoá"
                                        onclick="return confirm('<?= e($hoiXoaMon) ?>')"
                                        aria-label="Bỏ <?= e($p['name']) ?> khỏi giỏ hàng">−</button>
                            <?php else: ?>
                                <button type="submit" name="act" value="giam" class="cstep__btn"
                                        aria-label="Giảm số lượng <?= e($p['name']) ?>">−</button>
                            <?php endif; ?>

                            <!-- Ô số thật, không phải chữ: bàn phím và trình đọc
                                 màn hình sửa thẳng được số lượng thay vì phải
                                 bấm dấu − mười lần. Gửi form (Enter) không kèm
                                 `act` nào, controller hiểu là "đặt số lượng". -->
                            <label class="sr-only" for="qty-<?= e($line['key']) ?>">
                                Số lượng <?= e($p['name']) ?>
                            </label>
                            <input class="cstep__num" type="number" id="qty-<?= e($line['key']) ?>"
                                   name="quantity" value="<?= (int) $line['quantity'] ?>"
                                   min="1" max="<?= (int) min($maxQty, max(1, $line['stock'])) ?>"
                                   inputmode="numeric">

                            <?php /* Tắt theo TỒN KHO chứ không chỉ theo trần 20 —
                                     xem 'canAdd' trong CartController::lines().
                                     Đây chỉ là phần nhìn thấy được; máy chủ vẫn
                                     kiểm lại trong setQuantity(). */ ?>
                            <button type="submit" name="act" value="tang" class="cstep__btn"
                                    <?= $line['canAdd'] ? '' : 'disabled' ?>
                                    aria-label="Tăng số lượng <?= e($p['name']) ?>">+</button>
                        </div>

                        <div class="citem__end">
                            <span class="citem__total"><?= money($line['lineTotal']) ?></span>
                            <button type="submit" name="act" value="xoa" class="citem__del"
                                    data-confirm="<?= e($hoiXoaMon) ?>"
                                    data-confirm-title="Xoá sản phẩm?"
                                    data-confirm-ok="Xoá"
                                    onclick="return confirm('<?= e($hoiXoaMon) ?>')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                </svg>
                                <span class="sr-only">Xoá <?= e($p['name']) ?> khỏi giỏ hàng</span>
                            </button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>

            <!-- ══════════ KHỐI TÓM TẮT ══════════ -->
            <aside class="csum" aria-labelledby="csum-title">

                <div class="csum__card">
                    <h2 id="csum-title" class="csum__title">Tóm tắt đơn hàng</h2>

                    <div class="csum__row">
                        <span>Tạm tính (<?= (int) $picked ?> sản phẩm)</span>
                        <span class="csum__val"><?= money($subtotal) ?></span>
                    </div>

                    <div class="csum__row">
                        <span>Giảm giá voucher</span>
                        <span class="csum__val csum__val--cut">
                            <?= $discount > 0 ? '−' . money($discount) : money(0) ?>
                        </span>
                    </div>

                    <div class="csum__row">
                        <span>Phí vận chuyển</span>
                        <span class="csum__val<?= $shippingFee === 0 ? ' csum__val--free' : '' ?>">
                            <?= $shippingFee === 0 ? 'Miễn phí' : money($shippingFee) ?>
                        </span>
                    </div>

                    <?php if ($shippingFee > 0 && $subtotal > 0): ?>
                        <div class="csum__nudge">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a2701c"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M1 3h13v13H1zM14 8h4l3 3v5h-7V8z"></path>
                                <circle cx="5.5" cy="18.5" r="2"></circle>
                                <circle cx="17.5" cy="18.5" r="2"></circle>
                            </svg>
                            <span>Mua thêm <strong><?= money($threshold - $subtotal) ?></strong> để được miễn phí giao hàng</span>
                        </div>
                    <?php endif; ?>

                    <div class="csum__rule"></div>

                    <div class="csum__grand">
                        <span>Tổng cộng</span>
                        <span class="csum__grand-num"><?= money($total) ?></span>
                    </div>

                    <?php if ($picked > 0): ?>
                        <a class="csum__cta" href="/thanh-toan">Tiến hành thanh toán</a>
                    <?php else: ?>
                        <!-- Không có dòng nào được tick thì trang thanh toán sẽ
                             đá ngược về đây. Chặn ngay tại nút, kèm lý do, thay
                             vì để khách bấm rồi bị đẩy về chỗ cũ. -->
                        <span class="csum__cta is-off" aria-disabled="true">Tiến hành thanh toán</span>
                        <p class="csum__note">Hãy tick chọn ít nhất một sản phẩm.</p>
                    <?php endif; ?>

                    <a class="csum__more" href="/san-pham">← Tiếp tục mua sắm</a>
                </div>

                <div class="cvou">
                    <span class="cvou__label">Mã giảm giá</span>

                    <?php if ($voucher !== null): ?>
                        <div class="cvou__on">
                            <span class="cvou__code"><?= e($voucher['code']) ?></span>
                            <span class="cvou__title"><?= e($voucher['title']) ?></span>
                            <form method="post" action="/gio-hang/ma">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <button type="submit" name="act" value="go" class="cvou__off">Gỡ mã</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form class="cvou__form" method="post" action="/gio-hang/ma">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <label class="sr-only" for="ma-giam-gia">Mã giảm giá</label>
                            <input class="cvou__input" type="text" id="ma-giam-gia" name="code"
                                   maxlength="40" autocomplete="off" spellcheck="false"
                                   placeholder="Nhập mã giảm giá"
                                   value="<?= e($voucherCode) ?>">
                            <button type="submit" class="cvou__apply">Áp dụng</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($voucherMsg !== null): ?>
                        <span class="cvou__msg<?= $voucherOk ? ' is-ok' : ' is-err' ?>"
                              role="<?= $voucherOk ? 'status' : 'alert' ?>"><?= e($voucherMsg) ?></span>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

    <?php endif; ?>
</section>

<?php
/* Hộp thoại hỏi lại trước khi xoá — in một lần cho cả trang, mọi nút mang
   data-confirm đều dùng chung nó. Xem _layout/confirm-dialog.php. */
partial('_layout/confirm-dialog');
?>
