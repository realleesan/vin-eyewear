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

        /*
         * LỌC THEO DANH MỤC BẰNG DẢI VIÊN — thêm theo bản thiết kế
         * "Quản lý sản phẩm.dc.html".
         *
         * Ô tìm chữ đã có, nhưng nó trả lời câu hỏi khác: gõ chữ là khi đã
         * biết mình tìm cái gì. Dải viên trả lời câu "cửa hàng đang có những
         * gì trong mục gọng kính" — thao tác mở đầu, không phải thao tác tra
         * cứu, và nó phải bấm được chứ không phải gõ được.
         *
         * Tên tham số tiếng Việt không dấu theo quy ước URL của dự án.
         * ctype_digit chặn mọi thứ không phải số trước khi nó tới truy vấn;
         * giá trị rỗng hoặc rác thì coi như không lọc, KHÔNG báo lỗi — một
         * đường dẫn hỏng do sửa tay trên thanh địa chỉ nên trả về danh sách
         * đầy đủ, không nên trả về một trang lỗi.
         */
        $cat = trim((string) ($_GET['danh-muc'] ?? ''));

        if (!ctype_digit($cat)) {
            $cat = '';
        }

        // Khu quản trị thấy CẢ sản phẩm đang ẩn — khác trang bán hàng.
        // ProductModel::filter() luôn lọc is_visible nên ở đây truy vấn riêng.
        /* Gom điều kiện vào một mảng rồi mới ghép: hai bộ lọc (chữ và danh
           mục) cộng được với nhau, và mỗi cái thêm vào sau này chỉ phải đẩy
           thêm một phần tử chứ không phải viết lại chuỗi WHERE. */
        $dieuKien = [];
        $params   = [];

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
                $dieuKien[] = '(' . implode(' AND ', $groups) . ')';
            }
        }

        if ($cat !== '') {
            $dieuKien[]    = 'p.category_id = :danh_muc';
            $params['danh_muc'] = (int) $cat;
        }

        $where = $dieuKien !== [] ? 'WHERE ' . implode(' AND ', $dieuKien) : '';

        /* Đọc một lần rồi dùng lại: cả 'editing' lẫn 'variants' đều cần bản
           ghi này, mà gọi find() hai lần là hai lượt truy vấn cho cùng một
           dòng. */
        $dangSua = isset($_GET['sua']) ? ProductModel::find((string) $_GET['sua']) : null;

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
            'collections' => CollectionModel::allOrdered(),
            'total'      => $total,
            'page'       => $page,
            'totalPages' => (int) ceil($total / $perPage),
            'q'          => $q,
            // '' = không lọc danh mục nào; view dùng để tô viên đang chọn.
            'cat'        => $cat,
            'canEdit'    => UserModel::hasRole($this->userId, 'admin')
                         || UserModel::hasRole($this->userId, 'manager'),
            'editing'    => $dangSua,
            /* Lưới biến thể ở tab 4. allForProduct chứ không forProduct: khu
               quản trị phải thấy CẢ biến thể đang tắt, nếu không thì một
               phương án đã tắt vừa vô hình vừa không bật lại được. */
            'variants'   => $dangSua !== null ? VariantModel::allForProduct((string) $dangSua['id']) : [],
            /*
             * GỢI Ý THƯƠNG HIỆU cho ô nhập ở tab Thông tin.
             *
             * Dựng từ chính cột `brand` của hàng đã nhập, không phải từ một
             * bảng riêng: cửa hàng chưa bao giờ cần quản lý thương hiệu như
             * một thực thể (không logo, không trang riêng, không sắp xếp), nên
             * một bảng cho nó là bảng chỉ có mỗi cột tên.
             *
             * Ô nhập là <input list> chứ không phải <select> — xem chú thích
             * tại chỗ trong _form.php: hãng MỚI phải nhập được, mà select thì
             * chỉ chọn được thứ đã có.
             */
            'brands'     => array_column(Database::fetchAll(
                "SELECT DISTINCT brand FROM products
                  WHERE brand IS NOT NULL AND brand <> ''
                  ORDER BY brand ASC"
            ), 'brand'),
            /*
             * MỘT PHÉP THĂM DÒ CHO CẢ 27 CỘT thông số kính.
             *
             * Cả 27 ra đời trong cùng migration 2026-08-27-bo-suu-tap-khung-ba-lop
             * nên chúng có hoặc không có cùng lúc; `eyewear_type` là cột đầu
             * trong câu ALTER ấy. Chưa chạy nâng cấp thì khối 27 ô tự ẩn và
             * form vẫn lưu được như trước — xem save().
             */
            // Hộp thoại sáu tab: lưới ô, lưới biến thể, khung ảnh.
            // Chỉ trang này dùng — xem đầu assets/css/admin-products.css.
            'adminStyles' => ['assets/css/admin-products.css'],
            /* Tăng cường cho form: nút tự sinh SKU, slug theo tên, dòng xem
               trước cỡ gọng, nút ✕ xoá dòng biến thể. Không có file này thì
               mọi ô vẫn nhập tay được — xem đầu file JS. */
            'adminScripts' => ['assets/js/admin-product-form.js'],
        ]);
    }

    /**
     * Lưu sản phẩm — sáu tab của hộp thoại gộp vào một lần POST.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * BA KHỐI CỘT, BA MỨC "CÓ THẬT KHÔNG"
     *
     * Mã lên hosting bằng FTP tự động, migration thì bấm tay: giữa hai việc đó
     * có một khoảng dài hàng giờ, và trong khoảng ấy một câu INSERT nhắc tới
     * cột chưa tồn tại là lỗi 1054 — nhân viên không lưu nổi một mặt hàng nào,
     * kể cả khi chỉ định sửa giá.
     *
     * Nên $data dựng làm ba tầng, mỗi tầng hỏi một cột mốc trước khi ghép vào:
     *
     *   nền      cột có từ lược đồ gốc — luôn ghi.
     *   khối A   27 cột thông số kính (migration 2026-08-27). Mốc: eyewear_type.
     *   khối B   20 cột của bản vẽ mới (migration 2026-08-29). Mốc: publish_status.
     *
     * Thiếu khối nào thì form vẫn lưu được phần còn lại, chỉ là mấy ô của khối
     * ấy rơi vào im lặng cho tới khi chạy migration.
     */
    public function save(): void
    {
        $this->guardPostSize(self::BASE);
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

        /*
         * KHUYẾN MÃI PHẢI RẺ HƠN GIÁ BÁN, và ngày kết thúc không được trước
         * ngày bắt đầu. Hai phép kiểm này rẻ, còn hậu quả khi thiếu thì không:
         * một "khuyến mãi" đắt hơn giá thường in ra trang là chuyện khách chụp
         * màn hình, và một khoảng ngày ngược thì chương trình không bao giờ
         * chạy mà nhìn trong admin vẫn như đã đặt xong.
         */
        $sale     = trim((string) ($_POST['sale_price'] ?? ''));
        $saleFrom = trim((string) ($_POST['sale_from'] ?? ''));
        $saleTo   = trim((string) ($_POST['sale_to'] ?? ''));

        if ($sale !== '' && (int) $sale >= $price) {
            flash('admin_error', 'Giá khuyến mãi phải thấp hơn giá bán.');
            redirect(self::BASE);
        }

        if ($saleFrom !== '' && $saleTo !== '' && $saleTo < $saleFrom) {
            flash('admin_error', 'Ngày kết thúc khuyến mãi phải sau ngày bắt đầu.');
            redirect(self::BASE);
        }

        /* Bản ghi cũ, để giữ lại những giá trị chọn-sẵn chưa theo khoá chuẩn —
           xem tham số $cu của khoa(). Một truy vấn, dùng cho cả năm ô chọn. */
        $cu = $id !== '' ? ProductModel::find($id) : null;

        // Ảnh: giữ lại ảnh cũ nào, cộng ảnh vừa tải lên từ máy — xem images().
        //
        // Khối này nằm SAU mọi lần kiểm tra có redirect ở trên là CỐ Ý:
        // move_uploaded_file() chạy rồi thì file đã nằm trên đĩa, mà redirect
        // thì không quay lại đây để dọn. Nhận ảnh ở đây nghĩa là đã chắc chắn
        // sẽ ghi xuống CSDL. Đừng chuyển nó lên trên.
        [$images, $imageErrors] = $this->images($id);

        $categoryId = (string) ($_POST['category_id'] ?? '');

        /* Ba trạng thái xuất bản; giá trị lạ về 'visible'. `is_visible` suy ra
           từ đây chứ không còn là một ô tick riêng — cả trang bán hàng lọc theo
           cột đó, và để nó lệch với ô người dùng vừa chọn thì "đã ẩn" trong
           admin vẫn bày ra ngoài. */
        $trangThai = (string) ($_POST['publish_status'] ?? 'visible');

        if (!isset(((array) config('eyewear.publish_statuses'))[$trangThai])) {
            $trangThai = 'visible';
        }

        $data = [
            'slug'             => $slug,
            'sku'              => $sku,
            'name'             => $name,
            // Danh mục phải có thật; giá trị lạ đưa về NULL thay vì để khoá
            // ngoại ném lỗi 1452
            'category_id'      => CategoryModel::exists(['id' => $categoryId]) ? $categoryId : null,
            'brand'            => trim((string) ($_POST['brand'] ?? '')) ?: null,
            'frame_shape'      => $this->khoa('frame_shape', 'frame_shapes', $cu['frame_shape'] ?? null),
            'material'         => $this->khoa('material', 'frame_materials', $cu['material'] ?? null),
            'color'            => trim((string) ($_POST['color'] ?? '')) ?: null,
            'gender'           => $this->khoa('gender', 'genders', $cu['gender'] ?? null),
            'description'      => trim((string) ($_POST['description'] ?? '')) ?: null,
            // Chỉ nhận slug có thật trong bảng `collections`. Giá trị lạ về
            // NULL: một slug không khớp bộ nào thì mặt hàng vừa không hiện ở
            // trang chủ, vừa không lọc ra được bằng ?collection= — mất hút mà
            // trong admin nhìn vẫn như đã gán xong.
            'collection'       => $this->collection((string) ($_POST['collection'] ?? '')),
            'images'           => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'price'            => $price,
            'compare_at_price' => $compare !== '' ? (int) $compare : null,
            'stock_quantity'   => $stock,
            // Trạng thái kho suy ra từ tồn kho, không cho nhập tay: hai con số
            // này lệch nhau là nguồn gốc của "mua được hàng đã hết". KHÁC hẳn
            // `publish_status` bên dưới — đó là quyết định biên tập.
            'status'           => $stock > 0 ? 'in_stock' : 'out_of_stock',
            'is_featured'      => isset($_POST['is_featured']) ? 1 : 0,
            'is_visible'       => $trangThai === 'visible' ? 1 : 0,
        ];

        // Khối A — 27 cột thông số kính. Bản vẽ mới chỉ còn hỏi 8 trong số đó;
        // 19 cột kia GIỮ NGUYÊN giá trị cũ vì update() chỉ ghi những cột được
        // liệt kê. Xem migration 2026-08-29 về vì sao không xoá chúng.
        if (Database::columnExists('products', 'eyewear_type')) {
            $data = array_merge($data, $this->thuocTinhKinh());
        }

        // Khối B — 20 cột của bản vẽ mới.
        if (Database::columnExists('products', 'publish_status')) {
            $data = array_merge($data, $this->truongBanVe($trangThai, $images, $sale, $saleFrom, $saleTo, $cu));
        }

        if ($id !== '' && ProductModel::exists(['id' => $id])) {
            ProductModel::update($id, $data);
            flash('admin_success', 'Đã cập nhật sản phẩm.');
        } else {
            $id = ProductModel::insert($data);
            flash('admin_success', 'Đã thêm sản phẩm mới.');
        }

        /* Biến thể lưu SAU sản phẩm, cố ý: mỗi biến thể mang khoá ngoại
           product_id, mà sản phẩm vừa thêm chỉ có id kể từ dòng trên. Form của
           sản phẩm mới cũng không hiện lưới biến thể vì lý do ấy. */
        $loiBienThe = $this->luuBienThe($id);

        // Một ảnh hỏng KHÔNG huỷ cả lần lưu: mọi thứ khác đã hợp lệ và đã ghi
        // xuống. Báo riêng để người dùng biết ảnh nào chưa lên, khu quản trị
        // hiện được cả hai dòng thông báo cùng lúc (xem admin/_layout/master).
        $loi = array_merge($imageErrors, $loiBienThe);

        if ($loi !== []) {
            flash('admin_error', implode(' ', array_unique($loi)));
        }

        redirect(self::BASE);
    }

    /**
     * Khối A — tám ô thông số kính bản vẽ còn hỏi.
     *
     * Trước 2026-08-29 hàm này (tên cũ thongSoKinh) đọc cả 27 cột. Bản vẽ mới
     * bỏ hỏi 19 trong số đó — chất liệu tròng, chiết suất đi kèm, VLT, base
     * curve, độ dày gọng, phụ kiện, bảo hành, đổi trả, chứng nhận, mã vạch…
     *
     * Chúng KHÔNG bị xoá khỏi $data vì đã bị xoá khỏi form: update() chỉ ghi
     * những cột có mặt trong mảng, nên 19 cột kia giữ nguyên giá trị đã nhập và
     * trang bán hàng vẫn in chúng ra. Bỏ HỎI khác bỏ CHỨA.
     *
     * @return array<string,mixed>
     */
    private function thuocTinhKinh(): array
    {
        // Trần theo đúng kiểu cột trong CSDL, xem migration. Ép trần trên để
        // một cú gõ nhầm ("1450" thay vì "145") không lọt xuống cột TINYINT
        // rồi bị MySQL cắt cụt thành số khác hẳn mà không ai biết.
        $so = static function (string $key, int $tran): ?int {
            $v = (int) ($_POST[$key] ?? 0);

            return $v > 0 && $v <= $tran ? $v : null;
        };

        $cap = (string) ($_POST['lens_category'] ?? '');

        return [
            'weight_g'      => $so('weight_g', 500),
            'lens_width_mm' => $so('lens_width_mm', 99),
            'bridge_mm'     => $so('bridge_mm', 99),
            'temple_mm'     => $so('temple_mm', 250),
            'face_shapes'   => $this->khoaCsv($_POST['face_shapes'] ?? [], 'face_shapes'),
            'is_polarized'  => isset($_POST['is_polarized']) ? 1 : 0,
            // Cấp 0 là giá trị THẬT (tròng trong suốt), nên so với chuỗi rỗng
            // chứ không dùng phép ép số như mấy ô trên — (int) '' cũng ra 0.
            'lens_category' => $cap !== '' && isset(((array) config('eyewear.lens_categories'))[(int) $cap])
                                ? (int) $cap : null,
            'rx_ready'      => isset($_POST['rx_ready']) ? 1 : 0,
        ];
    }

    /**
     * Khối B — 20 cột ra đời cùng bản vẽ 2026-08-29.
     *
     * @param string[]                 $images đường dẫn ảnh cuối cùng, để lọc bản đồ alt text
     * @param array<string,mixed>|null $cu     bản ghi cũ, để giữ giá trị chọn chưa theo khoá
     * @return array<string,mixed>
     */
    private function truongBanVe(string $trangThai, array $images, string $sale, string $saleFrom, string $saleTo, ?array $cu): array
    {
        $chu = static fn (string $key): ?string => trim((string) ($_POST[$key] ?? '')) ?: null;

        // Số tiền: 0 và số âm đều về null. Một mặt hàng giá vốn 0đ là ô bỏ
        // trống chứ không phải hàng cho không.
        $tien = static function (string $key): ?int {
            $v = (int) ($_POST[$key] ?? 0);

            return $v > 0 ? $v : null;
        };

        /*
         * ALT TEXT LỌC THEO DANH SÁCH ẢNH CUỐI CÙNG.
         *
         * Form gửi lên một ô alt cho mỗi ảnh ĐANG hiện, kể cả ảnh người dùng
         * vừa bỏ tick "Giữ". Không lọc thì bản đồ này phình mãi: mỗi lần xoá
         * một ảnh lại để lại một dòng alt trỏ vào đường dẫn không còn tồn tại,
         * và sau vài lần sửa thì cột JSON dài hơn cả danh sách ảnh.
         *
         * Lọc theo $images cũng là phép chặn duy nhất cần có: khoá của mảng
         * này là đường dẫn do form gửi, mà $images thì dựng từ CSDL và từ
         * chính ProductImageStorage — một khoá bịa ra sẽ không khớp cái nào.
         */
        $altTho = (array) ($_POST['image_alts'] ?? []);
        $alts   = [];

        foreach ($images as $duongDan) {
            $chuThich = trim((string) ($altTho[$duongDan] ?? ''));

            if ($chuThich !== '') {
                $alts[$duongDan] = utf8Substr($chuThich, 0, 160);
            }
        }

        return [
            'publish_status'    => $trangThai,
            'tags'              => $chu('tags'),
            'description_short' => $chu('description_short'),

            'cost_price'        => $tien('cost_price'),
            'sale_price'        => $sale !== '' ? (int) $sale : null,
            // Ngày rỗng phải là NULL chứ không phải chuỗi '': cột DATE nhận ''
            // sẽ thành '0000-00-00' ở chế độ SQL lỏng, một giá trị không so
            // sánh được với NOW() theo cách nào có nghĩa.
            'sale_from'         => $saleFrom !== '' ? $saleFrom : null,
            'sale_to'           => $saleTo !== '' ? $saleTo : null,
            'low_stock_at'      => ($v = (int) ($_POST['low_stock_at'] ?? 0)) > 0 ? $v : null,
            /* 'allow_backorder' ĐÃ RA KHỎI DANH SÁCH NÀY — 2026-08-29.
               Ô tick tương ứng đã gỡ khỏi form (lý do đầy đủ ở
               admin/products/_form.php). Giữ dòng này lại thì mỗi lần lưu bất
               kỳ sản phẩm nào cũng ghi 0 đè lên giá trị cũ, vì $_POST không
               còn mang khoá đó nữa — dọn giao diện mà xoá sạch dữ liệu. */

            'rim_type'          => $this->khoa('rim_type', 'rim_types', $cu['rim_type'] ?? null),
            /* Ô CHỌN, KHÔNG CÒN Ô CHỮ TỰ DO (2026-08-30). Trước đây người
               nhập gõ tay "Xám khói", nên mỗi cách gõ thành một giá trị riêng
               và không lọc theo màu được. Nay cột lưu KHOÁ của một mục trong
               nhóm 'mau-trong'. Dữ liệu cũ (chữ tiếng Việt có dấu) không khớp
               khoá nào nên rơi về null — form sẽ hiện "— chưa chọn —" và cửa
               hàng chọn lại; không có cách nào đoán đúng tự động vì "Xám khói"
               và "xám khói đậm" là hai thứ khác nhau. */
            'lens_color'        => $this->khoaTrong($_POST['lens_color'] ?? '', 'mau-trong'),
            'size_class'        => $this->khoa('size_class', 'size_classes', $cu['size_class'] ?? null),
            'is_uv400'          => isset($_POST['is_uv400']) ? 1 : 0,

            'image_alts'        => $alts === []
                ? null
                : json_encode($alts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            // Chỉ nhận http(s). Cột này in thẳng vào href/src ở trang bán hàng,
            // mà "javascript:..." cũng là một URL hợp lệ với filter_var.
            'video_url'         => $this->duongDanNgoai((string) ($_POST['video_url'] ?? '')),

            'rx_order_enabled'  => isset($_POST['rx_order_enabled']) ? 1 : 0,
            /* Ba cột này nay đối chiếu với `lens_options` (quản trị sửa
               được) thay vì với mảng gõ cứng trong config. `lens_coatings`
               lần đầu có ô nhập — cột đã có từ lâu nhưng không form nào ghi
               vào nó, nên bộ lọc "Tính năng / lớp phủ" trước đây phải đoán
               bằng biểu thức chính quy trên mô tả sản phẩm. */
            'lens_types'        => $this->khoaCsvTrong($_POST['lens_types'] ?? [], 'loai-trong'),
            'lens_indexes'      => $this->khoaCsvTrong($_POST['lens_indexes'] ?? [], 'chiet-suat'),
            'lens_coatings'     => $this->khoaCsvTrong($_POST['lens_coatings'] ?? [], 'lop-phu'),
            // Giữ nguyên văn kể cả dấu âm — xem chú thích cột trong schema.sql.
            // Chỉ chặn ký tự không thuộc một số đo: chữ, dấu chấm, dấu cộng trừ.
            'sph_max'           => $this->doKinh((string) ($_POST['sph_max'] ?? '')),
            'cyl_max'           => $this->doKinh((string) ($_POST['cyl_max'] ?? '')),
        ];
    }

    /**
     * Một khoá có thật trong bảng config/eyewear.php, hoặc null.
     *
     * Lọc chứ không tin: form gửi cái gì là chuyện của trình duyệt, mà mấy cột
     * này được đọc ngược lại thành nhãn hiển thị và thành điều kiện lọc. Không
     * có phép này thì `frame_shape` sẽ dần chứa "Mắt mèo", "mat-meo", "cat-eye"
     * — và bộ lọc in ra ba dáng gọng cho cùng một thứ.
     */
    private function khoa(string $field, string $bang, ?string $cu = null): ?string
    {
        $v = trim((string) ($_POST[$field] ?? ''));

        if ($v === '') {
            return null;
        }

        if (isset(((array) config('eyewear.' . $bang))[$v])) {
            return $v;
        }

        /*
         * GIÁ TRỊ CŨ KHÔNG KHỚP KHOÁ VẪN ĐƯỢC GIỮ.
         *
         * Trước 2026-08-29 mấy cột này nhận chữ tự do, nên dòng nhập từ trước
         * có thể đang giữ "Titanium" thay vì khoá 'titanium'. Form in nó ra làm
         * một option riêng ghi rõ "(giá trị cũ)" — xem $doOption trong _form.php
         * — và nếu người nhập không đổi thì nó quay về đây y nguyên.
         *
         * Trả null ở đây thay vì giữ lại thì thành MẤT DỮ LIỆU ÂM THẦM: sửa giá
         * một mặt hàng cũ là xoá luôn chất liệu gọng của nó.
         *
         * Chỉ nhận đúng chuỗi ĐANG CÓ trong CSDL, không nhận chuỗi bất kỳ — nếu
         * không thì cả phép chuẩn hoá này vô nghĩa.
         */
        return $cu !== null && $cu !== '' && $v === $cu ? $v : null;
    }

    /**
     * Số đo mắt dạng chuỗi (-8.00, +2.50), hoặc null.
     *
     * Để chuỗi chứ không DECIMAL vì DẤU là phần không được mất: trong nhãn
     * khoa, dấu phân biệt viễn với cận. Nhưng cột này in ra trang nên vẫn phải
     * chặn — chỉ cho số, dấu chấm và dấu cộng/trừ đứng đầu.
     */
    private function doKinh(string $raw): ?string
    {
        $v = str_replace(',', '.', trim($raw));

        return preg_match('/^[+-]?\d{1,2}(\.\d{1,2})?$/', $v) === 1 ? $v : null;
    }

    /**
     * URL ngoài an toàn để in vào href/src, hoặc null.
     *
     * filter_var(FILTER_VALIDATE_URL) một mình KHÔNG đủ: "javascript:alert(1)"
     * qua được nó. Phải kiểm luôn giao thức.
     */
    private function duongDanNgoai(string $raw): ?string
    {
        $v = trim($raw);

        if ($v === '' || filter_var($v, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($v, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? utf8Substr($v, 0, 500)
            : null;
    }

    /**
     * Lưới biến thể ở tab 4 — thêm, sửa, xoá trong cùng một lần POST.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * NHÃN TỰ GHÉP TỪ MÀU VÀ SIZE
     *
     * `product_variants`.`label` là NOT NULL và nằm trong khoá UNIQUE cùng
     * product_id; trang /quan-tri/bien-the lẫn ngăn kéo thông số ở trang bán
     * hàng đều in nó. Bản vẽ mới không có ô "Nhãn" nào — nó hỏi màu và size
     * riêng. Nên nhãn ghép lại ở đây ("Đen nhám · M") thay vì bắt gõ lần nữa
     * một thứ vừa gõ xong.
     *
     * Hai dòng cùng màu cùng size sẽ ra cùng nhãn và đụng khoá UNIQUE. Bắt lại
     * để báo bằng tiếng Việt thay vì để PDO ném lỗi 1062 ra màn hình trắng.
     *
     * @return string[] lỗi để báo lại, mảng rỗng nếu trọn vẹn
     */
    private function luuBienThe(string $productId): array
    {
        $mau = (array) ($_POST['variant_color'] ?? []);

        if ($productId === '' || $mau === []) {
            return [];
        }

        $loi     = [];
        $daDung  = [];

        foreach (array_keys($mau) as $i) {
            $vid   = trim((string) ($_POST['variant_id'][$i] ?? ''));
            $mauNy = trim((string) ($mau[$i] ?? ''));
            $size  = trim((string) ($_POST['variant_size'][$i] ?? ''));
            $size  = isset(((array) config('eyewear.size_classes'))[$size]) ? $size : '';

            /* Dòng đã có mà bị tick xoá — xoá trước, để một dòng vừa tick xoá
               vừa để trống không rơi vào nhánh "bỏ qua dòng rỗng" bên dưới. */
            if ($vid !== '' && isset($_POST['variant_del'][$i])) {
                // findForProduct chứ không find: id đến từ form, và không có
                // phép này thì sửa một con số trên trang là xoá được biến thể
                // của mặt hàng bất kỳ.
                if (VariantModel::findForProduct($vid, $productId) !== null) {
                    VariantModel::delete($vid);
                }

                continue;
            }

            // Dòng trống hoàn toàn: người dùng không đụng tới. Ba dòng trống
            // luôn có sẵn trong form nên đây là trường hợp thường gặp nhất.
            if ($mauNy === '') {
                continue;
            }

            $nhan = $size !== '' ? $mauNy . ' · ' . $size : $mauNy;

            if (isset($daDung[$nhan])) {
                $loi[] = sprintf('Hai biến thể trùng nhau ở "%s" — chỉ dòng đầu được lưu.', $nhan);
                continue;
            }

            $daDung[$nhan] = true;

            $gia   = trim((string) ($_POST['variant_price'][$i] ?? ''));
            $anh   = $this->anhBienThe($i);
            $dong  = [
                'label'          => utf8Substr($nhan, 0, 60),
                'color'          => utf8Substr($mauNy, 0, 60),
                'size'           => $size !== '' ? $size : null,
                'sku'            => strtoupper(trim((string) ($_POST['variant_sku'][$i] ?? ''))) ?: null,
                'price'          => $gia !== '' && (int) $gia > 0 ? (int) $gia : null,
                'stock_quantity' => max(0, (int) ($_POST['variant_stock'][$i] ?? 0)),
            ];

            // Không có ảnh mới thì ĐỪNG đụng cột `image` — gán null ở đây là
            // xoá mất ảnh cũ mỗi lần lưu form vì lý do khác.
            if ($anh !== null) {
                $dong['image'] = $anh;
            }

            if ($vid !== '' && VariantModel::findForProduct($vid, $productId) !== null) {
                VariantModel::update($vid, $dong);
                continue;
            }

            VariantModel::insert($dong + [
                'product_id' => $productId,
                // Thứ tự hiện = thứ tự dòng trong lưới. Người sắp lưới theo ý
                // họ thì trang bán hàng phải hiện đúng thứ tự ấy.
                'position'   => (int) $i,
            ]);
        }

        return $loi;
    }

    /**
     * Ảnh của MỘT dòng biến thể, hoặc null nếu dòng đó không chọn ảnh.
     *
     * ImageUploader::normalize() trả về danh sách đã đánh lại chỉ số từ 0, nên
     * gọi thẳng storeMany($_FILES['variant_image']) thì không còn biết ảnh nào
     * thuộc dòng nào. Tự bóc từng dòng ra rồi mới đưa vào.
     */
    private function anhBienThe(int|string $i): ?string
    {
        $truong = $_FILES['variant_image'] ?? [];

        if (!is_array($truong['name'] ?? null)) {
            return null;
        }

        $mot = [];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $khoa) {
            $mot[$khoa] = $truong[$khoa][$i] ?? null;
        }

        if ((int) ($mot['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $ket = ProductImageStorage::storeMany($mot, 1);

        return $ket['paths'][0] ?? null;
    }

    /**
     * Ô chọn-nhiều (dáng mặt, loại tròng, chiết suất) -> chuỗi CSV khoá chuẩn,
     * hoặc null.
     *
     * Nhận MẢNG từ các ô tick, lọc theo bảng trong config rồi ghép lại. Lọc
     * chứ không tin: form gửi cái gì là chuyện của trình duyệt, mà cột này
     * được đọc ngược lại thành nhãn hiển thị.
     */
    /**
     * Như khoaCsv() nhưng bảng khoá đọc từ `lens_options` (quản trị sửa được)
     * thay vì từ config.
     *
     * NHẬN CẢ MỤC ĐANG ẨN — ofGroup() chứ không visible(). Một sản phẩm đã gắn
     * mục nào đó rồi thì mục ấy bị ẩn đi vẫn phải giữ được: lọc mất nó ở đây
     * nghĩa là mỗi lần cửa hàng mở một sản phẩm cũ ra sửa gì đó rồi bấm Lưu,
     * thuộc tính ấy lặng lẽ biến mất. Ẩn là "thôi đề nghị cho hàng mới", không
     * phải "xoá khỏi hàng cũ".
     */
    private function khoaCsvTrong(mixed $raw, string $nhom): ?string
    {
        $tho = is_array($raw) ? $raw : preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        $hop = array_column(LensOptionModel::ofGroup($nhom), 'option_key');
        $ra  = [];

        foreach ($tho ?: [] as $khoa) {
            if (is_scalar($khoa) && in_array((string) $khoa, $hop, true)) {
                $ra[] = (string) $khoa;
            }
        }

        return $ra === [] ? null : implode(',', array_unique($ra));
    }

    /** Một khoá đơn của `lens_options`. Không khớp danh sách thì trả null. */
    private function khoaTrong(mixed $raw, string $nhom): ?string
    {
        $khoa = is_scalar($raw) ? (string) $raw : '';
        $hop  = array_column(LensOptionModel::ofGroup($nhom), 'option_key');

        return in_array($khoa, $hop, true) ? $khoa : null;
    }

    private function khoaCsv(mixed $raw, string $bang): ?string
    {
        $tho   = is_array($raw) ? $raw : preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        $bangK = (array) config('eyewear.' . $bang);
        $ra    = [];

        foreach ($tho ?: [] as $khoa) {
            if (is_scalar($khoa) && isset($bangK[(string) $khoa])) {
                $ra[] = (string) $khoa;
            }
        }

        return $ra === [] ? null : implode(',', array_unique($ra));
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

        /*
         * Ô "Ảnh chính của kính" là một ô file RIÊNG (`image_main_file`), theo
         * bản vẽ. Nó THAY tấm đang đứng đầu chứ không nối vào cuối:
         *
         *   · chưa có ảnh nào  -> tấm vừa chọn thành ảnh chính;
         *   · đã có ảnh chính  -> nút ghi "Đổi ảnh", nên tấm cũ phải rời đi.
         *
         * Loại tấm cũ khỏi $keep chứ không xoá thẳng: mọi ảnh rơi khỏi $images
         * đều được dọn ở vòng array_diff cuối hàm, một chỗ duy nhất.
         */
        $mainUp = ProductImageStorage::storeMany($_FILES['image_main_file'] ?? [], 1);

        if ($mainUp['paths'] !== [] && ($current[0] ?? null) !== null) {
            $keep = array_values(array_filter(
                $keep,
                static fn (string $path): bool => $path !== $current[0]
            ));
        }

        $room   = max(0, ProductImageStorage::MAX_FILES - count($keep) - count($mainUp['paths']));
        $upload = ProductImageStorage::storeMany($_FILES['image_files'] ?? [], $room);

        /*
         * THỨ TỰ Ở ĐÂY CHÍNH LÀ Ý NGHĨA: phần tử đầu là ảnh chính.
         *
         * Không còn nút radio "Ảnh chính" nào cả — bản vẽ không có nó, ô ảnh
         * chính đứng thành khối riêng ở trên cùng. Nên ảnh chính được quyết
         * bằng đúng một luật: tấm vừa tải vào ô ảnh chính, nếu không có thì
         * tấm đầu tiên còn được giữ lại.
         *
         * Hệ quả cố ý: xoá ảnh chính thì tấm kế tiếp trong bộ ảnh tự lên thay,
         * không để sản phẩm rơi vào cảnh có ảnh mà không có ảnh đại diện.
         */
        $images = array_merge($mainUp['paths'], $keep, $upload['paths']);

        // Ảnh bị gỡ khỏi danh sách thì xoá khỏi đĩa luôn — nhưng chỉ ảnh do
        // chính khu quản trị tải lên; ProductImageStorage::remove() tự bỏ qua
        // đường dẫn nằm ngoài thư mục upload (ảnh đi kèm mã nguồn).
        foreach (array_diff($current, $images) as $gone) {
            ProductImageStorage::remove($gone);
        }

        return [$images, array_merge($mainUp['errors'], $upload['errors'])];
    }

    /**
     * Slug bộ sưu tập hợp lệ, hoặc null.
     */
    private function collection(string $slug): ?string
    {
        if ($slug === '') {
            return null;
        }

        /* Đối chiếu với bảng `collections`, kể cả bộ đang ẩn — xem
           CollectionModel::labels(). Chỉ nhận bộ đang hiện thì gắn sản phẩm
           vào một bộ sắp ra mắt (còn ẩn) sẽ bị từ chối, mà đó đúng là lúc
           người ta cần gắn. */
        return isset(CollectionModel::labels()[$slug]) ? $slug : null;
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
