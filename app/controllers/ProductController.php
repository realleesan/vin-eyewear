<?php

/**
 * ProductController — danh sách sản phẩm (/san-pham).
 *
 * Dựng theo "Vin Eyewear Category.dc.html" (Claude Design): đầu trang nền hồng
 * phấn, bộ lọc thành CỘT BÊN TRÁI dính theo cuộn, kết quả bên phải.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BỘ LỌC ĐỌC TỪ URL, KHÔNG TỪ TRẠNG THÁI TRONG TRÌNH DUYỆT
 *
 * Mọi tiêu chí đang bật đều nằm trong query string, nên mỗi trạng thái lọc là
 * một địa chỉ chia sẻ được, quay lại được bằng nút Back và lập chỉ mục được.
 * Tên tham số giữ nguyên từ bản trước để các liên kết sẵn có (mega menu, khối
 * "chọn theo khuôn mặt" ngoài trang chủ: /san-pham?shape=Round) chạy được ngay.
 *
 * GIÁ TRỊ trên URL nay là KHOÁ CHUẨN chứ không còn là chữ nguyên văn trong DB
 * — ?shape[]=cat-eye thay cho ?shape[]=Mắt+mèo+(Cat-eye). Liên kết cũ dùng
 * nhãn tiếng Anh vẫn chạy: ProductTaxonomy hạ chúng về slug trước khi so, nên
 * "Round" và "round" cùng trỏ về một khoá.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DANH SÁCH LỰA CHỌN KHÔNG CÒN Ở ĐÂU
 *
 * Controller này KHÔNG khai một thương hiệu, dáng gọng hay chất liệu nào. Toàn
 * bộ ô chọn của cột lọc do ProductModel::catalog() dựng từ chính hàng đang
 * bán, kèm số đếm động. Trước đây danh sách được gõ cứng và trôi khỏi kho:
 * bộ lọc mời người dùng chọn những hãng cửa hàng không bán, đồng thời giấu mất
 * hơn nửa số dáng gọng đang có.
 */

class ProductController extends BaseController
{
    /**
     * Các khoảng giá trong ô "Khoảng giá" của bộ lọc.
     *
     * Gõ cứng chứ không tính từ dữ liệu: đây là những mốc người mua kính tự
     * nghĩ trong đầu ("dưới 500 nghìn"), không phải phân vị của kho hàng. Kho
     * đổi giá thì mấy mốc này vẫn đúng.
     *
     * `max` là chặn TRÊN KHÔNG bao gồm — nếu không thì hàng đúng 500.000₫ rơi
     * vào cả hai khoảng đầu.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * CHIA DÀY Ở DƯỚI, THƯA DẦN LÊN TRÊN (2026-08-30, theo yêu cầu)
     *
     * Bước nhảy to dần: 0,5 ×6 · 1 ×2 · 2 · 3 (triệu). Người mua ở phân khúc
     * dưới cân nhắc từng vài trăm nghìn, còn người mua gọng 7 triệu thì 500
     * nghìn không đổi quyết định — chia đều tăm tắp là dồn hết độ phân giải
     * vào chỗ không ai cần.
     *
     * Đã qua ba đời trong một ngày, ghi lại để khỏi ai đi vòng lại:
     *   4 mốc  dưới 2tr · 2–5 · 5–10 · trên 10.  "Dưới 2 triệu" ôm gần hết
     *          hàng phổ thông nên bấm vào cũng nhận lại đúng cái lưới vừa nhìn.
     *   6 mốc  1 · 1 · 1 · 2 · 2 · 3 triệu.  Đỡ hơn, vẫn thô ở đáy thang.
     *   10 mốc bản hiện tại.
     *
     * CHIA DÀY KHÔNG SINH RA MỘT DANH SÁCH TOÀN DÒNG MỜ, và đây là chỗ dễ đoán
     * sai nên phải nói rõ. ProductFacets::group() chỉ dựng mốc nào CÓ ÍT NHẤT
     * MỘT sản phẩm trong phạm vi đang xét; mốc rỗng hoàn toàn thì không in ra.
     * Chỉ mốc nào có hàng nhưng chọi với tiêu chí khác đang bật mới hiện mờ.
     * Đã đo: /san-pham?q=6tr chỉ còn đúng hai dòng — "Tất cả mức giá" và
     * "5 – 7 triệu".
     *
     * Nghĩa là thang mịn tự co theo kho: kho mỏng thì danh sách ngắn, kho dày
     * thì mịn tới đâu dùng tới đó. Thêm mốc ở đây không phải trả giá bằng một
     * danh sách dài ngoằng cho cửa hàng chưa có hàng.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * TRẦN LÀ 10 TRIỆU, VÀ ĐÓ LÀ MỘT ĐÁNH ĐỔI CÓ THẬT
     *
     * Không còn khoảng "Trên 10 triệu" như bản cũ, nên hàng từ 10.000.000₫ trở
     * lên KHÔNG rơi vào khoảng nào: nó vẫn hiện bình thường khi chưa lọc giá,
     * nhưng chọn bất kỳ khoảng nào cũng làm nó biến mất, và không có cách nào
     * lọc riêng nó ra.
     *
     * Chấp nhận được chừng nào cửa hàng chưa bán tới mức đó. Ngày có hàng trên
     * 10 triệu thì thêm lại một dòng ['label' => 'Trên 10 triệu', 'min' =>
     * 10000000, 'max' => null] vào CUỐI mảng — thêm ở cuối chứ không chèn giữa,
     * xem đoạn về chỉ số ngay dưới.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ĐỔI MẢNG NÀY LÀ ĐỔI Ý NGHĨA CỦA MỌI LIÊN KẾT ĐÃ LƯU
     *
     * URL mang CHỈ SỐ (?price=2), không mang khoảng. Bản bốn mốc ?price=2 là
     * "5 – 10 triệu", bản sáu mốc là "2 – 3 triệu", nay là "1 – 1,5 triệu" —
     * cùng một địa chỉ, ba nghĩa khác nhau. Không có cách nào cứu
     * những liên kết đó, và cũng không đáng dựng bảng quy đổi cho một tham số
     * lọc — nhưng phải biết là nó xảy ra, và đừng chèn thêm dòng vào GIỮA mảng
     * nếu không muốn lặp lại chuyện này.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * Cả NHÓM này tự ẩn khi chưa hàng nào có giá (xem 'hasPrices' bên dưới):
     * một ô chọn không lọc ra được gì chỉ làm người dùng bấm rồi tưởng trang
     * hỏng. Nhập giá cho một sản phẩm là nhóm hiện lại, không phải sửa code.
     */
    private const PRICE_RANGES = [
        ['label' => 'Dưới 500 nghìn',     'min' => 0,       'max' => 500000],
        ['label' => '500 nghìn – 1 triệu','min' => 500000,  'max' => 1000000],
        ['label' => '1 – 1,5 triệu',      'min' => 1000000, 'max' => 1500000],
        ['label' => '1,5 – 2 triệu',      'min' => 1500000, 'max' => 2000000],
        ['label' => '2 – 2,5 triệu',      'min' => 2000000, 'max' => 2500000],
        ['label' => '2,5 – 3 triệu',      'min' => 2500000, 'max' => 3000000],
        ['label' => '3 – 4 triệu',        'min' => 3000000, 'max' => 4000000],
        ['label' => '4 – 5 triệu',        'min' => 4000000, 'max' => 5000000],
        ['label' => '5 – 7 triệu',        'min' => 5000000, 'max' => 7000000],
        ['label' => '7 – 10 triệu',       'min' => 7000000, 'max' => 10000000],
    ];

    /**
     * Danh mục có TRANG CON riêng: /san-pham/gong-kinh · /san-pham/trong-kinh
     *
     * ─────────────────────────────────────────────────────────────────────────
     * KHAI HAI CHỖ, VÀ HAI CHỖ ĐÓ PHẢI KHỚP NHAU
     *
     * Mảng này và config/routes.php. Thêm một danh mục vào đây mà quên thêm
     * route thì catalog() vẫn chạy nhưng URL trả 404; thêm route mà quên mảng
     * này thì trang con vẫn mở được nhưng ?category=<slug> không chuyển hướng
     * về nó, và site có hai địa chỉ cho cùng một nội dung.
     *
     * VÌ SAO KHÔNG DÙNG MỘT ROUTE CÓ THAM SỐ: 'san-pham/{slug}' đã là trang
     * CHI TIẾT SẢN PHẨM. Router khớp route chính xác trước rồi mới tới route
     * có tham số (xem core/Router.php), nên hai dòng khai đích danh ở
     * config/routes.php chặn đúng hai đường này lại trước khi chúng rơi xuống
     * trang chi tiết — cùng cách 'san-pham/danh-gia' đang làm.
     *
     * HỆ QUẢ: sản phẩm nào có slug đúng bằng 'gong-kinh' hay 'trong-kinh' sẽ
     * KHÔNG mở được trang chi tiết. Kho hiện đặt slug dạng
     * 'gong-kinh-titan-vin-t01' nên không đụng, nhưng ai thêm danh mục vào đây
     * thì kiểm lại điều đó trước.
     *
     * DANH MỤC THỨ BA — 'kinh-mat' — CỐ Ý KHÔNG CÓ trang con, vì cửa hàng chỉ
     * yêu cầu hai. Nó vẫn duyệt được qua /san-pham?category=kinh-mat như cũ.
     * Muốn nó cũng có trang con thì thêm một dòng ở đây và một dòng ở routes.
     * ─────────────────────────────────────────────────────────────────────────
     */
    /* public vì helper danhMucUrl() trong core/helpers.php đọc nó — đó là chỗ
       DUY NHẤT dựng liên kết danh mục cho cả site, và nó phải biết danh mục
       nào đã có trang con. */
    public const SUB_PAGES = ['gong-kinh', 'trong-kinh'];

    /**
     * /san-pham — cả kho, hoặc lọc theo ?category= với danh mục chưa có trang con.
     */
    public function index(): void
    {
        $category = (string) ($_GET['category'] ?? '');

        /*
         * ?category=<slug> CỦA DANH MỤC CÓ TRANG CON -> chuyển hướng về trang
         * con, giữ nguyên mọi tham số khác.
         *
         * Không phải để cho đẹp. Nếu để cả hai địa chỉ cùng phục vụ một nội
         * dung thì máy tìm kiếm thấy hai trang trùng nhau và tự chọn một cái
         * để lập chỉ mục — thường là cái ta không muốn. Chuyển hướng làm trang
         * con thành địa chỉ DUY NHẤT, mà liên kết cũ (bookmark của khách, thẻ
         * chia sẻ đã gửi đi) vẫn tới đúng nơi.
         *
         * 301 chứ không 302: đây là đổi địa chỉ vĩnh viễn, và 301 mới chuyển
         * được thứ hạng của liên kết cũ sang địa chỉ mới.
         */
        if (in_array($category, self::SUB_PAGES, true)) {
            $rest = $_GET;
            unset($rest['category']);

            redirect('/san-pham/' . rawurlencode($category)
                . ($rest === [] ? '' : '?' . http_build_query($rest)), 301);
        }

        $this->catalog($category);
    }

    /**
     * /san-pham/gong-kinh và /san-pham/trong-kinh — trang con của một danh mục.
     *
     * MỘT HÀM CHO CẢ HAI ROUTE, đọc slug từ chính đường dẫn. Router chỉ truyền
     * tham số cho route dạng 'san-pham/{slug}', mà hai đường này là route KHỚP
     * CHÍNH XÁC (bắt buộc thế, xem chú thích ở SUB_PAGES) nên action không
     * nhận được gì — phải tự lấy.
     *
     * An toàn vì đoạn ngay dưới đối chiếu với SUB_PAGES: chuỗi lấy từ URL
     * không đi tới đâu nếu không nằm trong danh sách cho phép.
     */
    public function category(): void
    {
        $parts = explode('/', trim(currentPath(), '/'));
        $slug  = (string) end($parts);

        /*
         * Đối chiếu với SUB_PAGES TRƯỚC KHI hỏi CSDL. Slug tới từ URL, và
         * không có bước này thì /san-pham/kinh-mat cũng mở ra một trang con
         * mà không có route nào khai — tức là địa chỉ chạy được nhưng không ai
         * biết nó tồn tại, và ?category=kinh-mat thì không chuyển hướng về đó.
         * Hai đường vào một nội dung, đúng thứ vừa bỏ công tránh.
         */
        if (!in_array($slug, self::SUB_PAGES, true)) {
            $this->notFound();

            return;
        }

        /*
         * Danh mục bị ẩn trong khu quản trị thì trang con phải 404, không phải
         * hiện ra một lưới rỗng: ẩn một danh mục là quyết định của cửa hàng,
         * và một trang trống mang tiêu đề "Tròng kính" trông như site hỏng.
         */
        if (CategoryModel::findVisibleBySlug($slug) === null) {
            $this->notFound();

            return;
        }

        $this->catalog($slug);
    }

    /**
     * Thân chung của /san-pham và hai trang con.
     *
     * @param string $category slug danh mục, '' là cả kho
     */
    private function catalog(string $category): void
    {
        /*
         * ĐƯỜNG GỐC CỦA TRANG. Mọi liên kết lọc, mọi form GET trong view đều
         * dựng từ đây. Trên trang con nó là '/san-pham/gong-kinh' và tham số
         * 'category' KHÔNG được in vào query nữa — đường dẫn đã nói rồi. Thiếu
         * chỗ này thì bấm một tiêu chí lọc bất kỳ là văng ngược về
         * /san-pham?category=gong-kinh, tức là ra khỏi trang con ngay ở cú bấm
         * đầu tiên.
         */
        $base = in_array($category, self::SUB_PAGES, true)
            ? '/san-pham/' . rawurlencode($category)
            : '/san-pham';

        // Chỉ nhận đúng các khoá đã biết. Không đổ nguyên $_GET vào model:
        // tham số lạ sẽ lọt xuống tầng dựng câu lệnh và là mặt tấn công thừa.
        $filters = [
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'category' => $category,
        ];

        // Các nhóm chọn-nhiều. Luôn là MẢNG kể từ đây, kể cả khi URL chỉ có
        // một giá trị — view và model không phải phân biệt hai dạng.
        foreach (ProductFacets::MULTI as $group) {
            $filters[$group] = $this->multi($group);
        }

        /*
         * Khoảng giá là CHỈ SỐ của một mục trong PRICE_RANGES, không phải cặp
         * min/max người dùng tự gõ. Nhận số tự do từ URL rồi ném thẳng vào phép
         * lọc là mở đường cho những truy vấn không nằm trong bất kỳ nút bấm nào
         * của giao diện; ở đây chỉ số ngoài dải thì coi như không lọc.
         */
        $raw        = $_GET['price'] ?? '';
        $priceIndex = is_scalar($raw) && $raw !== '' && ctype_digit((string) $raw) ? (int) $raw : null;

        if ($priceIndex === null || !isset(self::PRICE_RANGES[$priceIndex])) {
            $priceIndex = null;
        }

        // Model coi khoảng giá như mọi nhóm khác (mảng khoá), view thì cần một
        // số để đánh dấu <option> đang chọn. Giữ cả hai, cùng một nguồn.
        $filters['price'] = $priceIndex === null ? [] : [(string) $priceIndex];

        // Sắp xếp — chỉ bốn giá trị bản thiết kế vẽ, cộng 'rating' vẫn đỡ cho
        // các link cũ. Giá trị lạ rơi về mặc định thay vì báo lỗi.
        $sort = (string) ($_GET['sort'] ?? 'newest');
        $filters['sort'] = in_array($sort, ['newest', 'popular', 'price-asc', 'price-desc', 'rating'], true)
            ? $sort
            : 'newest';

        // Từ khoá lọc DANH SÁCH THƯƠNG HIỆU trong bộ lọc — không phải tìm sản
        // phẩm. Tên khác 'q' để hai ô không giẫm chân nhau trên cùng một URL.
        $filters['bq'] = trim((string) ($_GET['bq'] ?? ''));

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = ProductModel::catalog($filters, self::PRICE_RANGES, $page);

        /*
         * Trang vượt quá trang cuối -> đưa về đúng trang cuối.
         *
         * Không có đoạn này thì /san-pham?page=99 trả một lưới RỖNG kèm dải
         * phân trang vẫn chỉ vào 1-2-3, mà khối "chưa có sản phẩm phù hợp"
         * cũng không hiện (vì tổng số vẫn > 0) — người dùng nhìn thấy một
         * khoảng trắng không lời giải thích. Gặp thật khi ai đó lưu link
         * trang 3 rồi kho bớt hàng đi.
         *
         * Chuyển hướng chứ không lặng lẽ vẽ trang cuối: để địa chỉ trên thanh
         * URL luôn nói đúng nội dung đang xem, sao chép gửi cho người khác
         * vẫn ra đúng trang đó.
         */
        if ($page > 1 && $result['items'] === [] && $result['totalPages'] > 0) {
            $_GET['page'] = $result['totalPages'];

            /* $base chứ không phải '/san-pham' cố định: trên trang con thì
               chuyển hướng phải ở lại trang con, không ném khách về cả kho.
               Và 'category' bỏ khỏi query vì đường dẫn đã mang nó rồi. */
            $rest = $_GET;
            unset($rest['category']);

            redirect($base . ($rest === [] ? '' : '?' . http_build_query($rest)));
        }

        /*
         * Số tiêu chí đang bật — hiện trong huy hiệu tròn trên nút "Bộ lọc" ở
         * màn hình hẹp. Mỗi GIÁ TRỊ được chọn đếm một, nên chọn 3 dáng gọng là
         * 3 — đúng bằng số huy hiệu người dùng nhìn thấy sáng lên.
         *
         * Duyệt qua GROUPS thay vì cộng tay từng nhóm: thêm một nhóm lọc mới
         * mà quên cập nhật phép cộng ở đây thì huy hiệu đếm thiếu, và trên
         * mobile đó là chỗ DUY NHẤT cho biết bộ lọc đang bật (cột lọc thu vào
         * bottom-sheet, không nhìn thấy huy hiệu nào).
         */
        $activeCount = ($filters['category'] !== '' ? 1 : 0);

        foreach (ProductFacets::GROUPS as $group) {
            $activeCount += count($filters[$group]);
        }

        /*
         * Đầu trang đổi theo danh mục đang xem: bản thiết kế vẽ trang "Gọng
         * kính" với đúng tên và mô tả của danh mục đó, không phải một tiêu đề
         * chung cho mọi bộ lọc. Không lọc theo danh mục thì quay về tiêu đề
         * chung của cả catalog.
         */
        $categories = CategoryModel::visible();
        $current    = null;

        foreach ($categories as $c) {
            if ($c['slug'] === $filters['category']) {
                $current = $c;
                break;
            }
        }

        $heading = $current['name'] ?? 'Sản phẩm kính mắt';

        /*
         * Mô tả danh mục — dùng cho CẢ đoạn dẫn bên phải khối đầu trang lẫn
         * thẻ <meta description>. Một đoạn, một nguồn: hai chỗ lệch nhau thì
         * kết quả tìm kiếm hứa một đằng, trang mở ra một nẻo.
         *
         * Danh mục chưa ai viết mô tả thì lấy câu chung của cả catalog. Câu đó
         * không phải chữ viết cho có: nó nói đúng thứ cột bên trái làm được
         * (lọc theo thương hiệu, dáng gọng, chất liệu, tính năng tròng) —
         * thông tin mà người mới vào trang chưa biết.
         */
        $lead = (string) ($current['description'] ?? '');

        if ($lead === '') {
            $lead = 'Gọng kính, kính mát và tròng kính chính hãng — lọc theo '
                  . 'thương hiệu, dáng gọng, chất liệu và tính năng tròng phù hợp với bạn.';
        }

        /* Lọc theo danh mục thì breadcrumb có bậc "Sản phẩm" leo ngược về danh
           sách đầy đủ — cùng đường mà trang chi tiết sản phẩm trỏ tới. */
        $crumbs = $current === null
            ? [['label' => 'Sản phẩm']]
            : [['label' => 'Sản phẩm', 'url' => '/san-pham'], ['label' => $current['name']]];

        $this->renderView('product/index', [
            'pageTitle'   => $heading . ' — Vin Eyewear',
            'metaDesc'    => $lead,
            'products'    => $result['items'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'totalPages'  => $result['totalPages'],
            'filters'     => $filters,
            'activeCount' => $activeCount,
            'groups'      => $result['groups'],
            'hasPrices'   => $result['hasPrices'],
            'priceIndex'  => $priceIndex,
            'categories'  => $categories,
            'heading'     => $heading,
            'crumbs'      => $crumbs,
            'lead'        => $lead,
            'catalogBase' => $base,
        ]);
    }

    /**
     * Trả 404 qua ErrorController để trang lỗi đồng nhất với phần còn lại.
     * Cùng một hàm với ProductDetailController — hai controller, một trang lỗi.
     */
    private function notFound(): void
    {
        http_response_code(404);

        $controller = new ErrorController();
        $controller->notFound();
    }

    /**
     * Một tham số lọc từ $_GET dưới dạng mảng chuỗi, bỏ giá trị rỗng.
     *
     * Nhận cả `?brand=Gucci` lẫn `?brand[]=gucci&brand[]=dior`. Phần tử không
     * phải giá trị vô hướng (`?brand[][]=x`) bị bỏ qua — nếu để lọt thì view
     * sẽ ép (string) một mảng và ném lỗi khi in ra huy hiệu.
     *
     * slugify() ở đây là chỗ các LIÊN KẾT CŨ được cứu: mega menu trỏ tới
     * ?shape=Round và trang chủ trỏ tới ?shape=Cat-eye, còn khoá chuẩn là
     * 'round' và 'cat-eye'. Không hạ về slug thì cả hai khối điều hướng đó
     * dẫn tới một lưới rỗng.
     */
    private function multi(string $key): array
    {
        $raw   = $_GET[$key] ?? null;
        $items = is_array($raw) ? $raw : [$raw];
        $out   = [];

        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $value = slugify((string) $item);

            if ($value !== '') {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }
}
