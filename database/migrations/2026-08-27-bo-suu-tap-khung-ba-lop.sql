-- ============================================================================
-- NÂNG CẤP 2026-08-27 (thứ ba trong ngày)
-- Trang chi tiết bộ sưu tập: khung thông tin ba lớp
--
-- 14 cột cho `collections` · 27 cột cho `products` · 2 cột cho
-- `product_variants` · một bảng `collection_faqs`. Không thêm chỉ mục nào —
-- lý do ở cuối file.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- FILE NÀY KHÔNG THEO NẾP "MỖI CỘT MỘT KHỐI PREPARE"
--
-- Mọi migration trước đều thêm một hai cột, và mỗi cột là một khối mười hai
-- dòng hỏi information_schema rồi PREPARE. Ở đây có 43 cột: chép nếp cũ ra là
-- hơn năm trăm dòng gần như giống hệt nhau, và một file như thế thì không ai
-- đọc — người ta lướt, mà lướt qua năm trăm dòng SQL là cách bỏ sót một chữ
-- viết sai trong tên cột.
--
-- Nên mỗi bảng ở đây là MỘT câu ALTER duy nhất, dựng động từ danh sách cột
-- mong muốn trừ đi những cột đã có. Đọc được: danh sách cột nằm thành một khối
-- liền, đúng thứ tự chúng sẽ nằm trong bảng.
--
-- Vẫn chạy lại được nhiều lần: cột nào đã có thì không lọt vào câu ALTER, và
-- không còn cột nào thiếu thì câu ALTER không được dựng ra.
--
-- KHÔNG dùng `ADD COLUMN IF NOT EXISTS` — cú pháp đó có ở MariaDB nhưng KHÔNG
-- có ở MySQL 8, mà máy dev chạy MariaDB còn hosting chạy MySQL.
-- KHÔNG dùng stored procedure — nó cần đổi DELIMITER, thứ mà mỗi công cụ
-- (mysql CLI, phpMyAdmin, trình khách của hosting) lại xử một kiểu.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO CÓ CỘT NÀY MÀ KHÔNG CÓ CỘT KIA
--
-- Sáu thứ trong khung thông tin CỐ Ý không được cấp cột, vì chúng suy ra được
-- từ dữ liệu đã có, và một bản sao thì sẽ có ngày lệch khỏi bản gốc:
--
--   số mẫu trong bộ    COUNT(products WHERE collection = slug)
--   số phối màu, SKU   COUNT(product_variants) của các mẫu ấy
--   khoảng giá         MIN(price), MAX(price)
--   cỡ S / M / L       quy từ frame_width_mm qua ngưỡng ở config/eyewear.php
--   chuỗi 52□18-145    ghép lens_width_mm □ bridge_mm - temple_mm
--   bảng dáng mặt      gom face_shapes của các mẫu trong bộ
--
-- Và bốn thứ nằm ở config/eyewear.php chứ không ở đây, vì chúng đúng với MỌI
-- bộ và mọi mẫu: cách đo gọng cũ, ngưỡng cỡ, hướng dẫn bảo quản, và bản mặc
-- định của bảo hành / đổi trả / phụ kiện / chứng nhận.
--
-- Bốn cột `warranty` · `return_policy` · `accessories` · `certifications` trên
-- `products` để TRỐNG ở hầu hết mặt hàng: trống nghĩa là "theo chính sách
-- chung". Chỉ điền khi mặt hàng phải nói KHÁC — gọng cận đã cắt tròng theo đơn
-- thì không đổi trả được, dù chính sách chung cho bảy ngày.
-- ============================================================================

-- Danh sách cột dựng bằng GROUP_CONCAT; mặc định 1024 ký tự không đủ cho 27 cột.
SET SESSION group_concat_max_len = 32768;


-- ----------------------------------------------------------------------------
-- 1. `collections` — LỚP 1: những gì đúng với CẢ BỘ
-- ----------------------------------------------------------------------------
SET @cot := (
    SELECT GROUP_CONCAT(x.ddl SEPARATOR ', ')
      FROM (
            SELECT 'season_code' AS c, 'ADD COLUMN `season_code` VARCHAR(12) NULL DEFAULT NULL' AS ddl
      UNION ALL SELECT 'season_label',  'ADD COLUMN `season_label` VARCHAR(60) NULL DEFAULT NULL'
      UNION ALL SELECT 'brand',         'ADD COLUMN `brand` VARCHAR(120) NULL DEFAULT NULL'
      UNION ALL SELECT 'product_line',  'ADD COLUMN `product_line` VARCHAR(120) NULL DEFAULT NULL'
      UNION ALL SELECT 'designed_in',   'ADD COLUMN `designed_in` VARCHAR(120) NULL DEFAULT NULL'
      UNION ALL SELECT 'made_in',       'ADD COLUMN `made_in` VARCHAR(120) NULL DEFAULT NULL'
      UNION ALL SELECT 'audience',      'ADD COLUMN `audience` JSON NULL DEFAULT NULL'
      UNION ALL SELECT 'design_style',  'ADD COLUMN `design_style` VARCHAR(160) NULL DEFAULT NULL'
      UNION ALL SELECT 'palette',       'ADD COLUMN `palette` JSON NULL DEFAULT NULL'
      UNION ALL SELECT 'signature',     'ADD COLUMN `signature` JSON NULL DEFAULT NULL'
      UNION ALL SELECT 'launch_offer',  'ADD COLUMN `launch_offer` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'channels',      'ADD COLUMN `channels` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'meta_title',    'ADD COLUMN `meta_title` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'meta_description', 'ADD COLUMN `meta_description` VARCHAR(320) NULL DEFAULT NULL'
      ) x
     WHERE NOT EXISTS (
           SELECT 1 FROM information_schema.COLUMNS ic
            WHERE ic.TABLE_SCHEMA = DATABASE()
              AND ic.TABLE_NAME   = 'collections'
              AND ic.COLUMN_NAME  = x.c
     )
);

SET @sql := IF(@cot IS NULL,
    'SELECT ''collections: khong thieu cot nao, bo qua'' AS ghi_chu',
    CONCAT('ALTER TABLE `collections` ', @cot)
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;


-- ----------------------------------------------------------------------------
-- 2. `products` — LỚP 2: những gì khác nhau giữa các MẪU
--
-- Ba nhóm, xếp theo đúng thứ tự ngăn kéo thông số đọc chúng:
--   gọng (dáng, chất liệu, hoàn thiện, bản lề, đệm mũi, nặng)
--   kích thước (ba số của chuẩn ghi, tổng rộng, cao tròng, dáng mặt)
--   tròng (chất liệu, chiết suất, lớp phủ, phân cực, đổi màu, VLT, cấp, base)
-- rồi tới thương mại và chứng nhận.
--
-- `lens_index` là DECIMAL(3,2) chứ không phải VARCHAR: 1.61 và "1.61" trông
-- giống nhau cho tới lúc ai đó gõ "1,61" hoặc "1.610", và bảng so sánh sắp xếp
-- theo cột này thì chuỗi cho ra thứ tự sai.
--
-- `lens_vlt` thì NGƯỢC LẠI, là VARCHAR: tròng đổi màu có hai đầu ("18% → 62%")
-- nên nó không phải một con số. Ép thành số là mất mất nửa sự thật.
-- ----------------------------------------------------------------------------
SET @cot := (
    SELECT GROUP_CONCAT(x.ddl SEPARATOR ', ')
      FROM (
            SELECT 'eyewear_type' AS c, 'ADD COLUMN `eyewear_type` VARCHAR(20) NULL DEFAULT NULL' AS ddl
      UNION ALL SELECT 'frame_finish',    'ADD COLUMN `frame_finish` VARCHAR(120) NULL DEFAULT NULL'
      UNION ALL SELECT 'hinge_type',      'ADD COLUMN `hinge_type` VARCHAR(120) NULL DEFAULT NULL'
      UNION ALL SELECT 'nose_pad',        'ADD COLUMN `nose_pad` VARCHAR(120) NULL DEFAULT NULL'
      UNION ALL SELECT 'weight_g',        'ADD COLUMN `weight_g` SMALLINT UNSIGNED NULL DEFAULT NULL'
      UNION ALL SELECT 'lens_width_mm',   'ADD COLUMN `lens_width_mm` TINYINT UNSIGNED NULL DEFAULT NULL'
      UNION ALL SELECT 'bridge_mm',       'ADD COLUMN `bridge_mm` TINYINT UNSIGNED NULL DEFAULT NULL'
      UNION ALL SELECT 'temple_mm',       'ADD COLUMN `temple_mm` TINYINT UNSIGNED NULL DEFAULT NULL'
      UNION ALL SELECT 'frame_width_mm',  'ADD COLUMN `frame_width_mm` SMALLINT UNSIGNED NULL DEFAULT NULL'
      UNION ALL SELECT 'lens_height_mm',  'ADD COLUMN `lens_height_mm` TINYINT UNSIGNED NULL DEFAULT NULL'
      UNION ALL SELECT 'face_shapes',     'ADD COLUMN `face_shapes` VARCHAR(160) NULL DEFAULT NULL'
      UNION ALL SELECT 'lens_material',   'ADD COLUMN `lens_material` VARCHAR(120) NULL DEFAULT NULL'
      UNION ALL SELECT 'lens_index',      'ADD COLUMN `lens_index` DECIMAL(3,2) NULL DEFAULT NULL'
      UNION ALL SELECT 'lens_coatings',   'ADD COLUMN `lens_coatings` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'is_polarized',    'ADD COLUMN `is_polarized` TINYINT(1) NOT NULL DEFAULT 0'
      UNION ALL SELECT 'is_photochromic', 'ADD COLUMN `is_photochromic` TINYINT(1) NOT NULL DEFAULT 0'
      UNION ALL SELECT 'lens_vlt',        'ADD COLUMN `lens_vlt` VARCHAR(40) NULL DEFAULT NULL'
      UNION ALL SELECT 'lens_category',   'ADD COLUMN `lens_category` TINYINT UNSIGNED NULL DEFAULT NULL'
      UNION ALL SELECT 'base_curve',      'ADD COLUMN `base_curve` VARCHAR(20) NULL DEFAULT NULL'
      UNION ALL SELECT 'rx_ready',        'ADD COLUMN `rx_ready` TINYINT(1) NOT NULL DEFAULT 0'
      UNION ALL SELECT 'rx_note',         'ADD COLUMN `rx_note` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'price_with_lens', 'ADD COLUMN `price_with_lens` BIGINT NULL DEFAULT NULL'
      UNION ALL SELECT 'accessories',     'ADD COLUMN `accessories` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'warranty',        'ADD COLUMN `warranty` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'return_policy',   'ADD COLUMN `return_policy` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'certifications',  'ADD COLUMN `certifications` VARCHAR(255) NULL DEFAULT NULL'
      UNION ALL SELECT 'barcode',         'ADD COLUMN `barcode` VARCHAR(40) NULL DEFAULT NULL'
      ) x
     WHERE NOT EXISTS (
           SELECT 1 FROM information_schema.COLUMNS ic
            WHERE ic.TABLE_SCHEMA = DATABASE()
              AND ic.TABLE_NAME   = 'products'
              AND ic.COLUMN_NAME  = x.c
     )
);

SET @sql := IF(@cot IS NULL,
    'SELECT ''products: khong thieu cot nao, bo qua'' AS ghi_chu',
    CONCAT('ALTER TABLE `products` ', @cot)
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;


-- ----------------------------------------------------------------------------
-- 3. `product_variants` — PHỐI MÀU cần mã màu và ảnh riêng
--
-- Bảng này đang phục vụ MỌI loại phương án (chiết suất tròng, màu gọng, cỡ),
-- nên hai cột dưới đây chỉ có nghĩa với phương án MÀU. Để NULL ở phương án
-- khác là đúng — ngăn kéo thông số chỉ vẽ ô màu cho biến thể nào có mã màu.
-- ----------------------------------------------------------------------------
SET @cot := (
    SELECT GROUP_CONCAT(x.ddl SEPARATOR ', ')
      FROM (
            SELECT 'swatch_hex' AS c, 'ADD COLUMN `swatch_hex` VARCHAR(7) NULL DEFAULT NULL' AS ddl
      UNION ALL SELECT 'image',        'ADD COLUMN `image` VARCHAR(500) NULL DEFAULT NULL'
      ) x
     WHERE NOT EXISTS (
           SELECT 1 FROM information_schema.COLUMNS ic
            WHERE ic.TABLE_SCHEMA = DATABASE()
              AND ic.TABLE_NAME   = 'product_variants'
              AND ic.COLUMN_NAME  = x.c
     )
);

SET @sql := IF(@cot IS NULL,
    'SELECT ''product_variants: khong thieu cot nao, bo qua'' AS ghi_chu',
    CONCAT('ALTER TABLE `product_variants` ', @cot)
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;


-- ----------------------------------------------------------------------------
-- 4. `collection_faqs` — CÂU HỎI THƯỜNG GẶP, RIÊNG TỪNG BỘ
--
-- Đây là thứ DUY NHẤT của lớp 3 phải có bảng. Ba khối còn lại (cách đo gọng,
-- bảo quản, ngưỡng cỡ) giống hệt nhau ở mọi bộ nên nằm ở config/eyewear.php.
--
-- FAQ thì không: "kính râm bộ này lắp được độ cận không" là câu chỉ có nghĩa
-- với một bộ toàn kính râm, và câu trả lời nhắc đích danh mẫu nào lắp được tới
-- bao nhiêu độ. Nhét chúng vào config là bắt người viết nội dung phải sửa mã
-- và deploy mỗi lần ra bộ mới — đúng cái mà bảng `collections` đã bỏ đi hôm
-- 2026-08-25.
--
-- ON DELETE CASCADE: xoá bộ là xoá câu hỏi của nó. Không có bộ thì câu trả lời
-- "bốn trong sáu mẫu lắp được" không còn nghĩa gì để giữ lại.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `collection_faqs` (
    `id`            CHAR(36)     NOT NULL DEFAULT (UUID()),
    `collection_id` CHAR(36)     NOT NULL,
    `question`      VARCHAR(255) NOT NULL,
    `answer`        TEXT         NOT NULL,
    `sort_order`    SMALLINT     NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_collection_faqs` (`collection_id`, `sort_order`),
    CONSTRAINT `fk_collection_faqs_collection` FOREIGN KEY (`collection_id`)
        REFERENCES `collections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- KHÔNG THÊM CHỈ MỤC NÀO
--
-- Câu chạy nhiều nhất trong đợt này là "mọi mặt hàng đang hiện của bộ <slug>",
-- và `products` ĐÃ có sẵn `idx_products_collection (collection)` từ lược đồ
-- gốc. Cột dẫn đầu đã đúng nên chỉ mục đó phục vụ được câu trên; nới thành
-- (collection, is_visible) chỉ bớt được một lượt đọc hàng cho mỗi mặt hàng đã
-- ẩn — với kho vài chục gọng thì đó không phải một phép đo, đó là mê tín.
--
-- Ghi lại ở đây để lần sau không ai mở file này ra và tưởng là đã quên.
-- ----------------------------------------------------------------------------
