# Vin Eyewear - Website Bán Kính Mắt AR/AI

## Giới thiệu dự án

Website bán kính mắt Vin Eyewear là dự án thương mại điện tử tích hợp công nghệ AR (Augmented Reality) và AI (Artificial Intelligence) để mang lại trải nghiệm mua sắm kính mắt trực tuyến đột phá.

### Các tính năng chính

- **Thử kính ảo (AR):** Cho phép khách hàng đeo thử kính trực tuyến qua camera
- **Xem sản phẩm 360 độ:** Xoay và xem chi tiết kính từ mọi góc độ
- **Phân tích khuôn mặt AI:** Tự động nhận diện dáng mặt để đề xuất gọng kính phù hợp
- **Đo khoảng cách đồng tử (PD) Online:** Đo thông số PD qua ảnh chụp
- **Mini ERP:** Quản lý đơn hàng, tồn kho, dữ liệu khách hàng

## Team phát triển

| Thành viên | Vai trò | Nhiệm vụ chính                                  |
| ------------ | -------- | -------------------------------------------------- |
| Nhật        | Techlead | Architecture, AR/AI, Payment, Deploy               |
| Hoàng Nam   | BA/Dev   | Business logic, Order, Admin, Kèm Hoàng Anh      |
| Sơn         | Dev      | Database, Models, Face Analysis, Product           |
| Duy Anh      | Dev      | Frontend, UI/UX, Contact, Event, Booking           |
| Hoàng Anh   | Dev      | Auth UI, Contact UI (được kèm bởi Hoàng Nam) |

## Công nghệ

- **Backend:** PHP thuần (Native PHP)
- **Frontend:** HTML, CSS, JavaScript
- **Database:** MySQL
- **Architecture:** MVC (Model-View-Controller)
- **AR Library:** MindAR, Three.js
- **AI Library:** MediaPipe Face Mesh
- **3D Viewer:** Google model-viewer

## Cấu trúc dự án

```
vin-eyewear/
├── app/                    # Application code
│   ├── controllers/       # Controllers (Logic xử lý)
│   │   ├── Admin/         # Admin controllers
│   │   ├── AboutController.php
│   │   ├── ApiController.php
│   │   ├── ArController.php
│   │   ├── AuthController.php
│   │   ├── BookingController.php
│   │   ├── ContactController.php
│   │   ├── ErrorController.php
│   │   ├── EventController.php
│   │   ├── HomeController.php
│   │   ├── OrderController.php
│   │   └── ProductController.php
│   ├── models/            # Models (Database logic)
│   ├── middleware/        # Middleware (Auth, Guest)
│   ├── services/          # Services (Email, Validation)
│   └── views/             # Views (Giao diện)
│       ├── _layout/       # Layout components
│       ├── admin/         # Admin views
│       ├── about/
│       ├── ar/
│       ├── auth/
│       ├── booking/
│       ├── contact/
│       ├── event/
│       ├── home/
│       ├── order/
│       └── product/
├── assets/                # Static files
│   ├── css/              # Stylesheets
│   ├── fonts/            # Font files
│   ├── icons/            # Icon files
│   ├── images/           # Image files
│   ├── js/               # JavaScript files
│   ├── models/           # 3D model files (.glb)
│   └── uploads/          # User uploaded files
├── config/               # Configuration files
│   ├── app.php          # App configuration
│   ├── database.php     # Database configuration
│   └── routes.php       # Route definitions
├── core/                # Core MVC framework
│   ├── App.php          # Application bootstrap
│   ├── BaseController.php
│   ├── BaseModel.php
│   ├── Database.php
│   ├── Router.php
│   └── helpers.php
├── database/            # Database files
│   └── schema.sql       # Database schema
├── docs/                # Documentation
│   ├── brain.md         # Project requirements
│   ├── nhiem-vu.md      # Task assignments
│   ├── server-info.md   # Server credentials
│   └── sitemap.png      # Sitemap diagram
├── errors/              # Error pages
├── .env.example         # Environment variables example
├── .gitignore
├── .htaccess            # Apache configuration
└── index.php            # Entry point
```

## Cài đặt

### Yêu cầu hệ thống

- PHP 7.4 hoặc cao hơn
- MySQL 5.7 hoặc cao hơn
- Apache web server với mod_rewrite enabled
- Composer (nếu cần thư viện PHP)

### Các bước cài đặt

1. **Clone repository**

```bash
git clone https://github.com/realleesan/vin-eyewear.git
cd vin-eyewear
```

2. **Cấu hình database**

- Tạo database MySQL mới
- Import file `database/schema.sql`
- Cập nhật thông tin database trong `config/database.php`

3. **Cấu hình environment**

```bash
cp .env.example .env
```

- Chỉnh sửa file `.env` với thông tin cấu hình của bạn

4. **Cấu hình web server**

- Đảm bảo mod_rewrite được bật
- Cấu hình DocumentRoot trỏ đến thư mục dự án
- Đảm bảo quyền ghi cho thư mục `assets/uploads/`

5. **Truy cập website**

- Mở browser và truy cập: http://localhost/vin-eyewear
- Hoặc domain đã cấu hình

## Phát triển

### Quy tắc coding

- Tuân thủ chuẩn PSR-12 cho PHP
- Sử dụng camelCase cho tên biến và hàm
- Sử dụng PascalCase cho tên class
- Comment code rõ ràng, tiếng Việt hoặc tiếng Anh

### Quy trình git

1. Pull latest changes: `git pull origin main`
2. Tạo branch mới cho feature: `git checkout -b feature/ten-feature`
3. Commit changes: `git commit -m "message"`
4. Push branch: `git push origin feature/ten-feature`
5. Tạo Pull Request trên GitHub

### Branch strategy

- `main`: Branch production
- `develop`: Branch phát triển chính
- `feature/*`: Branch cho từng feature
- `hotfix/*`: Branch cho sửa lỗi gấp

## Triển khai

### Hosting

- **Hosting:** InfinityFree
- **Domain:** vreyewear.gt.tc (tạm)
- **Cpanel:** https://cpanel.infinityfree.com/login.php
- Chi tiết đăng nhập xem trong `docs/server-info.md`

### Các bước deploy

1. Commit và push changes lên GitHub
2. Login vào FTP/SFTP của hosting
3. Upload files lên hosting (hoặc dùng git pull trên server)
4. Import database nếu có thay đổi schema
5. Cấu hình `.env` trên production
6. Test functionality

## Tài liệu

- **Yêu cầu dự án:** `docs/brain.md`
- **Phân công nhiệm vụ:** `docs/nhiem-vu.md`
- **Thông tin server:** `docs/server-info.md`
- **Biên bản khảo sát:** `docs/[Output] Kính mắt.md`
- **Sitemap:** `docs/sitemap.png`

## Hỗ trợ

Nếu có vấn đề hoặc câu hỏi:

- Liên hệ Techlead (Nhật)
- Tạo issue trên GitHub
- Tham khảo tài liệu trong thư mục `docs/`

## License

Dự án này là tài sản của Vin Eyewear và Techcamp Việt Nam.

---

**Lưu ý:** Đây là dự án thương mại, không được chia sẻ bên ngoài team phát triển.
