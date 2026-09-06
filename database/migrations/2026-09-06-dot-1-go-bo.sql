-- ============================================================================
-- 2026-09-06 — ĐỢT 1: GỠ BỎ
--
-- Căn cứ: SRS v2.1.0, Phụ lục C. Các mục được gỡ trong file này:
--
--   K06 · K07 · K08 · K09   phân quyền theo cơ sở  → bảng `staff_stores`
--   A20                     mục "Kính đang đeo"    → 5 cột `prescriptions.wear_*`
--   H07                     trường người được đo   → `customer_prescriptions.nguoi_duoc_do`
--   J02                     trạng thái trưng bày Nháp → dữ liệu 'draft' → 'hidden'
--   C08                     kiểu tròng "Mắt đặt"   → dòng `lens_options`
--   —                       bảng `favorites` chưa từng có màn hình nào dùng
--
-- Ba mục khác của đợt 1 (F10 mở lại đơn, G10 tạo lịch hộ khách, G12 Zalo lịch
-- hẹn, E08 cộng dồn chuyển khoản, H03 và L07 lý do bắt buộc, H08 cảnh báo
-- chênh lệch, H09 hiệu lực 12 tháng, K12 hạn phiên) chỉ đụng MÃ NGUỒN, không
-- đụng lược đồ, nên không có gì trong file này.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ĐÂY LÀ MIGRATION XOÁ DỮ LIỆU. SAO LƯU TRƯỚC, KHÔNG CÓ NGOẠI LỆ.
--
-- Ba bước ở mục 1 đến 3 xoá cột và bảng. Cột đã DROP thì dữ liệu trong nó
-- không lấy lại được bằng bất kỳ câu lệnh nào — chỉ lấy lại được từ bản sao
-- lưu. Chạy đúng dòng này TRƯỚC, và chỉ chạy tiếp khi nó ra một file có kích
-- thước hợp lý:
--
--   mysqldump -u <user> -p <ten_csdl> > vin-eyewear-truoc-dot-1.sql
--
-- Trên hosting không có SSH (InfinityFree) thì dùng phpMyAdmin → Export →
-- Custom → chọn "Add DROP TABLE" → Go, và giữ file .sql tải về.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- THỨ TỰ CÓ Ý NGHĨA
--
-- Mục 0 (đổi 'draft' thành 'hidden') chạy TRƯỚC mọi lệnh DROP. Đó là bước duy
-- nhất trong file này KHÔNG mất dữ liệu, và nếu một lệnh DROP phía sau hỏng
-- giữa chừng thì phần đã chạy vẫn để lại một cơ sở dữ liệu hợp lệ.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN — nhưng KHÔNG hoàn toàn
--
-- DROP TABLE IF EXISTS và DELETE là idempotent. ALTER TABLE ... DROP COLUMN
-- thì KHÔNG: lần chạy thứ hai báo lỗi 1091 "Can't DROP; check that column
-- exists". Đó là lỗi VÔ HẠI — nó nghĩa là cột đã đi rồi. MySQL không có
-- "DROP COLUMN IF EXISTS" ở mọi phiên bản nên không tránh được; cứ bỏ qua
-- thông báo đó và chạy tiếp câu sau.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MÃ NGUỒN PHẢI ĐI CÙNG
--
-- Mã nguồn của đợt 1 KHÔNG còn đọc bất kỳ cột hay bảng nào bị gỡ ở đây, nên
-- thứ tự deploy không quan trọng. Nhưng đừng chạy file này trên một máy chủ
-- vẫn đang chạy mã nguồn CŨ: bản cũ còn SELECT `wear_lens_type` và
-- `nguoi_duoc_do`, và mất cột thì trang hồ sơ đo mắt đổ lỗi 1054.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 0. J02 — TRẠNG THÁI TRƯNG BÀY: BA CÒN HAI
--
-- 'draft' (Nháp) và 'hidden' (Ẩn) trước nay đều cho `is_visible` = 0, tức là
-- hai giá trị khác nhau nhưng cùng một hệ quả với người mua. Gộp về 'hidden'
-- không đổi thứ gì khách nhìn thấy.
--
-- KHÔNG đụng `is_visible`: nó vốn đã bằng 0 cho cả hai. Nếu có dòng nào lệch
-- (sửa tay trong CSDL) thì câu kiểm ở cuối file sẽ chỉ ra.
-- ----------------------------------------------------------------------------
UPDATE `products`
   SET `publish_status` = 'hidden'
 WHERE `publish_status` = 'draft';


-- ----------------------------------------------------------------------------
-- 1. C08 — GỠ KIỂU TRÒNG "MẮT ĐẶT"
--
-- Kiểu này không có bảng giá: cửa hàng báo giá sau khi xem thông số, và phần
-- tròng vào giỏ với giá 0đ. Chủ đầu tư đã bỏ — mọi kiểu tròng bán trên web
-- phải có giá xác định ngay lúc khách bấm đặt.
--
-- ĐƠN CŨ KHÔNG BỊ ẢNH HƯỞNG. Dòng hàng chép TÊN kiểu tròng vào chính nó lúc
-- đặt (`order_items`), không giữ khoá ngoại sang bảng này, nên một đơn từ
-- tháng trước vẫn in ra "Mắt đặt" đúng như lúc khách đặt.
-- ----------------------------------------------------------------------------
DELETE FROM `lens_options`
 WHERE `group_key` = 'loai-trong' AND `option_key` = 'mat-dat';


-- ----------------------------------------------------------------------------
-- 2. K06–K09 — GỠ PHÂN QUYỀN THEO CƠ SỞ
--
-- Bảng nối này quyết định mỗi tài khoản nội bộ nhìn thấy đơn hàng và lịch hẹn
-- của cơ sở nào. Chủ đầu tư đã bỏ hẳn cơ chế: mọi nhân viên thấy dữ liệu của
-- cả hệ thống.
--
-- `stores` VẪN GIỮ NGUYÊN. Cơ sở còn là một thuộc tính có thật của lịch hẹn
-- (khách chọn đến đâu) và của đơn nhận tại quầy — chỉ thôi làm ràng buộc
-- quyền. Ba cột `orders.store_id`, `appointments.store_id`,
-- `customer_prescriptions.store_id` đều giữ.
--
-- Ba khoá ngoại của bảng này đi theo bảng, không cần DROP riêng.
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `staff_stores`;


-- ----------------------------------------------------------------------------
-- 3. Bảng `favorites` — chưa từng có màn hình nào dùng
--
-- Có từ bản dựng đầu tiên nhưng không có nút "yêu thích" nào ở trang sản phẩm,
-- không có mục nào ở trang tài khoản, và không model nào trỏ vào. Một bảng
-- rỗng không hại gì, nhưng nó khiến người đọc lược đồ tin rằng tính năng ấy
-- có thật.
--
-- ĐỌC SỐ DÒNG TRƯỚC KHI XOÁ. Nếu câu dưới đây ra khác 0 thì có một đường ghi
-- nào đó mà rà soát chưa thấy — DỪNG LẠI và hỏi trước khi chạy tiếp:
--
--   SELECT COUNT(*) FROM favorites;
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `favorites`;


-- ----------------------------------------------------------------------------
-- 4. A20 — GỠ MỤC "KÍNH ĐANG ĐEO"
--
-- Năm cột khách tự khai về cặp kính đang dùng. Dữ liệu tự khai, không ai đối
-- chiếu, và trên thực tế gần như không được điền.
--
-- MUỐN GIỮ LẠI NHỮNG DÒNG ĐÃ ĐIỀN thì chạy câu này trước và lưu kết quả ra
-- ngoài — sau khi DROP thì không còn cách nào:
--
--   SELECT user_id, wear_lens_type, wear_lens_features,
--          wear_frame_type, wear_since, wear_note
--     FROM prescriptions
--    WHERE wear_lens_type     IS NOT NULL
--       OR wear_lens_features IS NOT NULL
--       OR wear_frame_type    IS NOT NULL
--       OR wear_since         IS NOT NULL
--       OR wear_note          IS NOT NULL;
--
-- Năm câu ALTER riêng chứ không gộp một câu năm mệnh đề: gộp thì lần chạy thứ
-- hai hỏng ở cột đầu và bốn cột sau không được thử, còn tách ra thì mỗi câu tự
-- báo riêng và ta biết chính xác cột nào đã đi, cột nào chưa.
-- ----------------------------------------------------------------------------
ALTER TABLE `prescriptions` DROP COLUMN `wear_lens_type`;
ALTER TABLE `prescriptions` DROP COLUMN `wear_lens_features`;
ALTER TABLE `prescriptions` DROP COLUMN `wear_frame_type`;
ALTER TABLE `prescriptions` DROP COLUMN `wear_since`;
ALTER TABLE `prescriptions` DROP COLUMN `wear_note`;


-- ----------------------------------------------------------------------------
-- 5. H07 — GỠ TRƯỜNG "NGƯỜI ĐƯỢC ĐO"
--
-- Cột này cho một tài khoản chứa số đo của nhiều người (mẹ và hai con, dùng
-- chung số điện thoại). Chủ đầu tư đã bỏ: MỘT TÀI KHOẢN ỨNG VỚI MỘT NGƯỜI.
--
-- ⚠️ ĐÂY LÀ BƯỚC NGUY HIỂM NHẤT CỦA CẢ FILE, và nó không nguy hiểm vì lệnh
-- SQL mà vì DỮ LIỆU Y TẾ.
--
-- Sau khi cột này đi, những bản ghi từng thuộc về người thân sẽ nằm lẫn vào
-- lịch sử của chủ tài khoản mà KHÔNG CÒN GÌ PHÂN BIỆT. Bản mới nhất trong đó
-- trở thành "độ hiện tại" của chủ tài khoản và được điền sẵn khi họ mua kính —
-- tức là số của người khác có thể đi thẳng vào một đơn cắt tròng.
--
-- CHẠY CÂU NÀY TRƯỚC. Nếu nó ra 0 dòng thì bước 5 an toàn tuyệt đối:
--
--   SELECT u.email, u.phone, p.nguoi_duoc_do,
--          cp.measured_at, cp.od_sph, cp.os_sph
--     FROM customer_prescriptions cp
--     JOIN users    u ON u.id = cp.user_id
--     LEFT JOIN profiles p ON p.id = cp.user_id
--    WHERE COALESCE(cp.nguoi_duoc_do, '') <> ''
--    ORDER BY cp.user_id, cp.measured_at DESC;
--
-- RA KHÁC 0 THÌ DỪNG. Xử lý xong mới chạy tiếp, theo một trong hai cách:
--
--   a) Lập tài khoản riêng cho từng người thân rồi chuyển các bản ghi sang
--      (UPDATE customer_prescriptions SET user_id = '<id mới>' WHERE ...).
--      Đây là cách đúng với ý "một tài khoản một người".
--
--   b) Xoá các bản ghi của người thân, nếu cửa hàng xác nhận không cần giữ:
--      DELETE FROM customer_prescriptions WHERE COALESCE(nguoi_duoc_do,'') <> '';
--
-- Đừng chọn cách thứ ba là cứ để nguyên rồi DROP.
-- ----------------------------------------------------------------------------
ALTER TABLE `customer_prescriptions` DROP COLUMN `nguoi_duoc_do`;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
-- Cả sáu câu phải ra đúng như ghi bên phải. Câu nào lệch thì bước tương ứng
-- chưa chạy xong.
--
--   SELECT COUNT(*) FROM products WHERE publish_status = 'draft';
--       -- = 0
--
--   SELECT publish_status, COUNT(*) FROM products GROUP BY publish_status;
--       -- chỉ còn 'visible' và 'hidden'
--
--   SELECT COUNT(*) FROM products WHERE publish_status = 'hidden' AND is_visible = 1;
--       -- = 0 (nếu khác 0 là có dòng sửa tay, sửa lại bằng:
--       --  UPDATE products SET is_visible = 0 WHERE publish_status = 'hidden';)
--
--   SELECT COUNT(*) FROM lens_options WHERE option_key = 'mat-dat';
--       -- = 0
--
--   SHOW TABLES LIKE 'staff_stores';   -- không ra dòng nào
--   SHOW TABLES LIKE 'favorites';      -- không ra dòng nào
--
--   SHOW COLUMNS FROM prescriptions LIKE 'wear\\_%';        -- không ra dòng nào
--   SHOW COLUMNS FROM customer_prescriptions LIKE 'nguoi_duoc_do';  -- không ra dòng nào
--
-- ----------------------------------------------------------------------------
-- QUAY LUI
--
-- Xem database/migrations/2026-09-06-dot-1-go-bo-QUAY-LUI.sql. File đó dựng
-- lại CẤU TRÚC (bảng và cột) nhưng KHÔNG dựng lại được DỮ LIỆU đã nằm trong
-- chúng — dữ liệu chỉ về từ bản mysqldump đã tạo ở đầu file này.
-- ----------------------------------------------------------------------------
