-- ============================================================================
-- QUAY LUI đợt 8 — mã quà tặng tự động cho khách chuyển đủ 100%.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ⚠ FILE NÀY DỰNG LẠI CẤU TRÚC, KHÔNG DỰNG LẠI ĐƯỢC DỮ LIỆU
--
-- Migration xuôi làm bốn việc; file này lùi được HAI:
--
--   ✓ cột `vouchers`.`is_reward`        dựng lại, mọi dòng về DEFAULT 0
--   ✓ chỉ mục `idx_vouchers_reward`     dựng lại
--   ✗ các lượt phát đã THU HỒI          KHÔNG dựng lại được — dòng đã xoá
--   ✗ cờ is_active của mã quà tặng      KHÔNG biết mã nào từng là mã quà tặng
--
-- Hai vế cuối mất theo đúng thứ tự nhân quả: cột `is_reward` biến mất trước
-- khi ai kịp hỏi "mã nào mang cờ ấy", nên sau khi lùi, cột dựng lại toàn số 0
-- và cửa hàng phải TỰ TICK LẠI mã muốn dùng làm quà.
--
-- Nếu đã xuất hai bảng mà migration xuôi dặn xuất trước khi chạy, thì chép lại
-- được bằng tay:
--
--   UPDATE vouchers SET is_reward = 1, is_active = 1 WHERE id IN (…);
--   INSERT INTO user_vouchers (user_id, voucher_id, granted_at) VALUES (…);
--
-- Không xuất thì không có đường nào — đó là cái giá của quyết định "thu hồi
-- ngay toàn bộ", đã ghi ở đầu file xuôi.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MÃ NGUỒN ĐỢT 8 KHÔNG DÙNG ĐƯỢC CỘT NÀY NỮA
--
-- Khác đợt 6 và 7: ở đây không có hàm available() nào để gác. Bốn hàm đọc cột
-- — VoucherModel::reward() · clearRewardFlag() · grantTo() · rewardHeldBy() —
-- đã bị XOÁ khỏi mã nguồn, cùng ô tick trong khu quản trị và hai màn hiển thị.
--
-- Nên chạy file này MỘT MÌNH chỉ dựng lại một cột không ai đọc. Muốn tính năng
-- sống lại thật thì phải DEPLOY NGƯỢC mã nguồn về trước đợt 8 — và trước khi
-- làm vậy, đọc lại lý do gỡ: SRS không đặc tả tính năng này ở bất kỳ mục nào.
--
-- KHUÔN PREPARE/EXECUTE nên chạy lại nhiều lần không lỗi.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. Cột TRƯỚC, chỉ mục SAU — ngược thứ tự của file xuôi.
--
-- Không có cột thì không tạo được chỉ mục trên nó. AFTER `is_public` đặt lại
-- đúng vị trí cũ, để `SHOW CREATE TABLE` khớp với bản trước khi gỡ.
-- ----------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'vouchers'
       AND COLUMN_NAME  = 'is_reward'
);

SET @sql := IF(@co = 0,
    'ALTER TABLE `vouchers`
        ADD COLUMN `is_reward` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_public`',
    'SELECT ''is_reward đã có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


SET @co := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'vouchers'
       AND INDEX_NAME   = 'idx_vouchers_reward'
);

SET @sql := IF(@co = 0,
    'CREATE INDEX `idx_vouchers_reward` ON `vouchers` (`is_reward`)',
    'SELECT ''idx_vouchers_reward đã có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 2. Nhắc lại, để người chạy không tưởng là đã xong.
-- ----------------------------------------------------------------------------
SELECT 'Cột và chỉ mục đã dựng lại. CHƯA có mã nào mang cờ is_reward = 1: '
       'cửa hàng phải tự tick lại, và các lượt phát đã thu hồi thì không lấy '
       'lại được. Đọc khối đầu file.' AS ghi_chu;
