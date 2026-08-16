/**
 * hero.js — carousel banner trang chủ (S03).
 *
 * Markup: app/views/_layout/home/hero.php · Giao diện: components/home-sections.css
 *
 * Làm gì:
 *   - trượt ngang giữa các slide bằng transform trên một track
 *   - tự chạy 6 giây một slide, dừng khi rê chuột / focus / chuyển tab
 *   - nút trái phải, hàng chấm tròn, nút tạm dừng
 *   - vuốt ngang trên màn cảm ứng
 *   - phím ← → khi tiêu điểm nằm trong banner
 *
 * Chạy được với thuộc tính `defer`.
 */

(function () {
    'use strict';

    var hero  = document.getElementById('hero');
    var track = document.getElementById('heroTrack');
    if (!hero || !track) return;

    var slides = track.querySelectorAll('.hslide');
    if (slides.length < 2) return;

    var dots    = hero.querySelectorAll('[data-hero-dot]');
    var status  = document.getElementById('heroStatus');
    var toggle  = document.getElementById('heroToggle');

    var INTERVAL = 6000;
    var index    = 0;
    var timer    = null;

    // Người dùng tự bấm tạm dừng thì tôn trọng đến hết phiên, không tự chạy lại
    var userPaused = false;

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    // Cụm điều khiển để sẵn `hidden` trong HTML cho trường hợp không có JS
    hero.querySelectorAll('[data-needs-js]').forEach(function (el) { el.hidden = false; });

    /* ====================================================================
       CHUYỂN SLIDE
       ==================================================================== */

    function go(next) {
        index = (next + slides.length) % slides.length;

        track.style.transform = 'translateX(' + (-index * 100) + '%)';

        slides.forEach(function (slide, i) {
            var on = i === index;
            slide.classList.toggle('is-active', on);

            /*
             * inert: slide nằm ngoài khung vẫn là DOM thật, không chặn thì
             * nhấn Tab sẽ đi vào các nút của slide không nhìn thấy và tiêu
             * điểm biến mất trước mắt người dùng.
             */
            if (on) {
                slide.removeAttribute('inert');
                slide.removeAttribute('aria-hidden');
            } else {
                slide.setAttribute('inert', '');
                slide.setAttribute('aria-hidden', 'true');
            }
        });

        dots.forEach(function (dot, i) {
            dot.classList.toggle('is-active', i === index);
            if (i === index) {
                dot.setAttribute('aria-current', 'true');
            } else {
                dot.removeAttribute('aria-current');
            }
        });

        if (status) status.textContent = 'Banner ' + (index + 1) + ' trên ' + slides.length;
    }

    /* ====================================================================
       TỰ CHẠY
       ==================================================================== */

    function play() {
        // Người dùng đã tắt hiệu ứng chuyển động -> không tự chạy, chỉ bấm tay
        if (userPaused || reduceMotion.matches) return;
        stop();
        timer = window.setInterval(function () { go(index + 1); }, INTERVAL);
    }

    function stop() {
        window.clearInterval(timer);
        timer = null;
    }

    /** Bấm tay thì nhảy slide và hẹn lại giờ, để slide vừa chọn được xem đủ lâu */
    function goManual(next) {
        go(next);
        if (timer) play();
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            userPaused = !userPaused;
            toggle.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
            toggle.setAttribute('aria-label', userPaused ? 'Cho banner chạy tiếp' : 'Tạm dừng banner tự chạy');
            hero.classList.toggle('is-paused', userPaused);
            userPaused ? stop() : play();
        });
    }

    /* ====================================================================
       ĐIỀU KHIỂN
       ==================================================================== */

    var prev = hero.querySelector('[data-hero-prev]');
    var next = hero.querySelector('[data-hero-next]');

    if (prev) prev.addEventListener('click', function () { goManual(index - 1); });
    if (next) next.addEventListener('click', function () { goManual(index + 1); });

    dots.forEach(function (dot, i) {
        dot.addEventListener('click', function () { goManual(i); });
    });

    /*
     * Phím ← → chỉ nghe TRONG cụm điều khiển, không nghe cả banner.
     *
     * Bắt ở cả banner thì người dùng bàn phím đang đứng ở nút "Mua ngay" mà
     * bấm mũi tên sẽ đổi slide, nút đó theo slide cũ ra ngoài và thành inert
     * — tiêu điểm rơi về body, không còn biết mình đang ở đâu (đo được:
     * bấm → xong thì bấm ← không còn tác dụng vì sự kiện không phát ra từ
     * trong banner nữa). Các nút điều khiển thì luôn ở lại nên không bị.
     */
    function onControlKey(e) {
        if (e.key === 'ArrowLeft')  { e.preventDefault(); goManual(index - 1); }
        if (e.key === 'ArrowRight') { e.preventDefault(); goManual(index + 1); }
    }

    // Hai cụm riêng biệt: chấm tròn ở giữa đáy, nút ‹ › ở góc phải
    hero.querySelectorAll('.hero__controls, .hero__dots').forEach(function (el) {
        el.addEventListener('keydown', onControlKey);
    });

    // Rê chuột hoặc đưa tiêu điểm vào banner -> dừng, để người dùng kịp đọc
    hero.addEventListener('mouseenter', stop);
    hero.addEventListener('mouseleave', play);
    hero.addEventListener('focusin', stop);
    hero.addEventListener('focusout', function (e) {
        if (!hero.contains(e.relatedTarget)) play();
    });

    // Chuyển sang tab khác thì không việc gì phải chạy tiếp
    document.addEventListener('visibilitychange', function () {
        document.hidden ? stop() : play();
    });

    /* ====================================================================
       VUỐT NGANG
       ==================================================================== */

    var startX = 0;
    var startY = 0;
    var dragging = false;

    track.addEventListener('pointerdown', function (e) {
        if (e.pointerType === 'mouse') return;   // chuột đã có nút trái/phải
        dragging = true;
        startX = e.clientX;
        startY = e.clientY;
        stop();
    }, { passive: true });

    track.addEventListener('pointerup', function (e) {
        if (!dragging) return;
        dragging = false;

        var dx = e.clientX - startX;
        var dy = e.clientY - startY;

        /*
         * Chỉ tính là vuốt khi đi ngang đủ xa VÀ ngang nhiều hơn dọc — nếu
         * không, mỗi lần người dùng cuộn trang bằng ngón tay đặt trên banner
         * sẽ vô tình đổi slide.
         */
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
            go(dx < 0 ? index + 1 : index - 1);
        }

        play();
    }, { passive: true });

    track.addEventListener('pointercancel', function () { dragging = false; play(); }, { passive: true });

    /* ====================================================================
       KHỞI ĐỘNG
       ==================================================================== */

    go(0);
    play();

    // Người dùng bật/tắt "giảm chuyển động" giữa chừng
    reduceMotion.addEventListener('change', function () {
        reduceMotion.matches ? stop() : play();
    });
})();
