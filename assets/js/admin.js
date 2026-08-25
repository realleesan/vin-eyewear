/**
 * admin.js — tiện ích nhỏ cho khu quản trị.
 *
 * Cải tiến dần: mọi thao tác đều gửi được bằng nút "Lưu" khi không có
 * JavaScript. File này chỉ bỏ bớt một cú bấm.
 */

(function () {
    'use strict';

    // Báo cho CSS biết JS đã chạy -> ẩn nút "Lưu" thừa cạnh ô chọn.
    // Đặt trên <html> chứ không phải <body> để CSS áp dụng ngay từ đầu.
    document.documentElement.classList.add('js');

    /* Ô chọn trạng thái: đổi là gửi luôn, không phải bấm Lưu. */
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) select.form.submit();
        });
    });

    /*
     * ────────────────────────────────────────────────────────────────────
     * BỎ ẢNH VỪA CHỌN NHẦM, TRƯỚC KHI BẤM LƯU
     *
     * ĐÂY LÀ CHỖ DUY NHẤT TRONG KHU QUẢN TRỊ BẮT BUỘC PHẢI CÓ JAVASCRIPT.
     * Không có cách nào bỏ lựa chọn của <input type="file"> bằng HTML thuần:
     * thuộc tính value của nó chỉ đặt lại được bằng mã. <button type="reset">
     * thì xoá sạch CẢ FORM — mất luôn tên, mô tả, ngày tháng vừa gõ.
     *
     * Nên không có JS thì cụm này KHÔNG hiện ra chút nào, và đường lùi vẫn còn
     * nguyên: chọn lại một file khác là trình duyệt tự thay cái cũ. Đó là hành
     * vi mặc định của ô file, không phải thứ ta phải dựng.
     *
     * Vì vậy toàn bộ giao diện dưới đây do JS DỰNG RA, không nằm sẵn trong
     * HTML — thà không có gì còn hơn để lại một dấu × bấm vào không làm gì.
     *
     * XOÁ TỪNG ẢNH, KHÔNG XOÁ SẠCH
     *
     * Ô ảnh sản phẩm là `multiple`. Chọn năm ảnh mà nhầm một tấm thì bắt chọn
     * lại cả năm là phạt người dùng vì một lỗi nhỏ. DataTransfer cho dựng lại
     * FileList thiếu đúng tấm bị bỏ.
     *
     * Trình duyệt cũ không có DataTransfer thì lùi về xoá sạch — vẫn hơn là
     * không có nút nào.
     * ────────────────────────────────────────────────────────────────────
     */
    document.querySelectorAll('.aform__grid input[type="file"]').forEach(function (input) {
        var list = document.createElement('ul');
        list.className = 'apick';
        list.hidden = true;
        input.insertAdjacentElement('afterend', list);

        function coDataTransfer() {
            try { return typeof DataTransfer === 'function' && new DataTransfer().items; }
            catch (e) { return false; }
        }

        function bo(index) {
            if (!coDataTransfer()) {
                input.value = '';
                ve();
                input.focus();
                return;
            }

            var dt = new DataTransfer();

            Array.prototype.forEach.call(input.files, function (f, i) {
                if (i !== index) dt.items.add(f);
            });

            input.files = dt.files;
            ve();
            input.focus();
        }

        function ve() {
            list.textContent = '';

            var files = input.files;

            if (!files || !files.length) {
                list.hidden = true;
                return;
            }

            Array.prototype.forEach.call(files, function (f, i) {
                var li = document.createElement('li');
                li.className = 'apick__item';

                var ten = document.createElement('span');
                ten.className = 'apick__name';
                ten.textContent = f.name;

                var x = document.createElement('button');
                // type="button" BẮT BUỘC: mặc định của <button> trong form là
                // submit, bấm bỏ một ảnh sẽ gửi luôn cả form.
                x.type = 'button';
                x.className = 'apick__x';
                x.setAttribute('aria-label', 'Bỏ ảnh ' + f.name);
                x.textContent = '\u00D7';
                x.addEventListener('click', function () { bo(i); });

                li.appendChild(ten);
                li.appendChild(x);
                list.appendChild(li);
            });

            list.hidden = false;
        }

        input.addEventListener('change', ve);
    });

    /*
     * Cảnh báo khi rời trang mà còn ô tồn kho chưa lưu.
     *
     * Chỉ theo dõi ô số của form tồn kho: đó là chỗ dễ gõ xong rồi chuyển
     * trang mà quên bấm Lưu, và mất số liệu kiểm kê thì phải đếm lại kho.
     */
    var dirty = false;

    document.querySelectorAll('.ainv__form input[type="number"]').forEach(function (input) {
        var initial = input.value;
        input.addEventListener('input', function () {
            if (input.value !== initial) dirty = true;
        });
        // Bấm Lưu là coi như đã xử lý xong
        if (input.form) {
            input.form.addEventListener('submit', function () { dirty = false; });
        }
    });

    window.addEventListener('beforeunload', function (e) {
        if (!dirty) return;
        e.preventDefault();
        // Trình duyệt hiện thông điệp mặc định của nó; chuỗi trả về chỉ để
        // tương thích với bản cũ.
        e.returnValue = '';
    });
})();
