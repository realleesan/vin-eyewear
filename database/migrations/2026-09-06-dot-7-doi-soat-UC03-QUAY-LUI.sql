-- ============================================================================
-- QUAY LUI đợt 7 — PHẦN UC-03. Gỡ cột hồ sơ nguồn trên dòng hàng.
--
-- TÁCH RIÊNG khỏi `2026-09-06-dot-7-doi-soat-QUAY-LUI.sql` là chủ ý: migration
-- xuôi gói hai việc vào một file vì chúng đi cùng một lần deploy, nhưng chúng
-- hỏng độc lập với nhau. Lùi sổ đối soát mà kéo theo cả liên kết hồ sơ đo mắt
-- của hàng trăm đơn là chữa một thứ bằng cách phá một thứ khác.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MẤT GÌ
--
--   prescription_id   MẤT đường tra ngược "đơn này cắt theo hồ sơ đo mắt nào"
--                     (UC-03 bước 5). Màn đơn hàng thôi hiện dòng "Cắt theo hồ
--                     sơ đo mắt ngày …".
--
--                     SỐ ĐO THẬT KHÔNG MẤT: cột `prescription` giữ nguyên chuỗi
--                     đã chốt lúc đặt, và đó mới là thứ đi xuống phiếu mài. Sổ
--                     đo mắt của khách cũng không đụng tới — file này chỉ cắt
--                     LIÊN KẾT, không xoá bản ghi nào.
--
-- ĐẾM TRƯỚC KHI CHẠY:
--
--   SELECT COUNT(*) FROM order_items WHERE prescription_id IS NOT NULL;
--
-- Khác 0 thì XUẤT RA TRƯỚC:
--
--   SELECT id, order_id, prescription_id
--     FROM order_items WHERE prescription_id IS NOT NULL;
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MÃ NGUỒN CHỊU ĐƯỢC FILE NÀY, khác phần sổ đối soát.
--
-- OrderModel::coHoSoNguon() hỏi trước ở cả ba chỗ đụng tới cột: place() bỏ khoá
-- ra khỏi câu INSERT, và items() dựng câu SELECT không nhắc tới nó. Chạy file
-- này KHÔNG bắt buộc deploy ngược — bước chọn hồ sơ trong hộp mua hàng vẫn
-- chạy, chỉ là đơn thôi ghi lại mã nguồn.
--
-- KHUÔN PREPARE/EXECUTE nên chạy lại nhiều lần không lỗi.
-- ============================================================================


-- ---------------------------------------------------------------------------
-- Khoá ngoại TRƯỚC, rồi mới tới cột.
--
-- InnoDB từ chối DROP một cột đang là chân của khoá ngoại. Khoá có thể KHÔNG
-- tồn tại dù cột có: migration xuôi bỏ qua phần khoá khi `customer_prescriptions`
-- chưa có. Nên hai bước tách rời và mỗi bước tự hỏi.
-- ---------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'order_items'
       AND CONSTRAINT_NAME = 'fk_order_items_prescription'
);

SET @sql := IF(@co > 0,
    'ALTER TABLE `order_items` DROP FOREIGN KEY `fk_order_items_prescription`',
    'SELECT ''fk_order_items_prescription không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'order_items'
       AND COLUMN_NAME  = 'prescription_id'
);

SET @sql := IF(@co > 0,
    'ALTER TABLE `order_items` DROP COLUMN `prescription_id`',
    'SELECT ''order_items.prescription_id không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- SAU KHI CHẠY
--
--   SHOW COLUMNS FROM `order_items` LIKE 'prescription%';   -- chỉ còn 1 dòng
--
-- Rồi mở một đơn có cắt tròng trong khu quản trị: số đo vẫn hiện, chỉ mất dòng
-- "Cắt theo hồ sơ đo mắt ngày …".
-- ----------------------------------------------------------------------------
