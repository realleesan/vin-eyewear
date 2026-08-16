-- ============================================================================
-- NÂNG CẤP 2026-08-16 (thứ ba trong ngày)
-- Đơn "nhận tại cửa hàng" phải ghi rõ NHẬN Ở CƠ SỞ NÀO
-- Theo "Vin Eyewear Checkout.dc.html"
--
-- Bản thiết kế trang thanh toán bắt khách chọn cơ sở khi chọn "Nhận tại cửa
-- hàng". Bảng `orders` không có chỗ nào ghi lựa chọn đó.
--
-- Đây KHÔNG chỉ là thiếu một tính năng mới — nó là một lỗ hổng đang có: từ
-- trước tới nay khách vẫn đặt được đơn "nhận tại cửa hàng", và không ai trong
-- cửa hàng biết phải soạn hàng ở cơ sở nào. Nhân viên phải gọi lại hỏi từng đơn.
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
-- CƠ SỞ NHẬN HÀNG
--
-- NULL có hai nghĩa, và cả hai đều hợp lệ:
--   • đơn giao tận nơi   — không nhận ở cơ sở nào cả
--   • đơn cũ đặt trước bản nâng cấp này — không có dữ liệu để điền ngược
--
-- Nên KHÔNG đặt NOT NULL, và cũng không cố đoán cơ sở cho đơn cũ: đoán sai thì
-- nhân viên soạn hàng ở nhầm nơi, tệ hơn là để trống rồi gọi hỏi khách.
--
-- ON DELETE SET NULL: đóng một cơ sở không được phép làm hỏng đơn đã phát sinh.
-- Cùng cách xử lý với prescriptions.store_id.
--
-- Đặt ngay sau `delivery_method` vì hai cột này luôn phải đọc cùng nhau —
-- store_id chỉ có nghĩa khi delivery_method = 'pickup'.
-- ----------------------------------------------------------------------------
ALTER TABLE `orders`
    ADD COLUMN `store_id` CHAR(36) NULL AFTER `delivery_method`,
    ADD KEY `idx_orders_store` (`store_id`),
    ADD CONSTRAINT `fk_orders_store` FOREIGN KEY (`store_id`)
        REFERENCES `stores` (`id`) ON DELETE SET NULL;
