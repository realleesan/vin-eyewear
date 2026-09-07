-- ============================================================================
-- 2026-09-06 — ĐỢT 8: GỠ MÃ QUÀ TẶNG TỰ ĐỘNG CHO KHÁCH CHUYỂN ĐỦ 100%
--
-- Căn cứ: rà soát mã nguồn theo SRS — PHẦN 1, mục X2.
--
-- VÌ SAO GỠ
--
-- Khi khách chọn chuyển khoản toàn bộ thay vì đặt cọc, hệ thống TỰ ĐỘNG phát
-- một mã giảm giá cho tài khoản đó. SRS không có một chữ nào về việc này: tìm
-- "tặng", "thưởng", "quà" trong toàn bộ tài liệu ra 0 kết quả. Mục 3.2.9.5
-- (Quản lý khuyến mãi) chỉ cho phép tạo/sửa/bật-tắt mã và *"phát mã riêng cho
-- toàn bộ khách hiện có"* — tức phát theo quyết định của NGƯỜI, không phải một
-- phần thưởng máy tự gắn vào luồng thanh toán.
--
-- Nó cũng là một khoản chi ngoài sổ: giảm doanh thu các đơn sau mà không mục
-- nào trong báo cáo giải thích được vì sao.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- QUYẾT ĐỊNH CỦA CỬA HÀNG: THU HỒI NGAY TOÀN BỘ (06/09/2026)
--
-- Mã đã phát đang nằm trong tay khách thật, ở bảng `user_vouchers`. Hai lối đi
-- đã được cân nhắc — tôn trọng tới khi hết hạn, hay thu hồi luôn — và cửa hàng
-- chọn vế thứ hai. File này thi hành đúng vế đó:
--
--   · XOÁ các lượt phát CHƯA DÙNG của mọi mã đang mang cờ is_reward.
--   · TẮT (is_active = 0) chính những mã ấy, để một mã lỡ để công khai không
--     tiếp tục dùng được sau khi lượt phát riêng đã bị thu.
--
-- KHÔNG đụng tới lượt đã dùng (used_at IS NOT NULL). Ở đó ưu đãi ĐÃ áp vào một
-- đơn hàng có thật: xoá dòng ấy không thu lại được đồng nào, chỉ làm mất căn
-- cứ giải thích vì sao đơn đó rẻ hơn. Sổ tiền phải đọc được sau khi tính năng
-- đã chết.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ⚠ XUẤT RA TRƯỚC KHI CHẠY — file này KHÔNG lùi lại được phần dữ liệu
--
--   SELECT id, code, title, is_active, expires_at
--     FROM vouchers WHERE is_reward = 1;
--
--   SELECT uv.user_id, uv.voucher_id, uv.granted_at
--     FROM user_vouchers uv
--     JOIN vouchers v ON v.id = uv.voucher_id
--    WHERE v.is_reward = 1 AND uv.used_at IS NULL;
--
-- Chạy xong thì cột `is_reward` biến mất, và không câu nào tìm lại được "mã
-- nào từng là mã quà tặng". File *-QUAY-LUI.sql dựng lại được CẤU TRÚC, không
-- dựng lại được hai bảng kết quả trên. Sao lưu CSDL trước (NFR-R08).
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- Cả bốn bước đi qua khuôn PREPARE/EXECUTE có hỏi information_schema trước —
-- kể cả hai bước DỮ LIỆU, vì sau lần chạy đầu cột `is_reward` không còn và câu
-- DELETE/UPDATE nhắc tới nó sẽ trả SQLSTATE[42S22] thay vì khớp 0 dòng. Lần
-- chạy thứ hai chỉ in ra dòng ghi chú.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 0. In ra thứ sắp bị đụng, để còn nằm trong nhật ký của migrate.sh.
--
-- Không đổi gì. Chạy trước hai bước dữ liệu nên con số này là tình trạng NGAY
-- TRƯỚC khi thu hồi — người đọc log về sau còn đối chiếu được.
-- ----------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'vouchers'
       AND COLUMN_NAME  = 'is_reward'
);

SET @sql := IF(@co > 0,
    'SELECT (SELECT COUNT(*) FROM vouchers WHERE is_reward = 1) AS ma_qua_tang,
            (SELECT COUNT(*) FROM user_vouchers uv
               JOIN vouchers v ON v.id = uv.voucher_id
              WHERE v.is_reward = 1 AND uv.used_at IS NULL)     AS luot_chua_dung_se_thu_hoi,
            (SELECT COUNT(*) FROM user_vouchers uv
               JOIN vouchers v ON v.id = uv.voucher_id
              WHERE v.is_reward = 1 AND uv.used_at IS NOT NULL) AS luot_da_dung_giu_nguyen',
    'SELECT ''is_reward không có — đã chạy trước đó'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 1. THU HỒI các lượt phát CHƯA DÙNG.
--
-- DELETE chứ không đánh dấu: `user_vouchers` là bảng "ai đang giữ mã nào", một
-- danh sách quyền chứ không phải một cuốn sổ. Dòng chưa dùng bị xoá thì mã đơn
-- giản là không còn trong ví khách — đúng nghĩa thu hồi, và không để lại một
-- trạng thái thứ ba ("đã thu hồi") mà không màn nào biết vẽ.
--
-- Lượt ĐÃ DÙNG giữ nguyên: xem khối đầu file.
-- ----------------------------------------------------------------------------
SET @sql := IF(@co > 0,
    'DELETE uv FROM user_vouchers uv
       JOIN vouchers v ON v.id = uv.voucher_id
      WHERE v.is_reward = 1 AND uv.used_at IS NULL',
    'SELECT ''bỏ qua thu hồi — is_reward không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 2. TẮT chính những mã ấy.
--
-- Bước 1 chỉ thu được mã RIÊNG (is_public = 0), vì mã công khai thì ai gõ đúng
-- cũng dùng được mà không cần một dòng trong `user_vouchers`. Ô trợ giúp trong
-- khu quản trị có khuyên "nên dùng với mã riêng", nhưng đó là lời khuyên chứ
-- không phải ràng buộc — nên phải tắt hẳn, nếu không thu hồi chỉ đúng một nửa.
--
-- KHÔNG xoá dòng `vouchers`: mã đã áp vào đơn nào thì `orders`.`voucher_id`
-- còn trỏ tới, và hoá đơn cũ phải in được tên mã.
-- ----------------------------------------------------------------------------
SET @sql := IF(@co > 0,
    'UPDATE vouchers SET is_active = 0 WHERE is_reward = 1',
    'SELECT ''bỏ qua tắt mã — is_reward không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 3. Chỉ mục TRƯỚC, cột SAU.
--
-- `idx_vouchers_reward` là chỉ mục một cột trên đúng `is_reward`. MySQL bỏ cột
-- thì tự bỏ luôn chỉ mục chỉ gồm cột ấy, nhưng khai tường minh và đúng thứ tự
-- thì file đọc được mà không phải nhớ luật ngầm ấy — cùng lối đã dùng ở
-- 2026-09-06-dot-7-doi-soat-QUAY-LUI.sql.
-- ----------------------------------------------------------------------------
SET @co := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'vouchers'
       AND INDEX_NAME   = 'idx_vouchers_reward'
);

SET @sql := IF(@co > 0,
    'ALTER TABLE `vouchers` DROP INDEX `idx_vouchers_reward`',
    'SELECT ''idx_vouchers_reward không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


SET @co := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'vouchers'
       AND COLUMN_NAME  = 'is_reward'
);

SET @sql := IF(@co > 0,
    'ALTER TABLE `vouchers` DROP COLUMN `is_reward`',
    'SELECT ''is_reward không có'' AS ghi_chu'
);

PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;
