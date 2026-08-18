<?php

/**
 * _layout/home/categories.php — lưới danh mục (S05).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": lưới 3 cột, mỗi thẻ là một khối trắng
 * viền mảnh bo 6px — ảnh cao 300px với huy hiệu số mẫu nổi trên góc trái, dưới
 * ảnh là tên danh mục + mô tả, và một chân thẻ có đường kẻ ghi "Xem danh mục →".
 * Rê chuột thì thẻ nhấc lên.
 *
 * Nhận qua partial():
 *   $categories — CategoryModel::withProductCounts()
 *
 * SỐ CỘT CHẠY THEO SỐ DANH MỤC, không gõ cứng 3 như bản thiết kế.
 *
 * Bản thiết kế vẽ 3 thẻ vừa khít một hàng, và một chú thích cũ ở đây khẳng
 * định "DB còn đúng ba danh mục, Kính áp tròng đã xoá hẳn". Chú thích đó SAI:
 * database/schema.sql seed đủ BỐN danh mục và "Kính áp tròng" vẫn còn. Lưới
 * khoá cứng 3 cột nên thẻ thứ tư rơi xuống một hàng riêng, để trống hai phần
 * ba chiều ngang — đó là lỗi bố cục nhìn thấy ngay ở trang chủ.
 *
 * Quy tắc dưới đây tránh cái thẻ mồ côi đó: chia hết cho 4 thì xếp 4 cột,
 * còn lại giữ 3 cột của bản thiết kế. Nghĩa là 3 -> 3, 4 -> 4, 6 -> 3, 8 -> 4.
 * Admin thêm/bớt danh mục thì lưới tự theo, không phải sửa CSS.
 *
 * Ảnh bìa: bảng `categories` chưa có cột ảnh, nên tra theo slug. Ưu tiên ảnh
 * của bản thiết kế (ô cat-* trong assets/images/home/); chưa tải về thì mượn
 * ảnh một sản phẩm tiêu biểu trong chính danh mục đó — vẫn đúng mặt hàng, chỉ
 * là nền trắng chứ chưa phải ảnh bìa biên tập.
 * TODO(data): thêm cột `cover_image` cho categories rồi bỏ bảng tra dưới đây.
 */

$covers = [
    'gong-kinh'     => ['cat-gong',     'assets/images/product-1.jpg'],
    'kinh-mat'      => ['cat-mat',      'assets/images/product-3.jpg'],
    'trong-kinh'    => ['cat-trong',    'assets/images/product-5.jpg'],
    // Thiếu dòng này thì "Kính áp tròng" rơi vào ảnh dự phòng chung và đi
    // mượn ảnh một cái GỌNG — sai hẳn mặt hàng ngay trên thẻ danh mục.
    'kinh-ap-trong' => ['cat-ap-trong', 'assets/images/product-6.jpg'],
];

$cols = count($categories) % 4 === 0 ? 4 : 3;
?>

<?php if ($categories !== []): ?>
<section class="hcat" data-section="s05" aria-labelledby="hcat-title">
    <div class="hcat__inner">

        <div class="hsec-head">
            <p class="eyebrow">Khám phá</p>
            <h2 id="hcat-title" class="section-h2 section-h2--plain">Danh mục</h2>
        </div>

        <ul class="hcat__grid" role="list" style="--hcat-cols: <?= (int) $cols ?>">
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

                            <?php /* Huy hiệu nằm trong .ccat__media nhưng KHÔNG bị
                                     bo theo ảnh vì khung media không cắt góc;
                                     pointer-events tắt để không chắn cú bấm. */ ?>
                            <span class="ccat__count">
                                <?= $count > 0 ? $count . ' mẫu' : 'Sắp có hàng' ?>
                            </span>
                        </span>

                        <span class="ccat__body">
                            <span class="ccat__name"><?= e($c['name']) ?></span>
                            <span class="ccat__note">
                                <?= e(excerpt($c['description'] ?? '', 56)) ?>
                            </span>
                        </span>

                        <span class="ccat__foot">
                            <span class="ccat__more">Xem danh mục</span>
                            <span class="ccat__arrow" aria-hidden="true">→</span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="hsec-all">
            <a class="hsec-all__link" href="/san-pham">Tất cả sản phẩm →</a>
        </div>
    </div>
</section>
<?php endif; ?>
