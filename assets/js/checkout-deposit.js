/**
 * checkout-deposit.js — đổi khối "đặt cọc" theo phương thức thanh toán đang chọn.
 *
 * Markup: app/views/order/checkout.php (hai khối [data-deposit-block])
 *
 * Chỉ đơn CÓ MÀI TRÒNG mới in ra hai khối này, và luật là:
 *
 *   COD          cọc 30% trước, phần còn lại trả khi nhận hàng
 *   chuyển khoản trả đủ 100%, không cọc
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CHỈ LÀ TĂNG CƯỜNG — VÀ Ở ĐÂY ĐIỀU ĐÓ AN TOÀN
 *
 * Trang thanh toán cố ý không dựa vào JavaScript. Không có file này thì khối
 * hiện ra là khối đúng với phương thức ĐANG chọn lúc máy chủ vẽ trang; khách
 * đổi ô radio xong thấy con số cũ trong một nhịp, tới khi đặt hàng thì máy chủ
 * chốt lại theo phương thức thật.
 *
 * Nói cách khác: file này lo phần ĐỌC, không lo phần TÍNH. Số tiền cọc do
 * OrderModel::place() quyết định trong cùng transaction với đơn, nên không có
 * cách nào sửa JS để trả ít đi.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    var khoi = document.querySelectorAll('[data-deposit-block]');

    if (!khoi.length) return;

    /* Đơn không mài tròng thì máy chủ không in khối nào — thoát ở dòng trên,
       và cả file coi như không tồn tại. */

    function doi() {
        var chon = document.querySelector('input[name="payment_method"]:checked');
        var pt   = chon ? chon.value : '';

        Array.prototype.forEach.call(khoi, function (el) {
            el.hidden = el.getAttribute('data-deposit-block') !== pt;
        });
    }

    /* Nghe trên document chứ không trên từng ô radio: gắn thẳng thì một ô
       thêm vào sau này (ví dụ ví điện tử) sẽ im lặng không đổi gì cả. */
    document.addEventListener('change', function (ev) {
        var t = ev.target;

        if (t && t.name === 'payment_method') doi();
    });

    /* Chạy một lần lúc tải: trình duyệt khôi phục lựa chọn cũ khi bấm Lùi, và
       lúc đó cái đang chọn có thể khác cái máy chủ vừa vẽ. */
    doi();
}());
