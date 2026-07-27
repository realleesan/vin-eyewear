/**
 * reveal.js
 * Scroll reveal dùng chung toàn site: phần tử .reveal hiện dần khi cuộn tới.
 *
 * Trước đây logic này nằm inline trong master.php và CHỈ chạy đúng 1 lần lúc
 * trang parse xong -> phần tử .reveal được render/thay thế SAU đó (ví dụ lưới
 * "Có thể bạn thích" ở trang chi tiết, hay lưới sản phẩm sau khi phân trang)
 * không bao giờ được observe, nên kẹt mãi ở trạng thái chưa .visible (mờ/xám).
 *
 * Nay tách thành window.initReveal(container) để gọi lại được sau mỗi lần render.
 *
 *   initReveal();                        // quét cả document
 *   initReveal('#pdRelatedGrid');        // quét trong 1 selector
 *   initReveal(document.querySelector('.pd-related'));
 *
 * AN TOÀN: hàm idempotent (phần tử đã gắn observer thì bỏ qua) và luôn có
 * fallback — không có IntersectionObserver thì hiện ngay opacity: 1. Trạng thái
 * ẩn ban đầu do class .js-reveal trên <html> khống chế (xem layout.css), nên nếu
 * file này không tải được thì nội dung vẫn hiển thị đầy đủ, không ẩn vĩnh viễn.
 */
(function (window, document) {
    'use strict';

    var BOUND_ATTR = 'data-reveal-bound';
    var observer = null;

    function show(el) {
        el.classList.add('visible');
    }

    function getObserver() {
        if (observer) return observer;

        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                show(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.1 });

        return observer;
    }

    /**
     * Gắn scroll reveal cho mọi .reveal bên trong container.
     * @param {Element|Document|string} [container=document] phần tử, hoặc selector.
     */
    function initReveal(container) {
        var root = container || document;

        if (typeof root === 'string') root = document.querySelector(root);
        if (!root) return;

        var targets = [];

        // Bản thân container cũng có thể là .reveal (vd: truyền thẳng section vào).
        if (root.nodeType === 1 && root.classList.contains('reveal')) targets.push(root);
        Array.prototype.push.apply(targets, root.querySelectorAll('.reveal'));

        // Chưa gắn observer lần nào -> mới xử lý (gọi lại nhiều lần vẫn an toàn).
        targets = targets.filter(function (el) {
            return !el.hasAttribute(BOUND_ATTR);
        });

        if (!targets.length) return;

        targets.forEach(function (el) {
            el.setAttribute(BOUND_ATTR, '');
        });

        // Fallback: trình duyệt không hỗ trợ IntersectionObserver -> hiện luôn,
        // KHÔNG để nội dung mắc kẹt ở opacity: 0.
        if (!('IntersectionObserver' in window)) {
            targets.forEach(show);
            return;
        }

        var io = getObserver();
        targets.forEach(function (el) {
            io.observe(el);
        });
    }

    window.initReveal = initReveal;

    // Lượt quét đầu tiên cho nội dung render sẵn từ server.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initReveal(); });
    } else {
        initReveal();
    }
})(window, document);
