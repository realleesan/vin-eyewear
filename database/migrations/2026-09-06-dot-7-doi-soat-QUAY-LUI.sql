-- ============================================================================
-- QUAY LUI đợt 7 — PHẦN SỔ ĐỐI SOÁT (FR-SG). Không đụng tới UC-03.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MẤT GÌ
--
--   gan_boi · gan_luc   MẤT dấu vết "ai đã gắn tay giao dịch này vào đơn nào,
--                       lúc nào". Bản thân việc gắn KHÔNG mất — `order_id` và
--                       `applied` là cột riêng, file này không đụng tới, nên
--                       giao dịch vẫn khớp đúng đơn.
--
--                       Và vết kiểm toán đầy đủ vẫn còn nguyên trong
--                       `customer_audit_logs` dưới mã `sepay.link_order` — đó
--                       mới là nơi SNFR-11 buộc ghi. Hai cột này chỉ là bản sao
--                       tiện đọc ngay trên dòng sổ.
--
--   idx_sepay_so        không mất dữ liệu, chỉ chậm lại.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- FILE NÀY KHÔNG ĐỤNG TỚI `order_items`.`prescription_id`
--
-- Migration xuôi gói cả hai việc vào một file vì chúng đi cùng một lần deploy.
-- Quay lui thì KHÔNG được gói: hai việc ấy hỏng độc lập với nhau.
--
-- Ca thật: sổ đối soát có vấn đề và phải lùi, trong khi UC-03 đã chạy êm mấy
-- tuần và hàng trăm đơn đã ghi mã hồ sơ nguồn. Một file quay lui chung sẽ xoá
-- sạch những liên kết ấy để chữa một thứ chẳng liên quan gì.
--
-- Phần UC-03 nằm ở `2026-09-06-dot-7-doi-soat-UC03-QUAY-LUI.sql`.
--
-- ĐẾM TRƯỚC KHI CHẠY:
--
--   SELECT COUNT(*) FROM sepay_transactions WHERE gan_boi IS NOT NULL;
--
-- Khác 0 thì XUẤT RA TRƯỚC:
--
--   SELECT id, sepay_id, order_code, gan_boi, gan_luc
--     FROM sepay_transactions WHERE gan_boi IS NOT NULL;
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MÃ NGUỒN ĐỢT 7 KHÔNG CHẠY ĐƯỢC SAU KHI QUAY LUI — KHÁC HẲN ĐỢT 6
--
-- Đợt 6 gỡ hai BẢNG, và mọi chỗ đụng tới chúng đều hỏi available() trước nên
-- trang vẫn chạy. Ở đây là hai CỘT trên những bảng luôn tồn tại, và màn Sổ giao
-- dịch đọc thẳng chúng.
--
-- SepayModel::coGanTay() có hỏi trước và màn hình tự bỏ hai cột ấy đi khi thiếu,
-- nhưng ĐỪNG dựa vào đó: chạy file này thì DEPLOY NGƯỢC mã nguồn về trước đợt 7.
--
-- KHUÔN PREPARE/EXECUTE nên chạy lại nhiều lần không lỗi.
-- ============================================================================


-- ---------------------------------------------------------------------------
-- 1. Gỡ khoá ngoại TRƯỚC, rồi mới gỡ cột.
--
-- InnoDB từ chối DROP một cột đang là chân của khoá ngoại, và gỡ khoá cũng gỡ
-- luôn chỉ mục đi kèm nếu không còn ai dùng. Thứ tự ngược lại là lỗi 1553 —
-- đúng cái đã gặp ở migration 2026-08-22-bo-gioi-han-khung-gio.sql.
-- ---------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'sepay_transactions'
       AND CONSTRAINT_NAME = 'fk_sepay_gan_boi'
);

SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP FOREIGN KEY `fk_sepay_gan_boi`',
    'SELECT ''fk_sepay_gan_boi không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND COLUMN_NAME  = 'gan_boi'
);

SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP COLUMN `gan_boi`',
    'SELECT ''gan_boi không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND COLUMN_NAME  = 'gan_luc'
);

SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP COLUMN `gan_luc`',
    'SELECT ''gan_luc không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ---------------------------------------------------------------------------
-- 2. Chỉ mục của màn sổ.
-- ---------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND INDEX_NAME   = 'idx_sepay_so'
);

SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP INDEX `idx_sepay_so`',
    'SELECT ''idx_sepay_so không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- SAU KHI CHẠY
--
--   SHOW COLUMNS FROM `sepay_transactions` LIKE 'gan_%';   -- không dòng nào
--
-- CỘT `order_items`.`prescription_id` KHÔNG BỊ ĐỤNG TỚI — nó thuộc UC-03, và
-- quay lui riêng bằng file *-UC03-QUAY-LUI.sql. Xem khối cảnh báo ở đầu file.
--
-- Và deploy ngược mã nguồn về trước đợt 7.
-- ----------------------------------------------------------------------------
