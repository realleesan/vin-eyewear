# Logo thương hiệu — lưới S13 trang chủ

Thả file logo vào đúng thư mục này, đặt tên khớp với khoá `logo` trong
`config/brands.php` (ví dụ `ray-ban.svg`). Không có file thì khối vẫn chạy:
`app/views/_layout/home/brands.php` tự đổ về tên thương hiệu dạng chữ.

## Yêu cầu file

- **Định dạng**: SVG (ưu tiên) hoặc PNG nền trong.
- **Nền**: trong suốt, không viền, không khung trắng.
- **Màu**: bản một màu tối. Lưới đang đặt trên nền sáng và CSS hạ độ tương
  phản của logo khi chưa rê chuột, logo nhiều màu sẽ nhìn lệch nhau.
- **Kích thước**: chiều cao khoảng 40–48px là đủ; PNG thì xuất 2× (khoảng
  96px) cho màn hình mật độ cao.

## Bản quyền

Logo là nhãn hiệu của từng hãng. Chỉ dùng file lấy từ bộ nhận diện chính
thức mà hãng phát cho đại lý, và chỉ cho đúng hãng cửa hàng đang bán.
