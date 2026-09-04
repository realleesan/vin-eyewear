-- ============================================================================
-- 2026-09-06 — Hồ sơ khúc xạ: trường "Người được đo"
--
-- Căn cứ: SRS v1.3.1 mục 3.2.2.4, quyết định X24 (chốt 04/09/2026).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MỘT CỘT, KHÔNG PHẢI MỘT LỚP DỮ LIỆU
--
-- Q67 hỏi có cho "nhiều người dùng kính trên một tài khoản" không. Câu trả lời
-- đầy đủ là một bảng người phụ thuộc: mỗi thành viên một hồ sơ, một chuỗi lịch
-- sử đo, một huy hiệu hiệu lực riêng. X24 chốt KHÔNG làm thứ đó ở giai đoạn 1.
--
-- Nhưng vấn đề có thật và đã xảy ra: cả nhà dùng chung một số điện thoại, mẹ
-- dẫn hai con đi đo cùng buổi, và ba lần đo rơi vào cùng một tài khoản. Nhìn
-- vào lịch sử thì thấy độ cận nhảy loạn giữa các dòng như thể một người thoái
-- hoá mắt trong ba ngày — mà thật ra là ba người khác nhau.
--
-- Cột này KHÔNG sinh Customer_ID mới và KHÔNG tách huy hiệu còn hiệu lực —
-- X24 nói rõ là không ảnh hưởng Customer_ID, và huy hiệu vẫn theo bản ghi mới
-- nhất của tài khoản.
--
-- Nó CÓ tách một thứ: phép trừ chênh lệch độ giữa hai lần đo (chenhLech()).
-- Trừ số của mẹ cho số của con ra một con số trông y hệt một con số thật —
-- "P -1.50 sau 0 tháng" đọc như mắt xấu đi trong một buổi chiều. Đó chính là
-- cái nhầm cột này sinh ra để chặn, nên chặn nó là phần của việc, không phải
-- một tính năng thêm.
--
-- Nếu sau này BA muốn thứ đầy đủ, cột này là chỗ để đọc ra danh sách thành viên
-- đã từng được đo mà không phải hỏi lại khách — nên nó lưu TÊN, không lưu cờ.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO ĐỂ NULL CHỨ KHÔNG BACKFILL TÊN CHỦ TÀI KHOẢN
--
-- NULL ở đây mang nghĩa "chính chủ tài khoản" — nghĩa mặc định, đúng với gần
-- như toàn bộ dữ liệu đang có. Chép tên chủ tài khoản vào từng dòng cũ trông
-- có vẻ gọn hơn, nhưng nó tạo ra một bản sao thứ hai của họ tên khách trong
-- bảng dữ liệu y tế: đổi tên trong hồ sơ thì bản sao đó không đổi theo, và sáu
-- tháng sau không ai biết dòng nào là tên thật, dòng nào là tên cũ đã chép.
--
-- Nơi hiển thị tự lùi về tên chủ tài khoản khi cột này trống.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- MySQL 8 không có `ADD COLUMN IF NOT EXISTS`, nên cột đi qua một vòng
-- PREPARE/EXECUTE hỏi information_schema trước — đúng lối đã dùng ở
-- 2026-09-04-ho-so-khuc-xa-chi-them.sql.
--
-- KHÔNG có bước nào xoá hay đổi kiểu cột đang chứa dữ liệu. Rollback: cột
-- NULL được, bỏ bằng DROP COLUMN không mất gì khác.
-- ============================================================================

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
       AND COLUMN_NAME  = 'nguoi_duoc_do'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `customer_prescriptions`
        ADD COLUMN `nguoi_duoc_do` VARCHAR(120) NULL DEFAULT NULL AFTER `store_id`',
    'SELECT ''customer_prescriptions.nguoi_duoc_do da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SELECT 'Xong: customer_prescriptions.nguoi_duoc_do' AS ket_qua;
