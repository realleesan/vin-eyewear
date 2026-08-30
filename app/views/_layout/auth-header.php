<?php

/**
 * _layout/auth-header.php — đầu trang RÚT GỌN của luồng tài khoản chưa đăng nhập.
 *
 * Dựng theo "Vin Eyewear Login.dc.html": chỉ logo bên trái và số hỗ trợ bên
 * phải. KHÔNG có thanh điều hướng, ô tìm kiếm, giỏ hàng.
 *
 * Vì sao bỏ hết: trang đăng nhập chỉ có đúng một việc cần làm. Mọi liên kết
 * khác ở đầu trang đều là một lối để người dùng bỏ dở việc đó. Bản thiết kế
 * giữ lại đúng hai thứ — đường về trang chủ, và số điện thoại cho người không
 * đăng nhập được.
 *
 * Dùng qua $bareLayout trong _layout/master.php, không gọi trực tiếp.
 */
?>

<header class="barebar">
    <a class="barebar__logo" href="/">Vin <em>Eyewear</em></a>

    <?php
    /*
     * ĐỔI NGÔN NGỮ — cùng widget với đầu trang đầy đủ, xem _layout/translator.php.
     *
     * Bản thiết kế "Vin Eyewear Login.dc.html" chỉ giữ lại logo và số hỗ trợ,
     * và nguyên tắc của đầu trang này là bỏ mọi lối kéo người dùng bỏ dở việc
     * đăng nhập. Nút ngôn ngữ KHÔNG phải một lối như thế: nó không dẫn đi đâu,
     * chỉ đổi chữ của chính trang đang mở. Thiếu nó thì người đọc tiếng Anh
     * gặp một biểu mẫu đăng nhập tiếng Việt và không có cách nào đổi.
     */
    partial('_layout/translator');
    ?>

    <span class="barebar__help">
        Bạn cần hỗ trợ?
        <a href="<?= e(config('company.hotline_href')) ?>"><?= e(config('company.hotline')) ?></a>
    </span>
</header>
