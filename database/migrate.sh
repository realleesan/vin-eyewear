#!/usr/bin/env bash
#
# database/migrate.sh — áp các file trong database/migrations/ lên CSDL ĐANG CHẠY.
#
#     sudo bash database/migrate.sh            # áp những file còn thiếu
#     sudo bash database/migrate.sh --status    # chỉ liệt kê, KHÔNG đụng vào DB
#
# ─────────────────────────────────────────────────────────────────────────────
# VÌ SAO CẦN FILE NÀY, TRONG KHI ĐÃ CÓ setup.sh
#
# setup.sh chỉ nạp database/schema.sql, và CHỈ nạp khi database còn trống. Gặp
# database đã có bảng thì nó vào chế độ sửa chữa và bỏ qua schema — đúng, vì
# schema.sql mở đầu bằng DROP TABLE cho cả 22 bảng.
#
# Hệ quả: một máy cài từ bản schema cũ rồi `git pull` sẽ KHÔNG bao giờ nhận
# được bảng và cột mới, mà cũng không có gì báo. Nó chỉ lộ ra khi mở một trang
# chạm tới bảng thiếu và nhận 500 — đúng cái đã xảy ra với trang tài khoản
# (bảng `user_vouchers` và `addresses` không tồn tại).
#
# File này lấp đúng khoảng đó: áp từng file migration một, ghi sổ lại, chạy
# lại bao nhiêu lần cũng không hỏng gì.
# ─────────────────────────────────────────────────────────────────────────────
# HAI CƠ CHẾ CHỐNG ÁP HAI LẦN
#
# Cần cả hai, vì chúng chặn hai tình huống khác nhau:
#
#   1. SỔ GHI (bảng `schema_migrations`). Chặn việc chạy lại chính script này.
#   2. CỘT MỐC (sentinel). Chặn việc áp lại file mà ai đó đã chạy TAY từ trước,
#      hồi chưa có sổ. Mỗi migration khai một thứ mà chỉ nó tạo ra; thứ đó có
#      sẵn nghĩa là file đã chạy rồi, script chỉ ghi sổ chứ không chạy lại.
#
# Không có cơ chế 2 thì trên máy hiện tại script sẽ chết ngay file đầu tiên:
# `2026-08-14` thêm UNIQUE KEY `uq_profiles_phone`, mà khoá đó đã tồn tại —
# ALTER lần hai đổ "Duplicate key name". Ba trong bảy file không có
# IF NOT EXISTS nên đây không phải phòng xa.
# ─────────────────────────────────────────────────────────────────────────────
# THỨ TỰ TRONG MIGRATIONS[] LÀ THỨ TỰ CHẠY — KHÔNG PHẢI THỨ TỰ TÊN FILE
#
# `2026-08-16-gio-hang-ma-giam-gia` chạy ALTER TABLE `vouchers`, mà bảng đó do
# `2026-08-16-trang-tai-khoan` tạo. Xếp theo alphabet thì "gio-hang" đứng trước
# "trang-tai-khoan" và ALTER đổ "Table doesn't exist". Chính đầu file gio-hang
# cũng ghi "CHẠY FILE ĐÓ TRƯỚC".
#
# Thêm migration mới thì THÊM MỘT DÒNG vào cuối mảng dưới đây. File nằm trong
# thư mục mà không có trong mảng sẽ bị script báo và bỏ qua, chứ không tự đoán
# chỗ chèn.
# ─────────────────────────────────────────────────────────────────────────────
#
# Vì sao cần sudo: tài khoản MySQL của ứng dụng chỉ có SELECT/INSERT/UPDATE/
# DELETE (setup.sh cố ý không cấp CREATE/ALTER — một lỗ SQL injection lọt lưới
# cũng không DROP được bảng nào). Đổi cấu trúc bảng phải mượn quyền root, mà
# trên Ubuntu root của MySQL dùng plugin auth_socket nên chỉ vào được khi tiến
# trình chạy dưới quyền root của hệ điều hành.

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MIG_DIR="${ROOT_DIR}/database/migrations"

STATUS_ONLY=0
[[ "${1:-}" == "--status" ]] && STATUS_ONLY=1

if [[ -n "${1:-}" && "${1}" != "--status" ]]; then
    echo "Dùng: sudo bash database/migrate.sh [--status]" >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Bảng migration: "tên file|loại cột mốc|bảng|tên"
#
#   table   -> đã có BẢNG <bảng> chưa
#   column  -> bảng <bảng> đã có CỘT <tên> chưa
#   index   -> bảng <bảng> đã có CHỈ MỤC <tên> chưa
#   data    -> KHÔNG có cột mốc; file chỉ đổi DỮ LIỆU và chạy lại được nhiều lần
#
# Cột mốc phải là thứ CHỈ file đó tạo ra. Chọn nhầm sang thứ file khác cũng
# tạo thì script sẽ bỏ qua một migration chưa chạy.
# ---------------------------------------------------------------------------
MIGRATIONS=(
    "2026-08-14-dang-nhap-sdt-ghi-nho-quen-mat-khau.sql|index|profiles|uq_profiles_phone"
    "2026-08-15-bo-suu-tap.sql|column|products|collection"
    "2026-08-15-dang-ky-nhan-tin.sql|table|newsletter_subscribers|"
    "2026-08-16-bien-the-va-danh-gia.sql|table|product_variants|"
    "2026-08-16-trang-tai-khoan.sql|table|addresses|"
    "2026-08-16-gio-hang-ma-giam-gia.sql|column|orders|discount"
    "2026-08-16-thanh-toan-chon-co-so.sql|column|orders|store_id"
    "2026-08-17-trang-thai-thanh-toan.sql|column|orders|payment_status"
    "2026-08-18-cat-trong-theo-so-do.sql|column|order_items|lens_id"
    # Cột mốc là `updated_at` chứ KHÔNG phải `slot_lock`: file 2026-08-19 xoá
    # slot_lock đi, lấy cột đó làm mốc thì sau đấy script tưởng file này chưa
    # chạy và áp lại từ đầu.
    "2026-08-18-doi-huy-lich-hen.sql|column|appointments|updated_at"
    "2026-08-19-khoa-khung-gio-cho-mariadb.sql|column|appointments|active_slot"
    "2026-08-19-so-dia-chi-tach-phuong-tinh.sql|column|addresses|province_code"
    "2026-08-19-dang-ky-khong-email-va-google.sql|column|users|google_id"
    # File này chỉ NỚI một cột sẵn có, không tạo ra bảng/cột/khoá nào mới, nên
    # không có thứ gì để lấy làm cột mốc theo ba kiểu trên. Dùng kiểu 'coltype':
    # mốc chính là kiểu mới của cột.
    "2026-08-20-so-do-tung-mat.sql|coltype|order_items|prescription=varchar(255)"
    # Chỉ XOÁ 5 dòng dữ liệu mẫu, không tạo ra bảng/cột/khoá nào để làm mốc.
    # Kiểu 'data': chỉ sổ ghi chặn chạy lại, mà chạy lại cũng không hại gì.
    "2026-08-20-bo-san-pham-mau.sql|data||"
    "2026-08-21-kinh-dang-deo.sql|column|prescriptions|wear_lens_type"
    "2026-08-22-bang-gia-trong.sql|table|lens_prices|"
    # File này XOÁ một khoá và một cột, không tạo ra thứ gì để làm mốc. Kiểu
    # 'data': chỉ sổ ghi chặn chạy lại. Chạy lại cũng chỉ báo "check that
    # column/key exists" rồi dừng, không hỏng dữ liệu.
    "2026-08-22-bo-gioi-han-khung-gio.sql|data||"
    "2026-08-22-dat-coc-cat-trong.sql|column|orders|deposit_amount"
    "2026-08-22-sepay-doi-soat.sql|table|sepay_transactions|"
    "2026-08-22-ma-thuong-chuyen-du.sql|column|vouchers|is_reward"
    # Chỉ NỚI `time_slot` từ NOT NULL sang NULL. Kiểu cột giữ nguyên varchar(20)
    # nên 'coltype' cũng không phân biệt được trước/sau — dùng 'colnull', mốc là
    # chính tính cho-phép-rỗng của cột.
    "2026-08-25-bo-khung-gio-khoi-form-khach.sql|colnull|appointments|time_slot=YES"
    "2026-08-25-dong-y-dieu-khoan.sql|column|users|terms_accepted_at"
    # File này XOÁ một cột, không tạo ra thứ gì để làm mốc. Kiểu 'data': chỉ sổ
    # ghi chặn chạy lại, mà bản thân file đã tự kiểm trước khi drop nên chạy
    # lại cũng chỉ in ra một dòng "đã bỏ, bỏ qua".
    "2026-08-25-bo-han-cot-khung-gio.sql|data||"
    "2026-08-25-bang-bo-suu-tap.sql|table|collections|"
    # Bỏ hẳn tính năng sự kiện: file này XOÁ bảng `events`, không tạo ra thứ gì
    # để làm mốc. Kiểu 'data' — DROP TABLE IF EXISTS chạy lại bao nhiêu lần
    # cũng ra cùng một kết quả.
    "2026-08-26-bo-su-kien.sql|data||"
    # Module Khách hàng. Mốc là bảng `customer_prescriptions` — bảng đầu tiên
    # file đó tạo ra, và không có gì khác tạo ra nó.
    "2026-08-26-module-khach-hang.sql|table|customer_prescriptions|"
    # Bỏ trạng thái liên hệ, đẩy sang Zalo CSKH. Mốc là cột `zalo_sent_at` —
    # cột này thêm vào, còn `status` thì bị xoá, nên KHÔNG lấy 'status' làm mốc
    # được (kiểu 'column' sẽ báo "chưa áp" mãi mãi sau khi cột đã biến mất).
    "2026-08-26-lien-he-qua-zalo.sql|column|contact_requests|zalo_sent_at"
    # Bỏ hẳn cột `contact_requests`.`status`. File chỉ XOÁ, không tạo ra thứ gì
    # để làm cột mốc — kiểu 'data', chỉ sổ ghi chặn chạy lại. Mà chạy lại cũng
    # vô hại: cả ba bước đều hỏi information_schema trước.
    "2026-08-27-bo-cot-status-lien-he.sql|data||"
    # Gói chiết suất rời config xuống CSDL. Mốc là chính bảng `lens_packages` —
    # không có gì khác tạo ra nó.
    "2026-08-27-bang-goi-trong.sql|table|lens_packages|"
    # Trang chi tiết bộ sưu tập. Mốc là cột `story` — cột duy nhất file đó thêm.
    "2026-08-27-bo-suu-tap-trang-chi-tiet.sql|column|collections|story"
    # Khung thông tin ba lớp của trang chi tiết bộ sưu tập: 43 cột mới trên ba
    # bảng, cộng bảng `collection_faqs`. Mốc là BẢNG đó — nó là thứ duy nhất
    # trong file chỉ có thể do file này tạo ra, còn 43 cột kia thì mỗi cột đều
    # có thể bị ai đó thêm tay lẻ tẻ, không cột nào đại diện cho cả file.
    "2026-08-27-bo-suu-tap-khung-ba-lop.sql|table|collection_faqs|"
    # Chữ trên trang do cửa hàng tự sửa. Mốc là chính bảng `site_texts`.
    "2026-08-27-noi-dung-trang-tong-quan.sql|table|site_texts|"
    # Bộ sưu tập chuyển từ một ảnh bìa sang một bộ ảnh. Mốc là cột `images`.
    "2026-08-28-bo-suu-tap-nhieu-anh.sql|column|collections|images"
    # Bỏ hẳn phần ghi chú nội bộ: file này XOÁ bảng `customer_notes`, không tạo
    # ra thứ gì để làm mốc. Kiểu 'data' — DROP TABLE IF EXISTS chạy lại bao
    # nhiêu lần cũng ra cùng một kết quả.
    "2026-08-28-bo-bang-ghi-chu-noi-bo.sql|data||"
    # Lấp `collections`.`sort_order` bằng thứ tự đang trưng bày, để nút ↑↓ ở khu
    # quản trị có chỗ bấu víu mà thứ tự ngoài mặt tiền không xê dịch lúc deploy.
    # Kiểu 'data': cột đã có sẵn từ lược đồ gốc nên không có mốc nào để tra, và
    # phép đánh số dựa trên `launched_at`/`name` chứ không dựa vào chính
    # `sort_order` — chạy lại ra cùng kết quả.
    "2026-08-28-bo-suu-tap-thu-tu-trung-bay.sql|data||"
    # Phản hồi công khai của cửa hàng dưới mỗi đánh giá. Mốc là cột `reply`.
    "2026-08-28-phan-hoi-danh-gia.sql|column|reviews|reply"
    # Form thêm/sửa sản phẩm dựng lại theo bản vẽ: 20 cột mới trên `products`,
    # 4 cột trên `product_variants`. Mốc là `publish_status` — cột đầu tiên
    # trong câu ALTER và không có gì khác tạo ra nó.
    "2026-08-29-san-pham-theo-ban-ve.sql|column|products|publish_status"
    # Danh sách khách chờ hàng về. Mốc là chính bảng `stock_waitlist` — không
    # có gì khác tạo ra nó, và file cũng chỉ tạo đúng một bảng.
    "2026-08-29-danh-sach-cho-hang.sql|table|stock_waitlist|"
    # Bốn danh sách thuộc tính tròng rời config xuống CSDL để sửa được từ khu
    # quản trị. Mốc là chính bảng `lens_options` — không có gì khác tạo ra nó.
    "2026-08-30-thuoc-tinh-trong-do-quan-tri-quan-ly.sql|table|lens_options|"
)

# ---------------------------------------------------------------------------
# Kiểm tra điều kiện chạy
# ---------------------------------------------------------------------------
command -v mysql >/dev/null 2>&1 || { echo "✗ Thiếu lệnh mysql." >&2; exit 1; }

if [[ ! -f "${ROOT_DIR}/.env" ]]; then
    echo "✗ Chưa có .env — chạy 'sudo bash database/setup.sh' trước." >&2
    exit 1
fi

# Đọc tên database từ .env. cut -d= -f2- chứ không phải -f2: mật khẩu và một
# số giá trị khác có thể chứa dấu '=' (ở đây là tên DB nên hiếm, nhưng dùng
# chung một lối đọc cho cả file thì không phải nhớ ngoại lệ).
DB_NAME="$(grep -E '^DB_NAME=' "${ROOT_DIR}/.env" | head -1 | cut -d= -f2- | tr -d '"'"'"' \r')"
DB_NAME="${DB_NAME:-vin_eyewear}"

if ! mysql -e 'SELECT 1;' >/dev/null 2>&1; then
    echo "✗ Không kết nối được MySQL bằng quyền root." >&2
    echo "  Script này phải chạy qua sudo (root dùng plugin auth_socket)." >&2
    exit 1
fi

if ! mysql -N -B -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';" | grep -q .; then
    echo "✗ Không thấy database '${DB_NAME}'. Chạy 'sudo bash database/setup.sh' trước." >&2
    exit 1
fi

echo "→ Database: ${DB_NAME}"

# ---------------------------------------------------------------------------
# Sổ ghi
# ---------------------------------------------------------------------------
if [[ "${STATUS_ONLY}" -eq 0 ]]; then
    mysql --database="${DB_NAME}" <<'SQL'
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `filename`   VARCHAR(191) NOT NULL,
    `applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
fi

# Đã ghi sổ chưa? (bảng có thể chưa tồn tại ở chế độ --status)
in_ledger() {
    local n
    n="$(mysql -N -B --database="${DB_NAME}" -e \
        "SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='schema_migrations';")"
    [[ "${n}" == "0" ]] && return 1

    n="$(mysql -N -B --database="${DB_NAME}" -e \
        "SELECT COUNT(*) FROM \`schema_migrations\` WHERE filename='${1}';")"
    [[ "${n}" != "0" ]]
}

# Cột mốc đã tồn tại chưa?
sentinel_exists() {
    local kind="$1" table="$2" name="$3" n
    case "${kind}" in
        table)
            n="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='${table}';")" ;;
        column)
            n="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='${table}'
                   AND COLUMN_NAME='${name}';")" ;;
        index)
            n="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='${table}'
                   AND INDEX_NAME='${name}';")" ;;
        coltype)
            # name có dạng "ten_cot=kieu_mong_doi", ví dụ prescription=varchar(255).
            # Dành cho migration chỉ ĐỔI KIỂU một cột sẵn có: cột thì vốn đã tồn
            # tại từ trước nên kiểu 'column' luôn báo "đã áp" và file không bao
            # giờ chạy.
            local col="${name%%=*}" want="${name#*=}"
            n="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='${table}'
                   AND COLUMN_NAME='${col}' AND COLUMN_TYPE='${want}';")" ;;
        colnull)
            # name có dạng "ten_cot=YES" hoặc "ten_cot=NO" — mốc là cột đó có cho
            # phép NULL hay không. Dành cho migration chỉ NỚI/SIẾT ràng buộc NULL:
            # 'column' thì cột vốn đã có nên luôn báo "đã áp", còn 'coltype' thì
            # COLUMN_TYPE không đổi (varchar(20) trước và sau vẫn thế) nên cũng
            # không phân biệt được.
            local ncol="${name%%=*}" nwant="${name#*=}"
            n="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='${table}'
                   AND COLUMN_NAME='${ncol}' AND IS_NULLABLE='${nwant}';")" ;;
        data)
            # File đổi dữ liệu: không có mốc nào để tra. Luôn trả "chưa có" để
            # quyết định hoàn toàn thuộc về sổ ghi. An toàn vì các file loại này
            # phải viết sao cho chạy lại không đổi kết quả.
            return 1 ;;
        *)
            echo "✗ Loại cột mốc lạ: ${kind}" >&2; exit 1 ;;
    esac
    [[ "${n}" != "0" ]]
}

# ---------------------------------------------------------------------------
# Chạy
# ---------------------------------------------------------------------------
applied=0
skipped=0

for row in "${MIGRATIONS[@]}"; do
    IFS='|' read -r file kind table name <<< "${row}"
    path="${MIG_DIR}/${file}"

    if [[ ! -f "${path}" ]]; then
        printf '  %-52s ✗ không thấy file\n' "${file}"
        exit 1
    fi

    if in_ledger "${file}"; then
        printf '  %-52s · đã ghi sổ\n' "${file}"
        skipped=$((skipped + 1))
        continue
    fi

    if sentinel_exists "${kind}" "${table}" "${name}"; then
        printf '  %-52s · đã áp từ trước, ghi sổ\n' "${file}"
        [[ "${STATUS_ONLY}" -eq 0 ]] && mysql --database="${DB_NAME}" -e \
            "INSERT IGNORE INTO \`schema_migrations\` (filename) VALUES ('${file}');"
        skipped=$((skipped + 1))
        continue
    fi

    if [[ "${STATUS_ONLY}" -eq 1 ]]; then
        printf '  %-52s → CHƯA ÁP\n' "${file}"
        applied=$((applied + 1))
        continue
    fi

    printf '  %-52s → đang áp…' "${file}"

    # Mỗi file một lần gọi mysql: client tự tách câu lệnh đúng cách, không
    # phải tự cắt chuỗi theo dấu ';' (dấu đó còn nằm trong chú thích và trong
    # chuỗi ký tự).
    if mysql --database="${DB_NAME}" < "${path}"; then
        mysql --database="${DB_NAME}" -e \
            "INSERT INTO \`schema_migrations\` (filename) VALUES ('${file}');"
        echo " xong"
        applied=$((applied + 1))
    else
        echo " LỖI"
        echo >&2
        echo "  Dừng lại tại '${file}'. Các file trước đã áp xong và đã ghi sổ," >&2
        echo "  nên sửa xong lỗi thì chạy lại script này, nó đi tiếp từ đây." >&2
        exit 1
    fi
done

# ---------------------------------------------------------------------------
# File nằm trong thư mục nhưng chưa khai trong MIGRATIONS[]
# ---------------------------------------------------------------------------
for path in "${MIG_DIR}"/*.sql; do
    [[ -e "${path}" ]] || continue
    file="$(basename "${path}")"

    if ! printf '%s\n' "${MIGRATIONS[@]}" | cut -d'|' -f1 | grep -qx "${file}"; then
        echo
        echo "  ⚠  '${file}' chưa khai trong MIGRATIONS[] của script này nên bị bỏ qua."
        echo "     Thêm một dòng vào cuối mảng đó (kèm cột mốc) rồi chạy lại."
    fi
done

echo
if [[ "${STATUS_ONLY}" -eq 1 ]]; then
    echo "✓ ${applied} file chưa áp · ${skipped} file đã xong."
else
    echo "✓ Áp ${applied} file · bỏ qua ${skipped} file đã có."
    echo "  Kiểm lại:  php database/schema-check.php"
fi
