/**
 * account.js — trang tài khoản (/tai-khoan)
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này trang vẫn chạy đủ — mỗi khối bên dưới
 * nói rõ nó bỏ được bước nào.
 *
 * Nút "Tải ảnh lên" bị CSS ẩn khi có JS (.js .acct-nav__send), và class .js do
 * <script> đầu master.php đặt — nên không có kịch bản nào mà cả nút lẫn chức
 * năng tự gửi đều vắng mặt.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO HAI KHỐI ĐẦU KHÔNG CÒN LÀ IIFE
 *
 * Từ 2026-08-30 bấm một mục ở cột trái KHÔNG tải lại trang nữa — khối thứ ba
 * bên dưới thay ruột .acct-nav và .acct-main bằng HTML mới. Mọi listener gắn
 * THẲNG vào phần tử trong hai khối ấy chết theo phần tử cũ.
 *
 * Nên hai khối đầu là hàm gọi lại được, và khối thứ ba gọi chúng sau mỗi lần
 * thay. Đây đúng cách assets/js/catalog.js đang làm với ganTienIch().
 *
 * copy-btn.js và confirm-dialog.js KHÔNG cần đụng tới: chúng uỷ quyền trên
 * document nên không có gì để mất. address-picker.js thì có — nó nghe sự kiện
 * 'vin:acct-moi' do khối thứ ba phát ra.
 *
 * CẢ BA KHỐI NẰM CHUNG MỘT IIFE để hai hàm gắn lại là biến cục bộ, không phải
 * biến toàn cục treo trên window. Dự án không có hệ thống module, nên đây là
 * cách duy nhất cho ba khối gọi được nhau mà không để lại gì ra ngoài.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';


/* ── Đổi ảnh đại diện: chọn ảnh xong gửi luôn, khỏi bấm nút thứ hai ─────────
 *
 * GỌI LẠI ĐƯỢC. Cột điều hướng bị thay mới sau mỗi lần đổi mục (xem khối "ĐỔI
 * MỤC KHÔNG TẢI LẠI TRANG" ở cuối file), nên ô chọn ảnh sau cú đổi đầu tiên là
 * một phần tử KHÁC với lúc trang mới tải. Gắn sự kiện đúng một lần lúc đầu thì
 * từ cú đổi mục thứ nhất trở đi, chọn ảnh xong không có gì xảy ra.
 */
function ganDoiAnh() {
    var input = document.querySelector('.acct-nav__face input[type="file"]');
    if (!input) return;

    var form = input.form;
    if (!form) return;

    input.addEventListener('change', function () {
        if (!input.files || !input.files.length) return;

        // Chặn tại chỗ để khách biết ngay, khỏi chờ một vòng lên máy chủ.
        // Máy chủ VẪN kiểm lại — xem core/AvatarStorage.php; giới hạn ở đây
        // sửa được bằng công cụ nhà phát triển nên nó không phải hàng rào.
        var limit = form.querySelector('input[name="MAX_FILE_SIZE"]');

        if (limit && input.files[0].size > Number(limit.value)) {
            window.alert('Ảnh vượt quá dung lượng cho phép (tối đa 1 MB).');
            input.value = '';
            return;
        }

        form.submit();
    });
}

/* ── "Xem chi tiết" / "Thu gọn" đơn hàng: bật tắt tại chỗ ─────────────────────
 *
 * Không có JS thì hai nút này là link thường (?don=<mã>) và trang tải lại —
 * vẫn đúng, chỉ chậm hơn. Ở đây chặn cái tải lại đó: khối chi tiết đã nằm sẵn
 * trong trang (xem chú thích ở app/views/auth/account/don-hang.php), nên chỉ
 * cần gỡ/đặt lại thuộc tính hidden.
 *
 * URL vẫn được cập nhật bằng replaceState để F5 hay chia sẻ link ra đúng cái
 * đang thấy — replaceState chứ không pushState: mở rồi thu gọn cùng một đơn là
 * quay về đúng chỗ ban đầu, ghi lại thì nút "lùi" của trình duyệt phải bấm mấy
 * lần mới ra khỏi trang tài khoản.
 */
function ganChiTietDon() {
    var list = document.querySelector('.acct-list');
    if (!list) return;

    list.addEventListener('click', function (ev) {
        // Bấm giữ Ctrl/Shift/giữa chuột là ý muốn mở tab khác — để nguyên.
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button !== 0) return;

        /* ĐÃ GỠ nhánh [data-reveal] của nút "Xem thông tin chuyển khoản".
           Nút đó nay đi thẳng tới màn quét QR chứ không mở panel nữa, nên nhánh
           này không còn ai gọi — xem chú thích ở chân thẻ trong don-hang.php. */
        var btn = ev.target.closest('.acct-order__more');
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

        // href giờ phải là hành động NGƯỢC lại, để mở tab mới từ nút này (hoặc
        // một lần bấm nữa khi JS lỗi giữa đường) ra đúng thứ chữ trên nút hứa.
        btn.setAttribute('href', btn.getAttribute(open ? 'data-close-href' : 'data-open-href'));

        // Cả hai href đều trỏ tới #<mã đơn>. Khi mở thì bỏ qua: thẻ đang nằm
        // trong tầm mắt rồi, cuộn thêm chỉ làm giật. Khi thu gọn mới cuộn —
        // đầu thẻ có thể đã bị đẩy lên trên mép màn hình.
        if (!open) {
            var card = btn.closest('.acct-order');
            var top  = card ? card.getBoundingClientRect().top : 0;

            if (card && top < 0) {
                card.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }
        }
    });
}

/* ── BẢNG XỔ CHỌN NHIỀU: cập nhật dòng tóm tắt, đóng khi bấm ra ngoài ───────
 *
 * Thẻ <details> tự lo phần mở/đóng, nên KHÔNG có file này thì bảng vẫn xổ được
 * và vẫn tick được — mất đúng hai thứ:
 *   · dòng tóm tắt trên nút chỉ đúng tới lúc tải trang, tick xong phải Lưu mới
 *     thấy nó đổi (lúc ấy bảng đang mở, người dùng nhìn thẳng vào các ô tick
 *     nên không mất thông tin gì)
 *   · bảng ở lại mở tới khi bấm lại chính nó
 *
 * Uỷ quyền trên document chứ không gắn vào từng bảng: vùng nội dung tài khoản
 * bị thay mới sau mỗi lần đổi mục, mà uỷ quyền thì không có gì để gắn lại.
 * Nhờ vậy khối này KHÔNG cần nằm trong danh sách gọi lại của gan().
 */
function ganBangXo() {
    document.addEventListener('change', function (ev) {
        var hop = ev.target && ev.target.closest ? ev.target.closest('[data-multi]') : null;

        if (!hop) return;

        var o   = hop.querySelector('[data-multi-val]');
        var tic = hop.querySelectorAll('input[type="checkbox"]:checked');

        if (!o) return;

        var ten = [];

        Array.prototype.forEach.call(tic, function (i) {
            var nhan = i.nextElementSibling;

            ten.push(nhan ? nhan.textContent.trim() : i.value);
        });

        /* Cùng một câu với thứ máy chủ in ra — hai chỗ lệch nhau thì tải lại
           trang là dòng chữ đổi mà chẳng ai chạm vào gì. */
        o.textContent = ten.length ? ten.join(' · ') : '— Chưa chọn —';
    });

    /* Bấm ra ngoài thì đóng. <details> không tự làm việc này, mà một bảng xổ
       cứ nằm mở sau khi người dùng đã đi chỗ khác thì che mất phần form dưới
       nó — bảng này NỔI LÊN TRÊN chứ không đẩy form xuống. */
    document.addEventListener('click', function (ev) {
        var trong = ev.target && ev.target.closest ? ev.target.closest('[data-multi]') : null;

        Array.prototype.forEach.call(document.querySelectorAll('[data-multi][open]'), function (d) {
            if (d !== trong) d.open = false;
        });
    });

    /* Esc đóng bảng đang mở — thói quen của mọi thứ nổi lên trên. */
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;

        Array.prototype.forEach.call(document.querySelectorAll('[data-multi][open]'), function (d) {
            d.open = false;
        });
    });
}

/* Chạy lần đầu cho HTML máy chủ vừa in ra.

   ganBangXo() KHÔNG nằm trong gan() ở cuối file: nó uỷ quyền trên document nên
   gọi lại là gắn chồng listener, mỗi lần đổi mục thêm một bộ. */
ganBangXo();
ganDoiAnh();
ganChiTietDon();

/* ── ĐỔI MỤC KHÔNG TẢI LẠI TRANG ──────────────────────────────────────────────
 *
 * Mỗi mục ở cột trái là một <a href="/tai-khoan?muc=…"> thật, nên bấm là điều
 * hướng: trang trắng một nhịp, cuộn nhảy về đầu, và cả cột trái lẫn thẻ khách
 * vẽ lại từ đầu dù chỉ có vùng phải đổi.
 *
 * Ở đây chặn cú bấm, nạp ngầm CHÍNH cái href máy chủ đã dựng (không bịa URL
 * mới), rồi thay ruột hai khối: .acct-nav và .acct-main.
 *
 * VÌ SAO THAY CẢ CỘT TRÁI chứ không chỉ đổi lớp .is-on bằng JS: trạng thái
 * "đang xem mục nào" nằm ở BA chỗ trong cột đó — lớp .is-on, thuộc tính
 * aria-current, và <details open> của nhóm "Tài khoản của tôi". Tự cập nhật cả
 * ba là chép lại luật của máy chủ ở tầng vẽ, và hai bản luật ấy sẽ lệch nhau ở
 * lần thêm mục thứ tám. Máy chủ dựng sẵn rồi thì lấy nguyên về.
 *
 * BA THỨ KHÔNG ĐƯỢC MẤT — cùng danh sách với catalog.js:
 *   · Tắt JS: mỗi mục còn nguyên là <a href>, không có gì để hỏng.
 *   · Nút Lùi của trình duyệt: pushState mỗi bước, popstate nạp lại.
 *   · Các tiện ích gắn trong hai khối bị thay: gọi lại sau mỗi lần thay.
 *
 * KHÔNG chặn cú bấm vào nút "Đăng xuất" — đó là <form method="post">, không
 * phải <a>, nên nó không lọt vào đây. Cũng không chặn liên kết ra ngoài
 * /tai-khoan (ví dụ "Xem chi tiết đơn" dẫn sang trang khác).
 * ─────────────────────────────────────────────────────────────────────────── */
    var grid = document.querySelector('.acct__grid');

    /* Thiếu bất kỳ mảnh API nào thì KHÔNG bật tính năng — trang vẫn chạy y như
       trước bằng đường điều hướng thật. Không polyfill, không nhánh mã thứ hai
       phải nuôi. Cùng lối với catalog.js. */
    if (!grid || !window.fetch || !window.DOMParser ||
        !window.history || !window.history.pushState) {
        return;
    }

    /* Đường của trang này, ghi MỘT LẦN. Đổi mục chỉ đổi chuỗi truy vấn, không
       bao giờ đổi đường dẫn — nên giá trị này đúng suốt vòng đời trang.
       Gõ cứng '/tai-khoan' thì ngày ai đó đổi route là hỏng im lặng, đúng lỗi
       đã dính ở catalog.js hồi tách trang con. */
    var duongTaiKhoan = window.location.pathname;

    var luot     = 0;     // số thứ tự lượt nạp — bỏ qua câu trả lời đến muộn
    var dangChay = null;  // AbortController của lượt đang chạy

    /* Trạng thái cho URL hiện tại: thiếu dòng này thì lần bấm Lùi đầu tiên trả
       về một entry không có state và trang đứng im. */
    window.history.replaceState({ acct: 1 }, '', window.location.href);

    grid.addEventListener('click', function (ev) {
        if (ev.defaultPrevented) return;

        /* Để yên cho các lối mở-ở-tab-khác: chuột giữa, Ctrl/Cmd/Shift/Alt. */
        if (ev.button !== 0) return;
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;

        var el = ev.target;
        if (!el || !el.closest) return;

        var a = el.closest('a[href]');
        if (!a || !grid.contains(a) || !laDuongMuc(a)) return;

        ev.preventDefault();
        napManh(a.href, true);
    });

    window.addEventListener('popstate', function () {
        /* Lùi ra khỏi trang tài khoản thì trình duyệt lo, không phải mình. */
        if (window.location.pathname !== duongTaiKhoan) return;

        napManh(window.location.href, false);
    });

    /**
     * Liên kết này có phải một cú đổi mục không?
     *
     * CHỈ trong cột điều hướng, và chỉ khi ở lại đúng trang này. Liên kết
     * trong vùng nội dung (xem chi tiết đơn, sửa địa chỉ…) phải điều hướng
     * thật — chúng dẫn sang trang khác hoặc mở hộp thoại theo URL.
     */
    function laDuongMuc(a) {
        if (a.origin !== window.location.origin) return false;
        if (a.pathname !== duongTaiKhoan) return false;

        return !!a.closest('.acct-nav');
    }

    function napManh(url, dayLichSu) {
        var stt = ++luot;

        /* Bấm nhanh ba mục liền tay là ba lượt chồng nhau. Huỷ lượt cũ cho đỡ
           tốn, NHƯNG vẫn phải so số thứ tự bên dưới: huỷ không phải lúc nào
           cũng kịp, và câu trả lời của lượt cũ về sau lượt mới sẽ vẽ đè lên
           kết quả đúng. */
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

                if (dayLichSu) window.history.pushState({ acct: 1 }, '', url);

                /* Cuộn lên đầu vùng nội dung, KHÔNG lên đầu trang: cột trái
                   dính theo cuộn nên người dùng vẫn thấy mục vừa bấm, mà nội
                   dung mới thì bắt đầu từ dòng đầu của nó. */
                var m = grid.querySelector('.acct-main');

                if (m && m.getBoundingClientRect().top < 0) {
                    m.scrollIntoView({ block: 'start' });
                }

                gan();
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;

                /* Mạng hỏng giữa chừng: đi đường thật. Người dùng nhận đúng
                   trang họ vừa bấm, cùng lắm là chậm hơn một nhịp — hơn hẳn
                   một vùng nội dung đứng im không lời giải thích. */
                window.location.href = url;
            });
    }

    /** Thay ruột một khối bằng bản mới trong tài liệu vừa nạp. */
    function thayKhoi(doc, sel) {
        var cu  = grid.querySelector(sel);
        var moi = doc.querySelector(sel);

        if (!cu || !moi) return;

        /* Thay RUỘT chứ không thay cả phần tử: giữ nguyên chính cái node mà
           listener uỷ quyền của copy-btn.js / confirm-dialog.js đang trỏ tới,
           và giữ luôn lớp CSS ngoài cùng. */
        cu.innerHTML = moi.innerHTML;
        cu.removeAttribute('aria-busy');
    }

    /**
     * Gắn lại các tiện ích sống trong hai khối vừa bị thay.
     *
     * Hai hàm đầu là của chính file này. Sự kiện 'vin:acct-moi' dành cho file
     * khác — hiện chỉ address-picker.js nghe; nó gắn thẳng vào hai ô chọn
     * tỉnh/phường nên không tự sống sót qua một lần thay ruột.
     */
    function gan() {
        ganDoiAnh();
        ganChiTietDon();

        document.dispatchEvent(new Event('vin:acct-moi'));
    }
}());
