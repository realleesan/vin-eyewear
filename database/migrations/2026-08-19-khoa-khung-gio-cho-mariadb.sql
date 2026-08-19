-- ============================================================================
-- NÂNG CẤP 2026-08-19
-- Dựng lại khoá chống đặt trùng khung giờ theo kiểu MariaDB chấp nhận được
--
-- CHẠY SAU `2026-08-18-doi-huy-lich-hen.sql` — file này sửa đúng cột `slot_lock`
-- mà file đó tạo ra.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO: HOSTING KHÔNG TẠO NỔI BẢNG `appointments`
--
-- Hosting thật (InfinityFree) chạy MariaDB 11.4. Import schema ở đó chết đúng
-- một bảng — `appointments` — với lỗi #1901, nên production có 14/15 bảng và
-- MỌI trang đụng tới lịch hẹn trả về 500. Bản vá trước (commit 578f8b4) viết
-- vòng vo để né CASE, nhưng đo lại trên chính MariaDB đó thì vẫn hỏng.
--
-- Thủ phạm KHÔNG phải CASE mà là CONCAT. Chạy thử từng biểu thức trên MariaDB
-- 11.4 của hosting:
--
--     CONCAT(store_id,'|',appointment_date,'|',time_slot)          -> #1901
--     CONCAT_WS('|', store_id, appointment_date, time_slot)        -> #1901
--     CASE WHEN status='cancelled' THEN NULL ELSE CONCAT(...) END  -> #1901
--     IF(status='cancelled', NULL, CONCAT(...))                    -> #1901
--     NULLIF(status, 'cancelled')                                  -> NHẬN
--     LEFT(NULLIF(status, 'cancelled'), 0)                         -> NHẬN
--
-- Tức là hàm điều kiện vốn KHÔNG bị cấm; CONCAT mới bị. Các đường vòng đã thử
-- và đều hỏng, để khỏi ai thử lại: khai VIRTUAL thay STORED (cột tạo được,
-- nhưng ADD UNIQUE lên nó ném lại đúng lỗi ấy), đổi bảng sang latin1, và đổi
-- bộ ký tự kết nối bằng SET NAMES latin1 / utf8mb3 / utf8mb4.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CÁCH LÀM: BỎ CHUỖI GỘP, ĐƯA BA CỘT THẲNG VÀO KHOÁ
--
-- Cột sinh mới `active_slot` chỉ còn là một CỜ: '' khi lịch còn hiệu lực, NULL
-- khi đã huỷ. Khoá duy nhất gồm bốn cột (cơ sở, ngày, giờ, cờ). MySQL bỏ qua
-- một hàng trong khoá duy nhất nếu BẤT KỲ cột nào của khoá là NULL, nên lịch
-- huỷ tự rời khỏi khoá — đúng hành vi mà `slot_lock` nhắm tới, chỉ khác chỗ
-- không phải ghép chuỗi.
--
-- Cờ phải là HẰNG khi lịch còn hiệu lực. Để NULLIF(status,'cancelled') trần thì
-- cờ mang luôn 'pending' / 'confirmed' / 'done', và hai lịch KHÁC TRẠNG THÁI ở
-- cùng khung giờ lại lọt qua khoá — đã đo, đúng là lọt. LEFT(..., 0) cắt về ''
-- cho mọi trạng thái còn hiệu lực, giữ NULL nguyên vẹn cho lịch huỷ.
--
-- Ứng dụng không đọc, không ghi cột này — nó chỉ bắt lỗi 1062 trong
-- BookingModel::create() và reschedule(). Đổi cách dựng khoá không đụng gì tới
-- mã nguồn, và số hiệu lỗi vẫn là 1062.
-- ============================================================================

-- Xoá cột kéo theo luôn khoá `uq_appointments_active_slot` cũ (khoá đó chỉ có
-- mỗi cột này), nên tên khoá được giải phóng để dùng lại ở câu dưới.
ALTER TABLE `appointments`
    DROP COLUMN `slot_lock`;

ALTER TABLE `appointments`
    ADD COLUMN `active_slot` VARCHAR(1)
        GENERATED ALWAYS AS (LEFT(NULLIF(`status`, 'cancelled'), 0)) STORED,
    ADD UNIQUE KEY `uq_appointments_active_slot`
        (`store_id`, `appointment_date`, `time_slot`, `active_slot`);


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   -- lịch còn hiệu lực giữ chỗ, lịch huỷ thì không:
--   SELECT status, active_slot FROM appointments LIMIT 20;
--
--   -- phải ra 4 cột, thứ tự (store_id, appointment_date, time_slot, active_slot):
--   SHOW INDEX FROM appointments WHERE Key_name = 'uq_appointments_active_slot';
-- ----------------------------------------------------------------------------
