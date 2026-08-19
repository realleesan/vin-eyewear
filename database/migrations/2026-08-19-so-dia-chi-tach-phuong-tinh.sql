-- ============================================================================
-- NÂNG CẤP 2026-08-19
-- Sổ địa chỉ: tách phường/xã và tỉnh/thành phố thành cột riêng
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO: MỘT Ô `line2` KHÔNG DÙNG ĐƯỢC VỚI DANH SÁCH CHỌN
--
-- Trước đây khách gõ tay "Phường Tây Hồ, Thành phố Hà Nội" vào một ô duy nhất.
-- Hai hệ quả:
--
--   1. Trang thanh toán lại có BA ô riêng (address_line / address_ward /
--      address_city), nên phải đoán ngược bằng AddressModel::splitArea() —
--      cắt ở dấu phẩy CUỐI và cầu cho khách không gõ khác kiểu. Đoán sai thì
--      form thanh toán điền sẵn sai.
--   2. Không ghép được với danh sách hành chính. Chữ khách gõ không khớp
--      chính xác tên trong danh mục ("Hà Nội" / "TP Hà Nội" / "Thành phố Hà
--      Nội"), nên không lọc, không thống kê, không tính phí ship theo vùng.
--
-- Nay hai cấp được chọn từ danh sách (provinces.open-api.vn) và lưu thành
-- bốn cột: mã để chọn lại đúng mục trong danh sách khi mở form sửa, tên để
-- hiển thị và in lên đơn hàng.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- HAI CẤP, KHÔNG PHẢI BA — ĐÂY LÀ CẤU TRÚC HÀNH CHÍNH TỪ 2025
--
-- Không có cột quận/huyện, và đó không phải thiếu sót: từ 01/07/2025 Việt Nam
-- bỏ cấp huyện, còn tỉnh/thành phố -> phường/xã. API v2 trả về đúng 34 tỉnh
-- thành, và `?depth=2` cho thẳng danh sách phường/xã của tỉnh.
--
-- Mã phường/xã hiện dài tối đa 5 chữ số nên MEDIUMINT UNSIGNED (tới 16 triệu)
-- là dư; mã tỉnh lớn nhất là 96 nên SMALLINT UNSIGNED đủ.
--
-- CẢ BỐN CỘT ĐỀU NULL ĐƯỢC: khách tắt JavaScript hoặc API chết thì form lùi
-- về hai ô gõ tay, khi đó có tên mà không có mã. Ứng dụng luôn hiển thị theo
-- TÊN, mã chỉ để chọn lại trong danh sách.
-- ============================================================================

ALTER TABLE `addresses`
    ADD COLUMN `province_code` SMALLINT UNSIGNED  NULL AFTER `line1`,
    ADD COLUMN `province_name` VARCHAR(120)       NULL AFTER `province_code`,
    ADD COLUMN `ward_code`     MEDIUMINT UNSIGNED NULL AFTER `province_name`,
    ADD COLUMN `ward_name`     VARCHAR(120)       NULL AFTER `ward_code`;


-- ----------------------------------------------------------------------------
-- CHUYỂN DỮ LIỆU CŨ SANG
--
-- Cùng phép cắt mà splitArea() vẫn dùng: mẩu sau dấu phẩy CUỐI CÙNG là
-- tỉnh/thành, phần còn lại là phường/xã. Không có dấu phẩy thì cả chuỗi vào
-- ô tỉnh/thành — đó là phần bắt buộc phải có trong mọi địa chỉ.
--
-- Chỉ có TÊN, không có mã: chuỗi khách từng gõ tay không khớp chắc chắn với
-- mục nào trong danh mục, và đoán bừa một mã sai còn tệ hơn để trống. Lần đầu
-- khách mở form sửa, danh sách sẽ hỏi lại và ghi mã đúng vào.
-- ----------------------------------------------------------------------------
UPDATE `addresses`
   SET `province_name` = NULLIF(TRIM(SUBSTRING_INDEX(`line2`, ',', -1)), ''),
       `ward_name`     = NULLIF(
           TRIM(CASE WHEN `line2` LIKE '%,%'
                     THEN LEFT(`line2`,
                               CHAR_LENGTH(`line2`)
                               - CHAR_LENGTH(SUBSTRING_INDEX(`line2`, ',', -1))
                               - 1)
                     ELSE '' END),
           '')
 WHERE `line2` IS NOT NULL AND TRIM(`line2`) <> '';


-- ----------------------------------------------------------------------------
-- BỎ CỘT CŨ
--
-- Xoá hẳn chứ không để lại: giữ `line2` song song với hai cột mới là dựng sẵn
-- hai nguồn sự thật cho cùng một thông tin, và chỉ cần một chỗ quên cập nhật
-- là địa chỉ in trên đơn hàng khác địa chỉ khách thấy trong sổ.
--
-- AddressModel::splitArea() cũng bị gỡ trong cùng lần sửa này — nó tồn tại
-- chỉ để đoán ngược cái cột vừa xoá.
-- ----------------------------------------------------------------------------
ALTER TABLE `addresses`
    DROP COLUMN `line2`;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM addresses;                    -- có 4 cột mới, không còn line2
--   SELECT line1, ward_name, province_name FROM addresses LIMIT 20;
-- ----------------------------------------------------------------------------
