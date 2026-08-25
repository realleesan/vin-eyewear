-- ============================================================================
-- Vin Eyewear — Schema MySQL
--
-- Chuyển từ 6 file migration Supabase (Postgres) trong dự án Lovable sang MySQL.
-- Ánh xạ kiểu dữ liệu đã dùng:
--
--   UUID          -> CHAR(36)      (sinh bằng UUID() hoặc PHP, giữ nguyên
--                                   định dạng để dữ liệu Supabase cũ import được)
--   TIMESTAMPTZ   -> DATETIME      (site chạy một múi giờ duy nhất: Asia/Ho_Chi_Minh,
--                                   đặt trong config/app.php nên không cần lưu offset)
--   JSONB         -> JSON
--   NUMERIC(12,0) -> BIGINT        (giá tiền VND, không có phần thập phân)
--   ENUM app_role -> ENUM(...)
--
-- Row Level Security của Postgres KHÔNG có tương đương trong MySQL.
-- Toàn bộ luật phân quyền (own-row / staff / admin) chuyển lên tầng PHP:
--   - app/middleware/AuthMiddleware.php  — chặn route theo vai trò
--   - các Model                          — luôn kèm điều kiện user_id khi
--                                          truy vấn dữ liệu riêng của khách
-- Xem bảng đối chiếu từng policy ở cuối file.
--
-- Cách chạy: xem khối ghi chú ngay dưới đây.
-- ============================================================================

-- ─────────────────────────────────────────────────────────────────────────────
-- KHÔNG có lệnh CREATE DATABASE / USE ở đây — CỐ Ý.
--
-- Trên hosting dùng chung (InfinityFree, cPanel…), tài khoản MySQL không có
-- quyền tạo database; database phải tạo sẵn trong bảng điều khiển. File này
-- chạy được vào BẤT KỲ database nào đang mở, nên import qua phpMyAdmin được.
--
-- Chạy tại máy:      mysql --database=vin_eyewear < database/schema.sql
-- Trên hosting:      phpMyAdmin -> chọn database -> tab Import -> chọn file này
-- ─────────────────────────────────────────────────────────────────────────────

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `newsletter_subscribers`;
DROP TABLE IF EXISTS `contact_requests`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `user_vouchers`;
DROP TABLE IF EXISTS `vouchers`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `lens_prices`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `stores`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `user_roles`;
DROP TABLE IF EXISTS `profiles`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. TÀI KHOẢN & PHÂN QUYỀN
-- ============================================================================

-- Thay cho auth.users của Supabase.
--
-- Supabase quản lý mật khẩu ở schema `auth` riêng mà ứng dụng không đụng tới.
-- Bản PHP phải tự giữ phần đó, nên bảng này gộp: định danh + mật khẩu.
-- password_hash lưu chuỗi của password_hash() PHP (bcrypt, 60 ký tự) —
-- KHÔNG BAO GIỜ lưu mật khẩu thô.
CREATE TABLE `users` (
    `id`              CHAR(36)     NOT NULL DEFAULT (UUID()),
    /*
     * EMAIL CHO PHÉP RỖNG. Form đăng ký chỉ hỏi họ tên, số điện thoại và mật
     * khẩu — số điện thoại vốn đã là một trong hai cách đăng nhập. Email chỉ
     * có ở tài khoản đăng ký bằng Google (do Google cung cấp) và ở tài khoản
     * cũ. MySQL cho nhiều dòng NULL trong khoá duy nhất, nên uq_users_email
     * vẫn chặn được hai người trùng email thật.
     */
    `email`           VARCHAR(255) NULL,
    /*
     * Trường `sub` của Google, KHÔNG phải email: `sub` không bao giờ đổi và
     * không bao giờ dùng lại, còn email thì đổi được và địa chỉ đã huỷ có thể
     * cấp lại cho người khác. Xem migration 2026-08-19-dang-ky-khong-email-va-google.
     */
    `google_id`       VARCHAR(64)  NULL,
    `password_hash`   VARCHAR(255) NOT NULL,
    `email_verified`  TINYINT(1)   NOT NULL DEFAULT 0,
    /*
     * ĐỒNG Ý ĐIỀU KHOẢN — mốc bấm nút đăng ký với ô tick đã bật, và phiên bản
     * văn bản lúc đó (config/auth.php ['consent']['version']).
     *
     * Lưu phiên bản chứ không phải một cờ bật/tắt: văn bản sẽ được sửa, mà cờ
     * thì sau lần sửa đầu tiên không phân biệt nổi ai đọc bản nào.
     *
     * NULL = tài khoản có trước 2026-08-25, hoặc tạo bằng đường không đi qua
     * form đăng ký. Không được điền đại ngày tạo tài khoản vào đây — đó là
     * dựng bằng chứng cho một cú tick chưa từng xảy ra.
     *
     * Xem database/migrations/2026-08-25-dong-y-dieu-khoan.sql.
     */
    `terms_accepted_at` DATETIME   NULL,
    `terms_version`   VARCHAR(20)  NULL,
    `last_login_at`   DATETIME     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    UNIQUE KEY `uq_users_google` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hồ sơ khách hàng — quan hệ 1-1 với users, tách riêng đúng như bản Supabase.
-- Migration 20260813080000 bổ sung address + date_of_birth, đã gộp sẵn vào đây.
CREATE TABLE `profiles` (
    `id`             CHAR(36)     NOT NULL,
    `full_name`      VARCHAR(255) NULL,
    `phone`          VARCHAR(32)  NULL,
    -- Bản sao của địa chỉ mặc định trong bảng `addresses`, giữ lại vì trang
    -- thanh toán đang đọc cột này. AddressModel::syncProfileAddress() ghi đè.
    `address`        TEXT         NULL,
    `date_of_birth`  DATE         NULL,
    -- 'nu' | 'nam' | 'khac' — danh sách trong UserModel::GENDERS. Chuỗi chứ
    -- không ENUM: thêm lựa chọn vào ENUM cần ALTER TABLE khoá bảng, còn ở đây
    -- sửa một dòng PHP là xong. NULL = chưa chọn.
    `gender`         VARCHAR(16)  NULL,
    -- Đường dẫn tương đối trong assets/uploads/avatars/, KHÔNG lưu nội dung
    -- ảnh: BLOB làm mọi câu SELECT hồ sơ nặng lên trong khi web server phục vụ
    -- file tĩnh nhanh hơn hẳn.
    `avatar_path`    VARCHAR(255) NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Số điện thoại là MỘT trong hai cách đăng nhập, nên phải chỉ về đúng một
    -- tài khoản. MySQL cho phép nhiều dòng cùng NULL trong khoá UNIQUE, nên
    -- ràng buộc này không cản người dùng bỏ trống số.
    --
    -- Số phải được chuẩn hoá TRƯỚC khi ghi (normalizePhone trong helpers.php):
    -- "0912345678" và "+84912345678" là cùng một thuê bao, nhưng với MySQL là
    -- hai chuỗi khác nhau và khoá UNIQUE sẽ cho lọt cả hai.
    UNIQUE KEY `uq_profiles_phone` (`phone`),
    CONSTRAINT `fk_profiles_user` FOREIGN KEY (`id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vai trò tách khỏi profiles: một người có thể giữ nhiều vai trò cùng lúc.
-- Đây cũng là lý do bản gốc không nhét cột `role` thẳng vào profiles —
-- để vai trò không bị người dùng tự sửa qua đường cập nhật hồ sơ.
CREATE TABLE `user_roles` (
    `id`         CHAR(36) NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36) NOT NULL,
    `role`       ENUM('customer','staff','manager','admin') NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_roles` (`user_id`, `role`),
    CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- GHI NHỚ ĐĂNG NHẬP
--
-- Chia đôi token thành selector + validator, đây là điểm quan trọng nhất:
--
--   selector  tra cứu, lưu NGUYÊN VĂN, có chỉ mục -> tìm được bằng một phép
--             so khớp chính xác, không phải quét cả bảng.
--   validator bí mật thật, chỉ lưu BĂM. Rò cả bảng này ra ngoài thì kẻ lấy
--             được cũng không đăng nhập nổi, y như cột password_hash.
--
-- Nếu gộp làm một chuỗi rồi lưu băm, muốn tra phải duyệt từng dòng mà băm lại
-- — bảng càng lớn càng chậm, và so sánh kiểu đó dễ lộ thời gian.
-- ----------------------------------------------------------------------------
CREATE TABLE `remember_tokens` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36)     NOT NULL,
    `selector`   CHAR(32)     NOT NULL,
    `validator`  CHAR(64)     NOT NULL,   -- sha256 của phần bí mật
    `expires_at` DATETIME     NOT NULL,
    `user_agent` VARCHAR(255) NULL,       -- để người dùng nhận ra thiết bị nào
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_remember_selector` (`selector`),
    KEY `idx_remember_user`    (`user_id`),
    KEY `idx_remember_expires` (`expires_at`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- QUÊN MẬT KHẨU
--
-- Một bảng cho CẢ HAI đường đặt lại, phân biệt bằng cột status:
--
--   pending  khách vừa yêu cầu, CHƯA có token. Dùng khi hosting không gửi
--            được mail (InfinityFree chặn mail() và cổng SMTP): nhân viên
--            thấy yêu cầu trong trang quản trị, gọi xác minh rồi mới tạo
--            liên kết. Không tạo token sẵn ở bước này — token nằm chờ trong
--            DB mà chưa ai xác minh danh tính là một chìa khoá bỏ ngỏ.
--   sent     đã có token (do gửi mail thành công, hoặc nhân viên vừa tạo).
--   used     đã dùng để đổi mật khẩu; không dùng lại được.
--
-- contact lưu ĐÚNG chuỗi khách gõ vào, kể cả khi không khớp tài khoản nào:
-- nhân viên cần thấy "khách gõ nhầm số" chứ không phải một danh sách rỗng.
-- ----------------------------------------------------------------------------
CREATE TABLE `password_resets` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36)     NULL,       -- NULL = chuỗi khách gõ không khớp ai
    `contact`    VARCHAR(255) NOT NULL,
    `status`     ENUM('pending','sent','used') NOT NULL DEFAULT 'pending',
    `selector`   CHAR(32)     NULL,
    `validator`  CHAR(64)     NULL,       -- sha256, giống remember_tokens
    `expires_at` DATETIME     NULL,
    `used_at`    DATETIME     NULL,
    `handled_by` CHAR(36)     NULL,       -- nhân viên đã tạo liên kết
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reset_selector` (`selector`),
    KEY `idx_reset_user`   (`user_id`),
    KEY `idx_reset_status` (`status`, `created_at`),
    CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hồ sơ khúc xạ — mỗi khách đúng một bản ghi, cập nhật đè lên (PK = user_id).
-- sph/cyl để DECIMAL(4,2): đủ cho khoảng -20.00 .. +20.00, bước 0.25.
--
-- Tám cột sau `os_cyl` do migration 2026-08-16-trang-tai-khoan.sql thêm, đã gộp
-- thẳng vào đây. Chúng là phần bảng "Thông số đo mắt" của trang tài khoản:
--   axis  trục loạn thị 0–180 độ, số nguyên
--   va    thị lực, ghi dạng phân số "10/10" nên phải là chuỗi
--   pd    khoảng cách đồng tử, milimet
--   measured_at  NGÀY ĐO — khác updated_at (lúc gõ vào máy). Huy hiệu "Còn
--                hiệu lực" tính từ ngày này; dùng updated_at thay thì sửa một
--                lỗi chính tả sẽ làm đơn thuốc hai năm tuổi trông như mới đo.
CREATE TABLE `prescriptions` (
    `user_id`        CHAR(36)      NOT NULL,
    `od_sph`         DECIMAL(4,2)  NULL,
    `od_cyl`         DECIMAL(4,2)  NULL,
    `od_axis`        SMALLINT      NULL,
    `od_va`          VARCHAR(16)   NULL,
    `os_sph`         DECIMAL(4,2)  NULL,
    `os_cyl`         DECIMAL(4,2)  NULL,
    `os_axis`        SMALLINT      NULL,
    `os_va`          VARCHAR(16)   NULL,
    `pd`             DECIMAL(4,1)  NULL,
    `measured_at`    DATE          NULL,
    `store_id`       CHAR(36)      NULL,
    `recommendation` VARCHAR(255)  NULL,

    -- Năm cột `wear_*` do migration 2026-08-21-kinh-dang-deo.sql thêm, đã gộp
    -- thẳng vào đây. Chúng là mục "Kính đang đeo" trong hồ sơ đo mắt — cặp
    -- kính khách ĐANG dùng, để cửa hàng có cơ sở tư vấn cặp mới.
    --   wear_lens_type      cùng bộ mã với lens_types ở config/taxonomy.php
    --                       ('don-trong'…'mat-dat'), cộng 'khong' = chưa đeo
    --                       kính bao giờ. Không khoá ngoại: bên kia là mảng PHP.
    --   wear_lens_features  nhiều tính chất, ngăn bằng '|' (nhãn có thể chứa
    --                       dấu phẩy nên không dùng dấu phẩy làm dấu ngăn)
    --   wear_frame_type     loại gọng, nguyên văn
    --   wear_since          đã dùng cặp hiện tại bao lâu
    --   wear_note           câu khách tự ghi
    -- Cả năm NULL được: phần này không bắt buộc.
    `wear_lens_type`     VARCHAR(32)  NULL,
    `wear_lens_features` VARCHAR(255) NULL,
    `wear_frame_type`    VARCHAR(64)  NULL,
    `wear_since`         VARCHAR(32)  NULL,
    `wear_note`          VARCHAR(255) NULL,

    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    KEY `idx_prescriptions_store` (`store_id`),
    CONSTRAINT `fk_prescriptions_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
    -- Khoá ngoại sang `stores` KHÔNG khai ở đây được: bảng đó mãi mục 3 mới
    -- tạo, mà FOREIGN_KEY_CHECKS đã bật lại từ đầu file. Xem lệnh ALTER TABLE
    -- ngay sau CREATE TABLE `stores`.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- BẢNG GIÁ TRÒNG — MỘT Ô CHO MỖI CẶP (KIỂU TRÒNG, GÓI CHIẾT SUẤT)
--
-- Bước "chọn tròng" khi mua hàng hỏi hai tầng: KIỂU tròng (đơn · hai · đa ·
-- mắt đặt) rồi GÓI chiết suất. Giá nằm ở giao điểm chứ không ở riêng tầng
-- nào — mài đa tròng trên phôi 1.67 đắt hơn nhiều lần đơn tròng trên phôi
-- 1.50, mà cũng đắt hơn đa tròng trên phôi 1.50.
--
-- 3 kiểu có bảng giá × 5 gói = 15 ô. Kiểu "Mắt đặt" KHÔNG có dòng nào: tròng
-- đặt riêng theo đơn thì cửa hàng báo giá sau khi xem thông số.
--
-- DANH MỤC ở config, GIÁ ở đây. Mã, tên và mô tả của kiểu tròng lẫn gói chiết
-- suất vẫn nằm trong config/taxonomy.php vì mã nguồn tham chiếu tới chúng
-- bằng id; còn giá là thứ cửa hàng sửa hằng tháng trên trình duyệt
-- (/quan-tri/gia-trong), không phải thứ đi kèm một lượt triển khai mã.
--
-- KHÔNG khoá ngoại cho hai cột mã: bên kia là mảng PHP trong file config,
-- không phải bảng. Cùng lý do với `order_items.lens_id`. Ô trỏ tới một mã đã
-- bị gỡ khỏi config thì LensModel bỏ qua, và màn quản trị không vẽ ra nó.
--
-- Không cột `id`: khoá chính là chính cặp mã, nên DB tự chặn việc tạo hai giá
-- cho cùng một lựa chọn.
-- ----------------------------------------------------------------------------
CREATE TABLE `lens_prices` (
    `lens_type`    VARCHAR(32) NOT NULL,
    `lens_package` VARCHAR(40) NOT NULL,
    `price`        BIGINT      NOT NULL DEFAULT 0,
    `updated_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`lens_type`, `lens_package`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- SỔ ĐỊA CHỈ
--
-- Một khách nhiều địa chỉ -> bảng riêng. `profiles.address` vẫn còn và giữ bản
-- sao của địa chỉ mặc định (xem AddressModel::syncProfileAddress) vì trang
-- thanh toán đang đọc nó.
--
-- recipient_name/phone KHÔNG lấy từ profiles: người đặt và người nhận thường
-- xuyên là hai người khác nhau (gửi quà, gửi về nhà bố mẹ).
--
-- KHÔNG đặt UNIQUE (user_id, is_default): MySQL sẽ hiểu thành "mỗi khách chỉ
-- được có một địa chỉ KHÔNG mặc định". Luật "đúng một mặc định" do
-- AddressModel::setDefault giữ, trong một transaction.
-- ----------------------------------------------------------------------------
CREATE TABLE `addresses` (
    `id`             CHAR(36)     NOT NULL DEFAULT (UUID()),
    `user_id`        CHAR(36)     NOT NULL,
    `recipient_name` VARCHAR(255) NOT NULL,
    `phone`          VARCHAR(32)  NOT NULL,
    `line1`          VARCHAR(255) NOT NULL,
    /*
     * HAI CẤP HÀNH CHÍNH, KHÔNG PHẢI BA — từ 01/07/2025 Việt Nam bỏ cấp huyện,
     * còn tỉnh/thành phố -> phường/xã. Danh sách lấy từ provinces.open-api.vn
     * (34 tỉnh thành; `?depth=2` cho phường/xã của một tỉnh).
     *
     * Lưu CẢ mã LẪN tên: mã để chọn lại đúng mục khi mở form sửa, tên để hiển
     * thị và in lên đơn — tên phải nằm trong bảng này chứ không tra lại theo mã,
     * vì địa chỉ đã lưu không được đổi chữ khi danh mục hành chính sáp nhập.
     *
     * NULL được cả bốn: JavaScript tắt hoặc API chết thì form lùi về hai ô gõ
     * tay, khi đó có tên mà không có mã. Ứng dụng luôn hiển thị theo TÊN.
     */
    `province_code`  SMALLINT UNSIGNED  NULL,
    `province_name`  VARCHAR(120)       NULL,
    `ward_code`      MEDIUMINT UNSIGNED NULL,
    `ward_name`      VARCHAR(120)       NULL,
    `is_default`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addresses_user` (`user_id`, `is_default`),
    CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- ƯU ĐÃI
--
-- Hai bảng chứ không một: `vouchers` định nghĩa chương trình (dùng chung),
-- `user_vouchers` ghi mã nào phát cho ai và đã dùng chưa.
--
-- Gộp làm một nghĩa là mỗi khách một dòng riêng cho cùng một chương trình:
-- sửa điều kiện phải UPDATE hàng nghìn dòng, và không trả lời được câu "chương
-- trình này đã phát cho bao nhiêu người".
--
-- `tag` là chuỗi ngắn in trong ô vuông bên trái thẻ ưu đãi ("-10%", "100K",
-- "FS"). Lưu sẵn thay vì suy ra từ discount_type/value — "100K" không phải
-- cách viết tắt duy nhất của 100.000₫, người làm khuyến mãi cần tự quyết.
-- ----------------------------------------------------------------------------
CREATE TABLE `vouchers` (
    `id`             CHAR(36)     NOT NULL DEFAULT (UUID()),
    `code`           VARCHAR(40)  NOT NULL,
    `tag`            VARCHAR(16)  NOT NULL,
    `title`          VARCHAR(255) NOT NULL,
    `condition_text` VARCHAR(255) NULL,
    -- 'percent' giảm theo % | 'amount' giảm số tiền | 'shipping' miễn phí ship
    `discount_type`  VARCHAR(16)  NOT NULL DEFAULT 'percent',
    `discount_value` BIGINT       NOT NULL DEFAULT 0,
    `min_order`      BIGINT       NOT NULL DEFAULT 0,
    -- Chỉ có nghĩa với 'percent' — chặn trần số tiền được giảm
    `max_discount`   BIGINT       NULL,
    `expires_at`     DATE         NULL,
    `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
    -- 1 = ai gõ đúng mã cũng dùng được (mã in tờ rơi, mã sự kiện)
    -- 0 = chỉ người đã được phát qua `user_vouchers` mới dùng được
    -- Cần cột này vì ô nhập mã ở giỏ hàng thì khách VÃNG LAI cũng gõ được,
    -- mà khách vãng lai không có dòng nào trong `user_vouchers`.
    `is_public`      TINYINT(1)   NOT NULL DEFAULT 1,
    -- Cột `is_reward` do migration 2026-08-22-ma-thuong-chuyen-du.sql thêm, đã
    -- gộp vào đây. 1 = mã này được TẶNG tự động cho khách chọn chuyển khoản đủ
    -- 100% cho đơn có cắt tròng. Chỉ MỘT mã được bật cùng lúc; luật đó do PHP
    -- giữ (VoucherAdminController::save) vì MySQL không có partial unique index.
    `is_reward`      TINYINT(1)   NOT NULL DEFAULT 0,
    -- NULL = không giới hạn. Thiếu nó thì một mã lọt ra ngoài là bán lỗ vô hạn.
    `max_uses`       INT          NULL,
    `used_count`     INT          NOT NULL DEFAULT 0,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vouchers_code` (`code`),
    KEY `idx_vouchers_reward` (`is_reward`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Khoá chính GHÉP: một người không nhận được hai lần cùng một mã, và ràng
-- buộc đó do CSDL giữ chứ không phải do code nhớ kiểm.
CREATE TABLE `user_vouchers` (
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

-- ============================================================================
-- 2. DANH MỤC SẢN PHẨM
-- ============================================================================

CREATE TABLE `categories` (
    `id`          CHAR(36)     NOT NULL DEFAULT (UUID()),
    `slug`        VARCHAR(160) NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT         NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `is_visible`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_slug` (`slug`),
    KEY `idx_categories_visible_sort` (`is_visible`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
    `id`               CHAR(36)     NOT NULL DEFAULT (UUID()),
    `slug`             VARCHAR(160) NOT NULL,
    `sku`              VARCHAR(64)  NOT NULL,
    `name`             VARCHAR(255) NOT NULL,
    `category_id`      CHAR(36)     NULL,
    `brand`            VARCHAR(120) NULL,
    `frame_shape`      VARCHAR(60)  NULL,
    `material`         VARCHAR(120) NULL,
    `color`            VARCHAR(120) NULL,
    `gender`           VARCHAR(20)  NULL,
    -- collection: bộ sưu tập theo mùa (S09). Khớp 'slug' trong
    --             config/collections.php. NULL = không thuộc bộ nào.
    `collection`       VARCHAR(64)  NULL,
    `description`      TEXT         NULL,
    -- specs: cặp nhãn-giá trị hiển thị ở bảng thông số trang chi tiết
    --        vd {"Vật liệu":"Titan","Kích thước":"52-18-140"}
    `specs`            JSON         NULL,
    -- images: mảng đường dẫn ảnh, phần tử đầu là ảnh đại diện
    `images`           JSON         NULL,
    `price`            BIGINT       NOT NULL DEFAULT 0,
    `compare_at_price` BIGINT       NULL,
    `stock_quantity`   INT          NOT NULL DEFAULT 0,
    `status`           VARCHAR(32)  NOT NULL DEFAULT 'in_stock',
    `is_featured`      TINYINT(1)   NOT NULL DEFAULT 0,
    `is_visible`       TINYINT(1)   NOT NULL DEFAULT 1,
    `ar_model_url`     VARCHAR(500) NULL,
    `rating`           DECIMAL(2,1) NOT NULL DEFAULT 5.0,
    `review_count`     INT          NOT NULL DEFAULT 0,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    UNIQUE KEY `uq_products_sku` (`sku`),
    KEY `idx_products_category` (`category_id`),
    -- Trang danh sách luôn lọc is_visible rồi mới sắp xếp -> index ghép
    KEY `idx_products_visible_featured` (`is_visible`, `is_featured`),
    KEY `idx_products_price` (`price`),
    -- Bộ lọc sidebar lọc theo 3 thuộc tính này
    KEY `idx_products_facets` (`frame_shape`, `material`, `gender`),
    -- Khối bộ sưu tập trang chủ lọc thẳng theo cột này
    KEY `idx_products_collection` (`collection`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- BIẾN THỂ SẢN PHẨM
--
-- Cùng một mặt hàng, khách chọn một phương án (chiết suất tròng, màu gọng…) và
-- phương án đó đi theo vào giỏ, vào đơn, trừ đúng tồn kho của nó.
--
-- `price_delta` là CHÊNH LỆCH so với products.price, không phải giá tuyệt đối:
-- đổi giá mặt hàng thì chỉ sửa MỘT số ở products, mọi biến thể tự theo. Cho
-- phép âm (bản rẻ hơn bản gốc).
--
-- Mặt hàng KHÔNG có dòng nào ở đây thì bán như cũ, dùng tồn kho ở products.
-- ----------------------------------------------------------------------------
CREATE TABLE `product_variants` (
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
    -- Hai biến thể cùng nhãn trong một mặt hàng là lỗi nhập liệu
    UNIQUE KEY `uq_variant_label` (`product_id`, `label`),
    KEY `idx_variant_product` (`product_id`, `position`),
    CONSTRAINT `fk_variant_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. SỰ KIỆN & CƠ SỞ
-- ============================================================================

-- Cột `category` do migration 20260805090053 thêm sau, đã gộp thẳng vào đây.
CREATE TABLE `events` (
    `id`          CHAR(36)     NOT NULL DEFAULT (UUID()),
    `slug`        VARCHAR(160) NOT NULL,
    `title`       VARCHAR(255) NOT NULL,
    `category`    VARCHAR(60)  NULL,
    `excerpt`     TEXT         NULL,
    `content`     TEXT         NULL,
    `cover_image` VARCHAR(500) NULL,
    `location`    VARCHAR(255) NULL,
    `starts_at`   DATETIME     NULL,
    `ends_at`     DATETIME     NULL,
    `is_visible`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_events_slug` (`slug`),
    KEY `idx_events_visible_starts` (`is_visible`, `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stores` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    `code`       VARCHAR(40)  NOT NULL,
    `name`       VARCHAR(255) NOT NULL,
    `address`    TEXT         NOT NULL,
    `phone`      VARCHAR(32)  NULL,
    `open_hours` VARCHAR(255) NULL,
    `map_url`    TEXT         NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_stores_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Khoá ngoại của `prescriptions.store_id` — khai ở đây vì `stores` tới bây giờ
-- mới tồn tại. SET NULL: cơ sở đóng cửa không làm kết quả đo mất giá trị.
ALTER TABLE `prescriptions`
    ADD CONSTRAINT `fk_prescriptions_store` FOREIGN KEY (`store_id`)
        REFERENCES `stores` (`id`) ON DELETE SET NULL;

-- ============================================================================
-- 4. TƯƠNG TÁC CỦA KHÁCH
-- ============================================================================

CREATE TABLE `favorites` (
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

-- Lịch hẹn khám mắt / tư vấn.
--
-- UNIQUE (store_id, appointment_date, time_slot) là ràng buộc nghiệp vụ quan
-- trọng: mỗi cơ sở chỉ nhận MỘT lịch cho mỗi khung giờ mỗi ngày. Nhờ nó,
-- hai người đặt trùng khung giờ cùng lúc sẽ có một người bị DB từ chối
-- (lỗi 1062) thay vì cả hai cùng được nhận. Tầng PHP bắt lỗi này và báo
-- "khung giờ vừa có người đặt" — xem BookingModel::create().
CREATE TABLE `appointments` (
    `id`               CHAR(36)     NOT NULL DEFAULT (UUID()),
    `code`             VARCHAR(40)  NOT NULL,
    `user_id`          CHAR(36)     NULL,
    `store_id`         CHAR(36)     NOT NULL,
    `appointment_date` DATE         NOT NULL,
    -- Giờ hẹn. NULL kể từ 2026-08-25: form khách chỉ còn chọn NGÀY, cửa hàng gọi
    -- điện xác nhận rồi tự xếp giờ (giả định A5). Lịch đặt trước mốc đó vẫn giữ
    -- giờ khách đã chọn, nên cột được nới NULL chứ không bị drop — xem
    -- database/migrations/2026-08-25-bo-khung-gio-khoi-form-khach.sql.
    `time_slot`        VARCHAR(20)  NULL     DEFAULT NULL,
    `service_type`     VARCHAR(60)  NOT NULL,
    `full_name`        VARCHAR(255) NOT NULL,
    `phone`            VARCHAR(32)  NOT NULL,
    `note`             TEXT         NULL,
    `status`           VARCHAR(32)  NOT NULL DEFAULT 'pending',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Mốc sửa gần nhất. Khách đổi lịch thì hàng này được sửa tại chỗ (giữ nguyên
    -- mã lịch), nên thiếu cột này thì nhân viên không biết lịch đã bị đổi.
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    /*
     * KHÔNG CÓ KHOÁ NÀO GIỚI HẠN SỐ NGƯỜI TRÊN MỘT KHUNG GIỜ — CHỦ Ý.
     *
     * Ở đây từng có UNIQUE (store_id, appointment_date, time_slot, active_slot)
     * cùng một cột sinh `active_slot` chỉ tồn tại để phục vụ nó, cho mỗi khung
     * giờ đúng một lịch còn hiệu lực. Cửa hàng yêu cầu bỏ hẳn: đo mắt và cắt
     * kính hết khoảng 30 phút, phần lâu nhất là 10–15 phút thử tròng còn lắp
     * kính thì máy làm rất nhanh, nên không cần chia ca như tiệm cắt tóc.
     *
     * Khung giờ khách chọn trên web nay là NGUYỆN VỌNG, không phải một chỗ đã
     * giữ. Cửa hàng ghi nhận rồi gọi điện xác nhận và tự xếp người — cái chốt
     * thật nằm ở cuộc gọi đó, không nằm ở ràng buộc trong DB.
     *
     * Xem database/migrations/2026-08-22-bo-gioi-han-khung-gio.sql.
     *
     * `uq_appointments_code` thì GIỮ, và nó không liên quan gì tới khung giờ:
     * nó chặn hai lịch trùng MÃ (LH…), thứ khách đọc qua điện thoại và nhân
     * viên tra trong khu quản trị. Sau khi bỏ khoá kia, lỗi 1062 trên bảng này
     * chỉ còn đúng một nghĩa là trùng mã.
     */
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_appointments_code` (`code`),
    KEY `idx_appointments_user` (`user_id`),
    KEY `idx_appointments_status` (`status`),
    /*
     * Chỉ mục cho `store_id` phải KHAI RÕ, đừng bỏ đi vì "InnoDB tự tạo".
     *
     * Khoá ngoại `fk_appointments_store` cần một chỉ mục bắt đầu bằng cột này.
     * Trước đây nó mượn tạm khoá duy nhất bốn cột (cột đầu là `store_id`), và
     * chính vì thế lệnh xoá khoá ấy bị InnoDB từ chối với lỗi 1553 — xem
     * migration 2026-08-22-bo-gioi-han-khung-gio.sql. Khai rõ ở đây thì bảng
     * dựng mới có cùng bộ chỉ mục với bảng đã nâng cấp, và cùng một cái tên.
     */
    KEY `idx_appointments_store` (`store_id`),
    CONSTRAINT `fk_appointments_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_appointments_store` FOREIGN KEY (`store_id`)
        REFERENCES `stores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. ĐƠN HÀNG
-- ============================================================================

CREATE TABLE `orders` (
    `id`               CHAR(36)     NOT NULL DEFAULT (UUID()),
    `code`             VARCHAR(40)  NOT NULL,
    -- ON DELETE SET NULL: xoá tài khoản không được xoá theo lịch sử đơn hàng,
    -- vì đơn đã phát sinh giao dịch thật và còn cần cho sổ sách.
    `user_id`          CHAR(36)     NULL,
    `customer_name`    VARCHAR(255) NOT NULL,
    `customer_phone`   VARCHAR(32)  NOT NULL,
    `customer_email`   VARCHAR(255) NULL,
    `shipping_address` TEXT         NULL,
    `delivery_method`  VARCHAR(32)  NOT NULL DEFAULT 'pickup',
    -- Cơ sở nhận hàng. CHỈ có nghĩa khi delivery_method = 'pickup'; đơn giao
    -- tận nơi để NULL. Thiếu cột này thì đơn "nhận tại cửa hàng" không nói được
    -- nhận ở đâu và nhân viên phải gọi hỏi từng đơn.
    `store_id`         CHAR(36)     NULL,
    `payment_method`   VARCHAR(32)  NOT NULL DEFAULT 'cod',
    -- CÁCH trả tiền (payment_method) và VIỆC tiền đã về hay chưa
    -- (payment_status) là hai chuyện khác nhau, và cũng khác cả cột `status` bên
    -- dưới — cột đó là vòng đời GIAO VẬN. Đơn COD chỉ thu được tiền lúc giao
    -- xong, còn đơn chuyển khoản phải thu tiền TRƯỚC khi giao; một cột không
    -- diễn được cả hai chiều đó.
    --
    -- VARCHAR chứ không ENUM để thêm 'pending'/'refunded' lúc nối cổng thanh
    -- toán không phải ALTER TABLE. Giá trị dùng hiện tại: 'unpaid' | 'paid'.
    -- Xem OrderModel::PAYMENT_STATUSES.
    `payment_status`   VARCHAR(16)  NOT NULL DEFAULT 'unpaid',
    -- Mốc kế toán, tách khỏi updated_at: updated_at đổi theo mọi lần sửa đơn,
    -- còn "tiền về lúc nào" thì phải đứng yên.
    `paid_at`          DATETIME     NULL,
    `note`             TEXT         NULL,
    `subtotal`         BIGINT       NOT NULL DEFAULT 0,
    `shipping_fee`     BIGINT       NOT NULL DEFAULT 0,
    -- discount là SỐ TIỀN, không phải phần trăm. Phần trăm là cách TÍNH ra nó
    -- và cách tính ấy nằm ở bảng `vouchers`; hoá đơn chỉ giữ kết quả — cùng lý
    -- do với product_name/unit_price trong order_items.
    `discount`         BIGINT       NOT NULL DEFAULT 0,
    -- Chỉ để tra cứu "chương trình này đã dùng bao nhiêu lần". SET NULL khi
    -- chương trình bị xoá: đơn đã phát sinh không được hỏng theo.
    `voucher_id`       CHAR(36)     NULL,
    -- subtotal + shipping_fee − discount = total
    `total`            BIGINT       NOT NULL DEFAULT 0,
    /*
     * ĐẶT CỌC — chỉ đơn CÓ CẮT TRÒNG THEO ĐỘ mới phải cọc.
     *
     * Tròng mài riêng theo số đo của một người thì không bán lại cho ai khác
     * được, nên cửa hàng thu trước 30% cho CẢ đơn COD lẫn đơn chuyển khoản.
     * Đơn chỉ mua gọng (gọng kèm tròng demo chưa cắt độ) để 0.
     *
     * LƯU SỐ TIỀN, không tính lại từ tỷ lệ lúc đọc: tỷ lệ nằm ở config và sẽ
     * có ngày bị đổi, mà số tiền khách đã chuyển thì phải đứng yên. Cùng lý lẽ
     * với `discount` ở trên. `deposit_rate` (30 = 30%) lưu kèm để đối chiếu.
     *
     * "Đã cọc chưa" thì đi bằng `payment_status` = 'deposit_paid', không cần
     * cột riêng — xem OrderModel::PAYMENT_STATUSES.
     */
    `deposit_amount`   BIGINT       NOT NULL DEFAULT 0,
    `deposit_rate`     SMALLINT     NOT NULL DEFAULT 0,
    `status`           VARCHAR(32)  NOT NULL DEFAULT 'new',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_code` (`code`),
    KEY `idx_orders_user` (`user_id`),
    KEY `idx_orders_status_created` (`status`, `created_at`),
    KEY `idx_orders_payment` (`payment_status`),
    KEY `idx_orders_voucher` (`voucher_id`),
    KEY `idx_orders_store` (`store_id`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_orders_voucher` FOREIGN KEY (`voucher_id`)
        REFERENCES `vouchers` (`id`) ON DELETE SET NULL,
    -- Đóng một cơ sở không được làm hỏng đơn đã phát sinh
    CONSTRAINT `fk_orders_store` FOREIGN KEY (`store_id`)
        REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- product_name và unit_price được CHÉP LẠI tại thời điểm đặt hàng, không join
-- ngược về products để lấy. Sản phẩm có thể đổi giá hoặc bị gỡ khỏi catalog,
-- nhưng hoá đơn cũ phải giữ nguyên giá và tên lúc khách mua.
CREATE TABLE `order_items` (
    `id`           CHAR(36)     NOT NULL DEFAULT (UUID()),
    `order_id`     CHAR(36)     NOT NULL,
    `product_id`   CHAR(36)     NULL,
    -- Biến thể đã mua. variant_label CHÉP LẠI nhãn lúc mua, y như product_name:
    -- đổi tên hay xoá biến thể thì hoá đơn cũ vẫn đọc được đúng thứ khách mua.
    `variant_id`    CHAR(36)    NULL,
    `variant_label` VARCHAR(60) NULL,
    -- Tròng cắt kèm theo số đo khách. Ghi THẲNG vào dòng hàng chứ không tách
    -- thành một dòng riêng: tròng mài theo đơn kính không tồn tại độc lập với
    -- chiếc gọng nó được lắp vào, tách ra là mọi chỗ đếm "đơn có mấy sản phẩm"
    -- đều đếm gấp đôi.
    --
    -- `lens_id` trỏ vào bảng giá gói tròng ở config/taxonomy.php — một mảng
    -- PHP, không phải bảng, nên không có khoá ngoại. `lens_name` chép lại tên
    -- gói tại thời điểm mua, cùng lý do với `product_name` ngay bên dưới.
    --
    -- `lens_price` ĐÃ NẰM TRONG `unit_price`; cột này chỉ để tách ra khi cần
    -- in "gọng 2.890.000₫ + tròng 450.000₫". Nhờ vậy line_total = unit_price ×
    -- quantity giữ nguyên nghĩa ở mọi nơi đang đọc bảng này.
    --
    -- `prescription` NULL = khách chưa biết độ, đo tại cửa hàng.
    `lens_id`       VARCHAR(40)  NULL,
    `lens_name`     VARCHAR(160) NULL,
    `lens_price`    BIGINT       NOT NULL DEFAULT 0,
    `prescription`  VARCHAR(255) NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `unit_price`   BIGINT       NOT NULL,
    `quantity`     INT          NOT NULL DEFAULT 1,
    `line_total`   BIGINT       NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order` (`order_id`),
    KEY `idx_order_items_product` (`product_id`),
    KEY `idx_order_items_variant` (`variant_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_order_items_variant` FOREIGN KEY (`variant_id`)
        REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- LỊCH SỬ TRẠNG THÁI ĐƠN
--
-- Thanh tiến trình trong trang tài khoản ghi GIỜ dưới từng bước (đặt hàng ·
-- xác nhận · chuẩn bị · giao · nhận). Bảng `orders` chỉ có created_at (bước
-- đầu) và updated_at (bước hiện tại), nên các bước ở giữa sẽ trống mãi nếu
-- không ghi lại mỗi lần trạng thái đổi.
--
-- `changed_by` NULL = hệ thống tự ghi lúc đặt hàng; có giá trị = nhân viên đã
-- bấm đổi trong khu quản trị.
-- ----------------------------------------------------------------------------
CREATE TABLE `order_status_history` (
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

-- Sổ giao dịch chuyển khoản do SePay báo về qua webhook.
-- Xem migration 2026-08-22-sepay-doi-soat và config/sepay.php.
--
-- `sepay_id` UNIQUE là thứ chặn một lần chuyển tiền bị tính thành hai: SePay
-- gửi lại tối đa 7 lần nếu không nhận được HTTP 200, kể cả khi máy chủ đã xử
-- lý xong rồi mới chết lúc trả lời.
--
-- Giao dịch KHÔNG khớp đơn nào vẫn được ghi (order_id NULL): khách gõ sai nội
-- dung chuyển khoản là chuyện thường, và dòng đó là manh mối duy nhất để lần ra.
CREATE TABLE `sepay_transactions` (
    `id`               CHAR(36)     NOT NULL DEFAULT (UUID()),
    `sepay_id`         BIGINT       NOT NULL,
    `order_id`         CHAR(36)     NULL,
    `order_code`       VARCHAR(40)  NULL,
    `gateway`          VARCHAR(64)  NULL,
    `account_number`   VARCHAR(64)  NULL,
    `transfer_type`    VARCHAR(8)   NOT NULL DEFAULT 'in',
    `amount`           BIGINT       NOT NULL DEFAULT 0,
    `content`          TEXT         NULL,
    `reference_code`   VARCHAR(64)  NULL,
    `transaction_date` DATETIME     NULL,
    -- paid | deposit_paid | partial | no_order | ignored
    `applied`          VARCHAR(32)  NOT NULL DEFAULT 'no_order',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sepay_txn` (`sepay_id`),
    KEY `idx_sepay_order` (`order_id`),
    CONSTRAINT `fk_sepay_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- ĐÁNH GIÁ SẢN PHẨM
--
-- `author_name` chép lại tên lúc viết, KHÔNG join sang profiles mỗi lần hiện:
-- khách đổi tên hồ sơ thì đánh giá cũ vẫn mang tên lúc họ viết, và xoá tài
-- khoản không làm mất tên người đánh giá.
--
-- `order_id` là thứ chứng minh "Đã mua". NULL = nhân viên nhập hộ.
--
-- `status` mặc định 'pending': KHÔNG hiện ngay. Một ô nhập văn bản công khai
-- đăng thẳng lên trang sản phẩm là lời mời spam và bôi nhọ.
--
-- products.rating / review_count là SỐ TỔNG tính lại từ bảng này — xem
-- ReviewModel::recount(). Chúng vẫn tồn tại vì lưới sản phẩm cần đọc điểm mà
-- không phải gộp nhóm cả bảng đánh giá ở mỗi lần hiện trang.
-- ----------------------------------------------------------------------------
CREATE TABLE `reviews` (
    `id`            CHAR(36)     NOT NULL DEFAULT (UUID()),
    `product_id`    CHAR(36)     NOT NULL,
    `user_id`       CHAR(36)     NULL,
    `order_id`      CHAR(36)     NULL,
    `author_name`   VARCHAR(255) NOT NULL,
    `rating`        TINYINT      NOT NULL,
    `body`          TEXT         NOT NULL,
    `variant_label` VARCHAR(60)  NULL,
    `status`        VARCHAR(16)  NOT NULL DEFAULT 'pending',
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Mỗi đơn chỉ đánh giá mỗi mặt hàng một lần. MySQL cho nhiều dòng cùng
    -- NULL nên đánh giá nhân viên nhập hộ không bị chặn.
    UNIQUE KEY `uq_review_order_product` (`order_id`, `product_id`),
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

-- ============================================================================
-- 6. LIÊN HỆ
-- ============================================================================

-- ----------------------------------------------------------------------------
-- ĐĂNG KÝ NHẬN TIN (S20)
--
-- `email` là khoá UNIQUE: đăng ký lại bằng đúng địa chỉ cũ không tạo dòng mới,
-- model bắt lỗi trùng và báo "đã đăng ký rồi" thay vì lỗi hệ thống.
--
-- `source` ghi nơi khách bấm đăng ký (trang chủ, footer…) — sau này muốn biết
-- vị trí nào ra đơn thì có sẵn số liệu, thêm cột sau khi bảng đã đầy thì không
-- truy ngược được nữa.
--
-- `unsubscribed_at` thay cho việc xoá dòng: xoá hẳn thì lần sau import danh
-- sách cũ là gửi lại cho đúng người vừa từ chối.
-- ----------------------------------------------------------------------------
CREATE TABLE `newsletter_subscribers` (
    `id`              CHAR(36)     NOT NULL DEFAULT (UUID()),
    `email`           VARCHAR(255) NOT NULL,
    `source`          VARCHAR(64)  NOT NULL DEFAULT 'home',
    `unsubscribed_at` DATETIME     NULL,
    `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_newsletter_email` (`email`),
    KEY `idx_newsletter_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_requests` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    `full_name`  VARCHAR(255) NOT NULL,
    `phone`      VARCHAR(32)  NOT NULL,
    `email`      VARCHAR(255) NULL,
    `message`    TEXT         NOT NULL,
    `status`     VARCHAR(32)  NOT NULL DEFAULT 'new',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_requests_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. DỮ LIỆU KHỞI TẠO
--
-- Chỉ KHUNG để trang chạy được ngay sau khi cài: cơ sở (form đặt lịch và trang
-- Liên hệ cần có), danh mục (bộ lọc và điều hướng cần có) và vài bài sự kiện
-- làm mẫu bố cục.
--
-- KHÔNG có sản phẩm nào — xem ghi chú "KHÔNG SEED SẢN PHẨM" bên dưới. Catalog
-- nhập ở /quan-tri/san-pham.
-- ============================================================================

INSERT INTO `stores` (`code`, `name`, `address`, `phone`, `open_hours`, `map_url`) VALUES
('TAYHO', 'Vin Eyewear Tây Hồ', '46 Hoàng Hoa Thám, phường Tây Hồ, TP. Hà Nội', '0901 234 567', '08:00 - 21:00 hàng ngày', 'https://www.google.com/maps?q=46+Ho%C3%A0ng+Hoa+Th%C3%A1m+H%C3%A0+N%E1%BB%99i&output=embed'),
('LONGBIEN', 'Vin Eyewear Long Biên', '261 Ngọc Lâm, phường Bồ Đề, TP. Hà Nội', '0901 234 568', '08:00 - 21:00 hàng ngày', 'https://www.google.com/maps?q=261+Ng%E1%BB%8Dc+L%C3%A2m+H%C3%A0+N%E1%BB%99i&output=embed');

INSERT INTO `categories` (`slug`, `name`, `description`, `sort_order`) VALUES
('gong-kinh',      'Gọng kính',      'Gọng kính cận chính hãng nhiều kiểu dáng',        1),
('kinh-mat',       'Kính mát',       'Kính mát chống tia UV cho mọi khuôn mặt',         2),
('trong-kinh',     'Tròng kính',     'Tròng kính chiết suất cao, chống ánh sáng xanh',  3);

-- ----------------------------------------------------------------------------
-- KHÔNG SEED SẢN PHẨM — CỐ Ý
--
-- Trước đây chỗ này có 5 mặt hàng mẫu (VEW-T01…VEW-L05) kèm ba câu UPDATE gán
-- chúng vào bộ sưu tập. Bỏ hết: catalog là thứ cửa hàng tự nhập qua
-- /quan-tri/san-pham, và hàng mẫu trong file cài đặt gây ra đúng hai chuyện:
--
--   1. Cài xong là trên trang bán hàng đã có 5 món không tồn tại, giá không
--      thật. Quên xoá một món là khách đặt được một thứ cửa hàng không có.
--   2. Sửa giá/tên hàng mẫu trong admin xong, lần cài lại nào cũng quay về giá
--      cũ — vì nguồn thật của chúng nằm trong file này chứ không phải DB.
--
-- Cơ sở, danh mục và sự kiện thì VẪN seed: đó là khung để trang chạy được
-- ngay (form đặt lịch cần cơ sở, bộ lọc cần danh mục), không phải hàng bán.
--
-- Bản cài cũ đã có 5 món đó thì database/migrations/2026-08-20-bo-san-pham-mau.sql
-- dọn giúp.
-- ----------------------------------------------------------------------------

-- 3 sự kiện gốc dùng thời gian tương đối (now() + interval) để luôn có sự kiện
-- sắp diễn ra dù chạy seed vào ngày nào.
INSERT INTO `events` (`slug`, `title`, `category`, `excerpt`, `content`, `location`, `starts_at`, `ends_at`) VALUES
('kham-mat-mien-phi-thang-8', 'Khám mắt miễn phí cùng chuyên gia', 'SỰ KIỆN',
    'Đo khúc xạ và tư vấn tròng kính miễn phí tại cả hai cơ sở Vin Eyewear.',
    'Trong suốt chương trình, khách hàng được đo khúc xạ bằng thiết bị tự động, tư vấn 1-1 với kỹ thuật viên khúc xạ và nhận voucher 10% cho đơn tròng kính.',
    'Cả 2 cơ sở Vin Eyewear',
    DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 20 DAY)),

('uu-dai-gong-0-dong', 'Ưu đãi Gọng 0 đồng khi mua tròng cao cấp', 'TIN ƯU ĐÃI',
    'Tặng gọng kính trị giá tới 1.500.000đ khi mua tròng kính cao cấp.',
    'Áp dụng cho các dòng tròng chiết suất từ 1.60 trở lên. Số lượng gọng ưu đãi có hạn tại từng cơ sở.',
    'Cơ sở Tây Hồ',
    DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 12 DAY)),

('workshop-chon-gong-theo-khuon-mat', 'Workshop: Chọn gọng theo khuôn mặt', 'SỰ KIỆN',
    'Buổi workshop hướng dẫn chọn gọng kính phù hợp từng dáng khuôn mặt.',
    'Chuyên gia hình ảnh của Vin Eyewear sẽ phân tích tỉ lệ khuôn mặt và hướng dẫn cách chọn gọng, màu sắc phù hợp. Có thử kính AR trực tiếp.',
    'Cơ sở Long Biên',
    DATE_ADD(NOW(), INTERVAL 25 DAY), DATE_ADD(NOW(), INTERVAL 25 DAY));

-- 6 sự kiện biên tập bổ sung ở migration 20260805090053 — mốc thời gian cố định.
INSERT INTO `events` (`slug`, `title`, `category`, `excerpt`, `content`, `location`, `starts_at`, `ends_at`, `is_visible`, `cover_image`) VALUES
('uu-dai-mua-he-di-san', 'Chương Trình Ưu Đãi Mùa Hè: Giảm 20% Bộ Sưu Tập Di Sản', 'TIN ƯU ĐÃI',
    'Toàn bộ Bộ Sưu Tập Di Sản giảm 20% trong mùa hè này, kèm dịch vụ đo khúc xạ và tinh chỉnh gọng miễn phí.',
    'Trong suốt tháng 7 và tháng 8, Vin Eyewear dành tặng ưu đãi 20% cho toàn bộ Bộ Sưu Tập Di Sản.\nƯu đãi áp dụng tại cả hai cơ sở, kèm dịch vụ đo khúc xạ, tinh chỉnh gọng và vệ sinh kính miễn phí trọn đời.',
    'Cả 2 cơ sở Vin Eyewear', '2026-07-15 09:00:00', '2026-08-15 19:00:00', 1, '/assets/images/product-1.jpg'),

('cham-soc-kinh-nhua-nhap-khau', 'Buổi Chia Sẻ: Nghệ Thuật Chăm Sóc Và Bảo Quản Kính Nhựa Nhập Khẩu', 'SỰ KIỆN',
    'Kỹ thuật viên Vin Eyewear hướng dẫn cách vệ sinh, bảo quản và phục hồi độ bóng cho gọng acetate nhập khẩu.',
    'Một buổi chiều dành riêng cho những người yêu gọng acetate.\nChúng tôi chia sẻ quy trình vệ sinh chuẩn, cách tránh biến dạng gọng và mẹo giữ độ bóng bền lâu.',
    'Cơ sở 46 Hoàng Hoa Thám, Tây Hồ', '2026-07-26 14:00:00', NULL, 1, '/assets/images/product-2.jpg'),

('ra-mat-kinh-titan-sieu-nhe', 'Ra Mắt Dòng Kính Titan Siêu Nhẹ Mới Mùa Thu 2026', 'SẢN PHẨM MỚI',
    'Dòng gọng titan nguyên khối chỉ 9 gram, thiết kế tối giản dành cho người đeo kính cả ngày.',
    'Bộ sưu tập titan mùa thu 2026 tập trung vào sự nhẹ nhàng gần như vô hình.\nGọng nguyên khối, khớp bản lề không ốc, hoàn thiện nhám mờ với bốn sắc độ trung tính.',
    'Cả 2 cơ sở Vin Eyewear', '2026-09-05 10:00:00', NULL, 1, '/assets/images/product-3.jpg'),

('trien-lam-khung-kinh-qua-cac-thap-ky', 'Triển Lãm: Khung Kính Qua Các Thập Kỷ Ký Ức', 'TRIỂN LÃM',
    'Hơn 60 mẫu gọng từ thập niên 1950 đến nay, kể lại lịch sử thiết kế kính mắt bằng hiện vật.',
    'Triển lãm trưng bày hơn 60 mẫu gọng sưu tầm, sắp đặt theo từng thập kỷ.\nKhách tham quan có thể thử một số phiên bản phục dựng và nghe câu chuyện phía sau từng thiết kế.',
    'Cơ sở 261 Ngọc Lâm, Long Biên', '2026-09-18 09:00:00', '2026-09-30 19:00:00', 1, '/assets/images/product-4.jpg'),

('kham-thi-luc-tu-van-dang-kinh', 'Chương Trình Khám Thị Lực Và Tư Vấn Dáng Kính Miễn Phí', 'SỰ KIỆN',
    'Đo khúc xạ bằng thiết bị chuyên sâu và tư vấn dáng gọng theo khuôn mặt, hoàn toàn miễn phí.',
    'Đội ngũ kỹ thuật viên khúc xạ của Vin Eyewear thực hiện quy trình đo 8 bước.\nSau khi đo, bạn được tư vấn dáng gọng phù hợp với tỉ lệ khuôn mặt và nhu cầu sử dụng.',
    'Cả 2 cơ sở Vin Eyewear', '2026-08-20 09:00:00', '2026-08-31 19:00:00', 1, '/assets/images/product-5.jpg'),

('dem-tiec-nhuom-mau-trong-kinh', 'Đêm Tiệc Trải Nghiệm: Nhuộm Màu Tròng Kính Thủ Công', 'SỰ KIỆN',
    'Tự tay chọn sắc độ và nhuộm màu tròng kính của riêng bạn cùng kỹ thuật viên Vin Eyewear.',
    'Một buổi tối giới hạn 20 khách, nơi bạn tự chọn sắc độ và theo dõi toàn bộ quy trình nhuộm tròng thủ công.\nMỗi khách mang về một cặp tròng màu độc bản.',
    'Cơ sở 46 Hoàng Hoa Thám, Tây Hồ', '2026-10-10 19:00:00', NULL, 1, '/assets/images/product-6.jpg');

-- ============================================================================
-- 8. TÀI KHOẢN QUẢN TRỊ
--
-- CỐ Ý KHÔNG seed tài khoản admin ở đây.
--
-- File này nằm trong git. Một tài khoản admin có sẵn trong file này đồng nghĩa
-- mật khẩu của nó công khai với mọi người đọc được repo — kể cả khi chỉ ghi
-- hash, vì mật khẩu gốc luôn phải viết ra ở comment thì người cài mới đăng
-- nhập được. Deploy mà quên đổi là mất trắng quyền quản trị.
--
-- Tài khoản admin được tạo bởi database/setup.sh với mật khẩu ngẫu nhiên,
-- in ra màn hình đúng một lần lúc cài đặt. Cùng cách làm với mật khẩu MySQL.
--
-- Tạo thêm admin về sau:
--     php database/make-admin.php <email>
-- ============================================================================

-- ============================================================================
-- PHỤ LỤC — ĐỐI CHIẾU RLS POSTGRES SANG PHP
--
-- Postgres tự chặn ở tầng DB; MySQL không có cơ chế này nên mọi luật dưới đây
-- PHẢI được thực thi bằng code. Bỏ sót một dòng là lộ dữ liệu khách hàng.
--
--   Bảng             | Policy gốc              | Nơi thực thi trong bản PHP
--   -----------------+-------------------------+----------------------------------
--   profiles         | own profile r/w         | UserModel: luôn WHERE id = session
--   user_roles       | own roles read          | UserModel::rolesOf($userId)
--   prescriptions    | own prescription r/w    | UserModel: WHERE user_id = session
--   categories       | public (is_visible)     | CategoryModel::visible()
--                    | admin all               | AuthMiddleware: admin|manager
--   products         | public (is_visible)     | ProductModel: mặc định lọc is_visible
--                    | admin all               | AuthMiddleware: admin|manager
--   events           | public (is_visible)     | EventModel: mặc định lọc is_visible
--                    | admin all               | AuthMiddleware: admin|manager
--   stores           | public (is_active)      | StoreModel::active()
--                    | admin all               | AuthMiddleware: admin|manager
--   addresses        | own addresses r/w       | AddressModel: MỌI câu đều kèm user_id
--   user_vouchers    | own vouchers read       | VoucherModel::forUser($userId)
--   vouchers         | public (is_active)      | chỉ đọc qua user_vouchers đã lọc
--   order_status_history | theo đơn cha        | OrderModel::historyFor() sau khi
--                    |                         | đơn đã qua forUser()/findByCode()
--   favorites        | own favorites           | WHERE user_id = session
--   appointments     | own read / staff all    | BookingModel::forUser() vs ::all()
--   orders           | own read / staff all    | OrderModel::forUser() vs ::all()
--   order_items      | theo đơn cha / staff    | luôn join qua orders đã lọc quyền
--   contact_requests | staff only              | AuthMiddleware: admin|manager|staff
-- ============================================================================
