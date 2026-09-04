-- ============================================================================
-- 2026-09-08 — Sổ địa chỉ đủ trường (Q75.1) và mốc xác thực số điện thoại (Q72)
--
-- Căn cứ: SRS v1.3.1 mục 3.2.1 và 3.2.9, quyết định Q72 và Q75.1.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- Q75.1 — HAI TRƯỜNG CÒN THIẾU CỦA SỔ ĐỊA CHỈ
--
-- Q75.1 liệt kê sổ địa chỉ gồm: Họ tên, Số điện thoại, Tỉnh/TP, Quận/Huyện,
-- Phường/Xã, Địa chỉ chi tiết, GHI CHÚ GIAO HÀNG và NHÃN ĐỊA CHỈ. Bảng
-- `addresses` đang có sáu thứ đầu; file này thêm hai thứ cuối.
--
--   ghi_chu  hướng dẫn cho người giao — "gọi trước 15 phút", "cổng sau",
--            "bảo vệ nhận giúp". Đây là thứ hôm nay khách nhét vào ô Địa chỉ
--            chi tiết vì không có chỗ nào khác, làm hỏng chính cái dòng sẽ
--            được in lên phiếu gửi hàng.
--   nhan     'nha' | 'cong_ty'. Không phải trang trí: địa chỉ công ty chỉ
--            nhận hàng trong giờ hành chính, và người sắp lịch giao cần biết
--            điều đó trước khi gọi xe.
--
-- NHÃN LÀ VARCHAR, KHÔNG PHẢI ENUM. Q75.1 chốt hai giá trị cho giai đoạn 1,
-- nhưng "Nhà riêng / Công ty" là loại danh sách hay được xin thêm mục ("Nhà bố
-- mẹ", "Kho"), và mỗi lần thêm vào ENUM là một ALTER TABLE khoá bảng trên
-- hosting miễn phí. Cùng lý lẽ đã dùng cho `orders.payment_status`.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- Q72 — MỘT CỘT MỐC, CHƯA PHẢI MỘT TÍNH NĂNG
--
-- Q72 định nghĩa "Hồ sơ đã hoàn thiện" = HỌ TÊN + SỐ ĐIỆN THOẠI ĐÃ XÁC THỰC.
-- Email, ngày sinh và địa chỉ mặc định KHÔNG nằm trong điều kiện này.
--
-- Vế "đã xác thực" hôm nay chưa có chỗ nào ghi được, vì luồng Zalo OTP (mục
-- 3.2.1, phụ thuộc Zalo Cloud API) chưa nối. Cột này là chỗ luồng ấy sẽ ghi
-- vào khi có, và là chỗ UserModel::hoSoDayDu() đọc ngay từ bây giờ.
--
-- ⚠ CHỪNG NÀO CHƯA CÓ OTP, KHÔNG BẢN GHI NÀO CÓ MỐC NÀY. Nếu để luật Q72 chạy
-- nguyên vẹn lúc đó thì MỌI khách đều "chưa hoàn thiện" và bị giữ ở trang Hồ
-- sơ vĩnh viễn — một quy tắc đúng chữ nhưng làm hỏng cả website. Lối thoát nằm
-- ở mã nguồn, không ở đây: xem UserModel::CO_KENH_XAC_THUC.
--
-- KHÔNG BACKFILL. Đánh dấu hàng loạt "đã xác thực" cho số điện thoại chưa ai
-- xác thực là biến một cột bằng chứng thành một cột vô nghĩa ngay từ ngày đầu.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CÒN THIẾU CÓ CHỦ Ý: QUẬN/HUYỆN
--
-- Q75.1 liệt kê ba cấp hành chính (Tỉnh/TP · Quận/Huyện · Phường/Xã), còn bảng
-- `addresses` chỉ có HAI (`province_*` và `ward_*`). Đây KHÔNG phải thiếu sót:
-- từ 2025 Việt Nam bỏ cấp huyện, và dữ liệu hành chính hiện hành chỉ còn hai
-- cấp. Thêm lại cột quận/huyện là tạo một ô không có nguồn dữ liệu để đổ vào.
--
-- Ghi lại ở đây để BA quyết: hoặc sửa Q75.1 cho khớp thực tế hành chính, hoặc
-- nói rõ vì sao vẫn cần ba cấp. Chưa gỡ được nên chưa động vào.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN — mỗi cột đi qua một vòng PREPARE/EXECUTE.
-- Không có bước nào xoá hay đổi kiểu cột đang chứa dữ liệu.
-- ============================================================================

-- ── addresses.ghi_chu ───────────────────────────────────────────────────────
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'addresses'
       AND COLUMN_NAME  = 'ghi_chu'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `addresses`
        ADD COLUMN `ghi_chu` VARCHAR(255) NULL DEFAULT NULL AFTER `ward_name`',
    'SELECT ''addresses.ghi_chu da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ── addresses.nhan ──────────────────────────────────────────────────────────
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'addresses'
       AND COLUMN_NAME  = 'nhan'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `addresses`
        ADD COLUMN `nhan` VARCHAR(16) NULL DEFAULT NULL AFTER `ghi_chu`',
    'SELECT ''addresses.nhan da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

-- ── profiles.phone_verified_at ──────────────────────────────────────────────
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'profiles'
       AND COLUMN_NAME  = 'phone_verified_at'
);
SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `profiles`
        ADD COLUMN `phone_verified_at` DATETIME NULL DEFAULT NULL AFTER `phone`',
    'SELECT ''profiles.phone_verified_at da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;

SELECT 'Xong: so dia chi (Q75.1) + moc xac thuc SDT (Q72)' AS ket_qua;
