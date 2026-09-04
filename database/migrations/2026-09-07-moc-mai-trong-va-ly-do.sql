-- ============================================================================
-- 2026-09-07 — Mốc "Bắt đầu mài" và lý do cho mỗi lần đổi trạng thái
--
-- Căn cứ: SRS v1.3.1 mục 3.2.6.4 và 3.2.7, các quyết định Q2.2, Q3.1, Q3.2,
-- X06, X07, Q52.1, Q56.2.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO MỐC MÀI LÀ MỘT CỘT RIÊNG, KHÔNG PHẢI MỘT TRẠNG THÁI
--
-- SRS gọi "Đang cắt tròng" là một trạng thái trong vòng đời đơn, và đúng là
-- nó hiện ra như vậy trên màn hình. Nhưng thứ mà mọi quy tắc về TIỀN hỏi tới
-- không phải "đơn đang ở đâu" mà là "đã từng bấm Bắt đầu mài chưa":
--
--   Q52.1  huỷ TRƯỚC khi bấm, khách trả 100%  -> hoàn 100%
--   Q56.2  mốc chặn việc tự huỷ đơn quá hạn là thời điểm bấm
--   FR-25  huỷ SAU khi bấm -> giữ cọc
--
-- Một đơn đã mài xong rồi đi tiếp sang "Chờ giao" thì trạng thái hiện tại
-- không còn là "Đang cắt tròng" nữa — nhưng tròng thì đã cắt, tiền vật tư đã
-- mất, và luật hoàn cọc vẫn phải áp. Đọc mốc từ trạng thái hiện tại là trả lời
-- sai câu hỏi; đọc từ `order_status_history` là đúng nhưng biến một luật tiền
-- thành một câu quét bảng lịch sử ở mọi nơi cần hỏi.
--
-- Nên mốc được ghi MỘT LẦN vào `orders.mai_bat_dau_luc` và ở đó vĩnh viễn.
-- Đây cùng một lối nghĩ đã tách `payment_status` khỏi `status`: trạng thái nói
-- đơn ĐANG ở đâu, các cột mốc nói chuyện gì ĐÃ xảy ra.
--
-- Cột này CHỈ bị xoá bởi một đường duy nhất: Quản lý cơ sở đảo ngược thao tác
-- kèm lý do (Q2.2). Không có đường nào khác gỡ nó.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- LÝ DO ĐI VÀO order_status_history, KHÔNG VÀO BẢNG VẾT
--
-- Q3.1 buộc ghi lý do khi mở lại đơn đã huỷ; Q2.2 buộc ghi lý do khi đảo mốc
-- mài. Lý do đó phải đọc được NGAY TRÊN ĐƠN — người mở đơn ra sáu tháng sau
-- cần biết vì sao có một lần huỷ rồi mở lại, và họ đang nhìn vào ngăn kéo đơn
-- hàng chứ không nhìn vào màn Nhật ký thao tác.
--
-- `customer_audit_logs` vẫn nhận vết như cũ (SNFR-11). Hai bảng, hai người
-- đọc: một bên là sổ nghiệp vụ của riêng đơn, một bên là sổ kiểm toán lọc
-- theo người thực hiện. Chép lý do vào cả hai là cố ý.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- MySQL 8 không có `ADD COLUMN IF NOT EXISTS`, nên mỗi cột và cả khoá ngoại
-- đều đi qua một vòng PREPARE/EXECUTE hỏi information_schema trước.
--
-- KHÔNG có bước nào xoá hay đổi kiểu cột đang chứa dữ liệu.
-- ============================================================================

-- ── orders.mai_bat_dau_luc ──────────────────────────────────────────────────
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'orders'
       AND COLUMN_NAME  = 'mai_bat_dau_luc'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `orders`
        ADD COLUMN `mai_bat_dau_luc` DATETIME NULL DEFAULT NULL AFTER `paid_at`',
    'SELECT ''orders.mai_bat_dau_luc da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ── orders.mai_bat_dau_boi ──────────────────────────────────────────────────
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'orders'
       AND COLUMN_NAME  = 'mai_bat_dau_boi'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `orders`
        ADD COLUMN `mai_bat_dau_boi` CHAR(36) NULL DEFAULT NULL AFTER `mai_bat_dau_luc`',
    'SELECT ''orders.mai_bat_dau_boi da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

/* CHỈ MỤC ĐẶT TÊN, TẠO TRƯỚC KHOÁ NGOẠI — và thứ tự ấy là cả điểm.

   InnoDB đòi một chỉ mục trên cột khoá ngoại; không có sẵn thì nó TỰ tạo một
   cái và đặt tên theo ràng buộc (`fk_orders_mai_boi`). Trong khi schema.sql
   khai một chỉ mục tên `idx_orders_mai_boi` như mọi bảng khác.

   Kết quả là cùng một cột, cùng một chỉ mục, HAI CÁI TÊN: máy cài mới một
   kiểu, máy nâng cấp một kiểu. Không hỏng gì hôm nay, nhưng nó phá đúng thứ
   dùng để kiểm tra rằng hai đường cho ra một lược đồ — và ngày nào đó có câu
   DROP INDEX gọi tên thì nó chỉ chạy được trên một nửa số máy. */
SET @co_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'orders'
       AND INDEX_NAME   = 'idx_orders_mai_boi'
);
SET @sql := IF(@co_idx = 0,
    'ALTER TABLE `orders` ADD INDEX `idx_orders_mai_boi` (`mai_bat_dau_boi`)',
    'SELECT ''idx_orders_mai_boi da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

/* Khoá ngoại SET NULL: người bấm nút nghỉ việc và bị xoá tài khoản thì MỐC
   vẫn phải còn — nó là căn cứ tính tiền hoàn, không phải một dòng nhật ký. */
SET @co_fk := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA     = DATABASE()
       AND TABLE_NAME       = 'orders'
       AND CONSTRAINT_NAME  = 'fk_orders_mai_boi'
);
SET @sql := IF(@co_fk = 0,
    'ALTER TABLE `orders`
        ADD CONSTRAINT `fk_orders_mai_boi` FOREIGN KEY (`mai_bat_dau_boi`)
            REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT ''fk_orders_mai_boi da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ── order_status_history.ly_do ──────────────────────────────────────────────
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'order_status_history'
       AND COLUMN_NAME  = 'ly_do'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `order_status_history`
        ADD COLUMN `ly_do` VARCHAR(255) NULL DEFAULT NULL AFTER `changed_by`',
    'SELECT ''order_status_history.ly_do da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SELECT 'Xong: moc mai trong + ly do doi trang thai' AS ket_qua;
