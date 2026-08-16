-- ============================================================================
-- NÂNG CẤP 2026-08-15
-- Đăng ký nhận tin (S20 — khối cuối trang chủ)
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
-- Chạy hai lần cũng không sao: đã có IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id`              CHAR(36)     NOT NULL DEFAULT (UUID()),
    `email`           VARCHAR(255) NOT NULL,
    `source`          VARCHAR(64)  NOT NULL DEFAULT 'home',
    `unsubscribed_at` DATETIME     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_newsletter_email` (`email`),
    KEY `idx_newsletter_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
