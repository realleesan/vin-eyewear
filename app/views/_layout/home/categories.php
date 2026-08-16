<?php

/**
 * _layout/home/categories.php — lưới danh mục (S05).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": lưới 3 cột, mỗi thẻ là một khối trắng
 * bo 32px — ảnh cao 300px, huy hiệu số mẫu nổi ở góc trái trên ảnh, dưới ảnh
 * là tên danh mục + ghi chú và một mũi tên tròn ở mép phải. Rê chuột thì thẻ
 * nhấc lên.
 *
 * Nhận qua partial():
 *   $categories — CategoryModel::withProductCounts()
 *
 * DB còn đúng ba danh mục nên lưới 3 cột của bản thiết kế vừa khít một hàng.
 * Danh mục "Kính áp tròng" đã xoá hẳn khỏi dự án (cả DB lẫn seed trong
 * database/schema.sql), không phải ẩn đi ở tầng hiển thị.
 *
 * Thêm danh mục thứ tư thì nó tự xuống hàng dưới — muốn giữ đúng một hàng thì
 * sửa số cột ở .hcat__grid trong components/home-sections.css.
 *
 * Ảnh bìa: bảng `categories` chưa có cột ảnh, nên tra theo slug. Ưu tiên ảnh
 * của bản thiết kế (ô cat-gong · cat-mat · cat-trong trong assets/images/home/);
 * chưa tải về thì mượn ảnh một sản phẩm tiêu biểu trong chính danh mục đó —
 * vẫn đúng mặt hàng, chỉ là nền trắng chứ chưa phải ảnh bìa biên tập.
 * TODO(data): thêm cột `cover_image` cho categories rồi bỏ bảng tra dưới đây.
 */

$covers = [
    'gong-kinh'  => ['cat-gong',  'assets/images/product-1.jpg'],
    'kinh-mat'   => ['cat-mat',   'assets/images/product-3.jpg'],
    'trong-kinh' => ['cat-trong', 'assets/images/product-5.jpg'],
];
?>

<?php if ($categories !== []): ?>
<section class="hcat" data-section="s05" aria-labelledby="hcat-title">
    <div class="hcat__inner">

        <div class="section-head">
            <div>
                <p class="eyebrow">Khám phá</p>
                <h2 id="hcat-title" class="section-h2 section-h2--plain">Danh mục</h2>
            </div>
            <a href="/san-pham" class="pill-link">Tất cả sản phẩm →</a>
        </div>

        <ul class="hcat__grid" role="list">
            <?php foreach ($categories as $i => $c): ?>
                <?php
                [$slot, $fallback] = $covers[$c['slug']] ?? ['', 'assets/images/product-1.jpg'];
                $cover = designImage($slot, $fallback);
                $count = (int) ($c['product_count'] ?? 0);
                ?>
                <li class="ccat">
                    <a class="ccat__link" href="/san-pham?category=<?= e(rawurlencode($c['slug'])) ?>">
                        <span class="ccat__media">
                            <img src="<?= e($cover) ?>" alt=""
                                 width="600" height="600"
                                 <?= $i < 3 ? '' : 'loading="lazy"' ?> decoding="async">
                        </span>

                        <?php /* Huy hiệu nằm ngoài .ccat__media để không bị bo
                                 theo ảnh; pointer-events tắt để không chắn cú
                                 bấm vào thẻ. */ ?>
                        <span class="ccat__count">
                            <?= $count > 0 ? $count . ' mẫu' : 'Sắp có hàng' ?>
                        </span>

                        <span class="ccat__body">
                            <span class="ccat__text">
                                <span class="ccat__name"><?= e($c['name']) ?></span>
                                <span class="ccat__note">
                                    <?= e(excerpt($c['description'] ?? '', 34)) ?>
                                </span>
                            </span>
                            <span class="ccat__arrow" aria-hidden="true">→</span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
