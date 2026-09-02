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
        var num  = resend.querySelector('.aresend__num');
        var nut  = resend.querySelector('[data-resend]');

        /*
         * ĐẾM NGƯỢC NGAY TRONG NHÃN NÚT.
         *
         * Bản trước giấu cả cụm gửi lại rồi mới hiện ra khi hết giờ — nghĩa là
         * không có file này thì nút không bao giờ xuất hiện. Nay nút nằm sẵn
         * trong HTML và mang `disabled`; việc của đoạn này chỉ là đếm lùi rồi
         * mở khoá. Không có JS thì tải lại trang là máy chủ tính lại số giây.
         *
         * Máy chủ vẫn là nơi chốt thật (AuthController::signupSend) — gỡ
         * disabled bằng devtools cũng không gửi thêm được mã nào.
         */
        if (left > 0 && num && nut) {
            /* Không kèm dấu cách ở đầu: khoảng hở do `gap` của .aresend__btn
               lo, và hộp inline-flex cắt bỏ khoảng trắng trong HTML nên dấu
               cách ở đây cũng vô nghĩa. */
            var ve = function () { num.textContent = '(' + left + 's)'; };

            ve();

            var tick = window.setInterval(function () {
                left -= 1;

                if (left > 0) {
                    ve();
                    return;
                }

                window.clearInterval(tick);
                num.hidden = true;
                nut.disabled = false;
            }, 1000);
        }
    }

    /* ── 3. NĂM QUY TẮC MẬT KHẨU, chấm ngay khi gõ ──────────────────────── */

    var form = document.querySelector('form[data-pw-rules]');

    if (form) {
        /* Ô mật khẩu mang tên khác nhau tuỳ màn: 'password' khi đăng ký,
           'new_password' ở hai màn đặt lại mật khẩu. querySelector trả về ô
           ĐẦU TIÊN theo thứ tự tài liệu, nên ở màn có thêm ô "nhập lại" thì
           vẫn đúng ô trên. */
        var input = form.querySelector('input[name="password"], input[name="new_password"]');
        /* NĂM quy tắc này phải KHỚP passwordProblem() trong core/helpers.php —
           đó mới là nơi quyết định, đây chỉ là bản chấm được của nó. Thêm
           'special' và siết 'len' thành 8–32 ngày 2026-09-02 theo SNFR-09.

           'len' đếm bằng Array.from(v).length chứ không phải v.length: chuỗi
           JavaScript đếm theo UTF-16 code unit, nên emoji hay vài ký tự hiếm
           bị tính thành 2 trong khi PHP utf8Length() tính thành 1. Với tiếng
           Việt thì hai cách cho cùng kết quả, nhưng để lệch là có ngày chấm
           xanh mà máy chủ vẫn từ chối — đúng thứ khối chú thích ở
           auth/_password-rules.php cảnh báo. */
        var rules = {
            len:     function (v) { var n = Array.from(v).length; return n >= 8 && n <= 32; },
            upper:   function (v) { return /[A-Z]/.test(v); },
            lower:   function (v) { return /[a-z]/.test(v); },
            digit:   function (v) { return /[0-9]/.test(v); },
            special: function (v) { return /[^A-Za-z0-9]/.test(v); }
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
