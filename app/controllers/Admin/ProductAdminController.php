<?php

/**
 * ProductAdminController — sản phẩm (/quan-tri/san-pham).
 *
 * Port từ src/routes/_authenticated/quan-tri/san-pham.tsx
 * và components/admin-product-form.tsx.
 */

class ProductAdminController extends AdminController
{
    private const BASE = '/quan-tri/san-pham';

    public function index(): void
    {
        $q      = trim((string) ($_GET['q'] ?? ''));
        $page   = max(1, (int) ($_GET['page'] ?? 1));

        // Khu quản trị thấy CẢ sản phẩm đang ẩn — khác trang bán hàng.
        // ProductModel::filter() luôn lọc is_visible nên ở đây truy vấn riêng.
        $where  = '';
        $params = [];

        if ($q !== '') {
            // Tách từ, khớp TẤT CẢ các từ — giống hệt tìm kiếm ở trang bán hàng
            // (ProductModel::filter). Ghép cả câu thành một chuỗi liền thì gõ
            // "gọng titan" không ra "Gọng kính Titan Vin T01", vì hai từ đó
            // cách nhau bởi chữ "kính".
            //
            // Tên tham số phải KHÁC NHAU cho từng vị trí: dự án tắt
            // EMULATE_PREPARES nên MySQL ánh xạ tham số theo vị trí, dùng lại
            // một tên cho nhiều dấu ? sẽ ném "Invalid parameter number".
            $words  = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $words  = array_slice($words, 0, 6);
            $groups = [];

            foreach ($words as $i => $word) {
                $groups[] = sprintf(
                    '(p.name LIKE :q%1$d_name OR p.sku LIKE :q%1$d_sku OR p.brand LIKE :q%1$d_brand)',
                    $i
                );
                $needle = '%' . addcslashes($word, '%_\\') . '%';
                $params["q{$i}_name"]  = $needle;
                $params["q{$i}_sku"]   = $needle;
                $params["q{$i}_brand"] = $needle;
            }

            if ($groups !== []) {
                $where = 'WHERE ' . implode(' AND ', $groups);
            }
        }

        $total   = (int) Database::fetchValue("SELECT COUNT(*) FROM products p {$where}", $params);
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $this->renderAdmin('admin/products/index', [
            'pageTitle'  => 'Sản phẩm — Quản trị',
            'products'   => Database::fetchAll(
                "SELECT p.*, c.name AS category_name
                   FROM products p
                   LEFT JOIN categories c ON c.id = p.category_id
                   {$where}
                  ORDER BY p.created_at DESC
                  LIMIT {$perPage} OFFSET {$offset}",
                $params
            ),
            'categories' => CategoryModel::all('sort_order ASC, name ASC'),
            // Bộ sưu tập theo mùa — nguồn là config, không phải bảng DB.
            'collections' => (array) config('collections'),
            'total'      => $total,
            'page'       => $page,
            'totalPages' => (int) ceil($total / $perPage),
            'q'          => $q,
            'canEdit'    => UserModel::hasRole($this->userId, 'admin')
                         || UserModel::hasRole($this->userId, 'manager'),
            'editing'    => isset($_GET['sua']) ? ProductModel::find((string) $_GET['sua']) : null,
        ]);
    }

    public function save(): void
    {
        $this->guardPostSize();
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id   = (string) ($_POST['id'] ?? '');
        $name = trim((string) ($_POST['name'] ?? ''));
        $sku  = strtoupper(trim((string) ($_POST['sku'] ?? '')));
        $slug = trim((string) ($_POST['slug'] ?? ''));

        if (utf8Length($name) < 2) {
            flash('admin_error', 'Tên sản phẩm phải có ít nhất 2 ký tự.');
            redirect(self::BASE);
        }

        if ($sku === '') {
            flash('admin_error', 'Vui lòng nhập mã SKU.');
            redirect(self::BASE);
        }

        $slug = $slug !== '' ? slugify($slug) : slugify($name);

        if ($slug === '') {
            flash('admin_error', 'Không tạo được slug từ tên này, vui lòng nhập slug thủ công.');
            redirect(self::BASE);
        }

        // slug và sku đều UNIQUE — kiểm cả hai để báo lỗi rõ ràng
        foreach (['slug' => $slug, 'sku' => $sku] as $column => $value) {
            $clash = ProductModel::findBy($column, $value);
            if ($clash !== null && $clash['id'] !== $id) {
                flash('admin_error', sprintf('%s "%s" đã được dùng cho sản phẩm khác.',
                    $column === 'slug' ? 'Slug' : 'SKU', $value));
                redirect(self::BASE);
            }
        }

        $price   = max(0, (int) ($_POST['price'] ?? 0));
        $compare = trim((string) ($_POST['compare_at_price'] ?? ''));
        $stock   = max(0, (int) ($_POST['stock_quantity'] ?? 0));

        // Giá gốc phải CAO HƠN giá bán, nếu không nhãn "-x%" sẽ vô nghĩa
        // hoặc âm. Để trống nghĩa là không có khuyến mãi.
        if ($compare !== '' && (int) $compare <= $price) {
            flash('admin_error', 'Giá gốc phải cao hơn giá bán, hoặc để trống nếu không giảm giá.');
            redirect(self::BASE);
        }

        // Ảnh: giữ lại ảnh cũ nào, cộng ảnh vừa tải lên từ máy — xem images().
        //
        // Khối này nằm SAU mọi lần kiểm tra có redirect ở trên là CỐ Ý:
        // move_uploaded_file() chạy rồi thì file đã nằm trên đĩa, mà redirect
        // thì không quay lại đây để dọn. Nhận ảnh ở đây nghĩa là đã chắc chắn
        // sẽ ghi xuống CSDL. Đừng chuyển nó lên trên.
        [$images, $imageErrors] = $this->images($id);

        // Thông số: mỗi dòng "Nhãn: giá trị"
        $specs = [];
        foreach (preg_split('/\R+/', (string) ($_POST['specs'] ?? '')) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$label, $value] = explode(':', $line, 2);
            $label = trim($label);
            $value = trim($value);
            if ($label !== '' && $value !== '') {
                $specs[$label] = $value;
            }
        }

        $categoryId = (string) ($_POST['category_id'] ?? '');

        $data = [
            'slug'             => $slug,
            'sku'              => $sku,
            'name'             => $name,
            // Danh mục phải có thật; giá trị lạ đưa về NULL thay vì để khoá
            // ngoại ném lỗi 1452
            'category_id'      => CategoryModel::exists(['id' => $categoryId]) ? $categoryId : null,
            'brand'            => trim((string) ($_POST['brand'] ?? '')) ?: null,
            'frame_shape'      => trim((string) ($_POST['frame_shape'] ?? '')) ?: null,
            'material'         => trim((string) ($_POST['material'] ?? '')) ?: null,
            'color'            => trim((string) ($_POST['color'] ?? '')) ?: null,
            'gender'           => in_array($_POST['gender'] ?? '', ['male', 'female', 'unisex', 'kids'], true)
                                    ? $_POST['gender'] : null,
            'description'      => trim((string) ($_POST['description'] ?? '')) ?: null,
            // Chỉ nhận slug có thật trong config/collections.php. Giá trị lạ về
            // NULL: một slug không khớp bộ nào thì mặt hàng vừa không hiện ở
            // trang chủ, vừa không lọc ra được bằng ?collection= — mất hút mà
            // trong admin nhìn vẫn như đã gán xong.
            'collection'       => $this->collection((string) ($_POST['collection'] ?? '')),
            'specs'            => json_encode($specs, JSON_UNESCAPED_UNICODE),
            'images'           => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'price'            => $price,
            'compare_at_price' => $compare !== '' ? (int) $compare : null,
            'stock_quantity'   => $stock,
            // Trạng thái suy ra từ tồn kho, không cho nhập tay: hai con số
            // này lệch nhau là nguồn gốc của "mua được hàng đã hết".
            'status'           => $stock > 0 ? 'in_stock' : 'out_of_stock',
            'is_featured'      => isset($_POST['is_featured']) ? 1 : 0,
            'is_visible'       => isset($_POST['is_visible']) ? 1 : 0,
        ];

        if ($id !== '' && ProductModel::exists(['id' => $id])) {
            ProductModel::update($id, $data);
            flash('admin_success', 'Đã cập nhật sản phẩm.');
        } else {
            ProductModel::insert($data);
            flash('admin_success', 'Đã thêm sản phẩm mới.');
        }

        // Một ảnh hỏng KHÔNG huỷ cả lần lưu: mọi thứ khác đã hợp lệ và đã ghi
        // xuống. Báo riêng để người dùng biết ảnh nào chưa lên, khu quản trị
        // hiện được cả hai dòng thông báo cùng lúc (xem admin/_layout/master).
        if ($imageErrors !== []) {
            flash('admin_error', implode(' ', array_unique($imageErrors)));
        }

        redirect(self::BASE);
    }

    /**
     * Danh sách ảnh cuối cùng của sản phẩm: ảnh cũ được giữ + ảnh vừa tải lên.
     *
     * @return array{0: string[], 1: string[]} [đường dẫn ảnh, lỗi để báo lại]
     */
    private function images(string $id): array
    {
        // Ảnh hiện có đọc TỪ CSDL chứ không lấy từ form. Form chỉ được quyền
        // nói "giữ cái nào"; nếu nhận thẳng đường dẫn do form gửi thì bất cứ ai
        // vào được trang này cũng nhét được một URL lạ vào cột images, và cột
        // đó in thẳng ra <img src> ở trang bán hàng.
        $current = [];

        if ($id !== '') {
            $row     = ProductModel::find($id);
            $current = $row !== null
                ? array_values(array_filter((array) json_decode((string) $row['images'], true), 'is_string'))
                : [];
        }

        $asked = array_map('strval', (array) ($_POST['image_keep'] ?? []));

        // Lọc trên $current (chứ không lặp $asked) để giữ nguyên THỨ TỰ CŨ:
        // thứ tự chính là ý nghĩa — ảnh đầu là ảnh đại diện, ảnh thứ hai hiện
        // khi rê chuột — mà thứ tự checkbox trình duyệt gửi lên thì không có
        // gì bảo đảm.
        $keep = array_values(array_filter(
            $current,
            static fn (string $path): bool => in_array($path, $asked, true)
        ));

        $room   = max(0, ProductImageStorage::MAX_FILES - count($keep));
        $upload = ProductImageStorage::storeMany($_FILES['image_files'] ?? [], $room);
        $images = array_merge($keep, $upload['paths']);

        // Ảnh đại diện: đưa ảnh được chọn lên đầu danh sách. Chỉ nhận giá trị
        // CÓ THẬT trong danh sách vừa dựng, nên nút radio bị sửa tay hoặc trỏ
        // vào ảnh vừa bị bỏ tick đều rơi vào im lặng chứ không sinh ảnh ma.
        $main = (string) ($_POST['image_main'] ?? '');

        if ($main !== '' && in_array($main, $images, true)) {
            $images = array_merge([$main], array_values(array_filter(
                $images,
                static fn (string $path): bool => $path !== $main
            )));
        }

        // Ảnh bị gỡ khỏi danh sách thì xoá khỏi đĩa luôn — nhưng chỉ ảnh do
        // chính khu quản trị tải lên; ProductImageStorage::remove() tự bỏ qua
        // đường dẫn nằm ngoài thư mục upload (ảnh đi kèm mã nguồn).
        foreach (array_diff($current, $images) as $gone) {
            ProductImageStorage::remove($gone);
        }

        return [$images, $upload['errors']];
    }

    /**
     * Chặn trường hợp gói POST vượt post_max_size của PHP.
     *
     * Khi đó PHP vứt SẠCH $_POST và $_FILES trước khi một dòng mã nào của ta
     * chạy — kể cả _token — nên requirePost() sẽ kết luận là hết hạn phiên và
     * báo "Phiên làm việc đã hết hạn, vui lòng thử lại.". Câu đó sai, và nó
     * đẩy người dùng đi đăng nhập lại thay vì bớt ảnh đi. Dấu hiệu nhận ra:
     * đúng là POST, CONTENT_LENGTH lớn hơn trần, mà $_POST lại rỗng.
     */
    private function guardPostSize(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $_POST !== []) {
            return;
        }

        $limit = self::iniBytes((string) ini_get('post_max_size'));

        if ($limit > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) {
            flash('admin_error', sprintf(
                'Tổng dung lượng gửi lên vượt giới hạn của máy chủ (%s). Hãy tải ít ảnh hơn trong một lần.',
                (string) ini_get('post_max_size')
            ));
            redirect(self::BASE);
        }
    }

    /** Đổi giá trị php.ini kiểu "8M", "128M", "1G" ra byte. */
    private static function iniBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    /**
     * Slug bộ sưu tập hợp lệ, hoặc null.
     */
    private function collection(string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }

        $known = array_column((array) config('collections'), 'slug');

        return in_array($slug, $known, true) ? $slug : null;
    }

    public function delete(): void
    {
        $this->requirePost(self::BASE);
        $this->requireManager(self::BASE);

        $id = (string) ($_POST['id'] ?? '');

        // Sản phẩm đã từng bán KHÔNG xoá được: order_items giữ product_id để
        // tra cứu, xoá đi là mất liên kết trong lịch sử đơn hàng. Ẩn đi
        // (is_visible = 0) đạt cùng mục đích mà không phá dữ liệu cũ.
        $sold = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM order_items WHERE product_id = :id',
            ['id' => $id]
        );

        if ($sold > 0) {
            flash('admin_error', sprintf(
                'Không xoá được: sản phẩm này đã xuất hiện trong %d đơn hàng. Hãy bỏ tick "Hiển thị" để ẩn khỏi trang bán hàng.',
                $sold
            ));
            redirect(self::BASE);
        }

        // Đọc danh sách ảnh TRƯỚC khi xoá bản ghi, nếu không thì không còn
        // đường nào biết những file kia thuộc về ai và chúng nằm lại trên đĩa
        // mãi mãi. Ảnh đi kèm mã nguồn không bị đụng tới (xem remove()).
        $row    = ProductModel::find($id);
        $images = $row !== null
            ? array_filter((array) json_decode((string) $row['images'], true), 'is_string')
            : [];

        ProductModel::delete($id);

        foreach ($images as $path) {
            ProductImageStorage::remove($path);
        }

        flash('admin_success', 'Đã xoá sản phẩm.');
        redirect(self::BASE);
    }
}
