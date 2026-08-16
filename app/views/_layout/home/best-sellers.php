<?php

/**
 * _layout/home/best-sellers.php — lưới sản phẩm bán chạy (S08).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": lưới 2 cột, mỗi thẻ NẰM NGANG — ảnh
 * vuông bên trái, bên phải là thương hiệu · tên · giá (kèm giá gạch) · hai nút
 * "Mua ngay" và "Chi tiết". Huy hiệu giảm giá nổi trên góc trái ảnh.
 *
 * Bản trước là 4 thẻ dọc thanh mảnh. Thiết kế đổi sang thẻ ngang nên mỗi thẻ
 * đủ chỗ cho cả hai nút — trước đó chỉ có một liên kết "Mua ngay" dạng chữ.
 *
 * KHÔNG dùng chung _layout/product-card.php: thẻ đó xếp dọc và có thêm nút
 * "Thử AR", đúng cho lưới trang /san-pham nhưng khác hẳn thẻ ngang ở đây.
 *
 * "Mua ngay" là form POST thêm thẳng vào giỏ (giống product-card), không phải
 * liên kết sang trang chi tiết — nhãn nút hứa gì thì làm đúng thế. "Chi tiết"
 * mới là đường sang trang sản phẩm.
 *
 * Nhận qua partial():
 *   $products — mảng sản phẩm đã qua ProductModel
 */

$products = $products ?? [];
?>

<?php if ($products !== []): ?>
<section class="hbest" data-section="s08" aria-labelledby="hbest-title">
    <div class="hbest__inner">

        <div class="section-head">
            <div>
                <p class="eyebrow">Được yêu thích</p>
                <h2 id="hbest-title" class="section-h2 section-h2--plain">Sản phẩm bán chạy</h2>
            </div>
            <a href="/san-pham" class="pill-link">Xem tất cả →</a>
        </div>

        <ul class="hbest__grid" role="list">
            <?php foreach ($products as $i => $p): ?>
                <?php
                $url     = '/san-pham/' . rawurlencode($p['slug']);
                $price   = (int) $p['price'];
                $compare = $p['compare_at_price'] !== null ? (int) $p['compare_at_price'] : null;
                $percent = discount($price, $compare);
                $inStock = ProductModel::inStock($p);

                // Huy hiệu: ưu tiên tình trạng kho, rồi tới mức giảm giá.
                // Thiết kế để trống huy hiệu ở thẻ không có gì đáng nói.
                $badge = null;
                if (!$inStock) {
                    $badge = 'Hết hàng';
                } elseif ($percent !== null) {
                    $badge = '-' . $percent . '%';
                } elseif (!empty($p['is_featured'])) {
                    $badge = 'Bán chạy';
                }
                ?>
                <li class="bcard">
                    <div class="bcard__media">
                        <img src="<?= e(ProductModel::image($p)) ?>" alt=""
                             width="600" height="600"
                             <?= $i < 2 ? '' : 'loading="lazy"' ?> decoding="async">

                        <?php if ($badge !== null): ?>
                            <span class="bcard__badge"><?= e($badge) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="bcard__body">
                        <p class="bcard__brand"><?= e($p['brand'] ?? 'Vin Eyewear') ?></p>

                        <h3 class="bcard__name">
                            <a href="<?= e($url) ?>"><?= e($p['name']) ?></a>
                        </h3>

                        <p class="bcard__prices">
                            <span class="sr-only">Giá bán </span>
                            <span class="bcard__price"><?= money($price) ?></span>
                            <?php if ($compare !== null && $compare > $price): ?>
                                <span class="bcard__was">
                                    <span class="sr-only">Giá gốc </span><?= money($compare) ?>
                                </span>
                            <?php endif; ?>
                        </p>

                        <div class="bcard__actions">
                            <form action="/gio-hang/them" method="post">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                                <button type="submit" class="bcard__btn bcard__btn--solid"
                                        <?= $inStock ? '' : 'disabled' ?>>
                                    <?= $inStock ? 'Mua ngay' : 'Hết hàng' ?>
                                    <span class="sr-only"> — <?= e($p['name']) ?></span>
                                </button>
                            </form>

                            <a class="bcard__btn bcard__btn--ghost" href="<?= e($url) ?>">
                                Chi tiết<span class="sr-only"> — <?= e($p['name']) ?></span>
                            </a>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
