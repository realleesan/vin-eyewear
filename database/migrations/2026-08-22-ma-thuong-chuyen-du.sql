-- ============================================================================
-- NÂNG CẤP 2026-08-22
-- Mã giảm giá TẶNG cho khách chuyển khoản đủ 100%
--
-- Đơn có cắt tròng nay được chọn: chuyển khoản 30% tiền cọc, hoặc chuyển đủ
-- 100%. Cửa hàng muốn khuyến khích vế thứ hai — tiền về đủ trước khi mài
-- tròng thì không còn rủi ro nào — nên khách chọn chuyển đủ được tặng một mã
-- giảm giá dùng cho lần mua sau.
--
-- MỘT CỜ TRÊN CHÍNH BẢNG `vouchers`, không phải một bảng cấu hình riêng.
-- Thứ cần lưu ở đây là "mã nào đang được dùng làm quà tặng" — một câu trả lời
-- thuộc về chính cái mã đó, không phải một thiết lập toàn hệ thống. Đặt vào
-- đây thì nhân viên bật/tắt ngay trong màn hình họ đã quen, và một mã hết hạn
-- tự thôi làm quà tặng theo đúng cột expires_at sẵn có.
--
-- CHỈ MỘT MÃ được bật cùng lúc — luật đó do PHP giữ (xem
-- VoucherAdminController::save), không phải ràng buộc của CSDL: MySQL không có
-- partial unique index, mà UNIQUE(is_reward) thì chặn luôn cả những mã có
-- is_reward = 0.
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

ALTER TABLE `vouchers`
    ADD COLUMN `is_reward` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_public`;

-- Tra "mã quà tặng đang bật" chạy MỖI LẦN một đơn chuyển khoản được trả đủ.
-- Không nhiều, nhưng bảng vouchers sẽ dài ra theo từng đợt khuyến mãi và câu
-- đó lọc đúng một dòng — rẻ hơn nhiều nếu có chỉ mục.
CREATE INDEX `idx_vouchers_reward` ON `vouchers` (`is_reward`);
