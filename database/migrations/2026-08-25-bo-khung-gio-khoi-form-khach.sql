-- ============================================================================
-- Bỏ trường "Khung giờ" khỏi form đặt lịch của KHÁCH — 2026-08-25.
--
-- Đây là câu trả lời cho giả định A5 trong CLAUDE.md ("lịch hẹn theo ngày hay
-- khung giờ"): khách chỉ chọn NGÀY, cửa hàng gọi điện xác nhận rồi tự xếp giờ.
--
-- VÌ SAO NULL CHỨ KHÔNG DROP CỘT
--
-- Lịch hẹn cũ trong bảng đang có giờ thật, do khách chọn khi form còn ô ấy.
-- Đó là dữ liệu vận hành có ích (khách hay hẹn giờ nào) và là thứ nhân viên
-- tra lại khi khách gọi hỏi một mã lịch cũ. DROP COLUMN xoá vĩnh viễn phần
-- đó, mà A5 vẫn đang là giả định chưa được BA nghiệm thu — đảo lại quyết định
-- này thì cột NULL chỉ cần điền tiếp, còn cột đã drop thì dữ liệu không về.
--
-- Nên: nới cột cho phép NULL. Lịch mới ghi NULL, lịch cũ giữ nguyên giờ.
--
-- Chạy lại nhiều lần không hỏng: MODIFY COLUMN đặt cột về đúng trạng thái
-- mong muốn dù nó đang NOT NULL hay đã NULL sẵn.
-- ============================================================================

ALTER TABLE `appointments`
    MODIFY COLUMN `time_slot` VARCHAR(20) NULL DEFAULT NULL
    COMMENT 'Giờ hẹn. NULL = lịch đặt sau 2026-08-25, khách chỉ chọn ngày; cửa hàng xếp giờ qua điện thoại.';
