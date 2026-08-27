-- ============================================================================
-- NÂNG CẤP 2026-08-27 (thứ tư trong ngày)
-- Bảng `site_texts`: mấy câu chữ trên trang mà cửa hàng tự sửa được
--
-- ─────────────────────────────────────────────────────────────────────────────
-- VÌ SAO CẦN MỘT BẢNG CHO HAI CÂU CHỮ
--
-- Đầu trang /bo-suu-tap có một tiêu đề và một đoạn dẫn. Tới giờ chúng nằm cứng
-- trong app/views/collection/index.php, nghĩa là đổi một chữ là sửa mã và
-- deploy — đúng cái mà bảng `collections` đã bỏ đi hôm 2026-08-25 cho phần dữ
-- liệu của từng bộ. Phần chữ GIỚI THIỆU CẢ TRANG thì vẫn kẹt lại.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- KHOÁ LÀ CHUỖI CÓ TIỀN TỐ, KHÔNG PHẢI MỘT CỘT CHO MỖI CÂU
--
-- Cách kia là thêm hai cột vào một bảng "cấu hình" một dòng. Nó chạy, nhưng
-- mỗi câu chữ mới ở trang khác lại là một migration nữa, và bảng ấy sẽ phình
-- ra hàng chục cột mà chín mươi phần trăm luôn NULL.
--
-- Ở đây: khoá dạng `<trang>.<chỗ>` — 'bo-suu-tap.tieu_de', 'bo-suu-tap.doan_dan'.
-- Thêm câu chữ cho trang khác là thêm một DÒNG, không phải một cột.
--
-- Cái giá: không có ràng buộc nào bắt khoá phải đúng chính tả, và một khoá gõ
-- sai thì lặng lẽ không hiện ra đâu cả. Bù lại bằng hai điều:
--   · mọi khoá đang dùng khai thành hằng trong SiteTextModel, không gõ tay;
--   · nơi đọc LUÔN truyền sẵn câu mặc định, nên khoá sai hay bảng trống thì
--     trang vẫn hiện đúng chữ như trước ngày hôm nay.
--
-- ─────────────────────────────────────────────────────────────────────────────
-- CỘT TÊN `text_key` CHỨ KHÔNG PHẢI `key`
--
-- `KEY` là từ khoá của MySQL. Đặt tên cột như thế thì mọi câu lệnh nhắc tới nó
-- đều phải bọc dấu huyền, và quên một chỗ là lỗi cú pháp ở đúng chỗ khó đoán
-- nhất. Đổi tên rẻ hơn nhớ bọc.
--
-- KHÔNG có cột `id` UUID như các bảng khác: khoá chính ở đây LÀ `text_key`.
-- Thêm một id thay thế chỉ để giống hàng xóm thì bảng có hai thứ định danh mà
-- không thứ nào được dùng làm khoá ngoại.
--
-- Chạy lại nhiều lần không hỏng: bảng có rồi thì bỏ qua, và hai dòng gieo sẵn
-- đi qua INSERT IGNORE nên không đè lên chữ cửa hàng đã sửa.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `site_texts` (
    `text_key`   VARCHAR(64) NOT NULL,
    `value`      TEXT        NOT NULL,
    `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`text_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gieo đúng hai câu đang nằm cứng trong view, để mở trang quản trị lên là thấy
-- chữ hiện hành chứ không phải hai ô trống.
--
-- INSERT IGNORE: chạy lại file này không đè lên chữ cửa hàng đã sửa.
INSERT IGNORE INTO `site_texts` (`text_key`, `value`) VALUES
    ('bo-suu-tap.tieu_de',  'Bộ sưu tập'),
    ('bo-suu-tap.doan_dan', 'Mỗi bộ là một cách chọn gọng và tròng cho một kiểu ngày. Mở bộ nào nghe hợp với bạn để xem kỹ, rồi lọc thẳng sang danh mục.');
