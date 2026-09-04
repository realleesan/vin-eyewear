-- ============================================================================
-- 2026-09-05 — Phân quyền theo CƠ SỞ cho tài khoản nội bộ
--
-- Căn cứ: SRS v1.3.1 — SNFR-07b, Q12.1, Q12.2, Q12.3, X31.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO ĐÂY LÀ LỖ HỔNG, KHÔNG PHẢI MỘT TÍNH NĂNG CÒN THIẾU
--
-- Bảng `user_roles` nói một người LÀM ĐƯỢC GÌ nhưng không nói họ làm được điều
-- đó VỚI DỮ LIỆU CỦA AI. Ba bảng nghiệp vụ đều đã có cột cơ sở từ lâu —
-- `orders.store_id`, `appointments.store_id`, `customer_prescriptions.store_id`
-- — nhưng không truy vấn nào đọc chúng như một ràng buộc quyền. Màn Lịch hẹn
-- có ô lọc theo cơ sở, và đó chính là chỗ dễ hiểu nhầm: ô đó là TIỆN ÍCH cho
-- người dùng tự chọn, bỏ chọn là thấy hết. Nhân viên Tây Hồ đọc và sửa được
-- đơn hàng, lịch hẹn và hồ sơ đo mắt của Long Biên, và không có gì ngăn lại.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- BẢNG NỐI, KHÔNG PHẢI MỘT CỘT TRÊN user_roles
--
-- Q12.2 chốt một tài khoản gán được NHIỀU cơ sở (quản lý phụ trách cả hai).
-- Một cột `store_id` trên `user_roles` chỉ chứa được một giá trị, nên phải là
-- bảng nối. Nối tới `users` chứ không tới `user_roles`: phạm vi cơ sở là thuộc
-- tính của CON NGƯỜI, không phải của từng vai trò họ giữ — một người vừa là
-- Nhân viên vừa là Kỹ thuật viên thì vẫn chỉ làm việc ở đúng những cơ sở đó.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- GÁN SẴN MỌI TÀI KHOẢN HIỆN CÓ VÀO MỌI CƠ SỞ — CÓ CHỦ Ý
--
-- Q12.3 chốt: chưa gán cơ sở thì KHÔNG THẤY GÌ. Đó là hành vi đúng cho tài
-- khoản tạo MỚI. Nhưng áp thẳng nó cho dữ liệu đang chạy thì ngay giây phút
-- deploy, toàn bộ nhân viên mở khu quản trị lên và thấy danh sách trống — kể
-- cả người đang xử lý đơn dở.
--
-- Nên bước seed dưới đây gán mọi tài khoản nội bộ hiện có vào TẤT CẢ cơ sở,
-- tức giữ nguyên đúng hành vi hôm nay, không hơn không kém. Cửa hàng vào màn
-- Tài khoản nội bộ bỏ tick những cơ sở không thuộc về mình là siết lại được
-- ngay, từng người một, và thấy hậu quả trước mắt thay vì bị khoá hàng loạt.
--
-- Bước này chỉ chạy cho tài khoản ĐÃ TỒN TẠI lúc migration chạy. Tài khoản
-- tạo sau đó rơi vào đúng luật Q12.3.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VAI TRÒ THỨ NĂM: technician
--
-- X31 chốt năm vai trò và BỎ "Chủ doanh nghiệp" (dùng chung quyền admin).
-- Bốn vai trò cũ giữ nguyên tên; thêm 'technician' cho Kỹ thuật viên khúc xạ.
-- Q77.2 quy định chỉ Kỹ thuật viên và Quản lý cơ sở được tạo và sửa hồ sơ
-- khúc xạ, nên vai trò này có việc thật kể cả khi X07 đã chốt người bấm
-- "Bắt đầu mài" là Quản lý cơ sở.
--
-- MODIFY COLUMN trên ENUM là thao tác THÊM giá trị, không xoá giá trị nào, nên
-- không dòng dữ liệu nào bị ảnh hưởng. Chạy lại nhiều lần cho cùng kết quả.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CHẠY LẠI ĐƯỢC NHIỀU LẦN
--
-- CREATE TABLE IF NOT EXISTS; MODIFY ENUM là idempotent; INSERT dùng
-- INSERT IGNORE cộng khoá duy nhất nên lần hai không sinh dòng trùng.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. VAI TRÒ THỨ NĂM
-- ----------------------------------------------------------------------------
ALTER TABLE `user_roles`
    MODIFY COLUMN `role`
    ENUM('customer','staff','technician','manager','admin') NOT NULL;


-- ----------------------------------------------------------------------------
-- 2. BẢNG NỐI TÀI KHOẢN NỘI BỘ ↔ CƠ SỞ
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_stores` (
    `id`         CHAR(36) NOT NULL DEFAULT (UUID()),
    `user_id`    CHAR(36) NOT NULL,
    `store_id`   CHAR(36) NOT NULL,
    -- Ai gán, lúc nào. Đây là thao tác phân quyền nên phải truy được người
    -- chịu trách nhiệm; SET NULL để người nghỉ việc không kéo mất bản ghi.
    `granted_by` CHAR(36) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Một người một cơ sở chỉ một dòng. Khoá này cũng là thứ làm cho
    -- INSERT IGNORE ở bước seed chạy lại được mà không sinh trùng.
    UNIQUE KEY `uq_staff_store` (`user_id`, `store_id`),
    KEY `idx_staff_stores_store` (`store_id`),
    CONSTRAINT `fk_staff_stores_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE,
    -- Đóng một cơ sở thì gỡ luôn phân công vào cơ sở đó; không để lại phạm vi
    -- trỏ vào chỗ không còn tồn tại.
    CONSTRAINT `fk_staff_stores_store` FOREIGN KEY (`store_id`)
        REFERENCES `stores` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_staff_stores_by` FOREIGN KEY (`granted_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- 3. SEED — giữ nguyên hành vi hôm nay cho người đang làm việc
--
-- Xem khối chú thích đầu file. Chỉ gán cho tài khoản NỘI BỘ (có vai trò khác
-- 'customer'), và bỏ qua vai trò 'admin' vì Quản trị viên vốn thấy toàn bộ,
-- không cần dòng phân công nào.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `staff_stores` (`user_id`, `store_id`)
SELECT DISTINCT ur.`user_id`, s.`id`
  FROM `user_roles` ur
 CROSS JOIN `stores` s
 WHERE ur.`role` IN ('staff', 'technician', 'manager');


-- ----------------------------------------------------------------------------
-- KIỂM TRA SAU KHI CHẠY
--
--   SHOW COLUMNS FROM user_roles LIKE 'role';        -- có 'technician'
--   SELECT COUNT(*) FROM staff_stores;               -- = số nhân viên × số cơ sở
--   SELECT p.full_name, s.name
--     FROM staff_stores ss
--     JOIN profiles p ON p.id = ss.user_id
--     JOIN stores   s ON s.id = ss.store_id
--    ORDER BY p.full_name;                           -- xem ai đang thuộc cơ sở nào
--
-- Sau khi chạy, vào /quan-tri/nhan-vien bỏ tick những cơ sở không thuộc về
-- từng người. Chưa siết thì hệ thống chạy y như trước lần nâng cấp này.
-- ----------------------------------------------------------------------------
