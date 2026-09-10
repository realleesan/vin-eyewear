<?php

/**
 * _layout/product-card.php — thẻ sản phẩm DỌC, dùng chung cho cả site.
 *
 * Dựng theo "Vin Eyewear Home.dc.html". Bản thiết kế vẽ ĐÚNG MỘT dáng thẻ và
 * dùng lại ở mọi lưới sản phẩm. NĂM nơi gọi tới nó:
 *
 *   _layout/home/new-arrivals.php   "Sản phẩm mới về"   (huy hiệu xanh, không giá gốc)
 *   _layout/home/best-sellers.php   "Sản phẩm bán chạy" (huy hiệu đỏ, có giá gốc)
 *   product/index.php               lưới danh mục /san-pham
 *   product/detail.php              "Sản phẩm liên quan"
 *   search/index.php                kết quả tìm kiếm
 *
 *   ảnh cao 300px (bấm vào ảnh sang trang chi tiết) + huy hiệu góc trái trên
 *   thương hiệu · tên · giá
 *   chân thẻ có đường kẻ: "Mua ngay" (đặc) + "Thêm vào giỏ" (viền)
 *
 * FILE NÀY TỪNG LÀ MỘT THỨ KHÁC HẲN. Bản cũ là thẻ Lovable (ảnh đổi khi rê
 * chuột, nút "Thử AR", bo góc lớn) và chỉ còn dùng ở dải "liên quan" — nên
 * cuộn xuống cuối trang chi tiết là gặp một dáng thẻ không giống trang nào
 * khác. Nay mọi lưới sản phẩm chung một file.
 *
 * ĐÃ GỘP _layout/product-tile.php VÀO ĐÂY. File đó là thẻ riêng của lưới danh
 * mục, dựng theo "Category.dc.html": cả thẻ là một liên kết, ảnh cao 220px,
 * không có nút nào. Hệ quả là cùng một sản phẩm hiện ra hai dáng khác nhau tuỳ
 * khách đi vào từ trang chủ hay từ danh mục — trong khi bốn chỗ còn lại đều đã
 * dùng thẻ này. Nay chỉ còn một dáng thẻ cho cả site.
 *
 * Nhận qua partial():
 *   $product      dòng sản phẩm ĐÃ qua ProductModel
 *   $badgeTone    'new' (xanh) hoặc 'sale' (đỏ) — quyết định màu huy hiệu
 *   $showCompare  true thì in thêm giá gốc gạch ngang bên cạnh giá bán
 *   $eager        true thì KHÔNG lazy-load ảnh (dành cho hàng thẻ đầu tiên)
 *
 * HAI NÚT Ở CHÂN THẺ ĐỀU LÀ FORM POST, không có nút nào là liên kết trá hình:
 * "Mua ngay" đi tiếp tới thanh toán, "Thêm vào giỏ" dừng ở giỏ — nhãn nút hứa
 * gì thì làm đúng thế.
 *
 * (Trước đây ô bên phải là liên kết "Chi tiết". Đã đổi theo yêu cầu. Đường sang
 * trang sản phẩm KHÔNG mất: tên sản phẩm và ô ảnh đều dẫn tới đó, và với mặt
 * hàng không mua thẳng được thì nút "Chi tiết" vẫn hiện ra — xem chân file.)
 */

$badgeTone   = $badgeTone   ?? 'sale';
$showCompare = $showCompare ?? true;
$eager       = $eager       ?? false;

$url     = '/san-pham/' . rawurlencode($product['slug']);
/* Giá qua ProductPricing: đang khuyến mãi có hạn thì $price là giá khuyến mãi
   và $compare là giá thường bị gạch. Không đọc thẳng hai cột nữa — xem khối
   "MỘT NƠI QUYẾT ĐỊNH GIÁ" ở app/services/ProductPricing.php. */
$price   = ProductPricing::giaBan($product);
$compare = ProductPricing::giaGach($product);
$percent = discount($price, $compare);
$inStock = ProductModel::inStock($product);

/*
 * Mua thẳng từ thẻ được không?
 *
 * Không, nếu hết hàng, hoặc nếu mặt hàng có phương án (chiết suất tròng, màu
 * gọng…): thẻ không có chỗ nào để chọn phương án, mà CartController::add() từ
 * chối một mặt hàng có phương án mà không kèm phương án nào.
 *
 * Tính MỘT lần ở đây vì chân thẻ hỏi tới hai lần: một lần để chọn dáng ô bên
 * trái, một lần để quyết định có in nút "Chi tiết" hay không.
 */
$canBuyNow = $inStock && !VariantModel::hasVariants($product['id']);

/*
 * Huy hiệu. Một thứ tự ưu tiên cho cả năm chỗ gọi, để một sản phẩm không mang
 * hai nhãn khác nhau ở hai trang:
 * hết hàng > giảm giá > nhãn riêng của khối.
 *
 * Khối "mới về" truyền $badgeTone = 'new' và không có giá gốc, nên rơi xuống
 * nhánh cuối và nhận chữ "Mới".
 */
$badge = null;
$tone  = $badgeTone;

if (!$inStock) {
    $badge = t('product.out_of_stock');
    $tone  = 'out';
} elseif ($percent !== null && $showCompare) {
    $badge = '-' . $percent . '%';
    $tone  = 'sale';
} elseif ($badgeTone === 'new') {
    $badge = t('product.badge_new');
} elseif (!empty($product['is_featured'])) {
    $badge = t('product.badge_hot');
}
?>

<li class="pcard">
    <div class="pcard__media">
        <?php /* Ô ẢNH CŨNG LÀ ĐƯỜNG SANG TRANG CHI TIẾT: khách bấm vào ảnh theo
                 phản xạ chứ không đi tìm nút "Chi tiết" ở chân thẻ.

                 Liên kết chỉ phủ ô ảnh, KHÔNG phủ cả thẻ: chân thẻ có form
                 "Mua ngay", mà lồng <button> vào trong <a> là HTML sai và bấm
                 nút sẽ hoá thành đi theo liên kết.

                 aria-hidden + tabindex="-1" vì đây là liên kết TRÙNG đích với
                 tên sản phẩm ngay bên dưới. Ảnh mang alt="" (tên đã có ở tiêu
                 đề, đọc lại là đọc hai lần) nên nếu để trình đọc màn hình thấy,
                 nó chỉ đọc được một "liên kết" trơ trọi không tên; còn để nhận
                 tiêu điểm thì người dùng bàn phím phải Tab qua bốn liên kết một
                 thẻ thay vì ba. Huy hiệu nằm NGOÀI liên kết nên vẫn đọc được.

                 Bàn phím và trình đọc màn hình không mất gì: tên sản phẩm và nút
                 "Chi tiết" vẫn là hai đường tới đúng trang này. */ ?>
        <a class="pcard__shot" href="<?= e($url) ?>" aria-hidden="true" tabindex="-1">
            <?php
            /*
             * ─────────────────────────────────────────────────────────────────
             * asset() BỌC NGOÀI — ĐỂ MỘT TẤM ẢNH CHỈ CÓ MỘT ĐỊA CHỈ
             *
             * asset() gắn `?v=filemtime` để phá cache. Các partial của trang chủ
             * (hero, danh mục, khối dịch vụ) vốn đã đi qua nó, còn đường dẫn lấy
             * từ CSDL thì trước đây in thẳng.
             *
             * Hệ quả đo được trên bản live: cùng một file có mặt trên trang dưới
             * HAI địa chỉ —
             *     /assets/images/product-1.jpg
             *     /assets/images/product-1.jpg?v=1787493435
             * — và trình duyệt coi đó là hai tài nguyên khác nhau nên tải cả hai.
             * Riêng trang chủ, 40 thẻ <img> phân giải thành 17 URL, trong đó 6
             * cặp là cùng một file: khoảng 460KB tải thừa mỗi lượt vào trang.
             *
             * Bọc ở TẦNG VIEW chứ không sửa ProductModel::image(): model trả về
             * dữ liệu, việc gắn chuỗi phá cache là chuyện của lúc in ra HTML.
             *
             * An toàn với ảnh ngoài miền: asset() kiểm is_file() trước, không
             * thấy file thì trả nguyên đường dẫn, không gắn gì.
             * ─────────────────────────────────────────────────────────────────
             */
            ?>
            <?php if (ProductModel::hasImage($product)): ?>
                <img src="<?= e(asset(ProductModel::image($product))) ?>" alt=""
                     width="600" height="600"
                     <?= $eager ? '' : 'loading="lazy"' ?> decoding="async">

                <?php
                /*
                 * ─────────────────────────────────────────────────────────────
                 * ẢNH THỨ HAI — HIỆN KHI RÊ CHUỘT
                 *
                 * Nếp chuẩn của mọi trang bán kính và bán thời trang: ảnh nghỉ
                 * là chiếc kính chụp trên nền sạch, rê chuột vào thì đổi sang
                 * ảnh người đeo. Khách hình dung ngay dáng và cỡ kính lên mặt —
                 * thứ mà một tấm ảnh gọng nằm không bao giờ nói được, và là rào
                 * cản lớn nhất của việc mua kính trực tuyến.
                 *
                 * DÙNG DỮ LIỆU ĐÃ CÓ, KHÔNG THÊM CỘT NÀO: `images` vốn là mảng
                 * nhiều ảnh (trang chi tiết đã dựng cả thư viện từ nó). Ở đây
                 * chỉ lấy thêm phần tử [1]. Mặt hàng chỉ có một ảnh thì không in
                 * gì cả và thẻ giữ nguyên hành vi cũ.
                 *
                 * KHÔNG MỘT DÒNG JAVASCRIPT: hai ảnh chồng lên nhau, CSS đổi
                 * opacity khi :hover / :focus-within — xem .pcard__alt trong
                 * components/product.css. Trên thiết bị chạm nó không bao giờ
                 * hiện, nên cũng không tốn gì.
                 *
                 * LUÔN `loading="lazy"`, kể cả khi $eager: ảnh này không bao giờ
                 * nằm trong màn hình đầu tiên ở trạng thái nghỉ, nên tải sớm nó
                 * là lấy băng thông của đúng tấm ảnh khách đang chờ.
                 * ─────────────────────────────────────────────────────────────
                 */
                $anhPhu = $product['images'][1] ?? '';

                /*
                 * LỌC ẢNH KHÔNG PHẢI ẢNH SẢN PHẨM — cùng luật với thư viện ở
                 * product/detail.php.
                 *
                 * Trên dữ liệu đang chạy, ảnh thứ hai của hai mặt hàng là ảnh
                 * nội thất cửa hàng (kèm biển hiệu một thương hiệu khác) và
                 * ảnh bìa chiến dịch. Ở thư viện thì khách phải bấm mới thấy;
                 * ở ĐÂY nó bung ra chỉ vì con trỏ đi ngang qua thẻ — tức là
                 * còn dễ gặp hơn.
                 *
                 * Ảnh cửa hàng, ảnh nội thất và ảnh bìa không bao giờ là ảnh
                 * sản phẩm, dù gắn cho mặt hàng nào. Loại theo VAI TRÒ, không
                 * đoán "có đúng mặt hàng không".
                 *
                 * Không còn ảnh thứ hai hợp lệ thì thẻ đơn giản không có hiệu
                 * ứng đổi ảnh — đúng hành vi của mặt hàng chỉ có một ảnh.
                 */
                foreach (['showroom-', 'store-interior', 'hero-'] as $dauHieu) {
                    if (str_starts_with(basename((string) $anhPhu), $dauHieu)) {
                        $anhPhu = '';
                        break;
                    }
                }
                ?>
                <?php if ($anhPhu !== ''): ?>
                    <img class="pcard__alt" src="<?= e(asset($anhPhu)) ?>" alt=""
                         width="600" height="600"
                         loading="lazy" decoding="async">
                <?php endif; ?>
            <?php else: ?>
                <?php /* Ô trống thật thà, không mượn ảnh của mặt hàng khác —
                         xem chú thích ở ProductModel::hasImage(). */ ?>
                <span class="pcard__noimg"><?= e(t('product.no_image')) ?></span>
            <?php endif; ?>
        </a>

        <?php if ($badge !== null): ?>
            <span class="pcard__badge pcard__badge--<?= e($tone) ?>"><?= e($badge) ?></span>
        <?php endif; ?>
    </div>

    <div class="pcard__body">
        <p class="pcard__brand"><?= e($product['brand'] ?? 'Vin Eyewear') ?></p>

        <?php
        /*
         * ─────────────────────────────────────────────────────────────────────
         * TÊN SẢN PHẨM KHÔNG ĐƯỢC DỊCH — quy ước của cả site, ghi ở đây vì đây
         * là chỗ in tên sản phẩm được dùng lại nhiều nhất (trang chủ, danh mục,
         * tìm kiếm, bộ sưu tập đều nạp file này).
         *
         * Grep 'notranslate' trong app/views/ ra đủ 14 chỗ. Thêm một view in
         * tên sản phẩm mới thì thêm cả hai thuộc tính dưới đây vào.
         *
         * VÌ SAO: "Vin T01 Titan" dịch sang tiếng Anh không ra thứ gì tốt hơn,
         * mà lại ra thứ khách đọc xong không tìm thấy trên hoá đơn, trong tin
         * nhắn của cửa hàng, hay khi gọi điện hỏi. Tên riêng để nguyên là đúng
         * ở mọi ngôn ngữ.
         *
         * HAI CÁCH ĐÁNH DẤU VÌ HAI BÊN ĐỌC KHÁC NHAU, không phải viết thừa:
         *   translate="no"        thuộc tính chuẩn HTML — trình dịch cài sẵn
         *                         trong Chrome/Safari đọc cái này, và đó là
         *                         thứ ĐANG có tác dụng thật ngay lúc này
         *   class="notranslate"   quy ước của các công cụ dịch bên ngoài. Chưa
         *                         ai dùng tới, giữ vì nó là tên lớp mà mọi thứ
         *                         từ Google Translate trở đi đều hiểu.
         *
         * TÊN TRÒNG KÍNH THÌ NGƯỢC LẠI — CỐ Ý ĐỂ DỊCH. "Tròng trắng 1.50",
         * "Chống sáng xanh 1.61" là câu mô tả chứ không phải tên riêng; người
         * đọc bằng ngôn ngữ khác cần hiểu mới chọn đúng được.
         * ─────────────────────────────────────────────────────────────────────
         */
        ?>
        <?php
        /*
         * ─────────────────────────────────────────────────────────────────────
         * lang="vi" — BA THUỘC TÍNH, BA NGƯỜI ĐỌC KHÁC NHAU
         *
         *   translate="no"       trình dịch cài sẵn của trình duyệt
         *   class="notranslate"  công cụ dịch bên ngoài
         *   lang="vi"            TRÌNH ĐỌC MÀN HÌNH  ← thêm ở đợt này
         *
         * Khung trang in ra `<html lang="en">` (mặc định English-first), nhưng
         * tên sản phẩm trong CSDL chỉ có một ngôn ngữ là tiếng Việt. Thiếu dòng
         * này thì trình đọc màn hình phát âm "Gọng titan siêu nhẹ" bằng bộ quy
         * tắc tiếng Anh — ra một chuỗi âm không ai hiểu.
         *
         * Khai VÔ ĐIỀU KIỆN chứ không kèm `if (currentLang() !== 'vi')`: nội
         * dung này là tiếng Việt ở CẢ HAI bản, nên nhãn ngôn ngữ đúng ở cả hai.
         * Khi trang đang là lang="vi" thì dòng này chỉ thừa, không sai.
         *
         * Đây chính là khoản nợ mà khối chú thích ở _layout/master.php đã ghi.
         * ─────────────────────────────────────────────────────────────────────
         */
        ?>
        <h3 class="pcard__name notranslate" translate="no" lang="vi">
            <a href="<?= e($url) ?>"><?= e($product['name']) ?></a>
        </h3>

        <p class="pcard__prices">
            <span class="sr-only"><?= e(t('product.price_label')) ?> </span>
            <span class="pcard__price"><?= money($price) ?></span>

            <?php /* Giá gốc đứng SAU trong DOM (trình đọc màn hình nghe giá thật
                     trước) nhưng hiện ra TRƯỚC, đúng thứ tự của thẻ Furnish —
                     xem `.pcard__was { order: -1 }` trong components/product.css. */ ?>
            <?php if ($showCompare && $compare !== null && $compare > $price): ?>
                <span class="pcard__was">
                    <span class="sr-only"><?= e(t('product.was_label')) ?> </span><?= money($compare) ?>
                </span>
            <?php endif; ?>
        </p>

        <?php
        /*
         * ─────────────────────────────────────────────────────────────────────
         * Ô MÀU — hàng chấm màu dưới giá, đúng lối thẻ sản phẩm của Gentle
         * Monster.
         *
         * NÓ TRẢ LỜI MỘT CÂU HỎI THẬT: cùng một dáng gọng thường có ba, bốn
         * phối màu, và màu là thứ quyết định mua hay không nhiều hơn cả dáng.
         * Không có hàng này thì khách phải mở từng mẫu ra mới biết mẫu nào có
         * màu mình muốn — tức là phải đoán.
         *
         * MỖI Ô LÀ MỘT <a> THẬT trỏ sang trang chi tiết, không phải một <span>
         * trang trí và cũng không phải một nút JavaScript:
         *   · chỗ CHỌN màu thật sự là trang chi tiết (ở đó có ô chọn phương án,
         *     giá theo phương án và nút mua) — thẻ này không có chỗ cho ngần ấy
         *   · tắt JavaScript vẫn đi được, mở tab mới được, máy tìm kiếm đi theo
         *     được
         *
         * PHẦN "XEM TRƯỚC" nằm ở assets/js/pcard-swatch.js: rê chuột (hoặc Tab)
         * vào một ô có ảnh riêng thì ảnh của thẻ đổi sang đúng phối màu ấy. Đó
         * là TĂNG CƯỜNG — không có JS thì ô vẫn là một liên kết đúng.
         *
         * data-swatch-img có thể RỖNG: cửa hàng mới gắn ảnh cho một phần biến
         * thể. Ô không có ảnh thì không đổi gì, và đó là hành vi đúng — thà
         * không xem trước còn hơn xem trước sai màu.
         *
         * KHÔNG THÊM MỘT TRUY VẤN NÀO CHO MỖI THẺ: VariantModel::swatchMap()
         * hỏi cả bảng đúng một lần rồi nhớ suốt request, cùng lối với
         * productIdsWithVariants() mà $canBuyNow phía trên đang dùng.
         * ─────────────────────────────────────────────────────────────────────
         */
        $swatches = VariantModel::swatches((string) $product['id']);
        ?>
        <?php if ($swatches !== []): ?>
            <ul class="pcard__sw" role="list">
                <?php foreach ($swatches as $sw): ?>
                    <li>
                        <?php /* aria-label mang CẢ tên màu lẫn tên mẫu: người
                                 dùng trình đọc màn hình nghe danh sách liên kết
                                 sẽ chỉ nghe được nhãn, mà "Bạc mờ" một mình
                                 không nói được nó thuộc chiếc kính nào. */ ?>
                        <a class="pcard__swatch"
                           href="<?= e($url) ?>"
                           style="--sw: <?= e($sw['hex']) ?>"
                           data-swatch-img="<?= $sw['anh'] === '' ? '' : e(asset($sw['anh'])) ?>"
                           aria-label="<?= e($sw['ten']) ?> — <?= e($product['name']) ?>"></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="pcard__actions">
        <?php if (!$inStock): ?>
            <span class="pcard__btn pcard__btn--solid is-off" aria-disabled="true">
                <?= e(t('product.out_of_stock')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
            </span>

        <?php elseif (!$canBuyNow): ?>
            <?php /* MẶT HÀNG CÓ PHƯƠNG ÁN (chiết suất tròng, màu gọng…) thì
                     KHÔNG mua được từ thẻ này: thẻ không có chỗ nào để chọn,
                     và CartController::add() từ chối một mặt hàng có phương án
                     mà không kèm phương án nào.

                     Trước đây chỗ này vẫn vẽ nút "Mua ngay". Bấm vào là bị đá
                     sang trang chi tiết kèm một dòng báo lỗi — trông y như
                     trang bị hỏng. Nhãn nay nói đúng việc sẽ xảy ra. */ ?>
            <a class="pcard__btn pcard__btn--solid" href="<?= e($url) ?>">
                <?= e(t('product.choose_option')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
            </a>

        <?php else: ?>
            <form action="/gio-hang/them" method="post">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                <?php /* Nơi quay về sau khi chọn hình thức mua. Gọng và kính mát
                         không vào thẳng giỏ — chúng mở hộp thoại "Chọn hình thức
                         mua" ngay trên trang này, và hộp thoại cần biết "trang
                         này" là trang nào. Xem CartController::add(). */ ?>
                <input type="hidden" name="back" value="<?= e(currentUrlWithout(['mua', 'buoc'])) ?>">
                <?php /* HAI NÚT, MỘT FORM. Trình duyệt chỉ gửi name/value của
                         ĐÚNG nút được bấm, nên không cần hai form:

                           "Mua ngay"     -> action=buy  -> CartController::add()
                                             đưa khách tiếp tới /thanh-toan
                           "Thêm vào giỏ" -> không gửi `action` nào -> add() hiểu
                                             là 'add' và dừng ở giỏ hàng

                         Nút thứ hai cố ý KHÔNG mang name/value, đúng cách trang
                         chi tiết đang làm (.pdbtn--buy / .pdbtn--add trong
                         product/detail.php) — hai chỗ cùng một quy ước thì đọc
                         add() một lần là hiểu cả hai. */ ?>
                <button type="submit" name="action" value="buy" class="pcard__btn pcard__btn--solid">
                    <?= e(t('product.buy_now')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
                </button>

                <button type="submit" class="pcard__btn pcard__btn--ghost">
                    <?= e(t('product.add_to_cart')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
                </button>
            </form>
        <?php endif; ?>

        <?php /* "Chi tiết" CHỈ còn cho mặt hàng không mua thẳng được từ thẻ (hết
                 hàng, hoặc phải chọn phương án): ô bên trái của chúng không phải
                 nút mua, nên chân thẻ cần một đường đi thật. Mặt hàng mua thẳng
                 được thì hai ô đã là hai nút mua, và đường sang trang chi tiết
                 nằm ở tên sản phẩm cùng ô ảnh phía trên. */ ?>
        <?php if (!$canBuyNow): ?>
            <a class="pcard__btn pcard__btn--ghost" href="<?= e($url) ?>">
                <?= e(t('product.details')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
            </a>
        <?php endif; ?>
    </div>
</li>
