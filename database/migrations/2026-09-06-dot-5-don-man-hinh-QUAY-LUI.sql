-- ============================================================================
-- QUAY LUI đợt 5 — gỡ ba cột và bảng cấu hình
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MẤT GÌ
--
--   order_items.cost_price   MẤT VĨNH VIỄN giá vốn của mọi đơn bán từ lúc chạy
--                            migration tới lúc quay lui. Không dựng lại được:
--                            `products`.`cost_price` là giá vốn HÔM NAY, không
--                            phải giá lúc bán — đó chính là lý do cột này ra
--                            đời (FR-DH-13).
--   order_items.lens_type    Mất phần "đơn này bán kiểu tròng nào". Đọc lại gần
--                            đúng từ chuỗi `lens_name` (nó ghép kiểu · gói),
--                            chỉ mất công.
--   stock_waitlist.user_id   Mất phần gắn lượt chờ với tài khoản. Hai cột
--                            `email` và `phone` vẫn còn nên nhân viên vẫn gọi
--                            được.
--   app_settings             Mất mốc tính doanh thu. Đặt lại bằng STATS_SINCE
--                            trong .env như trước đợt 5.
--
-- ĐẾM TRƯỚC KHI CHẠY. Cột giá vốn là cột duy nhất không dựng lại được:
--
--   SELECT COUNT(*) FROM order_items WHERE cost_price IS NOT NULL;
--
-- Khác 0 thì XUẤT RA TRƯỚC:
--
--   mysqldump -u <user> -p <ten_csdl> order_items > order-items-luu.sql
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MÃ NGUỒN ĐỢT 5 VẪN CHẠY ĐƯỢC SAU KHI QUAY LUI
--
-- Mọi chỗ đụng tới bốn thứ này đều hỏi trước — OrderModel::coGiaVon(),
-- ::coKieuTrong(), WaitlistModel::coChuTaiKhoan(), SettingModel::available().
-- Nghĩa là chạy file này KHÔNG bắt buộc phải deploy ngược mã nguồn: trang vẫn
-- chạy, chỉ mất bốn tính năng.
--
-- Riêng việc tự huỷ đơn quá hạn (FR-TT-11) sẽ NGỪNG HẲN khi không còn
-- `app_settings`: nó không có chỗ nào khác để ghi mốc lần quét, và
-- quetDonQuaHan() thoát ngay ở dòng đầu. Đó là hành vi đúng — thà không quét
-- còn hơn quét lại từ đầu ở mỗi lượt truy cập.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- DROP TABLE IF EXISTS là idempotent; ba lệnh bỏ cột đi qua PREPARE/EXECUTE có
-- hỏi information_schema trước.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. Bảng cấu hình
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `app_settings`;


-- ----------------------------------------------------------------------------
-- 2. Cột chờ hàng — gỡ khoá ngoại trước, cột sau
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_waitlist'
               AND CONSTRAINT_NAME = 'fk_waitlist_user');
SET @sql := IF(@co = 1,
    'ALTER TABLE `stock_waitlist` DROP FOREIGN KEY `fk_waitlist_user`',
    'SELECT ''fk_waitlist_user da go, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_waitlist'
               AND COLUMN_NAME = 'user_id');
SET @sql := IF(@co = 1,
    'ALTER TABLE `stock_waitlist` DROP COLUMN `user_id`',
    'SELECT ''stock_waitlist.user_id da go, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 3. Hai cột dòng đơn — ĐỌC KHỐI CẢNH BÁO Ở ĐẦU FILE TRƯỚC
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
               AND COLUMN_NAME = 'lens_type');
SET @sql := IF(@co = 1,
    'ALTER TABLE `order_items` DROP COLUMN `lens_type`',
    'SELECT ''order_items.lens_type da go, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
               AND COLUMN_NAME = 'cost_price');
SET @sql := IF(@co = 1,
    'ALTER TABLE `order_items` DROP COLUMN `cost_price`',
    'SELECT ''order_items.cost_price da go, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- SAU KHI CHẠY
--
--   SHOW TABLES LIKE 'app_settings';                  -- không ra dòng nào
--   SHOW COLUMNS FROM order_items LIKE 'cost_price';  -- không ra dòng nào
--   SHOW COLUMNS FROM order_items LIKE 'lens_type';   -- không ra dòng nào
--   SHOW COLUMNS FROM stock_waitlist LIKE 'user_id';  -- không ra dòng nào
--
-- Rồi đặt lại STATS_SINCE trong .env nếu cửa hàng vẫn muốn có mốc doanh thu.
-- ----------------------------------------------------------------------------
