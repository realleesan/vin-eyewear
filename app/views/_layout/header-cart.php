<?php

/**
 * _layout/header-cart.php — cụm GIỎ HÀNG trên thanh nav (huy hiệu + bảng xổ).
 *
 * Tách khỏi _layout/header.php vì có HAI nơi cần in nó:
 *
 *   1. header.php — như mọi khi, một phần của thanh nav;
 *   2. master.php ở CHẾ ĐỘ MẢNH — trả lời cú bấm "Mua ngay"/"Thêm vào giỏ"
 *      của assets/js/buy-flow.js. Xem khối chú thích đầu master.php.
 *
 * Không nhận tham số: mọi thứ đọc thẳng từ phiên, nên hai nơi gọi không thể
 * lệch nhau.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * data-cart LÀ MỐC CHO buy-flow.js
 *
 * Thêm hàng xong nó cập nhật huy hiệu và ruột bảng xổ mà không tải lại trang.
 * Nó chỉ thay RUỘT của hai chỗ đó, KHÔNG thay thẻ [data-hpop-trigger] —
 * header.js gắn sự kiện thẳng lên thẻ ấy, thay cả thẻ bọc [data-hpop] là mất
 * luôn cái bảng xổ: header.js đọc danh sách [data-hpop] MỘT LẦN lúc tải trang
 * và giữ luôn tham chiếu đó. (Riêng thẻ mở của giỏ hàng là <a> nên header.js
 * bỏ qua — nó chỉ gắn sự kiện cho <button>.)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BẢNG XỔ LIỆT KÊ NĂM MÓN MỚI THÊM, KHÔNG PHẢI CẢ GIỎ
 *
 * Trước đây bảng chỉ có một dòng chữ "N sản phẩm đang chờ" và hai liên kết.
 * Nay nó hiện đúng thứ khách vừa bỏ vào — ảnh, tên, giá — theo mẫu Shopee mà
 * cửa hàng gửi. Rê chuột vào giỏ là thấy ngay mình vừa thêm gì, không phải
 * mở hẳn trang giỏ hàng để kiểm.
 *
 * NĂM là con số của bản mẫu, và nó cũng là mức hợp lý: bảng dài hơn thì tràn
 * quá nửa màn hình dọc, mà đã cần cuộn trong một bảng xổ hover thì thà mở
 * trang giỏ hàng. Số dòng còn lại nói bằng một câu ở chân bảng.
 *
 * Đây là chỗ DUY NHẤT trong header chạm tới cơ sở dữ liệu — xem chú thích ở
 * CartController::recent() về việc cắt trước, tra sau.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$cartCount = count($_SESSION['cart'] ?? []);

/*
 * ĐẾM SỐ DÒNG TRONG GIỎ, KHÔNG CỘNG SỐ LƯỢNG.
 *
 * Thêm 2 chiếc cùng một gọng thì huy hiệu hiện 1, không phải 2 — giỏ đang giữ
 * MỘT món, món đó có số lượng 2. Con số ở đây phải trả lời "giỏ có mấy thứ",
 * cùng câu hỏi mà trang /gio-hang trả lời bằng số dòng nó vẽ ra.
 *
 * Khoá của $_SESSION['cart'] gồm cả phương án và gói tròng, nên cùng một gọng
 * mua trần và mua kèm tròng vẫn là HAI dòng — đúng như trang giỏ hàng hiện.
 */

$recent = $cartCount > 0 ? CartController::recent(5) : ['lines' => [], 'more' => 0];
?>
<div class="hpop" data-hpop data-cart>
    <a href="/gio-hang" class="hpop__trigger header-action"
       data-hpop-trigger
       aria-label="<?= e(t('cart.aria', [':n' => (string) (int) $cartCount])) ?>">
        <?php
        /* XE ĐẨY chứ không phải cái túi. Túi xách là biểu tượng của thời
           trang; xe đẩy là biểu tượng của "đang mua sắm", và đó mới là việc
           cái nút này làm. Khách nhìn một lần là hiểu, không phải đoán. */
        ?>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M2.5 3.5h2.2l2.3 10.3h9.6l2.1-7.2H6.4"
                  fill="none" stroke="currentColor" stroke-width="1.6"
                  stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="9.2" cy="19" r="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
            <circle cx="16.4" cy="19" r="1.5" fill="none" stroke="currentColor" stroke-width="1.6"/>
        </svg>
        <!-- Thiết kế hiện huy hiệu cả khi giỏ trống (số 0). Ở đây chỉ
             hiện khi có hàng: một chấm đỏ báo "0" là báo động giả. -->
        <?php if ($cartCount > 0): ?>
            <span class="header-action__badge" aria-hidden="true"><?= (int) $cartCount ?></span>
        <?php endif; ?>
    </a>

    <?php
    /* NỀN MỜ ĐÃ BỎ khỏi markup: Bootstrap Offcanvas tự dựng .offcanvas-backdrop
       và gắn vào <body> khi mở, xoá khi đóng — xem khối 2a trong header.js.
       Kiểu dáng của nó khai trong components/header.css.

       ─────────────────────────────────────────────────────────────────────────
       BA LỚP BOOTSTRAP TRÊN ĐÚNG THẺ CŨ, KHÔNG THÊM THẺ NÀO

       `offcanvas offcanvas-end` là hợp đồng lớp mà Bootstrap JS đọc; `.hpop__panel
       .hpop__panel--cart` là hợp đồng mà buy-flow.js và CSS của Vin đọc. Cả bốn
       nằm trên CÙNG MỘT phần tử nên không bên nào phải đổi.

       tabindex="-1": Offcanvas đặt tiêu điểm vào chính tấm khi mở.
       aria-modal / role=dialog do Bootstrap tự gắn — không khai tay nữa, khai
       đè lên thứ nó quản lý là cách sinh ra hai nguồn sự thật. */
    ?>
    <div class="hpop__panel hpop__panel--cart offcanvas offcanvas-end" tabindex="-1"
         id="cartDrawer" aria-label="<?= e(t('cart.title')) ?>">

        <?php
        /* Đầu ngăn kéo. NẰM TRONG .hpop__panel, và đó là chủ ý: buy-flow.js
           thay ruột bảng này bằng ruột do MÁY CHỦ dựng, mà máy chủ luôn chạy
           đúng file này — nên nút đóng quay lại nguyên vẹn sau mỗi lần thêm
           hàng. Đặt ngoài bảng thì nó sống sót, nhưng tiêu đề và nút đóng lại
           không đổi theo trạng thái giỏ được nữa.

           Dùng lại khoá 'menu.close' của ngăn kéo điều hướng: cùng một việc,
           cùng một câu, không thêm chuỗi dịch mới. */
        ?>
        <div class="cartdrawer__head">
            <p class="cartdrawer__title"><?= e(t('cart.title')) ?></p>
            <button type="button" class="cartdrawer__close tap-target" data-cart-close
                    aria-label="<?= e(t('menu.close')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <?php if ($recent['lines'] === []): ?>
            <?php /* KHÔNG in .hpop__head ở nhánh này nữa: nó đọc đúng chuỗi
                     'cart.title' mà .cartdrawer__title phía trên vừa in ra, nên
                     ngăn kéo rỗng hiện chữ "Giỏ hàng" HAI LẦN chồng nhau.

                     Nhánh có hàng thì giữ nguyên: ở đó .hpop__head là "Sản phẩm
                     mới thêm" — một câu khác, nói một việc khác. */ ?>
            <p class="hpop__note"><?= e(t('cart.empty')) ?></p>
            <ul class="hpop__list" role="list">
                <li><a class="hpop__item" href="/san-pham"><?= e(t('cart.browse')) ?></a></li>
            </ul>
        <?php else: ?>
            <p class="hpop__head"><?= e(t('cart.recent')) ?></p>

            <ul class="cartpop" role="list">
                <?php foreach ($recent['lines'] as $line): ?>
                    <li>
                        <a class="cartpop__row" href="/san-pham/<?= e(rawurlencode($line['slug'])) ?>">
                            <span class="cartpop__thumb">
                                <?php
                                /* onerror ẩn thẻ ảnh đi để lộ nền của ô — ô
                                   trống trông như "chưa có ảnh", còn biểu
                                   tượng ảnh vỡ của trình duyệt trông như
                                   website hỏng. Cần vì đường dẫn ảnh do nhân
                                   viên gõ tay vào trang quản trị: gõ nhầm một
                                   ký tự, hoặc ảnh ở miền ngoài bị gỡ, là ra
                                   đúng cảnh đó. */
                                ?>
                                <?php if ($line['image'] !== ''): ?>
                                    <img src="<?= e($line['image']) ?>" alt="" loading="lazy"
                                         width="40" height="40"
                                         onerror="this.style.display='none'">
                                <?php endif; ?>
                            </span>

                            <?php /* Tên CẮT MỘT DÒNG bằng CSS chứ không cắt chuỗi
                                     trong PHP: máy chủ không biết bảng rộng bao
                                     nhiêu pixel, mà cắt theo số ký tự thì tên
                                     ngắn cũng bị thêm dấu ba chấm vô cớ. */ ?>
<?php /* lang="vi" — tên sản phẩm, cùng quy ước đã ghi dài ở
                                     _layout/product-card.php. Bảng xổ giỏ hàng có mặt
                                     trên mọi trang khung đầy đủ. */ ?>
                            <span class="cartpop__name" lang="vi"><?= e($line['name']) ?></span>

                            <?php if ($line['quantity'] > 1): ?>
                                <span class="cartpop__qty">×<?= (int) $line['quantity'] ?></span>
                            <?php endif; ?>

                            <span class="cartpop__price"><?= money($line['price']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="cartpop__foot">
                <span class="cartpop__more">
                    <?= $recent['more'] > 0
                        ? e(t('cart.more', [':n' => (string) (int) $recent['more']]))
                        : '' ?>
                </span>
                <a class="cartpop__cta" href="/gio-hang"><?= e(t('cart.view')) ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
