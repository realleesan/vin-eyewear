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

        <div class="authcard__brand">
            <img class="authcard__photo"
                 src="<?= designImage('login-photo', 'assets/images/hero-models.jpg') ?>"
                 alt="" width="600" height="620">
            <div class="authcard__caption">
                <p class="authcard__quote">Kính đẹp là kính hợp với chính bạn.</p>
                <p class="authcard__sub">Hơn 50 thương hiệu quốc tế · Đo mắt chuẩn phòng khám</p>
            </div>
        </div>

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

                <form class="authform" method="post" action="/dat-lai-mat-khau/luu">
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
                            'pw_holder'   => 'Tối thiểu 8 ký tự',
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
