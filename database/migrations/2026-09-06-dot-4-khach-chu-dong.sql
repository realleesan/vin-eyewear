-- ============================================================================
-- 2026-09-06 — ĐỢT 4: KHÁCH CHỦ ĐỘNG HƠN
--
-- Căn cứ: SRS v2.1.0 — UC-01 (FR-TK-17), UC-02 (FR-HS-13), UC-04 (FR-DH-14).
--
-- Ba việc, một file:
--
--   1. `users`.`deleted_source`   phân biệt tài khoản do KHÁCH tự xoá với tài
--                                 khoản do NHÂN VIÊN xoá (BR-TK-17.3).
--   2. bảng `refund_requests`     yêu cầu hoàn tiền cọc, vòng đời chờ duyệt →
--                                 đã hoàn / từ chối (UC-04).
--   3. `orders`.`cancelled_by`    ai huỷ đơn: khách, nhân viên, hay hệ thống.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- HỆ THỐNG KHÔNG CHUYỂN TIỀN — BR-DH-14.5
--
-- Bảng `refund_requests` là một cuốn SỔ QUYẾT ĐỊNH, không phải một cổng thanh
-- toán. Nó lưu: số tiền hệ thống đề nghị, số tiền người duyệt chốt, lý do khi
-- hai số khác nhau, ai duyệt và lúc nào. Việc chuyển tiền làm ngoài hệ thống
-- (chuyển khoản tay), rồi quản trị viên quay lại bấm "Đã hoàn".
--
-- Vì thế KHÔNG có khoá ngoại sang bảng giao dịch ngân hàng và không có cột nào
-- mang số tài khoản. Đừng thêm — một cột số tài khoản ở đây sẽ mời người ta
-- tin rằng hệ thống tự chuyển được.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO TIỀN LƯU BẰNG BIGINT
--
-- Cùng lối với `orders`.`total` và mọi cột tiền khác của dự án: số nguyên đơn
-- vị đồng. Tiền Việt không có phần lẻ, và DECIMAL/FLOAT cho cột tiền là cách
-- sinh ra những khoản lệch một đồng mà không ai truy được.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- CREATE TABLE IF NOT EXISTS, và hai cột đi qua PREPARE/EXECUTE hỏi
-- information_schema trước — cùng khuôn mẫu với các migration khác trong dự án.
-- Không câu nào xoá hay sửa dữ liệu đang có.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. `users`.`deleted_source` — AI XOÁ TÀI KHOẢN NÀY
--
-- BR-TK-17.3 buộc khu quản trị phân biệt được hai trường hợp, vì chúng cần xử
-- lý khác nhau: tài khoản khách tự xoá thì khôi phục lại phải hỏi chính khách,
-- còn tài khoản nhân viên xoá (trùng lặp, dọn dẹp) thì quản trị viên tự quyết.
--
-- NULL nghĩa là "không rõ" — đúng với mọi dòng đã xoá TRƯỚC lần nâng cấp này.
-- Không backfill thành 'staff': lúc đó chưa có đường nào cho khách tự xoá nên
-- đoán vậy gần như chắc đúng, nhưng "gần như chắc đúng" ghi vào cột kiểm toán
-- thì sáu tháng sau không ai phân biệt được nó với dữ liệu thật.
--
-- VARCHAR chứ không ENUM: cùng lẽ với `orders`.`payment_status` — thêm giá trị
-- sau này không phải ALTER TABLE khoá bảng.
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'deleted_source');
SET @sql := IF(@co = 0,
    'ALTER TABLE `users` ADD COLUMN `deleted_source` VARCHAR(16) NULL DEFAULT NULL AFTER `deletion_reason`',
    'SELECT ''users.deleted_source da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 2. `orders`.`cancelled_by` — AI HUỶ ĐƠN
--
-- Ba giá trị: 'customer' · 'staff' · 'system'.
--
-- KHÔNG suy ra được từ `order_status_history`.`changed_by`: cột đó mang id tài
-- khoản, và khi khách tự huỷ thì id ấy chính là khách — nhưng khi NHÂN VIÊN
-- huỷ hộ một khách gọi điện, id là của nhân viên và không có gì phân biệt với
-- việc nhân viên tự quyết huỷ. Cột riêng trả lời thẳng câu hỏi nghiệp vụ.
--
-- 'system' để dành cho đường tự huỷ đơn chuyển khoản quá 24 giờ (FR-TT-11).
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
               AND COLUMN_NAME = 'cancelled_by');
SET @sql := IF(@co = 0,
    'ALTER TABLE `orders` ADD COLUMN `cancelled_by` VARCHAR(16) NULL DEFAULT NULL AFTER `status`',
    'SELECT ''orders.cancelled_by da co, bo qua'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 3. BẢNG `refund_requests` — SỔ QUYẾT ĐỊNH HOÀN TIỀN CỌC
--
-- MỘT YÊU CẦU MỖI ĐƠN, cưỡng chế bằng khoá duy nhất trên `order_id`.
--
-- Không phải để tiết kiệm dòng: một đơn có hai yêu cầu hoàn tiền là hai người
-- cùng duyệt hai số tiền khác nhau cho một khoản cọc, và bảng này không có
-- cách nào nói cái nào thắng. Khoá duy nhất biến chuyện đó thành một lỗi ghi
-- ngay lúc phát sinh thay vì một khoản chi sai sáu tháng sau.
--
-- Huỷ rồi mở lại đơn thì sao? Không xảy ra được: F10 đã gỡ đường mở lại đơn đã
-- huỷ (SRS v2.1.0), "Đã huỷ" là trạng thái kết thúc.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `refund_requests` (
    `id`             CHAR(36)    NOT NULL DEFAULT (UUID()),
    `order_id`       CHAR(36)    NOT NULL,

    -- ── SỐ TIỀN ────────────────────────────────────────────────────────────
    -- Ba con số, cố ý giữ cả ba thay vì chỉ giữ số cuối cùng.
    --
    --   received_amount  tiền cửa hàng ĐANG GIỮ của đơn, chép lại lúc tạo
    --   lens_amount      tiền tròng của đơn, chép lại cùng lúc
    --   suggested_amount số hệ thống đề nghị = received − lens, chặn sàn 0
    --
    -- TÊN LÀ `received_amount`, KHÔNG PHẢI `deposit_amount` — cố ý.
    -- `orders`.`deposit_amount` là số cọc PHẢI trả (30% tổng), chốt lúc đặt
    -- đơn; nó không nói tiền đã về hay chưa, cũng không nói về bao nhiêu. Khách
    -- chuyển khoản thường trả ĐỦ một lần, SePay đối soát đủ tổng thì đẩy đơn
    -- sang 'paid', và khi ấy cửa hàng đang giữ `total` chứ không phải 30%. Chép
    -- nhầm cột là ghi sổ thiếu 70% một khoản nợ khách mà không ai thấy.
    -- Xem RefundRequestModel::daNhan().
    --
    -- Chép lại chứ không đọc lại từ `orders` lúc hiển thị: giá tròng sửa được
    -- trong khu quản trị, và một yêu cầu duyệt tháng trước phải giải thích
    -- được bằng con số của tháng trước. Cùng lý lẽ với `order_items.unit_price`
    -- và `orders.deposit_rate`.
    `received_amount` BIGINT      NOT NULL DEFAULT 0,
    `lens_amount`     BIGINT      NOT NULL DEFAULT 0,
    `suggested_amount` BIGINT     NOT NULL DEFAULT 0,

    -- Số người duyệt CHỐT. NULL khi chưa duyệt. Khác `suggested_amount` thì
    -- `decision_note` bắt buộc có — BR-DH-14.6, ép ở tầng PHP.
    `approved_amount` BIGINT      NULL DEFAULT NULL,

    -- Đã bấm ô "lỗi cửa hàng" chưa (A1 của UC-04). Bật thì số đề nghị bằng
    -- TOÀN BỘ tiền cọc bất kể đã mài hay chưa — BR-DH-14.3.
    `shop_fault`      TINYINT(1)  NOT NULL DEFAULT 0,

    -- Đơn đã bấm mốc bắt đầu mài lúc huỷ chưa. Đây là CĂN CỨ TÍNH, chép lại vì
    -- cùng lý do với ba cột tiền: mốc mài gỡ được (trong 5 phút, hoặc Quản trị
    -- viên gỡ sau), và khi ấy căn cứ của một quyết định đã duyệt không được đổi
    -- theo.
    `lens_started`    TINYINT(1)  NOT NULL DEFAULT 0,

    -- ── VÒNG ĐỜI ───────────────────────────────────────────────────────────
    -- 'pending' chờ duyệt · 'approved' đã duyệt, chờ chuyển tiền
    -- 'refunded' đã chuyển tiền · 'rejected' từ chối hoàn
    --
    -- Tách 'approved' khỏi 'refunded' vì hai việc xảy ra ở hai thời điểm và do
    -- hai hành động khác nhau: duyệt là quyết định trong hệ thống, còn chuyển
    -- tiền là việc làm ở ngân hàng rồi quay lại bấm xác nhận. Gộp làm một thì
    -- không trả lời được câu "đã duyệt nhưng chưa chuyển" — đúng cái khoảng mà
    -- khách đang chờ tiền.
    `status`          VARCHAR(16) NOT NULL DEFAULT 'pending',

    -- Lý do khi số duyệt khác số đề nghị, hoặc khi từ chối hoàn.
    `decision_note`   VARCHAR(500) NULL DEFAULT NULL,
    `decided_by`      CHAR(36)    NULL DEFAULT NULL,
    `decided_at`      DATETIME    NULL DEFAULT NULL,

    -- Ngày tiền thật sự rời tài khoản cửa hàng, do người bấm nhập — KHÔNG lấy
    -- NOW(). Chuyển khoản làm ở ngân hàng có thể trước lúc bấm vài giờ hoặc
    -- vài ngày, và đối soát sau này đọc cột này.
    `refunded_on`     DATE        NULL DEFAULT NULL,
    `refund_note`     VARCHAR(500) NULL DEFAULT NULL,

    `created_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_refund_order` (`order_id`),
    KEY `idx_refund_status` (`status`),

    -- CASCADE theo đơn: đơn bị xoá vật lý thì yêu cầu hoàn tiền của nó cũng
    -- không còn nghĩa. Trên thực tế đơn không bao giờ bị xoá vật lý.
    CONSTRAINT `fk_refund_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE CASCADE,

    -- SET NULL cho người duyệt: nhân sự nghỉ việc không được kéo mất một quyết
    -- định chi tiền. Tên người duyệt lúc đó vẫn tra được ở nhật ký thao tác.
    CONSTRAINT `fk_refund_decided_by` FOREIGN KEY (`decided_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM users  LIKE 'deleted_source';   -- 1 dòng
--   SHOW COLUMNS FROM orders LIKE 'cancelled_by';     -- 1 dòng
--   SHOW TABLES LIKE 'refund_requests';               -- 1 dòng
--   SELECT COUNT(*) FROM refund_requests;             -- 0 (bảng mới)
--
-- Không có bước chuyển dữ liệu nào: cả ba thứ đều là chỗ chứa cho nghiệp vụ
-- mới, không thay thế gì đang có.
--
-- ----------------------------------------------------------------------------
-- QUAY LUI
--
-- Xem 2026-09-06-dot-4-khach-chu-dong-QUAY-LUI.sql.
--
-- Khác các đợt trước, quay lui ở đây MẤT DỮ LIỆU THẬT nếu đã có yêu cầu hoàn
-- tiền nào được tạo — bảng bị gỡ mang theo mọi quyết định chi tiền đã duyệt.
-- Đọc kỹ đầu file đó trước khi chạy.
-- ----------------------------------------------------------------------------
