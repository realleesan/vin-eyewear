/**
 * assets/js/home.js
 * JS riêng của trang chủ:
 *   1. Tab "Gọng ký hiệu" — đổi panel sản phẩm
 *   2. Feature gallery   — đổi ảnh lớn khi bấm thumbnail
 * Scroll reveal dùng chung được xử lý inline trong master.php.
 */
(function () {
    'use strict';

    /* ── 1. Tabs ──────────────────────────────────────── */
    var tabs = document.querySelectorAll('.tab[data-tab]');
    var panels = document.querySelectorAll('.tabpanel[data-panel]');

    if (tabs.length && panels.length) {
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-tab');

                tabs.forEach(function (t) {
                    var on = t === tab;
                    t.classList.toggle('is-active', on);
                    t.setAttribute('aria-selected', String(on));
                });

                panels.forEach(function (panel) {
                    var on = panel.getAttribute('data-panel') === target;
                    panel.classList.toggle('is-active', on);
                    panel.hidden = !on;
                });
            });
        });
    }

    /* ── 2. Feature gallery ───────────────────────────── */
    var featureImg = document.getElementById('featureImg');
    var thumbs = document.querySelectorAll('.feature__thumb-btn');

    if (featureImg && thumbs.length) {
        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var full = thumb.getAttribute('data-full');
                if (!full || full === featureImg.getAttribute('src')) return;

                featureImg.setAttribute('src', full);

                thumbs.forEach(function (t) { t.classList.remove('is-active'); });
                thumb.classList.add('is-active');
            });
        });
    }
})();
