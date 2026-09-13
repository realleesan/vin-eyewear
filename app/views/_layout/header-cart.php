<?php
/*
 * ═══════════════════════════════════════════════════════════════════════════
 * GIỎ HÀNG TRÊN THANH ĐẦU TRANG — icon, số món, và ngăn kéo
 *
 * Ở FILE RIÊNG vì hai chỗ cùng in nó: _layout/header.php khi dựng cả trang, và
 * _layout/buy-fragment.php khi trả lời buy-flow.js ở chế độ mảnh. Đó cũng là
 * lý do [data-cart] phải nằm trên phần tử NGOÀI CÙNG của file này — buy-flow.js
 * tìm đúng thuộc tính ấy để thay cụm giỏ sau mỗi lần thêm hàng.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * DÁNG THEO MẪU: BẢNG PHỦ TỪ TRÊN XUỐNG, KHÔNG PHẢI NGĂN KÉO BÊN PHẢI
 *
 * Mẫu vẽ giỏ hàng thành một bảng nền #f3f3f5 phủ từ dưới thanh đầu trang
 * xuống hết màn hình, hàng tab căn giữa ở đỉnh, nút đóng nép phải.
 *
 * Nên `offcanvas-top` chứ không còn `offcanvas-end`: Bootstrap trượt tấm từ
 * trên xuống và tấm chiếm trọn bề ngang — đúng hình của mẫu. Đổi đúng một
 * lớp, assets/js/header.js không phải sửa gì (nó chỉ gọi
 * `new bootstrap.Offcanvas(cartPanel)`, không quan tâm hướng).
 *
 * KHÔNG CÓ TAB "WISHLIST". Mẫu có hai tab (Bag / Wishlist) nhưng site này
 * chưa có chức năng yêu thích. Dựng một tab bấm vào không ra gì là tệ hơn
 * hẳn so với không dựng — hàng tab một mục vẫn giữ đúng dáng viên căn giữa
 * của mẫu. Có chức năng ấy thì thêm tab thứ hai vào đúng chỗ này.
 * ═══════════════════════════════════════════════════════════════════════════
 */

$cartCount = count($_SESSION['cart'] ?? []);

/* Chỉ truy vấn khi giỏ có hàng — giỏ rỗng là trường hợp phổ biến nhất và nó
   không cần một lượt xuống CSDL nào. */
$recent = $cartCount > 0 ? CartController::recent(5) : ['lines' => [], 'more' => 0];

/* ┌─ SỐ MÓN ĐÃ LƯU — cho tab "Yêu thích" bên cạnh ──────────────────────────
   │ Một câu COUNT, và chỉ hỏi khi khách ĐÃ đăng nhập VÀ bảng đã dựng: chưa
   │ đăng nhập thì danh sách yêu thích gắn với ai cũng không biết, nên số ấy
   │ luôn bằng 0 và không có gì để hỏi.
   │
   │ Ngăn kéo này dựng ở MỌI trang khung đầy đủ, nên mỗi truy vấn thêm vào
   │ đây là một truy vấn cho cả site — lý do phải chặn bằng hai điều kiện
   │ trên chứ không hỏi vô điều kiện. */
$wishId    = AuthMiddleware::customerId();
$wishCount = ($wishId !== null && FavoriteModel::available())
    ? FavoriteModel::dem($wishId)
    : 0;
?>
<div class="hpop" data-hpop data-cart>

    <?php /* LÀ <a href="/gio-hang"> THẬT, không phải <button>. Tắt JavaScript
             thì bấm vào là sang trang giỏ — không gãy lối nào. header.js chặn
             cú bấm và mở ngăn kéo khi Bootstrap có mặt. */ ?>
    <a href="/gio-hang" class="hpop__trigger oa-header__btn"
       data-hpop-trigger
       aria-label="<?= e(t('cart.aria', [':n' => (string) (int) $cartCount])) ?>">
        <?php /* Túi xách, không phải xe đẩy: mẫu dùng dáng túi có quai. */ ?>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
            <path d="M5 8h14l-1 13H6L5 8z"/>
            <path d="M9 8V6a3 3 0 0 1 6 0v2"/>
        </svg>
        <?php /* Chỉ hiện số khi giỏ CÓ hàng. Mẫu in "0" cạnh nhãn tab bên
                 trong bảng, nhưng một chấm đếm trên icon mà ghi "0" thì là
                 báo động giả. */ ?>
        <?php if ($cartCount > 0): ?>
            <span class="oa-header__count" aria-hidden="true"><?= (int) $cartCount ?></span>
        <?php endif; ?>
    </a>

    <?php
    /* BỐN LỚP TRÊN CÙNG MỘT THẺ, KHÔNG THÊM THẺ NÀO:
         offcanvas offcanvas-top  hợp đồng lớp mà Bootstrap JS đọc
         hpop__panel              hợp đồng mà header.js và buy-flow.js đọc
         oa-panel oa-panel--full  kiểu dáng, khai trong oa.css

       tabindex="-1": Offcanvas đặt tiêu điểm vào chính tấm khi mở.
       role/aria-modal do Bootstrap tự gắn — khai tay đè lên thứ nó quản lý là
       cách sinh ra hai nguồn sự thật. */
    ?>
    <div class="hpop__panel hpop__panel--cart oa-panel oa-panel--full offcanvas offcanvas-top" tabindex="-1"
         id="cartDrawer" aria-label="<?= e(t('cart.title')) ?>">

        <?php /* Hàng đỉnh ba cột: ô trống · tab căn giữa · nút đóng nép phải.
                 Ô trống bên trái là thứ giữ cho hàng tab ở CHÍNH GIỮA màn hình
                 chứ không phải giữa khoảng còn lại sau nút đóng. */ ?>
        <?php /* ┌─ HAI TAB — DÙNG CHUNG BỘ LỚP VỚI TRANG /gio-hang ──────────
                 │ .ctabs* khai một lần trong oa.css và dùng ở cả hai chỗ, nên
                 │ hàng tab trong ngăn kéo này trông y hệt hàng tab trên trang
                 │ giỏ. Trước 13/09/2026 ở đây là .oa-tab--caps, một dáng thứ
                 │ hai cho cùng một vai trò.
                 │
                 │ "Yêu thích" là LIÊN KẾT, không phải tab đổi nội dung tại
                 │ chỗ: danh sách ấy đã có nhà riêng ở /tai-khoan?muc=da-luu từ
                 │ 12/09. Dựng thêm một bản trong ngăn kéo là hai chỗ vẽ cùng
                 │ một danh sách — và chúng sẽ lệch nhau. Cùng lối với tab
                 │ tương ứng trên trang giỏ.
                 │
                 │ Chưa đăng nhập thì số là 0 và liên kết vẫn còn: bấm vào,
                 │ trang tài khoản tự đẩy sang /auth kèm đường quay lại. Giấu
                 │ tab đi thì khách không có đường nào biết chức năng tồn tại.
                 └──────────────────────────────────────────────────────────── */ ?>
        <div class="ctabs ctabs--drawer">
            <span class="ctabs__on">
                <?= e(t('cart.tab_bag')) ?><sup><?= (int) $cartCount ?><span class="sr-only"> <?= e(t('cart.tab_bag_sr')) ?></span></sup>
            </span>

            <a class="ctabs__off" href="/tai-khoan?muc=da-luu">
                <?= e(t('cart.tab_wish')) ?><sup><?= (int) $wishCount ?><span class="sr-only"> <?= e(t('cart.tab_wish_sr')) ?></span></sup>
            </a>

            <button type="button" class="ctabs__x" data-cart-close
                    aria-label="<?= e(t('menu.close')) ?>">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                     stroke-width="1.3" aria-hidden="true">
                    <line x1="2" y1="2" x2="14" y2="14"></line>
                    <line x1="14" y1="2" x2="2" y2="14"></line>
                </svg>
            </button>
        </div>

        <?php if ($recent['lines'] === []): ?>

            <div class="oa-empty">
                <p><?= e(t('cart.empty')) ?></p>
                <?php /* Nút VUÔNG cao 40 viền #bbb — dáng riêng của mẫu cho
                         nút "Continue Shopping" trong bảng giỏ, không dùng
                         .oa-btn viên ở đây. */ ?>
                <a class="cartdrawer__cta" href="/san-pham/gong-kinh"><?= e(t('cart.browse')) ?></a>
            </div>

        <?php else: ?>

            <div class="cartdrawer__body">
                <p class="oa-label" style="text-align:center"><?= e(t('cart.recent')) ?></p>

                <ul class="cartdrawer__list oa-plain" role="list">
                    <?php foreach ($recent['lines'] as $line): ?>
                        <li>
                            <a class="cartdrawer__row" href="/san-pham/<?= e(rawurlencode($line['slug'])) ?>">
                                <span class="oa-slot cartdrawer__thumb">
                                    <?php
                                    /* Thiếu ảnh thì để ô xám trống, KHÔNG in <img> rỗng:
                                       ô xám trông như "chưa có ảnh", còn biểu tượng ảnh
                                       vỡ của trình duyệt trông như website hỏng. Cần,
                                       vì đường dẫn ảnh do nhân viên gõ tay ở trang quản
                                       trị — gõ nhầm một ký tự là ra đúng cảnh đó. */
                                    ?>
                                    <?php if ($line['image'] !== ''): ?>
                                        <img src="<?= e($line['image']) ?>" alt="" loading="lazy"
                                             width="56" height="56"
                                             onerror="this.style.display='none'">
                                    <?php endif; ?>
                                </span>

                                <?php /* lang="vi" — tên sản phẩm luôn là tiếng Việt kể cả
                                         trên bản tiếng Anh của giao diện; xem khối chú
                                         thích dài ở _layout/product-card.php. */ ?>
                                <span class="cartdrawer__name" lang="vi"><?= e($line['name']) ?></span>

                                <?php if ($line['quantity'] > 1): ?>
                                    <span class="cartdrawer__qty">×<?= (int) $line['quantity'] ?></span>
                                <?php endif; ?>

                                <span class="cartdrawer__price"><?= money($line['price']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="cartdrawer__foot">
                    <?php if ($recent['more'] > 0): ?>
                        <p class="oa-muted"><?= e(t('cart.more', [':n' => (string) (int) $recent['more']])) ?></p>
                    <?php endif; ?>
                    <a class="cartdrawer__cta" href="/gio-hang"><?= e(t('cart.view')) ?></a>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>
