<?php

/**
 * StaffStoreModel — phân công tài khoản nội bộ theo CƠ SỞ (`staff_stores`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ RÀNG BUỘC QUYỀN, KHÔNG PHẢI MỘT BỘ LỌC
 *
 * SNFR-07b (SRS v1.3.1) nói rõ: phạm vi cơ sở phải được cưỡng chế Ở MÁY CHỦ
 * trong mọi truy vấn đơn hàng và lịch hẹn. Màn Lịch hẹn từ trước đã có ô chọn
 * cơ sở, và đó chính là chỗ dễ hiểu nhầm nhất — ô đó là tiện ích cho người
 * dùng, bỏ chọn là thấy hết. Hai thứ trông giống nhau trên màn hình nhưng khác
 * hẳn nhau về bản chất:
 *
 *     bộ lọc      người dùng chọn, bỏ chọn được, phục vụ sự tiện
 *     phạm vi     máy chủ áp, người dùng không gỡ được, phục vụ sự an toàn
 *
 * Nên hàm dựng mệnh đề ở dưới KHÔNG nhận tham số từ request. Nó chỉ đọc bảng
 * phân công của chính người đang đăng nhập.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA TRẠNG THÁI, KHÔNG PHẢI HAI
 *
 *     null          KHÔNG giới hạn — Quản trị viên, thấy toàn bộ hệ thống.
 *     []            KHÔNG THẤY GÌ — tài khoản nội bộ chưa được gán cơ sở nào.
 *     ['id', …]     chỉ những cơ sở này.
 *
 * Phân biệt null với [] là điểm dễ sai nhất khi dùng lớp này: `if (!$phamVi)`
 * gộp cả hai lại và biến "chưa gán nên không thấy gì" thành "thấy tất cả" —
 * đúng ngược lại thứ Q12.3 chốt. Luôn so sánh `=== null`.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐƠN VÀ LỊCH KHÔNG GẮN CƠ SỞ THÌ AI THẤY
 *
 * `orders.store_id` để NULL với đơn giao tận nơi — đơn đó không thuộc cơ sở
 * nào cả. Giấu chúng khỏi mọi nhân viên nghĩa là không ai xử lý được đơn giao
 * hàng, tức là làm hỏng việc bán hàng để đổi lấy một quy tắc.
 *
 * Nên dòng NULL hiện cho MỌI tài khoản đã được gán ít nhất một cơ sở. Người
 * chưa được gán vẫn không thấy gì, đúng Q12.3.
 *
 * ⚠ SRS v1.3.1 KHÔNG nói về trường hợp này — Q12 chỉ bàn tới nhân viên và cơ
 * sở, không bàn tới dữ liệu không thuộc cơ sở nào. Cách làm ở đây là lựa chọn
 * của nhóm phát triển, ghi lại để BA xác nhận hoặc bác. Siết lại sau là sửa
 * đúng một dòng trong mệnh đề dưới đây.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class StaffStoreModel extends BaseModel
{
    protected static string $table = 'staff_stores';

    /**
     * Vai trò KHÔNG bị giới hạn phạm vi.
     *
     * Chỉ 'admin'. X31 đã bỏ vai trò "Chủ doanh nghiệp" và cho dùng chung
     * quyền Quản trị viên, nên danh sách này chỉ có một phần tử — giữ dạng
     * mảng để thêm vai trò sau không phải sửa hình dạng của phép kiểm.
     */
    public const KHONG_GIOI_HAN = ['admin'];

    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /**
     * Danh sách id cơ sở của một tài khoản nội bộ.
     *
     * @return string[]
     */
    public static function forUser(string $userId): array
    {
        if (!self::available()) {
            return [];
        }

        return array_column(
            Database::fetchAll(
                'SELECT store_id FROM staff_stores WHERE user_id = :uid',
                ['uid' => $userId]
            ),
            'store_id'
        );
    }

    /**
     * Cơ sở kèm tên, để hiển thị. Dùng ở màn Tài khoản nội bộ.
     */
    public static function withNames(string $userId): array
    {
        if (!self::available()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT s.id, s.name, s.code
               FROM staff_stores ss
               JOIN stores s ON s.id = ss.store_id
              WHERE ss.user_id = :uid
              ORDER BY s.name ASC',
            ['uid' => $userId]
        );
    }

    /**
     * PHẠM VI của người đang đăng nhập — null · [] · [id, …].
     *
     * Đọc vai trò từ CSDL mỗi lần chứ không lấy từ phiên: phiên sống hàng giờ,
     * nên người vừa bị gỡ quyền vẫn giữ phạm vi cũ tới khi tự đăng xuất. Cùng
     * lý lẽ với AuthMiddleware::requireStaff().
     *
     * CHƯA CHẠY MIGRATION THÌ TRẢ null. Bảng chưa có nghĩa là chưa ai được gán
     * cơ sở nào; siết theo Q12.3 lúc đó là khoá toàn bộ nhân viên ra ngoài chỉ
     * vì một file .sql chưa chạy. Trả null giữ nguyên hành vi cũ và không ném
     * lỗi — cùng nếp phòng thủ với LoginAttemptModel::available().
     *
     * @return string[]|null
     */
    public static function phamVi(string $userId): ?array
    {
        foreach (self::KHONG_GIOI_HAN as $vaiTro) {
            if (UserModel::hasRole($userId, $vaiTro)) {
                return null;
            }
        }

        if (!self::available()) {
            return null;
        }

        return self::forUser($userId);
    }

    /**
     * Dựng mệnh đề WHERE cho một cột cơ sở, theo phạm vi đã cho.
     *
     * @param  string[]|null $phamVi kết quả của phamVi()
     * @param  string        $cot    tên cột đầy đủ, ví dụ 'o.store_id'
     * @param  string        $tien   tiền tố tên tham số, phải khác nhau giữa
     *                               hai lần gọi trong cùng một câu lệnh
     * @return array{0: ?string, 1: array<string, string>} [mệnh đề, tham số]
     */
    public static function menhDe(?array $phamVi, string $cot, string $tien = 'pv'): array
    {
        // Không giới hạn: không thêm điều kiện nào.
        if ($phamVi === null) {
            return [null, []];
        }

        /* CHƯA ĐƯỢC GÁN CƠ SỞ NÀO THÌ KHÔNG THẤY GÌ — Q12.3.

           '1 = 0' chứ không phải trả về danh sách rỗng rồi để nơi gọi tự lo:
           mỗi nơi gọi tự lo là mỗi nơi có một cơ hội quên, và cái quên đó
           không gây lỗi mà chỉ lặng lẽ mở toang dữ liệu. */
        if ($phamVi === []) {
            return ['1 = 0', []];
        }

        $oCho = [];
        $params = [];

        foreach (array_values($phamVi) as $i => $id) {
            $ten = $tien . $i;
            $oCho[] = ':' . $ten;
            $params[$ten] = $id;
        }

        /* DÒNG KHÔNG GẮN CƠ SỞ VẪN HIỆN. Xem khối chú thích đầu file — đây là
           dòng phải sửa nếu BA muốn siết. */
        return ['(' . $cot . ' IN (' . implode(', ', $oCho) . ') OR ' . $cot . ' IS NULL)', $params];
    }

    /**
     * Ghi đè toàn bộ phân công của một tài khoản.
     *
     * XOÁ RỒI THÊM trong một transaction, không so sánh từng dòng: danh sách
     * gửi lên từ form là TOÀN BỘ ý muốn của người bấm, nên chênh lệch từng
     * dòng chỉ là cách phức tạp hơn để tới cùng một kết quả. Bọc transaction
     * để không có khoảnh khắc nào tài khoản đó mất sạch phạm vi giữa chừng —
     * khoảnh khắc ấy mà trùng một request khác là người kia thấy trang trống.
     *
     * @param string[] $storeIds
     */
    public static function setForUser(string $userId, array $storeIds, string $actorId): void
    {
        if (!self::available()) {
            return;
        }

        Database::transaction(static function () use ($userId, $storeIds, $actorId): void {
            Database::execute('DELETE FROM staff_stores WHERE user_id = :uid', ['uid' => $userId]);

            foreach (array_unique($storeIds) as $sid) {
                $sid = trim((string) $sid);

                if ($sid === '') {
                    continue;
                }

                /* INSERT IGNORE: id cơ sở đến từ ô tick trên form, mà form thì
                   sửa được. Một id không có thật làm khoá ngoại ném lỗi 1452 và
                   kéo đổ cả transaction — tức là bấm Lưu một lần với dữ liệu
                   bịa là mất luôn phân công thật. IGNORE biến nó thành bỏ qua. */
                Database::execute(
                    'INSERT IGNORE INTO staff_stores (id, user_id, store_id, granted_by)
                     VALUES (:id, :uid, :sid, :by)',
                    ['id' => uuid(), 'uid' => $userId, 'sid' => $sid, 'by' => $actorId]
                );
            }
        });
    }
}
