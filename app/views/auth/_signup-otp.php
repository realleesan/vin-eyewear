<?php

/**
 * auth/_signup-otp.php — màn "Xác minh mã OTP" của luồng đăng ký.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÀN THỨ HAI CỦA ĐĂNG KÝ — theo "Đăng ký Đăng nhập.dc.html" (12/09/2026).
 *
 * Tới được đây nghĩa là form đăng ký đã hợp lệ HẾT và mã đã thật sự gửi đi:
 * AuthController::signupSubmit() kiểm xong mới gọi signupIssueCode(), rồi cất
 * hồ sơ chờ vào phiên. Tài khoản ra đời ở lượt gửi của form này, không sớm hơn
 * — xem AuthController::signupVerify().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SÁU Ô RỜI, KHÔNG PHẢI MỘT Ô SÁU KÝ TỰ
 *
 * Cùng khuôn với màn nhập mã của luồng quên mật khẩu (auth/forgot.php), và
 * dùng lại đúng bộ lớp .aotp/.aresend của nó — hai màn hỏi cùng một thứ thì
 * không nên trông khác nhau.
 *
 * Mỗi ô là một <input> THẬT nên tắt JavaScript vẫn gõ đủ sáu số rồi bấm được;
 * assets/js/auth.js chỉ thêm việc tự nhảy ô. Máy chủ nối sáu ô lại rồi kiểm —
 * xem signupVerify(), nhánh is_array($_POST['ma']).
 *
 * Nhận qua partial(): $signup (mảng của signupView()), $errors, $redirect, và
 * $loi để vẽ câu lỗi của ô mã.
 */

$signup = $signup ?? [];
$errors = $errors ?? [];
$loi    = $loi    ?? static function (string $f): void {};
$cho    = (int) ($signup['wait'] ?? 0);
?>

<form class="authform" method="post" action="/auth/dang-ky/xac-minh">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= e($redirect ?? '') ?>">

    <div class="authfield">
        <span class="authfield__label">Mã xác minh</span>

        <fieldset class="aotp">
            <legend class="sr-only">Mã xác minh gồm <?= Otp::LENGTH ?> chữ số</legend>
            <?php for ($i = 0; $i < Otp::LENGTH; $i++): ?>
                <input class="aotp__box<?= isset($errors['ma']) ? ' is-err' : '' ?>"
                       type="text" name="ma[]" inputmode="numeric"
                       pattern="[0-9]*" maxlength="1" autocomplete="one-time-code"
                       aria-label="Chữ số thứ <?= $i + 1 ?>"
                       <?= $i === 0 ? 'autofocus' : '' ?>>
            <?php endfor; ?>
        </fieldset>

        <?php $loi('ma'); ?>
    </div>

    <button type="submit" class="authbtn authbtn--primary">Xác nhận</button>
</form>

<?php
/*
 * GỬI LẠI MÃ — nút luôn HIỆN, khoá lại trong lúc chờ.
 *
 * Ẩn hẳn nút rồi cho nó hiện ra sau 60 giây thì khách không biết có thứ đó
 * tồn tại, và ngồi chờ một thứ họ không biết là bao lâu. Hiện và khoá thì câu
 * trả lời nằm ngay trên nhãn nút.
 *
 * Con số đầu tiên do MÁY CHỦ phát ra ($signup['wait']); auth.js chỉ đếm lùi
 * cho đỡ phải tải lại trang. Chốt thật nằm ở signupIssueCode().
 */
?>
<div class="aresend" data-wait="<?= $cho ?>">
    <p class="aresend__ask">Bạn chưa nhận được mã?</p>

    <form method="post" action="/auth/dang-ky/gui-ma">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect ?? '') ?>">
        <button type="submit" class="aresend__btn" data-resend <?= $cho > 0 ? 'disabled' : '' ?>>
            Gửi lại mã<span class="aresend__num"<?= $cho > 0 ? '' : ' hidden' ?>>
                (<?= $cho ?>s)</span>
        </button>
    </form>

    <span class="aresend__or">hoặc gọi</span>
    <a class="aresend__link" href="<?= e(config('company.hotline_href')) ?>">
        <?= e(config('company.hotline')) ?>
    </a>
</div>

<?php
/*
 * ĐƯỜNG LÙI VỀ FORM.
 *
 * Gõ nhầm số điện thoại thì đây là lối duy nhất sửa được — màn này không có ô
 * số nào (xem khối "SỐ LẤY TỪ HỒ SƠ ĐANG CHỜ" trong signupSendCode()).
 *
 * Là một LIÊN KẾT thường tới /auth?tab=dang-ky: hồ sơ chờ vẫn nằm trong phiên
 * nên không mất gì, và index() thấy ?buoc= trống thì vẽ lại form.
 */
?>
<p class="authalt">
    Gõ nhầm số? <a href="<?= e('/auth?tab=dang-ky' . (($redirect ?? '') !== '' && ($redirect ?? '') !== '/' ? '&redirect=' . rawurlencode((string) $redirect) : '')) ?>">Sửa lại thông tin</a>
</p>
