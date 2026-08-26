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
 * Nghe thì ngược, nhưng đây là cách duy nhất đúng: một quản lý cửa hàng cũng
 * mua kính cho vợ nên tài khoản của họ có CẢ 'customer' lẫn 'manager'. Lọc
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
        'active'  => 'Hoạt động',
        'locked'  => 'Đã khoá',
        'deleted' => 'Đã xoá',
    ];

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
        $sql = 'SELECT u.id, u.email, u.status, u.deleted_at, u.created_at, u.last_login_at,
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

    /** Số lượng cho dải lọc. Một câu lệnh chứ không bốn. */
    public static function counts(): array
    {
        $row = Database::fetchOne(
            'SELECT COUNT(*)                                            AS tat_ca,
                    SUM(u.deleted_at IS NULL AND u.status = \'active\')  AS active,
                    SUM(u.deleted_at IS NULL AND u.status = \'locked\')  AS locked,
                    SUM(u.deleted_at IS NOT NULL)                       AS deleted
               FROM users u
              WHERE ' . self::KHONG_NOI_BO
        ) ?? [];

        return [
            // Khoá '' là "Tất cả" của admin/_layout/filter-tabs.php. Nó KHÔNG
            // đếm tài khoản đã xoá mềm — xem buildFilter().
            ''        => (int) ($row['active'] ?? 0) + (int) ($row['locked'] ?? 0),
            'active'  => (int) ($row['active'] ?? 0),
            'locked'  => (int) ($row['locked'] ?? 0),
            'deleted' => (int) ($row['deleted'] ?? 0),
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
           có từ lược đồ gốc. */
        $contacts = self::coCotLienHe()
            ? Database::fetchAll(
                'SELECT id, message, status, created_at
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

    /**
     * Sửa hồ sơ.
     *
     * KHÔNG tự viết lại phần kiểm tra: UserModel::updateProfile() và
     * ::updateEmail() đã có đủ luật (chuẩn hoá số điện thoại, chặn trùng số,
     * chặn trùng email, dập cờ email_verified khi đổi địa chỉ) và câu thông
     * báo tiếng Việt của chúng đã nghiệm thu với khách hàng. Chép lại ở đây là
     * tạo phiên bản thứ hai của cùng một luật, rồi hai bên sẽ lệch nhau.
     *
     * Ở đây chỉ thêm đúng thứ hai hàm kia không có: kiểm ngày sinh.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function saveProfile(string $id, array $input): array
    {
        if (self::detail($id) === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy khách hàng.'];
        }

        $dob = trim((string) ($input['date_of_birth'] ?? ''));

        if ($dob !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $dob);

            // checkdate qua getLastErrors: createFromFormat nhận cả '2026-02-31'
            // rồi tự trôi sang 03/03. Không bắt ở đây thì một ngày gõ nhầm được
            // lưu thành một ngày khác hẳn mà không ai báo gì.
            $loi = DateTime::getLastErrors();

            if ($d === false || ($loi !== false && ($loi['warning_count'] ?? 0) > 0)) {
                return ['ok' => false, 'error' => 'Ngày sinh không hợp lệ.'];
            }

            if ($d > new DateTime('today')) {
                return ['ok' => false, 'error' => 'Ngày sinh không được ở tương lai.'];
            }
        }

        // Email đi trước: nó có thể bị từ chối vì trùng, và khi đó KHÔNG nên
        // đã kịp ghi tên với số điện thoại rồi mới báo lỗi — người bấm Lưu sẽ
        // thấy nửa form đã đổi, nửa kia thì không.
        $mail = UserModel::updateEmail($id, (string) ($input['email'] ?? ''));

        if (!$mail['ok']) {
            return $mail;
        }

        $ket = UserModel::updateProfile($id, [
            'full_name'     => trim((string) ($input['full_name'] ?? '')) ?: null,
            'phone'         => (string) ($input['phone'] ?? ''),
            'date_of_birth' => $dob !== '' ? $dob : null,
            'gender'        => (string) ($input['gender'] ?? ''),
        ]);

        if ($ket['ok']) {
            AuditLogModel::write($id, 'profile.update');
        }

        return $ket;
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
        $reason = trim($reason);

        if ($reason === '') {
            return ['ok' => false, 'error' => 'Phải ghi lý do khoá tài khoản.'];
        }

        $khach = self::detail($id);

        if ($khach === null) {
            return ['ok' => false, 'error' => 'Không tìm thấy khách hàng.'];
        }

        Database::execute(
            "UPDATE users
                SET status = 'locked', locked_reason = :ly_do,
                    locked_at = NOW(), locked_by = :boi
              WHERE id = :id",
            ['ly_do' => utf8Substr($reason, 0, 255), 'boi' => $actorId, 'id' => $id]
        );

        /* CẮT MỌI PHIÊN "GHI NHỚ ĐĂNG NHẬP" CỦA NGƯỜI NÀY.
           Không có dòng này thì khoá tài khoản chỉ chặn được người chưa đăng
           nhập: ai đang giữ cookie ghi nhớ vẫn vào thẳng như thường, và có thể
           vào như thế hàng tháng trời. Đúng cái mà nút khoá phải ngăn. */
        RememberModel::forgetAllFor($id);

        AuditLogModel::write($id, 'lock', $reason);

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
     * Ba vai trò gõ thẳng vào chuỗi được vì chúng là hằng trong chính file
     * này, không đến từ dữ liệu — nhưng nếu có ngày chúng chuyển sang đọc từ
     * cấu hình thì phải đổi sang tham số ràng buộc ngay.
     */
    private const KHONG_NOI_BO =
        "NOT EXISTS (SELECT 1 FROM user_roles r
                      WHERE r.user_id = u.id
                        AND r.role IN ('staff', 'manager', 'admin'))";

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
        $dieuKien[] = $filter === 'deleted' ? 'u.deleted_at IS NOT NULL' : 'u.deleted_at IS NULL';

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
