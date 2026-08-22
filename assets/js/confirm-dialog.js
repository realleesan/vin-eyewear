/**
 * confirm-dialog.js — thay hộp confirm() của trình duyệt bằng hộp thoại trên trang.
 *
 * Markup: app/views/_layout/confirm-dialog.php · CSS: components/confirm.css
 *
 * Cách dùng — đặt thuộc tính lên chính nút gửi form:
 *
 *     <button type="submit" name="act" value="xoa"
 *             data-confirm="Bỏ “Gọng Acetate A02” khỏi giỏ hàng?"
 *             data-confirm-title="Xoá sản phẩm?"
 *             data-confirm-ok="Xoá">
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG CÓ FILE NÀY THÌ VẪN PHẢI HỎI LẠI
 *
 * Xoá một dòng giỏ hàng là việc không lùi được. Nên trong HTML mỗi nút vẫn giữ
 * onclick="return confirm(...)" như cũ, và chính file này GỠ nó ra khi đã sẵn
 * sàng thay thế. Thứ tự đó quan trọng: JS hỏng, chưa tải xong, hay trình duyệt
 * không có <dialog> — cả ba trường hợp khách vẫn được hỏi bằng hộp của trình
 * duyệt, xấu nhưng an toàn. Đây là chỗ hiếm hoi mà "tăng cường" không được
 * phép làm mất đi một lớp bảo vệ.
 *
 * Câu chữ trong onclick và trong data-confirm do CÙNG một biến PHP sinh ra
 * (xem cart/index.php), nên hai đường không thể nói hai câu khác nhau.
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    var box = document.querySelector('[data-confirm-dialog]');

    if (!box || typeof box.showModal !== 'function') return;

    var title  = box.querySelector('[data-cfm-title]');
    var body   = box.querySelector('[data-cfm-body]');
    var ok     = box.querySelector('[data-cfm-ok]');
    var cancel = box.querySelector('[data-cfm-cancel]');

    if (!title || !body || !ok) return;

    /* Nút đang chờ trả lời. Giữ ở đây chứ không bám vào hộp thoại: hộp đóng
       xong mới tới lượt gửi form, mà lúc đó cần đúng nút đã bấm để form mang
       theo name/value của nó. */
    var dangHoi = null;

    var CHU_OK     = ok.textContent;
    var CHU_HUY    = cancel ? cancel.textContent : '';
    var CHU_TITLE  = title.textContent;

    /* Gỡ lớp dự phòng. Từ đây trở đi việc hỏi lại là của file này. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-confirm]'), function (el) {
        el.removeAttribute('onclick');
        el.removeAttribute('onsubmit');
    });

    function mo(el) {
        dangHoi = el;

        title.textContent = el.getAttribute('data-confirm-title') || CHU_TITLE;
        body.textContent  = el.getAttribute('data-confirm') || '';
        ok.textContent    = el.getAttribute('data-confirm-ok') || CHU_OK;

        if (cancel) cancel.textContent = el.getAttribute('data-confirm-cancel') || CHU_HUY;

        box.showModal();
    }

    document.addEventListener('click', function (ev) {
        var el = ev.target.closest ? ev.target.closest('[data-confirm]') : null;

        if (!el || el.disabled) return;

        ev.preventDefault();
        mo(el);
    });

    /* Gửi form bằng Enter từ trong một ô nhập cũng phải đi qua đây — nếu không
       thì ô số lượng của dòng giỏ hàng trở thành đường vòng không ai hỏi han. */
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        var el   = form.matches && form.matches('[data-confirm]') ? form : null;

        if (!el) return;

        ev.preventDefault();
        mo(el);
    });

    box.addEventListener('close', function () {
        var el = dangHoi;

        dangHoi = null;

        if (box.returnValue !== 'ok' || !el) return;

        /* Trả lại đúng cú bấm ban đầu.

           requestSubmit(nút) chứ không submit(): nút xoá mang name="act"
           value="xoa", và submit() KHÔNG gửi kèm name/value của nút nào cả —
           form đi lên thiếu 'act', controller hiểu thành "đặt lại số lượng"
           và món hàng ở nguyên trong giỏ. */
        if (el.tagName === 'FORM') {
            el.requestSubmit();
            return;
        }

        var form = el.form || (el.closest ? el.closest('form') : null);

        if (!form) return;

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(el);
            return;
        }

        /* Trình duyệt cũ không có requestSubmit: dựng một ô ẩn mang đúng
           name/value của nút rồi gửi thường. */
        if (el.name) {
            var o = document.createElement('input');

            o.type  = 'hidden';
            o.name  = el.name;
            o.value = el.value;
            form.appendChild(o);
        }

        form.submit();
    });

    /* Bấm ra ngoài panel để đóng — trình duyệt tính vùng nền (::backdrop) là
       chính thẻ <dialog>, nên cú bấm trúng nền có target là nó. */
    box.addEventListener('click', function (ev) {
        if (ev.target === box) box.close('');
    });
}());
