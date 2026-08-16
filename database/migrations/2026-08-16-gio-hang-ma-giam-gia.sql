-- ============================================================================
-- NÂNG CẤP 2026-08-16 (thứ hai trong ngày)
-- Mã giảm giá đi từ giỏ hàng vào đơn hàng — theo "Vin Eyewear Cart.dc.html"
--
-- Bản thiết kế giỏ hàng có ô "Mã giảm giá" và một dòng "Giảm giá voucher"
-- trong khối tóm tắt. Bảng `vouchers` / `user_vouchers` đã có từ migration
-- 2026-08-16-trang-tai-khoan.sql — CHẠY FILE ĐÓ TRƯỚC nếu chưa chạy.
--
-- Còn thiếu đúng một chỗ: bảng `orders` không có nơi nào ghi số tiền đã giảm.
-- Không có nó thì `total` bị trừ đi một khoản mà không ai giải thích được,
-- và sổ sách không đối chiếu được subtotal + ship − giảm = total.
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
-- Chạy hai lần thì MySQL báo "Duplicate column name". Đó là báo an toàn.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. SỐ TIỀN ĐÃ GIẢM + MÃ ĐÃ DÙNG
--
-- `discount` là SỐ TIỀN, không phải phần trăm. Phần trăm là cách TÍNH ra nó,
-- và cách tính ấy nằm ở bảng `vouchers`; đơn hàng chỉ cần biết kết quả. Lưu
-- phần trăm ở đây thì sửa chương trình khuyến mãi là mọi hoá đơn cũ đổi theo.
--
-- Cùng lý do với `product_name`/`unit_price` trong `order_items` — xem
-- schema.sql: hoá đơn phải giữ nguyên con số tại thời điểm khách mua.
--
-- `voucher_id` chỉ để tra cứu ("chương trình này đã dùng bao nhiêu lần"), nên
-- ON DELETE SET NULL: xoá một chương trình khuyến mãi cũ không được phép làm
-- hỏng đơn hàng đã phát sinh.
--
-- Đặt sau `shipping_fee` để thứ tự cột đọc đúng theo thứ tự cộng trừ:
--     subtotal + shipping_fee − discount = total
-- ----------------------------------------------------------------------------
ALTER TABLE `orders`
    ADD COLUMN `discount`   BIGINT   NOT NULL DEFAULT 0 AFTER `shipping_fee`,
    ADD COLUMN `voucher_id` CHAR(36) NULL              AFTER `discount`,
    ADD KEY `idx_orders_voucher` (`voucher_id`),
    ADD CONSTRAINT `fk_orders_voucher` FOREIGN KEY (`voucher_id`)
        REFERENCES `vouchers` (`id`) ON DELETE SET NULL;


-- ----------------------------------------------------------------------------
-- 2. MÃ DÙNG CHUNG, KHÔNG CẦN PHÁT RIÊNG CHO TỪNG NGƯỜI
--
-- `user_vouchers` trả lời câu "mã nào đã phát cho ai" — dùng cho mục "Ưu đãi
-- của tôi" trong trang tài khoản. Nhưng ô nhập mã ở giỏ hàng thì KHÁCH VÃNG
-- LAI cũng gõ được, mà khách vãng lai không có dòng nào trong bảng đó.
--
-- Nên thêm một cột nói rõ mã thuộc loại nào:
--   is_public = 1  ai gõ đúng mã cũng dùng được (mã in trên tờ rơi, mã sự kiện)
--   is_public = 0  chỉ người đã được phát mới dùng được (mã tri ân, mã bồi thường)
--
-- Mặc định 1 vì bốn mã mẫu hiện có đều là mã chung. Mã riêng thì tạo với giá
-- trị 0 rồi phát bằng `user_vouchers`.
-- ----------------------------------------------------------------------------
ALTER TABLE `vouchers`
    ADD COLUMN `is_public` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`;


-- ----------------------------------------------------------------------------
-- 3. GIỚI HẠN SỐ LẦN DÙNG  (TUỲ CHỌN NHƯNG NÊN CÓ)
--
-- Không có cột này thì một mã công khai dùng được vô hạn lần, và một mã lọt
-- ra ngoài là cửa hàng bán lỗ tới khi có người phát hiện.
--
-- NULL = không giới hạn. `used_count` do VoucherModel tăng mỗi lần đặt hàng
-- thành công, trong cùng transaction với đơn.
-- ----------------------------------------------------------------------------
ALTER TABLE `vouchers`
    ADD COLUMN `max_uses`   INT NULL          AFTER `is_public`,
    ADD COLUMN `used_count` INT NOT NULL DEFAULT 0 AFTER `max_uses`;
