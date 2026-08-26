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

    /*
     * KHÔNG CÒN HẰNG STATUSES — bỏ ngày 2026-08-26 cùng cột `status`.
     *
     * Nó là một hàng chờ ba nấc (Mới -> Đang xử lý -> Đã xử lý) mà không ai
     * đứng canh: nhân viên cửa hàng kính ngồi ở quầy và trả lời khách bằng
     * Zalo, không ngồi trước bảng quản trị chờ có dòng mới. Một hàng chờ không
     * người trực thì TRÔNG như đã có người lo, mà thật ra không — tệ hơn là
     * không có gì.
     *
     * Nay yêu cầu chạy thẳng sang Zalo của CSKH ngay lúc khách bấm gửi (xem
     * submit() ngay dưới), và việc theo dõi nằm trong chính cuộc trò chuyện
     * Zalo, nơi có người thật đang nhìn.
     *
     * Thứ còn lại ở phía CSDL là `zalo_sent_at` — một SỰ KIỆN, không phải một
     * trạng thái ai đó tự đặt. Xem database/migrations/2026-08-26-lien-he-qua-zalo.sql.
     */

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
            $id = static::insert($ban);
        } catch (Throwable $e) {
            error_log('[ContactModel] Không lưu được liên hệ: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Không gửi được, vui lòng thử lại.'];
        }

        /*
         * ĐẨY SANG ZALO CSKH NGAY, và đây là đường đi chính của yêu cầu này —
         * không phải một thông báo chạy kèm cho vui.
         *
         * Từ 2026-08-26, /quan-tri/lien-he không còn cột trạng thái và thành
         * sổ lưu trữ thuần: không ai ngồi canh nó. Tin Zalo này CHÍNH LÀ thứ
         * đưa yêu cầu tới một con người.
         *
         * KHÔNG bọc thêm try/catch ở đây: Zalo::contact() đã nuốt mọi ngoại lệ
         * bên trong và trả false. Thêm một lớp nữa chỉ làm người đọc tưởng nó
         * ném ra được.
         *
         * Gửi hỏng thì `zalo_sent_at` ở nguyên NULL, huy hiệu "Liên hệ" trên
         * thanh bên sáng lên, và nhân viên bấm "Gửi sang Zalo" để đẩy lại. Đó
         * là lý do cột kia tồn tại — ZNS hỏng im lặng, không có nó thì khách
         * ngồi chờ một cuộc gọi mà phía cửa hàng không ai biết là có người chờ.
         *
         * VÀ DÙ HỎNG THÌ VẪN TRẢ ok=true: yêu cầu đã nằm trong CSDL, khách đã
         * làm xong phần của họ. Báo "không gửi được" lúc này là bảo họ gõ lại
         * một lần nữa vào cùng cái bảng đã có bản ghi đầu tiên.
         */
        if (Zalo::contact($ban + ['id' => $id])) {
            self::markZaloSent($id);
        }

        return ['ok' => true];
    }

    /**
     * Danh sách cho khu quản trị.
     *
     * KHÔNG CÒN THAM SỐ LỌC. Trang này nay chỉ có một cách đọc — mới nhất
     * trước — vì không còn trạng thái nào để lọc theo.
     */
    public static function paginateAdmin(int $page = 1, int $perPage = 20): array
    {
        return static::paginate([], $page, $perPage, 'created_at DESC');
    }

    /**
     * Số yêu cầu CHƯA tới tay CSKH — hiện thành huy hiệu trên thanh bên.
     *
     * Thay cho countNew() cũ (đếm status = 'new'), và khác nó về bản chất:
     * con số cũ đếm việc CHƯA AI BẤM, con số này đếm việc HỆ THỐNG CHƯA LÀM
     * ĐƯỢC. Bình thường nó phải là 0 và huy hiệu tự ẩn — khác 0 nghĩa là ZNS
     * đang hỏng, và đó là thứ đáng gây chú ý.
     */
    public static function countChuaDayZalo(): int
    {
        // Cột chỉ có từ migration 2026-08-26-lien-he-qua-zalo. Chưa chạy thì
        // câu đếm này nằm trên đường MỌI trang quản trị đi qua (huy hiệu thanh
        // bên) và một lỗi 1054 ở đó đóng cửa cả khu quản trị.
        if (!Database::columnExists('contact_requests', 'zalo_sent_at')) {
            return 0;
        }

        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM contact_requests WHERE zalo_sent_at IS NULL'
        );
    }

    /** Đánh dấu đã đẩy sang Zalo thành công. */
    public static function markZaloSent(string $id): void
    {
        if (!Database::columnExists('contact_requests', 'zalo_sent_at')) {
            return;
        }

        Database::execute(
            'UPDATE contact_requests SET zalo_sent_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }
}
