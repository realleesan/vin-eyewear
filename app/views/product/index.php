<?php
/**
 * product/index.php
 * Biến nhận từ ProductController::index():
 *   $products — mảng sản phẩm (id, name, category, price, image, badge)
 * Card dùng component chung .product-card (định nghĩa ở layout.css), đồng bộ với home.
 */
?>

<!-- ============================================================
     PAGE HEADER
     ============================================================ -->
<div class="page-header reveal">
    <div class="page-header-inner">
        <p class="page-eyebrow">Eyewear Collection</p>
        <h1 class="page-title"><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Frames' ?></h1>
    </div>
</div>

<!-- ============================================================
     PRODUCT GRID
     ============================================================ -->
<section class="product-listing reveal">
    <div class="product-grid" id="productGrid">
        <?php foreach ($products as $card): require VIEWS_PATH . '/_layout/product-card.php'; endforeach; ?>
    </div>
</section>

<!-- ============================================================
     PAGINATION — nut duoc dung dong bang JS ben duoi
     ============================================================ -->
<nav class="pagination" id="productPagination" aria-label="Phan trang san pham"></nav>

<!-- Phan trang client-side: chia grid thanh nhieu trang, an/hien theo trang chon -->
<script>
(function () {
    'use strict';

    var PER_PAGE = 8; // 8 SP/trang = 2 hang x 4 cot, moi trang day khit

    var grid = document.getElementById('productGrid');
    var pager = document.getElementById('productPagination');
    if (!grid || !pager) return;

    var cards = Array.prototype.slice.call(grid.querySelectorAll('.product-card'));
    var totalPages = Math.ceil(cards.length / PER_PAGE);
    if (totalPages <= 1) return; // it hon 1 trang thi khong can pager

    var current = 1;

    // Hien card cua trang n, an cac card con lai.
    // scroll = true khi nguoi dung bam nut (cuon len dau danh sach),
    // false khi khoi tao (khong tu cuon luc tai trang).
    function showPage(n, scroll) {
        current = n;
        var start = (n - 1) * PER_PAGE;
        var end = start + PER_PAGE;
        cards.forEach(function (card, i) {
            card.style.display = (i >= start && i < end) ? '' : 'none';
        });
        renderPager();
        if (scroll) {
            grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Dung lai cac nut phan trang theo trang hien tai
    function renderPager() {
        pager.innerHTML = '';

        // Nut Prev
        pager.appendChild(makeBtn('Prev', current - 1, current === 1));

        // Cac nut so trang
        for (var p = 1; p <= totalPages; p++) {
            pager.appendChild(makeNum(p));
        }

        // Nut Next
        pager.appendChild(makeBtn('Next', current + 1, current === totalPages));
    }

    // Tao nut so trang
    function makeNum(p) {
        var el = document.createElement(p === current ? 'span' : 'a');
        el.textContent = p;
        if (p === current) {
            el.className = 'active';
            el.setAttribute('aria-current', 'page');
        } else {
            el.href = '#';
            el.addEventListener('click', function (e) {
                e.preventDefault();
                showPage(p, true);
            });
        }
        return el;
    }

    // Tao nut Prev/Next; disabled thi lam mo va khong bat su kien
    function makeBtn(label, target, disabled) {
        if (disabled) {
            var span = document.createElement('span');
            span.className = 'is-disabled';
            span.textContent = label;
            return span;
        }
        var a = document.createElement('a');
        a.href = '#';
        a.textContent = label;
        a.addEventListener('click', function (e) {
            e.preventDefault();
            showPage(target, true);
        });
        return a;
    }

    showPage(1, false);
})();
</script>
