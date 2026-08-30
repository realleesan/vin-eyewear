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
    $badge = 'Hết hàng';
    $tone  = 'out';
} elseif ($percent !== null && $showCompare) {
    $badge = '-' . $percent . '%';
    $tone  = 'sale';
} elseif ($badgeTone === 'new') {
    $badge = 'Mới';
} elseif (!empty($product['is_featured'])) {
    $badge = 'Bán chạy';
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
            <?php if (ProductModel::hasImage($product)): ?>
                <img src="<?= e(ProductModel::image($product)) ?>" alt=""
                     width="600" height="600"
                     <?= $eager ? '' : 'loading="lazy"' ?> decoding="async">
            <?php else: ?>
                <?php /* Ô trống thật thà, không mượn ảnh của mặt hàng khác —
                         xem chú thích ở ProductModel::hasImage(). */ ?>
                <span class="pcard__noimg">Chưa có ảnh</span>
            <?php endif; ?>
        </a>

        <?php if ($badge !== null): ?>
            <span class="pcard__badge pcard__badge--<?= e($tone) ?>"><?= e($badge) ?></span>
        <?php endif; ?>
    </div>

    <div class="pcard__body">
        <p class="pcard__brand"><?= e($product['brand'] ?? 'Vin Eyewear') ?></p>

        <h3 class="pcard__name notranslate" translate="no">
            <a href="<?= e($url) ?>"><?= e($product['name']) ?></a>
        </h3>

        <p class="pcard__prices">
            <span class="sr-only">Giá bán </span>
            <span class="pcard__price"><?= money($price) ?></span>

            <?php if ($showCompare && $compare !== null && $compare > $price): ?>
                <span class="pcard__was">
                    <span class="sr-only">Giá gốc </span><?= money($compare) ?>
                </span>
            <?php endif; ?>
        </p>
    </div>

    <div class="pcard__actions">
        <?php if (!$inStock): ?>
            <span class="pcard__btn pcard__btn--solid is-off" aria-disabled="true">
                Hết hàng<span class="sr-only"> — <?= e($product['name']) ?></span>
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
                Chọn phương án<span class="sr-only"> — <?= e($product['name']) ?></span>
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
                    Mua ngay<span class="sr-only"> — <?= e($product['name']) ?></span>
                </button>

                <button type="submit" class="pcard__btn pcard__btn--ghost">
                    Thêm vào giỏ<span class="sr-only"> — <?= e($product['name']) ?></span>
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
                Chi tiết<span class="sr-only"> — <?= e($product['name']) ?></span>
            </a>
        <?php endif; ?>
    </div>
</li>
