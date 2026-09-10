/**
 * account-drawer.js — ĐĂNG NHẬP NGAY TRONG NGĂN KÉO TÀI KHOẢN.
 *
 * Form trong ngăn kéo (_layout/header.php, [data-acct-login]) là một
 * <form method="post" action="/auth/dang-nhap"> THẬT, cùng tên trường với màn
 * /auth. File này chỉ chặn cú gửi lại để khách không phải rời trang.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN
 *
 * Tắt JavaScript (hoặc file này lỗi) thì form gửi như một form thường: sang
 * /auth, lỗi hiện ở đó, đăng nhập xong quay lại đúng trang cũ nhờ ô ẩn
 * `redirect`. Không mất một luồng nào.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO ĐỌC LỖI TỪ HTML CỦA /auth, KHÔNG ĐÒI MỘT API JSON
 *
 * AuthController::login() KHÔNG trả JSON và không có nhánh nào cho "gọi ngầm":
 * mọi ngả của nó kết thúc bằng redirect. Thêm một nhánh JSON nghĩa là luồng
 * đăng nhập có HAI đường ra, hai chỗ phải giữ đồng bộ luật báo lỗi — mà luật ấy
 * là luật BẢO MẬT (xem khối "HAI TẦNG BÁO LỖI" trong controller: sai tài khoản
 * và sai mật khẩu phải nói CHUNG một câu).
 *
 * Nên ở đây làm ngược lại: gửi đúng cú POST cũ, để fetch đi theo chuyển hướng,
 * rồi ĐỌC KẾT QUẢ TỪ ĐỊA CHỈ CUỐI CÙNG:
 *
 *     kết thúc ở /auth      -> đăng nhập HỎNG. Bóc chữ lỗi trong HTML vừa nhận
 *                              và vẽ lại trong ngăn kéo.
 *     kết thúc ở chỗ khác   -> ĐÃ VÀO. Nạp lại trang để đầu trang đổi sang
 *                              trạng thái đã đăng nhập và dải toast hiện ra.
 *
 * Cùng thủ pháp mà assets/js/catalog.js dùng cho bộ lọc: xin HTML máy chủ vừa
 * dựng rồi lấy đúng mảnh cần, thay vì dựng lại sự thật ở phía trình duyệt.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT ĐIỀU PHẢI BIẾT TRƯỚC KHI SỬA: PHIÊN CHỈ GIỮ LỖI ĐÚNG MỘT LƯỢT
 *
 * Controller ghi lỗi vào $_SESSION['_auth_errors'] / flash('auth_error') rồi
 * chuyển hướng; màn /auth đọc xong là XOÁ. Vì fetch ở đây đi theo chuyển hướng
 * nên chính nó là "một lượt" ấy — chữ lỗi nằm trong HTML ta vừa nhận và KHÔNG
 * còn trong phiên nữa.
 *
 * Hệ quả: nếu bóc sai selector thì lỗi biến mất hoàn toàn — khách bấm "Đăng
 * nhập", không có gì xảy ra, và mở /auth ra cũng sạch trơn. Đó là lý do có
 * nhánh dự phòng cuối cùng (thấy đang ở /auth mà không bóc được câu nào thì
 * ĐI THẲNG tới đó, mang theo cả cú POST) — thà tải một trang còn hơn nuốt lỗi.
 */

(function () {
    'use strict';

    var form = document.querySelector('[data-acct-login]');

    if (!form || !window.fetch || !window.DOMParser || !window.FormData) return;

    var alertBox = form.querySelector('[data-acct-alert]');
    var submit   = form.querySelector('[data-acct-submit]');
    var dangGui  = false;

    /** Xoá mọi lời báo của lượt trước — chạy trước MỖI lần gửi. */
    function donLoi() {
        if (alertBox) {
            alertBox.textContent = '';
            alertBox.hidden = true;
        }

        Array.prototype.forEach.call(form.querySelectorAll('[data-acct-err]'), function (el) {
            el.textContent = '';
            el.hidden = true;
        });

        Array.prototype.forEach.call(form.querySelectorAll('.acctdr__input'), function (el) {
            el.classList.remove('is-err');
        });
    }

    function bao(box, chu) {
        if (!box || !chu) return false;

        box.textContent = chu;
        box.hidden = false;

        return true;
    }

    /** Lỗi của một ô: tô đỏ gạch chân và in câu ngay dưới ô ấy. */
    function loiO(ten, chu) {
        var o   = form.querySelector('[name="' + ten + '"]');
        var box = form.querySelector('[data-acct-err="' + ten + '"]');

        if (o) o.classList.add('is-err');

        return bao(box, chu);
    }

    function chuGon(el) {
        return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    /**
     * Bóc lời báo lỗi ra khỏi HTML của màn /auth.
     *
     * Ba nguồn, đúng ba thứ mà view ấy vẽ ra (xem app/views/auth/index.php):
     *   .authfield__err   lỗi THEO TỪNG Ô — tìm qua chính thẻ <input> cùng tên
     *                     rồi leo lên .authfield, nên không phụ thuộc thứ tự
     *   .authflash--err   dải báo chung (sai tài khoản / mật khẩu)
     *   .authgate         "đây là tài khoản nội bộ"
     *
     * @return {boolean} đã vẽ được ít nhất một câu chưa
     */
    function bocLoi(doc) {
        var co = false;

        ['email', 'password'].forEach(function (ten) {
            var o = doc.querySelector('.authform [name="' + ten + '"]');
            var v = o && o.closest ? o.closest('.authfield') : null;
            var e = v ? v.querySelector('.authfield__err') : null;

            if (e && loiO(ten, chuGon(e))) co = true;
        });

        var chung = chuGon(doc.querySelector('.authflash--err'));

        /* Khối "tài khoản nội bộ" là hai đoạn (tiêu đề + giải thích); ghép lại
           thành một câu cho dải báo một dòng của ngăn kéo. */
        if (!chung) {
            var t = chuGon(doc.querySelector('.authgate__title'));
            var n = chuGon(doc.querySelector('.authgate__text'));

            chung = t && n ? t + '. ' + n : (t || n);
        }

        if (bao(alertBox, chung)) co = true;

        return co;
    }

    function khoa(dang) {
        dangGui = dang;

        if (submit) {
            submit.disabled = dang;
            /* aria-busy chứ không đổi nhãn nút: đổi chữ trên nút đang bấm làm
               trình đọc màn hình đọc lại cả cái nút, và nhãn mới ("Đang gửi…")
               thì biến mất ngay sau đó. */
            submit.setAttribute('aria-busy', dang ? 'true' : 'false');
        }
    }

    form.addEventListener('submit', function (e) {
        /* Đang có một lượt chạy thì bỏ qua cú bấm thứ hai — KHÔNG gửi thêm.
           Mỗi lượt là một vạch trong bộ đếm khoá tạm của LoginAttemptModel. */
        if (dangGui) {
            e.preventDefault();
            return;
        }

        e.preventDefault();
        donLoi();
        khoa(true);

        window.fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            /* redirect mặc định là 'follow', và ta CẦN nó: đích cuối cùng chính
               là câu trả lời (xem khối chú thích đầu file). */
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(function (res) {
                /* res.url là địa chỉ SAU KHI đã đi hết chuỗi chuyển hướng. */
                var oAuth = new URL(res.url, window.location.origin).pathname === '/auth';

                if (!oAuth) {
                    /* Vào được. Nạp lại trang đang đứng — không đọc res.url:
                       controller trả về đúng `redirect` ta gửi lên, tức là
                       chính trang này, nhưng dựng lại nó từ HTML vừa nhận thì
                       phải thay cả <head> lẫn <body>. Một lượt tải rẻ hơn nhiều
                       so với việc đó, và nó cũng là lúc dải toast "Đăng nhập
                       thành công!" được in ra. */
                    window.location.reload();

                    return null;
                }

                return res.text();
            })
            .then(function (html) {
                if (html === null) return;   // đang nạp lại trang

                khoa(false);

                var doc = new DOMParser().parseFromString(html, 'text/html');

                if (bocLoi(doc)) return;

                /* Đang ở /auth mà không bóc được câu nào: view đã đổi selector,
                   hoặc máy chủ trả một trang khác hẳn. Lỗi đã bị lượt fetch này
                   tiêu thụ mất khỏi phiên, nên KHÔNG nói suông "có lỗi" — gửi
                   lại bằng đường thật để khách thấy đúng câu máy chủ muốn nói. */
                form.submit();
            })
            .catch(function () {
                khoa(false);

                /* Mạng hỏng thì cú POST có thể CHƯA từng tới máy chủ — gửi lại
                   bằng form.submit() ở đây là rủi ro đăng nhập hai lần. Nói ra
                   và để khách tự bấm lại. */
                bao(alertBox, form.getAttribute('data-net-error') || 'Không gửi được. Vui lòng thử lại.');
            });
    });
})();
