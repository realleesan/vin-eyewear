-- ============================================================================
-- NÂNG CẤP 2026-08-25 (sau 2026-08-25-bo-khung-gio-khoi-form-khach.sql)
-- Bỏ HẲN cột `appointments.time_slot`
--
-- Bước trước mới nới cột cho phép NULL: khách thôi chọn giờ, nhưng nhân viên
-- vẫn chốt được giờ trong khu quản trị và lịch cũ vẫn giữ giờ đã đặt.
--
-- Nay bỏ luôn khái niệm giờ khỏi lịch hẹn. Cửa hàng chốt giờ qua điện thoại và
-- không cần hệ thống lưu lại con số đó: cái hẹn được xác lập trong cuộc gọi,
-- còn ở đây chỉ cần biết khách đến NGÀY nào.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ĐÂY LÀ THAO TÁC KHÔNG ĐẢO LẠI ĐƯỢC
--
-- Giờ của mọi lịch hẹn cũ — thứ khách đã chọn khi form còn ô ấy — MẤT VĨNH
-- VIỄN sau khi chạy file này. Không có bản sao nào trong lược đồ.
--
-- Bước trước cố ý KHÔNG drop mà chỉ nới NULL, đúng vì lý do đó: A5 là giả định
-- chưa được BA nghiệm thu. Quyết định drop ở đây là lựa chọn có ý thức của
-- người vận hành, không phải hệ quả kỹ thuật — nếu sau này A5 bị đảo, cột dựng
-- lại được nhưng dữ liệu cũ thì không.
--
-- SAO LƯU BẢNG `appointments` TRƯỚC KHI CHẠY nếu giờ của lịch cũ còn giá trị
-- tra cứu với cửa hàng.
-- ─────────────────────────────────────────────────────────────────────────────
--
-- Chạy lại nhiều lần không hỏng: hỏi information_schema trước, cột không còn
-- thì bỏ qua. MySQL 8 không có `DROP COLUMN IF EXISTS`, và không dùng stored
-- procedure vì file này được dán tay vào phpMyAdmin (không SSH trên hosting) —
-- xem giải thích dài trong 2026-08-25-dong-y-dieu-khoan.sql.
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE.
-- ============================================================================

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'appointments'
       AND COLUMN_NAME  = 'time_slot'
);

SET @sql := IF(@co_cot = 1,
    'ALTER TABLE `appointments` DROP COLUMN `time_slot`',
    'SELECT ''appointments.time_slot da bo, bo qua'' AS ghi_chu'
);

PREPARE cau_lenh FROM @sql;
EXECUTE cau_lenh;
DEALLOCATE PREPARE cau_lenh;
