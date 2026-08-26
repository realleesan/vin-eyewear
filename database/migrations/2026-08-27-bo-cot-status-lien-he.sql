-- ============================================================================
-- DỌN 2026-08-27
-- Bỏ hẳn cột `contact_requests`.`status` và chỉ mục của nó
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CỘT NÀY ĐÃ CHẾT TỪ 2026-08-26, FILE NÀY CHỈ CHÔN NÓ
--
-- Bản "liên hệ qua Zalo CSKH" (commit 4908ae2) bỏ ô chọn trạng thái ba nấc ở
-- /quan-tri/lien-he: yêu cầu nay chạy thẳng sang Zalo lúc khách bấm gửi, và
-- việc theo dõi nằm trong cuộc trò chuyện đó. Migration
-- 2026-08-26-lien-he-qua-zalo.sql đã DROP cột này một lần.
--
-- Nhưng ngày hôm đó CSDL đi trước mã một bước: cột bị xoá trong khi máy chủ vẫn
-- chạy bản cũ (deploy FTP lên thiếu file), nên MỌI trang khu quản trị trả 500 —
-- ContactModel::countNew() gọi từ AdminController::renderAdmin() hỏi một cột
-- không còn tồn tại. Bản vá khẩn lúc đó DỰNG LẠI `status` để mã cũ sống tiếp,
-- cố ý giữ luôn `zalo_sent_at` để cả hai phiên bản mã cùng chạy được.
--
-- Nay deploy đã lành và mã Zalo đã chắc chắn ở trên máy chủ, nên cột kia thành
-- thừa thật sự. File này dọn nốt.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ĐÃ SOÁT TRƯỚC KHI XOÁ, VÀ ĐÂY LÀ THỨ ĐÃ SOÁT
--
-- Không còn một câu SQL nào trong app/, core/, config/ nhắc tới
-- `contact_requests`.`status`. Ba chỗ còn chứa chữ "status" đều là CHÚ THÍCH
-- kể lại lịch sử:
--
--     app/views/admin/dashboard/index.php:84   nói ô "Liên hệ mới" từng đọc cột này
--     app/models/ContactModel.php:18, :146     nói vì sao bỏ hằng STATUSES
--
-- `database/schema.sql` cũng đã không còn cột này từ bản Zalo, nên máy cài mới
-- vốn đã sạch — file này chỉ dành cho CSDL đang chạy.
--
-- ⚠️ XOÁ CỘT LÀ MẤT DỮ LIỆU. Giá trị 'new'/'handling'/'done' còn lại không lấy
-- lại được. Chúng không còn ý nghĩa với mã hiện tại, nhưng vẫn nên sao lưu
-- database trước khi chạy trên máy chủ thật.
-- ============================================================================

-- --------------------------------------------------------------------------
-- 1. BỎ CHỈ MỤC TRƯỚC
--
-- `idx_contact_requests_status` là (`status`, `created_at`) — cột đầu sắp biến
-- mất. Bỏ chỉ mục trước rồi mới bỏ cột: đảo lại thì MySQL phải tự rút gọn chỉ
-- mục giữa chừng, và với InnoDB việc đó có phiên bản chấp nhận có phiên bản
-- từ chối bằng lỗi 1553 khó đọc. Dự án đã vướng đúng lỗi ấy một lần, xem
-- 2026-08-22-bo-gioi-han-khung-gio.sql.
--
-- Truy vấn còn lại trên bảng này sắp theo `created_at`, và
-- `idx_contact_requests_created` (do bản Zalo tạo) đã phục vụ nó.
-- --------------------------------------------------------------------------
SET @co_khoa := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_requests'
       AND INDEX_NAME   = 'idx_contact_requests_status'
);

SET @sql := IF(@co_khoa > 0,
    'ALTER TABLE `contact_requests` DROP KEY `idx_contact_requests_status`',
    'SELECT ''idx_contact_requests_status da bo, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- --------------------------------------------------------------------------
-- 2. BỎ CỘT
-- --------------------------------------------------------------------------
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_requests'
       AND COLUMN_NAME  = 'status'
);

SET @sql := IF(@co_cot > 0,
    'ALTER TABLE `contact_requests` DROP COLUMN `status`',
    'SELECT ''contact_requests.status da bo, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- --------------------------------------------------------------------------
-- 3. BẢO ĐẢM CHỈ MỤC THEO `created_at` CÓ THẬT
--
-- Máy nào chạy bản vá khẩn 26/08 rồi mới chạy file này thì đã có sẵn. Nhưng
-- một máy đi đường khác (chưa từng chạy bản Zalo, cột `status` có từ schema
-- gốc) thì sau bước 2 sẽ không còn chỉ mục nào phục vụ ORDER BY created_at.
-- Kiểm rồi tạo, thay vì giả định thứ tự các file đã chạy.
-- --------------------------------------------------------------------------
SET @co_khoa := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_requests'
       AND INDEX_NAME   = 'idx_contact_requests_created'
);

SET @sql := IF(@co_khoa = 0,
    'ALTER TABLE `contact_requests` ADD KEY `idx_contact_requests_created` (`created_at`)',
    'SELECT ''idx_contact_requests_created da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;
