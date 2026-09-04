<?php

/**
 * WaitlistModel — danh sách khách chờ hàng về.
 *
 * Khách mở một mặt hàng đã hết, để lại email hoặc số điện thoại, và được báo
 * khi hàng về. Bảng `stock_waitlist`; lược đồ và lý do từng quyết định ghi ở
 * database/migrations/2026-08-29-danh-sach-cho-hang.sql.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VIỆC BÁO TIN HÔM NAY LÀ VIỆC CỦA NGƯỜI, KHÔNG PHẢI CỦA MÃ
 *
 * Lớp này CHỈ ghi nhận và liệt kê. Không có hàm nào gửi mail, và đó là chủ ý:
 * hosting đang chạy (InfinityFree bản miễn phí) vô hiệu hoá hàm mail() và chặn
 * cổng SMTP ra ngoài, nên .env.production để MAIL_DRIVER=log — thư chỉ ghi vào
 * file, không ai nhận. Xem khối chú thích đầu core/Mailer.php.
 *
 * Nên nhân viên mở /quan-tri/cho-hang, thấy ai đang chờ món nào, rồi gọi hoặc
 * nhắn Zalo, xong bấm "Đã báo". Viết một hàm gửi mail ở đây sẽ chạy êm ru trên
 * máy dev rồi im lặng không tới ai trên trang thật — tệ hơn hẳn việc nói thẳng
 * rằng đây là việc tay.
 *
 * Ngày có kênh gửi thật (mẫu ZNS của Zalo, hoặc SMTP ngoài) thì thêm đúng một
 * hàm gửi và gọi nó ở chỗ nhập kho. Bảng đã có sẵn `notified_at` cho việc đó.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class WaitlistModel extends BaseModel
{
    protected static string $table = 'stock_waitlist';

    /**
     * Bảng đã tồn tại chưa — máy chưa chạy 2026-08-29-danh-sach-cho-hang.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * MỘT BẢNG THIẾU KHÔNG ĐƯỢC PHÉP LÀM ĐỔ TRANG
     *
     * Cho tới 09/09/2026 lớp này không có chốt nào, và hậu quả đã xảy ra trên
     * trang thật: mở /quan-tri/cho-hang trên hosting chưa chạy migration thì
     * nhận PDOException 1146 kèm nguyên vết gọi.
     *
     * Chỗ đau hơn nằm ở PHÍA KHÁCH. ProductDetailController gọi dangKy() khi
     * khách bấm "Thông báo khi có hàng" — tức một nút trên trang bán hàng đổ
     * 500 vào mặt người mua, vì một bảng của khu quản trị chưa được tạo.
     *
     * Mã lên hosting bằng FTP tự động còn migration thì phải bấm tay, nên
     * khoảng lệch giữa hai thứ là chuyện BÌNH THƯỜNG chứ không phải sự cố —
     * xem chú thích cùng ý ở CollectionController. Mọi lớp đọc bảng mới đều
     * phải chịu được khoảng lệch đó; đây là chốt ấy, cùng khuôn với
     * AuditLogModel::available() và SepayModel::available().
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /**
     * Người này đã đăng ký chờ đúng món này chưa?
     *
     * COALESCE cả hai vế chứ không so thẳng: `variant_id` NULL (mặt hàng không
     * có biến thể) và `phone` NULL (khách chỉ để email) là chuyện thường, mà
     * trong SQL thì NULL = NULL trả về NULL — tức là KHÔNG khớp. So thẳng thì
     * mọi lần đăng ký lại đều lọt qua và bảng đầy dòng trùng.
     *
     * Chỉ tính những dòng CHƯA ĐƯỢC BÁO: hàng về, nhân viên báo xong, rồi vài
     * tháng sau hết lần nữa — lúc đó khách đăng ký lại là một lượt chờ MỚI,
     * không phải bản trùng của lượt cũ.
     */
    public static function daDangKy(string $productId, ?string $variantId, ?string $email, ?string $phone): bool
    {
        if (!self::available()) {
            return false;
        }

        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM stock_waitlist
              WHERE product_id = :pid
                AND COALESCE(variant_id, "") = COALESCE(:vid, "")
                AND COALESCE(email, "")      = COALESCE(:email, "")
                AND COALESCE(phone, "")      = COALESCE(:phone, "")
                AND notified_at IS NULL',
            ['pid' => $productId, 'vid' => $variantId, 'email' => $email, 'phone' => $phone]
        ) > 0;
    }

    /**
     * Ghi một lượt chờ. Trả false nếu người này đã đăng ký rồi.
     *
     * ⚠ KHÔNG tự chặn khi thiếu bảng, và đó là chủ ý. Trả false ở đây thì nơi
     * gọi in ra "Bạn đã trong danh sách chờ rồi" — một câu SAI SỰ THẬT nói với
     * khách, tệ hơn cả việc im lặng. Nơi gọi phải hỏi available() TRƯỚC và tự
     * quyết định nói gì; xem ProductDetailController::waitlist().
     */
    public static function dangKy(string $productId, ?string $variantId, ?string $email, ?string $phone): bool
    {
        if (self::daDangKy($productId, $variantId, $email, $phone)) {
            return false;
        }

        static::insert([
            'id'         => uuid(),
            'product_id' => $productId,
            'variant_id' => $variantId,
            'email'      => $email,
            'phone'      => $phone,
        ]);

        return true;
    }

    /**
     * Danh sách cho khu quản trị — ĐANG CHỜ TRƯỚC, trong mỗi nhóm thì CŨ NHẤT
     * TRƯỚC.
     *
     * Cũ nhất trước chứ không phải mới nhất: người chờ lâu nhất là người đáng
     * được gọi đầu tiên. Đây là chỗ dễ theo quán tính "mới nhất lên đầu" của
     * mọi bảng khác trong khu quản trị mà làm sai.
     *
     * JOIN sang products và product_variants để bảng hiện được TÊN chứ không
     * phải một dãy UUID. LEFT JOIN cho biến thể vì cột ấy NULL được.
     */
    public static function danhSach(bool $chiDangCho = true): array
    {
        if (!self::available()) {
            return [];
        }

        $loc = $chiDangCho ? 'WHERE w.notified_at IS NULL' : '';

        return Database::fetchAll(
            "SELECT w.*, p.name AS product_name, p.slug AS product_slug, p.sku,
                    p.stock_quantity, p.status,
                    v.label AS variant_label, v.stock_quantity AS variant_stock
               FROM stock_waitlist w
               JOIN products p          ON p.id = w.product_id
               LEFT JOIN product_variants v ON v.id = w.variant_id
               {$loc}
              ORDER BY w.notified_at IS NULL DESC, w.created_at ASC"
        );
    }

    /** Số người đang chờ — cho huy hiệu trên thanh điều hướng quản trị. */
    public static function demDangCho(): int
    {
        if (!self::available()) {
            return 0;
        }

        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM stock_waitlist WHERE notified_at IS NULL'
        );
    }

    /** Nhân viên đã gọi / nhắn xong thì đánh dấu. */
    public static function danhDauDaBao(string $id): void
    {
        static::update($id, ['notified_at' => date('Y-m-d H:i:s')]);
    }
}
