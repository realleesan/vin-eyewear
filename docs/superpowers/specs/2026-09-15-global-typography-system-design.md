# Hệ typography điều khiển tập trung cho toàn site

## Mục tiêu

Mọi trang storefront và quản trị dùng cùng một bộ tên token typography, không
ghi cỡ chữ trực tiếp trong CSS trang. Người vận hành có thể tăng hoặc giảm cỡ
chữ cho một khu hoặc toàn site bằng biến `--type-scale`, mà vẫn giữ các vai trò
chữ và tỷ lệ giữa chúng.

## Phạm vi

- Storefront: layout chung, các trang sản phẩm, bộ sưu tập, giỏ hàng, thanh
  toán, tài khoản, liên hệ, đặt lịch và các component dùng chung.
- Quản trị: layout quản trị, dashboard, đăng nhập, đơn hàng, sản phẩm và các
  trang quản trị khác.
- CSS dùng chung giữa hai khu chỉ tham chiếu token ngữ nghĩa, không phụ thuộc
  vào tên token của một layout riêng.

Không đổi family font, copy, markup, luồng form, hay hành vi JavaScript.

## Kiến trúc token

Một file token typography dùng chung được nạp trước CSS của từng khu. File này
khai báo:

- `--type-scale`: hệ số điều khiển cỡ chữ, mặc định `1`.
- Token vai trò cho giao diện: `--fs-micro`, `--fs-label`, `--fs-caption`,
  `--fs-body-sm`, `--fs-body`, `--fs-body-lg`.
- Token vai trò cho tiêu đề: `--fs-title`, `--fs-heading`, `--fs-subtitle`,
  `--fs-display`.
- Token line-height và letter-spacing theo vai trò.

Mỗi token được tính từ một giá trị nền duy nhất nhân với `--type-scale`.
Tiêu đề dùng `clamp()` để thay đổi theo viewport nhưng vẫn chịu hệ số scale.
Các token cũ cần tương thích được ánh xạ về token mới trong một giai đoạn
chuyển đổi, sau đó CSS trang chỉ dùng tên vai trò chuẩn.

Storefront và quản trị có thể đặt `--type-scale` tại wrapper của khu tương ứng;
root giữ giá trị mặc định. Nhờ vậy tên token giống nhau ở mọi nơi nhưng một khu
vẫn có thể được điều chỉnh mà không vô tình ảnh hưởng khu còn lại.

## Luồng áp dụng

1. Nạp token chung trước `oa.css` (storefront) và `layout.css` (quản trị).
2. Sửa các khai báo token địa phương để dùng bộ token chung, không định nghĩa
   thang chữ cạnh tranh.
3. Thay từng `font-size` pixel mang nghĩa giao diện bằng token tương ứng.
   Các ngoại lệ kỹ thuật như `font-size: 0` để ẩn text hoặc kích thước icon sẽ
   được giữ và ghi chú.
4. Loại các override cỡ chữ kế thừa trong `contact.css` và `booking.css` để hai
   trang không còn là nguồn chuẩn riêng.
5. Giữ selector và class hiện hữu để JS, accessibility và form contract không
   thay đổi.

## Phân loại và ánh xạ

| Vai trò | Token | Dùng cho |
| --- | --- | --- |
| Meta rất nhỏ | `--fs-micro` | badge, trạng thái, chú thích ngắn |
| Nhãn | `--fs-label` | label form, nhãn viết hoa |
| Chú thích | `--fs-caption` | metadata, helper text |
| Thân bài nhỏ | `--fs-body-sm` | mô tả gọn trong thẻ |
| Thân bài | `--fs-body` | đoạn văn, trường nhập, bảng |
| Thân bài lớn | `--fs-body-lg` | lead và nội dung ưu tiên |
| Tiêu đề nhỏ | `--fs-subtitle` | heading thẻ và panel |
| Tiêu đề section | `--fs-heading` | section heading |
| Tiêu đề trang | `--fs-title` | h1 và page-head |
| Display | `--fs-display` | hero có chủ đích |

## Khả năng tương thích và lỗi

- Fallback token được giữ trong giai đoạn chuyển đổi để CSS component chung
  không bị rỗng khi một layout chưa nạp token mới.
- `--type-scale` chỉ ảnh hưởng text; các kích thước icon, vùng chạm và lưới
  không tự đổi để tránh vỡ bố cục. Những vị trí cần tăng vùng chạm sẽ được xử
  lý riêng bằng token kích thước control.
- CSS dùng `:has()` cho các trạng thái đặt lịch không bị sửa selector hoặc cấu
  trúc, chỉ thay giá trị font.

## Kiểm thử

- Kiểm tra tĩnh: không còn `font-size` pixel mang nghĩa typography trong CSS
  trang sau khi ngoại trừ danh sách kỹ thuật được xác định.
- Render test hiện có của Contact vẫn đạt.
- Kiểm tra các trang đại diện: trang chủ, sản phẩm, liên hệ, đặt lịch, giỏ
  hàng/thanh toán, tài khoản và một trang quản trị ở desktop và mobile.
- Đặt tạm `--type-scale` khác `1` tại DevTools để xác nhận text thay đổi đồng
  bộ mà không làm vỡ form hay navigation.
