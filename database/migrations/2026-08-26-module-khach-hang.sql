-- ============================================================================
-- NÂNG CẤP 2026-08-26
-- Module KHÁCH HÀNG trong khu quản trị
--
-- Ba bảng mới, hai bảng cũ thêm cột. Không xoá, không đổi kiểu cột nào đang có.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO CÓ BẢNG `customer_prescriptions` KHI ĐÃ CÓ `prescriptions`
--
-- `prescriptions` có khoá chính là `user_id` — MỘT dòng cho MỘT khách, lần đo
-- sau ghi đè lên lần đo trước. Nó đúng với việc nó sinh ra để làm: trang tài
-- khoản của khách chỉ hỏi "độ của tôi bây giờ là bao nhiêu".
--
-- Nhưng cửa hàng kính cần đọc được ĐƯỜNG ĐI của độ cận theo năm tháng: tăng
-- bao nhiêu diop một năm là câu hỏi quyết định việc tư vấn tròng. Ghi đè là
-- xoá mất chính dữ liệu đó, và xoá không lấy lại được.
--
-- ĐÃ CÂN NHẮC VÀ BỎ phương án đổi thẳng `prescriptions` thành bảng nhiều dòng:
-- bốn chỗ đang đọc nó đều nằm trên luồng mua hàng (CartController bước 'so-do',
-- UserModel::seedPrescription, trang /tai-khoan/do-mat, hộp mua hàng). Luồng đó
-- đã gãy đúng một lần vì bảng này — ngày 2026-08-22, khi migration
-- 2026-08-21-kinh-dang-deo.sql chưa chạy trên hosting nên năm cột wear_* không
-- tồn tại; khách bấm "Xác nhận độ kính" và bị đá về giỏ hàng không một lời giải
-- thích. Không đem luồng đó ra đổi lược đồ lần nữa để lấy một tính năng của khu
-- quản trị.
--
-- Nên: `customer_prescriptions` là NGUỒN CHÂN LÝ, còn `prescriptions` tụt xuống
-- thành BẢN SAO của bản ghi mới nhất. Đúng nếp đã có trong dự án —
-- `addresses` -> `profiles.address` cũng là một bản sao như vậy
-- (AddressModel::syncProfileAddress).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO PHẢI VÒNG VO PREPARE/EXECUTE THAY VÌ `ALTER TABLE ... ADD COLUMN`
--
-- Y hệt lý do đã ghi trong 2026-08-25-dong-y-dieu-khoan.sql: MySQL 8 không có
-- `ADD COLUMN IF NOT EXISTS`, mà file này phải chạy lại được nhiều lần. Chi
-- tiết đầy đủ ở file đó, không chép lại.
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE.
-- ============================================================================

-- --------------------------------------------------------------------------
-- 1. LỊCH SỬ ĐƠN THUỐC KÍNH
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_prescriptions` (
    `id`             CHAR(36)      NOT NULL DEFAULT (UUID()),
    `user_id`        CHAR(36)      NOT NULL,
    -- Toa khách mang từ ngoài vào thì NULL. ON DELETE SET NULL: xoá một lịch
    -- hẹn không được kéo theo số đo đã ghi trong lần hẹn đó.
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
     *
     * VARCHAR chứ không ENUM: thêm nguồn mới (vd 'may-do-tu-dong') không phải
     * ALTER TABLE khoá bảng. Cùng lẽ với `orders`.`payment_status`.
     */
    `source`         VARCHAR(16)   NOT NULL DEFAULT 'store',
    -- sph/cyl DECIMAL(4,2): đủ cho -20.00 .. +20.00, bước 0.25 — y như bảng
    -- `prescriptions` để hai bên chép qua lại không mất số lẻ.
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
     * Cả module này dựng trên trục thời gian đó: sắp xếp lịch sử, tính "độ
     * tăng bao nhiêu sau bao lâu", và huy hiệu còn hiệu lực. Cho NULL thì
     * một dòng không có ngày sẽ rơi ra khỏi mọi phép so sánh mà không ai
     * thấy — hỏng im lặng, kiểu hỏng tệ nhất.
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

-- --------------------------------------------------------------------------
-- 2. GHI CHÚ NỘI BỘ
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_notes` (
    `id`          CHAR(36)     NOT NULL DEFAULT (UUID()),
    `user_id`     CHAR(36)     NOT NULL,
    `body`        TEXT         NOT NULL,
    `author_id`   CHAR(36)     NULL,
    -- CHÉP LẠI tên người viết tại thời điểm viết, y như order_items.product_name.
    -- Nhân viên nghỉ việc và bị xoá tài khoản thì author_id thành NULL, nhưng
    -- ghi chú vẫn phải trả lời được "ai đã viết câu này".
    `author_name` VARCHAR(255) NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cnotes_user` (`user_id`, `created_at`),
    KEY `idx_cnotes_author` (`author_id`),
    CONSTRAINT `fk_cnotes_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cnotes_author` FOREIGN KEY (`author_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 3. VẾT THAO TÁC
--
-- CLAUDE.md mục 5: dữ liệu đơn thuốc kính là dữ liệu y tế, MỌI thao tác đọc và
-- ghi đều phải có vết. Bảng này là chỗ chứa vết đó, cộng thêm các thao tác
-- nặng tay khác trên tài khoản khách (khoá, xoá mềm, phát liên kết đổi mật
-- khẩu, xuất danh sách).
--
-- `user_id` và `actor_id` đều SET NULL chứ không CASCADE: xoá một tài khoản
-- không được xoá bằng chứng về những gì đã làm với tài khoản đó. Tên người
-- thao tác vì thế phải chép lại vào `actor_name`.
--
-- KHÔNG lưu nội dung số đo vào `detail`. Bảng vết mà chứa chính dữ liệu y tế
-- thì nó thành bản sao thứ hai của thứ đang cần bảo vệ, và bản sao đó không
-- được ai canh.
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_audit_logs` (
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

-- --------------------------------------------------------------------------
-- 4. `users` — TRẠNG THÁI TÀI KHOẢN VÀ XOÁ MỀM
--
-- VÌ SAO ĐẶT Ở `users` CHỨ KHÔNG PHẢI `profiles`
--
-- Khoá tài khoản phải chặn được ĐĂNG NHẬP, mà đường đăng nhập đọc bảng `users`
-- (UserModel::findByLogin, ::attempt, ::findOrCreateGoogle, RememberModel).
-- Để ở `profiles` thì nút khoá chỉ đổi một con chữ trên màn hình quản trị còn
-- người bị khoá vẫn vào được như thường — tệ hơn cả không có nút, vì nhân viên
-- tin là đã khoá rồi.
--
-- VÌ SAO `deleted_at` TÁCH RIÊNG, KHÔNG GỘP THÀNH status = 'deleted'
--
-- Khoá và xoá là hai chuyện chồng lên nhau chứ không loại trừ nhau: một tài
-- khoản bị khoá vì gian lận rồi mới xoá thì vẫn phải đọc được lý do khoá.
-- Gộp làm một cột là mất lý do ngay lúc bấm xoá. `deleted_at` còn mang theo
-- MỐC THỜI GIAN, thứ một giá trị 'deleted' không có.
--
-- VÌ SAO XOÁ MỀM CHỨ KHÔNG XOÁ CỨNG
--
-- `orders`.`user_id` là ON DELETE SET NULL — xoá cứng một khách thì đơn hàng
-- của họ mất chủ, và không cách nào nối lại. Sổ sách kế toán không cho phép
-- điều đó. Xem ghi chú ngay tại cột đó trong schema.sql.
-- --------------------------------------------------------------------------

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'status'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `users`
        ADD COLUMN `status` VARCHAR(16) NOT NULL DEFAULT ''active'' AFTER `email_verified`',
    'SELECT ''users.status da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'locked_reason'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `users`
        ADD COLUMN `locked_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `status`',
    'SELECT ''users.locked_reason da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'locked_at'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `users`
        ADD COLUMN `locked_at` DATETIME NULL DEFAULT NULL AFTER `locked_reason`',
    'SELECT ''users.locked_at da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'locked_by'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `users`
        ADD COLUMN `locked_by` CHAR(36) NULL DEFAULT NULL AFTER `locked_at`',
    'SELECT ''users.locked_by da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'deleted_at'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `users`
        ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `locked_by`',
    'SELECT ''users.deleted_at da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'deletion_reason'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `users`
        ADD COLUMN `deletion_reason` VARCHAR(500) NULL DEFAULT NULL AFTER `deleted_at`',
    'SELECT ''users.deletion_reason da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- Chỉ mục cho hai cột vừa thêm. Trang danh sách lọc theo cả hai ở MỌI lượt
-- tải, kể cả lượt không lọc gì (vì luôn phải loại tài khoản đã xoá mềm).
SET @co_khoa := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'users'
       AND INDEX_NAME   = 'idx_users_status'
);
SET @sql := IF(@co_khoa = 0,
    'ALTER TABLE `users` ADD KEY `idx_users_status` (`status`, `deleted_at`)',
    'SELECT ''idx_users_status da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_khoa := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA     = DATABASE()
       AND TABLE_NAME       = 'users'
       AND CONSTRAINT_NAME  = 'fk_users_locked_by'
);
SET @sql := IF(@co_khoa = 0,
    'ALTER TABLE `users`
        ADD CONSTRAINT `fk_users_locked_by` FOREIGN KEY (`locked_by`)
            REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_users_locked_by da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- --------------------------------------------------------------------------
-- 5. `contact_requests` — NỐI VỀ TÀI KHOẢN KHÁCH
--
-- Ba bảng hoạt động khác (`orders`, `appointments`, `reviews`) đều đã có
-- `user_id`; riêng bảng này KHÔNG CÓ ĐƯỜNG NÀO nối về khách, nên tab "Hoạt
-- động" sẽ khuyết một mục nếu không thêm.
--
-- ĐÃ CÂN NHẮC VÀ BỎ phương án so bằng số điện thoại lúc đọc:
-- `contact_requests`.`phone` lưu NGUYÊN VĂN khách gõ, còn `profiles`.`phone`
-- đã qua normalizePhone(). "0912 345 678" và "+84912345678" là hai chuỗi khác
-- nhau với MySQL, nên cách đó vừa sót vừa nhận nhầm — mà nó lại chạy ở MỌI
-- lượt mở trang chi tiết.
--
-- SET NULL chứ không CASCADE: xoá tài khoản không được xoá yêu cầu liên hệ,
-- vì module Liên hệ có hàng đợi riêng và nhân viên bên đó đang xử lý nó.
-- --------------------------------------------------------------------------

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_requests'
       AND COLUMN_NAME  = 'user_id'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `contact_requests`
        ADD COLUMN `user_id` CHAR(36) NULL DEFAULT NULL AFTER `id`',
    'SELECT ''contact_requests.user_id da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_khoa := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_requests'
       AND INDEX_NAME   = 'idx_contact_user'
);
SET @sql := IF(@co_khoa = 0,
    'ALTER TABLE `contact_requests` ADD KEY `idx_contact_user` (`user_id`, `created_at`)',
    'SELECT ''idx_contact_user da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SET @co_khoa := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'contact_requests'
       AND CONSTRAINT_NAME = 'fk_contact_user'
);
SET @sql := IF(@co_khoa = 0,
    'ALTER TABLE `contact_requests`
        ADD CONSTRAINT `fk_contact_user` FOREIGN KEY (`user_id`)
            REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_contact_user da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- --------------------------------------------------------------------------
-- 6. CHÉP DỮ LIỆU CŨ SANG BẢNG LỊCH SỬ
--
-- Mỗi dòng `prescriptions` đang có trở thành BẢN GHI ĐẦU TIÊN trong lịch sử
-- của khách đó, với source = 'customer'.
--
-- VÌ SAO LÀ 'customer' CHỨ KHÔNG PHẢI 'store': bảng cũ được ghi từ hai chỗ,
-- trang tài khoản của khách và bước 'so-do' khi mua hàng — cả hai đều là
-- KHÁCH TỰ GÕ. Không có đường nào cho kỹ thuật viên nhập vào bảng đó cả.
-- Đánh nhầm thành 'store' là gắn dấu "đã đo tại cửa hàng" lên những con số
-- chưa ai đo, đúng cái điều CLAUDE.md điểm A1 cấm.
--
-- measured_at: bảng cũ cho NULL, bảng mới thì không. Rơi về DATE(updated_at)
-- — mốc gõ vào máy. Không chính xác bằng ngày đo thật, nhưng nó là mốc DUY
-- NHẤT còn lại, và nó luôn muộn hơn hoặc bằng ngày đo nên thứ tự thời gian
-- vẫn đúng.
--
-- NOT EXISTS theo user_id: chạy lại file này lần thứ hai sẽ không chép lần
-- nữa, và cũng không đụng vào khách đã được nhân viên nhập số đo mới.
-- --------------------------------------------------------------------------
INSERT INTO `customer_prescriptions`
    (`id`, `user_id`, `source`,
     `od_sph`, `od_cyl`, `od_axis`, `od_va`,
     `os_sph`, `os_cyl`, `os_axis`, `os_va`,
     `pd`, `measured_at`, `store_id`, `note`, `created_at`, `updated_at`)
SELECT UUID(), p.`user_id`, 'customer',
       p.`od_sph`, p.`od_cyl`, p.`od_axis`, p.`od_va`,
       p.`os_sph`, p.`os_cyl`, p.`os_axis`, p.`os_va`,
       p.`pd`, COALESCE(p.`measured_at`, DATE(p.`updated_at`)),
       p.`store_id`, p.`recommendation`, p.`updated_at`, p.`updated_at`
  FROM `prescriptions` p
 WHERE NOT EXISTS (
           SELECT 1 FROM `customer_prescriptions` c WHERE c.`user_id` = p.`user_id`
       )
   -- Dòng rỗng hoàn toàn thì không chép: hồ sơ trống không phải một lần đo.
   AND (p.`od_sph` IS NOT NULL OR p.`os_sph` IS NOT NULL
     OR p.`od_cyl` IS NOT NULL OR p.`os_cyl` IS NOT NULL
     OR p.`pd`     IS NOT NULL);

-- --------------------------------------------------------------------------
-- 7. NỐI NGƯỢC CÁC YÊU CẦU LIÊN HỆ ĐÃ CÓ VỀ TÀI KHOẢN
--
-- So bằng CHÍN CHỮ SỐ CUỐI. Đó là phần không đổi giữa mọi cách viết một số di
-- động Việt Nam: "0912345678", "+84912345678", "84.912.345.678" đều cùng đuôi
-- "912345678". So cả chuỗi thì ba cách viết đó thành ba số khác nhau.
--
-- Chín chứ không phải mười: bản thân chữ số 0 dẫn đầu chính là thứ biến mất
-- khi có mã quốc gia.
--
-- `profiles`.`phone` là khoá UNIQUE nên một số chỉ trỏ về đúng một tài khoản.
-- LIMIT 1 chỉ để MySQL yên tâm về mặt cú pháp.
--
-- WHERE user_id IS NULL: chạy lại không ghi đè thứ đã nối đúng, và không đụng
-- vào dòng mà form liên hệ đã tự ghi user_id từ phiên đăng nhập.
-- --------------------------------------------------------------------------
UPDATE `contact_requests` cr
   SET cr.`user_id` = (
       SELECT p.`id`
         FROM `profiles` p
        WHERE p.`phone` IS NOT NULL
          AND CHAR_LENGTH(REGEXP_REPLACE(cr.`phone`, '[^0-9]', '')) >= 9
          AND RIGHT(p.`phone`, 9) = RIGHT(REGEXP_REPLACE(cr.`phone`, '[^0-9]', ''), 9)
        LIMIT 1
   )
 WHERE cr.`user_id` IS NULL;

-- Còn sót thì thử tiếp bằng email. Chạy SAU vì số điện thoại đáng tin hơn:
-- ô email trong form liên hệ không bắt buộc và không ai xác thực nó.
UPDATE `contact_requests` cr
   SET cr.`user_id` = (
       SELECT u.`id`
         FROM `users` u
        WHERE u.`email` IS NOT NULL
          AND u.`email` <> ''
          AND LOWER(u.`email`) = LOWER(cr.`email`)
        LIMIT 1
   )
 WHERE cr.`user_id` IS NULL
   AND cr.`email` IS NOT NULL
   AND cr.`email` <> '';
