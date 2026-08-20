-- ============================================================================
-- NÂNG CẤP 2026-08-20
-- Ghi chú riêng cho từng mắt ở bước "Nhập số đo khúc xạ"
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
-- Nên không có cột `od_note`/`os_note` nào cả. Đổi lại, chuỗi dài thêm tối đa
-- 2 × 63 ký tự (60 ký tự ghi chú + dấu cách + hai dấu ngoặc, xem
-- LensModel::NOTE_MAX).
--
-- KHÔNG GẤP — chạy lúc nào cũng được. Đo thử trường hợp dài nhất có thể xảy
-- ra (tên tật dài nhất + hai mắt −12.00 + hai ghi chú kín 60 ký tự) ra đúng
-- 158 ký tự, tức là VẪN LỌT trần cũ 160. Không có đơn hàng nào đang bị cắt cụt.
--
-- Nới vì 2 ký tự dư là quá sát: đổi NOTE_MAX, thêm một loại tật có tên dài
-- hơn, hay ghép thêm PD vào chuỗi — mỗi việc đó đều đẩy qua trần, và khi qua
-- trần thì MySQL ở chế độ strict KHÔNG cắt âm thầm mà ném lỗi ngay lúc khách
-- bấm đặt hàng. 255 cho tròn và còn chỗ thở.
-- ============================================================================

ALTER TABLE `order_items`
    MODIFY COLUMN `prescription` VARCHAR(255) NULL;


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM order_items LIKE 'prescription';   -- varchar(255) · YES
-- ----------------------------------------------------------------------------
