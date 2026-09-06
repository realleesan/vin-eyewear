-- ============================================================================
-- 2026-09-06 — ĐỢT 2: BA VAI TRÒ
--
-- Căn cứ: SRS v2.1.0, mục 5.2 và ma trận phân quyền 5.2.2.
--
-- Khách hàng · Nhân viên · Quản trị viên. Khu quản trị chỉ còn HAI bậc.
-- Hai vai trò bị gỡ:
--
--   technician  Kỹ thuật viên khúc xạ → thành Nhân viên.
--               Việc của vai trò này (nhập và đính chính hồ sơ đo mắt) nay
--               mọi Nhân viên làm được, nên nó không còn phân biệt điều gì.
--
--   manager     Quản lý cơ sở → thành QUẢN TRỊ VIÊN.
--               Phần lớn quyền của họ chuyển LÊN Quản trị viên (ghi danh mục,
--               sửa tồn kho, khoá tài khoản khách, tạo liên kết đặt lại mật
--               khẩu). Hạ họ xuống Nhân viên là lấy mất những quyền đó ngay
--               giữa ca trực.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- TRÊN CƠ SỞ DỮ LIỆU PRODUCTION NGÀY 06/09/2026, FILE NÀY KHÔNG ĐỔI DÒNG NÀO
--
-- Đếm thực tế lúc lập file: customer 15 · admin 1 · KHÔNG có dòng nào mang
-- 'staff', 'technician' hay 'manager'. Hai câu UPDATE ở mục 1 và 2 sẽ khớp 0
-- dòng, và mục 3 chỉ là thu ENUM trên một bảng không chứa giá trị nào sắp bị
-- bỏ.
--
-- Vẫn giữ đủ hai câu UPDATE: file migration phải chạy đúng trên MỌI bản sao
-- của cơ sở dữ liệu này, kể cả bản trên máy phát triển hay bản khôi phục từ
-- một mốc cũ hơn — và ở đó có thể có những dòng mà production không có.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- THỨ TỰ BẮT BUỘC: CHUYỂN DỮ LIỆU TRƯỚC, THU ENUM SAU
--
-- Làm ngược lại thì MySQL gặp một dòng mang giá trị không còn trong ENUM và
-- xử lý nó theo chế độ SQL đang bật: strict mode thì ALTER hỏng giữa chừng,
-- không strict thì nó ÂM THẦM đổi giá trị đó thành chuỗi rỗng. Chuỗi rỗng
-- không khớp vai trò nào, nên người ấy mất quyền vào khu quản trị mà không có
-- một dòng lỗi nào nói vì sao.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- MÃ NGUỒN PHẢI ĐI TRƯỚC, KHÔNG ĐI SAU
--
-- Mã đợt 2 chỉ còn biết 'staff' và 'admin'. Nó GHI được trên ENUM cũ vì nó
-- không bao giờ ghi hai giá trị kia, nên deploy mã trước rồi chạy file này là
-- chiều đúng.
--
-- Chiều ngược lại hỏng hẳn: thu ENUM xong mà mã cũ còn cho chọn "Kỹ thuật
-- viên" trong ô vai trò thì lần lưu đầu tiên ném lỗi 1265 ngay giữa form.
--
-- ⚠ NHƯNG KHOẢNG GIỮA HAI VIỆC KHÔNG PHẢI LÀ VÔ HẠI, nếu CSDL có tài khoản
-- mang 'technician' hoặc 'manager'. UserModel::STAFF_ROLES đã thu còn
-- ['staff','admin'], nên isStaff() trả false cho những người ấy: họ đăng nhập
-- đúng mật khẩu vẫn bị trả về form đăng nhập, KHÔNG có thông báo nào nói vì
-- sao, và họ cũng không hiện trên /quan-tri/nhan-vien để ai sửa vai trò hộ.
--
-- Trên production ngày 06/09 không ai bị (0 dòng technician/manager). Trên
-- máy phát triển hay bản khôi phục từ mốc cũ thì CHẠY FILE NÀY NGAY SAU KHI
-- deploy, đừng để qua đêm.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- Hai câu UPDATE lần hai khớp 0 dòng. MODIFY COLUMN đặt lại đúng định nghĩa đã
-- có nên không đổi gì. Không câu nào trong file này xoá dữ liệu.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 0. ĐẾM TRƯỚC — chạy riêng, đọc kết quả rồi mới chạy tiếp
--
--   SELECT role, COUNT(*) FROM user_roles GROUP BY role ORDER BY role;
--
--   SELECT COUNT(DISTINCT user_id) AS vao_duoc_quan_tri
--     FROM user_roles
--    WHERE role IN ('staff', 'technician', 'manager', 'admin');
--
-- GHI LẠI CẢ HAI KẾT QUẢ RA GIẤY. Câu thứ hai là số người vào được khu quản
-- trị TRƯỚC khi chạy; câu hậu kiểm ở cuối file so lại với chính con số này, và
-- nếu không ghi lại thì lúc đó không có gì để so.
-- ----------------------------------------------------------------------------


-- ----------------------------------------------------------------------------
-- 1. technician → staff
--
-- IGNORE để bỏ qua đúng một tình huống: người đã có SẴN dòng 'staff' bên cạnh
-- dòng 'technician'. Khoá duy nhất (user_id, role) chặn dòng thứ hai, và
-- không có IGNORE thì cả câu lệnh dừng vì một người như thế.
--
-- Dòng bị bỏ qua KHÔNG mất quyền: họ vốn đã là 'staff' rồi. Mục 4 dọn nốt
-- dòng 'technician' thừa của họ.
-- ----------------------------------------------------------------------------
UPDATE IGNORE `user_roles` SET `role` = 'staff' WHERE `role` = 'technician';


-- ----------------------------------------------------------------------------
-- 2. manager → admin
--
-- ĐÂY LÀ CÂU DUY NHẤT TRONG FILE MỞ RỘNG QUYỀN CỦA MỘT CON NGƯỜI.
--
-- Quản trị viên làm được nhiều hơn Quản lý cơ sở cũ: tạo và xoá tài khoản nội
-- bộ, đặt lại mật khẩu người khác, đọc toàn bộ nhật ký thao tác (kể cả vết của
-- chính mình), sửa tồn kho, xuất danh sách khách hàng.
--
-- Trên production hiện tại câu này khớp 0 dòng nên không có gì để cân nhắc.
-- Nếu chạy file này trên một bản sao CÓ tài khoản 'manager' mà những người đó
-- thực ra chỉ là nhân viên bán hàng được gán nhầm vai trò, thì đổi câu dưới
-- thành 'staff' TRƯỚC KHI chạy:
--
--   UPDATE IGNORE `user_roles` SET `role` = 'staff' WHERE `role` = 'manager';
--
-- Xem ai sẽ bị ảnh hưởng:
--
--   SELECT p.full_name, p.phone, u.email
--     FROM user_roles r
--     JOIN users    u ON u.id = r.user_id
--     LEFT JOIN profiles p ON p.id = r.user_id
--    WHERE r.role = 'manager';
-- ----------------------------------------------------------------------------
UPDATE IGNORE `user_roles` SET `role` = 'admin' WHERE `role` = 'manager';


-- ----------------------------------------------------------------------------
-- 3. Dọn dòng thừa mà UPDATE IGNORE đã bỏ qua
--
-- Sau hai câu trên, dòng nào CÒN mang 'technician' hoặc 'manager' đều là dòng
-- mà khoá duy nhất đã chặn — tức người đó đã có sẵn vai trò đích. Xoá đi
-- không ai mất quyền gì, và không xoá thì mục 4 không chạy được.
-- ----------------------------------------------------------------------------
DELETE FROM `user_roles` WHERE `role` IN ('technician', 'manager');


-- ----------------------------------------------------------------------------
-- 4. Thu ENUM xuống ba giá trị
--
-- Chạy được chỉ khi mục 1–3 đã dọn sạch hai giá trị kia. Nếu câu này báo lỗi
-- hoặc cảnh báo "Data truncated for column 'role'" thì DỪNG LẠI: nghĩa là còn
-- dòng chưa chuyển, và mục 3 đã bỏ sót.
-- ----------------------------------------------------------------------------
ALTER TABLE `user_roles`
    MODIFY COLUMN `role` ENUM('customer','staff','admin') NOT NULL;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY — cả bốn phải đúng như ghi bên phải
--
--   SELECT role, COUNT(*) FROM user_roles GROUP BY role ORDER BY role;
--       -- chỉ còn 'customer', 'staff', 'admin'; TỔNG số dòng không đổi
--       -- so với lúc đếm ở mục 0 (trừ phần mục 3 dọn dòng trùng)
--
--   SHOW COLUMNS FROM user_roles LIKE 'role';
--       -- Type = enum('customer','staff','admin')
--
--   SELECT COUNT(*) FROM user_roles WHERE role IN ('technician','manager');
--       -- = 0
--
--   SELECT COUNT(DISTINCT user_id) FROM user_roles
--    WHERE role IN ('staff','admin');
--       -- PHẢI BẰNG con số 'vao_duoc_quan_tri' đã ghi ở mục 0.
--       -- Nhỏ hơn nghĩa là có người vừa mất đường vào khu quản trị.
--
-- ----------------------------------------------------------------------------
-- QUAY LUI
--
-- Xem 2026-09-06-dot-2-ba-vai-tro-QUAY-LUI.sql. File đó nới ENUM trở lại năm
-- giá trị nhưng KHÔNG biết ai vốn là 'technician' hay 'manager' — thông tin đó
-- mất ngay ở câu UPDATE. Muốn khôi phục đúng người thì phải nạp bản sao lưu.
-- ----------------------------------------------------------------------------
