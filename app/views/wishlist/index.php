<?php

/**
 * wishlist/index.php — màn hình /yeu-thich, dựng theo "Wishlist.dc.html"
 * (dự án aac1974c-…, 13/09/2026).
 *
 * CỐ Ý MỎNG. Toàn bộ lưới nằm trong auth/account/da-luu.php và file này chỉ
 * đặt nó vào một trang đứng riêng, cộng thêm HÀNG TAB ở trên; lý do đầy đủ ở
 * đầu WishlistController.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * HÀNG TAB LÀ PHẦN CỦA BẢN THIẾT KẾ, VÀ NÓ DÙNG LẠI .ctabs
 *
 * Mẫu vẽ hàng "BAG / WISHLIST" cùng nút ✕ ngay trên lưới — đúng hàng tab mà
 * trang giỏ hàng và ngăn kéo giỏ đã có từ 13/09, khai một lần ở oa.css.
 *
 * Dùng lại chứ không dựng bản thứ hai, dù hai bản thiết kế lệch nhau vài
 * pixel (mẫu này: chữ 18px, nền #e6e6e6, số mũ 10px — .ctabs đang là 16px,
 * #ebebeb, 9px, lấy theo "Cart Screen.dc.html"). Lý do: hai cái tab ấy đứng
 * CẠNH NHAU và người dùng bấm qua lại giữa chúng. Một bên 16px một bên 18px
 * là chữ nhảy cỡ ngay khi đổi tab — tệ hơn hẳn việc lệch 2px so với một bản
 * vẽ. ⚠ Muốn theo đúng mẫu này thì phải đổi CẢ trang giỏ hàng cùng lúc.
 *
 * ✕ ở đây trả khách về trang họ vừa rời, không phải một trang cố định: màn
 * yêu thích mở ra từ khắp nơi trong site. Cùng lối với ✕ của trang giỏ.
 * ─────────────────────────────────────────────────────────────────────────
 */

$saved    = $saved    ?? [];
$wishSo   = count($saved);

?>
<section class="wlpage">

    <div class="ctabs">
        <?php /* "Giỏ hàng" là LIÊN KẾT (tab đang tắt), "Yêu thích" là tab đang
                 đứng — ngược đúng với trang /gio-hang. Cùng bộ lớp, nên hai
                 màn không thể lệch dáng nhau. */ ?>
        <a class="ctabs__off" href="/gio-hang">
            <?= e(t('cart.tab_bag')) ?><sup><?= (int) CartController::count() ?><span class="sr-only"> <?= e(t('cart.tab_bag_sr')) ?></span></sup>
        </a>

        <span class="ctabs__on">
            <?= e(t('cart.tab_wish')) ?><sup><?= $wishSo ?><span class="sr-only"> <?= e(t('cart.tab_wish_sr')) ?></span></sup>
        </span>

        <a class="ctabs__x" href="/san-pham/gong-kinh" aria-label="<?= e(t('cart.close')) ?>">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                 stroke-width="1.4" aria-hidden="true">
                <line x1="2" y1="2" x2="14" y2="14"></line><line x1="14" y1="2" x2="2" y2="14"></line>
            </svg>
        </a>
    </div>

    <?php
    partial('auth/account/da-luu', [
        'saved'    => $saved,
        'luuDuoc'  => $luuDuoc  ?? false,
        'variants' => $variants ?? [],
        'anhPhu'   => (bool) ($anhPhu ?? false),

        /* Điểm khác duy nhất về dữ liệu giữa hai cửa: mọi nút trong lưới (dấu
           trang, lật ảnh) phải quay về ĐÚNG trang này, không phải về tab trong
           trang tài khoản — khách vãng lai không vào được trang ấy. */
        'tabUrl'   => '/yeu-thich',

        /* Hàng tab ở trên ĐÃ nói màn này là gì, nên không in thêm dòng tiêu đề
           "ĐÃ LƯU" — mẫu cũng không có. Trang tài khoản thì ngược lại: mục nào
           cũng có tiêu đề riêng, nên bên đó mặc định vẫn in. */
        'wlTieuDe' => false,
    ]);
    ?>
</section>
