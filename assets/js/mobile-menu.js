/**
 * assets/js/mobile-menu.js
 * Xử lý mobile menu cho Vin Eyewear
 * - Toggle open/close khi bấm hamburger
 * - Đóng menu khi bấm ra ngoài
 * - Đóng menu khi chọn một link
 * - Hỗ trợ phím Enter/Space trên nút hamburger
 * - Tự đóng khi resize về desktop (≥ 900px)
 */

(function () {
    'use strict';

    const toggle = document.getElementById('navToggle');
    const menu   = document.getElementById('navMenu');

    if (!toggle || !menu) return; // Guard: không làm gì nếu DOM chưa sẵn sàng

    /* ── Helpers ─────────────────────────────────────── */

    function openMenu() {
        menu.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.classList.add('is-active');
    }

    function closeMenu() {
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.classList.remove('is-active');
    }

    function isOpen() {
        return menu.classList.contains('open');
    }

    /* ── Toggle khi bấm hamburger ───────────────────── */

    toggle.addEventListener('click', () => {
        isOpen() ? closeMenu() : openMenu();
    });

    /* ── Hỗ trợ bàn phím (Enter / Space) ───────────── */

    toggle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            isOpen() ? closeMenu() : openMenu();
        }
    });

    /* ── Đóng khi click ra ngoài menu ──────────────── */

    document.addEventListener('click', (e) => {
        if (isOpen() && !menu.contains(e.target) && !toggle.contains(e.target)) {
            closeMenu();
        }
    });

    /* ── Đóng khi chọn link trong menu ─────────────── */

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    /* ── Tự đóng khi resize về desktop ─────────────── */

    const DESKTOP_BREAKPOINT = 900;

    window.addEventListener('resize', () => {
        if (window.innerWidth >= DESKTOP_BREAKPOINT && isOpen()) {
            closeMenu();
        }
    });

})();