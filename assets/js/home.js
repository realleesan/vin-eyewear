/**
 * assets/js/home.js
 * Carousel trang chủ Vin Eyewear
 * Tính năng:
 *   - Tự động chạy (autoplay 5s)
 *   - Prev / Next buttons
 *   - Dots navigation
 *   - Touch / Swipe trên mobile
 *   - Dừng autoplay khi hover hoặc focus
 */

(function () {
    'use strict';

    /* ── DOM ──────────────────────────────────────────── */
    const track   = document.getElementById('carouselTrack');
    const prevBtn = document.getElementById('carouselPrev');
    const nextBtn = document.getElementById('carouselNext');
    const dotsWrap = document.getElementById('carouselDots');

    if (!track) return; // Không có carousel thì thoát

    const slides    = Array.from(track.querySelectorAll('.carousel__slide'));
    const dots      = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.carousel__dot')) : [];
    const total     = slides.length;
    const AUTOPLAY_DELAY = 5000; // 5 giây

    let current   = 0;
    let autoTimer = null;

    /* ── Core: chuyển slide ───────────────────────────── */
    function goTo(index) {
        // Wrap vòng lặp
        current = (index + total) % total;

        // Di chuyển track
        track.style.transform = `translateX(-${current * 100}%)`;

        // Cập nhật aria + class cho slides
        slides.forEach((slide, i) => {
            const active = i === current;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', String(!active));
        });

        // Cập nhật dots
        dots.forEach((dot, i) => {
            const active = i === current;
            dot.classList.toggle('is-active', active);
            dot.setAttribute('aria-selected', String(active));
        });
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    /* ── Autoplay ─────────────────────────────────────── */
    function startAutoplay() {
        stopAutoplay();
        autoTimer = setInterval(next, AUTOPLAY_DELAY);
    }

    function stopAutoplay() {
        if (autoTimer) {
            clearInterval(autoTimer);
            autoTimer = null;
        }
    }

    /* ── Button listeners ─────────────────────────────── */
    if (prevBtn) {
        prevBtn.addEventListener('click', () => { prev(); startAutoplay(); });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => { next(); startAutoplay(); });
    }

    /* ── Dots listeners ───────────────────────────────── */
    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            goTo(Number(dot.dataset.index));
            startAutoplay();
        });
    });

    /* ── Pause khi hover / focus ──────────────────────── */
    const carousel = document.getElementById('heroCarousel');
    if (carousel) {
        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        carousel.addEventListener('focusin',    stopAutoplay);
        carousel.addEventListener('focusout',   startAutoplay);
    }

    /* ── Touch / Swipe (mobile) ───────────────────────── */
    let touchStartX = 0;
    let touchEndX   = 0;
    const SWIPE_THRESHOLD = 50; // px

    track.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    track.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        const diff = touchStartX - touchEndX;

        if (Math.abs(diff) > SWIPE_THRESHOLD) {
            diff > 0 ? next() : prev();
            startAutoplay();
        }
    }, { passive: true });

    /* ── Khởi chạy ────────────────────────────────────── */
    goTo(0);
    startAutoplay();

})();