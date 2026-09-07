# =============================================================================
# Dockerfile — môi trường PHP + Apache cho Vin Eyewear
#
# Dự án không dùng Composer (không có vendor/), nên image này chỉ cần PHP,
# Apache, và các extension mà mã nguồn thực sự gọi tới:
#
#   pdo_mysql, mysqli  — config/database.php (Database::class dùng PDO)
#   gd                 — tools/thumbnails.php (imagecreatefromjpeg/imagejpeg)
#   curl               — core/Zalo.php, core/GoogleAuth.php, core/SepayRelay.php
#   mbstring, zip       — thao tác chuỗi tiếng Việt / xuất file, phòng khi cần
#
# Image này KHÔNG copy mã nguồn vào — docker-compose.yml bind-mount toàn bộ
# thư mục dự án vào /var/www/html, giống hệt cách XAMPP trỏ htdocs vào đây.
# Sửa file PHP trên máy thấy ngay, không cần build lại image.
# =============================================================================
FROM php:8.2-apache

# Thư viện hệ thống để BIÊN DỊCH các extension ở bước dưới (docker-php-ext-*
# tự tải mã nguồn PHP extension về rồi compile ngay trong image — không phải
# extension nào cũng có sẵn nhị phân). Mỗi dòng ứng với một extension:
#   libpng-dev, libjpeg62-turbo-dev, libfreetype6-dev  -> gd
#   libzip-dev                                          -> zip
#   libcurl4-openssl-dev                                -> curl
#   libonig-dev (Oniguruma)                             -> mbstring — thiếu gói
#     này thì docker-php-ext-install mbstring báo lỗi "oniguruma headers not
#     found" và dừng ngay ở bước configure, chưa kịp compile.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libcurl4-openssl-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli gd curl mbstring zip

# rewrite: bắt buộc — toàn bộ định tuyến của app đi qua RewriteRule trong .htaccess.
# headers: bắt buộc — khối "3. HEADER BẢO MẬT" trong .htaccess dùng mod_headers.
RUN a2enmod rewrite headers

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-vin-eyewear.ini
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint-vin.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-vin.sh

WORKDIR /var/www/html

ENTRYPOINT ["docker-entrypoint-vin.sh"]
CMD ["apache2-foreground"]
