<?php

/**
 * auth/_password.php — ô mật khẩu kèm nút con mắt hiện/ẩn.
 *
 * Dựng theo "Vin Eyewear Login.dc.html": ô nhập chừa 52px bên phải cho một nút
 * 36×36 nằm đè lên, đổi giữa hai hình con mắt.
 *
 * Nút mặc định ẨN (`hidden`), assets/js/auth.js gỡ thuộc tính đó ra khi chạy.
 * Không có JS thì nút không làm gì được, mà một nút không làm gì được còn tệ
 * hơn là không có nút — nên nó chỉ xuất hiện khi thật sự bấm được.
 *
 * Nhận qua partial():
 *   $pw_name     tên trường
 *   $pw_auto     giá trị autocomplete ('current-password' | 'new-password')
 *   $pw_holder   chữ mờ trong ô
 *   $pw_min      độ dài tối thiểu (bỏ trống = không đặt)
 *   $pw_required bắt buộc nhập hay không
 */

$pw_min      = $pw_min      ?? null;
$pw_required = $pw_required ?? false;
?>

<span class="authpw">
    <input class="authfield__input authpw__input" type="password"
           name="<?= e($pw_name) ?>"
           autocomplete="<?= e($pw_auto) ?>"
           placeholder="<?= e($pw_holder) ?>"
           <?= $pw_min !== null ? 'minlength="' . (int) $pw_min . '"' : '' ?>
           <?= $pw_required ? 'required' : '' ?>>

    <button type="button" class="authpw__eye" hidden
            aria-label="Hiện mật khẩu" aria-pressed="false">
        <!-- Hai hình chồng nhau, CSS ẩn một cái theo aria-pressed. Đổi bằng CSS
             chứ không bằng JS vẽ lại: JS chỉ phải đảo đúng một thuộc tính. -->
        <svg class="authpw__on" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
             stroke-linejoin="round" aria-hidden="true">
            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </svg>
        <svg class="authpw__off" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
             stroke-linejoin="round" aria-hidden="true">
            <path d="M17.94 17.94A10.5 10.5 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94M9.9 4.24A9.9 9.9 0 0 1 12 4c7 0 11 7 11 7a19.9 19.9 0 0 1-3.22 4.31"></path>
            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
        </svg>
    </button>
</span>
