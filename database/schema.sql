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

-- Hai bảng của module Khách hàng (mục 7) — xoá trước vì chúng trỏ sang
-- `users`, `appointments` và `stores`.
DROP TABLE IF EXISTS `customer_audit_logs`;
DROP TABLE IF EXISTS `customer_prescriptions`;
DROP TABLE IF EXISTS `password_resets`;
-- Ba dòng dưới: không có bảng nào trỏ tới chúng nên vị trí trong danh sách này
-- không quan trọng — nhưng CÓ MẶT thì quan trọng. Thiếu một dòng DROP là câu
-- CREATE của chính bảng đó đổ "table already exists" và dừng cả lần cài lại,
-- đúng cái bẫy đã ghi ở đầu khối.
--
-- `stock_waitlist` và `sepay_transactions` bị SÓT từ trước — phát hiện ngày
-- 2026-09-02 khi chạy lại schema.sql lần thứ hai trên một CSDL đã cài: nó dừng
-- ở dòng 849 với lỗi 'stock_waitlist' already exists. Tức là từ lúc hai bảng ấy
-- ra đời, `schema.sql` KHÔNG còn cài lại được trên máy đã có dữ liệu; máy cài
-- mới thì không lộ ra vì chưa có bảng nào để đụng.
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `stock_waitlist`;
DROP TABLE IF EXISTS `sepay_transactions`;
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
DROP TABLE IF EXISTS `lens_packages`;
DROP TABLE IF EXISTS `lens_options`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `stores`;
-- `collections` VÀ `collection_faqs` — con trước, cha sau.
--
-- `collections` ĐÃ THIẾU Ở DANH SÁCH NÀY từ lúc nó ra đời (2026-08-25): file
-- mở đầu bằng DROP cho mọi bảng rồi CREATE lại từ đầu, nên một bảng không được
-- DROP sẽ làm câu CREATE của chính nó đổ "table already exists" và dừng cả lần
-- cài lại. Không ai gặp vì chưa ai chạy setup.sh lần hai trên máy đã có dữ
-- liệu. Thêm vào đây cùng lúc với bảng FAQ trỏ vào nó.
DROP TABLE IF EXISTS `site_texts`;
DROP TABLE IF EXISTS `collection_faqs`;
DROP TABLE IF EXISTS `collections`;
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
     * TRẠNG THÁI TÀI KHOẢN — 'active' | 'locked'.
     *
     * Đặt ở `users` chứ không ở `profiles` vì khoá tài khoản phải chặn được
     * ĐĂNG NHẬP, mà đường đăng nhập đọc bảng này (UserModel::findByLogin,
     * ::attempt, ::findOrCreateGoogle, RememberModel::consume). Để ở
     * `profiles` thì nút khoá chỉ đổi một con chữ trên màn hình quản trị còn
     * người bị khoá vẫn vào được — tệ hơn cả không có nút, vì nhân viên tin
     * là đã khoá rồi.
     *
     * VARCHAR chứ không ENUM: thêm giá trị sau này không phải ALTER TABLE
     * khoá bảng. Cùng lẽ với `orders`.`payment_status`.
     */
    `status`          VARCHAR(16)  NOT NULL DEFAULT 'active',
    /*
     * Lý do khoá — CHỈ ĐỌC TRONG KHU QUẢN TRỊ, không bao giờ hiện cho khách.
     * Khách bị khoá chỉ thấy đúng câu "Tài khoản đã bị khoá. Vui lòng liên hệ
     * cửa hàng."; đọc lý do cho họ nghe là việc của người trả lời điện thoại,
     * ở đó còn cân nhắc được nên nói gì.
     *
     * Bắt buộc phải có giá trị khi khoá, nhưng ép ở tầng PHP chứ không bằng
     * CHECK constraint: MySQL 8 và MariaDB xử lý CHECK khác nhau mà dự án
     * chạy trên cả hai.
     */
    `locked_reason`   VARCHAR(255) NULL,
    `locked_at`       DATETIME     NULL,
    `locked_by`       CHAR(36)     NULL,
    /*
     * XOÁ MỀM. NULL = còn dùng.
     *
     * Tách riêng khỏi `status` chứ không gộp thành status = 'deleted': khoá và
     * xoá chồng lên nhau chứ không loại trừ nhau — một tài khoản bị khoá vì
     * gian lận rồi mới xoá thì vẫn phải đọc được lý do khoá. Cột này còn mang
     * theo MỐC THỜI GIAN, thứ một giá trị 'deleted' không có.
     *
     * Vì sao không xoá cứng: `orders`.`user_id` là ON DELETE SET NULL, nên xoá
     * cứng một khách là làm đơn hàng của họ mất chủ vĩnh viễn.
     */
    `deleted_at`      DATETIME     NULL,
    /*
     * Vì sao xoá. Không bắt buộc, khác hẳn `locked_reason` ở trên.
     *
     * CỘT NÀY CÓ TRƯỚC MODULE KHÁCH HÀNG. Nó do migration
     * 2026-08-22-xoa-tai-khoan.sql tạo ra — bản "khách tự yêu cầu xoá tài
     * khoản", commit 0628170, bị revert bốn phút sau bằng 7e14d0d vì cả site
     * trắng trang. Revert gỡ được mã nguồn nhưng KHÔNG gỡ được cột đã tạo
     * trong cơ sở dữ liệu đang chạy, nên từ đó tới 26/08/2026 nó nằm lại đây
     * không ai đọc, không ai ghi, và không có trong file này.
     *
     * Module Khách hàng nhận nó về dùng đúng việc nó được đặt tên: lý do một
     * tài khoản bị xoá. Khai lại ở đây để máy cài mới khớp với máy đang chạy —
     * lệch lược đồ giữa hai bên là thứ chỉ lộ ra lúc deploy.
     *
     * VARCHAR(500) chứ không 255 như `locked_reason`: giữ đúng độ dài cột cũ.
     * Nới hay siết một cột đang có dữ liệu là việc riêng, không lẫn vào đây.
     */
    `deletion_reason` VARCHAR(500) NULL,
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
    UNIQUE KEY `uq_users_google` (`google_id`),
    -- Trang danh sách khách lọc theo cả hai cột này ở MỌI lượt tải — kể cả
    -- lượt không lọc gì, vì luôn phải loại tài khoản đã xoá mềm.
    KEY `idx_users_status` (`status`, `deleted_at`),
    CONSTRAINT `fk_users_locked_by` FOREIGN KEY (`locked_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
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
-- ĐẾM ĐĂNG NHẬP HỎNG VÀ KHOÁ TẠM THỜI (SNFR-06)
--
-- SNFR-06: "tài khoản tạm thời bị khóa 15 phút nếu nhập sai mật khẩu quá 5 lần
-- liên tiếp". Áp cho CẢ HAI cổng đăng nhập, vì cả hai đi qua UserModel::attempt.
--
-- VÌ SAO LÀ MỘT BẢNG RIÊNG CHỨ KHÔNG PHẢI HAI CỘT THÊM VÀO `users`
--
-- Đây là điểm dễ làm sai nhất. Đếm trên `users` thì chỉ đếm được cho tài khoản
-- CÓ THẬT: kẻ dò gõ 5 lần sai vào một email bất kỳ, thấy câu "tạm khoá" là biết
-- email đó có tài khoản ở đây, thấy "sai thông tin" là biết không có. Cái khoá
-- dựng lên để chặn dò mật khẩu lại tặng không một máy tra cứu danh sách khách
-- hàng — đúng thứ mà UserModel::attempt() và AdminAuthController::login() đã
-- cẩn thận tránh bằng cách dùng chung một câu báo lỗi cho mọi ca hỏng.
--
-- Đếm theo chuỗi định danh thì email không tồn tại cũng bị khoá y hệt, nên câu
-- trả lời của hệ thống không nói lên điều gì về việc tài khoản có tồn tại.
--
-- KHOÁ CHÍNH LÀ BĂM SHA-256 của định danh đã hạ chữ thường, không phải định
-- danh nguyên văn: bảng này sẽ chứa email và số điện thoại của những người GÕ
-- NHẦM, tức phần lớn là khách hàng thật. Cất nguyên văn là dựng thêm một bản
-- danh sách liên hệ nữa nằm ngoài `profiles`, với đúng một công dụng là đếm.
--
-- KHÔNG có khoá ngoại sang `users`: cả điểm của bảng này là ghi được cả những
-- định danh không ứng với tài khoản nào.
-- ----------------------------------------------------------------------------
CREATE TABLE `login_attempts` (
    -- sha256 của strtolower(trim(<định danh>)) — KHÔNG dùng mb_*, máy chủ không có mbstring — xem LoginAttemptModel::khoa()
    `login_key`    CHAR(64)          NOT NULL,
    -- Số lần sai LIÊN TIẾP. Về 0 ngay khi đặt khoá, để hết 15 phút là người ta
    -- có lại đủ 5 lần thử chứ không bị khoá lại ở lần sai đầu tiên.
    `fails`        TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    -- NULL = đang được phép thử.
    `locked_until` DATETIME          NULL,
    `updated_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`login_key`),
    -- Cho LoginAttemptModel::donCu() quét theo mốc cập nhật mà không phải đọc
    -- cả bảng. Hosting không có cron nên việc dọn ăn theo lượt truy cập, và nó
    -- phải rẻ.
    KEY `idx_login_attempts_updated` (`updated_at`)
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
-- ----------------------------------------------------------------------------
-- GÓI CHIẾT SUẤT
--
-- Danh mục gói tròng khách chọn khi cắt kèm gọng. Ở trong CSDL chứ không ở
-- config vì cửa hàng tự thêm/sửa/xoá tại /quan-tri/gia-trong/goi — nhập phôi
-- mới hay ngừng bán một loại là việc của cửa hàng, không phải việc phải sửa mã
-- rồi triển khai lại.
--
-- `id` là MÃ DẠNG CHỮ ('clear-150'…), không phải UUID: order_items.lens_id và
-- lens_prices.lens_package đã lưu sẵn đúng những mã này. Xem
-- database/migrations/2026-08-27-bang-goi-trong.sql.
--
-- Không có khoá ngoại nào trỏ vào đây, cũng cố ý — lý do đầy đủ ở file trên.
-- ----------------------------------------------------------------------------
CREATE TABLE `lens_packages` (
    `id`          VARCHAR(40)  NOT NULL,
    -- 160 để khớp order_items.lens_name, chỗ tên này được chép sang lúc đặt hàng
    `name`        VARCHAR(160) NOT NULL,
    `description` VARCHAR(255) NULL,
    -- Thứ tự hiện cho khách; cách nhau 10 để chèn vào giữa không phải đánh số lại
    `sort_order`  SMALLINT     NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lens_packages_sort` (`sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- THUỘC TÍNH TRÒNG KÍNH DO QUẢN TRỊ QUẢN LÝ
--
-- Bốn danh sách lựa chọn của bộ lọc trang /san-pham/trong-kinh: loại tròng,
-- chiết suất, màu tròng, tính năng/lớp phủ. Trước 2026-08-30 cả bốn là mảng gõ
-- cứng trong config/eyewear.php và config/taxonomy.php — cửa hàng muốn thêm
-- một màu tròng phải nhờ người sửa mã rồi deploy.
--
-- KHÔNG có cột nào thêm vào `products`: chỗ lưu LỰA CHỌN của từng sản phẩm đã
-- có sẵn từ trước (lens_types · lens_indexes · lens_color · lens_coatings).
-- Bảng này chỉ định nghĩa DANH SÁCH CHỌN.
--
-- MỘT BẢNG CHO CẢ BỐN NHÓM vì cấu trúc y hệt nhau. Thêm nhóm thứ năm sau này
-- chỉ là thêm một hằng trong LensOptionModel::GROUPS, không phải đổi lược đồ.
-- CSDL không tự chặn được việc gán nhầm nhóm; chặn ở tầng mã là đủ vì không có
-- đường nào ghi vào bảng này ngoài màn quản trị.
--
-- `option_key` LÀ THỨ ĐI VÀO CỘT CỦA `products` NÊN KHÔNG ĐƯỢC ĐỔI. Đổi khoá
-- của một lựa chọn đã có hàng gắn vào là làm mồ côi toàn bộ số hàng ấy — chúng
-- giữ khoá cũ trong CSV rồi biến mất khỏi bộ lọc mà không báo gì. Màn quản trị
-- chỉ cho sửa NHÃN. Cùng lý do, gỡ một mục là ẨN (`is_visible = 0`) chứ không
-- xoá.
--
-- Dữ liệu khởi tạo nằm ở cuối file, cùng chỗ với seed của lens_packages.
-- ----------------------------------------------------------------------------
CREATE TABLE `lens_options` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    -- 'loai-trong' · 'chiet-suat' · 'mau-trong' · 'lop-phu'
    `group_key`  VARCHAR(32)  NOT NULL,
    `option_key` VARCHAR(64)  NOT NULL,
    `label`      VARCHAR(120) NOT NULL,
    -- Câu mô tả ngắn, chỉ dùng ở form quản trị để người nhập biết chọn cái nào.
    `note`       VARCHAR(255) NULL,
    -- Cách nhau 10 để chèn vào giữa không phải đánh số lại.
    `sort_order` SMALLINT     NOT NULL DEFAULT 0,
    `is_visible` TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Duy nhất TRONG một nhóm, không phải trên cả bảng.
    UNIQUE KEY `uniq_lens_options_key` (`group_key`, `option_key`),
    KEY `idx_lens_options_sort` (`group_key`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    -- Kiểu viền: full-rim / half-rim / rimless. Tách khỏi `frame_shape` vì
    -- "gọng vuông không viền" là hai thuộc tính, không phải một.
    `rim_type`         VARCHAR(20)  NULL,
    `material`         VARCHAR(120) NULL,
    `color`            VARCHAR(120) NULL,
    -- `color` ở trên là MÀU GỌNG. Kính râm luôn có cả hai màu.
    `lens_color`       VARCHAR(120) NULL,
    `gender`           VARCHAR(20)  NULL,
    -- collection: bộ sưu tập theo mùa (S09). Khớp 'slug' trong
    --             config/collections.php. NULL = không thuộc bộ nào.
    `collection`       VARCHAR(64)  NULL,
    -- Nhãn tự do, ngăn bằng dấu phẩy: "bestseller, mùa hè, quà tặng".
    `tags`             VARCHAR(255) NULL,
    `description`      TEXT         NULL,
    -- Đoạn 2 dòng cho thẻ sản phẩm, tách khỏi `description` (đoạn dài cho
    -- trang chi tiết): cắt cụt đoạn dài để làm đoạn ngắn thì luôn cắt giữa câu.
    `description_short` VARCHAR(500) NULL,
    -- specs: cặp nhãn-giá trị hiển thị ở bảng thông số trang chi tiết
    --        vd {"Vật liệu":"Titan","Kích thước":"52-18-140"}
    `specs`            JSON         NULL,
    -- images: mảng đường dẫn ảnh, phần tử đầu là ảnh đại diện
    `images`           JSON         NULL,
    -- Alt text để RIÊNG, ánh xạ đường-dẫn → chữ. Không nhét vào `images`:
    -- cột ấy được đọc ở rất nhiều chỗ, chỗ nào cũng coi phần tử là một chuỗi.
    `image_alts`       JSON         NULL,
    `video_url`        VARCHAR(500) NULL,
    `price`            BIGINT       NOT NULL DEFAULT 0,
    `compare_at_price` BIGINT       NULL,
    -- Giá vốn CHỈ để tính lãi, không bao giờ in ra trang bán hàng. Mọi câu
    -- SELECT * đều kéo nó theo — chỗ nào in dữ liệu sản phẩm ra ngoài phải tự loại.
    `cost_price`       BIGINT       NULL,
    -- Khuyến mãi CÓ HẠN: sale_price một mình thì không ai biết bao giờ tắt.
    `sale_price`       BIGINT       NULL,
    `sale_from`        DATE         NULL,
    `sale_to`          DATE         NULL,
    `stock_quantity`   INT          NOT NULL DEFAULT 0,
    -- Ngưỡng "sắp hết" riêng từng mặt hàng; NULL = dùng ngưỡng chung trang Tồn kho.
    `low_stock_at`     INT          NULL,
    `allow_backorder`  TINYINT(1)   NOT NULL DEFAULT 0,
    `status`           VARCHAR(32)  NOT NULL DEFAULT 'in_stock',
    `is_featured`      TINYINT(1)   NOT NULL DEFAULT 0,
    `is_visible`       TINYINT(1)   NOT NULL DEFAULT 1,
    -- Hiện / Ẩn / Nháp — quyết định của người biên tập, KHÁC `status` ở trên
    -- (in_stock/out_of_stock, suy ra từ tồn kho và không cho nhập tay).
    -- `is_visible` được đồng bộ theo cột này: visible → 1, hidden và draft → 0.
    -- Trang bán hàng vẫn lọc theo `is_visible` nên không phải biết cột này.
    `publish_status`   VARCHAR(16)  NOT NULL DEFAULT 'visible',

    -- ┌─ THÔNG SỐ KÍNH MẮT ─────────────────────────────────────────────────
    -- │ Thêm 2026-08-27 cho trang chi tiết bộ sưu tập (khung ba lớp).
    -- │ Xem migrations/2026-08-27-bo-suu-tap-khung-ba-lop.sql.
    -- │
    -- │ Cột nào TRỐNG thì trang bỏ hẳn dòng đó, không in dấu gạch — nên một
    -- │ mặt hàng chưa nhập gì vẫn hiện bình thường, chỉ ngắn hơn.
    -- └────────────────────────────────────────────────────────────────────
    -- eyewear_type: 'gong-can' | 'kinh-ram' | 'da-dung' (config/eyewear.php).
    --               KHÔNG phải categories — xem chú thích trong file đó.
    `eyewear_type`     VARCHAR(20)  NULL,
    `frame_finish`     VARCHAR(120) NULL,
    `hinge_type`       VARCHAR(120) NULL,
    `nose_pad`         VARCHAR(120) NULL,
    `weight_g`         SMALLINT UNSIGNED NULL,
    -- Ba số của chuẩn ghi 52□18-145. Lưu RỜI để so sánh và lọc được;
    -- chuỗi hiển thị do EyewearSpecs::size() ghép lại, không có cột riêng.
    `lens_width_mm`    TINYINT UNSIGNED  NULL,
    `bridge_mm`        TINYINT UNSIGNED  NULL,
    `temple_mm`        TINYINT UNSIGNED  NULL,
    -- S/M/L do người nhập CHỌN. Bảng quy đổi ở config/eyewear.php cần
    -- `frame_width_mm` mà form không còn hỏi, và nó cố ý bỏ trống khi số đo
    -- ngoài dải — nên vẫn phải có chỗ để nói thẳng.
    `size_class`       CHAR(1)      NULL,
    -- frame_width_mm nuôi phép quy đổi cỡ S/M/L (ngưỡng ở config/eyewear.php)
    `frame_width_mm`   SMALLINT UNSIGNED NULL,
    `lens_height_mm`   TINYINT UNSIGNED  NULL,
    -- face_shapes: CSV khoá chuẩn — 'tron,trai-xoan'. Bảng "gọng theo dáng
    --              mặt" trên trang bộ sưu tập dựng từ cột này của cả bộ.
    `face_shapes`      VARCHAR(160) NULL,
    `lens_material`    VARCHAR(120) NULL,
    -- DECIMAL chứ không VARCHAR: "1,61" và "1.610" là hai cách gõ sai cùng
    -- một số, và bảng so sánh sắp theo cột này.
    `lens_index`       DECIMAL(3,2) NULL,
    -- CSV chiết suất ĐẶT THÊM được: 1.56,1.61,1.67,1.74. Khác `lens_index`
    -- ở trên — cột đó tả chiết suất của tròng đi kèm sẵn trong hộp.
    `lens_indexes`     VARCHAR(60)  NULL,
    -- Chuỗi chứ không DECIMAL: người nhập gõ "-8.00" và dấu âm là phần không
    -- được mất. Số đo mắt luôn viết kèm dấu.
    `sph_max`          VARCHAR(10)  NULL,
    `cyl_max`          VARCHAR(10)  NULL,
    -- lens_coatings: CSV khoá chuẩn — 'uv400,chong-loa,chong-tray'
    `lens_coatings`    VARCHAR(255) NULL,
    `is_polarized`     TINYINT(1)   NOT NULL DEFAULT 0,
    -- Trước nằm trong CSV `lens_coatings`. Tách ra cột riêng để lọc bằng SQL:
    -- UV400 và phân cực là hai thứ khách hỏi nhiều nhất về kính râm.
    `is_uv400`         TINYINT(1)   NOT NULL DEFAULT 0,
    `is_photochromic`  TINYINT(1)   NOT NULL DEFAULT 0,
    -- VARCHAR chứ không số: tròng đổi màu có hai đầu ("18% → 62%")
    `lens_vlt`         VARCHAR(40)  NULL,
    -- lens_category: 0..4 theo ISO 12312-1. Cấp 4 KHÔNG được lái xe.
    `lens_category`    TINYINT UNSIGNED NULL,
    `base_curve`       VARCHAR(20)  NULL,
    -- rx_ready là CỜ để lọc, rx_note là câu để giải thích ("tới -6.00")
    `rx_ready`         TINYINT(1)   NOT NULL DEFAULT 0,
    -- KHÁC `rx_ready`: cột trên là "gọng lắp được tròng cận" (thuộc tính vật
    -- lý), cột này là "cửa hàng có nhận đặt kèm tròng cho mẫu này không"
    -- (quyết định kinh doanh). Lắp được nhưng hết tròng phù hợp thì 1 và 0.
    `rx_order_enabled` TINYINT(1)   NOT NULL DEFAULT 0,
    -- CSV: don-trong, da-trong, doi-mau, anh-sang-xanh.
    `lens_types`       VARCHAR(120) NULL,
    `rx_note`          VARCHAR(255) NULL,
    `price_with_lens`  BIGINT       NULL,
    -- Bốn cột dưới TRỐNG nghĩa là "theo chính sách chung"
    -- (config/eyewear.php → defaults). Chỉ điền khi mặt hàng phải nói KHÁC.
    `accessories`      VARCHAR(255) NULL,
    `warranty`         VARCHAR(255) NULL,
    `return_policy`    VARCHAR(255) NULL,
    `certifications`   VARCHAR(255) NULL,
    `barcode`          VARCHAR(40)  NULL,

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
    -- Bản vẽ cho mỗi biến thể một dòng màu · size · SKU · giá · tồn · ảnh.
    -- `label` vẫn NOT NULL và vẫn là khoá UNIQUE — form tự ghép nó từ hai
    -- cột dưới ("Đen nhám · M") thay vì bắt gõ lại.
    `color`          VARCHAR(60)  NULL,
    `size`           CHAR(1)      NULL,
    `sku`            VARCHAR(64)  NULL,
    `note`           VARCHAR(120) NULL,
    -- Hai cột dưới chỉ có nghĩa với phương án MÀU; phương án chiết suất tròng
    -- hay cỡ thì để NULL, và ngăn kéo thông số chỉ vẽ ô màu khi có mã màu.
    `swatch_hex`     VARCHAR(7)   NULL,
    `image`          VARCHAR(500) NULL,
    `price_delta`    BIGINT       NOT NULL DEFAULT 0,
    -- Giá TUYỆT ĐỐI, khác `price_delta` là CHÊNH LỆCH. NULL = không đặt giá
    -- riêng, vẫn tính theo products.price + price_delta như cũ.
    `price`          BIGINT       NULL,
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

-- ----------------------------------------------------------------------------
-- DANH SÁCH CHỜ HÀNG — khách để lại liên lạc để được báo khi hàng về.
--
-- GẮN THEO BIẾN THỂ, KHÔNG PHẢI THEO CẢ MẶT HÀNG. Người chờ gọng màu đen size
-- 52 không quan tâm màu nâu vừa về; gắn theo mặt hàng thì phần lớn tin nhắn
-- gửi đi là tin không liên quan tới người nhận, và vài lần như thế là không ai
-- đọc nữa. `variant_id` để NULL khi mặt hàng KHÔNG có biến thể nào — đó là
-- trường hợp thật, không phải dữ liệu thiếu.
--
-- HAI CÁCH LIÊN LẠC, CẦN ÍT NHẤT MỘT (tầng ứng dụng bắt — xem
-- ProductDetailController::waitlist). Phải có số điện thoại chứ không chỉ
-- email vì hosting hiện tại KHÔNG GỬI ĐƯỢC EMAIL: InfinityFree bản miễn phí
-- vô hiệu hoá mail() và chặn cổng SMTP, .env.production để MAIL_DRIVER=log.
-- Việc báo tin hôm nay là việc của người — nhân viên mở /quan-tri/cho-hang rồi
-- gọi hoặc nhắn Zalo. Bảng dựng sẵn đúng hình để ngày có kênh tự động thì chỉ
-- thêm chỗ gửi, không phải đổi lược đồ.
--
-- KHÔNG DÙNG UNIQUE KEY ĐỂ CHỐNG TRÙNG: MySQL coi mỗi NULL là một giá trị
-- khác nhau trong khoá duy nhất, mà cả `variant_id` lẫn một trong hai cột liên
-- lạc đều có thể NULL — khoá ấy sẽ cho cùng một người đăng ký lại vô số lần.
-- Phép chống trùng ở WaitlistModel::daDangKy(), dùng COALESCE.
-- ----------------------------------------------------------------------------
CREATE TABLE `stock_waitlist` (
    `id`          CHAR(36)     NOT NULL DEFAULT (UUID()),
    `product_id`  CHAR(36)     NOT NULL,
    `variant_id`  CHAR(36)     NULL,
    `email`       VARCHAR(190) NULL,
    `phone`       VARCHAR(20)  NULL,
    -- Đã báo cho người này chưa, và lúc nào. NULL = đang chờ.
    `notified_at` DATETIME     NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_waitlist_cho` (`notified_at`, `created_at`),
    KEY `idx_waitlist_product` (`product_id`),
    KEY `idx_waitlist_variant` (`variant_id`),
    CONSTRAINT `fk_waitlist_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_waitlist_variant` FOREIGN KEY (`variant_id`)
        REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bộ sưu tập theo mùa.
--
-- `slug` là thứ nối bảng này với `products.collection` và với mọi link
-- /san-pham?collection=<slug> đã phát ra ngoài. ĐỪNG đổi slug của một bộ đã
-- phát hành — đổi là làm chết cả hai.
--
-- Trước 2026-08-25 dữ liệu này nằm trong config/collections.php. Chuyển sang
-- CSDL để nhân viên tự thêm/sửa/ẩn trong khu quản trị thay vì phải sửa mã và
-- deploy mỗi lần cửa hàng ra bộ mới.
--
-- `tagline` (một dòng, cho thẻ trang chủ và mega menu) tách khỏi `intro`
-- (đoạn dài, cho trang /bo-suu-tap): hai chỗ cần độ dài rất khác nhau.
--
-- `sort_order` vì sắp theo `launched_at` không đủ — hai bộ có thể ra cùng
-- ngày, hoặc cửa hàng muốn đẩy một bộ cũ lên đầu vì còn hàng.
CREATE TABLE `collections` (
    `id`          CHAR(36)     NOT NULL DEFAULT (UUID()),
    `slug`        VARCHAR(64)  NOT NULL,
    `name`        VARCHAR(160) NOT NULL,
    `tagline`     VARCHAR(255) NULL,
    `intro`       TEXT         NULL,
    -- `intro` là MỘT đoạn (thẻ ở /bo-suu-tap), `story` là NHIỀU đoạn và chỉ
    -- hiện ở trang chi tiết /bo-suu-tap/{slug}. Tách ra vì hai chỗ có kích
    -- thước khác hẳn nhau — xem migrations/2026-08-27-bo-suu-tap-trang-chi-tiet.sql.
    `story`       TEXT         NULL,

    -- ┌─ KHUNG THÔNG TIN CẤP BỘ ────────────────────────────────────────────
    -- │ Thêm 2026-08-27 cùng trang chi tiết. Mọi cột đều NULL được: bộ chưa
    -- │ nhập gì thì trang bỏ hẳn khối tương ứng chứ không vẽ ô rỗng.
    -- └────────────────────────────────────────────────────────────────────
    -- season_code là mã ngắn cho huy hiệu ("SS26"), season_label là dòng chữ
    -- đọc được ("Xuân–Hè 2026"). Hai chỗ hiển thị khác nhau, hai cột.
    `season_code`  VARCHAR(12)  NULL,
    `season_label` VARCHAR(60)  NULL,
    -- brand ở đây là hãng đứng tên CẢ BỘ, khác products.brand của từng mẫu.
    `brand`        VARCHAR(120) NULL,
    `product_line` VARCHAR(120) NULL,
    `designed_in`  VARCHAR(120) NULL,
    `made_in`      VARCHAR(120) NULL,
    -- audience: [{"tieu_de","gia_tri","ghi_chu"}] — bốn ô "bộ này hợp với ai",
    --           trong đó ô cuối cố ý nói ai ĐỪNG mua.
    `audience`     JSON         NULL,
    `design_style` VARCHAR(160) NULL,
    -- palette: [{"ten","ma_mau"}] — ô màu chủ đạo
    `palette`      JSON         NULL,
    -- signature: ["câu ngắn", ...] — chi tiết nhận diện đặc trưng
    `signature`    JSON         NULL,
    `launch_offer` VARCHAR(255) NULL,
    `channels`     VARCHAR(255) NULL,
    -- Trống thì trang tự suy từ name/tagline — xem CollectionController::show
    `meta_title`       VARCHAR(255) NULL,
    `meta_description` VARCHAR(320) NULL,

    -- `cover_image` LÀ CỘT CHẾT từ 2026-08-28. Mã không ghi vào nó nữa; nó chỉ
    -- còn là lưới an toàn cho dòng có ảnh bìa mà `images` lại rỗng. Giữ lại
    -- một thời gian rồi dọn bằng một migration riêng — xem
    -- migrations/2026-08-28-bo-suu-tap-nhieu-anh.sql.
    `cover_image` VARCHAR(500) NULL,
    -- images: mảng đường dẫn ảnh lookbook, PHẦN TỬ ĐẦU là ảnh đại diện —
    -- cùng quy ước với `products`.`images`. Không có cột "ảnh nào là bìa"
    -- riêng: đổi bìa là đưa ảnh đó lên đầu mảng.
    `images`      JSON         NULL,
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


-- ----------------------------------------------------------------------------
-- CÂU HỎI THƯỜNG GẶP CỦA MỘT BỘ SƯU TẬP
--
-- Thứ DUY NHẤT trong "lớp nội dung hỗ trợ" của trang chi tiết phải có bảng.
-- Ba khối còn lại (cách đo gọng cũ, hướng dẫn bảo quản, ngưỡng cỡ S/M/L)
-- giống hệt nhau ở mọi bộ nên nằm ở config/eyewear.php.
--
-- FAQ thì không: "kính râm bộ này lắp được độ cận không" chỉ có nghĩa với một
-- bộ toàn kính râm, và câu trả lời nhắc đích danh mẫu nào lắp được tới bao
-- nhiêu độ. Để trong config là bắt người viết nội dung sửa mã và deploy mỗi
-- lần cửa hàng ra bộ mới — đúng cái mà bảng `collections` đã bỏ đi hôm
-- 2026-08-25.
--
-- CASCADE: xoá bộ là xoá câu hỏi của nó. Không còn bộ thì câu trả lời "bốn
-- trong sáu mẫu lắp được" không còn gì để nói về.
-- ----------------------------------------------------------------------------
CREATE TABLE `collection_faqs` (
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
-- CHỮ TRÊN TRANG DO CỬA HÀNG TỰ SỬA
--
-- Khoá dạng `<trang>.<chỗ>` ('bo-suu-tap.tieu_de'), một DÒNG cho mỗi câu chữ
-- thay vì một CỘT — thêm chữ cho trang khác không cần migration đổi lược đồ.
--
-- Nơi đọc luôn truyền sẵn câu mặc định (xem SiteTextModel), nên bảng trống hay
-- khoá gõ sai thì trang vẫn hiện đúng chữ cũ chứ không để lại khoảng trắng.
--
-- Cột tên `text_key` vì `KEY` là từ khoá của MySQL — xem
-- migrations/2026-08-27-noi-dung-trang-tong-quan.sql.
-- ----------------------------------------------------------------------------
CREATE TABLE `site_texts` (
    `text_key`   VARCHAR(64) NOT NULL,
    `value`      TEXT        NOT NULL,
    `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`text_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- 3. CƠ SỞ
--
-- Bảng `events` từng đứng đầu mục này. Bỏ 2026-08-26 cùng cả tính năng sự
-- kiện; máy đang chạy dọn bằng database/migrations/2026-08-26-bo-su-kien.sql.
-- ============================================================================

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
-- CHỈ CÓ NGÀY, KHÔNG CÓ GIỜ. Khách chọn ngày; giờ được thống nhất trong cuộc
-- gọi xác nhận của cửa hàng và không lưu lại ở đâu cả.
--
-- Chú thích cũ ở chỗ này mô tả một khoá UNIQUE (cơ sở, ngày, khung giờ) cho
-- mỗi khung giờ đúng một lịch. Khoá đó đã bị bỏ từ 2026-08-22 và cột khung giờ
-- bị bỏ nốt ngày 2026-08-25 — xem hai migration cùng tên ở database/migrations.
CREATE TABLE `appointments` (
    `id`               CHAR(36)     NOT NULL DEFAULT (UUID()),
    `code`             VARCHAR(40)  NOT NULL,
    `user_id`          CHAR(36)     NULL,
    `store_id`         CHAR(36)     NOT NULL,
    `appointment_date` DATE         NOT NULL,
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
     * Từ 2026-08-25 bảng này không còn cột giờ nào nữa: lịch hẹn chỉ có NGÀY.
     * Cửa hàng ghi nhận rồi gọi điện xác nhận và tự xếp người — cái chốt thật
     * nằm ở cuộc gọi đó, không nằm trong CSDL.
     *
     * Xem database/migrations/2026-08-22-bo-gioi-han-khung-gio.sql và
     * database/migrations/2026-08-25-bo-han-cot-khung-gio.sql.
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
    -- Phản hồi CÔNG KHAI của cửa hàng, hiện ngay dưới đánh giá ở trang sản
    -- phẩm. NULL = chưa từng trả lời; chuỗi rỗng = đã trả lời rồi xoá chữ đi.
    -- Hai chuyện khác nhau, nên chỗ hiển thị kiểm cả hai.
    `reply`         TEXT         NULL,
    -- Mốc trả lời — KHÔNG suy từ `updated_at` được: cột đó đổi theo mọi lần
    -- sửa dòng, kể cả lúc nhân viên bấm Duyệt.
    `replied_at`    DATETIME     NULL,
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
    /*
     * Tài khoản đã gửi yêu cầu, NULL nếu khách chưa đăng nhập.
     *
     * Ba bảng hoạt động khác của khách (`orders`, `appointments`, `reviews`)
     * đều đã có cột này; thiếu ở đây thì tab "Hoạt động" của module Khách hàng
     * khuyết mất một mục. So bằng số điện thoại lúc đọc KHÔNG thay thế được:
     * cột `phone` ngay dưới lưu nguyên văn khách gõ, còn `profiles`.`phone` đã
     * qua normalizePhone().
     *
     * SET NULL chứ không CASCADE: xoá tài khoản không được xoá yêu cầu liên
     * hệ, vì module Liên hệ có hàng đợi riêng và nhân viên bên đó đang xử lý.
     */
    `user_id`    CHAR(36)     NULL,
    `full_name`  VARCHAR(255) NOT NULL,
    `phone`      VARCHAR(32)  NOT NULL,
    `email`      VARCHAR(255) NULL,
    `message`    TEXT         NOT NULL,
    /*
     * MỐC ĐẨY SANG ZALO CSKH. NULL = chưa tới tay ai.
     *
     * ĐÂY LÀ THỨ THAY CHO CỘT `status` cũ (Mới -> Đang xử lý -> Đã xử lý), bỏ
     * ngày 2026-08-26. Cột kia là một hàng chờ mà không ai đứng canh: nhân
     * viên cửa hàng kính ngồi ở quầy và trả lời khách bằng Zalo, không ngồi
     * trước bảng quản trị chờ có dòng mới. Một hàng chờ không người trực thì
     * TRÔNG như đã có người lo, mà thật ra không.
     *
     * Nay yêu cầu chạy thẳng sang Zalo của CSKH ngay lúc khách bấm gửi, đúng
     * đường lịch hẹn và đơn hàng đã đi — xem Zalo::contact().
     *
     * Cột này là một SỰ KIỆN (đã xảy ra lúc nào), không phải một TRẠNG THÁI
     * (ai đó tự đặt): không có ô chọn nào ghi vào nó, chỉ có việc gửi thành
     * công. Cần nó vì ZNS hỏng IM LẶNG — token hết hạn, mẫu tin bị gỡ, mạng
     * ra ngoài bị chặn đều chỉ để lại một dòng error log không ai đọc, trong
     * khi khách ngồi chờ một cuộc gọi không bao giờ tới. Đây là chỗ duy nhất
     * nhìn ra điều đó, và nó nuôi huy hiệu "Liên hệ" trên thanh bên.
     *
     * Xem database/migrations/2026-08-26-lien-he-qua-zalo.sql.
     */
    `zalo_sent_at` DATETIME   NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_requests_created` (`created_at`),
    -- "Yêu cầu nào chưa đẩy sang Zalo" chạy ở MỌI lượt tải trang quản trị,
    -- vì nó nuôi huy hiệu trên thanh bên.
    KEY `idx_contact_requests_zalo` (`zalo_sent_at`),
    KEY `idx_contact_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_contact_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. KHÁCH HÀNG — DỮ LIỆU DO KHU QUẢN TRỊ SỞ HỮU
--
-- Ba bảng của module /quan-tri/khach-hang. Đặt ở CUỐI file vì cả ba đều trỏ
-- khoá ngoại sang `users`, `appointments` và `stores` — mà FOREIGN_KEY_CHECKS
-- đã bật lại từ đầu file, nên bảng được trỏ tới phải tồn tại trước.
--
-- Xem thêm database/migrations/2026-08-26-module-khach-hang.sql — file đó dành
-- cho cơ sở dữ liệu đang chạy, file này dành cho máy cài mới.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- LỊCH SỬ ĐƠN THUỐC KÍNH
--
-- VÌ SAO CÓ BẢNG NÀY KHI ĐÃ CÓ `prescriptions`
--
-- `prescriptions` có khoá chính là `user_id` — MỘT dòng cho MỘT khách, lần đo
-- sau ghi đè lên lần đo trước. Nó đúng với việc nó sinh ra để làm: trang tài
-- khoản của khách chỉ hỏi "độ của tôi bây giờ là bao nhiêu".
--
-- Nhưng cửa hàng kính cần đọc được ĐƯỜNG ĐI của độ cận theo năm tháng — tăng
-- bao nhiêu diop một năm là câu quyết định việc tư vấn tròng. Ghi đè là xoá
-- mất chính dữ liệu đó.
--
-- ĐÃ CÂN NHẮC VÀ BỎ phương án đổi thẳng `prescriptions` thành bảng nhiều dòng:
-- bốn chỗ đang đọc nó đều nằm trên luồng mua hàng, và luồng đó đã gãy đúng một
-- lần vì bảng này (2026-08-22, xem chú thích trong CartController). Không đem
-- luồng mua hàng ra đổi lược đồ để lấy một tính năng của khu quản trị.
--
-- Nên: bảng NÀY là nguồn chân lý, còn `prescriptions` tụt xuống thành BẢN SAO
-- của bản ghi mới nhất — đúng nếp `addresses` -> `profiles`.`address` đã có.
-- ----------------------------------------------------------------------------
CREATE TABLE `customer_prescriptions` (
    `id`             CHAR(36)      NOT NULL DEFAULT (UUID()),
    `user_id`        CHAR(36)      NOT NULL,
    -- Toa khách mang từ ngoài vào thì NULL. SET NULL: xoá một lịch hẹn không
    -- được kéo theo số đo đã ghi trong lần hẹn đó.
    `appointment_id` CHAR(36)      NULL,
    /*
     * NGUỒN SỐ ĐO — CỘT BẮT BUỘC, KHÔNG PHẢI CỘT TRANG TRÍ.
     *
     *   'store'    kỹ thuật viên của cửa hàng đo
     *   'customer' khách tự khai (trang tài khoản, hoặc bước 'so-do' khi mua)
     *   'external' toa của bệnh viện / phòng khám ngoài
     *
     * CLAUDE.md điểm A1: hai nguồn này KHÔNG ĐƯỢC TRỘN. Số khách tự gõ và số
     * máy đo ra không có cùng độ tin cậy, mà nhìn vào bảng số thì chúng giống
     * hệt nhau. Thiếu cột này thì sáu tháng sau không ai phân biệt nổi, và
     * người mài tròng sẽ tin nhầm một con số khách nhớ mang máng.
     */
    `source`         VARCHAR(16)   NOT NULL DEFAULT 'store',
    -- Cùng kiểu với bảng `prescriptions` để hai bên chép qua lại không mất số lẻ.
    `od_sph`         DECIMAL(4,2)  NULL,
    `od_cyl`         DECIMAL(4,2)  NULL,
    `od_axis`        SMALLINT      NULL,
    `od_va`          VARCHAR(16)   NULL,
    `os_sph`         DECIMAL(4,2)  NULL,
    `os_cyl`         DECIMAL(4,2)  NULL,
    `os_axis`        SMALLINT      NULL,
    `os_va`          VARCHAR(16)   NULL,
    `pd`             DECIMAL(4,1)  NULL,
    /*
     * NGÀY ĐO, BẮT BUỘC — khác created_at (lúc gõ vào máy).
     *
     * Cả module dựng trên trục thời gian này: sắp xếp lịch sử, tính "độ tăng
     * bao nhiêu sau bao lâu", và huy hiệu còn hiệu lực. Cho NULL thì một dòng
     * không có ngày sẽ rơi ra khỏi mọi phép so sánh mà không ai thấy.
     */
    `measured_at`    DATE          NOT NULL,
    `store_id`       CHAR(36)      NULL,
    `note`           VARCHAR(255)  NULL,
    -- Nhân viên đã nhập. SET NULL: người nghỉ việc bị xoá tài khoản thì số đo
    -- vẫn phải còn.
    `created_by`     CHAR(36)      NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Cột đầu user_id, cột hai ngày đo giảm dần: trang chi tiết luôn hỏi đúng
    -- một câu "số đo của người này, mới nhất trước".
    KEY `idx_cpres_user_date` (`user_id`, `measured_at` DESC),
    KEY `idx_cpres_appointment` (`appointment_id`),
    KEY `idx_cpres_store` (`store_id`),
    KEY `idx_cpres_author` (`created_by`),
    CONSTRAINT `fk_cpres_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cpres_appointment` FOREIGN KEY (`appointment_id`)
        REFERENCES `appointments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cpres_store` FOREIGN KEY (`store_id`)
        REFERENCES `stores` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_cpres_author` FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- VẾT THAO TÁC TRÊN DỮ LIỆU KHÁCH
--
-- CLAUDE.md mục 5: dữ liệu đơn thuốc kính là dữ liệu y tế, MỌI thao tác đọc và
-- ghi đều phải có vết. Bảng này là chỗ chứa vết đó, cộng các thao tác nặng tay
-- khác trên tài khoản khách (khoá, xoá mềm, phát liên kết đổi mật khẩu, xuất
-- danh sách).
--
-- Hai khoá ngoại đều SET NULL chứ không CASCADE: xoá một tài khoản không được
-- xoá bằng chứng về những gì đã làm với tài khoản đó. Tên người thao tác vì
-- thế phải chép lại vào `actor_name`.
--
-- KHÔNG lưu nội dung số đo vào `detail`. Bảng vết mà chứa chính dữ liệu y tế
-- thì nó thành bản sao thứ hai của thứ đang cần bảo vệ, và bản sao đó không
-- được ai canh.
-- ----------------------------------------------------------------------------
CREATE TABLE `customer_audit_logs` (
    `id`         CHAR(36)     NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36)     NULL,
    `actor_id`   CHAR(36)     NULL,
    `actor_name` VARCHAR(255) NULL,
    -- 'rx.read' | 'rx.create' | 'rx.update' | 'rx.delete' | 'profile.update'
    -- 'address.save' | 'address.delete' | 'note.save' | 'note.delete'
    -- 'lock' | 'unlock' | 'soft_delete' | 'restore' | 'reset_email' | 'export'
    `action`     VARCHAR(32)  NOT NULL,
    `detail`     VARCHAR(255) NULL,
    -- 45 ký tự: đủ cho IPv6 dạng dài nhất.
    `ip`         VARCHAR(45)  NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_calog_user` (`user_id`, `created_at`),
    KEY `idx_calog_action` (`action`, `created_at`),
    KEY `idx_calog_actor` (`actor_id`, `created_at`),
    CONSTRAINT `fk_calog_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_calog_actor` FOREIGN KEY (`actor_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. DỮ LIỆU KHỞI TẠO
--
-- Chỉ KHUNG để trang chạy được ngay sau khi cài: cơ sở (form đặt lịch và trang
-- Liên hệ cần có) và danh mục (bộ lọc và điều hướng cần có).
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
-- GÓI CHIẾT SUẤT — SEED, cùng lý lẽ với cơ sở và danh mục ở trên
--
-- Không phải hàng mẫu: thiếu bảng này thì bước "Chọn loại tròng kính" của hộp
-- mua hàng không có gì để chọn, tức là luồng mua kính có độ đứt ngay sau khi
-- cài. Cửa hàng sửa lại ở /quan-tri/gia-trong/goi.
--
-- Mã ('clear-150'…) phải giữ nguyên: chúng là thứ order_items.lens_id và
-- lens_prices.lens_package lưu lại. GIÁ thì không seed ở đây — bảng
-- `lens_prices` để trống, mọi lựa chọn hiện "Báo giá sau" cho tới khi cửa hàng
-- nhập giá thật.
-- ----------------------------------------------------------------------------
INSERT INTO `lens_packages` (`id`, `name`, `description`, `sort_order`) VALUES
('clear-150', 'Tròng trắng 1.50',          'Phù hợp độ cận/viễn nhẹ đến trung bình (dưới -4.00)',     10),
('clear-156', 'Tròng trắng 1.56',          'Mỏng hơn, phù hợp cận trung bình (-4.00 → -6.00)',        20),
('blue-161',  'Chống sáng xanh 1.61',      'Bảo vệ mắt khi làm việc máy tính nhiều giờ',              30),
('blue-167',  'Chống sáng xanh 1.67',      'Siêu mỏng, thẩm mỹ cao, cận nặng (trên -6.00)',           40),
('photo-156', 'Đổi màu Photochromic 1.56', 'Tự điều chỉnh theo ánh sáng, tiện dùng trong/ngoài trời', 50);

-- ----------------------------------------------------------------------------
-- THUỘC TÍNH TRÒNG — SEED, cùng lý lẽ với gói chiết suất ở trên
--
-- Không phải hàng mẫu: đây đúng là bốn danh sách site đang chạy, chép từ
-- config/eyewear.php và config/taxonomy.php vào CSDL để cửa hàng sửa được.
-- Bảng rỗng thì bộ lọc trang tròng kính không có mục nào để chọn.
--
-- LOẠI TRÒNG lấy bộ BỐN của config/taxonomy.php (Đơn · Hai · Đa · Mắt đặt),
-- không lấy bộ 'rx_lens_types' của config/eyewear.php (Đơn · Đa · Đổi màu ·
-- Chống ánh sáng xanh). Hai bộ ấy khác nhau và đó là mâu thuẫn có sẵn: bộ sau
-- trộn hai TÍNH NĂNG vào một nhóm nói về LOẠI, và thiếu "Hai tròng". Hai khoá
-- lạc ấy được seed vào nhóm 'lop-phu' bên dưới nên hàng đã nhập không mất bộ
-- lọc, chỉ hiện ở đúng nhóm hơn.
--
-- MÀU TRÒNG chưa từng có danh sách — cột `lens_color` là ô chữ tự do. Sáu mục
-- dưới đây là bộ khởi đầu để cửa hàng sửa lại theo hàng thật, không phải chuẩn.
-- ----------------------------------------------------------------------------
INSERT INTO `lens_options` (`group_key`, `option_key`, `label`, `note`, `sort_order`) VALUES
('loai-trong', 'don-trong', 'Đơn tròng', 'Một độ duy nhất trên cả mặt tròng — nhìn xa hoặc nhìn gần', 10),
('loai-trong', 'hai-trong', 'Hai tròng', 'Hai vùng nhìn tách nhau bằng một đường ranh: xa ở trên, gần ở dưới', 20),
('loai-trong', 'da-trong',  'Đa tròng',  'Độ chuyển dần từ xa sang gần, không có đường ranh trên mặt tròng', 30),
('loai-trong', 'mat-dat',   'Mắt đặt',   'Độ quá cao hoặc thông số đặc biệt, phải đặt riêng — cửa hàng báo giá sau', 40),

('chiet-suat', '1.50', '1.50', 'Tròng trắng cơ bản, độ nhẹ',            10),
('chiet-suat', '1.56', '1.56', 'Mỏng hơn 1.50, phù hợp cận trung bình', 20),
('chiet-suat', '1.61', '1.61', 'Mỏng, nhẹ — cận từ -4.00 trở lên',      30),
('chiet-suat', '1.67', '1.67', 'Siêu mỏng, cận nặng trên -6.00',        40),
('chiet-suat', '1.74', '1.74', 'Mỏng nhất, dành cho độ rất cao',        50),

('lop-phu', 'uv400',         'UV400',                  'Chặn 100% tia UVA/UVB', 10),
('lop-phu', 'chong-loa',     'Chống phản quang',       'Giảm loá đèn xe, đèn màn hình', 20),
('lop-phu', 'anh-sang-xanh', 'Lọc ánh sáng xanh',      'Cho người làm việc máy tính nhiều giờ', 30),
('lop-phu', 'doi-mau',       'Đổi màu (Photochromic)', 'Tự sẫm lại khi ra nắng', 40),
('lop-phu', 'phan-cuc',      'Phân cực (Polarized)',   'Cắt chói mặt nước, mặt đường', 50),
('lop-phu', 'chong-tray',    'Chống trầy',             NULL, 60),
('lop-phu', 'chong-nuoc',    'Chống bám nước',         NULL, 70),
('lop-phu', 'chong-bui',     'Chống bám bụi',          NULL, 80),

('mau-trong', 'trong-suot',  'Trong suốt',            'Tròng cận thường, không màu', 10),
('mau-trong', 'xam-khoi',    'Xám khói',              NULL, 20),
('mau-trong', 'nau-tra',     'Nâu trà',               NULL, 30),
('mau-trong', 'xanh-reu',    'Xanh rêu',              NULL, 40),
('mau-trong', 'gradient',    'Gradient (chuyển màu)', 'Đậm ở trên, nhạt dần xuống dưới', 50),
('mau-trong', 'trang-guong', 'Tráng gương',           'Mặt ngoài phản quang', 60);

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
-- Cơ sở và danh mục thì VẪN seed: đó là khung để trang chạy được ngay (form
-- đặt lịch cần cơ sở, bộ lọc cần danh mục), không phải hàng bán.
--
-- Bản cài cũ đã có 5 món đó thì database/migrations/2026-08-20-bo-san-pham-mau.sql
-- dọn giúp.
-- ----------------------------------------------------------------------------

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
