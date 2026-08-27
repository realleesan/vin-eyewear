<?php

/**
 * admin/lens-prices/packages-chua-nang-cap.php — chưa có bảng `lens_packages`.
 *
 * Controller: Admin/LensPriceAdminController::packages()
 *
 * VÌ SAO CÓ HẲN MỘT TRANG CHO VIỆC NÀY thay vì để nó đổ lỗi 500:
 *
 * Cùng lý do đã ghi ở admin/customers/chua-nang-cap.php. Thiếu bảng thì mọi
 * câu SQL của màn này trả lỗi 1146 và người mở trang chỉ thấy "500 Internal
 * Server Error" — không có gì nói cho họ biết phải làm gì, và kết luận đầu
 * tiên bao giờ cũng là "deploy hỏng".
 *
 * Riêng ở đây có một chuyện đáng nói thêm: TRANG BÁN HÀNG VẪN ĐANG CHẠY BÌNH
 * THƯỜNG. LensModel::packages() lùi về mảng trong config/taxonomy.php khi
 * chưa có bảng, nên khách vẫn thấy đúng năm gói và vẫn mua được. Người đọc
 * trang này cần biết điều đó, nếu không họ sẽ tưởng cửa hàng đang đóng.
 */
?>
<header class="ahead">
    <h1 class="ahead__title">Gói chiết suất</h1>
    <p class="ahead__lead">Cơ sở dữ liệu chưa được nâng cấp cho phần này</p>
</header>

<div class="anote anote--alert">
    <p>
        Bảng <code>lens_packages</code> chưa được tạo, nên chưa thêm/sửa/xoá gói được.
    </p>
    <p>
        <strong>Trang bán hàng vẫn chạy bình thường.</strong> Danh mục gói đang được đọc
        từ <code>config/taxonomy.php</code> — khách vẫn thấy đủ năm gói và vẫn đặt hàng
        được. Bảng giá ở <a href="/quan-tri/gia-trong">/quan-tri/gia-trong</a> cũng vẫn
        sửa được như thường.
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
        <code>database/migrations/2026-08-27-bang-goi-trong.sql</code>.
    </p>
    <p>
        File chạy lại nhiều lần không hỏng, nên nạp nhầm hai lần cũng không sao.
        Nó <strong>không</strong> xoá dữ liệu nào — chỉ thêm một bảng và chép vào đó
        đúng năm gói đang có.
    </p>
</div>
