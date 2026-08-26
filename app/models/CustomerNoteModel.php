<?php

/**
 * CustomerNoteModel — ghi chú nội bộ về khách (`customer_notes`).
 *
 * KHÁCH KHÔNG BAO GIỜ ĐỌC ĐƯỢC BẢNG NÀY. Không có route nào bên site bán hàng
 * chạm tới nó, và đừng thêm: nội dung ở đây là câu nhân viên viết cho nhân
 * viên ("khách khó tính chuyện màu gọng", "hay đổi lịch phút chót"), viết với
 * giả định người ngoài không đọc. Hở ra một đường đọc là đổi luôn thứ người ta
 * dám viết, và khi đó ghi chú mất hết giá trị.
 */

class CustomerNoteModel extends BaseModel
{
    protected static string $table = 'customer_notes';

    /** Dài tối đa một ghi chú. TEXT chứa được nhiều hơn, nhưng xem ghi chú ở validate(). */
    private const DAI_TOI_DA = 2000;

    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /** Ghi chú của một khách, mới nhất trước. */
    public static function forUser(string $userId): array
    {
        if (!self::available()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT * FROM customer_notes WHERE user_id = :uid ORDER BY created_at DESC',
            ['uid' => $userId]
        );
    }

    /** Một ghi chú, nhưng CHỈ khi nó thuộc về đúng khách đang mở. */
    public static function findOwned(string $id, string $userId): ?array
    {
        if (!self::available()) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM customer_notes WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );
    }

    /**
     * Thêm mới hoặc sửa.
     *
     * @param string|null $id null = thêm mới
     * @return array{ok:bool, error?:string}
     */
    public static function save(?string $id, string $userId, string $body, string $actorId): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' =>
                'Bảng customer_notes chưa tồn tại. Chạy database/migrations/2026-08-26-module-khach-hang.sql.'];
        }

        $body = trim($body);

        if ($body === '') {
            return ['ok' => false, 'error' => 'Ghi chú không được để trống.'];
        }

        /* CẮT NGẮN THAY VÌ TỪ CHỐI khi quá dài.

           Cột là TEXT nên chứa được 65KB, giới hạn 2000 ký tự là quyết định
           của giao diện chứ không của CSDL. Từ chối cả bài viết dài sẽ làm mất
           trắng thứ người ta vừa gõ mười phút — mà đây là ô ghi chú, không
           phải ô nhập độ cận: một câu bị cụt còn đọc được, còn một ô trống thì
           không. */
        $body = utf8Substr($body, 0, self::DAI_TOI_DA);

        if ($id !== null) {
            /* Điều kiện user_id nằm TRONG câu UPDATE, không phải một if phía
               trên: id đi qua hidden input nên sửa được, và thiếu điều kiện
               này thì đổi một ký tự là ghi đè ghi chú của khách khác. */
            $doi = Database::execute(
                'UPDATE customer_notes SET body = :body WHERE id = :id AND user_id = :uid',
                ['body' => $body, 'id' => $id, 'uid' => $userId]
            );

            if ($doi === 0) {
                // 0 dòng cũng xảy ra khi người ta bấm Lưu mà không sửa gì —
                // nhưng lúc đó bản ghi vẫn tồn tại. Phân biệt bằng một câu hỏi
                // nữa, thay vì báo "không tìm thấy" cho một thao tác vô hại.
                $co = Database::fetchValue(
                    'SELECT COUNT(*) FROM customer_notes WHERE id = :id AND user_id = :uid',
                    ['id' => $id, 'uid' => $userId]
                );

                if ((int) $co === 0) {
                    return ['ok' => false, 'error' => 'Không tìm thấy ghi chú.'];
                }
            }

            AuditLogModel::write($userId, 'note.save', 'Sửa ghi chú nội bộ');

            return ['ok' => true];
        }

        $who  = UserModel::profile($actorId);
        $name = $who['full_name'] ?? $who['email'] ?? null;

        Database::execute(
            'INSERT INTO customer_notes (id, user_id, body, author_id, author_name)
             VALUES (:id, :uid, :body, :aid, :aname)',
            [
                'id'    => uuid(),
                'uid'   => $userId,
                'body'  => $body,
                'aid'   => $actorId,
                // Chép lại tên tại thời điểm viết — người này có thể nghỉ việc
                // và bị xoá tài khoản, lúc đó author_id thành NULL.
                'aname' => $name !== null ? utf8Substr($name, 0, 255) : null,
            ]
        );

        AuditLogModel::write($userId, 'note.save', 'Thêm ghi chú nội bộ');

        return ['ok' => true];
    }

    /** @return array{ok:bool, error?:string} */
    public static function deleteOwned(string $id, string $userId): array
    {
        if (!self::available()) {
            return ['ok' => false, 'error' => 'Không tìm thấy ghi chú.'];
        }

        $xoa = Database::execute(
            'DELETE FROM customer_notes WHERE id = :id AND user_id = :uid',
            ['id' => $id, 'uid' => $userId]
        );

        if ($xoa === 0) {
            return ['ok' => false, 'error' => 'Không tìm thấy ghi chú.'];
        }

        AuditLogModel::write($userId, 'note.delete');

        return ['ok' => true];
    }
}
