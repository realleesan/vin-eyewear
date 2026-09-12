<?php

/**
 * admin/filter-options/chua-nang-cap.php — chưa có bảng `filter_overrides`.
 *
 * Controller: Admin/FilterOptionAdminController::index()
 *
 * Cùng lý lẽ với admin/lens-options/chua-nang-cap.php: thiếu bảng thì mọi câu
 * SQL của màn này trả lỗi 1146 và người mở trang chỉ thấy "500 Internal Server
 * Error", không có gì nói cho họ biết phải làm gì.
 *
 * Ở đây chuyện còn nhẹ hơn hẳn, và đó là phần quan trọng nhất của trang này:
 * BỘ LỌC NGOÀI KIA CHẠY ĐÚNG NHƯ CHƯA TỪNG CÓ TÍNH NĂNG NÀY. Bảng
 * `filter_overrides` chỉ chứa phần TUỲ BIẾN — tên gọi, gộp, ẩn, thứ tự. Không
 * có nó thì mọi tiêu chí vẫn được rút ra từ dữ liệu hàng và hiện bằng tên máy
 * tự dựng, y như hôm qua. Không sản phẩm nào biến mất, không liên kết nào hỏng.
 */
?>
<header class="ahead">
    <h1 class="ahead__title">Tiêu chí lọc</h1>
    <p class="ahead__lead">Cơ sở dữ liệu chưa được nâng cấp cho phần này</p>
</header>

<div class="anote anote--alert">
    <p>
        Bảng <code>filter_overrides</code> chưa được tạo, nên chưa đặt lại tên, gộp
        hay ẩn tiêu chí lọc được.
    </p>
    <p>
        <strong>Trang bán hàng vẫn chạy bình thường.</strong> Bộ lọc ở
        <a href="/san-pham/gong-kinh">/san-pham/gong-kinh</a> vẫn đủ tiêu chí — chúng
        được rút ra từ chính chữ trong ô dáng gọng, chất liệu, giới tính và màu của
        từng biến thể, không phụ thuộc bảng này. Bảng chỉ thêm phần tuỳ biến.
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
        <code>database/migrations/2026-09-13-tuy-bien-tieu-chi-loc.sql</code>.
    </p>
</div>
