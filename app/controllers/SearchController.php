<?php

/**
 * SearchController — tìm kiếm toàn site (/tim-kiem?q=...).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CÓ TRANG NÀY
 *
 * Ô tìm kiếm trên đầu trang trước đây gửi thẳng về /san-pham?q=..., tức là chỉ
 * lọc được SẢN PHẨM. Người dùng gõ "bảo hành" hay "workshop" vào một ô tìm
 * kiếm nằm giữa đầu trang thì đang hỏi cả site, không riêng catalog — và họ
 * nhận về "không tìm thấy sản phẩm nào", một câu trả lời sai cho câu hỏi họ
 * thực sự đặt ra.
 * ─────────────────────────────────────────────────────────────────────────────
 * BỐN NHÓM, HAI CÁCH TÌM KHÁC NHAU
 *
 *   sản phẩm · bài viết   -> tìm trong CSDL bằng LIKE. Collation
 *                            utf8mb4_unicode_ci bỏ qua hoa/thường VÀ dấu, nên
 *                            gõ "kinh mat" vẫn ra "Kính mát".
 *   cơ sở · chính sách    -> lọc bằng PHP. Cơ sở chỉ có vài dòng, còn chính
 *                            sách nằm ở config chứ không ở CSDL — không có
 *                            bảng nào để LIKE vào.
 *
 * Phía PHP phải TỰ bỏ dấu để khớp cho giống CSDL: xem matches() bên dưới.
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG PHÂN TRANG, CÓ TRẦN CHO TỪNG NHÓM
 *
 * Trang này để TRẢ LỜI NHANH "có gì liên quan không", không phải để duyệt.
 * Mỗi nhóm cắt ở vài kết quả đầu và kèm một liên kết sang đúng trang chuyên
 * dụng của nó (/san-pham?q=, /su-kien) — nơi đã có sẵn bộ lọc và phân trang.
 * Nhồi phân trang vào đây là dựng lại hai trang đó lần thứ hai.
 */

class SearchController extends BaseController
{
    /** Trần mỗi nhóm. Đủ để thấy "có gì", chưa đủ để phải cuộn. */
    private const MAX_PRODUCTS = 8;
    private const MAX_ARTICLES = 6;
    private const MAX_POLICIES = 6;

    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));

        /*
         * Cắt ở 120 ký tự. Chuỗi này đi vào tiêu đề trang, vào <meta> và vào
         * mệnh đề LIKE; một truy vấn dài vài nghìn ký tự chỉ tổ làm ba chỗ đó
         * xấu đi chứ không tìm ra thêm gì.
         */
        $q = utf8Substr($q, 0, 120);

        $products = ['items' => [], 'total' => 0];
        $articles = [];
        $stores   = [];
        $policies = [];

        if ($q !== '') {
            $products = ProductModel::filter(['q' => $q], 1, self::MAX_PRODUCTS);
            $articles = EventModel::search($q, self::MAX_ARTICLES);
            $stores   = $this->searchStores($q);
            $policies = $this->searchPolicies($q);
        }

        $total = $products['total'] + count($articles) + count($stores) + count($policies);

        $this->renderView('search/index', [
            'pageTitle' => $q === ''
                ? 'Tìm kiếm — Vin Eyewear'
                : sprintf('Tìm "%s" — Vin Eyewear', $q),
            'metaDesc'  => 'Tìm sản phẩm, bài viết, cơ sở và chính sách của Vin Eyewear.',

            /*
             * noindex: mỗi từ khoá là một URL, để máy tìm kiếm lập chỉ mục thì
             * sinh ra vô số trang mỏng trùng nội dung với /san-pham và /su-kien.
             */
            'noindex'   => true,

            'q'         => $q,
            'products'  => $products['items'],
            'productTotal' => $products['total'],
            'articles'  => $articles,
            'stores'    => $stores,
            'policies'  => $policies,
            'total'     => $total,
        ]);
    }

    /**
     * Chuỗi này có chứa từ khoá không? — so sánh BỎ DẤU, không phân biệt hoa
     * thường, để khớp cho giống cách CSDL đang khớp.
     *
     * Mượn slugify(): nó đã có sẵn bảng bỏ dấu tiếng Việt và hạ chữ thường,
     * nên "Bảo hành trọn đời" và "bao hanh" cùng quy về dạng có gạch nối rồi
     * so bằng str_contains. Viết bảng bỏ dấu thứ hai ở đây là chép đôi một thứ
     * chỉ đúng khi cả hai bản cùng được sửa.
     */
    private static function matches(string $haystack, string $needle): bool
    {
        $h = slugify($haystack);
        $n = slugify($needle);

        return $n !== '' && str_contains($h, $n);
    }

    /**
     * Cơ sở khớp theo tên hoặc địa chỉ. Lọc bằng PHP vì bảng chỉ có vài dòng —
     * thêm một câu LIKE vào CSDL cho hai bản ghi là không đáng.
     */
    private function searchStores(string $q): array
    {
        return array_values(array_filter(
            StoreModel::active(),
            static fn (array $s): bool =>
                self::matches((string) ($s['name'] ?? ''), $q)
                || self::matches((string) ($s['address'] ?? ''), $q)
        ));
    }

    /**
     * Câu hỏi thường gặp khớp từ khoá.
     *
     * Trả về cả `group` để dựng liên kết /chinh-sach#<id> — bấm vào là nhảy
     * đúng nhóm chứ không đổ khách xuống đầu một trang dài.
     */
    private function searchPolicies(string $q): array
    {
        $hits = [];

        foreach (config('policy.groups') ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (count($hits) >= self::MAX_POLICIES) {
                    return $hits;
                }

                if (self::matches($item['q'] ?? '', $q) || self::matches($item['a'] ?? '', $q)) {
                    $hits[] = [
                        'question' => $item['q'] ?? '',
                        'answer'   => $item['a'] ?? '',
                        'groupId'  => $group['id'] ?? '',
                        'group'    => $group['label'] ?? '',
                    ];
                }
            }
        }

        return $hits;
    }
}
