<?php

/**
 * auth/account/ho-so.php — mục "Hồ sơ của tôi" (/tai-khoan?muc=ho-so).
 *
 * Bản thiết kế vẽ MỘT thẻ form ở đây và một thẻ ảnh đại diện 280px bên phải.
 * Thẻ bên phải đã BỎ: việc đổi ảnh chuyển thẳng vào hình tròn ở cột trái
 * (app/views/auth/profile.php) — ảnh nằm đúng chỗ nó hiện ra, và đổi được từ
 * bất kỳ mục nào chứ không phải quay về mục này trước. Nhờ vậy thẻ form ở đây
 * chiếm trọn bề ngang.
 */

$gender = $profile['gender'] ?? null;
?>

<div class="acct-head">
    <h1 class="acct-head__title">Hồ sơ của tôi</h1>
    <p class="acct-head__lead">Quản lý thông tin để bảo mật tài khoản.</p>
</div>

<form class="acct-card acct-form" method="post" action="/tai-khoan/ho-so">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

    <div class="acct-form__row">
        <label class="acct-field">
            <span class="acct-field__label">Họ và tên</span>
            <input class="acct-field__input" type="text" name="full_name" required
                   maxlength="120" autocomplete="name"
                   value="<?= e($profile['full_name'] ?? '') ?>">
        </label>

        <label class="acct-field">
            <span class="acct-field__label">Số điện thoại</span>
            <input class="acct-field__input" type="tel" name="phone" autocomplete="tel"
                   value="<?= e($profile['phone'] ?? '') ?>">
        </label>
    </div>

    <label class="acct-field">
        <span class="acct-field__label">Email</span>
        <!-- SỬA ĐƯỢC, và không bắt buộc. Tài khoản đăng ký bằng số điện thoại
             ra đời không có email; để ô này khoá thì họ không bao giờ thêm
             được, tức mất luôn lối đăng nhập bằng email và lối nhận liên kết
             đặt lại mật khẩu khi đã đổi số.

             Địa chỉ gõ ở đây CHƯA XÁC MINH — UserModel::updateEmail() đặt
             email_verified về 0, nên nó không dùng để nối tài khoản Google. -->
        <input class="acct-field__input" type="email" name="email" maxlength="255"
               autocomplete="email" placeholder="ban@vidu.com"
               value="<?= e($profile['email'] ?? '') ?>">
        <span class="acct-field__hint">
            Dùng để đăng nhập và lấy lại mật khẩu. Bỏ trống nếu bạn chỉ muốn
            dùng số điện thoại.
        </span>
    </label>

    <div class="acct-form__row">
        <div class="acct-field">
            <span class="acct-field__label" id="nhan-gioi-tinh">Giới tính</span>
            <!-- Ba nút của bản thiết kế là ba ô radio thật, nhãn phủ lên
                 trên. Nút <button> như bản thiết kế thì bàn phím và trình
                 đọc màn hình không biết cái nào đang được chọn. -->
            <div class="acct-choice" role="radiogroup" aria-labelledby="nhan-gioi-tinh">
                <?php foreach ($genders as $key => $label): ?>
                    <label class="acct-choice__opt">
                        <input type="radio" name="gender" value="<?= e($key) ?>"
                               <?= $gender === $key ? 'checked' : '' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <label class="acct-field">
            <span class="acct-field__label">Ngày sinh</span>
            <!-- type=date chứ không phải ô chữ "09/06/1996" như bản thiết
                 kế: cột date_of_birth là kiểu DATE, và ô chọn ngày của
                 trình duyệt tự hiện đúng định dạng dd/mm/yyyy cho máy
                 đang đặt tiếng Việt. -->
            <input class="acct-field__input" type="date" name="date_of_birth"
                   max="<?= e(date('Y-m-d')) ?>"
                   value="<?= e($profile['date_of_birth'] ?? '') ?>">
        </label>
    </div>

    <button type="submit" class="acct-btn acct-btn--primary acct-btn--start">Lưu thay đổi</button>
</form>
