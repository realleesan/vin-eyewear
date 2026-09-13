<?php

/**
 * product/detail.php — chi tiết sản phẩm (/san-pham/{slug})
 *
 * Dựng theo "Product Detail.dc.html" (Claude Design, 13/09/2026):
 *
 *   ┌──────────────────────────────────────────┬──────────────┐
 *   │                                          │ Tên        ⚑ │  cột phải 236px,
 *   │    ảnh 1 — trọn một màn hình, căn giữa    │ Giá          │  DÍNH khi cuộn và
 *   │                                          │ ■ ■ ■    Màu │  tự cuộn bên trong
 *   ├──────────────────────────────────────────┤ [ MUA NGAY ] │  khi dài hơn màn
 *   │    ảnh 2 — một màn hình                   │ [THÊM VÀO GIỎ]│  hình
 *   │    …                                     │ Giao hàng  + │
 *   │                   ⌄                      │ Chi tiết   – │
 *   └──────────────────────────────────────────┴──────────────┘
 *   → Campaign: năm ảnh 3:4 của bộ sưu tập + đoạn giới thiệu
 *   → Sản phẩm tương tự: năm thẻ 1:1
 *
 * CSS: assets/css/product-detail.css · JS: assets/js/product-detail.js
 *
 * KHÔNG CẦN JAVASCRIPT ĐỂ DÙNG TRANG: mục gập là <details>, phương án là radio
 * thật trong form mua, dấu trang là một form POST. JS chỉ đổi dòng tên màu.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NHỮNG CHỖ BẢN THIẾT KẾ KHÔNG VẼ MÀ TRANG VẪN PHẢI CÓ
 *
 * Bản thiết kế là một mẫu tĩnh; cửa hàng thật có thêm vài trạng thái. Chúng
 * được đặt vào đúng ngôn ngữ của mẫu thay vì vẽ thêm khối mới:
 *   - phương án KHÔNG phải màu (chiết suất tròng) -> hàng chọn viền mảnh,
 *     cùng luật viền đen = đang chọn của ô màu;
 *   - hết hàng -> khối "Thông báo khi có hàng" ngay dưới hai nút;
 *   - thử ảo, video 360 -> liên kết gạch chân kiểu "WRITE A REVIEW";
 *   - bốn cam kết cửa hàng -> nằm trong mục gập "Giao hàng & đổi trả".
 * ─────────────────────────────────────────────────────────────────────────────
 */

/* Xem chú thích cùng nội dung ở _layout/product-card.php: giá hiện ra phải là
   giá GIỎ HÀNG SẼ TÍNH, nên cả hai chỗ cùng đi qua ProductPricing. */
$price   = ProductPricing::giaBan($product);
$compare = ProductPricing::giaGach($product);
$hanKM   = ProductPricing::hanKhuyenMai($product);
$rating  = (float) ($product['rating'] ?? 5);
$reviewN = (int) ($product['review_count'] ?? 0);

/*
 * LỌC ẢNH KHÔNG PHẢI ẢNH SẢN PHẨM RA KHỎI THƯ VIỆN
 *
 * Cột `images` cho dán đường dẫn bất kỳ, và dữ liệu đang chạy có mặt hàng gắn
 * nhầm ảnh nội thất một cửa hàng khác (showroom-frames.jpg) và ảnh bìa chiến
 * dịch (hero-eyewear.jpg). Ở đây không đoán "ảnh có đúng mặt hàng không" — chỉ
 * loại những tấm mà VAI TRÒ đã sai. Lọc ở tầng view: đây là quyết định trình
 * bày, tự hết tác dụng khi dữ liệu ảnh được dọn.
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
$specs = is_array($product['specs']) ? $product['specs'] : [];
$slug  = rawurlencode($product['slug']);

/* ── PHƯƠNG ÁN ──────────────────────────────────────────────────────────────
   Chọn sẵn: phương án khách vừa chọn (?pa= / ?bien-the=) NẾU còn hàng, không
   thì phương án còn hàng đầu tiên — chọn sẵn một phương án đã hết là bẫy. */
$firstInStock = null;
foreach ($variants as $v) {
    if ((int) $v['stock_quantity'] > 0) { $firstInStock = $v['id']; break; }
}

$activeVariant = $firstInStock ?? '';
foreach ($variants as $v) {
    if ($v['id'] === $pickedVariant && (int) $v['stock_quantity'] > 0) {
        $activeVariant = $v['id'];
        break;
    }
}

/* Còn hàng hay không, tính cả biến thể: có biến thể thì chỉ cần MỘT phương án
   còn hàng là mặt hàng còn bán được. */
$inStock = $variants === []
    ? ProductModel::inStock($product)
    : ($product['status'] === 'in_stock' && $firstInStock !== null);

/* Tồn tổng — chỉ để nói "Chỉ còn N" khi đã xuống dưới ngưỡng CỦA CHÍNH MẶT
   HÀNG (cột low_stock_at, trống thì mốc chung). Cùng nguồn với bảng Tồn kho
   ở khu quản trị: ProductModel::nguongSapHet(). */
$stockLeft = $variants === []
    ? (int) ($product['stock_quantity'] ?? 0)
    : array_sum(array_map(static fn ($v) => (int) $v['stock_quantity'], $variants));

$stockLow = $inStock
    && $stockLeft > 0
    && $stockLeft <= ProductModel::nguongSapHet($product);

/*
 * Ô MÀU HAY HÀNG CHỌN?
 *
 * Bản thiết kế chỉ vẽ ô màu vuông 16px. Chỉ dùng được khi MỌI phương án có
 * một mã màu — cùng hai nguồn với thẻ sản phẩm: `swatch_hex` gõ tay, rồi suy
 * từ tên màu qua ProductTaxonomy::colorHex(). Một phương án không có màu
 * (chiết suất 1.61, cỡ gọng) thì cả nhóm về hàng chọn có chữ: một ô màu trống
 * giữa các ô màu thật là một lựa chọn không ai đọc được.
 *
 * KHÔNG gộp màu trùng như ở thẻ: ở đây mỗi ô là một phương án MUA ĐƯỢC, gộp
 * là mất một thứ khách có thể muốn.
 */
$swatchHex = [];
foreach ($variants as $v) {
    $ma = trim((string) ($v['swatch_hex'] ?? ''));

    if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $ma)) {
        $ma = (string) ProductTaxonomy::colorHex((string) ($v['color'] ?? ''));
    }

    if ($ma === '') {
        $swatchHex = [];
        break;
    }

    $swatchHex[$v['id']] = $ma;
}
$laOMau = $variants !== [] && count($swatchHex) === count($variants);

/* Tên đọc lên của một phương án. Chênh giá và "Tạm hết" đi KÈM tên, vì hàng ô
   màu không còn chỗ nào khác để nói chúng — mà đổi giá âm thầm khi khách bấm
   là điều tệ nhất một trang bán hàng làm được. */
$tenPhuongAn = static function (array $v) use ($laOMau): string {
    $ten = $laOMau && trim((string) ($v['color'] ?? '')) !== ''
        ? (string) $v['color']
        : (string) $v['label'];

    $delta = (int) $v['price_delta'];

    if ($delta !== 0) {
        $ten .= ' · ' . ($delta > 0 ? '+' : '−') . money(abs($delta));
    }

    if ((int) $v['stock_quantity'] <= 0) {
        $ten .= ' · Tạm hết';
    }

    return $ten;
};

$tenDangChon = '';
foreach ($variants as $v) {
    if ($v['id'] === $activeVariant) { $tenDangChon = $tenPhuongAn($v); break; }
}
if ($tenDangChon === '' && $variants !== []) {
    $tenDangChon = $tenPhuongAn($variants[0]);
}

/* ── THÔNG SỐ ───────────────────────────────────────────────────────────────
   Chia làm hai mục gập như bản thiết kế: "Chi tiết" (chất liệu, màu, dáng…)
   và "Kích cỡ & vừa vặn" (số đo, trọng lượng). */

/* Giá trị rỗng hoặc chỉ là dấu gạch thì bỏ: cửa hàng gõ "—" vào ô không áp
   dụng, in nguyên ra trông như dữ liệu bị thiếu. */
$blank = static fn ($v): bool =>
    $v === null || trim((string) $v) === ''
    || in_array(trim((string) $v), ['-', '–', '—', 'N/A', 'n/a'], true);

$specRows = array_filter($specs, static fn ($v) => !$blank($v));

$layRa = static function (array &$rows, array $khoa): ?string {
    foreach ($khoa as $k) {
        if (isset($rows[$k])) {
            $gt = (string) $rows[$k];
            unset($rows[$k]);
            return $gt;
        }
    }
    return null;
};

$kichThuoc = $layRa($specRows, ['Kích thước', 'Kich thuoc', 'Size']);
$trongLuong = $layRa($specRows, ['Trọng lượng', 'Trong luong', 'Weight']);

/* "53-18-145" = rộng tròng · cầu kính · càng kính (mm), quy ước quốc tế của
   ngành kính. Tách thành ba số đo có nhãn; ghi kiểu khác thì in nguyên. */
$sizeRows = [];
$theoQuyUoc = false;

if ($kichThuoc !== null) {
    if (preg_match('/^\s*(\d{2,3})\s*[-–—]\s*(\d{1,2})\s*[-–—]\s*(\d{2,3})\s*$/u', $kichThuoc, $so)) {
        $sizeRows['Rộng tròng'] = $so[1] . ' mm';
        $sizeRows['Cầu kính']   = $so[2] . ' mm';
        $sizeRows['Càng kính']  = $so[3] . ' mm';
        $theoQuyUoc = true;
    } else {
        $sizeRows['Kích thước'] = $kichThuoc;
    }
}

if ($trongLuong !== null) {
    $sizeRows['Trọng lượng'] = $trongLuong;
}

$detailRows = array_filter([
    'Thương hiệu' => $product['brand'] ?? null,
    'Bộ sưu tập'  => $collection['name'] ?? null,
    'Chất liệu'   => $product['material'] ?? null,
    'Màu sắc'     => $product['color'] ?? null,
    'Dáng gọng'   => $product['frame_shape'] ?? null,
], static fn ($v) => !$blank($v)) + $specRows;

if ($variants !== []) {
    $detailRows['Phương án'] = implode(' / ', array_column($variants, 'label'));
}

if (!$blank($product['sku'] ?? null)) {
    $detailRows['Mã sản phẩm'] = (string) $product['sku'];
}

$commitments = [
    ['Bảo hành 24 tháng',   'Lỗi nhà sản xuất, đổi mới trong 7 ngày đầu'],
    ['Đo mắt miễn phí',     'Quy trình khúc xạ chuẩn, kể cả khi không mua'],
    ['Giao hàng đồng kiểm', 'Mở hộp kiểm tra trước khi thanh toán'],
    ['Nắn chỉnh trọn đời',  'Miễn phí không giới hạn tại cả hai cơ sở'],
];

/** Năm ngôi sao đặc/rỗng theo điểm. Sao ĐEN, không vàng — mẫu không có vàng. */
$stars = static function (float $score): string {
    $full = max(0, min(5, (int) round($score)));
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
};

/*
 * NÚT DẤU TRANG — một form POST /yeu-thich, dùng ở hai chỗ: cạnh tên sản phẩm
 * và trên từng thẻ "sản phẩm tương tự" (bản thiết kế vẽ cả hai).
 *
 * Luôn là FORM RIÊNG, không bao giờ nằm trong form mua: hai form lồng nhau là
 * HTML sai, trình duyệt vứt form bên trong mà không báo gì.
 *
 * ┌─ KHÔNG CÒN PHÂN BIỆT ĐÃ/CHƯA ĐĂNG NHẬP — 13/09/2026 ──────────────────────
 * │ Bản cũ đổi nhãn thành "Đăng nhập để lưu" và BỎ aria-pressed cho khách
 * │ vãng lai, vì khi ấy bấm vào là bị đẩy sang /auth — nút không phải một
 * │ công tắc, nó là một lời mời.
 * │
 * │ Nay khách vãng lai lưu được thật (xem app/services/Wishlist.php), nên nó
 * │ là công tắc với MỌI người: nhãn "Lưu sản phẩm" và aria-pressed nói đúng
 * │ việc sẽ xảy ra. Giữ nhãn cũ là hứa sai — bấm vào thì nó lưu luôn chứ
 * │ chẳng đưa ai đi đăng nhập cả.
 * │
 * │ ⚠ Khoá 'pd.save_login' trong lang/*.php ĐỂ LẠI, không xoá: gỡ hẳn thì
 * │   lúc muốn quay lại luật cũ phải viết lại cả hai bản dịch.
 * └──────────────────────────────────────────────────────────────────────────
 */
$nutLuu = static function (string $slugSp, string $tenSp, bool $dangLuu, int $co): string {
    $nhan  = t('pd.save');
    $trang = ' aria-pressed="' . ($dangLuu ? 'true' : 'false') . '"';

    return '<form class="pdsave" method="post" action="/yeu-thich/luu">'
        . '<input type="hidden" name="_token" value="' . e(csrfToken()) . '">'
        . '<input type="hidden" name="slug" value="' . e($slugSp) . '">'
        . '<input type="hidden" name="back" value="' . e(currentUrlWithout(['mua', 'buoc'])) . '">'
        . '<button type="submit" class="pdsave__btn pdsave__btn--' . $co . ($dangLuu ? ' is-on' : '') . '"' . $trang
        . ' aria-label="' . e($nhan . ' — ' . $tenSp) . '"'
        . ' title="' . e($dangLuu ? t('pd.saved') : $nhan) . '">'
        . '<svg viewBox="0 0 14 14" fill="' . ($dangLuu ? 'currentColor' : 'none') . '"'
        . ' stroke="currentColor" stroke-width="1.1" aria-hidden="true" focusable="false">'
        . '<path d="M3 1.5h8v11l-4-3-4 3z"/></svg>'
        . '</button></form>';
};
?>

<section class="pd">

    <?php /* KHÔNG CÒN breadcrumb — bản thiết kế mở trang thẳng bằng ảnh đầu,
             chiếm trọn màn hình dưới thanh đầu trang. */ ?>
    <div class="pd__main">

        <!-- ══════════ ẢNH — MỖI TẤM MỘT MÀN HÌNH ══════════ -->
        <div class="pdgal">
            <?php if ($images === []): ?>
                <?php /* Ô trống thật thà — không mượn ảnh của mặt hàng khác. */ ?>
                <figure class="pdgal__screen">
                    <span class="pdgal__empty"><?= e(t('product.no_image')) ?></span>
                </figure>
            <?php endif; ?>

            <?php foreach ($images as $i => $img): ?>
                <figure class="pdgal__screen">
                    <?php /* Tấm đầu fetchpriority="high" và không lazy — nó là
                             thứ khách nhìn để quyết định. asset() để một tấm ảnh
                             chỉ có một địa chỉ (xem _layout/product-card.php). */ ?>
                    <img class="pdgal__img" src="<?= e(asset($img)) ?>"
                         alt="<?= e($product['name']) ?><?= $i > 0 ? ' — ảnh ' . ($i + 1) : '' ?>"
                         width="1000" height="1000"
                         <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                </figure>
            <?php endforeach; ?>

            <div class="pdgal__end" aria-hidden="true">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1" focusable="false">
                    <path d="M2 5l6 6 6-6"/>
                </svg>
            </div>
        </div>

        <!-- ══════════ CỘT MUA — DÍNH, TỰ CUỘN ══════════ -->
        <?php
        /* [data-recent] — "đã xem gần đây" của lớp phủ tìm kiếm. search-suggest.js
           đọc JSON này và đẩy vào localStorage; máy chủ không lưu gì. Giá là
           CHUỖI ĐÃ ĐỊNH DẠNG để bên JS không phải biết luật giá. */
        $recentJson = json_encode([
            'slug'  => (string) $product['slug'],
            'name'  => (string) $product['name'],
            'image' => $images !== [] ? asset($images[0]) : '',
            'price' => money($price),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        <aside class="pdinfo" data-recent="<?= e($recentJson) ?>">

            <div class="pdinfo__head">
                <div>
                    <?php /* notranslate — tên sản phẩm là danh từ riêng, giữ
                             nguyên trên bản tiếng Anh của giao diện. */ ?>
                    <h1 class="pdinfo__title notranslate" translate="no" lang="vi"><?= e($product['name']) ?></h1>
                    <p class="pdinfo__price">
                        <span class="pdinfo__now"><?= money($price) ?></span>
                        <?php if ($compare !== null && $compare > $price): ?>
                            <s class="pdinfo__old"><span class="sr-only"><?= e(t('product.was_label')) ?> </span><?= money($compare) ?></s>
                        <?php endif; ?>
                    </p>
                </div>

                <?php /* $luuDuoc = false: máy chủ chưa chạy migration bảng
                         yêu thích — không vẽ một công tắc bấm vào chỉ ra
                         câu "đang tạm ngưng". */ ?>
                <?php if (!empty($luuDuoc)): ?>
                    <?= $nutLuu((string) $product['slug'], (string) $product['name'], !empty($daLuu), 14) ?>
                <?php endif; ?>
            </div>

            <form class="pdbuy" method="post" action="/gio-hang/them">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                <?php /* Nơi quay về sau khi chọn hình thức mua — hộp thoại
                         "Chọn hình thức mua" hiện đè lên chính trang này. */ ?>
                <input type="hidden" name="back" value="<?= e(currentUrlWithout(['mua', 'buoc'])) ?>">
                <?php /* Bản thiết kế không có ô số lượng: mua một chiếc, đổi số
                         lượng ở giỏ hàng (CartController kẹp theo tồn kho). */ ?>
                <input type="hidden" name="quantity" value="1">

                <?php if ($laOMau): ?>
                    <div class="pdswatch">
                        <div class="pdswatch__row" role="radiogroup" aria-label="<?= e(t('pd.color')) ?>">
                            <?php foreach ($variants as $v): ?>
                                <?php $vStock = (int) $v['stock_quantity']; $ten = $tenPhuongAn($v); ?>
                                <label class="pdswatch__opt" title="<?= e($ten) ?>">
                                    <input type="radio" name="variant_id" value="<?= e($v['id']) ?>"
                                           data-name="<?= e($ten) ?>"
                                           required <?= $vStock > 0 ? '' : 'disabled' ?>
                                           <?= $activeVariant === $v['id'] ? 'checked' : '' ?>>
                                    <span class="pdswatch__chip" style="--sw: <?= e($swatchHex[$v['id']]) ?>" aria-hidden="true"></span>
                                    <span class="sr-only"><?= e($ten) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <span class="pdswatch__name" data-variant-name><?= e($tenDangChon) ?></span>
                    </div>
                <?php elseif ($variants !== []): ?>
                    <div class="pdopts" role="radiogroup" aria-label="<?= e(t('pd.options')) ?>">
                        <?php foreach ($variants as $v): ?>
                            <?php $vStock = (int) $v['stock_quantity']; $delta = (int) $v['price_delta']; ?>
                            <label class="pdopt">
                                <input type="radio" name="variant_id" value="<?= e($v['id']) ?>"
                                       required <?= $vStock > 0 ? '' : 'disabled' ?>
                                       <?= $activeVariant === $v['id'] ? 'checked' : '' ?>>
                                <span class="pdopt__name"><?= e($v['label']) ?></span>
                                <span class="pdopt__note">
                                    <?php
                                    /* Một chỗ ghi chú, theo thứ tự khách cần biết:
                                       hết hàng > chênh giá > sắp hết > ghi chú. */
                                    if ($vStock <= 0) {
                                        echo 'Tạm hết';
                                    } elseif ($delta !== 0) {
                                        echo ($delta > 0 ? '+' : '−') . money(abs($delta));
                                    } elseif ($vStock <= 10) {
                                        echo 'Chỉ còn ' . $vStock;
                                    } else {
                                        echo e($v['note'] ?? '');
                                    }
                                    ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="pdbuy__btns">
                    <?php /* HAI NÚT, MỘT FORM: trình duyệt chỉ gửi name/value của
                             nút được bấm. "Mua ngay" gửi action=buy và
                             CartController::add() đưa thẳng tới thanh toán. */ ?>
                    <button type="submit" name="action" value="buy" class="pdbtn pdbtn--solid"
                            <?= $inStock ? '' : 'disabled' ?>>
                        <?= $inStock ? e(t('product.buy_now')) : 'Tạm hết hàng' ?>
                    </button>
                    <button type="submit" class="pdbtn" <?= $inStock ? '' : 'disabled' ?>>
                        <?= e(t('product.add_to_cart')) ?>
                    </button>
                </div>

                <?php
                /* THỬ ẢO — FR-SP-22, chỉ với mẫu có trong config('ar.frames'):
                   mẫu khác thì trang thử AR không có gì để đội lên mặt. <a>
                   trong <form> hợp lệ và không gửi form. */
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
                    <a class="pdlink pdbuy__ar" href="/thu-ar?gong=<?= e(rawurlencode((string) $product['slug'])) ?>">
                        <?= e(t('pd.try_ar')) ?>
                    </a>
                <?php endif; ?>
            </form>

            <?php
            /*
             * HẾT HÀNG -> XIN BÁO KHI HÀNG VỀ. Ngoài form mua (không lồng form).
             *
             * Chỉ khách đã đăng nhập — FR-SP-17: liên hệ lấy từ tài khoản, nên
             * mỗi lượt chờ có một chủ thật. Không hứa email: hosting chưa gửi
             * được thư, cửa hàng LIÊN HỆ qua điện thoại hoặc Zalo.
             *
             * Câu trả lời hiện cả khi hàng vừa về giữa lúc khách gửi và lúc
             * trang vẽ lại — khối đăng ký lúc đó đã biến mất, im lặng là kiểu
             * hỏng tệ nhất.
             */
            $waitMsg = flash('waitlist_msg');
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

                    <?php if ($daDangNhap): ?>
                        <p class="pdwait__lead">
                            Cửa hàng sẽ liên hệ theo số điện thoại và email trong tài khoản
                            của bạn ngay khi mẫu này về.
                        </p>

                        <form class="pdwait__form" method="post" action="/san-pham/cho-hang">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">

                            <?php /* Ô chọn RIÊNG: bộ chọn phía trên đã disabled
                                     vì hết hàng, và ô disabled không gửi giá trị. */ ?>
                            <?php if ($variants !== []): ?>
                                <label class="pdwait__label" for="cho-pa"><?= e(t('pd.wait_option')) ?></label>
                                <select class="pdwait__select" id="cho-pa" name="variant_id" required>
                                    <?php foreach ($variants as $v): ?>
                                        <option value="<?= e($v['id']) ?>"><?= e($v['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>

                            <button type="submit" class="pdwait__go"><?= e(t('pd.wait_go')) ?></button>
                        </form>
                    <?php else: ?>
                        <?php $veLai = '/san-pham/' . rawurlencode((string) $product['slug']) . '#cho-hang'; ?>
                        <p class="pdwait__lead">
                            Đăng nhập để cửa hàng báo cho bạn khi mẫu này về — thông tin liên hệ
                            lấy sẵn từ tài khoản, bạn không phải gõ lại.
                        </p>
                        <?php /* [data-authov-open]: mở ngăn kéo tại chỗ thay vì
                                 sang trang — 13/09/2026, chủ dự án chốt MỌI lối
                                 vào đăng nhập đều phải là lớp phủ. Đây là lối
                                 thứ hai (lối kia là popup "chỉ dành cho thành
                                 viên"); sau hai chỗ này, không còn thẻ nào
                                 trong app/views đưa khách rời trang để đăng
                                 nhập. Vẫn là <a href> thật để tắt JS không mất
                                 lối. Xem _layout/login-gate.php. */ ?>
                        <a class="pdwait__go" href="/auth?redirect=<?= e(rawurlencode($veLai)) ?>"
                           data-authov-open aria-haspopup="dialog">
                            Đăng nhập để nhận thông báo
                        </a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- ══════════ MỤC GẬP — thứ tự của bản thiết kế ══════════ -->
            <div class="pdacc">

                <details class="pdacc__item">
                    <summary class="pdacc__sum">
                        <span><?= e(t('pd.acc_shipping')) ?></span>
                        <span class="pdacc__ico" aria-hidden="true"></span>
                    </summary>
                    <div class="pdacc__body">
                        <p><?= e(t('pd.acc_shipping_text')) ?></p>
                        <ul class="pdacc__list" role="list">
                            <?php foreach ($commitments as [$tieuDe, $moTa]): ?>
                                <li>
                                    <span class="pdacc__em"><?= e($tieuDe) ?></span>
                                    <span class="pdacc__mute"><?= e($moTa) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="pdlink" href="/chinh-sach"><?= e(t('pd.acc_shipping_link')) ?></a>
                    </div>
                </details>

                <?php /* "Chi tiết" MỞ SẴN — đúng trạng thái mặc định của mẫu. */ ?>
                <details class="pdacc__item" open>
                    <summary class="pdacc__sum">
                        <span><?= e(t('pd.acc_details')) ?></span>
                        <span class="pdacc__ico" aria-hidden="true"></span>
                    </summary>
                    <div class="pdacc__body pdacc__body--details">
                        <?php if (!empty($product['description'])): ?>
                            <p class="pdacc__text"><?= e($product['description']) ?></p>
                        <?php endif; ?>

                        <?php if ($detailRows !== []): ?>
                            <dl class="pdkv">
                                <?php foreach ($detailRows as $k => $v): ?>
                                    <dt><?= e((string) $k) ?></dt>
                                    <dd><?= e((string) $v) ?></dd>
                                <?php endforeach; ?>
                            </dl>
                        <?php elseif (empty($product['description'])): ?>
                            <p class="pdacc__mute"><?= e(t('pd.specs_empty')) ?></p>
                        <?php endif; ?>

                        <div>
                            <?php
                            /* BA CÂU, CHỈ MỘT CÂU CÓ CON SỐ: "Hết hàng" · "Chỉ còn 3
                               sản phẩm" · "Còn hàng". Con số chỉ in khi nó nói được
                               điều gì — lúc nào cũng có số thì "Chỉ còn 3" không còn
                               khác gì "Còn 42" trong mắt người lướt qua. */
                            ?>
                            <p class="pdinfo__stock<?= $inStock ? '' : ' is-out' ?>">
                                <?php
                                if (!$inStock) {
                                    echo 'Hết hàng';
                                } elseif ($stockLow) {
                                    echo 'Chỉ còn ' . $stockLeft . ' sản phẩm';
                                } else {
                                    echo 'Còn hàng';
                                }
                                ?>
                            </p>
                            <?php /* Hạn khuyến mãi — chỉ khi chương trình ĐANG chạy
                                     và CÓ mốc kết thúc (ProductPricing lo cả hai). */ ?>
                            <?php if ($hanKM !== null): ?>
                                <p>Giá khuyến mãi tới hết ngày <?= e(formatDate($hanKM, 'd/m/Y')) ?>.</p>
                            <?php endif; ?>
                        </div>

                        <?php
                        /* ẢNH 360 / VIDEO — mở tab mới, không nhúng iframe (script
                           và cookie bên thứ ba trên mọi lượt mở trang). Chỉ nhận
                           http/https: cột này in thẳng ra href, mà "javascript:"
                           trong href là chạy mã trên phiên của khách. */
                        $video = trim((string) ($product['video_url'] ?? ''));
                        $video = preg_match('#^https?://#i', $video) ? $video : '';
                        ?>
                        <?php if ($video !== ''): ?>
                            <a class="pdlink" href="<?= e($video) ?>" target="_blank" rel="noopener noreferrer">
                                Xem ảnh 360 / video
                            </a>
                        <?php endif; ?>
                    </div>
                </details>

                <?php if ($sizeRows !== []): ?>
                    <details class="pdacc__item">
                        <summary class="pdacc__sum">
                            <span><?= e(t('pd.acc_size')) ?></span>
                            <span class="pdacc__ico" aria-hidden="true"></span>
                        </summary>
                        <div class="pdacc__body">
                            <dl class="pdkv">
                                <?php foreach ($sizeRows as $k => $v): ?>
                                    <dt><?= e($k) ?></dt>
                                    <dd><?= e($v) ?></dd>
                                <?php endforeach; ?>
                            </dl>
                            <?php if ($theoQuyUoc): ?>
                                <p><?= e(t('pd.size_note')) ?></p>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <?php
                /* ĐÁNH GIÁ — mở sẵn khi vừa gửi xong (có câu báo) hoặc khi đang
                   xem ?danh-gia=tat-ca: cả hai trường hợp khách tới đây là để
                   đọc đúng khối này, bắt họ bấm mở thêm là một bước thừa. */
                $moDanhGia = $reviewMsg !== null || $showAll;
                ?>
                <details class="pdacc__item" id="danh-gia" <?= $moDanhGia ? 'open' : '' ?>>
                    <summary class="pdacc__sum">
                        <span><?= e(t('pd.reviews')) ?> (<?= $reviewN ?>)</span>
                        <span class="pdacc__ico" aria-hidden="true"></span>
                    </summary>
                    <div class="pdacc__body">
                        <?php if ($reviewMsg !== null): ?>
                            <p class="pdrv__flash<?= $reviewOk ? '' : ' is-err' ?>"
                               role="<?= $reviewOk ? 'status' : 'alert' ?>"><?= e($reviewMsg) ?></p>
                        <?php endif; ?>

                        <?php if ($reviews === []): ?>
                            <p class="pdacc__mute"><?= e(t('pd.reviews_empty')) ?></p>
                        <?php else: ?>
                            <div>
                                <div class="pdrv__score">
                                    <span class="pdrv__num"><?= e(number_format($rating, 1)) ?></span>
                                    <span class="pdrv__stars" aria-label="<?= e(number_format($rating, 1)) ?> trên 5 sao"><?= $stars($rating) ?></span>
                                </div>
                                <p class="pdacc__mute"><?= e(t('pd.reviews_based', [':n' => $reviewN])) ?></p>
                            </div>

                            <div class="pdrv__list">
                                <?php foreach ($reviews as $rv): ?>
                                    <article class="pdrv">
                                        <div class="pdrv__top">
                                            <span><?= e($rv['author_name']) ?></span>
                                            <span class="pdrv__stars" aria-label="<?= (int) $rv['rating'] ?> trên 5 sao"><?= $stars((float) $rv['rating']) ?></span>
                                        </div>
                                        <p class="pdrv__text"><?= e($rv['body']) ?></p>
                                        <p class="pdacc__mute">
                                            <?php
                                            /* "Đã mua · Đen · 08/2026" — "Đã mua" chỉ khi
                                               đánh giá gắn với một đơn thật. */
                                            $bits = [];
                                            if ($rv['order_id'] !== null)     { $bits[] = 'Đã mua'; }
                                            if (!empty($rv['variant_label'])) { $bits[] = $rv['variant_label']; }
                                            $bits[] = formatDate($rv['created_at'], 'm/Y');
                                            echo e(implode(' · ', $bits));
                                            ?>
                                        </p>

                                        <?php /* Phản hồi của cửa hàng (cột `reply`, từ
                                                 migration 2026-08-28). isset lẫn chuỗi
                                                 rỗng: máy chưa chạy migration thì khoá
                                                 không tồn tại. */ ?>
                                        <?php if (trim((string) ($rv['reply'] ?? '')) !== ''): ?>
                                            <div class="pdreply">
                                                <p class="pdreply__from">
                                                    Phản hồi của <?= e(config('company.short_name', 'cửa hàng')) ?>
                                                    <?php if (!empty($rv['replied_at'])): ?>
                                                        <span class="pdacc__mute"><?= e(formatDate($rv['replied_at'])) ?></span>
                                                    <?php endif; ?>
                                                </p>
                                                <p><?= e($rv['reply']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$showAll && $reviewN > count($reviews)): ?>
                            <a class="pdlink" href="/san-pham/<?= $slug ?>?danh-gia=tat-ca#danh-gia">
                                <?= e(t('pd.reviews_all')) ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($canReview['ok']): ?>
                            <?php /* "VIẾT ĐÁNH GIÁ" của mẫu là một dòng gạch chân; bấm
                                     vào mở form ngay tại chỗ. Gửi hỏng thì mở sẵn để
                                     khách sửa luôn. */ ?>
                            <details class="pdrv__write" <?= $reviewMsg !== null && !$reviewOk ? 'open' : '' ?>>
                                <summary class="pdlink"><?= e(t('pd.write_link')) ?></summary>

                                <form class="pdwrite" method="post" action="/san-pham/danh-gia">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">

                                    <!-- Năm ô radio xếp NGƯỢC (5 → 1) rồi lật lại bằng
                                         flex-direction: row-reverse. Nhờ vậy "tô mọi sao
                                         bên trái sao đang chọn" làm được bằng bộ chọn ~,
                                         vốn chỉ nhìn về phía SAU trong cây DOM. -->
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
                                    <?php /* maxlength đọc từ ReviewModel::BODY_MAX — máy
                                             chủ là chốt cuối, hai con số ở hai nơi là
                                             ngày chúng lệch nhau. */ ?>
                                    <textarea class="pdwrite__area" id="noi-dung" name="body" rows="3"
                                              required minlength="10" maxlength="<?= ReviewModel::BODY_MAX ?>"
                                              placeholder="<?= e(t('pd.comment_ph')) ?>"></textarea>
                                    <span class="pdacc__mute"><?= e(t('pd.comment_max', [':n' => ReviewModel::BODY_MAX])) ?></span>

                                    <button type="submit" class="pdwrite__send"><?= e(t('pd.send')) ?></button>
                                    <span class="pdacc__mute"><?= e(t('pd.moderated')) ?></span>
                                </form>
                            </details>
                        <?php elseif (!empty($canReview['reason'])): ?>
                            <p class="pdacc__mute"><?= e($canReview['reason']) ?></p>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </aside>
    </div>

    <?php
    /*
     * ══════════ CAMPAIGN — BỘ SƯU TẬP CỦA MẶT HÀNG ══════════
     *
     * Khối "Campaign" của mẫu là năm ảnh chiến dịch + một đoạn giới thiệu +
     * "Discover more". Nguồn thật là bộ sưu tập mà mặt hàng thuộc về: ảnh của
     * bộ (CollectionModel::images), đoạn `intro` (trống thì `tagline`), và
     * liên kết sang trang bộ đó.
     *
     * Mặt hàng không thuộc bộ nào, hoặc bộ chưa có ảnh -> KHÔNG vẽ khối. Mẫu
     * cũng có công tắc showCampaign cho đúng trường hợp này.
     */
    /* Cùng bộ lọc với thư viện ảnh, TRỪ 'hero-': ảnh bìa chiến dịch là đúng
       vai ở khối này. Ảnh nội thất cửa hàng thì không — tấm showroom-frames.jpg
       còn đọc được biển hiệu của một thương hiệu khác. */
    $campImgs = $collection === null ? [] : array_slice(array_values(array_filter(
        CollectionModel::images($collection),
        static function ($duongDan) use ($anhKhongPhaiSanPham): bool {
            $ten = basename(parse_url((string) $duongDan, PHP_URL_PATH) ?? '');

            foreach (array_diff($anhKhongPhaiSanPham, ['hero-']) as $dauHieu) {
                if (str_starts_with($ten, $dauHieu)) {
                    return false;
                }
            }

            return $ten !== '';
        }
    )), 0, 5);
    $campText = $collection !== null
        ? (trim((string) ($collection['intro'] ?? '')) ?: trim((string) ($collection['tagline'] ?? '')))
        : '';
    ?>
    <?php if ($campImgs !== []): ?>
        <section class="pdcamp" aria-labelledby="pdcamp-title">
            <div class="pdcamp__grid">
                <?php foreach ($campImgs as $anh): ?>
                    <figure class="pdcamp__cell">
                        <?php /* Ảnh CHIẾN DỊCH thì cắt tràn khung (cover) — khác ảnh
                                 sản phẩm luôn phải thấy trọn gọng. */ ?>
                        <img src="<?= e(asset($anh)) ?>" alt="" width="600" height="800" loading="lazy" decoding="async">
                    </figure>
                <?php endforeach; ?>
            </div>

            <div class="pdcamp__text">
                <h2 class="pdsec__title" id="pdcamp-title"><?= e($collection['name']) ?></h2>
                <?php if ($campText !== ''): ?>
                    <p class="pdcamp__lead"><?= e($campText) ?></p>
                <?php endif; ?>
                <a class="pdlink" href="/bo-suu-tap/<?= e(rawurlencode((string) $collection['slug'])) ?>">
                    <?= e(t('pd.campaign_more')) ?>
                </a>
            </div>
        </section>
    <?php endif; ?>

    <?php
    /*
     * ══════════ SẢN PHẨM TƯƠNG TỰ ══════════
     *
     * THẺ RIÊNG của trang này, KHÔNG dùng _layout/product-card.php: mẫu vẽ một
     * thẻ tối giản (ảnh 1:1 · tên · giá · dấu trang), không huy hiệu, không ô
     * màu, không hai nút mua nổi trên ảnh. Ép thẻ dùng chung vào hình này là
     * thêm một nhánh cờ vào một thẻ đang phục vụ năm trang khác.
     */
    ?>
    <?php if ($related !== []): ?>
        <section class="pdsim" aria-labelledby="pdsim-title">
            <h2 class="pdsec__title" id="pdsim-title"><?= e(t('pd.similar')) ?></h2>

            <ul class="pdsim__grid" role="list">
                <?php foreach ($related as $item): ?>
                    <?php
                    $urlSp   = '/san-pham/' . rawurlencode($item['slug']);
                    $giaSp   = ProductPricing::giaBan($item);
                    $gachSp  = ProductPricing::giaGach($item);
                    ?>
                    <li class="pdsim__card">
                        <?php /* Ảnh là liên kết TRÙNG ĐÍCH với tên bên dưới — ẩn
                                 khỏi trình đọc màn hình và khỏi phím Tab. */ ?>
                        <a class="pdsim__media" href="<?= e($urlSp) ?>" aria-hidden="true" tabindex="-1">
                            <?php if (ProductModel::hasImage($item)): ?>
                                <img src="<?= e(asset(ProductModel::image($item))) ?>" alt=""
                                     width="600" height="600" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="pdgal__empty"><?= e(t('product.no_image')) ?></span>
                            <?php endif; ?>
                        </a>

                        <div class="pdsim__info">
                            <div>
                                <a class="pdsim__name notranslate" translate="no" lang="vi" href="<?= e($urlSp) ?>"><?= e($item['name']) ?></a>
                                <p class="pdsim__price">
                                    <span><?= money($giaSp) ?></span>
                                    <?php if ($gachSp !== null && $gachSp > $giaSp): ?>
                                        <s><span class="sr-only"><?= e(t('product.was_label')) ?> </span><?= money($gachSp) ?></s>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <?php if (!empty($luuDuoc)): ?>
                                <?= $nutLuu((string) $item['slug'], (string) $item['name'], !empty($relatedSaved[$item['id']]), 13) ?>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
