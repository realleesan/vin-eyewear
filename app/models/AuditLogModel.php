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
    ];

    public static function available(): bool
    {
        return Database::tableExists(static::$table);
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
