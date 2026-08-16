<?php

/**
 * errors/404.php — không tìm thấy trang.
 *
 * Không breadcrumb, không đầu trang: người tới đây đang ở một địa chỉ KHÔNG
 * tồn tại, nên chẳng có chỗ nào trong cây điều hướng để định vị họ vào. Thứ
 * duy nhất cần là một lối đi tiếp.
 */

$pageTitle = '404 - Trang không tìm thấy | Vin Eyewear';
?>

<section class="error-page">
    <div class="error-page__container">
        <span class="error-page__code">404</span>
        <h1 class="error-page__title">Trang không tìm thấy</h1>
        <p class="error-page__desc">Xin lỗi, trang bạn đang tìm kiếm không tồn tại hoặc đã bị di chuyển.</p>
        <a href="/" class="error-page__btn">Về trang chủ</a>
    </div>
</section>