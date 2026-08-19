-- ============================================================================
-- NÂNG CẤP 2026-08-18
-- Khách tự ĐỔI và HUỶ lịch hẹn
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO PHẢI ĐỘNG VÀO CSDL: HUỶ LỊCH HIỆN KHÔNG THẬT SỰ TRẢ LẠI KHUNG GIỜ
--
-- Bảng `appointments` đang có:
--
--     UNIQUE KEY uq_appointments_slot (store_id, appointment_date, time_slot)
--
-- Khoá này KHÔNG biết tới cột `status`. Còn phía ứng dụng thì
-- BookingModel::bookedSlots() lại lọc `status <> 'cancelled'`. Hai bên nói hai
-- điều khác nhau, và hệ quả là một lỗi đang có sẵn (khu quản trị đã huỷ được
-- lịch từ trước):
--
--     1. nhân viên huỷ lịch 09:00 ngày 25/08 ở cơ sở A
--     2. trang đặt lịch hiện 09:00 hôm đó là TRỐNG (đúng theo bookedSlots)
--     3. khách chọn 09:00, bấm đặt
--     4. INSERT đụng uq_appointments_slot -> lỗi 1062 -> khách nhận thông báo
--        "Khung giờ này vừa được đặt, vui lòng chọn giờ khác."
--
-- Khung giờ đó thành KHÔNG BAO GIỜ đặt lại được, mà vẫn hiện ra như còn trống.
-- Ghi chú trong AppointmentAdminController::updateStatus ("bookedSlots() bỏ qua
-- lịch cancelled, nên không cần làm gì thêm") vì thế là sai.
--
-- Cho khách tự huỷ lịch sẽ biến lỗi này từ "hiếm" thành "gặp hằng ngày", nên
-- phải sửa TRƯỚC.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CÁCH SỬA: KHOÁ DUY NHẤT CHỈ ÁP CHO LỊCH CÒN HIỆU LỰC
--
-- Không bỏ khoá duy nhất đi rồi chỉ kiểm bằng PHP: hai người bấm đặt cùng một
-- khung giờ trong cùng một giây thì phép kiểm ở PHP đọc trước, ghi sau, và cả
-- hai đều lọt. Khoá ở CSDL là thứ duy nhất chặn được đúng trường hợp đó.
--
-- Nên giữ khoá, nhưng đặt nó lên một cột SINH RA: cột này bằng NULL khi lịch đã
-- huỷ, còn lại là bộ ba (cơ sở, ngày, giờ). MySQL BỎ QUA giá trị NULL trong khoá
-- duy nhất, nên:
--
--     • một khung giờ chỉ có ĐÚNG MỘT lịch còn hiệu lực  (chặn đặt trùng)
--     • bao nhiêu lịch đã huỷ trong cùng khung giờ cũng được  (huỷ = trả giờ)
--
-- Cột sinh ra (STORED) cần MySQL 5.7.6+ hoặc MariaDB 10.2+. Hai bản đó ra từ
-- 2015–2017; nếu máy chủ cũ hơn thì dừng lại và nói với người viết code, ĐỪNG bỏ
-- khoá duy nhất đi để chạy cho xong.
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE.
--
-- Cách chạy
--   Trên máy:      mysql -u <user> -p <ten_db> < file_này.sql
--   InfinityFree:  vPanel -> phpMyAdmin -> chọn database -> tab SQL
--                  -> dán toàn bộ nội dung file -> Go
--
-- Chạy hai lần thì MySQL báo "Duplicate column name" / "Duplicate key name".
-- Đó là báo an toàn.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- BƯỚC 1 — DỌN DỮ LIỆU CŨ TRƯỚC KHI ĐỔI KHOÁ
--
-- Nếu trong bảng đang có HAI lịch còn hiệu lực trùng khung giờ (không thể xảy ra
-- với khoá cũ, nhưng cứ kiểm cho chắc trước khi tạo khoá mới) thì câu ALTER bên
-- dưới sẽ thất bại. Chạy câu SELECT này trước; nó phải trả về 0 dòng:
--
--   SELECT store_id, appointment_date, time_slot, COUNT(*) n
--     FROM appointments WHERE status <> 'cancelled'
--    GROUP BY store_id, appointment_date, time_slot HAVING n > 1;
-- ----------------------------------------------------------------------------


-- ----------------------------------------------------------------------------
-- BƯỚC 2 — ĐỔI KHOÁ DUY NHẤT SANG CỘT SINH RA
--
-- Làm trong MỘT câu ALTER: giữa lúc khoá cũ đã mất mà khoá mới chưa có, bảng
-- không còn gì chặn đặt trùng.
--
-- 96 ký tự đủ cho 36 (UUID cơ sở) + 1 + 10 (ngày) + 1 + 20 (khung giờ) = 68.
-- ----------------------------------------------------------------------------
-- Vì sao biểu thức viết vòng vo thay vì CASE WHEN status = 'cancelled' cho dễ
-- đọc: MariaDB (hosting InfinityFree) không cho dùng hàm điều kiện trong
-- GENERATED ALWAYS AS — báo lỗi #1901 và không chạy được câu này. Bản dưới cho
-- ra đúng cùng giá trị bằng hai tính chất sẵn có: NULLIF(x, y) = NULL khi x = y,
-- và CONCAT = NULL nếu có tham số NULL. Xem giải thích đầy đủ ở database/schema.sql.
--
--   lịch đã huỷ  -> LEFT(NULLIF('cancelled','cancelled'), 0) = NULL -> cả cột NULL
--   còn hiệu lực -> LEFT('pending', 0) = ''  -> 'cơ sở|ngày|giờ'
--
-- CSDL nào ĐÃ chạy bản CASE cũ (máy các bạn dùng MySQL) thì KHÔNG cần làm lại:
-- hai cách viết cho ra kết quả y hệt, chỉ khác chỗ MariaDB có nhận hay không.
ALTER TABLE `appointments`
    DROP INDEX `uq_appointments_slot`,
    ADD COLUMN `slot_lock` VARCHAR(96)
        GENERATED ALWAYS AS (
            CONCAT(`store_id`, '|', `appointment_date`, '|', `time_slot`,
                   LEFT(NULLIF(`status`, 'cancelled'), 0))
        ) STORED,
    ADD UNIQUE KEY `uq_appointments_active_slot` (`slot_lock`);


-- ----------------------------------------------------------------------------
-- BƯỚC 3 — MỐC SỬA GẦN NHẤT
--
-- Khách đổi lịch thì hàng cũ được sửa tại chỗ (giữ nguyên mã lịch để khách khỏi
-- phải nhớ mã mới). Không có cột này thì nhân viên không biết lịch đã bị đổi và
-- đổi lúc nào — chỉ thấy giờ mới như thể khách đặt vậy từ đầu.
--
-- Lịch đổi xong quay về 'pending': nhân viên đã xác nhận cho một giờ CỤ THỂ, giờ
-- khác thì phải xác nhận lại. Luật đó nằm ở BookingModel::reschedule.
-- ----------------------------------------------------------------------------
ALTER TABLE `appointments`
    ADD COLUMN `updated_at` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
