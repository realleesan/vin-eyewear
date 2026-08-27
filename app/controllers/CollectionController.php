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
 * mục chứa nổi, và cũng không phải thứ người ta vừa cuộn lưới hàng vừa đọc.
 *
 * Nên trang này KHÔNG dựng lại lưới hàng. Nó làm ba việc mà
 * /san-pham?collection= không làm được, rồi giao lại:
 *
 *   1. KỂ — ảnh bìa lớn, ngày ra mắt, câu dẫn, và cột `story` nhiều đoạn dành
 *      riêng cho trang này (xem migration 2026-08-27-bo-suu-tap-trang-chi-tiet).
 *   2. TÓM TẮT — bộ này có bao nhiêu món, rẻ nhất từ bao nhiêu, đang có những
 *      dáng gọng nào. Trang danh mục chỉ trả lời được sau khi người dùng đã
 *      bấm vào và đọc cột lọc.
 *   3. DẪN ĐI — mọi nút trên trang đều đổ về /san-pham?collection=<slug>,
 *      trong đó cụm dáng gọng còn kèm sẵn ?shape= tương ứng.
 *
 * Vài thẻ sản phẩm ở giữa trang là HÀNG MẪU (tối đa self::PREVIEW món, hàng
 * nổi bật trước), không phải danh sách đầy đủ và cố ý không có phân trang —
 * đủ để tin là bộ này có hàng thật, không đủ để thay trang danh mục.
 *
 * `slug` TRÊN URL LÀ SLUG CỦA BẢNG `collections`, cũng chính là chuỗi nằm
 * trong `products.collection`. Đổi slug của một bộ đã phát hành là làm chết cả
 * trang này lẫn mọi liên kết đã chia sẻ — CollectionAdminController chặn sẵn.
 * ─────────────────────────────────────────────────────────────────────────────
 */

class CollectionController extends BaseController
{
    /**
     * Số sản phẩm mẫu trên trang chi tiết.
     *
     * Bốn là một hàng thẻ trên màn hình rộng và hai hàng trên máy tính bảng —
     * hết hàng mẫu là mắt gặp ngay nút "Xem tất cả", đúng lúc người xem vừa
     * tin là bộ này có hàng. Tám thì lấp đầy màn hình thứ hai và người ta bắt
     * đầu cuộn như thể đây là trang danh mục, mà nó thì không có bộ lọc nào.
     */
    private const PREVIEW = 4;

    /** Số dáng gọng in ra ở cụm "lọc nhanh". */
    private const SHAPES = 6;

    public function index(): void
    {
        $collections = CollectionModel::visible();

        $this->renderView('collection/index', [
            'pageTitle'   => 'Bộ sưu tập — Vin Eyewear',
            'metaDesc'    => 'Các bộ sưu tập kính mắt theo mùa của Vin Eyewear: '
                           . 'gọng, tròng và phong cách được chọn theo từng nhu cầu.',
            'collections' => $collections,
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

        $stats    = ProductModel::collectionStats($slug);
        $products = $stats['count'] > 0 ? ProductModel::inCollection($slug, self::PREVIEW) : [];

        /*
         * Các bộ KHÁC, cho dải cuối trang. Lọc chính nó ra bằng slug chứ không
         * bằng id: cùng một giá trị mà URL đang dùng, nên đọc lại thấy ngay
         * quan hệ, và không phụ thuộc vào việc find() có trả cột id hay không.
         */
        $others = array_values(array_filter(
            CollectionModel::visible(),
            static fn (array $c): bool => $c['slug'] !== $collection['slug']
        ));

        $this->renderView('collection/detail', [
            'pageTitle'  => $collection['name'] . ' — Bộ sưu tập — Vin Eyewear',
            'metaDesc'   => excerpt(
                (string) ($collection['tagline'] ?? '') !== ''
                    ? (string) $collection['tagline']
                    : (string) ($collection['intro'] ?? ''),
                155
            ),
            'collection' => $collection,
            'products'   => $products,
            'total'      => $stats['count'],
            'minPrice'   => $stats['minPrice'],
            'shapes'     => $this->shapes($products, $slug),
            'others'     => $others,
        ]);
    }

    /**
     * Dáng gọng có mặt trong bộ, cho cụm "lọc nhanh".
     *
     * ĐỌC TỪ HÀNG MẪU, không phải từ cả bộ. Đó là một đánh đổi cố ý: đọc cả bộ
     * là kéo thêm mọi dòng sản phẩm về RAM chỉ để dựng vài con chip, mà chip
     * này không hứa "đây là toàn bộ dáng gọng của bộ" — nó là lối tắt vào
     * trang danh mục, nơi cột lọc bên trái mới là danh sách đầy đủ và có số
     * đếm. Bộ nào có nhiều dáng hơn thì người dùng gặp chúng ở đó.
     *
     * Cắt ở self::SHAPES: quá số đó thì cụm chip dài bằng một cột lọc thật,
     * và nó không phải cột lọc thật.
     *
     * @return array<int, array{key:string, label:string, url:string}>
     */
    private function shapes(array $products, string $slug): array
    {
        $found = [];

        foreach ($products as $product) {
            foreach (ProductTaxonomy::of($product)['shape'] as $key => $label) {
                $found[$key] = $label;
            }
        }

        $out = [];

        foreach (array_slice($found, 0, self::SHAPES, true) as $key => $label) {
            $out[] = [
                'key'   => $key,
                'label' => $label,
                // http_build_query lo phần mã hoá — tự nối chuỗi ở đây là chỗ
                // slug có dấu gạch hay ký tự lạ sẽ lọt ra ngoài chưa mã hoá.
                'url'   => '/san-pham?' . http_build_query(['collection' => $slug, 'shape' => $key]),
            ];
        }

        return $out;
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
