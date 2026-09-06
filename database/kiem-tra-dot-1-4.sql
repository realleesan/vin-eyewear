-- ============================================================================
-- KIỂM TRA ĐỢT 1–4 ĐÃ VÀO CHƯA — chạy được trên BẤT KỲ cơ sở dữ liệu nào
--
-- Dán toàn bộ file này vào phpMyAdmin (production) hoặc chạy dưới local:
--
--   mysql -u root -p vin_eyewear < database/kiem-tra-dot-1-4.sql
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO PHẢI HỎI CHÍNH LƯỢC ĐỒ, KHÔNG HỎI SỔ GHI `schema_migrations`
--
-- Sổ ghi chỉ đúng với máy CHẠY ĐƯỢC `database/migrate.sh` — tức là local.
-- Production nằm trên InfinityFree, không có SSH, nên migration ở đó được DÁN
-- TAY vào phpMyAdmin và sổ ghi không hề được cập nhật. Đọc sổ ghi trên
-- production là đọc một cuốn sổ trắng và kết luận rằng chưa có gì chạy.
--
-- Bảng dưới đây hỏi thẳng `information_schema`: cột này còn không, bảng kia có
-- chưa, kiểu ENUM đã thu chưa. Đó là sự thật, không phải bản ghi chép về nó.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ĐỌC KẾT QUẢ
--
-- File trả về BA bảng kết quả, đọc từ trên xuống:
--
--   A · Cấu trúc     mọi dòng phải OK. Ngoại lệ duy nhất là dòng
--                    'dot 3 · bang prescriptions', nó chỉ nhắc chứ không chấm.
--   B · Dữ liệu đợt 3  hai con số phải bằng nhau — xem chú thích ở đó.
--   C · Tồn đọng hoàn tiền  một con số, không phải OK/CHUA.
--
-- Chạy lại bao nhiêu lần cũng được: cả file chỉ ĐỌC, không ghi gì.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- PHẦN A — CẤU TRÚC. Mọi dòng phải ra OK.
-- ----------------------------------------------------------------------------
SELECT 'dot 1 · bang staff_stores da go' AS muc,
       IF(COUNT(*) = 0, 'OK', 'CHUA') AS ket_qua
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_stores'

UNION ALL SELECT 'dot 1 · bang favorites da go',
       IF(COUNT(*) = 0, 'OK', 'CHUA')
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'favorites'

-- Năm cột "kính đang đeo" — FR-DM-15. Đếm gộp cho gọn: phải bằng 0.
UNION ALL SELECT 'dot 1 · 5 cot kinh dang deo da go',
       IF(COUNT(*) = 0, 'OK', CONCAT('CHUA - con ', COUNT(*), ' cot'))
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prescriptions'
   AND COLUMN_NAME IN ('wear_lens_type','wear_lens_features','wear_frame_type',
                       'wear_since','wear_note')

UNION ALL SELECT 'dot 1 · cot nguoi_duoc_do da go',
       IF(COUNT(*) = 0, 'OK', 'CHUA')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_prescriptions'
   AND COLUMN_NAME = 'nguoi_duoc_do'

-- Đợt 2: ENUM phải thu còn ba giá trị. So chuỗi kiểu chứ không đếm dòng —
-- không còn dòng 'technician' nào KHÔNG có nghĩa là ENUM đã sửa.
UNION ALL SELECT 'dot 2 · ENUM user_roles con 3 gia tri',
       IF(COLUMN_TYPE = "enum('customer','staff','admin')", 'OK',
          CONCAT('CHUA - dang la ', COLUMN_TYPE))
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_roles'
   AND COLUMN_NAME = 'role'

UNION ALL SELECT 'dot 2 · khong con dong technician/manager',
       IF(COUNT(*) = 0, 'OK', CONCAT('CHUA - con ', COUNT(*), ' dong'))
  FROM user_roles WHERE role IN ('technician','manager')

-- Đợt 3: bảng `prescriptions` CỐ Ý còn — đường lùi giữ tới 13/09/2026.
-- Dòng này chỉ nhắc, không phải lỗi dù ra giá trị nào.
UNION ALL SELECT 'dot 3 · bang prescriptions (con = binh thuong den 13/09)',
       IF(COUNT(*) = 0, 'da go', 'con - dung y')
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prescriptions'

UNION ALL SELECT 'dot 4 · bang refund_requests da tao',
       IF(COUNT(*) = 1, 'OK', 'CHUA')
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests'

-- Tên cột PHẢI là received_amount. Bản nháp đầu của đợt 4 đặt tên
-- deposit_amount và đọc nhầm ý nghĩa — xem RefundRequestModel::daNhan().
UNION ALL SELECT 'dot 4 · cot tien ten received_amount',
       IF(COUNT(*) = 1, 'OK', 'CHUA - kiem lai ban migration')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests'
   AND COLUMN_NAME = 'received_amount'

UNION ALL SELECT 'dot 4 · cot orders.cancelled_by',
       IF(COUNT(*) = 1, 'OK', 'CHUA')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'
   AND COLUMN_NAME = 'cancelled_by'

UNION ALL SELECT 'dot 4 · cot users.deleted_source',
       IF(COUNT(*) = 1, 'OK', 'CHUA')
  FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
   AND COLUMN_NAME = 'deleted_source';


-- ----------------------------------------------------------------------------
-- PHẦN B — DỮ LIỆU ĐỢT 3. Hai con số, PHẢI BẰNG NHAU.
--
-- Trái: số khách có số đo ở bảng tóm tắt cũ.
-- Phải: số khách có số đo trong sổ mới.
--
-- Ra 'KIEM LAI' có ĐÚNG HAI nguyên nhân, và chúng khác hẳn nhau:
--
--   · Migration đợt 3 chưa chạy trên cơ sở dữ liệu này. Bình thường — chạy nó.
--   · Đợt 3 ĐÃ chạy mà con số bên phải vẫn nhỏ hơn. Nghĩa là có khách mất hồ sơ
--     đo mắt trong lúc hợp nhất. DỪNG LẠI, đừng chạy file gỡ bảng, báo lại.
--
-- Xem dòng 'dot 2 · ENUM' ở phần A để biết mình đang ở trường hợp nào: ENUM còn
-- năm giá trị thì cả đợt 2 lẫn đợt 3 đều chưa chạy.
--
-- Phải > Trái là bình thường: khách mới khai số đo sau khi đợt 3 chạy.
-- Bỏ qua phần này nếu bảng `prescriptions` đã bị gỡ (sau 13/09).
-- ----------------------------------------------------------------------------
SELECT (SELECT COUNT(DISTINCT user_id) FROM prescriptions)           AS khach_o_bang_cu,
       (SELECT COUNT(DISTINCT user_id) FROM customer_prescriptions)  AS khach_o_so_moi,
       IF((SELECT COUNT(DISTINCT user_id) FROM customer_prescriptions)
          >= (SELECT COUNT(DISTINCT user_id) FROM prescriptions), 'OK', 'KIEM LAI')
                                                                     AS ket_qua;


-- PHẦN C — TỒN ĐỌNG HOÀN TIỀN.
--
-- Đơn đã huỷ mà cửa hàng còn giữ tiền, chưa có yêu cầu hoàn tiền nào. Con số
-- này khớp với dải cảnh báo ở đầu màn /quan-tri/hoan-tien.
--
-- Lần đầu chạy nó SẼ lớn, và đó là đúng: mọi đơn huỷ từ trước khi có sổ đều rơi
-- vào đây. Chúng là tồn đọng thật cần dọn một lượt, không phải lỗi.
--
-- ĐI QUA PREPARE/EXECUTE vì câu này nhắc tên `refund_requests`. Viết thẳng thì
-- trên một cơ sở dữ liệu chưa chạy migration đợt 4, MySQL báo lỗi 1146 và
-- phpMyAdmin dừng cả file — nghĩa là người đang kiểm mất luôn phần A và B,
-- đúng hai phần họ cần đọc nhất lúc đó. Cùng khuôn mẫu với các file migration.
-- ----------------------------------------------------------------------------
SET @co := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'refund_requests');
SET @sql := IF(@co = 1,
    "SELECT COUNT(*) AS don_huy_con_giu_tien_chua_co_yeu_cau
       FROM orders o
      WHERE o.status = 'cancelled'
        AND o.payment_status IN ('deposit_paid', 'paid')
        AND NOT EXISTS (SELECT 1 FROM refund_requests r WHERE r.order_id = o.id)",
    "SELECT 'chua chay migration dot 4 - bo qua phan C' AS ghi_chu");
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;
