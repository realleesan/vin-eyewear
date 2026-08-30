<?php

/**
 * admin/lens-options/chua-nang-cap.php — chưa có bảng `lens_options`.
 *
 * Controller: Admin/LensOptionAdminController::index()
 *
 * Cùng lý lẽ với admin/lens-prices/packages-chua-nang-cap.php: thiếu bảng thì
 * mọi câu SQL của màn này trả lỗi 1146 và người mở trang chỉ thấy "500
 * Internal Server Error" — không có gì nói cho họ biết phải làm gì, và kết
 * luận đầu tiên bao giờ cũng là "deploy hỏng".
 *
 * Ở đây cũng có một chuyện đáng nói thêm, và nó là phần quan trọng nhất của
 * trang này: TRANG BÁN HÀNG VẪN CHẠY BÌNH THƯỜNG. LensOptionModel lùi về mảng
 * trong config khi chưa có bảng, nên bộ lọc tròng kính vẫn đủ loại tròng,
 * chiết suất và lớp phủ. Chỉ nhóm "Màu tròng" là trống, vì config chưa bao giờ
 * có danh sách màu.
 */
?>
<header class="ahead">
    <h1 class="ahead__title">Thuộc tính tròng</h1>
    <p class="ahead__lead">Cơ sở dữ liệu chưa được nâng cấp cho phần này</p>
</header>

<div class="anote anote--alert">
    <p>
        Bảng <code>lens_options</code> chưa được tạo, nên chưa thêm/sửa bốn danh sách
        thuộc tính tròng được.
    </p>
    <p>
        <strong>Trang bán hàng vẫn chạy bình thường.</strong> Bộ lọc ở
        <a href="/san-pham/trong-kinh">/san-pham/trong-kinh</a> đang đọc danh sách từ
        <code>config/eyewear.php</code> và <code>config/taxonomy.php</code> — khách vẫn
        lọc được theo loại tròng, chiết suất và lớp phủ. Riêng nhóm
        <strong>Màu tròng</strong> đang trống, vì hai file config ấy chưa bao giờ có
        danh sách màu.
    </p>
    <p><strong>Cách chạy — chọn một trong hai:</strong></p>
    <p>
        Máy có dòng lệnh:
        <code>sudo bash database/migrate.sh</code>
        (thêm <code>--status</code> để xem trước những gì sẽ chạy).
    </p>
    <p>
        Hosting không có SSH: mở phpMyAdmin, chọn database của site, vào tab
        <strong>SQL</strong> rồi dán nguyên nội dung file
        <code>database/migrations/2026-08-30-thuoc-tinh-trong-do-quan-tri-quan-ly.sql</code>.
    </p>
</div>
