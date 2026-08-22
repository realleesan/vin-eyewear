<?php

/**
 * _layout/confirm-dialog.php — hộp thoại "bạn có chắc không" dùng chung.
 *
 * In MỘT LẦN cho mỗi trang có thao tác cần hỏi lại; assets/js/confirm-dialog.js
 * điền chữ vào rồi mở nó ra. Không phải mỗi nút một hộp thoại: chữ đọc từ
 * data-confirm của nút vừa bấm, nên thêm một nút mới ở đâu đó chỉ cần thêm
 * thuộc tính, không phải sửa file này.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DÙNG <dialog> THẬT, KHÔNG PHẢI MỘT <div> GIẢ HỘP THOẠI
 *
 * showModal() cho sẵn ba thứ mà một <div> phải tự dựng lại và thường dựng
 * thiếu: nền chặn tương tác với phần còn lại của trang, TIÊU ĐIỂM BÀN PHÍM bị
 * giữ trong hộp (Tab không lọt ra sau lưng), và phím Esc để đóng. Trình đọc
 * màn hình cũng tự hiểu đây là hộp thoại mà không cần role/aria-modal gõ tay.
 *
 * Nút "Giữ lại" mang autofocus — mở ra là tiêu điểm nằm ở lựa chọn AN TOÀN.
 * Bấm nhầm Enter thêm một lần nữa thì không mất gì.
 *
 * <form method="dialog"> là cách của HTML: bấm nút nào thì hộp đóng lại và
 * value của nút đó thành dialog.returnValue. Nhờ vậy phần JS không phải gắn sự
 * kiện cho từng nút, chỉ đọc một chuỗi lúc hộp đóng — kể cả khi khách đóng
 * bằng Esc (returnValue rỗng, tức là huỷ).
 * ─────────────────────────────────────────────────────────────────────────────
 */
?>
<dialog class="cfm" data-confirm-dialog aria-labelledby="cfm-title">
    <form method="dialog" class="cfm__panel">
        <h2 class="cfm__title" id="cfm-title" data-cfm-title>Xác nhận</h2>

        <p class="cfm__body" data-cfm-body></p>

        <div class="cfm__acts">
            <?php
            /* Hai nút TỰ MANG kiểu của mình (.cfm__act--*), không mượn
               .btn-primary/.btn-outline của components/ui.css.

               Vì hộp thoại này còn dùng ở KHU QUẢN TRỊ, mà layout bên đó chỉ
               nạp layout.css + admin.css — không có ui.css. Mượn lớp ở đó thì
               hai nút hiện ra trần trụi kiểu mặc định của trình duyệt, giữa
               một hộp thoại đã dựng xong. Token màu thì cả hai layout đều có
               vì chúng nằm trong layout.css. */
            ?>
            <button type="submit" value="" class="cfm__act cfm__act--huy" autofocus
                    data-cfm-cancel>Giữ lại</button>

            <button type="submit" value="ok" class="cfm__act cfm__act--ok"
                    data-cfm-ok>Xoá</button>
        </div>
    </form>
</dialog>
