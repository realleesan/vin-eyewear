-- ============================================================================
-- 2026-09-10 — GỠ quy trình đối soát hai bước (X13)
--
-- Đây là file ĐI NGƯỢC của 2026-09-09-giao-dich-chua-khop.sql. Màn "Giao dịch
-- chưa khớp" đã bị gỡ khỏi mã nguồn theo yêu cầu, nên sáu cột vết của nó không
-- còn ai đọc và không còn ai ghi.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO CẦN FILE NÀY, TRONG KHI ĐÃ XOÁ FILE 09-09 KHỎI REPO
--
-- Xoá một file migration khỏi thư mục KHÔNG gỡ được thứ nó đã tạo ra. Máy nào
-- đã chạy 09-09 thì sáu cột nằm lại trong bảng vĩnh viễn, trong khi schema.sql
-- (nguồn cho máy cài mới) đã quay về bản không có chúng. Hai đường cho ra hai
-- lược đồ khác nhau là đúng thứ mà `php database/schema-check.php` sinh ra để
-- bắt, và nó sẽ báo lệch ở mọi máy đã lỡ chạy.
--
-- File này lấp khoảng đó. Trên máy CHƯA từng chạy 09-09 (kể cả hosting) nó
-- không làm gì cả — mọi bước đều hỏi information_schema trước.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- THỨ TỰ BẮT BUỘC: KHOÁ NGOẠI -> CHỈ MỤC -> CỘT
--
-- InnoDB không cho bỏ một chỉ mục đang đỡ khoá ngoại, và không cho bỏ một cột
-- đang nằm trong chỉ mục. Đảo thứ tự là nhận lỗi 1553 hoặc 1091 giữa chừng,
-- rồi file dừng lại ở trạng thái nửa vời.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- DỮ LIỆU: TRẢ 'cho_xac_nhan' VỀ 'no_order'
--
-- 'cho_xac_nhan' là giá trị do X13 sinh ra. Gỡ mã đi thì không còn chỗ nào
-- hiểu nó: SepayModel::handle() không đọc nó, và màn hình đọc nó cũng không
-- còn. Một dòng mang giá trị ấy sẽ nằm im mãi, không lọt vào bất kỳ danh sách
-- nào — tức tiền về mà không ai thấy.
--
-- 'no_order' là đúng nghĩa của những dòng đó SAU KHI gỡ: tiền đã về, chưa khớp
-- vào đơn nào. Chúng quay lại đúng chỗ chúng đứng trước khi có X13.
--
-- ⚠ KHÔNG khôi phục được ai đã gán và vì sao — ba cột `gan_*` bị bỏ cùng lúc.
-- Nếu máy nào đang có dòng `cho_xac_nhan` THẬT thì chụp lại bảng trước khi
-- chạy file này; ở thời điểm viết, không máy nào có (bảng rỗng ở local, và
-- hosting chưa bao giờ chạy 09-09).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN — mỗi bước hỏi information_schema trước, nên lần
-- thứ hai chỉ in ra "đã gỡ, bỏ qua". Đăng ký ở migrate.sh với loại `data`:
-- file này không tạo ra thứ gì để làm cột mốc, và nó vốn đã an toàn khi lặp.
-- ============================================================================

-- ── 1. KHOÁ NGOẠI ───────────────────────────────────────────────────────────
SET @co := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'sepay_transactions'
       AND CONSTRAINT_NAME = 'fk_sepay_gan_boi'
);
SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP FOREIGN KEY `fk_sepay_gan_boi`',
    'SELECT ''fk_sepay_gan_boi khong co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'sepay_transactions'
       AND CONSTRAINT_NAME = 'fk_sepay_xac_nhan_boi'
);
SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP FOREIGN KEY `fk_sepay_xac_nhan_boi`',
    'SELECT ''fk_sepay_xac_nhan_boi khong co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;


-- ── 2. CHỈ MỤC ──────────────────────────────────────────────────────────────
-- `idx_sepay_applied` cũng do 09-09 thêm, nên nó đi cùng: schema.sql sau khi
-- gỡ không khai nó, và để lại là tạo ra đúng chỗ lệch mà file này đang sửa.
SET @co := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND INDEX_NAME   = 'idx_sepay_applied'
);
SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP INDEX `idx_sepay_applied`',
    'SELECT ''idx_sepay_applied khong co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND INDEX_NAME   = 'idx_sepay_gan_boi'
);
SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP INDEX `idx_sepay_gan_boi`',
    'SELECT ''idx_sepay_gan_boi khong co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND INDEX_NAME   = 'idx_sepay_xac_nhan_boi'
);
SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions` DROP INDEX `idx_sepay_xac_nhan_boi`',
    'SELECT ''idx_sepay_xac_nhan_boi khong co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;


-- ── 3. DỮ LIỆU — trả trạng thái trung gian về chỗ cũ ────────────────────────
-- Chạy TRƯỚC khi bỏ cột: sau đó không còn `gan_ly_do` để mà đọc lại nữa.
-- Không cần hỏi cột tồn tại — `applied` là cột có từ đầu, và câu này không đổi
-- gì nếu không có dòng nào mang giá trị ấy.
UPDATE `sepay_transactions`
   SET `applied` = 'no_order'
 WHERE `applied` = 'cho_xac_nhan';


-- ── 4. SÁU CỘT ──────────────────────────────────────────────────────────────
SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND COLUMN_NAME  = 'gan_boi'
);
SET @sql := IF(@co > 0,
    'ALTER TABLE `sepay_transactions`
        DROP COLUMN `gan_boi`,
        DROP COLUMN `gan_luc`,
        DROP COLUMN `gan_ly_do`,
        DROP COLUMN `xac_nhan_boi`,
        DROP COLUMN `xac_nhan_luc`,
        DROP COLUMN `xac_nhan_ly_do`',
    'SELECT ''sau cot doi soat khong co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SELECT 'Xong: da go doi soat hai buoc (X13)' AS ket_qua;
