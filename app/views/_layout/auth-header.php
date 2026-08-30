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

    <span class="barebar__help">
        Bạn cần hỗ trợ?
        <a href="<?= e(config('company.hotline_href')) ?>"><?= e(config('company.hotline')) ?></a>
    </span>
</header>
