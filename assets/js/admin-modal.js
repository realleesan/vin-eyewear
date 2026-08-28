/*
 * admin-modal.js — mở hộp thoại NGAY TẠI CHỖ, không tải lại trang.
 *
 * KHÔNG CÓ FILE NÀY THÌ CHUYỆN GÌ XẢY RA:
 * Mọi nút mở hộp vẫn là thẻ <a> trỏ tới chính trang đó kèm ?them=1 / ?sua=<id>
 * / ?xem=<id>, nên bấm vào là trình duyệt tải lại trang và máy chủ dựng sẵn hộp
 * thoại trong HTML. Chậm hơn một lượt tải, còn lại y hệt: mở được, điền được,
 * lưu được, đóng được. Đó cũng là đường duy nhất khi người dùng tắt JavaScript.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO LÀ FETCH CHỨ KHÔNG PHẢI DỰNG SẴN HỘP RỒI ẨN ĐI
 *
 * Cách hiển nhiên là in sẵn mọi hộp thoại vào trang rồi cho JS bật lên — mở là
 * tức thì, không cần mạng. Nó hỏng ở form SỬA: một trang sản phẩm có hai mươi
 * dòng, mà form sửa sản phẩm dài hơn sáu trăm dòng HTML. Dựng sẵn hai mươi bản
 * là nhân trang lên gấp mười lăm lần, tải chậm hẳn cho MỌI lần mở trang — kể cả
 * những lần không ai bấm Sửa.
 *
 * Fetch thì chỉ lấy đúng cái hộp người ta vừa bấm, và lấy từ CHÍNH ĐỊA CHỈ mà
 * thẻ <a> đang trỏ tới. Không có endpoint riêng, không có bản dựng HTML thứ hai
 * ở phía JS — máy chủ vẫn là nơi duy nhất biết cách vẽ cái hộp đó. Sửa form ở
 * view là cả hai đường cùng đổi.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỊA CHỈ VẪN ĐỔI THEO
 *
 * Mở hộp thì pushState sang đúng địa chỉ của nó, đóng thì history.back(). Nhờ
 * vậy F5 giữa chừng vẫn ra cái hộp đang mở, gửi đường dẫn cho đồng nghiệp cũng
 * vậy, và nút "quay lại" của trình duyệt đóng hộp thay vì rời trang — thói quen
 * mà người dùng mang theo từ mọi hộp thoại khác họ từng gặp.
 */
(function () {
    'use strict';

    var MO_HOST = 'amodal-host';

    /* Trang đơn hàng không dùng .amodal mà có cặp .aodim + .aodraw riêng (nền
       mờ và khung, hai thẻ anh em). Gom cả hai kiểu vào một chỗ để phần còn lại
       của file không phải biết sự khác nhau đó. */
    function bocHopThoai(doc) {
        var hop = doc.querySelector('.amodal');

        if (hop !== null) {
            return [hop];
        }

        var dim  = doc.querySelector('.aodim');
        var draw = doc.querySelector('.aodraw');

        return (dim !== null && draw !== null) ? [dim, draw] : [];
    }

    function host() {
        var el = document.getElementById(MO_HOST);

        if (el === null) {
            el = document.createElement('div');
            el.id = MO_HOST;
            document.body.appendChild(el);
        }

        return el;
    }

    function dangMo() {
        var el = document.getElementById(MO_HOST);

        return el !== null && el.childNodes.length > 0;
    }

    /* Địa chỉ để quay về khi đóng — lấy từ chính nút đóng trong hộp, vì mỗi
       trang có một cách bỏ tham số riêng (có trang còn phải giữ ?q= và ?status=
       đang lọc). Không có thì về địa chỉ trước lúc mở. */
    function urlDong() {
        var nut = document.querySelector('#' + MO_HOST + ' [data-modal-close]');

        return nut !== null ? nut.getAttribute('href') : null;
    }

    var truocKhiMo = null;

    function dong(dungBack) {
        var el = document.getElementById(MO_HOST);

        if (el === null || el.childNodes.length === 0) {
            return;
        }

        el.innerHTML = '';

        if (dungBack) {
            history.back();
        }

        /* Trả tiêu điểm về đúng cái nút vừa bấm. Không làm thì sau khi đóng,
           tiêu điểm rơi về đầu trang và người dùng bàn phím phải đi lại từ
           đầu bảng. */
        if (truocKhiMo !== null && document.contains(truocKhiMo)) {
            truocKhiMo.focus();
        }

        truocKhiMo = null;
    }

    function moTaiCho(href, nut) {
        return fetch(href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }

                return res.text();
            })
            .then(function (html) {
                var doc  = new DOMParser().parseFromString(html, 'text/html');
                var nodes = bocHopThoai(doc);

                if (nodes.length === 0) {
                    /* Máy chủ trả về một trang không có hộp thoại nào — hết
                       phiên, mất quyền, hoặc bản ghi vừa bị xoá. Đi tiếp bằng
                       đường thường để người dùng thấy trang thật (kể cả trang
                       đăng nhập) thay vì một cú bấm im lặng không làm gì. */
                    window.location.assign(href);

                    return;
                }

                /* Đang mở sẵn rồi mà bấm tiếp (đổi tab trong hồ sơ khách, bấm
                   Sửa một bản ghi đo) thì THAY địa chỉ chứ không đẩy thêm mục
                   lịch sử. Đẩy thêm thì một lần đóng chỉ lùi được một tab, và
                   hộp biến mất trong khi địa chỉ vẫn còn tham số của nó — F5
                   lại bật hộp lên. Cả phiên mở hộp chỉ nên tốn đúng một mục. */
                var daMo = dangMo();

                var el = host();
                el.innerHTML = '';

                nodes.forEach(function (n) {
                    el.appendChild(document.importNode(n, true));
                });

                if (!daMo) {
                    truocKhiMo = nut;
                    history.pushState({ vinModal: href }, '', href);
                } else {
                    history.replaceState({ vinModal: href }, '', href);
                }

                /* Địa chỉ có neo (#form-don-thuoc) thì cuộn tới đó: thân hộp
                   vừa được dựng lại nên nó đang ở đầu, mà thứ người ta vừa bấm
                   để xem có thể nằm tận cuối. */
                var neo = href.indexOf('#') >= 0 ? href.slice(href.indexOf('#') + 1) : '';
                var dich = neo !== '' ? document.getElementById(neo) : null;

                if (dich !== null) {
                    dich.scrollIntoView();
                }

                /* Tiêu điểm vào ô nhập đầu tiên — người bấm "Thêm mới" định gõ
                   ngay, và bắt họ bấm thêm một cái nữa vào ô đầu là thừa.

                   CHỈ với hộp CÓ nút lưu ở chân. Hộp chỉ để xem (hồ sơ khách,
                   ngăn kéo đơn hàng) vẫn có ô nhập nằm đâu đó giữa trang — hồ
                   sơ khách có ô "lý do khoá" — và nhảy tiêu điểm vào đó là cuộn
                   thân hộp xuống giữa chừng ngay khi vừa mở. Không có ô nào thì
                   lấy chính khung hộp, để phím Esc và trình đọc màn hình bắt
                   đúng chỗ. */
                var coLuu = el.querySelector(
                    '.amodal__foot button[type="submit"], .aodraw button[type="submit"]'
                ) !== null;
                var oDau = coLuu
                    ? el.querySelector('input:not([type="hidden"]), select, textarea')
                    : null;
                var khung = el.querySelector('.amodal__panel, .aodraw');

                if (oDau !== null) {
                    oDau.focus();
                } else if (dich === null && khung !== null) {
                    khung.setAttribute('tabindex', '-1');
                    khung.focus();
                }
            })
            .catch(function () {
                // Mạng hỏng giữa chừng: đi bằng đường thường, đừng nuốt cú bấm.
                window.location.assign(href);
            });
    }

    document.addEventListener('click', function (e) {
        /* Chuột giữa, Ctrl/Cmd+bấm, Shift+bấm — người dùng đang cố mở tab mới
           hoặc cửa sổ mới. Đừng chặn: hộp thoại này CÓ địa chỉ thật, mở ở tab
           khác là chuyện hợp lệ. */
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey
            || e.shiftKey || e.altKey) {
            return;
        }

        var dong_ = e.target.closest('[data-modal-close]');

        if (dong_ !== null && dangMo()) {
            e.preventDefault();
            dong(true);

            return;
        }

        var mo = e.target.closest('a[data-modal]');

        if (mo === null) {
            return;
        }

        var href = mo.getAttribute('href');

        if (href === null || href === '') {
            return;
        }

        e.preventDefault();
        moTaiCho(href, mo);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && dangMo()) {
            dong(true);
        }
    });

    /* Bấm "quay lại" khi hộp đang mở thì đóng hộp — không rời trang. Cũng chạy
       khi chính hàm dong() gọi history.back(). */
    window.addEventListener('popstate', function () {
        dong(false);
    });
}());
