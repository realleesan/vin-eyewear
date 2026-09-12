/**
 * auth-drawer.js — đổ trang /auth vào ngăn kéo bên phải của thanh đầu trang.
 *
 * Markup: app/views/_layout/header-auth.php
 * Khung:  .authdrawer trong assets/css/oa.css
 * Mảnh:   nhánh `X-Auth` ở app/views/_layout/master.php
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * NGUYÊN TẮC DUY NHẤT CỦA FILE NÀY: KHÔNG BIẾT GÌ VỀ LUỒNG ĐĂNG NHẬP
 *
 * Nó không biết có mấy bước, ô nào bắt buộc, lỗi trông ra sao, OTP gửi đi
 * đâu. Nó chỉ làm đúng hai việc:
 *
 *   1. GET /auth  → đổ HTML trả về vào ngăn kéo
 *   2. Form nào bên trong submit → gửi đúng form ấy tới đúng action của nó,
 *      rồi đổ HTML trả về vào chỗ cũ
 *
 * Nghĩa là mọi thứ thuộc về nghiệp vụ vẫn nằm nguyên ở AuthController và
 * app/views/auth/. Thêm một bước, đổi một nhãn, sửa một luật kiểm tra —
 * KHÔNG phải sửa file này. Đó là cả lý do ngăn kéo được phép tồn tại; xem
 * khối chú thích ở _layout/header-auth.php.
 *
 * Hệ quả phải giữ: đừng bao giờ đọc tên trường, đừng kiểm tra dữ liệu, đừng
 * dựng thông báo lỗi ở đây.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * TẮT JAVASCRIPT: file này không chạy, thẻ mở là <a href="/auth"> thật nên
 * bấm vào là sang trang. Không mất lối nào.
 */

(function () {
    'use strict';

    var cum = document.querySelector('[data-auth-drawer]');
    if (!cum) return;

    var than = cum.querySelector('[data-auth-body]');
    if (!than) return;

    var daNap = false;
    var dangNap = false;

    /* ------------------------------------------------------------------
       NHỮNG ĐƯỜNG SỐNG ĐƯỢC TRONG NGĂN KÉO

       Danh sách CHỌN-VÀO, cố ý gõ tay chứ không đoán: tấm chỉ dựng được
       những trang máy chủ trả ra ở dạng mảnh VÀ vẫn thuộc luồng đăng nhập.

         /auth            đổi tab Đăng nhập <-> Đăng ký
         /quen-mat-khau   xin mã đặt lại mật khẩu

       Dùng ở HAI chỗ, và phải là cùng một danh sách: lọc liên kết được bấm,
       và xét địa chỉ CUỐI CÙNG sau khi máy chủ chuyển hướng. Tách làm hai
       bản là một luồng đi vào được mà không đi ra được, hoặc ngược lại.

       ĐỪNG THÊM /auth/google. Nó chuyển hướng sang tên miền Google: fetch()
       vướng CORS, mà kể cả qua được thì màn đồng ý của Google không có lý do
       gì sống trong một cái tấm 520px của ta. Nó phải là điều hướng thật,
       nên nó rơi ra khỏi đây và chạy như liên kết bình thường.

       Mọi đường khác (Chính sách bảo mật, /tai-khoan sau khi đăng nhập
       xong…) cũng vậy: rời tấm, điều hướng như thường. Đúng hành vi, không
       phải thiếu sót.
       ------------------------------------------------------------------ */
    var DUONG_TRONG_TAM = ['/auth', '/quen-mat-khau'];

    /* ------------------------------------------------------------------
       NẠP MỘT ĐỊA CHỈ VÀO NGĂN KÉO

       `X-Auth: 1` là thứ bảo máy chủ in nguyên view, không in khung. Xem
       nhánh trả mảnh ở master.php — cùng hợp đồng với X-Catalog/X-Account.

       `credentials: same-origin` BẮT BUỘC: phiên đăng nhập nằm trong cookie
       và fetch() không tự gửi cookie cho mọi cấu hình trình duyệt cũ. Thiếu
       nó thì token CSRF in ra thuộc về một phiên khác và mọi lần gửi form
       đều hỏng — kiểu lỗi chỉ lộ ra lúc bấm nút, không phải lúc mở.
       ------------------------------------------------------------------ */
    var nap = function (url, tuyChon) {
        if (dangNap) return;
        dangNap = true;
        than.setAttribute('aria-busy', 'true');

        var caiDat = tuyChon || {};
        caiDat.credentials = 'same-origin';
        caiDat.headers = caiDat.headers || {};
        caiDat.headers['X-Auth'] = '1';

        return window.fetch(url, caiDat)
            .then(function (res) {
                /* ĐÃ BỊ CHUYỂN HƯỚNG RA KHỎI /auth = ĐĂNG NHẬP XONG.

                   AuthController luôn kết thúc bằng redirect(): thất bại thì
                   về lại /auth kèm flash lỗi, thành công thì sang đích thật
                   (/tai-khoan, hoặc chỗ khách đang dở). Nên chỉ cần nhìn địa
                   chỉ CUỐI CÙNG là biết kết quả — không phải đọc nội dung,
                   không phải đoán theo chữ trong trang.

                   Thành công thì rời hẳn sang trang đó: phiên vừa đổi, mà cả
                   trang nền phía sau (giỏ hàng, icon tài khoản, thanh nav)
                   đang dựng theo phiên CŨ. Vá từng mẩu ở đây là dựng lại
                   nửa cái máy chủ trong trình duyệt.

                   SO VỚI CẢ DANH SÁCH DUONG_TRONG_TAM, không riêng '/auth':
                   luồng quên mật khẩu cũng sống trong tấm này, nên một lượt
                   POST /quen-mat-khau/gui kết thúc ở /quen-mat-khau vẫn là
                   "còn ở trong tấm". So đúng một đường thì mỗi bước của luồng
                   ấy lại đá khách ra một trang thật. */
                var dich = new URL(res.url, window.location.href);

                if (DUONG_TRONG_TAM.indexOf(dich.pathname) === -1) {
                    window.location.assign(res.url);
                    return null;
                }

                return res.text();
            })
            .then(function (html) {
                if (html === null) return;
                than.innerHTML = html;
                daNap = true;

                /* Con trỏ vào ô đầu tiên — người mở ngăn kéo là để gõ. Bỏ qua
                   ô ẩn và ô chỉ-đọc. */
                var o = than.querySelector('input:not([type=hidden]):not([readonly])');
                if (o) o.focus();
            })
            .catch(function () {
                /* Mạng hỏng — KHÔNG nuốt lỗi rồi để ngăn kéo trống. Đưa khách
                   sang trang thật, ở đó ít nhất họ thấy trang lỗi của trình
                   duyệt thay vì một tấm trắng không giải thích gì. */
                window.location.assign('/auth');
            })
            .then(function () {
                dangNap = false;
                than.setAttribute('aria-busy', 'false');
            });
    };

    /* ------------------------------------------------------------------
       MỞ LẦN ĐẦU THÌ NẠP

       header.js gắn .is-open (xem vòng lặp [data-hpop] ở đó) — file này chỉ
       NGHE, không tự mở/đóng gì. MutationObserver chứ không nghe cú bấm:
       ngăn kéo còn mở được bằng bàn phím và đóng được bằng Esc / nền mờ /
       nút ✕, mà mọi lối ấy đều đi qua đúng một chỗ là lớp .is-open.

       Nạp LẠI mỗi lần mở, không phải chỉ lần đầu — trừ khi đang có form dở.
       Token CSRF và các bước OTP có hạn; một ngăn kéo mở ra sau nửa tiếng
       với nội dung cũ là một form gửi đi sẽ hỏng.
       ------------------------------------------------------------------ */
    var quanSat = new MutationObserver(function () {
        if (!cum.classList.contains('is-open')) return;
        if (dangNap) return;

        /* Có chữ khách đang gõ dở thì giữ nguyên — đóng nhầm rồi mở lại
           không được phép xoá mất thứ họ vừa nhập. */
        if (daNap && coChuDangGo()) return;

        nap('/auth');
    });

    var coChuDangGo = function () {
        var os = than.querySelectorAll('input:not([type=hidden]), textarea');
        for (var i = 0; i < os.length; i++) {
            if (os[i].type === 'checkbox' || os[i].type === 'radio') {
                if (os[i].checked !== os[i].defaultChecked) return true;
            } else if (os[i].value !== '') {
                return true;
            }
        }
        return false;
    };

    quanSat.observe(cum, { attributes: true, attributeFilter: ['class'] });

    /* Liên kết trong tấm — lọc qua DUONG_TRONG_TAM khai ở đầu file. */
    than.addEventListener('click', function (e) {
        var a = e.target instanceof Element ? e.target.closest('a[href]') : null;
        if (!a) return;

        /* Ctrl/Cmd/Shift/chuột giữa = mở tab mới, để trình duyệt lo. */
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

        var dich = new URL(a.href, window.location.href);
        if (dich.origin !== window.location.origin) return;
        if (DUONG_TRONG_TAM.indexOf(dich.pathname) === -1) return;

        e.preventDefault();
        nap(dich.pathname + dich.search);
    });

    /* ------------------------------------------------------------------
       GỬI FORM NGAY TRONG NGĂN KÉO

       Uỷ quyền từ thẻ bọc, không gắn vào từng form: ruột ngăn kéo bị thay
       mới sau mỗi lượt nạp, nên handler gắn trực tiếp sẽ chết ngay sau đó.

       FormData(form) lấy ĐÚNG những gì trình duyệt sẽ gửi — kể cả _token và
       các ô ẩn. Không liệt kê tay trường nào, xem nguyên tắc đầu file.
       ------------------------------------------------------------------ */
    than.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        /* Form dùng GET (nếu có) để nguyên cho trình duyệt: nó chỉ đổi địa
           chỉ chứ không đổi gì phía máy chủ. */
        if ((form.method || 'get').toLowerCase() !== 'post') return;

        e.preventDefault();

        /* Nút submit vừa bấm có `name` thì giá trị của nó PHẢI đi kèm —
           FormData(form) không tự thêm, và vài form dùng chính nó để phân
           biệt hành động. e.submitter không có ở trình duyệt cũ, nên có
           kiểm tra trước. */
        var data = new FormData(form);
        var nut = e.submitter;
        if (nut && nut.name) data.append(nut.name, nut.value);

        nap(form.action, { method: 'POST', body: data });
    });
})();
