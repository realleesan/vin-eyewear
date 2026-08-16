/**
 * header.js — Thu gọn header khi cuộn + menu trượt mobile.
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
       1. THU GỌN HEADER KHI CUỘN
       ==================================================================== */

    if (header) {
        var scrolled = false;

        /*
         * Hai ngưỡng LỆCH NHAU: thu gọn khi đã cuộn quá 80px, và chỉ bung
         * trở lại khi về ĐÚNG đỉnh trang (y === 0).
         *
         * Vì sao phải là 0 chứ không phải một con số nhỏ như 12:
         *
         * Bung dải thông báo ra làm header CAO THÊM ~30px. Header nằm trong
         * luồng nên toàn bộ nội dung bên dưới bị đẩy xuống 30px, và cơ chế
         * neo cuộn (scroll anchoring) của trình duyệt bù lại bằng cách cộng
         * đúng 30px vào scrollY để nội dung đứng yên trước mắt người dùng.
         *
         * Với ngưỡng cũ 12/32, cú bù đó đưa scrollY từ 8 vọt lên 38 — vượt
         * ngưỡng 32 nên header lập tức thu gọn lại, scrollY lại tụt xuống,
         * rồi lại bung… Đo được 3 lần lật class trong MỘT lần cuộn lên, và
         * đó chính là cú giật người dùng nhìn thấy.
         *
         * Ở scrollY = 0 thì không còn gì phía trên để neo, nên phép bù không
         * xảy ra và vòng lặp bị cắt tại gốc.
         */
        function onScroll() {
            var y = window.scrollY;
            var next = scrolled ? y > 0 : y > 80;

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
       2. Ô TÌM KIẾM BUNG RA (cạnh icon tài khoản)
       ==================================================================== */

    var searchToggle = document.getElementById('headerSearchToggle');
    var searchPanel = document.getElementById('headerSearchPanel');

    if (searchToggle && searchPanel) {
        var searchInput = searchPanel.querySelector('.header-search__input');

        var openSearch = function () {
            searchPanel.hidden = false;
            searchToggle.setAttribute('aria-expanded', 'true');
            if (searchInput) searchInput.focus();
        };

        var closeSearch = function (returnFocus) {
            searchPanel.hidden = true;
            searchToggle.setAttribute('aria-expanded', 'false');
            if (returnFocus) searchToggle.focus();
        };

        searchToggle.addEventListener('click', function () {
            if (searchPanel.hidden) {
                openSearch();
            } else {
                closeSearch(false);
            }
        });

        // Bấm ra ngoài -> đóng. Bắt ở document nên phải loại trừ chính cụm
        // nút + panel, nếu không cú click mở nút cũng tự đóng ngay.
        document.addEventListener('click', function (e) {
            if (searchPanel.hidden) return;
            if (e.target.closest('.header-search')) return;
            closeSearch(false);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !searchPanel.hidden) closeSearch(true);
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
