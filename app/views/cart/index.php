<?php

/**
 * cart/index.php — trang giỏ hàng /gio-hang
 *
 * Dựng 1:1 từ "Cart Screen.dc.html" (Claude Design, 13/09/2026).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * BẢN NÀY ĐÃ BỎ NĂM THỨ SO VỚI BẢN TRƯỚC — chủ dự án chốt "giống design tuyệt
 * đối". Ghi ra đây vì cả năm đều còn nguyên phần xử lý ở máy chủ, và người đọc
 * file này sau sẽ tưởng chúng chưa từng tồn tại:
 *
 *   1. Ô TICK TỪNG DÒNG (`.citem__pick`, /gio-hang/chon-tat-ca, /gio-hang/xoa-chon)
 *      Cờ `selected` VẪN sống và vẫn quyết định đơn hàng. Bù lại: mở trang giỏ
 *      là tick lại tất cả — xem khối chú thích dài trong CartController::index().
 *      ⚠ Đây là chỗ nguy hiểm nhất của đợt đổi này. Đọc khối ấy trước khi đụng.
 *
 *   2. Ô NHẬP MÃ GIẢM GIÁ (`.cvou`, POST /gio-hang/ma)
 *      Không mất đường dùng mã: TRANG THANH TOÁN có ô riêng (POST
 *      /thanh-toan/ma, xem .covou trong order/checkout.php). Mã đã áp vẫn được
 *      applyVoucher() tính vào tổng như cũ.
 *
 *   3. DẢI "MUA THÊM X ĐỂ MIỄN PHÍ SHIP" (`.csum__nudge`)
 *   4. GÓI TRÒNG KÈM + SỐ ĐO MẮT trên mỗi dòng (`.clens`)
 *   5. CẢNH BÁO GIÁ ĐÃ ĐỔI / KHÔNG ĐỦ TỒN KHO (`.citem__pricechg`, `.citem__warn`)
 *      Máy chủ vẫn kiểm tồn kho lúc đặt hàng — khách chỉ không thấy trước nữa.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT CHỖ CỐ Ý KHÔNG CHÉP THEO DESIGN: DÒNG "SHIPPING — FREE"
 *
 * Bản thiết kế in cứng chữ FREE. Cửa hàng này CÓ thu phí ship khi đơn dưới
 * ngưỡng (config app.shipping_fee / app.free_shipping_threshold), nên in FREE
 * lên đó là nói sai một con số tiền. Ô ấy giữ nguyên vị trí, cỡ chữ, cách canh
 * của design — chỉ thay chữ bằng con số thật, và "MIỄN PHÍ" khi nó thật sự
 * bằng không.
 *
 * ⚠ ĐỪNG đổi lại thành chữ FREE cứng cho "giống mẫu".
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Nhận từ controller: xem CartController::index().
 */

$soMon = 0;

foreach ($lines as $line) {
    $soMon += (int) $line['quantity'];
}

$hoiXoaMon = static fn (string $ten): string => t('cart.confirm_one', [':name' => $ten]);
?>

<section class="cart">

    <?php /* ┌─ HAI TAB + NÚT ĐÓNG ──────────────────────────────────────────
             │ BAG là tab đang đứng (nền xám), WISHLIST là liên kết sang mục
             │ "Đã lưu" của trang tài khoản — nơi danh sách ấy đã sống từ
             │ 12/09/2026. Không dựng trang thứ hai cho cùng một danh sách.
             │
             │ ✕ trả về trang khách vừa rời, không phải một trang cố định:
             │ giỏ hàng mở ra từ khắp nơi trong site. */ ?>
    <div class="ctabs">
        <span class="ctabs__on">
            <?= e(t('cart.tab_bag')) ?><sup><?= (int) $soMon ?><span class="sr-only"> <?= e(t('cart.tab_bag_sr')) ?></span></sup>
        </span>

        <a class="ctabs__off" href="/tai-khoan?muc=da-luu">
            <?= e(t('cart.tab_wish')) ?><sup><?= (int) $wishCount ?><span class="sr-only"> <?= e(t('cart.tab_wish_sr')) ?></span></sup>
        </a>

        <a class="ctabs__x" href="/san-pham/gong-kinh" aria-label="<?= e(t('cart.close')) ?>">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                 stroke-width="1.3" aria-hidden="true">
                <line x1="2" y1="2" x2="14" y2="14"></line>
                <line x1="14" y1="2" x2="2" y2="14"></line>
            </svg>
        </a>
    </div>

    <?php /* Dải báo của thao tác vừa rồi. KHÔNG có trong bản thiết kế, và vẫn
             giữ: nó là câu trả lời cho "vừa bấm xoá thì chuyện gì xảy ra".
             Chỉ hiện khi có việc gì đó vừa xảy ra, nên trang ở trạng thái
             thường vẫn đúng y mẫu. */ ?>
    <?php if ($success !== null || $error !== null): ?>
        <p class="cmsg<?= $error !== null ? ' is-err' : '' ?>" role="status">
            <?= e($error ?? $success) ?>
        </p>
    <?php endif; ?>

    <div class="cmain">

        <!-- ══════════ DANH SÁCH ══════════ -->
        <section class="clist" aria-label="<?= e(t('cart.title')) ?>">

            <?php if ($lines === []): ?>
                <p class="cempty"><?= e(t('cart.empty_title')) ?></p>
            <?php endif; ?>

            <?php foreach ($lines as $line): ?>
                <?php
                $p    = $line['product'];
                $slug = '/san-pham/' . rawurlencode($p['slug']);

                /* Dòng "phiên bản": nhãn biến thể nếu có, không thì ghép màu và
                   chất liệu — cùng phép với bản trước. */
                $variant = $line['variant'] !== null
                    ? $line['variant']['label'] . ($line['variant']['note'] ? ' · ' . $line['variant']['note'] : '')
                    : implode(' · ', array_filter([$p['color'] ?? null, $p['material'] ?? null]));

                /* Trần số lượng của ĐÚNG dòng này. Bản thiết kế vẽ năm mục 1–5;
                   ở đây danh sách dài tới tồn kho thật (chặn trên bằng
                   ABS_MAX_QTY), vì một giỏ đang có sáu chiếc mà ô chọn chỉ tới
                   năm thì không còn cách nào giữ nguyên số đang có. */
                $tran = (int) min($maxQty, max(1, $line['stock']));
                $daCo = !empty($daLuu[(string) $p['id']]);
                ?>
                <article class="citem">

                    <a class="citem__thumb" href="<?= e($slug) ?>" tabindex="-1" aria-hidden="true">
                        <?php /* asset() bọc ngoài — xem _layout/product-card.php. */ ?>
                        <img src="<?= e(asset(ProductModel::image($p))) ?>" alt=""
                             width="220" height="110" loading="lazy" decoding="async">
                    </a>

                    <div class="citem__body">
                        <span class="citem__name notranslate" translate="no" lang="vi">
                            <a href="<?= e($slug) ?>"><?= e($p['name']) ?></a>
                        </span>

                        <?php if ($variant !== ''): ?>
                            <span class="citem__variant"><?= e($variant) ?></span>
                        <?php endif; ?>

                        <span class="citem__price"><?= money($line['unitPrice']) ?></span>

                        <?php /* ┌─ SỐ LƯỢNG ────────────────────────────────
                                 │ Bản thiết kế là một <select> TRONG SUỐT phủ
                                 │ lên con số và mũi tên — trông như chữ thường
                                 │ nhưng bấm đâu cũng mở được danh sách.
                                 │
                                 │ Có JS thì đổi ô là gửi luôn (assets/js/cart.js).
                                 │ KHÔNG có JS thì nút "Cập nhật" ngay dưới hiện
                                 │ ra — xem `html.js .cqty__go` trong cart.css.
                                 │ Cùng lối với ô chọn của khu quản trị. */ ?>
                        <form class="cqty" method="post" action="/gio-hang/sua">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="key" value="<?= e($line['key']) ?>">

                            <span class="cqty__label" id="qtyl-<?= e($line['key']) ?>"><?= e(t('cart.qty')) ?></span>

                            <span class="cqty__pick">
                                <select name="quantity" data-cart-qty
                                        aria-labelledby="qtyl-<?= e($line['key']) ?>">
                                    <?php for ($i = 1; $i <= $tran; $i++): ?>
                                        <option value="<?= $i ?>"<?= $i === (int) $line['quantity'] ? ' selected' : '' ?>><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                                <span class="cqty__num" aria-hidden="true"><?= (int) $line['quantity'] ?></span>
                                <svg class="cqty__caret" width="12" height="8" viewBox="0 0 12 8" fill="none"
                                     stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                    <path d="M1 1l5 5 5-5"></path>
                                </svg>
                            </span>

                            <button type="submit" class="cqty__go"><?= e(t('cart.qty_go')) ?></button>
                        </form>

                        <form method="post" action="/gio-hang/sua"
                              data-confirm="<?= e($hoiXoaMon($p['name'])) ?>"
                              data-confirm-title="<?= e(t('cart.confirm_title')) ?>"
                              data-confirm-ok="<?= e(t('cart.remove')) ?>"
                              onsubmit="return confirm('<?= e($hoiXoaMon($p['name'])) ?>')">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="key" value="<?= e($line['key']) ?>">
                            <button type="submit" name="act" value="xoa" class="crem">
                                <?= e(t('cart.remove')) ?><span class="sr-only"> — <?= e($p['name']) ?></span>
                            </button>
                        </form>
                    </div>

                    <?php /* Dấu trang. POST vì nó ĐỔI dữ liệu — lý do đầy đủ ở
                             đầu FavoriteController. Tô đặc khi đã lưu, đúng
                             `item.wishFill` của bản thiết kế. */ ?>
                    <form class="cwish" method="post" action="/yeu-thich">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
                        <input type="hidden" name="back" value="/gio-hang">
                        <button type="submit" class="cwish__btn"
                                aria-pressed="<?= $daCo ? 'true' : 'false' ?>">
                            <svg width="20" height="22" viewBox="0 0 20 22"
                                 fill="<?= $daCo ? 'currentColor' : 'none' ?>"
                                 stroke="currentColor" stroke-width="1.3" aria-hidden="true">
                                <path d="M4 2h12v18l-6-4.5L4 20z"></path>
                            </svg>
                            <span class="sr-only">
                                <?= e($daCo ? t('cart.wish_off') : t('cart.wish_on')) ?> — <?= e($p['name']) ?>
                            </span>
                        </button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <!-- ══════════ TÓM TẮT ══════════ -->
        <aside class="csum" aria-labelledby="csum-t">
            <h2 class="sr-only" id="csum-t"><?= e(t('cart.summary')) ?></h2>

            <dl class="csum__rows">
                <dt><?= e(t('cart.subtotal_plain')) ?></dt>
                <dd><?= money($subtotal) ?></dd>

                <?php if ($discount > 0): ?>
                    <?php /* Mã giảm giá KHÔNG nhập được ở trang này nữa, nhưng
                             mã đã áp thì vẫn phải hiện: nó đang trừ tiền thật.
                             Giấu đi là để khách tự dò xem vì sao tổng lệch. */ ?>
                    <dt><?= e(t('cart.discount')) ?></dt>
                    <dd class="csum__cut">−<?= money($discount) ?></dd>
                <?php endif; ?>

                <dt><?= e(t('cart.shipping')) ?></dt>
                <dd><?= $shippingFee === 0 ? e(t('cart.free')) : money($shippingFee) ?></dd>

                <dt><?= e(t('cart.tax')) ?></dt>
                <dd><?= e(t('cart.tax_at_checkout')) ?></dd>
            </dl>

            <p class="csum__total">
                <span><?= e(t('cart.grand')) ?></span>
                <span class="csum__num"><?= money($total) ?></span>
            </p>

            <?php if ($lines !== []): ?>
                <a class="csum__go" href="/thanh-toan">
                    <?= e(t('cart.checkout_short')) ?> — <?= money($total) ?>
                </a>
            <?php else: ?>
                <span class="csum__go is-off" aria-disabled="true"><?= e(t('cart.checkout_short')) ?></span>
            <?php endif; ?>

            <a class="csum__keep" href="/san-pham/gong-kinh"><?= e(t('cart.keep_shopping_caps')) ?></a>

            <?php
            /* ┌─ HAI MỤC GẬP ─────────────────────────────────────────────────
               │ Bản thiết kế để sẵn hai mục nói về VẬN CHUYỂN & ĐỔI TRẢ và về
               │ THANH TOÁN. Chữ ở đây KHÔNG chép từ mẫu (mẫu nói chuyện của một
               │ cửa hàng khác: miễn ship mọi đơn, đổi trả 14 ngày, trả góp) —
               │ nó dựng từ chính cấu hình của cửa hàng này, nên không hứa gì
               │ sai. Chi tiết đầy đủ nằm ở /chinh-sach.
               │
               │ <details> chứ không JavaScript: cùng lối với tấm lọc danh mục.
               └───────────────────────────────────────────────────────────── */
            $nguong = (int) $threshold;
            $phi    = (int) config('app.shipping_fee');

            $mucGap = [
                [
                    'tua' => t('cart.faq_ship'),
                    'than' => $nguong > 0
                        ? t('cart.faq_ship_body', [':n' => money($nguong), ':fee' => money($phi)])
                        : t('cart.faq_ship_body_flat', [':fee' => money($phi)]),
                ],
                [
                    'tua'  => t('cart.faq_pay'),
                    'than' => t('cart.faq_pay_body'),
                ],
            ];
            ?>
            <div class="cfaq">
                <?php foreach ($mucGap as $m): ?>
                    <details class="cfaq__item">
                        <summary class="cfaq__q">
                            <span><?= e($m['tua']) ?></span>
                            <svg class="cfaq__ico" width="14" height="14" viewBox="0 0 14 14" fill="none"
                                 stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                                <line x1="7" y1="1" x2="7" y2="13"></line>
                                <line x1="1" y1="7" x2="13" y2="7"></line>
                            </svg>
                        </summary>
                        <p class="cfaq__a"><?= e($m['than']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</section>
