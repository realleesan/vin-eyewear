/**
 * account.js — trang tài khoản (/tai-khoan)
 *
 * CHỈ LÀ TĂNG CƯỜNG. Không có file này trang vẫn chạy đủ: chọn ảnh xong bấm
 * nút "Tải ảnh lên" là được. File này bỏ đi cái bấm thứ hai đó — bấm vào hình
 * tròn, chọn ảnh, xong.
 *
 * Nút "Tải ảnh lên" bị CSS ẩn khi có JS (.js .acct-nav__send), và class .js do
 * <script> đầu master.php đặt — nên không có kịch bản nào mà cả nút lẫn chức
 * năng tự gửi đều vắng mặt.
 */
(function () {
    'use strict';

    var input = document.querySelector('.acct-nav__face input[type="file"]');
    if (!input) return;

    var form = input.form;
    if (!form) return;

    input.addEventListener('change', function () {
        if (!input.files || !input.files.length) return;

        // Chặn tại chỗ để khách biết ngay, khỏi chờ một vòng lên máy chủ.
        // Máy chủ VẪN kiểm lại — xem core/AvatarStorage.php; giới hạn ở đây
        // sửa được bằng công cụ nhà phát triển nên nó không phải hàng rào.
        var limit = form.querySelector('input[name="MAX_FILE_SIZE"]');

        if (limit && input.files[0].size > Number(limit.value)) {
            window.alert('Ảnh vượt quá dung lượng cho phép (tối đa 1 MB).');
            input.value = '';
            return;
        }

        form.submit();
    });
}());
