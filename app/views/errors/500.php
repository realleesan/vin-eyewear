<?php

/**
 * errors/500.php — lỗi phía máy chủ.
 *
 * Không breadcrumb, không đầu trang — cùng lý do đã ghi ở errors/404.php:
 * trang lỗi không phải một nhánh của cây nội dung, nó là một ngõ cụt kèm lối ra.
 */

$pageTitle = '500 - Lỗi hệ thống | Vin Eyewear';
?>

<section class="error-page">
    <div class="error-page__container">
        <span class="error-page__code">500</span>
        <h1 class="error-page__title">Lỗi hệ thống</h1>
        <p class="error-page__desc">Đã xảy ra lỗi khi xử lý yêu cầu của bạn. Vui lòng thử lại sau.</p>
        <a href="/" class="error-page__btn">Về trang chủ</a>
    </div>
</section>