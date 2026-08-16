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
