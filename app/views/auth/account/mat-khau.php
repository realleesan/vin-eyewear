<?php

/**
 * auth/account/mat-khau.php — KHU VỰC 3 của trang Hồ sơ cá nhân.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TỪNG LÀ MỘT MỤC RIÊNG, NAY LÀ MỘT MẢNH
 *
 * Tới 2026-09-10 đây là mục ?muc=mat-khau: một URL riêng, một lần tải trang
 * riêng. BR-UC.USER.05-03 cấm đúng chuyện đó — ba khu vực phải nằm trên CÙNG
 * MỘT TRANG, không tab, không chuyển trang. Nên file này nay được
 * auth/account/ho-so.php gọi bằng partial().
 *
 * GIỮ NGUYÊN TÊN FILE dù nó không còn là một "mục": mọi tài liệu và chú thích
 * đang trỏ tới đường dẫn này, và đổi tên chỉ để cho đúng danh xưng là một lần
 * sửa lan ra chục chỗ mà không đổi được một hành vi nào.
 *
 * ?muc=mat-khau CŨ KHÔNG VỠ: 'mat-khau' đã rời khỏi AuthController::SECTIONS
 * nên nó là một giá trị lạ, và profile() đưa mọi giá trị lạ về mục mặc định —
 * tức chính trang này. Liên kết cũ vẫn tới đúng nơi, chỉ không nhảy tới neo.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NÚT CON MẮT VÀ GỢI Ý MẬT KHẨU — mục Giao diện, Khu vực 3
 *
 * KHÔNG mượn auth/_password.php. File ấy dùng bộ lớp .authpw/.authfield của
 * assets/css/auth.css, mà trang tài khoản KHÔNG nạp auth.css (xem bảng
 * $pageStyles trong _layout/master.php — trang này chỉ có account.css +
 * confirm.css). Mượn lớp ở đó thì nút hiện ra trần trụi kiểu mặc định của
 * trình duyệt, giữa một form đã dựng xong.
 *
 * Nút mặc định mang `hidden`; assets/js/account.js gỡ thuộc tính ấy ra khi
 * chạy. Không có JavaScript thì nút không bao giờ hiện — một cái nút bấm mà
 * không xảy ra gì còn khó hiểu hơn là không có nút, và ô mật khẩu vẫn dùng
 * bình thường.
 *
 * Khối gợi ý thì NGƯỢC LẠI: nó là chữ thuần, hiện bằng CSS khi con trỏ vào ô
 * (:focus-within), nên không cần một dòng JavaScript nào. Năm dòng phải KHỚP
 * với passwordProblem() trong core/helpers.php — đó mới là nơi quyết định, còn
 * đây chỉ là bản đọc được của nó.
 */

/* Năm quy tắc, cùng danh sách với auth/_password-rules.php. Chép sang đây chứ
   không partial() file ấy: nó mang lớp .arule của auth.css, không có trên
   trang này. Sửa passwordProblem() thì phải sửa CẢ HAI chỗ — dòng này là lời
   nhắc đó. */
$quyTac = [
    'Từ 8 đến 32 ký tự',
    'Ít nhất một chữ hoa',
    'Ít nhất một chữ thường',
    'Ít nhất một chữ số',
    'Ít nhất một ký tự đặc biệt',
];

/** In một ô mật khẩu kèm nút con mắt. */
$oMatKhau = static function (
    string $id,
    string $name,
    string $nhan,
    string $auto,
    bool $coGoiY = false
) use ($quyTac): void {
    ?>
    <div class="acct-field">
        <label class="acct-field__label" for="<?= e($id) ?>">
            <?= e($nhan) ?> <span aria-hidden="true">*</span>
        </label>

        <span class="acct-pw">
            <input class="acct-field__input acct-pw__input" type="password"
                   id="<?= e($id) ?>" name="<?= e($name) ?>" required
                   autocomplete="<?= e($auto) ?>" placeholder="••••••••"
                   <?= $auto === 'new-password' ? 'minlength="8" maxlength="32"' : '' ?>
                   <?= $coGoiY ? 'aria-describedby="' . e($id) . '-rules"' : '' ?>>

            <button type="button" class="acct-pw__eye" hidden
                    aria-label="Hiện mật khẩu" aria-pressed="false">
                <!-- Hai hình chồng nhau, CSS ẩn một cái theo aria-pressed. Đổi
                     bằng CSS chứ không bằng JS vẽ lại: JS chỉ phải đảo đúng một
                     thuộc tính. -->
                <svg class="acct-pw__on" width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg class="acct-pw__off" width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                     stroke-linejoin="round" aria-hidden="true">
                    <path d="M17.94 17.94A10.5 10.5 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94M9.9 4.24A9.9 9.9 0 0 1 12 4c7 0 11 7 11 7a19.9 19.9 0 0 1-3.22 4.31"></path>
                    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                </svg>
            </button>
        </span>

        <?php if ($coGoiY): ?>
            <ul class="acct-rules" id="<?= e($id) ?>-rules" role="list">
                <?php foreach ($quyTac as $dong): ?>
                    <li class="acct-rules__item"><?= e($dong) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
};
?>

<section id="doi-mat-khau">
    <div class="acct-head acct-head--sub">
        <h2 class="acct-head__title">Đổi mật khẩu</h2>
        <p class="acct-head__lead">Để bảo mật, không chia sẻ mật khẩu cho người khác.</p>
    </div>

    <form class="acct-card acct-form acct-form--narrow" method="post"
          action="/tai-khoan/mat-khau">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <!-- Ô email ẩn: trình quản lý mật khẩu cần biết mục này thuộc tài khoản
             nào thì mới đề nghị cập nhật đúng bản ghi đã lưu. -->
        <input type="hidden" autocomplete="username" value="<?= e($profile['email'] ?? '') ?>">

        <?php
        $oMatKhau('mk-hien-tai', 'current_password', 'Mật khẩu hiện tại', 'current-password');
        $oMatKhau('mk-moi', 'new_password', 'Mật khẩu mới', 'new-password', true);
        ?>

        <?php
        /* Ô này KHÔNG được gửi lên để đối chiếu ở model — máy chủ chỉ cần một
           mật khẩu mới (xem UserModel::changePassword). Nhưng nó VẪN được gửi,
           và AuthController::changePassword() so hai chuỗi trước khi gọi model:
           thuộc tính `required` của HTML chỉ là gợi ý của trình duyệt, request
           gửi tay không đi qua nó. */
        $oMatKhau('mk-xac-nhan', 'new_password_confirm', 'Xác nhận mật khẩu mới', 'new-password');
        ?>

        <p class="acct-form__note">
            Đổi mật khẩu sẽ đăng xuất mọi thiết bị khác đang ghi nhớ đăng nhập.
        </p>

        <button type="submit" class="acct-btn acct-btn--primary acct-btn--start">Đổi mật khẩu</button>
    </form>
</section>
