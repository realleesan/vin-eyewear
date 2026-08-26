#!/usr/bin/env bash
#
# database/setup.sh — tạo database, tài khoản MySQL riêng cho dự án, rồi nạp schema.
#
#     sudo bash database/setup.sh              # cài mới, hoặc sửa bản cài hỏng
#     sudo bash database/setup.sh --force      # XOÁ SẠCH rồi cài lại từ đầu
#
# --force luôn đòi xác nhận, không có ngoại lệ: chạy trong terminal thì gõ lại
# tên database, chạy tự động (CI/cron) thì phải đặt sẵn biến môi trường
#     VIN_CONFIRM_DESTROY='XOA vin_eyewear' sudo -E bash database/setup.sh --force
#
# ─────────────────────────────────────────────────────────────────────────────
# HAI CHẾ ĐỘ — script tự chọn, an toàn khi chạy lại bao nhiêu lần cũng được
#
#   Database chưa có bảng nào  ->  CÀI MỚI: nạp schema + dữ liệu mẫu.
#   Database đã có bảng        ->  SỬA CHỮA: KHÔNG đụng tới schema, không xoá
#                                  gì cả. Chỉ bù tài khoản MySQL, .env, admin.
#
# schema.sql mở đầu bằng DROP TABLE cho cả 13 bảng, nên nạp lại nó lên
# database đang chạy sẽ xoá sạch đơn hàng, khách hàng, VÀ cả nội dung site
# (sản phẩm, danh mục, bộ sưu tập, cơ sở — những thứ admin nhập qua trang quản
# trị). Vì vậy chỉ --force mới nạp lại schema, và nó liệt kê từng bảng cùng
# số bản ghi sắp mất trước khi làm.
#
# Quên mật khẩu admin thì KHÔNG cần chạy lại script này. Dùng:
#     php database/make-admin.php --reset-password <email>
# ─────────────────────────────────────────────────────────────────────────────
#
# Vì sao cần sudo: trên Ubuntu, tài khoản MySQL 'root' dùng plugin auth_socket
# — chỉ đăng nhập được khi tiến trình chạy dưới quyền root của hệ điều hành,
# không nhận mật khẩu. Script này mượn quyền đó đúng một lần để tạo ra tài
# khoản 'vin_eyewear' đăng nhập bằng mật khẩu, sau đó ứng dụng dùng tài khoản
# thường ấy — không bao giờ chạy web bằng quyền root của DB.

set -euo pipefail

DB_NAME="vin_eyewear"
DB_USER="vin_eyewear"
ADMIN_EMAIL="admin@vineyewear.vn"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# ---------------------------------------------------------------------------
# Tham số
#
# Từ chối cờ lạ thay vì lặng lẽ bỏ qua. Gõ nhầm `--froce` mà script vẫn chạy
# bình thường sẽ khiến người dùng tưởng đã xoá và cài lại, trong khi thực tế
# nó chỉ chạy chế độ sửa chữa.
# ---------------------------------------------------------------------------
FORCE=0
for arg in "$@"; do
    case "${arg}" in
        --force) FORCE=1 ;;
        -h|--help)
            sed -n '3,25p' "${BASH_SOURCE[0]}" | sed 's/^# \?//'
            exit 0
            ;;
        *)
            echo "Tham số không hiểu: ${arg}" >&2
            echo "Dùng: sudo bash database/setup.sh [--force]" >&2
            exit 1
            ;;
    esac
done

if [[ $EUID -ne 0 ]]; then
    echo "Cần chạy bằng sudo:  sudo bash database/setup.sh" >&2
    exit 1
fi

# Người dùng thật đứng sau sudo — cần để trả quyền sở hữu .env và chạy PHP
# bằng quyền thường thay vì root.
REAL_USER="${SUDO_USER:-$(logname 2>/dev/null || echo root)}"

# ---------------------------------------------------------------------------
# KIỂM TRA ĐIỀU KIỆN TRƯỚC KHI LÀM GÌ
#
# Không có bước này, một máy thiếu MySQL hoặc thiếu extension pdo_mysql sẽ
# chạy được nửa chừng rồi chết với thông báo thô của mysql/PHP — người cài
# không biết mình thiếu gì. Tệ hơn: chốt an toàn ở dưới nuốt mọi lỗi của
# truy vấn đếm bảng thành "0 bảng", nên MySQL không chạy sẽ bị hiểu nhầm
# thành "database trống, cứ cài mới đi".
# ---------------------------------------------------------------------------
MISSING_CMDS=()
for cmd in mysql php openssl sed tr cut; do
    command -v "${cmd}" >/dev/null 2>&1 || MISSING_CMDS+=("${cmd}")
done

if [[ ${#MISSING_CMDS[@]} -gt 0 ]]; then
    echo "✗ Thiếu lệnh: ${MISSING_CMDS[*]}" >&2
    echo "  Cài bằng:  sudo apt install mysql-server php-cli openssl" >&2
    exit 1
fi

# PHP phải có pdo_mysql, nếu không make-admin.php sẽ chết ở bước cuối
if ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);'; then
    echo "✗ PHP thiếu extension pdo_mysql — make-admin.php sẽ không chạy được." >&2
    echo "  Cài bằng:  sudo apt install php-mysql" >&2
    exit 1
fi

# Máy chủ MySQL phải đang chạy VÀ root phải đăng nhập được bằng auth_socket
if ! mysql -e 'SELECT 1;' >/dev/null 2>&1; then
    echo "✗ Không kết nối được MySQL bằng quyền root." >&2
    echo "  Kiểm tra máy chủ có chạy không:  systemctl status mysql" >&2
    echo "  Và script này phải chạy qua sudo (root dùng plugin auth_socket)." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# Sinh chuỗi ngẫu nhiên chỉ gồm chữ và số.
#
# Hai chi tiết ở đây đều là bẫy đã gặp thật, không phải phòng xa:
#
# 1. `tr -dc` (delete complement) GIỮ LẠI đúng tập ký tự cho phép. Dùng
#    `tr -d '/+='` thì ký tự xuống dòng của base64 vẫn lọt qua — một dấu
#    xuống dòng trong mật khẩu sẽ cắt đôi dòng DB_PASS trong .env.
#
# 2. `cut -c1-N` chứ KHÔNG phải `head -c N`. head đóng ống ngay khi đủ N byte,
#    khiến tr nhận SIGPIPE và thoát với mã 141; gặp `set -o pipefail` thì cả
#    script chết lặng lẽ ngay tại đây, để lại database dựng dở. cut đọc hết
#    stdin nên không có chuyện đó. Đầu vào lấy từ openssl (hữu hạn) thay vì
#    /dev/urandom (vô hạn) để cut luôn kết thúc.
# ---------------------------------------------------------------------------
randpass() {
    local n="${1:-24}"

    # n*3 byte -> khoảng 4n ký tự base64, thừa đủ sau khi lọc bỏ '+/='
    LC_ALL=C openssl rand -base64 $(( n * 3 )) | tr -dc 'A-Za-z0-9' | cut -c1-"${n}"
}

# ---------------------------------------------------------------------------
# CHỐT AN TOÀN — chọn giữa CÀI MỚI và SỬA CHỮA
#
# Nguyên tắc: schema.sql CHỈ được nạp khi database chưa có bảng nào, hoặc khi
# người dùng nói rõ --force. Không có đường nào khác dẫn tới DROP TABLE.
#
# Bản trước của script quyết định bằng cách đếm bản ghi ở 4 bảng nghiệp vụ
# (orders/appointments/contact_requests/users) và cố ý BỎ QUA products,
# categories, stores với lý do "schema seed sẵn chúng". Đó là lỗ hổng:
# bốn bảng bị bỏ qua ấy chính là nội dung sống của site — danh mục sản phẩm,
# bộ sưu tập, địa chỉ cơ sở đều do admin nhập qua trang quản trị. Một cửa hàng đã
# dựng xong catalog nhưng chưa phát sinh đơn nào sẽ bị coi là "cài dở" và bị
# xoá sạch.
#
# Sửa bằng cách bỏ hẳn việc đoán xem dữ liệu nào "đáng giữ": đã có bảng thì
# không đụng tới schema, chỉ sửa những thứ còn thiếu (tài khoản MySQL, .env,
# admin). Nhờ vậy chạy lại sau khi hỏng giữa chừng vẫn an toàn, mà không cần
# script phải phân loại đúng-sai về dữ liệu của người dùng.
# ---------------------------------------------------------------------------

# 13 bảng schema.sql phải tạo ra. Dùng để phát hiện schema nạp DỞ — trường hợp
# lần cài trước chết ngay giữa lúc nạp, để lại vài bảng đầu tiên.
EXPECTED_TABLES=(
    users profiles user_roles prescriptions
    categories products stores
    favorites appointments orders order_items contact_requests
)

TABLE_COUNT="$(mysql -N -B -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_NAME}';" 2>/dev/null || echo 0)"

# MODE: install = nạp schema | repair = giữ nguyên dữ liệu, chỉ bù phần thiếu
MODE=install

# ---------------------------------------------------------------------------
# Liệt kê các bảng còn thiếu so với schema đầy đủ.
# ---------------------------------------------------------------------------
missingTables() {
    local present missing=()
    present="$(mysql -N -B -e \
        "SELECT table_name FROM information_schema.tables WHERE table_schema = '${DB_NAME}';" 2>/dev/null || true)"

    local t
    for t in "${EXPECTED_TABLES[@]}"; do
        grep -qx "${t}" <<<"${present}" || missing+=("${t}")
    done

    printf '%s\n' "${missing[@]:-}"
}

# ---------------------------------------------------------------------------
# Tổng số bản ghi trên các bảng ĐANG tồn tại.
#
# Đếm CẢ bảng nội dung (products, categories, stores) — chúng là dữ
# liệu thật do admin nhập qua trang quản trị, không phải "chỉ là dữ liệu mẫu".
# Bảng chưa tồn tại thì bỏ qua thay vì làm hỏng cả câu lệnh.
# ---------------------------------------------------------------------------
countRows() {
    local total=0 t n
    for t in "${EXPECTED_TABLES[@]}"; do
        n="$(mysql -N -B --database="${DB_NAME}" -e "SELECT COUNT(*) FROM \`${t}\`;" 2>/dev/null || echo 0)"
        [[ "${n}" =~ ^[0-9]+$ ]] || n=0
        # total=$((...)) chứ không dùng (( total += n )): biểu thức (( )) trả
        # mã thoát 1 khi kết quả bằng 0, gặp `set -e` là script chết ngang.
        total=$(( total + n ))
    done
    echo "${total}"
}

if [[ "${TABLE_COUNT}" -gt 0 ]]; then
    if [[ "${FORCE}" -eq 1 ]]; then
        # -------------------------------------------------------------------
        # Báo cáo thiệt hại.
        #
        # Duyệt EXPECTED_TABLES chứ KHÔNG gõ tay danh sách bảng. Bản trước
        # liệt kê tay 11 bảng và bỏ sót profiles + user_roles, tức là báo
        # thiếu mất hồ sơ khách hàng và toàn bộ phân quyền — người dùng đọc
        # xong tưởng mất ít hơn thực tế rồi mới đồng ý.
        # -------------------------------------------------------------------
        echo
        echo "  ╔═══════════════════════════════════════════════════════════╗"
        echo "  ║  ⚠  XOÁ VĨNH VIỄN — KHÔNG THỂ HOÀN TÁC                    ║"
        echo "  ╚═══════════════════════════════════════════════════════════╝"
        echo
        echo "     schema.sql sẽ DROP toàn bộ ${#EXPECTED_TABLES[@]} bảng dưới đây"
        echo "     của database '${DB_NAME}' rồi tạo lại từ đầu:"
        echo

        FORCE_TOTAL=0
        FORCE_UNKNOWN=0
        for t in "${EXPECTED_TABLES[@]}"; do
            n="$(mysql -N -B --database="${DB_NAME}" -e "SELECT COUNT(*) FROM \`${t}\`;" 2>/dev/null || echo 'X')"
            if [[ "${n}" =~ ^[0-9]+$ ]]; then
                FORCE_TOTAL=$(( FORCE_TOTAL + n ))
                # Đánh dấu bảng còn dữ liệu để mắt bắt được ngay giữa danh sách dài
                printf '       %-20s %8s bản ghi%s\n' "${t}" "${n}" \
                       "$( [[ "${n}" -gt 0 ]] && echo '   ←' || true )"
            else
                FORCE_UNKNOWN=$(( FORCE_UNKNOWN + 1 ))
                printf '       %-20s %8s (chưa có bảng)\n' "${t}" "—"
            fi
        done

        echo "       ────────────────────────────────────────────"
        printf '       %-20s %8s bản ghi sẽ MẤT\n' "TỔNG CỘNG" "${FORCE_TOTAL}"
        [[ "${FORCE_UNKNOWN}" -gt 0 ]] && \
            echo "       (${FORCE_UNKNOWN} bảng chưa tồn tại nên không đếm được)"

        echo
        echo "     Sao lưu trước nếu chưa:"
        echo "         mysqldump ${DB_NAME} > sao-luu-\$(date +%F).sql"
        echo

        # -------------------------------------------------------------------
        # Xác nhận — LUÔN LUÔN bắt buộc, không có ngoại lệ.
        #
        # Bản trước đếm ngược 10 giây khi không chạy trong terminal rồi tự đi
        # tiếp. Đó không phải xác nhận: trong CI, cron hay script gọi lồng
        # nhau thì chẳng có ai ngồi xem để bấm Ctrl+C, nên `--force` xoá sạch
        # database mà không một người nào phải đồng ý.
        #
        # Không bao giờ được có đường nào xoá dữ liệu chỉ vì HẾT GIỜ CHỜ.
        # Hai cách xác nhận, đều đòi một hành động cố ý:
        #   - có bàn phím  -> gõ lại đúng tên database
        #   - không có     -> đặt sẵn biến môi trường VIN_CONFIRM_DESTROY
        # -------------------------------------------------------------------
        CONFIRM_PHRASE="XOA ${DB_NAME}"

        if [[ -t 0 ]]; then
            printf '     Gõ chính xác  %s  để xác nhận: ' "${CONFIRM_PHRASE}"
            read -r ANSWER || ANSWER=''

            if [[ "${ANSWER}" != "${CONFIRM_PHRASE}" ]]; then
                echo
                echo "     Đã huỷ — không có gì bị thay đổi."
                exit 1
            fi
        elif [[ "${VIN_CONFIRM_DESTROY:-}" == "${CONFIRM_PHRASE}" ]]; then
            echo "     Đã xác nhận qua VIN_CONFIRM_DESTROY."
        else
            cat >&2 <<MSG

     ✗ DỪNG LẠI — không chạy trong terminal nên không hỏi xác nhận được.

       Chưa xoá gì cả.

       Nếu ĐÚNG là bạn muốn xoá ${FORCE_TOTAL} bản ghi ở trên trong một
       phiên tự động, hãy nói rõ bằng biến môi trường:

           VIN_CONFIRM_DESTROY='${CONFIRM_PHRASE}' \\
               sudo -E bash database/setup.sh --force

       (sudo cần cờ -E để giữ lại biến môi trường.)

MSG
            exit 1
        fi
        echo
    else
        # Bảng có tồn tại, nhưng ĐỦ hay chưa lại là chuyện khác. Lần cài trước
        # có thể đã chết ngay giữa lúc nạp schema, để lại vài bảng đầu.
        MISSING="$(missingTables)"

        if [[ -z "${MISSING}" ]]; then
            # ----- Schema đầy đủ: sửa chữa thật, không đụng dữ liệu -----
            MODE=repair
            cat <<MSG

  • Database '${DB_NAME}' có đủ ${#EXPECTED_TABLES[@]} bảng — CHẾ ĐỘ SỬA CHỮA.

    KHÔNG nạp lại schema, KHÔNG xoá bất cứ dữ liệu nào.
    Chỉ bù lại những phần có thể còn thiếu:
      - tài khoản MySQL của ứng dụng và quyền của nó
      - file .env
      - tài khoản quản trị

    Lưu ý: script chỉ đối chiếu TÊN BẢNG, không kiểm tra từng cột. Nếu
    schema.sql đã đổi (thêm cột, đổi kiểu) từ lần cài trước, những thay đổi
    đó KHÔNG được áp dụng ở đây — phải tự chạy câu ALTER TABLE tương ứng.

MSG
        else
            # ----- Schema nạp dở -----
            #
            # Đây là trường hợp bản trước của script xử lý sai: nó thấy "có
            # bảng" là chuyển sang sửa chữa và bỏ qua schema, nên số bảng còn
            # thiếu không bao giờ được tạo — bản cài kẹt hỏng vĩnh viễn, còn
            # người dùng chỉ nhận được lỗi khó hiểu từ make-admin.php.
            #
            # Cách xử lý phụ thuộc vào việc có dữ liệu hay không.
            EXISTING_ROWS="$(countRows)"
            MISSING_LIST="$(tr '\n' ' ' <<<"${MISSING}")"

            if [[ "${EXISTING_ROWS}" -eq 0 ]]; then
                # Không có gì để mất -> nạp lại schema cho trọn vẹn.
                MODE=install
                cat <<MSG

  • Phát hiện schema nạp DỞ: thiếu ${MISSING_LIST}
    Các bảng đang có đều rỗng (0 bản ghi) nên không có gì để mất.

    → Nạp lại schema cho đầy đủ.

MSG
            else
                # CÓ dữ liệu và schema lại thiếu bảng. Không tự động xoá:
                # nạp lại schema sẽ DROP luôn số dữ liệu đang còn đó.
                cat >&2 <<MSG

  ✗ DỪNG LẠI — schema nạp dở NHƯNG database đang có dữ liệu.

    Bảng còn thiếu : ${MISSING_LIST}
    Bản ghi hiện có: ${EXISTING_ROWS}

    Script không tự nạp lại schema, vì làm vậy sẽ DROP toàn bộ bảng và
    xoá mất ${EXISTING_ROWS} bản ghi đó.

    Hãy chọn một trong hai:

      1. Giữ dữ liệu — tạo tay các bảng còn thiếu, lấy định nghĩa trong
         database/schema.sql rồi chạy lại script này.

      2. Bỏ dữ liệu — sao lưu rồi cài lại từ đầu:
             mysqldump ${DB_NAME} > sao-luu-\$(date +%F).sql
             sudo bash database/setup.sh --force

MSG
                exit 1
            fi
        fi
    fi
fi

# ---------------------------------------------------------------------------
# 1. Database + tài khoản MySQL của ứng dụng
# ---------------------------------------------------------------------------
DB_PASS="$(randpass 24)"

echo "→ Tạo database và tài khoản MySQL '${DB_USER}'…"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';

-- THU HỒI TOÀN BỘ quyền cũ trước khi cấp lại.
--
-- Bắt buộc phải có: GRANT chỉ THÊM quyền, không bao giờ bớt. Bản script trước
-- của dự án từng cấp CREATE, DROP, INDEX, ALTER, REFERENCES; máy nào đã chạy
-- bản đó mà giờ chỉ chạy GRANT bên dưới thì vẫn giữ nguyên đống quyền cũ —
-- việc siết xuống 4 quyền DML sẽ không có tác dụng gì.
--
-- Dòng này chạy được vì CREATE USER IF NOT EXISTS ở trên đã bảo đảm user tồn tại.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${DB_USER}'@'localhost';

-- Chỉ cấp quyền trên đúng database của dự án, không cấp toàn server.
-- Không có CREATE/DROP/ALTER: ứng dụng web chỉ đọc-ghi dữ liệu. Việc đổi cấu
-- trúc bảng là của người quản trị chạy schema bằng tay dưới quyền root.
-- Nhờ vậy, một lỗ SQL injection lọt lưới cũng không DROP được bảng nào.
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------------------
# Ghi .env NGAY tại đây, không đợi các bước sau.
#
# Mật khẩu MySQL vừa bị xoay ở lệnh ALTER USER trên. Nếu để việc ghi .env ở
# cuối script mà một bước giữa chừng (nạp schema, tạo admin) hỏng, mật khẩu
# mới chỉ còn tồn tại trong bộ nhớ của shell vừa chết: .env giữ mật khẩu cũ
# đã hết hiệu lực, và không ai lấy lại được mật khẩu mới. Ghi ngay khi vừa
# đặt thì hỏng ở bước nào cũng vẫn kết nối được.
# ---------------------------------------------------------------------------
echo "→ Ghi thông số kết nối vào .env…"
if [[ ! -f "${ROOT_DIR}/.env" ]]; then
    cp "${ROOT_DIR}/.env.example" "${ROOT_DIR}/.env"
fi

sed -i "s|^DB_NAME=.*|DB_NAME=${DB_NAME}|" "${ROOT_DIR}/.env"
sed -i "s|^DB_USER=.*|DB_USER=${DB_USER}|" "${ROOT_DIR}/.env"
sed -i "s|^DB_PASS=.*|DB_PASS=${DB_PASS}|" "${ROOT_DIR}/.env"

# .env chứa mật khẩu -> trả quyền sở hữu về người dùng thật và khoá quyền đọc.
# Không có bước này, file do sudo tạo sẽ thuộc root và web server đọc không được.
chown "${REAL_USER}:${REAL_USER}" "${ROOT_DIR}/.env"
chmod 600 "${ROOT_DIR}/.env"

# ---------------------------------------------------------------------------
# 2. Schema + dữ liệu mẫu
#
# Bước DUY NHẤT trong script có thể xoá dữ liệu (schema.sql mở đầu bằng
# DROP TABLE). Ở chế độ sửa chữa thì bỏ qua hoàn toàn.
# ---------------------------------------------------------------------------
if [[ "${MODE}" == install ]]; then
    echo "→ Nạp schema và dữ liệu mẫu…"
    # --database bắt buộc: schema.sql không còn lệnh USE (xem ghi chú đầu file
    # đó — bỏ đi để import được qua phpMyAdmin trên hosting dùng chung).
    mysql --database="${DB_NAME}" < "${ROOT_DIR}/database/schema.sql"
else
    echo "→ Bỏ qua schema (chế độ sửa chữa) — dữ liệu giữ nguyên."
fi

# ---------------------------------------------------------------------------
# 3. Tài khoản quản trị
#
# Giao hẳn cho make-admin.php thay vì tự dựng câu lệnh SQL trong bash:
#   - PDO dùng prepared statement, không nối chuỗi vào SQL;
#   - mật khẩu không đi qua tham số dòng lệnh (ps đọc được) cũng không đi
#     qua biến shell được nội suy vào heredoc;
#   - chỉ còn một chỗ duy nhất sinh và in mật khẩu, không phải hai bản dễ lệch.
#
# Chạy bằng quyền người dùng thật để mọi file PHP lỡ tạo ra không thuộc root.
# ---------------------------------------------------------------------------
echo "→ Tạo tài khoản quản trị…"

# Không để `set -e` giết script ở đây. Tới bước này database và .env đã xong
# xuôi; chết ngang mà không nói gì sẽ khiến người dùng tưởng phải cài lại từ
# đầu — trong khi chỉ cần chạy lại đúng một lệnh.
if ! sudo -u "${REAL_USER}" php "${ROOT_DIR}/database/make-admin.php" "${ADMIN_EMAIL}" admin; then
    cat >&2 <<MSG

  ⚠  Chưa tạo được tài khoản quản trị.

     Database và .env đã cài đặt xong, KHÔNG cần chạy lại setup.sh.
     Chỉ cần chạy lại riêng bước này:

         php database/make-admin.php ${ADMIN_EMAIL} admin

MSG
    exit 1
fi

echo
echo "✓ Cài đặt xong."
echo "  Database        : ${DB_NAME}"
echo "  Tài khoản MySQL : ${DB_USER}  (mật khẩu đã ghi vào .env, quyền 600)"
echo
echo "  Chạy thử:  cd ${ROOT_DIR} && php -S localhost:8000 server.php"
echo
echo "  Bắt buộc có 'server.php' ở cuối lệnh. Thiếu nó, server built-in của"
echo "  PHP sẽ phục vụ nguyên văn mọi file — kể cả .env chứa mật khẩu DB."
