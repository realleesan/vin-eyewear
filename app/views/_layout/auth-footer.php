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
 * "Điều khoản" chỉ hiện khi văn bản ĐÃ TỒN TẠI. Trước đây nó trỏ cứng tới
 * /chinh-sach#dieu-khoan, mà config/policy.php không có mục nào mang neo đó —
 * bấm vào chỉ nhảy lên đầu trang chính sách. Một liên kết pháp lý dẫn tới
 * không đâu thì tệ hơn là không có liên kết. Xem config/auth.php.
 *
 * Dùng qua $bareLayout trong _layout/master.php, không gọi trực tiếp.
 */

$termsUrl = (string) config('auth.consent.terms_url', '');
?>

<footer class="barefoot">
    <div class="barefoot__inner">
        <span>© <?= date('Y') ?> Vin Eyewear · <?= e(config('company.name')) ?></span>

        <div class="barefoot__links">
            <a href="<?= e((string) config('auth.consent.privacy_url', '/chinh-sach#bao-mat')) ?>">Chính sách bảo mật</a>
            <?php if ($termsUrl !== ''): ?>
                <a href="<?= e($termsUrl) ?>">Điều khoản</a>
            <?php endif; ?>
        </div>
    </div>
</footer>
