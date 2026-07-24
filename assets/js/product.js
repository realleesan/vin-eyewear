/**
 * assets/js/product.js
 * Filter + phân trang sản phẩm client-side — Vin Eyewear
 * - Filter theo kiểu sản phẩm + dáng gọng + khoảng giá (AND 3 điều kiện)
 * - Phân trang PAGE_SIZE sp/trang TRÊN KẾT QUẢ ĐÃ LỌC, đổi filter về trang 1
 * Hoạt động hoàn toàn client-side, không cần reload trang
 */

(function () {
    'use strict';

    /* ── Config ───────────────────────────────────────── */
    const PAGE_SIZE = 8; // 8 SP/trang = 2 hang x 4 cot; khop $perPage trong app/views/product/index.php

    /* ── DOM ──────────────────────────────────────────── */
    const grid       = document.getElementById('productGrid');
    const countEl    = document.getElementById('productCount');
    const emptyEl    = document.getElementById('productEmpty');
    const pagEl      = document.getElementById('productPagination');
    const kindChips  = document.querySelectorAll('[data-filter-kind]');
    const catChips   = document.querySelectorAll('[data-filter-cat]');
    const priceChips = document.querySelectorAll('[data-filter-price]');

    if (!grid) return;

    const cards = Array.from(grid.querySelectorAll('.product-card'));

    /* ── State ────────────────────────────────────────── */
    let activeKind  = 'all';
    let activeCat   = 'all';
    let activePrice = 'all';
    let currentPage = 1;

    /* ── Helpers ──────────────────────────────────────── */

    // Kiểm tra card có khớp bộ lọc giá không
    // (key range phải khớp $filter_price_ranges ở _layout/filter-sidebar.php)
    function matchPrice(price, range) {
        const p = parseInt(price, 10);
        if (range === 'all')   return true;
        if (range === '0-1')   return p < 1_000_000;
        if (range === '1-1.5') return p >= 1_000_000 && p <= 1_500_000;
        if (range === '1.5-2') return p > 1_500_000 && p <= 2_000_000;
        if (range === '2-3')   return p > 2_000_000 && p <= 3_000_000;
        if (range === '3+')    return p > 3_000_000;
        return true;
    }

    // Card có khớp cả 3 chiều filter đang chọn không
    function matchesCard(card) {
        const kindOk  = activeKind === 'all' || card.dataset.kind === activeKind;
        const catOk   = activeCat === 'all'  || card.dataset.category === activeCat;
        const priceOk = matchPrice(card.dataset.price, activePrice);
        return kindOk && catOk && priceOk;
    }

    /* ── Core: áp dụng filter + phân trang ────────────── */
    function applyFilters() {
        const matched = cards.filter(matchesCard);

        // Clamp trang hiện tại theo số trang thực tế của kết quả lọc
        const totalPages = Math.max(1, Math.ceil(matched.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;

        // Chỉ hiện các card thuộc trang hiện tại
        const start      = (currentPage - 1) * PAGE_SIZE;
        const visibleSet = new Set(matched.slice(start, start + PAGE_SIZE));
        cards.forEach((card) => {
            card.classList.toggle('is-hidden', !visibleSet.has(card));
        });

        // Đếm = TỔNG số khớp filter (không phải số card trên trang)
        if (countEl) countEl.textContent = matched.length;

        // Hiện/ẩn empty state
        if (emptyEl) emptyEl.hidden = matched.length > 0;

        renderPagination(totalPages);
    }

    /* ── Pagination ───────────────────────────────────── */

    // Vẽ lại dải nút trang theo kết quả lọc; <=1 trang thì giấu hẳn.
    // Prev/Next het trang -> span .is-disabled (mo di, khong bam duoc).
    function renderPagination(totalPages) {
        if (!pagEl) return;

        pagEl.hidden = totalPages <= 1;
        if (pagEl.hidden) {
            pagEl.innerHTML = '';
            return;
        }

        let html = '';

        // Nut Prev
        html += currentPage > 1
            ? '<a href="#" data-page="' + (currentPage - 1) + '">Prev</a>'
            : '<span class="is-disabled">Prev</span>';

        // Cac nut so trang
        for (let i = 1; i <= totalPages; i++) {
            html += i === currentPage
                ? '<span class="active" aria-current="page">' + i + '</span>'
                : '<a href="#" data-page="' + i + '">' + i + '</a>';
        }

        // Nut Next
        html += currentPage < totalPages
            ? '<a href="#" data-page="' + (currentPage + 1) + '">Next</a>'
            : '<span class="is-disabled">Next</span>';

        pagEl.innerHTML = html;
    }

    // Cuộn về đầu danh sách khi đổi trang (chừa 100px cho header sticky)
    function scrollToListingTop() {
        const y = grid.getBoundingClientRect().top + window.scrollY - 100;
        window.scrollTo({ top: y, behavior: 'smooth' });
    }

    // Delegation: 1 listener duy nhất cho cả dải pagination
    // (nội dung được innerHTML lại mỗi lần render nên không bind từng link)
    if (pagEl) {
        pagEl.addEventListener('click', (e) => {
            const link = e.target.closest('a[data-page]');
            if (!link) return;
            e.preventDefault();
            currentPage = parseInt(link.dataset.page, 10);
            applyFilters();
            scrollToListingTop();
        });
    }

    /* ── Cập nhật chip active ─────────────────────────── */
    function setActiveChip(chips, selected) {
        chips.forEach((chip) => {
            const isActive = chip === selected;
            chip.classList.toggle('is-active', isActive);
            chip.setAttribute('aria-pressed', String(isActive));
        });
    }

    /* ── Event: filter kiểu sản phẩm ──────────────────── */
    kindChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            activeKind  = chip.dataset.filterKind;
            currentPage = 1; // doi filter -> ve trang dau
            setActiveChip(kindChips, chip);
            applyFilters();
        });
    });

    /* ── Event: filter dáng gọng ──────────────────────── */
    catChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            activeCat   = chip.dataset.filterCat;
            currentPage = 1;
            setActiveChip(catChips, chip);
            applyFilters();
        });
    });

    /* ── Event: filter giá ────────────────────────────── */
    priceChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            activePrice = chip.dataset.filterPrice;
            currentPage = 1;
            setActiveChip(priceChips, chip);
            applyFilters();
        });
    });

    /* ── Khởi chạy ────────────────────────────────────── */
    applyFilters();

})();
