-- ============================================================================
-- QUAY LUI đợt 2 — nới ENUM `user_roles`.`role` trở lại năm giá trị
--
-- ─────────────────────────────────────────────────────────────────────────────
-- FILE NÀY KHÔNG TRẢ AI VỀ VAI TRÒ CŨ
--
-- Nó chỉ làm cho cột `role` NHẬN LẠI hai giá trị 'technician' và 'manager'.
-- Ai từng mang chúng thì không biết được nữa: câu UPDATE ở migration đã ghi đè
-- nhãn, và sau đó không còn gì phân biệt một người "vốn là Quản lý cơ sở" với
-- một người "vốn đã là Quản trị viên".
--
-- Muốn đúng người thì nạp bản sao lưu tạo trước khi chạy migration:
--
--   mysql -u <user> -p <ten_csdl> < vin-eyewear-truoc-dot-2.sql
--
-- File này chỉ dùng cho một tình huống: mã nguồn CŨ đã được deploy ngược trở
-- lại, ô chọn vai trò của nó vẫn bày "Kỹ thuật viên" và "Quản lý", và lần lưu
-- đầu tiên ném lỗi 1265 "Data truncated for column 'role'". Nới ENUM ra là hết
-- lỗi ngay, còn ai giữ vai trò nào thì xử lý sau bằng bản sao lưu.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- KHÔNG CÓ RỦI RO MẤT DỮ LIỆU
--
-- Nới một ENUM là thao tác THÊM giá trị, không giá trị nào đang dùng bị đụng
-- tới. Chạy lại nhiều lần cho cùng kết quả.
-- ============================================================================

ALTER TABLE `user_roles`
    MODIFY COLUMN `role`
    ENUM('customer','staff','technician','manager','admin') NOT NULL;


-- ----------------------------------------------------------------------------
-- SAU KHI CHẠY
--
--   SHOW COLUMNS FROM user_roles LIKE 'role';
--       -- Type = enum('customer','staff','technician','manager','admin')
--
-- Rồi deploy lại mã nguồn TRƯỚC đợt 2. Cấu trúc khớp thì khu quản trị chạy
-- lại được; ai đang mang vai trò nào thì vẫn là kết quả sau migration cho tới
-- khi nạp bản sao lưu.
-- ----------------------------------------------------------------------------
