<?php

/**
 * CustomerModel — dữ liệu khách hàng cho khu quản trị (/quan-tri/khach-hang).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MODULE NÀY SỞ HỮU CÁI GÌ, VÀ CHỈ MƯỢN CÁI GÌ
 *
 * SỞ HỮU (sửa được ở đây):  hồ sơ · trạng thái tài khoản · sổ địa chỉ ·
 *                           đơn thuốc kính · ghi chú nội bộ
 * CHỈ MƯỢN (đọc, có link):  đơn hàng · lịch hẹn · liên hệ · đánh giá
 *
 * Ranh giới đó là RÀNG BUỘC THIẾT KẾ, không phải gợi ý. Model này KHÔNG có một
 * hàm nào đổi trạng thái đơn, duyệt đánh giá hay xác nhận lịch hẹn — bốn module
 * kia đã làm việc đó và mỗi việc chỉ nên có đúng một chỗ làm. Thêm một nút
 * "duyệt đánh giá" vào đây là bắt đầu có hai chỗ, rồi hai chỗ sẽ lệch nhau.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * "KHÁCH HÀNG" ĐỊNH NGHĨA LÀ GÌ
 *
 * Là tài khoản KHÔNG giữ vai trò nội bộ nào (staff · manager · admin), chứ
 * không phải "tài khoản có vai trò customer".
 *
 * Nghe thì ngược, nhưng đây là cách duy nhất đúng: một nhân viên cũng mua
 * kính cho vợ nên tài khoản của họ có CẢ 'customer' lẫn 'staff'. Lọc
 * theo "có customer" sẽ kéo người đó vào danh sách khách, và rồi ai đó sẽ bấm
 * khoá tài khoản ngay trước giờ mở cửa. Lọc theo "không có vai trò nội bộ" thì
 * không bao giờ xảy ra chuyện đó — và danh sách khách với danh sách tài khoản
 * nội bộ không có ai đứng ở cả hai bên.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CustomerModel extends BaseModel
{
    protected static string $table = 'users';

    /** Trạng thái tài khoản. Khớp giá trị cột `users`.`status`. */
    public const STATUSES = [
        'active' => 'Hoạt động',
        'locked' => 'Đã khoá',
    ];

    /**
     * Dải lọc trên trang danh sách.
     *
     * 'deleted' KHÔNG phải một giá trị của cột `status` — nó đọc `deleted_at`.
     * Gộp chung vào đây vì với người dùng thì cả ba đều là "lọc theo tình
     * trạng tài khoản"; việc chúng nằm ở hai cột khác nhau là chuyện của CSDL.
     */
    public const FILTERS = [
        'active'           => 'Hoạt động',
        'locked'           => 'Đã khoá',
        'deleted-customer' => 'Khách tự xoá',
        'deleted-staff'    => 'Nhân viên xoá',
    ];

    /**
     * Ba bộ lọc đọc `deleted_at IS NOT NULL`.
     *
     * 'deleted' KHÔNG còn là một tab — FR-KH-02 tách nó làm hai — nhưng vẫn
     * nhận, vì nó đã nằm trong đường dẫn suốt từ khi có màn này: liên kết dán
     * trong tin nhắn, dấu trang của nhân viên, và mọi địa chỉ đã chia sẻ.
     * Không nhận nữa thì locHopLe() hạ nó về '' và người bấm nhận được danh
     * sách khách ĐANG HOẠT ĐỘNG dưới một cái nhãn nói "đã xoá".
     */
    private const LOC_DA_XOA = ['deleted', 'deleted-customer', 'deleted-staff'];

    /**
     * Đơn ở trạng thái này KHÔNG tính vào "tổng chi tiêu".
     *
     * Chỉ loại đơn đã huỷ. Đơn chưa giao xong vẫn tính, vì cột này đo MỨC GẮN
     * BÓ của khách chứ không phải doanh thu đã thực nhận — người vừa đặt ba
     * triệu tiền kính sáng nay là khách quan trọng ngay lúc đó, không phải chờ
     * tới lúc giao hàng.
     *
     * Cần con số kế toán thì đọc ở module Đơn hàng, nơi có đủ cả trục
     * `payment_status`.
     */
    private const KHONG_TINH_TIEN = 'cancelled';

    /**
     * CSDL đã có cột `users`.`deleted_source` chưa — migration đợt 4.
     *
     * Chưa có thì hai tab xoá vẫn hiện và vẫn liệt kê được, chỉ là cả hai cùng
     * ra toàn bộ tài khoản đã xoá. Thà trùng nhau còn hơn để một trang quản trị
     * ném lỗi 1054 vì một cột chưa nâng cấp.
     */
    public static function coNguonXoa(): bool
    {
        return Database::columnExists('users', 'deleted_source');
    }

    /**
     * Chuẩn hoá giá trị `?status=` — trả '' nếu không nhận ra.
     *
     * Gộp ở model chứ không lặp ở hai action của controller: danh sách và xuất
     * file phải hiểu cùng một tập khoá, nếu không thì nút Xuất sẽ lặng lẽ xuất
     * một tập khác với bảng đang xem.
     */
    public static function locHopLe(string $filter): string
    {
        if (isset(self::FILTERS[$filter])) {
            return $filter;
        }

        // Khoá cũ: giữ nguyên nghĩa "mọi tài khoản đã xoá", không có tab.
        return in_array($filter, self::LOC_DA_XOA, true) ? $filter : '';
    }

    // ========================================================================
    // SẴN SÀNG CHƯA
    // ========================================================================

    /**
     * Cơ sở dữ liệu đã chạy migration 2026-08-26 chưa.
     *
     * Cả module dựa vào năm cột thêm vào `users`. Thiếu chúng thì mọi câu SQL
     * dưới đây đổ lỗi 1054 và trang quản trị trả 500 — đúng kiểu hỏng đã xảy
     * ra ngày 2026-08-22 khi hosting chưa chạy migration. Controller hỏi hàm
     * này trước rồi hiện một trang nói rõ phải chạy file nào.
     */
    public static function ready(): bool
    {
        static $ket = null;

        if ($ket !== null) {
            return $ket;
        }

        return $ket = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'users'
                AND COLUMN_NAME IN ('status', 'deleted_at')"
        ) === 2;
    }

    // ========================================================================
    // DANH SÁCH
    // ========================================================================

    /**
     * Trang danh sách: tìm kiếm + lọc + phân trang, kèm số đơn và tổng chi tiêu.
     *
     * @return array{items:array, total:int, page:int, totalPages:int}
     */
    public static function paginateList(string $q, string $filter, int $page, int $perPage = 20): array
    {
        [$where, $params] = self::buildFilter($q, $filter);

        $total      = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM users u LEFT JOIN profiles p ON p.id = u.id ' . $where,
            $params
        );
        $totalPages = (int) ceil($total / max(1, $perPage));
        $page       = max(1, $page);
        $offset     = ($page - 1) * $perPage;

        /* SỐ ĐƠN VÀ TỔNG CHI TIÊU LẤY BẰNG MỘT BẢNG DẪN XUẤT, KHÔNG PHẢI HAI
           TRUY VẤN CON CHẠY LẠI CHO TỪNG DÒNG.

           Truy vấn con tương quan sẽ quét bảng `orders` một lần cho mỗi khách
           trên trang — 20 khách là 20 lượt. Bảng dẫn xuất gom một lần rồi ghép,
           và `idx_orders_user` lo phần gom. Cùng lý do với chỗ gom order_items
           trong OrderAdminController::index(). */
        /* `deleted_source` CHỈ CHỌN KHI CỘT CÓ — huy hiệu ở bảng đọc nó để nói
           "Khách tự xoá" hay "Nhân viên xoá". Máy chưa chạy migration đợt 4 thì
           khoá này vắng và view lùi về nhãn "Đã xoá" trần (nó đọc bằng ?? null).
           Chọn thẳng là lỗi 1054 ngay ở màn danh sách. */
        $cotNguon = self::coNguonXoa() ? 'u.deleted_source,' : '';

        $sql = 'SELECT u.id, u.email, u.status, u.deleted_at, u.created_at, u.last_login_at,
                       ' . $cotNguon . '
                       p.full_name, p.phone,
                       COALESCE(o.so_don, 0)   AS so_don,
                       COALESCE(o.tong_tien, 0) AS tong_tien
                  FROM users u
                  LEFT JOIN profiles p ON p.id = u.id
                  LEFT JOIN (
                      SELECT user_id,
                             COUNT(*)    AS so_don,
                             SUM(total)  AS tong_tien
                        FROM orders
                       WHERE user_id IS NOT NULL
                         AND status <> :bo_trang_thai
                       GROUP BY user_id
                  ) o ON o.user_id = u.id
                ' . $where . '
                 ORDER BY u.created_at DESC
                 LIMIT ' . max(1, $perPage) . ' OFFSET ' . max(0, $offset);

        return [
            'items'      => Database::fetchAll($sql, $params + ['bo_trang_thai' => self::KHONG_TINH_TIEN]),
            'total'      => $total,
            'page'       => $page,
            'totalPages' => $totalPages,
        ];
    }

    /** Số lượng cho dải lọc. Một câu lệnh chứ không năm. */
    public static function counts(): array
    {
        /* HAI Ô ĐẾM CUỐI GHÉP THEO CỘT CÓ HAY KHÔNG.

           `deleted_source` đến ở migration đợt 4. Nhắc tên nó trong một câu
           chạy ở MỌI lượt mở trang khách hàng nghĩa là một máy chưa nâng cấp sẽ
           gặp lỗi 1054 ngay ở màn danh sách — không phải ở một góc ít ai vào.

           Chưa có cột thì cả hai ô đếm cùng ra tổng số tài khoản đã xoá, khớp
           với việc buildFilter() lúc ấy cũng không lọc được theo nguồn. Con số
           trên tab và số dòng trong bảng nói cùng một điều, dù điều đó là
           "chưa phân biệt được". */
        $coNguon = self::coNguonXoa();

        $khach = $coNguon
            ? "SUM(u.deleted_at IS NOT NULL AND u.deleted_source = 'customer')"
            : 'SUM(u.deleted_at IS NOT NULL)';

        /* NULL XẾP VÀO "NHÂN VIÊN XOÁ" — cố ý, và đây là chỗ duy nhất suy đoán.

           Migration đợt 4 KHÔNG backfill `deleted_source` cho những dòng xoá
           trước nó: viết một giá trị đoán vào cột kiểm toán thì sáu tháng sau
           không ai phân biệt được nó với dữ liệu thật.

           Nhưng một BỘ LỌC thì khác một cột lưu trữ: nó chỉ là cách nhìn, sửa
           lại được bất cứ lúc nào. Và nếu NULL không thuộc tab nào thì những
           tài khoản ấy biến mất khỏi cả bốn tab — không còn đường nào mở chúng
           trong khu quản trị nữa. Mất hẳn một lối vào tệ hơn hẳn một phép suy
           đoán, nhất là khi phép suy đoán này gần như chắc đúng: trước đợt 4
           chỉ nhân viên mới xoá được tài khoản khách. */
        $nhanVien = $coNguon
            ? "SUM(u.deleted_at IS NOT NULL AND (u.deleted_source <> 'customer'
                                                 OR u.deleted_source IS NULL))"
            : 'SUM(u.deleted_at IS NOT NULL)';

        $row = Database::fetchOne(
            'SELECT COUNT(*)                                            AS tat_ca,
                    SUM(u.deleted_at IS NULL AND u.status = \'active\')  AS active,
                    SUM(u.deleted_at IS NULL AND u.status = \'locked\')  AS locked,
                    SUM(u.deleted_at IS NOT NULL)                       AS deleted,
                    ' . $khach . '                                      AS deleted_customer,
                    ' . $nhanVien . '                                   AS deleted_staff
               FROM users u
              WHERE ' . self::KHONG_NOI_BO
        ) ?? [];

        return [
            // Khoá '' là "Tất cả" của admin/_layout/filter-tabs.php. Nó KHÔNG
            // đếm tài khoản đã xoá mềm — xem buildFilter().
            ''                 => (int) ($row['active'] ?? 0) + (int) ($row['locked'] ?? 0),
            'active'           => (int) ($row['active'] ?? 0),
            'locked'           => (int) ($row['locked'] ?? 0),
            // Khoá cũ, không còn tab nào đọc. Giữ để đường dẫn '?status=deleted'
            // đã chia sẻ vẫn hiện đúng con số — xem LOC_DA_XOA.
            'deleted'          => (int) ($row['deleted'] ?? 0),
            'deleted-customer' => (int) ($row['deleted_customer'] ?? 0),
            'deleted-staff'    => (int) ($row['deleted_staff'] ?? 0),
        ];
    }

    /**
     * Toàn bộ dòng khớp bộ lọc, không phân trang — dành cho việc xuất file.
     *
     * KHÔNG dùng cho màn hình: không có LIMIT nào ở đây.
     */
    public static function exportRows(string $q, string $filter): array
    {
        [$where, $params] = self::buildFilter($q, $filter);

        return Database::fetchAll(
            'SELECT u.id, u.email, u.status, u.deleted_at, u.created_at, u.last_login_at,
                    p.full_name, p.phone, p.date_of_birth, p.gender,
                    COALESCE(o.so_don, 0)    AS so_don,
                    COALESCE(o.tong_tien, 0) AS tong_tien
               FROM users u
               LEFT JOIN profiles p ON p.id = u.id
               LEFT JOIN (
                   SELECT user_id, COUNT(*) AS so_don, SUM(total) AS tong_tien
                     FROM orders
                    WHERE user_id IS NOT NULL AND status <> :bo_trang_thai
                    GROUP BY user_id
               ) o ON o.user_id = u.id
             ' . $where . '
              ORDER BY u.created_at DESC',
            $params + ['bo_trang_thai' => self::KHONG_TINH_TIEN]
        );
    }

    // ========================================================================
    // MỘT KHÁCH
    // ========================================================================

    /**
     * Hồ sơ đầy đủ của một khách, hoặc null nếu id không phải khách hàng.
     *
     * Trả về null CẢ KHI id có thật nhưng là tài khoản nội bộ. Nếu không, gõ
     * tay địa chỉ /quan-tri/khach-hang/<id-của-admin> là mở được màn sửa hồ sơ
     * của quản trị viên khác từ một module không có ràng buộc nào về việc đó.
     */
    public static function detail(string $id): ?array
    {
        return Database::fetchOne(
            'SELECT u.id, u.email, u.email_verified, u.google_id, u.status,
                    u.locked_reason, u.locked_at, u.locked_by,
                    u.deleted_at, u.deletion_reason,
                    u.last_login_at, u.created_at,
                    u.terms_accepted_at, u.terms_version,
                    p.full_name, p.phone, p.address, p.date_of_birth,
                    p.gender, p.avatar_path,
                    lp.full_name AS locked_by_name
               FROM users u
               LEFT JOIN profiles p  ON p.id = u.id
               LEFT JOIN profiles lp ON lp.id = u.locked_by
              WHERE u.id = :id
                AND ' . self::KHONG_NOI_BO,
            ['id' => $id]
        );
    }

    /**
     * Số đơn, tổng chi tiêu, ngày mua gần nhất.
     *
     * @return array{so_don:int, tong_tien:int, don_gan_nhat:?string}
     */
    public static function stats(string $id): array
    {
        $row = Database::fetchOne(
            'SELECT COUNT(*)                  AS so_don,
                    COALESCE(SUM(total), 0)   AS tong_tien,
                    MAX(created_at)           AS don_gan_nhat
               FROM orders
              WHERE user_id = :id
                AND status <> :bo_trang_thai',
            ['id' => $id, 'bo_trang_thai' => self::KHONG_TINH_TIEN]
        ) ?? [];

        return [
            'so_don'       => (int) ($row['so_don'] ?? 0),
            'tong_tien'    => (int) ($row['tong_tien'] ?? 0),
            'don_gan_nhat' => $row['don_gan_nhat'] ?? null,
        ];
    }

    /**
     * Tab "Hoạt động" — bốn danh sách CHỈ ĐỌC, mỗi cái thuộc về một module khác.
     *
     * Giới hạn số dòng ở đây là CỐ Ý. Đây không phải màn quản lý đơn hàng, chỉ
     * là chỗ trả lời "khách này gần đây làm gì" trong một cái liếc mắt. Muốn
     * xem đủ thì bấm sang module gốc — mỗi khối trong view đều có một đường
     * dẫn như vậy.
     *
     * @return array{orders:array, appointments:array, contacts:array, reviews:array}
     */
    public static function activity(string $id, int $limit = 10): array
    {
        $limit = max(1, $limit);

        $orders = Database::fetchAll(
            'SELECT id, code, status, payment_status, total, created_at
               FROM orders
              WHERE user_id = :id
              ORDER BY created_at DESC
              LIMIT ' . $limit,
            ['id' => $id]
        );

        $appointments = Database::fetchAll(
            'SELECT a.id, a.code, a.appointment_date, a.service_type, a.status,
                    a.created_at, s.name AS store_name
               FROM appointments a
               LEFT JOIN stores s ON s.id = a.store_id
              WHERE a.user_id = :id
              ORDER BY a.appointment_date DESC
              LIMIT ' . $limit,
            ['id' => $id]
        );

        /* Bảng `contact_requests` chỉ có cột `user_id` từ migration 2026-08-26.
           Cơ sở dữ liệu chưa chạy file đó thì câu lệnh này đổ lỗi 1054 và cả
           trang chi tiết trắng — nên hỏi trước, và khuyết một khối còn hơn mất
           cả trang. Ba khối kia không cần lối thoát này: `user_id` của chúng
           có từ lược đồ gốc.

           KHÔNG HỎI CỘT `status`. Nó đã bị xoá bởi migration
           2026-08-27-bo-cot-status-lien-he.sql — module Liên hệ bỏ hẳn trạng
           thái, mọi yêu cầu chạy thẳng sang Zalo CSKH. Câu này vẫn hỏi cột đó
           tới tận 2026-08-28 và làm CẢ TAB HOẠT ĐỘNG đổ lỗi 1054 trên trang
           thật: view đã bỏ chỗ hiển thị trạng thái từ hôm bỏ cột, nhưng câu
           SELECT thì không ai sờ tới.

           Bài học để lại đây: coCotLienHe() chỉ canh cột `user_id`, nó KHÔNG
           bảo vệ được những cột khác trong cùng câu lệnh. Thêm cột nào vào
           danh sách dưới thì phải tự hỏi cột đó có thể bị xoá không. */
        $contacts = self::coCotLienHe()
            ? Database::fetchAll(
                'SELECT id, message, created_at
                   FROM contact_requests
                  WHERE user_id = :id
                  ORDER BY created_at DESC
                  LIMIT ' . $limit,
                ['id' => $id]
            )
            : [];

        $reviews = Database::fetchAll(
            'SELECT r.id, r.rating, r.body, r.status, r.created_at,
                    pr.name AS product_name, pr.slug AS product_slug
               FROM reviews r
               LEFT JOIN products pr ON pr.id = r.product_id
              WHERE r.user_id = :id
              ORDER BY r.created_at DESC
              LIMIT ' . $limit,
            ['id' => $id]
        );

        return [
            'orders'       => $orders,
            'appointments' => $appointments,
            'contacts'     => $contacts,
            'reviews'      => $reviews,
        ];
    }

    /**
     * Lịch hẹn ĐÃ HOÀN TẤT của khách — để gắn vào một bản ghi đơn thuốc.
     *
     * Chỉ lấy 'done': đơn thuốc gắn vào một lịch hẹn chưa diễn ra là nói rằng
     * số đo này lấy từ một buổi đo chưa xảy ra.
     */
    public static function doneAppointments(string $id): array
    {
        return Database::fetchAll(
            "SELECT a.id, a.code, a.appointment_date, s.name AS store_name
               FROM appointments a
               LEFT JOIN stores s ON s.id = a.store_id
              WHERE a.user_id = :id
                AND a.status = 'done'
              ORDER BY a.appointment_date DESC
              LIMIT 50",
            ['id' => $id]
        );
    }

    // ========================================================================
    // HÀNH ĐỘNG
    // ========================================================================

    /*
     * KHÔNG CÓ saveProfile() Ở ĐÂY — bỏ ngày 2026-08-28, cố ý.
     *
     * Hồ sơ khách (họ tên, số điện thoại, email, ngày sinh, giới tính) nay là
     * CHỈ XEM trong khu quản trị. Hai trong năm ô đó là thứ khách dùng để đăng
     * nhập, nên nhân viên gõ nhầm là khách mất đường vào tài khoản của chính
     * mình mà người gõ không thấy hậu quả gì ngay lúc đó. Khách tự sửa ở
     * /tai-khoan?muc=ho-so — nơi họ đang cầm sẵn hòm thư và số điện thoại ấy.
     *
     * Luật kiểm tra vẫn còn nguyên và vẫn đang chạy cho đường của khách:
     * UserModel::updateProfile() và ::updateEmail(). Cần mở lại đường cho
     * nhân viên thì gọi hai hàm đó, đừng chép luật sang đây — và nhớ thêm cả
     * route lẫn vết audit, xem đầu CustomerAdminController.
     */

    // ========================================================================
    // KHOÁ ĐĂNG NHẬP 15 PHÚT — FR-KH-08
    //
    // KHÁC HẲN "Khoá tài khoản" ngay dưới. Hai thứ chồng lên nhau nên rất dễ
    // lẫn, và lẫn ở đây thì nhân viên mở nhầm cánh cửa:
    //
    //   Khoá tài khoản   do NGƯỜI đặt, không hạn, có lý do, là biện pháp hành
    //                    chính. Cột `users`.`status`.
    //   Khoá đăng nhập   do HỆ THỐNG đặt sau 5 lần gõ sai mật khẩu, tự tan sau
    //                    15 phút, không có lý do nào để đọc. Bảng
    //                    `login_attempts`, bám theo chuỗi định danh đã gõ.
    //
    // Cái thứ hai là thứ khách gọi điện phàn nàn: gõ sai vài lần, bị chặn, và
    // câu duy nhất nhân viên nói được là "anh chờ 15 phút". Khu nội bộ đã có nút
    // gỡ từ đợt 2 (StaffAdminController::moKhoaDangNhap); đây là bản cho khách.
    // ========================================================================

    /**
     * Còn bị khoá đăng nhập bao nhiêu giây — 0 nghĩa là không bị khoá.
     *
     * Hỏi theo CẢ email lẫn số điện thoại: bộ đếm bám vào chuỗi khách đã gõ ở ô
     * đăng nhập, mà ô đó nhận cả hai. Khách gõ sai bằng số điện thoại thì khoá
     * nằm ở chuỗi số, và hỏi mỗi email sẽ trả "không bị khoá" cho một người
     * đang bị chặn.
     */
    public static function conKhoaDangNhap(array $khach): int
    {
        $chuoi = array_values(array_filter([
            (string) ($khach['email'] ?? ''),
            (string) ($khach['phone'] ?? ''),
        ], static fn (string $v): bool => $v !== ''));

        return $chuoi === [] ? 0 : LoginAttemptModel::conKhoaBatKy($chuoi);
    }

    /**
     * Gỡ khoá đăng nhập cho một khách.
     *
     * KHÔNG đụng tới `users`.`status`: một tài khoản vừa bị khoá hành chính vừa
     * bị khoá đăng nhập thì gỡ cái sau không được mở cái trước. Nhân viên tưởng
     * mình vừa giúp khách mà khách vẫn không vào được — thà thế còn hơn một cú
     * bấm lặng lẽ huỷ quyết định khoá của người khác.
     */
    public static function moKhoaDangNhap(string $id): array
    {
        $khach = self::detail($id);

        if ($khach === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy khách hàng.'];
        }

        LoginAttemptModel::moKhoa([
            (string) ($khach['email'] ?? ''),
            (string) ($khach['phone'] ?? ''),
        ]);

        /* Chủ thể của vết là TÀI KHOẢN ĐƯỢC MỞ, không phải người bấm — người
           bấm đã nằm ở cột actor_id do write() tự điền. Cùng quy ước với
           StaffAdminController::moKhoaDangNhap(). */
        AuditLogModel::write($id, 'customer.unlock_login',
            'Gỡ khoá đăng nhập cho ' . (string) ($khach['full_name'] ?? $khach['email'] ?? 'khách'));

        return ['ok' => true];
    }

    /**
     * Khoá tài khoản. Lý do là BẮT BUỘC.
     *
     * Bắt buộc vì người đọc lý do đó không phải người gõ nó: ba tháng sau,
     * khách gọi điện hỏi vì sao không đăng nhập được, và người nhấc máy là ca
     * trực khác. "Đã khoá" không trả lời được câu nào cả.
     */
    public static function lock(string $id, string $reason, string $actorId): array
    {
        /* LÝ DO KHOÁ KHÔNG CÒN BẮT BUỘC — SRS v2.1.0, L07.
           Ô nhập vẫn còn và vẫn lưu vào `locked_reason` khi có; để trống thì
           cột nhận NULL chứ không nhận chuỗi rỗng, để câu hỏi "có ghi lý do
           không" trả lời được bằng chính dữ liệu. */
        $reason = trim($reason);

        $khach = self::detail($id);

        if ($khach === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy khách hàng.'];
        }

        Database::execute(
            "UPDATE users
                SET status = 'locked', locked_reason = :ly_do,
                    locked_at = NOW(), locked_by = :boi
              WHERE id = :id",
            [
                'ly_do' => $reason === '' ? null : utf8Substr($reason, 0, 255),
                'boi'   => $actorId,
                'id'    => $id,
            ]
        );

        /* CẮT MỌI PHIÊN "GHI NHỚ ĐĂNG NHẬP" CỦA NGƯỜI NÀY.
           Không có dòng này thì khoá tài khoản chỉ chặn được người chưa đăng
           nhập: ai đang giữ cookie ghi nhớ vẫn vào thẳng như thường, và có thể
           vào như thế hàng tháng trời. Đúng cái mà nút khoá phải ngăn. */
        RememberModel::forgetAllFor($id);

        AuditLogModel::write($id, 'lock', $reason === '' ? 'Khoá tài khoản (không ghi lý do)' : $reason);

        return ['ok' => true];
    }

    public static function unlock(string $id): array
    {
        if (self::detail($id) === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy khách hàng.'];
        }

        /* Xoá luôn lý do và mốc khoá chứ không giữ lại: cột này trả lời câu
           "vì sao tài khoản NÀY đang bị khoá", mà nó đã không còn bị khoá.
           Muốn tra lịch sử thì đọc customer_audit_logs — chỗ đó mới là nơi
           giữ vết, và giữ được nhiều lần khoá chứ không chỉ lần cuối. */
        Database::execute(
            "UPDATE users
                SET status = 'active', locked_reason = NULL,
                    locked_at = NULL, locked_by = NULL
              WHERE id = :id",
            ['id' => $id]
        );

        AuditLogModel::write($id, 'unlock');

        return ['ok' => true];
    }

    /**
     * Xoá mềm.
     *
     * VÌ SAO KHÔNG XOÁ CỨNG: `orders`.`user_id` là ON DELETE SET NULL, nên một
     * lệnh DELETE thật sẽ làm toàn bộ đơn hàng của khách mất chủ vĩnh viễn —
     * không có đường nào nối lại, và sổ sách kế toán không cho phép.
     */
    /**
     * KHÁCH TỰ YÊU CẦU XOÁ TÀI KHOẢN — SRS v2.1.0, UC-01 · FR-TK-17.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * XOÁ MỀM, KHÔNG XOÁ VẬT LÝ — BR-TK-17.1
     *
     * Tài khoản biến mất khỏi mọi giao diện và mọi đường đăng nhập, nhưng đơn
     * hàng, hồ sơ đo mắt và vết kiểm toán GIỮ NGUYÊN trong cơ sở dữ liệu.
     *
     * Đó không phải sự nửa vời. Đơn hàng cũ là chứng từ kế toán của cửa hàng,
     * không phải tài sản riêng của khách — xoá chúng đi là làm hỏng sổ sách của
     * một bên thứ ba. Hồ sơ đo mắt là dữ liệu y tế đã dùng để cắt tròng, và nếu
     * khách quay lại khiếu nại "các anh mài sai độ" thì không còn gì đối chiếu.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * BA ĐIỀU KIỆN CHẶN — BR-TK-17.2, cùng E2/E3/E4 của UC-01
     *
     *   đơn chưa kết thúc   từ chối. Xoá tài khoản giữa lúc còn đơn đang giao
     *                       là để lại một đơn không liên hệ được với ai.
     *   cọc chưa xử lý      từ chối. Đây là tiền của khách đang nằm ở cửa hàng.
     *   lịch hẹn sắp tới    CẢNH BÁO, không chặn — khách xác nhận tiếp thì xoá,
     *                       và lịch hẹn bị huỷ theo.
     *
     * Ba mức khác nhau vì hậu quả khác nhau: hai cái đầu có bên thứ ba (cửa
     * hàng, hoặc tiền) chịu ảnh hưởng, cái cuối chỉ ảnh hưởng chính khách.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array{ok:bool, error?:string, canhBao?:string}
     */
    public static function khachTuXoa(string $userId, string $matKhau, bool $daXacNhanLich): array
    {
        $user = UserModel::find($userId);

        if ($user === null || $user['deleted_at'] !== null) {
            return ['ok' => false, 'error' => 'Không tìm thấy tài khoản.'];
        }

        /* E1 — SAI MẬT KHẨU.
 
           Kiểm danh tính bằng chính mật khẩu chứ không dựa vào việc "đang đăng
           nhập": phiên có thể là của một máy để quên ở quán cà phê, và xoá tài
           khoản là thao tác không lùi lại được bằng một cú bấm.
 
           Tài khoản đăng ký bằng Google không có mật khẩu để nhập — bản này
           chưa mở đường xoá cho họ, và nói thẳng ra thay vì báo "sai mật khẩu"
           cho một ô mà họ chưa bao giờ đặt.

           ─────────────────────────────────────────────────────────────────────
           THỬ MẬT KHẨU TRƯỚC, RỒI MỚI HỎI GOOGLE — thứ tự này là bản sửa

           Bản đầu hỏi ngược: google_id có VÀ password_hash rỗng thì báo câu
           Google. Vế thứ hai không bao giờ đúng — UserModel::findOrCreateGoogle()
           đặt một mật khẩu ngẫu nhiên 32 byte cho tài khoản tạo qua Google, nên
           `password_hash` luôn là một chuỗi bcrypt thật dài 60 ký tự và phép so
           với '' không bao giờ khớp. Người dùng Google vì thế rơi thẳng xuống
           password_verify(), vốn cũng không bao giờ khớp vì không ai biết cái
           mật khẩu ngẫu nhiên ấy. Kết quả: họ gõ gì cũng nhận đúng câu "Mật khẩu
           không đúng", mãi mãi — đúng câu mà khối này viết ra để tránh.

           Đảo lại thì đúng cả hai chiều, kể cả ca lai: người đăng ký bằng Google
           rồi sau đó ĐẶT mật khẩu qua "quên mật khẩu" sẽ qua vế đầu như mọi
           khách khác, còn người chưa từng đặt thì nhận đúng câu Google.
           ───────────────────────────────────────────────────────────────────── */
        if (!password_verify($matKhau, (string) ($user['password_hash'] ?? ''))) {
            if (($user['google_id'] ?? null) !== null) {
                return ['ok' => false, 'error' =>
                    'Tài khoản đăng nhập bằng Google chưa tự xoá được ở đây. '
                    . 'Vui lòng liên hệ cửa hàng để được hỗ trợ.'];
            }

            return ['ok' => false, 'error' => 'Mật khẩu không đúng.'];
        }

        // E2 — còn đơn chưa kết thúc.
        $soDon = OrderModel::countActive($userId);

        if ($soDon > 0) {
            return ['ok' => false, 'error' => sprintf(
                'Bạn còn %d đơn hàng chưa kết thúc nên chưa xoá tài khoản được. '
                . 'Vui lòng chờ đơn hoàn tất, hoặc liên hệ cửa hàng.',
                $soDon
            )];
        }

        /* E3 — CÒN TIỀN CỌC CHƯA XỬ LÝ.

           HỎI available() TRƯỚC KHI CHẠY CÂU LỆNH, không phải sau: câu dưới
           nhắc tới `refund_requests`, và trên một máy chưa chạy migration đợt 4
           thì đó là lỗi 1146 ném thẳng vào giữa luồng xoá tài khoản. Kiểm sau
           là kiểm một kết quả không bao giờ tới. */
        $soCoc = 0;

        if (RefundRequestModel::available()) {
            $soCoc = (int) Database::fetchValue(
                /* KHÔNG kèm `deposit_amount > 0` — bỏ ở bản sửa đợt 4.

                   `deposit_amount` là phần cọc PHẢI trả, và đơn chỉ mua gọng để
                   nó bằng 0 vì gọng không cần cọc. Nhưng khách vẫn chuyển khoản
                   đủ tiền cho một đơn như thế được, và khi ấy cửa hàng đang giữ
                   TRỌN số tiền ấy. Điều kiện cũ bỏ sót đúng ca đó: khách xoá
                   được tài khoản trong khi cửa hàng còn giữ tiền của họ và
                   không còn hồ sơ nào để liên hệ.

                   `payment_status` mới là cột trả lời "tiền đã về chưa" — cùng
                   một luật với RefundRequestModel::daNhan(). */
                "SELECT COUNT(*) FROM orders o
                  WHERE o.user_id = :uid
                    AND o.payment_status IN ('deposit_paid', 'paid')
                    AND o.status = 'cancelled'
                    AND NOT EXISTS (SELECT 1 FROM refund_requests r
                                     WHERE r.order_id = o.id
                                       AND r.status IN ('refunded', 'rejected'))",
                ['uid' => $userId]
            );
        }

        if ($soCoc > 0) {
            return ['ok' => false, 'error' =>
                'Bạn còn tiền cọc đang chờ cửa hàng xử lý hoàn trả nên chưa xoá '
                . 'tài khoản được. Vui lòng liên hệ cửa hàng.'];
        }

        /* E4 — LỊCH HẸN SẮP TỚI: cảnh báo một lần, không chặn.
 
           Trả về `canhBao` thay vì `error` để nơi gọi phân biệt được hai thứ:
           lần gửi thứ nhất hiện lời cảnh báo và một nút xác nhận, lần thứ hai
           mang cờ $daXacNhanLich và đi tiếp. */
        if (!$daXacNhanLich) {
            $soLich = (int) Database::fetchValue(
                "SELECT COUNT(*) FROM appointments
                  WHERE user_id = :uid
                    AND status NOT IN ('cancelled', 'done')
                    AND appointment_date >= CURDATE()",
                ['uid' => $userId]
            );

            if ($soLich > 0) {
                return ['ok' => false, 'canhBao' => sprintf(
                    'Bạn còn %d lịch hẹn sắp tới. Xoá tài khoản sẽ huỷ luôn %s. '
                    . 'Bấm xác nhận lần nữa nếu bạn vẫn muốn xoá.',
                    $soLich,
                    $soLich > 1 ? 'các lịch này' : 'lịch này'
                )];
            }
        }

        Database::transaction(static function () use ($userId): void {
            Database::execute(
                "UPDATE users
                    SET deleted_at = NOW(),
                        deletion_reason = 'Khách tự yêu cầu xoá tài khoản'
                  WHERE id = :id AND deleted_at IS NULL",
                ['id' => $userId]
            );

            /* NGUỒN YÊU CẦU — BR-TK-17.3.
 
               Bọc riêng vì cột đến ở migration đợt 4: máy chưa nâng cấp thì mất
               phần phân biệt "khách tự xoá / nhân viên xoá", không mất cả thao
               tác xoá mà khách vừa yêu cầu. */
            if (Database::columnExists('users', 'deleted_source')) {
                Database::execute(
                    "UPDATE users SET deleted_source = 'customer' WHERE id = :id",
                    ['id' => $userId]
                );
            }

            /* HUỶ LỊCH HẸN SẮP TỚI. Khách đã được cảnh báo ở E4 và đã xác nhận.
 
               Không xoá dòng: lịch hẹn đã huỷ vẫn là dữ liệu vận hành của cửa
               hàng (ai đặt, đặt rồi bỏ). Chỉ đổi trạng thái. */
            Database::execute(
                "UPDATE appointments
                    SET status = 'cancelled'
                  WHERE user_id = :uid
                    AND status NOT IN ('cancelled', 'done')
                    AND appointment_date >= CURDATE()",
                ['uid' => $userId]
            );
        });

        /* CẮT MỌI ĐƯỜNG VÀO CÒN LẠI.
 
           Không có dòng này thì "đã xoá" chỉ đúng với người chưa đăng nhập: ai
           đang giữ cookie ghi nhớ vẫn vào thẳng như thường, và vào được hàng
           tháng trời. Cùng lý lẽ với lock() và softDelete(). */
        RememberModel::forgetAllFor($userId);

        /* MÃ RIÊNG, không dùng lại 'soft_delete' — FR-NK-07.

           'soft_delete' là hành vi của CỬA HÀNG (nhân viên dọn tài khoản trùng
           lặp); đây là KHÁCH thực hiện quyền của họ. Gộp hai thứ vào một dòng
           nhật ký là xoá mất phần phân biệt duy nhất giữa chúng — và đó đúng là
           phần có hệ quả pháp lý. */
        AuditLogModel::write($userId, 'account.self_delete', 'Khách tự yêu cầu xoá tài khoản');

        return ['ok' => true];
    }

    public static function softDelete(string $id, string $reason = ''): array
    {
        if (self::detail($id) === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy khách hàng.'];
        }

        /* LÝ DO XOÁ KHÔNG BẮT BUỘC, khác hẳn lý do khoá.

           Khoá là một biện pháp đang có hiệu lực với một người vẫn là khách
           hàng, nên ba tháng sau sẽ có người phải giải thích nó qua điện thoại
           — vì thế lock() từ chối lý do rỗng. Xoá thì thường là dọn dẹp (trùng
           tài khoản, khách yêu cầu rút lui), không có ai gọi lại hỏi. Bắt gõ
           lý do cho mọi lần dọn dẹp chỉ đẻ ra một cột đầy chữ "xoá". */
        Database::execute(
            'UPDATE users
                SET deleted_at = NOW(), deletion_reason = :ly_do
              WHERE id = :id AND deleted_at IS NULL',
            ['ly_do' => $reason !== '' ? utf8Substr(trim($reason), 0, 500) : null, 'id' => $id]
        );

        // Cùng lý do với lock(): cookie ghi nhớ còn sống thì "đã xoá" chỉ đúng
        // trên màn hình quản trị.
        RememberModel::forgetAllFor($id);

        AuditLogModel::write($id, 'soft_delete');

        return ['ok' => true];
    }

    public static function restore(string $id): array
    {
        if (self::detail($id) === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy khách hàng.'];
        }

        // Xoá cả lý do, cùng lẽ với unlock(): cột này trả lời câu "vì sao tài
        // khoản NÀY đang bị xoá", mà nó đã không còn bị xoá. Lịch sử nằm ở
        // customer_audit_logs, chỗ giữ được nhiều lần chứ không chỉ lần cuối.
        Database::execute(
            'UPDATE users SET deleted_at = NULL, deletion_reason = NULL WHERE id = :id',
            ['id' => $id]
        );

        AuditLogModel::write($id, 'restore');

        return ['ok' => true];
    }

    // ========================================================================
    // NỘI BỘ
    // ========================================================================

    /**
     * Điều kiện "không phải tài khoản nội bộ".
     *
     * NOT EXISTS chứ không LEFT JOIN ... IS NULL: một người có hai vai trò sẽ
     * ra hai dòng trong phép nối, và câu đếm ở counts() đếm gấp đôi họ.
     *
     * Vai trò gõ thẳng vào chuỗi được vì chúng là hằng trong chính file này,
     * không đến từ dữ liệu — nhưng nếu có ngày chúng chuyển sang đọc từ cấu
     * hình thì phải đổi sang tham số ràng buộc ngay.
     *
     * GIỮ 'manager' VÀ 'technician' DÙ HAI VAI TRÒ ĐÓ ĐÃ GỠ — SRS v2.1.0.
     *
     * Danh sách này trả lời "ai KHÔNG phải khách hàng", và nó phải đúng cả
     * trong khoảng giữa lúc deploy mã và lúc chạy migration đợt 2. Bỏ hai giá
     * trị cũ ra sớm thì một tài khoản nội bộ chưa được chuyển vai trò sẽ hiện
     * trong danh sách khách hàng, kèm số điện thoại và lịch sử đơn của chính
     * nhân viên đó.
     *
     * Thừa hai giá trị không khớp dòng nào thì không hại gì; thiếu một giá trị
     * còn khớp thì rò một tài khoản. Hai chiều sai không cân nhau, nên chọn
     * chiều thừa.
     */
    private const KHONG_NOI_BO =
        "NOT EXISTS (SELECT 1 FROM user_roles r
                      WHERE r.user_id = u.id
                        AND r.role IN ('staff', 'technician', 'manager', 'admin'))";

    /**
     * Dựng mệnh đề WHERE dùng chung cho đếm, phân trang và xuất file.
     *
     * @return array{0:string, 1:array}
     */
    private static function buildFilter(string $q, string $filter): array
    {
        $dieuKien = [self::KHONG_NOI_BO];
        $params   = [];

        /* TÀI KHOẢN ĐÃ XOÁ MỀM MẶC ĐỊNH KHÔNG HIỆN, kể cả ở tab "Tất cả".
           "Tất cả" ở đây nghĩa là "mọi khách hàng", và một tài khoản đã xoá
           thì không còn là khách hàng — nó nằm lại chỉ để đơn hàng cũ còn chủ.
           Muốn xem thì có tab riêng. */
        $daXoa      = in_array($filter, self::LOC_DA_XOA, true);
        $dieuKien[] = $daXoa ? 'u.deleted_at IS NOT NULL' : 'u.deleted_at IS NULL';

        /* TÁCH HAI NGUỒN XOÁ — FR-KH-02.

           Chỉ siết thêm khi cột có. Chưa chạy migration đợt 4 thì hai tab cùng
           ra toàn bộ tài khoản đã xoá — trùng nhau, nhưng không mất dòng nào và
           không ném lỗi 1054 vào giữa màn danh sách.

           Vế 'nhân viên xoá' viết là "khác 'customer' hoặc NULL" chứ không phải
           "= 'staff'": lý do đầy đủ ở counts(). Tóm tắt — NULL là những dòng xoá
           TRƯỚC đợt 4 và migration cố ý không đoán giá trị cho chúng; nếu ở đây
           cũng không nhận chúng thì chúng rơi ra ngoài cả bốn tab và không còn
           đường nào mở được trong khu quản trị. */
        if ($daXoa && $filter !== 'deleted' && self::coNguonXoa()) {
            $dieuKien[] = $filter === 'deleted-customer'
                ? "u.deleted_source = 'customer'"
                : "(u.deleted_source <> 'customer' OR u.deleted_source IS NULL)";
        }

        if (isset(self::STATUSES[$filter])) {
            $dieuKien[]      = 'u.status = :trang_thai';
            $params['trang_thai'] = $filter;
        }

        $q = trim($q);

        if ($q !== '') {
            /* SỐ ĐIỆN THOẠI TÌM THEO CHÍN CHỮ SỐ CUỐI, không phải LIKE nguyên
               chuỗi. Nhân viên chép số từ tin nhắn hay từ đơn giấy sẽ dán vào
               đủ kiểu — "0912 345 678", "+84912345678" — mà cột `phone` lưu
               dạng đã chuẩn hoá "0912345678". LIKE nguyên chuỗi thì cả ba cách
               dán đó đều không ra gì, và người tìm sẽ kết luận là khách chưa
               có tài khoản.

               Chín chứ không mười: chữ số 0 dẫn đầu chính là thứ biến mất khi
               có mã quốc gia. */
            $chuSo = preg_replace('/\D+/', '', $q) ?? '';

            if (strlen($chuSo) >= 9) {
                $dieuKien[]     = 'RIGHT(p.phone, 9) = :duoi_sdt';
                $params['duoi_sdt'] = substr($chuSo, -9);
            } else {
                /* BA THAM SỐ RIÊNG CHO CÙNG MỘT GIÁ TRỊ, không dùng lại
                   :tim ba lần.

                   core/Database.php đặt ATTR_EMULATE_PREPARES = false, tức là
                   câu lệnh do MySQL tự phân tích chứ không do PDO ghép chuỗi.
                   Ở chế độ đó, một tên tham số chỉ gắn được vào MỘT vị trí;
                   lặp lại thì PDO ném SQLSTATE[HY093] "Invalid parameter
                   number" — và nó chỉ ném khi có người GÕ TÌM KIẾM, tức là
                   không lộ ra ở lượt mở trang bình thường. */
                $dieuKien[] = '(p.full_name LIKE :tim_ten
                             OR u.email     LIKE :tim_mail
                             OR p.phone     LIKE :tim_sdt)';

                // Thoát % và _ trước khi ghép: khách tên "100%" mà không thoát
                // thì dấu % thành ký tự đại diện và câu tìm trả về cả bảng.
                $mau = '%' . addcslashes($q, '%_\\') . '%';

                $params['tim_ten']  = $mau;
                $params['tim_mail'] = $mau;
                $params['tim_sdt']  = $mau;
            }
        }

        return ['WHERE ' . implode("\n   AND ", $dieuKien), $params];
    }

    /** `contact_requests` đã có cột `user_id` chưa (migration 2026-08-26). */
    private static function coCotLienHe(): bool
    {
        static $co = null;

        if ($co !== null) {
            return $co;
        }

        return $co = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'contact_requests'
                AND COLUMN_NAME  = 'user_id'"
        ) === 1;
    }
}
