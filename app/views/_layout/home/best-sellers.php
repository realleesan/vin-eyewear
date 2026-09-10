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

        <?php /* "Xem tất cả" trong đầu khối — cùng lý do đã ghi ở
                 _layout/home/new-arrivals.php. */ ?>
        <div class="hsec-head reveal">
            <p class="eyebrow"><?= e(t('home.best.eyebrow')) ?></p>
            <h2 id="hbest-title" class="section-h2 section-h2--plain"><?= e(t('home.best.title')) ?></h2>

            <div class="hsec-all">
                <a class="hsec-all__link" href="/san-pham"><?= e(t('home.see_all')) ?></a>
            </div>
        </div>

        <?php /* BĂNG TRƯỢT NGANG → LƯỚI (Phase 2). Lý do đầy đủ ở khối chú
                 thích cùng chỗ trong _layout/home/new-arrivals.php: cả site nay
                 dùng đúng một lớp lưới `.pgrid`, và hai mũi tên bỏ đi không làm
                 home.js hỏng vì nó lặp qua `[data-product-strip]` chứ không giả
                 định có phần tử nào. */ ?>
        <ul class="pgrid" role="list">
            <?php foreach ($products as $i => $p): ?>
                <?php partial('_layout/product-card', [
                    'product'     => $p,
                    'badgeTone'   => 'sale',
                    'showCompare' => true,
                    // Hàng đầu của lưới (4 thẻ ở desktop) nằm trong khung nhìn
                    // nếu khách cuộn tới đây; giữ lazy cho phần còn lại.
                    'eager'       => false,
                ]); ?>
            <?php endforeach; ?>
        </ul>

        <div class="hsec-all">
            <a class="hsec-all__link" href="/san-pham/gong-kinh?sort=popular"><?= e(t('home.see_all')) ?></a>
        </div>
    </div>
</section>
<?php endif; ?>
