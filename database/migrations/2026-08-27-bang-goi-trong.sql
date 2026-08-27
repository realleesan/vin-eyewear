-- ============================================================================
-- NÂNG CẤP 2026-08-27
-- Gói chiết suất rời config xuống CSDL, để cửa hàng tự thêm/sửa/xoá
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO ĐỔI
--
-- Bản 2026-08-22 đã đưa GIÁ tròng xuống bảng `lens_prices` nhưng cố ý để lại
-- DANH MỤC gói (mã · tên · mô tả) trong config/taxonomy.php, với lý lẽ: "thêm
-- một gói là một quyết định về sản phẩm chứ không phải một lần chỉnh giá".
--
-- Lý lẽ đó sai ở một chỗ: quyết định về sản phẩm CŨNG là quyết định của cửa
-- hàng, không phải của lập trình viên. Nhập được phôi 1.74, hay ngừng bán
-- Photochromic, là chuyện xảy ra vài tháng một lần — và mỗi lần đều phải sửa
-- file rồi triển khai lại, đúng cái việc mà bản trước đã kết luận là "không ai
-- ở cửa hàng làm được".
--
-- Nay danh mục xuống đây và sửa được ở /quan-tri/gia-trong/goi.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MÃ GIỮ NGUYÊN DẠNG CHỮ, KHÔNG ĐỔI SANG UUID
--
-- `id` là VARCHAR(40) chứa đúng những mã đang dùng ('clear-150', 'blue-161'…)
-- chứ không phải khoá tự sinh. Bắt buộc phải thế: hai chỗ khác đã lưu sẵn
-- những mã ấy và không được đụng tới —
--
--     order_items.lens_id       chép mã lúc khách đặt, là dữ liệu hoá đơn
--     lens_prices.lens_package  mười lăm ô giá đang trỏ vào
--
-- Đổi sang UUID nghĩa là phải viết một bước dịch mã cho cả hai bảng, và mọi
-- đơn cũ mất đường lần về gói đã mua. Giữ nguyên mã thì bảng này chèn vào là
-- xong, không câu UPDATE nào chạm vào dữ liệu đang có.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- KHÔNG CÓ KHOÁ NGOẠI TỪ `lens_prices` HAY `order_items` SANG ĐÂY
--
-- `order_items` thì rõ: hoá đơn phải đứng yên kể cả khi gói bị xoá, y như
-- `product_name` và `unit_price` vẫn còn khi sản phẩm bị gỡ. Một khoá ngoại ở
-- đó sẽ CHẶN việc xoá gói, hoặc tệ hơn là ON DELETE CASCADE xoá theo dòng hàng
-- của một đơn đã phát sinh tiền thật.
--
-- `lens_prices` thì cố ý dọn bằng MÃ chứ không bằng khoá ngoại — xem
-- LensModel::deletePackage(), nó xoá gói và các ô giá của gói trong cùng một
-- transaction. Lý do: bảng ấy cũng trỏ sang `lens_types`, thứ vẫn còn nằm
-- trong config; đặt khoá ngoại cho một nửa số cột làm người đọc lược đồ tưởng
-- cả hai nửa đều được CSDL bảo vệ.
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE và sẽ
-- xoá sạch đơn hàng, lịch hẹn, tài khoản khách.
--
-- Cách chạy
--   Trên máy:      sudo bash database/migrate.sh
--   InfinityFree:  vPanel -> phpMyAdmin -> chọn database -> tab SQL
--                  -> dán toàn bộ nội dung file -> Go
--
-- CHẠY LẠI NHIỀU LẦN KHÔNG HỎNG: CREATE TABLE IF NOT EXISTS và INSERT IGNORE.
-- Đặc biệt là INSERT IGNORE — cửa hàng đã sửa tên hay mô tả một gói rồi mà file
-- chạy lại thì bản sửa của họ Ở NGUYÊN, không bị năm dòng mẫu dưới đây ghi đè.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `lens_packages` (
    -- Mã dạng chữ, chính là thứ order_items.lens_id và lens_prices.lens_package
    -- đang lưu. VARCHAR(40) để khớp đúng bề rộng của cả hai cột ấy.
    --
    -- KHÔNG SỬA ĐƯỢC SAU KHI TẠO (màn quản trị khoá ô này lại): đổi mã là cắt
    -- đứt cả các ô giá lẫn đường lần về gói của mọi đơn đã bán, mà không có gì
    -- báo cho ai biết.
    `id`          VARCHAR(40)  NOT NULL,
    -- 160 ký tự để khớp order_items.lens_name — chỗ tên này được CHÉP vào lúc
    -- khách đặt. Cho tên dài hơn ô chép sang thì hoá đơn giữ một cái tên cụt.
    `name`        VARCHAR(160) NOT NULL,
    -- Dòng mô tả dưới tên, ở hộp mua hàng và ở khối "Gói tròng phổ biến" trang
    -- chủ. Để rỗng được: gói mới nhập về chưa nghĩ ra câu mô tả thì vẫn bán.
    `description` VARCHAR(255) NULL,
    -- Thứ tự hiện ra cho khách. Trước đây là thứ tự viết trong mảng PHP; nay
    -- phải có một cột thật, nếu không thứ tự sẽ là thứ tự CSDL trả về — tức là
    -- đổi tuỳ lúc, và gói rẻ nhất không còn chắc đứng đầu.
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Sắp theo sort_order rồi tới id: hai gói cùng số thứ tự vẫn phải ra cùng
    -- một trật tự ở mọi lần đọc, không thì danh sách nhảy chỗ giữa hai lần tải.
    KEY `idx_lens_packages_sort` (`sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- NĂM GÓI ĐANG BÁN — chép nguyên văn từ config/taxonomy.php
--
-- Mã, tên, mô tả giống hệt bản trong config, kể cả thứ tự. Phải giống hệt:
-- mười lăm ô trong `lens_prices` đang trỏ vào đúng năm mã này, và mọi đơn đã
-- bán cũng vậy. Lệch một ký tự là một gói mồ côi giá và một gói không ai mua
-- được.
--
-- sort_order cách nhau 10 để về sau chèn một gói vào giữa mà không phải đánh
-- số lại cả bảng.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `lens_packages` (`id`, `name`, `description`, `sort_order`) VALUES
    ('clear-150', 'Tròng trắng 1.50',
     'Phù hợp độ cận/viễn nhẹ đến trung bình (dưới -4.00)', 10),
    ('clear-156', 'Tròng trắng 1.56',
     'Mỏng hơn, phù hợp cận trung bình (-4.00 → -6.00)', 20),
    ('blue-161',  'Chống sáng xanh 1.61',
     'Bảo vệ mắt khi làm việc máy tính nhiều giờ', 30),
    ('blue-167',  'Chống sáng xanh 1.67',
     'Siêu mỏng, thẩm mỹ cao, cận nặng (trên -6.00)', 40),
    ('photo-156', 'Đổi màu Photochromic 1.56',
     'Tự điều chỉnh theo ánh sáng, tiện dùng trong/ngoài trời', 50);
