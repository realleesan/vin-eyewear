/**
 * header.js — Bóng đổ của header khi cuộn, bảng xổ của cụm tác vụ (tìm kiếm ·
 * tài khoản · giỏ hàng) và menu trượt mobile.
 *
 * Port hành vi từ src/components/site-header.tsx.
 * Không phụ thuộc thư viện nào; chạy được với thuộc tính `defer`.
 */

(function () {
    'use strict';

    var header = document.getElementById('siteHeader');
    var toggle = document.getElementById('navToggle');
    var nav = document.getElementById('mobileNav');

    /* ====================================================================
       1. BÓNG ĐỔ CỦA HEADER KHI CUỘN

       Class này CHỈ bật bóng đổ, không đổi kích thước gì nữa — xem ghi chú
       ở .site-header trong components/header.css và ở dải thông báo trong
       _layout/header.php.

       Vì không còn gì đổi kích thước nên MỘT ngưỡng là đủ. Bản trước phải
       dùng hai ngưỡng lệch nhau (thu ở 80, bung ở đúng 0): lật class khi đó
       làm header cao thêm ~30px, cơ chế neo cuộn của trình duyệt cộng bù 30px
       vào scrollY, con số bù lại vượt ngưỡng và lật ngược lớp — đo được 3 lần
       lật trong MỘT lần cuộn lên. Nay lật class không đụng tới chiều cao
       trang, không có gì để neo bù, nên vòng lặp đó không còn cửa xảy ra.

       4px chứ không phải 0: chuột và trackpad hay để lại scrollY lẻ 1-2px ở
       sát đỉnh, bóng nhấp nháy theo thì khó chịu.
       ==================================================================== */

    /* ────────────────────────────────────────────────────────────────────
       1b. ĐÃ ĐI QUA HERO CHƯA — lớp .is-past-hero

       .is-scrolled ở trên trả lời "đã rời đỉnh trang chưa", và nó vẫn giữ
       nguyên nghĩa đó. Nhưng trang chủ cần một câu hỏi KHÁC: "thanh nav còn
       nằm trên video hay đã sang nội dung trắng?".

       Hai câu hỏi lệch nhau đúng một chiều cao hero (82dvh). Trước đây đầu
       trang lật sang trắng ngay ở 4px — nghĩa là mới nhích chuột một cái là
       chữ đen đã nằm trên video, còn 80% quãng cuộn hero thì đầu trang là
       một dải trắng đè lên ảnh chiến dịch. Nay chữ trắng ở lại đúng chừng nào
       còn video sau lưng, và chỉ đổi đen + kính mờ khi mép dưới hero đã trượt
       qua mép dưới thanh nav — đúng cách gentlemonster.com làm.

       Đo bằng getBoundingClientRect() mỗi khung hình chứ không cộng dồn một
       con số đo sẵn: hero cao theo dvh, và trên di động thanh địa chỉ thu vào
       bung ra là con số đó đổi. Đo lại thì không bao giờ lệch.

       8px trễ một chiều (bật ở đúng mép, tắt khi đã lùi quá 8px) để cú cuộn
       dừng đúng ngay mốc không làm lớp lật qua lật lại. Lật lớp KHÔNG đụng
       chiều cao trang nên không có vòng lặp neo-cuộn như ghi chú ở trên.
       ──────────────────────────────────────────────────────────────────── */

    var hero = document.querySelector('[data-video-hero]');

    if (header) {
        var scrolled = false;
        var quaHero = false;

        function onScroll() {
            var next = window.scrollY > 4;

            if (next !== scrolled) {
                scrolled = next;
                header.classList.toggle('is-scrolled', scrolled);
            }

            if (hero) {
                var mepDuoiThanh = header.offsetHeight;
                var mepDuoiHero = hero.getBoundingClientRect().bottom;
                var nextHero = quaHero
                    ? mepDuoiHero <= mepDuoiThanh + 8
                    : mepDuoiHero <= mepDuoiThanh;

                if (nextHero !== quaHero) {
                    quaHero = nextHero;
                    header.classList.toggle('is-past-hero', quaHero);
                }
            }
        }

        /*
         * Gom nhiều sự kiện scroll vào một lần vẽ khung hình.
         * Trình duyệt bắn scroll dày hơn nhịp vẽ rất nhiều; đọc scrollY và
         * đổi class ở mỗi lần bắn sẽ gây layout thrashing.
         */
        var ticking = false;
        window.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                onScroll();
                ticking = false;
            });
        }, { passive: true });

        /* Đổi bề ngang là hero đổi chiều cao (82dvh), nên mốc lật cũng đổi —
           mà không có sự kiện scroll nào bắn ra để tính lại. */
        window.addEventListener('resize', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(function () {
                onScroll();
                ticking = false;
            });
        }, { passive: true });

        // Chạy một lần lúc tải: người dùng có thể mở trang ở giữa chừng
        // (tải lại trang đã cuộn, hoặc mở link có #anchor).
        onScroll();
    }

    /* ====================================================================
       2. BẢNG XỔ CỦA CỤM TÁC VỤ (tìm kiếm · tài khoản · giỏ hàng)

       PHẦN CHÍNH NẰM Ở CSS, KHÔNG PHẢI Ở ĐÂY. Bảng bung ra khi rê chuột
       (:hover) và khi tiêu điểm bàn phím đi vào cụm (:focus-within) — cả hai
       đều là selector thuần CSS, xem khối .hpop trong components/header.css.
       Tắt JavaScript thì ba bảng vẫn mở được bằng chuột và bằng phím Tab.

       Đoạn dưới đây chỉ THÊM một thứ CSS không làm được: màn hình cảm ứng
       không có động tác "rê chuột". Nên nút KHÔNG phải liên kết (kính lúp)
       được gắn thêm cú bấm để bật/tắt lớp .is-open.

       Vòng lặp dưới vẫn quét [data-hpop] chung chứ không nhắm riêng nút tìm
       kiếm: cụm này từng có bốn thành viên (nút thứ tư là bảng đổi ngôn ngữ
       VI/EN, gỡ 2026-08-30) và có thể lại có thêm. Nhắm đích danh một nút là
       tự đặt bẫy cho lần thêm sau.

       Tài khoản và giỏ hàng không cần: chúng là <a> thật, chạm là đi thẳng
       tới /tai-khoan và /gio-hang — đúng hành vi cũ, không mất gì.
       ==================================================================== */

    var pops = Array.prototype.slice.call(document.querySelectorAll('[data-hpop]'));

    if (pops.length) {
        var closePop = function (pop) {
            /* CHỈ ĐÁNH DẤU CỤM THẬT SỰ ĐANG MỞ.

               .is-closed nghĩa là "vừa bị đóng chủ động", nên nó chỉ hợp lý
               với cụm đang mang .is-open. Gắn bừa cho mọi cụm thì hai cụm
               chỉ mở bằng rê chuột (tài khoản, giỏ hàng) sẽ mang một lớp cấm
               mở mà chẳng ai gỡ đúng lúc — người dùng bàn phím Tab tới đó
               không thấy bảng đâu nữa. */
            var dangMo = pop.classList.contains('is-open');

            pop.classList.remove('is-open');

            /* Lớp phủ tìm kiếm khoá cuộn nền và nâng đầu trang lên trên cụm nút
               nổi bằng một lớp trên <body> — gỡ ở đây, đúng chỗ mọi lối đóng
               (Esc · bấm ngoài · nút X · rê sang bảng khác) đều đi qua. */
            if (pop.classList.contains('hpop--search')) {
                document.body.classList.remove('is-search-open');

                /* Gỡ cùng chỗ, cùng điều kiện với is-search-open — khối chú
                   thích ngay trên đã nói vì sao đây là chỗ đúng: mọi lối đóng
                   đều đi qua closePop. Gắn ở nhánh mở nhưng gỡ ở một chỗ khác
                   là kiểu sai để lại thanh đầu trang kẹt màu nền sau khi bảng
                   đã biến mất. */
                if (header) header.classList.remove('is-overlay');
            }

            /* GỠ LỚP THÔI LÀ CHƯA ĐÓNG ĐƯỢC.

               Bảng hiện ra bởi BA điều kiện trong CSS: :hover, :focus-within,
               và .is-open (xem khối .hpop trong components/header.css). Bấm
               kính lúp thì handler bên dưới đưa tiêu điểm thẳng vào ô nhập —
               nên sau khi gỡ .is-open, ô nhập VẪN đang giữ tiêu điểm và
               :focus-within một mình đủ để bảng ở lại.

               Đúng cảnh người dùng gặp: bấm kính lúp, rê sang giỏ hàng, hai
               bảng cùng hiện đè lên nhau. Đã kiểm bằng getComputedStyle:
               .is-open đã mất mà visibility vẫn là 'visible'.

               Nên đóng là phải trả luôn tiêu điểm ra ngoài. Chỉ blur khi tiêu
               điểm đang NẰM TRONG cụm này: gọi blur() vô điều kiện là cướp
               tiêu điểm của thứ người dùng vừa chuyển sang. */
            if (pop.contains(document.activeElement)) {
                document.activeElement.blur();
            }

            /* GẮN DẤU SAU KHI BLUR, KHÔNG PHẢI TRƯỚC — thứ tự này là bắt buộc.

               blur() bắn `focusout` NGAY LẬP TỨC, đồng bộ. Mà handler focusout
               bên dưới lại có việc gỡ .is-closed khi tiêu điểm rời khỏi cụm
               (relatedTarget của một cú blur là null, tức là "đi ra ngoài").
               Gắn dấu trước thì chính cú blur ở trên xoá nó đi, rồi Esc trả
               tiêu điểm về nút và :focus-within mở bảng lại — đo được: Esc
               không đóng được gì. */
            if (dangMo) {
                pop.classList.add('is-closed');
            }

            var btn = pop.querySelector('[data-hpop-trigger]');
            if (btn && btn.hasAttribute('aria-expanded')) {
                btn.setAttribute('aria-expanded', 'false');
            }
        };

        var closeAllPops = function (except) {
            pops.forEach(function (pop) {
                if (pop !== except) closePop(pop);
            });
        };

        pops.forEach(function (pop) {
            var trigger = pop.querySelector('[data-hpop-trigger]');
            if (!trigger || trigger.tagName !== 'BUTTON') return;

            trigger.addEventListener('click', function () {
                var willOpen = !pop.classList.contains('is-open');

                // Mở cái này thì đóng ba cái kia — hai bảng chồng nhau thì
                // cái sau đè lên cái trước, không đọc được cái nào.
                closeAllPops(pop);

                if (willOpen) {
                    /* Gỡ dấu "vừa đóng" của chính cụm này: cú bấm là ý muốn
                       mở rõ ràng nhất, không có lý do gì bắt người ta rê ra
                       rồi rê vào mới mở lại được. */
                    pop.classList.remove('is-closed');
                    pop.classList.add('is-open');

                    if (pop.classList.contains('hpop--search')) {
                        document.body.classList.add('is-search-open');

                        /* THANH ĐẦU TRANG LIỀN MỘT MẢNG VỚI BẢNG.

                           oa.css có sẵn `.oa-header.is-overlay` — nền
                           --bg-panel, bỏ blur, chữ --ink — đúng cho lúc này,
                           nhưng cho tới nay KHÔNG mã nào gắn lớp ấy, nên luật
                           đó nằm chết trong file. Đây là chỗ gắn nó.

                           Thấy rõ nhất ở trang chủ: thanh đang trong suốt đè
                           lên ảnh hero, mở ô tìm ra thì phía dưới là một mảng
                           --bg-panel còn thanh vẫn là ảnh — hai khối rời nhau
                           ngay chỗ giáp. Gắn lớp này là chúng thành một.

                           Trang trong cũng có tác dụng, chỉ là nhẹ: nền đổi từ
                           --bg sang --bg-panel, mất đường ranh mờ giữa hai
                           khối.

                           `header &&`: khung rút gọn của trang thanh toán /
                           đăng nhập không có #siteHeader. Hôm nay ô tìm nằm
                           BÊN TRONG thanh nên không có thanh là cũng không có
                           ô tìm, tức nhánh này không với tới được — nhưng đó
                           là một bất biến của markup, không phải của file này.
                           Xem `if (header)` ở khối cuộn đầu file, cùng lối. */
                        if (header) header.classList.add('is-overlay');
                    }
                } else {
                    /* ĐÓNG PHẢI ĐI QUA closePop, không phải chỉ gỡ .is-open.

                       Con trỏ lúc này vẫn nằm trên chính cái nút vừa bấm, nên
                       :hover một mình đủ giữ bảng ở lại — đó là lý do cú bấm
                       thứ hai trước đây không đóng được gì. closePop gắn
                       .is-closed để đè lên :hover.

                       Rồi trả tiêu điểm về nút: closePop vừa blur thứ đang
                       giữ tiêu điểm bên trong, mà bỏ tiêu điểm rơi ra <body>
                       thì người dùng bàn phím mất chỗ đứng, lần Tab sau phải
                       đi lại từ đầu trang. Chuột không thiệt gì: vòng focus
                       chỉ hiện với :focus-visible. */
                    closePop(pop);
                    trigger.focus();
                }

                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

                /* Mở ô tìm kiếm bằng CÚ BẤM thì đưa luôn tiêu điểm vào ô nhập —
                   người ta bấm kính lúp là để gõ. Cố ý KHÔNG làm việc này khi
                   bảng bung ra do rê chuột: cướp tiêu điểm chỉ vì con trỏ lướt
                   qua là hành vi rất khó chịu, nhất là khi đang gõ ở chỗ khác. */
                if (willOpen) {
                    var field = pop.querySelector('.header-search__input');

                    /* HAI khung hình, không phải một — và đây là chỗ đã sai.

                       Ngay trong handler này bảng vẫn còn visibility:hidden
                       (kiểu chưa được tính lại), mà trình duyệt từ chối đặt
                       tiêu điểm vào phần tử ẩn. Nên phải hoãn — đúng.

                       Nhưng MỘT requestAnimationFrame là chưa đủ: theo thứ tự
                       "cập nhật hiển thị" của trình duyệt, các callback rAF
                       chạy TRƯỚC bước tính lại kiểu của khung hình đó. Tới lúc
                       callback chạy, .is-open đã gắn nhưng kiểu vẫn là kiểu cũ
                       — ô nhập vẫn đang visibility:hidden và focus() rơi vào
                       khoảng không.

                       Đo bằng Playwright: bấm kính lúp rồi đợi 500ms, tiêu điểm
                       vẫn nằm trên chính cái nút vừa bấm, không phải ô nhập.
                       Lồng thêm một khung hình nữa thì tiêu điểm vào đúng
                       .header-search__input.

                       Nghĩa là cả tính năng "bấm kính lúp là gõ được luôn" —
                       thứ mà khối chú thích ngay trên mô tả — chưa từng chạy. */
                    if (field) {
                        window.requestAnimationFrame(function () {
                            window.requestAnimationFrame(function () { field.focus(); });
                        });
                    }
                }
            });
        });

        /* ----------------------------------------------------------------
           RÊ CHUỘT SANG BẢNG KHÁC -> ĐÓNG BẢNG ĐANG BẤM MỞ

           Đây là chỗ hai cơ chế mở bảng giẫm lên nhau. Bảng bung ra khi rê
           chuột là việc của CSS (:hover), còn bảng mở bằng cú bấm là lớp
           .is-open do đoạn trên gắn. CSS không biết gì về .is-open, nên:

             bấm kính lúp (ô tìm kiếm mở, .is-open)
             -> rê sang giỏ hàng (bảng giỏ hàng bung ra do :hover)
             -> HAI bảng cùng hiện, chồng lên nhau, không đọc được cái nào.

           Cú bấm đã lo trường hợp bấm-rồi-bấm (closeAllPops trong handler
           click). Thiếu đúng nhánh rê-chuột này.

           Đóng ở mouseenter chứ không phải mouseover: mouseover bắn lại mỗi
           lần con trỏ đi qua một phần tử con bên trong cụm, tức là hàng chục
           lần cho một lần rê tay.

           DANH SÁCH "ĐỐI THỦ" GỒM CẢ HAI MEGA MENU, không chỉ ba nút bên
           phải: bảng "Sản phẩm" và "Bộ sưu tập" trải hết bề ngang, mà
           .hpop__panel mang z-index 60 còn .mega__panel chỉ có 1 — nên ô tìm
           kiếm đang mở sẽ nằm đè lên chúng, đúng cùng một lỗi.

           KHÔNG đóng khi rê vào chỗ trống của header (wordmark, khoảng hở):
           người vừa bấm kính lúp là để GÕ, hất bảng đi chỉ vì con trỏ lướt
           qua chỗ không liên quan là cướp mất thao tác đang dở. Chỉ những thứ
           tự mở ra một bảng cạnh tranh mới đóng nó.

           focusin để người dùng bàn phím không rơi vào đúng cảnh ấy: Tab từ ô
           tìm kiếm sang nút tài khoản thì :focus-within mở bảng tài khoản, và
           nếu không có nhánh này thì lại hai bảng cùng hiện.
           ---------------------------------------------------------------- */
        var doiThu = pops.concat(
            Array.prototype.slice.call(document.querySelectorAll('.mega'))
        );

        doiThu.forEach(function (el) {
            var nhuong = function () { closeAllPops(el); };

            el.addEventListener('mouseenter', nhuong);
            el.addEventListener('focusin', nhuong);
        });

        /* GỠ DẤU "VỪA ĐÓNG" KHI NGƯỜI DÙNG QUAY LẠI.

           .is-closed mà không có chỗ gỡ thì cụm đó chết hẳn: rê vào bao nhiêu
           lần cũng không mở nữa. Hai lối quay lại, đúng hai lối đã mở nó ra
           lúc đầu:

             · mouseenter — con trỏ rời đi rồi vào lại. Đủ để tách khỏi cú bấm
               đóng, vì lúc bấm con trỏ ĐANG ở trong cụm nên mouseenter không
               bắn lại cho tới khi ra hẳn ngoài.
             · focusout ra khỏi cụm — cho người dùng bàn phím. Sau Esc tiêu
               điểm nằm trên nút, tức là vẫn trong cụm, nên bảng đúng là phải
               đóng; Tab đi chỗ khác rồi Tab về thì mới mở lại.

           relatedTarget là nơi tiêu điểm ĐANG ĐẾN. Không kiểm nó thì mỗi lần
           tiêu điểm nhảy giữa hai phần tử BÊN TRONG cụm cũng tính là rời đi,
           và dấu vừa-đóng bị gỡ oan. */
        pops.forEach(function (pop) {
            pop.addEventListener('mouseenter', function () {
                pop.classList.remove('is-closed');
            });

            pop.addEventListener('focusout', function (e) {
                if (!pop.contains(e.relatedTarget)) {
                    pop.classList.remove('is-closed');
                }
            });
        });

        /* Bấm ra ngoài -> đóng hết. Bắt ở document nên phải loại trừ chính cụm
           vừa bấm, nếu không cú click MỞ cũng chạy tiếp xuống đây và đóng ngay.

           instanceof Element: e.target của một click do script phát ra có thể
           là chính document, mà document không có closest() — thiếu chốt này
           thì handler ném lỗi và bảng kẹt mở. */
        document.addEventListener('click', function (e) {
            var inside = e.target instanceof Element ? e.target.closest('[data-hpop]') : null;
            closeAllPops(inside);
        });

        /* NÚT X VÀ NỀN MỜ — [data-hpop-close] bên trong một cụm. Uỷ quyền từ
           document chứ không gắn thẳng: phần tử này nằm trong bảng, mà bảng thì
           có thể bị thay ruột (giỏ hàng qua buy-flow.js). Đóng xong trả tiêu
           điểm về nút mở, cùng lý do với Esc bên dưới. */
        document.addEventListener('click', function (e) {
            var nut = e.target instanceof Element ? e.target.closest('[data-hpop-close]') : null;
            if (!nut) return;

            var pop = nut.closest('[data-hpop]');
            if (!pop) return;

            closePop(pop);

            var trigger = pop.querySelector('[data-hpop-trigger]');
            if (trigger) trigger.focus();
        });

        /* Esc đóng và trả tiêu điểm về nút — người dùng bàn phím cần đường lui,
           nếu không họ mắc kẹt phải Tab hết các mục trong bảng mới ra được. */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            pops.forEach(function (pop) {
                if (!pop.classList.contains('is-open')) return;

                var trigger = pop.querySelector('[data-hpop-trigger]');
                closePop(pop);
                if (trigger) trigger.focus();
            });
        });
    }

    /* ====================================================================
       2a. GIỎ HÀNG LÀ NGĂN KÉO BÊN PHẢI — CHẠY BẰNG BOOTSTRAP OFFCANVAS

       ĐỢT 09/09/2026: khối này TRƯỚC ĐÂY tự viết ~120 dòng (mở/đóng, nền mờ,
       khoá cuộn, bẫy tiêu điểm, Esc, trả tiêu điểm). Nay Bootstrap lo toàn bộ
       phần HÀNH VI đó; Vin giữ nguyên phần HÌNH ẢNH và CHUYỂN ĐỘNG trong
       components/header.css.

       VÌ SAO ĐỔI — ba thứ bản tự viết làm sai hoặc không làm:
         · KHOÁ CUỘN KHÔNG BÙ THANH CUỘN. `overflow:hidden` trên <body> làm
           thanh cuộn Windows (~15px) biến mất, và cả trang GIẬT SANG PHẢI đúng
           lúc ngăn kéo trượt vào. Bootstrap đo bề rộng thanh cuộn rồi bù bằng
           padding-right — hết giật.
         · BẪY TIÊU ĐIỂM tự viết chỉ bắt phím Tab. Bootstrap còn chặn cả
           `focusin` từ ngoài (kéo thả, trình đọc màn hình nhảy vùng).
         · aria-modal / inert / trả tiêu điểm về đúng phần tử mở — Bootstrap
           làm sẵn và đã qua kiểm chứng rộng hơn bất cứ thứ gì viết ở đây.

       KHÔNG NẠP BOOTSTRAP CSS. Chỉ nạp `bootstrap.bundle.min.js` (79 KB, tự
       host trong assets/vendor). Bộ 227 KB CSS kia sẽ đè lên gm.css và phá cả
       hệ token — xem chú thích ở _layout/master.php.

       HỢP ĐỒNG GIỮ NGUYÊN TUYỆT ĐỐI: [data-cart], [data-hpop-trigger] và
       .hpop__panel vẫn đúng tên, đúng cách lồng, đúng phần tử — buy-flow.js
       thay ruột hai thứ sau mỗi lần thêm hàng và không biết gì về đợt này.

       TẮT JAVASCRIPT: thẻ mở vẫn là <a href="/gio-hang"> thật; không có
       Bootstrap thì bấm vào là sang thẳng trang giỏ hàng.

       CÒN LẠI Ở ĐÂY đúng hai việc mà Bootstrap không lo:
         1. đồng bộ aria-expanded trên thẻ mở (Offcanvas không đụng tới nó);
         2. giữ nút X hoạt động sau khi buy-flow.js thay ruột bảng — nút mới
            không có instance nào gắn vào, nên uỷ quyền từ [data-cart].
       ==================================================================== */

    var cart = document.querySelector('[data-hpop][data-cart]');

    if (cart && window.bootstrap && window.bootstrap.Offcanvas) {
        var cartTrigger = cart.querySelector('[data-hpop-trigger]');
        var cartPanel   = cart.querySelector('.hpop__panel');

        if (cartPanel) {
            /* `backdrop: true` -> Bootstrap tự dựng .offcanvas-backdrop và gắn
               vào <body>; `scroll: false` -> khoá cuộn nền CÓ BÙ thanh cuộn. */
            var cartOc = window.bootstrap.Offcanvas.getOrCreateInstance(cartPanel, {
                backdrop: true,
                scroll: false,
                keyboard: true
            });

            if (cartTrigger) {
                cartTrigger.setAttribute('aria-haspopup', 'dialog');
                cartTrigger.setAttribute('aria-expanded', 'false');

                cartTrigger.addEventListener('click', function (e) {
                    /* Ctrl/Cmd/giữa chuột = ý muốn MỞ TAB MỚI tới /gio-hang —
                       để trình duyệt làm việc của nó. */
                    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

                    e.preventDefault();
                    cartOc.toggle();
                });
            }

            /* Nút X nằm TRONG .hpop__panel, mà buy-flow.js thay ruột bảng ấy
               sau mỗi lần thêm hàng — nút gắn sự kiện trực tiếp sẽ chết ngay
               sau đó. Uỷ quyền từ thẻ bọc [data-cart] thì nút mới nào cũng
               chạy. (Không dùng data-bs-dismiss vì lý do y hệt: Bootstrap đọc
               thuộc tính ấy qua data-API ở document nên vẫn sống — nhưng gọi
               thẳng instance thì không phụ thuộc vào việc nút có nằm trong
               .offcanvas hay không.) */
            cart.addEventListener('click', function (e) {
                if (e.target instanceof Element && e.target.closest('[data-cart-close]')) {
                    e.preventDefault();
                    cartOc.hide();
                }
            });

            /* ┌─ BA THỨ OFFCANVAS KHÔNG LO, PHẢI TỰ NỐI ────────────────────────
               │
               │ 1. LỚP TRÊN <body>. Offcanvas KHÔNG gắn `modal-open` — lớp đó
               │    là của Modal. Không có lớp nào thì luật nâng z-index của đầu
               │    trang không bao giờ chạy, và hậu quả đo được rất cụ thể: tấm
               │    giỏ nằm trong .site-header (z 60) nên NỀN MỜ (z 105) phủ ĐÈ
               │    LÊN CHÍNH NÓ và nuốt cú bấm nút X. Gắn lớp riêng của Vin.
               │
               │ 2. aria-expanded của thẻ mở — Offcanvas không đụng tới.
               │
               │ 3. TRẢ TIÊU ĐIỂM. Bootstrap chỉ tự trả về thẻ mở khi ngăn kéo
               │    được mở QUA data-API (data-bs-toggle). Ở đây ta gọi thẳng
               │    instance (để còn chặn Ctrl/Cmd+bấm cho lối mở tab mới), nên
               │    Bootstrap không có tham chiếu nào để trả về — tiêu điểm rơi
               │    ra <body> và người dùng bàn phím phải Tab lại từ đầu trang.
               └──────────────────────────────────────────────────────────────── */
            cartPanel.addEventListener('shown.bs.offcanvas', function () {
                document.body.classList.add('is-cart-open');
                if (cartTrigger) cartTrigger.setAttribute('aria-expanded', 'true');

                /* ĐƯA TIÊU ĐIỂM VÀO NÚT ĐÓNG — và đây không phải chuyện trợ
                   năng suông, nó là thứ làm phím Esc chạy được.

                   Đo được: sau khi mở, document.activeElement vẫn là chính thẻ
                   <a> mở ngăn kéo. Mà Bootstrap nghe phím Esc TRÊN CHÍNH PHẦN
                   TỬ offcanvas — tiêu điểm còn ở ngoài thì sự kiện keydown
                   không bao giờ nổi bọt tới đó, và Esc không đóng được gì.

                   Nhắm `button[data-cart-close]` chứ không `[data-cart-close]`
                   trần: cùng cái bẫy đã gặp ở bản tự viết — nền mờ (nay do
                   Bootstrap dựng) không còn mang thuộc tính ấy, nhưng giữ tên
                   chọn chặt vẫn đúng hơn. Nút đóng cũng là thứ người dùng bàn
                   phím cần chạm tới đầu tiên. */
                var close = cartPanel.querySelector('button[data-cart-close]');

                if (close) {
                    /* HOÃN HAI KHUNG HÌNH. Bootstrap gắn lớp `.show` rồi bắn
                       `shown` NGAY trong cùng một khối đồng bộ — kiểu chưa được
                       tính lại, nên tấm vẫn đang là `visibility: hidden` theo
                       luật trạng thái đóng, và trình duyệt TỪ CHỐI đặt tiêu
                       điểm vào phần tử ẩn.

                       Đo được: không có lấy một sự kiện `focusin` nào sau khi
                       gọi close.focus() — nó rơi vào khoảng không, và Esc vì
                       thế cũng chết theo (xem khối trên).

                       Một rAF là chưa đủ: callback rAF chạy TRƯỚC bước tính lại
                       kiểu của khung hình đó. Đây đúng là cái bẫy đã làm ô tìm
                       kiếm không nhận tiêu điểm — xem khối 2. */
                    window.requestAnimationFrame(function () {
                        window.requestAnimationFrame(function () { close.focus(); });
                    });
                }
            });

            /* ESC BẮT Ở CẤP DOCUMENT, KHÔNG CHỈ DỰA VÀO BOOTSTRAP.

               Bootstrap nghe Esc trên chính phần tử offcanvas, nên nó chỉ chạy
               khi tiêu điểm đã nằm trong tấm. Mà tiêu điểm chỉ vào tấm SAU khi
               nó trượt xong (xem khối `shown` bên dưới) — đo được: bấm Esc ở
               mili-giây thứ 120, trong lúc tấm đang trượt vào, thì không đóng
               được gì.

               Một khe 360ms nghe thì nhỏ, nhưng "bấm nhầm rồi Esc ngay" đúng là
               lúc người ta bấm Esc nhanh nhất. Bắt ở document thì phím ăn ngay
               từ khung hình đầu tiên. */
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (!cartPanel.classList.contains('show') &&
                    !cartPanel.classList.contains('showing')) return;

                cartOc.hide();
            });

            cartPanel.addEventListener('hidden.bs.offcanvas', function () {
                document.body.classList.remove('is-cart-open');
                if (cartTrigger) {
                    cartTrigger.setAttribute('aria-expanded', 'false');
                    cartTrigger.focus();
                }
            });
        }
    }

    /* ====================================================================
       2b. BẢNG XỔ ĐIỀU HƯỚNG (.mega) — ESC · BẤM RA NGOÀI · ARIA

       BẢNG NÀY MỞ BẰNG CSS, KHÔNG BẰNG JS. `:hover` và `:focus-within` trong
       components/mega-menu.css làm toàn bộ việc mở/đóng và cả chuyển động —
       khối này KHÔNG đụng vào đó.

       Nó chỉ thêm ba thứ mà CSS thuần không làm được:

         1. Esc đóng bảng. Không có selector nào huỷ được một trạng thái hover
            đang diễn ra, nên phải có một lớp đè: .is-dismissed.
         2. Bấm ra ngoài đóng bảng đang mở bằng tiêu điểm bàn phím.
         3. aria-expanded phản ánh đúng trạng thái, cho trình đọc màn hình.

       .is-dismissed được GỠ khi con trỏ rời hẳn cụm hoặc khi tiêu điểm quay
       lại — nếu không, một lần Esc sẽ khoá bảng vĩnh viễn cho tới khi tải lại
       trang. Đây đúng là nếp mà .hpop.is-closed ở khối trên đang dùng.

       Không có .mega nào (khung rút gọn của trang thanh toán / đăng nhập) thì
       vòng lặp chạy 0 lần và khối này im lặng.
       ==================================================================== */

    var megas = Array.prototype.slice.call(document.querySelectorAll('.mega'));

    if (megas.length) {
        /* ────────────────────────────────────────────────────────────────
           .is-mega-live — "hàng nav đang có một bảng mở"

           CSS thuần không trả lời được câu hỏi "vừa nãy có bảng nào mở
           không", mà đó đúng là thứ quyết định cú mở tiếp theo nên chờ ý
           định hay không:

             chưa có bảng nào mở  → chờ 140ms (--mega-intent), tránh mở nhầm
                                    khi con trỏ chỉ đi ngang qua
             đã có bảng đang mở   → đổi sang bảng bên cạnh TỨC THÌ, và chạy
                                    vũ đạo rút gọn (xem motion-choreo.css §4)

           260ms ân hạn sau khi rời hẳn: đủ để con trỏ băng qua khe giữa hai
           mục mà menu không "chết" giữa chừng, và đủ ngắn để quay lại sau
           một quãng nghỉ thật thì lại phải có ý định.

           Không đụng vào việc MỞ/ĐÓNG — chỗ đó vẫn là :hover/:focus-within
           của CSS, đúng như khối chú thích ở trên đã cam kết. Lớp này chỉ
           nói cho CSS biết BỐI CẢNH.
           ──────────────────────────────────────────────────────────────── */
        var navList = megas[0].closest('.header-nav__list');
        var liveTimer = null;

        /* Ân hạn ĐỌC TỪ TOKEN, không chép lại. --mo-intent-grace khai bằng ms
           trong gm.css nên parseFloat ra đúng con số; dự phòng 260 chỉ dùng khi
           token biến mất (bảng token chưa nạp, hoặc ai đó xoá nhầm). */
        var graceMs = parseFloat(
            window.getComputedStyle(document.documentElement)
                .getPropertyValue('--mo-intent-grace')
        ) || 260;

        var moLive = function () {
            if (!navList) return;
            window.clearTimeout(liveTimer);
            navList.classList.add('is-mega-live');
        };

        var hetLive = function (ngay) {
            if (!navList) return;
            window.clearTimeout(liveTimer);

            if (ngay) {
                navList.classList.remove('is-mega-live');
                return;
            }

            liveTimer = window.setTimeout(function () {
                navList.classList.remove('is-mega-live');
            }, graceMs);
        };

        megas.forEach(function (mega, i) {
            var trigger = mega.querySelector('.mega__trigger');
            var panel = mega.querySelector('.mega__panel');

            if (!trigger || !panel) return;

            /* aria-controls cần một id. Bảng "Sản phẩm" đã có sẵn trong markup;
               những bảng khác (Bộ sưu tập) thì đặt ở đây để không phải sửa view
               chỉ vì một thuộc tính kỹ thuật. */
            if (!panel.id) {
                panel.id = 'megaPanel' + i;
            }

            trigger.setAttribute('aria-haspopup', 'true');
            trigger.setAttribute('aria-controls', panel.id);
            trigger.setAttribute('aria-expanded', 'false');

            var danhDau = function (mo) {
                trigger.setAttribute('aria-expanded', mo ? 'true' : 'false');
            };

            /* ────────────────────────────────────────────────────────────────
               --mega-x : KHOẢNG CÁCH TỪ MÉP TRÁI MÀN HÌNH TỚI MỤC NAV NÀY

               Bảng xổ tràn hết bề ngang (nền phải phủ kín, nếu không nội dung
               trang lộ ra sau chữ). Nhưng CHỮ thì phải nằm thẳng cột với đúng
               mục vừa rê vào — nếu không, rê COLLECTIONS mà danh sách hiện
               dưới EYEWEAR.

               CSS thuần không trả lời được "mục này cách mép trái bao nhiêu":
               con số ấy đổi theo bề ngang cửa sổ, theo độ dài nhãn của các mục
               đứng trước, và theo cả ngôn ngữ đang chọn. getBoundingClientRect
               là cách duy nhất biết chính xác.

               Đo LÚC SẮP MỞ chứ không đo sẵn một lần: hàng nav dùng lưới ba
               cột căn giữa wordmark, nên vị trí mọi mục dịch mỗi khi cửa sổ
               đổi bề ngang. Đo tại chỗ thì không cần nghe sự kiện resize và
               không bao giờ lệch.

               Ghi lên chính .mega (không phải lên panel): --mega-x thừa kế
               xuống, mà panel thì có thể bị buy-flow.js thay ruột.
               ──────────────────────────────────────────────────────────────── */
            var doViTri = function () {
                mega.style.setProperty(
                    '--mega-x',
                    Math.round(trigger.getBoundingClientRect().left) + 'px'
                );
            };

            /* Con trỏ vào: bỏ dấu "vừa đóng" — cú rê chuột mới là ý muốn mới,
               nó phải thắng lần Esc trước đó. */
            mega.addEventListener('mouseenter', function () {
                mega.classList.remove('is-dismissed');
                doViTri();
                danhDau(true);
                moLive();
            });

            /* hetLive() KHÔNG gỡ lớp ngay mà hẹn sau graceMs. Nhờ vậy khi con
               trỏ đi từ mục này sang mục kế bên, thứ tự sự kiện là
               mouseleave(A) → mouseenter(B), và cú enter của B huỷ hẹn giờ
               trước khi nó kịp chạy — menu không chết ở khe giữa hai mục. */
            mega.addEventListener('mouseleave', function () {
                mega.classList.remove('is-dismissed');
                danhDau(false);
                hetLive();
            });

            mega.addEventListener('focusin', function () {
                mega.classList.remove('is-dismissed');
                doViTri();
                danhDau(true);
                moLive();
            });

            /* relatedTarget là phần tử SẮP nhận tiêu điểm. Còn nằm trong cụm
               thì người dùng chỉ đang Tab giữa các liên kết của chính bảng —
               chưa đóng. */
            mega.addEventListener('focusout', function (e) {
                if (!mega.contains(e.relatedTarget)) {
                    danhDau(false);
                    hetLive();
                }
            });
        });

        var dongMega = function (mega) {
            if (mega.contains(document.activeElement)) {
                document.activeElement.blur();
            }

            mega.classList.add('is-dismissed');

            /* Đóng CHỦ ĐỘNG (Esc, bấm ra ngoài) thì cắt "đang sống" NGAY, không
               chờ hết ân hạn: người vừa nói rõ là họ xong với menu. Để lớp còn
               nằm đó thì cú rê chuột kế tiếp mở tức thì — đúng thứ họ vừa bảo
               đừng làm. */
            hetLive(true);

            var trigger = mega.querySelector('.mega__trigger');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');

            return trigger;
        };

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            megas.forEach(function (mega) {
                /* Chỉ đóng cụm ĐANG mở. `:hover` hỏi được bằng matches(); tiêu
                   điểm thì hỏi bằng contains() — hai đường mở, hai cách kiểm. */
                var dangMo = mega.matches(':hover') || mega.contains(document.activeElement);
                if (!dangMo) return;

                var trigger = dongMega(mega);

                /* Trả tiêu điểm về nút: người dùng bàn phím vừa ở trong bảng,
                   không trả thì tiêu điểm rơi về <body> và họ phải Tab lại từ
                   đầu trang. Chỉ trả khi họ THẬT SỰ đang dùng bàn phím — đóng
                   bằng Esc lúc đang rê chuột thì không cướp tiêu điểm. */
                if (trigger && mega.matches(':focus-within')) {
                    trigger.focus();
                }
            });
        });

        document.addEventListener('click', function (e) {
            var trong = e.target instanceof Element ? e.target.closest('.mega') : null;

            megas.forEach(function (mega) {
                if (mega === trong) return;
                if (!mega.contains(document.activeElement)) return;

                /* Chỉ đóng cụm đang giữ tiêu điểm. Cụm chỉ đang mở vì con trỏ
                   nằm trên nó thì không cần đụng tới: rời chuột là nó tự đóng,
                   mà gắn .is-dismissed lúc này lại làm bảng biến mất ngay dưới
                   con trỏ đang đọc dở. */
                dongMega(mega);
            });
        });
    }

    /* ====================================================================
       3. MENU TRƯỢT MOBILE
       ==================================================================== */

    if (!toggle || !nav) return;

    var panel = nav.querySelector('.mobile-nav__panel');
    var lastFocused = null;

    function openNav() {
        lastFocused = document.activeElement;

        nav.hidden = false;
        // Ép trình duyệt tính lại layout trước khi thêm class, nếu không
        // nó gộp hai thay đổi vào một khung hình và hiệu ứng trượt không chạy.
        void nav.offsetWidth;

        nav.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');

        // Khoá cuộn nền: thiếu dòng này, cuộn trong menu tới cuối sẽ
        // "lây" sang trang phía dưới.
        document.body.style.overflow = 'hidden';

        var firstLink = panel && panel.querySelector('a, button, input');
        if (firstLink) firstLink.focus();
    }

    function closeNav() {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';

        /* Đợi hiệu ứng trượt xong mới ẩn hẳn, nếu không panel biến mất đột ngột.
           ĐỌC THỜI LƯỢNG TỪ CHÍNH CSS, không gõ lại một con số ở đây.

           Trước: gõ cứng 280ms, trong khi .mobile-nav__panel chuyển bằng
           var(--dur-med) = 360ms. Đo bằng Playwright ở 390×844: `hidden` được
           gắn ở t=287ms trong lúc tấm mới trượt tới -827px trên quãng -844px —
           tức là bị cắt ở 98%, còn lại một cú giật 17px. Chú thích trong
           components/header.css thì vẫn ghi "GIỮ NGUYÊN 250ms", một con số
           không còn ở đâu trong file cả.

           Hỏi getComputedStyle thì đổi token là chỗ này tự theo, và
           prefers-reduced-motion (transition-duration bị ép về 0.01ms) cũng
           tự đúng — không phải chờ 280ms với một hiệu ứng không hề chạy. */
        var doi = 0;

        if (panel) {
            /* THỜI LƯỢNG + ĐỘ TRỄ, không riêng thời lượng. Vũ đạo đóng
               (motion-choreo.css) cho ruột đi trước rồi tấm mới trượt, tức là
               tấm có transition-delay 60ms; chỉ đọc duration thì `hidden`
               được gắn sớm 60ms và tấm bị cắt ở ~85% quãng. */
            var cs = window.getComputedStyle(panel);
            var tre = (cs.transitionDelay || '').split(',');
            (cs.transitionDuration || '').split(',').forEach(function (v, i) {
                var d = (parseFloat(v) || 0) * 1000 + (parseFloat(tre[i] || tre[0]) || 0) * 1000;
                doi = Math.max(doi, d);
            });
        }

        window.setTimeout(function () {
            if (!nav.classList.contains('is-open')) nav.hidden = true;
        }, doi + 20);

        // Trả tiêu điểm về nút vừa mở menu — người dùng bàn phím không bị
        // văng lên đầu trang.
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
    }

    toggle.addEventListener('click', function () {
        if (nav.classList.contains('is-open')) {
            closeNav();
        } else {
            openNav();
        }
    });

    // Nền mờ và nút X đều mang data-close-nav
    nav.querySelectorAll('[data-close-nav]').forEach(function (el) {
        el.addEventListener('click', closeNav);
    });

    // Bấm vào một liên kết -> đóng menu (điều hướng cùng trang vẫn cần đóng)
    nav.addEventListener('click', function (e) {
        if (e.target.closest('a[href]')) closeNav();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && nav.classList.contains('is-open')) {
            closeNav();
        }
    });

    /*
     * Giữ tiêu điểm trong menu khi đang mở (focus trap).
     * Không có đoạn này, nhấn Tab sẽ đi xuyên qua menu xuống các liên kết
     * của trang phía sau — người dùng bàn phím lạc mất khỏi hộp thoại mà
     * không biết mình đang ở đâu.
     */
    nav.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || !panel) return;

        var focusable = panel.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), summary, [tabindex]:not([tabindex="-1"])'
        );
        if (!focusable.length) return;

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    // Chuyển sang bề ngang desktop khi menu đang mở -> đóng lại, vì thanh
    // nav desktop đã hiện và menu trượt trở thành thừa.
    var desktop = window.matchMedia('(min-width: 1101px)');
    desktop.addEventListener('change', function (e) {
        if (e.matches && nav.classList.contains('is-open')) closeNav();
    });
})();
