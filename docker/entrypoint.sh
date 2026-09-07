#!/usr/bin/env bash
#
# docker/entrypoint.sh — chạy MỖI LẦN container app khởi động, trước Apache.
#
# Dự án đọc cấu hình từ FILE .env (core/helpers.php:env()), cố tình KHÔNG đọc
# biến môi trường của tiến trình (xem chú thích trong hàm đó). Vì vậy
# docker-compose không thể "chỉ" truyền environment: cho PHP đọc được — script
# này là cầu nối: nhận biến môi trường từ docker-compose.yml rồi GHI xuống
# .env, giống việc database/setup.sh làm trên máy thật, chỉ khác nguồn giá trị.
#
# Idempotent: chạy lại bao nhiêu lần (docker compose restart) cũng an toàn,
# không ghi đè giá trị người dùng đã tự sửa tay trong .env ngoài các khoá dưới
# đây.
set -euo pipefail

cd /var/www/html

# -----------------------------------------------------------------------------
# 1. Đồng bộ .env với biến môi trường của container
# -----------------------------------------------------------------------------
if [[ ! -f .env ]]; then
    if [[ ! -f .env.example ]]; then
        echo "✗ Thiếu cả .env lẫn .env.example — không có gì để dựng cấu hình." >&2
        exit 1
    fi
    echo "→ Chưa có .env, tạo mới từ .env.example…"
    cp .env.example .env
fi

set_env() {
    local key="$1" value="$2"
    if grep -q "^${key}=" .env; then
        # '|' làm dấu phân cách vì giá trị có thể chứa '/' (APP_URL, đường dẫn)
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        echo "${key}=${value}" >> .env
    fi
}

set_env APP_ENV          "${APP_ENV:-local}"
set_env APP_DEBUG         "${APP_DEBUG:-true}"
set_env APP_URL           "${APP_URL:-http://localhost:8080}"
set_env APP_TIMEZONE      "${APP_TIMEZONE:-Asia/Ho_Chi_Minh}"
set_env DB_HOST           "${DB_HOST:-db}"
set_env DB_PORT           "${DB_PORT:-3306}"
set_env DB_NAME           "${DB_NAME:-vin_eyewear}"
set_env DB_USER           "${DB_USER:-vin_eyewear}"
set_env DB_PASS           "${DB_PASS:-vin_eyewear}"
set_env SESSION_LIFETIME  "${SESSION_LIFETIME:-1209600}"
set_env MAIL_DRIVER       "${MAIL_DRIVER:-log}"

# Các khoá tuỳ chọn (Google, Zalo, SePay…) chỉ ghi khi thật sự được truyền vào
# container — để trống thì giữ nguyên giá trị rỗng có sẵn trong .env.example
# thay vì in chữ "  " thừa vào file.
for key in INSTALL_TOKEN GOOGLE_CLIENT_ID GOOGLE_CLIENT_SECRET AUTH_OTP_BYPASS \
           ZALO_APP_ID ZALO_APP_SECRET ZALO_OA_REFRESH_TOKEN ZALO_ZNS_TEMPLATE_OTP \
           SEPAY_ENABLED SEPAY_WEBHOOK_KEY; do
    val="${!key:-}"
    [[ -n "${val}" ]] && set_env "${key}" "${val}"
done

# -----------------------------------------------------------------------------
# 2. Thư mục ghi — nằm trong .gitignore nên không có sẵn khi mới clone repo
#    (storage/) hoặc chỉ có sau khi ai đó tải ảnh lên (assets/uploads/*).
#    core/ImageUploader.php tự sinh .htaccess bên trong khi cần, ở đây chỉ cần
#    thư mục tồn tại và ghi được.
# -----------------------------------------------------------------------------
mkdir -p storage/mail storage/quet storage/zalo \
         assets/uploads/avatars assets/uploads/san-pham \
         assets/uploads/su-kien assets/uploads/bo-suu-tap
chown -R www-data:www-data storage assets/uploads 2>/dev/null || true

# -----------------------------------------------------------------------------
# 3. Chờ MariaDB nhận kết nối trước khi Apache phục vụ request đầu tiên.
#
# Không có bước này, lượt truy cập đầu tiên (thường là chính người vừa gõ
# `docker compose up`) hứng một trang lỗi PDO ngay khi container app khởi
# động nhanh hơn vài giây so với container db.
# -----------------------------------------------------------------------------
DB_HOST_CHECK="${DB_HOST:-db}"
DB_PORT_CHECK="${DB_PORT:-3306}"

echo "→ Chờ database (${DB_HOST_CHECK}:${DB_PORT_CHECK})…"
for _ in $(seq 1 30); do
    if php -r "exit(@fsockopen('${DB_HOST_CHECK}', (int) '${DB_PORT_CHECK}', \$e, \$s, 1) ? 0 : 1);"; then
        echo "✓ Database đã sẵn sàng."
        break
    fi
    sleep 2
done

exec "$@"
