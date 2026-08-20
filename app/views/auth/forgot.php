<?php

/**
 * auth/forgot.php — quên mật khẩu, bốn chặng trên cùng một địa chỉ.
 *
 *   ''          Nhập email hoặc số điện thoại  → POST /quen-mat-khau/gui
 *   ma          Nhập 6 số                      → POST /quen-mat-khau/xac-minh
 *   mat-khau    Đặt mật khẩu mới               → POST /quen-mat-khau/dat-lai
 *   xong        Đã đổi xong                    → lối ra: /auth
 *
 * Bước nào được phép mở là do AuthController::forgotStep() quyết, KHÔNG phải
 * ?buoc= trên URL — xem chú thích ở đó.
 *
 * Dùng CHUNG thẻ hai cột và toàn bộ lớp CSS của luồng đăng ký (.astage, .asay,
 * .aotp, .aresend, .arule). Hai luồng đi qua đúng những chặng giống nhau —
 * nhận mã, gõ mã, đặt mật khẩu — nên trông giống nhau là đúng, và assets/js/auth.js
 * tự bắt được sáu ô mã lẫn đồng hồ đếm ngược mà không phải thêm dòng nào.
 *
 * Nhận từ controller: $step, $forgot (rỗng khi chưa bắt đầu), $old, $error,
 * $notice.
 *
 * KHÔNG NÓI TÀI KHOẢN CÓ TỒN TẠI HAY KHÔNG. Gõ email lạ vào thì màn nhập mã
 * vẫn hiện lên y hệt; mã của một yêu cầu không khớp ai thì không được gửi đi
 * đâu nên không ai nhập đúng. Báo "không tìm thấy email này" sẽ biến trang này
 * thành công cụ dò xem ai là khách hàng của cửa hàng.
 */

$forgot = $forgot ?? [];
$where  = $forgot['display'] ?? '';

/** Dải ba bước ở đầu thẻ. Bước hiện tại quyết ba cái chấm sáng tới đâu. */
$stage = match ($step) {
    'mat-khau' => 2,
    'xong'     => 3,
    default    => 1,
};

/** Nút lùi của các màn giữa — cùng dáng với nút "‹" của luồng đăng ký. */
$backTo = static function (string $href): void { ?>
    <a class="aback" href="<?= e($href) ?>" aria-label="Quay lại">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 18l-6-6 6-6"></path>
        </svg>
    </a>
<?php };
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

            <?php if ($notice !== null): ?>
                <p class="authflash authflash--ok" role="status"><?= e($notice) ?></p>
            <?php endif; ?>

            <?php if ($error !== null): ?>
                <p class="authflash authflash--err" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <?php if ($step !== ''): ?>
                <ol class="astage" role="list">
                    <?php foreach (['Xác minh', 'Mật khẩu mới', 'Hoàn tất'] as $i => $label): ?>
                        <?php $n = $i + 1; ?>
                        <li class="astage__item<?= $n < $stage ? ' is-done' : ($n === $stage ? ' is-now' : '') ?>">
                            <span class="astage__dot" aria-hidden="true"><?= $n < $stage ? '✓' : $n ?></span>
                            <span class="astage__label"><?= e($label) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if ($step === ''): ?>

                <!-- ══════════ 1. NHẬP EMAIL HOẶC SỐ ĐIỆN THOẠI ══════════ -->
                <div class="authhead">
                    <h1 class="authhead__title">Quên mật khẩu</h1>
                    <p class="authhead__lead">
                        Nhập email hoặc số điện thoại bạn đã dùng để đăng ký.
                        Chúng tôi sẽ gửi mã xác minh gồm <?= Otp::LENGTH ?> chữ số.
                    </p>
                </div>

                <form class="authform" method="post" action="/quen-mat-khau/gui">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                    <label class="authfield">
                        <span class="authfield__label">Số điện thoại hoặc email</span>
                        <input class="authfield__input" type="text" name="contact" required
                               autocomplete="username" autofocus
                               placeholder="Số điện thoại / Email"
                               value="<?= e($old) ?>">
                        <?php /* Nói trước mã sẽ đi đường nào, để khách khỏi ngồi
                                 chờ tin nhắn trong khi mã nằm trong hộp thư. */ ?>
                        <span class="authfield__hint">
                            Gõ email thì mã gửi qua email, gõ số điện thoại thì mã gửi qua Zalo.
                        </span>
                    </label>

                    <button type="submit" class="authbtn authbtn--primary">Gửi mã xác minh</button>
                </form>

                <p class="authalt">Nhớ ra rồi? <a href="/auth">Quay lại đăng nhập</a></p>

            <?php elseif ($step === 'ma'): ?>

                <!-- ══════════ 2. NHẬP MÃ ══════════ -->
                <div class="asay">
                    <?php $backTo('/quen-mat-khau'); ?>
                    <p class="asay__title">Nhập mã xác minh</p>
                    <p class="asay__text">
                        <?= e($forgot['sentVia'] ?? '') ?> <strong><?= e($where) ?></strong>
                    </p>
                </div>

                <form class="authform" method="post" action="/quen-mat-khau/xac-minh">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                    <?php /* SÁU Ô RỜI, không phải một ô sáu ký tự — cùng khuôn với
                             màn nhập mã của luồng đăng ký. Mỗi ô là một <input>
                             thật nên tắt JavaScript vẫn gõ đủ sáu số rồi bấm được;
                             auth.js chỉ thêm việc tự nhảy ô. */ ?>
                    <fieldset class="aotp">
                        <legend class="sr-only">Mã xác minh gồm <?= Otp::LENGTH ?> chữ số</legend>
                        <?php for ($i = 0; $i < Otp::LENGTH; $i++): ?>
                            <input class="aotp__box" type="text" name="ma[]" inputmode="numeric"
                                   pattern="[0-9]*" maxlength="1" autocomplete="one-time-code"
                                   aria-label="Chữ số thứ <?= $i + 1 ?>"
                                   <?= $i === 0 ? 'autofocus' : '' ?>>
                        <?php endfor; ?>
                    </fieldset>

                    <button type="submit" class="authbtn authbtn--primary">Xác minh</button>
                </form>

                <?php /* Đồng hồ đếm ngược do máy chủ phát ra con số đầu tiên, auth.js
                         chỉ đếm lùi cho đỡ phải tải lại trang. Chốt thật nằm ở máy
                         chủ — xem AuthController::forgotResend(). */ ?>
                <div class="aresend" data-wait="<?= (int) ($forgot['wait'] ?? 0) ?>">
                    <p class="aresend__wait"<?= ($forgot['wait'] ?? 0) > 0 ? '' : ' hidden' ?>>
                        Vui lòng chờ <span class="aresend__num"><?= (int) ($forgot['wait'] ?? 0) ?></span> giây để gửi lại.
                    </p>

                    <div class="aresend__go"<?= ($forgot['wait'] ?? 0) > 0 ? ' hidden' : '' ?>>
                        <p class="aresend__ask">Bạn chưa nhận được mã?</p>
                        <form method="post" action="/quen-mat-khau/gui-lai">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <button type="submit" class="aresend__link">Gửi lại</button>
                        </form>
                        <span class="aresend__or">hoặc gọi</span>
                        <a class="aresend__link" href="<?= e(config('company.hotline_href')) ?>">
                            <?= e(config('company.hotline')) ?>
                        </a>
                    </div>
                </div>

            <?php elseif ($step === 'mat-khau'): ?>

                <!-- ══════════ 3. ĐẶT MẬT KHẨU MỚI ══════════ -->
                <div class="asay">
                    <p class="asay__title">Đặt mật khẩu mới</p>
                    <p class="asay__text">
                        Đã xác minh <strong><?= e($where) ?></strong>. Chọn mật khẩu mới cho tài khoản.
                    </p>
                </div>

                <?php /* KHÔNG có nút lùi ở màn này: lùi về màn nhập mã thì mã đã
                         dùng xong và bị xoá, khách sẽ kẹt ở một màn không qua được.
                         Muốn làm lại thì bắt đầu lại từ đầu. */ ?>
                <form class="authform" method="post" action="/quen-mat-khau/dat-lai" data-pw-rules>
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                    <label class="authfield">
                        <span class="sr-only">Mật khẩu mới</span>
                        <?php partial('auth/_password', [
                            'pw_name'     => 'new_password',
                            'pw_auto'     => 'new-password',
                            'pw_holder'   => 'Mật khẩu mới',
                            'pw_min'      => 8,
                            'pw_required' => true,
                        ]); ?>
                    </label>

                    <label class="authfield">
                        <span class="sr-only">Nhập lại mật khẩu mới</span>
                        <?php partial('auth/_password', [
                            'pw_name'     => 'new_password_confirm',
                            'pw_auto'     => 'new-password',
                            'pw_holder'   => 'Nhập lại mật khẩu mới',
                            'pw_min'      => 8,
                            'pw_required' => true,
                        ]); ?>
                    </label>

                    <?php partial('auth/_password-rules'); ?>

                    <button type="submit" class="authbtn authbtn--primary">Đổi mật khẩu</button>
                </form>

            <?php else: ?>

                <!-- ══════════ 4. XONG ══════════ -->
                <div class="authhead">
                    <h1 class="authhead__title">Đã đổi mật khẩu</h1>
                    <p class="authhead__lead">Đăng nhập lại bằng mật khẩu mới nhé.</p>
                </div>

                <div class="authdone" role="status">
                    <p>
                        Mật khẩu của bạn đã được đổi. Mọi thiết bị đang ghi nhớ đăng nhập
                        đã được đăng xuất, kể cả thiết bị của người khác nếu có.
                    </p>
                </div>

                <p class="authalt"><a href="/auth">Đăng nhập →</a></p>

            <?php endif; ?>
        </div>
    </div>
</section>
