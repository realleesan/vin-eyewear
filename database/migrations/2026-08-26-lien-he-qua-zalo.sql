-- ============================================================================
-- NÂNG CẤP 2026-08-26 (thứ hai trong ngày)
-- Yêu cầu liên hệ: bỏ trạng thái, đẩy thẳng sang Zalo CSKH
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO BỎ CỘT `status`
--
-- Nó là một hàng chờ ba nấc (Mới -> Đang xử lý -> Đã xử lý) mà không ai đứng
-- canh: nhân viên cửa hàng kính ngồi ở quầy và trả lời khách bằng Zalo, không
-- ngồi trước bảng quản trị chờ có dòng mới. Một hàng chờ không có người trực
-- thì nó không phải hàng chờ, nó là một ô chọn người ta quên bấm — và tệ hơn
-- là nó TRÔNG như đã có người lo.
--
-- Nay yêu cầu chạy thẳng sang Zalo của CSKH ngay lúc khách bấm gửi, đúng đường
-- mà lịch hẹn và đơn hàng đã đi (xem core/Zalo.php). Việc theo dõi nằm trong
-- chính cuộc trò chuyện Zalo, nơi có người thật đang nhìn.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÀ VÌ SAO VẪN CẦN `zalo_sent_at`
--
-- Bỏ trạng thái mà không thay gì thì mất luôn câu trả lời cho một câu hỏi mới
-- nảy ra: yêu cầu này ĐÃ TỚI TAY CSKH CHƯA?
--
-- Câu đó quan trọng vì ZNS hỏng IM LẶNG. Token hết hạn, mẫu tin bị Zalo gỡ,
-- mạng ra ngoài bị chặn — cả ba đều chỉ để lại một dòng trong error log mà
-- không ai đọc, trong khi khách ngồi chờ một cuộc gọi không bao giờ tới. Cột
-- này là chỗ duy nhất nhìn ra điều đó, và nó nuôi cả huy hiệu "Liên hệ" ở
-- thanh bên lẫn ô "Liên hệ chưa đẩy" ở trang Tổng quan.
--
-- Nó là một SỰ KIỆN (đã xảy ra lúc nào), không phải một TRẠNG THÁI (ai đó tự
-- đặt). Không ai bấm tay được vào nó ngoài việc bấm nút gửi lại.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- ⚠️ FILE NÀY XOÁ MỘT CỘT CÓ DỮ LIỆU — đọc kỹ khối dưới trước khi chạy.
--
-- `contact_requests`.`status` bị DROP, và nội dung của nó KHÔNG lấy lại được.
-- Sao lưu database trước khi chạy trên máy chủ thật.
--
-- Bù lại, bước 1 chép ý nghĩa của cột đó sang `zalo_sent_at` trước khi xoá:
-- yêu cầu đã có người xử lý ('handling'/'done') được đánh dấu là xong, còn
-- yêu cầu chưa ai đụng ('new') ở nguyên trong hàng chờ để đẩy sang Zalo. Xem
-- chú thích tại chỗ — thứ tự các bước trong file này KHÔNG đảo được.
-- ============================================================================

-- --------------------------------------------------------------------------
-- 1. THÊM `zalo_sent_at`, VÀ ĐÁNH DẤU MỌI YÊU CẦU CŨ LÀ ĐÃ XONG
-- --------------------------------------------------------------------------
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_requests'
       AND COLUMN_NAME  = 'zalo_sent_at'
);

SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `contact_requests`
        ADD COLUMN `zalo_sent_at` DATETIME NULL DEFAULT NULL AFTER `message`',
    'SELECT ''contact_requests.zalo_sent_at da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

/*
 * CHÉP Ý NGHĨA CỦA CỘT `status` SANG TRƯỚC KHI XOÁ NÓ — đây là lúc duy nhất
 * làm được việc đó, và câu UPDATE này là toàn bộ lý do bước 1 đứng trước bước 3.
 *
 *   status 'handling' | 'done'  -> ĐÃ CÓ NGƯỜI LO. Đánh dấu đã xong, để chúng
 *                                  không nhảy vào hàng chờ mới.
 *   status 'new'                -> ĐỂ NGUYÊN NULL. Chưa ai đụng tới, nên nó
 *                                  vẫn là việc phải làm, chỉ là nay làm bằng
 *                                  cách đẩy sang Zalo.
 *
 * ⚠️ ĐỪNG ĐỔI THÀNH "đánh dấu tất cả cho gọn". Giữa lúc mã mới lên máy chủ và
 * lúc file này được chạy có một KHOẢNG TRỐNG — deploy đi bằng FTP tự động, còn
 * migration thì phải mở phpMyAdmin bấm tay, nên khoảng đó dài hàng giờ là
 * chuyện thường. Trong khoảng ấy form liên hệ ĐÃ cố đẩy sang Zalo nhưng chưa có
 * cột nào để ghi nhận kết quả, và những yêu cầu đó ra đời với status mặc định
 * 'new'. Đánh dấu tất cả nghĩa là chôn luôn chúng: khách đã gửi, tin không tới
 * CSKH, và không còn dấu vết nào cho ai biết là có người đang chờ.
 *
 * Mốc lấy `created_at` chứ không phải NOW(): dòng này không khẳng định "đã gửi
 * Zalo lúc đó" — Zalo chưa hề nhận gì. Nó chỉ nói "yêu cầu này không thuộc về
 * hàng chờ mới". Lấy NOW() thì cả bảng mang cùng một mốc trùng đúng phút chạy
 * migration, đọc lại sáu tháng sau còn khó hiểu hơn.
 *
 * WHERE zalo_sent_at IS NULL: chạy lại file này không đụng vào dòng đã được
 * đẩy Zalo thật sau đó.
 */
UPDATE `contact_requests`
   SET `zalo_sent_at` = `created_at`
 WHERE `zalo_sent_at` IS NULL
   AND `status` <> 'new';

-- --------------------------------------------------------------------------
-- 2. CHỈ MỤC MỚI THEO `created_at`
--
-- Chỉ mục cũ là (`status`, `created_at`) — cột đầu sắp biến mất, và một chỉ
-- mục bắt đầu bằng cột không còn tồn tại thì không giúp gì cho câu lệnh nào.
-- Trang danh sách nay chỉ còn một cách sắp: mới nhất trước.
--
-- Tạo cái mới TRƯỚC khi xoá cái cũ, để không có khoảnh khắc nào bảng đứng
-- không có chỉ mục phục vụ ORDER BY.
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

-- Chỉ mục phục vụ "yêu cầu nào chưa đẩy sang Zalo" — câu hỏi chạy ở MỌI lượt
-- tải trang quản trị, vì nó nuôi huy hiệu trên thanh bên.
SET @co_khoa := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'contact_requests'
       AND INDEX_NAME   = 'idx_contact_requests_zalo'
);

SET @sql := IF(@co_khoa = 0,
    'ALTER TABLE `contact_requests` ADD KEY `idx_contact_requests_zalo` (`zalo_sent_at`)',
    'SELECT ''idx_contact_requests_zalo da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

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
-- 3. XOÁ CỘT `status`
--
-- Đặt CUỐI CÙNG, sau khi bước 1 đã chép xong ý nghĩa của nó. Đảo thứ tự là
-- xoá dữ liệu trước khi đọc nó.
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
