-- ============================================================================
-- NÂNG CẤP 2026-08-20
-- Số đo khúc xạ theo TỪNG MẮT: loại tật riêng, trụ/trục cho mắt loạn, ghi chú riêng
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHỈ NỚI MỘT CỘT, KHÔNG THÊM CỘT MỚI
--
-- Số đo khách nhập lúc mua được lưu thành MỘT chuỗi trong
-- `order_items.prescription` — lý do đã ghi ở LensModel::formatRx(): đó là thứ
-- nhân viên ĐỌC rồi nhập vào máy mài, không phải thứ hệ thống tính toán.
--
-- Ghi chú từng mắt đi theo đúng lối đó, ghép vào ngay sau con số của mắt nó
-- nói tới:
--
--     Cận thị · MP −2.00 (hay mỏi khi đọc lâu) · MT −2.25
--
-- Nên không có cột `od_note`/`os_note` nào cả — cũng không có `od_cyl`,
-- `od_axis`. Tất cả nằm trong chuỗi đó.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- PHẢI CHẠY TRƯỚC KHI DÙNG BẢN MỚI — 160 KHÔNG CÒN ĐỦ
--
-- Cùng lần sửa này, màn nhập số đo chuyển sang hỏi LOẠI TẬT THEO TỪNG MẮT, và
-- mắt loạn thị có thêm độ trụ với trục. Một mắt nay dài nhất là:
--
--     MP Loạn thị −12.00 / −6.00 × 180° (60 ký tự ghi chú)
--
-- Hai mắt cộng dấu phân cách ra 195 ký tự — VƯỢT trần cũ 160. Chưa chạy file
-- này mà khách đặt một đơn như vậy thì MySQL ở chế độ strict ném lỗi ngay lúc
-- bấm đặt hàng (không phải cắt âm thầm), tức là mất đơn.
--
-- 255 cho tròn và còn chỗ thở: 195 là trần tính được của bản hôm nay, còn thừa
-- 60 ký tự cho lần thêm trường tiếp theo.
-- ============================================================================

ALTER TABLE `order_items`
    MODIFY COLUMN `prescription` VARCHAR(255) NULL;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM order_items LIKE 'prescription';   -- varchar(255) · YES
-- ----------------------------------------------------------------------------
