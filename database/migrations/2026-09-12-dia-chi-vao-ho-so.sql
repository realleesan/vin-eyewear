-- ============================================================================
-- 2026-09-12 — ĐỊA CHỈ VỀ THẲNG HỒ SƠ, BỎ SỔ ĐỊA CHỈ
--
-- Mỗi khách từ nay có ĐÚNG MỘT địa chỉ, và nó nằm trong chính form Hồ sơ, cạnh
-- họ tên / số điện thoại / email. Sổ nhiều địa chỉ (bảng `addresses`, mục
-- ?muc=dia-chi) đã gỡ khỏi giao diện.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- FILE NÀY KHÔNG XOÁ GÌ CẢ
--
-- Nó chỉ THÊM bốn cột vào `profiles` rồi chép địa chỉ mặc định của từng khách
-- sang. Bảng `addresses` vẫn còn nguyên với đủ dữ liệu — mã nguồn mới đơn giản
-- là không đọc nó nữa.
--
-- Cố ý tách làm hai bước, đúng nếp của đợt 3 (xem
-- 2026-09-06-dot-3-go-bang-tom-tat.sql): chừng nào bảng cũ còn thì deploy
-- ngược mã nguồn là chạy lại được ngay. Lệnh DROP nằm ở file riêng
-- 2026-09-12-go-bang-addresses.sql, chạy sau khi đã yên tâm.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CÁI GÌ ĐI, CÁI GÌ Ở LẠI
--
-- Bảng `addresses` giữ bảy thứ cho mỗi địa chỉ; hồ sơ chỉ nhận bốn:
--
--     line1          -> profiles.address          (số nhà, tên đường)
--     province_code  -> profiles.province_code
--     province_name  -> profiles.province_name
--     ward_code      -> profiles.ward_code
--     ward_name      -> profiles.ward_name
--
--     recipient_name -> KHÔNG chép. Hồ sơ đã có full_name, và một địa chỉ
--                       trong hồ sơ thì người nhận mặc nhiên là chủ tài khoản.
--                       Muốn gửi cho người khác thì gõ ở trang thanh toán —
--                       ô "Người nhận" ở đó vẫn còn.
--     phone          -> KHÔNG chép, cùng lẽ: đã có profiles.phone.
--     ghi_chu, nhan  -> KHÔNG chép. Cả hai chỉ có nghĩa khi sổ có NHIỀU địa
--                       chỉ để phân biệt ("Nhà riêng" vs "Công ty"); còn đúng
--                       một địa chỉ thì nhãn không nói thêm được gì.
--
-- Ba thứ không chép ấy vẫn nằm trong `addresses` cho tới khi chạy file DROP.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- profiles.address ĐỔI Ý NGHĨA — đây là chỗ dễ nhầm nhất của file này
--
-- Cột này ĐÃ CÓ SẴN, nhưng trước nay nó là BẢN SAO của cả địa chỉ mặc định đã
-- ghép chuỗi: "Số 12 ngõ 5 Đội Cấn, Phường Ba Đình, Thành phố Hà Nội" —
-- AddressModel::syncProfileAddress() ghi đè mỗi lần khách sửa sổ.
--
-- Từ nay nó chỉ giữ PHẦN CHI TIẾT: "Số 12 ngõ 5 Đội Cấn". Phường và tỉnh nằm ở
-- bốn cột mới. Câu UPDATE dưới đây vì thế GHI ĐÈ cột address bằng
-- addresses.line1 chứ không giữ chuỗi cũ — để nguyên thì phường/tỉnh xuất hiện
-- hai lần trên phiếu gửi hàng.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- SAO LƯU TRƯỚC — NFR-R08
--
--   mysqldump -u <user> -p <ten_csdl> > vin-eyewear-truoc-dia-chi-vao-ho-so.sql
--
-- File này không xoá bảng, nhưng nó GHI ĐÈ profiles.address của mọi khách có
-- địa chỉ mặc định. Không có ngoại lệ.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. BỐN CỘT MỚI
--
-- Kiểu dữ liệu chép NGUYÊN của `addresses` (SMALLINT cho tỉnh, MEDIUMINT cho
-- phường/xã, VARCHAR(120) cho tên) — lệch một chữ là câu UPDATE bên dưới âm
-- thầm cắt cụt giá trị.
--
-- NULL được cả bốn, cùng lý do như ở bảng cũ: JavaScript tắt hoặc
-- provinces.open-api.vn chết thì form lùi về hai ô gõ tay, khi đó có TÊN mà
-- không có MÃ. Ứng dụng luôn hiển thị theo tên; mã chỉ để address-picker.js
-- chọn lại đúng mục khi mở form.
--
-- ⚠ MySQL 8 / MariaDB 10.4 không có `ADD COLUMN IF NOT EXISTS` dùng chung
-- được, nên chạy file này HAI LẦN sẽ báo lỗi 1060 "Duplicate column name".
-- Đó là lỗi vô hại: nó nghĩa là bước này đã chạy rồi.
-- ----------------------------------------------------------------------------
ALTER TABLE `profiles`
    ADD COLUMN `province_code` SMALLINT UNSIGNED  NULL AFTER `address`,
    ADD COLUMN `province_name` VARCHAR(120)       NULL AFTER `province_code`,
    ADD COLUMN `ward_code`     MEDIUMINT UNSIGNED NULL AFTER `province_name`,
    ADD COLUMN `ward_name`     VARCHAR(120)       NULL AFTER `ward_code`;


-- ----------------------------------------------------------------------------
-- 2. CHÉP ĐỊA CHỈ MẶC ĐỊNH SANG
--
-- JOIN thẳng vào `is_default = 1`, không lấy "địa chỉ mới nhất": mặc định là
-- thứ khách đã tự chọn làm nơi nhận hàng, và cũng đúng thứ mà trang thanh toán
-- vẫn điền sẵn từ trước tới nay. Chuyển sang một địa chỉ khác nghĩa là đổi
-- hành vi của trang đó ngay giữa lần deploy.
--
-- Khách KHÔNG có địa chỉ mặc định nào thì không có dòng nào khớp — hồ sơ của
-- họ giữ nguyên address cũ và bốn cột mới để NULL. Đúng: họ vốn chưa có địa
-- chỉ nào để mà mất.
--
-- Ràng buộc "đúng một mặc định mỗi khách" do AddressModel::setDefault() giữ
-- chứ không phải UNIQUE (xem ghi chú trong schema.sql). Nếu dữ liệu từng lệch
-- và một khách có hai dòng is_default = 1, MySQL chọn một dòng bất kỳ. Chạy
-- câu này TRƯỚC để biết có phải lo không — ra 0 dòng là sạch:
--
--     SELECT user_id, COUNT(*) FROM addresses WHERE is_default = 1
--      GROUP BY user_id HAVING COUNT(*) > 1;
-- ----------------------------------------------------------------------------
UPDATE `profiles` p
  JOIN `addresses` a
    ON a.`user_id` = p.`id`
   AND a.`is_default` = 1
   SET p.`address`       = a.`line1`,
       p.`province_code` = a.`province_code`,
       p.`province_name` = a.`province_name`,
       p.`ward_code`     = a.`ward_code`,
       p.`ward_name`     = a.`ward_name`;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
-- 1. Bốn cột đã có:
--
--      SHOW COLUMNS FROM profiles LIKE '%ward%';      -- 2 dòng
--      SHOW COLUMNS FROM profiles LIKE '%province%';  -- 2 dòng
--
-- 2. Số khách có địa chỉ trong hồ sơ phải BẰNG số khách có địa chỉ mặc định
--    trong sổ — ra hai con số khác nhau là câu UPDATE đã bỏ sót ai đó:
--
--      SELECT COUNT(*) FROM profiles  WHERE province_name IS NOT NULL;
--      SELECT COUNT(DISTINCT user_id) FROM addresses WHERE is_default = 1
--                                       AND province_name IS NOT NULL;
--
-- 3. Mở /tai-khoan?muc=ho-so bằng một tài khoản khách CÓ địa chỉ: ba ô Tỉnh /
--    Phường / Địa chỉ chi tiết phải hiện sẵn đúng nội dung cũ, và ô Tỉnh phải
--    là danh sách chọn chứ không phải ô gõ tay (address-picker.js).
--
-- 4. Mở /thanh-toan bằng chính tài khoản đó: hai ô Tỉnh và Phường vẫn phải
--    điền sẵn như trước.
--
-- ----------------------------------------------------------------------------
-- QUAY LUI
--
-- 2026-09-12-dia-chi-vao-ho-so-QUAY-LUI.sql — dựng lại profiles.address theo
-- đúng dạng chuỗi ghép cũ rồi gỡ bốn cột. Chạy được VÔ ĐIỀU KIỆN chừng nào
-- chưa chạy file DROP, vì bảng `addresses` vẫn còn nguyên dữ liệu gốc.
-- ----------------------------------------------------------------------------
