-- ============================================================================
-- NÂNG CẤP 2026-08-28
-- Bộ sưu tập: một ảnh bìa -> một BỘ ảnh lookbook
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CỘT MỚI `images`, VÀ VÌ SAO KHÔNG XOÁ `cover_image` NGAY
--
-- `collections.cover_image` là VARCHAR, chứa đúng một đường dẫn. Cửa hàng nay
-- chụp cả bộ ảnh cho mỗi bộ sưu tập, nên chỗ chứa phải là một DANH SÁCH.
--
-- Làm giống hệt `products.images` — cùng dự án thì cùng một cách giải cho cùng
-- một bài toán: mảng JSON các đường dẫn, PHẦN TỬ ĐẦU là ảnh đại diện. Form
-- quản trị đổi ảnh đại diện bằng cách đưa ảnh được chọn lên đầu mảng, không có
-- cột "ảnh nào là bìa" riêng.
--
-- Bước 2 CHÉP cover_image hiện có vào images, nên không bộ nào mất ảnh.
--
-- Sau bước đó `cover_image` thành cột CHẾT: mã không ghi vào nó nữa, và chỉ đọc
-- nó như một lưới an toàn cho dòng nào có ảnh bìa mà `images` lại rỗng — tình
-- huống mà bước 2 đã dọn sạch, nhưng vẫn có thể xuất hiện nếu ai đó sửa tay
-- trong phpMyAdmin.
--
-- KHÔNG DROP nó trong file này, cố ý. Xoá cột là thao tác không lấy lại được,
-- và ngay sau một lần chuyển dữ liệu là lúc tệ nhất để làm: nếu bước 2 sai ở
-- một dòng nào đó thì cột cũ là thứ duy nhất còn giữ đường dẫn gốc. Để nó nằm
-- đó một thời gian, khi nào chắc thì dọn bằng một migration riêng chỉ có mỗi
-- việc ấy — lúc đó nhìn tên file là biết mình đang xoá gì.
--
-- Chạy lại nhiều lần không hỏng: có cột rồi thì bỏ qua, và bước 2 chỉ đụng
-- những dòng `images` còn NULL nên không đè lên bộ ảnh cửa hàng đã sắp lại.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1. THÊM CỘT
-- ----------------------------------------------------------------------------
SET @co_cot := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'collections'
       AND COLUMN_NAME  = 'images'
);

SET @sql := IF(@co_cot = 0,
    'ALTER TABLE `collections`
        ADD COLUMN `images` JSON NULL DEFAULT NULL AFTER `cover_image`',
    'SELECT ''collections.images da co, bo qua'' AS ghi_chu'
);
PREPARE cau_lenh FROM @sql; EXECUTE cau_lenh; DEALLOCATE PREPARE cau_lenh;


-- ----------------------------------------------------------------------------
-- 2. CHÉP ẢNH BÌA ĐANG CÓ VÀO DANH SÁCH MỚI
--
-- JSON_ARRAY() chứ không ghép chuỗi tay: nó tự lo dấu nháy và ký tự cần thoát,
-- mà đường dẫn ảnh do nhân viên tải lên nên tên file có thể chứa bất cứ thứ gì
-- ImageUploader cho qua.
--
-- WHERE `images` IS NULL: dòng nào đã có bộ ảnh (vì file này chạy lần hai, hoặc
-- vì cửa hàng đã sắp lại thứ tự) thì không đụng tới. Đây là điều làm cho việc
-- dán lại file này trong phpMyAdmin trở nên vô hại.
--
-- Điều kiện `cover_image` khác rỗng: bộ chưa có ảnh nào thì để `images` NULL,
-- KHÔNG ghi mảng rỗng "[]". Trang phân biệt hai thứ đó — NULL đọc là "chưa có
-- ảnh", còn một mảng rỗng thì cũng ra cùng kết quả nhưng đọc lại trong CSDL
-- không ai biết là cố ý hay hỏng. Cùng luật với `story` và ba cột JSON kia.
-- ----------------------------------------------------------------------------
UPDATE `collections`
   SET `images` = JSON_ARRAY(`cover_image`)
 WHERE `images` IS NULL
   AND `cover_image` IS NOT NULL
   AND TRIM(`cover_image`) <> '';
