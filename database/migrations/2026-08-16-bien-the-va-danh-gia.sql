-- ============================================================================
-- NÂNG CẤP 2026-08-16 (thứ tư trong ngày)
-- Biến thể sản phẩm + đánh giá của khách — theo "Vin Eyewear Product.dc.html"
--
-- Bản thiết kế trang chi tiết sản phẩm có hai khối mà CSDL chưa có chỗ chứa:
--
--   "Chiết suất — chọn theo độ cận"  ba nút 1.56 / 1.61 / 1.67
--        -> đây là BIẾN THỂ: cùng một mặt hàng, khách chọn một phương án và
--           phương án đó phải đi theo vào giỏ, vào đơn, và trừ đúng tồn kho.
--
--   "Đánh giá (45)"                  từng nhận xét kèm tên, sao, ngày
--        -> hiện chỉ có hai SỐ TỔNG trong products.rating / review_count,
--           không có dòng nào chứa nội dung nhận xét.
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE và sẽ
-- xoá sạch đơn hàng, lịch hẹn, tài khoản khách.
--
-- Cách chạy
--   Trên máy:      mysql -u <user> -p <ten_db> < file_này.sql
--   InfinityFree:  vPanel -> phpMyAdmin -> chọn database -> tab SQL
--
-- Chạy hai lần thì MySQL báo "Table already exists" / "Duplicate column name".
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. BIẾN THỂ SẢN PHẨM
--
-- `price_delta` là CHÊNH LỆCH so với products.price, không phải giá tuyệt đối.
-- Lý do: đổi giá một mặt hàng thì chỉ sửa MỘT số ở products, ba biến thể tự
-- theo. Lưu giá tuyệt đối ở đây thì mỗi lần đổi giá phải nhớ sửa đủ ba dòng,
-- và quên một dòng là bán sai giá mà không ai thấy.
-- Cho phép ÂM: biến thể rẻ hơn bản gốc (chiết suất 1.56 rẻ hơn 1.61).
--
-- `stock_quantity` riêng cho từng biến thể: cửa hàng có thể còn tròng 1.61
-- nhưng hết 1.67. Tồn kho ở products vẫn giữ nguyên ý nghĩa cho mặt hàng KHÔNG
-- có biến thể — xem ProductModel::inStock.
--
-- `position` để người quản trị xếp thứ tự hiển thị (1.56 trước 1.61 trước
-- 1.67), không phụ thuộc thứ tự nhập liệu.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id`             CHAR(36)     NOT NULL DEFAULT (UUID()),
    `product_id`     CHAR(36)     NOT NULL,
    `label`          VARCHAR(60)  NOT NULL,
    `note`           VARCHAR(120) NULL,
    `price_delta`    BIGINT       NOT NULL DEFAULT 0,
    `stock_quantity` INT          NOT NULL DEFAULT 0,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `position`       INT          NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Hai biến thể cùng nhãn trong một mặt hàng là lỗi nhập liệu, không phải
    -- trường hợp hợp lệ — để CSDL chặn thay vì trông chờ người nhập cẩn thận.
    UNIQUE KEY `uq_variant_label` (`product_id`, `label`),
    KEY `idx_variant_product` (`product_id`, `position`),
    CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2. BIẾN THỂ ĐÃ MUA, GHI VÀO DÒNG HÀNG
--
-- `variant_label` CHÉP LẠI nhãn tại thời điểm mua, y như product_name và
-- unit_price ở cùng bảng: đổi tên biến thể hay xoá nó đi thì hoá đơn cũ vẫn
-- phải đọc được đúng thứ khách đã mua.
--
-- `variant_id` chỉ để tra cứu ngược (thống kê biến thể nào bán chạy), nên
-- SET NULL khi biến thể bị xoá.
-- ----------------------------------------------------------------------------
ALTER TABLE `order_items`
    ADD COLUMN `variant_id`    CHAR(36)    NULL AFTER `product_id`,
    ADD COLUMN `variant_label` VARCHAR(60) NULL AFTER `variant_id`,
    ADD KEY `idx_order_items_variant` (`variant_id`),
    ADD CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`)
        REFERENCES `product_variants` (`id`) ON DELETE SET NULL;


-- ----------------------------------------------------------------------------
-- 3. ĐÁNH GIÁ
--
-- `author_name` chép lại tên lúc viết, KHÔNG join sang profiles mỗi lần hiện:
-- khách đổi tên hồ sơ về sau thì đánh giá cũ vẫn mang tên lúc họ viết, và xoá
-- tài khoản (user_id thành NULL) không làm mất tên người đánh giá.
--
-- `order_id` là thứ chứng minh "Đã mua" — huy hiệu trong bản thiết kế. NULL =
-- đánh giá do nhân viên nhập hộ (thu từ Facebook, tại quầy), không có huy hiệu.
--
-- `status` mặc định 'pending': đánh giá KHÔNG hiện ngay. Một ô nhập văn bản
-- công khai mà đăng thẳng lên trang sản phẩm là lời mời spam và bôi nhọ.
--
-- UNIQUE (order_id, product_id): mỗi đơn chỉ đánh giá mỗi mặt hàng một lần.
-- MySQL cho nhiều dòng cùng NULL trong khoá UNIQUE, nên đánh giá do nhân viên
-- nhập (order_id NULL) không bị ràng buộc này chặn.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
    `id`            CHAR(36)     NOT NULL DEFAULT (UUID()),
    `product_id`    CHAR(36)     NOT NULL,
    `user_id`       CHAR(36)     NULL,
    `order_id`      CHAR(36)     NULL,
    `author_name`   VARCHAR(255) NOT NULL,
    `rating`        TINYINT      NOT NULL,
    `body`          TEXT         NOT NULL,
    -- Bản thiết kế in "Đã mua · Chiết suất 1.61 · 08/2026" dưới tên người viết
    `variant_label` VARCHAR(60)  NULL,
    `status`        VARCHAR(16)  NOT NULL DEFAULT 'pending',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_review_order_product` (`order_id`, `product_id`),
    -- Trang sản phẩm luôn lọc theo (product_id, status) rồi xếp theo ngày
    KEY `idx_reviews_product` (`product_id`, `status`, `created_at`),
    KEY `idx_reviews_status`  (`status`),
    KEY `idx_reviews_user`    (`user_id`),
    CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 4. DỮ LIỆU MẪU  (TUỲ CHỌN — chỉ chạy trên bản demo/dev)
--
-- Ba biến thể chiết suất cho mặt hàng tròng kính, đúng như bản thiết kế vẽ.
-- Trên kho hàng thật thì bỏ qua và nhập bằng khu quản trị.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `product_variants` (`product_id`, `label`, `note`, `price_delta`, `stock_quantity`, `position`)
SELECT p.`id`, v.`label`, v.`note`, v.`delta`, v.`stock`, v.`pos`
  FROM `products` p
  JOIN (
        SELECT '1.56' AS `label`, 'Cận dưới 3.00'    AS `note`, -250000 AS `delta`, 40 AS `stock`, 1 AS `pos`
  UNION SELECT '1.61',            'Cận 3.00 – 6.00',                 0,            49,           2
  UNION SELECT '1.67',            'Cận trên 6.00',            600000,            18,           3
  ) v
 WHERE p.`sku` = 'VEW-L05';


-- ----------------------------------------------------------------------------
-- 5. ĐỒNG BỘ SỐ TỔNG  (BẮT BUỘC — không phải tuỳ chọn)
--
-- products.rating / review_count trước đây là số gõ tay, không có gì đối
-- chiếu. Từ nay chúng là SỐ TỔNG tính lại từ bảng `reviews`, nên phải đưa về
-- đúng ngay bây giờ — nếu không trang sản phẩm sẽ tự mâu thuẫn: phần đầu ghi
-- "45 đánh giá" còn khối bên dưới ghi "Chưa có đánh giá nào".
--
-- Vài đánh giá mẫu bên dưới là TUỲ CHỌN (bỏ qua trên site thật); câu UPDATE
-- cuối cùng thì không.
-- ----------------------------------------------------------------------------

-- Đánh giá mẫu — order_id NULL nghĩa là "nhân viên nhập hộ", nên chúng KHÔNG
-- mang huy hiệu "Đã mua". Huy hiệu đó chỉ thuộc về đánh giá gắn với đơn thật.
INSERT IGNORE INTO `reviews` (`id`, `product_id`, `author_name`, `rating`, `body`, `variant_label`, `status`)
SELECT UUID(), p.`id`, r.`who`, r.`stars`, r.`text`, r.`variant`, 'published'
  FROM `products` p
  JOIN (
        SELECT 'Trần Minh Quân' AS `who`, 5 AS `stars`, 'Chiết suất 1.61' AS `variant`,
               'Đeo làm việc máy tính cả ngày đỡ mỏi mắt hẳn. Cắt lắp đúng 60 phút như hẹn.' AS `text`
  UNION SELECT 'Nguyễn Thu Hà', 5, 'Chiết suất 1.67',
               'Cận 7 độ mà tròng vẫn mỏng, nhẹ hơn kính cũ nhiều. Nhân viên tư vấn đúng loại theo độ.'
  UNION SELECT 'Lê Hoàng Nam', 4, 'Chiết suất 1.61',
               'Tròng tốt, nhìn rõ và không bị loá. Trừ một sao vì phải chờ hơi lâu vào cuối tuần.'
  ) r
 WHERE p.`sku` = 'VEW-L05';

-- Đưa mọi số tổng về đúng với bảng `reviews`. Mặt hàng chưa có đánh giá nào
-- trả về 5.0 / 0 — đúng giá trị mặc định của cột.
UPDATE `products` p
   SET p.`review_count` = (SELECT COUNT(*) FROM `reviews` r
                            WHERE r.`product_id` = p.`id` AND r.`status` = 'published'),
       p.`rating` = COALESCE((SELECT ROUND(AVG(r.`rating`), 1) FROM `reviews` r
                               WHERE r.`product_id` = p.`id` AND r.`status` = 'published'), 5.0);
