-- ============================================================================
-- NÂNG CẤP 2026-08-21
-- Hồ sơ đo mắt — thêm phần "Kính đang đeo"
--
-- Cửa hàng yêu cầu hồ sơ khúc xạ của khách ghi thêm CẶP KÍNH HỌ ĐANG ĐEO
-- (tính chất tròng, loại tròng, loại gọng) để có cơ sở tư vấn chính xác hơn:
-- cùng một đơn thuốc −3.00 nhưng người đang đeo đa tròng gọng khoan không viền
-- và người lần đầu cắt kính nhận hai lời khuyên khác hẳn nhau.
--
-- Năm cột đi thẳng vào `prescriptions` chứ không thành bảng riêng. Thứ cần khi
-- tư vấn là cặp kính ĐANG đeo, mà cặp đang đeo thì mỗi khách có đúng một — y
-- như hồ sơ khúc xạ. Đặt cạnh số độ thì một truy vấn ra đủ thứ cần cho một
-- buổi tư vấn. Cần lịch sử thật (đổi kính lần thứ mấy, mỗi lần đổi gì) thì đó
-- là một tính năng khác và phải có bảng riêng có mốc thời gian.
--
-- Dùng file này cho cơ sở dữ liệu ĐANG CÓ DỮ LIỆU.
-- KHÔNG nạp lại database/schema.sql: file đó bắt đầu bằng DROP TABLE và sẽ
-- xoá sạch đơn hàng, lịch hẹn, tài khoản khách.
--
-- Cách chạy
--   Trên máy:      mysql -u <user> -p <ten_db> < file_này.sql
--   InfinityFree:  vPanel -> phpMyAdmin -> chọn database -> tab SQL
--                  -> dán toàn bộ nội dung file -> Go
--
-- Chạy hai lần thì MySQL báo "Duplicate column name". Đó là báo an toàn.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- KÍNH ĐANG ĐEO
--
--   wear_lens_type      kiểu tròng đang đeo. Cùng bộ mã với bước "Chọn loại
--                       tròng kính" của hộp thoại mua hàng ('don-trong',
--                       'hai-trong', 'da-trong', 'mat-dat' — bảng giá nằm ở
--                       config/taxonomy.php, không phải trong CSDL), cộng
--                       thêm 'khong' = chưa đeo kính bao giờ. Không có khoá
--                       ngoại: bên kia là một mảng PHP trong file config.
--
--   wear_lens_features  tính chất tròng, NHIỀU giá trị ngăn bằng dấu gạch
--                       đứng: "Chống ánh sáng xanh|Siêu mỏng (chiết suất cao)".
--                       Dấu gạch đứng chứ không phải dấu phẩy vì nhãn nào cũng
--                       có thể chứa dấu phẩy. LƯU NGUYÊN VĂN, không lưu mã:
--                       đây là ghi chú tư vấn — thứ nhân viên ĐỌC chứ không
--                       phải thứ hệ thống lọc hay tính. Danh sách hợp lệ vẫn
--                       kiểm ở PHP (UserModel::savePrescription).
--
--   wear_frame_type     loại gọng, cũng nguyên văn cùng lý do.
--   wear_since          đã dùng cặp kính hiện tại bao lâu ("1 – 2 năm").
--   wear_note           câu khách tự ghi ("hay tuột gọng", "đeo máy tính cả ngày").
--
-- Cả năm đều NULL được: phần này KHÔNG bắt buộc. Khách bỏ trống thì trang tài
-- khoản hiện một lời mời khai, không hiện một thẻ đầy dấu gạch ngang.
-- ----------------------------------------------------------------------------
ALTER TABLE `prescriptions`
    ADD COLUMN `wear_lens_type`     VARCHAR(32)  NULL AFTER `recommendation`,
    ADD COLUMN `wear_lens_features` VARCHAR(255) NULL AFTER `wear_lens_type`,
    ADD COLUMN `wear_frame_type`    VARCHAR(64)  NULL AFTER `wear_lens_features`,
    ADD COLUMN `wear_since`         VARCHAR(32)  NULL AFTER `wear_frame_type`,
    ADD COLUMN `wear_note`          VARCHAR(255) NULL AFTER `wear_since`;
