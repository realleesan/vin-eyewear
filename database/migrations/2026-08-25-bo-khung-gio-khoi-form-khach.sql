-- ============================================================================
-- NÂNG CẤP 2026-08-25
-- Bỏ trường "Khung giờ" khỏi form đặt lịch của KHÁCH
--
-- Đây là câu trả lời cho giả định A5 trong CLAUDE.md ("lịch hẹn theo ngày hay
-- khung giờ"): khách chỉ chọn NGÀY, cửa hàng gọi điện xác nhận rồi tự xếp giờ.
--
-- VÌ SAO NỚI NULL CHỨ KHÔNG DROP CỘT
--
-- Lịch hẹn cũ trong bảng đang có giờ thật, do khách chọn khi form còn ô ấy.
-- Đó là dữ liệu vận hành có ích (khách hay hẹn giờ nào) và là thứ nhân viên
-- tra lại khi khách gọi hỏi một mã lịch cũ. DROP COLUMN xoá vĩnh viễn phần
-- đó, mà A5 vẫn đang là giả định chưa được BA nghiệm thu — đảo lại quyết định
-- này thì cột NULL chỉ cần điền tiếp, còn cột đã drop thì dữ liệu không về.
--
-- Nên: nới cột cho phép NULL. Lịch mới ghi NULL, lịch cũ giữ nguyên giờ.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO HỎI TRƯỚC RỒI MỚI SỬA, THAY VÌ MỘT CÂU `MODIFY COLUMN` TRẦN
--
-- `MODIFY COLUMN` vốn đã chạy lại được: lần thứ hai nó đặt cột về đúng trạng
-- thái đang có, không báo lỗi. Nhưng nó THAY THẾ TOÀN BỘ định nghĩa cột, nên
-- "không báo lỗi" không có nghĩa là "không làm gì":
--
--   · Ai đó về sau đổi kiểu cột (VARCHAR(20) -> TIME chẳng hạn) rồi lỡ chạy
--     lại file này thì cột bị kéo NGƯỢC về varchar(20). Một migration cũ âm
--     thầm đảo việc của một migration mới hơn là kiểu hỏng khó truy nhất.
--   · MODIFY dựng lại cả bảng. Trên bảng lịch hẹn đã lớn thì đó là một lần
--     khoá bảng hoàn toàn vô ích.
--
-- Nên hỏi information_schema trước: cột đã cho phép NULL rồi thì bỏ qua hẳn.
-- Cùng cách với 2026-08-25-dong-y-dieu-khoan.sql — xem giải thích dài ở đó về
-- việc vì sao không dùng stored procedure (phpMyAdmin vướng `DELIMITER`).
-- ─────────────────────────────────────────────────────────────────────────────
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE và sẽ
-- xoá sạch đơn hàng, lịch hẹn, tài khoản khách.
-- ============================================================================

SET @cho_rong := (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'appointments'
       AND COLUMN_NAME  = 'time_slot'
);

SET @sql := IF(@cho_rong = 'NO',
    'ALTER TABLE `appointments`
        MODIFY COLUMN `time_slot` VARCHAR(20) NULL DEFAULT NULL',
    'SELECT ''appointments.time_slot da cho phep NULL, bo qua'' AS ghi_chu'
);

PREPARE cau_lenh FROM @sql;
EXECUTE cau_lenh;
DEALLOCATE PREPARE cau_lenh;
