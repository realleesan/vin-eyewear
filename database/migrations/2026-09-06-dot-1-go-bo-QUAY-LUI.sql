-- ============================================================================
-- QUAY LUI đợt 1 — dựng lại cấu trúc mà 2026-09-06-dot-1-go-bo.sql đã gỡ
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ĐỌC TRƯỚC KHI CHẠY: FILE NÀY KHÔNG TRẢ LẠI DỮ LIỆU
--
-- Nó dựng lại BẢNG RỖNG và CỘT RỖNG. Số đo "kính đang đeo" của khách, tên
-- người được đo, và các dòng phân công cơ sở đều đã đi cùng lệnh DROP và không
-- có câu SQL nào lấy chúng về được.
--
-- Muốn cả cấu trúc lẫn dữ liệu thì KHÔNG chạy file này — hãy nạp lại bản
-- mysqldump đã tạo trước khi chạy migration:
--
--   mysql -u <user> -p <ten_csdl> < vin-eyewear-truoc-dot-1.sql
--
-- File này chỉ dùng cho một tình huống: mã nguồn cũ đã được deploy ngược trở
-- lại và đang đổ lỗi 1054 "Unknown column", cần cấu trúc khớp lại NGAY, và
-- việc mất nội dung mấy cột đó chấp nhận được cho tới khi nạp bản sao lưu.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- BƯỚC 0 (J02) KHÔNG QUAY LUI ĐƯỢC
--
-- Migration đổi mọi dòng 'draft' thành 'hidden'. Sau bước đó không còn gì phân
-- biệt "vốn là Nháp" với "vốn đã Ẩn", nên không có câu UPDATE ngược nào đúng.
-- Danh sách sản phẩm từng ở Nháp chỉ có trong bản sao lưu.
--
-- Cũng vì vậy file này KHÔNG đụng tới `products`.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. C08 — dựng lại kiểu tròng "Mắt đặt"
--
-- `sort_order` 40 để nó về đúng chỗ cũ, sau "Đa tròng" (30).
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `lens_options` (`group_key`, `option_key`, `label`, `note`, `sort_order`)
VALUES ('loai-trong', 'mat-dat', 'Mắt đặt',
        'Độ quá cao hoặc thông số đặc biệt, phải đặt riêng — cửa hàng báo giá sau', 40);


-- ----------------------------------------------------------------------------
-- 2. K06 — dựng lại bảng phân công cơ sở (RỖNG)
--
-- Mã nguồn cũ đọc bảng này qua StaffStoreModel::phamVi(), và ở đó "không có
-- dòng nào" nghĩa là KHÔNG THẤY GÌ, không phải "thấy tất cả" (Q12.3).
--
-- Nên bảng rỗng sẽ khoá mọi nhân viên khỏi đơn hàng và lịch hẹn. Câu seed ở
-- cuối mục này gán lại mọi tài khoản nội bộ vào mọi cơ sở — tức khôi phục đúng
-- hành vi "ai cũng thấy tất cả", giống hệt trạng thái sau khi gỡ phân quyền.
-- Cửa hàng siết lại từng người ở /quan-tri/nhan-vien nếu cần.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_stores` (
    `id`         CHAR(36) NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36) NOT NULL,
    `store_id`   CHAR(36) NOT NULL,
    `granted_by` CHAR(36) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_staff_store` (`user_id`, `store_id`),
    KEY `idx_staff_stores_store` (`store_id`),
    CONSTRAINT `fk_staff_stores_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_staff_stores_store` FOREIGN KEY (`store_id`)
        REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_staff_stores_by` FOREIGN KEY (`granted_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `staff_stores` (`user_id`, `store_id`)
SELECT DISTINCT ur.`user_id`, s.`id`
  FROM `user_roles` ur
 CROSS JOIN `stores` s
 WHERE ur.`role` IN ('staff', 'technician', 'manager');


-- ----------------------------------------------------------------------------
-- 3. Dựng lại bảng `favorites` (RỖNG)
--
-- Bảng này chưa từng có dòng nào, nên đây là lần duy nhất trong file mà "quay
-- lui không mất dữ liệu" đúng theo nghĩa đen.
-- ----------------------------------------------------------------------------
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
-- 4. A20 — dựng lại năm cột "kính đang đeo" (RỖNG, toàn NULL)
--
-- AFTER `recommendation` để thứ tự cột khớp lược đồ cũ. Không bắt buộc về mặt
-- kỹ thuật, nhưng một `SELECT *` đọc ra đúng thứ tự cũ thì dễ đối chiếu với
-- bản sao lưu hơn.
--
-- Chạy lại lần hai báo lỗi 1060 "Duplicate column name" — vô hại, nghĩa là cột
-- đã có.
-- ----------------------------------------------------------------------------
ALTER TABLE `prescriptions` ADD COLUMN `wear_lens_type`     VARCHAR(32)  NULL AFTER `recommendation`;
ALTER TABLE `prescriptions` ADD COLUMN `wear_lens_features` VARCHAR(255) NULL AFTER `wear_lens_type`;
ALTER TABLE `prescriptions` ADD COLUMN `wear_frame_type`    VARCHAR(64)  NULL AFTER `wear_lens_features`;
ALTER TABLE `prescriptions` ADD COLUMN `wear_since`         VARCHAR(32)  NULL AFTER `wear_frame_type`;
ALTER TABLE `prescriptions` ADD COLUMN `wear_note`          VARCHAR(255) NULL AFTER `wear_since`;


-- ----------------------------------------------------------------------------
-- 5. H07 — dựng lại cột "người được đo" (RỖNG, toàn NULL)
--
-- NULL nghĩa là chính chủ tài khoản, nên một cột toàn NULL đọc ra "mọi bản ghi
-- đều của chủ tài khoản" — đúng với thực tế sau khi migration đã trộn chúng
-- lại. Nó KHÔNG khôi phục được việc bản nào từng thuộc về ai.
-- ----------------------------------------------------------------------------
ALTER TABLE `customer_prescriptions` ADD COLUMN `nguoi_duoc_do` VARCHAR(120) NULL;


-- ----------------------------------------------------------------------------
-- SAU KHI CHẠY
--
--   SHOW TABLES LIKE 'staff_stores';                                -- có
--   SHOW TABLES LIKE 'favorites';                                   -- có
--   SHOW COLUMNS FROM prescriptions LIKE 'wear\\_%';                 -- 5 dòng
--   SHOW COLUMNS FROM customer_prescriptions LIKE 'nguoi_duoc_do';  -- 1 dòng
--   SELECT COUNT(*) FROM staff_stores;   -- = số nhân viên × số cơ sở
--
-- Rồi deploy lại mã nguồn TRƯỚC đợt 1. Cấu trúc khớp thì trang chạy; nội dung
-- mấy cột vừa dựng lại vẫn trống cho tới khi nạp bản sao lưu.
-- ----------------------------------------------------------------------------
