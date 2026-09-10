-- ============================================================================
-- QUAY LUI cho 2026-09-13-so-dia-chi-tro-lai.sql
--
-- Chép lại chiều addresses -> profiles, tức đưa dữ liệu về đúng trạng thái mà
-- mã nguồn bản 12/09 (địa chỉ nằm trong form Hồ sơ) chờ đợi.
--
-- KHÔNG GỠ BẢNG, KHÔNG GỠ CỘT — file xuôi cũng không tạo ra cấu trúc nào. Nó
-- chỉ chuyển dữ liệu, nên đường lùi cũng chỉ chuyển dữ liệu.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CÁI KHÔNG LÙI ĐƯỢC
--
-- File xuôi có thể đã TẠO MỚI vài dòng trong `addresses` (bước 2: khách khai
-- địa chỉ sau 12/09 nên sổ trống) và ĐẶT MẶC ĐỊNH cho vài khách khác (bước 3).
-- File này không xoá chúng đi: chúng không phải rác, và một địa chỉ mặc định
-- thừa còn hơn một khách không có địa chỉ mặc định nào.
--
-- Nếu thật sự cần một bản `addresses` y hệt lúc trước, đường về là bản sao lưu
-- mysqldump tạo ở đầu file xuôi.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- SAU KHI CHẠY FILE NÀY, PHẢI DEPLOY NGƯỢC MÃ NGUỒN
--
-- Để nguyên mã mới mà chạy file này thì không hỏng gì ngay, nhưng lần đầu khách
-- sửa sổ địa chỉ là AddressModel::dongBoHoSo() ghi đè lại `profiles` — tức mọi
-- thứ file này vừa làm bị hoàn tác, âm thầm.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- CHÉP ĐỊA CHỈ MẶC ĐỊNH VỀ HỒ SƠ
--
-- Giống hệt bước 2 của 2026-09-12-dia-chi-vao-ho-so.sql. `profiles.address` giữ
-- PHẦN CHI TIẾT (số nhà, tên đường); phường và tỉnh nằm ở bốn cột kia.
--
-- Khách không có địa chỉ mặc định nào thì không dòng nào khớp, và hồ sơ của họ
-- giữ nguyên — đúng, họ vốn chưa có gì để mà chép.
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
-- KIỂM TRA SAU KHI CHẠY — phải ra 0 dòng:
--
--   SELECT a.user_id FROM addresses a JOIN profiles p ON p.id = a.user_id
--    WHERE a.is_default = 1 AND COALESCE(p.address,'') <> COALESCE(a.line1,'');
-- ----------------------------------------------------------------------------
