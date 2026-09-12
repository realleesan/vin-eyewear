<?php

/**
 * FavoriteModel — dấu trang: mặt hàng khách lưu lại để xem sau.
 *
 * Bảng `favorites`; lược đồ và lý do từng quyết định ghi ở
 * database/migrations/2026-09-12-yeu-thich-tro-lai.sql.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CHỈ CHO NGƯỜI ĐÃ ĐĂNG NHẬP, KHÔNG CÓ BẢN "LƯU TẠM TRONG PHIÊN"
 *
 * Giỏ hàng có bản cho khách vãng lai (core/GioHangPhien.php) vì mua hàng là
 * việc người ta làm ngay, và bắt đăng nhập giữa chừng là mất đơn. Dấu trang thì
 * ngược lại: cả giá trị của nó nằm ở chỗ LẦN SAU MỞ MÁY KHÁC VẪN CÒN. Lưu vào
 * $_SESSION thì nó biến mất sau 24 giờ (App::startSession) hoặc ngay khi khách
 * đổi sang điện thoại — tức là hỏng đúng cái việc mà nó sinh ra để làm.
 *
 * Nên tầng này KHÔNG có nhánh nào cho khách chưa đăng nhập, và
 * FavoriteController chặn ngay từ đầu bằng AuthMiddleware::requireLogin().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BẢNG CÓ THỂ CHƯA TỒN TẠI, VÀ ĐIỀU ĐÓ KHÔNG ĐƯỢC LÀM ĐỔ TRANG
 *
 * Mã lên hosting bằng FTP tự động còn migration thì bấm tay, nên có những quãng
 * mã mới chạy trên lược đồ cũ — chuyện bình thường ở dự án này, không phải sự
 * cố. available() là chốt cho quãng ấy, cùng khuôn với WaitlistModel,
 * AuditLogModel và SepayModel: chưa có bảng thì nút lưu không hiện, mục "Đã
 * lưu" không hiện, và không ai nhận trang 500.
 */

class FavoriteModel extends BaseModel
{
    protected static string $table = 'favorites';

    /**
     * Bảng đã tồn tại chưa — máy chưa chạy 2026-09-12-yeu-thich-tro-lai.
     */
    public static function available(): bool
    {
        return Database::tableExists(static::$table);
    }

    /**
     * Người này đã lưu mặt hàng này chưa?
     *
     * Không COALESCE gì cả, khác WaitlistModel::daDangKy(): cả hai cột đều
     * NOT NULL nên phép so bằng là đủ.
     */
    public static function daLuu(string $productId, string $userId): bool
    {
        if (!self::available()) {
            return false;
        }

        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM favorites WHERE user_id = :u AND product_id = :p',
            ['u' => $userId, 'p' => $productId]
        ) > 0;
    }

    /**
     * Bật/tắt dấu trang. Trả về trạng thái SAU khi bấm: true = đang lưu.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * MỘT NÚT, KHÔNG PHẢI HAI ĐƯỜNG
     *
     * Nút trên trang là một cái công tắc, nên máy chủ cũng phải là một cái công
     * tắc. Tách thành /them và /xoa thì hai bên phải cùng biết trạng thái hiện
     * tại mới gửi đúng đường — mà trang khách đang mở có thể đã cũ (họ mở hai
     * tab, bấm ở tab kia trước), và lúc đó "thêm" một thứ đã có sẽ va UNIQUE
     * KEY, còn "xoá" một thứ đã mất thì im lặng không làm gì.
     *
     * Đọc-rồi-ghi ở đây có khe hở lý thuyết (hai lần bấm thật nhanh cùng lúc),
     * và UNIQUE(user_id, product_id) là thứ bịt nó: cú INSERT thứ hai bị CSDL
     * từ chối chứ không tạo dòng trùng. Không quấn transaction cho một công tắc
     * — cái giá của khe hở ấy là một lần bấm không ăn, không phải dữ liệu sai.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function batTat(string $productId, string $userId): bool
    {
        if (!self::available()) {
            return false;
        }

        if (self::daLuu($productId, $userId)) {
            Database::execute(
                'DELETE FROM favorites WHERE user_id = :u AND product_id = :p',
                ['u' => $userId, 'p' => $productId]
            );

            return false;
        }

        static::insert([
            'id'         => uuid(),
            'user_id'    => $userId,
            'product_id' => $productId,
        ]);

        return true;
    }

    /**
     * Đếm cho huy hiệu ở cột điều hướng trang tài khoản.
     *
     * COUNT(*) trên chỉ mục UNIQUE, không nạp dòng nào — cột ấy vẽ ở MỌI mục
     * nên câu này chạy mọi lần mở trang tài khoản.
     *
     * ĐẾM CẢ MẶT HÀNG ĐÃ ẨN, khác với danhSach() bên dưới. Hai con số lệch nhau
     * là chuyện có thật (khách lưu một gọng rồi cửa hàng ẩn nó đi), và ở đây
     * chấp nhận lệch: sửa cho khớp phải JOIN sang products ở một câu chạy mọi
     * lượt tải trang, để đổi lấy một con số trên huy hiệu.
     */
    public static function dem(string $userId): int
    {
        if (!self::available()) {
            return 0;
        }

        return (int) Database::fetchValue(
            'SELECT COUNT(*) FROM favorites WHERE user_id = :u',
            ['u' => $userId]
        );
    }

    /**
     * Danh sách mặt hàng đã lưu — MỚI LƯU TRƯỚC.
     *
     * Trả về đúng thứ ProductModel trả: mảng các dòng sản phẩm ĐÃ GIẢI MÃ cột
     * JSON, để _layout/product-card.php dựng thẳng được.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * HAI CÂU, KHÔNG PHẢI MỘT CÂU JOIN
     *
     * Câu một lấy id theo thứ tự lưu; câu hai nhờ ProductModel::findManyById()
     * đọc sản phẩm. Dài hơn một dòng JOIN nhưng đúng hơn ở hai chỗ:
     *
     *   · findManyById() đã lọc `is_visible = 1`. Mặt hàng cửa hàng vừa ẩn thì
     *     rơi khỏi danh sách — dòng lưu VẪN CÒN trong bảng, nên bật lại là nó
     *     hiện lại. JOIN tay ở đây là chép lại luật ẩn/hiện vào chỗ thứ hai, và
     *     hai bản chép rồi sẽ lệch nhau.
     *   · Nó chạy decode() cho cột JSON (`images`, `specs`). Hàm ấy private
     *     trong ProductModel, nên một câu JOIN ở đây trả về `images` dạng
     *     CHUỖI và thẻ sản phẩm hỏng ảnh.
     *
     * Sắp lại theo đúng thứ tự id ban đầu: findManyById() trả mảng đánh khoá
     * theo id, thứ tự của nó là thứ tự CSDL trả về chứ không phải thứ tự lưu.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public static function danhSach(string $userId, int $limit = 60): array
    {
        if (!self::available()) {
            return [];
        }

        $ids = Database::fetchAll(
            'SELECT product_id FROM favorites
              WHERE user_id = :u
              ORDER BY created_at DESC, id DESC
              LIMIT ' . max(1, $limit),
            ['u' => $userId]
        );

        $ids = array_column($ids, 'product_id');

        if ($ids === []) {
            return [];
        }

        $sanPham = ProductModel::findManyById($ids);
        $ra      = [];

        foreach ($ids as $id) {
            if (isset($sanPham[$id])) {
                $ra[] = $sanPham[$id];
            }
        }

        return $ra;
    }
}
