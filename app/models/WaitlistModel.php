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
    public static function daDangKy(string $productId, ?string $variantId, string $userId): bool
    {
        if (!self::available() || !self::coChuTaiKhoan()) {
            return false;
        }

        /* SO THEO user_id, KHÔNG THEO CẶP EMAIL + SỐ ĐIỆN THOẠI — FR-SP-17.

           Phép so cũ đối chiếu đúng chuỗi khách vừa gõ. Cùng một người để lại
           "0912345678" hôm nay và "0912 345 678" tuần sau là hai dòng khác nhau
           trong sổ chờ, và tới lúc hàng về họ nhận hai cuộc gọi. Một id tài
           khoản thì không có hai cách viết. */
        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM stock_waitlist
              WHERE product_id = :pid
                AND COALESCE(variant_id, "") = COALESCE(:vid, "")
                AND user_id = :uid
                AND notified_at IS NULL',
            ['pid' => $productId, 'vid' => $variantId, 'uid' => $userId]
        ) > 0;
    }

    /** CSDL đã có cột `stock_waitlist`.`user_id` chưa — migration đợt 5. */
    public static function coChuTaiKhoan(): bool
    {
        return Database::columnExists('stock_waitlist', 'user_id');
    }

    /**
     * Ghi một lượt chờ. Trả false nếu người này đã đăng ký rồi.
     *
     * ⚠ KHÔNG tự chặn khi thiếu bảng, và đó là chủ ý. Trả false ở đây thì nơi
     * gọi in ra "Bạn đã trong danh sách chờ rồi" — một câu SAI SỰ THẬT nói với
     * khách, tệ hơn cả việc im lặng. Nơi gọi phải hỏi available() TRƯỚC và tự
     * quyết định nói gì; xem ProductDetailController::waitlist().
     */
    public static function dangKy(string $productId, ?string $variantId, string $userId): bool
    {
        if (self::daDangKy($productId, $variantId, $userId)) {
            return false;
        }

        /* CHÉP EMAIL VÀ SỐ ĐIỆN THOẠI TẠI THỜI ĐIỂM ĐĂNG KÝ, không join lúc đọc.

           Hai cột ấy vẫn còn, chỉ đổi nguồn: trước là ô khách gõ, nay là hồ sơ
           tài khoản. Chép lại vì màn Chờ hàng phải đọc được cả khi khách đã xoá
           tài khoản — đơn hàng cũ giữ tên và số điện thoại cũng vì lý do này.

           `user_id` mới là thứ định danh; hai cột kia là bản chụp để gọi.

           MÁY CHƯA NÂNG CẤP thì bỏ qua cột user_id: mất phần chống trùng theo
           tài khoản, nhưng lượt chờ vẫn ghi được và nhân viên vẫn gọi được. */
        $ho = Database::fetchOne(
            'SELECT u.email, p.phone FROM users u
               LEFT JOIN profiles p ON p.id = u.id
              WHERE u.id = :id',
            ['id' => $userId]
        ) ?? [];

        $dong = [
            'id'         => uuid(),
            'product_id' => $productId,
            'variant_id' => $variantId,
            'email'      => ($ho['email'] ?? '') !== '' ? $ho['email'] : null,
            'phone'      => ($ho['phone'] ?? '') !== '' ? $ho['phone'] : null,
        ];

        if (self::coChuTaiKhoan()) {
            $dong['user_id'] = $userId;
        }

        static::insert($dong);

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

    /**
     * Những lượt chờ ĐANG CHỜ của đúng một thứ hàng.
     *
     * Dùng cho thư báo hàng có lại — FR-EM-04, FR-SP-18.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * `$variantId` NULL NGHĨA LÀ "PHƯƠNG ÁN NULL", KHÔNG PHẢI "MỌI PHƯƠNG ÁN"
     *
     * Hàm này lọc theo ĐÚNG thứ khách đã đăng ký chờ, và cột `variant_id` của
     * họ chỉ có hai dạng: một biến thể cụ thể, hoặc NULL khi mặt hàng không có
     * phương án nào để chọn. Hai dạng ấy có hai kho riêng —
     * VariantModel::stockOf() đọc `product_variants`.`stock_quantity` cho dạng
     * đầu và `products`.`stock_quantity` cho dạng sau.
     *
     * Bản đầu để NULL nghĩa là "mọi phương án", với lập luận rằng
     * `products`.`status` chặn trước mọi biến thể nên mở nó ra là mở cho tất
     * cả. Lập luận ấy SAI: VariantModel::inStock() là phép VÀ — mặt hàng phải
     * đang bán VÀ tồn của đúng biến thể ấy phải còn. Nhập 5 cái màu Nâu rồi
     * sửa tồn mặt hàng 0 → 5 không làm màu Đen mua được.
     *
     * Hậu quả của lỗi ấy không dừng ở một lá thư sai: lượt chờ màu Đen bị đánh
     * dấu đã báo, nên hôm màu Đen về thật thì không còn ai để gửi.
     *
     * LEFT JOIN sang profiles để lấy tên gọi trong thư. Cột `email` đã chép sẵn
     * trong chính bảng này (bản chụp lúc đăng ký), không join sang users.
     */
    public static function dangChoCho(string $productId, ?string $variantId = null): array
    {
        if (!self::available()) {
            return [];
        }

        $where  = 'w.product_id = :pid AND w.notified_at IS NULL';
        $params = ['pid' => $productId];

        if ($variantId !== null) {
            $where          .= ' AND w.variant_id = :vid';
            $params['vid']   = $variantId;
        } else {
            $where .= ' AND w.variant_id IS NULL';
        }

        $cotTen = Database::columnExists('stock_waitlist', 'user_id')
            ? 'p.full_name, w.user_id'
            : 'NULL AS full_name, NULL AS user_id';

        $joinP = Database::columnExists('stock_waitlist', 'user_id')
            ? ' LEFT JOIN profiles p ON p.id = w.user_id' : '';

        return Database::fetchAll(
            'SELECT w.id, w.email, w.variant_id, v.label AS variant_label, ' . $cotTen . '
               FROM stock_waitlist w
               LEFT JOIN product_variants v ON v.id = w.variant_id' . $joinP . '
              WHERE ' . $where . '
              ORDER BY w.created_at ASC
              LIMIT 200',
            $params
        );
    }

    /**
     * Những lượt chờ của một mặt hàng mà thứ họ chờ BÂY GIỜ MUA ĐƯỢC.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * VÌ SAO CẦN MỘT CÂU RIÊNG, KHÔNG DÙNG dangChoCho()
     *
     * `products`.`status` là cổng chặn đứng trước mọi biến thể — xem
     * VariantModel::inStock(). Nên khi màn Kho online sửa tồn mặt hàng từ 0 lên
     * và cột `status` lật từ 'out_of_stock' sang 'in_stock', thứ vừa mở ra
     * KHÔNG chỉ là mặt hàng gốc: mọi biến thể vốn đã có tồn riêng cũng vừa
     * thôi bị khoá.
     *
     * Một mặt hàng có phương án Đen (còn 3) và Nâu (hết) mà tồn gốc là 0 thì
     * không mua được gì cả. Nhập kho cho tồn gốc là mở cho Đen — và người chờ
     * Đen đáng được biết. Người chờ Nâu thì không, vì Nâu vẫn hết.
     *
     * dangChoCho() không trả lời được câu ấy: nó lọc theo ĐÚNG một thứ hàng.
     * Câu dưới đây lọc theo TỒN THẬT của thứ mỗi người đang chờ.
     *
     * BA CỔNG, KHÔNG PHẢI MỘT. Lá thư này nói "bấm vào đây mà mua", nên mọi
     * thứ chặn giữa khách và nút Thêm vào giỏ đều phải mở:
     *
     *   pr.is_visible = 1        mặt hàng bị ẩn thì bấm vào thư ra trang 404 —
     *                            ProductModel::findBySlug() lọc đúng cột này.
     *   pr.status = 'in_stock'   cổng chặn đứng TRƯỚC mọi biến thể; xem
     *                            VariantModel::inStock(). Thiếu nó thì nhập
     *                            hàng cho một phương án trong khi cả mặt hàng
     *                            còn bị đánh dấu hết là gửi thư mời khách vào
     *                            một cái nút đang khoá — rồi lượt chờ của họ
     *                            bị đánh dấu đã báo và không bao giờ được gửi
     *                            lại.
     *   v.is_active = 1          phương án đã bỏ tick "đang bán" thì khách bấm
     *                            vào cũng không thấy nó đâu.
     */
    public static function dangChoMuaDuoc(string $productId): array
    {
        if (!self::available()) {
            return [];
        }

        $coUser = Database::columnExists('stock_waitlist', 'user_id');
        $cotTen = $coUser ? 'p.full_name, w.user_id' : 'NULL AS full_name, NULL AS user_id';
        $joinP  = $coUser ? ' LEFT JOIN profiles p ON p.id = w.user_id' : '';

        return Database::fetchAll(
            'SELECT w.id, w.email, w.variant_id, v.label AS variant_label, ' . $cotTen . '
               FROM stock_waitlist w
               JOIN products pr ON pr.id = w.product_id
               LEFT JOIN product_variants v ON v.id = w.variant_id' . $joinP . '
              WHERE w.product_id = :pid
                AND w.notified_at IS NULL
                AND pr.is_visible = 1
                AND pr.status     = \'in_stock\'
                AND (
                     (w.variant_id IS NULL AND pr.stock_quantity > 0)
                  OR (w.variant_id IS NOT NULL AND v.stock_quantity > 0 AND v.is_active = 1)
                )
              ORDER BY w.created_at ASC
              LIMIT 200',
            ['pid' => $productId]
        );
    }

    /**
     * Đánh dấu NHIỀU lượt chờ cùng lúc.
     *
     * Một câu lệnh chứ không N: nhập một lô hàng đang có ba mươi người chờ thì
     * ba mươi lần đi-về tới CSDL nằm ngay trong lượt bấm Lưu của nhân viên.
     */
    public static function danhDauDaBaoNhieu(array $ids): void
    {
        $ids = array_values(array_filter($ids, static fn ($v): bool => is_string($v) && $v !== ''));

        if ($ids === [] || !self::available()) {
            return;
        }

        $ph = [];
        $pr = [];

        foreach ($ids as $i => $id) {
            $ph[]        = ":i{$i}";
            $pr["i{$i}"] = $id;
        }

        Database::execute(
            'UPDATE stock_waitlist SET notified_at = NOW()
              WHERE id IN (' . implode(', ', $ph) . ') AND notified_at IS NULL',
            $pr
        );
    }
}
