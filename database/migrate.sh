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

    # ── SNFR-06 · khoá đăng nhập 15 phút sau 5 lần sai ──────────────────────
    #
    # ĐÃ BỊ BỎ QUÊN Ở ĐÂY TỪ 02/09/2026. File migration được viết và commit,
    # nhưng không ai thêm dòng này, nên script lặng lẽ bỏ qua nó ở mọi lần
    # chạy — và cái bỏ qua đó KHÔNG gây lỗi gì mà cũng không ai thấy:
    # LoginAttemptModel::available() thấy bảng chưa có thì cho qua mọi lượt
    # đăng nhập, đúng như thiết kế ("một cái khoá gãy không được phép biến
    # thành cửa đóng với cả cửa hàng"). Hệ quả là SNFR-06 coi như chưa có
    # hiệu lực suốt hai ngày, trong khi tài liệu ghi là đã xong.
    #
    # Cột mốc là chính bảng đếm — chỉ file này tạo ra nó.
    "2026-09-02-khoa-dang-nhap-sau-5-lan-sai.sql|table|login_attempts|"

    # ── X21 · hồ sơ khúc xạ chuyển sang mô hình chỉ-thêm ────────────────────
    #
    # Cột mốc là `ban_goc_id` chứ không phải một trong mười một cột số đo cùng
    # đợt: nó là cột DUY NHẤT không thể do file khác tạo ra, và nó cũng là thứ
    # PrescriptionRecordModel::coPhienBan() hỏi để biết CSDL đã nâng cấp chưa.
    # Hai nơi cùng nhìn một cột thì không có cảnh script bảo "đã chạy" trong
    # khi mã nguồn vẫn tưởng là chưa.
    "2026-09-04-ho-so-khuc-xa-chi-them.sql|column|customer_prescriptions|ban_goc_id"

    # ── SNFR-07b · phân quyền theo cơ sở cho tài khoản nội bộ ───────────────
    #
    # Cột mốc là chính bảng nối. Không dùng giá trị 'technician' của ENUM làm
    # mốc: information_schema không cho hỏi "ENUM có chứa giá trị này không"
    # bằng một câu đơn giản như hỏi bảng hay cột, mà file này còn tạo bảng nên
    # đã có sẵn một mốc sạch.
    "2026-09-05-phan-quyen-theo-co-so.sql|table|staff_stores|"

    # ── X24 · trường "Người được đo" trên hồ sơ khúc xạ ─────────────────────
    #
    # File chỉ thêm ĐÚNG MỘT cột, nên cột đó vừa là nội dung vừa là mốc.
    "2026-09-06-nguoi-duoc-do.sql|column|customer_prescriptions|nguoi_duoc_do"

    # ── Q2.2 · Q3.1 · Q3.2 · mốc mài tròng và lý do đổi trạng thái ──────────
    #
    # Cột mốc là `orders.mai_bat_dau_luc` — cột đầu tiên file này thêm, và là
    # thứ OrderModel::coMocMai() hỏi để biết CSDL đã nâng cấp chưa. Hai nơi
    # cùng nhìn một cột thì không có cảnh script bảo "đã chạy" trong khi mã
    # nguồn vẫn tưởng là chưa.
    "2026-09-07-moc-mai-trong-va-ly-do.sql|column|orders|mai_bat_dau_luc"

    # ── Q75.1 · Q72 · sổ địa chỉ đủ trường và mốc xác thực SĐT ──────────────
    #
    # Cột mốc là `addresses.ghi_chu` — cột đầu tiên file này thêm, và là thứ
    # AddressModel::coTruongQ751() hỏi để biết CSDL đã nâng cấp chưa.
    "2026-09-08-so-dia-chi-va-xac-thuc-sdt.sql|column|addresses|ghi_chu"

    # ── GỠ X13 · đối soát hai bước bị bỏ khỏi sản phẩm ──────────────────────
    #
    # File ĐI NGƯỢC: nó BỎ sáu cột chứ không thêm gì, nên không có thứ nào để
    # làm cột mốc — mốc kiểu `column` sẽ báo "chưa chạy" mãi mãi sau khi đã
    # chạy xong, vì cột thì đã biến mất.
    #
    # Loại `data` giao toàn bộ quyết định cho sổ ghi, và điều đó an toàn ở đây
    # vì mỗi bước trong file đều hỏi information_schema trước: chạy lần hai chỉ
    # in ra "khong co, bo qua".
    "2026-09-10-go-doi-soat-hai-buoc.sql|data||"

    # ── Đơn hàng: đối chiếu được tiền, theo dõi tới lúc khách nhận ──────────
    #
    # File này nằm trong thư mục từ 11/09 nhưng CHƯA TỪNG được khai ở đây, nên
    # migrate.sh vẫn cảnh báo "bị bỏ qua" mỗi lượt chạy.
    #
    # Mốc là `orders.tax_amount` — cột đầu tiên nó thêm. Nếu ai đó đã áp file
    # này bằng tay thì phép kiểm mốc chạy TRƯỚC bước áp, nên script chỉ ghi sổ
    # chứ không chạy lại. Bản thân file cũng tự bọc mọi bước bằng
    # PREPARE/EXECUTE hỏi information_schema, nên chạy lại vô hại.
    "2026-09-11-don-hang-doi-soat-tien.sql|column|orders|tax_amount"

    # ═══════════════════════════════════════════════════════════════════════
    # SRS v2.1.0 — hai đợt hiệu chỉnh theo bản đặc tả hệ thống đích
    #
    # ⚠ TÊN FILE GHI 06/09 NHƯNG HAI FILE NÀY CHẠY SAU CÙNG.
    #
    # Thứ tự thi hành là THỨ TỰ TRONG MẢNG NÀY, không phải thứ tự ngày trên
    # tên file — vòng lặp áp file duyệt "${MIGRATIONS[@]}", còn phần glob thư
    # mục ở cuối script chỉ dùng để cảnh báo file chưa khai. Hai file dưới đây
    # là công việc mới nhất nên phải đứng cuối, dù ngày trên tên nhỏ hơn
    # 2026-09-10 và 2026-09-11.
    #
    # KHÔNG khai hai file *-QUAY-LUI.sql đi kèm. Chúng là đường lùi, chạy bằng
    # tay khi cần; khai vào đây thì script sẽ chạy chúng ngay sau file xuôi và
    # dựng lại đúng thứ vừa gỡ. Vòng cảnh báo ở cuối script đã lọc chúng ra.
    # ═══════════════════════════════════════════════════════════════════════

    # ── Đợt 1 · gỡ 20 chức năng theo Phụ lục C ──────────────────────────────
    #
    # File CHỈ GỠ: bỏ hai bảng, sáu cột, một dòng danh mục, và gộp một giá trị
    # trạng thái. Không tạo ra thứ gì để làm cột mốc, nên kiểu 'data' — sổ ghi
    # là thứ duy nhất chặn chạy lại.
    #
    # Giao cho sổ ghi ở đây AN TOÀN vì bản thân file đã idempotent hoàn toàn:
    # DROP TABLE IF EXISTS, DELETE, UPDATE vốn chạy lại được, còn sáu lệnh bỏ
    # cột thì đi qua PREPARE/EXECUTE hỏi information_schema trước.
    "2026-09-06-dot-1-go-bo.sql|data||"

    # ── Đợt 2 · rút vai trò từ năm xuống ba ─────────────────────────────────
    #
    # Cũng kiểu 'data': file chuyển dữ liệu rồi THU ENUM. Không có bảng hay cột
    # mới nào để làm mốc, và information_schema không cho hỏi "ENUM có chứa giá
    # trị này không" bằng một câu đơn giản như hỏi bảng hay cột.
    #
    # Chạy lại vô hại: hai câu UPDATE lần hai khớp 0 dòng, và MODIFY COLUMN đặt
    # lại đúng định nghĩa đã có.
    "2026-09-06-dot-2-ba-vai-tro.sql|data||"

    # ── Đợt 3 · hợp nhất hai nơi lưu số đo về một sổ chỉ-thêm ───────────────
    #
    # File này chỉ CHÈN — chép phần chỉ có ở bảng tóm tắt sang sổ. Không xoá,
    # không sửa dòng cũ, và mệnh đề NOT EXISTS tự loại những khách đã chép ở
    # lượt trước, nên chạy lại chèn 0 dòng.
    #
    # Kiểu 'data': không tạo ra bảng hay cột mới nào để làm mốc.
    #
    # CẶP ĐÔI CỦA NÓ — 2026-09-06-dot-3-go-bang-tom-tat.sql — CỐ Ý KHÔNG KHAI
    # Ở ĐÂY. SRS mục 6.7.3 bước 6 buộc chờ hệ thống chạy ổn định một tuần rồi
    # mới gỡ bảng, và chừng nào bảng còn thì đợt 3 còn đường lùi thật. Khai nó
    # vào mảng này là chạy nó ngay hôm nay và đóng đường lùi đó lại.
    "2026-09-06-dot-3-hop-nhat-so-do.sql|data||"

    # ── Đợt 4 · khách chủ động: tự xoá tài khoản, tự huỷ đơn, hoàn tiền cọc ──
    #
    # Một file làm ba việc: tạo bảng `refund_requests`, thêm
    # `users.deleted_source` và thêm `orders.cancelled_by`.
    #
    # Mốc là BẢNG chứ không phải cột, vì bảng là thứ nặng nhất trong ba và là
    # thứ mã nguồn thật sự hỏi trước khi chạy (RefundRequestModel::available()).
    # Hai cột kia đi qua PREPARE/EXECUTE có hỏi information_schema nên chạy lại
    # cũng không sao.
    #
    # File *-QUAY-LUI.sql đi kèm CỐ Ý KHÔNG KHAI — nó DROP bảng, tức xoá mọi
    # quyết định chi tiền đã duyệt. Đọc khối cảnh báo ở đầu file ấy trước khi
    # nghĩ tới chuyện chạy nó.
    "2026-09-06-dot-4-khach-chu-dong.sql|table|refund_requests|"

    # ── Đợt 5 · dọn màn hình và luật nhỏ ────────────────────────────────────
    #
    # Một file làm bốn việc: hai cột cho `order_items` (giá vốn, kiểu tròng),
    # một cột cho `stock_waitlist` (chủ tài khoản), và bảng `app_settings`.
    #
    # Mốc là BẢNG `app_settings` chứ không phải một cột: nó là thứ nặng nhất
    # trong bốn, và là thứ mã nguồn hỏi trước khi chạy ở MỌI lượt truy cập
    # (SettingModel::available, qua OrderModel::quetDonQuaHan). Ba cột kia đi
    # qua PREPARE/EXECUTE có hỏi information_schema nên chạy lại cũng không sao.
    "2026-09-06-dot-5-don-man-hinh.sql|table|app_settings|"

    # ── Đợt 6 · module thư ──────────────────────────────────────────────────
    #
    # Hai bảng: `email_templates` (mẫu thư, nạp sẵn 15 mẫu tiếng Việt) và
    # `email_queue` (hàng chờ và sổ kết quả gửi).
    #
    # Mốc là `email_queue` chứ không phải `email_templates`, dù bảng mẫu được
    # tạo trước trong file. Lý do là ở chỗ MÃ NGUỒN hỏi bảng nào:
    # EmailQueueModel::available() đứng gác trước MỌI đường sinh thư, và
    # EmailTemplateModel chỉ được hỏi sau khi nó đã cho qua. Lấy bảng mẫu làm
    # mốc thì một lần chạy dở dang — tạo xong bảng mẫu rồi đứt — sẽ được ghi
    # là "đã xong", và hàng chờ vĩnh viễn không có bảng.
    #
    # Chọn bảng cuối cùng file tạo ra làm mốc là luật chung cho mọi migration
    # nhiều bảng, không riêng file này.
    #
    # File *-QUAY-LUI.sql đi kèm CỐ Ý KHÔNG KHAI: nó DROP cả hai bảng, tức xoá
    # sổ mọi lá thư đã gửi cho khách — thứ duy nhất trả lời được câu "cửa hàng
    # đã báo cho khách chưa".
    "2026-09-06-dot-6-email.sql|table|email_queue|"

    # ── Đợt 7 · sổ đối soát ngân hàng và hồ sơ đo mắt trên đơn ─────────────
    #
    # Bốn việc, hai bảng: hai cột dấu vết gắn tay và một chỉ mục cho
    # `sepay_transactions`, cùng cột `prescription_id` cho `order_items`.
    #
    # Mốc là CỘT `order_items`.`prescription_id` — việc CUỐI CÙNG file tạo ra,
    # theo đúng luật chung "lấy thứ sau cùng làm mốc" đã ghi ở đợt 6. Một lần
    # chạy dở dang (đứt giữa hai câu ALTER, và DDL của MySQL không nằm trong
    # transaction) sẽ không bị ghi nhầm là đã xong.
    #
    # KHÔNG bảng nào được tạo mới ở đợt này: `sepay_transactions` đã ghi đủ
    # mọi giao dịch từ 22/08/2026, chỉ là chưa màn hình nào đọc ra. Đó là cả
    # nội dung của Quyết định E06.
    #
    # HAI file *-QUAY-LUI.sql đi kèm và CỐ Ý KHÔNG KHAI, cũng cố ý TÁCH ĐÔI:
    # phần sổ đối soát và phần UC-03 hỏng độc lập với nhau, nên lùi cái này
    # không được kéo theo cái kia. Đọc đầu mỗi file trước khi chạy.
    "2026-09-06-dot-7-doi-soat.sql|column|order_items|prescription_id"
)

# ---------------------------------------------------------------------------
# Kiểm tra điều kiện chạy
# ---------------------------------------------------------------------------
command -v mysql >/dev/null 2>&1 || { echo "✗ Thiếu lệnh mysql." >&2; exit 1; }

# ---------------------------------------------------------------------------
# GỌI mysql QUA MỘT HÀM, ĐỂ ÉP BẢNG MÃ utf8mb4 — thêm 06/09/2026 (đợt 6)
#
# ─────────────────────────────────────────────────────────────────────────────
# VÌ SAO CHUYỆN NÀY CHỈ LỘ RA Ở ĐỢT 6
#
# Trình khách mysql lấy bảng mã từ my.cnf của MÁY ĐANG CHẠY, không phải từ CSDL.
# Nhiều bản cài để 'latin1'. Khi đó mọi ký tự tiếng Việt trong file .sql bị
# hiểu là latin1 rồi mã hoá LẠI sang utf8mb4 lúc ghi — cột đúng utf8mb4, dữ
# liệu bên trong hỏng, và không có lỗi nào báo ra.
#
# Tới trước đợt 6, không migration nào CHÈN chữ tiếng Việt: chúng chỉ đổi cấu
# trúc, hoặc chép dữ liệu trong nội bộ CSDL (đợt 3) — việc chép ấy chạy hẳn
# trong máy chủ nên bảng mã trình khách không đụng tới.
#
# Đợt 6 chèn 15 mẫu thư tiếng Việt. Không có dòng này thì trên một máy cấu hình
# latin1, mọi lá thư gửi cho khách sẽ đầy "Ä‘Æ¡n hÃ ng" — và người vận hành chỉ
# phát hiện khi khách gọi điện hỏi.
#
# ĐẶT Ở MỌI LỜI GỌI, không chỉ ở chỗ nạp file: các câu đếm information_schema
# trả về tên bảng ASCII nên không đổi gì, còn dòng ghi vào `schema_migrations`
# thì có mang tên file. Một quy tắc áp khắp nơi thì không có chỗ nào để quên.
# ---------------------------------------------------------------------------
mysql() { command mysql --default-character-set=utf8mb4 "$@"; }

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

    # File *-QUAY-LUI.sql là ĐƯỜNG LÙI, cố ý không khai trong MIGRATIONS[].
    #
    # Khai vào đó thì script chạy chúng ngay sau file xuôi và dựng lại đúng thứ
    # vừa gỡ — nên chúng phải nằm ngoài, và cũng phải nằm ngoài cảnh báo này:
    # một dòng "chưa khai, bị bỏ qua" lặp mỗi lượt chạy sớm muộn sẽ khiến ai đó
    # khai chúng vào cho hết cảnh báo.
    if [[ "${file}" == *-QUAY-LUI.sql ]]; then
        continue
    fi

    # Cùng lẽ đó với file gỡ bảng tóm tắt của đợt 3: nó phải chờ một tuần sau
    # file hợp nhất (SRS mục 6.7.3 bước 6), nên chạy BẰNG TAY chứ không qua
    # script. Lọc khỏi cảnh báo để không ai khai nó vào cho hết dòng nhắc.
    if [[ "${file}" == "2026-09-06-dot-3-go-bang-tom-tat.sql" ]]; then
        continue
    fi

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
