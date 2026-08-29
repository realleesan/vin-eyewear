/**
 * catalog.js — bốn tiện ích cho trang danh sách sản phẩm (/san-pham).
 *
 * CẢ BỐN ĐỀU LÀ TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN ĐỂ TRANG CHẠY. Bộ lọc của
 * trang này là liên kết và form GET thật; tắt JavaScript thì mọi thứ vẫn lọc
 * được, chỉ mất bốn chỗ tiện tay:
 *
 *   1. Đổi ô "Sắp xếp theo" là lọc luôn, không phải bấm "Áp dụng".
 *   2. Gõ vào ô "Tìm thương hiệu" là danh sách lọc ngay tại chỗ, không phải
 *      tải lại trang.
 *   3. Bấm một tiêu chí lọc thì chỉ hai mảnh của trang được thay, cả trang
 *      KHÔNG tải lại: vị trí cuộn còn nguyên và bảng bộ lọc trên điện thoại
 *      không bị đóng sập sau mỗi lần tick.
 *   4. Trên màn hình hẹp, bottom-sheet bộ lọc đóng được bằng nút "Xem N sản
 *      phẩm", bằng nền mờ và bằng phím Esc — thay vì phải cuộn ngược lên tìm
 *      lại chữ "Bộ lọc".
 *
 * Hai nút "Áp dụng" và "Lọc" bị CSS ẩn đi khi <html> có class .js (master.php
 * gắn class đó ngay đầu <head>). File này chỉ lo phần hành vi.
 */

(function () {
    'use strict';

    /* ------------------------------------------------------------------
       1 + 2. HAI TIỆN ÍCH NÀY PHẢI GẮN LẠI ĐƯỢC

       Mục 3 thay RUỘT của cột lọc và lưới kết quả bằng HTML mới, nên ô "Sắp
       xếp theo" và ô "Tìm thương hiệu" sau mỗi cú lọc là hai phần tử KHÁC với
       lúc trang mới tải. Nghe sự kiện đúng một lần lúc đầu thì cú lọc đầu tiên
       là mất sạch — ô sắp xếp thành ô chết, gõ vào ô thương hiệu không lọc gì
       nữa.

       Gom vào một hàm để mục 3 gọi lại sau mỗi lần thay. Không sợ gắn chồng:
       phần tử cũ bị thay hẳn, listener cũ đi theo nó.
       ------------------------------------------------------------------ */

    ganTienIch();

    function ganTienIch() {
        // 1. Đổi ô sắp xếp -> lọc luôn
        var sortSelect = document.getElementById('f-sort');

        if (sortSelect && sortSelect.form) {
            sortSelect.addEventListener('change', function () {
                /* Rẽ nhánh NGAY TẠI ĐÂY chứ không chặn sự kiện 'submit' của
                   form: form.submit() gọi bằng mã KHÔNG phát sự kiện 'submit',
                   nên một listener trên form sẽ không bao giờ chạy. */
                if (!diToiTaiCho(urlSapXep(sortSelect))) {
                    sortSelect.form.submit();
                }
            });
        }

        // 2. Lọc danh sách thương hiệu ngay khi gõ
        var brandForm = document.querySelector('[data-brand-filter]');

        if (brandForm) {
            initBrandSearch(brandForm);
        }

        /* Nền mờ sau bottom-sheet: HTML để hidden vì không có JS thì nó không
           đóng được gì mà vẫn phủ kín màn hình chặn mọi cú bấm. Bật ở đây chứ
           không ở mục 4 vì nó nằm TRONG cột lọc, tức là bị thay mới sau mỗi cú
           lọc và quay lại trạng thái hidden của HTML. */
        var scrim = document.querySelector('.cfilter__scrim');

        if (scrim) {
            scrim.hidden = false;
        }
    }

    /** URL cho một lựa chọn sắp xếp, hoặc null nếu trình duyệt thiếu URL API. */
    function urlSapXep(select) {
        if (!window.URL) return null;

        try {
            var u = new URL(window.location.href);

            u.searchParams.set('sort', select.value);
            // Đổi cách sắp xếp thì phải về trang 1: trang 7 của thứ tự cũ
            // không có nghĩa gì trong thứ tự mới.
            u.searchParams.delete('page');

            return u.toString();
        } catch (e) {
            return null;
        }
    }

    function initBrandSearch(form) {
        var input = form.querySelector('input[name="bq"]');
        var list  = form.parentNode.querySelector('.pfacet__list');

        if (!input || !list) return;

        var rows = Array.prototype.slice.call(list.querySelectorAll('[data-brand]'));

        // Câu "không có thương hiệu nào khớp" — server đã in sẵn khi lọc phía
        // nó ra rỗng. Lọc phía trình duyệt thì phải tự dựng, nhưng chỉ dựng
        // MỘT lần rồi bật/tắt, không tạo lại mỗi lần gõ.
        var empty = list.querySelector('.pfacet__none');

        if (!empty) {
            empty = document.createElement('p');
            empty.className = 'pfacet__none';
            empty.textContent = 'Không có thương hiệu nào khớp.';
            empty.hidden = true;
            list.appendChild(empty);
        }

        // Không cho Enter tải lại trang: danh sách đã lọc sẵn trước mắt rồi.
        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        input.addEventListener('input', function () {
            // Hai phép so, khớp đúng hai phép so ở phía server (xem $checkGroup
            // trong app/views/product/index.php):
            //
            //   data-brand        chữ thường CÒN DẤU  -> gõ "saint" ra Saint Laurent
            //   data-brand-plain  slug ĐÃ BỎ DẤU      -> gõ "gioi" ra "Giới"
            //
            // Thiếu vế thứ hai thì người gõ không dấu (cách gõ nhanh phổ biến
            // nhất trên điện thoại) không tìm ra hãng nào có tên tiếng Việt.
            var typed = input.value.trim();
            var lower = typed.toLowerCase();
            var plain = deaccent(lower);
            var shown = 0;

            rows.forEach(function (row) {
                var match = typed === ''
                    || row.getAttribute('data-brand').indexOf(lower) !== -1
                    || (row.getAttribute('data-brand-plain') || '').indexOf(plain) !== -1;

                row.hidden = !match;
                if (match) shown++;
            });

            empty.hidden = shown > 0;
        });
    }

    /**
     * Bỏ dấu tiếng Việt, cho ra cùng dạng với slugify() bên PHP.
     *
     * normalize('NFD') tách chữ khỏi dấu rồi xoá dải dấu kết hợp; 'đ' không
     * phải chữ có dấu kết hợp nên phải thay tay. Trình duyệt không có
     * String.normalize (rất cũ) thì bỏ qua bước bỏ dấu — người gõ đủ dấu vẫn
     * tìm được, chỉ mất đường gõ tắt.
     */
    function deaccent(text) {
        if (!String.prototype.normalize) return text;

        return text.normalize('NFD')
                   .replace(/[\u0300-\u036f]/g, '')
                   .replace(/đ/g, 'd')
                   .replace(/[^a-z0-9]+/g, '-');
    }

    /* ==================================================================
       3. LỌC TẠI CHỖ — MỖI TIÊU CHÍ KHÔNG TẢI LẠI CẢ TRANG

       Mỗi tiêu chí lọc là một <a href> thật, nên bấm là điều hướng: trang
       trắng một nhịp, cuộn nhảy về đầu, và trên điện thoại thì bảng bộ lọc
       (một <details>) đóng sập lại — muốn tick tiêu chí thứ hai phải mở nó ra
       từ đầu. Chọn năm tiêu chí là năm lần như thế.

       Ở đây chặn cú bấm, nạp ngầm URL ĐÓ (không bịa URL mới — chính cái href
       máy chủ đã dựng), rồi thay ruột hai khối: cột lọc và lưới kết quả.

       BA THỨ KHÔNG ĐƯỢC MẤT KHI LÀM VIỆC NÀY
         · Tắt JS: liên kết còn nguyên, không có gì để hỏng.
         · Nút Lùi của trình duyệt: pushState cho mỗi bước, popstate nạp lại.
         · Số đếm bên cạnh mỗi tiêu chí: chúng đổi theo tiêu chí đang bật, nên
           cột lọc phải được thay MỚI chứ không chỉ tô lại cái đang có. Đó
           cũng là lý do không gom nhiều lựa chọn rồi mới gọi máy chủ một lần:
           trong lúc gom, mọi con số trên màn hình đều sai.

       VÌ SAO XIN "MẢNH" CHỨ KHÔNG NẠP CẢ TRANG RỒI TỰ BÓC: đo trên chính
       trang này với 18 sản phẩm — cả trang 80.246 byte, hai mảnh cần dùng
       38.917 byte, đúng 48%. Header X-Catalog: 1 bảo máy chủ in đúng phần
       view (xem khối chú thích thứ hai ở đầu _layout/master.php).
       ================================================================== */

    var catbody = document.querySelector('.catbody');

    /* Thiếu bất kỳ mảnh API nào thì KHÔNG bật tính năng — và trang vẫn chạy
       y như trước bằng đường điều hướng thật. Không có polyfill, không có
       nhánh mã thứ hai phải nuôi. */
    var coTaiCho = !!(catbody && window.fetch && window.DOMParser &&
                      window.history && window.history.pushState && window.URL);

    var luot     = 0;     // số thứ tự lượt nạp — để bỏ qua câu trả lời đến muộn
    var dangChay = null;  // AbortController của lượt đang chạy
    var loa      = null;  // vùng thông báo cho trình đọc màn hình
    var viTri    = null;  // chỗ đứng của tiêu điểm trước khi thay mảnh

    if (coTaiCho) {
        khoiDongTaiCho();
    }

    function khoiDongTaiCho() {
        /* Trạng thái cho URL hiện tại: thiếu dòng này thì lần bấm Lùi đầu tiên
           trả về một entry không có state và trang đứng im. */
        window.history.replaceState({ cat: 1 }, '', window.location.href);

        /* Vùng thông báo nằm NGOÀI hai khối bị thay. Đặt bên trong thì mỗi lần
           thay là nó bị dựng lại, mà một vùng aria-live vừa mới sinh ra không
           đọc nội dung của chính nó — trình đọc màn hình chỉ đọc thay đổi xảy
           ra TRONG một vùng đã tồn tại từ trước. */
        loa = document.createElement('p');
        loa.className = 'sr-only';
        loa.setAttribute('aria-live', 'polite');
        catbody.appendChild(loa);

        catbody.addEventListener('click', function (event) {
            if (event.defaultPrevented) return;

            /* Để yên cho các lối mở-ở-tab-khác: chuột giữa, Ctrl/Cmd/Shift/Alt.
               Chặn hết thì người quen mở tiêu chí ra tab mới mất thói quen mà
               không hiểu vì sao. */
            if (event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            var el = event.target;

            if (!el || !el.closest) return;

            var a = el.closest('a[href]');

            if (!a || !catbody.contains(a) || !laDuongLoc(a)) return;
            if (a.target && a.target !== '_self') return;

            event.preventDefault();
            nhoViTri(a);
            napManh(a.href, true);
        });

        window.addEventListener('popstate', function () {
            /* Chỉ nhận việc khi vẫn còn ở trang danh sách. Người dùng lùi về
               một trang khác hẳn thì trình duyệt lo, không phải mình. */
            if (window.location.pathname !== '/san-pham') return;

            napManh(window.location.href, false);
        });
    }

    /**
     * Liên kết này có phải một bước lọc không?
     *
     * CHỈ ba chỗ: cột lọc, dải phân trang, và chữ "bỏ từ khoá" ở thanh trên.
     * KHÔNG đụng tới liên kết trong thẻ sản phẩm — bấm vào một chiếc kính là
     * đi xem chiếc kính đó, phải điều hướng thật.
     */
    function laDuongLoc(a) {
        if (a.origin !== window.location.origin) return false;
        if (a.pathname !== '/san-pham') return false;

        return !!(a.closest('.cfilter') ||
                  a.closest('.catpager') ||
                  a.classList.contains('catbar__drop'));
    }

    /** Đi tới một URL bằng đường mảnh. Trả false nếu không làm được. */
    function diToiTaiCho(url) {
        if (!coTaiCho || !url) return false;

        napManh(url, true);

        return true;
    }

    function napManh(url, dayLichSu) {
        var stt = ++luot;

        /* Bấm nhanh ba tiêu chí liền tay là ba lượt nạp chồng nhau. Huỷ lượt
           cũ cho đỡ tốn, NHƯNG vẫn phải so số thứ tự ở dưới: huỷ không phải
           lúc nào cũng kịp, và câu trả lời của lượt cũ về sau lượt mới sẽ vẽ
           đè lên kết quả đúng. */
        if (dangChay) dangChay.abort();
        dangChay = window.AbortController ? new AbortController() : null;

        var main = catbody.querySelector('.catmain');

        if (main) main.setAttribute('aria-busy', 'true');

        window.fetch(url, {
            headers: { 'X-Catalog': '1' },
            credentials: 'same-origin',
            signal: dangChay ? dangChay.signal : undefined
        })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);

                return res.text();
            })
            .then(function (html) {
                if (stt !== luot) return;   // lượt cũ về muộn, vứt

                if (!thayMang(html)) {
                    window.location.href = url;
                    return;
                }

                if (dayLichSu) {
                    window.history.pushState({ cat: 1 }, '', url);
                }
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;

                /* Mạng hỏng, máy chủ 500, HTML lạ — đi đường thật. Người dùng
                   vẫn được đúng bộ lọc họ vừa bấm, chỉ là mất một lần tải
                   trang. Nuốt lỗi rồi đứng im mới là thứ không tha thứ được:
                   họ bấm mà không có gì xảy ra. */
                window.location.href = url;
            });
    }

    /**
     * Thay ruột hai khối. Trả false nếu HTML nhận về không có đủ hai mảnh —
     * lúc đó người gọi rơi về điều hướng thật.
     */
    function thayMang(html) {
        var doc     = new DOMParser().parseFromString(html, 'text/html');
        var locMoi  = doc.querySelector('.cfilter');
        var mainMoi = doc.querySelector('.catmain');
        var locCu   = catbody.querySelector('.cfilter');
        var mainCu  = catbody.querySelector('.catmain');

        if (!locMoi || !mainMoi || !locCu || !mainCu) return false;

        /* THAY RUỘT (innerHTML), KHÔNG THAY CẢ PHẦN TỬ.
           .cfilter chính là cái <details> mà mục 4 đang nghe sự kiện, và
           thuộc tính `open` của nó là trạng thái "bảng lọc đang mở" trên điện
           thoại. Thay cả phần tử là mất cả hai: listener đi theo phần tử cũ,
           còn HTML mới từ máy chủ thì không mang `open` — đúng cái lỗi đóng
           sập mà mục này sinh ra để sửa. */
        var panel = locCu.querySelector('.cfilter__panel');
        var cuon  = panel ? panel.scrollTop : 0;

        locCu.innerHTML  = locMoi.innerHTML;
        mainCu.innerHTML = mainMoi.innerHTML;

        /* Trả lại chỗ cuộn TRONG bảng lọc: người dùng tick một tiêu chí ở gần
           cuối danh sách thì phải thấy nó vẫn ở trước mắt, không bị hất về
           đầu bảng. */
        panel = locCu.querySelector('.cfilter__panel');

        if (panel) panel.scrollTop = cuon;

        mainCu.removeAttribute('aria-busy');

        ganTienIch();
        traLaiTieuDiem();
        bao(mainCu);

        return true;
    }

    /**
     * Nhớ chỗ đứng của tiêu điểm theo VỊ TRÍ, không theo phần tử.
     *
     * Phần tử vừa bấm sẽ không còn tồn tại sau khi thay ruột, nên không giữ
     * tham chiếu tới nó được. Cũng không dùng href làm mốc: bấm xong thì href
     * của chính mục đó đổi từ "bật lên" thành "tắt đi".
     *
     * Còn lại là vị trí: nhóm tiêu chí thứ mấy, mục thứ mấy trong nhóm. Danh
     * sách tiêu chí không đổi thứ tự sau một cú lọc (chỉ đổi số đếm và trạng
     * thái bật/tắt), nên vị trí là mốc ổn định.
     */
    function nhoViTri(a) {
        viTri = null;

        var oLoc = a.closest('.cfilter');
        var nhom = a.closest('.pfacet');

        if (!oLoc || !nhom) return;

        var cacNhom = Array.prototype.slice.call(oLoc.querySelectorAll('.pfacet'));
        var cacMuc  = Array.prototype.slice.call(nhom.querySelectorAll('a'));

        viTri = { nhom: cacNhom.indexOf(nhom), muc: cacMuc.indexOf(a) };
    }

    function traLaiTieuDiem() {
        var nho = viTri;

        viTri = null;

        if (!nho || nho.nhom < 0 || nho.muc < 0) return;

        var oLoc = catbody.querySelector('.cfilter');
        var nhom = oLoc ? oLoc.querySelectorAll('.pfacet')[nho.nhom] : null;
        var muc  = nhom ? nhom.querySelectorAll('a')[nho.muc] : null;

        if (!muc) return;

        /* preventScroll: vừa trả lại scrollTop của bảng lọc ở trên xong, đặt
           tiêu điểm mà để trình duyệt tự cuộn tới thì công đó đổ sông. Trình
           duyệt cũ không hiểu tuỳ chọn này sẽ bỏ qua nó, không lỗi. */
        try {
            muc.focus({ preventScroll: true });
        } catch (e) {
            muc.focus();
        }
    }

    /** Đọc số kết quả mới cho người dùng trình đọc màn hình. */
    function bao(main) {
        if (!loa) return;

        var dem = main.querySelector('.catbar__count');

        loa.textContent = dem ? dem.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    /* ------------------------------------------------------------------
       4. Bottom-sheet bộ lọc (dưới 1101px)
       ------------------------------------------------------------------ */

    var sheet = document.querySelector('[data-filter-sheet]');

    if (!sheet) return;

    sheet.addEventListener('click', function (event) {
        if (event.target.closest('[data-sheet-close]')) {
            sheet.open = false;
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sheet.open && isSheetMode()) {
            sheet.open = false;
        }
    });

    /*
     * Khoá cuộn trang nền khi sheet mở.
     *
     * Không có nó, cuộn hết danh sách trong sheet là trang phía sau bắt đầu
     * trôi theo — người dùng đóng sheet ra thì thấy mình đang đứng ở một chỗ
     * khác hẳn trong lưới sản phẩm. (overscroll-behavior của panel chặn được
     * phần lớn, nhưng không chặn cú vuốt bắt đầu ngay trên nền mờ.)
     *
     * <details> không phát sự kiện riêng cho việc mở/đóng ở mọi trình duyệt,
     * nên nghe 'toggle' — sự kiện chuẩn của chính nó.
     */
    sheet.addEventListener('toggle', function () {
        document.body.style.overflow = (sheet.open && isSheetMode()) ? 'hidden' : '';
    });

    // Xoay ngang máy hoặc kéo rộng cửa sổ qua mốc 1101px: sheet biến thành cột
    // lọc thường, mà khoá cuộn thì vẫn còn — cả trang đứng im không hiểu vì sao.
    window.addEventListener('resize', function () {
        if (!isSheetMode()) document.body.style.overflow = '';
    });

    /** Bề rộng hiện tại có đang vẽ bộ lọc dưới dạng sheet không? */
    function isSheetMode() {
        return window.matchMedia('(max-width: 1100px)').matches;
    }
})();
