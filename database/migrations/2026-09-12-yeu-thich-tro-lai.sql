-- ============================================================================
-- YÊU THÍCH (DẤU TRANG) — dựng lại bảng `favorites`.
--
-- Chạy lại nhiều lần không hỏng (IF NOT EXISTS).
--
-- ----------------------------------------------------------------------------
-- VÌ SAO BẢNG NÀY TỪNG BỊ GỠ, VÀ VÌ SAO NAY TRỞ LẠI
--
-- 2026-09-06-dot-1-go-bo.sql xoá `favorites` với đúng một lý do, ghi rõ trong
-- file đó: "chưa từng có màn hình nào dùng". Bảng rỗng, không mã nào đọc, nên
-- giữ lại chỉ là một cái tên trong SHOW TABLES.
--
-- Nay (12/09/2026) chức năng có thật: trang chi tiết sản phẩm có nút lưu, và
-- trang tài khoản có mục "Đã lưu". Lược đồ dưới đây CHÉP NGUYÊN từ file
-- 2026-09-06-dot-1-go-bo-QUAY-LUI.sql — cùng tên bảng, cùng tên cột, cùng tên
-- khoá — để một máy đã lỡ chạy file quay lui kia rồi thì chạy file này không
-- có gì xảy ra, chứ không phải va nhau.
--
-- ----------------------------------------------------------------------------
-- MỘT NGƯỜI, MỘT MẶT HÀNG, MỘT DÒNG
--
-- UNIQUE(user_id, product_id) và nó DÙNG ĐƯỢC ở đây, khác với `stock_waitlist`
-- (xem khối "KHÔNG DÙNG UNIQUE KEY ĐỂ CHỐNG TRÙNG" trong
-- 2026-08-29-danh-sach-cho-hang.sql): cả hai cột đều NOT NULL, nên không có
-- NULL nào để MySQL coi là "khác nhau".
--
-- KHÔNG GẮN THEO BIẾN THỂ, khác hẳn danh sách chờ. Lưu là "để dành xem lại cái
-- gọng này", không phải "tôi đợi đúng màu đen size 52". Gắn theo biến thể thì
-- một mặt hàng ba màu nằm ba dòng trong danh sách đã lưu của cùng một người.
--
-- ----------------------------------------------------------------------------
-- XOÁ TÀI KHOẢN HOẶC XOÁ MẶT HÀNG THÌ DÒNG LƯU ĐI THEO
--
-- Hai khoá ngoại đều ON DELETE CASCADE. Đây là dữ liệu TIỆN ÍCH, không phải
-- chứng từ: khác với `orders` (giữ tên và số điện thoại kể cả khi khách đã xoá
-- tài khoản, vì đó là giấy tờ mua bán), một dấu trang trỏ tới mặt hàng không
-- còn tồn tại thì không ai cần.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `favorites` (
    `id`         CHAR(36) NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36) NOT NULL,
    `product_id` CHAR(36) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_favorites` (`user_id`, `product_id`),
    KEY `idx_favorites_product` (`product_id`),
    CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_favorites_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- KIỂM LẠI SAU KHI CHẠY
--
--   SHOW TABLES LIKE 'favorites';        -- ra một dòng
--   SELECT COUNT(*) FROM favorites;      -- 0
--
-- MÃ CHẠY ĐƯỢC CẢ KHI CHƯA CHẠY FILE NÀY: FavoriteModel::available() hỏi
-- Database::tableExists() trước mọi câu lệnh, nút lưu tự ẩn đi và mục "Đã lưu"
-- không hiện trong cột tài khoản. Không trang nào đổ 500 — cùng khuôn với
-- WaitlistModel, xem chú thích ở đó.
-- ----------------------------------------------------------------------------
