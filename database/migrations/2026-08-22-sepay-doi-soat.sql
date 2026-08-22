-- ============================================================================
-- NÂNG CẤP 2026-08-22
-- SePay — sổ ghi giao dịch chuyển khoản nhận qua webhook
--
-- SePay (sepay.vn) đọc biến động số dư tài khoản ngân hàng của cửa hàng rồi
-- báo về website. Nhờ đó đơn chuyển khoản tự chuyển sang "đã thanh toán" /
-- "đã đặt cọc" mà không cần ai ngồi đối chiếu sao kê — xem config/sepay.php.
--
-- VÌ SAO PHẢI CÓ BẢNG NÀY CHỨ KHÔNG CHỈ CẬP NHẬT THẲNG VÀO `orders`
--
--   1. CHỐNG XỬ LÝ HAI LẦN. SePay gửi lại tối đa 7 lần trong 5 giờ nếu lần
--      trước không nhận được HTTP 200 — kể cả khi máy chủ ĐÃ xử lý xong rồi
--      mới chết lúc trả lời. Khoá UNIQUE trên `sepay_id` là thứ duy nhất chặn
--      được việc một lần chuyển tiền bị tính thành hai.
--
--   2. GIỮ CẢ GIAO DỊCH KHÔNG KHỚP ĐƠN NÀO. Khách gõ sai nội dung chuyển
--      khoản, hoặc tiền của một việc khác chảy vào cùng tài khoản. Những dòng
--      đó KHÔNG được im lặng biến mất: nhân viên cần thấy "có 660k về lúc
--      14:20 mà không biết của đơn nào" thì mới lần ra được.
--
--   3. SỔ ĐỐI CHIẾU. Mỗi dòng ghi lại đã LÀM GÌ với giao dịch đó (`applied`),
--      nên khi khách cãi "tôi chuyển rồi" thì có chỗ tra thay vì đoán.
--
-- KHÔNG đụng tới bảng nào đang có. Đây là CREATE TABLE thuần: chạy trước hay
-- sau khi đẩy code đều được, và nếu không chạy thì chỉ mỗi địa chỉ webhook
-- hỏng, phần còn lại của site không biết bảng này tồn tại.
--
-- Cách chạy
--   Trên máy:      mysql -u <user> -p <ten_db> < file_này.sql
--   InfinityFree:  vPanel -> phpMyAdmin -> chọn database -> tab SQL
--                  -> dán toàn bộ nội dung file -> Go
--
-- Chạy hai lần thì MySQL báo "Table 'sepay_transactions' already exists".
-- Đó là báo an toàn.
-- ============================================================================

CREATE TABLE `sepay_transactions` (
    `id`               CHAR(36)     NOT NULL DEFAULT (UUID()),

    -- Mã giao dịch DO SEPAY CẤP. Đây là khoá chống xử lý hai lần, nên nó phải
    -- UNIQUE và phải được ghi TRƯỚC khi đụng vào đơn hàng.
    `sepay_id`         BIGINT       NOT NULL,

    -- Đơn khớp được, hoặc NULL khi nội dung chuyển khoản không chỉ tới đơn nào.
    -- ON DELETE SET NULL: xoá đơn không được xoá theo dòng tiền đã về thật.
    `order_id`         CHAR(36)     NULL,
    -- Mã đơn ĐỌC ĐƯỢC từ nội dung chuyển khoản, giữ cả khi không khớp đơn nào:
    -- đó chính là manh mối để nhân viên lần ra khách gõ nhầm chỗ nào.
    `order_code`       VARCHAR(40)  NULL,

    `gateway`          VARCHAR(64)  NULL,   -- tên ngân hàng SePay báo về
    `account_number`   VARCHAR(64)  NULL,   -- tài khoản nhận, để đối chiếu
    -- 'in' = tiền vào, 'out' = tiền ra. Chỉ 'in' mới có nghĩa với đơn hàng,
    -- nhưng vẫn ghi cả 'out' để sổ khớp với sao kê.
    `transfer_type`    VARCHAR(8)   NOT NULL DEFAULT 'in',
    `amount`           BIGINT       NOT NULL DEFAULT 0,
    `content`          TEXT         NULL,   -- nội dung chuyển khoản nguyên văn
    `reference_code`   VARCHAR(64)  NULL,   -- số tham chiếu của ngân hàng
    `transaction_date` DATETIME     NULL,   -- giờ ngân hàng ghi nhận

    /*
     * ĐÃ LÀM GÌ VỚI GIAO DỊCH NÀY. VARCHAR chứ không ENUM, cùng lý do với
     * orders.payment_status. Các giá trị hiện dùng:
     *
     *   paid          đủ tiền -> đơn sang 'paid'
     *   deposit_paid  đủ tiền cọc -> đơn sang 'deposit_paid'
     *   partial       tiền về nhưng chưa đủ ngưỡng nào -> KHÔNG đổi đơn
     *   no_order      nội dung không chỉ tới đơn nào có thật
     *   ignored       tiền ra, hoặc đơn đã ở trạng thái cao hơn
     */
    `applied`          VARCHAR(32)  NOT NULL DEFAULT 'no_order',

    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sepay_txn` (`sepay_id`),
    KEY `idx_sepay_order` (`order_id`),
    CONSTRAINT `fk_sepay_order` FOREIGN KEY (`order_id`)
        REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
