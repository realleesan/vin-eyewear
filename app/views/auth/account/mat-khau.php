<?php

/**
 * auth/account/mat-khau.php — mục "Đổi mật khẩu" (/tai-khoan?muc=mat-khau).
 *
 * Bản thiết kế: một thẻ hẹp 520px, ba ô mật khẩu, nút "Xác nhận".
 */
?>

<div class="acct-head">
    <h1 class="acct-head__title">Đổi mật khẩu</h1>
    <p class="acct-head__lead">Để bảo mật, không chia sẻ mật khẩu cho người khác.</p>
</div>

<form class="acct-card acct-form acct-form--narrow" method="post" action="/tai-khoan/mat-khau">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

    <!-- Ô email ẩn: trình quản lý mật khẩu cần biết mục này thuộc tài khoản
         nào thì mới đề nghị cập nhật đúng bản ghi đã lưu. -->
    <input type="hidden" autocomplete="username" value="<?= e($profile['email']) ?>">

    <label class="acct-field">
        <span class="acct-field__label">Mật khẩu hiện tại</span>
        <input class="acct-field__input" type="password" name="current_password" required
               autocomplete="current-password" placeholder="••••••••">
    </label>

    <label class="acct-field">
        <span class="acct-field__label">Mật khẩu mới</span>
        <input class="acct-field__input" type="password" name="new_password" required
               minlength="8" autocomplete="new-password" placeholder="Tối thiểu 8 ký tự">
    </label>

    <label class="acct-field">
        <span class="acct-field__label">Nhập lại mật khẩu mới</span>
        <!-- Ô này KHÔNG được gửi lên; nó chỉ để trình duyệt so hai lần gõ tại
             chỗ. Máy chủ chỉ cần một mật khẩu mới — xem UserModel::changePassword. -->
        <input class="acct-field__input" type="password" name="new_password_confirm" required
               minlength="8" autocomplete="new-password" placeholder="••••••••">
    </label>

    <p class="acct-form__note">
        Đổi mật khẩu sẽ đăng xuất mọi thiết bị khác đang ghi nhớ đăng nhập.
    </p>

    <button type="submit" class="acct-btn acct-btn--primary acct-btn--start">Xác nhận</button>
</form>
