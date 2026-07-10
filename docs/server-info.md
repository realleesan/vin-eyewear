# THÔNG TIN SERVER & DEPLOYMENT

## ⚠️ CẢNH BÁO BẢO MẬT
File này chứa thông tin nhạy cảm về đăng nhập server và GitHub. 
**CHỈ DÀNH CHO TEAM PHÁT TRIỂN VIN EYEWEAR.**
KHÔNG CHIA SẺ BÊN NGOÀI TEAM.

---

## HOSTING INFORMATION

### InfinityFree Hosting

**URL:** https://cpanel.infinityfree.com/login.php

**Thông tin đăng nhập:**
- **Username:** if0_42377950
- **Password:** 8eOO5cNS8DqXjwF

**Domain tạm:** vreyewear.gt.tc

**Chi tiết cấu hình:**
- **Control Panel:** VistaCP
- **FTP Server:** ftpupload.net
- **MySQL Server:** sqlXXX.infinityfree.com (xem trong cpanel)
- **PHP Version:** 8.0 hoặc cao hơn
- **Database:** Tạo qua cpanel, thông tin connection trong file `config/database.php`

### Cách truy cập

1. **FTP/SFTP:**
   - Host: ftpupload.net
   - Username: if0_42377950
   - Password: 8eOO5cNS8DqXjwF
   - Port: 21 (FTP) hoặc 22 (SFTP)
   - Directory: /htdocs/

2. **Cpanel:**
   - Truy cập: https://cpanel.infinityfree.com/login.php
   - Login với username/password ở trên

3. **phpMyAdmin:**
   - Truy cập qua Cpanel → MySQL Databases → phpMyAdmin
   - Hoặc direct link (nếu có)

---

## GITHUB INFORMATION

### Repository

**Repository URL:** https://github.com/realleesan/vin-eyewear.git

**Clone command:**
```bash
git clone https://github.com/realleesan/vin-eyewear.git
```

**Thông tin tài khoản GitHub:**
- **Owner:** realleesan
- **Repository:** vin-eyewear
- **Branch chính:** main

### Quy trình làm việc với GitHub

1. **Clone repository:**
```bash
git clone https://github.com/realleesan/vin-eyewear.git
cd vin-eyewear
```

2. **Pull latest changes:**
```bash
git pull origin main
```

3. **Create new branch:**
```bash
git checkout -b feature/ten-feature
```

4. **Commit changes:**
```bash
git add .
git commit -m "Mô tả changes"
```

5. **Push to GitHub:**
```bash
git push origin feature/ten-feature
```

6. **Create Pull Request:**
   - Vào GitHub repository
   - Click "Pull Requests" → "New Pull Request"
   - Chọn branch feature → main
   - Điền mô tả và tạo PR

---

## DEPLOYMENT PROCESS

### Cách 1: Deploy qua FTP/SFTP

1. **Build/Prepare files locally**
   - Đảm bảo code đã test
   - Run `composer install` nếu cần
   - Minify CSS/JS nếu cần

2. **Upload qua FTP:**
   - Kết nối FTP client (FileZilla, WinSCP, etc.)
   - Upload files vào thư mục `/htdocs/`
   - **LƯU Ý:** Không upload thư mục `.git/`

3. **Cấu hình trên server:**
   - Copy `.env.example` → `.env`
   - Chỉnh sửa thông tin database trong `.env`
   - Import database nếu cần
   - Set quyền cho thư mục uploads: `chmod 755 assets/uploads/`

4. **Test:**
   - Truy cập domain: vreyewear.gt.tc
   - Test các chức năng chính

### Cách 2: Deploy qua Git trên server (nếu server hỗ trợ)

1. **SSH vào server** (nếu có)
2. **Clone repository:**
```bash
cd /htdocs/
git clone https://github.com/realleesan/vin-eyewear.git .
```

3. **Pull changes:**
```bash
git pull origin main
```

4. **Cấu hình environment**
5. **Test**

---

## DATABASE INFORMATION

### Local Development

**Thông tin database local (ví dụ):**
- Host: localhost
- Username: root
- Password: (để trống)
- Database: vin_eyewear_db
- Port: 3306

### Production (InfinityFree)

**Thông tin database production:**
- Xem trong Cpanel → MySQL Databases
- Thông tin connection:
  - Host: sqlXXX.infinityfree.com (XXX thay bằng số server)
  - Username: if0_42377950
  - Password: (password database, khác password hosting)
  - Database: if0_42377950_vin_eyewear
  - Port: 3306

**Lưu ý:**
- Password database thường khác password hosting
- Tạo database mới qua Cpanel → MySQL Databases
- Import database qua phpMyAdmin

---

## BACKUP STRATEGY

### Database Backup

**Local:**
```bash
mysqldump -u root -p vin_eyewear_db > backup_$(date +%Y%m%d).sql
```

**Production:**
- Dùng phpMyAdmin → Export
- Hoặc Cpanel → Backups

### Files Backup

**Local:**
```bash
# Backup toàn bộ project
tar -czf vin_eyewear_backup_$(date +%Y%m%d).tar.gz .
```

**Production:**
- Download qua FTP
- Hoặc dùng Cpanel → Backups

---

## TROUBLESHOOTING

### Common Issues

**1. 500 Internal Server Error:**
- Check file `.htaccess`
- Check error log: `/htdocs/error_log`
- Check PHP version compatibility

**2. Database Connection Error:**
- Verify database credentials in `config/database.php`
- Check if database exists
- Check if MySQL server is running

**3. Permission Denied:**
- Set correct permissions: `chmod 755` for directories, `chmod 644` for files
- Check ownership: `chown -R user:group`

**4. White Screen of Death:**
- Enable error reporting in PHP
- Check PHP error log
- Check syntax errors in PHP files

---

## CONTACT

Nếu gặp vấn đề với server hoặc GitHub:
- **Techlead:** Nhật
- **BA/Dev:** Hoàng Nam

---

## CẬP NHẬT

**Last updated:** 11/07/2026
**Updated by:** AI Assistant

**Lưu ý:** Thông tin này có thể thay đổi, cập nhật thường xuyên khi có thay đổi cấu hình.
