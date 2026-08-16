<?php

/**
 * _layout/product-card.php
 * Port từ src/components/product-card.tsx của bản Lovable.
 *
 * Nhận qua partial():
 *   $product — một dòng sản phẩm ĐÃ qua ProductModel (images/specs đã giải mã)
 *
 * Cách dùng:
 *   partial('_layout/product-card', ['product' => $p]);
 *
 * Nút "Mua ngay" của bản React mở hộp thoại QuickBuyDialog. Ở đây là form
 * POST thật tới /gio-hang/them — không cần JavaScript, và người tắt JS vẫn
 * mua được. Kèm token CSRF vì đây là thao tác thay đổi trạng thái.
 */

$price    = (int) $product['price'];
$compare  = $product['compare_at_price'] !== null ? (int) $product['compare_at_price'] : null;
$percent  = discount($price, $compare);
$inStock  = ProductModel::inStock($product);
$image    = ProductModel::image($product);
$hover    = ProductModel::hoverImage($product);
$url      = '/san-pham/' . rawurlencode($product['slug']);
?>
<article class="pcard<?= $inStock ? '' : ' is-out' ?>">

    <!-- Ảnh: aria-hidden + tabindex=-1 vì tiêu đề bên dưới đã là liên kết tới
         cùng địa chỉ; để cả hai vào luồng Tab sẽ bắt người dùng bàn phím nhấn
         hai lần cho mỗi sản phẩm. -->
    <a class="pcard__media" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
        <img class="pcard__img" src="<?= e($image) ?>" alt=""
             width="600" height="600" loading="lazy" decoding="async">

        <?php if ($hover !== null): ?>
            <img class="pcard__img pcard__img--hover" src="<?= e($hover) ?>" alt=""
                 width="600" height="600" loading="lazy" decoding="async">
        <?php endif; ?>

        <?php if ($percent !== null): ?>
            <span class="pcard__sale">-<?= $percent ?>%</span>
        <?php endif; ?>

        <?php if (!$inStock): ?>
            <span class="pcard__oos">Hết hàng</span>
        <?php endif; ?>
    </a>

    <div class="pcard__body">
        <h3 class="pcard__name">
            <a href="<?= e($url) ?>"><?= e($product['name']) ?></a>
        </h3>

        <p class="pcard__price">
            <span class="pcard__price-now"><span class="sr-only">Giá bán </span><?= money($price) ?></span>
            <?php if ($percent !== null): ?>
                <span class="pcard__price-old"><span class="sr-only">Giá gốc </span><?= money($compare) ?></span>
            <?php endif; ?>
        </p>

        <div class="pcard__actions">
            <form action="/gio-hang/them" method="post">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                <button type="submit" class="pcard__btn pcard__btn--buy"<?= $inStock ? '' : ' disabled' ?>>
                    <?= $inStock ? 'Mua ngay' : 'Hết hàng' ?>
                    <span class="sr-only"> — <?= e($product['name']) ?></span>
                </button>
            </form>
            <a class="pcard__btn pcard__btn--detail" href="<?= e($url) ?>">
                Chi tiết<span class="sr-only"> <?= e($product['name']) ?></span>
            </a>
        </div>

        <?php if (ProductModel::hasArTryOn($product)): ?>
            <!-- Chỉ mẫu nào có ảnh gọng PNG trong config/ar.php mới hiện nút này.
                 Đặt thành một dòng riêng dưới hai nút chính, không chen vào
                 hàng ngang: ở lưới 4 cột thẻ chỉ rộng ~218px, ba nút một hàng
                 là chữ nào cũng bị cắt. -->
            <a class="pcard__ar" href="/thu-ar?<?= e(http_build_query(['gong' => $product['slug']])) ?>">
                <?= icon('scan-eye', 'pcard__ar-ico', 14) ?>
                Thử AR<span class="sr-only"> mẫu <?= e($product['name']) ?></span>
            </a>
        <?php endif; ?>
    </div>
</article>
