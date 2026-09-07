-- ============================================================================
-- Vin Eyewear — SEED DỮ LIỆU MẪU
--
-- Chạy SAU database/schema.sql. Nạp một bộ dữ liệu đầy đủ để mọi màn hình của
-- trang bán hàng lẫn khu quản trị đều có thứ để hiện: 10 bản ghi cho mỗi loại
-- dữ liệu nghiệp vụ.
--
-- CÁCH CHẠY
--     mysql --database=vin_eyewear < database/seed.sql
--     docker compose exec -T db mariadb -uvin_eyewear -pvin_eyewear_pass vin_eyewear < database/seed.sql
--     phpMyAdmin -> chọn database -> Import -> chọn file này
--
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN. Mọi dòng seed mang khoá chính CỐ ĐỊNH (uuid dạng
-- '44444444-...-0000000000NN'), và khối DỌN ở đầu file xoá đúng những dòng ấy
-- trước khi chèn lại. Dữ liệu thật do người dùng nhập KHÔNG bị đụng tới, trừ
-- ba chỗ nói rõ ở đúng chỗ (lens_prices, site_texts, app_settings).
--
-- MẬT KHẨU MỌI TÀI KHOẢN SEED:  Vin@12345
--     admin@vineyewear.vn      Quản trị viên
--     quanly@vineyewear.vn     Nhân viên
--     kythuat@vineyewear.vn    Nhân viên
--     bảy tài khoản khách, đăng nhập bằng số điện thoại (xem mục 2)
--
-- ĐÂY LÀ DỮ LIỆU GIẢ. Đừng chạy file này lên máy chủ đang bán hàng thật: giá,
-- tồn kho, đơn hàng và số đo mắt ở đây đều bịa, mà chúng nằm lẫn với dữ liệu
-- thật thì không ai phân biệt nổi sau một tuần.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- BA CHỖ KHÔNG PHẢI 10 — CỐ Ý, KHÔNG PHẢI SÓT
--
--   lens_options nhóm 'loai-trong'   giữ đúng BA (đơn · hai · đa tròng). Đó là
--       bộ của config/taxonomy.php mà luồng mua hàng đọc; thêm mục thứ tư là
--       thêm một lựa chọn lọc ra 0 sản phẩm và một kiểu tròng không có giá.
--   site_texts / app_settings        chỉ có 2 và 1 khoá THẬT (SiteTextModel::
--       BST_TIEU_DE, ::BST_DOAN_DAN, SettingModel::MOC_DOANH_THU). Khoá bịa
--       thêm không nơi nào đọc, chỉ làm màn cấu hình dài ra vô ích.
--   refund_requests                  4 dòng. Khoá `uq_refund_order` cho mỗi đơn
--       đúng một yêu cầu hoàn tiền, và yêu cầu chỉ có nghĩa với đơn ĐÃ HUỶ mà
--       cửa hàng ĐANG GIỮ TIỀN — trong 10 đơn seed có đúng 4 đơn như thế. Muốn
--       10 thì phải bịa thêm 6 đơn huỷ nữa, tức là bóp méo màn Đơn hàng để làm
--       đầy màn Hoàn tiền.
--
--   email_templates                  15 mẫu, chép từ database/migrations/
--       2026-09-06-dot-6-email.sql (nguồn thật). INSERT IGNORE nên chạy file
--       nào trước cũng được.
-- ─────────────────────────────────────────────────────────────────────────────
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- 0. DỌN DỮ LIỆU SEED CŨ
--
-- Xoá theo thứ tự CON TRƯỚC CHA, không tắt FOREIGN_KEY_CHECKS: tắt kiểm tra là
-- cách nhanh nhất để bỏ sót một bảng con rồi để lại dòng mồ côi trỏ vào khoảng
-- không, và lỗi đó chỉ lộ ra ở một màn hình nào đó vài tuần sau.
-- ============================================================================

DELETE FROM `customer_audit_logs`   WHERE `id` LIKE 'ffffffff-%';
DELETE FROM `email_queue`           WHERE `id` LIKE '18181818-%';
DELETE FROM `sepay_transactions`    WHERE `id` LIKE '17171717-%';
DELETE FROM `refund_requests`       WHERE `id` LIKE '19191919-%';
DELETE FROM `order_status_history`  WHERE `id` LIKE 'bbbbbbbb-%';
DELETE FROM `reviews`               WHERE `id` LIKE 'cccccccc-%';
DELETE FROM `order_items`           WHERE `id` LIKE 'aaaaaaaa-%';
DELETE FROM `orders`                WHERE `id` LIKE '99999999-%';
DELETE FROM `customer_prescriptions` WHERE `id` LIKE 'eeeeeeee-%';
DELETE FROM `appointments`          WHERE `id` LIKE '88888888-%';
DELETE FROM `stock_waitlist`        WHERE `id` LIKE 'dddddddd-%';
DELETE FROM `product_variants`      WHERE `id` LIKE '55555555-%';
DELETE FROM `products`              WHERE `id` LIKE '44444444-%';
DELETE FROM `collection_faqs`       WHERE `id` LIKE '67676767-%';
DELETE FROM `collections`           WHERE `id` LIKE '66666666-%';
DELETE FROM `categories`            WHERE `id` LIKE '33333333-%';
DELETE FROM `user_vouchers`         WHERE `voucher_id` LIKE '22222222-%';
DELETE FROM `vouchers`              WHERE `id` LIKE '22222222-%';
DELETE FROM `addresses`             WHERE `id` LIKE '14141414-%';
DELETE FROM `remember_tokens`       WHERE `id` LIKE '15151515-%';
DELETE FROM `password_resets`       WHERE `id` LIKE '16161616-%';
DELETE FROM `contact_requests`      WHERE `id` LIKE '12121212-%';
DELETE FROM `newsletter_subscribers` WHERE `id` LIKE '13131313-%';
DELETE FROM `prescriptions`         WHERE `user_id` LIKE '11111111-%';
-- profiles, user_roles, remember_tokens... đều CASCADE theo users; xoá tường
-- minh ở trên vẫn cần vì file này chạy lại được cả khi bảng users không đổi.
DELETE FROM `users`                 WHERE `id` LIKE '11111111-%';
DELETE FROM `stores`                WHERE `id` LIKE '77777777-%';

-- Ba bảng dưới đây khoá chính KHÔNG phải uuid nên không lọc được bằng LIKE.
DELETE FROM `login_attempts` WHERE `login_key` IN (
    '20faf05baccf8a477fa337b77c0acb0e8bff77f34664feec6a057bd3cf23235b',
    '17756315ebd47b7110359fc7b168179bf6f2df3646fcc888bc8aa05c78b38ac1',
    '6030f4d0c80f4c2e5e324cd8fdfc7aa1abd3cd8a06947d72094bdf1a3a781328',
    '9b7ad5b16e43f7bdf3ae8c69b5e21c26c5ebaf6f46a2ae1db7d6c969397aa405',
    '6f30d912c9c6cb7d4d100a4d124670ad1b5474953f823f809ef1c644b2780228',
    'a57575d7526ea046bfe2581f03bc0f54f7328b45ddb25799a62e13be63520535',
    'e9d04367629929ffcdf0f2fd55dd3f5ea1e1e595c22da850dd8215f0f60a9023',
    '509f466b5f4b0ba4b77c5dc1fd137ed1140f3c04657e4d117905d6c9a6dec141',
    '4dcd08183afa2fce08ef4df19b690fbf9656131a3863c98d1ff811623c287c34',
    '9eceb13483d7f187ec014fd6d4854d1420cfc634328af85f51d0323ba8622e21'
);
DELETE FROM `lens_packages` WHERE `id` IN
    ('clear-160', 'clear-167', 'clear-174', 'polar-160', 'photo-161');
DELETE FROM `lens_prices` WHERE `lens_package` IN
    ('clear-160', 'clear-167', 'clear-174', 'polar-160', 'photo-161');
DELETE FROM `lens_options` WHERE (`group_key`, `option_key`) IN (
    ('chiet-suat', '1.53'), ('chiet-suat', '1.59'), ('chiet-suat', '1.60'),
    ('chiet-suat', '1.71'), ('chiet-suat', '1.76'),
    ('lop-phu', 'chong-tinh-dien'), ('lop-phu', 'chong-hoi-nuoc'),
    ('mau-trong', 'xanh-duong'), ('mau-trong', 'hong-nhat'),
    ('mau-trong', 'tim-khoi'), ('mau-trong', 'vang-tuong-phan')
);

-- ============================================================================
-- 1. CƠ SỞ, DANH MỤC, TRÒNG KÍNH
-- ============================================================================

-- ----------------------------------------------------------------------------
-- CƠ SỞ — 8 dòng ở đây + 2 dòng schema.sql đã nạp (TAYHO, LONGBIEN) = 10.
--
-- Không chèn lại hai cơ sở của schema.sql: chúng có thể đã được sửa số điện
-- thoại hay giờ mở cửa trong khu quản trị, và seed không có quyền ghi đè.
-- ----------------------------------------------------------------------------
INSERT INTO `stores` (`id`, `code`, `name`, `address`, `phone`, `open_hours`, `map_url`, `is_active`) VALUES
('77777777-0000-4000-8000-000000000003', 'CAUGIAY',    'Vin Eyewear Cầu Giấy',     '128 Xuân Thủy, phường Cầu Giấy, TP. Hà Nội',       '0901 234 569', '08:00 - 21:00 hàng ngày',   'https://www.google.com/maps?q=128+Xuan+Thuy+Ha+Noi&output=embed', 1),
('77777777-0000-4000-8000-000000000004', 'HOANKIEM',   'Vin Eyewear Hoàn Kiếm',    '35 Hàng Bài, phường Hoàn Kiếm, TP. Hà Nội',        '0901 234 570', '08:30 - 21:30 hàng ngày',   'https://www.google.com/maps?q=35+Hang+Bai+Ha+Noi&output=embed', 1),
('77777777-0000-4000-8000-000000000005', 'THANHXUAN',  'Vin Eyewear Thanh Xuân',   '212 Nguyễn Trãi, phường Thanh Xuân, TP. Hà Nội',   '0901 234 571', '08:00 - 21:00 hàng ngày',   'https://www.google.com/maps?q=212+Nguyen+Trai+Ha+Noi&output=embed', 1),
('77777777-0000-4000-8000-000000000006', 'HADONG',     'Vin Eyewear Hà Đông',      '89 Quang Trung, phường Hà Đông, TP. Hà Nội',       '0901 234 572', '08:00 - 20:30 hàng ngày',   'https://www.google.com/maps?q=89+Quang+Trung+Ha+Dong&output=embed', 1),
('77777777-0000-4000-8000-000000000007', 'MYDINH',     'Vin Eyewear Mỹ Đình',      '17 Lê Đức Thọ, phường Từ Liêm, TP. Hà Nội',        '0901 234 573', '08:00 - 21:00 hàng ngày',   'https://www.google.com/maps?q=17+Le+Duc+Tho+Ha+Noi&output=embed', 1),
('77777777-0000-4000-8000-000000000008', 'GIALAM',     'Vin Eyewear Gia Lâm',      '52 Ngô Xuân Quảng, xã Gia Lâm, TP. Hà Nội',        '0901 234 574', '08:00 - 20:30 hàng ngày',   'https://www.google.com/maps?q=52+Ngo+Xuan+Quang+Gia+Lam&output=embed', 1),
('77777777-0000-4000-8000-000000000009', 'DONGDA',     'Vin Eyewear Đống Đa',      '301 Tây Sơn, phường Đống Đa, TP. Hà Nội',          '0901 234 575', '08:00 - 21:00 hàng ngày',   'https://www.google.com/maps?q=301+Tay+Son+Ha+Noi&output=embed', 1),
-- Cơ sở đã đóng cửa: StoreModel::active() phải lọc được nó ra khỏi form đặt
-- lịch, mà không có dòng nào is_active = 0 thì phép lọc ấy không bao giờ chạy.
('77777777-0000-4000-8000-000000000010', 'HAIBATRUNG', 'Vin Eyewear Hai Bà Trưng', '145 Bà Triệu, phường Hai Bà Trưng, TP. Hà Nội',    '0901 234 576', 'Tạm ngừng hoạt động',       NULL, 0);

-- Hai cơ sở của schema.sql — lấy id để dùng ở lịch hẹn và đơn hàng bên dưới.
-- Đọc qua biến vì INSERT không được phép SELECT từ chính bảng đang chèn.
SET @store_tayho    = (SELECT `id` FROM `stores` WHERE `code` = 'TAYHO'    LIMIT 1);
SET @store_longbien = (SELECT `id` FROM `stores` WHERE `code` = 'LONGBIEN' LIMIT 1);

-- ----------------------------------------------------------------------------
-- DANH MỤC — 7 dòng ở đây + 3 dòng schema.sql (gong-kinh, kinh-mat,
-- trong-kinh) = 10.
-- ----------------------------------------------------------------------------
INSERT INTO `categories` (`id`, `slug`, `name`, `description`, `sort_order`, `is_visible`) VALUES
('33333333-0000-4000-8000-000000000004', 'gong-titan',     'Gọng titan',      'Gọng titanium siêu nhẹ, không gỉ, hợp da nhạy cảm',        4,  1),
('33333333-0000-4000-8000-000000000005', 'kinh-doi-mau',   'Kính đổi màu',    'Tròng photochromic tự sẫm khi ra nắng',                    5,  1),
('33333333-0000-4000-8000-000000000006', 'gong-tre-em',    'Gọng trẻ em',     'Gọng dẻo TR90 cho trẻ 4 đến 14 tuổi',                      6,  1),
('33333333-0000-4000-8000-000000000007', 'kinh-the-thao',  'Kính thể thao',   'Gọng ôm sát, tròng phân cực cho chạy bộ và đạp xe',        7,  1),
('33333333-0000-4000-8000-000000000008', 'kinh-doc-sach',  'Kính đọc sách',   'Kính lão có sẵn độ, dùng đọc gần',                         8,  1),
('33333333-0000-4000-8000-000000000009', 'phu-kien',       'Phụ kiện',        'Hộp kính, khăn lau, dây đeo, nước rửa tròng',              9,  1),
-- Danh mục ẩn: CategoryModel::visible() phải có dòng để lọc ra.
('33333333-0000-4000-8000-000000000010', 'kinh-bao-ho',    'Kính bảo hộ',     'Kính chống bụi và tia lửa cho xưởng — chưa mở bán',       10,  0);

SET @cat_gong  = (SELECT `id` FROM `categories` WHERE `slug` = 'gong-kinh'  LIMIT 1);
SET @cat_ram   = (SELECT `id` FROM `categories` WHERE `slug` = 'kinh-mat'   LIMIT 1);
SET @cat_trong = (SELECT `id` FROM `categories` WHERE `slug` = 'trong-kinh' LIMIT 1);

-- ----------------------------------------------------------------------------
-- GÓI CHIẾT SUẤT — 5 gói ở đây + 5 gói schema.sql = 10.
--
-- Mã ('clear-167'…) là thứ order_items.lens_id và lens_prices.lens_package lưu
-- lại, nên đặt theo đúng nếp của năm gói có sẵn.
-- ----------------------------------------------------------------------------
INSERT INTO `lens_packages` (`id`, `name`, `description`, `sort_order`) VALUES
('clear-160', 'Tròng trắng 1.60',          'Mỏng vừa, cân giữa giá và độ dày (-4.00 → -5.00)',  25),
('clear-167', 'Tròng trắng 1.67',          'Siêu mỏng không lớp phủ lọc, cận nặng trên -6.00',  45),
('photo-161', 'Đổi màu Photochromic 1.61', 'Đổi màu, mỏng hơn bản 1.56',                        55),
('clear-174', 'Tròng trắng 1.74',          'Mỏng nhất hiện có, dành cho độ rất cao trên -8.00', 60),
('polar-160', 'Phân cực 1.60',             'Cắt chói mặt nước và mặt đường, cắt độ được',       70);

-- ----------------------------------------------------------------------------
-- THUỘC TÍNH TRÒNG — nâng ba nhóm lên 10 mục mỗi nhóm.
--
-- 'loai-trong' GIỮ NGUYÊN BA MỤC. Xem khối "BA CHỖ KHÔNG PHẢI 10" ở đầu file.
--
-- INSERT IGNORE: `uniq_lens_options_key` chặn trùng (nhóm, khoá), nên chạy lại
-- file này sau khi cửa hàng đã tự thêm một mục cùng khoá thì bỏ qua, không đổi
-- nhãn người ta đã sửa.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `lens_options` (`group_key`, `option_key`, `label`, `note`, `sort_order`) VALUES
('chiet-suat', '1.53', '1.53', 'Trivex — dẻo, khó vỡ, hợp gọng khoan và trẻ em', 15),
('chiet-suat', '1.59', '1.59', 'Polycarbonate — chống va đập, hợp kính thể thao', 25),
('chiet-suat', '1.60', '1.60', 'Mỏng vừa, cân giữa giá và độ dày',               35),
('chiet-suat', '1.71', '1.71', 'Mỏng hơn 1.67, quang học tốt',                   45),
('chiet-suat', '1.76', '1.76', 'Mỏng nhất, hàng đặt theo đơn',                   55),

('lop-phu', 'chong-tinh-dien', 'Chống tĩnh điện',  'Ít bám bụi hơn khi dùng điều hoà', 90),
('lop-phu', 'chong-hoi-nuoc',  'Chống hơi nước',   'Đỡ mờ khi đeo khẩu trang',        100),

('mau-trong', 'xanh-duong',      'Xanh dương',            NULL,                                 70),
('mau-trong', 'hong-nhat',       'Hồng nhạt',             NULL,                                 80),
('mau-trong', 'tim-khoi',        'Tím khói',              NULL,                                 90),
('mau-trong', 'vang-tuong-phan', 'Vàng tăng tương phản',  'Nhìn rõ hơn khi trời mù và lái đêm', 100);

-- ----------------------------------------------------------------------------
-- BẢNG GIÁ TRÒNG — GIAO ĐIỂM kiểu tròng × gói, nên phải ĐỦ MA TRẬN.
--
-- 3 kiểu × 10 gói = 30 ô. Thiếu một ô là lựa chọn đó hiện "Báo giá sau" giữa
-- luồng mua hàng, đúng thứ SRS v2.1.0 (C08) đã bỏ.
--
-- REPLACE chứ không INSERT: bảng này người vận hành sửa hằng tháng ở
-- /quan-tri/gia-trong, và seed cố ý ĐẶT LẠI toàn bộ về giá mẫu. Đây là một
-- trong ba chỗ file này ghi đè dữ liệu có sẵn — đừng chạy trên máy thật.
--
-- Giá lên dần theo chiết suất, và mỗi bậc kiểu tròng nhân lên: mài đa tròng
-- trên phôi mỏng đắt hơn hẳn đơn tròng trên phôi dày.
-- ----------------------------------------------------------------------------
REPLACE INTO `lens_prices` (`lens_type`, `lens_package`, `price`) VALUES
('don-trong', 'clear-150',   300000),
('don-trong', 'clear-156',   450000),
('don-trong', 'clear-160',   650000),
('don-trong', 'blue-161',    890000),
('don-trong', 'clear-167',  1290000),
('don-trong', 'blue-167',   1490000),
('don-trong', 'photo-156',  1190000),
('don-trong', 'photo-161',  1590000),
('don-trong', 'clear-174',  2290000),
('don-trong', 'polar-160',  1390000),

('hai-trong', 'clear-150',   750000),
('hai-trong', 'clear-156',   950000),
('hai-trong', 'clear-160',  1250000),
('hai-trong', 'blue-161',   1590000),
('hai-trong', 'clear-167',  2190000),
('hai-trong', 'blue-167',   2490000),
('hai-trong', 'photo-156',  1990000),
('hai-trong', 'photo-161',  2490000),
('hai-trong', 'clear-174',  3490000),
('hai-trong', 'polar-160',  2190000),

('da-trong',  'clear-150',  1490000),
('da-trong',  'clear-156',  1890000),
('da-trong',  'clear-160',  2390000),
('da-trong',  'blue-161',   2990000),
('da-trong',  'clear-167',  3890000),
('da-trong',  'blue-167',   4390000),
('da-trong',  'photo-156',  3590000),
('da-trong',  'photo-161',  4290000),
('da-trong',  'clear-174',  5890000),
('da-trong',  'polar-160',  3790000);

-- ----------------------------------------------------------------------------
-- CHỮ TRÊN TRANG VÀ CẤU HÌNH VẬN HÀNH
--
-- Đúng ba khoá mà mã nguồn thật sự đọc. Xem khối "BA CHỖ KHÔNG PHẢI 10".
-- REPLACE: hai bảng này cũng bị seed ghi đè.
-- ----------------------------------------------------------------------------
REPLACE INTO `site_texts` (`text_key`, `value`) VALUES
('bo-suu-tap.tieu_de',  'Bộ sưu tập Vin Eyewear'),
('bo-suu-tap.doan_dan', 'Mỗi bộ là một cách nhìn: chất liệu, dáng gọng và tròng kính được chọn cùng nhau chứ không ghép lại từ những món rời.');

REPLACE INTO `app_settings` (`name`, `value`) VALUES
-- Rỗng = tính doanh thu từ đầu. Đặt một ngày vào đây để bỏ qua dữ liệu trước
-- lúc khai trương — xem SettingModel::MOC_DOANH_THU.
('moc_doanh_thu', '');

-- ============================================================================
-- 2. TÀI KHOẢN — 10 người
--
--   01  admin@vineyewear.vn    Quản trị viên
--   02  quanly@vineyewear.vn   Nhân viên
--   03  kythuat@vineyewear.vn  Nhân viên
--   04..10  bảy khách hàng
--
-- Mật khẩu của cả mười: Vin@12345 (băm bcrypt bên dưới — KHÔNG lưu mật khẩu
-- thô ở đâu ngoài khối chú thích này, và đây là mật khẩu của dữ liệu giả).
--
-- Tài khoản 10 ĐANG BỊ KHOÁ, cố ý: đường khoá tài khoản chặn ở UserModel::
-- attempt, và không có dòng nào status = 'locked' thì nhánh ấy không bao giờ
-- được chạy thử.
--
-- `terms_accepted_at` + `terms_version` khớp config/auth.php ['consent'].
-- ============================================================================

INSERT INTO `users`
    (`id`, `email`, `google_id`, `password_hash`, `email_verified`, `status`,
     `locked_reason`, `locked_at`, `locked_by`, `terms_accepted_at`, `terms_version`,
     `last_login_at`, `created_at`) VALUES
('11111111-0000-4000-8000-000000000001', 'admin@vineyewear.vn',      NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 1, 'active', NULL, NULL, NULL, '2026-07-01 08:00:00', '1.0', '2026-09-08 08:12:00', '2026-07-01 08:00:00'),
('11111111-0000-4000-8000-000000000002', 'quanly@vineyewear.vn',     NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 1, 'active', NULL, NULL, NULL, '2026-07-01 08:05:00', '1.0', '2026-09-08 07:40:00', '2026-07-01 08:05:00'),
('11111111-0000-4000-8000-000000000003', 'kythuat@vineyewear.vn',    NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 1, 'active', NULL, NULL, NULL, '2026-07-01 08:10:00', '1.0', '2026-09-07 17:55:00', '2026-07-01 08:10:00'),
('11111111-0000-4000-8000-000000000004', 'nguyenthuha@gmail.com',    NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 1, 'active', NULL, NULL, NULL, '2026-07-14 09:20:00', '1.0', '2026-09-06 20:31:00', '2026-07-14 09:20:00'),
('11111111-0000-4000-8000-000000000005', 'tranminhquan@gmail.com',   NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 0, 'active', NULL, NULL, NULL, '2026-07-19 14:02:00', '1.0', '2026-09-05 11:07:00', '2026-07-19 14:02:00'),
-- Tài khoản đăng ký bằng Google: có email do Google cung cấp (đã xác minh) và
-- `google_id` là trường `sub`, KHÔNG phải email — xem chú thích cột trong schema.
('11111111-0000-4000-8000-000000000006', 'lephuonganh@gmail.com',    '117402938475610293845', '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 1, 'active', NULL, NULL, NULL, '2026-08-02 10:45:00', '1.0', '2026-09-07 09:14:00', '2026-08-02 10:45:00'),
('11111111-0000-4000-8000-000000000007', 'phamduclong@gmail.com',    NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 0, 'active', NULL, NULL, NULL, '2026-08-08 19:30:00', '1.0', '2026-09-04 21:02:00', '2026-08-08 19:30:00'),
('11111111-0000-4000-8000-000000000008', 'vuhaiyen@gmail.com',       NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 0, 'active', NULL, NULL, NULL, '2026-08-15 08:55:00', '1.0', '2026-09-08 07:02:00', '2026-08-15 08:55:00'),
-- KHÔNG CÓ EMAIL — đăng ký bằng số điện thoại, đúng luồng form đăng ký hiện tại.
-- MySQL cho nhiều dòng NULL trong khoá duy nhất nên uq_users_email không cản.
('11111111-0000-4000-8000-000000000009', NULL,                       NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 0, 'active', NULL, NULL, NULL, '2026-08-21 16:12:00', '1.0', '2026-09-03 18:44:00', '2026-08-21 16:12:00'),
('11111111-0000-4000-8000-000000000010', 'hoangnamtrung@gmail.com',  NULL, '$2y$10$A1CVeSpAHx.YF6uSzyDPOOIXC.VeYg3KZnd.zmOjvuq0YeAulM156', 0, 'locked', 'Đặt 6 đơn COD liên tiếp rồi từ chối nhận hàng', '2026-09-02 15:20:00', '11111111-0000-4000-8000-000000000001', '2026-08-25 11:00:00', '1.0', '2026-09-01 20:10:00', '2026-08-25 11:00:00');

-- ----------------------------------------------------------------------------
-- HỒ SƠ — số điện thoại đã CHUẨN HOÁ về dạng 0xxxxxxxxx (normalizePhone), vì
-- uq_profiles_phone so chuỗi nguyên văn: "+84912345678" và "0912345678" là
-- cùng một thuê bao nhưng với MySQL là hai dòng khác nhau.
--
-- province_code 1 = Thành phố Hà Nội (provinces.open-api.vn). ward_code để
-- NULL: mã phường mới sau sáp nhập 01/07/2025 phải lấy từ API chứ không bịa —
-- ứng dụng luôn hiển thị theo TÊN nên để trống mã không hỏng gì.
--
-- phone_verified_at chỉ có ở bốn hồ sơ: luồng Zalo OTP chưa nối, nên trên máy
-- thật cột này rỗng hết. Điền sẵn vài dòng để luật "hồ sơ đã hoàn thiện" (Q72,
-- UserModel::hoSoDayDu) có cả hai vế để chạy thử.
-- ----------------------------------------------------------------------------
INSERT INTO `profiles`
    (`id`, `full_name`, `phone`, `phone_verified_at`, `address`,
     `province_code`, `province_name`, `ward_code`, `ward_name`,
     `date_of_birth`, `gender`, `avatar_path`) VALUES
('11111111-0000-4000-8000-000000000001', 'Nguyễn Quản Trị',  '0901000001', '2026-07-01 08:00:00', '46 Hoàng Hoa Thám', 1, 'Thành phố Hà Nội', NULL, 'Phường Tây Hồ',        '1988-03-12', 'nam',  NULL),
('11111111-0000-4000-8000-000000000002', 'Trần Thu Hằng',    '0901000002', '2026-07-01 08:05:00', '261 Ngọc Lâm',      1, 'Thành phố Hà Nội', NULL, 'Phường Bồ Đề',         '1994-11-02', 'nu',   NULL),
('11111111-0000-4000-8000-000000000003', 'Lê Minh Khôi',     '0901000003', '2026-07-01 08:10:00', '128 Xuân Thủy',     1, 'Thành phố Hà Nội', NULL, 'Phường Cầu Giấy',      '1996-06-25', 'nam',  NULL),
('11111111-0000-4000-8000-000000000004', 'Nguyễn Thu Hà',    '0912345678', '2026-07-14 09:25:00', 'Số 12 ngõ 45 Trần Duy Hưng', 1, 'Thành phố Hà Nội', NULL, 'Phường Yên Hòa',   '1997-05-18', 'nu',   NULL),
('11111111-0000-4000-8000-000000000005', 'Trần Minh Quân',   '0987654321', NULL,                  'Số 8 Lê Trọng Tấn', 1, 'Thành phố Hà Nội', NULL, 'Phường Khương Đình',   '1990-09-30', 'nam',  NULL),
('11111111-0000-4000-8000-000000000006', 'Lê Phương Anh',    '0905112233', '2026-08-02 10:50:00', '77 Hàng Bài',       1, 'Thành phố Hà Nội', NULL, 'Phường Hoàn Kiếm',     '2000-01-08', 'nu',   NULL),
('11111111-0000-4000-8000-000000000007', 'Phạm Đức Long',    '0938222444', NULL,                  '145 Tây Sơn',       1, 'Thành phố Hà Nội', NULL, 'Phường Đống Đa',       '1985-12-01', 'nam',  NULL),
('11111111-0000-4000-8000-000000000008', 'Vũ Hải Yến',       '0977335566', '2026-08-15 09:00:00', 'Số 3 ngách 20 Quang Trung', 1, 'Thành phố Hà Nội', NULL, 'Phường Hà Đông',    '1999-07-21', 'nu',   NULL),
('11111111-0000-4000-8000-000000000009', 'Đỗ Minh Châu',     '0966778899', NULL,                  '52 Ngô Xuân Quảng', 1, 'Thành phố Hà Nội', NULL, 'Xã Gia Lâm',           '1992-02-14', 'khac', NULL),
('11111111-0000-4000-8000-000000000010', 'Hoàng Nam Trung',  '0944556677', NULL,                  '17 Lê Đức Thọ',     1, 'Thành phố Hà Nội', NULL, 'Phường Từ Liêm',       '1993-10-05', 'nam',  NULL);

-- Một người một vai trò là đủ cho dữ liệu mẫu; bảng vẫn cho nhiều vai trò
-- cùng lúc (uq_user_roles khoá theo cặp user + role).
INSERT INTO `user_roles` (`id`, `user_id`, `role`) VALUES
('11111111-1000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000001', 'admin'),
('11111111-1000-4000-8000-000000000002', '11111111-0000-4000-8000-000000000002', 'staff'),
('11111111-1000-4000-8000-000000000003', '11111111-0000-4000-8000-000000000003', 'staff'),
('11111111-1000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000004', 'customer'),
('11111111-1000-4000-8000-000000000005', '11111111-0000-4000-8000-000000000005', 'customer'),
('11111111-1000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000006', 'customer'),
('11111111-1000-4000-8000-000000000007', '11111111-0000-4000-8000-000000000007', 'customer'),
('11111111-1000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000008', 'customer'),
('11111111-1000-4000-8000-000000000009', '11111111-0000-4000-8000-000000000009', 'customer'),
('11111111-1000-4000-8000-000000000010', '11111111-0000-4000-8000-000000000010', 'customer');

-- ----------------------------------------------------------------------------
-- SỔ ĐỊA CHỈ — BẢNG DI SẢN, ĐANG CHỜ GỠ (xem chú thích trong schema.sql).
--
-- Mã nguồn KHÔNG còn đọc hay ghi bảng này: mỗi khách nay có đúng một địa chỉ ở
-- năm cột của `profiles`. Seed vẫn nạp để file QUAY-LUI của
-- 2026-09-12-dia-chi-vao-ho-so.sql có dữ liệu mà lùi về.
-- ----------------------------------------------------------------------------
INSERT INTO `addresses`
    (`id`, `user_id`, `recipient_name`, `phone`, `line1`,
     `province_code`, `province_name`, `ward_code`, `ward_name`,
     `ghi_chu`, `nhan`, `is_default`) VALUES
('14141414-0000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000004', 'Nguyễn Thu Hà',   '0912345678', 'Số 12 ngõ 45 Trần Duy Hưng', 1, 'Thành phố Hà Nội', NULL, 'Phường Yên Hòa',     'Gọi trước 15 phút',            'nha',     1),
('14141414-0000-4000-8000-000000000002', '11111111-0000-4000-8000-000000000004', 'Nguyễn Thu Hà',   '0912345678', 'Tầng 9 toà Keangnam, Phạm Hùng', 1, 'Thành phố Hà Nội', NULL, 'Phường Từ Liêm', 'Chỉ nhận trong giờ hành chính', 'cong_ty', 0),
('14141414-0000-4000-8000-000000000003', '11111111-0000-4000-8000-000000000005', 'Trần Minh Quân',  '0987654321', 'Số 8 Lê Trọng Tấn',          1, 'Thành phố Hà Nội', NULL, 'Phường Khương Đình', NULL,                           'nha',     1),
('14141414-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000006', 'Lê Phương Anh',   '0905112233', '77 Hàng Bài',                1, 'Thành phố Hà Nội', NULL, 'Phường Hoàn Kiếm',   'Bảo vệ nhận giúp',             'nha',     1),
('14141414-0000-4000-8000-000000000005', '11111111-0000-4000-8000-000000000007', 'Phạm Đức Long',   '0938222444', '145 Tây Sơn',                1, 'Thành phố Hà Nội', NULL, 'Phường Đống Đa',     NULL,                           'nha',     1),
('14141414-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000007', 'Phạm Thị Mai',    '0938222555', '90 Nguyễn Chí Thanh',        1, 'Thành phố Hà Nội', NULL, 'Phường Láng',        'Gửi quà cho mẹ, đừng gọi trước', 'nha',   0),
('14141414-0000-4000-8000-000000000007', '11111111-0000-4000-8000-000000000008', 'Vũ Hải Yến',      '0977335566', 'Số 3 ngách 20 Quang Trung',  1, 'Thành phố Hà Nội', NULL, 'Phường Hà Đông',     NULL,                           'nha',     1),
('14141414-0000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000009', 'Đỗ Minh Châu',    '0966778899', '52 Ngô Xuân Quảng',          1, 'Thành phố Hà Nội', NULL, 'Xã Gia Lâm',         NULL,                           'nha',     1),
('14141414-0000-4000-8000-000000000009', '11111111-0000-4000-8000-000000000010', 'Hoàng Nam Trung', '0944556677', '17 Lê Đức Thọ',              1, 'Thành phố Hà Nội', NULL, 'Phường Từ Liêm',     NULL,                           'nha',     1),
('14141414-0000-4000-8000-000000000010', '11111111-0000-4000-8000-000000000006', 'Lê Phương Anh',   '0905112233', 'Toà VP Bank, 89 Láng Hạ',    1, 'Thành phố Hà Nội', NULL, 'Phường Láng',        'Nhận tại quầy lễ tân tầng 1',  'cong_ty', 0);

-- ----------------------------------------------------------------------------
-- GHI NHỚ ĐĂNG NHẬP
--
-- `validator` là BĂM sha256 của phần bí mật, không phải bí mật. Mười dòng dưới
-- đây băm những chuỗi ngẫu nhiên KHÔNG được lưu ở đâu cả — nghĩa là không cookie
-- nào trên đời khớp được với chúng. Đó là chủ ý: seed dựng dữ liệu cho màn
-- "thiết bị đã ghi nhớ" xem, không phát ra chìa khoá đăng nhập sẵn.
-- ----------------------------------------------------------------------------
INSERT INTO `remember_tokens` (`id`, `user_id`, `selector`, `validator`, `expires_at`, `user_agent`, `created_at`) VALUES
('15151515-0000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000004', '8f75fb3e6c15b1f32a9231d2aae3146b', 'b09bdcc663945dab254e29f51a277bb3a1b576404ed5c117309a5a373a3b3c2d', '2026-09-22 20:31:00', 'Chrome 129 · Windows 11',  '2026-09-06 20:31:00'),
('15151515-0000-4000-8000-000000000002', '11111111-0000-4000-8000-000000000004', '309b3035a5977e72af6d8d6805a97d31', '9d2dafa5fb3692d77a4db3895d0ae9ee3b1e60bdf39320398e34474a9dac84fc', '2026-09-18 08:00:00', 'Safari 18 · iPhone',       '2026-09-04 08:00:00'),
('15151515-0000-4000-8000-000000000003', '11111111-0000-4000-8000-000000000005', '90ec9d694a731ce811f0b27a911949ee', '6aa99676ca6831bb538360c7b97f406d21b77e0fd95ce610487f01e94058d074', '2026-09-19 11:07:00', 'Chrome 129 · Android 15',  '2026-09-05 11:07:00'),
('15151515-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000006', '0f65f0090ca8b0b5a63713affd34b626', '9362144694ffc5bc6fa4f1325608f19923a9c2aa1043182e089a66c4ad0d621e', '2026-09-21 09:14:00', 'Edge 129 · Windows 11',    '2026-09-07 09:14:00'),
('15151515-0000-4000-8000-000000000005', '11111111-0000-4000-8000-000000000007', '444f8403857bb1ed85f2b5540c6771b7', '86ab6fab17b3ee18d85772ae7234718bdb00bd7b3703c90d14e5f19c9088102e', '2026-09-18 21:02:00', 'Chrome 128 · macOS 15',    '2026-09-04 21:02:00'),
('15151515-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000008', 'f7aa0f6ad79f815183705da0ab104085', '9b4677c96ed4a386412cccb172f966012158d01576f534fa752d01c864cbe1aa', '2026-09-22 07:02:00', 'Safari 18 · iPad',         '2026-09-08 07:02:00'),
('15151515-0000-4000-8000-000000000007', '11111111-0000-4000-8000-000000000009', '14399a53f7cd4e480003b36a4f4a1874', 'fe40777aba246097f2440f667f97e9b8a19d924ff08c45eec20d33c756ac2dd6', '2026-09-17 18:44:00', 'Chrome 129 · Android 14',  '2026-09-03 18:44:00'),
('15151515-0000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000002', '88dc03ff1cdd84fc1fd1397da6664894', 'a9c1690d04e45b02b1e4e23b8c3c3367862e5307afd5a14742e3aa839debf461', '2026-09-22 07:40:00', 'Firefox 132 · Windows 11', '2026-09-08 07:40:00'),
-- Hai dòng ĐÃ HẾT HẠN: RememberModel::consume() phải bỏ qua chúng, và
-- idx_remember_expires có việc để làm khi dọn.
('15151515-0000-4000-8000-000000000009', '11111111-0000-4000-8000-000000000005', 'b524e135277152a0bde008ec27e9497c', '54c73917713b2b9a286e5c4227acfe8d7dac20bb1fb02ff160e972f181ddd3ac', '2026-08-20 10:00:00', 'Chrome 127 · Windows 10',  '2026-08-06 10:00:00'),
('15151515-0000-4000-8000-000000000010', '11111111-0000-4000-8000-000000000010', '3f969c3bee7be7605b1f64edd48aeb32', '7c53db886868df41a45662057928b708eb5ab613b0431117406ebe91166611be', '2026-08-31 20:10:00', 'Chrome 128 · Android 15',  '2026-08-17 20:10:00');

-- ----------------------------------------------------------------------------
-- QUÊN MẬT KHẨU — cả ba trạng thái đều có mặt.
--
--   pending  khách vừa gửi yêu cầu, CHƯA có token. Nhân viên gọi xác minh rồi
--            mới bấm tạo liên kết — đó là lý do bảng này có cột `handled_by`.
--   sent     đã có token.
--   used     đã đổi mật khẩu xong.
--
-- Dòng 03 và 09 có `user_id` NULL: khách gõ nhầm số / gõ một email không có
-- tài khoản nào. Nhân viên cần THẤY những dòng ấy, nên chúng vẫn được ghi.
-- ----------------------------------------------------------------------------
INSERT INTO `password_resets`
    (`id`, `user_id`, `contact`, `status`, `selector`, `validator`,
     `expires_at`, `used_at`, `handled_by`, `created_at`) VALUES
('16161616-0000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000004', 'nguyenthuha@gmail.com',     'used',    'e3f5f1be8e8556614d82553320915a14', 'e01c2907b82837c8027c62d34912ff7a0ab489b322fa2a5e208f39b01ed93f55', '2026-08-20 15:00:00', '2026-08-20 14:31:00', '11111111-0000-4000-8000-000000000002', '2026-08-20 14:00:00'),
('16161616-0000-4000-8000-000000000002', '11111111-0000-4000-8000-000000000005', '0987654321',                'used',    '8cd47a4c2f8128dd25567264011696c0', '20041afc2265d7b7c00562ee3cf6decbe268b3453715a9532c10503ee097c760', '2026-08-28 10:00:00', '2026-08-28 09:22:00', NULL,                                   '2026-08-28 09:00:00'),
('16161616-0000-4000-8000-000000000003', NULL,                                   '0912345670',                'pending', NULL,                               NULL,                                                               NULL,                  NULL,                  NULL,                                   '2026-09-05 08:30:00'),
('16161616-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000006', 'lephuonganh@gmail.com',     'sent',    '1c8cb3c1de907069d12f6992a7c21055', 'ddb1cc5ef07cbe8c96bff8331c84b5186e59a677886cb606a99aca873aa20fe3', '2026-09-08 12:00:00', NULL,                  '11111111-0000-4000-8000-000000000002', '2026-09-08 11:00:00'),
('16161616-0000-4000-8000-000000000005', '11111111-0000-4000-8000-000000000007', '0938222444',                'pending', NULL,                               NULL,                                                               NULL,                  NULL,                  NULL,                                   '2026-09-07 19:45:00'),
('16161616-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000008', 'vuhaiyen@gmail.com',        'sent',    '672be8fe00a77e31f588f0c504ff9cff', '1db930c4247167f56218998923e57c25120d8bcf9655afa4fdf491df92dffe8d', '2026-09-08 13:30:00', NULL,                  '11111111-0000-4000-8000-000000000003', '2026-09-08 12:30:00'),
('16161616-0000-4000-8000-000000000007', '11111111-0000-4000-8000-000000000009', '0966778899',                'used',    'b7279adb9f18cd11f9f5844524226fd8', 'c26d669322eb0bc224d7bb04e4ac6eae65d12c0a8507afa6c83910b9a4fd5ce0', '2026-09-01 09:00:00', '2026-09-01 08:41:00', '11111111-0000-4000-8000-000000000002', '2026-09-01 08:00:00'),
-- Token ĐÃ QUÁ HẠN mà chưa ai dùng: liên kết đặt lại phải từ chối nó.
('16161616-0000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000010', 'hoangnamtrung@gmail.com',   'sent',    'cdcedc7810e3ca7192b3a183c5364866', '1348f48b3e0fa4705912de8657286337a058a364de839c1d5fcfb77c1f64efa6', '2026-09-02 10:00:00', NULL,                  '11111111-0000-4000-8000-000000000001', '2026-09-02 09:00:00'),
('16161616-0000-4000-8000-000000000009', NULL,                                   'khongcotaikhoan@gmail.com', 'pending', NULL,                               NULL,                                                               NULL,                  NULL,                  NULL,                                   '2026-09-06 22:10:00'),
('16161616-0000-4000-8000-000000000010', '11111111-0000-4000-8000-000000000004', '0912345678',                'pending', NULL,                               NULL,                                                               NULL,                  NULL,                  NULL,                                   '2026-09-08 06:55:00');

-- ----------------------------------------------------------------------------
-- ĐẾM ĐĂNG NHẬP HỎNG (SNFR-06)
--
-- `login_key` là sha256 của định danh đã hạ chữ thường — mười giá trị dưới đây
-- băm đúng những chuỗi ghi ở cuối mỗi dòng, tính bằng cùng công thức với
-- LoginAttemptModel::khoa().
--
-- KHÔNG dòng nào đang khoá: `locked_until` của dòng cuối nằm trong QUÁ KHỨ.
-- Một cái khoá 15 phút còn hiệu lực trong file seed nghĩa là người vừa cài xong
-- đăng nhập không được mà không hiểu vì sao.
-- ----------------------------------------------------------------------------
INSERT INTO `login_attempts` (`login_key`, `fails`, `locked_until`, `updated_at`) VALUES
('20faf05baccf8a477fa337b77c0acb0e8bff77f34664feec6a057bd3cf23235b', 1, NULL,                  '2026-09-06 20:29:00'), -- 0912345678
('17756315ebd47b7110359fc7b168179bf6f2df3646fcc888bc8aa05c78b38ac1', 2, NULL,                  '2026-09-05 11:05:00'), -- 0987654321
('6030f4d0c80f4c2e5e324cd8fdfc7aa1abd3cd8a06947d72094bdf1a3a781328', 1, NULL,                  '2026-09-07 09:12:00'), -- 0905112233
('9b7ad5b16e43f7bdf3ae8c69b5e21c26c5ebaf6f46a2ae1db7d6c969397aa405', 3, NULL,                  '2026-09-04 22:15:00'), -- nguyenthuha@gmail.com
('6f30d912c9c6cb7d4d100a4d124670ad1b5474953f823f809ef1c644b2780228', 1, NULL,                  '2026-09-02 18:03:00'), -- tranminhquan@gmail.com
('a57575d7526ea046bfe2581f03bc0f54f7328b45ddb25799a62e13be63520535', 4, NULL,                  '2026-09-07 19:44:00'), -- 0938222444
('e9d04367629929ffcdf0f2fd55dd3f5ea1e1e595c22da850dd8215f0f60a9023', 1, NULL,                  '2026-09-08 07:01:00'), -- 0977335566
-- Định danh KHÔNG ứng với tài khoản nào — và vẫn được đếm y hệt. Đó là cả điểm
-- của bảng này: câu trả lời của hệ thống không tiết lộ email nào có tài khoản.
('509f466b5f4b0ba4b77c5dc1fd137ed1140f3c04657e4d117905d6c9a6dec141', 2, NULL,                  '2026-09-06 22:09:00'), -- khongcotaikhoan@gmail.com
('4dcd08183afa2fce08ef4df19b690fbf9656131a3863c98d1ff811623c287c34', 3, NULL,                  '2026-09-03 14:20:00'), -- 0911222333
('9eceb13483d7f187ec014fd6d4854d1420cfc634328af85f51d0323ba8622e21', 0, '2026-09-07 21:15:00', '2026-09-07 21:00:00'); -- abc@example.com — khoá đã hết hạn

-- Bảng tóm tắt số đo (di sản, chờ gỡ theo FR-DM-01) — bản sao của bản ghi mới
-- nhất trong `customer_prescriptions`. Nạp cho cả 10 tài khoản.
INSERT INTO `prescriptions` (`user_id`, `od_sph`, `od_cyl`, `od_axis`, `od_va`, `os_sph`, `os_cyl`, `os_axis`, `os_va`, `pd`, `measured_at`, `store_id`, `recommendation`) VALUES
('11111111-0000-4000-8000-000000000001', -1.25, -0.50,  80, '10/10', -1.00, -0.25,  95, '10/10', 63.0, '2026-06-20', '77777777-0000-4000-8000-000000000003', 'Đơn tròng 1.60 chống ánh sáng xanh'),
('11111111-0000-4000-8000-000000000002', -2.50, -0.75, 170, '10/10', -2.75, -0.50,  10, '10/10', 61.5, '2026-06-22', '77777777-0000-4000-8000-000000000003', NULL),
('11111111-0000-4000-8000-000000000003', -0.75,  NULL,NULL, '10/10', -0.50,  NULL,NULL, '10/10', 64.0, '2026-06-25', '77777777-0000-4000-8000-000000000004', NULL),
('11111111-0000-4000-8000-000000000004', -3.25, -0.75, 175, '10/10', -3.50, -1.00,   5, '9/10',  62.0, '2026-08-30', @store_tayho,                          'Tròng 1.61 chống ánh sáng xanh, làm máy tính nhiều giờ'),
('11111111-0000-4000-8000-000000000005', -5.75, -1.25,  15, '9/10',  -6.00, -1.50, 165, '9/10',  64.5, '2026-08-18', @store_longbien,                       'Tròng 1.67 trở lên cho đỡ dày mép'),
('11111111-0000-4000-8000-000000000006', -1.50, -0.25,  90, '10/10', -1.75, -0.50,  85, '10/10', 60.0, '2026-08-25', '77777777-0000-4000-8000-000000000004', NULL),
('11111111-0000-4000-8000-000000000007', -0.50,  NULL,NULL, '10/10', -0.75,  NULL,NULL, '10/10', 66.0, '2026-08-12', '77777777-0000-4000-8000-000000000005', 'Có dấu hiệu lão thị, nên cân nhắc đa tròng'),
('11111111-0000-4000-8000-000000000008', -7.00, -2.00,  20, '8/10',  -7.25, -2.25, 160, '8/10',  61.0, '2026-09-02', @store_tayho,                          'Tròng 1.74, đo lại sau 6 tháng'),
('11111111-0000-4000-8000-000000000009', -2.00, -0.50, 100, '10/10', -2.00, -0.50,  80, '10/10', 63.5, '2026-07-28', '77777777-0000-4000-8000-000000000006', NULL),
('11111111-0000-4000-8000-000000000010', -4.00, -1.00,  30, '9/10',  -4.25, -1.00, 150, '9/10',  65.0, '2026-07-10', '77777777-0000-4000-8000-000000000007', NULL);

-- ============================================================================
-- 3. BỘ SƯU TẬP VÀ SẢN PHẨM
-- ============================================================================

INSERT INTO `collections`
    (`id`, `slug`, `name`, `tagline`, `intro`, `story`, `season_code`, `season_label`,
     `brand`, `product_line`, `designed_in`, `made_in`, `audience`, `design_style`,
     `palette`, `signature`, `launch_offer`, `channels`, `images`, `launched_at`,
     `sort_order`, `is_visible`) VALUES
('66666666-0000-4000-8000-000000000001', 'titan-sieu-nhe', 'Titan Siêu Nhẹ', 'Dưới 12 gram, quên là đang đeo kính', 'Bộ gọng titan nguyên khối cho người đeo kính suốt ngày làm việc.', 'Titan beta được cắt CNC rồi đánh mờ bằng tay. Mỗi chiếc qua 14 công đoạn và mất hai ngày để hoàn thiện phần càng.', 'SS26', 'Xuân–Hè 2026', 'Vin Eyewear', 'Titan Line', 'Hà Nội', 'Nhật Bản', '[{"tieu_de":"Hợp với","gia_tri":"Người đeo trên 8 tiếng mỗi ngày"},{"tieu_de":"Hợp với","gia_tri":"Da nhạy cảm, dị ứng kim loại thường"},{"tieu_de":"Hợp với","gia_tri":"Độ cận trung bình tới cao"},{"tieu_de":"Đừng mua nếu","gia_tri":"Bạn thích gọng bản to tạo điểm nhấn","ghi_chu":"Bộ này cố ý mảnh"}]', 'Tối giản, không logo lớn', '[{"ten":"Bạc mờ","ma_mau":"#C8CBD0"},{"ten":"Ghi khói","ma_mau":"#6E7278"},{"ten":"Vàng hồng","ma_mau":"#C9A18A"}]', '["Càng kính mảnh 1.2mm","Bản lề không ốc","Đệm mũi silicon thay được"]', 'Tặng tròng chống ánh sáng xanh 1.60 cho 50 khách đầu tiên', 'Website, cửa hàng Tây Hồ, cửa hàng Cầu Giấy', '["/assets/images/product-1.jpg","/assets/images/showroom-frames.jpg"]', '2026-03-15', 10, 1),
('66666666-0000-4000-8000-000000000002', 'acetate-thu-cong', 'Acetate Thủ Công', 'Vân acetate không chiếc nào giống chiếc nào', 'Gọng acetate Ý, mài và đánh bóng thủ công.', 'Tấm acetate Mazzucchelli được ủ 8 tuần trước khi cắt, nên vân màu nằm sâu trong thân gọng chứ không phải lớp phủ ngoài.', 'AW26', 'Thu–Đông 2026', 'Vin Eyewear', 'Atelier', 'Hà Nội', 'Ý', '[{"tieu_de":"Hợp với","gia_tri":"Người thích gọng có cá tính"},{"tieu_de":"Hợp với","gia_tri":"Khuôn mặt nhỏ tới trung bình"},{"tieu_de":"Đừng mua nếu","gia_tri":"Bạn cần gọng nhẹ nhất có thể","ghi_chu":"Acetate nặng hơn titan khoảng 8 gram"}]', 'Cổ điển, bản dày vừa', '[{"ten":"Nâu vân rùa","ma_mau":"#6B4423"},{"ten":"Đen bóng","ma_mau":"#1A1A1A"},{"ten":"Kem sữa","ma_mau":"#E8DCC8"}]', '["Vân đá mài tay","Lõi thép trong càng","Khắc số hiệu bên trong càng trái"]', 'Khắc tên miễn phí trong tháng ra mắt', 'Website, toàn bộ cửa hàng', '["/assets/images/product-2.jpg","/assets/images/product-3.jpg"]', '2026-08-01', 20, 1),
('66666666-0000-4000-8000-000000000003', 'phi-cong-co-dien', 'Phi Công Cổ Điển', 'Dáng aviator nguyên bản, tròng phân cực', 'Kính râm dáng phi công với tròng phân cực cắt chói mặt đường.', 'Khuôn giọt nước giữ đúng tỉ lệ bản gốc thập niên 1930, chỉ đổi phần đệm mũi cho hợp sống mũi người Việt.', 'SS26', 'Xuân–Hè 2026', 'Vin Eyewear', 'Heritage', 'Hà Nội', 'Việt Nam', '[{"tieu_de":"Hợp với","gia_tri":"Lái xe đường dài"},{"tieu_de":"Hợp với","gia_tri":"Mặt trái xoan, mặt vuông"},{"tieu_de":"Đừng mua nếu","gia_tri":"Bạn cần cắt độ trên -6.00","ghi_chu":"Tròng cong khó mài độ cao"}]', 'Cổ điển, kim loại mảnh', '[{"ten":"Vàng đồng","ma_mau":"#B08D57"},{"ten":"Bạc","ma_mau":"#BFC3C7"}]', '["Tròng phân cực 3 lớp","Đệm mũi kép","Bản lề lò xo"]', 'Giảm 15% khi mua kèm hộp da', 'Website, cửa hàng Hoàn Kiếm', '["/assets/images/product-4.jpg"]', '2026-04-20', 30, 1),
('66666666-0000-4000-8000-000000000004', 'van-phong-anh-sang-xanh', 'Văn Phòng Ánh Sáng Xanh', 'Cho tám tiếng trước màn hình', 'Gọng nhẹ đi kèm tròng lọc ánh sáng xanh.', 'Bộ này sinh ra từ đúng một câu hỏi lặp lại ở quầy: đeo máy tính cả ngày thì chọn gì.', 'SS26', 'Xuân–Hè 2026', 'Vin Eyewear', 'Daily', 'Hà Nội', 'Việt Nam', '[{"tieu_de":"Hợp với","gia_tri":"Dân văn phòng, lập trình viên"},{"tieu_de":"Đừng mua nếu","gia_tri":"Bạn cần kính đi nắng","ghi_chu":"Tròng gần như trong suốt"}]', 'Trung tính, dễ phối', '[{"ten":"Đen nhám","ma_mau":"#222222"},{"ten":"Xám khói","ma_mau":"#7A7A7A"}]', '["Tròng lọc 40% ánh sáng xanh","Gọng TR90 dẻo"]', 'Miễn phí cắt tròng lọc ánh sáng xanh 1.56', 'Website', '["/assets/images/product-5.jpg"]', '2026-05-10', 40, 1),
('66666666-0000-4000-8000-000000000005', 'the-thao-ngoai-troi', 'Thể Thao Ngoài Trời', 'Ôm sát, không tuột khi chạy', 'Gọng ôm cong, tròng phân cực chống va đập.', 'Càng kính phủ cao su và đệm mũi bám ẩm — hai chi tiết chỉ có nghĩa khi người đeo đổ mồ hôi.', 'SS26', 'Xuân–Hè 2026', 'Vin Eyewear', 'Active', 'Hà Nội', 'Việt Nam', '[{"tieu_de":"Hợp với","gia_tri":"Chạy bộ, đạp xe"},{"tieu_de":"Đừng mua nếu","gia_tri":"Bạn muốn đeo đi làm","ghi_chu":"Dáng ôm rất thể thao"}]', 'Ôm cong, hiện đại', '[{"ten":"Đen mờ","ma_mau":"#1C1C1C"},{"ten":"Xanh neon","ma_mau":"#39FF88"}]', '["Tròng polycarbonate 1.59","Càng phủ cao su"]', NULL, 'Website, cửa hàng Mỹ Đình', '["/assets/images/product-6.jpg"]', '2026-06-05', 50, 1),
('66666666-0000-4000-8000-000000000006', 'doi-mau-thong-minh', 'Đổi Màu Thông Minh', 'Một chiếc cho cả trong nhà và ngoài trời', 'Tròng photochromic sẫm lại trong 30 giây khi ra nắng.', 'Lớp đổi màu nằm trong phôi chứ không phủ ngoài, nên không bạc theo thời gian như hàng phủ.', 'AW26', 'Thu–Đông 2026', 'Vin Eyewear', 'Daily', 'Hà Nội', 'Hàn Quốc', '[{"tieu_de":"Hợp với","gia_tri":"Người ra vào liên tục trong ngày"},{"tieu_de":"Đừng mua nếu","gia_tri":"Bạn hay đeo trong xe hơi","ghi_chu":"Kính chắn gió cản tia UV nên tròng không sẫm"}]', 'Tối giản', '[{"ten":"Trong suốt","ma_mau":"#F2F2F2"},{"ten":"Xám khói","ma_mau":"#585858"}]', '["Sẫm 30 giây, nhạt lại 3 phút","Chặn 100% UV ở mọi mức màu"]', 'Giảm 500.000đ khi cắt tròng đổi màu 1.61', 'Website, toàn bộ cửa hàng', '["/assets/images/product-1.jpg"]', '2026-08-20', 60, 1),
('66666666-0000-4000-8000-000000000007', 'khong-vien-toi-gian', 'Không Viền Tối Giản', 'Nhìn như không đeo gì', 'Gọng khoan không viền, tròng gắn trực tiếp vào càng.', 'Không viền thì tròng phải là Trivex hoặc polycarbonate — acrylic thường nứt ngay tại lỗ khoan.', 'SS26', 'Xuân–Hè 2026', 'Vin Eyewear', 'Titan Line', 'Hà Nội', 'Nhật Bản', '[{"tieu_de":"Hợp với","gia_tri":"Người muốn kính kín đáo nhất có thể"},{"tieu_de":"Đừng mua nếu","gia_tri":"Bạn hay làm rơi kính","ghi_chu":"Không viền thì mép tròng chịu lực trực tiếp"}]', 'Tối giản tuyệt đối', '[{"ten":"Bạc","ma_mau":"#C0C4C8"},{"ten":"Vàng champagne","ma_mau":"#D9C7A3"}]', '["Không ốc lộ ra ngoài","Trọng lượng 9 gram"]', NULL, 'Website, cửa hàng Tây Hồ', '["/assets/images/product-2.jpg"]', '2026-02-10', 70, 1),
('66666666-0000-4000-8000-000000000008', 'tre-em-an-toan', 'Trẻ Em An Toàn', 'Dẻo, không gãy, không ốc', 'Gọng TR90 cho trẻ 4 đến 14 tuổi.', 'Bẻ ngược 180 độ mà không gãy — thử ngay tại quầy được, và đó là cách duy nhất phụ huynh tin.', 'AW26', 'Thu–Đông 2026', 'Vin Eyewear', 'Kids', 'Hà Nội', 'Việt Nam', '[{"tieu_de":"Hợp với","gia_tri":"Trẻ 4–14 tuổi"},{"tieu_de":"Đừng mua nếu","gia_tri":"Trẻ cần gọng người lớn cỡ nhỏ","ghi_chu":"Cầu kính bộ này rất hẹp"}]', 'Dẻo, nhiều màu', '[{"ten":"Xanh biển","ma_mau":"#2C6FB5"},{"ten":"Hồng","ma_mau":"#E58FA8"},{"ten":"Xanh lá","ma_mau":"#4CAF50"}]', '["Bẻ ngược không gãy","Dây đeo sau gáy kèm theo"]', 'Bảo hành gãy gọng 24 tháng', 'Website, toàn bộ cửa hàng', '["/assets/images/product-6.jpg"]', '2026-07-15', 80, 1),
('66666666-0000-4000-8000-000000000009', 'doc-sach-lao-thi', 'Đọc Sách Lão Thị', 'Kính đọc gần có sẵn độ', 'Kính lão từ +1.00 đến +3.00, lấy ngay tại quầy.', 'Kính có sẵn độ chỉ đúng khi hai mắt lệch nhau ít — nên bộ này luôn bán kèm một lần đo miễn phí để biết mình có nằm trong số đó không.', 'AW26', 'Thu–Đông 2026', 'Vin Eyewear', 'Daily', 'Hà Nội', 'Việt Nam', '[{"tieu_de":"Hợp với","gia_tri":"Người trên 45 tuổi, chỉ cần đọc gần"},{"tieu_de":"Đừng mua nếu","gia_tri":"Hai mắt lệch độ nhiều","ghi_chu":"Phải cắt tròng riêng"}]', 'Gọn, nhẹ, gấp được', '[{"ten":"Nâu","ma_mau":"#5A4632"},{"ten":"Đen","ma_mau":"#202020"}]', '["Gấp bỏ túi","Có sẵn 9 mức độ"]', NULL, 'Cửa hàng', '["/assets/images/product-3.jpg"]', '2026-09-01', 90, 1),
-- Bộ CHƯA MỞ BÁN: idx_collections_visible và CollectionModel::visible() cần một
-- dòng is_visible = 0 để có gì mà lọc.
('66666666-0000-4000-8000-000000000010', 'tet-2027', 'Tết 2027', 'Sắp ra mắt', 'Bộ quà tặng Tết, mở bán tháng 12.', NULL, 'SS27', 'Tết 2027', 'Vin Eyewear', 'Gift', 'Hà Nội', 'Việt Nam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100, 0);

INSERT INTO `collection_faqs` (`id`, `collection_id`, `question`, `answer`, `sort_order`) VALUES
('67676767-0000-4000-8000-000000000001', '66666666-0000-4000-8000-000000000001', 'Gọng titan có lắp được tròng cận nặng không?', 'Được tới -8.00. Trên mức đó nên chọn mẫu full rim trong bộ vì mép tròng dày sẽ lộ ra ngoài viền.', 10),
('67676767-0000-4000-8000-000000000002', '66666666-0000-4000-8000-000000000001', 'Titan có bị đen da không?', 'Không. Titan nguyên chất không gây phản ứng với mồ hôi, đây là lý do bộ này hợp người dị ứng kim loại thường.', 20),
('67676767-0000-4000-8000-000000000003', '66666666-0000-4000-8000-000000000002', 'Acetate có bị cong khi để trong xe không?', 'Có. Đừng để trên táp-lô giữa trưa. Nếu lỡ cong, mang ra cửa hàng chỉnh lại miễn phí.', 10),
('67676767-0000-4000-8000-000000000004', '66666666-0000-4000-8000-000000000002', 'Vân gọng có giống ảnh không?', 'Gần giống nhưng không trùng khít. Vân nằm trong tấm acetate nên mỗi chiếc một kiểu.', 20),
('67676767-0000-4000-8000-000000000005', '66666666-0000-4000-8000-000000000003', 'Kính râm bộ này cắt độ được không?', 'Bốn trong sáu mẫu cắt được tới -6.00. Hai mẫu tròng cong nhiều thì không.', 10),
('67676767-0000-4000-8000-000000000006', '66666666-0000-4000-8000-000000000003', 'Phân cực khác chống UV thế nào?', 'Phân cực cắt chói phản xạ từ mặt đường và mặt nước. Chống UV là chuyện khác, và cả bộ này đều có UV400.', 20),
('67676767-0000-4000-8000-000000000007', '66666666-0000-4000-8000-000000000004', 'Tròng lọc ánh sáng xanh có bị ám vàng không?', 'Bản 1.56 hơi ám vàng nhẹ khi nhìn giấy trắng. Bản 1.61 gần như không.', 10),
('67676767-0000-4000-8000-000000000008', '66666666-0000-4000-8000-000000000005', 'Đeo chạy bộ có tuột không?', 'Không, nếu chọn đúng cỡ. Đệm mũi càng ẩm càng bám, đó là thiết kế chứ không phải lỗi.', 10),
('67676767-0000-4000-8000-000000000009', '66666666-0000-4000-8000-000000000006', 'Đeo trong ô tô có sẫm không?', 'Không sẫm mấy. Kính chắn gió đã cản phần lớn tia UV, mà tròng đổi màu cần tia UV để hoạt động.', 10),
('67676767-0000-4000-8000-000000000010', '66666666-0000-4000-8000-000000000008', 'Trẻ làm gãy gọng có bảo hành không?', 'Có, 24 tháng cho phần gọng, kể cả gãy do va đập. Tròng vỡ thì không.', 10);

-- ----------------------------------------------------------------------------
-- SẢN PHẨM — 10 mặt hàng.
--
-- schema.sql cố ý KHÔNG seed sản phẩm (xem khối "KHÔNG SEED SẢN PHẨM"). Lý do
-- ở đó vẫn đúng và không mâu thuẫn với file này: catalog mẫu thuộc về seed.sql
-- — thứ người ta chạy khi MUỐN dữ liệu giả — chứ không thuộc về file cài đặt
-- mà ai cũng phải chạy.
--
-- Hai mặt hàng để `stock_quantity` = 0 và `status` = 'out_of_stock': nút "Báo
-- khi có hàng" và bảng `stock_waitlist` chỉ có đường chạy khi hết hàng thật.
-- `is_visible` luôn khớp `publish_status` (visible → 1) như schema đã dặn.
-- ----------------------------------------------------------------------------
INSERT INTO `products`
    (`id`, `slug`, `sku`, `name`, `category_id`, `brand`, `frame_shape`, `rim_type`,
     `material`, `color`, `lens_color`, `gender`, `collection`, `tags`,
     `description`, `description_short`, `specs`, `images`,
     `price`, `compare_at_price`, `cost_price`, `sale_price`, `sale_from`, `sale_to`,
     `stock_quantity`, `low_stock_at`, `status`, `is_featured`, `is_visible`, `publish_status`,
     `eyewear_type`, `frame_finish`, `hinge_type`, `nose_pad`, `weight_g`,
     `lens_width_mm`, `bridge_mm`, `temple_mm`, `size_class`, `face_shapes`,
     `lens_material`, `lens_index`, `lens_indexes`, `sph_max`, `cyl_max`,
     `lens_coatings`, `is_polarized`, `is_uv400`, `is_photochromic`, `lens_vlt`, `lens_category`,
     `rx_ready`, `rx_order_enabled`, `lens_types`, `rx_note`, `price_with_lens`,
     `rating`, `review_count`, `created_at`) VALUES

('44444444-0000-4000-8000-000000000001', 'aurora-titan-vuong', 'VEW-G01', 'Aurora Titan Vuông', @cat_gong, 'Vin Eyewear', 'square', 'full-rim', 'titanium', 'Bạc mờ', NULL, 'unisex', 'titan-sieu-nhe', 'bestseller, đi làm, siêu nhẹ', 'Gọng titan beta nguyên khối, càng mảnh 1.2mm, bản lề không ốc. Dáng vuông bo nhẹ hợp phần lớn khuôn mặt và không tràn ra ngoài đuôi mắt.', 'Titan nguyên khối 11 gram, dáng vuông bo nhẹ, hợp đeo cả ngày.', '{"Vật liệu":"Titan beta","Kích thước":"52-18-145","Trọng lượng":"11g","Bản lề":"Không ốc"}', '["/assets/images/product-1.jpg","/assets/images/showroom-frames.jpg"]', 3290000, 3890000, 1450000, NULL, NULL, NULL, 24, 5, 'in_stock', 1, 1, 'visible', 'gong-can', 'Đánh mờ', 'Không ốc', 'Silicon thay được', 11, 52, 18, 145, 'M', 'tron,vuong,trai-xoan', NULL, NULL, '1.56,1.61,1.67,1.74', '-8.00', '-4.00', 'uv400,chong-loa,chong-tray', 0, 1, 0, NULL, NULL, 1, 1, 'don-trong,da-trong,anh-sang-xanh', 'Cắt độ tới -8.00', 3740000, 4.5, 2, '2026-03-15 09:00:00'),

('44444444-0000-4000-8000-000000000002', 'hanoi-acetate-tron', 'VEW-G02', 'Hanoi Acetate Tròn', @cat_gong, 'Vin Eyewear', 'round', 'full-rim', 'acetate', 'Nâu vân rùa', NULL, 'female', 'acetate-thu-cong', 'thủ công, cổ điển', 'Gọng acetate Mazzucchelli ủ 8 tuần, mài và đánh bóng thủ công. Lõi thép trong càng giữ dáng sau nhiều lần gấp.', 'Acetate Ý vân rùa, dáng tròn cổ điển, lõi thép trong càng.', '{"Vật liệu":"Acetate Ý","Kích thước":"48-20-140","Trọng lượng":"19g"}', '["/assets/images/product-2.jpg"]', 1890000, NULL, 780000, 1590000, '2026-09-01', '2026-09-30', 15, 5, 'in_stock', 1, 1, 'visible', 'gong-can', 'Đánh bóng', 'Bản lề thường', 'Liền thân', 19, 48, 20, 140, 'S', 'vuong,chu-nhat,tim', NULL, NULL, '1.56,1.61,1.67', '-6.00', '-3.00', 'uv400,chong-loa', 0, 1, 0, NULL, NULL, 1, 1, 'don-trong,anh-sang-xanh', NULL, 2340000, 5.0, 1, '2026-08-01 10:00:00'),

('44444444-0000-4000-8000-000000000003', 'meta-browline-nau-van', 'VEW-G03', 'Meta Browline Nâu Vân', @cat_gong, 'Vin Eyewear', 'browline', 'half-rim', 'acetate', 'Nâu vân', NULL, 'male', 'acetate-thu-cong', 'đi làm, browline', 'Browline nửa gọng: phần trên acetate vân nâu, phần dưới dây cước. Nhẹ hơn full rim mà vẫn có nét.', 'Browline nửa gọng, acetate vân nâu, nhẹ và lịch sự.', '{"Vật liệu":"Acetate + kim loại","Kích thước":"50-19-145","Trọng lượng":"16g"}', '["/assets/images/product-3.jpg"]', 2190000, NULL, 950000, NULL, NULL, NULL, 8, 10, 'in_stock', 0, 1, 'visible', 'gong-can', 'Đánh bóng', 'Bản lề lò xo', 'Đệm mũi kép', 16, 50, 19, 145, 'M', 'tron,trai-xoan', NULL, NULL, '1.56,1.61,1.67', '-6.00', '-2.00', 'uv400,chong-loa,chong-tray', 0, 1, 0, NULL, NULL, 1, 1, 'don-trong,da-trong', 'Nửa gọng dây cước — không cắt độ trên -6.00', 2640000, 4.0, 1, '2026-08-05 11:00:00'),

('44444444-0000-4000-8000-000000000004', 'lumen-rimless-titan', 'VEW-G04', 'Lumen Rimless Titan', '33333333-0000-4000-8000-000000000004', 'Vin Eyewear', 'oval', 'rimless', 'titanium', 'Vàng champagne', NULL, 'unisex', 'khong-vien-toi-gian', 'không viền, cao cấp', 'Gọng khoan không viền, tròng bắt trực tiếp vào càng. Bắt buộc dùng phôi Trivex hoặc polycarbonate — acrylic thường nứt tại lỗ khoan.', 'Không viền, 9 gram, kín đáo nhất trong các dòng gọng.', '{"Vật liệu":"Titan","Kích thước":"53-17-145","Trọng lượng":"9g"}', '["/assets/images/product-4.jpg"]', 4290000, 4890000, 2100000, NULL, NULL, NULL, 5, 3, 'in_stock', 1, 1, 'visible', 'gong-can', 'Đánh mờ', 'Không ốc', 'Silicon thay được', 9, 53, 17, 145, 'M', 'trai-xoan,mat-dai', 'Trivex', 1.53, '1.53,1.59,1.60', '-5.00', '-2.00', 'uv400,chong-loa', 0, 1, 0, NULL, NULL, 1, 1, 'don-trong', 'Chỉ nhận phôi Trivex hoặc polycarbonate', 5580000, 5.0, 0, '2026-02-10 09:00:00'),

('44444444-0000-4000-8000-000000000005', 'nova-mat-meo', 'VEW-G05', 'Nova Mắt Mèo', @cat_gong, 'Vin Eyewear', 'cat-eye', 'full-rim', 'tr90', 'Hồng phấn', NULL, 'female', 'van-phong-anh-sang-xanh', 'nữ, mắt mèo', 'Gọng TR90 dáng mắt mèo, nhẹ và dẻo. Đuôi gọng hếch nhẹ nâng nét mặt mà không quá điệu.', 'Mắt mèo TR90 nhẹ 13 gram, dáng nữ tính vừa phải.', '{"Vật liệu":"TR90","Kích thước":"51-17-140","Trọng lượng":"13g"}', '["/assets/images/product-5.jpg"]', 1590000, NULL, 620000, NULL, NULL, NULL, 0, 5, 'out_of_stock', 0, 1, 'visible', 'gong-can', 'Nhám', 'Bản lề lò xo', 'Liền thân', 13, 51, 17, 140, 'S', 'tron,vuong,tim', NULL, NULL, '1.56,1.61,1.67', '-6.00', '-3.00', 'uv400,anh-sang-xanh', 0, 1, 0, NULL, NULL, 1, 1, 'don-trong,anh-sang-xanh', NULL, 2180000, 5.0, 0, '2026-05-10 10:00:00'),

('44444444-0000-4000-8000-000000000006', 'solis-phi-cong-phan-cuc', 'VEW-S01', 'Solis Phi Công Phân Cực', @cat_ram, 'Vin Eyewear', 'aviator', 'full-rim', 'metal', 'Vàng đồng', 'Xám khói', 'unisex', 'phi-cong-co-dien', 'bestseller, phân cực, lái xe', 'Dáng aviator giọt nước giữ đúng tỉ lệ bản gốc. Tròng phân cực ba lớp cắt chói mặt đường, cấp 3 hợp nắng gắt.', 'Aviator phân cực cấp 3, cắt chói mặt đường khi lái xe.', '{"Vật liệu":"Kim loại","Kích thước":"58-14-140","Tròng":"Phân cực cấp 3"}', '["/assets/images/product-6.jpg","/assets/images/hero-eyewear.jpg"]', 2690000, 2990000, 1150000, NULL, NULL, NULL, 12, 5, 'in_stock', 1, 1, 'visible', 'kinh-ram', 'Mạ bóng', 'Bản lề lò xo', 'Đệm mũi kép', 24, 58, 14, 140, 'L', 'trai-xoan,vuong,tim', 'CR-39', 1.50, '1.56,1.61', '-6.00', '-2.00', 'uv400,chong-loa,phan-cuc', 1, 1, 0, '12%', 3, 1, 1, 'don-trong', 'Tròng cong — cắt độ tối đa -6.00', 3980000, 5.0, 2, '2026-04-20 09:00:00'),

('44444444-0000-4000-8000-000000000007', 'bay-wayfarer-xam-khoi', 'VEW-S02', 'Bay Wayfarer Xám Khói', @cat_ram, 'Vin Eyewear', 'square', 'full-rim', 'acetate', 'Đen bóng', 'Xám khói', 'unisex', 'phi-cong-co-dien', 'cổ điển, đi biển', 'Dáng vuông bản dày kinh điển, tròng xám khói cấp 3. Acetate đặc nên nặng tay hơn kính râm kim loại.', 'Kính râm dáng vuông bản dày, tròng xám khói cấp 3.', '{"Vật liệu":"Acetate","Kích thước":"54-18-145","Tròng":"Xám khói cấp 3"}', '["/assets/images/product-2.jpg"]', 1990000, NULL, 820000, NULL, NULL, NULL, 0, 5, 'out_of_stock', 0, 1, 'visible', 'kinh-ram', 'Đánh bóng', 'Bản lề thường', 'Liền thân', 28, 54, 18, 145, 'M', 'tron,trai-xoan,mat-dai', 'CR-39', 1.50, NULL, NULL, NULL, 'uv400,chong-tray', 0, 1, 0, '15%', 3, 0, 0, NULL, NULL, NULL, 5.0, 0, '2026-04-20 09:10:00'),

('44444444-0000-4000-8000-000000000008', 'marine-oval-gradient', 'VEW-S03', 'Marine Oval Gradient', @cat_ram, 'Vin Eyewear', 'oval', 'half-rim', 'metal', 'Bạc', 'Gradient nâu', 'female', 'phi-cong-co-dien', 'nữ, gradient', 'Tròng gradient đậm ở trên nhạt dần xuống dưới — che nắng mà vẫn nhìn rõ bàn tay và điện thoại.', 'Kính râm oval tròng gradient nâu, nhẹ 20 gram.', '{"Vật liệu":"Kim loại","Kích thước":"55-16-140","Tròng":"Gradient nâu"}', '["/assets/images/product-3.jpg"]', 2290000, NULL, 980000, NULL, NULL, NULL, 9, 5, 'in_stock', 0, 1, 'visible', 'kinh-ram', 'Mạ bóng', 'Bản lề lò xo', 'Đệm mũi kép', 20, 55, 16, 140, 'M', 'vuong,chu-nhat', 'CR-39', 1.50, NULL, NULL, NULL, 'uv400,chong-loa', 0, 1, 0, '18% → 45%', 2, 0, 0, NULL, NULL, NULL, 5.0, 0, '2026-04-22 09:00:00'),

('44444444-0000-4000-8000-000000000009', 'chroma-doi-mau-da-dung', 'VEW-D01', 'Chroma Đổi Màu Đa Dụng', '33333333-0000-4000-8000-000000000005', 'Vin Eyewear', 'geometric', 'full-rim', 'tr90', 'Xám khói', 'Đổi màu', 'unisex', 'doi-mau-thong-minh', 'bestseller, đổi màu, đa dụng', 'Một chiếc dùng cả trong nhà lẫn ngoài trời. Tròng sẫm trong 30 giây khi ra nắng và nhạt lại sau khoảng 3 phút.', 'Tròng đổi màu, một chiếc cho cả trong nhà và ngoài trời.', '{"Vật liệu":"TR90","Kích thước":"53-18-145","Tròng":"Photochromic"}', '["/assets/images/product-1.jpg"]', 2890000, NULL, 1240000, NULL, NULL, NULL, 18, 5, 'in_stock', 1, 1, 'visible', 'da-dung', 'Nhám', 'Bản lề lò xo', 'Đệm mũi kép', 15, 53, 18, 145, 'M', 'tron,vuong,trai-xoan', NULL, NULL, '1.56,1.61,1.67', '-7.00', '-3.00', 'uv400,doi-mau,chong-loa', 0, 1, 1, '18% → 62%', 2, 1, 1, 'don-trong,da-trong,doi-mau', 'Đổi màu cắt độ tới -7.00', 4480000, 4.0, 1, '2026-08-20 09:00:00'),

('44444444-0000-4000-8000-000000000010', 'kid-flex-tr90', 'VEW-D02', 'Kid Flex TR90', '33333333-0000-4000-8000-000000000006', 'Vin Eyewear', 'round', 'full-rim', 'tr90', 'Xanh biển', NULL, 'unisex', 'tre-em-an-toan', 'trẻ em, dẻo', 'Gọng TR90 bẻ ngược 180 độ không gãy, không có ốc nào để rơi. Kèm dây đeo sau gáy.', 'Gọng trẻ em dẻo, bẻ ngược không gãy, kèm dây đeo.', '{"Vật liệu":"TR90","Kích thước":"44-16-125","Trọng lượng":"10g"}', '["/assets/images/product-6.jpg"]', 990000, NULL, 340000, NULL, NULL, NULL, 30, 8, 'in_stock', 0, 1, 'visible', 'gong-can', 'Nhám', 'Không ốc', 'Liền thân', 10, 44, 16, 125, 'S', 'tron,vuong', NULL, NULL, '1.56,1.59', '-6.00', '-3.00', 'uv400,chong-tray', 0, 1, 0, NULL, NULL, 1, 1, 'don-trong', 'Ưu tiên phôi 1.59 polycarbonate cho trẻ', 1290000, 5.0, 1, '2026-07-15 09:00:00');

-- Biến thể: `label` do form ghép từ màu và cỡ, và uq_variant_label chặn hai
-- biến thể trùng nhãn trong một mặt hàng.
INSERT INTO `product_variants` (`id`, `product_id`, `label`, `color`, `size`, `sku`, `swatch_hex`, `image`, `price_delta`, `price`, `stock_quantity`, `is_active`, `position`) VALUES
('55555555-0000-4000-8000-000000000001', '44444444-0000-4000-8000-000000000001', 'Bạc mờ · M',    'Bạc mờ',    'M', 'VEW-G01-BM', '#C8CBD0', '/assets/images/product-1.jpg', 0,      NULL, 12, 1, 10),
('55555555-0000-4000-8000-000000000002', '44444444-0000-4000-8000-000000000001', 'Ghi khói · M',  'Ghi khói',  'M', 'VEW-G01-GK', '#6E7278', NULL,                          0,      NULL,  8, 1, 20),
('55555555-0000-4000-8000-000000000003', '44444444-0000-4000-8000-000000000001', 'Vàng hồng · L', 'Vàng hồng', 'L', 'VEW-G01-VH', '#C9A18A', NULL,                          200000, NULL,  4, 1, 30),
('55555555-0000-4000-8000-000000000004', '44444444-0000-4000-8000-000000000002', 'Nâu vân · S',   'Nâu vân',   'S', 'VEW-G02-NV', '#6B4423', '/assets/images/product-2.jpg', 0,      NULL, 10, 1, 10),
('55555555-0000-4000-8000-000000000005', '44444444-0000-4000-8000-000000000002', 'Đen bóng · M',  'Đen bóng',  'M', 'VEW-G02-DB', '#1A1A1A', NULL,                          0,      NULL,  5, 1, 20),
('55555555-0000-4000-8000-000000000006', '44444444-0000-4000-8000-000000000005', 'Hồng phấn · S', 'Hồng phấn', 'S', 'VEW-G05-HP', '#E58FA8', '/assets/images/product-5.jpg', 0,      NULL,  0, 1, 10),
('55555555-0000-4000-8000-000000000007', '44444444-0000-4000-8000-000000000006', 'Vàng · Xám khói', 'Vàng đồng', 'L', 'VEW-S01-VD', '#B08D57', '/assets/images/product-6.jpg', 0,   NULL,  7, 1, 10),
('55555555-0000-4000-8000-000000000008', '44444444-0000-4000-8000-000000000006', 'Bạc · Nâu trà',   'Bạc',       'L', 'VEW-S01-BN', '#BFC3C7', NULL,                       0,      NULL,  5, 1, 20),
('55555555-0000-4000-8000-000000000009', '44444444-0000-4000-8000-000000000009', 'Xám khói · M',  'Xám khói',  'M', 'VEW-D01-XK', '#585858', '/assets/images/product-1.jpg', 0,      NULL, 11, 1, 10),
-- Biến thể NGỪNG BÁN: ProductModel phải bỏ nó khỏi ô chọn mà không xoá dữ liệu
-- của những đơn đã mua nó.
('55555555-0000-4000-8000-000000000010', '44444444-0000-4000-8000-000000000009', 'Xanh rêu · L',  'Xanh rêu',  'L', 'VEW-D01-XR', '#4A5D3A', NULL,                          150000, NULL,  0, 0, 20);

-- Chờ hàng: gắn theo BIẾN THỂ khi mặt hàng có biến thể, để NULL khi không.
INSERT INTO `stock_waitlist` (`id`, `product_id`, `variant_id`, `user_id`, `email`, `phone`, `notified_at`, `created_at`) VALUES
('dddddddd-0000-4000-8000-000000000001', '44444444-0000-4000-8000-000000000005', '55555555-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000004', 'nguyenthuha@gmail.com',   '0912345678', NULL, '2026-09-01 09:12:00'),
('dddddddd-0000-4000-8000-000000000002', '44444444-0000-4000-8000-000000000005', '55555555-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000006', 'lephuonganh@gmail.com',   '0905112233', NULL, '2026-09-02 14:30:00'),
('dddddddd-0000-4000-8000-000000000003', '44444444-0000-4000-8000-000000000005', '55555555-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000008', 'vuhaiyen@gmail.com',      '0977335566', NULL, '2026-09-03 20:05:00'),
('dddddddd-0000-4000-8000-000000000004', '44444444-0000-4000-8000-000000000005', '55555555-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000009', NULL,                      '0966778899', NULL, '2026-09-04 08:44:00'),
('dddddddd-0000-4000-8000-000000000005', '44444444-0000-4000-8000-000000000007', NULL,                                  '11111111-0000-4000-8000-000000000005', 'tranminhquan@gmail.com',  '0987654321', NULL, '2026-09-01 17:20:00'),
('dddddddd-0000-4000-8000-000000000006', '44444444-0000-4000-8000-000000000007', NULL,                                  '11111111-0000-4000-8000-000000000007', 'phamduclong@gmail.com',   '0938222444', NULL, '2026-09-02 09:55:00'),
('dddddddd-0000-4000-8000-000000000007', '44444444-0000-4000-8000-000000000007', NULL,                                  '11111111-0000-4000-8000-000000000004', 'nguyenthuha@gmail.com',   '0912345678', NULL, '2026-09-05 11:02:00'),
('dddddddd-0000-4000-8000-000000000008', '44444444-0000-4000-8000-000000000007', NULL,                                  '11111111-0000-4000-8000-000000000010', 'hoangnamtrung@gmail.com', '0944556677', NULL, '2026-09-06 19:31:00'),
-- Hai lượt ĐÃ BÁO: cột notified_at là thứ tách "đang chờ" khỏi "đã gọi rồi".
('dddddddd-0000-4000-8000-000000000009', '44444444-0000-4000-8000-000000000004', NULL,                                  '11111111-0000-4000-8000-000000000006', 'lephuonganh@gmail.com',   '0905112233', '2026-08-28 10:15:00', '2026-08-20 10:00:00'),
-- Lượt chờ của một khách đã rời đi: fk_waitlist_user là SET NULL nên nhu cầu
-- thị trường vẫn còn đó, chỉ mất tên người.
('dddddddd-0000-4000-8000-000000000010', '44444444-0000-4000-8000-000000000004', NULL,                                  NULL,                                  'khachcu@gmail.com',       '0911222333', '2026-08-29 16:40:00', '2026-08-21 15:20:00');

-- ============================================================================
-- 4. ƯU ĐÃI, LỊCH HẸN, HỒ SƠ KHÚC XẠ
-- ============================================================================

-- `is_public` = 0 nghĩa là chỉ người đã được phát mới dùng được — ô nhập mã ở
-- giỏ hàng phải từ chối mã đó với khách vãng lai.
INSERT INTO `vouchers`
    (`id`, `code`, `tag`, `title`, `condition_text`, `discount_type`, `discount_value`,
     `min_order`, `max_discount`, `expires_at`, `is_active`, `is_public`, `max_uses`, `used_count`) VALUES
('22222222-0000-4000-8000-000000000001', 'CHAOBAN10',  '-10%', 'Giảm 10% đơn đầu tiên',            'Đơn từ 1.000.000đ',             'percent',  10,      1000000, 300000,  '2026-12-31', 1, 1, 500,  0),
('22222222-0000-4000-8000-000000000002', 'FREESHIP',   'FS',   'Miễn phí giao hàng',               'Đơn từ 500.000đ',               'shipping', 0,       500000,  NULL,    '2026-12-31', 1, 1, NULL, 1),
('22222222-0000-4000-8000-000000000003', 'GIAM100K',   '100K', 'Giảm ngay 100.000đ',               'Đơn từ 1.500.000đ',             'amount',   100000,  1500000, NULL,    '2026-10-31', 1, 1, 300,  1),
('22222222-0000-4000-8000-000000000004', 'SINHNHAT15', '-15%', 'Ưu đãi sinh nhật',                 'Dùng trong tháng sinh nhật',    'percent',  15,      0,       500000,  '2026-12-31', 1, 0, NULL, 0),
('22222222-0000-4000-8000-000000000005', 'TRONG20',    '-20%', 'Giảm 20% tiền cắt tròng',          'Khi mua kèm gọng',              'percent',  20,      2000000, 800000,  '2026-11-30', 1, 1, 200,  0),
('22222222-0000-4000-8000-000000000006', 'SVGIAM12',   '-12%', 'Ưu đãi học sinh sinh viên',        'Xuất trình thẻ tại cửa hàng',   'percent',  12,      0,       400000,  '2027-06-30', 1, 0, NULL, 0),
('22222222-0000-4000-8000-000000000007', 'VIP500K',    '500K', 'Giảm 500.000đ cho khách thân thiết','Đơn từ 4.000.000đ',            'amount',   500000,  4000000, NULL,    '2026-12-31', 1, 0, 50,   0),
('22222222-0000-4000-8000-000000000008', 'COMBO2',     '-18%', 'Mua 2 gọng giảm 18%',              'Áp dụng cho gọng cận',          'percent',  18,      3000000, 1000000, '2026-10-15', 1, 1, 100,  0),
-- Mã ĐÃ HẾT HẠN và mã ĐÃ TẮT: VoucherModel phải từ chối cả hai, mỗi cái vì một
-- lý do khác nhau.
('22222222-0000-4000-8000-000000000009', 'HE2026',     '-25%', 'Ưu đãi hè 2026',                   'Đơn từ 2.000.000đ',             'percent',  25,      2000000, 900000,  '2026-08-31', 1, 1, 400,  0),
('22222222-0000-4000-8000-000000000010', 'TET2027',    '-30%', 'Ưu đãi Tết 2027',                  'Chưa tới ngày áp dụng',         'percent',  30,      2000000, 1000000, '2027-02-28', 0, 1, 300,  0);

INSERT INTO `user_vouchers` (`user_id`, `voucher_id`, `used_at`, `granted_at`) VALUES
('11111111-0000-4000-8000-000000000004', '22222222-0000-4000-8000-000000000004', NULL,                  '2026-08-01 09:00:00'),
('11111111-0000-4000-8000-000000000004', '22222222-0000-4000-8000-000000000007', NULL,                  '2026-09-01 09:00:00'),
('11111111-0000-4000-8000-000000000005', '22222222-0000-4000-8000-000000000004', NULL,                  '2026-09-01 09:00:00'),
('11111111-0000-4000-8000-000000000005', '22222222-0000-4000-8000-000000000002', '2026-08-20 10:12:00', '2026-08-01 09:00:00'),
('11111111-0000-4000-8000-000000000006', '22222222-0000-4000-8000-000000000003', '2026-09-03 15:40:00', '2026-08-15 09:00:00'),
('11111111-0000-4000-8000-000000000006', '22222222-0000-4000-8000-000000000006', NULL,                  '2026-08-15 09:00:00'),
('11111111-0000-4000-8000-000000000007', '22222222-0000-4000-8000-000000000001', NULL,                  '2026-08-08 19:35:00'),
('11111111-0000-4000-8000-000000000008', '22222222-0000-4000-8000-000000000006', NULL,                  '2026-08-15 09:05:00'),
('11111111-0000-4000-8000-000000000009', '22222222-0000-4000-8000-000000000001', NULL,                  '2026-08-21 16:20:00'),
('11111111-0000-4000-8000-000000000010', '22222222-0000-4000-8000-000000000004', NULL,                  '2026-08-25 11:05:00');

-- Lịch hẹn CHỈ CÓ NGÀY, không có giờ — giờ thống nhất qua cuộc gọi xác nhận.
-- service_type lấy đúng bốn chuỗi của BookingModel::SERVICES.
INSERT INTO `appointments` (`id`, `code`, `user_id`, `store_id`, `appointment_date`, `service_type`, `full_name`, `phone`, `note`, `status`, `created_at`) VALUES
('88888888-0000-4000-8000-000000000001', 'LH-260830-4A1C', '11111111-0000-4000-8000-000000000004', @store_tayho,                          '2026-08-30', 'Đo mắt cận/loạn',         'Nguyễn Thu Hà',   '0912345678', 'Đo lại sau 1 năm',                 'done',      '2026-08-27 10:00:00'),
('88888888-0000-4000-8000-000000000002', 'LH-260902-7B22', '11111111-0000-4000-8000-000000000008', @store_tayho,                          '2026-09-02', 'Đo mắt cận/loạn',         'Vũ Hải Yến',      '0977335566', 'Cận nặng, muốn tư vấn tròng mỏng', 'done',      '2026-08-31 09:20:00'),
('88888888-0000-4000-8000-000000000003', 'LH-260909-9C33', '11111111-0000-4000-8000-000000000005', '77777777-0000-4000-8000-000000000003', '2026-09-09', 'Tư vấn & Thử gọng',       'Trần Minh Quân',  '0987654321', NULL,                               'confirmed', '2026-09-05 11:10:00'),
('88888888-0000-4000-8000-000000000004', 'LH-260910-1D44', '11111111-0000-4000-8000-000000000006', '77777777-0000-4000-8000-000000000004', '2026-09-10', 'Cắt tròng lấy liền',      'Lê Phương Anh',   '0905112233', 'Đến sau 17h',                      'confirmed', '2026-09-06 08:30:00'),
('88888888-0000-4000-8000-000000000005', 'LH-260911-3E55', '11111111-0000-4000-8000-000000000007', '77777777-0000-4000-8000-000000000005', '2026-09-11', 'Bảo hành / Vệ sinh kính', 'Phạm Đức Long',   '0938222444', 'Gọng lỏng bản lề',                 'confirmed', '2026-09-07 19:40:00'),
('88888888-0000-4000-8000-000000000006', 'LH-260912-5F66', '11111111-0000-4000-8000-000000000009', '77777777-0000-4000-8000-000000000006', '2026-09-12', 'Đo mắt cận/loạn',         'Đỗ Minh Châu',    '0966778899', NULL,                               'pending',   '2026-09-08 06:15:00'),
-- Khách VÃNG LAI: user_id NULL, đặt lịch không cần tài khoản.
('88888888-0000-4000-8000-000000000007', 'LH-260913-7A77', NULL,                                  @store_longbien,                       '2026-09-13', 'Đo mắt cận/loạn',         'Bùi Thanh Vân',   '0911222333', 'Đo cho con 8 tuổi',                'pending',   '2026-09-08 07:05:00'),
('88888888-0000-4000-8000-000000000008', 'LH-260914-9B88', NULL,                                  '77777777-0000-4000-8000-000000000007', '2026-09-14', 'Tư vấn & Thử gọng',       'Ngô Gia Bảo',     '0922334455', NULL,                               'pending',   '2026-09-08 08:02:00'),
('88888888-0000-4000-8000-000000000009', 'LH-260905-2C99', '11111111-0000-4000-8000-000000000010', '77777777-0000-4000-8000-000000000009', '2026-09-05', 'Cắt tròng lấy liền',      'Hoàng Nam Trung', '0944556677', 'Khách báo bận',                    'cancelled', '2026-09-02 14:00:00'),
('88888888-0000-4000-8000-000000000010', 'LH-260906-4DAA', NULL,                                  @store_longbien,                       '2026-09-06', 'Bảo hành / Vệ sinh kính', 'Trịnh Khánh Linh','0933445566', 'Không tới, không liên lạc được',   'cancelled', '2026-09-03 16:30:00');

-- ----------------------------------------------------------------------------
-- HỒ SƠ KHÚC XẠ — sổ CHỈ-THÊM (X21). Sửa một bản ghi là CHÈN phiên bản mới,
-- bản cũ giữ nguyên: `ban_goc_id` nối các phiên bản của cùng một lần đo và bản
-- đầu tiên tự trỏ vào chính nó.
--
-- `source` phân biệt số kỹ thuật viên đo với số khách tự khai — hai thứ không
-- được trộn (CLAUDE.md A1).
-- ----------------------------------------------------------------------------
INSERT INTO `customer_prescriptions`
    (`id`, `user_id`, `appointment_id`, `source`, `od_sph`, `od_cyl`, `od_axis`, `od_va`, `od_va_num`,
     `os_sph`, `os_cyl`, `os_axis`, `os_va`, `os_va_num`, `pd`, `pd_od`, `pd_os`,
     `od_add`, `os_add`, `od_seg_height`, `os_seg_height`, `measured_at`, `store_id`,
     `note`, `tech_note`, `ly_do`, `ban_goc_id`, `phien_ban`, `created_by`, `created_at`) VALUES
-- Nguyễn Thu Hà: lần đo 2025 -> lần đo 2026 (2 phiên bản, bản 2 sửa trục loạn).
('eeeeeeee-0000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000004', NULL,                                  'store',    -2.75, -0.50, 180, '10/10', 1.00, -3.00, -0.75,   5, '10/10', 1.00, 62.0, 31.0, 31.0, NULL, NULL, NULL, NULL, '2025-09-02', @store_tayho, 'Độ tăng nhẹ so với năm trước', 'Đo bằng máy Topcon, khách đeo kính cũ 2 năm', NULL, 'eeeeeeee-0000-4000-8000-000000000001', 1, '11111111-0000-4000-8000-000000000003', '2025-09-02 10:30:00'),
('eeeeeeee-0000-4000-8000-000000000002', '11111111-0000-4000-8000-000000000004', '88888888-0000-4000-8000-000000000001', 'store',    -3.25, -0.75, 170, '10/10', 1.00, -3.50, -1.00,  10, '9/10',  0.90, 62.0, 31.0, 31.0, NULL, NULL, NULL, NULL, '2026-08-30', @store_tayho, 'Tăng 0.50 diop sau một năm', 'Khách làm máy tính 9 tiếng/ngày, tư vấn lọc ánh sáng xanh', NULL, 'eeeeeeee-0000-4000-8000-000000000002', 1, '11111111-0000-4000-8000-000000000003', '2026-08-30 11:05:00'),
('eeeeeeee-0000-4000-8000-000000000003', '11111111-0000-4000-8000-000000000004', '88888888-0000-4000-8000-000000000001', 'store',    -3.25, -0.75, 175, '10/10', 1.00, -3.50, -1.00,   5, '9/10',  0.90, 62.0, 31.0, 31.0, NULL, NULL, NULL, NULL, '2026-08-30', @store_tayho, 'Tăng 0.50 diop sau một năm', 'Khách làm máy tính 9 tiếng/ngày, tư vấn lọc ánh sáng xanh', 'Ghi nhầm trục loạn hai mắt, đối chiếu lại phiếu máy đo', 'eeeeeeee-0000-4000-8000-000000000002', 2, '11111111-0000-4000-8000-000000000003', '2026-08-30 15:40:00'),
-- Trần Minh Quân: một bản khách tự khai lúc mua hàng, một bản cửa hàng đo lại.
('eeeeeeee-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000005', NULL,                                  'customer', -5.50, -1.00,  20, '9/10',  0.90, -5.75, -1.25, 160, '9/10',  0.90, 64.5, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-05', NULL,         'Khách đọc từ toa cũ',       NULL, NULL, 'eeeeeeee-0000-4000-8000-000000000004', 1, NULL,                                  '2026-07-05 21:10:00'),
('eeeeeeee-0000-4000-8000-000000000005', '11111111-0000-4000-8000-000000000005', NULL,                                  'store',    -5.75, -1.25,  15, '9/10',  0.90, -6.00, -1.50, 165, '9/10',  0.90, 64.5, 32.0, 32.5, NULL, NULL, NULL, NULL, '2026-08-18', @store_longbien, 'Lệch so với toa khách khai', 'Chênh 0.25 so với số khách tự khai — dùng số đo tại cửa hàng', NULL, 'eeeeeeee-0000-4000-8000-000000000005', 1, '11111111-0000-4000-8000-000000000003', '2026-08-18 16:20:00'),
-- Lê Phương Anh: toa bệnh viện mang từ ngoài vào.
('eeeeeeee-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000006', NULL,                                  'external', -1.50, -0.25,  90, '10/10', 1.00, -1.75, -0.50,  85, '10/10', 1.00, 60.0, 30.0, 30.0, NULL, NULL, NULL, NULL, '2026-08-25', NULL,         'Toa Bệnh viện Mắt Trung ương', 'Khách mang toa giấy, đã chụp lưu hồ sơ', NULL, 'eeeeeeee-0000-4000-8000-000000000006', 1, '11111111-0000-4000-8000-000000000002', '2026-08-26 09:15:00'),
-- Phạm Đức Long: lão thị — có ADD và chiều cao tâm tròng cho đa tròng.
('eeeeeeee-0000-4000-8000-000000000007', '11111111-0000-4000-8000-000000000007', NULL,                                  'store',    -0.50,  NULL,NULL, '10/10', 1.00, -0.75,  NULL,NULL, '10/10', 1.00, 66.0, 33.0, 33.0, 1.75, 1.75, 18.0, 18.0, '2026-08-12', '77777777-0000-4000-8000-000000000005', 'Bắt đầu lão thị', 'Tư vấn đa tròng, khách còn cân nhắc', NULL, 'eeeeeeee-0000-4000-8000-000000000007', 1, '11111111-0000-4000-8000-000000000003', '2026-08-12 14:00:00'),
-- Vũ Hải Yến: cận nặng, hai phiên bản của cùng lần đo.
('eeeeeeee-0000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000008', '88888888-0000-4000-8000-000000000002', 'store',    -7.00, -2.00,  20, '8/10',  0.80, -7.25, -2.25, 160, '8/10',  0.80, 61.0, 30.5, 30.5, NULL, NULL, NULL, NULL, '2026-09-02', @store_tayho, 'Cận nặng, nên dùng 1.74', 'Đo hai lần cách nhau 10 phút, kết quả trùng', NULL, 'eeeeeeee-0000-4000-8000-000000000008', 1, '11111111-0000-4000-8000-000000000003', '2026-09-02 10:40:00'),
('eeeeeeee-0000-4000-8000-000000000009', '11111111-0000-4000-8000-000000000008', '88888888-0000-4000-8000-000000000002', 'store',    -7.00, -2.00,  20, '8/10',  0.80, -7.25, -2.25, 160, '8/10',  0.80, 61.0, 30.5, 30.5, NULL, NULL, NULL, NULL, '2026-09-02', @store_tayho, 'Cận nặng, nên dùng 1.74', 'Bổ sung PD từng mắt sau khi đo lại bằng thước đồng tử', 'Thiếu PD từng mắt ở bản đầu, máy mài cần số này', 'eeeeeeee-0000-4000-8000-000000000008', 2, '11111111-0000-4000-8000-000000000003', '2026-09-02 11:20:00'),
('eeeeeeee-0000-4000-8000-000000000010', '11111111-0000-4000-8000-000000000009', NULL,                                  'customer', -2.00, -0.50, 100, '10/10', 1.00, -2.00, -0.50,  80, '10/10', 1.00, 63.5, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28', NULL,         'Khách tự khai khi mua online', NULL, NULL, 'eeeeeeee-0000-4000-8000-000000000010', 1, NULL, '2026-07-28 20:05:00');

-- ============================================================================
-- 5. ĐƠN HÀNG
--
-- Mười đơn trải đủ sáu trạng thái vòng đời và cả ba nấc tiền (unpaid ·
-- deposit_paid · paid). Bốn đơn cuối đã huỷ MÀ CỬA HÀNG ĐANG GIỮ TIỀN — đó là
-- điều kiện duy nhất sinh ra một yêu cầu hoàn tiền, nên refund_requests có 4
-- dòng chứ không 10 (xem đầu file).
--
-- subtotal + shipping_fee − discount = total, và line_total = unit_price ×
-- quantity. Tiền tròng ĐÃ NẰM TRONG unit_price; `lens_price` chỉ tách ra để in.
-- ============================================================================
INSERT INTO `orders`
    (`id`, `code`, `user_id`, `customer_name`, `customer_phone`, `customer_email`,
     `shipping_address`, `delivery_method`, `store_id`, `payment_method`, `payment_status`,
     `paid_at`, `mai_bat_dau_luc`, `mai_bat_dau_boi`, `note`,
     `subtotal`, `shipping_fee`, `discount`, `voucher_id`, `total`,
     `deposit_amount`, `deposit_rate`, `status`, `cancelled_by`, `created_at`) VALUES
('99999999-0000-4000-8000-000000000001', 'DH-260901-A1B2', '11111111-0000-4000-8000-000000000004', 'Nguyễn Thu Hà',   '0912345678', 'nguyenthuha@gmail.com',   'Số 12 ngõ 45 Trần Duy Hưng, Phường Yên Hòa, Thành phố Hà Nội', 'shipping', NULL,                                  'cod',           'paid',         '2026-09-04 16:20:00', '2026-09-01 15:00:00', '11111111-0000-4000-8000-000000000003', 'Giao giờ hành chính', 3740000, 0,     0,      NULL,                                   3740000, 1122000, 30, 'completed', NULL,       '2026-09-01 09:12:00'),
('99999999-0000-4000-8000-000000000002', 'DH-260902-C3D4', '11111111-0000-4000-8000-000000000005', 'Trần Minh Quân',  '0987654321', 'tranminhquan@gmail.com',  NULL,                                                           'pickup',   '77777777-0000-4000-8000-000000000003', 'bank_transfer', 'paid',         '2026-09-02 14:05:00', NULL,                  NULL,                                   NULL,                  2690000, 0,     0,      NULL,                                   2690000, 0,       0,  'shipping',  NULL,       '2026-09-02 13:40:00'),
('99999999-0000-4000-8000-000000000003', 'DH-260903-E5F6', '11111111-0000-4000-8000-000000000006', 'Lê Phương Anh',   '0905112233', 'lephuonganh@gmail.com',   '77 Hàng Bài, Phường Hoàn Kiếm, Thành phố Hà Nội',              'shipping', NULL,                                  'bank_transfer', 'deposit_paid', NULL,                  NULL,                  NULL,                                   'Bảo vệ nhận giúp',    2480000, 30000, 100000, '22222222-0000-4000-8000-000000000003', 2410000, 723000,  30, 'preparing', NULL,       '2026-09-03 15:38:00'),
('99999999-0000-4000-8000-000000000004', 'DH-260904-1122', '11111111-0000-4000-8000-000000000007', 'Phạm Đức Long',   '0938222444', 'phamduclong@gmail.com',   '145 Tây Sơn, Phường Đống Đa, Thành phố Hà Nội',                'shipping', NULL,                                  'cod',           'unpaid',       NULL,                  NULL,                  NULL,                                   NULL,                  2890000, 30000, 0,      NULL,                                   2920000, 0,       0,  'confirmed', NULL,       '2026-09-04 20:15:00'),
('99999999-0000-4000-8000-000000000005', 'DH-260905-3344', '11111111-0000-4000-8000-000000000008', 'Vũ Hải Yến',      '0977335566', 'vuhaiyen@gmail.com',      NULL,                                                           'pickup',   '77777777-0000-4000-8000-000000000006', 'cod',           'unpaid',       NULL,                  NULL,                  NULL,                                   'Lấy tại Hà Đông',     2490000, 0,     0,      NULL,                                   2490000, 747000,  30, 'new',       NULL,       '2026-09-05 08:44:00'),
('99999999-0000-4000-8000-000000000006', 'DH-260820-5566', '11111111-0000-4000-8000-000000000009', 'Đỗ Minh Châu',    '0966778899', NULL,                      '52 Ngô Xuân Quảng, Xã Gia Lâm, Thành phố Hà Nội',              'shipping', NULL,                                  'bank_transfer', 'paid',         '2026-08-20 10:14:00', NULL,                  NULL,                                   'Mua cho hai cháu',    1980000, 30000, 30000,  '22222222-0000-4000-8000-000000000002', 1980000, 0,       0,  'completed', NULL,       '2026-08-20 09:50:00'),
('99999999-0000-4000-8000-000000000007', 'DH-260825-7788', '11111111-0000-4000-8000-000000000004', 'Nguyễn Thu Hà',   '0912345678', 'nguyenthuha@gmail.com',   'Số 12 ngõ 45 Trần Duy Hưng, Phường Yên Hòa, Thành phố Hà Nội', 'shipping', NULL,                                  'bank_transfer', 'paid',         '2026-08-25 11:02:00', NULL,                  NULL,                                   'Khách đổi ý, chưa mài', 2290000, 30000, 0,    NULL,                                   2320000, 0,       0,  'cancelled', 'customer', '2026-08-25 10:40:00'),
('99999999-0000-4000-8000-000000000008', 'DH-260826-99AA', '11111111-0000-4000-8000-000000000005', 'Trần Minh Quân',  '0987654321', 'tranminhquan@gmail.com',  'Số 8 Lê Trọng Tấn, Phường Khương Đình, Thành phố Hà Nội',      'shipping', NULL,                                  'bank_transfer', 'deposit_paid', NULL,                  '2026-08-27 09:30:00', '11111111-0000-4000-8000-000000000003', 'Đã mài tròng rồi mới huỷ', 5580000, 0, 0,   NULL,                                   5580000, 1674000, 30, 'cancelled', 'customer', '2026-08-26 16:20:00'),
('99999999-0000-4000-8000-000000000009', 'DH-260828-BBCC', '11111111-0000-4000-8000-000000000006', 'Lê Phương Anh',   '0905112233', 'lephuonganh@gmail.com',   NULL,                                                           'pickup',   @store_tayho,                          'bank_transfer', 'deposit_paid', NULL,                  NULL,                  NULL,                                   'Huỷ trước khi mài',   3380000, 0,     0,      NULL,                                   3380000, 1014000, 30, 'cancelled', 'customer', '2026-08-28 09:05:00'),
('99999999-0000-4000-8000-000000000010', 'DH-260830-DDEE', '11111111-0000-4000-8000-000000000007', 'Phạm Đức Long',   '0938222444', 'phamduclong@gmail.com',   '145 Tây Sơn, Phường Đống Đa, Thành phố Hà Nội',                'shipping', NULL,                                  'bank_transfer', 'paid',         '2026-08-30 12:10:00', NULL,                  NULL,                                   'Hết hàng màu khách đặt — lỗi cửa hàng', 3290000, 0, 0, NULL,                            3290000, 0,       0,  'cancelled', 'staff',    '2026-08-30 11:50:00');

INSERT INTO `order_items`
    (`id`, `order_id`, `product_id`, `variant_id`, `variant_label`,
     `lens_id`, `lens_type`, `lens_name`, `lens_price`, `prescription`, `prescription_id`,
     `product_name`, `unit_price`, `cost_price`, `quantity`, `line_total`) VALUES
('aaaaaaaa-0000-4000-8000-000000000001', '99999999-0000-4000-8000-000000000001', '44444444-0000-4000-8000-000000000001', '55555555-0000-4000-8000-000000000001', 'Bạc mờ · M',    'clear-156', 'don-trong', 'Đơn tròng · Tròng trắng 1.56',      450000,  'OD -3.25/-0.75x175 · OS -3.50/-1.00x5 · PD 62.0', 'eeeeeeee-0000-4000-8000-000000000003', 'Aurora Titan Vuông',       3740000, 1450000, 1, 3740000),
('aaaaaaaa-0000-4000-8000-000000000002', '99999999-0000-4000-8000-000000000002', '44444444-0000-4000-8000-000000000006', '55555555-0000-4000-8000-000000000007', 'Vàng · Xám khói', NULL,      NULL,        NULL,                                0,       NULL,                                              NULL,                                   'Solis Phi Công Phân Cực',  2690000, 1150000, 1, 2690000),
('aaaaaaaa-0000-4000-8000-000000000003', '99999999-0000-4000-8000-000000000003', '44444444-0000-4000-8000-000000000002', '55555555-0000-4000-8000-000000000004', 'Nâu vân · S',   'blue-161',  'don-trong', 'Đơn tròng · Chống sáng xanh 1.61',  890000,  'OD -1.50/-0.25x90 · OS -1.75/-0.50x85 · PD 60.0', 'eeeeeeee-0000-4000-8000-000000000006', 'Hanoi Acetate Tròn',       2480000, 780000,  1, 2480000),
('aaaaaaaa-0000-4000-8000-000000000004', '99999999-0000-4000-8000-000000000004', '44444444-0000-4000-8000-000000000009', '55555555-0000-4000-8000-000000000009', 'Xám khói · M',  NULL,        NULL,        NULL,                                0,       NULL,                                              NULL,                                   'Chroma Đổi Màu Đa Dụng',   2890000, 1240000, 1, 2890000),
-- Khách CHƯA BIẾT ĐỘ, hẹn đo tại cửa hàng: `prescription` NULL là trường hợp
-- thật chứ không phải dữ liệu thiếu.
('aaaaaaaa-0000-4000-8000-000000000005', '99999999-0000-4000-8000-000000000005', '44444444-0000-4000-8000-000000000003', NULL,                                  NULL,            'clear-150', 'don-trong', 'Đơn tròng · Tròng trắng 1.50',      300000,  NULL,                                              NULL,                                   'Meta Browline Nâu Vân',    2490000, 950000,  1, 2490000),
('aaaaaaaa-0000-4000-8000-000000000006', '99999999-0000-4000-8000-000000000006', '44444444-0000-4000-8000-000000000010', NULL,                                  NULL,            NULL,        NULL,        NULL,                                0,       NULL,                                              NULL,                                   'Kid Flex TR90',            990000,  340000,  2, 1980000),
('aaaaaaaa-0000-4000-8000-000000000007', '99999999-0000-4000-8000-000000000007', '44444444-0000-4000-8000-000000000008', NULL,                                  NULL,            NULL,        NULL,        NULL,                                0,       NULL,                                              NULL,                                   'Marine Oval Gradient',     2290000, 980000,  1, 2290000),
('aaaaaaaa-0000-4000-8000-000000000008', '99999999-0000-4000-8000-000000000008', '44444444-0000-4000-8000-000000000004', NULL,                                  NULL,            'clear-167', 'don-trong', 'Đơn tròng · Tròng trắng 1.67',      1290000, 'OD -5.75/-1.25x15 · OS -6.00/-1.50x165 · PD 64.5', 'eeeeeeee-0000-4000-8000-000000000005', 'Lumen Rimless Titan',      5580000, 2100000, 1, 5580000),
('aaaaaaaa-0000-4000-8000-000000000009', '99999999-0000-4000-8000-000000000009', '44444444-0000-4000-8000-000000000003', NULL,                                  NULL,            'photo-156', 'don-trong', 'Đơn tròng · Đổi màu Photochromic 1.56', 1190000, 'OD -1.50/-0.25x90 · OS -1.75/-0.50x85 · PD 60.0', 'eeeeeeee-0000-4000-8000-000000000006', 'Meta Browline Nâu Vân', 3380000, 950000, 1, 3380000),
('aaaaaaaa-0000-4000-8000-000000000010', '99999999-0000-4000-8000-000000000010', '44444444-0000-4000-8000-000000000001', '55555555-0000-4000-8000-000000000002', 'Ghi khói · M',  NULL,        NULL,        NULL,                                0,       NULL,                                              NULL,                                   'Aurora Titan Vuông',       3290000, 1450000, 1, 3290000);

-- Một dòng cho MỐC HIỆN TẠI của mỗi đơn. Đơn thật có nhiều dòng hơn (mỗi lần
-- đổi trạng thái một dòng); ở đây giữ một dòng để tổng vẫn là 10.
-- `changed_by` NULL = hệ thống ghi lúc đặt hàng.
INSERT INTO `order_status_history` (`id`, `order_id`, `status`, `changed_by`, `ly_do`, `created_at`) VALUES
('bbbbbbbb-0000-4000-8000-000000000001', '99999999-0000-4000-8000-000000000001', 'completed', '11111111-0000-4000-8000-000000000002', NULL,                                        '2026-09-04 16:25:00'),
('bbbbbbbb-0000-4000-8000-000000000002', '99999999-0000-4000-8000-000000000002', 'shipping',  '11111111-0000-4000-8000-000000000002', NULL,                                        '2026-09-06 10:00:00'),
('bbbbbbbb-0000-4000-8000-000000000003', '99999999-0000-4000-8000-000000000003', 'preparing', '11111111-0000-4000-8000-000000000003', NULL,                                        '2026-09-04 08:30:00'),
('bbbbbbbb-0000-4000-8000-000000000004', '99999999-0000-4000-8000-000000000004', 'confirmed', '11111111-0000-4000-8000-000000000002', NULL,                                        '2026-09-05 09:10:00'),
('bbbbbbbb-0000-4000-8000-000000000005', '99999999-0000-4000-8000-000000000005', 'new',       NULL,                                  NULL,                                        '2026-09-05 08:44:00'),
('bbbbbbbb-0000-4000-8000-000000000006', '99999999-0000-4000-8000-000000000006', 'completed', '11111111-0000-4000-8000-000000000002', NULL,                                        '2026-08-22 15:40:00'),
('bbbbbbbb-0000-4000-8000-000000000007', '99999999-0000-4000-8000-000000000007', 'cancelled', NULL,                                  'Khách tự huỷ trong trang tài khoản',        '2026-08-25 11:30:00'),
('bbbbbbbb-0000-4000-8000-000000000008', '99999999-0000-4000-8000-000000000008', 'cancelled', '11111111-0000-4000-8000-000000000002', 'Khách báo huỷ qua điện thoại, tròng đã mài', '2026-08-27 14:10:00'),
('bbbbbbbb-0000-4000-8000-000000000009', '99999999-0000-4000-8000-000000000009', 'cancelled', NULL,                                  'Khách tự huỷ, chưa bắt đầu mài',            '2026-08-28 10:20:00'),
('bbbbbbbb-0000-4000-8000-000000000010', '99999999-0000-4000-8000-000000000010', 'cancelled', '11111111-0000-4000-8000-000000000001', 'Hết hàng màu khách đặt, cửa hàng huỷ và hoàn tiền', '2026-08-30 14:00:00');

-- ----------------------------------------------------------------------------
-- HOÀN TIỀN CỌC — bốn đơn huỷ mà cửa hàng đang giữ tiền, đủ cả bốn trạng thái.
--
--   suggested_amount = received_amount − lens_amount, chặn sàn 0.
--   approved_amount khác suggested thì decision_note BẮT BUỘC (BR-DH-14.6) —
--   dòng thứ ba dưới đây là đúng ca đó: huỷ TRƯỚC khi mài nên hoàn 100% dù
--   công thức trừ tiền tròng ra đề nghị số 0.
-- ----------------------------------------------------------------------------
INSERT INTO `refund_requests`
    (`id`, `order_id`, `received_amount`, `lens_amount`, `suggested_amount`, `approved_amount`,
     `shop_fault`, `lens_started`, `status`, `decision_note`, `decided_by`, `decided_at`,
     `refunded_on`, `refund_note`, `created_at`) VALUES
('19191919-0000-4000-8000-000000000001', '99999999-0000-4000-8000-000000000007', 2320000, 0,       2320000, 2320000, 0, 0, 'refunded', NULL,                                                                 '11111111-0000-4000-8000-000000000001', '2026-08-26 09:00:00', '2026-08-27', 'Chuyển khoản Vietcombank, mã GD 8827104', '2026-08-25 11:35:00'),
('19191919-0000-4000-8000-000000000002', '99999999-0000-4000-8000-000000000008', 1674000, 1290000, 384000,  384000,  0, 1, 'approved', NULL,                                                                 '11111111-0000-4000-8000-000000000001', '2026-08-28 08:30:00', NULL,         NULL,                                      '2026-08-27 14:15:00'),
('19191919-0000-4000-8000-000000000003', '99999999-0000-4000-8000-000000000009', 1014000, 1190000, 0,       1014000, 0, 0, 'refunded', 'Huỷ trước khi bấm Bắt đầu mài nên hoàn 100% tiền đã nhận (Q52.1)',  '11111111-0000-4000-8000-000000000001', '2026-08-29 09:15:00', '2026-08-29', 'Chuyển khoản Techcombank, mã GD 5510992', '2026-08-28 10:25:00'),
('19191919-0000-4000-8000-000000000004', '99999999-0000-4000-8000-000000000010', 3290000, 0,       3290000, NULL,    1, 0, 'pending',  NULL,                                                                 NULL,                                   NULL,                  NULL,         NULL,                                      '2026-08-30 14:05:00');

-- ----------------------------------------------------------------------------
-- SỔ GIAO DỊCH SEPAY
--
-- `applied` trả lời "một mình khoản này đủ tới đâu", KHÔNG trả lời "đơn đang ở
-- đâu" — hai dòng 'partial' cộng lại mới thành đủ. Hai dòng cuối là khoản
-- KHÔNG khớp đơn nào (khách gõ sai nội dung), và chúng vẫn phải được ghi.
-- ----------------------------------------------------------------------------
INSERT INTO `sepay_transactions`
    (`id`, `sepay_id`, `order_id`, `order_code`, `gateway`, `account_number`, `transfer_type`,
     `amount`, `content`, `reference_code`, `transaction_date`, `applied`, `gan_boi`, `gan_luc`, `created_at`) VALUES
('17171717-0000-4000-8000-000000000001', 8800101, '99999999-0000-4000-8000-000000000002', 'DH-260902-C3D4', 'Vietcombank',  '0011000123456', 'in', 2690000, 'DH260902C3D4 TRAN MINH QUAN chuyen khoan',      'FT26090211223', '2026-09-02 14:05:00', 'paid',         NULL, NULL, '2026-09-02 14:05:10'),
('17171717-0000-4000-8000-000000000002', 8800102, '99999999-0000-4000-8000-000000000003', 'DH-260903-E5F6', 'Techcombank',  '1903 8888 9999', 'in', 723000, 'CT tu 0905112233 DH260903E5F6 dat coc',        'FT26090333441', '2026-09-03 15:52:00', 'deposit_paid', NULL, NULL, '2026-09-03 15:52:08'),
('17171717-0000-4000-8000-000000000003', 8800103, '99999999-0000-4000-8000-000000000006', 'DH-260820-5566', 'MB Bank',      '0011000123456', 'in', 1980000, 'DH2608205566 DO MINH CHAU',                    'FT26082055661', '2026-08-20 10:14:00', 'paid',         NULL, NULL, '2026-08-20 10:14:05'),
('17171717-0000-4000-8000-000000000004', 8800104, '99999999-0000-4000-8000-000000000007', 'DH-260825-7788', 'Vietcombank',  '0011000123456', 'in', 2320000, 'DH260825 7788 NGUYEN THU HA thanh toan',       'FT26082577881', '2026-08-25 11:02:00', 'paid',         NULL, NULL, '2026-08-25 11:02:12'),
('17171717-0000-4000-8000-000000000005', 8800105, '99999999-0000-4000-8000-000000000008', 'DH-260826-99AA', 'Techcombank',  '1903 8888 9999', 'in', 1674000, 'DH26082699AA coc 30%',                        'FT26082699AA1', '2026-08-26 16:35:00', 'deposit_paid', NULL, NULL, '2026-08-26 16:35:04'),
('17171717-0000-4000-8000-000000000006', 8800106, '99999999-0000-4000-8000-000000000009', 'DH-260828-BBCC', 'MB Bank',      '0011000123456', 'in', 1014000, 'LE PHUONG ANH DH260828BBCC',                   'FT26082800112', '2026-08-28 09:20:00', 'deposit_paid', NULL, NULL, '2026-08-28 09:20:07'),
('17171717-0000-4000-8000-000000000007', 8800107, '99999999-0000-4000-8000-000000000010', 'DH-260830-DDEE', 'Vietcombank',  '0011000123456', 'in', 3290000, 'DH260830DDEE PHAM DUC LONG',                   'FT26083000998', '2026-08-30 12:10:00', 'paid',         NULL, NULL, '2026-08-30 12:10:09'),
-- Khoản GẮN TAY: khách gõ thiếu mã đơn, nhân viên đối chiếu rồi gắn vào đơn.
('17171717-0000-4000-8000-000000000008', 8800108, '99999999-0000-4000-8000-000000000003', 'DH-260903-E5F6', 'Techcombank',  '1903 8888 9999', 'in', 200000,  'LE PHUONG ANH chuyen tien',                    'FT26090400556', '2026-09-04 08:10:00', 'partial',      '11111111-0000-4000-8000-000000000002', '2026-09-04 09:02:00', '2026-09-04 08:10:03'),
('17171717-0000-4000-8000-000000000009', 8800109, NULL,                                   NULL,             'MB Bank',      '0011000123456', 'in', 500000,  'CHUYEN TIEN MUA KINH',                          'FT26090512345', '2026-09-05 19:22:00', 'no_order',     NULL, NULL, '2026-09-05 19:22:11'),
('17171717-0000-4000-8000-000000000010', 8800110, NULL,                                   NULL,             'Vietcombank',  '0011000123456', 'in', 1000000, 'DH26099999 khong ro',                           'FT26090688776', '2026-09-06 08:41:00', 'no_order',     NULL, NULL, '2026-09-06 08:41:06');

-- ============================================================================
-- 6. ĐÁNH GIÁ, LIÊN HỆ, NHẬT KÝ
-- ============================================================================

-- `products`.`rating` và `review_count` đã được điền SẴN ở mục 3 cho khớp tám
-- dòng 'published' dưới đây (ReviewModel::recount() cho ra đúng các số ấy).
-- Sửa đánh giá ở đây thì phải sửa cả hai cột kia, hoặc bấm duyệt lại một đánh
-- giá bất kỳ trong khu quản trị để model tự tính lại.
--
-- uq_review_order_product: mỗi đơn đánh giá mỗi mặt hàng đúng một lần. Dòng có
-- `order_id` là đánh giá "Đã mua"; dòng NULL là khách viết không kèm đơn.
INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `order_id`, `author_name`, `rating`, `body`, `reply`, `replied_at`, `variant_label`, `status`, `created_at`) VALUES
('cccccccc-0000-4000-8000-000000000001', '44444444-0000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000004', '99999999-0000-4000-8000-000000000001', 'Nguyễn Thu Hà',    5, 'Nhẹ thật, đeo cả ngày làm việc không thấy cấn tai. Cắt tròng lọc ánh sáng xanh xong nhìn màn hình dễ chịu hơn hẳn.', 'Cảm ơn chị Hà. Chị ghé cửa hàng cân lại gọng miễn phí sau 3 tháng nhé.', '2026-09-06 09:00:00', 'Bạc mờ · M',   'published', '2026-09-05 20:14:00'),
('cccccccc-0000-4000-8000-000000000002', '44444444-0000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000005', NULL,                                  'Trần Minh Quân',   4, 'Gọng đẹp và nhẹ, chỉ tiếc đệm mũi hơi thấp với sống mũi mình.', NULL, NULL, NULL, 'published', '2026-09-06 11:22:00'),
('cccccccc-0000-4000-8000-000000000003', '44444444-0000-4000-8000-000000000002', '11111111-0000-4000-8000-000000000006', NULL,                                  'Lê Phương Anh',    5, 'Vân acetate đẹp hơn ảnh. Nhân viên chỉnh gọng cho vừa mặt ngay tại quầy.', NULL, NULL, 'Nâu vân · S', 'published', '2026-09-04 18:40:00'),
('cccccccc-0000-4000-8000-000000000004', '44444444-0000-4000-8000-000000000003', '11111111-0000-4000-8000-000000000008', NULL,                                  'Vũ Hải Yến',       4, 'Nửa gọng nên nhẹ, nhưng dây cước cần cẩn thận khi lau.', NULL, NULL, NULL, 'published', '2026-09-02 08:15:00'),
('cccccccc-0000-4000-8000-000000000005', '44444444-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000005', '99999999-0000-4000-8000-000000000002', 'Trần Minh Quân',   5, 'Phân cực ăn tiền khi lái xe buổi trưa, hết chói mặt đường.', 'Cảm ơn anh Quân đã tin chọn Vin Eyewear.', '2026-09-07 08:30:00', 'Vàng · Xám khói', 'published', '2026-09-06 21:05:00'),
('cccccccc-0000-4000-8000-000000000006', '44444444-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000007', NULL,                                  'Phạm Đức Long',    5, 'Dáng chuẩn aviator, bản lề lò xo nên đeo không bị bó.', NULL, NULL, NULL, 'published', '2026-09-01 19:30:00'),
('cccccccc-0000-4000-8000-000000000007', '44444444-0000-4000-8000-000000000009', '11111111-0000-4000-8000-000000000007', NULL,                                  'Phạm Đức Long',    4, 'Đổi màu nhanh khi ra nắng. Trong ô tô thì gần như không sẫm, đúng như tư vấn.', NULL, NULL, 'Xám khói · M', 'published', '2026-09-03 12:10:00'),
('cccccccc-0000-4000-8000-000000000008', '44444444-0000-4000-8000-000000000010', '11111111-0000-4000-8000-000000000009', '99999999-0000-4000-8000-000000000006', 'Đỗ Minh Châu',     5, 'Mua cho hai cháu, bẻ thoải mái không gãy. Dây đeo sau gáy rất cần cho trẻ hiếu động.', NULL, NULL, NULL, 'published', '2026-08-25 09:44:00'),
-- Chờ duyệt: một ô nhập công khai đăng thẳng lên trang sản phẩm là lời mời spam.
('cccccccc-0000-4000-8000-000000000009', '44444444-0000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000004', '99999999-0000-4000-8000-000000000007', 'Nguyễn Thu Hà',    3, 'Kính đẹp nhưng mình đổi ý huỷ đơn nên chưa dùng thử.', NULL, NULL, NULL, 'pending',   '2026-08-26 07:50:00'),
('cccccccc-0000-4000-8000-000000000010', '44444444-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000006', NULL,                                  'Lê Phương Anh',    2, 'Nội dung quảng cáo, không liên quan tới sản phẩm.', NULL, NULL, NULL, 'rejected',  '2026-08-30 22:15:00');

INSERT INTO `newsletter_subscribers` (`id`, `email`, `source`, `unsubscribed_at`, `created_at`) VALUES
('13131313-0000-4000-8000-000000000001', 'nguyenthuha@gmail.com',    'home',     NULL,                  '2026-07-14 09:30:00'),
('13131313-0000-4000-8000-000000000002', 'tranminhquan@gmail.com',   'footer',   NULL,                  '2026-07-20 10:02:00'),
('13131313-0000-4000-8000-000000000003', 'lephuonganh@gmail.com',    'home',     NULL,                  '2026-08-02 11:15:00'),
('13131313-0000-4000-8000-000000000004', 'phamduclong@gmail.com',    'popup',    NULL,                  '2026-08-09 20:40:00'),
('13131313-0000-4000-8000-000000000005', 'vuhaiyen@gmail.com',       'footer',   NULL,                  '2026-08-16 08:12:00'),
('13131313-0000-4000-8000-000000000006', 'ngogiabao@gmail.com',      'home',     NULL,                  '2026-08-22 15:33:00'),
('13131313-0000-4000-8000-000000000007', 'trinhkhanhlinh@gmail.com', 'bo-suu-tap', NULL,                '2026-08-28 09:07:00'),
('13131313-0000-4000-8000-000000000008', 'buithanhvan@gmail.com',    'popup',    NULL,                  '2026-09-02 17:50:00'),
-- Hai người ĐÃ HUỶ NHẬN TIN. Không xoá dòng: xoá hẳn thì lần sau nhập lại danh
-- sách cũ là gửi đúng cho người vừa từ chối.
('13131313-0000-4000-8000-000000000009', 'hoangnamtrung@gmail.com',  'footer',   '2026-09-01 10:20:00', '2026-08-25 11:10:00'),
('13131313-0000-4000-8000-000000000010', 'dominhchau@gmail.com',     'home',     '2026-09-04 08:00:00', '2026-08-21 16:25:00');

-- `zalo_sent_at` là SỰ KIỆN (đã đẩy sang Zalo CSKH lúc nào), không phải trạng
-- thái ai đó tự đặt. NULL = chưa tới tay ai — huy hiệu trên thanh bên đếm đúng
-- những dòng này.
INSERT INTO `contact_requests` (`id`, `user_id`, `full_name`, `phone`, `email`, `message`, `zalo_sent_at`, `created_at`) VALUES
('12121212-0000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000004', 'Nguyễn Thu Hà',    '0912345678', 'nguyenthuha@gmail.com',   'Kính mình mua tháng trước bị lỏng bản lề, mang ra cửa hàng nào cũng chỉnh được phải không ạ?', '2026-09-05 09:02:00', '2026-09-05 09:00:00'),
('12121212-0000-4000-8000-000000000002', '11111111-0000-4000-8000-000000000005', 'Trần Minh Quân',   '0987654321', 'tranminhquan@gmail.com',  'Cửa hàng có nhận cắt tròng cho gọng mua ngoài không?',                                          '2026-09-05 14:31:00', '2026-09-05 14:30:00'),
('12121212-0000-4000-8000-000000000003', NULL,                                   'Ngô Gia Bảo',      '0922334455', NULL,                      'Cho mình hỏi giá tròng 1.74 cho cận 8 độ khoảng bao nhiêu?',                                     '2026-09-06 10:12:00', '2026-09-06 10:10:00'),
('12121212-0000-4000-8000-000000000004', NULL,                                   'Trịnh Khánh Linh', '0933445566', 'trinhkhanhlinh@gmail.com','Cửa hàng Hoàn Kiếm mở cửa chủ nhật không ạ?',                                                    '2026-09-06 16:45:00', '2026-09-06 16:44:00'),
('12121212-0000-4000-8000-000000000005', '11111111-0000-4000-8000-000000000006', 'Lê Phương Anh',    '0905112233', 'lephuonganh@gmail.com',   'Đơn của mình đang chuẩn bị, mình muốn đổi sang tròng đổi màu có được không?',                    '2026-09-07 08:22:00', '2026-09-07 08:20:00'),
('12121212-0000-4000-8000-000000000006', NULL,                                   'Bùi Thanh Vân',    '0911222333', NULL,                      'Đo mắt cho trẻ 8 tuổi có cần đặt lịch trước không?',                                             '2026-09-07 19:03:00', '2026-09-07 19:00:00'),
-- Bốn dòng CHƯA ĐẨY được sang Zalo — token hết hạn hoặc mạng ra ngoài bị chặn.
-- Đây là chỗ duy nhất nhìn ra việc đó; ZNS hỏng thì im lặng.
('12121212-0000-4000-8000-000000000007', '11111111-0000-4000-8000-000000000007', 'Phạm Đức Long',    '0938222444', 'phamduclong@gmail.com',   'Mình bắt đầu lão thị, đa tròng của cửa hàng giá thế nào?',                                       NULL,                  '2026-09-08 06:40:00'),
('12121212-0000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000008', 'Vũ Hải Yến',       '0977335566', 'vuhaiyen@gmail.com',      'Tròng 1.74 cửa hàng có sẵn hay phải đặt?',                                                       NULL,                  '2026-09-08 07:15:00'),
('12121212-0000-4000-8000-000000000009', NULL,                                   'Đặng Quốc Việt',   '0955667788', 'dangquocviet@gmail.com',  'Cửa hàng có xuất hoá đơn đỏ cho công ty không?',                                                 NULL,                  '2026-09-08 08:05:00'),
('12121212-0000-4000-8000-000000000010', NULL,                                   'Khách gọi nhỡ',    '0900111222', NULL,                      'Gửi thử từ form liên hệ, không có nội dung cụ thể.',                                             NULL,                  '2026-09-08 08:30:00');

-- Vết thao tác: `user_id` là CHỦ dữ liệu bị đụng tới, `actor_id` là người bấm.
-- Không lưu nội dung số đo vào `detail` — bảng vết không được thành bản sao thứ
-- hai của chính dữ liệu y tế đang cần bảo vệ.
INSERT INTO `customer_audit_logs` (`id`, `user_id`, `actor_id`, `actor_name`, `action`, `detail`, `ip`, `created_at`) VALUES
('ffffffff-0000-4000-8000-000000000001', '11111111-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000003', 'Lê Minh Khôi',    'rx.read',        'Mở tab Đơn thuốc kính',                       '192.168.1.24',  '2026-08-30 10:25:00'),
('ffffffff-0000-4000-8000-000000000002', '11111111-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000003', 'Lê Minh Khôi',    'rx.create',      'Thêm bản ghi đo ngày 30/08/2026',              '192.168.1.24',  '2026-08-30 11:05:00'),
('ffffffff-0000-4000-8000-000000000003', '11111111-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000003', 'Lê Minh Khôi',    'rx.update',      'Sửa thành phiên bản 2 — ghi nhầm trục loạn',   '192.168.1.24',  '2026-08-30 15:40:00'),
('ffffffff-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000003', 'Lê Minh Khôi',    'rx.create',      'Thêm bản ghi đo ngày 02/09/2026',              '192.168.1.24',  '2026-09-02 10:40:00'),
('ffffffff-0000-4000-8000-000000000005', '11111111-0000-4000-8000-000000000010', '11111111-0000-4000-8000-000000000001', 'Nguyễn Quản Trị', 'lock',           'Khoá tài khoản — từ chối nhận 6 đơn COD',      '192.168.1.10',  '2026-09-02 15:20:00'),
('ffffffff-0000-4000-8000-000000000006', NULL,                                   '11111111-0000-4000-8000-000000000001', 'Nguyễn Quản Trị', 'export',         'Xuất danh sách khách hàng, 10 dòng',           '192.168.1.10',  '2026-09-03 09:00:00'),
('ffffffff-0000-4000-8000-000000000007', '11111111-0000-4000-8000-000000000005', '11111111-0000-4000-8000-000000000002', 'Trần Thu Hằng',   'payment.deposit', 'Đơn DH-260826-99AA — ghi nhận đã cọc',        '192.168.1.31',  '2026-08-26 16:36:00'),
('ffffffff-0000-4000-8000-000000000008', '11111111-0000-4000-8000-000000000004', '11111111-0000-4000-8000-000000000002', 'Trần Thu Hằng',   'payment.paid',   'Đơn DH-260901-A1B2 — đã thu đủ khi giao',      '192.168.1.31',  '2026-09-04 16:21:00'),
('ffffffff-0000-4000-8000-000000000009', '11111111-0000-4000-8000-000000000007', '11111111-0000-4000-8000-000000000001', 'Nguyễn Quản Trị', 'order.cancel',   'Đơn DH-260830-DDEE — huỷ do hết hàng',         '192.168.1.10',  '2026-08-30 14:00:00'),
('ffffffff-0000-4000-8000-000000000010', '11111111-0000-4000-8000-000000000006', '11111111-0000-4000-8000-000000000002', 'Trần Thu Hằng',   'sepay.link_order', 'Gắn giao dịch 8800108 vào đơn DH-260903-E5F6', '192.168.1.31', '2026-09-04 09:02:00');

-- ============================================================================
-- 7. THƯ
--
-- 15 MẪU THƯ — chép từ database/migrations/2026-09-06-dot-6-email.sql, là NGUỒN
-- THẬT của câu chữ. INSERT IGNORE nên chạy file nào trước cũng được, và câu chữ
-- cửa hàng đã sửa trong khu quản trị không bị ghi đè.
--
-- Không rút xuống 10: đây là 15 sự kiện mã nguồn thật sự phát ra. Thiếu một mẫu
-- thì EmailQueueModel::xepHang() ghi error_log "không có mẫu thư" rồi bỏ qua —
-- lá thư đó không bao giờ được gửi và cũng không ai thấy.
-- ============================================================================
INSERT IGNORE INTO `email_templates` (`key`, `nhan`, `mo_ta`, `bien`, `subject_vi`, `body_vi`) VALUES
('don.tao', 'Đơn hàng đã được tạo', 'Gửi ngay sau khi khách đặt hàng thành công.', '{{ten_khach}}, {{ma_don}}, {{tong_tien}}, {{link_don}}', 'Vin Eyewear — đã nhận đơn {{ma_don}}', '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã nhận đơn <strong>{{ma_don}}</strong> trị giá <strong>{{tong_tien}}</strong>. Chúng tôi sẽ liên hệ để xác nhận trong thời gian sớm nhất.</p><p><a href="{{link_don}}">Xem chi tiết đơn hàng</a></p>'),
('don.xac_nhan', 'Đơn hàng đã được xác nhận', 'Gửi khi nhân viên chuyển đơn sang trạng thái Đã xác nhận.', '{{ten_khach}}, {{ma_don}}, {{link_don}}', 'Đơn {{ma_don}} đã được xác nhận', '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> đã được xác nhận và đang chờ chuẩn bị hàng.</p><p><a href="{{link_don}}">Theo dõi đơn hàng</a></p>'),
('don.giao', 'Đơn hàng đang giao / sẵn sàng tại cửa hàng', 'Gửi khi đơn sang trạng thái Đang giao. Câu chữ đổi theo hình thức nhận hàng.', '{{ten_khach}}, {{ma_don}}, {{nhan_trang_thai}}, {{link_don}}', 'Đơn {{ma_don}} — {{nhan_trang_thai}}', '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> nay ở trạng thái <strong>{{nhan_trang_thai}}</strong>.</p><p><a href="{{link_don}}">Xem chi tiết</a></p>'),
('don.hoan_tat', 'Đơn hàng đã hoàn tất', 'Gửi khi đơn sang trạng thái Hoàn tất.', '{{ten_khach}}, {{ma_don}}, {{link_don}}', 'Cảm ơn bạn — đơn {{ma_don}} đã hoàn tất', '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> đã hoàn tất. Cảm ơn bạn đã chọn Vin Eyewear.</p><p>Kính có vấn đề gì trong quá trình sử dụng, bạn cứ liên hệ cửa hàng — bảo hành và cân chỉnh gọng là miễn phí.</p>'),
('don.huy', 'Đơn hàng đã huỷ', 'Gửi khi đơn chuyển sang Đã huỷ, dù do khách, nhân viên hay hệ thống.', '{{ten_khach}}, {{ma_don}}, {{ly_do}}, {{link_don}}', 'Đơn {{ma_don}} đã được huỷ', '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> đã được huỷ.</p><p>{{ly_do}}</p><p>Nếu đơn đã phát sinh thanh toán, cửa hàng sẽ liên hệ với bạn về việc hoàn tiền.</p>'),
('tien.coc', 'Đã nhận tiền cọc', 'Gửi khi hệ thống ghi nhận đã nhận tiền cọc của đơn.', '{{ten_khach}}, {{ma_don}}, {{so_tien}}, {{link_don}}', 'Đã nhận tiền cọc đơn {{ma_don}}', '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã nhận <strong>{{so_tien}}</strong> tiền cọc cho đơn <strong>{{ma_don}}</strong>.</p><p><a href="{{link_don}}">Xem đơn hàng</a></p>'),
('tien.du', 'Đã thanh toán đủ', 'Gửi khi đơn được ghi nhận đã trả đủ.', '{{ten_khach}}, {{ma_don}}, {{so_tien}}, {{link_don}}', 'Đã nhận thanh toán đơn {{ma_don}}', '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã nhận đủ <strong>{{so_tien}}</strong> cho đơn <strong>{{ma_don}}</strong>.</p><p><a href="{{link_don}}">Xem đơn hàng</a></p>'),
('tien.hoan', 'Đã hoàn tiền cọc', 'Gửi khi Quản trị viên bấm "Đã hoàn tiền" ở màn Hoàn tiền cọc.', '{{ten_khach}}, {{ma_don}}, {{so_tien}}, {{ngay_hoan}}', 'Đã hoàn {{so_tien}} cho đơn {{ma_don}}', '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã chuyển lại <strong>{{so_tien}}</strong> của đơn <strong>{{ma_don}}</strong> vào ngày {{ngay_hoan}}.</p><p>Nếu sau ba ngày làm việc bạn chưa thấy tiền về, vui lòng liên hệ cửa hàng.</p>'),
('lich.dat', 'Đã nhận lịch hẹn đo mắt', 'Gửi ngay sau khi khách đặt lịch trên website.', '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}, {{co_so}}, {{link_lich}}', 'Đã nhận lịch hẹn đo mắt ngày {{ngay_hen}}', '<p>Chào {{ten_khach}},</p><p>Cửa hàng đã nhận lịch hẹn <strong>{{ma_lich}}</strong> vào ngày <strong>{{ngay_hen}}</strong> tại {{co_so}}.</p><p>Chúng tôi sẽ liên hệ xác nhận với bạn.</p>'),
('lich.xac_nhan', 'Lịch hẹn đã được xác nhận', 'Gửi khi nhân viên xác nhận lịch hẹn.', '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}, {{co_so}}', 'Lịch hẹn ngày {{ngay_hen}} đã được xác nhận', '<p>Chào {{ten_khach}},</p><p>Lịch hẹn <strong>{{ma_lich}}</strong> ngày <strong>{{ngay_hen}}</strong> tại {{co_so}} đã được xác nhận. Hẹn gặp bạn.</p>'),
('lich.doi_ngay', 'Lịch hẹn đã đổi ngày', 'Gửi khi lịch hẹn được dời sang ngày khác.', '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}, {{ngay_cu}}, {{co_so}}', 'Lịch hẹn {{ma_lich}} đã đổi sang ngày {{ngay_hen}}', '<p>Chào {{ten_khach}},</p><p>Lịch hẹn <strong>{{ma_lich}}</strong> đã được dời từ ngày {{ngay_cu}} sang <strong>{{ngay_hen}}</strong> tại {{co_so}}.</p>'),
('lich.huy', 'Lịch hẹn đã huỷ', 'Gửi khi lịch hẹn bị huỷ, dù do khách hay nhân viên.', '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}', 'Lịch hẹn ngày {{ngay_hen}} đã được huỷ', '<p>Chào {{ten_khach}},</p><p>Lịch hẹn <strong>{{ma_lich}}</strong> ngày {{ngay_hen}} đã được huỷ.</p><p>Bạn có thể đặt lịch mới bất cứ lúc nào trên website.</p>'),
('lich.nhac', 'Nhắc lịch hẹn trước một ngày', 'Bộ quét tự sinh cho lịch hẹn của ngày mai, ở trạng thái chờ hoặc đã xác nhận.', '{{ten_khach}}, {{ma_lich}}, {{ngay_hen}}, {{co_so}}', 'Nhắc bạn: lịch đo mắt ngày mai {{ngay_hen}}', '<p>Chào {{ten_khach}},</p><p>Nhắc bạn lịch đo mắt <strong>{{ngay_hen}}</strong> tại {{co_so}}.</p><p>Bận đột xuất thì bạn báo lại giúp cửa hàng nhé — huỷ hoặc đổi ngày được ngay trong trang tài khoản.</p>'),
('kho.co_hang', 'Hàng đã có lại', 'Gửi cho khách đã đăng ký chờ, khi kho online của mặt hàng từ 0 tăng lên.', '{{ten_khach}}, {{ten_san_pham}}, {{phuong_an}}, {{link_san_pham}}', '{{ten_san_pham}} đã có hàng trở lại', '<p>Chào {{ten_khach}},</p><p><strong>{{ten_san_pham}}</strong> {{phuong_an}} đã có hàng trở lại.</p><p><a href="{{link_san_pham}}">Xem sản phẩm</a></p><p>Số lượng có hạn — mẫu này đã có người chờ.</p>'),
('don.sap_qua_han', 'Đơn chuyển khoản sắp quá hạn', 'Bộ quét tự sinh, gửi trước thời điểm tự huỷ 2 giờ.', '{{ten_khach}}, {{ma_don}}, {{tong_tien}}, {{so_gio}}, {{link_don}}', 'Đơn {{ma_don}} sẽ tự huỷ sau {{so_gio}} giờ nữa', '<p>Chào {{ten_khach}},</p><p>Đơn <strong>{{ma_don}}</strong> ({{tong_tien}}) đặt bằng chuyển khoản nhưng cửa hàng chưa nhận được thanh toán. Đơn sẽ tự huỷ sau <strong>{{so_gio}} giờ</strong> nữa và hàng được trả về kho.</p><p>Nếu bạn đã chuyển khoản rồi, cứ bỏ qua thư này — cửa hàng sẽ đối chiếu và xác nhận.</p>');

-- Hàng chờ thư. Nội dung DỰNG SẴN lúc phát sinh sự kiện, không dựng lúc gửi —
-- một lá "đơn đã xác nhận" nằm chờ ba ngày rồi đơn bị huỷ thì dựng lại lúc gửi
-- là gửi một câu nói về hiện tại dưới tên một sự kiện quá khứ.
--
-- Phần lớn ở trạng thái 'cho': hosting hiện tại không gửi được lá nào
-- (MAIL_DRIVER=log), nên đây đúng là hình dạng của bảng trên máy thật.
INSERT INTO `email_queue`
    (`id`, `loai`, `nguoi_nhan`, `user_id`, `ngon_ngu`, `lien_quan_loai`, `lien_quan_id`,
     `subject`, `body`, `trang_thai`, `so_lan_thu`, `loi_gan_nhat`, `gui_sau`, `gui_luc`,
     `khoa_chong_trung`, `created_at`) VALUES
('18181818-0000-4000-8000-000000000001', 'don.tao',       'nguyenthuha@gmail.com',  '11111111-0000-4000-8000-000000000004', 'vi', 'order',       '99999999-0000-4000-8000-000000000001', 'Vin Eyewear — đã nhận đơn DH-260901-A1B2', '<p>Chào Nguyễn Thu Hà,</p><p>Cửa hàng đã nhận đơn <strong>DH-260901-A1B2</strong> trị giá <strong>3.740.000đ</strong>.</p>', 'xong', 1, NULL,                              '2026-09-01 09:12:00', '2026-09-01 09:12:30', 'don.tao:99999999-0000-4000-8000-000000000001', '2026-09-01 09:12:00'),
('18181818-0000-4000-8000-000000000002', 'don.hoan_tat',  'nguyenthuha@gmail.com',  '11111111-0000-4000-8000-000000000004', 'vi', 'order',       '99999999-0000-4000-8000-000000000001', 'Cảm ơn bạn — đơn DH-260901-A1B2 đã hoàn tất', '<p>Chào Nguyễn Thu Hà,</p><p>Đơn <strong>DH-260901-A1B2</strong> đã hoàn tất.</p>', 'xong', 1, NULL,                          '2026-09-04 16:25:00', '2026-09-04 16:25:20', 'don.hoan_tat:99999999-0000-4000-8000-000000000001', '2026-09-04 16:25:00'),
('18181818-0000-4000-8000-000000000003', 'tien.du',       'tranminhquan@gmail.com', '11111111-0000-4000-8000-000000000005', 'vi', 'order',       '99999999-0000-4000-8000-000000000002', 'Đã nhận thanh toán đơn DH-260902-C3D4', '<p>Chào Trần Minh Quân,</p><p>Cửa hàng đã nhận đủ <strong>2.690.000đ</strong>.</p>', 'cho',  0, NULL,                                '2026-09-02 14:06:00', NULL,                  'tien.du:99999999-0000-4000-8000-000000000002', '2026-09-02 14:06:00'),
('18181818-0000-4000-8000-000000000004', 'don.giao',      'tranminhquan@gmail.com', '11111111-0000-4000-8000-000000000005', 'vi', 'order',       '99999999-0000-4000-8000-000000000002', 'Đơn DH-260902-C3D4 — Sẵn sàng tại cửa hàng', '<p>Chào Trần Minh Quân,</p><p>Đơn <strong>DH-260902-C3D4</strong> đã sẵn sàng tại cơ sở Cầu Giấy.</p>', 'cho', 0, NULL,                '2026-09-06 10:00:00', NULL,                  'don.giao:99999999-0000-4000-8000-000000000002', '2026-09-06 10:00:00'),
('18181818-0000-4000-8000-000000000005', 'tien.coc',      'lephuonganh@gmail.com',  '11111111-0000-4000-8000-000000000006', 'vi', 'order',       '99999999-0000-4000-8000-000000000003', 'Đã nhận tiền cọc đơn DH-260903-E5F6', '<p>Chào Lê Phương Anh,</p><p>Cửa hàng đã nhận <strong>723.000đ</strong> tiền cọc.</p>', 'cho',  0, NULL,                             '2026-09-03 15:53:00', NULL,                  'tien.coc:99999999-0000-4000-8000-000000000003', '2026-09-03 15:53:00'),
('18181818-0000-4000-8000-000000000006', 'don.xac_nhan',  'phamduclong@gmail.com',  '11111111-0000-4000-8000-000000000007', 'vi', 'order',       '99999999-0000-4000-8000-000000000004', 'Đơn DH-260904-1122 đã được xác nhận', '<p>Chào Phạm Đức Long,</p><p>Đơn <strong>DH-260904-1122</strong> đã được xác nhận.</p>', 'cho',  0, NULL,                            '2026-09-05 09:10:00', NULL,                  'don.xac_nhan:99999999-0000-4000-8000-000000000004', '2026-09-05 09:10:00'),
('18181818-0000-4000-8000-000000000007', 'lich.xac_nhan', 'lephuonganh@gmail.com',  '11111111-0000-4000-8000-000000000006', 'vi', 'appointment', '88888888-0000-4000-8000-000000000004', 'Lịch hẹn ngày 10/09/2026 đã được xác nhận', '<p>Chào Lê Phương Anh,</p><p>Lịch hẹn <strong>LH-260910-1D44</strong> đã được xác nhận.</p>', 'cho', 0, NULL,                    '2026-09-06 08:35:00', NULL,                  'lich.xac_nhan:88888888-0000-4000-8000-000000000004', '2026-09-06 08:35:00'),
-- THƯ HẸN GIỜ: xếp hàng ngay lúc biết, `gui_sau` đặt ở tương lai.
('18181818-0000-4000-8000-000000000008', 'lich.nhac',     'phamduclong@gmail.com',  '11111111-0000-4000-8000-000000000007', 'vi', 'appointment', '88888888-0000-4000-8000-000000000005', 'Nhắc bạn: lịch đo mắt ngày mai 11/09/2026', '<p>Chào Phạm Đức Long,</p><p>Nhắc bạn lịch <strong>LH-260911-3E55</strong> tại cơ sở Thanh Xuân.</p>', 'cho', 0, NULL,                   '2026-09-10 08:00:00', NULL,                  'lich.nhac:88888888-0000-4000-8000-000000000005', '2026-09-08 06:00:00'),
-- Thử đủ 4 lần vẫn không được: hệ thống bó tay ('hong').
('18181818-0000-4000-8000-000000000009', 'kho.co_hang',   'khachcu@gmail.com',      NULL,                                  'vi', 'product',     '44444444-0000-4000-8000-000000000004', 'Lumen Rimless Titan đã có hàng trở lại', '<p>Chào bạn,</p><p><strong>Lumen Rimless Titan</strong> đã có hàng trở lại.</p>', 'hong', 4, 'SMTP connect timeout sau 12 giây', '2026-08-29 16:40:00', NULL,                  'kho.co_hang:dddddddd-0000-4000-8000-000000000010', '2026-08-29 16:40:00'),
-- Người vận hành chủ động bỏ ('bo') — KHÁC 'hong': một bên là quyết định của
-- con người, một bên là hệ thống không gửi được.
('18181818-0000-4000-8000-000000000010', 'don.huy',       'hoangnamtrung@gmail.com', '11111111-0000-4000-8000-000000000010', 'vi', 'order',      NULL,                                  'Đơn DH-260830-DDEE đã được huỷ', '<p>Chào Hoàng Nam Trung,</p><p>Đơn đã được huỷ.</p>', 'bo',   0, NULL,                                                         '2026-08-30 14:05:00', NULL,                  'don.huy:99999999-0000-4000-8000-000000000010', '2026-08-30 14:05:00');

-- ============================================================================
-- KIỂM TRA SAU KHI CHẠY
--
--   SELECT 'stores', COUNT(*) FROM stores
--   UNION ALL SELECT 'categories',    COUNT(*) FROM categories
--   UNION ALL SELECT 'lens_packages', COUNT(*) FROM lens_packages
--   UNION ALL SELECT 'lens_prices',   COUNT(*) FROM lens_prices
--   UNION ALL SELECT 'users',         COUNT(*) FROM users
--   UNION ALL SELECT 'products',      COUNT(*) FROM products
--   UNION ALL SELECT 'collections',   COUNT(*) FROM collections
--   UNION ALL SELECT 'orders',        COUNT(*) FROM orders
--   UNION ALL SELECT 'appointments',  COUNT(*) FROM appointments
--   UNION ALL SELECT 'reviews',       COUNT(*) FROM reviews;
--
--   -- Ba phép kiểm tính toàn vẹn — cả ba phải trả về 0 dòng:
--   SELECT code FROM orders WHERE total <> subtotal + shipping_fee - discount;
--   SELECT id   FROM order_items WHERE line_total <> unit_price * quantity;
--   SELECT p.sku FROM products p LEFT JOIN (
--            SELECT product_id, COUNT(*) n, ROUND(AVG(rating),1) r
--              FROM reviews WHERE status = 'published' GROUP BY product_id) v
--          ON v.product_id = p.id
--        WHERE p.review_count <> COALESCE(v.n, 0);
-- ============================================================================
