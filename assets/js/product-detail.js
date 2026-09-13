/**
 * product-detail.js — cột mua hàng ở trang chi tiết sản phẩm.
 *
 * CHỈ LÀ TĂNG CƯỜNG. Chọn phương án là ô radio thật trong form "Thêm vào giỏ",
 * nên không có file này thì vẫn chọn và mua được — chỉ mất dòng tên màu bên
 * phải hàng ô màu đổi theo cú bấm (nó đứng yên ở phương án chọn sẵn).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Ô SỐ LƯỢNG ĐÃ GỠ (13/09/2026)
 *
 * Bản thiết kế "Product Detail" không có ô số lượng: cột mua chỉ còn ô màu và
 * hai nút. Form gửi quantity=1 cố định; khách đổi số lượng ở trang giỏ hàng,
 * nơi CartController kẹp theo tồn kho và báo bằng tiếng Việt. Toàn bộ phần
 * kẹp số, hai nút − / + và dòng nhắc tại chỗ đi theo ô số đó.
 * ─────────────────────────────────────────────────────────────────────────────
 */

(function () {
    'use strict';

    var ten = document.querySelector('[data-variant-name]');

    if (!ten) return;

    var pa = document.querySelectorAll('input[name="variant_id"][data-name]');

    Array.prototype.forEach.call(pa, function (r) {
        r.addEventListener('change', function () {
            if (r.checked) ten.textContent = r.getAttribute('data-name');
        });
    });
})();
