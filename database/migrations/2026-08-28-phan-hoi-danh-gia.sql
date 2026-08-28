-- 2026-08-28 — đánh giá: thêm phần phản hồi công khai của cửa hàng
--
-- Bản thiết kế "Đánh giá.dc.html" vẽ một khối "Phản hồi của cửa hàng" nằm dưới
-- mỗi đánh giá, và một hộp thoại để nhân viên soạn câu trả lời. Bảng `reviews`
-- chưa có chỗ nào chứa câu ấy.
--
-- HAI CỘT, KHÔNG PHẢI MỘT
--
-- `reply` giữ nội dung, `replied_at` giữ MỐC THỜI GIAN. Suy mốc từ
-- `updated_at` thì không được: cột đó đổi theo mọi lần sửa dòng, kể cả lúc
-- nhân viên bấm Duyệt. Khách đọc trang sản phẩm cần biết cửa hàng trả lời lúc
-- nào — trả lời sau ba ngày và trả lời sau ba tháng là hai chuyện khác nhau.
--
-- CẢ HAI ĐỀU NULL ĐƯỢC: phần lớn đánh giá sẽ không có phản hồi. Và NULL khác
-- chuỗi rỗng ở đây — rỗng nghĩa là "đã trả lời rồi xoá chữ đi", NULL nghĩa là
-- "chưa từng trả lời". Chỗ hiển thị kiểm cả hai.
--
-- KHÔNG DÙNG `ADD COLUMN IF NOT EXISTS`: MySQL 8 không có cú pháp đó (chỉ
-- MariaDB có), mà máy chủ của dự án là MySQL 8.4. Việc chặn chạy lại đã có
-- migrate.sh lo bằng cột mốc `column|reviews|reply` — đúng cơ chế mà mọi file
-- ALTER khác trong thư mục này đang dựa vào.

ALTER TABLE `reviews`
    ADD COLUMN `reply`      TEXT     NULL AFTER `body`,
    ADD COLUMN `replied_at` DATETIME NULL AFTER `reply`;
