/**
 * product.detail.js
 * - Đổi ảnh lớn khi bấm thumbnail.
 * - Khởi tạo lại scroll reveal cho khối "Có thể bạn thích" sau khi lưới sản
 *   phẩm liên quan đã render xong.
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Gallery: thumbnail -> ảnh lớn
       ------------------------------------------------------------------ */
    var mainImg = document.getElementById('pd-main-img');
    var thumbs = document.querySelectorAll('.pd-thumb');

    if (mainImg && thumbs.length) {
        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var full = thumb.getAttribute('data-full');
                if (!full || full === mainImg.getAttribute('src')) return;

                mainImg.setAttribute('src', full);

                thumbs.forEach(function (t) { t.classList.remove('active'); });
                thumb.classList.add('active');
            });
        });
    }

    /* ------------------------------------------------------------------
       SẢN PHẨM LIÊN QUAN — khởi tạo lại scroll reveal

       Lưới hiện được render sẵn từ server, nhưng lượt quét .reveal đầu tiên
       chạy 1 lần duy nhất lúc trang load. Bất kỳ card nào được render/thay thế
       SAU thời điểm đó sẽ không có observer -> kẹt ở trạng thái chưa .visible.
       Gọi lại initReveal() ngay sau khi danh sách sẵn sàng để tránh việc đó, và
       để chỗ này là điểm móc sẵn cho khi lưới chuyển sang render động (fetch).
       ------------------------------------------------------------------ */
    function revealRelated() {
        var section = document.querySelector('.pd-related');
        if (!section) return;

        if (typeof window.initReveal === 'function') {
            window.initReveal(section);
            return;
        }

        // Fallback: reveal.js không dùng được -> hiện đủ, không để nội dung ẩn.
        section.classList.add('visible');
        section.querySelectorAll('.reveal').forEach(function (el) {
            el.classList.add('visible');
        });
    }

    revealRelated();
})();
