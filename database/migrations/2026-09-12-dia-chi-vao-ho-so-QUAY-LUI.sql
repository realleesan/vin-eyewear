-- ============================================================================
-- QUAY LUI cho 2026-09-12-dia-chi-vao-ho-so.sql
--
-- Trả profiles.address về dạng chuỗi ghép cũ ("<số nhà>, <phường>, <tỉnh>") và
-- gỡ bốn cột mới. Sau khi chạy, deploy ngược mã nguồn là sổ địa chỉ chạy lại
-- nguyên vẹn — bảng `addresses` chưa hề bị đụng tới.
--
-- ⚠ CHỈ CHẠY ĐƯỢC KHI CHƯA CHẠY 2026-09-12-go-bang-addresses.sql.
--   Đã DROP bảng rồi thì file này không dựng lại được gì: dữ liệu người nhận,
--   số điện thoại giao hàng, ghi chú và nhãn nằm trong chính bảng đó. Đường về
--   duy nhất khi ấy là bản sao lưu mysqldump.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. GHÉP LẠI CHUỖI ĐỊA CHỈ ĐẦY ĐỦ
--
-- Chép đúng cách AddressModel::syncProfileAddress() dựng chuỗi:
-- line1, rồi phường/xã và tỉnh/thành nối bằng ", ", bỏ qua phần rỗng.
-- CONCAT_WS bỏ NULL nhưng KHÔNG bỏ chuỗi rỗng, nên NULLIF() lo nốt vế đó —
-- thiếu nó thì địa chỉ chỉ có tỉnh sẽ ra ", , Thành phố Hà Nội".
--
-- Đọc từ `addresses` chứ không ghép từ bốn cột sắp gỡ: bảng gốc là nguồn thật,
-- và nó còn giữ cả những địa chỉ mà bước chép sang hồ sơ đã bỏ qua.
-- ----------------------------------------------------------------------------
UPDATE `profiles` p
  JOIN `addresses` a
    ON a.`user_id` = p.`id`
   AND a.`is_default` = 1
   SET p.`address` = CONCAT_WS(', ',
           NULLIF(TRIM(a.`line1`), ''),
           NULLIF(TRIM(COALESCE(a.`ward_name`, '')), ''),
           NULLIF(TRIM(COALESCE(a.`province_name`, '')), ''));


-- ----------------------------------------------------------------------------
-- 2. GỠ BỐN CỘT
--
-- Chạy hai lần sẽ báo lỗi 1091 "Can't DROP ...; check that column/key exists".
-- Vô hại: nghĩa là bước này đã chạy rồi.
-- ----------------------------------------------------------------------------
ALTER TABLE `profiles`
    DROP COLUMN `province_code`,
    DROP COLUMN `province_name`,
    DROP COLUMN `ward_code`,
    DROP COLUMN `ward_name`;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM profiles LIKE '%ward%';   -- không ra dòng nào
--
-- Rồi deploy ngược mã nguồn và mở /tai-khoan?muc=dia-chi: sổ địa chỉ phải
-- hiện đủ mọi địa chỉ như trước.
-- ----------------------------------------------------------------------------
