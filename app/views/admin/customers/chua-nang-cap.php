<?php

/**
 * admin/customers/chua-nang-cap.php — cơ sở dữ liệu chưa chạy migration.
 *
 * Controller: Admin/CustomerAdminController::sanSang()
 *
 * VÌ SAO CÓ HẲN MỘT TRANG CHO VIỆC NÀY thay vì để nó đổ lỗi 500:
 *
 * Cả module dựa vào năm cột thêm vào `users`. Thiếu chúng thì mọi câu SQL đều
 * trả lỗi 1054 và người mở trang chỉ thấy "500 Internal Server Error" — không
 * có gì nói cho họ biết phải làm gì, và kết luận đầu tiên bao giờ cũng là
 * "deploy hỏng". Đúng chuyện đã xảy ra ngày 2026-08-22 với năm cột wear_*.
 *
 * Hosting InfinityFree bản miễn phí không có SSH, nên câu hướng dẫn phải nói
 * được cả đường phpMyAdmin — đó là đường duy nhất chạy được ở đó.
 */
?>
<header class="ahead">
    <h1 class="ahead__title">Khách hàng</h1>
    <p class="ahead__lead">Cơ sở dữ liệu chưa được nâng cấp cho module này</p>
</header>

<div class="anote anote--alert">
    <p>
        Bảng <code>users</code> còn thiếu các cột trạng thái tài khoản, và ba bảng
        <code>customer_prescriptions</code>, <code>customer_notes</code>,
        <code>customer_audit_logs</code> chưa được tạo.
    </p>
    <p><strong>Cách chạy — chọn một trong hai:</strong></p>
    <p>
        Máy có dòng lệnh:
        <code>sudo bash database/migrate.sh</code>
        (thêm <code>--status</code> để xem trước những gì sẽ chạy).
    </p>
    <p>
        Trên hosting: mở <strong>phpMyAdmin</strong> → chọn đúng cơ sở dữ liệu →
        tab <strong>Import</strong> → nạp file
        <code>database/migrations/2026-08-26-module-khach-hang.sql</code>.
    </p>
    <p>
        File chạy lại nhiều lần không hỏng, nên nạp nhầm hai lần cũng không sao.
        Nó <strong>không</strong> xoá dữ liệu nào — chỉ thêm cột và thêm bảng.
    </p>
</div>
