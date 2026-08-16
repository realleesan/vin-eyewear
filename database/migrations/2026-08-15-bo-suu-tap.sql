-- ============================================================================
-- NÂNG CẤP 2026-08-15
-- Bộ sưu tập / lookbook (S09 — khối trang chủ + lọc /san-pham?collection=...)
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE và sẽ
-- xoá sạch đơn hàng, lịch hẹn, tài khoản khách.
--
-- Cách chạy
--   Trên máy:      mysql -u <user> -p <ten_db> < file_này.sql
--   InfinityFree:  vPanel -> phpMyAdmin -> chọn database -> tab SQL
--                  -> dán toàn bộ nội dung file -> Go
--
-- Chạy hai lần thì MySQL báo "Duplicate column name" / "Duplicate key name".
-- Đó là báo an toàn, không phải hỏng dữ liệu.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. CỘT BỘ SƯU TẬP
--
-- Một sản phẩm thuộc TỐI ĐA một bộ sưu tập -> một cột chuỗi là đủ, không cần
-- bảng nối. Giá trị khớp khoá 'slug' trong config/collections.php.
--
-- NULL = không thuộc bộ sưu tập nào. Có chỉ mục vì đây là điều kiện lọc.
-- ----------------------------------------------------------------------------
ALTER TABLE `products`
    ADD COLUMN `collection` VARCHAR(64) NULL AFTER `gender`,
    ADD KEY `idx_products_collection` (`collection`);

-- ----------------------------------------------------------------------------
-- 2. GẮN BỘ SƯU TẬP CHO DỮ LIỆU MẪU  (TUỲ CHỌN)
--
-- Chỉ chạy phần này trên bản demo/dev. Trên kho hàng thật thì bỏ qua và gắn
-- bộ sưu tập bằng khu quản trị, đừng để câu UPDATE chạm vào hàng thật.
--
-- Không gắn gì thì khối bộ sưu tập ngoài trang chủ vẫn hiện, nhưng bấm vào
-- sẽ ra trang "0 sản phẩm".
-- ----------------------------------------------------------------------------
UPDATE `products` SET `collection` = 'nang-he'
 WHERE `slug` IN ('kinh-mat-polarized-vin-s03', 'kinh-mat-mat-meo-vin-s04');

UPDATE `products` SET `collection` = 'nhe-ca-ngay'
 WHERE `slug` IN ('gong-kinh-titan-vin-t01', 'trong-kinh-chong-anh-sang-xanh');

UPDATE `products` SET `collection` = 'co-dien-tro-lai'
 WHERE `slug` IN ('gong-kinh-acetate-vin-a02');
