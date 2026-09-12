<?php

/**
 * product/detail.php — chi tiết sản phẩm (/san-pham/{slug})
 *
 * Dựng theo "Vin Eyewear Product.dc.html" (Claude Design):
 *
 *   breadcrumb → hai cột đều nhau, gap 48:
 *     trái  thư viện ảnh dính theo cuộn (ảnh lớn 520px + hàng ảnh nhỏ 92px)
 *     phải  thông tin, chọn phương án, mua hàng, 4 thẻ cam kết
 *   → hai thẻ ngang nhau: Thông số kỹ thuật | Đánh giá
 *
 * CSS: assets/css/product-detail.css
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỔI ẢNH VÀ CHỌN PHƯƠNG ÁN — KHÔNG JAVASCRIPT
 *
 * Ảnh: mỗi ảnh lớn có id, ảnh nhỏ là <a href="#id">. CSS `:target` quyết định
 * ảnh nào hiện. Bấm ảnh nhỏ đổi ảnh lớn mà không tải lại trang, và địa chỉ
 * mang theo ảnh đang xem nên gửi link được.
 *
 * Phương án (chiết suất): ô radio thật + `:has()`, đúng cách đã dùng ở trang
 * thanh toán. Giá trị đi thẳng vào form "Thêm vào giỏ" nằm ngay dưới, nên
 * không có bước đồng bộ nào giữa hai nơi.
 * ─────────────────────────────────────────────────────────────────────────────
 */

/* Xem chú thích cùng nội dung ở _layout/product-card.php: giá hiện ra phải là
   giá GIỎ HÀNG SẼ TÍNH, nên cả hai chỗ cùng đi qua ProductPricing. */
$price    = ProductPricing::giaBan($product);
$compare  = ProductPricing::giaGach($product);
$percent  = discount($price, $compare);
$hanKM    = ProductPricing::hanKhuyenMai($product);
$rating   = (float) ($product['rating'] ?? 5);
$reviewN  = (int) ($product['review_count'] ?? 0);
/*
 * ─────────────────────────────────────────────────────────────────────────────
 * LỌC ẢNH KHÔNG PHẢI ẢNH SẢN PHẨM RA KHỎI THƯ VIỆN
 *
 * Cột `images` cho phép dán đường dẫn bất kỳ, và trên dữ liệu đang chạy có hai
 * mặt hàng bị gán nhầm ảnh KHÔNG PHẢI chụp sản phẩm:
 *
 *     Aurora Titan Vuông        -> /assets/images/showroom-frames.jpg
 *     Solis Phi Công Phân Cực   -> /assets/images/hero-eyewear.jpg
 *
 * Tấm thứ nhất là ảnh NỘI THẤT MỘT CỬA HÀNG KHÁC — trong ảnh còn đọc được biển
 * hiệu của thương hiệu đó. Đặt nó làm ảnh thứ hai trong thư viện của một chiếc
 * gọng titan là thứ khách nhìn thấy ngay khi bấm sang ảnh kế tiếp.
 *
 * Tấm thứ hai là ảnh bìa chiến dịch, không phải ảnh chụp chiếc kính đang bán.
 *
 * Ở đây KHÔNG đoán "ảnh này có đúng mặt hàng không" — việc đó cần mắt người.
 * Chỉ loại những tấm mà bản thân VAI TRÒ của chúng đã sai: ảnh cửa hàng, ảnh
 * nội thất, ảnh bìa chiến dịch không bao giờ là ảnh sản phẩm, dù gắn cho mặt
 * hàng nào.
 *
 * Lọc ở TẦNG VIEW, không sửa CSDL và không đụng ProductModel: đây là quyết
 * định trình bày ("đừng vẽ tấm này ra"), và nó tự biến mất khi dữ liệu ảnh
 * được dọn. Gỡ luật này chỉ là xoá một mảng.
 *
 * Nếu lọc xong không còn tấm nào thì rơi về ô trống thật thà `.pdgal--empty`
 * chứ KHÔNG mượn ảnh của mặt hàng khác — cùng nguyên tắc với `.pcard__noimg`.
 * ─────────────────────────────────────────────────────────────────────────────
 */
$anhKhongPhaiSanPham = ['showroom-', 'store-interior', 'hero-'];

$images = array_values(array_filter(
    $product['images'] ?: [ProductModel::image($product)],
    static function ($duongDan) use ($anhKhongPhaiSanPham): bool {
        $ten = basename(parse_url((string) $duongDan, PHP_URL_PATH) ?? '');

        foreach ($anhKhongPhaiSanPham as $dauHieu) {
            if (str_starts_with($ten, $dauHieu)) {
                return false;
            }
        }

        return $ten !== '';
    }
));
$specs    = is_array($product['specs']) ? $product['specs'] : [];
$slug     = rawurlencode($product['slug']);

/* Biến thể đang được chọn sẵn. Ưu tiên cái khách vừa chọn hỏng (?pa=), rồi tới
   phương án còn hàng đầu tiên — chọn sẵn một phương án đã hết hàng là bẫy. */
$firstInStock = null;
foreach ($variants as $v) {
    if ((int) $v['stock_quantity'] > 0) { $firstInStock = $v['id']; break; }
}
$activeVariant = $pickedVariant !== '' ? $pickedVariant : ($firstInStock ?? ($variants[0]['id'] ?? ''));

/* Còn hàng hay không, tính cả biến thể: có biến thể thì chỉ cần MỘT phương án
   còn hàng là mặt hàng còn bán được. */
$inStock = $variants === []
    ? ProductModel::inStock($product)
    : ($product['status'] === 'in_stock' && $firstInStock !== null);

/*
 * SỐ LƯỢNG CÒN LẠI — con số thật, không chỉ chữ "Còn hàng".
 *
 * Trước bản này trang chỉ nói còn/hết. Khách muốn mua ba cái không có cách nào
 * biết cửa hàng còn mấy cái, và chỉ phát hiện ra ở trang giỏ khi máy chủ từ
 * chối tăng số lượng — đúng lúc họ tưởng việc mua đã xong.
 *
 * Mặt hàng CÓ biến thể thì cộng tồn của mọi phương án: mỗi phương án hiện tồn
 * riêng ngay trên nút chọn bên dưới, còn con số ở đây trả lời câu "cửa hàng
 * này còn bao nhiêu cái tất cả".
 */
$stockLeft = $variants === []
    ? (int) ($product['stock_quantity'] ?? 0)
    : array_sum(array_map(static fn ($v) => (int) $v['stock_quantity'], $variants));

/*
 * SẮP HẾT HAY CHƯA — HỎI NGƯỠNG CỦA CHÍNH MẶT HÀNG, KHÔNG GÕ CỨNG
 *
 * Số nhỏ mới đáng nói. Còn 80 cái mà in "còn 80" thì con số đó không giúp ai
 * quyết định gì, chỉ làm dòng thông tin dài thêm; còn 3 cái thì đó là thứ
 * khách cần biết trước khi chọn số lượng.
 *
 * Nhưng "nhỏ" là bao nhiêu thì TUỲ MẶT HÀNG. Ba cái gọng bán một tháng chưa
 * hết là chuyện thường; ba hộp tròng thông dụng thì hết trong hai ngày. Cửa
 * hàng đặt riêng cho từng mã ở ô "Ngưỡng cảnh báo hết hàng" (cột
 * `low_stock_at`), để trống thì rơi về mốc chung 5.
 *
 * Trước bản này chỗ đây gõ cứng số 10, trong khi khu quản trị dùng đúng cơ
 * chế trên. Cùng một cái gọng còn 7 chiếc: trang bán hàng kêu "Chỉ còn 7",
 * bảng Tồn kho bảo vẫn bình thường. Nay cả hai hỏi cùng một nguồn —
 * ProductModel::nguongSapHet() và ::nguongSapHetSql() dùng chung hằng số.
 */
$stockLow = $inStock
    && $stockLeft > 0
    && $stockLeft <= ProductModel::nguongSapHet($product);

/*
 * TRẦN CỦA Ô SỐ LƯỢNG — theo tồn kho thật, không phải một con số gõ cứng.
 *
 * Ô này từng ghi cứng max="20": hàng còn 3 cái mà mũi tên vẫn chạy tới 20, và
 * khách chỉ biết mình chọn hụt khi máy chủ trả lời. Con số 20 còn là tàn dư
 * của luật "trần 20" mà CartController đã bỏ từ lâu (trần tuyệt đối nay là
 * 999, và giới hạn thật là tồn kho).
 *
 * KHÔNG DÙNG $stockLeft: nó là TỔNG tồn của mọi phương án, mà một dòng giỏ chỉ
 * mua được MỘT phương án. Lấy tổng làm trần thì mặt hàng có hai phương án mỗi
 * cái 2 chiếc sẽ cho chọn 4 — nhiều hơn bất kỳ phương án nào có thật.
 *
 * Lấy phương án DỒI DÀO NHẤT chứ không phải phương án đang chọn sẵn: trang này
 * không có JavaScript nào cập nhật lại `max` khi khách đổi phương án, nên một
 * trần tính theo phương án đang chọn sẽ CHẶN NHẦM khi họ chuyển sang phương án
 * còn nhiều hàng hơn. Chặn nhầm một lượt mua có thật thì tệ hơn là cho gõ một
 * số hơi cao rồi bị kẹp — mà kẹp thì nay có báo (xem CartController::add).
 */
$maxMua = $variants === []
    ? (int) ($product['stock_quantity'] ?? 0)
    : (int) max(array_map(static fn ($v) => (int) $v['stock_quantity'], $variants));

/* Sàn 1 để không in ra max="0" — thuộc tính đó làm ô số vô dụng ngay cả khi
   nó đang bị disabled vì hết hàng. */
$maxMua = max(1, $maxMua);

$commitments = [
    ['shield', 'Bảo hành 24 tháng',   'Lỗi nhà sản xuất, đổi mới trong 7 ngày đầu'],
    ['eye',    'Đo mắt miễn phí',     'Quy trình khúc xạ chuẩn, kể cả khi không mua'],
    ['truck',  'Giao hàng đồng kiểm', 'Mở hộp kiểm tra trước khi thanh toán'],
    ['wrench', 'Nắn chỉnh trọn đời',  'Miễn phí không giới hạn tại cả hai cơ sở'],
];

/** Năm ngôi sao đặc/rỗng theo điểm — dùng cho cả phần đầu và từng đánh giá. */
$stars = static function (float $score): string {
    $full = (int) round($score);
    return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full));
};
?>

<section class="pd">

    <?php
    /*
     * CHỈ breadcrumb — không tiêu đề, không mô tả.
     *
     * Tên sản phẩm đã là <h1> của trang (nằm trong cột thông tin bên phải),
     * nên một dải tiêu đề nữa vừa nói hai lần vừa đẩy thư viện ảnh xuống dưới
     * màn hình đầu tiên. Breadcrumb thì vẫn cần: trang này nằm sâu trong cây
     * và phần lớn người xem vào thẳng từ tìm kiếm.
     */
    $crumbs = [];
    if ($category !== null) {
        $crumbs[] = [
            'label' => $category['name'],
            'url'   => danhMucUrl($category['slug']),
        ];
    }
    $crumbs[] = ['label' => $product['name']];

    partial('_layout/page-head', ['head_crumbs' => $crumbs]);
    ?>

    <div class="pd__grid">

        <!-- ══════════ THƯ VIỆN ẢNH ══════════ -->
        <?php
        /*
         * ─────────────────────────────────────────────────────────────────────
         * XẾP DỌC, KHÔNG CÒN "MỘT Ô LỚN + HÀNG ẢNH NHỎ"
         *
         * Mọi ảnh của mẫu hàng in ra thành một CỘT ẢNH LỚN cuộn tự nhiên, mỗi
         * tấm chiếm trọn bề ngang cột. Đây là cách trang sản phẩm của các nhà
         * thời trang trình bày: người xem cuộn qua từng tấm thay vì bấm vào ảnh
         * nhỏ để đổi ảnh lớn.
         *
         * VÀ NÓ SỬA MỘT LỖI CÓ THẬT. Bản trước xếp mọi ảnh chồng lên nhau trong
         * một ô `.pdgal__stage` cao 1:1 rồi dựa vào CSS `:target` để chọn ảnh
         * nào hiện — nhưng KHÔNG CÓ luật `:target` nào trong assets/css:
         * grep `.pdgal__img` và `:target` cả thư mục ra 0 kết quả. Nghĩa là mọi
         * ảnh phụ vẫn nằm trong luồng, bị `overflow:hidden` của ô cắt đi, và
         * hàng ảnh nhỏ bấm vào không đổi được gì. Trang chỉ có MỘT ảnh dùng
         * được, đúng như khi soi trang thật.
         *
         * Cột dọc không cần `:target`, không cần ảnh nhỏ, không cần một dòng JS.
         *
         * TẤM ĐẦU `fetchpriority="high"` và KHÔNG lazy — nó là thứ khách nhìn
         * để quyết định. Các tấm sau lazy như thường.
         * ─────────────────────────────────────────────────────────────────────
         */
        ?>
        <div class="pdgal">
            <?php /* Ô TRỐNG THẬT THÀ khi mặt hàng không còn tấm ảnh hợp lệ nào —
                     cùng nguyên tắc với `.pcard__noimg` ở thẻ sản phẩm: thà để
                     trống còn hơn mượn ảnh của mặt hàng khác. Dùng lại đúng bộ
                     lớp đó nên không phát sinh kiểu dáng mới. */ ?>
            <?php if ($images === []): ?>
                <figure class="pdgal__frame">
                    <span class="pcard__noimg"><?= e(t('product.no_image')) ?></span>
                </figure>
            <?php endif; ?>

            <?php foreach ($images as $i => $img): ?>
                <figure class="pdgal__frame">
                    <?php /* asset() bọc ngoài để một tấm ảnh chỉ có một địa chỉ —
                             lý do đầy đủ ở _layout/product-card.php. */ ?>
                    <img class="pdgal__img" src="<?= e(asset($img)) ?>"
                         alt="<?= e($product['name']) ?><?= $i > 0 ? ' — ảnh ' . ($i + 1) : '' ?>"
                         width="1000" height="1000"
                         <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">

                    <?php /* Huy hiệu giảm giá chỉ trên TẤM ĐẦU — lặp lại ở mọi
                             tấm là biến một thông tin thành một hoa văn. */ ?>
                    <?php if ($i === 0 && $percent !== null): ?>
                        <span class="pdgal__sale">-<?= (int) $percent ?>%</span>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>

            <?php
            /*
             * ẢNH 360 / VIDEO — mở ở TAB MỚI, không nhúng iframe.
             *
             * Cột `video_url` có ô nhập trong form quản trị từ lâu mà không
             * trang nào đọc; nối dây 2026-08-29.
             *
             * Nhúng thẳng <iframe> YouTube thì kéo theo script của bên thứ ba
             * vào mọi lượt mở trang sản phẩm — chậm hơn hẳn phần còn lại của
             * trang, và đặt cookie theo dõi của họ lên khách của mình. Một
             * đường dẫn thì khách nào muốn xem mới trả cái giá đó.
             *
             * Chỉ nhận http/https. Cột này người trong cửa hàng gõ vào, nhưng
             * nó in thẳng ra href — mà "javascript:" trong href là chạy mã
             * ngay trên phiên của khách. Một tài khoản quản trị bị chiếm là đủ
             * để biến mọi trang sản phẩm thành bẫy.
             */
            $video = trim((string) ($product['video_url'] ?? ''));
            $video = preg_match('#^https?://#i', $video) ? $video : '';
            ?>
            <?php if ($video !== ''): ?>
                <a class="pdgal__video" href="<?= e($video) ?>"
                   target="_blank" rel="noopener noreferrer">
                    <?= icon('play', 'pdgal__video-ico', 18) ?>
                    Xem ảnh 360 / video của mẫu này
                </a>
            <?php endif; ?>
        </div>

        <!-- ══════════ THÔNG TIN ══════════ -->
        <?php
        /* [data-recent] — "đã xem gần đây" của lớp phủ tìm kiếm. search-suggest.js
           đọc JSON này ở mọi trang có nó và đẩy vào localStorage; máy chủ không
           lưu gì. Giá là CHUỖI ĐÃ ĐỊNH DẠNG ($price là giá hiệu lực, có khuyến
           mãi thì đã trừ) để bên JS không phải biết luật giá. */
        $recentJson = json_encode([
            'slug'  => (string) $product['slug'],
            'name'  => (string) $product['name'],
            'image' => $images !== [] ? asset($images[0]) : '',
            'price' => money($price),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        <div class="pdinfo" data-recent="<?= e($recentJson) ?>">

<?php
            /*
             * ═════════════════════════════════════════════════════════════════
             * CỘT THÔNG TIN XẾP LẠI THEO NHÀ MỐT (09/09/2026)
             *
             * Trước: SKU → tên (48px) → sao · số đánh giá · tồn kho → giá → hạn
             * khuyến mãi → mô tả → phương án → số lượng + hai nút. Mười thứ xếp
             * dọc, và tám trong số đó là "chrome thương mại" đứng TRƯỚC nút mua.
             * Đối chiếu với trang sản phẩm của một nhà mốt: tên (nhỏ) → giá →
             * phương án → MỘT nút → rồi mới tới các mục gập.
             *
             * KHÔNG XOÁ DỮ LIỆU NÀO. SKU, sao, số đánh giá, tồn kho, hạn khuyến
             * mãi, mô tả: tất cả DỜI XUỐNG mục gập "Chi tiết" phía dưới nút mua
             * (mở sẵn). Ai cần vẫn thấy, nhưng thứ đầu tiên chạm mắt là ẢNH và
             * TÊN, không phải một bảng thông số.
             *
             * Thứ tự mới:
             *   tên → giá → phương án → số lượng + nút → [Chi tiết] → [Giao hàng]
             * ═════════════════════════════════════════════════════════════════
             */
            ?>
            <div class="pdinfo__head">
                <h1 class="pdinfo__title notranslate" translate="no" lang="vi"><?= e($product['name']) ?></h1>
            </div>

            <div class="pdinfo__price">
                <span class="pdinfo__now"><?= money($price) ?></span>
                <?php if ($compare !== null && $compare > $price): ?>
                    <span class="pdinfo__old"><?= money($compare) ?></span>
                <?php endif; ?>
            </div>

            <?php
            /* Khối "meta" — SKU · sao · đánh giá · tồn kho · hạn KM · mô tả —
               nay dựng ở ĐÂY vào một biến rồi in ra BÊN TRONG mục gập "Chi tiết"
               sau form mua. Gom thành một chuỗi để markup của mục gập đọc gọn,
               và để mọi lớp .pdinfo__* cũ giữ nguyên tên (CSS không đổi). */
            ob_start();
            ?>
                <span class="pdinfo__eyebrow">
                    <?= e($product['brand'] ?? 'Vin Eyewear') ?> · SKU <?= e($product['sku']) ?>
                </span>

                <div class="pdinfo__rate">
                    <span class="pdstars" aria-hidden="true"><?= $stars($rating) ?></span>
                    <span class="pdinfo__score"><?= e(number_format($rating, 1)) ?></span>
                    <span class="pdinfo__dot" aria-hidden="true">·</span>
                    <a href="#danh-gia"><?= $reviewN ?> đánh giá</a>
                    <span class="pdinfo__dot" aria-hidden="true">·</span>
                    <span class="pdinfo__stock<?= $inStock ? '' : ' is-out' ?>">
                        <?php
                        /*
                         * BA CÂU, VÀ CHỈ MỘT CÂU CÓ CON SỐ.
                         *
                         *   hết hàng   -> "Hết hàng"
                         *   sắp hết    -> "Chỉ còn 3 sản phẩm"   (xem $stockLow)
                         *   còn nhiều  -> "Còn hàng"             (KHÔNG kèm số)
                         *
                         * Vế cuối trước đây in "Còn hàng · 42 sản phẩm". Con số
                         * đó không giúp ai quyết định gì — người ta chỉ cần biết
                         * mua được hay không — mà lại làm hỏng chính chỗ con số
                         * có ích: nếu lúc nào cũng có số thì "Chỉ còn 3" không
                         * còn khác gì "Còn hàng · 42" trong mắt người lướt qua.
                         * Để dành con số cho đúng lúc nó nói được điều gì đó.
                         *
                         * "Hết hàng" chứ không phải "Tạm hết hàng": thẻ sản phẩm
                         * ngoài trang danh sách đang dùng đúng chữ này, hai chỗ
                         * nói về cùng một trạng thái thì phải cùng một chữ.
                         */
                        if (!$inStock) {
                            echo 'Hết hàng';
                        } elseif ($stockLow) {
                            echo 'Chỉ còn ' . $stockLeft . ' sản phẩm';
                        } else {
                            echo 'Còn hàng';
                        }
                        ?>
                    </span>
                </div>

            <?php /* HẠN KHUYẾN MÃI NÓI RA, không để khách tự đoán.

                     Chỉ hiện khi chương trình ĐANG chạy và CÓ mốc kết thúc —
                     ProductPricing::hanKhuyenMai() lo cả hai điều kiện. Giá
                     giảm mà không nói tới bao giờ thì người đang phân vân
                     không có gì để quyết, còn in hạn của một chương trình đã
                     tắt thì tệ hơn cả không in. */ ?>
            <?php if ($hanKM !== null): ?>
                <p class="pdinfo__sale-han">
                    Giá khuyến mãi tới hết ngày <?= e(formatDate($hanKM, 'd/m/Y')) ?>.
                </p>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
                <p class="pdinfo__desc"><?= e($product['description']) ?></p>
            <?php endif; ?>
            <?php $pdMeta = ob_get_clean(); ?>

            <form class="pdbuy" method="post" action="/gio-hang/them">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">

                <?php /* Nơi quay về sau khi chọn hình thức mua — hộp thoại
                         "Chọn hình thức mua" hiện đè lên chính trang này. */ ?>
                <input type="hidden" name="back" value="<?= e(currentUrlWithout(['mua', 'buoc'])) ?>">

                <?php if ($variants !== []): ?>
                    <div class="pdopts">
                        <span class="pdopts__label" id="nhan-pa"><?= e(t('pd.options')) ?></span>

                        <div class="pdopts__row" role="radiogroup" aria-labelledby="nhan-pa">
                            <?php foreach ($variants as $v): ?>
                                <?php $vStock = (int) $v['stock_quantity']; ?>
                                <label class="pdopt<?= $vStock > 0 ? '' : ' is-out' ?>">
                                    <?php /* data-stock: tồn của ĐÚNG phương án này, để
                                             assets/js/product-detail.js hạ trần ô số lượng
                                             khi khách đổi phương án. Không có nó thì trần
                                             đứng nguyên ở phương án dồi dào nhất, và nút "+"
                                             mờ ở một con số không đúng với thứ đang chọn. */ ?>
                                    <input type="radio" name="variant_id" value="<?= e($v['id']) ?>"
                                           data-stock="<?= $vStock ?>"
                                           required <?= $vStock > 0 ? '' : 'disabled' ?>
                                           <?= $activeVariant === $v['id'] && $vStock > 0 ? 'checked' : '' ?>>
                                    <span class="pdopt__body">
                                        <span class="pdopt__name"><?= e($v['label']) ?></span>
                                        <span class="pdopt__note">
                                            <?php
                                            /* Ghi chú của phương án, và khi tồn
                                               xuống thấp thì NHƯỜNG CHỖ cho con
                                               số: sắp hết là thứ quyết định
                                               khách bấm hay không, còn ghi chú
                                               ("dành cho độ cận dưới 4") thì
                                               đọc lúc nào cũng được. */
                                            if ($vStock <= 0) {
                                                echo 'Tạm hết';
                                            } elseif ($vStock <= 10) {
                                                echo 'Chỉ còn ' . $vStock;
                                            } else {
                                                echo e($v['note'] ?? '');
                                            }
                                            ?>
                                        </span>
                                    </span>
                                    <?php if ((int) $v['price_delta'] !== 0): ?>
                                        <!-- Chênh giá phải hiện ngay trên nút: bản thiết
                                             kế mẫu có ba phương án cùng giá, nhưng dữ liệu
                                             thật thì không, và đổi giá âm thầm khi khách
                                             bấm là điều tệ nhất một trang bán hàng làm được. -->
                                        <span class="pdopt__delta">
                                            <?= (int) $v['price_delta'] > 0 ? '+' : '−' ?><?= money(abs((int) $v['price_delta'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pdbuy__row">
                    <?php
                    /*
                     * HAI NÚT − / + QUANH MỘT Ô SỐ — đúng bản thiết kế gốc.
                     *
                     * Trước đây chỗ này chỉ có mỗi ô số, và chú thích cũ giải
                     * thích vì sao: "không JS nên hai nút ± sẽ phải gửi form,
                     * tức mỗi lần đổi số lượng là một vòng tải lại trang". Lý
                     * do đó hết hiệu lực từ khi trang có assets/js/product-detail.js.
                     *
                     * Ô SỐ THẬT VẪN Ở LẠI, không thay bằng một ô chữ: tắt JS thì
                     * hai nút biến mất (CSS chỉ hiện chúng khi <html> có lớp .js
                     * — cùng cách mà nút "Áp dụng" của bộ lọc tự ẩn khi có JS,
                     * chỉ ngược chiều) và khách vẫn còn mũi tên lên/xuống của
                     * trình duyệt cùng bàn phím. Không có trạng thái nào mà cả
                     * hai lối đều mất.
                     *
                     * type="button" chứ không để mặc định: nút trong <form> mà
                     * không khai type thì là nút GỬI FORM — bấm "+" một cái là
                     * mua hàng.
                     */
                    ?>
                    <div class="pdqty">
                        <button type="button" class="pdqty__btn" data-qty-step="-1"
                                aria-label="<?= e(t('pd.qty_down')) ?>" aria-controls="so-luong"
                                <?= $inStock ? '' : 'disabled' ?>><span aria-hidden="true">−</span></button>

                        <label class="sr-only" for="so-luong"><?= e(t('pd.qty')) ?></label>
                        <input class="pdqty__num" type="number" id="so-luong" name="quantity"
                               value="1" min="1" max="<?= $maxMua ?>" step="1" inputmode="numeric"
                               <?= $inStock ? '' : 'disabled' ?>>

                        <button type="button" class="pdqty__btn" data-qty-step="1"
                                aria-label="<?= e(t('pd.qty_up')) ?>" aria-controls="so-luong"
                                <?= $inStock ? '' : 'disabled' ?>><span aria-hidden="true">+</span></button>
                    </div>

                    <button type="submit" name="action" value="buy" class="pdbtn pdbtn--buy"
                            <?= $inStock ? '' : 'disabled' ?>>
                        <?= $inStock ? 'Mua ngay' : 'Tạm hết hàng' ?>
                    </button>

                    <button type="submit" class="pdbtn pdbtn--add" <?= $inStock ? '' : 'disabled' ?>>
                        Thêm vào giỏ
                    </button>
                </div>

                <?php
                /* ─────────────────────────────────────────────────────────────
                   THỬ ẢO — FR-SP-22, chỉ hiện với mẫu THẬT SỰ thử được

                   config('ar.frames') liệt kê những gọng có ảnh PNG và toạ độ
                   bản lề để vẽ lên khuôn mặt. Mẫu nào không nằm trong đó thì
                   trang thử AR không có gì để đội lên — dẫn khách tới đó là dẫn
                   họ tới một danh sách gọng khác hẳn thứ họ đang xem.

                   NẰM TRONG form mua hàng, ngay dưới hai nút. <a> lồng trong
                   <form> là HTML hợp lệ và không gửi form; đặt nó ở đây vì thử
                   ảo là việc người ta làm TRƯỚC khi bấm mua, nên nó phải ở
                   trong tầm mắt của người đang cân nhắc hai nút kia. */
                $arThuDuoc = false;

                if (config('ar.nav_enabled')) {
                    foreach ((array) config('ar.frames') as $gong) {
                        if (($gong['slug'] ?? null) === ($product['slug'] ?? '')) {
                            $arThuDuoc = true;
                            break;
                        }
                    }
                }
                ?>
                <?php if ($arThuDuoc): ?>
                    <a class="pdbtn pdbtn--ar"
                       href="/thu-ar?gong=<?= e(rawurlencode((string) $product['slug'])) ?>">
                        Thử ảo trên khuôn mặt
                    </a>
                <?php endif; ?>

                <?php
                /*
                 * CHỖ CHO LỜI NHẮC VỀ SỐ LƯỢNG — assets/js/product-detail.js điền.
                 *
                 * Thay cho bong bóng "Value must be less than or equal to 3." của
                 * trình duyệt: tiếng Anh, kiểu dáng của hệ điều hành, CSS không với
                 * tới, và nó CHẶN LUÔN form nên câu báo tiếng Việt của máy chủ
                 * không bao giờ hiện ra được.
                 *
                 * In sẵn thẻ rỗng ở đây chứ không để JS tự dựng: chỗ của nó trong
                 * luồng đọc phải cố định — ngay sau hàng nút mua, trước danh sách
                 * cam kết — chứ không phải nơi nào script chạy tới.
                 *
                 * aria-live="polite": trình đọc màn hình đọc câu mới khi nó xuất
                 * hiện, mà không cắt ngang thứ đang đọc dở.
                 */
                ?>
                <p class="pdqty__note" data-qty-note role="status" aria-live="polite" hidden></p>
            </form>

            <?php
            /* ═════════════════════════════════════════════════════════════════
               DẤU TRANG — lưu mặt hàng để xem lại (/tai-khoan?muc=da-luu)

               NẰM NGOÀI form mua ở trên, và bắt buộc phải thế: hai <form> lồng
               nhau là HTML không hợp lệ, trình duyệt vứt cái bên trong đi mà
               không báo gì. Cùng lý do với khối "Thông báo khi có hàng" ngay
               dưới đây.

               ĐÂY LÀ NƠI DUY NHẤT CÓ DẤU TRANG. Thẻ sản phẩm trong lưới từng
               có một icon cờ, nhưng nó không lưu được gì (bảng `favorites` khi
               đó đã gỡ khỏi CSDL) — đã bỏ ngày 12/09/2026 cùng lúc chức năng
               này dựng thật. Đừng đưa lại: một lưới bốn thẻ có bốn cái công
               tắc nhỏ là bốn cú bấm nhầm chờ sẵn, còn ở đây khách đã dừng lại
               trước đúng một mặt hàng.

               MỘT NÚT, HAI TRẠNG THÁI. Cùng một form, cùng một đường: máy chủ
               tự lật (FavoriteModel::batTat). aria-pressed nói cho trình đọc
               màn hình biết đây là công tắc và nó đang bật hay tắt — thiếu nó
               thì người dùng nghe "nút Đã lưu" mà không biết đó là trạng thái
               hiện tại hay việc sắp xảy ra.

               CHƯA ĐĂNG NHẬP thì vẫn in ra nút, chỉ đổi nhãn: bấm vào đi
               /auth kèm đường quay lại, nên khách quay về đúng trang này. Giấu
               hẳn thì không ai biết là có chức năng ấy.

               $luuDuoc = false nghĩa là máy chủ chưa chạy migration
               2026-09-12-yeu-thich-tro-lai. Lúc ấy KHÔNG in gì cả — một cái
               công tắc bấm vào chỉ ra câu "đang tạm ngưng" thì thà đừng vẽ.
               ═════════════════════════════════════════════════════════════════ */
            ?>
            <?php if (!empty($luuDuoc)): ?>
                <form class="pdsave" method="post" action="/yeu-thich">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                    <input type="hidden" name="back" value="<?= e(currentUrlWithout(['mua', 'buoc'])) ?>">

                    <button type="submit" class="pdsave__btn<?= !empty($daLuu) ? ' is-on' : '' ?>"
                            <?= empty($daDangNhap) ? '' : 'aria-pressed="' . (!empty($daLuu) ? 'true' : 'false') . '"' ?>>
                        <?php /* Cờ ĐẶC khi đã lưu, cờ RỖNG khi chưa — khác nhau ở
                                 mỗi `fill`. Hình dạng giữ nguyên để nút không
                                 nhảy kích thước giữa hai trạng thái. */ ?>
                        <svg class="pdsave__ico" width="14" height="16" viewBox="0 0 12 14"
                             fill="<?= !empty($daLuu) ? 'currentColor' : 'none' ?>"
                             stroke="currentColor" stroke-width="1.2"
                             aria-hidden="true" focusable="false">
                            <path d="M1 1h10v12L6 9.5 1 13V1z"/>
                        </svg>

                        <span><?php
                            if (empty($daDangNhap)) {
                                echo e(t('pd.save_login'));
                            } else {
                                echo e(!empty($daLuu) ? t('pd.saved') : t('pd.save'));
                            }
                        ?></span>
                    </button>
                </form>
            <?php endif; ?>

            <?php
            /* ─────────────────────────────────────────────────────────────────
               HAI MỤC GẬP DƯỚI NÚT MUA — <details> thật, không JS.

               "Chi tiết" MỞ SẴN: nó chứa mô tả và mọi thứ vừa dời từ đầu cột
               xuống (SKU · sao · tồn kho · hạn KM). Gập kín thì khách phải bấm
               mới thấy mô tả — một bước thừa cho thứ ai cũng đọc.

               "Giao hàng & đổi trả" GẬP: ai cần mới mở, và nó dẫn sang trang
               chính sách đầy đủ chứ không chép cả trang đó vào đây.

               Mục "Thông số kỹ thuật" và "Đánh giá" VẪN ở khối .pdbottom phía
               dưới, không đụng — chúng dài, không hợp nằm trong một cột hẹp.
               ───────────────────────────────────────────────────────────────── */
            ?>
            <div class="pdacc">
                <details class="pdacc__item" open>
                    <summary class="pdacc__sum">
                        <span><?= e(t('pd.acc_details')) ?></span>
                        <span class="pdacc__ico" aria-hidden="true"></span>
                    </summary>
                    <div class="pdacc__body">
                        <?= $pdMeta ?>
                    </div>
                </details>

                <details class="pdacc__item">
                    <summary class="pdacc__sum">
                        <span><?= e(t('pd.acc_shipping')) ?></span>
                        <span class="pdacc__ico" aria-hidden="true"></span>
                    </summary>
                    <div class="pdacc__body">
                        <p class="pdacc__text"><?= e(t('pd.acc_shipping_text')) ?></p>
                        <a class="pdacc__link" href="/chinh-sach"><?= e(t('pd.acc_shipping_link')) ?></a>
                    </div>
                </details>
            </div>

            <?php
            /*
             * ══════════════════════════════════════════════════════════════
             * HẾT HÀNG -> CHỖ ĐỂ KHÁCH XIN ĐƯỢC BÁO KHI HÀNG VỀ
             *
             * Nằm NGOÀI form mua ở trên: hai form lồng nhau là HTML không hợp
             * lệ, và trình duyệt sẽ vứt cái bên trong đi mà không báo gì.
             *
             * GẮN THEO BIẾN THỂ. Mặt hàng có phương án thì phải hỏi khách chờ
             * phương án nào — người chờ màu đen không quan tâm màu nâu vừa về.
             * Ô chọn ở đây là ô RIÊNG, không dùng lại bộ chọn phía trên: bộ
             * kia đã bị disabled vì hết hàng, mà thẻ disabled thì không gửi
             * giá trị nào theo form.
             *
             * KHÔNG HỨA EMAIL. Hosting hiện tại không gửi được thư (xem
             * WaitlistModel), nên chữ trên nút và câu xác nhận đều nói "liên
             * hệ" — nhân viên gọi hoặc nhắn Zalo. Ô số điện thoại vì thế đứng
             * TRƯỚC ô email: nó là kênh chắc chắn tới được.
             * ══════════════════════════════════════════════════════════════
             */
            $waitMsg = flash('waitlist_msg');
            ?>
            <?php
            /*
             * CÂU TRẢ LỜI HIỆN CẢ KHI HÀNG VỪA VỀ.
             *
             * Khối đăng ký chỉ vẽ khi mặt hàng đang hết. Nhưng giữa lúc khách
             * bấm gửi và lúc trang vẽ lại, nhân viên có thể vừa nhập hàng —
             * lúc đó $inStock thành true, khối biến mất, và câu "Đã ghi nhận"
             * bị nuốt: khách thấy trang tải lại mà không có gì xảy ra, rồi gửi
             * thêm lần nữa. Hiếm, nhưng im lặng là kiểu hỏng tệ nhất.
             */
            ?>
            <?php if ($inStock && $waitMsg !== null): ?>
                <p class="pdwait__msg pdwait__msg--loi" role="status"><?= e($waitMsg) ?></p>
            <?php endif; ?>

            <?php if (!$inStock): ?>
                <section class="pdwait" id="cho-hang" aria-labelledby="pdwait-title">
                    <h2 class="pdwait__title" id="pdwait-title"><?= e(t('pd.wait_title')) ?></h2>

                    <?php if ($waitMsg !== null): ?>
                        <p class="pdwait__msg" role="status"><?= e($waitMsg) ?></p>
                    <?php endif; ?>

                    <?php
                    /* ══════════════════════════════════════════════════════════
                       CHỈ KHÁCH ĐÃ ĐĂNG NHẬP — FR-SP-17

                       Hai ô "Số điện thoại" và "Email" đã gỡ; thông tin liên hệ
                       nay lấy từ chính tài khoản.

                       Không phải để bắt người ta đăng ký. Ô liên hệ tự do biến
                       danh sách chờ thành một hộp thư ai gõ gì cũng được: số sai,
                       số của người khác, cùng một người để lại bốn số khác nhau
                       cho bốn mẫu, và không có cách nào gộp hay đối chiếu. Tới
                       lúc hàng về, nhân viên cầm một danh sách không tra được ai
                       là ai.

                       Gắn vào tài khoản thì mỗi lượt chờ có một chủ thật: gọi
                       đúng người, biết họ đã mua gì, và khi module email chạy
                       (FR-EM-04) thì địa chỉ gửi đi là địa chỉ đã có chủ. */
                    ?>
                    <?php if ($daDangNhap): ?>
                        <p class="pdwait__lead">
                            Cửa hàng sẽ liên hệ theo số điện thoại và email trong tài khoản
                            của bạn ngay khi mẫu này về.
                        </p>

                        <form class="pdwait__form" method="post" action="/san-pham/cho-hang">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">

                            <?php if ($variants !== []): ?>
                                <label class="pdwait__label" for="cho-pa"><?= e(t('pd.wait_option')) ?></label>
                                <select class="pdwait__select" id="cho-pa" name="variant_id" required>
                                    <?php foreach ($variants as $v): ?>
                                        <option value="<?= e($v['id']) ?>"><?= e($v['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>

                            <div class="pdwait__row">
                                <button type="submit" class="pdwait__go"><?= e(t('pd.wait_go')) ?></button>
                            </div>
                        </form>
                    <?php else: ?>
                        <?php
                        /* LỜI MỜI ĐĂNG NHẬP, KHÔNG PHẢI MỘT DÒNG TỪ CHỐI.

                           ?redirect= đưa khách quay lại đúng mặt hàng này kèm neo
                           #cho-hang — đăng nhập xong là thấy ngay cái nút họ vừa
                           định bấm, không phải tự tìm đường về. */
                        $veLai = '/san-pham/' . rawurlencode((string) $product['slug']) . '#cho-hang';
                        ?>
                        <p class="pdwait__lead">
                            Đăng nhập để cửa hàng báo cho bạn khi mẫu này về — thông tin liên hệ
                            lấy sẵn từ tài khoản, bạn không phải gõ lại.
                        </p>
                        <a class="pdwait__go" href="/auth?redirect=<?= e(rawurlencode($veLai)) ?>">
                            Đăng nhập để nhận thông báo
                        </a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <ul class="pdcommit" role="list">
                <?php foreach ($commitments as [$ico, $title, $desc]): ?>
                    <li class="pdcommit__item">
                        <?= icon($ico, 'pdcommit__ico', 17) ?>
                        <span class="pdcommit__text">
                            <span class="pdcommit__title"><?= e($title) ?></span>
                            <span class="pdcommit__desc"><?= e($desc) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- ══════════ THÔNG SỐ + ĐÁNH GIÁ ══════════ -->
    <div class="pdbottom">

        <div class="pdcard">
            <h2 class="pdcard__title"><?= e(t('pd.specs')) ?></h2>

            <?php
            /* Ghép vài cột có sẵn vào bảng thông số để nó không quá lèo tèo khi
               cột `specs` chưa được điền — bản thiết kế vẽ sáu dòng. Cột nào
               trống thì bỏ, không in "—". */
            /* Lọc cả giá trị RỖNG lẫn dấu gạch: cửa hàng gõ "—" vào ô không áp
               dụng (tròng kính thì không có dáng gọng), và in nguyên dấu gạch
               ra bảng thông số trông như dữ liệu bị thiếu chứ không phải cố ý. */
            $blank = static fn ($v): bool =>
                $v === null || trim((string) $v) === ''
                || in_array(trim((string) $v), ['-', '–', '—', 'N/A', 'n/a'], true);

            $rows = array_filter([
                'Thương hiệu'  => $product['brand'] ?? null,
                'Chất liệu'    => $product['material'] ?? null,
                'Màu sắc'      => $product['color'] ?? null,
                'Dáng gọng'    => $product['frame_shape'] ?? null,
            ], static fn ($v) => !$blank($v)) + array_filter($specs, static fn ($v) => !$blank($v));

            if ($variants !== []) {
                $rows['Phương án'] = implode(' / ', array_column($variants, 'label'));
            }
            ?>

            <?php
            /*
             * ─────────────────────────────────────────────────────────────────
             * SỐ ĐO TÁCH RA KHỎI BẢNG THÔNG SỐ
             *
             * Cột `specs` của cửa hàng ghi kích thước theo quy ước quốc tế của
             * ngành kính: "53-18-145" = rộng tròng · cầu kính · càng kính (mm).
             *
             * In nguyên chuỗi đó vào một dòng bảng thì nó là một mã số không ai
             * đọc được. Tách thành ba số đo CÓ NHÃN là thứ khách thật sự cần để
             * biết gọng có vừa mặt mình không — và đó cũng là cách các nhà kính
             * lớn trình bày phần "size & fit".
             *
             * KHÔNG ĐỘNG TỚI DỮ LIỆU: chỉ đọc lại chuỗi đã có. Mẫu nào ghi
             * kiểu khác (hoặc để trống) thì $sizeParts rỗng và dòng đó ở lại
             * trong bảng thông số như cũ — không mất gì.
             * ─────────────────────────────────────────────────────────────────
             */
            $sizeParts = [];

            foreach (['Kích thước', 'Kich thuoc', 'Size'] as $khoa) {
                if (!isset($rows[$khoa])) {
                    continue;
                }

                if (preg_match('/^\s*(\d{2,3})\s*[-–—]\s*(\d{1,2})\s*[-–—]\s*(\d{2,3})\s*$/u', (string) $rows[$khoa], $so)) {
                    $sizeParts = [
                        ['Rộng tròng', $so[1]],
                        ['Cầu kính',   $so[2]],
                        ['Càng kính',  $so[3]],
                    ];
                    unset($rows[$khoa]);
                }

                break;
            }
            ?>

            <?php if ($rows === [] && $sizeParts === []): ?>
                <p class="pdcard__empty"><?= e(t('pd.specs_empty')) ?></p>
            <?php else: ?>
                <?php if ($rows !== []): ?>
                    <dl class="pdspecs">
                        <?php foreach ($rows as $k => $v): ?>
                            <div class="pdspecs__row">
                                <dt><?= e((string) $k) ?></dt>
                                <dd><?= e((string) $v) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($sizeParts !== []): ?>
            <?php /* KHỐI SỐ ĐO — nét mảnh, đơn sắc, không đồ hoạ màu. Ba con số
                     đứng thành hàng với nhãn nhỏ IN HOA bên dưới, đúng ngôn ngữ
                     kỹ thuật tối giản của phần còn lại. */ ?>
            <div class="pdcard">
                <h2 class="pdcard__title">Kích thước &amp; vừa vặn</h2>

                <ul class="pdsize" role="list">
                    <?php foreach ($sizeParts as [$nhan, $mm]): ?>
                        <li class="pdsize__item">
                            <span class="pdsize__num"><?= e($mm) ?><span class="pdsize__unit">mm</span></span>
                            <span class="pdsize__label"><?= e($nhan) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p class="pdcard__note">
                    Số đo theo quy ước quốc tế: rộng tròng · cầu kính · càng kính.
                </p>
            </div>
        <?php endif; ?>

        <div class="pdcard" id="danh-gia">
            <div class="pdcard__head">
                <h2 class="pdcard__title"><?= e(t('pd.reviews')) ?> (<?= $reviewN ?>)</h2>
                <div class="pdcard__score">
                    <span class="pdcard__num"><?= e(number_format($rating, 1)) ?></span>
                    <span class="pdstars pdstars--sm" aria-hidden="true"><?= $stars($rating) ?></span>
                </div>
            </div>

            <?php if ($reviewMsg !== null): ?>
                <p class="pdreview__flash<?= $reviewOk ? ' is-ok' : ' is-err' ?>"
                   role="<?= $reviewOk ? 'status' : 'alert' ?>"><?= e($reviewMsg) ?></p>
            <?php endif; ?>

            <?php if ($reviews === []): ?>
                <p class="pdcard__empty"><?= e(t('pd.reviews_empty')) ?></p>
            <?php else: ?>
                <?php foreach ($reviews as $rv): ?>
                    <article class="pdreview">
                        <div class="pdreview__head">
                            <span class="pdreview__face" aria-hidden="true">
                                <?= e(utf8Substr($rv['author_name'], 0, 1)) ?>
                            </span>
                            <span class="pdreview__who">
                                <span class="pdreview__name"><?= e($rv['author_name']) ?></span>
                                <span class="pdreview__meta">
                                    <?php
                                    /* "Đã mua · Chiết suất 1.61 · 08/2026" — huy hiệu
                                       "Đã mua" chỉ có khi đánh giá gắn với một đơn thật. */
                                    $bits = [];
                                    if ($rv['order_id'] !== null)      { $bits[] = 'Đã mua'; }
                                    if (!empty($rv['variant_label']))  { $bits[] = $rv['variant_label']; }
                                    $bits[] = formatDate($rv['created_at'], 'm/Y');
                                    echo e(implode(' · ', $bits));
                                    ?>
                                </span>
                            </span>
                            <span class="pdstars pdstars--sm pdreview__stars" aria-label="<?= (int) $rv['rating'] ?> trên 5 sao">
                                <?= $stars((float) $rv['rating']) ?>
                            </span>
                        </div>
                        <p class="pdreview__body"><?= e($rv['body']) ?></p>

                        <?php /* PHẢN HỒI CỦA CỬA HÀNG — cột `reply`, do khu quản trị
                                 soạn (xem ReviewAdminController::reply()).

                                 Kiểm cả isset lẫn chuỗi rỗng: cột chỉ có từ migration
                                 2026-08-28-phan-hoi-danh-gia, và trên máy chưa chạy
                                 file đó thì khoá này không tồn tại — trang sản phẩm là
                                 trang khách xem, không được đổ lỗi vì một cột thiếu.
                                 Rỗng thì cũng không vẽ: nó nghĩa là đã trả lời rồi xoá
                                 chữ đi, không phải một khối trống cần bày ra. */ ?>
                        <?php if (trim((string) ($rv['reply'] ?? '')) !== ''): ?>
                            <div class="pdreply">
                                <p class="pdreply__from">
                                    Phản hồi của <?= e(config('company.short_name', 'cửa hàng')) ?>
                                    <?php if (!empty($rv['replied_at'])): ?>
                                        <span class="pdreply__when"><?= e(formatDate($rv['replied_at'])) ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="pdreply__body"><?= e($rv['reply']) ?></p>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!$showAll && $reviewN > count($reviews)): ?>
                <a class="pdcard__more" href="/san-pham/<?= $slug ?>?danh-gia=tat-ca#danh-gia">
                    Xem tất cả đánh giá
                </a>
            <?php endif; ?>

            <?php if ($canReview['ok']): ?>
                <form class="pdwrite" method="post" action="/san-pham/danh-gia">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">

                    <span class="pdwrite__label"><?= e(t('pd.write')) ?></span>

                    <!-- Năm ô radio xếp NGƯỢC trong HTML rồi lật lại bằng CSS
                         (flex-direction: row-reverse). Nhờ vậy "tô sáng mọi sao
                         bên trái sao đang chọn" làm được bằng bộ chọn ~ của CSS,
                         vốn chỉ nhìn được về phía SAU trong cây DOM. -->
                    <div class="pdwrite__stars" role="radiogroup" aria-label="<?= e(t('pd.rate')) ?>">
                        <?php foreach ([5, 4, 3, 2, 1] as $n): ?>
                            <input type="radio" name="rating" id="sao-<?= $n ?>" value="<?= $n ?>"
                                   required <?= $n === 5 ? 'checked' : '' ?>>
                            <label for="sao-<?= $n ?>" title="<?= $n ?> sao">
                                <span class="sr-only"><?= $n ?> sao</span>
                                <span aria-hidden="true">★</span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <label class="sr-only" for="noi-dung"><?= e(t('pd.comment')) ?></label>
                    <textarea class="pdwrite__area" id="noi-dung" name="body" rows="3"
                              required minlength="10" maxlength="2000"
                              placeholder="<?= e(t('pd.comment_ph')) ?>"></textarea>

                    <button type="submit" class="pdwrite__send"><?= e(t('pd.send')) ?></button>
                    <span class="pdwrite__note"><?= e(t('pd.moderated')) ?></span>
                </form>
            <?php elseif (!empty($canReview['reason'])): ?>
                <p class="pdcard__note"><?= e($canReview['reason']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($related !== []): ?>
        <section class="pdrelated" aria-labelledby="lien-quan">
            <h2 class="pdcard__title" id="lien-quan"><?= e(t('pd.related')) ?></h2>
            <?php /* `.pgrid` — MỘT lưới thẻ cho cả site (Phase 2). */ ?>
<ul class="pgrid" role="list">
                <?php foreach ($related as $item): ?>
                    <?php partial('_layout/product-card', ['product' => $item]); ?>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
