-- ============================================================================
-- 2026-09-11 — Đơn hàng: đối chiếu được tiền, và theo dõi được tới lúc khách nhận
--
-- ĐỌC KHỐI NÀY TRƯỚC KHI THÊM BẤT KỲ CỘT NÀO KHÁC VÀO `orders`.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- BỐN THỨ YÊU CẦU ĐÒI MÀ ĐÃ CÓ SẴN DƯỚI TÊN KHÁC — KHÔNG TẠO TRÙNG
--
--   shipping_fee       -> `orders`.`shipping_fee`   (đã có từ đầu)
--   discount_amount    -> `orders`.`discount`       (BIGINT, là SỐ TIỀN)
--   discount_code_id   -> `orders`.`voucher_id`     (FK -> vouchers, SET NULL)
--   customer_note      -> `orders`.`note`           (ghi chú khách gõ lúc đặt;
--                                                    xem OrderController dòng
--                                                    'note' => $_POST['note'])
--   deposit_amount     -> `orders`.`deposit_amount` (kèm `deposit_rate`)
--
-- Thêm cột thứ hai cùng nghĩa là chia đôi sự thật: nửa số chỗ đọc cột cũ, nửa
-- đọc cột mới, và không có lỗi nào báo cho tới lúc hai con số lệch nhau trên
-- một hoá đơn. File này CHỈ thêm những gì thật sự chưa có.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- `branch_id` KHÔNG PHẢI `store_id` ĐỔI TÊN — HAI CÂU HỎI KHÁC NHAU
--
--   store_id   KHÁCH TỚI LẤY Ở ĐÂU. Chỉ có nghĩa khi delivery_method='pickup';
--              đơn giao tận nơi để NULL vì không ai tới lấy cả.
--   branch_id  CƠ SỞ NÀO LÀM ĐƠN NÀY — mài tròng, lắp gọng, đóng gói. Đơn giao
--              tận nơi VẪN có một cơ sở làm nó; đó chính là chỗ store_id không
--              trả lời được.
--
-- Gộp hai thứ vào một cột thì mất một trong hai: hoặc không biết khách tới cơ
-- sở nào, hoặc không biết ai làm đơn giao hàng.
--
-- PHẠM VI QUYỀN CHUYỂN SANG ĐỌC branch_id (Q: chốt 04/09/2026). Nhân viên thấy
-- đơn CƠ SỞ MÌNH LÀM, kể cả đơn giao tận nơi — trước đây đơn giao tận nơi
-- (store_id NULL) hiện cho mọi nhân viên vì không có cách nào biết nó của ai.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ĐIỀN DỮ LIỆU CŨ: CHỈ ĐIỀN THỨ BIẾT CHẮC, PHẦN CÒN LẠI ĐỂ NULL
--
--   branch_id        <- store_id với đơn nhận tại cửa hàng. Đơn giao tận nơi
--                       để NULL: không có dữ liệu nào nói cơ sở nào đã làm nó,
--                       và bịa ra một cơ sở là làm hỏng chính con số mà cột này
--                       sinh ra để đếm. NULL vẫn hiện cho mọi nhân viên đã được
--                       gán cơ sở (StaffStoreModel::menhDe có vế `OR … IS
--                       NULL`), nên không ai mất việc đang làm dở.
--   deposit_paid_at  <- mốc thật lấy từ `sepay_transactions`, không phải
--                       updated_at. updated_at đổi theo MỌI lần sửa đơn, nên
--                       dùng nó là ghi vào sổ kế toán một thời điểm bịa.
--                       Đơn không có giao dịch SePay khớp thì để NULL, và giao
--                       diện ghi "chưa rõ mốc" — đúng hơn một con số sai.
--   from_status      <- trạng thái của chính mốc LIỀN TRƯỚC cùng đơn. Mốc đầu
--                       tiên của mỗi đơn để NULL: trước nó đơn chưa tồn tại.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- MySQL 8 không có `ADD COLUMN IF NOT EXISTS`, nên mỗi cột, mỗi chỉ mục và mỗi
-- khoá ngoại đều đi qua một vòng PREPARE/EXECUTE hỏi information_schema trước.
-- Các câu UPDATE điền dữ liệu đều có mệnh đề `IS NULL` nên chạy lần hai không
-- ghi đè gì.
--
-- KHÔNG có bước nào xoá cột, đổi kiểu cột đang chứa dữ liệu, hay xoá một dòng.
-- ============================================================================


-- ────────────────────────────────────────────────────────────────────────────
-- 1. TIỀN
-- ────────────────────────────────────────────────────────────────────────────

-- `tax_amount` — phần VAT của đơn, tính tại thời điểm đặt.
--
-- Cửa hàng HIỆN CHƯA xuất hoá đơn VAT (chốt 04/09/2026), nên cột này sẽ là 0
-- với mọi đơn cho tới khi có người đặt thuế suất trong .env. Thêm sẵn vì bật
-- VAT sau đó chỉ còn là đổi cấu hình, không phải một lần ALTER TABLE nữa trên
-- một bảng đã có hàng nghìn dòng.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'tax_amount');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `tax_amount` BIGINT NOT NULL DEFAULT 0 AFTER `discount`',
    'SELECT ''orders.tax_amount da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

-- `price_includes_tax` — CHÉP LẠI cấu hình tại thời điểm đặt, không đọc config
-- lúc hiển thị.
--
-- Cùng lý lẽ với `deposit_rate` và `order_items.unit_price`: cấu hình sẽ bị đổi,
-- còn hoá đơn đã phát hành thì phải đứng yên. Đọc config lúc in hoá đơn nghĩa là
-- ngày cửa hàng đổi từ "giá đã gồm thuế" sang "cộng thêm", MỌI hoá đơn cũ đổi
-- cách diễn giải theo — kể cả những tờ đã đưa cho khách.
--
-- Mặc định 1 (giá niêm yết đã gồm thuế) vì đó là cách bán lẻ ở Việt Nam vẫn làm,
-- và vì với thuế suất 0 thì hai chế độ cho ra cùng một con số.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'price_includes_tax');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `price_includes_tax` TINYINT(1) NOT NULL DEFAULT 1 AFTER `tax_amount`',
    'SELECT ''orders.price_includes_tax da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

-- `deposit_paid_at` — LÚC THU được tiền cọc.
--
-- Tách khỏi `paid_at` chứ không dùng chung: paid_at là mốc thu ĐỦ. Một đơn cắt
-- tròng đi qua HAI lần nhận tiền cách nhau nhiều ngày (cọc 30% để bắt đầu mài,
-- phần còn lại lúc giao), và sổ sách cần cả hai mốc chứ không chỉ mốc cuối.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'deposit_paid_at');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `deposit_paid_at` DATETIME NULL DEFAULT NULL AFTER `deposit_rate`',
    'SELECT ''orders.deposit_paid_at da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ────────────────────────────────────────────────────────────────────────────
-- 2. GIAO HÀNG
-- ────────────────────────────────────────────────────────────────────────────

-- `carrier` — TÊN đơn vị giao, không phải khoá ngoại.
--
-- Cửa hàng thuê ngoài và KHÔNG nối API hãng nào (chốt 04/09/2026): nhân viên
-- đọc mã vận đơn từ tờ biên nhận rồi gõ vào. Không có gì để đồng bộ, không có
-- trạng thái nào tự về, nên một bảng `carriers` kèm màn quản trị riêng chỉ là
-- một mục menu nữa để trông coi.
--
-- VARCHAR chứ không ENUM, và danh sách chọn nằm ở OrderModel::DON_VI_GIAO:
-- thêm một hãng là sửa một dòng PHP, không phải ALTER TABLE. Cùng nếp với
-- BookingModel::SERVICES.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'carrier');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `carrier` VARCHAR(40) NULL DEFAULT NULL AFTER `shipping_address`',
    'SELECT ''orders.carrier da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

-- `tracking_code` — mã vận đơn của hãng. 64 ký tự đủ cho mọi hãng trong nước.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'tracking_code');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `tracking_code` VARCHAR(64) NULL DEFAULT NULL AFTER `carrier`',
    'SELECT ''orders.tracking_code da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

-- `promised_at` — NGÀY hẹn giao, hoặc ngày hẹn khách tới lấy.
--
-- DATE chứ không DATETIME, và đây là quyết định đã có tiền lệ: bảng
-- `appointments` bỏ hẳn cột giờ ngày 2026-08-25 vì cửa hàng chốt giờ cụ thể qua
-- điện thoại, không chốt trong CSDL. Hẹn giao kính cũng vậy — "chiều thứ Năm"
-- là thứ nhân viên nói với khách, và ghi 14:00:00 vào đây là dựng lên một lời
-- hứa chính xác hơn lời hứa thật.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'promised_at');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `promised_at` DATE NULL DEFAULT NULL AFTER `tracking_code`',
    'SELECT ''orders.promised_at da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ────────────────────────────────────────────────────────────────────────────
-- 3. AI LÀM, LÀM Ở ĐÂU
-- ────────────────────────────────────────────────────────────────────────────

-- `branch_id` — cơ sở XỬ LÝ. Xem khối chú thích đầu file về việc nó khác
-- `store_id` thế nào và vì sao không gộp làm một.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'branch_id');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `branch_id` CHAR(36) NULL DEFAULT NULL AFTER `store_id`',
    'SELECT ''orders.branch_id da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

-- `assigned_staff_id` — nhân viên phụ trách đơn.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'assigned_staff_id');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `assigned_staff_id` CHAR(36) NULL DEFAULT NULL AFTER `branch_id`',
    'SELECT ''orders.assigned_staff_id da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

-- `internal_note` — ghi chú CHỈ NỘI BỘ.
--
-- Tách hẳn khỏi `orders`.`note`, và đây là ranh giới quan trọng nhất trong cả
-- file: `note` là chữ KHÁCH gõ lúc đặt hàng và khách đọc lại được ở trang tài
-- khoản. Nhân viên ghi "khách hay đổi ý, gọi xác nhận trước khi mài" vào đúng ô
-- đó thì khách sẽ đọc được. Hai người viết, hai người đọc, phải là hai cột.
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'internal_note');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `internal_note` TEXT NULL DEFAULT NULL AFTER `note`',
    'SELECT ''orders.internal_note da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ────────────────────────────────────────────────────────────────────────────
-- 4. CHỈ MỤC VÀ KHOÁ NGOẠI
--
-- CHỈ MỤC ĐẶT TÊN, TẠO TRƯỚC KHOÁ NGOẠI — và thứ tự ấy là cả điểm. InnoDB đòi
-- một chỉ mục trên cột khoá ngoại; không có sẵn thì nó TỰ tạo một cái và đặt tên
-- theo ràng buộc, trong khi schema.sql khai tên `idx_orders_*` như mọi bảng
-- khác. Cùng một cột, hai cái tên: máy cài mới một kiểu, máy nâng cấp một kiểu.
-- Xem migration 2026-09-07 nơi đúng chuyện này đã suýt xảy ra.
-- ────────────────────────────────────────────────────────────────────────────

SET @co := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND INDEX_NAME = 'idx_orders_branch');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD INDEX `idx_orders_branch` (`branch_id`)',
    'SELECT ''idx_orders_branch da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

SET @co := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND INDEX_NAME = 'idx_orders_assigned');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD INDEX `idx_orders_assigned` (`assigned_staff_id`)',
    'SELECT ''idx_orders_assigned da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

-- SET NULL cả hai: đóng một cơ sở hay xoá tài khoản một nhân viên nghỉ việc
-- KHÔNG được xoá theo đơn hàng đã phát sinh giao dịch thật.
SET @co := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND CONSTRAINT_NAME = 'fk_orders_branch');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_branch` FOREIGN KEY (`branch_id`)
        REFERENCES `stores` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_orders_branch da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;

SET @co := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND CONSTRAINT_NAME = 'fk_orders_assigned');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD CONSTRAINT `fk_orders_assigned` FOREIGN KEY (`assigned_staff_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_orders_assigned da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ────────────────────────────────────────────────────────────────────────────
-- 5. LỊCH SỬ TRẠNG THÁI: THÊM `from_status`
--
-- Bảng `order_status_history` ĐÃ CÓ từ trước với order_id · status · changed_by
-- · ly_do · created_at. Ba tên trong yêu cầu chỉ là tên khác của cột đã có:
--   to_status -> `status`      user_id -> `changed_by`      reason -> `ly_do`
-- Chỉ `from_status` là thật sự thiếu.
--
-- Vì sao cần: đọc một dòng "-> Đang chuẩn bị" không nói được đơn vừa TIẾN hay
-- vừa LÙI. Suy từ dòng liền trước thì được, nhưng mọi nơi đọc bảng đều phải tự
-- suy — và cái luật "không cho lùi từ Hoàn tất" thì cần biết chiều đi ngay tại
-- dòng đó, không phải sau một lượt quét cả chuỗi.
-- ────────────────────────────────────────────────────────────────────────────

SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_status_history'
               AND COLUMN_NAME = 'from_status');
SET @sql := IF(@co = 0,
    'ALTER TABLE `order_status_history`
        ADD COLUMN `from_status` VARCHAR(32) NULL DEFAULT NULL AFTER `order_id`',
    'SELECT ''order_status_history.from_status da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ────────────────────────────────────────────────────────────────────────────
-- 6. ĐIỀN DỮ LIỆU CŨ
-- ────────────────────────────────────────────────────────────────────────────

-- branch_id <- store_id, CHỈ với đơn nhận tại cửa hàng.
-- Đơn giao tận nơi để NULL — xem khối chú thích đầu file.
UPDATE `orders`
   SET `branch_id` = `store_id`
 WHERE `branch_id` IS NULL
   AND `store_id` IS NOT NULL;

-- deposit_paid_at <- mốc THẬT từ sổ giao dịch SePay.
--
-- Chỉ lấy giao dịch đã được ghi nhận là tiền cọc (`applied` = 'deposit_paid'),
-- và lấy lần SỚM NHẤT: một đơn có thể có nhiều dòng nếu SePay gửi lại webhook,
-- và mốc đúng là lần tiền vào đầu tiên.
--
-- Đơn không tìm được giao dịch nào thì giữ NULL. Giao diện in "chưa rõ mốc" —
-- một ô trống trung thực đọc được đúng như nó là, còn một mốc bịa từ updated_at
-- thì không ai phân biệt được với mốc thật.
UPDATE `orders` o
   SET o.`deposit_paid_at` = (
       SELECT MIN(t.`transaction_date`)
         FROM `sepay_transactions` t
        WHERE t.`order_id` = o.`id`
          AND t.`applied`  = 'deposit_paid'
          AND t.`transaction_date` IS NOT NULL
   )
 WHERE o.`deposit_paid_at` IS NULL
   AND o.`deposit_amount` > 0;

-- from_status <- trạng thái của mốc LIỀN TRƯỚC cùng đơn.
--
-- Đi qua một bảng dẫn xuất (`x`) chứ không phải một truy vấn con tương quan
-- thẳng trong WHERE: MySQL cấm vừa cập nhật một bảng vừa đọc chính nó trong
-- truy vấn con (lỗi 1093), nhưng bảng dẫn xuất thì được vật chất hoá trước nên
-- hợp lệ trên cả MySQL 8 lẫn MariaDB 10.
--
-- Sắp theo (created_at, id): nhiều mốc của cùng một đơn có thể trùng giây —
-- thao tác hàng loạt ghi cả loạt trong cùng một transaction. Thiếu vế `id` thì
-- thứ tự do máy chủ tự chọn và hai lần chạy cho hai kết quả khác nhau.
--
-- Mốc ĐẦU TIÊN của mỗi đơn giữ NULL: trước nó đơn chưa tồn tại, nên không có
-- trạng thái nào để ghi. Đó là NULL có nghĩa, không phải dữ liệu thiếu.
UPDATE `order_status_history` h
  JOIN (
        SELECT h1.`id`,
               (SELECT h2.`status`
                  FROM `order_status_history` h2
                 WHERE h2.`order_id` = h1.`order_id`
                   AND (h2.`created_at` < h1.`created_at`
                        OR (h2.`created_at` = h1.`created_at` AND h2.`id` < h1.`id`))
                 ORDER BY h2.`created_at` DESC, h2.`id` DESC
                 LIMIT 1) AS `truoc`
          FROM `order_status_history` h1
       ) x ON x.`id` = h.`id`
   SET h.`from_status` = x.`truoc`
 WHERE h.`from_status` IS NULL
   AND x.`truoc` IS NOT NULL;


SELECT 'Xong: don hang doi chieu tien + theo doi giao hang' AS ket_qua;
