-- ============================================================================
-- NÂNG CẤP 2026-08-27 (thứ hai trong ngày)
-- Bộ sưu tập: thêm cột `story` cho trang chi tiết /bo-suu-tap/{slug}
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO CẦN MỘT CỘT NỮA, KHI ĐÃ CÓ `tagline` VÀ `intro`
--
-- Ba cột này phục vụ ba chỗ có kích thước khác hẳn nhau:
--
--   tagline  MỘT DÒNG. Nằm trên tấm biển ở trang chủ và trong mega menu — chỗ
--            rộng chừng bốn mươi ký tự.
--   intro    MỘT ĐOẠN. Nằm trên thẻ ở /bo-suu-tap, cạnh ảnh bìa, dài quá thì
--            thẻ cao hơn ảnh và hàng thẻ so le gãy nhịp.
--   story    NHIỀU ĐOẠN. Chỉ hiện ở trang chi tiết của chính bộ đó, nơi duy
--            nhất có đủ chỗ để kể: bộ này ra đời từ đâu, hợp với ai, chất liệu
--            và dáng gọng chọn thế vì lý do gì.
--
-- Nếu không có cột này thì trang chi tiết chỉ còn `intro` — đúng những chữ mà
-- trang danh sách đã in ra ngay phía trước. Một trang lặp lại trang trước nó
-- rồi đẩy người đọc đi tiếp thì không đáng tồn tại; cột này là thứ làm nó
-- đáng. Trang tự ẩn cả khối khi cột còn trống, nên bộ chưa ai viết gì vẫn
-- hiện bình thường.
--
-- Cột NULL được, không đặt mặc định chuỗi rỗng: "chưa ai viết" và "đã viết rồi
-- xoá hết" là cùng một chuyện với người đọc, nhưng NULL nói đúng hơn khi truy
-- ngược trong CSDL.
--
-- Chạy lại nhiều lần không hỏng: có cột rồi thì bỏ qua.
-- ============================================================================

SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'collections'
       AND COLUMN_NAME  = 'story'
);

SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `collections`
        ADD COLUMN `story` TEXT NULL DEFAULT NULL AFTER `intro`',
    'SELECT ''collections.story da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;
