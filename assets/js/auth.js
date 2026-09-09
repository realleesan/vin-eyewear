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
}());

/**
 * ────────────────────────────────────────────────────────────────────────────
 * NÚT "GỬI MÃ" CỦA MÀN ĐĂNG KÝ — gọi ngầm, không rời trang.
 *
 * CHỈ LÀ TĂNG CƯỜNG, đúng nếp của cả file này. Nút trong HTML là một nút
 * submit thật mang formaction="/auth/dang-ky/gui-ma": không có JavaScript thì
 * nó gửi form đi, máy chủ cất mọi chữ đã gõ vào phiên rồi trả khách về đúng
 * form ấy với dữ liệu còn nguyên — chậm hơn một nhịp tải trang, nhưng không
 * kẹt. Đoạn dưới đây chặn cú submit ấy lại và làm cùng việc bằng fetch.
 *
 * Đồng hồ đếm ngược ở đây CHỈ để đỡ bấm oan. Chốt thật nằm ở máy chủ —
 * AuthController::signupSendCode(), `if (time() < resend)` — nên gỡ `disabled`
 * bằng devtools cũng không xin thêm được mã nào.
 * ────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    var box = document.querySelector('[data-code]');
    if (!box) return;

    /* ── Ô MÃ CHỈ NHẬN CHỮ SỐ ────────────────────────────────────────────
 
       ĐỨNG TRƯỚC CHỐT `window.fetch` bên dưới, và đó là chủ ý: việc lọc này
       không dính gì tới cú gọi ngầm, nên một trình duyệt cũ không có fetch
       vẫn phải được lọc. Gộp vào khối dưới là để nó biến mất đúng ở nơi khó
       kiểm thử nhất.
 
       Lọc chứ không CHẶN phím (keydown): chặn phím thì hỏng cả dán chuột,
       dán bằng bàn phím, gõ tiếng Việt qua bộ gõ, và tự điền mã OTP của điện
       thoại. Nghe `input` thì mọi đường nhập liệu đều đi qua đây.
 
       Con trỏ phải dời theo số ký tự VỪA BỊ BỎ Ở PHÍA TRƯỚC nó, nếu không
       gõ chèn một chữ cái vào giữa dãy sẽ ném con trỏ về cuối ô.
 
       Đây chỉ là lớp tiện tay. Hai chốt thật là `pattern` của ô (trình duyệt)
       và AuthController::signupCodeProblem() (máy chủ). */
    var oMa = box.querySelector('.acode__input');

    if (oMa) {
        oMa.addEventListener('input', function () {
            var sach = oMa.value.replace(/\D/g, '');

            if (sach === oMa.value) return;

            var viTri = oMa.selectionStart;
            var bo    = (oMa.value.slice(0, viTri).match(/\D/g) || []).length;

            oMa.value = sach;

            try { oMa.setSelectionRange(viTri - bo, viTri - bo); } catch (err) { /* ô số cũ */ }
        });
    }

    if (!window.fetch) return;

    var form  = box.closest('form');
    var btn   = box.querySelector('[data-send-code]');
    var nhan  = box.querySelector('[data-send-label]');
    var so    = box.querySelector('.acode__num');
    var ghi   = box.querySelector('[data-code-note]');

    if (!form || !btn) return;

    var con  = parseInt(box.getAttribute('data-wait'), 10) || 0;
    var nhip = null;

    /* Vẽ số giây còn lại ngay trong nhãn nút. Không kèm dấu cách ở đầu:
       khoảng hở do `gap` của .acode__send lo, và hộp inline-flex cắt bỏ
       khoảng trắng trong HTML nên dấu cách ở đây cũng vô nghĩa. */
    var ve = function () {
        if (!so) return;

        so.hidden = con <= 0;
        so.textContent = con > 0 ? '(' + con + 's)' : '';
    };

    var dem = function () {
        window.clearInterval(nhip);
        btn.disabled = con > 0;
        ve();

        if (con <= 0) return;

        nhip = window.setInterval(function () {
            con -= 1;
            ve();

            if (con > 0) return;

            window.clearInterval(nhip);
            btn.disabled = false;
        }, 1000);
    };

    var noi = function (cau, hong) {
        if (!ghi) return;

        ghi.textContent = cau;
        ghi.classList.toggle('is-err', !!hong);
    };

    dem();

    btn.addEventListener('click', function (e) {
        e.preventDefault();

        if (btn.disabled) return;

        btn.disabled = true;
        if (nhan) nhan.textContent = 'Đang gửi…';

        var goi = new FormData(form);

        /* HAI Ô MẬT KHẨU KHÔNG ĐI CÙNG YÊU CẦU NÀY. Máy chủ không đọc tới
           chúng, mà một mật khẩu gửi đi mỗi lần bấm "Gửi mã" là một chuỗi
           nữa nằm trong log của mọi proxy trên đường — không cần thì đừng gửi. */
        goi.delete('password');
        goi.delete('password_confirm');

        window.fetch(btn.getAttribute('formaction') || '/auth/dang-ky/gui-ma', {
            method: 'POST',
            headers: { 'X-Requested-With': 'fetch' },
            body: goi,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json();
        }).then(function (d) {
            if (nhan) nhan.textContent = d.ok ? 'Gửi lại mã' : 'Gửi mã';

            noi(d.message || '', !d.ok);

            con = parseInt(d.wait, 10) || 0;
            dem();
        }).catch(function () {
            /* Mạng hỏng, hoặc máy chủ trả về thứ không phải JSON. Mở khoá nút
               lại ngay: khách bấm lần nữa là đường duy nhất còn lại. */
            if (nhan) nhan.textContent = 'Gửi mã';

            noi('Không gửi được mã. Vui lòng thử lại.', true);
            btn.disabled = false;
        });
    });
}());
