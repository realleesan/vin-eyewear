/**
 * product.detail.js
 * Đổi ảnh lớn khi bấm thumbnail ở trang chi tiết sản phẩm.
 */
(function () {
    'use strict';

    var mainImg = document.getElementById('pd-main-img');
    var thumbs = document.querySelectorAll('.pd-thumb');

    if (!mainImg || !thumbs.length) return;

    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            var full = thumb.getAttribute('data-full');
            if (!full || full === mainImg.getAttribute('src')) return;

            mainImg.setAttribute('src', full);

            thumbs.forEach(function (t) { t.classList.remove('active'); });
            thumb.classList.add('active');
        });
    });
})();
