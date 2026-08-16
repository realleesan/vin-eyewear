# Ảnh trang chủ — lấy từ bản thiết kế Claude Design

Bản thiết kế `Vin Eyewear Home.dc.html` gắn ảnh vào từng ô `<image-slot>` theo
**id của ô**. Những tấm ảnh đó nằm trong dự án Claude Design chứ không nằm trong
repo, và **phải tải về tay** — công cụ đọc file của Claude Design cắt nội dung ở
256 KB, trong khi cả bộ ảnh nằm chung một file `.image-slots.state.json` nặng
khoảng 1 MB, nên chỉ hai tấm đầu tiên lấy được tự động.

Dự án: <https://claude.ai/design/p/189016ac-229d-4e2e-bd8c-8639a10b828c>

## Cách đặt tên

**Tên file = id của ô trong bản thiết kế.** Đuôi file nào cũng được, hàm
`designImage()` trong `core/helpers.php` thử lần lượt `.webp` → `.jpg` →
`.jpeg` → `.png` rồi mới quay về ảnh dự phòng. Thả file đúng tên vào thư mục
này là trang tự đổi, không phải sửa view.

| Tên file cần đặt | Ô trong thiết kế | Nội dung ảnh | Ảnh dự phòng đang dùng |
|---|---|---|---|
| `hero-photo.*`   | `hero-photo`   | Chân dung đeo kính (ảnh lớn bên phải hero) | `hero-models.jpg` |
| `hero-thumb-1.*` | `uploads/1.jpg` | Ảnh tròn nhỏ số 1 dưới hero | `product-1.jpg` |
| `hero-thumb-2.*` | `uploads/2.jpg` | Ảnh tròn nhỏ số 2 dưới hero | `product-2.jpg` |
| `hero-thumb-3.*` | `uploads/3.jpg` | Ảnh tròn nhỏ số 3 dưới hero | `product-3.jpg` |
| `cat-gong.*`     | `cat-gong`     | Ảnh bìa danh mục "Gọng kính"  | `product-1.jpg` |
| `cat-mat.*`      | `cat-mat`      | Ảnh bìa danh mục "Kính mát"   | `product-3.jpg` |
| `cat-trong.*`    | `cat-trong`    | Ảnh bìa danh mục "Tròng kính" | `product-5.jpg` |
| `style-1.*` ✅   | `style-1`      | Gọng vuông — thẻ "Năng động"  | *(đã có)* |
| `style-2.*`      | `style-2`      | Gọng oval — thẻ "Thanh lịch"  | `product-3.jpg` |
| `style-3.*`      | `style-3`      | Gọng tròn — thẻ "Cổ điển trở lại" | `product-2.jpg` |
| `lab-photo.*`    | `lab-photo`    | Máy đo mắt / bảng thị lực     | `showroom-exam-room.jpg` |
| `store-photo.*`  | `store-photo`  | Không gian cửa hàng           | `showroom-frames.jpg` |
| `cta-photo.*` ✅ | `cta-photo`    | Kệ trưng bày kính             | *(đã có)* |

✅ = đã trích được sẵn, không cần tải lại.

## Hai ô KHÔNG nằm trong bảng này

`prod-1` và `prod-2` (hai thẻ "Sản phẩm bán chạy") **cố ý không** đọc từ thư
mục này. Khối đó lấy ảnh từ cột ảnh của bảng `products` qua
`ProductModel::image()`, vì nó hiển thị hàng có thật trong kho chứ không phải
hai món cố định của bản thiết kế. Muốn đổi ảnh thì sửa trong trang quản trị
sản phẩm.

## Cắt ảnh thế nào

Bản thiết kế để `object-fit: cover` cho mọi ô, trừ hai ô sản phẩm dùng
`contain`. Nghĩa là ảnh sẽ **bị xén cho vừa khung**, không phải thu nhỏ lọt
khung — nên cứ tải nguyên tấm về, đừng cắt trước. Tỉ lệ khung của từng ô:

- `hero-photo` — cột cao, tối thiểu 560px chiều cao
- `cat-*` — khung ngang 300px chiều cao
- `style-*` — khung vòm 260px chiều cao, hai góc trên bo bán nguyệt
- `lab-photo` — khung ngang 280px chiều cao
- `store-photo` — khung vòm cao 460px
- `cta-photo` — cột phải của khối CTA, tối thiểu 340px chiều cao

Nếu chưa tải ảnh về, trang vẫn chạy bình thường bằng ảnh dự phòng — chỉ khác
là mấy ô danh mục và khuôn mặt sẽ xén mất càng gọng, vì ảnh dự phòng là ảnh
sản phẩm cắt nền chứ không phải ảnh bìa chụp tràn khung.
