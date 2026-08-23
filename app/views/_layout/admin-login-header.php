<?php

/**
 * _layout/admin-login-header.php — đầu trang của CỔNG QUẢN TRỊ.
 *
 * Dựng theo "Admin Login.dc.html": tên hiệu bên trái kèm huy hiệu ADMIN, chỉ
 * báo môi trường bên phải. Không có gì khác — không điều hướng, không hotline.
 *
 * KHÁC auth-header.php ĐÚNG HAI CHỖ, và cả hai đều có lý do:
 *
 *   1. TÊN HIỆU KHÔNG PHẢI LIÊN KẾT. Bản của khách cho bấm về trang chủ. Ở
 *      đây thì không: người đứng trước cổng này đang muốn vào khu quản trị,
 *      ném họ ra trang bán hàng không giúp được gì. Bản thiết kế cũng vẽ nó
 *      là chữ thường, không phải liên kết.
 *
 *   2. THAY HOTLINE BẰNG CHỈ BÁO MÔI TRƯỜNG. Số tổng đài chăm sóc khách hàng
 *      không giúp được nhân viên không đăng nhập được. Thứ họ cần biết ở đây
 *      là mình đang gõ mật khẩu vào máy chủ NÀO — xem khối dưới.
 *
 * Dùng qua $bareHeader trong _layout/master.php, không gọi trực tiếp.
 */

/*
 * CHỈ BÁO MÔI TRƯỜNG — đọc từ APP_ENV, không gõ cứng.
 *
 * Bản thiết kế cho chọn giữa "Production" và "Staging" bằng một ô trong trình
 * sửa. Ở đây thì nó phải nói SỰ THẬT về máy chủ đang chạy, nếu không thì nó
 * còn tệ hơn là không có: một người tưởng mình đang ở bản thử mà thực ra đang
 * sửa dữ liệu thật.
 *
 * Mọi giá trị KHÔNG PHẢI 'production' đều đọc là bản thử và ăn màu hổ phách.
 * Chọn mặc định về phía cảnh báo: một máy chủ thật bị gắn nhãn vàng chỉ gây
 * khó chịu, còn máy chủ thử gắn nhãn xanh "PRODUCTION" thì dạy người ta tin
 * nhầm cái nhãn.
 */
$laProduction = config('app.env') === 'production';

/* strtoupper() chứ không phải bản UTF-8: APP_ENV là một mã ASCII do người
   triển khai đặt ('production', 'staging', 'local'), không phải chữ tiếng
   Việt. Rỗng thì đọc là 'LOCAL' — không có .env nghĩa là máy của lập trình
   viên, và một nhãn trống thì không cảnh báo được ai. */
$envLabel = $laProduction
    ? 'PRODUCTION'
    : strtoupper(trim((string) config('app.env')) ?: 'local');
?>

<header class="alogbar">
    <span class="alogbar__brand">
        <span class="alogbar__logo">Vin <em>Eyewear</em></span>
        <span class="alogbar__badge">ADMIN</span>
    </span>

    <span class="alogbar__env<?= $laProduction ? '' : ' alogbar__env--thu' ?>">
        <?php /* Chấm tròn là trang trí thuần tuý — chữ ngay cạnh đã nói đủ,
                 nên aria-hidden để trình đọc màn hình không đọc một ô trống. */ ?>
        <span class="alogbar__dot" aria-hidden="true"></span><?= e($envLabel) ?>
    </span>
</header>
