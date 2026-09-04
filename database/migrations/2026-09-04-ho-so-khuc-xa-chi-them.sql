-- ============================================================================
-- 2026-09-04 — Hồ sơ khúc xạ: mô hình CHỈ-THÊM và các trường số đo còn thiếu
--
-- Căn cứ: phiếu chốt nghiệp vụ vòng 1 (câu Q63.1–Q63.7, Q65.1, Q65.2, Q66.1)
-- và phiếu gỡ mâu thuẫn vòng 2 (X21 = A, X23 = A, X24 = C, X25 = A).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHỈ-THÊM NGHĨA LÀ GÌ, VÀ VÌ SAO NÓ CẦN HAI CỘT
--
-- BA chốt: sửa một bản ghi khúc xạ KHÔNG ghi đè bản cũ — nó sinh một PHIÊN BẢN
-- mới, bản cũ giữ nguyên và vẫn đọc lại được. Nhân viên không có đường xoá dưới
-- bất kỳ hình thức nào, kể cả xoá mềm.
--
-- Chép đè là thứ đang chạy hôm nay (PrescriptionRecordModel::save gọi UPDATE),
-- và với dữ liệu y tế thì đó là mất bằng chứng: khách khiếu nại "các anh cắt
-- sai độ" mà bản ghi đã bị sửa thành số đúng thì không còn gì để đối chiếu.
--
--   ban_goc_id  nối mọi phiên bản của CÙNG MỘT LẦN ĐO về bản đầu tiên. Bản đầu
--               tiên tự trỏ vào chính nó, nên không có dòng nào mồ côi và một
--               câu GROUP BY là đủ để lấy "phiên bản mới nhất của từng lần đo".
--   phien_ban   số thứ tự trong nhóm, bắt đầu từ 1. Có ban_goc_id rồi vẫn cần
--               nó: hai phiên bản sinh trong cùng một giây thì created_at
--               không phân được thứ tự, mà thứ tự chính là thứ người đọc cần.
--   ly_do       lý do sửa, bắt buộc từ 10 ký tự (mã nguồn chặn, không phải CSDL
--               chặn — thông báo lỗi phải bằng tiếng Việt cho người nhập đọc).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- BỐN NHÓM CỘT SỐ ĐO MỚI
--
--   pd_od / pd_os     Q63.4 chốt PD tách theo TỪNG MẮT, 20–40 mm mỗi bên. Cột
--                     `pd` cũ là PD hai mắt (30–90 mm) và ĐƯỢC GIỮ NGUYÊN, không
--                     đổi tên, không backfill. Chia đôi số cũ để lấp hai cột mới
--                     là BỊA DỮ LIỆU Y TẾ: PD hai mắt hiếm khi cân bằng, và một
--                     con số bịa trông y hệt một con số đo thật. Bản ghi cũ để
--                     trống hai cột này; nơi đọc tự lùi về `pd`.
--   od_add / os_add   Q63.7 — độ cộng cho khách lão thị, 0 đến 3.50 bước 0.25.
--   *_seg_height      Q63.7 — chiều cao tâm tròng, cần khi mài tròng đa tròng.
--   *_va_num          Q63.6 — thị lực NHẬP và HIỂN THỊ dạng 10/10, nhưng LƯU
--                     dạng thập phân để so sánh và vẽ biểu đồ được. Cột `od_va`
--                     / `os_va` (VARCHAR) giữ nguyên văn người nhập gõ.
--   tech_note         Ghi chú của kỹ thuật viên — NỘI BỘ. Tách khỏi `note` vốn
--                     là ghi chú khách đọc được (Q65.3 cho khách xem lịch sử).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- MySQL 8 không có `ADD COLUMN IF NOT EXISTS` (MariaDB thì có), nên mỗi cột đi
-- qua một vòng PREPARE/EXECUTE hỏi information_schema trước — đúng lối đã dùng
-- ở 2026-08-26-module-khach-hang.sql. Các câu UPDATE backfill đều có mệnh đề
-- `IS NULL` nên chạy lần hai không đụng vào dữ liệu đã có.
--
-- KHÔNG CÓ BƯỚC NÀO XOÁ HAY ĐỔI KIỂU CỘT ĐANG CHỨA DỮ LIỆU. Rollback nếu cần:
-- các cột mới đều NULL được, bỏ đi bằng DROP COLUMN không mất dữ liệu cũ.
-- ============================================================================

-- pd_od
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'pd_od'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `pd_od` DECIMAL(4,1) NULL DEFAULT NULL AFTER `pd`',
    'SELECT ''customer_prescriptions.pd_od da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- pd_os
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'pd_os'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `pd_os` DECIMAL(4,1) NULL DEFAULT NULL AFTER `pd_od`',
    'SELECT ''customer_prescriptions.pd_os da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- od_add
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'od_add'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `od_add` DECIMAL(3,2) NULL DEFAULT NULL AFTER `pd_os`',
    'SELECT ''customer_prescriptions.od_add da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- os_add
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'os_add'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `os_add` DECIMAL(3,2) NULL DEFAULT NULL AFTER `od_add`',
    'SELECT ''customer_prescriptions.os_add da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- od_seg_height
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'od_seg_height'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `od_seg_height` DECIMAL(4,1) NULL DEFAULT NULL AFTER `os_add`',
    'SELECT ''customer_prescriptions.od_seg_height da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- os_seg_height
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'os_seg_height'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `os_seg_height` DECIMAL(4,1) NULL DEFAULT NULL AFTER `od_seg_height`',
    'SELECT ''customer_prescriptions.os_seg_height da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- od_va_num
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'od_va_num'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `od_va_num` DECIMAL(3,2) NULL DEFAULT NULL AFTER `os_seg_height`',
    'SELECT ''customer_prescriptions.od_va_num da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- os_va_num
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'os_va_num'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `os_va_num` DECIMAL(3,2) NULL DEFAULT NULL AFTER `od_va_num`',
    'SELECT ''customer_prescriptions.os_va_num da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- tech_note
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'tech_note'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `tech_note` VARCHAR(500) NULL DEFAULT NULL AFTER `note`',
    'SELECT ''customer_prescriptions.tech_note da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ly_do
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'ly_do'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `ly_do` VARCHAR(255) NULL DEFAULT NULL AFTER `tech_note`',
    'SELECT ''customer_prescriptions.ly_do da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ban_goc_id
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'ban_goc_id'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `ban_goc_id` CHAR(36) NULL DEFAULT NULL AFTER `ly_do`',
    'SELECT ''customer_prescriptions.ban_goc_id da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- phien_ban
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'phien_ban'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `phien_ban` SMALLINT NOT NULL DEFAULT 1 AFTER `ban_goc_id`',
    'SELECT ''customer_prescriptions.phien_ban da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ----------------------------------------------------------------------------
-- CHỈ MỤC CHO NHÓM PHIÊN BẢN
--
-- Mọi câu đọc lịch sử đều hỏi "các phiên bản của lần đo này" hoặc "phiên bản
-- mới nhất của từng lần đo của khách này". Không có chỉ mục thì cả hai đều
-- quét toàn bảng.
-- ----------------------------------------------------------------------------
SET @co_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND INDEX_NAME   = 'idx_cpres_bangoc'
);
SET @sql := IF(@co_idx = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD KEY `idx_cpres_bangoc` (`user_id`, `ban_goc_id`, `phien_ban`)',
    'SELECT ''idx_cpres_bangoc da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;


-- ----------------------------------------------------------------------------
-- BẢN GHI CŨ ĐỀU LÀ PHIÊN BẢN 1 CỦA CHÍNH NÓ
--
-- Không có bước này thì `ban_goc_id` rỗng cho toàn bộ dữ liệu sẵn có, và câu
-- "phiên bản mới nhất của từng lần đo" bỏ sót đúng những bản ghi cũ nhất — tức
-- là màn hình lịch sử trống trơn ngay sau khi nâng cấp.
-- ----------------------------------------------------------------------------
UPDATE `customer_prescriptions`
   SET `ban_goc_id` = `id`
 WHERE `ban_goc_id` IS NULL;


-- ----------------------------------------------------------------------------
-- THỊ LỰC: CHUYỂN CHUỖI CŨ SANG SỐ, KHI ĐỌC ĐƯỢC
--
-- Chỉ đụng dòng có od_va_num/os_va_num còn rỗng, nên chạy lại không ghi đè số
-- ai đã sửa tay. Chuỗi không đúng dạng "a/b" hay "0.8" thì để NULL — cột
-- VARCHAR vẫn giữ nguyên văn, không mất gì.
-- ----------------------------------------------------------------------------
UPDATE `customer_prescriptions`
   SET `od_va_num` = CAST(SUBSTRING_INDEX(`od_va`, '/', 1) AS DECIMAL(6,2))
                   / NULLIF(CAST(SUBSTRING_INDEX(`od_va`, '/', -1) AS DECIMAL(6,2)), 0)
 WHERE `od_va_num` IS NULL
   AND `od_va` REGEXP '^[0-9]+(\\.[0-9]+)?/[0-9]+(\\.[0-9]+)?$';

UPDATE `customer_prescriptions`
   SET `os_va_num` = CAST(SUBSTRING_INDEX(`os_va`, '/', 1) AS DECIMAL(6,2))
                   / NULLIF(CAST(SUBSTRING_INDEX(`os_va`, '/', -1) AS DECIMAL(6,2)), 0)
 WHERE `os_va_num` IS NULL
   AND `os_va` REGEXP '^[0-9]+(\\.[0-9]+)?/[0-9]+(\\.[0-9]+)?$';

UPDATE `customer_prescriptions`
   SET `od_va_num` = CAST(`od_va` AS DECIMAL(6,2))
 WHERE `od_va_num` IS NULL
   AND `od_va` REGEXP '^[0-9]+(\\.[0-9]+)?$';

UPDATE `customer_prescriptions`
   SET `os_va_num` = CAST(`os_va` AS DECIMAL(6,2))
 WHERE `os_va_num` IS NULL
   AND `os_va` REGEXP '^[0-9]+(\\.[0-9]+)?$';


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM customer_prescriptions LIKE 'pd\_%';      -- 2 dòng
--   SHOW COLUMNS FROM customer_prescriptions LIKE '%\_add';     -- 2 dòng
--   SHOW COLUMNS FROM customer_prescriptions LIKE 'ban_goc_id'; -- 1 dòng
--   SELECT COUNT(*) FROM customer_prescriptions WHERE ban_goc_id IS NULL; -- 0
--
-- Chưa chạy file này thì màn Đơn thuốc trong khu quản trị vẫn mở được:
-- PrescriptionRecordModel hỏi Database::columnExists() trước khi nhắc tới các
-- cột mới, và lùi về hành vi cũ khi chưa có. Đó là chủ ý — cùng lối phòng thủ
-- đã cứu trang chủ hồi 2026-08-22.
-- ----------------------------------------------------------------------------
