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

/**
 * ────────────────────────────────────────────────────────────────────────────
 * LUỒNG ĐĂNG KÝ NHIỀU CHẶNG — ba thứ tăng cường, không thứ nào bắt buộc.
 *
 * Tắt JavaScript thì: sáu ô mã vẫn gõ được từng ô rồi bấm Tiếp theo, đồng hồ
 * đếm ngược đứng yên ở con số máy chủ in ra (tải lại trang là thấy số mới), và
 * bốn quy tắc mật khẩu vẫn đọc được — máy chủ mới là nơi kiểm chúng.
 * ────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    /* ── 1. SÁU Ô MÃ: gõ xong nhảy ô, xoá thì lùi ô ─────────────────────── */

    var boxes = Array.prototype.slice.call(document.querySelectorAll('.aotp__box'));

    if (boxes.length) {
        boxes.forEach(function (box, i) {
            box.addEventListener('input', function () {
                // Chỉ giữ chữ số, và giữ ký tự VỪA gõ chứ không phải ký tự đầu:
                // gõ đè lên một ô đã có số thì cái mới mới là cái người ta muốn.
                box.value = box.value.replace(/\D/g, '').slice(-1);

                if (box.value && boxes[i + 1]) boxes[i + 1].focus();
            });

            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !box.value && boxes[i - 1]) {
                    boxes[i - 1].focus();
                }
            });

            /* Dán cả mã sáu số vào ô đầu — trình duyệt và trình quản lý tin
               nhắn đều làm thế. Không có đoạn này thì năm số sau rơi mất và
               người dùng tưởng ô nhập hỏng. */
            box.addEventListener('paste', function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text') || '';
                var digits = text.replace(/\D/g, '');

                if (digits.length < 2) return;

                e.preventDefault();

                for (var k = 0; k < boxes.length - i; k++) {
                    if (digits[k] === undefined) break;
                    boxes[i + k].value = digits[k];
                }

                var last = Math.min(i + digits.length, boxes.length - 1);
                boxes[last].focus();
            });
        });
    }

    /* ── 2. ĐẾM NGƯỢC 60 GIÂY ───────────────────────────────────────────── */

    var resend = document.querySelector('.aresend');

    if (resend) {
        var left = parseInt(resend.getAttribute('data-wait'), 10) || 0;
        var wait = resend.querySelector('.aresend__wait');
        var num  = resend.querySelector('.aresend__num');
        var go   = resend.querySelector('.aresend__go');

        if (left > 0 && wait && num && go) {
            var tick = window.setInterval(function () {
                left -= 1;

                if (left > 0) {
                    num.textContent = left;
                    return;
                }

                window.clearInterval(tick);
                wait.hidden = true;
                go.hidden = false;
            }, 1000);
        }
    }

    /* ── 3. BỐN QUY TẮC MẬT KHẨU, chấm ngay khi gõ ──────────────────────── */

    var form = document.querySelector('form[data-pw-rules]');

    if (form) {
        var input = form.querySelector('input[name="password"]');
        var rules = {
            len:   function (v) { return v.length >= 8; },
            upper: function (v) { return /[A-Z]/.test(v); },
            lower: function (v) { return /[a-z]/.test(v); },
            digit: function (v) { return /[0-9]/.test(v); }
        };

        if (input) {
            input.addEventListener('input', function () {
                var v = input.value;

                Object.keys(rules).forEach(function (key) {
                    var li = form.querySelector('[data-rule="' + key + '"]');
                    if (li) li.classList.toggle('is-ok', rules[key](v));
                });
            });
        }
    }
}());
