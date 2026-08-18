-- ============================================================================
-- NÂNG CẤP 2026-08-18
-- Hộp thoại "Chọn hình thức mua" — chỉ mua gọng, hay mua gọng + cắt tròng
--
-- Thêm một mặt hàng thuộc danh mục `gong-kinh` / `kinh-mat` vào giỏ nay hỏi
-- khách hai câu: mua trần, hay cắt kèm tròng theo số đo mắt. Chọn cắt tròng
-- thì khách chọn một GÓI TRÒNG (bảng giá nằm ở config/taxonomy.php, không
-- phải trong CSDL — xem app/models/LensModel.php) và nhập độ hai mắt.
--
-- Ba thứ đó phải đi vào hoá đơn, nên `order_items` cần thêm cột. Không có
-- chúng thì đơn ghi "Gọng kính Titan Vin T01 — 3.340.000₫" mà không ai biết
-- 450.000₫ chênh lên là cái gì, và bộ phận mài tròng không có số để mài.
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
-- TRÒNG CẮT KÈM, GHI THẲNG VÀO DÒNG HÀNG
--
-- Vì sao KHÔNG tách thành một dòng `order_items` riêng: tròng cắt theo số đo
-- không tồn tại độc lập với chiếc gọng nó được lắp vào. Tách ra thì huỷ một
-- dòng mà quên dòng kia là đơn còn lại một cặp tròng không lắp vào đâu, và
-- mọi chỗ đếm "đơn này có mấy sản phẩm" đều đếm gấp đôi.
--
--   lens_id       khoá gói trong config/taxonomy.php ('blue-156', 'progressive'…)
--   lens_name     CHÉP LẠI tên gói tại thời điểm mua. Cùng lý do với
--                 `product_name`: đổi tên gói năm sau không được sửa hoá đơn cũ.
--   lens_price    phần tiền của tròng, ĐÃ NẰM TRONG `unit_price`.
--   prescription  số đo mắt, một chuỗi để nhân viên đọc ("MP −2.00 / −0.50 ·
--                 MT −2.25 · PD 62"). NULL = khách chưa biết độ, đo tại cửa hàng.
--
-- `lens_price` NẰM TRONG `unit_price` chứ không cộng thêm bên ngoài, để
-- `line_total = unit_price × quantity` giữ nguyên ý nghĩa cũ ở mọi nơi đang
-- đọc bảng này (trang xác nhận đơn, trang tài khoản, khu quản trị). Cột này
-- chỉ để TÁCH RA khi cần in "gọng 2.890.000₫ + tròng 450.000₫".
--
-- Không đặt khoá ngoại cho `lens_id`: bên kia là một mảng PHP trong file
-- config, không phải bảng.
-- ----------------------------------------------------------------------------
ALTER TABLE `order_items`
    ADD COLUMN `lens_id`      VARCHAR(40)  NULL          AFTER `variant_label`,
    ADD COLUMN `lens_name`    VARCHAR(160) NULL          AFTER `lens_id`,
    ADD COLUMN `lens_price`   BIGINT       NOT NULL DEFAULT 0 AFTER `lens_name`,
    ADD COLUMN `prescription` VARCHAR(160) NULL          AFTER `lens_price`;
