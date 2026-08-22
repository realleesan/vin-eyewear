/**
 * copy-btn.js — nút "Sao chép" dùng chung.
 *
 * Bắt mọi phần tử `.js-copy[data-copy]` ở BẤT KỲ trang nào nạp file này. Hiện
 * dùng ở hai chỗ, và hai chỗ đó chép đúng cùng một loại dữ liệu:
 *
 *   /tai-khoan?muc=don-hang        số tài khoản · nội dung chuyển khoản
 *   /thanh-toan/chuyen-khoan       số tài khoản · nội dung chuyển khoản
 *
 * VÌ SAO TÁCH RA MỘT FILE RIÊNG: cùng hành vi này đã suýt được viết lần thứ ba
 * (order-success.js đã có một bản cho nút chép mã đơn). Ba bản sao của cùng một
 * việc nghĩa là ngày sửa cách báo "Đã chép" sẽ có hai chỗ bị quên.
 *
 * CHỈ LÀ TĂNG CƯỜNG. Chuỗi cần chép luôn được in ra dạng chữ ngay cạnh nút, nên
 * không có JS thì khách bôi đen chép tay như trước — nút là lối tắt, không phải
 * lối duy nhất.
 */
(function () {
    'use strict';

    /* Đường lui cho trình duyệt cũ, và cho trang mở qua HTTP:
       navigator.clipboard chỉ tồn tại ở ngữ cảnh bảo mật, mà site vẫn có thể
       được mở bằng http trong lúc chạy thử. */
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');

        ta.value = text;
        ta.setAttribute('readonly', '');
        /* Đặt ngoài tầm nhìn thay vì display:none — trình duyệt không chọn được
           chữ trong một phần tử đã bị ẩn hẳn. */
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        document.body.appendChild(ta);
        ta.select();

        try { document.execCommand('copy'); } catch (e) { /* hết cách, thôi */ }

        document.body.removeChild(ta);
    }

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.js-copy[data-copy]');
        if (!btn) return;

        var text = btn.getAttribute('data-copy');
        if (!text) return;

        ev.preventDefault();

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).catch(function () { fallbackCopy(text); });
        } else {
            fallbackCopy(text);
        }

        /* Báo bằng CHÍNH CÁI NÚT vừa bấm, không phải một dải thông báo ở đầu
           trang: mắt khách đang ở đúng đây, và một thẻ đơn có tới hai nút chép
           nên phải nói rõ vừa chép cái nào. */
        var old = btn.getAttribute('data-label') || btn.textContent;

        btn.setAttribute('data-label', old);
        btn.textContent = 'Đã chép ✓';
        btn.classList.add('is-done');

        clearTimeout(btn._copyTimer);
        btn._copyTimer = setTimeout(function () {
            btn.textContent = old;
            btn.classList.remove('is-done');
        }, 1600);
    });
}());
