/**
 * share.js — nút "sao chép liên kết" ở trang chi tiết bài viết.
 *
 * CHỈ LÀ TĂNG CƯỜNG. Nút mang sẵn thuộc tính `hidden` trong HTML; file này gỡ
 * nó ra. Không có JS thì nút không bao giờ hiện — một nút bấm mà không xảy ra
 * gì còn khó hiểu hơn là không có nút. Nút chia sẻ Facebook bên cạnh là liên
 * kết thật nên vẫn chạy trong mọi trường hợp.
 */
(function () {
    'use strict';

    var btn = document.getElementById('copy-link');
    if (!btn) return;

    // navigator.clipboard chỉ tồn tại trên HTTPS (và localhost). Trên HTTP
    // thường thì không có gì để gỡ ẩn — đúng nguyên tắc ở trên.
    if (!navigator.clipboard) return;

    btn.hidden = false;

    btn.addEventListener('click', function () {
        navigator.clipboard.writeText(btn.dataset.url).then(function () {
            var old = btn.getAttribute('aria-label');

            btn.classList.add('is-done');
            btn.setAttribute('aria-label', 'Đã sao chép liên kết');

            // Trả lại trạng thái cũ sau hai giây: dấu tích nằm mãi sẽ khiến
            // lần bấm sau trông như không có tác dụng.
            window.setTimeout(function () {
                btn.classList.remove('is-done');
                btn.setAttribute('aria-label', old);
            }, 2000);
        });
    });
}());
