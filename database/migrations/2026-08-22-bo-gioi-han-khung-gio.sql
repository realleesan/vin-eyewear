-- ============================================================================
-- NÂNG CẤP 2026-08-22
-- Đặt lịch đo mắt — BỎ giới hạn mỗi khung giờ một người
--
-- Bảng `appointments` đang có UNIQUE (store_id, appointment_date, time_slot,
-- active_slot). Khoá ấy cho mỗi khung giờ của mỗi cơ sở đúng MỘT lịch còn hiệu
-- lực; khung đã có người thì mờ đi trên trang đặt lịch và không bấm được.
--
-- Cửa hàng yêu cầu bỏ hẳn. Lý do của họ: đo mắt và cắt kính hết khoảng 30 phút,
-- phần lâu nhất là 10–15 phút thử tròng còn lắp kính thì máy làm rất nhanh, nên
-- không cần chia ca như tiệm cắt tóc. Khách cứ chọn ngày và khung giờ mong
-- muốn, cửa hàng ghi nhận rồi GỌI ĐIỆN xác nhận và tự xếp người.
--
-- Nói cách khác: khung giờ trên web nay là NGUYỆN VỌNG của khách, không phải
-- một chỗ ngồi đã giữ. Cái chốt thật nằm ở cuộc gọi xác nhận.
--
-- Bỏ luôn cột sinh `active_slot`. Nó tồn tại DUY NHẤT để phục vụ khoá trên —
-- LEFT(NULLIF(status,'cancelled'), 0) cho ra '' khi lịch còn hiệu lực và NULL
-- khi đã huỷ, để hàng đã huỷ tự rời khỏi khoá duy nhất. Không còn khoá thì cột
-- ấy là một cột luôn bằng '' hoặc NULL mà không ai đọc, kèm một khối chú thích
-- dài giải thích một ràng buộc đã biến mất.
--
-- BA BƯỚC, ĐÚNG THỨ TỰ NÀY — ĐỔI THỨ TỰ LÀ HỎNG:
--
--   1. Dựng `idx_appointments_store`.
--   2. Xoá khoá `uq_appointments_active_slot`.
--   3. Xoá cột `active_slot`.
--
-- Bước 1 nghe như thừa nhưng KHÔNG. Khoá ngoại `fk_appointments_store` cần một
-- chỉ mục bắt đầu bằng `store_id`, và từ trước tới nay nó vẫn mượn tạm chính
-- khoá duy nhất kia — cột đầu của khoá đó là `store_id`. Xoá thẳng khoá thì
-- InnoDB từ chối:
--
--     ERROR 1553: Cannot drop index 'uq_appointments_active_slot':
--                 needed in a foreign key constraint
--
-- (Đã gặp thật khi chạy bản đầu của file này.) Dựng chỉ mục riêng trước thì
-- khoá ngoại có chỗ dựa mới và bước 2 đi qua.
--
-- Bước 3 phải sau bước 2 vì `active_slot` đang nằm trong khoá.
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
-- Chạy hai lần thì MySQL dừng ngay ở bước 1 với "Duplicate key name
-- 'idx_appointments_store'". Đó là báo an toàn: không bước nào phía sau chạy,
-- và bảng giữ nguyên trạng thái đã đúng.
-- ============================================================================

-- 1. Chỗ dựa mới cho khoá ngoại `fk_appointments_store`.
ALTER TABLE `appointments`
    ADD KEY `idx_appointments_store` (`store_id`);

-- 2. Khoá giới hạn mỗi khung giờ một người — thứ file này sinh ra để bỏ.
ALTER TABLE `appointments`
    DROP INDEX `uq_appointments_active_slot`;

-- 3. Cột sinh chỉ tồn tại để phục vụ khoá vừa xoá.
ALTER TABLE `appointments`
    DROP COLUMN `active_slot`;

-- ----------------------------------------------------------------------------
-- KHOÁ `code` GIỮ NGUYÊN
--
-- uq_appointments_code không liên quan gì tới khung giờ: nó chặn hai lịch trùng
-- MÃ (LH…), thứ khách đọc qua điện thoại và nhân viên tra trong khu quản trị.
-- Trùng mã thì tra ra hai lịch của hai người khác nhau.
--
-- Sau bản này, 1062 trên bảng `appointments` chỉ còn một nghĩa duy nhất là
-- trùng mã — xem cách BookingModel::create() bắt lỗi.
-- ----------------------------------------------------------------------------
