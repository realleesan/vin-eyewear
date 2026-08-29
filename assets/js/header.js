/**
 * header.js — Bóng đổ của header khi cuộn, bảng xổ của cụm tác vụ (ngôn ngữ ·
 * tìm kiếm · tài khoản · giỏ hàng) và menu trượt mobile.
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

    if (header) {
        var scrolled = false;

        function onScroll() {
            var next = window.scrollY > 4;

            if (next !== scrolled) {
                scrolled = next;
                header.classList.toggle('is-scrolled', scrolled);
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

        // Chạy một lần lúc tải: người dùng có thể mở trang ở giữa chừng
        // (tải lại trang đã cuộn, hoặc mở link có #anchor).
        onScroll();
    }

    /* ====================================================================
       2. BẢNG XỔ CỦA CỤM TÁC VỤ (ngôn ngữ · tìm kiếm · tài khoản · giỏ hàng)

       PHẦN CHÍNH NẰM Ở CSS, KHÔNG PHẢI Ở ĐÂY. Bảng bung ra khi rê chuột
       (:hover) và khi tiêu điểm bàn phím đi vào cụm (:focus-within) — cả hai
       đều là selector thuần CSS, xem khối .hpop trong components/header.css.
       Tắt JavaScript thì bốn bảng vẫn mở được bằng chuột và bằng phím Tab.

       Đoạn dưới đây chỉ THÊM một thứ CSS không làm được: màn hình cảm ứng
       không có động tác "rê chuột". Nên hai nút KHÔNG phải liên kết (ngôn ngữ
       và kính lúp) được gắn thêm cú bấm để bật/tắt lớp .is-open.

       Tài khoản và giỏ hàng không cần: chúng là <a> thật, chạm là đi thẳng
       tới /tai-khoan và /gio-hang — đúng hành vi cũ, không mất gì.
       ==================================================================== */

    var pops = Array.prototype.slice.call(document.querySelectorAll('[data-hpop]'));

    if (pops.length) {
        var closePop = function (pop) {
            /* CHỈ ĐÁNH DẤU CỤM THẬT SỰ ĐANG MỞ.

               .is-closed nghĩa là "vừa bị đóng chủ động", nên nó chỉ hợp lý
               với cụm đang mang .is-open. Gắn bừa cho cả bốn cụm thì hai cụm
               chỉ mở bằng rê chuột (tài khoản, giỏ hàng) sẽ mang một lớp cấm
               mở mà chẳng ai gỡ đúng lúc — người dùng bàn phím Tab tới đó
               không thấy bảng đâu nữa. */
            var dangMo = pop.classList.contains('is-open');

            pop.classList.remove('is-open');

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

                    /* Hoãn một khung hình: ngay trong handler này bảng vẫn còn
                       visibility:hidden (kiểu chưa được tính lại), mà trình
                       duyệt từ chối đặt tiêu điểm vào phần tử đang ẩn — gọi
                       thẳng field.focus() ở đây là không có tác dụng gì. */
                    if (field) window.requestAnimationFrame(function () { field.focus(); });
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

        // Đợi hiệu ứng trượt xong mới ẩn hẳn, nếu không panel biến mất đột ngột
        window.setTimeout(function () {
            if (!nav.classList.contains('is-open')) nav.hidden = true;
        }, 280);

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
