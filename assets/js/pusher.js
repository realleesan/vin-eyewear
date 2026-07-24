/**
 * assets/js/pusher.js
 * Pusher (Scroll to Top) — Vin Eyewear, dùng chung toàn site
 * - Ẩn khi ở gần đầu trang, tự hiện khi cuộn quá ngưỡng
 * - Bấm thì cuộn mượt về đầu trang (native scrollTo, không thư viện ngoài)
 * - Scroll handler nhẹ: rAF throttle + chỉ toggle class khi trạng thái đổi
 */

(function () {
    'use strict';

    const btn = document.getElementById('pusherBtn');

    if (!btn) return; // Guard: trang khong render pusher thi khong lam gi

    // Nguong hien nut: ~nua man hinh cuon, du xa de khong chop tat
    // khi nguoi dung chi cuon nhe o dau trang
    const SCROLL_THRESHOLD = 400;

    let visible = false;
    let ticking = false;

    /* ── Cập nhật trạng thái hiện/ẩn ─────────────────── */

    function update() {
        ticking = false;
        const shouldShow = window.scrollY > SCROLL_THRESHOLD;

        // Chi dong den DOM khi trang thai thuc su doi
        if (shouldShow !== visible) {
            visible = shouldShow;
            btn.classList.toggle('is-visible', visible);
        }
    }

    /* ── Scroll: rAF throttle, passive, khong de handler khac ── */

    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });

    /* ── Click: cuộn mượt về đầu trang ───────────────── */

    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ── Trạng thái ban đầu (vd: reload khi đang ở giữa trang) ── */
    update();

})();
