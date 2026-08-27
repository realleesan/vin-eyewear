<?php

/**
 * CollectionModel — bộ sưu tập theo mùa.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NGUỒN DUY NHẤT, THAY CHO config/collections.php
 *
 * Trước 2026-08-25 ba bộ sưu tập nằm cứng trong config/collections.php, và
 * NĂM chỗ đọc thẳng file đó: mega menu, khối lookbook trang chủ, nhãn của bộ
 * lọc sản phẩm (ProductTaxonomy), form sửa sản phẩm và phép kiểm slug khi lưu
 * sản phẩm (ProductAdminController).
 *
 * Chuyển sang CSDL để nhân viên tự thêm/sửa/ẩn thay vì phải sửa mã và deploy.
 * Cả năm chỗ nay đi qua lớp này.
 *
 * `slug` LÀ THỨ KHÔNG ĐƯỢC ĐỔI. Nó nối bảng này với `products.collection` và
 * với mọi link /san-pham?collection=… đã phát ra ngoài — đổi slug của một bộ
 * đã phát hành là làm chết cả hai cùng lúc.
 *
 * Mọi hàm cho trang công khai đều lọc is_visible = 1;
 * khu quản trị dùng all() để thấy cả bản đang ẩn.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CollectionModel extends BaseModel
{
    protected static string $table = 'collections';

    /**
     * Thứ tự trưng bày dùng CHUNG cho mọi nơi.
     *
     * sort_order trước, rồi mới tới ngày ra mắt mới nhất. Chỉ sắp theo ngày
     * là không đủ: hai bộ ra cùng ngày thì thứ tự thành hên xui, và cửa hàng
     * không có cách nào đẩy một bộ cũ lên đầu khi bộ đó còn hàng.
     *
     * `name` chốt cuối để hai bộ trùng cả sort_order lẫn ngày vẫn ra thứ tự
     * ổn định giữa các lần tải — không thì phân trang và ảnh chụp màn hình
     * đều nhảy lung tung.
     */
    private const ORDER = 'sort_order ASC, launched_at DESC, name ASC';

    /** Bộ sưu tập đang hiển thị, cho trang /bo-suu-tap và trang chủ. */
    public static function visible(): array
    {
        return static::where(['is_visible' => 1], self::ORDER);
    }

    /** Toàn bộ, kể cả bản đang ẩn — chỉ dùng trong khu quản trị. */
    public static function allOrdered(): array
    {
        return static::all(self::ORDER);
    }

    /** Một bộ đang hiển thị theo slug, hoặc null. */
    public static function findVisibleBySlug(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        return static::firstWhere(['slug' => $slug, 'is_visible' => 1]);
    }

    /**
     * Bảng slug => tên, cho nhãn của bộ lọc sản phẩm và ô chọn trong form.
     *
     * Lấy CẢ bộ đang ẩn, cố ý: một sản phẩm có thể còn gắn bộ vừa bị ẩn đi.
     * Bỏ qua bộ ẩn ở đây thì trang quản trị hiện sản phẩm đó với ô bộ sưu tập
     * trống, và lưu lại một phát là mất luôn liên kết mà không ai định xoá.
     *
     * @return array<string,string>
     */
    public static function labels(): array
    {
        static $nho = null;

        if ($nho === null) {
            $nho = array_column(static::allOrdered(), 'name', 'slug');
        }

        return $nho;
    }

    /**
     * Một cột JSON của bộ sưu tập, đã giải mã thành mảng.
     *
     * Ba cột dùng tới: `audience` · `palette` · `signature`. Chúng là JSON chứ
     * không phải ba bảng con vì mỗi cái chỉ có vài dòng, không dòng nào được
     * truy vấn riêng, và không dòng nào có ý nghĩa ngoài bộ chứa nó — đúng ba
     * điều kiện để một danh sách nên nằm trong cột thay vì trong bảng.
     *
     * KHÔNG giải mã sẵn trong visible()/allOrdered(): mega menu và khối trang
     * chủ gọi hai hàm đó ở mọi lượt tải mà không đụng tới ba cột này, nên giải
     * mã sẵn là ba lượt json_decode cho mỗi bộ, mỗi trang, để không ai dùng.
     *
     * Trả RỖNG khi cột trống hoặc JSON hỏng — cột do form quản trị ghi nên
     * hỏng là chuyện hiếm, nhưng dữ liệu gieo tay thì không hứa gì cả, và một
     * khối biến mất vẫn hơn một trang 500.
     */
    public static function jsonField(array $collection, string $key): array
    {
        $tho = $collection[$key] ?? null;

        if (is_array($tho)) {
            return $tho;
        }

        if (!is_string($tho) || trim($tho) === '') {
            return [];
        }

        $ra = json_decode($tho, true);

        return is_array($ra) ? $ra : [];
    }

    /**
     * Ảnh bìa của một bộ — RỖNG nếu không dùng được.
     *
     * Kiểm cả sự TỒN TẠI của file, không chỉ kiểm chuỗi khác rỗng. Đường dẫn
     * trong CSDL là chữ nhân viên gõ (hoặc dữ liệu gieo sẵn), nên nó trỏ tới
     * một file đã bị xoá hay gõ sai là chuyện thường. Trả nguyên đường dẫn
     * hỏng ra view thì trình duyệt vẽ icon ảnh vỡ — xấu hơn hẳn ô giữ chỗ mà
     * chính view đã có sẵn.
     *
     * Đây cũng là chỗ thay cho cặp 'image' / 'image_sample' của
     * config/collections.php cũ: một cột ảnh, một phép kiểm, thay vì hai khoá
     * mà nhân viên không bao giờ nhìn thấy khoá thứ hai.
     *
     * Nơi gọi tự quyết làm gì với chuỗi rỗng — trang /bo-suu-tap vẽ ô giữ chỗ,
     * mega menu thì ẩn hẳn cả thẻ.
     */
    public static function cover(array $collection): string
    {
        $duongDan = trim((string) ($collection['cover_image'] ?? ''));

        if ($duongDan === '') {
            return '';
        }

        return is_file(ROOT_PATH . '/' . ltrim($duongDan, '/')) ? $duongDan : '';
    }
}
