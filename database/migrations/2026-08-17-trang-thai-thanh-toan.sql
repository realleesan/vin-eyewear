-- ============================================================================
-- NÂNG CẤP 2026-08-17
-- Đơn hàng phải ghi rõ ĐÃ NHẬN TIỀN HAY CHƯA
--
-- Cho tới nay bảng `orders` chỉ có `payment_method` — cách khách CHỌN để trả
-- tiền. Không có chỗ nào nói tiền đã về hay chưa.
--
-- Đây không phải thiếu một tính năng, nó là một lỗ hổng đang có:
--
--   • Một đơn chuyển khoản đã nhận tiền và một đơn chuyển khoản chưa nhận tiền
--     trông y hệt nhau, ở cả thẻ đơn của khách lẫn khu quản trị.
--   • Nhân viên đối chiếu sao kê xong không có chỗ nào ghi lại, nên cách duy
--     nhất là đẩy đơn sang "Đã xác nhận" — tức là mượn trạng thái GIAO VẬN để
--     nói chuyện TIỀN. Hai thứ đó không đi cùng nhau: đơn COD giao xong mới thu
--     được tiền, còn đơn chuyển khoản phải thu tiền trước khi giao.
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
-- TRẠNG THÁI THANH TOÁN
--
-- VARCHAR chứ không ENUM, cố ý: sắp tới sẽ nối cổng thanh toán (SePay…) và khi
-- đó cần thêm ít nhất 'pending' (khách đã bấm trả, cổng chưa xác nhận) và
-- 'refunded'. Với VARCHAR thì thêm một giá trị là sửa một hằng trong PHP; với
-- ENUM thì lại phải ALTER TABLE trên CSDL đang chạy.
--
-- Giá trị dùng ngay: 'unpaid' | 'paid'. Xem OrderModel::PAYMENT_STATUSES.
--
-- Mặc định 'unpaid' — đúng cho MỌI đơn mới, kể cả COD (COD chỉ thu được tiền
-- lúc giao) lẫn chuyển khoản (chưa đối chiếu sao kê thì chưa có gì cả).
--
-- paid_at tách riêng chứ không suy ra từ updated_at: updated_at đổi theo mọi
-- lần sửa đơn, còn đây là mốc kế toán — "tiền về lúc nào" phải đứng yên.
-- ----------------------------------------------------------------------------
ALTER TABLE `orders`
    ADD COLUMN `payment_status` VARCHAR(16) NOT NULL DEFAULT 'unpaid' AFTER `payment_method`,
    ADD COLUMN `paid_at`        DATETIME    NULL                      AFTER `payment_status`,
    ADD KEY `idx_orders_payment` (`payment_status`);


-- ----------------------------------------------------------------------------
-- ĐIỀN NGƯỢC CHO ĐƠN CŨ
--
-- Đơn ĐÃ HOÀN TẤT thì tiền chắc chắn đã về: COD thu lúc giao, chuyển khoản thì
-- không ai đóng đơn khi chưa nhận được tiền. Đây là suy luận an toàn duy nhất
-- có thể làm trên dữ liệu cũ.
--
-- KHÔNG đoán gì cho các trạng thái khác. Đơn 'shipping' chuyển khoản có thể đã
-- trả hoặc chưa, và đánh dấu sai là sai vào sổ tiền — để 'unpaid' rồi nhân viên
-- rà lại vẫn hơn.
--
-- paid_at lấy updated_at vì đó là mốc gần đúng nhất còn lại (lần cuối đơn đổi
-- trạng thái, thường chính là lúc đóng đơn). Đơn cũ nào cần con số chính xác
-- thì tra bảng `order_status_history`.
-- ----------------------------------------------------------------------------
UPDATE `orders`
   SET `payment_status` = 'paid',
       `paid_at`        = `updated_at`
 WHERE `status` = 'completed'
   AND `payment_status` = 'unpaid';
