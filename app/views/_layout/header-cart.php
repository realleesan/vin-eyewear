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
       aria-label="Giỏ hàng, <?= (int) $cartCount ?>">
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

    <div class="hpop__panel hpop__panel--cart">
        <?php if ($recent['lines'] === []): ?>
            <p class="hpop__head">Giỏ hàng</p>
            <p class="hpop__note">Giỏ hàng đang trống</p>
            <ul class="hpop__list" role="list">
                <li><a class="hpop__item" href="/san-pham">Xem sản phẩm</a></li>
            </ul>
        <?php else: ?>
            <p class="hpop__head">Sản phẩm mới thêm</p>

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
                            <span class="cartpop__name"><?= e($line['name']) ?></span>

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
                        ? e(sprintf('Còn %d sản phẩm nữa trong giỏ', $recent['more']))
                        : '' ?>
                </span>
                <a class="cartpop__cta" href="/gio-hang">Xem giỏ hàng</a>
            </div>
        <?php endif; ?>
    </div>
</div>
