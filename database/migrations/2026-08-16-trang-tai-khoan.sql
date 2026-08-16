-- ============================================================================
-- NÂNG CẤP 2026-08-16
-- Trang tài khoản người dùng (/tai-khoan) — dựng theo "Vin Eyewear Account.dc.html"
--
-- Bản thiết kế vẽ SÁU mục trong trang tài khoản. Ba mục đầu đã có chỗ chứa
-- trong CSDL (hồ sơ, đổi mật khẩu, đơn hàng); ba mục còn lại thì chưa:
--
--   Sổ địa chỉ      -> profiles.address chỉ là MỘT ô text. Bản thiết kế cho
--                      khách lưu nhiều địa chỉ, mỗi địa chỉ có người nhận và
--                      số điện thoại riêng, một trong số đó là mặc định.
--                      -> bảng `addresses`.
--   Thông số đo mắt -> prescriptions mới có SPH/CYL hai mắt. Bản thiết kế còn
--                      hiện TRỤC, thị lực, khoảng cách đồng tử, ngày đo, cơ sở
--                      đo và câu khuyến nghị.  -> thêm cột.
--   Ưu đãi          -> chưa có gì.  -> bảng `vouchers` + `user_vouchers`.
--
-- Thêm hai thứ nữa mà bản thiết kế đòi nhưng CSDL chưa có:
--   profiles.gender / profiles.avatar_path — ô "Giới tính" và ảnh đại diện
--   order_status_history — thanh tiến trình đơn hàng ghi GIỜ dưới từng bước.
--                          Bảng orders chỉ có created_at và updated_at, tức
--                          chỉ đủ cho bước đầu và bước hiện tại; các bước ở
--                          giữa sẽ trống mãi nếu không ghi lại lịch sử.
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
-- Chạy hai lần thì MySQL báo "Duplicate column name" / "Table already exists".
-- Đó là báo an toàn, không phải hỏng dữ liệu.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. SỔ ĐỊA CHỈ
--
-- Một khách nhiều địa chỉ -> bảng riêng, không nhét thêm cột vào profiles.
--
-- `recipient_name` và `phone` KHÔNG lấy từ profiles: người đặt và người nhận
-- thường xuyên là hai người khác nhau (gửi quà, gửi về nhà bố mẹ). Bản thiết
-- kế cũng vẽ đúng vậy — hai địa chỉ mẫu mang hai tên và hai số khác nhau.
--
-- Địa chỉ tách làm HAI dòng đúng như bản thiết kế in ra:
--   line1  số nhà, ngõ, đường            "Ngách 19 Ngõ 123A Thụy Khuê, Tây Hồ"
--   line2  phường/xã, tỉnh/thành phố     "Phường Tây Hồ, Thành phố Hà Nội"
--
-- `is_default` không đặt khoá UNIQUE trên (user_id, is_default): MySQL sẽ hiểu
-- là mỗi khách chỉ được có MỘT địa chỉ không-mặc-định. Ràng buộc "đúng một
-- địa chỉ mặc định" do AddressModel::setDefault giữ, trong một transaction.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `addresses` (
    `id`             CHAR(36)     NOT NULL DEFAULT (UUID()),
    `user_id`        CHAR(36)     NOT NULL,
    `recipient_name` VARCHAR(255) NOT NULL,
    `phone`          VARCHAR(32)  NOT NULL,
    `line1`          VARCHAR(255) NOT NULL,
    `line2`          VARCHAR(255) NULL,
    `is_default`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Danh sách luôn đọc theo khách rồi xếp mặc-định-lên-trước -> index ghép
    KEY `idx_addresses_user` (`user_id`, `is_default`),
    CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 2. CHUYỂN profiles.address SANG SỔ ĐỊA CHỈ
--
-- Khách cũ đã điền ô "Địa chỉ giao hàng" thì không được để trống trơn sau khi
-- nâng cấp. Chép sang thành địa chỉ mặc định đầu tiên của họ.
--
-- Tên người nhận và số điện thoại lấy từ chính hồ sơ — đó là tất cả những gì
-- ô cũ có. COALESCE cho trường hợp hồ sơ thiếu tên/số.
--
-- Cột profiles.address KHÔNG bị xoá: trang thanh toán và các đơn hàng cũ vẫn
-- đang đọc nó. Nó thành bản sao của địa chỉ mặc định, được AddressModel cập
-- nhật theo — xem ghi chú trong AddressModel::syncProfileAddress().
-- ----------------------------------------------------------------------------
INSERT INTO `addresses` (`id`, `user_id`, `recipient_name`, `phone`, `line1`, `is_default`)
SELECT UUID(),
       p.`id`,
       COALESCE(NULLIF(TRIM(p.`full_name`), ''), 'Khách hàng'),
       COALESCE(NULLIF(TRIM(p.`phone`), ''), ''),
       TRIM(p.`address`),
       1
  FROM `profiles` p
 WHERE p.`address` IS NOT NULL
   AND TRIM(p.`address`) <> ''
   AND NOT EXISTS (SELECT 1 FROM `addresses` a WHERE a.`user_id` = p.`id`);


-- ----------------------------------------------------------------------------
-- 3. GIỚI TÍNH + ẢNH ĐẠI DIỆN
--
-- `gender` là chuỗi tự do trong ba giá trị 'nu' | 'nam' | 'khac' chứ không
-- phải ENUM: đổi/​thêm lựa chọn trong ENUM cần ALTER TABLE khoá bảng, còn ở
-- đây danh sách nằm trong UserModel::GENDERS, sửa một dòng PHP là xong.
-- NULL = chưa chọn.
--
-- `avatar_path` lưu ĐƯỜNG DẪN tương đối trong assets/uploads/avatars/, không
-- lưu nội dung ảnh: nhét BLOB vào MySQL làm mọi câu SELECT hồ sơ nặng lên
-- trong khi web server phục vụ file tĩnh nhanh hơn hẳn.
-- ----------------------------------------------------------------------------
ALTER TABLE `profiles`
    ADD COLUMN `gender`      VARCHAR(16)  NULL AFTER `date_of_birth`,
    ADD COLUMN `avatar_path` VARCHAR(255) NULL AFTER `gender`;


-- ----------------------------------------------------------------------------
-- 4. MỞ RỘNG HỒ SƠ KHÚC XẠ
--
-- Bản thiết kế in một bảng 5 cột (Mắt · Cầu · Trụ · Trục · Thị lực) cộng hai
-- thẻ "Khoảng cách đồng tử" và "Khuyến nghị", cộng dòng "Đo ngày ... · Cơ sở
-- ..." và huy hiệu "Còn hiệu lực".
--
-- `od_axis` / `os_axis`  trục loạn thị, 0–180 độ. SMALLINT vì là số nguyên độ.
-- `od_va` / `os_va`      thị lực, ghi dạng phân số "10/10" -> phải là chuỗi.
-- `pd`                   khoảng cách đồng tử, milimet, có phần thập phân .5.
-- `measured_at`          NGÀY ĐO, khác `updated_at` (lúc gõ vào máy). Huy hiệu
--                        "Còn hiệu lực" tính từ ngày này, nên không thể dùng
--                        updated_at thay: sửa lại một lỗi chính tả sẽ làm một
--                        đơn thuốc hai năm tuổi trông như vừa đo hôm qua.
-- `store_id`             cơ sở đã đo. SET NULL khi cơ sở đóng cửa — kết quả đo
--                        vẫn còn giá trị dù cửa hàng không còn.
-- `recommendation`       câu khuyến nghị của kỹ thuật viên.
-- ----------------------------------------------------------------------------
ALTER TABLE `prescriptions`
    ADD COLUMN `od_axis`        SMALLINT     NULL AFTER `od_cyl`,
    ADD COLUMN `od_va`          VARCHAR(16)  NULL AFTER `od_axis`,
    ADD COLUMN `os_axis`        SMALLINT     NULL AFTER `os_cyl`,
    ADD COLUMN `os_va`          VARCHAR(16)  NULL AFTER `os_axis`,
    ADD COLUMN `pd`             DECIMAL(4,1) NULL AFTER `os_va`,
    ADD COLUMN `measured_at`    DATE         NULL AFTER `pd`,
    ADD COLUMN `store_id`       CHAR(36)     NULL AFTER `measured_at`,
    ADD COLUMN `recommendation` VARCHAR(255) NULL AFTER `store_id`,
    ADD KEY `idx_prescriptions_store` (`store_id`),
    ADD CONSTRAINT `fk_prescriptions_store` FOREIGN KEY (`store_id`)
        REFERENCES `stores` (`id`) ON DELETE SET NULL;


-- ----------------------------------------------------------------------------
-- 5. ƯU ĐÃI
--
-- Hai bảng chứ không một:
--   `vouchers`       định nghĩa mã — dùng chung, do quản trị tạo một lần.
--   `user_vouchers`  mã nào đã phát cho ai, và đã dùng chưa.
--
-- Gộp làm một bảng nghĩa là mỗi khách một dòng riêng cho cùng một chương trình
-- khuyến mãi: sửa điều kiện của chương trình phải UPDATE hàng nghìn dòng, và
-- không có chỗ nào trả lời được "chương trình này đã phát cho bao nhiêu người".
--
-- `discount_type` = 'percent' (giảm theo %) | 'amount' (giảm số tiền) |
--                   'shipping' (miễn phí vận chuyển).
-- `max_discount`  chỉ có nghĩa với 'percent' — chặn trần số tiền được giảm.
-- `tag`           chuỗi ngắn in trong ô vuông bên trái thẻ ưu đãi ở bản thiết
--                 kế ("-10%", "100K", "FS"). Lưu sẵn thay vì suy ra từ
--                 discount_type/value: "100K" không phải cách duy nhất viết
--                 tắt của 100.000₫, và người làm khuyến mãi cần tự quyết.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vouchers` (
    `id`             CHAR(36)     NOT NULL DEFAULT (UUID()),
    `code`           VARCHAR(40)  NOT NULL,
    `tag`            VARCHAR(16)  NOT NULL,
    `title`          VARCHAR(255) NOT NULL,
    `condition_text` VARCHAR(255) NULL,
    `discount_type`  VARCHAR(16)  NOT NULL DEFAULT 'percent',
    `discount_value` BIGINT       NOT NULL DEFAULT 0,
    `min_order`      BIGINT       NOT NULL DEFAULT 0,
    `max_discount`   BIGINT       NULL,
    `expires_at`     DATE         NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vouchers_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Khoá chính GHÉP (user_id, voucher_id): một người không thể nhận hai lần
-- cùng một mã, và ràng buộc đó do CSDL giữ chứ không phải do code nhớ kiểm.
CREATE TABLE IF NOT EXISTS `user_vouchers` (
    `user_id`    CHAR(36) NOT NULL,
    `voucher_id` CHAR(36) NOT NULL,
    `used_at`    DATETIME NULL,
    `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `voucher_id`),
    KEY `idx_user_vouchers_voucher` (`voucher_id`),
    CONSTRAINT `fk_user_vouchers_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_vouchers_voucher` FOREIGN KEY (`voucher_id`)
        REFERENCES `vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 6. LỊCH SỬ TRẠNG THÁI ĐƠN HÀNG
--
-- Thanh tiến trình trong bản thiết kế ghi giờ dưới TỪNG bước:
--   Đã đặt hàng 12/08 · 09:14   Đã xác nhận 12/08 · 10:02   Đang giao 14/08 …
--
-- orders chỉ có created_at (bước đầu) và updated_at (bước hiện tại). Muốn có
-- giờ của các bước ở giữa thì phải ghi lại mỗi lần trạng thái đổi.
--
-- `changed_by` NULL = hệ thống tự ghi (lúc đặt hàng), có giá trị = nhân viên
-- đã bấm đổi trong khu quản trị.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id`         CHAR(36)    NOT NULL DEFAULT (UUID()),
    `order_id`   CHAR(36)    NOT NULL,
    `status`     VARCHAR(32) NOT NULL,
    `changed_by` CHAR(36)    NULL,
    `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_osh_order` (`order_id`, `created_at`),
    CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_osh_user` FOREIGN KEY (`changed_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đơn đã có từ trước không có lịch sử. Dựng lại bước ĐẦU cho chúng từ
-- created_at, để thanh tiến trình không trống hoàn toàn với đơn cũ.
INSERT INTO `order_status_history` (`id`, `order_id`, `status`, `created_at`)
SELECT UUID(), o.`id`, 'new', o.`created_at`
  FROM `orders` o
 WHERE NOT EXISTS (
       SELECT 1 FROM `order_status_history` h WHERE h.`order_id` = o.`id`
 );


-- ----------------------------------------------------------------------------
-- 7. DỮ LIỆU MẪU CHO ƯU ĐÃI  (TUỲ CHỌN)
--
-- Chỉ chạy phần này trên bản demo/dev. Bốn mã dưới đây đúng bốn thẻ mà bản
-- thiết kế vẽ, để mở trang ra là thấy khối ưu đãi có nội dung.
--
-- Trên site thật thì bỏ qua và tạo mã bằng khu quản trị — không thì khách nào
-- cũng nhận được bốn mã giảm giá này.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `vouchers`
    (`code`, `tag`, `title`, `condition_text`, `discount_type`, `discount_value`, `min_order`, `max_discount`, `expires_at`)
VALUES
    ('GONG10',  '-10%', 'Giảm 10% gọng kính chính hãng',   'Đơn tối thiểu 2.000.000₫ · Giảm tối đa 500.000₫', 'percent',   10, 2000000, 500000, '2026-08-31'),
    ('TRONG15', '-15%', 'Giảm 15% tròng Essilor / Zeiss',  'Áp dụng khi mua kèm gọng',                        'percent',   15,       0,   NULL, '2026-08-31'),
    ('CHAO100', '100K', 'Giảm 100.000₫ cho khách hàng mới', 'Đơn tối thiểu 1.000.000₫',                       'amount', 100000, 1000000,   NULL, '2026-09-30'),
    ('FREESHIP', 'FS',  'Miễn phí vận chuyển',              'Mọi đơn hàng toàn quốc',                          'shipping',   0,       0,   NULL, '2026-12-31');

-- Phát cả bốn mã cho mọi tài khoản khách hiện có.
INSERT IGNORE INTO `user_vouchers` (`user_id`, `voucher_id`)
SELECT u.`id`, v.`id` FROM `users` u CROSS JOIN `vouchers` v;
