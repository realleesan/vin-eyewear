<?php

/**
 * _layout/auth-footer.php — chân trang RÚT GỌN của luồng tài khoản chưa đăng nhập.
 *
 * Dựng theo "Vin Eyewear Login.dc.html": một dòng bản quyền và hai liên kết
 * pháp lý. Chân trang đầy đủ (4 cột, đăng ký nhận tin, mạng xã hội) cao gần
 * bằng cả màn hình — đặt dưới một thẻ đăng nhập 620px thì nó át mất phần
 * việc chính của trang.
 *
 * Hai liên kết ở đây là ngoại lệ có lý do: đăng nhập tức là chấp nhận điều
 * khoản, nên chúng phải với tới được ngay tại chỗ.
 *
 * Dùng qua $bareLayout trong _layout/master.php, không gọi trực tiếp.
 */
?>

<footer class="barefoot">
    <div class="barefoot__inner">
        <span>© <?= date('Y') ?> Vin Eyewear · <?= e(config('company.name')) ?></span>

        <div class="barefoot__links">
            <a href="/chinh-sach#bao-mat">Chính sách bảo mật</a>
            <a href="/chinh-sach#dieu-khoan">Điều khoản</a>
        </div>
    </div>
</footer>
