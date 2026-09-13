<?php

/**
 * Wishlist — danh sách yêu thích, CHUNG MỘT CỬA cho cả hai loại khách.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * VÌ SAO CÓ FILE NÀY
 *
 * Từ 13/09/2026, theo yêu cầu chủ dự án, KHÁCH CHƯA ĐĂNG NHẬP CŨNG LƯU ĐƯỢC.
 * Nghĩa là danh sách yêu thích nay có hai nơi cất:
 *
 *   đã đăng nhập  ->  bảng `favorites` (FavoriteModel) — sống qua mọi máy
 *   chưa đăng nhập ->  $_SESSION['yeu_thich'] — sống trong phiên trình duyệt
 *
 * Trước file này có MƯỜI chỗ gọi thẳng FavoriteModel::* (thẻ sản phẩm, trang
 * chi tiết, trang giỏ, ngăn kéo giỏ, bốn chỗ trong AuthController…). Rắc phép
 * rẽ nhánh "đăng nhập chưa" vào cả mười chỗ là cách chắc chắn nhất để quên
 * một chỗ — và chỗ bị quên sẽ im lặng báo "chưa lưu" cho một món đã lưu.
 *
 * Nên mọi nơi gọi ĐÚNG MỘT CỬA là file này, và chỉ file này biết có hai kho.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ĐĂNG NHẬP LÀ GỘP, KHÔNG PHẢI TRÁO — cùng lý lẽ với GioHangPhien
 *
 * Khách lướt xem, bấm lưu vài chiếc, rồi mới đăng nhập. Tráo ở đây nghĩa là
 * đăng nhập xong thì mất sạch thứ vừa lưu — đúng cái cảnh khiến người ta bỏ đi.
 * Nên khiDangNhap() đổ ngăn vãng lai vào tài khoản rồi XOÁ ngăn ấy.
 *
 * Xoá là bắt buộc, không phải dọn dẹp: để lại thì nó sẽ được gộp tiếp vào tài
 * khoản KẾ TIẾP đăng nhập trên cùng máy — người sau nhận danh sách của người
 * trước. Đây đúng là lỗ hổng mà GioHangPhien sinh ra để bịt cho giỏ hàng; ở
 * đây bịt ngay từ đầu.
 *
 * ⚠ GỌI Ở ĐÂU: AuthMiddleware::customerId() và ::login(), ngay cạnh
 *   GioHangPhien::theoChu(). KHÔNG móc riêng vào login(): danh tính còn đổi ở
 *   ba đường khác (dựng lại phiên từ cookie ghi nhớ, phiên 24 giờ hết hạn, tài
 *   khoản bị khoá giữa chừng) mà login() không hề chạy.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * TRẦN SỐ MÓN CHO KHÁCH VÃNG LAI
 *
 * Ngăn vãng lai nằm trong phiên, tức là trong file phiên trên máy chủ, và nó
 * được đọc-ghi ở MỌI lượt tải trang. Không có trần thì một con bọ (hoặc một
 * người rảnh) bấm lưu cả kho hàng sẽ phình phiên lên vài trăm KB, và cái giá
 * ấy phải trả ở từng lượt tải trang của chính họ. 200 là rộng rãi so với một
 * người mua kính thật, và chật so với một vòng lặp.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class Wishlist
{
    /** Ô chứa ngăn vãng lai: [ product_id => số thứ tự lưu (int) ]. */
    private const O = 'yeu_thich';

    /**
     * Bộ đếm cho số thứ tự lưu.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * SỐ THỨ TỰ, KHÔNG PHẢI time() — VÀ ĐÂY LÀ MỘT LỖI ĐÃ ĐO ĐƯỢC
     *
     * Bản đầu của file này ghi time(). Khung đo bắt ngay: lưu ba món trong cùng
     * một giây thì cả ba mang đúng một con số, arsort() gặp giá trị bằng nhau
     * thì giữ nguyên thứ tự chèn (PHP 8 sắp ổn định), nên lưới "mới lưu trước"
     * in ra CŨ TRƯỚC. Mà bấm hai trái tim liền nhau trong một giây chính là
     * cách người ta dùng thật.
     *
     * Đây đúng bài học đã ghi ở CartController::add() cho `cart_seq`: "hai lần
     * thêm trong cùng một giây là chuyện thường, và khi đó dấu thời gian bằng
     * nhau thì thứ tự trở về ngẫu nhiên. Bộ đếm thì không bao giờ hoà."
     *
     * ⚠ ĐỪNG ĐỔI VỀ time() để "biết lúc nào lưu". Ngăn này không có màn hình
     *   nào in ngày giờ; thứ duy nhất nó cần trả lời là "món nào trước món nào".
     * ─────────────────────────────────────────────────────────────────────────
     */
    private const O_SEQ = 'yeu_thich_seq';

    /** Trần số món của ngăn vãng lai — xem khối chú thích đầu file. */
    private const TRAN_VANG_LAI = 200;

    /**
     * Tính năng có dùng được không.
     *
     * Người đã đăng nhập thì phụ thuộc bảng `favorites` (mã lên hosting bằng
     * FTP tự động còn migration thì bấm tay — xem FavoriteModel::available).
     * Khách vãng lai thì LUÔN dùng được: ngăn của họ nằm trong phiên, không
     * cần bảng nào cả.
     */
    public static function available(): bool
    {
        return self::ai() === null || FavoriteModel::available();
    }

    /** Đã lưu mặt hàng này chưa? */
    public static function daLuu(string $productId): bool
    {
        $userId = self::ai();

        return $userId === null
            ? isset(self::ngan()[$productId])
            : FavoriteModel::daLuu($productId, $userId);
    }

    /** Bật/tắt. Trả về trạng thái SAU khi bấm: true = đang lưu. */
    public static function batTat(string $productId): bool
    {
        $userId = self::ai();

        if ($userId !== null) {
            return FavoriteModel::batTat($productId, $userId);
        }

        $ngan = self::ngan();

        if (isset($ngan[$productId])) {
            unset($ngan[$productId]);
            self::ghi($ngan);

            return false;
        }

        /* CHẠM TRẦN THÌ BỎ MÓN CŨ NHẤT, KHÔNG TỪ CHỐI CÚ BẤM.
           Từ chối nghĩa là khách bấm vào trái tim mà nó không đổi, và không có
           chỗ nào trên thẻ sản phẩm để giải thích vì sao. Đẩy món cũ nhất ra
           thì cú bấm luôn có tác dụng đúng như mắt thấy. */
        if (count($ngan) >= self::TRAN_VANG_LAI) {
            asort($ngan);
            array_shift($ngan);
        }

        $_SESSION[self::O_SEQ] = (int) ($_SESSION[self::O_SEQ] ?? 0) + 1;
        $ngan[$productId]      = $_SESSION[self::O_SEQ];
        self::ghi($ngan);

        return true;
    }

    /** Đếm cho huy hiệu. */
    public static function dem(): int
    {
        $userId = self::ai();

        return $userId === null ? count(self::ngan()) : FavoriteModel::dem($userId);
    }

    /**
     * Danh sách mặt hàng đã lưu — MỚI LƯU TRƯỚC, đã giải mã cột JSON, dựng
     * thẳng được bằng _layout/product-card.php.
     */
    public static function danhSach(int $limit = 60): array
    {
        $userId = self::ai();

        if ($userId !== null) {
            return FavoriteModel::danhSach($userId, $limit);
        }

        $ngan = self::ngan();

        if ($ngan === []) {
            return [];
        }

        arsort($ngan);   // mới lưu trước

        return ProductModel::theoThuTuId(array_slice(array_keys($ngan), 0, max(1, $limit)));
    }

    /**
     * Danh tính vừa thành $userId — đổ ngăn vãng lai vào tài khoản.
     *
     * RẺ KHI KHÔNG CÓ GÌ ĐỂ LÀM, và đó là điều kiện bắt buộc: hàm này chạy
     * trong customerId(), tức là hàng chục lần mỗi lượt dựng trang. Ngăn rỗng
     * (trường hợp gần như luôn luôn) thì thoát trước khi chạm tới CSDL.
     */
    public static function khiDangNhap(?string $userId): void
    {
        if ($userId === null || empty($_SESSION[self::O])) {
            return;
        }

        /* BẢNG CHƯA CÓ THÌ GIỮ NGUYÊN NGĂN, ĐỪNG XOÁ.
           Quãng mã-mới-trên-lược-đồ-cũ là chuyện bình thường ở dự án này. Xoá
           ngăn lúc này là vứt thứ khách vừa lưu để đổi lấy đúng không gì cả;
           giữ lại thì họ vẫn thấy đủ, và lần đăng nhập sau khi bảng đã có thì
           nó gộp được. */
        if (!FavoriteModel::available()) {
            return;
        }

        $ngan = self::ngan();
        asort($ngan);   // gộp theo đúng thứ tự đã lưu, cũ trước

        foreach (array_keys($ngan) as $productId) {
            /* batTat() là CÔNG TẮC: gọi nó cho một món tài khoản ĐÃ có sẽ
               XOÁ món ấy đi — đúng ngược điều cần. Nên hỏi trước rồi mới thêm. */
            if (!FavoriteModel::daLuu($productId, $userId)) {
                FavoriteModel::batTat($productId, $userId);
            }
        }

        unset($_SESSION[self::O], $_SESSION[self::O_SEQ]);
    }

    // ========================================================================
    // BÊN TRONG
    // ========================================================================

    /** Ai đang xem — null là khách vãng lai. */
    private static function ai(): ?string
    {
        return AuthMiddleware::customerId();
    }

    /** @return array<string,int> */
    private static function ngan(): array
    {
        $ngan = $_SESSION[self::O] ?? null;

        return is_array($ngan) ? $ngan : [];
    }

    /** @param array<string,int> $ngan */
    private static function ghi(array $ngan): void
    {
        $_SESSION[self::O] = $ngan;
    }
}
