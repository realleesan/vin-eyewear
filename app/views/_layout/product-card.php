<?php
/*
 * ═══════════════════════════════════════════════════════════════════════════
 * THẺ SẢN PHẨM — dựng 1:1 từ mẫu "Eyewear Collection"
 *
 * Dùng chung cho MỌI lưới hàng: trang chủ, /san-pham, /bo-suu-tap/{slug},
 * /tim-kiem, khối "sản phẩm tương tự" ở trang chi tiết. Một thẻ, một chỗ sửa.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * HÌNH CỦA MẪU, từ trên xuống:
 *
 *   ┌─────────────────────────┐
 *   │ [MỚI] [-30%]            │  huy hiệu góc trên trái, nằm NGOÀI liên kết
 *   │                         │
 *   │      ảnh 4:5 contain    │  nền thẻ là một trong bốn tông xám luân phiên
 *   │                         │
 *   ├─────────────────────────┤
 *   │ —                    ⚑  │  dòng nhãn hiệu (mờ) · dấu trang góc phải
 *   │ Tên sản phẩm            │
 *   │ 8.446.300₫              │  hoặc  8̶.̶9̶0̶0̶.̶0̶0̶0̶  6.230.000₫  (đỏ)
 *   │ ● ● ●   Đen             │  ô màu + tên màu đang chọn
 *   ├─────────────────────────┤
 *   │ [ Mua ngay ][Thêm vào..]│  hai nút viên chia đôi, chữ 9px
 *   └─────────────────────────┘
 *
 * ẢNH `contain` CHỨ KHÔNG `cover`, và đó là điểm mẫu phân biệt rất rõ: ảnh
 * SẢN PHẨM phải thấy trọn cái gọng, ảnh CHIẾN DỊCH mới cắt tràn khung.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * THAM SỐ
 *
 *   $product      bắt buộc — một dòng từ ProductModel
 *   $i            chỉ số trong lưới, quyết định tông nền (mặc định 0)
 *   $variants     mảng biến thể của MẶT HÀNG NÀY, để vẽ ô màu. Không truyền
 *                 thì hàng ô màu không hiện — thẻ vẫn đúng, chỉ thiếu một
 *                 dòng. Xem khối "Ô MÀU" bên dưới về cách lấy rẻ.
 *   $badgeTone    'sale' (mặc định) | 'new' — quyết định huy hiệu nào hiện
 *                 khi mặt hàng KHÔNG giảm giá
 *   $showCompare  false để giấu giá gạch (dùng ở khối gợi ý)
 *   $eager        true cho thẻ nằm trong màn hình đầu — bỏ loading="lazy"
 * ═══════════════════════════════════════════════════════════════════════════
 */

$badgeTone   = $badgeTone   ?? 'sale';
$showCompare = $showCompare ?? true;
$eager       = $eager       ?? false;
$i           = $i           ?? 0;
$variants    = $variants    ?? [];

$url = '/san-pham/' . rawurlencode($product['slug']);

/* MỘT NƠI QUYẾT ĐỊNH GIÁ — không đọc thẳng hai cột `price`/`compare_price`.
   Mặt hàng đang khuyến mãi có hạn thì giá bán là giá khuyến mãi, và cả giỏ
   hàng lẫn lúc tạo đơn đều đi qua đúng hai hàm này. Xem
   app/services/ProductPricing.php. */
$price   = ProductPricing::giaBan($product);
$compare = ProductPricing::giaGach($product);
$percent = discount($price, $compare);

$inStock   = ProductModel::inStock($product);
$hasOption = VariantModel::hasVariants($product['id']);
$canBuyNow = $inStock && !$hasOption;

/* ┌─ TÔNG NỀN LUÂN PHIÊN ─────────────────────────────────────────────────
   │ Mẫu đổi tông theo chỉ số thẻ để lưới không thành một mảng xám phẳng.
   │ Công thức của mẫu: `(i + floor(i/4)) % 4` — nó dịch pha mỗi khi xuống
   │ một hàng, nên hai thẻ nằm ngay trên/dưới nhau không bao giờ cùng tông.
   │ Chép nguyên, đừng rút gọn thành `i % 4`: rút gọn là mất đúng hiệu ứng đó.
   └──────────────────────────────────────────────────────────────────────── */
$tone = (((int) $i + intdiv((int) $i, 4)) % 4) + 1;

/* ┌─ Ô MÀU ───────────────────────────────────────────────────────────────
   │ Chỉ vẽ từ biến thể CÓ mã màu thật (`swatch_hex`). Phương án chiết suất
   │ tròng hay cỡ gọng để cột ấy NULL, và một ô màu trống thì vô nghĩa.
   │
   │ Ô ĐẦU TIÊN LÀ Ô ĐANG CHỌN. Ở lưới thì ô màu chỉ là xem trước, không đổi
   │ được gì — nên chúng là <a> dẫn sang trang chi tiết kèm ?bien-the=, chứ
   │ không phải <button> giả vờ bấm được rồi không xảy ra gì.
   └──────────────────────────────────────────────────────────────────────── */
$swatches = [];

foreach ($variants as $v) {
    if (!empty($v['swatch_hex'])) {
        $swatches[] = $v;
    }
}
?>
<li class="oa-card oa-card--t<?= $tone ?>">

    <div class="oa-card__media">
        <?php
        /* Ô ẢNH CŨNG LÀ ĐƯỜNG SANG TRANG CHI TIẾT — khách bấm vào ảnh theo phản
           xạ chứ không đi tìm nút.

           Liên kết chỉ phủ ô ảnh, KHÔNG phủ cả thẻ: chân thẻ có form "Mua
           ngay", mà lồng <button> vào trong <a> là HTML sai và bấm nút sẽ hoá
           thành đi theo liên kết.

           aria-hidden + tabindex="-1" vì đây là liên kết TRÙNG ĐÍCH với tên
           sản phẩm ngay bên dưới. Ảnh mang alt="" (tên đã có ở tiêu đề, đọc
           lại là đọc hai lần) nên nếu để trình đọc màn hình thấy, nó chỉ đọc
           được một "liên kết" trơ trọi không tên; còn để nhận tiêu điểm thì
           người dùng bàn phím phải Tab qua bốn liên kết một thẻ thay vì ba.
           Huy hiệu nằm NGOÀI liên kết nên vẫn đọc được. */
        ?>
        <a class="oa-slot oa-slot--contain" href="<?= e($url) ?>" aria-hidden="true" tabindex="-1"
           style="position:absolute;inset:0">
            <?php if (ProductModel::hasImage($product)): ?>
                <img src="<?= e(asset(ProductModel::image($product))) ?>" alt=""
                     width="600" height="750"
                     <?= $eager ? '' : 'loading="lazy"' ?> decoding="async">
            <?php else: ?>
                <?php /* Ô trống thật thà, không mượn ảnh của mặt hàng khác —
                         xem chú thích ở ProductModel::hasImage(). */ ?>
                <span class="oa-slot__ph"><?= e(t('product.no_image')) ?></span>
            <?php endif; ?>
        </a>

        <?php
        /* ┌─ HUY HIỆU ────────────────────────────────────────────────────
           │ Mẫu cho phép tối đa hai huy hiệu cạnh nhau: đen "MỚI" rồi đỏ
           │ "-30%". Hết hàng thì nuốt cả hai — lúc đó điều duy nhất đáng
           │ nói về mặt hàng này là nó không mua được.
           └──────────────────────────────────────────────────────────────── */
        ?>
        <div class="oa-badges">
            <?php if (!$inStock): ?>
                <span class="oa-badge"><?= e(t('product.out_of_stock')) ?></span>
            <?php else: ?>
                <?php if ($badgeTone === 'new' || !empty($product['is_featured'])): ?>
                    <span class="oa-badge"><?= e($badgeTone === 'new' ? t('product.badge_new') : t('product.badge_hot')) ?></span>
                <?php endif; ?>
                <?php if ($percent !== null && $showCompare): ?>
                    <span class="oa-badge oa-badge--sale">-<?= (int) $percent ?>%</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="oa-card__body">
        <div class="oa-card__info">

            <span class="oa-card__kicker"><?= e($product['brand'] ?? 'Vin Eyewear') ?></span>

            <?php
            /* notranslate / translate="no" / lang="vi" — tên sản phẩm là danh
               từ riêng và phải giữ nguyên trên cả bản tiếng Anh của giao diện.
               CSDL chỉ có một ngôn ngữ; xem khối chú thích ở đầu master.php. */
            ?>
            <a class="oa-card__name notranslate" translate="no" lang="vi" href="<?= e($url) ?>"><?= e($product['name']) ?></a>

            <?php if ($showCompare && $compare !== null && $compare > $price): ?>
                <span class="oa-card__pricesale">
                    <span class="sr-only"><?= e(t('product.was_label')) ?> </span>
                    <s><?= money($compare) ?></s>
                    <span class="sr-only"><?= e(t('product.price_label')) ?> </span>
                    <b><?= money($price) ?></b>
                </span>
            <?php else: ?>
                <span class="oa-card__price">
                    <span class="sr-only"><?= e(t('product.price_label')) ?> </span><?= money($price) ?>
                </span>
            <?php endif; ?>

            <?php if (!$inStock): ?>
                <span class="oa-card__status"><?= e(t('product.out_of_stock')) ?></span>
            <?php endif; ?>

            <?php if ($swatches !== []): ?>
                <div class="oa-swatches">
                    <?php foreach (array_slice($swatches, 0, 5) as $k => $v): ?>
                        <a class="oa-swatch<?= $k === 0 ? ' is-active' : '' ?>"
                           href="<?= e($url) ?>?bien-the=<?= e(rawurlencode((string) $v['id'])) ?>"
                           title="<?= e($v['color'] ?? $v['label']) ?>"
                           style="background:<?= e($v['swatch_hex']) ?>">
                            <span class="sr-only"><?= e($v['color'] ?? $v['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <span class="oa-swatch__label"><?= e($swatches[0]['color'] ?? $swatches[0]['label']) ?></span>
                </div>
            <?php endif; ?>

        </div>

        <?php /* Dấu trang — trang trí thuần, chưa có chức năng yêu thích. Mang
                 aria-hidden vì nó không phải nút: vẽ một icon mà trình đọc màn
                 hình gọi là "hình ảnh" rồi không làm gì thì thà đừng đọc. */ ?>
        <svg class="oa-card__flag" width="12" height="14" viewBox="0 0 12 14" fill="none"
             stroke="currentColor" stroke-width="1.2" aria-hidden="true" focusable="false">
            <path d="M1 1h10v12L6 9.5 1 13V1z"/>
        </svg>
    </div>

    <div class="oa-card__actions">
        <?php if (!$inStock): ?>

            <span class="oa-btn oa-btn--card oa-btn--solid" aria-disabled="true">
                <?= e(t('product.out_of_stock')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
            </span>

        <?php elseif (!$canBuyNow): ?>

            <?php
            /* MẶT HÀNG CÓ PHƯƠNG ÁN (chiết suất tròng, màu gọng…) KHÔNG mua được
               từ thẻ này: thẻ không có chỗ nào để chọn, và CartController::add()
               từ chối một mặt hàng có phương án mà không kèm phương án nào.

               Trước đây chỗ này vẫn vẽ nút "Mua ngay". Bấm vào là bị đá sang
               trang chi tiết kèm một dòng báo lỗi — trông y như trang bị hỏng.
               Nhãn nay nói đúng việc sẽ xảy ra. */
            ?>
            <a class="oa-btn oa-btn--card oa-btn--solid" href="<?= e($url) ?>">
                <?= e(t('product.choose_option')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
            </a>
            <a class="oa-btn oa-btn--card" href="<?= e($url) ?>">
                <?= e(t('product.details')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
            </a>

        <?php else: ?>

            <form action="/gio-hang/them" method="post" style="display:contents">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                <?php /* Nơi quay về sau khi chọn hình thức mua. Gọng và kính mát
                         không vào thẳng giỏ — chúng mở hộp thoại "Chọn hình thức
                         mua" ngay trên trang này, và hộp thoại cần biết "trang
                         này" là trang nào. Xem CartController::add(). */ ?>
                <input type="hidden" name="back" value="<?= e(currentUrlWithout(['mua', 'buoc'])) ?>">

                <?php /* HAI NÚT, MỘT FORM. Trình duyệt chỉ gửi name/value của
                         ĐÚNG nút được bấm, nên không cần hai form:
                           "Mua ngay"     -> action=buy -> add() đưa tiếp tới
                                             /thanh-toan
                           "Thêm vào giỏ" -> không gửi `action` -> add() hiểu là
                                             'add' và dừng ở giỏ hàng
                         Nút thứ hai cố ý KHÔNG mang name/value, đúng quy ước mà
                         trang chi tiết cũng dùng. */ ?>
                <button type="submit" name="action" value="buy" class="oa-btn oa-btn--card oa-btn--solid">
                    <?= e(t('product.buy_now')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
                </button>
                <button type="submit" class="oa-btn oa-btn--card">
                    <?= e(t('product.add_to_cart')) ?><span class="sr-only"> — <?= e($product['name']) ?></span>
                </button>
            </form>

        <?php endif; ?>
    </div>
</li>
