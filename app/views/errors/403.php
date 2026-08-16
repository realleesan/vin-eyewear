<?php

/**
 * errors/403.php — không đủ quyền truy cập.
 *
 * Khác 404: người dùng đã đăng nhập nhưng tài khoản không có vai trò cần
 * thiết. Nêu rõ để họ biết cần liên hệ ai, thay vì loay hoay tưởng gõ sai địa chỉ.
 */

$company = config('company');
?>
<section class="errpage">
    <div class="errpage__inner">
        <p class="errpage__code">403</p>
        <h1 class="errpage__title">Không đủ quyền truy cập</h1>
        <p class="errpage__lead">
            Tài khoản của bạn không có quyền xem trang này. Nếu bạn cho rằng đây là
            nhầm lẫn, hãy liên hệ quản trị viên để được cấp quyền.
        </p>
        <div class="errpage__actions">
            <a href="/" class="btn-primary btn-inline">Về trang chủ</a>
            <a href="/tai-khoan" class="btn-outline btn-inline">Tài khoản của tôi</a>
            <a href="<?= e($company['hotline_href']) ?>" class="btn-outline btn-inline">
                Gọi <?= e($company['hotline']) ?>
            </a>
        </div>
    </div>
</section>
