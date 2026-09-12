/**
 * auth-drawer.js — ngăn kéo đăng nhập/đăng ký.
 *
 * Khuôn:  app/views/_layout/auth-drawer.php   (<template id="authDrawerTpl">)
 * Khung:  assets/css/components/auth-drawer.css
 * Ruột:   chính trang /auth, nạp ngầm qua nhánh `X-Auth` ở master.php
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * NGUYÊN TẮC DUY NHẤT: FILE NÀY KHÔNG BIẾT GÌ VỀ LUỒNG ĐĂNG NHẬP
 *
 * Nó không biết có mấy bước, ô nào bắt buộc, lỗi trông ra sao, OTP gửi đi
 * đâu. Nó làm đúng ba việc: mở/đóng một cái tấm, nạp HTML của /auth vào đó,
 * và gửi form nào được submit bên trong.
 *
 * Mọi thứ thuộc nghiệp vụ vẫn nằm ở AuthController và app/views/auth/. Thêm
 * một bước, đổi một nhãn, sửa một luật kiểm tra — KHÔNG phải sửa file này.
 * Đừng bao giờ đọc tên trường, kiểm tra dữ liệu hay dựng thông báo lỗi ở đây.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * BỐN TRẠNG THÁI, KHÔNG PHẢI MỘT CỜ isOpen
 *
 *     'closed'  → không có node nào trong DOM
 *     'open'    → đã gắn, đang/đã trượt vào
 *     'closing' → đang trượt ra, node VẪN CÒN
 *     'closed'  → transitionend bắn xong mới gỡ node
 *
 * Một cờ boolean không đủ, và đây là lý do cụ thể: gỡ node ngay lúc bấm đóng
 * thì hiệu ứng ra không bao giờ chạy — tấm biến mất tức thì, còn nền mờ nhấp
 * nháy. Phải có một pha thứ ba để chờ.
 *
 * Chiều ngược lại cũng cần một bước riêng: gắn node rồi thêm .is-open ngay
 * trong cùng một khung hình thì trình duyệt GỘP hai trạng thái, không thấy
 * giá trị đầu, nên không có gì để nội suy — tấm hiện ra tại chỗ. Nên giữa
 * hai bước phải ÉP MỘT LƯỢT REFLOW (đọc offsetWidth). Đó là một dòng trông
 * như thừa và nó tuyệt đối không thừa.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * TẮT JAVASCRIPT: file này không chạy, thẻ mở là <a href="/auth"> thật nên
 * bấm vào là sang trang. Không mất lối nào.
 */

(function () {
    'use strict';

    var khuon = document.getElementById('authDrawerTpl');
    var goc   = document.getElementById('modal-root');

    if (!khuon || !goc) return;

    /* Trạng thái: 'closed' | 'open' | 'closing'. */
    var trangThai = 'closed';

    var lop = null;        // .authov đang sống trong DOM
    var than = null;       // [data-auth-body]
    var panel = null;      // .authov__panel
    var moBoi = null;      // phần tử đã bấm để mở — trả tiêu điểm về đúng nó
    var hetGio = null;     // lưới an toàn cho transitionend
    var daNap = false;
    var dangNap = false;

    /* ──────────────────────────────────────────────────────────────────
       KHOÁ CUỘN NỀN

       Bù đúng bề ngang thanh cuộn vừa mất: thiếu bước này, khoá cuộn làm
       cả trang nhảy sang phải vài pixel đúng lúc tấm trượt vào — cú nhảy
       thấy rõ nhất ở thanh đầu trang dính.

       ĐẾM SỐ LẦN thay vì đặt/xoá thẳng: ngăn kéo này không phải lớp phủ
       duy nhất của site (còn ô tìm kiếm, hộp thoại mua). Nếu hai thứ cùng
       mở rồi một cái đóng, trả cuộn về ngay là mở khoá dưới chân cái còn
       lại. Biến đếm nằm trên window để mọi lớp phủ dùng chung được.
       ────────────────────────────────────────────────────────────────── */
    var khoaCuon = function () {
        window.__scrollLocks = (window.__scrollLocks || 0) + 1;

        if (window.__scrollLocks > 1) return;

        var bu = window.innerWidth - document.documentElement.clientWidth;

        document.body.dataset.authovPad = document.body.style.paddingRight || '';
        document.body.style.overflow = 'hidden';

        if (bu > 0) document.body.style.paddingRight = bu + 'px';
    };

    var moKhoaCuon = function () {
        window.__scrollLocks = Math.max(0, (window.__scrollLocks || 1) - 1);

        if (window.__scrollLocks > 0) return;

        document.body.style.overflow = '';
        document.body.style.paddingRight = document.body.dataset.authovPad || '';
        delete document.body.dataset.authovPad;
    };

    /* ──────────────────────────────────────────────────────────────────
       BẪY TIÊU ĐIỂM

       Tab ở phần tử cuối vòng về đầu, Shift+Tab ở đầu vòng về cuối. Danh
       sách phần tử tính LẠI mỗi lần bấm phím chứ không nhớ sẵn: ruột tấm
       bị thay mới sau mỗi lượt nạp (bước email → bước mật khẩu), nên một
       danh sách nhớ sẵn sẽ trỏ vào những nút đã bị gỡ.
       ────────────────────────────────────────────────────────────────── */
    var CHON_DUOC =
        'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),' +
        'select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

    var danhSachTieuDiem = function () {
        if (!lop) return [];

        return Array.prototype.filter.call(
            lop.querySelectorAll(CHON_DUOC),
            function (el) {
                /* offsetParent === null = đang bị ẩn. Bẫy tiêu điểm vào một
                   nút vô hình là cách chắc chắn làm người dùng bàn phím kẹt
                   cứng trong tấm. */
                return el.offsetParent !== null || el === document.activeElement;
            }
        );
    };

    var onKeydown = function (e) {
        if (trangThai !== 'open') return;

        if (e.key === 'Escape') {
            e.preventDefault();
            dong();
            return;
        }

        if (e.key !== 'Tab') return;

        var ds = danhSachTieuDiem();
        if (!ds.length) return;

        var dau = ds[0];
        var cuoi = ds[ds.length - 1];

        /* Tiêu điểm đang ở NGOÀI tấm (vừa mở, hoặc trình duyệt trả nhầm) thì
           kéo về phần tử đầu. */
        if (!lop.contains(document.activeElement)) {
            e.preventDefault();
            dau.focus();
            return;
        }

        if (!e.shiftKey && document.activeElement === cuoi) {
            e.preventDefault();
            dau.focus();
        } else if (e.shiftKey && document.activeElement === dau) {
            e.preventDefault();
            cuoi.focus();
        }
    };

    /* ──────────────────────────────────────────────────────────────────
       NẠP MỘT ĐỊA CHỈ VÀO TẤM

       `X-Auth: 1` bảo máy chủ in nguyên view, không in khung — cùng hợp
       đồng với X-Catalog/X-Account, xem nhánh trả mảnh ở master.php.

       `credentials: same-origin` BẮT BUỘC: phiên nằm trong cookie, thiếu
       nó thì token CSRF in ra thuộc một phiên khác và mọi lần gửi form đều
       hỏng — lỗi chỉ lộ ra lúc bấm nút, không phải lúc mở.
       ────────────────────────────────────────────────────────────────── */
    var DUONG_TRONG_TAM = ['/auth', '/quen-mat-khau'];

    /* ──────────────────────────────────────────────────────────────────
       TÊN MÀN TRÊN THANH ĐẦU TẤM

       Tấm sống qua bốn màn (định danh · mật khẩu · đăng ký · nhập mã) nhưng
       khuôn chỉ được nhân bản MỘT lần, nên cái tên phải đi theo mảnh vừa nạp
       chứ không nằm cứng trong khuôn.

       Nguồn là data-short của <h1 id="authovTitle"> — bản RÚT GỌN mà
       app/views/auth/index.php in ra riêng cho chỗ này. Không lấy luôn chữ
       của h1: nó là câu đầy đủ ("Đăng nhập hoặc tạo tài khoản"), dài gấp ba
       chỗ trống trên thanh. Thiếu data-short thì lùi về chữ của h1 — xấu còn
       hơn để trống.
       ────────────────────────────────────────────────────────────────── */
    var datTenMan = function () {
        if (!lop || !than) return;

        var o   = lop.querySelector('[data-authov-title]');
        var h1  = than.querySelector('#authovTitle');

        if (!o) return;

        o.textContent = h1
            ? (h1.getAttribute('data-short') || h1.textContent || '').trim()
            : '';
    };

    var nap = function (url, tuyChon) {
        if (dangNap || !than) return;

        dangNap = true;
        than.setAttribute('aria-busy', 'true');

        var caiDat = tuyChon || {};
        caiDat.credentials = 'same-origin';
        caiDat.headers = caiDat.headers || {};
        caiDat.headers['X-Auth'] = '1';

        return window.fetch(url, caiDat)
            .then(function (res) {
                /* ĐÃ BỊ CHUYỂN HƯỚNG RA KHỎI luồng = ĐĂNG NHẬP XONG.

                   AuthController luôn kết thúc bằng redirect(): hỏng thì về
                   lại /auth kèm flash lỗi, xong thì sang đích thật. Nên chỉ
                   cần nhìn địa chỉ CUỐI CÙNG — không phải đọc nội dung.

                   Rời hẳn sang trang đó: phiên vừa đổi, mà cả trang nền phía
                   sau (giỏ hàng, icon tài khoản, nav) đang dựng theo phiên
                   CŨ. Vá từng mẩu ở đây là dựng lại nửa cái máy chủ trong
                   trình duyệt. */
                var dich = new URL(res.url, window.location.href);

                if (DUONG_TRONG_TAM.indexOf(dich.pathname) === -1) {
                    window.location.assign(res.url);
                    return null;
                }

                return res.text();
            })
            .then(function (html) {
                if (html === null || !than) return;

                than.innerHTML = html;
                daNap = true;

                datTenMan();

                /* Gắn lại phần tăng cường của auth.js cho mảnh vừa về: nút con
                   mắt, sáu ô mã tự nhảy, đồng hồ "Gửi lại mã". Không gọi thì
                   trong tấm nút con mắt mang `hidden` mãi mãi và sáu ô mã phải
                   bấm Tab từng ô — xem khối "BA KHỐI DƯỚI ĐÂY NHẬN MỘT GỐC"
                   trong assets/js/auth.js. */
                if (window.AuthUI && window.AuthUI.gan) window.AuthUI.gan(than);

                /* Con trỏ vào ô đầu tiên — người mở ngăn kéo là để gõ. */
                var o = than.querySelector('input:not([type=hidden]):not([readonly])');
                if (o) o.focus();
            })
            .catch(function () {
                /* Mạng hỏng — KHÔNG nuốt lỗi rồi để tấm trống. Đưa khách sang
                   trang thật, ở đó ít nhất họ thấy trang lỗi của trình duyệt
                   thay vì một tấm trắng không giải thích gì. */
                window.location.assign('/auth');
            })
            .then(function () {
                dangNap = false;
                if (than) than.setAttribute('aria-busy', 'false');
            });
    };

    /* ──────────────────────────────────────────────────────────────────
       MỞ
       ────────────────────────────────────────────────────────────────── */
    var mo = function (nutMo) {
        /* Đang đóng dở thì huỷ pha ra và mở lại chính node ấy — không gắn
           thêm cái thứ hai. Bấm nhanh hai lần là cảnh có thật. */
        if (trangThai === 'closing') {
            huyHetGio();
            trangThai = 'open';
            lop.classList.remove('is-closing');
            lop.classList.add('is-open');
            return;
        }

        if (trangThai !== 'closed') return;

        moBoi = nutMo || document.activeElement;

        lop = khuon.content.firstElementChild.cloneNode(true);
        goc.appendChild(lop);

        than  = lop.querySelector('[data-auth-body]');
        panel = lop.querySelector('[data-authov-panel]');

        khoaCuon();

        /* ÉP REFLOW — xem khối chú thích đầu file. Trình duyệt phải THẤY
           trạng thái ngoài màn trước khi ta đổi sang trạng thái vào, nếu
           không nó gộp hai bước và tấm hiện ra tại chỗ. */
        void lop.offsetWidth;

        trangThai = 'open';
        lop.classList.add('is-open');

        /* Tiêu điểm vào tấm (tabindex="-1") chứ không vào nút đầu tiên:
           trình đọc màn hình đọc tên hộp thoại trước, rồi người dùng Tab
           tới nút đầu — đúng thứ tự họ mong đợi ở một hộp thoại. */
        if (panel) panel.focus();

        document.addEventListener('keydown', onKeydown, true);

        /* Nạp LẠI mỗi lần mở, trừ khi có chữ đang gõ dở: token CSRF và các
           bước OTP đều có hạn, một tấm mở ra sau nửa tiếng với nội dung cũ
           là một form gửi đi sẽ hỏng. */
        if (!daNap || !coChuDangGo()) nap('/auth');
    };

    var coChuDangGo = function () {
        if (!than) return false;

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

    /* ──────────────────────────────────────────────────────────────────
       ĐÓNG — ba pha: bỏ lớp, đợi transitionend, mới gỡ node
       ────────────────────────────────────────────────────────────────── */
    var huyHetGio = function () {
        if (hetGio) {
            window.clearTimeout(hetGio);
            hetGio = null;
        }
    };

    var goHan = function () {
        if (trangThai !== 'closing' || !lop) return;

        huyHetGio();

        lop.removeEventListener('transitionend', onXongRa);
        lop.remove();

        lop = than = panel = null;
        trangThai = 'closed';
        daNap = false;

        document.removeEventListener('keydown', onKeydown, true);
        moKhoaCuon();

        /* Trả tiêu điểm về đúng phần tử đã bấm để mở. Kiểm isConnected: sau
           một lượt nạp ngầm, nút cũ có thể đã bị thay — lúc ấy focus() vào
           một node mồ côi là mất tiêu điểm về <body>. */
        if (moBoi && moBoi.isConnected) moBoi.focus();
        moBoi = null;
    };

    var onXongRa = function (e) {
        /* CHỈ nghe `transform` của chính tấm. Nền mờ cũng chạy transition
           (opacity) và cũng bắn transitionend — nghe bừa thì node bị gỡ giữa
           chừng lúc tấm còn đang trượt. */
        if (e.propertyName !== 'transform') return;
        if (panel && e.target !== panel) return;

        goHan();
    };

    var dong = function () {
        if (trangThai !== 'open' || !lop) return;

        trangThai = 'closing';
        lop.classList.remove('is-open');
        lop.classList.add('is-closing');

        lop.addEventListener('transitionend', onXongRa);

        /* LƯỚI AN TOÀN. transitionend KHÔNG bắn khi: tấm bị ẩn giữa chừng,
           người dùng đổi tab (trình duyệt dừng hoạt ảnh), hoặc hệ điều hành
           đặt "giảm chuyển động" khiến thời lượng về ~0 ở một số bản. Không
           có nhánh này thì node kẹt lại trong DOM và khoá cuộn không bao giờ
           được trả — trang đứng chết, không cuộn được nữa. 600ms > 450ms của
           hiệu ứng dài nhất. */
        huyHetGio();
        hetGio = window.setTimeout(goHan, 600);
    };

    /* ──────────────────────────────────────────────────────────────────
       CÁC LỐI MỞ / ĐÓNG
       ────────────────────────────────────────────────────────────────── */

    /* Uỷ quyền từ document: thẻ mở nằm trong thanh đầu trang, mà thanh ấy
       bị thay ruột ở vài luồng (đăng xuất, đổi giỏ) — handler gắn trực tiếp
       sẽ chết theo. */
    document.addEventListener('click', function (e) {
        var el = e.target instanceof Element ? e.target : null;
        if (!el) return;

        var nutMo = el.closest('[data-authov-open]');

        if (nutMo) {
            /* Ctrl/Cmd/Shift/chuột giữa trên một <a> = ý muốn mở tab mới. */
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

            e.preventDefault();
            mo(nutMo);
            return;
        }

        if (el.closest('[data-authov-close]')) {
            /* CHỈ nuốt cú bấm KHI TẤM ĐANG MỞ. Vài phần tử mang thuộc tính
               này còn sống trên trang /auth mở bằng đường dẫn thường — ở đó
               không có tấm nào để đóng, và chúng là liên kết THẬT (ví dụ
               "Tiếp tục không đăng nhập" trỏ về trang chủ). Nuốt vô điều
               kiện là biến chúng thành nút chết ở đúng trang ấy. */
            if (trangThai !== 'open') return;

            e.preventDefault();
            dong();
        }
    });

    /* ──────────────────────────────────────────────────────────────────
       LIÊN KẾT VÀ FORM BÊN TRONG TẤM

       Uỷ quyền từ document chứ không từ tấm: tấm bị gỡ và dựng lại mỗi lần
       mở, nên handler gắn vào nó phải gắn lại mỗi lượt — và đó đúng là kiểu
       rò rỉ listener mà đặc tả yêu cầu tránh. Một handler sống suốt đời
       trang, lọc bằng closest(), thì không có gì để rò.
       ────────────────────────────────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        if (trangThai !== 'open' || !than) return;

        var a = e.target instanceof Element ? e.target.closest('a[href]') : null;
        if (!a || !than.contains(a)) return;

        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

        var dich = new URL(a.href, window.location.href);
        if (dich.origin !== window.location.origin) return;

        /* Danh sách CHỌN-VÀO: chỉ những đường máy chủ trả ra được ở dạng
           mảnh VÀ vẫn thuộc luồng đăng nhập. ĐỪNG thêm /auth/google — nó
           chuyển hướng sang tên miền Google (fetch vướng CORS, mà màn đồng ý
           của Google cũng không có lý do gì sống trong một cái tấm). Nó phải
           là điều hướng thật, nên nó rơi ra khỏi đây. */
        if (DUONG_TRONG_TAM.indexOf(dich.pathname) === -1) return;

        e.preventDefault();
        nap(dich.pathname + dich.search);
    });

    document.addEventListener('submit', function (e) {
        if (trangThai !== 'open' || !than) return;

        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !than.contains(form)) return;

        /* Form GET để nguyên cho trình duyệt: nó chỉ đổi địa chỉ. */
        if ((form.method || 'get').toLowerCase() !== 'post') return;

        e.preventDefault();

        /* Nút submit vừa bấm có `name` thì giá trị của nó PHẢI đi kèm —
           FormData(form) không tự thêm, và vài form dùng chính nó để phân
           biệt hành động. */
        var data = new FormData(form);
        var nut = e.submitter;
        if (nut && nut.name) data.append(nut.name, nut.value);

        nap(form.action, { method: 'POST', body: data });
    });
})();
