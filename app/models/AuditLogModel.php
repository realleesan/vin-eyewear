<?php

/**
 * AuditLogModel — vết thao tác trên dữ liệu khách hàng.
 *
 * CLAUDE.md mục 5: dữ liệu đơn thuốc kính là dữ liệu y tế, MỌI thao tác đọc và
 * ghi đều phải có vết. Model này là chỗ duy nhất ghi bảng `customer_audit_logs`.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO write() KHÔNG NÉM LỖI RA NGOÀI
 *
 * Nó được gọi ngay cạnh việc chính (mở tab đơn thuốc, bấm khoá tài khoản). Để
 * nó ném ra thì một bảng chưa được tạo trên hosting sẽ làm cả trang quản trị
 * đổ 500 — đúng kiểu hỏng đã xảy ra ngày 2026-08-22 với năm cột wear_*.
 *
 * NHƯNG NUỐT LỖI IM LẶNG THÌ CŨNG SAI: một bảng vết không ghi được mà không ai
 * biết còn tệ hơn không có bảng vết, vì nó tạo cảm giác an toàn giả. Nên:
 *
 *   · thiếu bảng      -> available() trả false, và GIAO DIỆN PHẢI NÓI RA.
 *                        Xem dải cảnh báo ở tab Đơn thuốc kính.
 *   · lỗi khác        -> error_log() rồi đi tiếp.
 *
 * Đây là đánh đổi có chủ ý, không phải chỗ quên xử lý lỗi.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class AuditLogModel extends BaseModel
{
    protected static string $table = 'customer_audit_logs';

    /**
     * Nhãn tiếng Việt của từng hành động.
     *
     * Khoá phải khớp giá trị ghi vào cột `action`. Thiếu một khoá thì view in
     * ra mã thô — xấu, nhưng không vỡ.
     */
    public const ACTIONS = [
        'rx.read'        => 'Xem đơn thuốc kính',
        'rx.create'      => 'Thêm đơn thuốc kính',
        'rx.update'      => 'Sửa đơn thuốc kính',
        'rx.delete'      => 'Xoá đơn thuốc kính',
        // NĂM KHOÁ DƯỚI ĐÂY GIỮ LẠI CHO VẾT CŨ, không còn ai ghi mới: ngày
        // 2026-08-28 khu quản trị bỏ quyền sửa hồ sơ và sổ địa chỉ của khách,
        // và bỏ hẳn phần ghi chú nội bộ. Xoá chúng đi thì những dòng đã ghi
        // trước đó in ra mã thô — mà đó đúng là loại vết cần đọc được nhất:
        // ai đã đổi số điện thoại hay địa chỉ của khách, hồi nào.
        'profile.update' => 'Sửa hồ sơ',
        'address.save'   => 'Lưu địa chỉ',
        'address.delete' => 'Xoá địa chỉ',
        'note.save'      => 'Lưu ghi chú nội bộ',
        'note.delete'    => 'Xoá ghi chú nội bộ',
        'lock'           => 'Khoá tài khoản',
        'unlock'         => 'Mở khoá tài khoản',
        'soft_delete'    => 'Xoá tài khoản (xoá mềm)',
        'restore'        => 'Khôi phục tài khoản',
        // Cùng loại với năm khoá trên: bỏ nút gửi email đặt lại mật khẩu
        // ngày 2026-08-28, nhưng vết đã ghi thì phải còn đọc được.
        'reset_email'    => 'Gửi liên kết đặt lại mật khẩu',
        'export'         => 'Xuất danh sách khách hàng',

        /* ── VẾT CHO THAO TÁC TIỀN VÀ KHO — SNFR-11 ────────────────────────
           SNFR-11 liệt kê đích danh bốn nhóm phải có vết: tạo/sửa/xoá hồ sơ
           khúc xạ, CẬP NHẬT TRẠNG THÁI CỌC, ĐIỀU CHỈNH KHO ẢO, HUỶ ĐƠN HÀNG.
           Bốn khoá rx.* ở trên lo nhóm đầu; ba nhóm còn lại trước bản này
           không để lại vết nào — đánh dấu "đã thu tiền" của một đơn là thao
           tác ra tiền thật mà không ai biết ai bấm, lúc nào.

           Bảng vết vốn viết cho dữ liệu khách hàng nên cột tên là `user_id`;
           với các vết dưới đây nó mang id CHỦ ĐƠN (NULL với khách vãng lai và
           với thao tác kho, vì kho không thuộc về khách nào). Cột `actor_id`
           mới là thứ trả lời "ai bấm", và nó đã đúng sẵn. */
        'payment.paid'    => 'Đánh dấu đã thanh toán',
        'payment.unpaid'  => 'Gỡ đánh dấu thanh toán',
        'payment.deposit' => 'Ghi nhận tiền cọc',
        'order.status'    => 'Đổi trạng thái đơn hàng',
        'order.cancel'    => 'Huỷ đơn hàng',
        'stock.adjust'    => 'Điều chỉnh tồn kho',

        /* TÀI KHOẢN NỘI BỘ — mở theo Quyết định Q80.1 (04/09/2026), vế "mở
           rộng ghi vết sang đăng nhập nội bộ, phân quyền, cấu hình hệ thống".

           Mã đầu tiên của nhóm này là thao tác đi VÒNG QUA một biện pháp bảo
           mật: mở khoá 15 phút của SNFR-06 trước hạn. Thứ đó bắt buộc phải có
           vết — không có thì một quản trị viên tự mở khoá cho mình rồi tiếp
           tục dò mật khẩu là chuyện không ai đọc lại được. */
        'staff.unlock_login' => 'Mở khoá đăng nhập nội bộ',
    ];

    /**
     * Gom hành động thành PHÂN HỆ để lọc.
     *
     * Người mở màn Lịch sử thao tác gần như không bao giờ hỏi "cho tôi xem
     * hành động rx.update"; họ hỏi "tuần này ai đụng vào hồ sơ khúc xạ" hoặc
     * "ai sửa trạng thái tiền của đơn này". Một danh sách 20 mã hành động thô
     * bắt họ tự dịch câu hỏi đó sang mã — nhóm lại thì bấm một cái là xong,
     * và vẫn lọc được từng mã ở ô bên cạnh.
     *
     * Mã nào không nằm trong bảng này rơi vào nhóm 'khac'. Cố ý không ném lỗi:
     * thêm một mã hành động mới mà quên khai ở đây thì màn hình vẫn đọc được,
     * chỉ là mã đó nằm ở nhóm Khác cho tới khi có người xếp lại.
     */
    public const NHOM = [
        'khuc-xa'  => ['nhan' => 'Hồ sơ khúc xạ', 'actions' => ['rx.read', 'rx.create', 'rx.update', 'rx.delete']],
        'tai-khoan' => ['nhan' => 'Tài khoản khách', 'actions' => [
            'lock', 'unlock', 'soft_delete', 'restore', 'reset_email', 'export',
            'profile.update', 'address.save', 'address.delete', 'note.save', 'note.delete',
        ]],
        'tien'     => ['nhan' => 'Đơn hàng và tiền', 'actions' => [
            'payment.paid', 'payment.unpaid', 'payment.deposit', 'order.status', 'order.cancel',
        ]],
        'kho'      => ['nhan' => 'Tồn kho', 'actions' => ['stock.adjust']],
        'noi-bo'   => ['nhan' => 'Tài khoản nội bộ', 'actions' => ['staff.unlock_login']],
    ];

    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /** Mã hành động thuộc nhóm nào. */
    public static function nhomCua(string $action): string
    {
        foreach (self::NHOM as $khoa => $n) {
            if (in_array($action, $n['actions'], true)) {
                return $khoa;
            }
        }

        return 'khac';
    }

    /**
     * Dựng mệnh đề WHERE dùng chung cho đếm, phân trang và xuất file.
     *
     * MỘT CHỖ DỰNG DUY NHẤT. Ba nơi gọi phải lọc y hệt nhau, nếu không thì con
     * số trên viên lọc nói một đằng, bảng hiện một nẻo, và file xuất ra lại
     * khác cả hai — kiểu sai không ai phát hiện cho tới lúc đối soát.
     *
     * @param array $loc nhom, action, actor, tu, den, q
     * @return array{0:string, 1:array} mệnh đề WHERE (rỗng nếu không lọc) và tham số
     */
    private static function dieuKien(array $loc): array
    {
        $ve = [];
        $ts = [];

        // Lọc theo nhóm = lọc theo tập mã hành động của nhóm đó. Tên cột được
        // sinh từ chỉ số vòng lặp chứ không từ dữ liệu người dùng, nên không
        // có đường nào để một chuỗi lạ lọt vào câu SQL.
        if (isset(self::NHOM[$loc['nhom'] ?? ''])) {
            $ma = self::NHOM[$loc['nhom']]['actions'];
            $o  = [];

            foreach (array_values($ma) as $i => $a) {
                $o[] = ':nhom' . $i;
                $ts['nhom' . $i] = $a;
            }

            $ve[] = 'l.action IN (' . implode(', ', $o) . ')';
        }

        if (($loc['action'] ?? '') !== '' && isset(self::ACTIONS[$loc['action']])) {
            $ve[] = 'l.action = :action';
            $ts['action'] = $loc['action'];
        }

        if (($loc['actor'] ?? '') !== '') {
            $ve[] = 'l.actor_id = :actor';
            $ts['actor'] = $loc['actor'];
        }

        /* Ngày TỪ lấy từ 00:00, ngày ĐẾN lấy tới hết 23:59:59.

           Dùng thẳng `l.created_at >= :tu` với chuỗi 'YYYY-MM-DD' thì MySQL tự
           hiểu là 00:00 — đúng. Nhưng vế ĐẾN mà so với 'YYYY-MM-DD' thì thành
           00:00 của chính ngày đó, tức là lọc "đến ngày 5" sẽ RỚT HẾT vết của
           ngày 5. Nối thêm giờ vào cho khỏi bẫy. */
        if (($loc['tu'] ?? '') !== '') {
            $ve[] = 'l.created_at >= :tu';
            $ts['tu'] = $loc['tu'] . ' 00:00:00';
        }

        if (($loc['den'] ?? '') !== '') {
            $ve[] = 'l.created_at <= :den';
            $ts['den'] = $loc['den'] . ' 23:59:59';
        }

        if (($loc['q'] ?? '') !== '') {
            /* Tìm theo tên người thao tác, tên và số điện thoại của khách bị
               tác động, hoặc IP — bốn thứ người đọc nhật ký thật sự gõ vào ô
               tìm.

               BỐN TÊN THAM SỐ KHÁC NHAU CHO CÙNG MỘT GIÁ TRỊ, ĐỪNG GỘP LẠI.
               Dự án tắt chế độ giả lập của PDO (xem config/database.php), nên
               câu lệnh đi thẳng xuống MySQL — mà prepared statement thật KHÔNG
               cho lặp lại một tên tham số. Viết `:q` bốn lần thì PDO chỉ ràng
               buộc một chỗ và ném "Invalid parameter number", tức là ô tìm
               kiếm đổ lỗi ngay lần gõ đầu tiên.

               Bản đầu của hàm này viết `:q` bốn lần thật, và bệ thử bắt được.
               Cùng loại bẫy với `:max`/`:max2` trong LoginAttemptModel. */
            $tim = '%' . $loc['q'] . '%';
            $ve[] = '(l.actor_name LIKE :q1 OR p.full_name LIKE :q2'
                  . ' OR p.phone LIKE :q3 OR l.ip LIKE :q4)';
            $ts['q1'] = $tim;
            $ts['q2'] = $tim;
            $ts['q3'] = $tim;
            $ts['q4'] = $tim;
        }

        return [$ve === [] ? '' : ' WHERE ' . implode(' AND ', $ve), $ts];
    }

    /**
     * Nguồn dữ liệu chung: bảng vết nối sang hồ sơ khách bị tác động.
     *
     * LEFT JOIN chứ không JOIN: `user_id` để NULL với những vết không thuộc về
     * khách nào — điều chỉnh tồn kho là ví dụ. JOIN thường sẽ nuốt sạch nhóm
     * Tồn kho khỏi màn hình mà không báo gì.
     */
    private const TU = ' FROM customer_audit_logs l LEFT JOIN profiles p ON p.id = l.user_id';

    /**
     * Danh sách vết toàn hệ thống, mới nhất trước.
     *
     * @return array{items:array, total:int, page:int, totalPages:int}
     */
    public static function paginateAll(int $page, int $perPage, array $loc): array
    {
        if (!self::available()) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'totalPages' => 1];
        }

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        [$where, $ts] = self::dieuKien($loc);

        $total = (int) Database::fetchValue('SELECT COUNT(*)' . self::TU . $where, $ts);

        /* LIMIT và OFFSET nội suy thẳng sau khi ép (int), không ràng buộc tham
           số: MySQL không nhận placeholder ở hai chỗ này khi PDO tắt chế độ
           giả lập. Cùng cách làm với BaseModel::paginate() và forUser(). */
        $items = Database::fetchAll(
            'SELECT l.*, p.full_name AS khach_ten, p.phone AS khach_sdt'
            . self::TU . $where
            . ' ORDER BY l.created_at DESC, l.id DESC'
            . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $ts
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Đếm vết theo từng nhóm, ĐỂ NGUYÊN các bộ lọc khác.
     *
     * Giữ nguyên bộ lọc khác là có chủ ý: người đang lọc "tuần trước" cần biết
     * tuần trước mỗi nhóm có bao nhiêu vết, không phải tổng của mọi thời gian.
     */
    public static function demTheoNhom(array $loc): array
    {
        if (!self::available()) {
            return [];
        }

        $ra = ['' => 0];

        foreach (array_keys(self::NHOM) as $khoa) {
            [$where, $ts] = self::dieuKien(['nhom' => $khoa] + $loc);
            $ra[$khoa] = (int) Database::fetchValue('SELECT COUNT(*)' . self::TU . $where, $ts);
        }

        [$where, $ts] = self::dieuKien(['nhom' => ''] + $loc);
        $ra[''] = (int) Database::fetchValue('SELECT COUNT(*)' . self::TU . $where, $ts);

        return $ra;
    }

    /**
     * Những người đã từng thao tác — để đổ vào ô lọc "Người thực hiện".
     *
     * Đọc từ chính bảng vết chứ không từ danh sách nhân viên đang có: người đã
     * nghỉ việc và bị xoá tài khoản vẫn phải tra được, vì vết của họ còn đó.
     * actor_name đã được chép lại lúc ghi nên vẫn đọc được tên.
     */
    public static function dsNguoiThaoTac(): array
    {
        if (!self::available()) {
            return [];
        }

        /* ORDER BY LẶP LẠI MAX(actor_name), KHÔNG DÙNG BÍ DANH `ten`.

           MySQL cho dùng bí danh của cột thường trong ORDER BY, nhưng KHÔNG
           cho khi bí danh đó trỏ vào một hàm gộp: `ORDER BY ten` ném
           "Reference 'ten' not supported (reference to group function)". Bản
           đầu viết vậy thật và ô lọc "Người thực hiện" đổ lỗi ngay khi mở
           trang — bệ thử bắt được.

           MAX() chứ không phải cột trần: actor_name được chép lại tại thời
           điểm ghi vết, nên một người đổi tên hiển thị sẽ có nhiều tên khác
           nhau trong bảng. Lấy MAX là lấy một cái tên xác định thay vì để
           MySQL tự chọn bừa. */
        return Database::fetchAll(
            "SELECT actor_id, MAX(actor_name) AS ten, COUNT(*) AS so_luot
               FROM customer_audit_logs
              WHERE actor_id IS NOT NULL
              GROUP BY actor_id
              ORDER BY MAX(actor_name) IS NULL, MAX(actor_name)"
        );
    }

    /**
     * Dữ liệu cho nút xuất CSV.
     *
     * CÓ TRẦN CỨNG. Bảng vết chỉ có thêm chứ không bớt, nên sau một năm nó là
     * bảng lớn nhất hệ thống. Một câu SELECT không giới hạn trên hosting chia
     * sẻ là hết bộ nhớ giữa chừng, và người bấm nút chỉ thấy trang trắng.
     * Trần này áp SAU bộ lọc, nên muốn lấy đủ thì lọc hẹp lại theo khoảng ngày.
     */
    public const TRAN_XUAT = 5000;

    public static function deXuat(array $loc): array
    {
        if (!self::available()) {
            return [];
        }

        [$where, $ts] = self::dieuKien($loc);

        return Database::fetchAll(
            'SELECT l.created_at, l.action, l.actor_name, l.detail, l.ip,'
            . ' p.full_name AS khach_ten, p.phone AS khach_sdt'
            . self::TU . $where
            . ' ORDER BY l.created_at DESC, l.id DESC'
            . ' LIMIT ' . self::TRAN_XUAT,
            $ts
        );
    }

    /**
     * Ghi một vết.
     *
     * $detail KHÔNG ĐƯỢC chứa nội dung số đo. Bảng vết mà chứa chính dữ liệu y
     * tế thì nó thành bản sao thứ hai của thứ đang cần bảo vệ, và bản sao đó
     * không được ai canh. Viết "Đã sửa bản ghi đo ngày 12/03/2026", đừng viết
     * "OD -2.25".
     */
    public static function write(?string $userId, string $action, ?string $detail = null): void
    {
        if (!self::available()) {
            return;
        }

        try {
            /* false = CHỈ HỎI, không huỷ phiên và không gia hạn phiên.

               Ghi log mà đăng xuất được người dùng thì sai vai; và hàm này có
               thể chạy giữa lúc dựng trang, lúc đó huỷ phiên là phần view còn
               lại mất flash lẫn token CSRF. Xem chú thích ở staffId(). */
            $actorId = AuthMiddleware::staffId(false);

            /* Tên người thao tác CHÉP LẠI tại đây, không join lúc đọc: người
               này có thể nghỉ việc và bị xoá tài khoản, lúc đó actor_id thành
               NULL và vết mất luôn chủ. Cùng lẽ với order_items.product_name. */
            $actorName = null;

            if ($actorId !== null) {
                $who = UserModel::profile($actorId);
                $actorName = $who['full_name'] ?? $who['email'] ?? null;
            }

            Database::execute(
                'INSERT INTO customer_audit_logs
                     (id, user_id, actor_id, actor_name, action, detail, ip)
                 VALUES (:id, :uid, :aid, :aname, :action, :detail, :ip)',
                [
                    'id'     => uuid(),
                    'uid'    => $userId,
                    'aid'    => $actorId,
                    'aname'  => $actorName !== null ? utf8Substr($actorName, 0, 255) : null,
                    'action' => $action,
                    'detail' => $detail !== null ? utf8Substr($detail, 0, 255) : null,
                    'ip'     => self::clientIp(),
                ]
            );
        } catch (Throwable $e) {
            error_log('AuditLogModel::write: ' . $e->getMessage());
        }
    }

    /** Vết của một khách, mới nhất trước. */
    public static function forUser(string $userId, int $limit = 50): array
    {
        if (!self::available()) {
            return [];
        }

        /* $limit nội suy thẳng vào chuỗi SQL chứ không ràng buộc tham số: MySQL
           không cho LIMIT :ph khi PDO chạy ở chế độ emulate prepares tắt. Ép
           (int) ngay tại đây là đủ an toàn — không có đường nào để một chuỗi
           lọt qua phép ép kiểu đó. Cùng cách làm với BaseModel::paginate(). */
        return Database::fetchAll(
            'SELECT * FROM customer_audit_logs
              WHERE user_id = :uid
              ORDER BY created_at DESC
              LIMIT ' . max(1, (int) $limit),
            ['uid' => $userId]
        );
    }

    /**
     * Địa chỉ IP của người đang thao tác.
     *
     * KHÔNG đọc X-Forwarded-For: header đó do client gửi và giả được thoải mái,
     * nên một vết ghi theo nó là một vết trỏ vào bất kỳ đâu kẻ ghi muốn. Trên
     * hosting có proxy đứng trước thì REMOTE_ADDR sẽ là IP của proxy — kém chi
     * tiết hơn, nhưng không bịa.
     */
    private static function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? utf8Substr($ip, 0, 45) : null;
    }
}
