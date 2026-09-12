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

    /* ┌─ CHẠY ĐÚNG MỘT LẦN, DÙ THẺ <script> CÓ HAI ──────────────────────────
       │ File này nay nạp ở HAI chỗ trong _layout/master.php: cạnh
       │ auth-drawer.js cho mọi trang khung đầy đủ (ngăn kéo bật ra được ở bất
       │ kỳ đâu), và trong bảng $pageScripts cho mấy view dùng khung rút gọn.
       │ Một view vừa khung đầy đủ vừa có tên trong bảng ấy sẽ nhận hai thẻ.
       │
       │ Hai thẻ `defer` cùng URL thì trình duyệt tải một lần nhưng CHẠY hai
       │ lần — và lần thứ hai gắn chồng một bộ sự kiện nữa lên cùng những nút:
       │ bấm nút con mắt một cái, hai bộ xử lý cùng đảo `type`, mật khẩu hiện
       │ ra rồi ẩn lại ngay trong một cú bấm.
       │
       │ Chốt bằng chính thứ file này xuất ra, nên không cần thêm cờ nào. */
    if (window.AuthUI) return;

/* ════════════════════════════════════════════════════════════════════════════
   BA KHỐI DƯỚI ĐÂY NHẬN MỘT "GỐC" THAY VÌ TỰ HỎI `document`

   Trước 12/09/2026 cả ba là IIFE chạy đúng một lần lúc tải trang và hỏi thẳng
   `document`. Đúng khi /auth là một TRANG. Nhưng NGĂN KÉO đăng nhập nạp chính
   view ấy vào giữa phiên (assets/js/auth-drawer.js), tức HTML xuất hiện SAU
   khi file này đã chạy xong — nên trong tấm: nút con mắt không bao giờ hiện ra
   (nó mang `hidden` sẵn trong HTML), sáu ô mã không tự nhảy, đồng hồ gửi lại
   mã đứng im.

   Nay chúng là hàm nhận `goc`, và window.AuthUI.gan(goc) ở cuối file gọi lại
   được sau mỗi lượt nạp mảnh.

   ⚠ ĐỪNG đổi `goc.querySelector` thành `document.querySelector` cho "chắc".
   Làm thế là bắt tấm đi tìm trong cả trang — nó sẽ tìm thấy đúng các phần tử
   của trang nền phía sau và gắn sự kiện vào đó.
   ════════════════════════════════════════════════════════════════════════════ */

    /* Đồng hồ đang chạy của lần gắn trước. Ngăn kéo nạp mảnh mới đè lên mảnh
       cũ, mà setInterval thì không chết theo node bị gỡ: không dọn thì mỗi lượt
       chuyển màn để lại một đồng hồ chạy mãi trên những ô không còn tồn tại. */
    var demGio = null;

    /* ── 0. NÚT CON MẮT HIỆN/ẨN MẬT KHẨU ────────────────────────────────── */

    var ganMatKhau = function (goc) {
    var buttons = goc.querySelectorAll('.authpw__eye');
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
    };

/**
 * ────────────────────────────────────────────────────────────────────────────
 * LUỒNG ĐĂNG KÝ NHIỀU CHẶNG — ba thứ tăng cường, không thứ nào bắt buộc.
 *
 * Tắt JavaScript thì: sáu ô mã vẫn gõ được từng ô rồi bấm Tiếp theo, đồng hồ
 * đếm ngược đứng yên ở con số máy chủ in ra (tải lại trang là thấy số mới), và
 * bốn quy tắc mật khẩu vẫn đọc được — máy chủ mới là nơi kiểm chúng.
 * ────────────────────────────────────────────────────────────────────────────
 */
    var ganLuongMa = function (goc) {

    /* ── 1. SÁU Ô MÃ: gõ xong nhảy ô, xoá thì lùi ô ─────────────────────── */

    var boxes = Array.prototype.slice.call(goc.querySelectorAll('.aotp__box'));

    if (boxes.length) {
        /* Nhảy sang ô sau rồi bôi đen nội dung sẵn có: gõ tiếp là ĐÈ LÊN chứ
           không bị maxlength="1" nuốt mất phím. Đây là chỗ luồng cũ hỏng —
           ô đích đã có số thì trình duyệt chặn ký tự mới, không sinh sự kiện
           `input` nào, và người dùng thấy như ô nhập chết cứng. */
        var toi = function (j) {
            var next = boxes[j];
            if (!next) return;

            next.focus();
            try { next.setSelectionRange(0, next.value.length); } catch (err) { /* ô số cũ */ }
        };

        boxes.forEach(function (box, i) {
            box.addEventListener('input', function () {
                // Chỉ giữ chữ số, và giữ ký tự VỪA gõ chứ không phải ký tự đầu:
                // gõ đè lên một ô đã có số thì cái mới mới là cái người ta muốn.
                var truoc = box.value;
                box.value = truoc.replace(/\D/g, '').slice(-1);

                // Gõ chữ cái vào ô trống: xoá sạch rồi ĐỨNG YÊN, đừng nhảy ô.
                if (box.value) toi(i + 1);
            });

            /* Bôi đen sẵn khi bấm/tab vào một ô đã có số — cùng lý do với
               toi(): để phím tiếp theo ghi đè được. */
            box.addEventListener('focus', function () {
                try { box.setSelectionRange(0, box.value.length); } catch (err) { /* ô số cũ */ }
            });

            box.addEventListener('keydown', function (e) {
                /* CHẶN NƯỚC ĐÔI CỦA maxlength: ô đang đầy mà con trỏ không bôi
                   đen gì thì trình duyệt lặng lẽ bỏ phím vừa gõ. Ở đây ta tự
                   ghi đè rồi nhảy ô — không có đoạn này thì gõ lại một dãy mã
                   thứ hai đè lên dãy cũ là bất động hoàn toàn. */
                if (/^[0-9]$/.test(e.key) && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    e.preventDefault();
                    box.value = e.key;
                    toi(i + 1);
                    return;
                }

                if (e.key === 'Backspace') {
                    /* Ô trống thì lùi ô VÀ xoá luôn số ở đó: một nhịp Backspace
                       ăn một chữ số, đúng như người ta chờ đợi. */
                    if (!box.value && boxes[i - 1]) {
                        e.preventDefault();
                        boxes[i - 1].value = '';
                        toi(i - 1);
                    }
                    return;
                }

                if (e.key === 'Delete') {
                    box.value = '';
                    return;
                }

                if (e.key === 'ArrowLeft' && boxes[i - 1]) {
                    e.preventDefault();
                    toi(i - 1);
                    return;
                }

                if (e.key === 'ArrowRight' && boxes[i + 1]) {
                    e.preventDefault();
                    toi(i + 1);
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

    var resend = goc.querySelector('.aresend');

    if (demGio !== null) {
        window.clearInterval(demGio);
        demGio = null;
    }

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

            demGio = window.setInterval(function () {
                left -= 1;

                if (left > 0) {
                    ve();
                    return;
                }

                window.clearInterval(demGio);
                demGio = null;
                num.hidden = true;
                nut.disabled = false;
            }, 1000);
        }
    }

    /* ── 3. NĂM QUY TẮC MẬT KHẨU, chấm ngay khi gõ ──────────────────────── */

    var form = goc.querySelector('form[data-pw-rules]');

    if (form) {
        /* Ô mật khẩu mang tên khác nhau tuỳ màn: 'password' khi đăng ký,
           'new_password' ở hai màn đặt lại mật khẩu. Màn ĐĂNG KÝ nay không
           còn danh sách quy tắc (chỉ một dòng gợi ý tĩnh dưới ô), nên khối này
           thực tế chỉ còn chạy ở hai màn đặt lại mật khẩu — vẫn giữ nguyên,
           vì nó dò theo `data-pw-rules` chứ không theo tên màn. querySelector trả về ô
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
    };

/* ════════════════════════════════════════════════════════════════════════════
   CỬA DUY NHẤT ĐỂ GẮN LẠI — window.AuthUI.gan(goc)

   Trang thật gọi một lần ở dòng cuối. Ngăn kéo gọi lại sau MỖI lượt nạp mảnh
   (xem nap() trong assets/js/auth-drawer.js): mảnh mới là những node mới hoàn
   toàn, nên không có chuyện gắn chồng sự kiện lên cùng một phần tử.

   ─────────────────────────────────────────────────────────────────────────
   KHỐI "GỬI MÃ GỌI NGẦM" ĐÃ GỠ (12/09/2026)

   Ở đây từng có một khối thứ tư: nút "Gửi mã" nằm ngay trong form đăng ký,
   chặn submit rồi gọi ngầm /auth/dang-ky/gui-ma. Nó chết theo cái nút — bản
   thiết kế 12/09/2026 tách màn nhập mã ra riêng, và ở màn ấy việc xin lại mã
   do cụm .aresend lo (khối 2 bên trên), đúng cùng một cụm mà màn quên mật
   khẩu đang dùng.

   Cụm .aresend gửi form THẬT chứ không gọi ngầm: nó đứng một mình trên màn,
   không có ô nào để mất chữ khi tải lại trang — lý do duy nhất khiến khối cũ
   phải gọi ngầm.
   ════════════════════════════════════════════════════════════════════════════ */

    window.AuthUI = {
        gan: function (goc) {
            var g = goc || document;

            ganMatKhau(g);
            ganLuongMa(g);
        }
    };

    window.AuthUI.gan(document);
}());
