/**
 * order-success.js — trang xác nhận đặt hàng (/thanh-toan/hoan-tat)
 *
 * Một việc duy nhất: nút sao chép mã đơn.
 *
 * CHỈ LÀ TĂNG CƯỜNG. Nút nằm sẵn trong HTML với thuộc tính `hidden` và chỉ file
 * này mới bỏ ra — không có JS (hoặc trình duyệt không có clipboard) thì không có
 * nút bấm rỗng nào, mã đơn vẫn in ngay bên cạnh để khách tự chọn và chép.
 *
 * Trang này KHÔNG tự chuyển đi đâu cả. Trước đây có đếm ngược 3 giây rồi sang
 * "Đơn hàng của tôi"; đã bỏ — đây là hoá đơn khách đang đọc, và hai nút ở cuối
 * trang để họ tự quyết định đi đâu.
 */
(function () {
    'use strict';

    var copy = document.querySelector('[data-copy]');
    if (!copy) return;

    if (!navigator.clipboard) return;

    var said  = document.querySelector('.ocomp__copied');
    var timer = null;

    copy.hidden = false;

    copy.addEventListener('click', function () {
        navigator.clipboard.writeText(copy.getAttribute('data-copy')).then(function () {
            if (!said) return;

            said.hidden = false;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () { said.hidden = true; }, 2000);
        });
    });
}());
