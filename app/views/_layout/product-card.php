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
 *   │ [MỚI] [-30%]            │  huy hiệu góc trên trái CỦA KHUNG ẢNH, nằm
 *   │                         │  NGOÀI liên kết
 *   │    ảnh 1:1,36 contain   │  KHÔNG nền — thẻ trong suốt, lấy nền trang
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
 * (gentlemonster.com dùng `cover` phóng 2x rồi cắt — cố ý KHÔNG chép, vì họ
 * bán dáng kính còn ta phải cho khách thấy trọn gọng.)
 *
 * CỠ THẺ lấy từ gentlemonster.com, đo trực tiếp — xem ba khối chú thích
 * .oa-grid / --gap-col / .oa-card__media trong assets/css/oa.css. Tóm tắt:
 * ba cột, khe 6%, khung ảnh khoá tỉ lệ 1/1,36, thẻ không có nền riêng.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * THAM SỐ
 *
 *   $product      bắt buộc — một dòng từ ProductModel
 *   $i            chỉ số trong lưới (mặc định 0). NAY KHÔNG AI ĐỌC — xem
 *                 khối "ĐÃ GỠ TÔNG NỀN LUÂN PHIÊN" bên dưới.
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

/* ┌─ ĐÃ GỠ TÔNG NỀN LUÂN PHIÊN (12/09/2026) ──────────────────────────────
   │ Ở đây từng tính `$tone = ((i + floor(i/4)) % 4) + 1` rồi in ra lớp
   │ .oa-card--t1…t4, cho lưới bốn tông xám xen kẽ theo mẫu.
   │
   │ Gỡ theo yêu cầu chủ dự án, và lý do là số học chứ không phải gu: bốn
   │ tông khác nhau thì một tấm ảnh sản phẩm chỉ khớp nền được với NHIỀU
   │ NHẤT một trong bốn ô, ba ô còn lại luôn lộ ra hình chữ nhật nền ảnh.
   │ Muốn ảnh "sạch" như gentlemonster.com thì nền thẻ phải là một màu, và
   │ đúng bằng màu trang — xem khối .oa-card trong assets/css/oa.css.
   │
   │ $i VẪN CÒN THAM SỐ và vẫn nhận được: nó là chỉ số trong lưới, nơi gọi
   │ đang truyền sẵn, và bỏ tham số đi thì phải sửa năm chỗ gọi cho một thứ
   │ chẳng ai được lợi. Chỉ là nay không ai đọc nó nữa.
   └──────────────────────────────────────────────────────────────────────── */

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
<li class="oa-card">

    <div class="oa-card__media">

        <?php
        /* ┌─ KHUNG ÔM SÁT TẤM ẢNH ────────────────────────────────────────
           │ Lớp bọc này KHÔNG phải để trang trí: nó cao đúng bằng tấm ảnh,
           │ và huy hiệu neo vào nó. Bỏ đi thì huy hiệu tụt về góc của KHUNG
           │ 1/1,36, nổi lơ lửng trên khoảng trắng phía trên ảnh vuông — xem
           │ khối chú thích .oa-card__frame trong assets/css/oa.css.
           │
           │ Không có ảnh thì khung không có gì để lấy chiều cao, nên thêm
           │ lớp --empty cho nó cao trọn ô giữ chỗ. */
        ?>
        <div class="oa-card__frame<?= ProductModel::hasImage($product) ? '' : ' oa-card__frame--empty' ?>">

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
            <a class="oa-slot oa-slot--contain" href="<?= e($url) ?>" aria-hidden="true" tabindex="-1">
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

            <?php
            /* ┌─ HAI NÚT MUA — NỔI TRÊN ẢNH, HIỆN KHI RÊ CHUỘT ───────────
               │ Nằm TRONG .oa-card__frame chứ không dưới chân thẻ nữa
               │ (12/09/2026, theo yêu cầu chủ dự án): khung này cao đúng
               │ bằng tấm ảnh, nên hai nút luôn nổi trong lòng ảnh, không
               │ bao giờ trôi xuống dải trắng dưới ảnh.
               │
               │ NGOÀI <a> ẢNH, và bắt buộc phải thế: lồng <button> vào
               │ trong <a> là HTML sai, bấm nút sẽ hoá thành đi theo liên
               │ kết. Cùng lý do với huy hiệu ở trên.
               │
               │ MÀN CẢM ỨNG THÌ HAI NÚT HIỆN SẴN — không có "rê chuột" để
               │ mở chúng ra. Xem @media (hover: hover) ở khối
               │ .oa-card__actions trong assets/css/oa.css. */
            ?>
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

        </div><?php /* .oa-card__frame */ ?>
    </div>

    <div class="oa-card__body">
        <div class="oa-card__info">

            <?php
            /* ĐÃ GỠ DÒNG NHÃN HIỆU (.oa-card__kicker) — 12/09/2026, theo yêu
               cầu chủ dự án. Nó in $product['brand'], mà cả kho là một nhãn
               "Vin Eyewear" nên mỗi thẻ lặp lại đúng một chuỗi ấy: một dòng
               chữ không mang tin nào, chen giữa ảnh và tên hàng.

               CỘT `brand` TRONG CSDL VẪN CÒN và các nơi khác vẫn đọc — đây
               chỉ là gỡ khỏi THẺ. Lớp .oa-card__kicker cũng đã gỡ khỏi
               oa.css vì đây là chỗ duy nhất dùng nó. */
            ?>

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
</li>
