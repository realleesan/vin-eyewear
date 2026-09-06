-- ============================================================================
-- 2026-09-06 — ĐỢT 7: SỔ GIAO DỊCH NGÂN HÀNG VÀ HỒ SƠ ĐO MẮT TRÊN ĐƠN
--
-- Căn cứ: SRS v2.1.0 — FR-SG-01..07 (Quyết định E06) và UC-03 · FR-GH-15
-- (Quyết định C06).
--
-- Bốn việc, hai bảng:
--
--   1. `sepay_transactions`.`gan_boi`      ai gắn tay giao dịch này  (FR-SG-05)
--   2. `sepay_transactions`.`gan_luc`      gắn lúc nào               (FR-SG-05)
--   3. chỉ mục `idx_sepay_so`              cho màn sổ                (FR-SG-01)
--   4. `order_items`.`prescription_id`     đơn cắt theo hồ sơ nào    (UC-03)
--
-- ─────────────────────────────────────────────────────────────────────────────
-- KHÔNG BẢNG NÀO ĐƯỢC TẠO MỚI Ở ĐỢT NÀY — VÀ ĐÓ LÀ CẢ CÂU CHUYỆN CỦA FR-SG
--
-- Bảng `sepay_transactions` đã ghi đầy đủ mọi giao dịch từ ngân hàng từ ngày
-- 22/08/2026. SRS mở đầu mục 4.2 bằng đúng câu ấy: *"Hệ thống đã ghi đầy đủ
-- mọi giao dịch nhận được từ ngân hàng, nhưng không có màn hình nào đọc ra."*
--
-- Hệ quả không phải chuyện thẩm mỹ: khách chuyển thiếu hoặc gõ sai nội dung
-- thì giao dịch rơi vào nhãn 'partial' hoặc 'no_order', nằm im trong bảng, và
-- đơn vẫn hiện "chưa thanh toán" cho tới khi có người đọc sao kê ngân hàng rồi
-- bấm tay. Tiền đã về tài khoản cửa hàng mà không ai biết.
--
-- Nên đợt 7 gần như không đụng vào lược đồ. Ba dòng dưới đây chỉ thêm thứ mà
-- một MÀN HÌNH cần: dấu vết của thao tác gắn tay, và một chỉ mục để bảng còn
-- đọc được khi nó dài ra.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- Cả bốn việc đi qua khuôn PREPARE/EXECUTE có hỏi information_schema trước,
-- cùng lối với năm đợt trước. Chạy hai lần không lỗi, không đổi gì thêm.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. `sepay_transactions`.`gan_boi` — AI GẮN TAY GIAO DỊCH NÀY
--
-- NULL nghĩa là "webhook tự khớp", không phải "chưa biết ai". Đó là trạng thái
-- BÌNH THƯỜNG của gần như mọi dòng trong bảng: SePay gửi sang, hệ thống đọc mã
-- đơn trong nội dung chuyển khoản và khớp ngay.
--
-- Khác 0 dòng thì có một con người đã quyết định rằng khoản tiền này thuộc về
-- đơn kia — thường vì khách gõ sai nội dung chuyển khoản. Quyết định ấy làm đổi
-- trạng thái tiền của một đơn hàng, nên nó phải có tên người chịu trách nhiệm.
--
-- SNFR-11 buộc ghi vết mọi thao tác ra tiền; `customer_audit_logs` vẫn là nơi
-- ghi vết đầy đủ (mã `sepay.link_order`). Hai cột ở đây là bản sao NGAY TRÊN
-- DÒNG SỔ, để người mở màn đối soát thấy ngay dòng nào do người gắn mà không
-- phải nhảy sang màn nhật ký.
--
-- ON DELETE SET NULL: xoá một tài khoản nội bộ không được làm mất một dòng sổ
-- tiền. Cùng lối với `refund_requests`.`decided_by`.
-- ----------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND COLUMN_NAME  = 'gan_boi'
);

SET @sql := IF(@co = 0,
    'ALTER TABLE `sepay_transactions`
        ADD COLUMN `gan_boi` CHAR(36) NULL DEFAULT NULL AFTER `applied`,
        ADD KEY `idx_sepay_gan_boi` (`gan_boi`),
        ADD CONSTRAINT `fk_sepay_gan_boi` FOREIGN KEY (`gan_boi`)
            REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT ''sepay_transactions.gan_boi đã có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 2. `sepay_transactions`.`gan_luc` — GẮN LÚC NÀO
--
-- Tách khỏi `created_at`, và khoảng cách giữa hai mốc mới là thứ đáng đọc:
-- `created_at` là lúc tiền về tài khoản, `gan_luc` là lúc có người tìm ra nó.
-- Ba ngày chênh nhau nghĩa là một khách đã chờ ba ngày mà đơn vẫn hiện chưa
-- thanh toán — con số ấy nói về chất lượng vận hành, không nói về dữ liệu.
-- ----------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND COLUMN_NAME  = 'gan_luc'
);

SET @sql := IF(@co = 0,
    'ALTER TABLE `sepay_transactions`
        ADD COLUMN `gan_luc` DATETIME NULL DEFAULT NULL AFTER `gan_boi`',
    'SELECT ''sepay_transactions.gan_luc đã có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 3. CHỈ MỤC CHO MÀN SỔ — `(applied, transaction_date)`
--
-- FR-SG-03 nói lọc theo kết quả đối soát, và đó là việc chỉ mục này làm: năm
-- viên lọc đều chạy `WHERE applied = ?`, cộng thêm hai câu đếm cho dải viên và
-- cho huy hiệu thanh bên.
--
-- NÓ KHÔNG GIÚP ĐƯỢC CÂU SẮP XẾP, và cần nói thẳng để không ai tưởng đã xong:
-- danhSach() sắp theo `COALESCE(transaction_date, created_at)` — một BIỂU THỨC,
-- mà MySQL không dùng chỉ mục cột cho biểu thức. COALESCE ở đó là cố ý và đáng
-- giữ (thiếu nó thì mấy dòng SePay không gửi kèm ngày rơi xuống đáy sổ vĩnh
-- viễn — đúng những dòng bất thường nhất), nên filesort vẫn còn.
--
-- Đổi lại, filesort chạy trên TẬP ĐÃ LỌC. Với viên "Không tìm thấy đơn" —
-- viên mở nhiều nhất mỗi sáng — đó là vài chục dòng thay vì cả bảng. Chỉ có
-- viên "Tất cả" là vẫn quét hết.
--
-- Muốn hết hẳn filesort thì phải có một cột sinh (`GENERATED ALWAYS AS
-- COALESCE(...) STORED`) rồi đánh chỉ mục lên nó. Chưa làm: bảng còn nhỏ, và
-- một cột sinh là thứ phải nhớ tới ở mọi lần sửa lược đồ sau này.
-- ----------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'sepay_transactions'
       AND INDEX_NAME   = 'idx_sepay_so'
);

SET @sql := IF(@co = 0,
    'ALTER TABLE `sepay_transactions`
        ADD KEY `idx_sepay_so` (`applied`, `transaction_date`)',
    'SELECT ''idx_sepay_so đã có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 4. `order_items`.`prescription_id` — ĐƠN NÀY CẮT THEO HỒ SƠ ĐO MẮT NÀO
--
-- UC-03 bước 5: *"Đơn hàng ghi lại số đo đã dùng kèm mã hồ sơ nguồn, để sau này
-- tra được đơn này cắt theo hồ sơ nào."*
--
-- ─────────────────────────────────────────────────────────────────────────────
-- KHÔNG THAY THẾ CỘT `prescription` ĐANG CÓ — HAI CỘT TRẢ LỜI HAI CÂU
--
--   `prescription`     VARCHAR(255), CHUỖI SỐ ĐO ĐÃ CHỐT của dòng hàng này.
--                      Bản chụp, không đổi nữa. Đây là thứ đi xuống phiếu mài
--                      và là thứ phải đọc được kể cả khi hồ sơ nguồn bị xoá.
--   `prescription_id`  hồ sơ khách đã CHỌN LÚC ĐẶT. Có thể NULL (khách gõ tay),
--                      và số đo trên dòng hàng có thể KHÁC hồ sơ ấy vì khách
--                      sửa vài ô sau khi chọn — UC-03 luồng A1 cho phép, và
--                      BR-GH-15.3 cấm sửa ngược lại hồ sơ gốc.
--
-- Nên hai cột lệch nhau là chuyện BÌNH THƯỜNG, không phải dữ liệu hỏng. Người
-- đọc `prescription_id` đang hỏi "khách lấy số này từ đâu ra", không hỏi "đơn
-- này cắt bao nhiêu độ".
--
-- ON DELETE SET NULL: khách xoá một hồ sơ trong sổ đo mắt thì đơn hàng cũ phải
-- còn nguyên. Cột `prescription` giữ số thật nên không mất gì đáng kể — chỉ mất
-- đường tra ngược, và đó đúng là thứ khách vừa yêu cầu xoá.
--
-- CHỈ MỤC ĐỂ PHỤC VỤ KHOÁ NGOẠI, khai rõ chứ không để InnoDB tự đặt tên: cùng
-- lý do đã ghi dài ở `appointments`.`idx_appointments_store`.
-- ----------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'order_items'
       AND COLUMN_NAME  = 'prescription_id'
);

/* KHOÁ NGOẠI CHỈ THÊM KHI BẢNG ĐÍCH CÓ THẬT.

   `customer_prescriptions` ra đời ở đợt 3. Một máy chưa chạy migration đợt ấy
   mà gặp ADD CONSTRAINT trỏ vào bảng không tồn tại sẽ dừng cả file với lỗi
   1215 — và ba việc phía trên đã chạy xong rồi, nên file thành "chạy dở".

   Thà thêm cột không kèm khoá: mã nguồn chỉ đọc và ghi một chuỗi id, nó không
   cần khoá ngoại để chạy đúng. Khoá là lưới an toàn cho ca xoá hồ sơ, và máy
   nào chưa có bảng ấy thì cũng chưa có hồ sơ nào để xoá. */
SET @co_bang := (
    SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
);

SET @sql := IF(@co > 0,
    'SELECT ''order_items.prescription_id đã có'' AS ghi_chu',
    IF(@co_bang > 0,
        'ALTER TABLE `order_items`
            ADD COLUMN `prescription_id` CHAR(36) NULL DEFAULT NULL AFTER `prescription`,
            ADD KEY `idx_order_items_prescription` (`prescription_id`),
            ADD CONSTRAINT `fk_order_items_prescription` FOREIGN KEY (`prescription_id`)
                REFERENCES `customer_prescriptions` (`id`) ON DELETE SET NULL',
        'ALTER TABLE `order_items`
            ADD COLUMN `prescription_id` CHAR(36) NULL DEFAULT NULL AFTER `prescription`,
            ADD KEY `idx_order_items_prescription` (`prescription_id`)'
    )
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 4b. KHOÁ NGOẠI BỔ SUNG — cho máy đã chạy file này TRƯỚC khi có đợt 3.
--
-- Bước 4 bỏ qua phần khoá khi `customer_prescriptions` chưa tồn tại, và nó
-- thoát sớm ở lần chạy sau vì CỘT đã có. Nghĩa là một máy chạy đợt 7 trước đợt
-- 3 rồi chạy đợt 3 sau sẽ KHÔNG BAO GIỜ có khoá ấy, dù chạy lại đợt 7 bao
-- nhiêu lần.
--
-- Thứ tự đó nghe lạ nhưng có thật: `database/migrate.sh` chạy theo mảng khai
-- sẵn, còn production thì dán tay từng file qua phpMyAdmin — và người dán
-- không phải lúc nào cũng theo đúng thứ tự.
--
-- Khối này hỏi riêng về KHOÁ chứ không về cột, nên nó vá được đúng khe ấy.
-- ----------------------------------------------------------------------------
SET @co_khoa := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA    = DATABASE()
       AND TABLE_NAME      = 'order_items'
       AND CONSTRAINT_NAME = 'fk_order_items_prescription'
);

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'order_items'
       AND COLUMN_NAME  = 'prescription_id'
);

SET @co_bang := (
    SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'customer_prescriptions'
);

SET @sql := IF(@co_khoa = 0 AND @co_cot > 0 AND @co_bang > 0,
    'ALTER TABLE `order_items`
        ADD CONSTRAINT `fk_order_items_prescription` FOREIGN KEY (`prescription_id`)
            REFERENCES `customer_prescriptions` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_order_items_prescription đã có hoặc chưa tới lúc'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM `sepay_transactions` LIKE 'gan_%';        -- 2 dòng
--   SHOW INDEX FROM `sepay_transactions` WHERE Key_name = 'idx_sepay_so';
--   SHOW COLUMNS FROM `order_items` LIKE 'prescription_id';     -- 1 dòng
--
-- Rồi mở /quan-tri/doi-soat: phải thấy mọi giao dịch ngân hàng đã nhận từ
-- 22/08/2026 tới nay. Bảng rỗng nghĩa là chưa có khách nào chuyển khoản, không
-- phải màn hình hỏng.
--
-- Hai viên lọc đáng nhìn trước tiên là "Thu một phần" và "Không tìm thấy đơn":
-- đó là hàng chờ CÓ NGƯỜI ĐANG ĐỢI — mỗi dòng là một khoản tiền đã về tài khoản
-- cửa hàng mà đơn của khách vẫn hiện chưa thanh toán.
-- ----------------------------------------------------------------------------
