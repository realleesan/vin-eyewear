<?php

/**
 * auth/reset.php — bước 2 của quên mật khẩu: chọn mật khẩu mới.
 *
 * Dùng CHUNG thẻ hai cột của "Vin Eyewear Login.dc.html", cùng lý do đã ghi ở
 * đầu auth/forgot.php.
 *
 * Tới đây bằng liên kết chứa token, nên trang này mở được khi CHƯA đăng nhập.
 * Token đã được kiểm ở controller; $valid nói kết quả.
 */
?>

<section class="authwrap">
    <div class="authcard">

        <?php
        /* CỘT ẢNH THƯƠNG HIỆU ĐÃ GỠ (12/09/2026) — bản thiết kế
           "Đăng ký Đăng nhập.dc.html" vẽ một cột. Xem khối chú thích cùng tên
           trong auth/index.php về lý do phải gỡ HẲN markup chứ không chỉ ẩn. */
        ?>

        <div class="authcard__panel">

            <?php if (!$valid): ?>

                <div class="authhead">
                    <h1 class="authhead__title">Liên kết không dùng được</h1>
                    <p class="authhead__lead">Hãy xin một liên kết mới.</p>
                </div>

                <div class="authdone" role="alert">
                    <p>
                        Liên kết đặt lại mật khẩu đã hết hạn, đã được dùng, hoặc bị sao
                        chép thiếu. Mỗi liên kết chỉ có hiệu lực 60 phút và dùng được
                        một lần.
                    </p>
                </div>

                <a class="authbtn authbtn--primary" href="/quen-mat-khau">Yêu cầu liên kết mới</a>

                <p class="authalt"><a href="/auth">← Quay lại đăng nhập</a></p>

            <?php else: ?>

                <div class="authhead">
                    <h1 class="authhead__title">Đặt mật khẩu mới</h1>
                    <p class="authhead__lead">
                        Cho tài khoản <strong><?= e($email) ?></strong>.
                    </p>
                </div>

                <?php if ($error !== null): ?>
                    <p class="authflash authflash--err" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <form class="authform" method="post" action="/dat-lai-mat-khau/luu" data-pw-rules>
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <!-- Ô email ẩn: trình quản lý mật khẩu cần biết mật khẩu mới
                         này thuộc về tài khoản nào để lưu đè đúng mục. Thiếu nó,
                         nhiều trình sẽ tạo một mục mới không có tên đăng nhập. -->
                    <input type="text" name="username" value="<?= e($email) ?>"
                           autocomplete="username" hidden readonly>

                    <label class="authfield">
                        <span class="authfield__label">Mật khẩu mới</span>
                        <?php partial('auth/_password', [
                            'pw_name'     => 'new_password',
                            'pw_auto'     => 'new-password',
                            'pw_holder'   => 'Mật khẩu mới',
                            'pw_min'      => 8,
                            'pw_required' => true,
                        ]); ?>
                    </label>

                    <label class="authfield">
                        <span class="authfield__label">Nhập lại mật khẩu mới</span>
                        <?php partial('auth/_password', [
                            'pw_name'     => 'new_password_confirm',
                            'pw_auto'     => 'new-password',
                            'pw_holder'   => '••••••••',
                            'pw_min'      => 8,
                            'pw_required' => true,
                        ]); ?>
                    </label>

                    <?php partial('auth/_password-rules'); ?>

                    <button type="submit" class="authbtn authbtn--primary">Đặt mật khẩu mới</button>
                </form>

                <p class="authnote">
                    Đổi xong, mọi thiết bị đang ghi nhớ đăng nhập của tài khoản này
                    sẽ bị đăng xuất.
                </p>

            <?php endif; ?>
        </div>
    </div>
</section>
