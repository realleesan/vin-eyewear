-- ============================================================================
-- 2026-09-06 — ĐỢT 3: HỢP NHẤT SỔ SỐ ĐO
--
-- Căn cứ: SRS v2.1.0, FR-DM-01 và FR-DM-03; kịch bản chuyển đổi mục 6.7.3
-- bước 2.
--
-- Trước đây số đo mắt nằm ở HAI nơi:
--
--   prescriptions           bảng tóm tắt, đúng MỘT dòng mỗi khách. Đây là thứ
--                           trang tài khoản của khách đọc, và là thứ điền sẵn
--                           khi họ mua kính.
--   customer_prescriptions  sổ lịch sử chỉ-thêm, nhiều dòng mỗi khách, có
--                           nguồn dữ liệu và có phiên bản.
--
-- Một hàm mirrorLatest() chép từ sổ sang bảng tóm tắt sau mỗi lần ghi. Hai nơi
-- lưu cùng một sự thật thì sớm muộn lệch nhau, và khi lệch thì không có cách
-- nào biết bên nào đúng — trong khi đây là con số đem đi mài tròng.
--
-- File này CHÉP nốt phần chỉ có ở bảng tóm tắt sang sổ. Sổ trở thành nguồn
-- duy nhất.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- FILE NÀY KHÔNG XOÁ GÌ CẢ
--
-- Bảng `prescriptions` vẫn còn nguyên sau khi chạy. Đó là chủ ý, theo bước 6
-- của kịch bản chuyển đổi: *"gỡ bảng và cột không còn dùng — chỉ thực hiện sau
-- khi năm bước trên đã được kiểm và hệ thống chạy ổn định ít nhất một tuần."*
--
-- Việc gỡ nằm ở file riêng: 2026-09-06-dot-3-go-bang-tom-tat.sql. ĐỪNG chạy
-- file đó cùng hôm nay.
--
-- Vì thế đợt 3 là đợt DUY NHẤT trong loạt này có đường lùi thật sự: mã nguồn
-- cũ deploy ngược lại là chạy được ngay, vì bảng nó đọc vẫn còn và vẫn mang
-- đúng dữ liệu như trước.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHÉP KHI BẢNG TÓM TẮT MỚI HƠN SỔ — KHÔNG PHẢI "KHI SỔ CÒN TRỐNG"
--
-- Bản đầu của file này lọc `NOT EXISTS (… c.user_id = p.user_id)`, tức bỏ qua
-- mọi khách đã có bất kỳ dòng nào trong sổ. Cách đó SÓT một ca có thật:
--
--   Khách A được kỹ thuật viên đo 01/08 → sổ có một dòng, bảng tóm tắt là bản
--   sao của nó. Ngày 01/09 khách A vào trang tài khoản tự sửa số của mình →
--   mã CŨ GHI ĐÈ dòng bảng tóm tắt bằng số tự khai, còn sổ KHÔNG ĐỔI.
--
--   Tới lúc chạy migration, khách A đã có dòng trong sổ nên bị loại, và con số
--   họ tự khai ngày 01/09 không được chép đi đâu cả. Ngay sau khi deploy, trang
--   tài khoản của họ quay về số đo 01/08. Không lỗi, không cảnh báo — và câu
--   hậu kiểm đếm theo NGƯỜI cũng không bắt được, vì số người không đổi, chỉ nội
--   dung đổi.
--
-- Nên điều kiện đúng là so THỜI ĐIỂM: chép khi bảng tóm tắt được ghi SAU lần
-- ghi cuối cùng vào sổ. Nó bắt cả hai ca cần bắt — khách chưa có dòng nào
-- (COALESCE lùi về một mốc rất cũ nên mọi dòng đều "mới hơn"), và khách đã tự
-- sửa sau lần đo gần nhất.
--
-- Ca ngược lại tự loại đúng: dòng do mirrorLatest() chép xuống mang
-- `updated_at` bằng hoặc cũ hơn bản trong sổ mà nó chép từ đó, nên không thoả
-- dấu lớn hơn — không sinh bản trùng.
--
-- CHẠY LẠI: lượt hai, mọi dòng đủ điều kiện đã có bản sao trong sổ với
-- `created_at` = `p.updated_at`, nên phép so `>` không còn đúng và câu chèn 0
-- dòng. Vẫn idempotent.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- NGUỒN LÀ 'customer', KHÔNG PHẢI 'store'
--
-- Bảng tóm tắt KHÔNG có cột nguồn. Nó nhận dữ liệu từ hai chỗ: khách tự nhập ở
-- trang tài khoản, và mirrorLatest() chép xuống từ sổ. Phần chép xuống thì
-- khách đó đã có dòng trong sổ nên bị mệnh đề NOT EXISTS loại ra — phần còn
-- lại, tức phần file này thật sự chép, đúng là khách tự khai.
--
-- Đoán 'store' cho một con số không rõ ai đo là nói dối trên dữ liệu y tế theo
-- chiều nguy hiểm: người đọc sẽ tin nó hơn mức đáng tin. Nhãn 'customer' sai
-- theo chiều an toàn.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- NGÀY ĐO TRỐNG THÌ LẤY `updated_at`
--
-- `customer_prescriptions.measured_at` là NOT NULL, còn bên bảng tóm tắt thì
-- NULL được — bản ghi sinh từ luồng mua hàng cũ không có ngày đo nào.
--
-- Lấy DATE(updated_at) là mốc trung thực nhất còn lại: nó nói "con số này có
-- trong hệ thống từ ngày đó". Không bịa một ngày trong quá khứ, và không bỏ
-- những dòng ấy lại — bỏ lại thì khách mất hồ sơ đúng vào ngày nâng cấp.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- Mệnh đề NOT EXISTS tự loại những khách đã được chép ở lượt trước, nên lần
-- chạy thứ hai chèn 0 dòng.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 0. ĐẾM TRƯỚC — chạy riêng, GHI KẾT QUẢ RA GIẤY
--
--   SELECT COUNT(DISTINCT user_id) AS khach_co_so_do_truoc
--     FROM (SELECT user_id FROM prescriptions
--           UNION
--           SELECT user_id FROM customer_prescriptions) t;
--
--   SELECT COUNT(*) AS se_chep FROM prescriptions p
--    WHERE p.updated_at > COALESCE(
--            (SELECT MAX(c.created_at) FROM customer_prescriptions c
--              WHERE c.user_id = p.user_id), '1000-01-01 00:00:00');
--
-- Con số thứ nhất là thứ câu hậu kiểm ở cuối file so lại. Con số thứ hai là số
-- dòng bước 1 sẽ chèn.
-- ----------------------------------------------------------------------------


-- ----------------------------------------------------------------------------
-- 1. CHÉP BẢNG TÓM TẮT SANG SỔ
--
-- `id` để CSDL tự sinh (cột có DEFAULT (UUID())).
--
-- `ban_goc_id` = chính id vừa sinh thì không làm được trong một câu INSERT
-- ... SELECT, nên để NULL và bước 2 điền sau. Bản ghi có ban_goc_id NULL vẫn
-- đọc được bình thường — PrescriptionRecordModel lùi về `id` khi cột đó trống
-- — nhưng điền cho đủ thì phần lịch sử phiên bản mới nhóm đúng.
--
-- `created_by` để NULL: không biết ai nhập bản ghi cũ, và gán bừa một tài
-- khoản là bịa ra một dòng trong vết kiểm toán.
-- ----------------------------------------------------------------------------
-- BỌC QUA PREPARE/EXECUTE vì bảng nguồn CÓ NGÀY BIẾN MẤT.
--
-- File 2026-09-06-dot-3-go-bang-tom-tat.sql gỡ `prescriptions` sau một tuần,
-- và cùng lúc đó bảng ấy cũng rời khỏi database/schema.sql. Từ đó trở đi, mọi
-- lần CÀI MỚI đều dựng một CSDL không có bảng nguồn — mà migrate.sh vẫn chạy
-- lần lượt hết mọi file, kể cả file này.
--
-- Không bọc thì lượt cài mới nào cũng dừng ở đây với lỗi 1146 "Table
-- 'prescriptions' doesn't exist", trong khi việc đúng ở tình huống đó là bỏ
-- qua: CSDL mới thì không có gì để hợp nhất.
SET @co := (SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prescriptions');
SET @sql := IF(@co = 1,
    'INSERT INTO `customer_prescriptions`
        (`user_id`, `source`, `od_sph`, `od_cyl`, `od_axis`, `od_va`,
         `os_sph`, `os_cyl`, `os_axis`, `os_va`,
         `pd`, `measured_at`, `store_id`, `note`, `phien_ban`, `created_at`)
     SELECT
        p.`user_id`,
        ''customer'',
        p.`od_sph`, p.`od_cyl`, p.`od_axis`, p.`od_va`,
        p.`os_sph`, p.`os_cyl`, p.`os_axis`, p.`os_va`,
        p.`pd`,
        COALESCE(p.`measured_at`, DATE(p.`updated_at`), CURDATE()),
        p.`store_id`,
        p.`recommendation`,
        1,
        p.`updated_at`
       FROM `prescriptions` p
      WHERE p.`updated_at` > COALESCE(
              (SELECT MAX(c.`created_at`) FROM `customer_prescriptions` c
                WHERE c.`user_id` = p.`user_id`),
              ''1000-01-01 00:00:00''
            )',
    'SELECT ''bang prescriptions da go, khong co gi de hop nhat'' AS ghi_chu');
PREPARE c FROM @sql; EXECUTE c; DEALLOCATE PREPARE c;


-- ----------------------------------------------------------------------------
-- 2. ĐIỀN `ban_goc_id` CHO NHỮNG DÒNG VỪA CHÈN
--
-- Mỗi bản ghi tự là gốc của nhóm phiên bản của chính nó. Câu này cũng dọn luôn
-- mọi dòng cũ còn thiếu cột đó, nếu có.
-- ----------------------------------------------------------------------------
UPDATE `customer_prescriptions`
   SET `ban_goc_id` = `id`
 WHERE `ban_goc_id` IS NULL;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY — cả bốn phải đúng như ghi bên phải
--
--   SELECT COUNT(DISTINCT user_id) FROM customer_prescriptions;
--       -- PHẢI BẰNG 'khach_co_so_do_truoc' đã ghi ở mục 0.
--       -- Nhỏ hơn nghĩa là có khách vừa mất hồ sơ đo mắt.
--
--   SELECT COUNT(*) FROM prescriptions p
--    WHERE p.updated_at > COALESCE(
--            (SELECT MAX(c.created_at) FROM customer_prescriptions c
--              WHERE c.user_id = p.user_id), '1000-01-01 00:00:00');
--       -- = 0. Không còn dòng nào ở bảng tóm tắt mới hơn sổ.
--
--   SELECT COUNT(*) FROM customer_prescriptions WHERE ban_goc_id IS NULL;
--       -- = 0
--
--   SELECT COUNT(*) FROM customer_prescriptions WHERE measured_at IS NULL;
--       -- = 0 (cột NOT NULL, nhưng kiểm cho chắc bước 1 không lọt NULL nào)
--
-- Rồi mở bằng trình duyệt:
--
--   /tai-khoan?muc=do-mat        khách thấy đúng số đo cũ của mình, có ngày đo
--   /quan-tri/khach-hang → một khách → tab Đơn thuốc
--                                bản vừa chép hiện với nhãn "Khách tự khai"
--
-- ----------------------------------------------------------------------------
-- QUAY LUI
--
-- File này KHÔNG cần script quay lui: nó chỉ CHÈN, không xoá và không sửa dữ
-- liệu cũ. Bảng `prescriptions` còn nguyên nên deploy ngược mã cũ là chạy lại
-- được ngay.
--
-- Muốn bỏ hẳn phần vừa chèn (hiếm — chỉ khi phát hiện nhãn nguồn sai hàng
-- loạt) thì xoá theo đúng dấu vết của bước 1:
--
--   DELETE c FROM customer_prescriptions c
--     JOIN prescriptions p ON p.user_id = c.user_id
--    WHERE c.source = 'customer'
--      AND c.created_by IS NULL
--      AND c.created_at = p.updated_at;
--
-- ĐỌC KỸ trước khi chạy câu đó: nó xoá dữ liệu y tế, và điều kiện
-- `created_by IS NULL` là thứ duy nhất phân biệt bản do migration chèn với bản
-- khách tự khai qua trang tài khoản.
-- ----------------------------------------------------------------------------
