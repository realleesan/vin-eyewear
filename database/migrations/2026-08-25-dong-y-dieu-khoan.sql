-- ============================================================================
-- NÂNG CẤP 2026-08-25
-- Ghi nhận việc khách ĐỒNG Ý điều khoản khi đăng ký
--
-- Màn đăng ký nay có ô tick bắt buộc. Hai cột này là phần còn lại của việc đó.
--
-- VÌ SAO PHẢI GHI, KHÔNG CHỈ CHẶN Ở FORM
--
-- Một ô tick bắt buộc mới chỉ ngăn người dùng bấm nút. Nó không trả lời được
-- câu hỏi duy nhất đáng hỏi về sau: TÀI KHOẢN NÀY đã đồng ý, VÀO LÚC NÀO, với
-- PHIÊN BẢN văn bản nào. Thiếu ba thứ đó thì cái tick chỉ là trang trí.
--
-- VÌ SAO LƯU PHIÊN BẢN CHỨ KHÔNG PHẢI MỘT CỜ BẬT/TẮT
--
-- Văn bản điều khoản sẽ được sửa. Lưu TINYINT(1) "đã đồng ý" thì sau lần sửa
-- đầu tiên không còn cách nào biết ai đã đọc bản cũ và ai đã đọc bản mới — mà
-- đó đúng là lúc câu hỏi trở nên quan trọng. Cùng lẽ với `deposit_rate` trong
-- orders: lưu cả mức đã áp, không chỉ lưu kết quả.
--
-- CẢ HAI CỘT ĐỀU NULL, CỐ Ý
--
-- Tài khoản đăng ký TRƯỚC hôm nay không có dữ liệu này và không được phép bịa
-- ra. NULL đọc đúng là "không biết"; điền đại ngày tạo tài khoản là dựng bằng
-- chứng cho một cú tick chưa từng xảy ra.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO PHẢI VÒNG VO THẾ NÀY THAY VÌ MỘT CÂU `ALTER TABLE ... ADD COLUMN`
--
-- CLAUDE.md yêu cầu migration chạy lại nhiều lần không hỏng. `ADD COLUMN` trần
-- thì lần thứ hai đổ "Duplicate column name" và DỪNG — nghĩa là mọi câu lệnh
-- sau nó trong cùng file cũng không chạy. Nguy hiểm khi file có nhiều bước.
--
-- MySQL 8 KHÔNG có `ADD COLUMN IF NOT EXISTS` (MariaDB thì có, nhưng dự án
-- chạy trên cả hai nên không dùng được cú pháp riêng của một bên).
--
-- Đã cân nhắc và BỎ phương án stored procedure: nó cần `DELIMITER`, mà hosting
-- InfinityFree không có SSH nên file này được dán tay vào phpMyAdmin — chỗ mà
-- `DELIMITER` hay vướng nhất.
--
-- Còn lại cách này: hỏi information_schema, rồi PREPARE một câu lệnh dựng sẵn.
-- Chạy trên cả MySQL 8 lẫn MariaDB, cả mysql CLI lẫn phpMyAdmin, không cần
-- DELIMITER. Chạy lần thứ hai thì @sql thành một câu SELECT vô hại.
-- ─────────────────────────────────────────────────────────────────────────────
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE và sẽ
-- xoá sạch đơn hàng, lịch hẹn, tài khoản khách.
-- ============================================================================

-- --------------------------------------------------------------------------
-- 1. Mốc bấm nút đăng ký với ô đồng ý đã tick
-- --------------------------------------------------------------------------
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'terms_accepted_at'
);

SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `users`
        ADD COLUMN `terms_accepted_at` DATETIME NULL DEFAULT NULL AFTER `email_verified`',
    'SELECT ''users.terms_accepted_at da co, bo qua'' AS ghi_chu'
);

PREPARE cau_lenh FROM @sql;
EXECUTE cau_lenh;
DEALLOCATE PREPARE cau_lenh;

-- --------------------------------------------------------------------------
-- 2. Phiên bản văn bản đã đồng ý — config/auth.php ['consent']['version']
-- --------------------------------------------------------------------------
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'terms_version'
);

SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `users`
        ADD COLUMN `terms_version` VARCHAR(20) NULL DEFAULT NULL AFTER `terms_accepted_at`',
    'SELECT ''users.terms_version da co, bo qua'' AS ghi_chu'
);

PREPARE cau_lenh FROM @sql;
EXECUTE cau_lenh;
DEALLOCATE PREPARE cau_lenh;
