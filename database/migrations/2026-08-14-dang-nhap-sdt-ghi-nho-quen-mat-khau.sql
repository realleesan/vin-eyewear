-- ============================================================================
-- NÂNG CẤP 2026-08-14
-- Đăng nhập bằng số điện thoại · Ghi nhớ đăng nhập · Quên mật khẩu
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
-- Chạy hai lần thì MySQL báo "Duplicate key name" / "Table already exists".
-- Đó là báo an toàn, không phải hỏng dữ liệu — bỏ qua và chạy tiếp phần sau.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. SỐ ĐIỆN THOẠI PHẢI LÀ DUY NHẤT
--
-- KIỂM TRA TRƯỚC KHI CHẠY. Nếu câu này trả về dòng nào thì lệnh ALTER bên
-- dưới sẽ thất bại, phải xử lý trùng lặp trước:
--
--     SELECT phone, COUNT(*) FROM profiles
--      WHERE phone IS NOT NULL AND phone <> ''
--      GROUP BY phone HAVING COUNT(*) > 1;
--
-- Ô trống nên là NULL chứ không phải chuỗi rỗng: MySQL coi nhiều NULL là
-- khác nhau trong khoá UNIQUE, nhưng hai chuỗi rỗng thì trùng nhau.
-- ----------------------------------------------------------------------------
UPDATE `profiles` SET `phone` = NULL WHERE `phone` = '';

ALTER TABLE `profiles`
    ADD UNIQUE KEY `uq_profiles_phone` (`phone`);

-- ----------------------------------------------------------------------------
-- 2. GHI NHỚ ĐĂNG NHẬP
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36)     NOT NULL,
    `selector`   CHAR(32)     NOT NULL,
    `validator`  CHAR(64)     NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_remember_selector` (`selector`),
    KEY `idx_remember_user`    (`user_id`),
    KEY `idx_remember_expires` (`expires_at`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. QUÊN MẬT KHẨU
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36)     NULL,
    `contact`    VARCHAR(255) NOT NULL,
    `status`     ENUM('pending','sent','used') NOT NULL DEFAULT 'pending',
    `selector`   CHAR(32)     NULL,
    `validator`  CHAR(64)     NULL,
    `expires_at` DATETIME     NULL,
    `used_at`    DATETIME     NULL,
    `handled_by` CHAR(36)     NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reset_selector` (`selector`),
    KEY `idx_reset_user`   (`user_id`),
    KEY `idx_reset_status` (`status`, `created_at`),
    CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. KIỂM TRA SAU KHI CHẠY — phải ra đúng 15 bảng
-- ----------------------------------------------------------------------------
-- SHOW TABLES;
