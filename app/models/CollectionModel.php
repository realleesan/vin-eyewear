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
     * Thứ tự sắp xếp dùng CHUNG cho mọi nơi: bộ ra mắt gần nhất lên đầu.
     *
     * TRƯỚC ĐÂY còn một cột `sort_order` do nhân viên tự đánh số đứng trước
     * ngày ra mắt. Đã bỏ khỏi giao diện quản trị: nó bắt người nhập phải nhớ
     * số của cả bảng để chèn một bộ mới, trong khi thứ tự theo ngày ra mắt là
     * thứ cửa hàng thực sự muốn. Cột vẫn còn trong CSDL nhưng KHÔNG còn chỗ
     * nào đọc — đừng đưa nó trở lại câu ORDER BY mà không đưa lại cả ô nhập,
     * vì dữ liệu cũ trong đó nay là số chết.
     *
     * `name` chốt cuối để hai bộ trùng ngày vẫn ra thứ tự ổn định giữa các
     * lần tải — không thì phân trang và ảnh chụp màn hình đều nhảy lung tung.
     */
    private const ORDER = 'launched_at DESC, name ASC';

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
     * MỌI ảnh dùng được của một bộ, theo thứ tự đã sắp.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * LỌC THEO FILE CÓ THẬT, KHÔNG CHỈ THEO CHUỖI KHÁC RỖNG
     *
     * Đường dẫn trong CSDL là chữ nhân viên tải lên hoặc dữ liệu gieo sẵn, nên
     * nó trỏ tới một file đã bị xoá là chuyện thường. Trả nguyên đường dẫn
     * hỏng ra view thì trình duyệt vẽ icon ảnh vỡ — xấu hơn hẳn ô giữ chỗ mà
     * view đã có sẵn. Đây là luật cũ của cover(), nay áp cho cả danh sách.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * CÒN ĐỌC `cover_image` — NHƯNG CHỈ NHƯ LƯỚI AN TOÀN
     *
     * Từ 2026-08-28 ảnh nằm ở cột `images`; migration cùng ngày đã chép ảnh bìa
     * cũ sang. Nhánh đọc `cover_image` bên dưới chỉ cứu dòng nào có ảnh bìa mà
     * `images` lại rỗng — tình huống migration đã dọn, nhưng vẫn xuất hiện
     * được nếu ai đó sửa tay trong phpMyAdmin.
     *
     * BỎ NHÁNH ẤY ĐI khi nào cột `cover_image` được xoá hẳn. Hai thứ đó phải
     * đi cùng nhau: bỏ nhánh mà giữ cột thì cột thành rác không ai đọc, bỏ cột
     * mà giữ nhánh thì đây là một câu truy cập vào cột không tồn tại.
     *
     * @return string[]
     */
    public static function images(array $collection): array
    {
        $tho = $collection['images'] ?? null;

        if (is_string($tho)) {
            $tho = json_decode($tho, true);
        }

        $danhSach = is_array($tho) ? $tho : [];

        // Lưới an toàn — xem khối chú thích trên.
        if ($danhSach === []) {
            $bia = trim((string) ($collection['cover_image'] ?? ''));

            if ($bia !== '') {
                $danhSach = [$bia];
            }
        }

        $ra = [];

        foreach ($danhSach as $duongDan) {
            if (!is_string($duongDan) || trim($duongDan) === '') {
                continue;
            }

            if (is_file(ROOT_PATH . '/' . ltrim($duongDan, '/'))) {
                $ra[] = $duongDan;
            }
        }

        return $ra;
    }

    /**
     * Ảnh ĐẠI DIỆN của một bộ — RỖNG nếu không có ảnh nào dùng được.
     *
     * Là phần tử đầu của images(), không phải một cột riêng: đổi ảnh đại diện
     * nghĩa là đưa ảnh đó lên đầu mảng, đúng quy ước của `products`.`images`.
     * Một cột "ảnh nào là bìa" riêng sẽ là thứ thứ hai phải giữ cho khớp, và
     * nó sẽ lệch vào đúng ngày ai đó xoá tấm ảnh mà nó đang trỏ tới.
     *
     * Nơi gọi tự quyết làm gì với chuỗi rỗng — trang /bo-suu-tap vẽ ô giữ chỗ,
     * mega menu thì ẩn hẳn cả thẻ.
     */
    public static function cover(array $collection): string
    {
        return self::images($collection)[0] ?? '';
    }

    /**
     * Ảnh còn lại sau ảnh đại diện — dải lookbook trên trang chi tiết.
     *
     * Tách khỏi images() để view không phải tự cắt phần tử đầu ở mỗi chỗ dùng;
     * cắt sai một chỗ là ảnh bìa hiện hai lần liền nhau trên cùng một trang.
     *
     * @return string[]
     */
    public static function gallery(array $collection): array
    {
        return array_slice(self::images($collection), 1);
    }
}
