<?php

/**
 * wishlist/index.php — khung của trang /yeu-thich.
 *
 * CỐ Ý MỎNG. Toàn bộ lưới nằm trong auth/account/da-luu.php và file này chỉ
 * đặt nó vào một trang đứng riêng; lý do đầy đủ ở đầu WishlistController.
 *
 * .acct-sec là lớp mà tab "Đã lưu" vốn nằm trong (xem auth/profile.php), nên
 * mượn lại nó là mượn luôn khoảng đệm và bề rộng chữ đã cân sẵn — không thêm
 * một luật CSS nào cho trang này. account.css phải có mặt trong $pageStyles,
 * xem _layout/master.php.
 */

?>
<section class="acct-sec wlpage">
    <?php
    partial('auth/account/da-luu', [
        'saved'    => $saved    ?? [],
        'luuDuoc'  => $luuDuoc  ?? false,
        'variants' => $variants ?? [],
        'anhPhu'   => (bool) ($anhPhu ?? false),

        /* Đây là điểm khác duy nhất giữa hai cửa: mọi nút trong lưới (dấu
           trang, lật ảnh) phải quay về ĐÚNG trang này, không phải về tab
           trong trang tài khoản — khách vãng lai không vào được trang ấy. */
        'tabUrl'   => '/yeu-thich',
    ]);
    ?>
</section>
