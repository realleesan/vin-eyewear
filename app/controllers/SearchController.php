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
 * dụng của nó (/san-pham?q=) — nơi đã có sẵn bộ lọc và phân trang. Nhồi phân
 * trang vào đây là dựng lại trang đó lần thứ hai.
 */

class SearchController extends BaseController
{
    /* Trần mỗi nhóm. Đủ để thấy "có gì", chưa đủ để phải cuộn.

       MAX_ARTICLES đã bỏ cùng nhóm "Bài viết & sự kiện" (2026-08-26) — nhóm
       đó tìm trong bảng `events`, mà bảng ấy không còn. */
    private const MAX_PRODUCTS = 8;
    private const MAX_POLICIES = 6;

    /* Trần cho DANH SÁCH GỢI Ý (X29). Nhỏ hơn hẳn hai trần trên: gợi ý hiện
       ra trong lúc người ta đang gõ, và một danh sách dài đọc chậm hơn tự gõ
       nốt từ còn lại. */
    private const MAX_SUGGEST = 8;

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
        $stores   = [];
        $policies = [];

        if ($q !== '') {
            $products = ProductModel::filter(['q' => $q], 1, self::MAX_PRODUCTS);
            $stores   = $this->searchStores($q);
            $policies = $this->searchPolicies($q);
        }

        $total = $products['total'] + count($stores) + count($policies);

        $this->renderView('search/index', [
            'pageTitle' => $q === ''
                ? 'Tìm kiếm — Vin Eyewear'
                : sprintf('Tìm "%s" — Vin Eyewear', $q),
            'metaDesc'  => 'Tìm sản phẩm, cơ sở và chính sách của Vin Eyewear.',

            /*
             * noindex: mỗi từ khoá là một URL, để máy tìm kiếm lập chỉ mục thì
             * sinh ra vô số trang mỏng trùng nội dung với /san-pham.
             */
            'noindex'   => true,

            'q'         => $q,
            'products'  => $products['items'],
            'productTotal' => $products['total'],
            'stores'    => $stores,
            'policies'  => $policies,
            'total'     => $total,
        ]);
    }

    /**
     * GỢI Ý TỪ KHOÁ KHI ĐANG GÕ — X29 / Q10, chốt 04/09/2026 (GET /tim-kiem/goi-y).
     *
     * ─────────────────────────────────────────────────────────────────────────
     * PHẠM VI ĐÚNG BẰNG QUYẾT ĐỊNH, KHÔNG HƠN
     *
     * X29 chốt "tìm kiếm gần đúng" của giai đoạn 1 gồm ba thứ: bỏ dấu, không
     * phân biệt hoa thường, CỘNG THÊM gợi ý từ khoá khi đang gõ. Hai thứ đầu
     * đã có sẵn nhờ collation utf8mb4_unicode_ci; đây là thứ ba.
     *
     * KHÔNG làm dung sai lỗi gõ sai chính tả (fuzzy thật) — X29 nói rõ hạng
     * mục đó để giai đoạn sau. Ghi ra đây vì đó chính là thứ dễ bị "tiện tay
     * làm luôn": thêm một phép Levenshtein vào vòng lặp trông vô hại, nhưng nó
     * biến một quyết định đã chốt thành một quyết định đã bị lặng lẽ đổi.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * TẠI SAO GỢI Ý CHỈ LÀ CHỮ, KHÔNG PHẢI LIÊN KẾT
     *
     * Trả về danh sách CHUỖI để đổ vào <datalist> của chính ô tìm kiếm. Chọn
     * một gợi ý là điền chữ đó vào ô rồi gửi form như thường — không có đường
     * đi tắt nào riêng, nên không có luồng thứ hai phải bảo trì, và tắt
     * JavaScript thì ô tìm kiếm vẫn hoạt động y như trước.
     *
     * Trộn cả tên sản phẩm, tên cơ sở và câu hỏi chính sách vì trang kết quả
     * cũng tìm cả ba nhóm — gợi ý mà hẹp hơn kết quả thì nó dạy người dùng một
     * bản đồ sai về thứ tìm được.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function suggest(): void
    {
        $q = utf8Substr(trim((string) ($_GET['q'] ?? '')), 0, 60);

        /* NGƯỠNG HAI KÝ TỰ. Một ký tự khớp gần như mọi thứ, nên danh sách trả
           về là ngẫu nhiên trong mắt người đọc — và nó tốn một lượt gọi máy
           chủ cho mỗi lần chạm phím đầu tiên của mọi người dùng. */
        $goiY = utf8Length($q) < 2 ? [] : $this->gomGoiY($q);

        /* Không đệm ở proxy hay trình duyệt: danh sách sản phẩm đổi theo tồn
           kho và trạng thái hiện, mà một gợi ý cũ dẫn tới trang trắng thì tệ
           hơn không gợi ý. Cho phép đệm riêng tư 30 giây để người gõ nhanh
           không bắn hai lượt giống hệt nhau. */
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=30');

        echo json_encode(['goi_y' => $goiY], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Gom gợi ý từ ba nguồn, bỏ trùng, cắt ở MAX_SUGGEST.
     *
     * @return string[]
     */
    private function gomGoiY(string $q): array
    {
        $ra = ProductModel::goiYTen($q, self::MAX_SUGGEST);

        foreach ($this->searchStores($q) as $s) {
            $ra[] = (string) ($s['name'] ?? '');
        }

        foreach ($this->searchPolicies($q) as $p) {
            $ra[] = (string) ($p['question'] ?? '');
        }

        /* Bỏ trùng KHÔNG PHÂN BIỆT HOA THƯỜNG, giữ dạng viết đầu tiên gặp:
           "Kính mát" và "kính mát" là một gợi ý, và hiện cả hai trông như
           hệ thống không biết mình đang nói gì. array_unique() thẳng thì
           không bắt được cặp đó. */
        $thay = [];
        $sach = [];

        foreach ($ra as $chuoi) {
            $chuoi = trim($chuoi);
            $khoa  = mb_strtolower($chuoi, 'UTF-8');

            if ($chuoi === '' || isset($thay[$khoa])) {
                continue;
            }

            $thay[$khoa] = true;
            $sach[]      = $chuoi;

            if (count($sach) >= self::MAX_SUGGEST) {
                break;
            }
        }

        return $sach;
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
