# Ảnh lookbook bộ sưu tập — khối S09 trang chủ

Thả ảnh vào đúng thư mục này, đặt tên khớp khoá `image` trong
`config/collections.php` (ví dụ `nang-he.jpg`). Chưa có file thì khối vẫn
chạy: `app/views/_layout/home/collections.php` tạm dùng ảnh `image_sample`
lấy từ kho ảnh sẵn có.

## Yêu cầu ảnh

- **Nội dung**: NGƯỜI THẬT ĐANG ĐEO KÍNH, chụp ngoài đời hoặc trong studio có
  bối cảnh. Không dùng ảnh sản phẩm nền trắng — loại đó đã có ở lưới sản phẩm,
  lặp lại ở đây thì bộ sưu tập không còn là lookbook.
- **Khung**: dọc, tỉ lệ khoảng 3:4. Thẻ cắt theo tỉ lệ này, ảnh ngang sẽ bị
  cắt mất hai bên.
- **Kích thước**: cạnh dài tối thiểu 1200px, xuất JPG chất lượng ~80.
- **Chỗ đặt chữ**: chừa phần dưới ảnh thoáng một chút — tên bộ sưu tập và mô
  tả nằm đè lên vùng đó.

Xong ảnh thật thì xoá khoá `image_sample` của bộ sưu tập đó trong config.
