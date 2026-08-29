<?php

/**
 * VariantModel — biến thể sản phẩm (chiết suất tròng, màu gọng…).
 *
 * Dựng theo khối "Chiết suất — chọn theo độ cận" của "Vin Eyewear Product.dc.html".
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * GIÁ LÀ CHÊNH LỆCH, KHÔNG PHẢI GIÁ TUYỆT ĐỐI
 *
 * `price_delta` cộng vào `products.price`. Nhờ vậy đổi giá một mặt hàng chỉ
 * phải sửa MỘT số; lưu giá tuyệt đối ở đây thì mỗi lần đổi giá phải nhớ sửa đủ
 * mọi biến thể, và quên một dòng là bán sai giá mà không ai thấy.
 *
 * KHÔNG có mặt hàng nào BẮT BUỘC phải có biến thể. Mặt hàng không có dòng nào
 * ở bảng này bán như cũ và dùng tồn kho của chính nó — xem priceOf()/stockOf().
 * ─────────────────────────────────────────────────────────────────────────────
 */

class VariantModel extends BaseModel
{
    protected static string $table = 'product_variants';

    /**
     * Biến thể ĐANG BẬT của một mặt hàng, theo thứ tự người quản trị xếp.
     */
    public static function forProduct(string $productId): array
    {
        return Database::fetchAll(
            'SELECT * FROM product_variants
              WHERE product_id = :pid AND is_active = 1
              ORDER BY position ASC, label ASC',
            ['pid' => $productId]
        );
    }

    /** Kể cả biến thể đã tắt — dùng cho khu quản trị. */
    public static function allForProduct(string $productId): array
    {
        return Database::fetchAll(
            'SELECT * FROM product_variants
              WHERE product_id = :pid
              ORDER BY position ASC, label ASC',
            ['pid' => $productId]
        );
    }

    /**
     * Id của MỌI mặt hàng đang có ít nhất một biến thể bật.
     *
     * Một câu truy vấn cho cả trang, nhớ lại trong suốt request: thẻ sản phẩm
     * (_layout/product-card.php) phải biết mặt hàng có phương án hay không để
     * quyết định vẽ nút "Mua ngay" hay liên kết "Chọn phương án", mà một lưới
     * trang chủ có tới 8 thẻ và partial thì được gọi từng cái một.
     *
     * Hỏi NGƯỢC (ai có biến thể) thay vì hỏi từng mặt hàng: bảng này nhỏ và
     * thưa — phần lớn mặt hàng không có biến thể nào — nên danh sách trả về
     * ngắn hơn hẳn danh sách mặt hàng.
     *
     * @return array<string, true> tra bằng isset()
     */
    public static function productIdsWithVariants(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];

            foreach (Database::fetchAll(
                'SELECT DISTINCT product_id FROM product_variants WHERE is_active = 1'
            ) as $row) {
                $cache[$row['product_id']] = true;
            }
        }

        return $cache;
    }

    /** Mặt hàng này có bắt buộc chọn phương án trước khi mua không? */
    public static function hasVariants(string $productId): bool
    {
        return isset(self::productIdsWithVariants()[$productId]);
    }

    /**
     * Một biến thể, CHỈ khi nó thuộc đúng mặt hàng đang xét.
     *
     * Nhận cả hai tham số chứ không chỉ id: id đến từ form nên sửa được, và
     * không kiểm mặt hàng thì khách gửi id biến thể của món khác lên là mua
     * được với giá của món này.
     */
    public static function findForProduct(string $id, string $productId): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM product_variants
              WHERE id = :id AND product_id = :pid AND is_active = 1',
            ['id' => $id, 'pid' => $productId]
        );
    }

    /** Biến thể của NHIỀU mặt hàng cùng lúc, gom theo product_id. */
    public static function forProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $ph = [];
        $params = [];

        foreach (array_values($productIds) as $i => $id) {
            $ph[] = ':id' . $i;
            $params['id' . $i] = $id;
        }

        $rows = Database::fetchAll(
            'SELECT * FROM product_variants
              WHERE product_id IN (' . implode(', ', $ph) . ') AND is_active = 1
              ORDER BY position ASC, label ASC',
            $params
        );

        $out = [];

        foreach ($rows as $row) {
            $out[$row['product_id']][] = $row;
        }

        return $out;
    }

    // ========================================================================
    // GIÁ & TỒN KHO
    // ========================================================================

    /**
     * Giá bán thật của một mặt hàng, có tính biến thể.
     *
     * $variant null = mặt hàng không có biến thể, hoặc khách chưa chọn.
     */
    public static function priceOf(array $product, ?array $variant): int
    {
        /* Giá NỀN đi qua ProductPricing chứ không đọc thẳng cột `price`: mặt
           hàng đang khuyến mãi có hạn thì giá nền là giá khuyến mãi. Đây là
           đường tiền — giỏ hàng và lúc tạo đơn đều gọi hàm này — nên nó phải
           dùng đúng công thức mà thẻ sản phẩm đang hiện cho khách xem. */
        $price = ProductPricing::giaBan($product) + (int) ($variant['price_delta'] ?? 0);

        // Chặn sàn 0: một price_delta âm quá tay sẽ thành giá âm, và giá âm
        // biến hoá đơn thành khoản cửa hàng nợ khách.
        return max(0, $price);
    }

    /**
     * Tồn kho thật: của biến thể nếu có, không thì của mặt hàng.
     */
    public static function stockOf(array $product, ?array $variant): int
    {
        return $variant !== null
            ? (int) $variant['stock_quantity']
            : (int) $product['stock_quantity'];
    }

    /**
     * Còn đủ hàng để bán $quantity cái không?
     *
     * `products.status` vẫn quyết định trước: cả mặt hàng bị đánh dấu hết hàng
     * thì không biến thể nào bán được, dù tồn kho riêng của nó còn.
     */
    public static function inStock(array $product, ?array $variant, int $quantity = 1): bool
    {
        if (($product['status'] ?? '') !== 'in_stock') {
            return false;
        }

        return self::stockOf($product, $variant) >= $quantity;
    }

    /**
     * Trừ tồn kho của ĐÚNG chỗ đã bán.
     *
     * Câu UPDATE mang điều kiện số lượng, cùng lý do đã ghi ở
     * OrderModel::reserveStock: hai người mua cùng lúc không được cùng lấy
     * món cuối cùng.
     *
     * @return bool false nếu không còn đủ hàng (0 dòng bị sửa)
     */
    public static function reserve(?string $variantId, string $productId, int $quantity): bool
    {
        if ($variantId === null) {
            // Mặt hàng không có biến thể: trừ ở products và tự đánh dấu hết
            // hàng khi về 0, y như bản trước khi có biến thể.
            /*
             * ─────────────────────────────────────────────────────────────
             * TRỪ MỘT LẦN, KHÔNG PHẢI HAI — chỗ này từng trừ hai lần
             *
             * Bản trước viết:
             *     SET stock_quantity = stock_quantity - :q,
             *         status = CASE WHEN stock_quantity - :q2 <= 0 …
             *
             * MySQL tính các vế của SET theo THỨ TỰ TRÁI SANG PHẢI, và vế sau
             * đọc được giá trị vế trước VỪA GHI (khác chuẩn SQL, nhưng đó là
             * hành vi của MySQL lẫn MariaDB). Nên `stock_quantity` trong CASE
             * đã là tồn MỚI, rồi lại bị trừ thêm :q2 lần nữa. Điều kiện thật
             * sự đang chạy là `tồn_cũ - 2×số_bán <= 0`.
             *
             * Hậu quả đo được: bán từ MỘT NỬA kho trở lên là phần còn lại bị
             * đánh dấu hết hàng.
             *     kho 10, bán 5 -> còn 5  mà status = out_of_stock
             *     kho 3,  bán 2 -> còn 1  mà status = out_of_stock
             *     kho 2,  bán 1 -> còn 1  mà status = out_of_stock
             * Số hàng còn lại đó thành hàng chết: trang sản phẩm hiện "Tạm hết
             * hàng", nút thêm vào giỏ bị tắt, và OrderModel::place() từ chối —
             * cửa hàng mất doanh thu cho tới khi có người vào sửa tay.
             *
             * Nay CASE không trừ nữa, chỉ hỏi thẳng `stock_quantity <= 0`:
             * tới lượt nó thì cột ĐÃ mang tồn mới, nên câu hỏi đúng nghĩa là
             * "bán xong còn 0 chứ?". Giữ nguyên thứ tự hai vế — đảo lại là
             * CASE đọc phải tồn cũ và luật sai theo chiều ngược lại.
             * ─────────────────────────────────────────────────────────────
             */
            $ok = Database::execute(
                'UPDATE products
                    SET stock_quantity = stock_quantity - :q,
                        status = CASE WHEN stock_quantity <= 0 THEN :oos ELSE status END
                  WHERE id = :id AND stock_quantity >= :q2',
                ['q' => $quantity, 'q2' => $quantity,
                 'oos' => 'out_of_stock', 'id' => $productId]
            ) > 0;

            return $ok;
        }

        $ok = Database::execute(
            'UPDATE product_variants SET stock_quantity = stock_quantity - :q
              WHERE id = :id AND stock_quantity >= :q2',
            ['q' => $quantity, 'id' => $variantId, 'q2' => $quantity]
        ) > 0;

        if (!$ok) {
            return false;
        }

        // Mặt hàng CÓ biến thể thì "hết hàng" nghĩa là MỌI biến thể đang bật
        // đều về 0 — còn một phương án còn hàng thì mặt hàng vẫn bán được.
        // Thiếu bước này, lưới sản phẩm vẫn hiện "Còn hàng" cho một món mà
        // không phương án nào mua nổi.
        $left = (int) Database::fetchValue(
            'SELECT COALESCE(SUM(stock_quantity), 0) FROM product_variants
              WHERE product_id = :pid AND is_active = 1',
            ['pid' => $productId]
        );

        if ($left <= 0) {
            Database::execute(
                'UPDATE products SET status = :oos WHERE id = :id',
                ['oos' => 'out_of_stock', 'id' => $productId]
            );
        }

        return true;
    }

    /**
     * TRẢ HÀNG VỀ KHO — bản đối xứng của reserve().
     *
     * ─────────────────────────────────────────────────────────────────────
     * VÌ SAO CẦN HÀM NÀY
     *
     * Đặt hàng TRỪ tồn kho ngay trong transaction tạo đơn, nhưng huỷ đơn thì
     * trước nay KHÔNG trả lại — kể cả khi nhân viên huỷ trong khu quản trị.
     * Mỗi đơn huỷ là một lần kho ghi thiếu vĩnh viễn: món hàng vẫn nằm trên
     * kệ mà hệ thống coi như đã bán, và tới lúc nào đó nó tự đánh dấu
     * "hết hàng" trong khi cửa hàng còn nguyên vài cái.
     *
     * KHÔNG CÓ TRẦN TRÊN. reserve() có điều kiện `stock_quantity >= :q` để hai
     * người mua cùng lúc không cùng lấy được món cuối; ở chiều ngược lại không
     * có ràng buộc tương ứng nào — cộng trả về thì bao nhiêu cũng hợp lệ.
     *
     * MỞ LẠI TRẠNG THÁI BÁN được, và đây mới là phần dễ quên: reserve() tự đặt
     * products.status = 'out_of_stock' khi tồn về 0. Chỉ cộng số mà không mở
     * lại cờ ấy thì hàng có trong kho nhưng vẫn biến mất khỏi danh mục — lỗi
     * im lặng, chỉ lộ ra khi có người hỏi "sao món này không bán nữa".
     *
     * Mốc mở lại là `stock_quantity > 0`, KHÔNG phải một ngưỡng "còn ít": cột
     * status chỉ có hai giá trị 'in_stock' / 'out_of_stock' (xem
     * InventoryAdminController, nơi cũng đồng bộ theo đúng luật này) — còn
     * "sắp hết" là thứ khu quản trị TÍNH RA từ con số, không lưu.
     * ─────────────────────────────────────────────────────────────────────
     */
    public static function release(?string $variantId, string $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        if ($variantId === null) {
            /* Cùng cái bẫy trừ-hai-lần như reserve(), chỉ đổi dấu: vế sau
               đọc `stock_quantity` đã là tồn MỚI nên cộng thêm :q2 nữa là
               cộng hai lần. Ở chiều trả hàng nó rộng tay chứ không chặt tay —
               mở cờ bán sớm hơn mức đáng — nhưng vẫn là một câu hỏi sai, và
               để nguyên thì hai hàm anh em nói hai luật khác nhau. */
            Database::execute(
                'UPDATE products
                    SET stock_quantity = stock_quantity + :q,
                        status = CASE WHEN stock_quantity > 0 THEN :ok ELSE status END
                  WHERE id = :id',
                ['q' => $quantity, 'ok' => 'in_stock', 'id' => $productId]
            );

            return;
        }

        Database::execute(
            'UPDATE product_variants SET stock_quantity = stock_quantity + :q WHERE id = :id',
            ['q' => $quantity, 'id' => $variantId]
        );

        /* Mặt hàng CÓ biến thể: mở lại cờ bán khi TỔNG tồn của các biến thể
           đang bật lớn hơn 0 — cùng phép đếm mà reserve() dùng để đóng cờ, chỉ
           ngược chiều. Đếm lại từ CSDL chứ không suy từ :q, vì biến thể vừa
           cộng có thể đang bị tắt (is_active = 0). */
        $con = (int) Database::fetchValue(
            'SELECT COALESCE(SUM(stock_quantity), 0) FROM product_variants
              WHERE product_id = :pid AND is_active = 1',
            ['pid' => $productId]
        );

        if ($con > 0) {
            Database::execute(
                'UPDATE products SET status = :ok WHERE id = :id AND status = :oos',
                ['ok' => 'in_stock', 'id' => $productId, 'oos' => 'out_of_stock']
            );
        }
    }
}
