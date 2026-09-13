/**
 * cart.js — đổi ô số lượng là gửi luôn, khỏi bấm nút thứ hai.
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này thì nút "Cập nhật" ngay cạnh ô chọn
 * hiện ra (xem `html.js .cqty__go` trong cart.css) và mọi thứ vẫn chạy bằng
 * một form POST thật. Cùng lối với ô chọn trạng thái ở khu quản trị và ô
 * chọn của trang danh mục.
 *
 * ⚠ ĐỪNG chuyển sang fetch() rồi tự vá lại con số. Trang giỏ còn phải tính
 * lại tạm tính, giảm giá, phí ship và tổng cộng — tất cả đều ở máy chủ, và
 * một bản sao phép tính ấy trong JavaScript là hai nguồn sự thật về TIỀN.
 */
(function () {
    'use strict';

    document.addEventListener('change', function (e) {
        var o = e.target;

        if (!o || !o.matches || !o.matches('select[data-cart-qty]')) {
            return;
        }

        var form = o.closest('form');

        if (!form) {
            return;
        }

        /* requestSubmit() chứ không submit(): nó đi qua sự kiện `submit` nên
           hộp xác nhận dùng chung (confirm-dialog.js) vẫn chặn được nếu về
           sau có ai gắn xác nhận lên form này. submit() nhảy qua, im lặng.

           Trình duyệt cũ không có requestSubmit thì lùi về submit() — vẫn
           đúng việc, chỉ mất cái móc kia. */
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
}());
