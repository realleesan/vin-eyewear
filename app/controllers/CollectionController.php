<?php

/**
 * CollectionController — bộ sưu tập theo mùa.
 *
 *   /bo-suu-tap          index()  danh sách các bộ đang trưng bày
 *   /bo-suu-tap/{slug}   show()   trang chi tiết của một bộ
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TRANG CHI TIẾT: ĐÃ BỎ NGÀY 2026-08-25, DỰNG LẠI NGÀY 2026-08-27
 *
 * Bản trước KHÔNG có trang này: nút "Xem chi tiết" dẫn thẳng sang
 * /san-pham?collection=<slug>. Lý lẽ hồi đó — và nó vẫn đúng — là một trang
 * liệt kê lại đúng những sản phẩm mà trang danh mục đã liệt kê, nhưng thiếu
 * toàn bộ phần lọc, sắp xếp và phân trang, thì chỉ là một bản sao nghèo hơn.
 *
 * Chỗ sai của lý lẽ đó là nó coi bộ sưu tập chỉ là một BỘ LỌC. Với kính thời
 * trang thì không: một bộ có ngày ra mắt, có ảnh lookbook, có câu chuyện về
 * việc nó dành cho kiểu ngày nào — những thứ không có ô nào trên trang danh
 * mục chứa nổi.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA LỚP THÔNG TIN, VÀ RANH GIỚI VỚI TRANG SẢN PHẨM
 *
 *   Lớp 1  cấp BỘ — mùa, xuất xứ, câu chuyện, đối tượng, bảng màu, quy mô.
 *          Không trang nào khác có chỗ cho chúng.
 *   Lớp 2  cấp MẪU — nhưng ở đây là một BẢNG SO SÁNH, không phải sáu bản sao
 *          của trang sản phẩm. Bảng trả lời câu "mẫu nào hợp tôi"; trang sản
 *          phẩm trả lời câu "tôi mua cái này". Ngăn kéo thông số là chỗ nối
 *          hai câu đó, và nó KẾT THÚC bằng nút sang trang sản phẩm.
 *   Lớp 3  hỗ trợ — chọn cỡ, dáng mặt, bảo quản, FAQ, CTA. Ba khối đầu là
 *          kiến thức chung nên nằm ở config/eyewear.php; FAQ theo bộ nên nằm
 *          ở bảng collection_faqs.
 *
 * Hệ quả cho người sửa sau: trang này KHÔNG được có nút thêm-vào-giỏ, không
 * chọn phương án, không phân trang, không ô sắp xếp. Mỗi thứ đó là một bước
 * tiến tới đúng bản sao nghèo hơn mà quyết định 2026-08-25 đã lo.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NGĂN KÉO MỞ BẰNG ĐỊA CHỈ (?mau=<slug>), KHÔNG BẰNG JAVASCRIPT
 *
 * Cùng cơ chế mà ngăn kéo đơn hàng của khu quản trị dùng (?xem=<id>): nút ✕ và
 * lớp nền mờ đều là thẻ <a> trỏ về chính trang này khi bỏ tham số ấy. Tắt JS
 * thì đóng mở vẫn chạy, chỉ là mỗi lần tải lại trang.
 *
 * Cái giá: mở một mẫu là một lượt tải. Đổi lại, mỗi mẫu có một địa chỉ gửi cho
 * nhau được, nút Lùi của trình duyệt làm đúng việc người dùng chờ đợi, và
 * trang giữ được lời hứa "tắt JS mọi luồng vẫn chạy" của cả dự án.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CollectionController extends BaseController
{
    /** Số dáng gọng in ra ở cụm "lọc nhanh" dưới bảng so sánh. */
    private const SHAPES = 6;

    /**
     * Câu mặc định của đầu trang /bo-suu-tap.
     *
     * Đây là chữ đang hiện trước khi bảng `site_texts` ra đời, và nó vẫn là
     * nguồn chân lý mỗi khi CSDL im lặng — bảng chưa tồn tại, dòng bị xoá, hay
     * nhân viên lưu một ô trống. Xem SiteTextModel::get().
     */
    /* public: CollectionAdminController đọc lại đúng hai câu này để ô nhập
       trong khu quản trị hiện y hệt chữ khách đang thấy. Hai bản chép tay ở
       hai file là hai bản sẽ lệch nhau. */
    public const DAU_TRANG = [
        'tieu_de'  => 'Bộ sưu tập',
        'doan_dan' => 'Mỗi bộ là một cách chọn gọng và tròng cho một kiểu ngày. '
                    . 'Mở bộ nào nghe hợp với bạn để xem kỹ, rồi lọc thẳng sang danh mục.',
    ];

    public function index(): void
    {
        $collections = CollectionModel::visible();

        $tieuDe  = SiteTextModel::get(SiteTextModel::BST_TIEU_DE, self::DAU_TRANG['tieu_de']);
        $doanDan = SiteTextModel::get(SiteTextModel::BST_DOAN_DAN, self::DAU_TRANG['doan_dan']);

        $this->renderView('collection/index', [
            // Thẻ <title> đi theo tiêu đề cửa hàng đặt, không còn gõ cứng: đổi
            // tiêu đề trang mà tên trên tab trình duyệt vẫn nói câu cũ thì hai
            // chỗ nói khác nhau về cùng một trang.
            'pageTitle'   => $tieuDe . ' — Vin Eyewear',
            // metaDesc lấy luôn đoạn dẫn, cùng một lý do: đoạn dẫn LÀ câu giới
            // thiệu trang, và giữ hai bản khác nhau nghĩa là kết quả tìm kiếm
            // hứa một đằng còn trang mở ra một nẻo.
            'metaDesc'    => excerpt($doanDan, 155),
            'collections' => $collections,
            'headTitle'   => $tieuDe,
            'headLead'    => $doanDan,
        ]);
    }

    /**
     * @param string $slug lấy từ route 'bo-suu-tap/{slug}' trong config/routes.php
     */
    public function show(string $slug = ''): void
    {
        $collection = $slug === '' ? null : CollectionModel::findVisibleBySlug($slug);

        /*
         * Không có bộ nào mang slug đó, hoặc bộ đang ẩn -> 404 thật.
         *
         * Ẩn cũng là 404 chứ không phải một trang "bộ này đã hết mùa": bộ bị
         * ẩn thường là bộ CHƯA ra mắt (xem ghi chú ở form quản trị), và một
         * trang nói tên bộ sắp ra là làm lộ kế hoạch của cửa hàng cho bất kỳ
         * ai đoán trúng slug.
         */
        if ($collection === null) {
            $this->notFound();
            return;
        }

        // Cả bộ, không phân trang — xem ProductModel::inCollection.
        $products = ProductModel::inCollection($slug);

        /*
         * Mẫu đang mở trong ngăn kéo. Slug lạ thì coi như KHÔNG mở ngăn kéo
         * nào, chứ không 404: cả trang vẫn đúng và vẫn đọc được, chỉ thiếu một
         * lớp phủ. Trả 404 cho cả trang vì một tham số phụ sai là phạt người
         * dùng vì một liên kết cũ mà chính cửa hàng phát ra.
         *
         * Tìm trong $products chứ không truy vấn lại: mẫu phải THUỘC BỘ NÀY
         * thì ngăn kéo mới có nghĩa. Không có phép kiểm đó thì
         * /bo-suu-tap/nang-he?mau=<một mẫu bộ khác> vẽ ra một ngăn kéo nói về
         * mặt hàng không nằm trong bảng phía sau nó.
         */
        $mauSlug = trim((string) ($_GET['mau'] ?? ''));
        $open    = null;

        foreach ($products as $p) {
            if ($p['slug'] === $mauSlug) {
                $open = $p;
                break;
            }
        }

        $stats = ProductModel::collectionStats($slug);

        $this->renderView('collection/detail', [
            'pageTitle'  => $this->metaTitle($collection),
            'metaDesc'   => $this->metaDesc($collection),
            'collection' => $collection,
            'products'   => $products,
            'total'      => $stats['count'],
            'minPrice'   => $stats['minPrice'],
            'maxPrice'   => $this->maxPrice($products),
            'skuCount'   => $this->skuCount($products),

            // Lớp 1 — ba cột JSON, giải mã một lần ở đây thay vì trong view.
            'audience'   => CollectionModel::jsonField($collection, 'audience'),
            'palette'    => CollectionModel::jsonField($collection, 'palette'),
            'signature'  => CollectionModel::jsonField($collection, 'signature'),

            // Lớp 2 — ngăn kéo và lối tắt vào danh mục đã lọc.
            'open'       => $open,
            'openVariants' => $open === null ? [] : VariantModel::forProduct($open['id']),
            'shapes'     => $this->shapes($products, $slug),

            // Lớp 3 — hai bảng dựng từ chính hàng của bộ, cộng nội dung chung.
            'sizeTable'  => EyewearSpecs::sizeTable($products),
            'faceTable'  => EyewearSpecs::faceTable($products),
            'sizeGuide'  => (array) config('eyewear.size_guide'),
            'care'       => (array) config('eyewear.care'),
            'faqs'       => $this->faqs((string) $collection['id']),

            'others'     => $this->others($collection),
        ]);
    }

    // ========================================================================
    // NHỮNG PHÉP NHỎ, TÁCH RA CHO show() ĐỌC ĐƯỢC TRONG MỘT MÀN HÌNH
    // ========================================================================

    /**
     * Tiêu đề thẻ <title>: cột meta_title nếu cửa hàng đã viết, không thì dựng.
     *
     * Bản dựng có kèm "Bộ sưu tập" vì tên bộ một mình ("Nắng hè") không nói
     * được đây là trang gì khi nó nằm giữa mười kết quả tìm kiếm.
     */
    private function metaTitle(array $c): string
    {
        $rieng = trim((string) ($c['meta_title'] ?? ''));

        return $rieng !== '' ? $rieng : $c['name'] . ' — Bộ sưu tập — Vin Eyewear';
    }

    /**
     * Thẻ <meta description>: cột riêng, rồi tagline, rồi intro.
     *
     * Ba mức chứ không hai: bộ nào cũng có ít nhất một trong ba, nên trang
     * không bao giờ ra đời với ô mô tả rỗng — thứ mà công cụ tìm kiếm sẽ tự
     * bịa bằng cách cắt một đoạn bất kỳ trên trang.
     */
    private function metaDesc(array $c): string
    {
        foreach (['meta_description', 'tagline', 'intro'] as $cot) {
            $v = trim((string) ($c[$cot] ?? ''));

            if ($v !== '') {
                return excerpt($v, 155);
            }
        }

        return '';
    }

    /**
     * Câu hỏi thường gặp của bộ — RỖNG khi bảng chưa tồn tại.
     *
     * `collection_faqs` ra đời cùng migration 2026-08-27-bo-suu-tap-khung-ba-lop,
     * mà mã lên hosting bằng FTP tự động còn migration thì phải bấm tay: giữa
     * hai việc đó có một khoảng dài hàng giờ. Hỏi thẳng một bảng chưa tồn tại
     * trong khoảng ấy là lỗi 1146 và cả trang bộ sưu tập trả 500 — mất luôn
     * chín khối không liên quan gì tới FAQ.
     *
     * Cùng lối mà CollectionAdminController dùng cho cột `story`, và cùng lối
     * mà Database::tableExists() sinh ra để phục vụ (xem chú thích trong đó).
     */
    private function faqs(string $collectionId): array
    {
        if (!Database::tableExists('collection_faqs')) {
            return [];
        }

        return CollectionFaqModel::forCollection($collectionId);
    }

    /** Giá cao nhất của bộ, hoặc null. Cặp với minPrice để in một khoảng. */
    private function maxPrice(array $products): ?int
    {
        $gia = array_filter(array_map(static fn ($p) => (int) ($p['price'] ?? 0), $products));

        return $gia === [] ? null : max($gia);
    }

    /**
     * Tổng SKU của bộ: mỗi mẫu tính bằng số phương án của nó, tối thiểu 1.
     *
     * Tối thiểu 1 vì mặt hàng KHÔNG có phương án nào vẫn là một SKU bán được
     * (xem chú thích bảng product_variants trong schema.sql). Đếm 0 cho những
     * mặt hàng ấy thì con số "22 SKU" trên trang sẽ nhỏ hơn số hàng thật.
     */
    private function skuCount(array $products): int
    {
        if ($products === []) {
            return 0;
        }

        $ids = array_column($products, 'id');
        $cho = implode(',', array_fill(0, count($ids), '?'));

        $dem = array_column(
            Database::fetchAll(
                "SELECT product_id, COUNT(*) AS n
                   FROM product_variants
                  WHERE is_active = 1 AND product_id IN ({$cho})
                  GROUP BY product_id",
                $ids
            ),
            'n',
            'product_id'
        );

        $tong = 0;

        foreach ($ids as $id) {
            $tong += max(1, (int) ($dem[$id] ?? 0));
        }

        return $tong;
    }

    /**
     * Dáng gọng có mặt trong bộ, cho cụm "lọc nhanh" dưới bảng so sánh.
     *
     * Mỗi chip là trang danh mục ĐÃ bật sẵn hai tiêu chí: bộ này và dáng gọng
     * đó. Đây là việc trang này làm mà một đường /san-pham?collection= trơn
     * không làm được — nó tiết kiệm cho người dùng đúng một cú bấm mò trong
     * cột lọc, và nó biết trước là bấm vào sẽ có hàng.
     *
     * Cắt ở self::SHAPES: quá số đó thì cụm chip dài bằng một cột lọc thật, mà
     * nó không phải cột lọc thật — danh sách đầy đủ và số đếm nằm ở trang danh
     * mục.
     *
     * @return array<int, array{key:string, label:string, url:string}>
     */
    private function shapes(array $products, string $slug): array
    {
        $thay = [];

        foreach ($products as $product) {
            foreach (ProductTaxonomy::of($product)['shape'] as $key => $label) {
                $thay[$key] = $label;
            }
        }

        $ra = [];

        foreach (array_slice($thay, 0, self::SHAPES, true) as $key => $label) {
            $ra[] = [
                'key'   => $key,
                'label' => $label,
                // http_build_query lo phần mã hoá — tự nối chuỗi ở đây là chỗ
                // slug có ký tự lạ sẽ lọt ra ngoài chưa mã hoá.
                'url'   => '/san-pham?' . http_build_query(['collection' => $slug, 'shape' => $key]),
            ];
        }

        return $ra;
    }

    /**
     * Các bộ KHÁC, cho dải cuối trang.
     *
     * Lọc chính nó ra bằng slug chứ không bằng id: cùng một giá trị mà URL
     * đang dùng, nên đọc lại thấy ngay quan hệ.
     */
    private function others(array $collection): array
    {
        return array_values(array_filter(
            CollectionModel::visible(),
            static fn (array $c): bool => $c['slug'] !== $collection['slug']
        ));
    }

    /**
     * Trả 404 qua ErrorController để trang lỗi đồng nhất với phần còn lại.
     * Cùng lối với ProductDetailController::notFound().
     */
    private function notFound(): void
    {
        http_response_code(404);

        $controller = new ErrorController();
        $controller->notFound();
    }
}
