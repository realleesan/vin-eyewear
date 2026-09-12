-- ============================================================================
-- QUAY LUI: TUỲ BIẾN TIÊU CHÍ LỌC — 2026-09-13
--
-- An toàn tuyệt đối: bảng này là bảng ĐÈ, không giữ dữ liệu gốc nào. Mọi tiêu
-- chí lọc vẫn được rút ra từ chữ trong `products` và `product_variants` như
-- trước khi có nó. Bỏ bảng đi thì bộ lọc quay về nhãn máy tự dựng — không mất
-- sản phẩm nào, không hỏng liên kết nào.
--
-- Thứ MẤT là công cửa hàng đã bỏ ra để đặt tên, gộp và ẩn. Sao lưu trước nếu
-- bảng đã có dòng:
--     SELECT * FROM filter_overrides;
-- ============================================================================

DROP TABLE IF EXISTS `filter_overrides`;
