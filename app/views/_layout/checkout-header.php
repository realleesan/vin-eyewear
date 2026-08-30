<?php

/**
 * _layout/checkout-header.php — đầu trang RÚT GỌN của trang thanh toán.
 *
 * Dựng theo "Vin Eyewear Checkout.dc.html": logo bên trái, bên phải là ổ khoá
 * xanh + "Thanh toán an toàn" + số hỗ trợ.
 *
 * KHÁC _layout/auth-header.php ở đúng nửa bên phải. Cùng dùng bộ lớp .barebar*
 * trong components/bare-shell.css — hai bản thiết kế vẽ khối này giống nhau
 * tới từng con số, chỉ khác chữ.
 *
 * Vì sao trang thanh toán cũng bỏ thanh điều hướng: khách đang ở bước cuối của
 * việc mua hàng. Mọi liên kết "Kính mát / Gọng kính / Khuyến mãi" ở đây đều là
 * một lối để họ rời khỏi giỏ hàng đã điền dở.
 *
 * Ổ khoá KHÔNG phải trang trí: nó là chỗ khách quen tìm để yên tâm nhập thông
 * tin. Để màu xanh (--success) chứ không màu thương hiệu, vì đó là quy ước
 * người dùng đã biết từ thanh địa chỉ trình duyệt.
 *
 * DẢI THÔNG BÁO ở trên cùng là thứ DUY NHẤT bản thiết kế giữ lại từ đầu trang
 * đầy đủ. Nó không phải liên kết nên không kéo ai ra khỏi luồng, mà lại trả lời
 * đúng câu hỏi khách đang có ở bước này: "có mất phí ship không?". Chữ dùng
 * chung với dải trên trang thường ('Miễn phí giao hàng toàn quốc cho đơn từ 1.000.000₫') để hai nơi không nói lệch nhau.
 *
 * Dùng qua $bareLayout trong _layout/master.php, không gọi trực tiếp.
 */
?>

<p class="barepromo">Miễn phí giao hàng toàn quốc cho đơn từ 1.000.000₫</p>

<header class="barebar">
    <a class="barebar__logo" href="/">Vin <em>Eyewear</em></a>

    <?php
    /*
     * ĐỔI NGÔN NGỮ — cùng widget với đầu trang đầy đủ, xem _layout/translator.php.
     *
     * Có mặt ở đây dù bản thiết kế "Vin Eyewear Checkout.dc.html" không vẽ:
     * widget chỉ dịch được trang nào có thẻ của nó, nên thiếu ở bước này là
     * khách đang đọc tiếng Anh bỗng gặp một biểu mẫu tiền nong toàn tiếng
     * Việt. Đây đúng là bước không được phép để họ đọc mò.
     *
     * Đặt TRƯỚC cụm "Thanh toán an toàn" để ổ khoá vẫn là thứ sát mép phải —
     * đó là chỗ khách quen liếc tới để yên tâm, đừng đẩy nó vào giữa.
     */
    partial('_layout/translator');
    ?>

    <span class="barebar__help barebar__help--pay">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#4d7a3f"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="4" y="10" width="16" height="11" rx="2"></rect>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
        </svg>
        <span>Thanh toán an toàn</span>
        <span class="barebar__dot" aria-hidden="true">·</span>
        <span>Hỗ trợ:
            <a href="<?= e(config('company.hotline_href')) ?>"><?= e(config('company.hotline')) ?></a>
        </span>
    </span>
</header>
