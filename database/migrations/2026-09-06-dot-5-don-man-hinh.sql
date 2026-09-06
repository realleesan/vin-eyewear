-- ============================================================================
-- 2026-09-06 — ĐỢT 5: DỌN MÀN HÌNH VÀ LUẬT NHỎ
--
-- Căn cứ: SRS v2.1.0 — FR-DH-13, FR-HS-10, FR-SP-17, FR-NK-04, FR-TT-11.
--
-- Bốn việc, một file:
--
--   1. `order_items`.`cost_price`   giá vốn tại thời điểm bán     (FR-DH-13)
--   2. `order_items`.`lens_type`    kiểu tròng đã bán             (FR-HS-10)
--   3. `stock_waitlist`.`user_id`   chờ hàng gắn với tài khoản    (FR-SP-17)
--   4. bảng `app_settings`          cấu hình cửa hàng tự sửa được (FR-NK-04,
--                                                                  FR-TT-11)
--
-- ─────────────────────────────────────────────────────────────────────────────
-- KHÔNG CÂU NÀO XOÁ HAY SỬA DỮ LIỆU ĐANG CÓ
--
-- Cả bốn đều là THÊM. Ba cột mới đều NULL được và không backfill: NULL ở đây có
-- nghĩa thật là "đơn/dòng này có trước bản nâng cấp", và đoán một giá trị rồi
-- ghi vào thì sáu tháng sau không ai phân biệt được nó với dữ liệu thật.
--
-- Riêng `cost_price` thì đoán còn tệ hơn thế: giá vốn hôm nay KHÁC giá vốn lúc
-- bán, và chép ngược nó vào đơn cũ là dựng ra một bảng lợi nhuận sai mà trông
-- như thật.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- CREATE TABLE IF NOT EXISTS, và ba cột đi qua PREPARE/EXECUTE có hỏi
-- information_schema trước — cùng khuôn mẫu với mọi migration khác của dự án.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. `order_items`.`cost_price` — GIÁ VỐN TẠI THỜI ĐIỂM BÁN
--
-- Hồ sơ sản phẩm đã có `products`.`cost_price`, nhưng đó là giá vốn HÔM NAY.
-- Nhập lô mới với giá khác là mọi đơn cũ đổi lợi nhuận theo — tức không đơn nào
-- tính lại được. SRS nói thẳng: *"phải làm ngay dù chưa có báo cáo, vì lịch sử
-- đã trôi qua thì không dựng lại được."*
--
-- Cùng lý lẽ đã chép `product_name`, `unit_price`, `variant_label` và
-- `lens_name` vào dòng đơn.
--
-- LÀ GIÁ VỐN CỦA GỌNG, MỖI ĐƠN VỊ. Không gồm tiền tròng — bảng giá tròng chỉ có
-- giá bán, không có giá nhập — và không gồm chênh giá theo biến thể. Đọc cột này
-- để tính lãi thì nhớ nhân với `quantity`, và nhớ rằng phần tròng chưa trừ.
--
-- NULL = chưa biết, khác hẳn 0 = nhập không mất tiền. Đơn đặt trước bản này để
-- NULL và KHÔNG backfill.
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
               AND COLUMN_NAME = 'cost_price');
SET @sql := IF(@co = 0,
    'ALTER TABLE `order_items` ADD COLUMN `cost_price` BIGINT NULL DEFAULT NULL AFTER `unit_price`',
    'SELECT ''order_items.cost_price da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 2. `order_items`.`lens_type` — KIỂU TRÒNG ĐÃ BÁN
--
-- LensModel::combo() cố ý gộp kiểu tròng và gói chiết suất thành MỘT chuỗi tên
-- (`lens_name`), và chú thích ở đó giải thích vì sao: mọi nơi in phần tròng chỉ
-- cần một tên và một con số tiền.
--
-- Nhưng nút "Mua lại" (FR-HS-10) cần DỰNG LẠI lựa chọn chứ không in nó, và giá
-- tròng nằm ở giao điểm kiểu × gói — một mình `lens_id` không tra được giá.
-- Không có cột này thì mua lại hoặc bỏ mất phần tròng, hoặc phải tách ngược
-- chuỗi `lens_name`, một chuỗi hiển thị có thể đổi bất cứ lúc nào.
--
-- Nó cũng lấp một lỗ hổng báo cáo vốn đã có: trước bản này không câu SQL nào trả
-- lời được "tháng này bán bao nhiêu tròng đa tròng".
--
-- KHÔNG khoá ngoại: danh mục kiểu tròng nằm trong config/taxonomy.php (một mảng
-- PHP), không phải một bảng. Cùng lối với `lens_id`.
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_items'
               AND COLUMN_NAME = 'lens_type');
SET @sql := IF(@co = 0,
    'ALTER TABLE `order_items` ADD COLUMN `lens_type` VARCHAR(32) NULL DEFAULT NULL AFTER `lens_id`',
    'SELECT ''order_items.lens_type da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 3. `stock_waitlist`.`user_id` — CHỜ HÀNG GẮN VỚI TÀI KHOẢN
--
-- FR-SP-17: đăng ký nhận báo khi có hàng chỉ dành cho khách đã đăng nhập, và
-- thông tin liên hệ lấy từ tài khoản thay vì hai ô tự do.
--
-- Hai cột `email` và `phone` VẪN GIỮ, chỉ đổi nguồn: chúng nay là bản chụp lấy
-- từ hồ sơ lúc đăng ký. Chép lại vì màn Chờ hàng phải đọc được cả khi khách đã
-- xoá tài khoản — cùng lý do đơn hàng giữ tên và số điện thoại người mua.
--
-- ON DELETE SET NULL, không CASCADE: khách xoá tài khoản thì lượt chờ vẫn là dữ
-- liệu vận hành của cửa hàng ("mẫu này có mấy người hỏi"), và hai cột chụp ở
-- trên vẫn đủ để gọi. CASCADE là lặng lẽ xoá mất một phần nhu cầu thị trường.
--
-- KHÔNG backfill: dòng chờ cũ có email/số điện thoại tự do, không tra ngược
-- được về tài khoản nào một cách chắc chắn. Chúng để NULL và vẫn hiện ở màn Chờ
-- hàng như trước.
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_waitlist'
               AND COLUMN_NAME = 'user_id');
SET @sql := IF(@co = 0,
    'ALTER TABLE `stock_waitlist`
        ADD COLUMN `user_id` CHAR(36) NULL DEFAULT NULL AFTER `variant_id`,
        ADD KEY `idx_waitlist_user` (`user_id`),
        ADD CONSTRAINT `fk_waitlist_user` FOREIGN KEY (`user_id`)
            REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT ''stock_waitlist.user_id da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 4. BẢNG `app_settings` — CẤU HÌNH CỬA HÀNG TỰ SỬA ĐƯỢC
--
-- Chỗ cho những giá trị NGƯỜI VẬN HÀNH quyết định, khác hẳn config/*.php vốn là
-- chỗ cho những giá trị LẬP TRÌNH VIÊN quyết định. Ranh giới ấy đáng giữ: một
-- khoá vào nhầm nơi thì hoặc chủ cửa hàng không sửa được thứ họ nên sửa, hoặc họ
-- sửa được thứ đủ sức làm hỏng trang.
--
-- Hai khoá dùng ngay ở đợt 5:
--
--   moc_doanh_thu      ngày bắt đầu tính tiền trên bảng tổng quan  (FR-NK-04)
--                      Trước đây nằm ở STATS_SINCE trong .env, tức đổi được thì
--                      phải mở FTP và hy vọng lần deploy sau không ghi đè.
--   quet_qua_han_luc   mốc lần quét đơn chuyển khoản quá hạn gần nhất, dạng
--                      timestamp Unix                              (FR-TT-11)
--                      Hosting không có cron, nên việc định kỳ đi nhờ lượt truy
--                      cập của khách; khoá này là thứ giữ cho hai lượt liền
--                      nhau không cùng quét. Xem OrderModel::quetDonQuaHan().
--
-- MỌI GIÁ TRỊ LÀ CHUỖI. Không cột kiểu, không JSON: chỉ nơi gọi mới biết một
-- chuỗi rỗng nghĩa là 0, là NULL, hay là "chưa đặt". Thêm cột `type` ở đây là
-- mời người sau dựng một hệ thống kiểu nhỏ trong một bảng bốn cột.
--
-- `name` LÀ KHOÁ CHÍNH, không có cột id riêng: bảng này tra theo tên và chỉ tra
-- theo tên. Một khoá tự tăng ở đây chỉ tạo ra khả năng có hai dòng cùng tên.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_settings` (
    `name`       VARCHAR(64)  NOT NULL,
    `value`      TEXT         NULL DEFAULT NULL,

    -- Ai sửa lần cuối. SET NULL: người sửa nghỉ việc và bị xoá tài khoản thì
    -- giá trị vẫn phải còn — nó đang chi phối những con số cả cửa hàng đọc.
    -- Vết đầy đủ (cũ -> mới, ai, lúc nào) nằm ở `customer_audit_logs`.
    `updated_by` CHAR(36)     NULL DEFAULT NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`name`),
    KEY `idx_settings_by` (`updated_by`),
    CONSTRAINT `fk_settings_by` FOREIGN KEY (`updated_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY — cả bốn phải ra đúng một dòng
--
--   SHOW COLUMNS FROM order_items    LIKE 'cost_price';
--   SHOW COLUMNS FROM order_items    LIKE 'lens_type';
--   SHOW COLUMNS FROM stock_waitlist LIKE 'user_id';
--   SHOW TABLES LIKE 'app_settings';
--
-- Rồi thử tay:
--
--   · Đặt một đơn mới → SELECT cost_price, lens_type FROM order_items
--     ORDER BY id DESC LIMIT 1;  phải có số (hoặc NULL nếu sản phẩm chưa điền
--     giá vốn), và lens_type phải có nếu đơn cắt tròng.
--   · /quan-tri → dòng dẫn có liên kết "Đổi mốc" (chỉ Quản trị viên thấy).
--   · Mở trang một mặt hàng đã hết → khối chờ hàng mời đăng nhập nếu chưa
--     đăng nhập, và không còn ô số điện thoại / email.
-- ----------------------------------------------------------------------------
