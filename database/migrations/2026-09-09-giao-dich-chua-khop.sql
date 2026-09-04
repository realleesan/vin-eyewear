-- ============================================================================
-- 2026-09-09 — Giao dịch chưa khớp: quy trình HAI BƯỚC
--
-- Căn cứ: SRS v1.3.1 mục 3.2.6.2 và 3.2.9.3, quyết định X13 (chốt lại
-- 04/09/2026, thay câu Q49.3 của vòng 1).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- HAI BƯỚC, HAI NGƯỜI — VÀ ĐÓ LÀ TOÀN BỘ ĐIỂM CỦA X13
--
-- Một khoản tiền về tài khoản mà nội dung chuyển khoản không mang mã đơn nào
-- thì hệ thống ghi `applied = 'no_order'` rồi đứng im. Ai đó phải nhìn sao kê,
-- đoán ra nó là của đơn nào, và gán tay.
--
-- Bản chốt vòng 1 (Q49.3) giao cả việc ấy cho Quản trị viên. Rà soát độc lập
-- chỉ ra: đối soát là việc HẰNG NGÀY, còn Quản trị viên thì có một người —
-- dồn cả hai việc cho họ biến một thao tác thường nhật thành nút cổ chai, và
-- nút cổ chai ở khâu tiền nghĩa là đơn của khách nằm chờ.
--
-- X13 tách làm hai:
--
--   BƯỚC 1  nhân viên GÁN giao dịch vào một đơn, kèm lý do
--   BƯỚC 2  Quản lý cơ sở XÁC NHẬN, kèm lý do — lúc này tiền mới vào đơn
--
-- Người gán KHÔNG được tự xác nhận. Đây không phải nghi ngờ nhân viên: gán
-- tiền vào đơn là thao tác duy nhất trong hệ thống mà một người có thể tự tay
-- biến một khoản tiền lạ thành "đơn này đã trả đủ" — bốn con mắt là biện pháp
-- tối thiểu cho một việc như vậy, và nó cũng bảo vệ chính người gán khi có
-- tranh chấp.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- SÁU CỘT, KHÔNG PHẢI MỘT CỘT TRẠNG THÁI
--
-- `applied` có thêm giá trị 'cho_xac_nhan' (VARCHAR nên không phải ALTER
-- ENUM). Nhưng chỉ một cột trạng thái thì không trả lời được câu mà người đọc
-- sổ sáu tháng sau sẽ hỏi: AI gán, LÚC NÀO, VÌ SAO — và ai đã duyệt.
--
--   gan_boi · gan_luc · gan_ly_do              bước 1
--   xac_nhan_boi · xac_nhan_luc · xac_nhan_ly_do   bước 2
--
-- Hai khoá ngoại đều SET NULL: người nghỉ việc bị xoá tài khoản thì bằng
-- chứng về việc đã gán vẫn phải còn. Cùng lý lẽ với `customer_audit_logs`.
--
-- LÝ DO LƯU CẢ Ở ĐÂY LẪN Ở BẢNG VẾT. Không phải chép thừa: người đối soát mở
-- màn "Giao dịch chưa khớp" và cần đọc lý do NGAY TRÊN DÒNG giao dịch, chứ
-- không phải mở thêm màn Nhật ký rồi tự khớp theo dấu thời gian.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- KHÔNG BACKFILL, VÀ KHÔNG ĐỘNG VÀO DÒNG ĐÃ CÓ
--
-- Mọi dòng `no_order` đang nằm sẵn trong bảng vẫn là `no_order` sau khi chạy
-- file này — chúng chỉ có thêm một con đường để đi tiếp. Đánh dấu hàng loạt
-- "đã xác nhận" cho những khoản chưa ai nhìn là đúng thứ quy trình này sinh ra
-- để ngăn.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN — mỗi cột và mỗi khoá ngoại đi qua một vòng
-- PREPARE/EXECUTE hỏi information_schema trước. Không xoá, không đổi kiểu cột.
-- ============================================================================

-- ── sáu cột vết của hai bước ────────────────────────────────────────────────
SET @co_cot := (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND COLUMN_NAME = 'gan_boi');
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `sepay_transactions`
        ADD COLUMN `gan_boi` CHAR(36) NULL DEFAULT NULL AFTER `applied`',
    'SELECT ''sepay_transactions.gan_boi da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND COLUMN_NAME = 'gan_luc');
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `sepay_transactions`
        ADD COLUMN `gan_luc` DATETIME NULL DEFAULT NULL AFTER `gan_boi`',
    'SELECT ''sepay_transactions.gan_luc da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND COLUMN_NAME = 'gan_ly_do');
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `sepay_transactions`
        ADD COLUMN `gan_ly_do` VARCHAR(255) NULL DEFAULT NULL AFTER `gan_luc`',
    'SELECT ''sepay_transactions.gan_ly_do da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND COLUMN_NAME = 'xac_nhan_boi');
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `sepay_transactions`
        ADD COLUMN `xac_nhan_boi` CHAR(36) NULL DEFAULT NULL AFTER `gan_ly_do`',
    'SELECT ''sepay_transactions.xac_nhan_boi da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND COLUMN_NAME = 'xac_nhan_luc');
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `sepay_transactions`
        ADD COLUMN `xac_nhan_luc` DATETIME NULL DEFAULT NULL AFTER `xac_nhan_boi`',
    'SELECT ''sepay_transactions.xac_nhan_luc da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND COLUMN_NAME = 'xac_nhan_ly_do');
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `sepay_transactions`
        ADD COLUMN `xac_nhan_ly_do` VARCHAR(255) NULL DEFAULT NULL AFTER `xac_nhan_luc`',
    'SELECT ''sepay_transactions.xac_nhan_ly_do da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ── chỉ mục cho màn đối soát ────────────────────────────────────────────────
/* Màn "Giao dịch chưa khớp" hỏi đúng MỘT câu: lấy các dòng đang chờ xử lý,
   mới nhất trước. Không có chỉ mục là quét cả bảng — mà bảng này chỉ có thêm
   chứ không bớt, nên nó lớn dần theo doanh thu. */
SET @co_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND INDEX_NAME = 'idx_sepay_applied');
SET @sql := IF(@co_idx = 0,
    'ALTER TABLE `sepay_transactions`
        ADD INDEX `idx_sepay_applied` (`applied`, `transaction_date`)',
    'SELECT ''idx_sepay_applied da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND INDEX_NAME = 'idx_sepay_gan_boi');
SET @sql := IF(@co_idx = 0,
    'ALTER TABLE `sepay_transactions` ADD INDEX `idx_sepay_gan_boi` (`gan_boi`)',
    'SELECT ''idx_sepay_gan_boi da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND INDEX_NAME = 'idx_sepay_xac_nhan_boi');
SET @sql := IF(@co_idx = 0,
    'ALTER TABLE `sepay_transactions` ADD INDEX `idx_sepay_xac_nhan_boi` (`xac_nhan_boi`)',
    'SELECT ''idx_sepay_xac_nhan_boi da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ── hai khoá ngoại, đều SET NULL ────────────────────────────────────────────
SET @co_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND CONSTRAINT_NAME = 'fk_sepay_gan_boi');
SET @sql := IF(@co_fk = 0,
    'ALTER TABLE `sepay_transactions`
        ADD CONSTRAINT `fk_sepay_gan_boi` FOREIGN KEY (`gan_boi`)
            REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_sepay_gan_boi da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sepay_transactions'
       AND CONSTRAINT_NAME = 'fk_sepay_xac_nhan_boi');
SET @sql := IF(@co_fk = 0,
    'ALTER TABLE `sepay_transactions`
        ADD CONSTRAINT `fk_sepay_xac_nhan_boi` FOREIGN KEY (`xac_nhan_boi`)
            REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_sepay_xac_nhan_boi da co, bo qua'' AS ghi_chu');
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SELECT 'Xong: giao dich chua khop — quy trinh hai buoc (X13)' AS ket_qua;
