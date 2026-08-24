<?php

/**
 * admin/account/mat-khau.php — nhân viên tự đổi mật khẩu.
 *
 * Controller: Admin/AccountAdminController
 *
 * Dải báo thành công và lỗi do khung quản trị in sẵn (admin_success /
 * admin_error trong admin/_layout/master.php), nên trang này không tự vẽ.
 */
?>

<div class="ahead">
    <h1 class="ahead__title">Đổi mật khẩu</h1>
    <?php /* Email in chữ đẳng khoảng, theo bản thiết kế. Đây là chỗ người ta
             kiểm lại "mình đang đổi mật khẩu của tài khoản nào" trước khi gõ,
             nên nó cần đọc ra là một ĐỊNH DANH chứ không lẫn vào câu văn. */ ?>
    <p class="ahead__lead">
        Tài khoản <code class="ahead__id"><?= e($me['email'] ?? $me['full_name'] ?? 'của bạn') ?></code>
    </p>
</div>

<form class="aform aform--narrow" method="post" action="/quan-tri/doi-mat-khau/luu">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

    <div class="aform__grid">
        <div class="field--wide">
            <label for="mk-cu">Mật khẩu hiện tại</label>
            <?php /*
                autocomplete="current-password" và "new-password" là hai giá
                trị trình quản lý mật khẩu dùng để phân biệt ô nào là ô cũ, ô
                nào là ô mới — đặt sai thì nó điền mật khẩu cũ vào cả ba ô, và
                người dùng bấm lưu mà không hiểu vì sao báo trùng.
            */ ?>
            <input type="password" name="current_password" id="mk-cu"
                   required autocomplete="current-password" autofocus>
        </div>

        <div class="field--wide">
            <label for="mk-moi">Mật khẩu mới</label>
            <?php /*
                minlength="8" khớp đúng ngưỡng của UserModel::changePassword().
                Máy chủ vẫn là nơi chốt — thuộc tính này chỉ để người dùng biết
                sớm, trước khi mất một vòng gửi form.
            */ ?>
            <input type="password" name="new_password" id="mk-moi"
                   required minlength="8" autocomplete="new-password">
        </div>

        <div class="field--wide">
            <label for="mk-lai">Nhập lại mật khẩu mới</label>
            <input type="password" name="new_password_confirm" id="mk-lai"
                   required minlength="8" autocomplete="new-password">
        </div>
    </div>

    <p class="aform__note">
        Ít nhất 8 ký tự. Đổi xong, mọi thiết bị khác đang ghi nhớ đăng nhập sẽ bị
        đăng xuất — phiên bạn đang mở thì không.
    </p>

    <button type="submit" class="astatus__save">Đổi mật khẩu</button>
</form>
