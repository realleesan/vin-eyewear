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

    /* Giỏ rỗng hoặc trang khác thì không có khối nào — thoát ở dòng trên và
       cả file coi như không tồn tại. Trang thanh toán bình thường LUÔN có ít
       nhất khối chuyển khoản, vì mọi đơn đều chọn được cọc hay chuyển đủ. */

    /* Nút đặt hàng. Có thể không có (đơn rỗng) nên mọi chỗ đụng tới đều hỏi
       lại — file này phải chạy được trên trang thanh toán ở mọi trạng thái. */
    var nut = document.querySelector('[data-cta]');

    /* Đơn CÓ PHẢI CỌC KHÔNG suy từ chính DOM: khối cọc COD chỉ được máy chủ
       in ra cho đơn có mài tròng. Không truyền thêm một data-* nữa vì hai
       nguồn sự thật cho cùng một câu hỏi là hai nguồn có thể lệch nhau. */
    var coCoc = !!document.querySelector('[data-deposit-block="cod"]');

    function doi() {
        var chon = document.querySelector('input[name="payment_method"]:checked');
        var pt   = chon ? chon.value : '';

        Array.prototype.forEach.call(khoi, function (el) {
            el.hidden = el.getAttribute('data-deposit-block') !== pt;
        });

        if (!nut) return;

        /* "Đặt hàng" trơn CHỈ khi sau đó không còn bước trả tiền nào. Chuyển
           khoản luôn còn màn QR; COD đơn cắt tròng cũng vậy vì phải cọc. */
        var xong = pt === 'cod' && !coCoc;

        nut.textContent = nut.getAttribute(xong ? 'data-cta-plain' : 'data-cta-pay');
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
