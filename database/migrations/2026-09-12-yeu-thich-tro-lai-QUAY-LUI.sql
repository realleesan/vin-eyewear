-- ============================================================================
-- QUAY LUI cho 2026-09-12-yeu-thich-tro-lai.sql
--
-- ĐỌC TRƯỚC KHI CHẠY: file này XOÁ HẲN mọi dấu trang khách đã lưu. Khác với
-- lần gỡ ngày 06/09 (bảng lúc đó rỗng, chưa màn hình nào dùng), nay chức năng
-- đang chạy thật nên bảng CÓ dữ liệu. Sao lưu trước nếu định dựng lại:
--
--   SELECT * FROM favorites;
--
-- Chỉ chạy khi đã gỡ mã phía ứng dụng (nút lưu ở trang chi tiết và mục "Đã
-- lưu" trong trang tài khoản). Để nguyên mã mà xoá bảng thì KHÔNG đổ 500 —
-- FavoriteModel::available() chịu được — nhưng khách sẽ thấy dấu trang của họ
-- biến mất không lời giải thích.
-- ============================================================================

DROP TABLE IF EXISTS `favorites`;
