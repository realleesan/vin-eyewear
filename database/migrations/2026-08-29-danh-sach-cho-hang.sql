-- ============================================================================
-- DANH SÁCH CHỜ HÀNG — khách để lại liên lạc để được báo khi hàng về.
--
-- Chạy lại nhiều lần không hỏng (IF NOT EXISTS).
--
-- ----------------------------------------------------------------------------
-- GẮN THEO BIẾN THỂ, KHÔNG PHẢI THEO CẢ MẶT HÀNG
--
-- Người chờ gọng màu đen size 52 không quan tâm màu nâu vừa về. Gắn theo mặt
-- hàng thì mọi người trong danh sách nhận cùng một tin, và phần lớn là tin
-- không liên quan tới họ — vài lần như thế là không ai đọc nữa.
--
-- `variant_id` để NULL khi mặt hàng KHÔNG có biến thể nào. Đó là trường hợp
-- thật, không phải dữ liệu thiếu.
--
-- ----------------------------------------------------------------------------
-- HAI CÁCH LIÊN LẠC, CẦN ÍT NHẤT MỘT
--
-- `email` và `phone` đều cho NULL, nhưng tầng ứng dụng bắt phải có ít nhất
-- một (xem ProductDetailController::waitlist). Lý do phải có SỐ ĐIỆN THOẠI
-- chứ không chỉ email: hosting hiện tại KHÔNG GỬI ĐƯỢC EMAIL — InfinityFree
-- bản miễn phí vô hiệu hoá hàm mail() và chặn cổng SMTP ra ngoài, nên
-- .env.production đang để MAIL_DRIVER=log (thư chỉ ghi vào file, không ai
-- nhận). Xem khối chú thích đầu core/Mailer.php.
--
-- Nghĩa là hôm nay việc báo tin là việc CỦA NGƯỜI: nhân viên mở
-- /quan-tri/cho-hang rồi gọi hoặc nhắn Zalo. Bảng này dựng sẵn đúng hình để
-- ngày có kênh gửi tự động thì chỉ việc thêm chỗ gửi, không phải đổi lược đồ.
--
-- ----------------------------------------------------------------------------
-- KHÔNG DÙNG UNIQUE KEY ĐỂ CHỐNG TRÙNG
--
-- MySQL coi mỗi NULL là một giá trị KHÁC NHAU trong khoá duy nhất, mà
-- `variant_id` và một trong hai cột liên lạc đều có thể NULL — nên
-- UNIQUE(product_id, variant_id, email, phone) sẽ cho phép đúng cùng một người
-- đăng ký lại vô số lần. Phép chống trùng nằm ở WaitlistModel::daDangKy(),
-- dùng COALESCE nên NULL so được với NULL.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `stock_waitlist` (
    `id`          CHAR(36)     NOT NULL DEFAULT (UUID()),
    `product_id`  CHAR(36)     NOT NULL,
    `variant_id`  CHAR(36)     NULL,
    `email`       VARCHAR(190) NULL,
    `phone`       VARCHAR(20)  NULL,
    -- Đã báo cho người này chưa, và lúc nào. NULL = đang chờ.
    `notified_at` DATETIME     NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Màn quản trị sắp theo "đang chờ trước, cũ nhất trước": lọc notified_at
    -- rồi sắp created_at, nên hai cột đi chung một khoá.
    KEY `idx_waitlist_cho` (`notified_at`, `created_at`),
    KEY `idx_waitlist_product` (`product_id`),
    KEY `idx_waitlist_variant` (`variant_id`),
    CONSTRAINT `fk_waitlist_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_waitlist_variant` FOREIGN KEY (`variant_id`)
        REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
