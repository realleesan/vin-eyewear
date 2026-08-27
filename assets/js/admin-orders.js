/**
 * admin-orders.js — bảng đơn hàng (/quan-tri/don-hang).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG CÓ FILE NÀY THÌ CHUYỆN GÌ XẢY RA
 *
 *   · Ô chọn trạng thái không tự gửi -> nút "Lưu" cạnh nó hiện ra và dùng được
 *     (admin-orders.css chỉ ẩn nút ấy khi <html> có lớp .js).
 *   · Không có câu hỏi lại trước khi đổi trạng thái. Đường lùi vẫn còn nguyên:
 *     thanh "Hoàn tác" ở góc dưới là một form POST thật.
 *   · Ô "chọn tất cả" ở đầu bảng KHÔNG hiện ra chút nào — nó ra đời với thuộc
 *     tính `hidden` và chỉ file này mở. Từng ô tick của mỗi dòng vẫn bấm được,
 *     và thanh thao tác hàng loạt vẫn tự hiện: việc đó do CSS `:has()` làm, xem
 *     .aobulk trong admin-orders.css.
 *   · Bấm vào giữa một dòng không mở ngăn kéo, nhưng mã đơn là thẻ <a> thật.
 *   · Ngăn kéo chi tiết đóng bằng nút ✕ hoặc bấm nền mờ — cả hai là <a> trỏ về
 *     chính trang này khi bỏ tham số ?xem=, không cần JavaScript.
 *
 * Nói cách khác: file này bỏ bớt cú bấm và thêm một lớp hỏi lại, không giữ
 * chức năng nào làm của riêng.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * CỐ Ý KHÔNG LÀM: tự ẩn thanh hoàn tác sau vài giây như bản thiết kế. Ở đó
 * thanh ấy là state trong trình duyệt nên không tự mất thì nó nằm lại mãi mãi;
 * ở đây nó là flash của phiên, tải trang lần sau là hết. Đặt thêm một cái hẹn
 * giờ chỉ để lấy mất đường lùi của người dùng sớm hơn.
 */
(function () {
    'use strict';

    var hopThoai = document.querySelector('[data-confirm-dialog]');

    /* Ô chọn đang đợi trả lời, và cách trả nó về chỗ cũ nếu người dùng bấm
       "Huỷ" — không có bước này thì viên trạng thái ở lại giá trị mới trong
       khi CSDL vẫn giữ giá trị cũ, tức là bảng nói dối. */
    var dangHoi = null;

    // ========================================================================
    // ĐỔI TRẠNG THÁI — HỎI LẠI RỒI MỚI GỬI
    // ========================================================================

    /*
     * KHÔNG dùng data-autosubmit của admin.js ở đây.
     *
     * Hàm đó gọi thẳng form.submit(), mà submit() KHÔNG phát ra sự kiện
     * 'submit' — nên confirm-dialog.js không bao giờ thấy gì để chặn, và đổi
     * trạng thái là chuyện xảy ra không lời hỏi han.
     *
     * requestSubmit() thì có phát sự kiện. Đặt data-confirm lên form ngay
     * trước khi gọi, và confirm-dialog.js lo phần còn lại — nó đã biết cách
     * gửi lại form sau khi người dùng đồng ý.
     */
    Array.prototype.forEach.call(document.querySelectorAll('[data-status-pick]'), function (sel) {
        // Giá trị đang có trong CSDL, đọc MỘT LẦN lúc dựng trang.
        var cu = sel.value;

        sel.addEventListener('change', function () {
            var form = sel.form;

            if (!form) return;

            if (sel.value === cu) return;   // chọn lại đúng giá trị cũ: không có gì để làm

            form.setAttribute(
                'data-confirm',
                sel.getAttribute('data-ma') + ': «' + sel.getAttribute('data-cu') + '» → «'
                    + sel.options[sel.selectedIndex].text + '»'
            );
            form.setAttribute('data-confirm-title', 'Xác nhận đổi trạng thái');
            form.setAttribute('data-confirm-ok', 'Xác nhận');

            dangHoi = { sel: sel, cu: cu, form: form };

            /* Trình duyệt cũ không có requestSubmit thì gửi thẳng, không hỏi.
               Thà đổi được trạng thái mà thiếu câu hỏi còn hơn một ô chọn bấm
               vào không làm gì — và thanh Hoàn tác vẫn còn đó. */
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    // ========================================================================
    // THAO TÁC HÀNG LOẠT
    // ========================================================================

    var formLoat = document.querySelector('.aoform');

    if (formLoat) {
        var oTatCa = document.getElementById('aoAll');
        var oDem   = formLoat.querySelector('[data-bulk-count]');
        var oChon  = Array.prototype.slice.call(
            formLoat.querySelectorAll('.aopick:not(.aopick--all)')
        );

        var demLai = function () {
            var so = oChon.filter(function (o) { return o.checked; }).length;

            if (oDem) {
                oDem.textContent = so + ' đơn đã chọn';
            }

            if (oTatCa) {
                oTatCa.checked = so > 0 && so === oChon.length;
                /* Trạng thái "một phần": tick vài dòng thì ô đầu bảng không
                   được nói là đã chọn hết, cũng không được nói là chưa chọn
                   gì. HTML có sẵn nấc giữa cho đúng việc này. */
                oTatCa.indeterminate = so > 0 && so < oChon.length;
            }
        };

        if (oTatCa && oChon.length) {
            // Đây là chỗ ô "chọn tất cả" ra khỏi bóng tối — xem chú thích đầu file.
            oTatCa.hidden = false;

            oTatCa.addEventListener('change', function () {
                oChon.forEach(function (o) { o.checked = oTatCa.checked; });
                demLai();
            });
        }

        oChon.forEach(function (o) { o.addEventListener('change', demLai); });

        /* Nút "Bỏ chọn" là <button type="reset"> — nó chạy được cả khi không có
           JavaScript, nhưng reset KHÔNG phát sự kiện 'change' trên từng ô, nên
           con số phải tự đếm lại. setTimeout(0) vì lúc sự kiện 'reset' chạy thì
           trình duyệt còn chưa xoá dấu tick. */
        formLoat.addEventListener('reset', function () {
            window.setTimeout(demLai, 0);
        });

        demLai();

        /* Ô chọn trạng thái của thanh hàng loạt: chọn xong là gửi luôn, không
           phải bấm thêm "Áp dụng" (CSS ẩn nút ấy khi có JS). Đi qua nút để cú
           gửi mang theo name="act" value="trang-thai" — nếu không, controller
           không biết phải làm việc gì. */
        var selLoat = formLoat.querySelector('.aobulk__sel');
        var nutLoat = formLoat.querySelector('.aobulk__go');

        if (selLoat && nutLoat && typeof formLoat.requestSubmit === 'function') {
            selLoat.addEventListener('change', function () {
                if (!selLoat.value) return;

                formLoat.setAttribute(
                    'data-confirm',
                    'Chuyển ' + oChon.filter(function (o) { return o.checked; }).length
                        + ' đơn đã chọn sang «' + selLoat.options[selLoat.selectedIndex].text + '»?'
                );
                formLoat.setAttribute('data-confirm-title', 'Xác nhận đổi trạng thái');
                formLoat.setAttribute('data-confirm-ok', 'Xác nhận');

                dangHoi = { sel: selLoat, cu: '', form: formLoat };

                formLoat.requestSubmit(nutLoat);
            });
        }
    }

    // ========================================================================
    // TRẢ Ô CHỌN VỀ CHỖ CŨ KHI NGƯỜI DÙNG BẤM "HUỶ"
    // ========================================================================

    if (hopThoai) {
        /*
         * Nghe sự kiện 'close' của chính hộp thoại dùng chung. Đăng ký SAU
         * confirm-dialog.js (file đó nạp trước trong khung quản trị), nên khi
         * người dùng đồng ý thì nó đã kịp gửi form và trang đang rời đi — việc
         * dưới đây chỉ còn ý nghĩa ở nhánh "Huỷ".
         *
         * Gỡ luôn mấy thuộc tính data-confirm vừa gắn: để lại thì lần bấm tiếp
         * theo trên cùng form (ví dụ nút "Đã nhận tiền" của thanh hàng loạt)
         * sẽ mang câu hỏi của thao tác trước.
         */
        hopThoai.addEventListener('close', function () {
            if (!dangHoi) return;

            if (hopThoai.returnValue !== 'ok') {
                dangHoi.sel.value = dangHoi.cu;
            }

            dangHoi.form.removeAttribute('data-confirm');
            dangHoi.form.removeAttribute('data-confirm-title');
            dangHoi.form.removeAttribute('data-confirm-ok');

            dangHoi = null;
        });
    }

    // ========================================================================
    // MỞ NGĂN KÉO BẰNG CÁCH BẤM VÀO DÒNG
    // ========================================================================

    /*
     * Cả dòng bấm được, nhưng chỉ ở những chỗ KHÔNG phải một thứ bấm được sẵn.
     * Không có bộ lọc này thì tick một ô chọn hay bấm nút "Đã nhận tiền" cũng
     * kéo theo một lần chuyển trang, và thao tác vừa bấm mất tăm.
     *
     * Địa chỉ lấy từ data-open chứ không tự ghép: nó đã mang sẵn bộ lọc và số
     * trang hiện tại, dựng lại ở đây là hai nơi cùng phải nhớ một luật.
     */
    Array.prototype.forEach.call(document.querySelectorAll('.aorow'), function (dong) {
        dong.addEventListener('click', function (ev) {
            if (!ev.target.closest) return;

            /* KHÔNG có 'form' trong danh sách này. Cả bảng nằm trong form thao
               tác hàng loạt, nên closest('form') khớp với mọi cú bấm và dòng
               sẽ không bao giờ mở ra. */
            if (ev.target.closest('a, button, input, select, label')) return;

            // Bấm để bôi đen một đoạn chữ (số điện thoại, mã đơn) thì đừng
            // chuyển trang — người dùng đang định chép nó ra.
            var chon = window.getSelection && window.getSelection();
            if (chon && String(chon).length > 0) return;

            var url = dong.getAttribute('data-open');
            if (url) window.location.href = url;
        });
    });

    // ========================================================================
    // ESC ĐÓNG NGĂN KÉO
    // ========================================================================

    var ngan = document.querySelector('.aodraw');
    var nen  = document.querySelector('.aodim');

    if (ngan && nen) {
        /* Đưa tiêu điểm bàn phím vào ngăn kéo vừa mở, nếu không thì bấm Tab
           tiếp theo rơi về đầu trang phía sau lớp nền mờ. */
        var dong1 = ngan.querySelector('.aodraw__x');
        if (dong1) dong1.focus();

        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape') return;

            // Còn hộp thoại xác nhận đang mở thì Esc là của nó, không phải của
            // ngăn kéo — <dialog> tự xử lý và ta không được cướp mất.
            if (hopThoai && hopThoai.open) return;

            window.location.href = nen.getAttribute('href');
        });
    }
})();
