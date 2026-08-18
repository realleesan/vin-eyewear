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

| Tên file cần đặt | Nội dung ảnh | Ảnh dự phòng đang dùng |
|---|---|---|
| `hero-photo.*`    | Băng hero, ảnh 1 — chân dung đeo kính      | `hero-models.jpg` |
| `hero-slide-2.*`  | Băng hero, ảnh 2 — bộ sưu tập kính mát     | `showroom-frames.jpg` |
| `hero-slide-3.*`  | Băng hero, ảnh 3 — gọng titan mới lên kệ   | `hero-eyewear.jpg` |
| `cat-gong.*`      | Ảnh bìa danh mục "Gọng kính"               | `product-1.jpg` |
| `cat-mat.*`       | Ảnh bìa danh mục "Kính mát"                | `product-3.jpg` |
| `cat-trong.*`     | Ảnh bìa danh mục "Tròng kính"              | `product-5.jpg` |
| `check-1.*`       | Thẻ "Chọn tròng" — tròng kính cận cảnh     | `product-5.jpg` |
| `check-2.*`       | Thẻ "Chọn gọng" — kệ trưng bày gọng        | `showroom-frames.jpg` |
| `check-3.*`       | Thẻ "Đặt lịch" — tư vấn tại cửa hàng       | `showroom-exam-room.jpg` |
| `lab-photo.*`     | Máy đo mắt / bảng thị lực                  | `showroom-exam-room.jpg` |
| `store-photo.*`   | Không gian cửa hàng (khối "Đo mắt")        | `showroom-frames.jpg` |
| `cta-photo.*` ✅  | Kệ trưng bày kính (khối cuối trang)        | *(đã có)* |

✅ = đã trích được sẵn, không cần tải lại.

`style-1.webp` còn nằm trong thư mục này nhưng **không còn nơi nào dùng**: nó
phục vụ khối "chọn theo khuôn mặt" của bản thiết kế trước, đã được khối "Bỏ ra
5 phút để kiểm tra" thay chỗ. Xoá được, giữ lại cũng không sao.

## Ba ô KHÔNG nằm trong bảng này

- **Hai lưới sản phẩm** ("Sản phẩm mới về" và "Sản phẩm bán chạy") lấy ảnh từ
  cột ảnh của bảng `products` qua `ProductModel::image()`, vì chúng hiển thị
  hàng có thật trong kho chứ không phải mấy món cố định của bản thiết kế. Muốn
  đổi ảnh thì sửa trong trang quản trị sản phẩm.
- **Lưới "Bộ sưu tập mới"** lấy ảnh từ `config/collections.php` — xem
  `assets/images/collections/README.md`.

## Cắt ảnh thế nào

Bản thiết kế để `object-fit: cover` cho mọi ô, trừ hai lưới sản phẩm dùng
`contain`. Nghĩa là ảnh sẽ **bị xén cho vừa khung**, không phải thu nhỏ lọt
khung — nên cứ tải nguyên tấm về, đừng cắt trước. Tỉ lệ khung của từng ô:

- `hero-photo` · `hero-slide-2` · `hero-slide-3` — cột cao, tối thiểu 680px
  chiều cao; cả ba phải cùng tỉ lệ, không thì băng ảnh nhảy khung khi trượt
- `cat-*` — khung ngang 300px chiều cao
- `check-*` — khung ngang 400px chiều cao
- `lab-photo` — khung ngang 280px chiều cao
- `store-photo` — khung ngang 460px chiều cao
- `cta-photo` — cột phải của khối CTA, tối thiểu 340px chiều cao

Nếu chưa tải ảnh về, trang vẫn chạy bình thường bằng ảnh dự phòng — chỉ khác
là mấy ô danh mục sẽ xén mất càng gọng, vì ảnh dự phòng là ảnh sản phẩm cắt
nền chứ không phải ảnh bìa chụp tràn khung.
