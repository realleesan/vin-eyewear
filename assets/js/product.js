/**
 * assets/js/product.js
 * Filter sản phẩm theo danh mục + giá — Vin Eyewear
 * Hoạt động hoàn toàn client-side, không cần reload trang
 */

(function () {
    'use strict';

    /* ── DOM ──────────────────────────────────────────── */
    const grid         = document.getElementById('productGrid');
    const countEl      = document.getElementById('productCount');
    const emptyEl      = document.getElementById('productEmpty');
    const catChips     = document.querySelectorAll('[data-filter-cat]');
    const priceChips   = document.querySelectorAll('[data-filter-price]');

    if (!grid) return;

    const cards = Array.from(grid.querySelectorAll('.product-card'));

    /* ── State ────────────────────────────────────────── */
    let activeCat   = 'all';
    let activePrice = 'all';

    /* ── Helpers ──────────────────────────────────────── */

    // Kiểm tra card có khớp bộ lọc giá không
    function matchPrice(price, range) {
        const p = parseInt(price, 10);
        if (range === 'all')  return true;
        if (range === '0-1')  return p < 1_000_000;
        if (range === '1-1.5')return p >= 1_000_000 && p <= 1_500_000;
        if (range === '1.5+') return p > 1_500_000;
        return true;
    }

    /* ── Core: áp dụng filter ─────────────────────────── */
    function applyFilters() {
        let visible = 0;

        cards.forEach((card) => {
            const cat   = card.dataset.category;
            const price = card.dataset.price;

            const catOk   = activeCat === 'all'   || cat === activeCat;
            const priceOk = matchPrice(price, activePrice);

            if (catOk && priceOk) {
                card.classList.remove('is-hidden');
                visible++;
            } else {
                card.classList.add('is-hidden');
            }
        });

        // Cập nhật đếm
        if (countEl) countEl.textContent = visible;

        // Hiện/ẩn empty state
        if (emptyEl) emptyEl.hidden = visible > 0;
    }

    /* ── Cập nhật chip active ─────────────────────────── */
    function setActiveChip(chips, selected) {
        chips.forEach((chip) => {
            const isActive = chip === selected;
            chip.classList.toggle('is-active', isActive);
            chip.setAttribute('aria-pressed', String(isActive));
        });
    }

    /* ── Event: filter danh mục ───────────────────────── */
    catChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            activeCat = chip.dataset.filterCat;
            setActiveChip(catChips, chip);
            applyFilters();
        });
    });

    /* ── Event: filter giá ────────────────────────────── */
    priceChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            activePrice = chip.dataset.filterPrice;
            setActiveChip(priceChips, chip);
            applyFilters();
        });
    });

    /* ── Khởi chạy ────────────────────────────────────── */
    applyFilters();

})();