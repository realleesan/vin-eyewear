<?php

/**
 * _layout/home/best-sellers.php — lưới sản phẩm bán chạy (S08).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": thẻ sản phẩm DỌC — ảnh cao 300px, huy
 * hiệu đỏ mức giảm giá ở góc trái, dưới ảnh là thương hiệu · tên · giá kèm giá
 * gốc gạch ngang, chân thẻ có hai nút "Mua ngay" và "Chi tiết".
 *
 * KHÁC BẢN THIẾT KẾ: lưới tĩnh 4 cột của bản thiết kế nay là BĂNG TRƯỢT có hai
 * mũi tên tới/lui — xem chú thích dài hơn ở đầu _layout/home/new-arrivals.php,
 * hai khối dùng chung hệt nhau bộ lớp .pstrip và makeStrip() của home.js.
 *
 * Bản trước là 2 cột thẻ NẰM NGANG (ảnh trái, chữ phải). Bản thiết kế này gom
 * cả hai lưới sản phẩm của trang chủ về cùng một dáng thẻ dọc, nên phần dựng
 * thẻ đã dời sang _layout/product-card.php dùng chung với khối "mới về" và với
 * khối này giờ chỉ còn tiêu đề và cái lưới.
 *
 * Nhận qua partial():
 *   $products — ProductModel::featured(8). Kho hàng mẫu hiện chỉ có 4 sản
 *               phẩm gắn "nổi bật" nên băng này chưa trượt được; hai mũi tên
 *               vẫn in ra ở trạng thái mờ và tự sống lại khi có thêm hàng.
 */

$products = $products ?? [];
?>

<?php if ($products !== []): ?>
<section class="hbest" data-section="s08" aria-labelledby="hbest-title">
    <div class="hbest__inner">

        <div class="hsec-head">
            <p class="eyebrow">Được yêu thích</p>
            <h2 id="hbest-title" class="section-h2 section-h2--plain">Sản phẩm bán chạy</h2>
        </div>

        <div class="pstrip" data-product-strip>
            <?php /* Hai mũi tên LUÔN in ra, kể cả khi chưa đủ hàng để trượt.
                     Lúc đó makeStrip() (assets/js/home.js) tự đặt `disabled` cho
                     cả hai — chúng mờ đi nhưng vẫn giữ chỗ, nên thêm sản phẩm
                     trong trang quản trị là băng chạy được ngay, không phải sửa
                     file nào. Xem .pstrip__arrow:disabled trong home-sections.css
                     để biết trạng thái mờ trông thế nào. */ ?>
            <button type="button" class="pstrip__arrow pstrip__arrow--prev"
                    data-strip="prev" aria-label="Sản phẩm trước" disabled>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </button>
            <button type="button" class="pstrip__arrow pstrip__arrow--next"
                    data-strip="next" aria-label="Sản phẩm sau">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M9 6l6 6-6 6"/>
                </svg>
            </button>

            <div class="pstrip__window">
                <ul class="pstrip__track" role="list">
                    <?php foreach ($products as $p): ?>
                        <?php partial('_layout/product-card', [
                            'product'     => $p,
                            'badgeTone'   => 'sale',
                            'showCompare' => true,
                        ]); ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="hsec-all">
            <a class="hsec-all__link" href="/san-pham">Xem tất cả →</a>
        </div>
    </div>
</section>
<?php endif; ?>
