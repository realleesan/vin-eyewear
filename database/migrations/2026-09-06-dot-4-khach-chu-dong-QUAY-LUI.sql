-- ============================================================================
-- QUAY LUI đợt 4 — gỡ bảng hoàn tiền và hai cột mới
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ⚠ FILE NÀY XOÁ DỮ LIỆU THẬT — khác ba đợt trước
--
-- Ba đợt trước quay lui chỉ mất cấu trúc. Ở đây, `refund_requests` mang MỌI
-- QUYẾT ĐỊNH CHI TIỀN đã duyệt: số tiền chốt, ai duyệt, lúc nào, vì sao khác
-- số đề nghị, và ngày tiền rời tài khoản cửa hàng.
--
-- Gỡ bảng là mất hết. Không có nơi nào khác giữ những con số đó — hệ thống
-- không tự chuyển tiền nên không có giao dịch ngân hàng nào để đối chiếu ngược.
--
-- ĐẾM TRƯỚC KHI CHẠY. Ra khác 0 thì dừng lại và nghĩ kỹ:
--
--   SELECT status, COUNT(*), SUM(COALESCE(approved_amount, 0))
--     FROM refund_requests GROUP BY status;
--
-- Nếu vẫn phải quay lui mà có dữ liệu, hãy XUẤT BẢNG RA TRƯỚC:
--
--   mysqldump -u <user> -p <ten_csdl> refund_requests > refund-requests-luu.sql
--
-- ─────────────────────────────────────────────────────────────────────────────
-- HAI CỘT KIA THÌ VÔ HẠI
--
-- `users.deleted_source` và `orders.cancelled_by` chỉ ghi thêm ngữ cảnh cho
-- những việc vốn đã xảy ra; gỡ chúng không làm hỏng dòng nào. Mất phần phân
-- biệt "khách tự xoá / nhân viên xoá" và "ai huỷ đơn" — đọc lại được gần đúng
-- từ nhật ký thao tác, chỉ mất công.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- DROP TABLE IF EXISTS là idempotent; hai lệnh bỏ cột đi qua PREPARE/EXECUTE
-- hỏi information_schema trước.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. Gỡ bảng hoàn tiền — ĐỌC KHỐI CẢNH BÁO Ở ĐẦU FILE TRƯỚC
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `refund_requests`;


-- ----------------------------------------------------------------------------
-- 2. Gỡ hai cột ngữ cảnh
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'cancelled_by');
SET @sql := IF(@co = 1,
    'ALTER TABLE `orders` DROP COLUMN `cancelled_by`',
    'SELECT ''orders.cancelled_by da go, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'deleted_source');
SET @sql := IF(@co = 1,
    'ALTER TABLE `users` DROP COLUMN `deleted_source`',
    'SELECT ''users.deleted_source da go, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- SAU KHI CHẠY
--
--   SHOW TABLES LIKE 'refund_requests';             -- không ra dòng nào
--   SHOW COLUMNS FROM orders LIKE 'cancelled_by';   -- không ra dòng nào
--   SHOW COLUMNS FROM users  LIKE 'deleted_source'; -- không ra dòng nào
--
-- Rồi deploy lại mã nguồn TRƯỚC đợt 4. Tài khoản khách đã tự xoá vẫn ở trạng
-- thái xoá mềm — mã cũ đọc `deleted_at` nên vẫn hiểu đúng, chỉ không biết ai
-- đã xoá.
-- ----------------------------------------------------------------------------
