-- ============================================================================
-- THUỘC TÍNH TRÒNG KÍNH DO QUẢN TRỊ QUẢN LÝ — 2026-08-30
--
-- Bộ lọc của trang /san-pham/trong-kinh cần bốn nhóm lựa chọn: loại tròng,
-- chiết suất, màu tròng, và tính năng/lớp phủ. Cả bốn hiện là mảng GÕ CỨNG
-- trong config/eyewear.php và config/taxonomy.php, nghĩa là cửa hàng muốn thêm
-- một màu tròng mới thì phải nhờ người sửa mã rồi deploy.
--
-- Bảng này đưa bốn danh sách ấy vào CSDL để sửa được từ khu quản trị.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- KHÔNG THÊM CỘT NÀO VÀO `products` — ĐÃ CÓ ĐỦ
--
-- Đã kiểm trước khi viết file này. Bốn cột lưu LỰA CHỌN của từng sản phẩm đều
-- có sẵn từ trước:
--   lens_types     CSV khoá loại tròng
--   lens_indexes   CSV khoá chiết suất (và lens_index DECIMAL cho tròng lắp sẵn)
--   lens_color     màu tròng — NAY LƯU KHOÁ, trước là chữ tự do người nhập gõ
--   lens_coatings  CSV khoá lớp phủ
-- Bảng mới chỉ định nghĩa DANH SÁCH CHỌN, không đụng tới chỗ lưu lựa chọn.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MỘT BẢNG CHO CẢ BỐN NHÓM, KHÔNG PHẢI BỐN BẢNG
--
-- Bốn nhóm có cấu trúc y hệt nhau: khoá, nhãn, thứ tự, ẩn/hiện. Tách bốn bảng
-- là bốn model, bốn màn quản trị, bốn migration cho mỗi lần đổi — mà không mua
-- thêm được gì. Gộp một bảng thì thêm nhóm thứ năm sau này (chất liệu tròng,
-- độ đậm…) chỉ là thêm một hằng trong mã, không phải một lần đổi lược đồ nữa.
--
-- Đánh đổi đã cân nhắc: một hàng của nhóm "chiết suất" và một hàng của nhóm
-- "màu tròng" nằm chung bảng nên CSDL không tự chặn được việc gán nhầm nhóm.
-- Chặn ở tầng mã (LensOptionModel::GROUPS) đủ, vì không có đường nào ghi vào
-- bảng này ngoài màn quản trị.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- `option_key` LÀ THỨ ĐI VÀO CỘT CỦA `products`, NÊN KHÔNG ĐƯỢC ĐỔI
--
-- Đổi khoá của một lựa chọn đã có hàng gắn vào là làm mồ côi toàn bộ số hàng
-- ấy — chúng vẫn giữ khoá cũ trong CSV và biến mất khỏi bộ lọc mà không báo gì.
-- Màn quản trị vì thế chỉ cho sửa NHÃN, không cho sửa khoá; muốn đổi khoá thì
-- tạo lựa chọn mới rồi gắn lại hàng.
--
-- ẨN thay vì XOÁ, cùng lý do: `is_visible = 0` giữ nguyên khoá cho hàng cũ mà
-- vẫn rút mục đó khỏi bộ lọc và khỏi form nhập.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `lens_options` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    -- Nhóm: 'loai-trong' · 'chiet-suat' · 'mau-trong' · 'lop-phu'
    -- Danh sách hợp lệ ở LensOptionModel::GROUPS.
    `group_key`  VARCHAR(32)  NOT NULL,
    -- Khoá đi vào products.lens_types / lens_indexes / lens_color / lens_coatings
    `option_key` VARCHAR(64)  NOT NULL,
    `label`      VARCHAR(120) NOT NULL,
    -- Câu mô tả ngắn, chỉ dùng ở form quản trị để người nhập biết chọn cái nào.
    `note`       VARCHAR(255) NULL,
    -- Cách nhau 10 để chèn vào giữa không phải đánh số lại — cùng lối với
    -- lens_packages.sort_order.
    `sort_order` SMALLINT     NOT NULL DEFAULT 0,
    `is_visible` TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Khoá phải duy nhất TRONG một nhóm, không phải trên cả bảng: '1.61' là
    -- chiết suất, mà một ngày nào đó cũng có thể là khoá của nhóm khác.
    UNIQUE KEY `uniq_lens_options_key` (`group_key`, `option_key`),
    KEY `idx_lens_options_sort` (`group_key`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- SEED — CHÉP NGUYÊN BỐN DANH SÁCH ĐANG GÕ CỨNG
--
-- Không phải hàng mẫu. Đây đúng là những lựa chọn site đang chạy hôm nay, nên
-- ngày chạy migration này giao diện KHÔNG đổi một chữ — chỉ là từ nay sửa được
-- từ khu quản trị.
--
-- INSERT IGNORE để chạy lại nhiều lần không hỏng, và để không đè lên thứ cửa
-- hàng đã sửa tay sau lần chạy đầu.
--
-- LOẠI TRÒNG lấy bộ BỐN của config/taxonomy.php (Đơn · Hai · Đa · Mắt đặt),
-- không lấy bộ của config/eyewear.php ('rx_lens_types': Đơn · Đa · Đổi màu ·
-- Chống ánh sáng xanh). Hai bộ ấy khác nhau và đó là một mâu thuẫn có sẵn:
-- bộ sau trộn hai TÍNH NĂNG vào một nhóm nói về LOẠI, và thiếu "Hai tròng".
-- Bộ bốn là thứ đúng nghĩa và cũng là thứ hộp thoại mua hàng đang dùng.
--
-- HỆ QUẢ PHẢI XỬ LÝ SAU: hàng đã nhập trước hôm nay có thể đang mang khoá
-- 'doi-mau' hoặc 'anh-sang-xanh' trong cột `lens_types`. Hai khoá đó nay thuộc
-- nhóm lớp phủ chứ không phải loại tròng, nên chúng cũng được seed vào nhóm
-- 'lop-phu' bên dưới — hàng cũ không mất bộ lọc, chỉ hiện ở đúng nhóm hơn.
-- ─────────────────────────────────────────────────────────────────────────────

INSERT IGNORE INTO `lens_options` (`group_key`, `option_key`, `label`, `note`, `sort_order`) VALUES
-- Loại tròng — config/taxonomy.php 'lens_types'
('loai-trong', 'don-trong', 'Đơn tròng', 'Một độ duy nhất trên cả mặt tròng — nhìn xa hoặc nhìn gần', 10),
('loai-trong', 'hai-trong', 'Hai tròng', 'Hai vùng nhìn tách nhau bằng một đường ranh: xa ở trên, gần ở dưới', 20),
('loai-trong', 'da-trong',  'Đa tròng',  'Độ chuyển dần từ xa sang gần, không có đường ranh trên mặt tròng', 30),
('loai-trong', 'mat-dat',   'Mắt đặt',   'Độ quá cao hoặc thông số đặc biệt, phải đặt riêng — cửa hàng báo giá sau', 40),

-- Chiết suất — config/eyewear.php 'rx_indexes'. Khoá chính là con số, vì bảng
-- giá tròng (`lens_prices`) cũng khoá theo chuỗi này.
('chiet-suat', '1.50', '1.50', 'Tròng trắng cơ bản, độ nhẹ',                 10),
('chiet-suat', '1.56', '1.56', 'Mỏng hơn 1.50, phù hợp cận trung bình',      20),
('chiet-suat', '1.61', '1.61', 'Mỏng, nhẹ — cận từ -4.00 trở lên',           30),
('chiet-suat', '1.67', '1.67', 'Siêu mỏng, cận nặng trên -6.00',             40),
('chiet-suat', '1.74', '1.74', 'Mỏng nhất, dành cho độ rất cao',             50),

-- Tính năng / lớp phủ — config/eyewear.php 'coatings', cộng ba thứ vốn nằm ở
-- cột riêng (is_uv400/is_polarized/is_photochromic) và hai khoá cũ trôi từ
-- 'rx_lens_types' sang.
('lop-phu', 'uv400',         'UV400',                'Chặn 100% tia UVA/UVB', 10),
('lop-phu', 'chong-loa',     'Chống phản quang',     'Giảm loá đèn xe, đèn màn hình', 20),
('lop-phu', 'anh-sang-xanh', 'Lọc ánh sáng xanh',    'Cho người làm việc máy tính nhiều giờ', 30),
('lop-phu', 'doi-mau',       'Đổi màu (Photochromic)', 'Tự sẫm lại khi ra nắng', 40),
('lop-phu', 'phan-cuc',      'Phân cực (Polarized)', 'Cắt chói mặt nước, mặt đường', 50),
('lop-phu', 'chong-tray',    'Chống trầy',           NULL, 60),
('lop-phu', 'chong-nuoc',    'Chống bám nước',       NULL, 70),
('lop-phu', 'chong-bui',     'Chống bám bụi',        NULL, 80),

-- Màu tròng — CHƯA TỪNG CÓ DANH SÁCH. Cột `lens_color` là ô chữ tự do
-- (placeholder "Xám khói"), nên mỗi cách gõ là một giá trị riêng. Sáu mục dưới
-- đây là bộ khởi đầu để cửa hàng sửa lại theo hàng thật, không phải một chuẩn.
('mau-trong', 'trong-suot',  'Trong suốt',       'Tròng cận thường, không màu', 10),
('mau-trong', 'xam-khoi',    'Xám khói',         NULL, 20),
('mau-trong', 'nau-tra',     'Nâu trà',          NULL, 30),
('mau-trong', 'xanh-reu',    'Xanh rêu',         NULL, 40),
('mau-trong', 'gradient',    'Gradient (chuyển màu)', 'Đậm ở trên, nhạt dần xuống dưới', 50),
('mau-trong', 'trang-guong', 'Tráng gương',      'Mặt ngoài phản quang', 60);
