/**
 * catalog.js — hai tiện ích nhỏ cho trang danh sách sản phẩm (/san-pham).
 *
 * CẢ HAI ĐỀU LÀ TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN ĐỂ TRANG CHẠY. Bộ lọc của
 * trang này là liên kết và form GET thật; tắt JavaScript thì mọi thứ vẫn lọc
 * được, chỉ mất đúng hai chỗ tiện tay:
 *
 *   1. Đổi ô "Sắp xếp theo" là gửi form luôn, không phải bấm "Áp dụng".
 *   2. Gõ vào ô "Tìm thương hiệu" là danh sách lọc ngay tại chỗ, không phải
 *      tải lại trang.
 *
 * Hai nút "Áp dụng" và "Lọc" bị CSS ẩn đi khi <html> có class .js (master.php
 * gắn class đó ngay đầu <head>). File này chỉ lo phần hành vi.
 */

(function () {
    'use strict';

    /* ------------------------------------------------------------------
       1. Đổi ô sắp xếp -> gửi form
       ------------------------------------------------------------------ */

    var sortSelect = document.getElementById('f-sort');

    if (sortSelect && sortSelect.form) {
        sortSelect.addEventListener('change', function () {
            sortSelect.form.submit();
        });
    }

    /* ------------------------------------------------------------------
       2. Lọc danh sách thương hiệu ngay khi gõ
       ------------------------------------------------------------------ */

    var brandForm = document.querySelector('[data-brand-filter]');

    if (!brandForm) return;

    var input = brandForm.querySelector('input[name="bq"]');
    var list  = brandForm.parentNode.querySelector('.pfacet__list');

    if (!input || !list) return;

    var rows = Array.prototype.slice.call(list.querySelectorAll('[data-brand]'));

    // Câu "không có thương hiệu nào khớp" — server đã in sẵn khi lọc phía nó
    // ra rỗng. Lọc phía trình duyệt thì phải tự dựng, nhưng chỉ dựng MỘT lần
    // rồi bật/tắt, không tạo lại mỗi lần gõ.
    var empty = list.querySelector('.pfacet__none');

    if (!empty) {
        empty = document.createElement('p');
        empty.className = 'pfacet__none';
        empty.textContent = 'Không có thương hiệu nào khớp.';
        empty.hidden = true;
        list.appendChild(empty);
    }

    // Không cho Enter tải lại trang: danh sách đã lọc sẵn trước mắt rồi.
    brandForm.addEventListener('submit', function (event) {
        event.preventDefault();
    });

    input.addEventListener('input', function () {
        // toLowerCase ở cả hai vế để gõ thường vẫn khớp tên viết hoa. Thuộc
        // tính data-brand server in ra đã là chữ thường sẵn.
        var needle = input.value.trim().toLowerCase();
        var shown  = 0;

        rows.forEach(function (row) {
            var match = needle === '' || row.getAttribute('data-brand').indexOf(needle) !== -1;
            row.hidden = !match;
            if (match) shown++;
        });

        empty.hidden = shown > 0;
    });
})();
