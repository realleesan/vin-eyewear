/**
 * account.js — trang tài khoản (/tai-khoan)
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này trang vẫn chạy đủ — mỗi khối bên dưới
 * nói rõ nó bỏ được bước nào.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÃ GỠ CÙNG BẢN DỰNG "Ho So Nguoi Dung" (13/09/2026)
 *
 *   ganDoiAnh()          ảnh đại diện không còn trên trang
 *   ganHienMatKhau()     bản thiết kế không có nút con mắt ở ô mật khẩu
 *   ganBangXo()          không còn bảng xổ chọn nhiều nào ([data-multi])
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO ganChiTietDon() LÀ HÀM GỌI LẠI ĐƯỢC
 *
 * Bấm một tab KHÔNG tải lại trang — khối cuối file thay ruột .acct-nav và
 * .acct-main bằng HTML mới. Mọi listener gắn THẲNG vào phần tử trong hai khối
 * ấy chết theo phần tử cũ, nên khối cuối gọi lại nó sau mỗi lần thay.
 *
 * copy-btn.js và confirm-dialog.js KHÔNG cần đụng tới: chúng uỷ quyền trên
 * document. address-picker.js thì có — nó nghe sự kiện 'vin:acct-moi'.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

/* ── "Xem chi tiết" / "Thu gọn" đơn hàng: bật tắt tại chỗ ─────────────────────
 *
 * Không có JS thì hai nút này là link thường (?don=<mã>) và trang tải lại —
 * vẫn đúng, chỉ chậm hơn. Ở đây chặn cái tải lại đó: khối chi tiết đã nằm sẵn
 * trong trang (xem app/views/auth/account/don-hang.php), nên chỉ cần gỡ/đặt
 * lại thuộc tính hidden.
 *
 * URL vẫn cập nhật bằng replaceState để F5 hay chia sẻ link ra đúng cái đang
 * thấy — replaceState chứ không pushState: mở rồi thu gọn cùng một đơn là quay
 * về đúng chỗ ban đầu, ghi lại thì nút Lùi phải bấm mấy lần mới ra khỏi trang.
 */
function ganChiTietDon() {
    var list = document.querySelector('.acct-cards');
    if (!list) return;

    list.addEventListener('click', function (ev) {
        // Bấm giữ Ctrl/Shift/giữa chuột là ý muốn mở tab khác — để nguyên.
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button !== 0) return;

        var btn = ev.target.closest('[data-more]');
        if (!btn) return;

        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        if (!panel) return;   // không tìm thấy khối chi tiết thì cứ để link chạy

        ev.preventDefault();

        var open = panel.hidden;   // đang ẩn ⇒ lần bấm này là để mở

        panel.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? 'Thu gọn' : 'Xem chi tiết';

        var here = btn.getAttribute(open ? 'data-open-href' : 'data-close-href');

        if (here && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', here);
        }

        // href giờ phải là hành động NGƯỢC lại, để mở tab mới từ nút này ra
        // đúng thứ chữ trên nút hứa.
        btn.setAttribute('href', btn.getAttribute(open ? 'data-close-href' : 'data-open-href'));

        // Khi thu gọn mới cuộn — đầu thẻ có thể đã bị đẩy lên trên mép màn hình.
        if (!open) {
            var card = btn.closest('.acct-card');
            var top  = card ? card.getBoundingClientRect().top : 0;

            if (card && top < 0) {
                /* 'smooth' chỉ khi người dùng KHÔNG tắt hiệu ứng chuyển động:
                   behavior viết trong JS đè lên cả scroll-behavior của CSS. */
                card.scrollIntoView({
                    block: 'start',
                    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                        ? 'auto'
                        : 'smooth'
                });
            }
        }
    });
}

ganChiTietDon();

/* ── CUỘN XUỐNG: CHỈ CÒN TIÊU ĐỀ ───────────────────────────────────────────────
 *
 * Rời đỉnh trang thì thanh đầu trang và hàng tab lui đi, chỉ tiêu đề tab dính
 * ở mép trên màn hình (chủ dự án, 13/09/2026). Hàm này chỉ bật/tắt MỘT lớp,
 * .is-acct-gon trên <body>; mọi thứ nhìn thấy nằm trong account.css.
 *
 * NGƯỠNG: đáy hàng tab đã khuất dưới thanh đầu trang. Sớm hơn thì thanh đầu
 * trang lui đi khi hàng tab còn đang hiện dở — hai thanh không cùng nhịp.
 *
 * ĐO BẰNG VỊ TRÍ TRONG TÀI LIỆU (offsetHeight, bottom + scrollY), không đo đáy
 * thanh đầu trang trên màn hình: thanh đã trượt lên thì đáy ấy đổi theo, và
 * phép so sẽ lật lớp qua lại liên tục ngay tại ngưỡng. Hàng tab không dính
 * và không bị transform nên vị trí trong tài liệu của nó đứng yên.
 *
 * KHÔNG CÓ JS: lớp không bao giờ bật, hai thanh ở nguyên chỗ cũ, và tiêu đề
 * dính ngay dưới thanh đầu trang — trang vẫn đọc được bình thường.
 */
function ganThuGon() {
    var header = document.querySelector('.oa-header');
    var dangGon = false;

    /* Tính THẲNG trong sự kiện scroll, không hoãn qua requestAnimationFrame:
       trình duyệt đã tự gom scroll về tối đa một lần mỗi khung hình, và rAF
       bị tạm dừng ở tab không có tiêu điểm — lớp sẽ đứng ở trạng thái cũ cho
       tới khi tab được nhìn lại. */
    function tinh() {
        /* Hỏi lại mỗi lần: đổi tab thay ruột .acct-nav, không thay chính nó,
           nhưng rẻ và khỏi phải nhớ điều đó. */
        var nav = document.querySelector('.acct-nav');
        if (!nav) return;

        var dayNav = nav.getBoundingClientRect().bottom + window.scrollY;
        var cao    = header ? header.offsetHeight : 0;
        var gon    = window.scrollY + cao >= dayNav;

        if (gon !== dangGon) {
            dangGon = gon;
            document.body.classList.toggle('is-acct-gon', gon);
        }
    }

    window.addEventListener('scroll', tinh, { passive: true });
    window.addEventListener('resize', tinh);

    // F5 giữa trang: trình duyệt khôi phục vị trí cuộn mà không bắn sự kiện.
    tinh();
}

ganThuGon();

/* ── ĐỔI TAB KHÔNG TẢI LẠI TRANG ──────────────────────────────────────────────
 *
 * Mỗi tab là một <a href="/tai-khoan?muc=…"> thật, nên bấm là điều hướng:
 * trang trắng một nhịp và cuộn nhảy về đầu. Ở đây chặn cú bấm, nạp ngầm CHÍNH
 * cái href máy chủ đã dựng, rồi thay ruột hai khối: .acct-nav và .acct-main.
 *
 * THAY CẢ HÀNG TAB chứ không tự đổi lớp .is-active bằng JS: trạng thái "đang ở
 * tab nào" nằm ở cả lớp lẫn aria-current, và máy chủ dựng sẵn rồi thì lấy
 * nguyên về — tự cập nhật là chép lại luật của máy chủ ở tầng vẽ.
 *
 * BA THỨ KHÔNG ĐƯỢC MẤT — cùng danh sách với catalog.js:
 *   · Tắt JS: mỗi tab còn nguyên là <a href>, không có gì để hỏng.
 *   · Nút Lùi của trình duyệt: pushState mỗi bước, popstate nạp lại.
 *   · Tiện ích gắn trong hai khối bị thay: gọi lại sau mỗi lần thay.
 *
 * KHÔNG chặn nút "Đăng xuất" — đó là <form method="post">, không phải <a>. Liên
 * kết trong vùng nội dung (sửa hồ sơ, sửa địa chỉ, xem đơn…) cũng không: chúng
 * mở trạng thái theo URL và phải điều hướng thật.
 * ─────────────────────────────────────────────────────────────────────────── */
    var grid = document.querySelector('.acct__grid');

    /* Thiếu bất kỳ mảnh API nào thì KHÔNG bật tính năng — trang vẫn chạy bằng
       đường điều hướng thật. Không polyfill. Cùng lối với catalog.js. */
    if (!grid || !window.fetch || !window.DOMParser ||
        !window.history || !window.history.pushState) {
        return;
    }

    /* Đường của trang này, ghi MỘT LẦN — đổi tab chỉ đổi chuỗi truy vấn. Gõ cứng
       '/tai-khoan' thì ngày ai đó đổi route là hỏng im lặng. */
    var duongTaiKhoan = window.location.pathname;

    var luot     = 0;     // số thứ tự lượt nạp — bỏ qua câu trả lời đến muộn
    var dangChay = null;  // AbortController của lượt đang chạy

    /* Trạng thái cho URL hiện tại: thiếu dòng này thì lần bấm Lùi đầu tiên trả
       về một entry không có state và trang đứng im. */
    window.history.replaceState({ acct: 1 }, '', window.location.href);

    grid.addEventListener('click', function (ev) {
        if (ev.defaultPrevented) return;
        if (ev.button !== 0) return;
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;

        var el = ev.target;
        if (!el || !el.closest) return;

        var a = el.closest('a[href]');
        if (!a || !grid.contains(a) || !laDuongTab(a)) return;

        ev.preventDefault();
        napManh(a.href, true);
    });

    window.addEventListener('popstate', function () {
        if (window.location.pathname !== duongTaiKhoan) return;

        napManh(window.location.href, false);
    });

    /** Liên kết này có phải một cú đổi tab không? Chỉ trong hàng tab, và chỉ
        khi ở lại đúng trang này. */
    function laDuongTab(a) {
        if (a.origin !== window.location.origin) return false;
        if (a.pathname !== duongTaiKhoan) return false;

        return !!a.closest('.acct-nav');
    }

    function napManh(url, dayLichSu) {
        var stt = ++luot;

        /* Bấm nhanh ba tab liền tay là ba lượt chồng nhau. Huỷ lượt cũ, NHƯNG
           vẫn so số thứ tự bên dưới: huỷ không phải lúc nào cũng kịp. */
        if (dangChay) dangChay.abort();
        dangChay = window.AbortController ? new AbortController() : null;

        var main = grid.querySelector('.acct-main');
        if (main) main.setAttribute('aria-busy', 'true');

        window.fetch(url, {
            headers: { 'X-Account': '1' },
            credentials: 'same-origin',
            signal: dangChay ? dangChay.signal : undefined
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);

                return res.text();
            })
            .then(function (html) {
                if (stt !== luot) return;   // lượt cũ về muộn — bỏ

                var doc = new window.DOMParser().parseFromString(html, 'text/html');

                thayKhoi(doc, '.acct-nav');
                thayKhoi(doc, '.acct-main');

                /* Tiêu đề thẻ trình duyệt đi theo tab. Mảnh máy chủ trả về KHÔNG
                   có <title> (nhánh X-Account ở _layout/master.php in view, không
                   in khung), nên dựng lại đúng khuôn AuthController::profile()
                   dùng: nhãn tab + " — Vin Eyewear". */
                var tabMoi = grid.querySelector('.acct-nav__tab.is-active');

                if (tabMoi) document.title = tabMoi.textContent.trim() + ' — Vin Eyewear';

                if (dayLichSu) window.history.pushState({ acct: 1 }, '', url);

                /* Đang cuộn giữa trang (hàng tab và thanh đầu trang đã lui đi,
                   xem ganThuGon) thì đưa về đầu tab, để thấy hàng tab vừa bấm
                   và dòng tiêu đề mới. scroll-margin-top của .acct__grid dừng
                   nó ngay dưới thanh đầu trang — tức về đúng chỗ hai thanh hiện
                   lại. */
                if (document.body.classList.contains('is-acct-gon')) {
                    grid.scrollIntoView({ block: 'start' });
                }

                ganChiTietDon();
                document.dispatchEvent(new Event('vin:acct-moi'));
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;

                /* Mạng hỏng giữa chừng: đi đường thật — hơn hẳn một vùng nội
                   dung đứng im không lời giải thích. */
                window.location.href = url;
            });
    }

    /** Thay ruột một khối bằng bản mới trong tài liệu vừa nạp. */
    function thayKhoi(doc, sel) {
        var cu  = grid.querySelector(sel);
        var moi = doc.querySelector(sel);

        if (!cu || !moi) return;

        /* Thay RUỘT chứ không thay cả phần tử: giữ nguyên node mà listener uỷ
           quyền của copy-btn.js / confirm-dialog.js đang trỏ tới. */
        cu.innerHTML = moi.innerHTML;
        cu.removeAttribute('aria-busy');
    }
}());
