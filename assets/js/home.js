/**
 * assets/js/home.js
 * JS riêng của trang chủ:
 *   1. Best Seller carousel — nút prev/next cuộn track sản phẩm
 *   2. Feature gallery       — đổi ảnh lớn khi bấm thumbnail
 * Scroll reveal dùng chung được xử lý inline trong master.php.
 */
(function () {
    'use strict';

    /* ── 1. Best Seller carousel ──────────────────────── */
    var track = document.getElementById('bestsellerTrack');
    var carouselBtns = document.querySelectorAll('.carousel__btn[data-dir]');

    if (track && carouselBtns.length) {
        // Cuộn 1 "trang" = bề rộng khung nhìn (đã scroll-snap tự canh thẻ)
        carouselBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dir = btn.getAttribute('data-dir') === 'prev' ? -1 : 1;
                track.scrollBy({ left: dir * track.clientWidth, behavior: 'smooth' });
            });
        });

        // Ẩn nút prev/next khi chạm 2 đầu (dư 2px cho sai số làm tròn)
        var prevBtn = document.querySelector('.carousel__btn--prev');
        var nextBtn = document.querySelector('.carousel__btn--next');

        var updateBtns = function () {
            var maxScroll = track.scrollWidth - track.clientWidth;
            if (prevBtn) prevBtn.disabled = track.scrollLeft <= 2;
            if (nextBtn) nextBtn.disabled = track.scrollLeft >= maxScroll - 2;
        };

        track.addEventListener('scroll', updateBtns);
        window.addEventListener('resize', updateBtns);
        updateBtns();
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
