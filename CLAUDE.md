# VIN EYEWEAR — Hướng dẫn cho Claude Code

## 1. Bối cảnh dự án

- Website bán kính mắt, **PHP thuần, mô hình MVC tự dựng** (KHÔNG dùng Laravel/CodeIgniter, không composer, không framework).
- Giai đoạn hiện tại: **Phase 1.5 (22/07 – 24/07/2026)** — chỉnh sửa Prototype tĩnh, chuẩn bị demo khách ngày 24/07.
- Phase 1.5 **vẫn chưa có Database**. Controller chỉ nhận request và render thẳng View tĩnh. Không viết Model, không kết nối PDO ở phase này trừ khi tôi yêu cầu rõ.

## 2. Cấu trúc thư mục (bắt buộc tuân thủ, không tự đẻ thư mục mới)

```
public/index.php              # Front controller, cửa ngõ duy nhất
config/routes.php             # Bản đồ định tuyến: 'contact' => 'ContactController@index'
core/Router.php               # Bộ tìm đường
core/BaseController.php       # Chứa hàm renderView() dùng chung
app/controllers/              # HomeController.php, ProductController.php...
app/views/_layout/            # master.php, header.php, footer.php + các component chung
app/views/<module>/           # home/index.php, product/index.php...
assets/css/                   # global.css, layout.css, home.css, product.css
assets/css/components/        # CSS riêng của từng component
assets/js/                    # home.js, product.js...
assets/js/components/         # JS riêng của từng component
errors/                       # 404.php, 500.php
```

## 3. Luật vàng về CSS/JS (vi phạm là gây xung đột merge cả team)

- **TUYỆT ĐỐI KHÔNG** thêm thẻ `<link>` / `<script>` vào `header.php` hoặc `master.php`.
- Mỗi view con tự nhúng tài nguyên của mình ngay dòng đầu file:

```php
<link rel="stylesheet" href="/assets/css/product.css">
<script src="/assets/js/product.js" defer></script>
```

- Nếu file CSS/JS chưa tồn tại thì tự tạo file mới đúng vị trí quy định, không nhét chung vào file của người khác.

## 4. Luật về Reusable Component (Phase 1.5)

- Chuẩn thiết kế gốc (design benchmark) của **Page Header** và **CTA** là **trang Product**. Lấy cấu trúc HTML/CSS trang Product làm mẫu, không tự sáng tác style mới.
- Component phải nhận biến cờ boolean để bật/tắt động, có giá trị mặc định an toàn:

```php
$show_page_header = $show_page_header ?? true;
$show_breadcrumb  = $show_breadcrumb  ?? true;
$show_cta         = $show_cta         ?? true;
$filter_type      = $filter_type      ?? 'product';   // 'product' | 'category' | 'event'
```

- Nguyên tắc phân định Filter Sidebar: **Duy Anh làm "Vỏ" (UI framework + JS toggle), thành viên khác tự thầu "Ruột" (data & logic lọc)**. Vì vậy `filter-sidebar.php` chỉ dựng khung, nhận `$filter_type` để ẩn/hiện nhóm input, KHÔNG hard-code danh sách dữ liệu lọc của trang Category hay Event.
- Component nhúng bằng `require`/`include` từ `app/views/_layout/`, không copy-paste HTML sang từng trang.

## 5. Bảng màu thương hiệu (Color Guideline)

| Vai trò                      | Mã HEX    |
| ---------------------------- | --------- |
| Primary (chủ đạo, nền sáng)  | `#FFFFFF` |
| Secondary (Đỏ đô / Burgundy) | `#800020` |
| Accent (Vàng ánh kim)        | `#ffcc00` |
| Nền tối                      | `#2B0A14` |
| Trung tính                   | `#7cafad` |
| Warning                      | `#e62916` |
| Success                      | `#2bcd33` |
| Error                        | `#C0392B` |

Định hướng: tối giản, tinh tế, sang trọng. Nút "Mua ngay" dùng `#800020`.
Khi viết CSS mới, khai báo qua CSS variable trong `global.css` thay vì rải mã hex khắp nơi.

## 6. Responsive

Breakpoint bắt buộc test: **390px, 480px (mobile), 768px, 1024px (tablet)**.
Mobile-first. Filter Sidebar trên mobile chạy dạng **slide-over** (trượt từ cạnh màn hình), có nút đóng/mở và overlay nền.

## 7. Git — quy tắc nghiêm ngặt

- **KHÔNG BAO GIỜ commit trực tiếp vào `main`.** Chỉ Tech Lead (Nhật) được merge.
- Mỗi hạng mục nằm đúng nhánh của nó:
  - `feature/layout-components` → filter-sidebar, page-header, breadcrumb, cta, pusher
  - `feature/home-fixed` → trang Home + Booking Section
  - `feature/product-fixed` → trang Product + nhúng Filter
- Khi xong module: push nhánh feature → tạo Pull Request vào `main` → báo Zalo.
- Claude Code **không tự chạy `git push`, không tự tạo PR, không tự merge** nếu tôi chưa yêu cầu rõ ràng.

## 8. Phạm vi file được phép sửa (rất quan trọng)

Tôi là **Duy Anh**. Chỉ được sửa các file thuộc phần việc của tôi:

- `app/views/home/`, `app/views/product/`
- `app/views/_layout/` (các component dùng chung + header/footer khi làm task A3)
- `assets/css/home.css`, `product.css`, `layout.css`, `pusher.css`, `assets/css/components/*`
- `assets/js/` tương ứng

**KHÔNG được đụng vào:** `app/views/contact/`, `about/`, `event/`, `category/`, `ar/`, `errors/`, `core/`, `config/routes.php`, `public/index.php`.
Nếu thấy cần sửa file ngoài phạm vi, hãy **dừng lại và báo tôi**, đừng tự sửa.

## 9. Cách làm việc tôi mong muốn

- Trước khi code, đọc file hiện có để bám sát style code sẵn có, không viết lại từ đầu.
- Với task lớn, dùng **Plan mode** trình bày kế hoạch trước, tôi duyệt rồi mới sửa file.
- Comment code bằng tiếng Việt không dấu hoặc tiếng Anh, ngắn gọn.
- Không thêm thư viện ngoài (Bootstrap, Tailwind, jQuery) nếu tôi không yêu cầu. Viết CSS/JS thuần.
- Sau mỗi task, tóm tắt ngắn: đã sửa file nào, còn gì cần tôi tự kiểm tra bằng tay.
