# Chạy Vin Eyewear bằng Docker

Bộ file này dựng một môi trường tương đương XAMPP (PHP + Apache + MySQL) nhưng
cô lập hoàn toàn trong container — không cần cài PHP/MySQL/Apache lên máy,
không đụng tới các dự án khác đang chạy trên XAMPP.

Ba dịch vụ, khai báo trong [docker-compose.yml](docker-compose.yml):

| Dịch vụ      | Vai trò                                  | Cổng trên máy |
|--------------|-------------------------------------------|---------------|
| `app`        | PHP 8.2 + Apache, chạy mã nguồn dự án      | 8080          |
| `db`         | MariaDB, tự nạp `database/schema.sql`      | 3306          |
| `phpmyadmin` | Xem/sửa dữ liệu qua trình duyệt (tuỳ chọn) | 8081          |

## Yêu cầu

- Docker Desktop (Windows/Mac) hoặc Docker Engine + Compose plugin (Linux).

## Chạy lần đầu

```powershell
docker compose up -d --build
```

Lần chạy đầu tiên sẽ tự động:

1. Build image `app` (cài extension `pdo_mysql`, `gd`, `curl`…).
2. Khởi động `db`, nạp `database/schema.sql` vào database `vin_eyewear`
   (chỉ nạp khi volume database còn trống — chạy lại `up` không xoá dữ liệu).
3. [docker/entrypoint.sh](docker/entrypoint.sh) tạo file `.env` từ
   `.env.example` (nếu chưa có) và điền `DB_HOST=db` cùng các giá trị khớp với
   `docker-compose.yml`, rồi tạo sẵn `storage/` và `assets/uploads/*` — hai
   thư mục bị `.gitignore` bỏ qua nên không có sẵn khi mới `git clone`.

Mở <http://localhost:8080> để xem site.

## Tạo tài khoản quản trị

Không tự động — script in mật khẩu ngẫu nhiên ra màn hình đúng một lần, nên
phải chạy tay:

```powershell
docker compose exec app php database/make-admin.php admin@vineyewear.vn admin
```

Chép lại mật khẩu hiện ra, đăng nhập ở <http://localhost:8080/quan-tri>.

Quên mật khẩu sau này thì đặt lại bằng:

```powershell
docker compose exec app php database/make-admin.php --reset-password admin@vineyewear.vn
```

## Xem/sửa database

- phpMyAdmin: <http://localhost:8081> — server `db`, user `root`, mật khẩu
  `root_pass_doi_di` (đổi trong `docker-compose.yml` nếu cần, xem mục bên dưới).
- Hoặc dùng client DB bất kỳ trên máy, nối `127.0.0.1:3306`, database
  `vin_eyewear`, user `vin_eyewear` / mật khẩu `vin_eyewear_pass`.

## Lệnh thường dùng

```powershell
docker compose logs -f app        # xem log Apache/PHP
docker compose exec app bash      # vào shell trong container app
docker compose restart app        # khởi động lại sau khi sửa Dockerfile/docker/*
docker compose down               # dừng, GIỮ dữ liệu (volume vẫn còn)
docker compose down -v            # dừng và XOÁ SẠCH database + storage/uploads
```

## Đổi mật khẩu / cổng mặc định

Giá trị mẫu (`vin_eyewear_pass`, `root_pass_doi_di`, cổng `8080/8081/3306`)
chỉ dùng cho máy dev cá nhân. Muốn đổi thì sửa trực tiếp trong
`docker-compose.yml` (mục `environment` và `ports` của từng service) rồi:

```powershell
docker compose down -v   # đổi mật khẩu DB thì bắt buộc down -v để tạo lại volume
docker compose up -d --build
```

## Vì sao không dùng biến môi trường của Docker trực tiếp cho PHP?

`core/helpers.php` đọc cấu hình từ **file** `.env` bằng `file()`, cố tình
không đọc `getenv()`/`$_ENV` (tránh rò rỉ biến môi trường giữa các site dùng
chung PHP-FPM trên hosting giá rẻ — xem chú thích trong hàm `env()`).
`docker/entrypoint.sh` vì vậy đóng vai trò giống hệt `database/setup.sh` trên
máy thật: nhận cấu hình từ nơi vận hành (ở đây là `docker-compose.yml`) rồi
ghi xuống `.env` mỗi lần container khởi động.

## Những phần không nằm trong bộ Docker này

- `relay/` (cầu nối SePay chạy trên Render, Node.js) — runtime khác hẳn
  (Node, không phải PHP) và vốn được thiết kế chạy trên Render, không phải
  máy dev. Xem `relay/README.md` nếu cần chạy thử riêng.
- Gửi mail/Zalo/SePay thật — mặc định `MAIL_DRIVER=log` (ghi ra
  `storage/mail/` thay vì gửi đi) và các khoá Zalo/SePay để trống, đúng hành
  vi an toàn mặc định của `.env.example`. Cần thử luồng thật thì khai thêm
  biến tương ứng trong mục `environment` của service `app`.
