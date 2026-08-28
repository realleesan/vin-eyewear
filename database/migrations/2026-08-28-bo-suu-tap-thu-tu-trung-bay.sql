-- 2026-08-28 — bộ sưu tập: lấp `sort_order` bằng thứ tự đang trưng bày
--
-- BỐI CẢNH
--
-- Cột `collections`.`sort_order` có trong lược đồ từ đầu nhưng chưa nơi nào
-- ĐỌC tới: CollectionModel sắp theo `launched_at DESC, name ASC`. Từ hôm nay
-- khu quản trị có nút ↑↓ để đổi thứ tự trưng bày, nên model đổi sang
-- `sort_order ASC, launched_at DESC, name ASC`.
--
-- VÌ SAO PHẢI LẤP TRƯỚC
--
-- Nếu mọi dòng cùng mang một giá trị thì thêm `sort_order` vào đầu mệnh đề
-- ORDER BY không đổi gì cả — hai vế sau vẫn quyết định hết. Nhưng trên CSDL
-- đang chạy thì KHÔNG: ba bộ đang mang 0, 20, 30 (giá trị sót lại từ đợt nhập
-- liệu đầu). Đổi model mà không lấp thì thứ tự bộ sưu tập ở TRANG BÁN HÀNG
-- nhảy ngay lúc deploy, theo một dãy số không ai cố ý đặt.
--
-- Nên: ghi lại `sort_order` = đúng vị trí mà mỗi bộ ĐANG đứng theo luật cũ.
-- Sau file này, đổi model không làm xê dịch một dòng nào; chỉ từ lần đầu ai đó
-- bấm ↑↓ thì tay người mới thắng ngày ra mắt.
--
-- CHẠY LẠI ĐƯỢC: phép đánh số dựa trên `launched_at`/`name` chứ không dựa vào
-- `sort_order`, nên chạy hai lần ra cùng kết quả. (migrate.sh cũng ghi sổ để
-- không chạy lại — xem bảng `schema_migrations`.)
--
-- LƯU Ý: nếu đã có ai bấm ↑↓ rồi thì ĐỪNG chạy lại file này bằng tay — nó sẽ
-- xoá thứ tự họ vừa sắp và trả về thứ tự theo ngày ra mắt.

UPDATE `collections` AS c
  JOIN (
    SELECT
      `id`,
      ROW_NUMBER() OVER (ORDER BY `launched_at` DESC, `name` ASC) - 1 AS `vi_tri`
    FROM `collections`
  ) AS t ON t.`id` = c.`id`
   SET c.`sort_order` = t.`vi_tri`;
