<?php

/**
 * _layout/admin-login-footer.php — chân trang của CỔNG QUẢN TRỊ.
 *
 * Dựng theo "Admin Login.dc.html": một dòng bản quyền bên trái, một đường
 * liên hệ xin cấp quyền bên phải.
 *
 * KHÁC auth-footer.php ở chỗ BỎ HAI LIÊN KẾT PHÁP LÝ. Bên đó chúng có lý do
 * rõ ràng — tạo tài khoản tức là chấp nhận điều khoản, nên phải với tới được
 * ngay tại chỗ. Ở đây thì người đăng nhập là nhân viên đã ký hợp đồng lao
 * động; "Chính sách bảo mật" dành cho khách mua hàng không phải thứ họ cần
 * đọc trước khi vào ca.
 *
 * Thay vào đó là thứ người đứng ngoài cửa THẬT SỰ cần: hỏi ai để được cấp
 * quyền. Không có dòng đó thì người mới vào không có bước tiếp theo nào ngoài
 * việc thử lại mật khẩu.
 *
 * Dùng qua $bareFooter trong _layout/master.php, không gọi trực tiếp.
 */

$emailIt = (string) config('company.email_it');
?>

<footer class="alogfoot">
    <span>
        <?php /*
            SỐ PHIÊN BẢN LẤY NGUYÊN VĂN TỪ BẢN THIẾT KẾ ("v2.4").

            Dự án chưa theo dõi phiên bản khu quản trị ở đâu cả — không có
            hằng, không có tệp, không có thẻ git nào mang con số này. Nên nó
            đứng đây như một dòng chữ chết: đúng vào ngày dựng trang, và sai
            dần từ ngày hôm sau.

            Giữ lại vì bản thiết kế vẽ như vậy, nhưng KHÔNG bịa thêm một cơ
            chế đánh số chỉ để nuôi nó. Khi nào cửa hàng thật sự đánh số các
            bản quản trị thì thay chuỗi này bằng giá trị đọc từ cấu hình; còn
            không thì bỏ hẳn mấy chữ "v2.4" đi vẫn hơn là để một con số không
            ai cập nhật.
        */ ?>
        © <?= date('Y') ?> <?= e(config('company.short_name')) ?> · Hệ thống quản trị nội bộ v2.4
    </span>

    <span>
        Cần cấp quyền? Liên hệ
        <a href="mailto:<?= e($emailIt) ?>"><?= e($emailIt) ?></a>
    </span>
</footer>
