/**
 * auth.js — nút con mắt hiện/ẩn mật khẩu (/auth, /dat-lai-mat-khau)
 *
 * CHỈ LÀ TĂNG CƯỜNG. Nút được đánh dấu `hidden` ngay trong HTML
 * (app/views/auth/_password.php); file này gỡ thuộc tính đó ra. Không có JS
 * thì nút không bao giờ hiện — một cái nút bấm mà không xảy ra gì còn khó
 * hiểu hơn là không có nút.
 */
(function () {
    'use strict';

    var buttons = document.querySelectorAll('.authpw__eye');
    if (!buttons.length) return;

    buttons.forEach(function (btn) {
        var input = btn.parentNode.querySelector('input');
        if (!input) return;

        btn.hidden = false;

        btn.addEventListener('click', function () {
            var show = input.type === 'password';

            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');

            // Trả con trỏ về ô nhập, đúng chỗ nó đang đứng: đổi thuộc tính
            // `type` khiến trình duyệt bỏ tiêu điểm và đẩy con trỏ về cuối,
            // nên phải đặt lại tay.
            var at = input.value.length;
            input.focus();
            input.setSelectionRange(at, at);
        });
    });
}());
