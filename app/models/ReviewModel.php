<?php

/**
 * ReviewModel — đánh giá sản phẩm.
 *
 * Dựng theo khối "Đánh giá (45)" của "Vin Eyewear Product.dc.html".
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DUYỆT TRƯỚC KHI HIỆN — MẶC ĐỊNH, KHÔNG PHẢI TUỲ CHỌN
 *
 * Đánh giá mới vào với status 'pending' và KHÔNG hiện trên trang sản phẩm.
 * Một ô nhập văn bản công khai mà đăng thẳng lên là lời mời spam, quảng cáo
 * và bôi nhọ đối thủ — và người chịu hậu quả là cửa hàng, không phải người viết.
 *
 * CHỈ NGƯỜI ĐÃ NHẬN HÀNG MỚI VIẾT ĐƯỢC
 *
 * canReview() đòi một đơn ĐÃ HOÀN TẤT của chính tài khoản đó, có chứa đúng mặt
 * hàng này. Không có ràng buộc ấy thì điểm đánh giá chỉ nói lên ai chịu khó
 * lập nhiều tài khoản hơn.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class ReviewModel extends BaseModel
{
    protected static string $table = 'reviews';

    public const STATUSES = [
        'pending'   => 'Chờ duyệt',
        'published' => 'Đang hiện',
        'rejected'  => 'Đã từ chối',
    ];

    /** Số đánh giá hiện sẵn trên trang sản phẩm trước khi phải bấm "xem tất cả". */
    public const PREVIEW = 3;

    /**
     * TRẦN KÝ TỰ CỦA MỘT NHẬN XÉT — 400.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * CON SỐ NÀY ĐO TỪ CÁI THẺ, KHÔNG PHẢI CHỌN BỪA
     *
     * Thẻ trong băng đánh giá ở trang chủ rộng 320px, chữ 10px, mỗi dòng vừa
     * khoảng 52 ký tự, và thẻ chừa chỗ cho 6 dòng — tức ~310 ký tự hiện trọn.
     * 400 là mức cho phép dôi thêm một chút (CSS cắt phần thừa bằng
     * -webkit-line-clamp, xem .hrev__body trong home.css) mà vẫn không có
     * chuyện một nhận xét dài đẩy vỡ cả băng.
     *
     * TRƯỚC ĐÂY LÀ 2000 VÀ CẮT ÂM THẦM: submit() gọi utf8Substr($body, 0, 2000)
     * rồi lưu. Khách gõ 2100 ký tự thì 100 ký tự cuối biến mất, không một lời
     * nào — và họ chỉ phát hiện khi đọc lại đánh giá của chính mình. Nay quá
     * trần là BÁO LỖI và giữ nguyên thứ họ gõ để sửa.
     *
     * ĐỔI SỐ NÀY THÌ NHỚ ba chỗ đi cùng: maxlength của <textarea> và dòng nhắc
     * dưới ô nhập (app/views/product/detail.php), và số dòng cắt của
     * .hrev__body. Trần ở máy chủ là chốt cuối; hai chỗ kia chỉ để khách biết
     * trước.
     */
    public const BODY_MAX = 400;

    // ========================================================================
    // ĐỌC
    // ========================================================================

    /**
     * Đánh giá ĐÃ DUYỆT của một mặt hàng, mới trước cũ sau.
     *
     * $limit null = lấy hết (trang "xem tất cả").
     */
    public static function published(string $productId, ?int $limit = null): array
    {
        $sql = 'SELECT * FROM reviews
                 WHERE product_id = :pid AND status = \'published\'
                 ORDER BY created_at DESC';

        if ($limit !== null) {
            // Nội suy số nguyên đã ép kiểu — LIMIT không nhận tham số buộc
            // trong nhiều bản MySQL, và (int) chặn mọi thứ không phải số.
            $sql .= ' LIMIT ' . max(1, (int) $limit);
        }

        return Database::fetchAll($sql, ['pid' => $productId]);
    }

    /**
     * Đánh giá ĐÃ DUYỆT mới nhất của CẢ SITE — băng đánh giá ở trang chủ.
     *
     * Khác published() ở chỗ không hỏi theo một mặt hàng: trang chủ cần
     * "khách nói gì về cửa hàng", không phải về một cái gọng.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * JOIN CHỨ KHÔNG PHẢI HAI CÂU
     *
     * Thẻ đánh giá in kèm tên mặt hàng và dẫn sang trang của nó, nên tên và
     * slug phải đi cùng dòng đánh giá. Ở đây JOIN được (khác
     * FavoriteModel::danhSach, nơi phải đi hai câu để mượn decode() của
     * ProductModel): thẻ này chỉ cần `name` và `slug`, không đụng tới cột JSON
     * nào của sản phẩm.
     *
     * `p.is_visible = 1` là phép lọc THẬT, không phải cho đẹp: đánh giá của một
     * mặt hàng cửa hàng vừa ẩn mà vẫn nằm trên trang chủ thì cú bấm vào tên
     * hàng ra trang 404.
     *
     * BẢNG CÓ THỂ CHƯA CÓ CỘT `reply` trên máy chưa chạy migration — nên câu
     * này KHÔNG đọc nó. Băng trên trang chủ chỉ in lời khách.
     */
    public static function latestPublished(int $limit = 5): array
    {
        return Database::fetchAll(
            'SELECT r.id, r.rating, r.body, r.author_name, r.order_id,
                    r.variant_label, r.created_at,
                    p.name AS product_name, p.slug AS product_slug
               FROM reviews r
               JOIN products p ON p.id = r.product_id
              WHERE r.status = \'published\' AND p.is_visible = 1
              ORDER BY r.created_at DESC
              LIMIT ' . max(1, $limit)
        );
    }

    /** Danh sách cho khu quản trị, lọc theo trạng thái, kèm tên sản phẩm. */
    public static function paginateAdmin(string $status = '', int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $where  = $status === '' ? '' : ' WHERE r.status = :status';
        $params = $status === '' ? [] : ['status' => $status];

        $total  = static::count($status === '' ? [] : ['status' => $status]);
        $offset = ($page - 1) * $perPage;

        $items = Database::fetchAll(
            'SELECT r.*, p.name AS product_name, p.slug AS product_slug
               FROM reviews r
               JOIN products p ON p.id = r.product_id'
            . $where .
            // Chờ duyệt lên đầu: đó là việc người quản trị vào đây để làm.
            ' ORDER BY (r.status = \'pending\') DESC, r.created_at DESC'
            . sprintf(' LIMIT %d OFFSET %d', $perPage, $offset),
            $params
        );

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }

    /** Số đánh giá đang chờ duyệt — huy hiệu trên menu quản trị. */
    public static function countPending(): int
    {
        try {
            return static::count(['status' => 'pending']);
        } catch (Throwable $e) {
            // Chưa chạy file nâng cấp thì bảng chưa tồn tại. Trả 0 thay vì làm
            // vỡ mọi trang quản trị — cùng cách làm với PasswordResetModel.
            return 0;
        }
    }

    // ========================================================================
    // QUYỀN VIẾT
    // ========================================================================

    /**
     * Khách này có được đánh giá mặt hàng này không?
     *
     * @return array{ok:bool, reason?:string, orderId?:string, variant?:?string}
     */
    public static function canReview(?string $userId, string $productId): array
    {
        if ($userId === null) {
            return ['ok' => false, 'reason' => 'Đăng nhập để viết đánh giá.'];
        }

        // Đơn ĐÃ HOÀN TẤT chứa mặt hàng này. Lấy đơn gần nhất chưa đánh giá.
        $row = Database::fetchOne(
            'SELECT o.id, oi.variant_label
               FROM orders o
               JOIN order_items oi ON oi.order_id = o.id
              WHERE o.user_id = :uid
                AND oi.product_id = :pid
                AND o.status = \'completed\'
                AND NOT EXISTS (
                    SELECT 1 FROM reviews r
                     WHERE r.order_id = o.id AND r.product_id = :pid2
                )
              ORDER BY o.created_at DESC
              LIMIT 1',
            ['uid' => $userId, 'pid' => $productId, 'pid2' => $productId]
        );

        if ($row === null) {
            // Thông điệp CỐ TÌNH gộp hai trường hợp "chưa mua" và "đã đánh giá
            // rồi" — phân biệt chúng không giúp khách làm gì thêm.
            return ['ok' => false, 'reason' =>
                'Chỉ khách đã nhận hàng mới viết được đánh giá, và mỗi đơn đánh giá một lần.'];
        }

        return ['ok' => true, 'orderId' => $row['id'], 'variant' => $row['variant_label']];
    }

    // ========================================================================
    // GHI
    // ========================================================================

    /**
     * Khách gửi một đánh giá. Vào hàng chờ duyệt.
     *
     * @return array{ok:bool, error?:string}
     */
    public static function submit(string $userId, string $productId, int $rating, string $body): array
    {
        $allowed = self::canReview($userId, $productId);

        if (!$allowed['ok']) {
            return ['ok' => false, 'error' => $allowed['reason']];
        }

        if ($rating < 1 || $rating > 5) {
            return ['ok' => false, 'error' => 'Vui lòng chọn số sao từ 1 đến 5.'];
        }

        $body = trim($body);

        if (utf8Length($body) < 10) {
            return ['ok' => false, 'error' => 'Nhận xét cần ít nhất 10 ký tự.'];
        }

        /* Quá dài thì TỪ CHỐI, không cắt bớt rồi lưu — xem khối chú thích của
           hằng BODY_MAX. Câu báo nói cả trần lẫn số khách đang có, vì "quá
           dài" mà không biết dài bao nhiêu thì phải xoá mò. */
        if (utf8Length($body) > self::BODY_MAX) {
            return [
                'ok'    => false,
                'error' => sprintf(
                    'Nhận xét tối đa %d ký tự (bạn đang viết %d). Vui lòng rút gọn.',
                    self::BODY_MAX,
                    utf8Length($body)
                ),
            ];
        }

        $profile = UserModel::profile($userId);

        static::insert([
            'product_id'    => $productId,
            'user_id'       => $userId,
            'order_id'      => $allowed['orderId'],
            // Chép lại tên lúc viết — xem ghi chú trong schema.sql
            'author_name'   => $profile['full_name'] ?: 'Khách hàng',
            'rating'        => $rating,
            /* utf8Substr là lưới an toàn cuối, KHÔNG phải phép cắt: phép kiểm
               ngay trên đã chặn mọi chuỗi dài hơn BODY_MAX, nên dòng này chỉ
               chạy nếu ai đó sửa phép kiểm mà quên chỗ này. */
            'body'          => utf8Substr($body, 0, self::BODY_MAX),
            'variant_label' => $allowed['variant'],
            'status'        => 'pending',
        ]);

        return ['ok' => true];
    }

    /**
     * Duyệt / từ chối, rồi tính lại điểm trung bình của mặt hàng.
     */
    public static function setStatus(string $id, string $status): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }

        $review = static::find($id);

        if ($review === null) {
            return false;
        }

        static::update($id, ['status' => $status]);
        self::recount($review['product_id']);

        return true;
    }

    public static function remove(string $id): void
    {
        $review = static::find($id);

        if ($review === null) {
            return;
        }

        static::delete($id);
        self::recount($review['product_id']);
    }

    /**
     * Tính lại products.rating và products.review_count từ bảng này.
     *
     * Hai cột đó là SỐ TỔNG được giữ sẵn, không phải nguồn sự thật. Chúng tồn
     * tại vì lưới sản phẩm cần đọc điểm mà không phải gộp nhóm cả bảng đánh
     * giá ở mỗi lần hiện trang.
     *
     * CHỈ đếm đánh giá ĐÃ DUYỆT: điểm hiện ra ngoài phải khớp đúng những nhận
     * xét mà khách đọc được.
     *
     * Chưa có đánh giá nào thì trả rating về 5.0 — giá trị mặc định của cột,
     * và cũng là cách các thẻ sản phẩm hiển thị trước khi có đánh giá đầu tiên.
     */
    public static function recount(string $productId): void
    {
        $row = Database::fetchOne(
            'SELECT COUNT(*) AS n, AVG(rating) AS avg_rating
               FROM reviews
              WHERE product_id = :pid AND status = \'published\'',
            ['pid' => $productId]
        );

        $count = (int) ($row['n'] ?? 0);

        Database::execute(
            'UPDATE products SET rating = :r, review_count = :c WHERE id = :id',
            [
                'r'  => $count > 0 ? round((float) $row['avg_rating'], 1) : 5.0,
                'c'  => $count,
                'id' => $productId,
            ]
        );
    }
}
