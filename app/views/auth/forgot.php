<?php

/**
 * auth/forgot.php — bước 1 của quên mật khẩu: nhập email hoặc số điện thoại.
 *
 * Dùng CHUNG thẻ hai cột của "Vin Eyewear Login.dc.html". Bản thiết kế không
 * vẽ riêng trang này, nhưng nó tới từ chính liên kết "Quên mật khẩu?" trong
 * form đăng nhập — để nó ở kiểu cũ (khối nền tối + breadcrumb) thì bấm một cái
 * là trang đổi hẳn diện mạo.
 *
 * Sau khi gửi, trang LUÔN hiện cùng một thông báo dù tài khoản có tồn tại hay
 * không. Nói "không tìm thấy email này" sẽ biến trang này thành công cụ dò
 * xem ai là khách hàng của cửa hàng.
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

            <?php if ($done !== null): ?>

                <div class="authhead">
                    <h1 class="authhead__title">Đã nhận yêu cầu</h1>
                    <p class="authhead__lead">Kiểm tra hộp thư của bạn.</p>
                </div>

                <div class="authdone" role="status">
                    <?php if ($sent): ?>
                        <p>
                            Nếu thông tin bạn nhập khớp với một tài khoản, chúng tôi vừa gửi
                            một email kèm liên kết đặt lại mật khẩu. Liên kết có hiệu lực
                            trong 60 phút.
                        </p>
                        <p class="authdone__note">
                            Chưa thấy email? Kiểm tra thư mục spam, hoặc gọi
                            <a href="<?= e(config('company.hotline_href')) ?>"><?= e(config('company.hotline')) ?></a>
                            để được hỗ trợ trực tiếp.
                        </p>
                    <?php else: ?>
                        <p>
                            Nhân viên sẽ liên hệ với bạn để xác minh và hướng dẫn đặt lại
                            mật khẩu. Để nhanh hơn, bạn có thể gọi
                            <a href="<?= e(config('company.hotline_href')) ?>"><?= e(config('company.hotline')) ?></a>
                            trong giờ làm việc.
                        </p>
                    <?php endif; ?>
                </div>

                <p class="authalt"><a href="/auth">← Quay lại đăng nhập</a></p>

            <?php else: ?>

                <div class="authhead">
                    <h1 class="authhead__title">Quên mật khẩu</h1>
                    <p class="authhead__lead">
                        Nhập email hoặc số điện thoại bạn đã dùng để đăng ký.
                    </p>
                </div>

                <?php if ($error !== null): ?>
                    <p class="authflash authflash--err" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <form class="authform" method="post" action="/quen-mat-khau/gui">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                    <label class="authfield">
                        <span class="authfield__label">Số điện thoại hoặc email</span>
                        <input class="authfield__input" type="text" name="contact" required
                               autocomplete="username" autofocus
                               placeholder="Số điện thoại / Email"
                               value="<?= e($old) ?>">
                    </label>

                    <button type="submit" class="authbtn authbtn--primary">Gửi yêu cầu</button>
                </form>

                <p class="authalt">Nhớ ra rồi? <a href="/auth">Quay lại đăng nhập</a></p>

            <?php endif; ?>
        </div>
    </div>
</section>
