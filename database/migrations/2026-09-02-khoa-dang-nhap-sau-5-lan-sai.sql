-- ============================================================================
-- 2026-09-02 — Khoá đăng nhập tạm thời sau 5 lần sai (SNFR-06)
--
-- Đối chiếu SRS v1.2 phát hiện: hệ thống không đếm lần đăng nhập hỏng ở bất cứ
-- đâu. UserModel::attempt() chỉ so mật khẩu rồi trả lời đúng/sai, nên cả ô đăng
-- nhập của khách lẫn cổng /quan-tri đều là mục tiêu dò mật khẩu không có trần.
--
-- SNFR-06 chốt: "tài khoản tạm thời bị khóa 15 phút nếu nhập sai mật khẩu quá 5
-- lần liên tiếp".
--
-- VÌ SAO MỘT BẢNG RIÊNG, KHÔNG PHẢI HAI CỘT THÊM VÀO `users`
--
-- Đếm trên `users` chỉ đếm được cho tài khoản có thật, và câu trả lời khác nhau
-- giữa "có tài khoản nên bị khoá" với "không có tài khoản nên báo sai thông
-- tin" biến chính cái khoá thành công cụ dò danh sách khách hàng. Đếm theo
-- chuỗi định danh (đã băm) thì mọi định danh đều đếm được, có tài khoản hay
-- không, nên phản hồi của hệ thống không rò rỉ gì.
--
-- Lý do đầy đủ ghi ở đầu app/models/LoginAttemptModel.php và trong schema.sql.
--
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN: chỉ có một câu CREATE TABLE IF NOT EXISTS, không
-- đụng tới dữ liệu sẵn có, không có bước nào phụ thuộc trạng thái trước đó.
--
-- KHÔNG CẦN ROLLBACK: bảng chỉ chứa bộ đếm tạm. Muốn tắt tính năng thì DROP
-- bảng này — LoginAttemptModel::available() thấy bảng biến mất sẽ tự cho qua
-- mọi lượt đăng nhập, không ném lỗi. Đó là chủ ý: một cái khoá gãy không được
-- phép biến thành cửa đóng với cả cửa hàng.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- SNFR-10 — SIẾT TOKEN "GHI NHỚ ĐĂNG NHẬP" ĐÃ PHÁT VỀ 7 NGÀY
--
-- RememberModel::LIFETIME đổi từ 30 ngày xuống 7 ngày trong cùng lần sửa này,
-- nhưng hằng số đó chỉ áp cho token CẤP MỚI: issue() ghi `expires_at` vào CSDL
-- lúc cấp, và consume() đọc đúng mốc đã ghi. Không có câu dưới đây thì mọi
-- token đã phát trước lần deploy này vẫn sống nốt 30 ngày của nó — tức con số
-- 7 ngày mà SNFR-10 (Quyết định C7) chốt phải đợi gần một tháng mới có hiệu
-- lực thật.
--
-- LEAST() chứ không gán thẳng: token nào vốn đã hết hạn sớm hơn 7 ngày nữa thì
-- giữ nguyên mốc của nó, đừng KÉO DÀI ra. Đây cũng là thứ làm câu này chạy lại
-- được nhiều lần mà không đổi gì thêm.
-- ----------------------------------------------------------------------------
UPDATE `remember_tokens`
   SET `expires_at` = LEAST(`expires_at`, DATE_ADD(NOW(), INTERVAL 7 DAY));

CREATE TABLE IF NOT EXISTS `login_attempts` (
    -- sha256 của strtolower(trim(<định danh>)) — KHÔNG dùng mb_*, máy chủ không có mbstring — xem LoginAttemptModel::khoa()
    `login_key`    CHAR(64)          NOT NULL,
    -- Số lần sai LIÊN TIẾP. Về 0 ngay khi đặt khoá, để hết 15 phút là người ta
    -- có lại đủ 5 lần thử chứ không bị khoá lại ở lần sai đầu tiên.
    `fails`        TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    -- NULL = đang được phép thử.
    `locked_until` DATETIME          NULL,
    `updated_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`login_key`),
    KEY `idx_login_attempts_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
