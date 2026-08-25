-- ============================================================================
-- NÂNG CẤP 2026-08-25
-- Bộ sưu tập chuyển từ config sang CSDL, có trang riêng và CRUD quản trị
--
-- Trước file này, bộ sưu tập sống trong config/collections.php: ba mục cứng,
-- mỗi mục có slug · tên · tagline · ảnh. Cột `products.collection` đã trỏ tới
-- slug đó và /san-pham?collection=<slug> đã lọc thật — phần ấy KHÔNG đổi.
--
-- Cái đổi là AI SỬA ĐƯỢC. Config nghĩa là mỗi lần cửa hàng ra bộ mới phải sửa
-- mã và deploy; nay nhân viên tự thêm/sửa/ẩn trong khu quản trị.
--
-- HAI CỘT MỚI SO VỚI CONFIG
--
--   `intro`       — đoạn giới thiệu dài, hiện trên trang /bo-suu-tap. Tách
--                   khỏi `tagline` (một dòng, dùng cho thẻ ở trang chủ và
--                   mega menu) vì hai chỗ cần độ dài rất khác nhau.
--   `launched_at` — ngày ra mắt. DATE chứ không DATETIME: bộ sưu tập ra mắt
--                   theo ngày, không ai quan tâm mấy giờ.
--
-- `sort_order` để cửa hàng tự xếp thứ tự trưng bày. Sắp theo `launched_at`
-- không đủ: hai bộ ra cùng ngày, hoặc bộ cũ đang muốn đẩy lên đầu vì còn hàng.
--
-- ẢNH BÌA GIEO BẰNG ẢNH CÓ THẬT TRONG KHO, không phải đường dẫn mong muốn.
--
-- config/collections.php cũ trỏ tới assets/images/collections/<slug>.jpg —
-- những file CHƯA TỒN TẠI (xem assets/images/collections/README.md) — rồi kèm
-- một khoá 'image_sample' để view tự lùi về ảnh có thật. Cơ chế hai đường ấy
-- không mang sang đây được: bảng chỉ có một cột ảnh, và quan trọng hơn, nhân
-- viên sửa ảnh trong khu quản trị thì không ai đi sửa một khoá dự phòng nằm
-- trong mã.
--
-- Nên gieo thẳng ảnh mẫu đang có. Khi chụp được ảnh lookbook thật, thay bằng
-- khu quản trị — đó chính là việc bảng này sinh ra để làm.
--
-- GIEO SẴN BA BỘ ĐANG CÓ, giữ nguyên slug. Slug là thứ nối với
-- products.collection và với mọi link /san-pham?collection=… đã phát ra ngoài
-- — đổi slug là làm chết cả hai. INSERT IGNORE nên chạy lại không nhân đôi.
--
-- Chạy lại nhiều lần không hỏng: CREATE TABLE IF NOT EXISTS + INSERT IGNORE.
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `collections` (
    `id`          CHAR(36)     NOT NULL DEFAULT (UUID()),
    -- Khớp `products.collection`. ĐỪNG đổi slug của một bộ đã phát hành.
    `slug`        VARCHAR(64)  NOT NULL,
    `name`        VARCHAR(160) NOT NULL,
    -- Một dòng, cho thẻ ở trang chủ và mega menu.
    `tagline`     VARCHAR(255) NULL,
    -- Đoạn dài, cho trang /bo-suu-tap.
    `intro`       TEXT         NULL,
    `cover_image` VARCHAR(500) NULL,
    `launched_at` DATE         NULL,
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    `is_visible`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_collections_slug` (`slug`),
    KEY `idx_collections_visible` (`is_visible`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `collections`
    (`slug`, `name`, `tagline`, `intro`, `cover_image`, `sort_order`)
VALUES
    ('nang-he', 'Nắng hè',
     'Kính mát phân cực, dáng phi công và mắt mèo cho những ngày chói nhất.',
     'Bộ sưu tập dành cho mùa nắng gắt: tròng phân cực cắt chói mặt đường và mặt nước, gọng nhẹ đeo cả ngày không hằn thái dương. Dáng phi công và mắt mèo là hai kiểu hợp với nhiều khuôn mặt nhất, nên đây cũng là chỗ dễ chọn nhất nếu bạn mua kính mát lần đầu.',
     'assets/images/showroom-storefront.jpg', 10),
    ('nhe-ca-ngay', 'Nhẹ cả ngày',
     'Gọng titanium và tròng lọc ánh sáng xanh cho tám tiếng trước màn hình.',
     'Dành cho người ngồi máy tính cả ngày. Gọng titanium nhẹ tới mức quên là mình đang đeo, tròng lọc ánh sáng xanh giảm mỏi mắt về cuối buổi chiều. Toàn bộ gọng trong bộ này đều cắt được tròng theo độ.',
     'assets/images/hero-eyewear.jpg', 20),
    ('co-dien-tro-lai', 'Cổ điển trở lại',
     'Acetate bản dày, dáng tròn và vuông — kiểu gọng không bao giờ cũ.',
     'Acetate bản dày, đánh bóng thủ công, dáng tròn và vuông lấy cảm hứng từ những năm 50–70. Kiểu gọng này tôn đường nét khuôn mặt rõ hơn gọng kim loại mảnh, và không lỗi mốt theo mùa.',
     'assets/images/showroom-frames.jpg', 30);
