-- ============================================================================
-- NÂNG CẤP 2026-08-22
-- Bảng giá tròng theo KIỂU × GÓI, và giá rời config xuống CSDL
--
-- Trước bản này, phần tròng có đúng MỘT giá cho mỗi gói chiết suất, nằm ở
-- config/taxonomy.php. Từ khi bước chọn tròng tách hai tầng (kiểu tròng ·
-- gói chiết suất), giá một-chiều đó thành sai: đơn tròng và đa tròng cùng
-- chọn phôi 1.61 ra cùng số tiền, trong khi mài đa tròng đắt hơn nhiều lần.
--
-- Nay mỗi Ô (kiểu, gói) có giá riêng — 3 kiểu có bảng giá × 5 gói = 15 ô.
-- "Mắt đặt" KHÔNG có ô nào: tròng đặt riêng theo đơn thì cửa hàng báo giá
-- sau khi xem thông số, đó là hành vi sẵn có và không đổi.
--
-- VÌ SAO XUỐNG CSDL CHỨ KHÔNG Ở LẠI config:
-- giá là thứ cửa hàng sửa, không phải thứ lập trình viên sửa. 15 ô thì càng
-- đúng — mỗi lần đổi bảng giá mà phải sửa file rồi triển khai lại là một
-- việc không ai ở cửa hàng làm được. Có bảng này thì màn /quan-tri/gia-trong
-- sửa được ngay trên trình duyệt.
--
-- KHÔNG có khoá ngoại cho `lens_type` và `lens_package`: bên kia là hai mảng
-- PHP trong config/taxonomy.php, không phải bảng. Cùng lý do với
-- `order_items.lens_id` — xem migration 2026-08-18-cat-trong-theo-so-do.sql.
-- DANH MỤC (mã, tên, mô tả) ở lại config vì nó là thứ mã nguồn tham chiếu
-- tới bằng id; chỉ GIÁ mới xuống đây.
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
-- Chạy hai lần thì MySQL báo "Table already exists". Đó là báo an toàn.
-- ============================================================================

CREATE TABLE `lens_prices` (
    -- Mã kiểu tròng trong config/taxonomy.php -> lens_types ('don-trong',
    -- 'hai-trong', 'da-trong'). 'mat-dat' không bao giờ có dòng ở đây.
    `lens_type`    VARCHAR(32) NOT NULL,
    -- Mã gói chiết suất trong config/taxonomy.php -> lens_packages.
    `lens_package` VARCHAR(40) NOT NULL,
    -- Đồng, không thập phân — cùng kiểu với products.price.
    `price`        BIGINT      NOT NULL DEFAULT 0,
    `updated_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    -- Khoá chính là chính CẶP mã: mỗi ô đúng một giá, và INSERT trùng ô sẽ bị
    -- DB chặn thay vì lặng lẽ tạo ra hai giá cho cùng một lựa chọn.
    PRIMARY KEY (`lens_type`, `lens_package`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- GIÁ MỞ ĐẦU — SỐ TẠM, CỬA HÀNG SỬA LẠI TRONG /quan-tri/gia-trong
--
-- Cột "Đơn tròng" giữ nguyên bảng giá cũ trong config, nên khách đang mua đơn
-- tròng không thấy giá nhảy sau khi nâng cấp. Hai cột còn lại nhân lên theo hệ
-- số ×2 (hai tròng) và ×2.8 (đa tròng) — nhân chứ không cộng một khoản cố
-- định, vì phôi càng mỏng thì mài đa tròng càng đắt, cộng phẳng sẽ làm đa
-- tròng trên phôi 1.50 thành đắt tương đối và trên 1.67 thành rẻ.
--
-- Đây là SỐ TẠM để bảng giá không rỗng ngày đầu. Con số thật là việc của cửa
-- hàng và nhập thẳng trên trình duyệt.
-- ----------------------------------------------------------------------------
INSERT INTO `lens_prices` (`lens_type`, `lens_package`, `price`) VALUES
    ('don-trong', 'clear-150',   500000),
    ('don-trong', 'clear-156',   700000),
    ('don-trong', 'blue-161',   1200000),
    ('don-trong', 'blue-167',   1800000),
    ('don-trong', 'photo-156',  2500000),

    ('hai-trong', 'clear-150',  1000000),
    ('hai-trong', 'clear-156',  1400000),
    ('hai-trong', 'blue-161',   2400000),
    ('hai-trong', 'blue-167',   3600000),
    ('hai-trong', 'photo-156',  5000000),

    ('da-trong',  'clear-150',  1400000),
    ('da-trong',  'clear-156',  1960000),
    ('da-trong',  'blue-161',   3360000),
    ('da-trong',  'blue-167',   5040000),
    ('da-trong',  'photo-156',  7000000);
