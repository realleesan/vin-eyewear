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
            pop.classList.remove('is-open');

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
                pop.classList.toggle('is-open', willOpen);
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
