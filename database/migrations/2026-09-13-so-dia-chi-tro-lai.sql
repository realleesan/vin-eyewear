-- ============================================================================
-- 2026-09-13 — SỔ ĐỊA CHỈ TRỞ LẠI: ĐỒNG BỘ NGƯỢC `profiles` -> `addresses`
--
-- (Viết ngày 10/09/2026. Tên file mang ngày 13/09 để đứng SAU cặp file 12/09
--  mà nó bắt buộc phải chạy sau — thứ tự thi hành thật vẫn là thứ tự trong
--  mảng MIGRATIONS của migrate.sh, xem CLAUDE.md.)
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO CẦN FILE NÀY
--
-- UC-USER-05 đòi lại một SỔ nhiều địa chỉ (Khu vực 2), nên mã nguồn quay lại
-- đọc/ghi bảng `addresses` — bảng ấy chưa bao giờ bị xoá, vì
-- 2026-09-12-go-bang-addresses.sql không được khai trong migrate.sh và bản thân
-- migrate.sh có một nhánh từ chối chạy nó.
--
-- NHƯNG DỮ LIỆU ĐÃ LỆCH. Trong quãng từ 12/09 tới nay, form Hồ sơ ghi thẳng vào
-- năm cột `profiles` (address · province_* · ward_*) và KHÔNG đụng tới
-- `addresses`. Nên với khách đã sửa địa chỉ trong quãng đó:
--
--     profiles  = địa chỉ MỚI, đúng, thứ trang thanh toán đang dùng
--     addresses = địa chỉ CŨ, từ trước 12/09
--
-- Bật sổ lên mà không chạy file này thì khách mở trang Hồ sơ và thấy sổ của
-- mình hiện một địa chỉ họ đã sửa từ lâu — trông y hệt như hệ thống vừa nuốt
-- mất thay đổi của họ.
--
-- CHIỀU ĐỒNG BỘ Ở ĐÂY LÀ NGƯỢC với migration 12/09: lần đó chép
-- addresses -> profiles, lần này chép profiles -> addresses. `profiles` là bản
-- ghi mới hơn nên nó thắng. Từ sau file này, chiều duy nhất là
-- addresses -> profiles, do AddressModel::dongBoHoSo() giữ.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CÁI GÌ KHÔNG LẤY LẠI ĐƯỢC
--
-- Hồ sơ chỉ giữ năm cột. Ba thứ mà `addresses` có thêm — người nhận, số điện
-- thoại giao hàng, ghi chú/nhãn — KHÔNG có bản sao nào bên `profiles`, nên câu
-- UPDATE dưới đây giữ nguyên giá trị cũ của chúng. Đúng: người nhận của một địa
-- chỉ không đổi chỉ vì số nhà đổi.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- SAO LƯU TRƯỚC — NFR-R08
--
--   mysqldump -u <user> -p <ten_csdl> addresses profiles > vin-eyewear-truoc-so-dia-chi.sql
--
-- File này GHI ĐÈ dòng địa chỉ mặc định của mọi khách có địa chỉ trong hồ sơ.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 0. TRƯỚC KHI CHẠY — hai câu để biết mình sắp đụng vào bao nhiêu dòng
--
--   -- Số địa chỉ mặc định sắp bị ghi đè:
--   SELECT COUNT(*) FROM addresses a JOIN profiles p ON p.id = a.user_id
--    WHERE a.is_default = 1
--      AND (COALESCE(a.line1,'') <> COALESCE(p.address,'')
--        OR COALESCE(a.ward_name,'') <> COALESCE(p.ward_name,'')
--        OR COALESCE(a.province_name,'') <> COALESCE(p.province_name,''));
--
--   -- Số khách sắp được tạo dòng đầu tiên trong sổ:
--   SELECT COUNT(*) FROM profiles p
--    WHERE TRIM(COALESCE(p.address,'')) <> ''
--      AND NOT EXISTS (SELECT 1 FROM addresses a WHERE a.user_id = p.id);
-- ----------------------------------------------------------------------------


-- ----------------------------------------------------------------------------
-- 1. GHI ĐÈ ĐỊA CHỈ MẶC ĐỊNH BẰNG BẢN TRONG HỒ SƠ
--
-- CHỈ dòng is_default = 1: hồ sơ chỉ từng giữ bản sao của đúng dòng đó
-- (xem bước 2 của migration 12/09), nên nó không nói được gì về các địa chỉ phụ.
--
-- ĐIỀU KIỆN `TRIM(...) <> ''` ở cuối là hàng rào quan trọng nhất của file:
-- khách chưa từng khai địa chỉ trong hồ sơ thì p.address rỗng, và không có vế
-- ấy thì câu này XOÁ TRẮNG số nhà của họ trong sổ. Có địa chỉ mới thì mới ghi đè.
--
-- Chạy lại vô hại: lần hai không dòng nào còn khác nhau nữa nên khớp 0 dòng.
-- ----------------------------------------------------------------------------
UPDATE `addresses` a
  JOIN `profiles` p
    ON p.`id` = a.`user_id`
   AND a.`is_default` = 1
   SET a.`line1`         = p.`address`,
       a.`province_code` = p.`province_code`,
       a.`province_name` = p.`province_name`,
       a.`ward_code`     = p.`ward_code`,
       a.`ward_name`     = p.`ward_name`
 WHERE TRIM(COALESCE(p.`address`, '')) <> ''
   AND (COALESCE(a.`line1`, '')         <> COALESCE(p.`address`, '')
     OR COALESCE(a.`province_name`, '') <> COALESCE(p.`province_name`, '')
     OR COALESCE(a.`ward_name`, '')     <> COALESCE(p.`ward_name`, ''));


-- ----------------------------------------------------------------------------
-- 2. KHÁCH CÓ ĐỊA CHỈ TRONG HỒ SƠ MÀ SỔ TRỐNG -> TẠO DÒNG ĐẦU TIÊN
--
-- Đây là những người đăng ký (hoặc khai địa chỉ lần đầu) SAU 12/09, tức sau khi
-- sổ đã bị gỡ — họ chưa bao giờ có một dòng nào trong `addresses`.
--
-- NGƯỜI NHẬN VÀ SỐ ĐIỆN THOẠI lấy từ chính hồ sơ: địa chỉ trong hồ sơ vốn thuộc
-- về chủ tài khoản, nên người nhận mặc nhiên là họ. Gửi cho người khác thì sửa
-- lại trong sổ, hoặc đổi ô "Người nhận" ở trang thanh toán.
--
-- ⚠ BỎ QUA KHÁCH THIẾU HỌ TÊN HOẶC SỐ ĐIỆN THOẠI. Hai cột ấy NOT NULL trong
-- `addresses`, và nhét chuỗi rỗng vào thì sổ hiện ra một địa chỉ không có người
-- nhận — vừa vô nghĩa vừa không sửa được (form đòi cả hai). Để họ ở trạng thái
-- "sổ trống" là trung thực hơn: khách bấm "Thêm địa chỉ mới" và khai một lần.
-- Đếm số người rơi vào diện này:
--
--     SELECT COUNT(*) FROM profiles p
--      WHERE TRIM(COALESCE(p.address,'')) <> ''
--        AND (TRIM(COALESCE(p.full_name,'')) = '' OR TRIM(COALESCE(p.phone,'')) = '')
--        AND NOT EXISTS (SELECT 1 FROM addresses a WHERE a.user_id = p.id);
--
-- UUID() gọi tường minh chứ không nhờ DEFAULT của cột: MariaDB đời cũ không
-- nhận giá trị mặc định là biểu thức, và câu này phải chạy được ở cả hai.
--
-- ĐI QUA MỘT BẢNG TẠM thay vì `NOT EXISTS (SELECT ... FROM addresses)` viết
-- thẳng trong câu INSERT. Lý do là lỗi 1093 của MySQL ("You can't specify
-- target table for update in FROM clause"): hỏi lại chính bảng đang ghi trong
-- cùng một câu lệnh là thứ MySQL từ chối ở nhiều hình dạng, và ranh giới giữa
-- hình được với hình không được đổi theo phiên bản (8.0.14 bắt đầu gộp bảng dẫn
-- xuất vào câu ngoài, làm hỏng vài câu vốn chạy). Bảng tạm thì không có tranh
-- cãi nào: nó được tính xong trước khi câu ghi bắt đầu.
--
-- Bảng tạm sống theo KẾT NỐI, và migrate.sh nạp cả file bằng đúng một lệnh
-- `mysql < file` — tức một kết nối. Chạy tay từng câu ở hai cửa sổ khác nhau
-- thì bước sau không thấy bảng tạm của bước trước.
--
-- Chạy lại vô hại: lần hai bảng tạm rỗng vì mọi người đã có dòng trong sổ.
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `_tam_khach_chua_co_so`;

CREATE TEMPORARY TABLE `_tam_khach_chua_co_so` AS
SELECT p.`id` AS user_id
  FROM `profiles` p
  LEFT JOIN `addresses` a ON a.`user_id` = p.`id`
 WHERE a.`user_id` IS NULL
   AND TRIM(COALESCE(p.`address`, ''))   <> ''
   AND TRIM(COALESCE(p.`full_name`, '')) <> ''
   AND TRIM(COALESCE(p.`phone`, ''))     <> '';

INSERT INTO `addresses`
    (`id`, `user_id`, `recipient_name`, `phone`, `line1`,
     `province_code`, `province_name`, `ward_code`, `ward_name`, `is_default`)
SELECT UUID(), p.`id`, p.`full_name`, p.`phone`, p.`address`,
       p.`province_code`, p.`province_name`, p.`ward_code`, p.`ward_name`, 1
  FROM `profiles` p
  JOIN `_tam_khach_chua_co_so` t ON t.`user_id` = p.`id`;

DROP TEMPORARY TABLE `_tam_khach_chua_co_so`;


-- ----------------------------------------------------------------------------
-- 3. KHÁCH CÓ ĐỊA CHỈ TRONG SỔ MÀ KHÔNG CÁI NÀO MẶC ĐỊNH
--
-- Không phải trạng thái do file này gây ra — nó có thể đã lệch từ trước, và
-- cả UNIQUE lẫn ứng dụng đều không bắt được vì nó hợp lệ về mặt cấu trúc. Nhưng
-- từ nay nó có hậu quả thật: AddressModel::dongBoHoSo() sẽ XOÁ TRẮNG địa chỉ
-- trong hồ sơ của họ ở lần ghi tiếp theo, và trang thanh toán mất phần điền sẵn.
--
-- Chọn địa chỉ CŨ NHẤT làm mặc định, cùng luật với AddressModel::xoa().
--
-- CHỐT HOÀ: `created_at` là DATETIME, chỉ tới giây — hai địa chỉ tạo trong cùng
-- một giây (dữ liệu mẫu nạp hàng loạt) sẽ cùng là "cũ nhất", và đặt cả hai làm
-- mặc định thì vừa sửa xong một chỗ lệch đã tạo ra một chỗ lệch khác. MIN(id)
-- phân xử nốt: so chuỗi UUID không có nghĩa gì về thời gian, nhưng nó luôn cho
-- đúng MỘT người thắng, và đó là tất cả những gì cần ở đây.
--
-- Vẫn qua bảng tạm, cùng lý do 1093 như bước 2.
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS `_tam_thieu_mac_dinh`;
DROP TEMPORARY TABLE IF EXISTS `_tam_dia_chi_len_mac_dinh`;

CREATE TEMPORARY TABLE `_tam_thieu_mac_dinh` AS
SELECT x.`user_id`, MIN(x.`created_at`) AS som_nhat
  FROM `addresses` x
 GROUP BY x.`user_id`
HAVING SUM(x.`is_default`) = 0;

CREATE TEMPORARY TABLE `_tam_dia_chi_len_mac_dinh` AS
SELECT MIN(a.`id`) AS id
  FROM `addresses` a
  JOIN `_tam_thieu_mac_dinh` t
    ON t.`user_id` = a.`user_id`
   AND a.`created_at` = t.som_nhat
 GROUP BY a.`user_id`;

UPDATE `addresses`
   SET `is_default` = 1
 WHERE `id` IN (SELECT `id` FROM `_tam_dia_chi_len_mac_dinh`);

DROP TEMPORARY TABLE `_tam_thieu_mac_dinh`;
DROP TEMPORARY TABLE `_tam_dia_chi_len_mac_dinh`;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
-- 1. Không còn khách nào có sổ mà thiếu địa chỉ mặc định — phải ra 0 dòng:
--
--      SELECT user_id FROM addresses GROUP BY user_id HAVING SUM(is_default) = 0;
--
-- 2. Không còn khách nào có hai địa chỉ mặc định — phải ra 0 dòng:
--
--      SELECT user_id FROM addresses WHERE is_default = 1
--       GROUP BY user_id HAVING COUNT(*) > 1;
--
-- 3. Hồ sơ và sổ đã khớp — phải ra 0 dòng:
--
--      SELECT a.user_id FROM addresses a JOIN profiles p ON p.id = a.user_id
--       WHERE a.is_default = 1
--         AND TRIM(COALESCE(p.address,'')) <> ''
--         AND COALESCE(a.line1,'') <> COALESCE(p.address,'');
--
-- 4. Mở /tai-khoan?muc=ho-so bằng một tài khoản khách CÓ địa chỉ: khu vực
--    "Sổ địa chỉ" phải hiện đúng địa chỉ mà form Hồ sơ vẫn hiện trước đây, kèm
--    nhãn "Mặc định".
--
-- 5. Mở /thanh-toan bằng chính tài khoản đó: các ô địa chỉ vẫn điền sẵn như cũ.
--
-- ----------------------------------------------------------------------------
-- QUAY LUI
--
-- 2026-09-13-so-dia-chi-tro-lai-QUAY-LUI.sql — chép lại chiều
-- addresses -> profiles, tức đưa dữ liệu về đúng trạng thái mà mã nguồn của
-- bản 12/09 chờ đợi. Không gỡ bảng, không gỡ cột: file xuôi cũng không tạo ra
-- cấu trúc nào.
-- ----------------------------------------------------------------------------
