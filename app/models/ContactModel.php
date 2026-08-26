<?php

/**
 * ContactModel — yêu cầu liên hệ từ khách.
 *
 * Port từ submitContact trong src/lib/shop.functions.ts.
 *
 * Bảng contact_requests ở Postgres chỉ nhân viên đọc được (policy
 * "staff contact requests"). Bên PHP: hàm ghi mở cho khách, còn các hàm đọc
 * chỉ được gọi từ khu quản trị đã qua AuthMiddleware.
 */

class ContactModel extends BaseModel
{
    protected static string $table = 'contact_requests';

    public const STATUSES = [
        'new'      => 'Mới',
        'handling' => 'Đang xử lý',
        'done'     => 'Đã xử lý',
    ];

    /**
     * Nhận một yêu cầu liên hệ.
     *
     * Giới hạn độ dài khớp z.object() của bản Lovable — nhưng kiểm ở SERVER
     * chứ không chỉ ở form: thuộc tính maxlength trong HTML chỉ là gợi ý cho
     * trình duyệt, ai gửi thẳng POST cũng bỏ qua được.
     *
     * @return array ['ok'=>true] | ['ok'=>false,'error'=>...]
     */
    public static function submit(array $data): array
    {
        $fullName = trim((string) ($data['fullName'] ?? ''));
        $phone    = trim((string) ($data['phone'] ?? ''));
        $email    = trim((string) ($data['email'] ?? ''));
        $message  = trim((string) ($data['message'] ?? ''));

        if (utf8Length($fullName) < 2 || utf8Length($fullName) > 120) {
            return ['ok' => false, 'error' => 'Họ tên phải từ 2 đến 120 ký tự.'];
        }

        // Chỉ giữ chữ số để đếm: người dùng hay gõ "090 123 4567" hoặc
        // "(+84) 90-123-4567", đếm cả dấu sẽ từ chối nhầm số hợp lệ.
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return ['ok' => false, 'error' => 'Số điện thoại không hợp lệ.'];
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email không hợp lệ.'];
        }

        if (utf8Length($message) < 5 || utf8Length($message) > 1000) {
            return ['ok' => false, 'error' => 'Nội dung phải từ 5 đến 1000 ký tự.'];
        }

        $ban = [
            'full_name' => $fullName,
            'phone'     => $phone,
            'email'     => $email ?: null,
            'message'   => $message,
        ];

        /* GHI LẠI AI ĐANG ĐĂNG NHẬP LÚC GỬI.

           Module Khách hàng cần biết một khách đã gửi những yêu cầu liên hệ
           nào (tab "Hoạt động"). Nối ngược bằng số điện thoại lúc đọc thì
           không đáng tin: cột `phone` ngay trên lưu NGUYÊN VĂN khách gõ, còn
           `profiles`.`phone` đã qua normalizePhone() — "0912 345 678" và
           "+84912345678" là hai chuỗi khác nhau với MySQL.

           NULL khi khách chưa đăng nhập, và đó là phần lớn: form liên hệ mở
           cho mọi người, không bắt đăng nhập. Migration 2026-08-26 nối ngược
           những dòng cũ bằng chín chữ số cuối, nhưng từ đây thì ghi thẳng.

           Hỏi cột có tồn tại không trước khi ghi: máy chưa chạy migration mà
           nhét khoá lạ vào INSERT là lỗi 1054 — và nó rơi đúng vào form liên
           hệ của khách, một trong vài đường khách nói chuyện được với cửa
           hàng. Thà thiếu một liên kết còn hơn mất cả cái form. */
        if (Database::columnExists('contact_requests', 'user_id')) {
            $ban['user_id'] = AuthMiddleware::customerId();
        }

        try {
            static::insert($ban);
        } catch (Throwable $e) {
            error_log('[ContactModel] Không lưu được liên hệ: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không gửi được, vui lòng thử lại.'];
        }

        return ['ok' => true];
    }

    /**
     * Danh sách cho khu quản trị.
     */
    public static function paginateAdmin(string $status = '', int $page = 1, int $perPage = 20): array
    {
        $conditions = $status === '' ? [] : ['status' => $status];

        return static::paginate($conditions, $page, $perPage, 'created_at DESC');
    }

    /**
     * Số yêu cầu chưa xử lý — hiện thành huy hiệu trên menu quản trị.
     */
    public static function countNew(): int
    {
        return static::count(['status' => 'new']);
    }
}
