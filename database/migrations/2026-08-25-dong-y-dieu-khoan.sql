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
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE và sẽ
-- xoá sạch đơn hàng, lịch hẹn, tài khoản khách.
--
-- Chạy hai lần thì MySQL báo "Duplicate column name 'terms_accepted_at'". Đó
-- là báo an toàn, không hỏng dữ liệu.
-- ============================================================================

ALTER TABLE `users`
    -- Mốc bấm nút đăng ký với ô đồng ý đã tick.
    ADD COLUMN `terms_accepted_at` DATETIME    NULL DEFAULT NULL AFTER `email_verified`,
    -- Phiên bản văn bản đã đồng ý, lấy từ config/auth.php ['consent']['version'].
    ADD COLUMN `terms_version`     VARCHAR(20) NULL DEFAULT NULL AFTER `terms_accepted_at`;
