-- ============================================================================
-- NÂNG CẤP 2026-08-22
-- Khách tự yêu cầu khoá / xoá tài khoản (xoá MỀM)
--
-- Luật bảo vệ dữ liệu cá nhân buộc phải có một nút để khách rút lui. Nhưng
-- cửa hàng vẫn cần giữ dữ liệu gốc: đơn hàng đã giao, lịch hẹn đã đo, số
-- điện thoại để gọi lại khi có bảo hành. Hai đòi hỏi đó KHÔNG mâu thuẫn nếu
-- tách bạch "thấy được" và "còn tồn tại":
--
--   · PHÍA KHÁCH   tài khoản biến mất — không đăng nhập được, không lấy lại
--                  mật khẩu được, phiên đang mở trên máy khác bị đá ra.
--   · PHÍA CSDL    KHÔNG XOÁ MỘT DÒNG NÀO. users, profiles, orders,
--                  appointments, prescriptions, addresses giữ nguyên.
--
-- Vì thế bản này KHÔNG có DELETE và KHÔNG có ON DELETE CASCADE nào mới. Nó
-- chỉ thêm hai cột đánh dấu vào `users`.
--
-- VÌ SAO CỘT NẰM Ở `users` CHỨ KHÔNG PHẢI MỘT BẢNG YÊU CẦU RIÊNG:
-- mọi chỗ cần biết "tài khoản này còn sống không" đều đã cầm sẵn dòng `users`
-- trong tay (đăng nhập, khôi phục từ cookie, đăng nhập Google, quên mật
-- khẩu). Để ở bảng khác là thêm một JOIN vào đúng những câu chạy nhiều nhất
-- site, đổi lấy một bảng chỉ có một dòng cho mỗi khách.
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
-- Chạy hai lần thì MySQL báo "Duplicate column name 'deleted_at'". Đó là báo
-- an toàn, không hỏng dữ liệu.
-- ============================================================================

ALTER TABLE `users`
    -- NULL = tài khoản đang dùng bình thường. Có giá trị = khách đã yêu cầu
    -- khoá/xoá vào đúng lúc đó. Một cột NULL-được thay cho cờ is_active vì nó
    -- trả lời được cả hai câu: "còn sống không" VÀ "khoá từ bao giờ" — cái sau
    -- là thứ nhân viên cần khi khách gọi lại đòi mở.
    ADD COLUMN `deleted_at`      DATETIME     NULL AFTER `last_login_at`,
    -- Lý do khách chọn trong danh sách, hoặc chữ họ tự gõ. Đây là thứ DUY
    -- NHẤT bản này thu thập thêm, và nó phục vụ chăm sóc khách hàng: biết vì
    -- sao khách rời đi thì mới mời lại được.
    ADD COLUMN `deletion_reason` VARCHAR(500) NULL AFTER `deleted_at`;

-- Câu chạy nhiều nhất trên cột này là "WHERE ... AND deleted_at IS NULL" ở
-- mỗi lần đăng nhập. Chỉ mục thưa (đại đa số dòng là NULL) nên rất nhẹ.
CREATE INDEX `idx_users_deleted` ON `users` (`deleted_at`);
