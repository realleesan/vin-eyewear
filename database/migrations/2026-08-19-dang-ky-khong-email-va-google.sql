-- ============================================================================
-- NÂNG CẤP 2026-08-19
-- Bỏ email khỏi form đăng ký, thêm đăng nhập bằng tài khoản Google
--
-- ─────────────────────────────────────────────────────────────────────────────
-- EMAIL TỪ BẮT BUỘC THÀNH TUỲ CHỌN
--
-- Form đăng ký nay chỉ hỏi họ tên, số điện thoại và mật khẩu. Số điện thoại
-- vốn ĐÃ là một trong hai cách đăng nhập (xem UserModel::findByLogin), nên bỏ
-- email đi không mất lối vào tài khoản nào.
--
-- Cột `email` vẫn còn và vẫn UNIQUE, chỉ khác là cho phép NULL:
--
--   · tài khoản đăng ký bằng Google có email — do Google cung cấp, không phải
--     khách tự gõ;
--   · tài khoản cũ giữ nguyên email và vẫn đăng nhập bằng email được;
--   · tài khoản mới đăng ký bằng số điện thoại thì email là NULL.
--
-- MySQL cho phép NHIỀU dòng NULL trong một khoá duy nhất (chỉ giá trị thật mới
-- phải khác nhau), nên không cần bỏ `uq_users_email` — nghĩa là hai người cùng
-- không có email vẫn cùng tồn tại, mà hai người cùng một email thì vẫn bị chặn.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- `google_id` LÀ `sub` TRONG ID TOKEN, KHÔNG PHẢI EMAIL
--
-- Google định danh một tài khoản bằng trường `sub` — chuỗi số không bao giờ
-- đổi và không bao giờ dùng lại. Email thì đổi được, và một địa chỉ đã huỷ có
-- thể được cấp lại cho người khác. Ghép tài khoản theo email là mời người lạ
-- vào nhà; ghép theo `sub` mới đúng.
--
-- Vẫn dùng email để NỐI một lần: người đã có tài khoản mật khẩu với email
-- abc@gmail.com, nay bấm đăng nhập Google bằng chính địa chỉ đó, thì được nối
-- vào tài khoản cũ chứ không tạo tài khoản thứ hai — xem
-- UserModel::findOrCreateGoogle().
--
-- VARCHAR(64): `sub` hiện dài 21 ký tự; Google chỉ hứa "tối đa 255 ký tự"
-- nhưng đó là trần cho mọi loại chủ thể, còn tài khoản người dùng thì ngắn.
-- 64 là chỗ dư ba lần mà vẫn nằm gọn trong một khoá duy nhất.
-- ============================================================================

ALTER TABLE `users`
    MODIFY COLUMN `email` VARCHAR(255) NULL,
    ADD COLUMN `google_id` VARCHAR(64) NULL AFTER `email`,
    ADD UNIQUE KEY `uq_users_google` (`google_id`);


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM users;   -- email: Null=YES · có cột google_id
--   SELECT COUNT(*) FROM users WHERE email IS NULL;   -- 0 ngay sau khi chạy
-- ----------------------------------------------------------------------------
