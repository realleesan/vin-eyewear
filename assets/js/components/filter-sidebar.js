/**
 * assets/js/components/filter-sidebar.js
 * Toggle slide-over cho Filter Sidebar ("Vỏ") — Vin Eyewear
 * - Mở/đóng sidebar trên mobile (≤900px), có overlay nền
 * - Đóng khi bấm nút đóng, bấm overlay hoặc phím Esc
 * - Tự đóng khi resize về desktop (≥900px)
 *
 * LƯU Ý: file này KHÔNG chứa logic lọc. Logic lọc sản phẩm nằm ở
 * assets/js/product.js (đọc chip [data-filter-cat] / [data-filter-price]).
 */

(function () {
    'use strict';

    const sidebar  = document.getElementById('filterSidebar');
    const toggle   = document.getElementById('filterToggle');
    const overlay  = document.getElementById('filterOverlay');
    const closeBtn = document.getElementById('filterClose');
    const resetBtn = document.getElementById('filterReset');

    if (!sidebar || !toggle || !overlay) return; // Guard: trang không có filter sidebar

    /* ── Helpers ─────────────────────────────────────── */

    function openSidebar() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden'; // khoa scroll nen khi slide-over mo
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function isOpen() {
        return sidebar.classList.contains('is-open');
    }

    /* ── Toggle khi bấm nút Bộ lọc ──────────────────── */

    toggle.addEventListener('click', () => {
        isOpen() ? closeSidebar() : openSidebar();
    });

    /* ── Đóng: nút đóng / overlay / Esc ─────────────── */

    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen()) {
            closeSidebar();
        }
    });

    /* ── Tự đóng khi resize về desktop ─────────────── */

    const DESKTOP_BREAKPOINT = 900; // khop voi mobile-menu.js va filter-sidebar.css

    window.addEventListener('resize', () => {
        if (window.innerWidth >= DESKTOP_BREAKPOINT && isOpen()) {
            closeSidebar();
        }
    });

    /* ── Reset Filters ─────────────────────────────── */

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            // Reset all filter chips to default state
            const allChips = sidebar.querySelectorAll('.filter-chip');
            allChips.forEach(chip => {
                chip.classList.remove('is-active');
                chip.setAttribute('aria-pressed', 'false');
            });

            // Set "all" chips as active for each filter group
            const allValueChips = sidebar.querySelectorAll('.filter-chip[data-filter-kind="all"], .filter-chip[data-filter-cat="all"], .filter-chip[data-filter-category="all"], .filter-chip[data-filter-event="all"], .filter-chip[data-filter-price="all"]');
            allValueChips.forEach(chip => {
                chip.classList.add('is-active');
                chip.setAttribute('aria-pressed', 'true');
            });

            // Trigger custom event for other scripts to handle
            const resetEvent = new CustomEvent('filterReset', { bubbles: true });
            sidebar.dispatchEvent(resetEvent);
        });
    }

})();
