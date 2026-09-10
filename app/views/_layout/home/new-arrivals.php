<?php

/**
 * _layout/home/new-arrivals.php — "Sản phẩm mới về" (S07).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": thẻ sản phẩm DỌC, huy hiệu xanh lá
 * "Mới" ở góc trái trên mỗi ảnh.
 *
 * KHÁC BẢN THIẾT KẾ: bản thiết kế là một lưới TĨNH 4 cột; ở đây là BĂNG TRƯỢT
 * 4 thẻ một khung nhìn với hai mũi tên tới/lui ở hai mép — cùng cơ chế với
 * khối đánh giá (_layout/home/reviews.php), dùng chung makeStrip() trong
 * assets/js/home.js. Khác khối đánh giá đúng một điểm: băng này KHÔNG tự chạy.
 * Đánh giá là thứ lướt qua cho biết, còn sản phẩm thì khách đang cân nhắc —
 * món hàng tự trôi đi khi người ta đang nhìn nó là một cách gây bực.
 *
 * TẮT JS VẪN ĐỌC ĐƯỢC: không có JS thì băng đứng yên ở bốn thẻ đầu và hai mũi
 * tên không làm gì. Bốn thẻ sau vẫn nằm trong DOM (chỉ ngoài vùng cắt của
 * .pstrip__window), nên trình đọc màn hình và máy tìm kiếm vẫn thấy đủ.
 *
 * Khối này và "Sản phẩm bán chạy" dùng CHUNG một dáng thẻ — xem
 * _layout/product-card.php. Khác nhau đúng hai điểm, cả hai đều truyền
 * qua tham số: huy hiệu xanh thay vì đỏ, và không in giá gốc gạch ngang.
 *
 * Vì sao KHÔNG in giá gốc: hàng vừa lên kệ thì mức giảm (nếu có) là giá mở
 * bán chứ không phải một đợt hạ giá — treo giá gạch ngang lên đó là hứa một
 * thứ khuyến mãi không có thật.
 *
 * Nhận qua partial():
 *   $products — ProductModel::newest(8). Ít hơn một khung nhìn thì hai mũi
 *               tên vẫn in ra nhưng ở trạng thái mờ (disabled).
 */

$products = $products ?? [];
?>

<?php if ($products !== []): ?>
<section class="hnew" data-section="s07" aria-labelledby="hnew-title">
    <div class="hnew__inner">

        <div class="hsec-head reveal">
            <p class="eyebrow"><?= e(t('home.new.eyebrow')) ?></p>
            <h2 id="hnew-title" class="section-h2 section-h2--plain"><?= e(t('home.new.title')) ?></h2>
        </div>

        <?php
        /*
         * ─────────────────────────────────────────────────────────────────────
         * BĂNG TRƯỢT NGANG → LƯỚI (Phase 2)
         *
         * Khối này từng là `.pstrip`: một băng cuộn ngang có hai mũi tên, do
         * makeStrip() trong assets/js/home.js điều khiển.
         *
         * VÌ SAO ĐỔI: trang chủ có BỐN khối cùng bày sản phẩm/danh mục, và mỗi
         * khối lại là một hình dạng khác nhau — một khối lưới, một khối băng
         * trượt 4 thẻ, một khối băng trượt thẻ nhỏ, một khối vỡ hẳn thành một
         * cột. Cùng một thẻ sản phẩm hiện ra bốn cỡ khác nhau trong một lần
         * cuộn trang. Đó là thứ đọc ra ngay là "ghép từ nhiều nguồn".
         *
         * Nay mọi lưới thẻ của site — trang chủ, /san-pham, /tim-kiem — dùng
         * ĐÚNG một lớp `.pgrid`: 2 cột trên điện thoại, 3 từ 901px, 4 từ
         * 1101px. Xem components/product.css.
         *
         * HAI MŨI TÊN BỎ THEO, và JS KHÔNG HỎNG: home.js tìm băng trượt bằng
         * `document.querySelectorAll('[data-product-strip]')` rồi lặp qua kết
         * quả — không còn phần tử nào thì vòng lặp chạy 0 lần. Không có lỗi,
         * không có mã chết nào phải gỡ ở đây (makeStrip vẫn phục vụ băng đánh
         * giá và băng dáng mặt).
         *
         * LAZY-LOAD ĐỔI THEO: trước đây chỉ 4 thẻ đầu `eager` vì 4 thẻ sau nằm
         * ngoài vùng cắt. Lưới thì mọi thẻ đều hiện, nhưng chỉ HÀNG ĐẦU (4 thẻ
         * ở desktop) nằm trong khung nhìn — nên con số 4 vẫn đúng, chỉ đổi lý do.
         * ─────────────────────────────────────────────────────────────────────
         */
        ?>
        <ul class="pgrid" role="list">
            <?php foreach ($products as $i => $p): ?>
                <?php partial('_layout/product-card', [
                    'product'     => $p,
                    'badgeTone'   => 'new',
                    'showCompare' => false,
                    'eager'       => $i < 4,
                ]); ?>
            <?php endforeach; ?>
        </ul>

        <div class="hsec-all">
            <a class="hsec-all__link" href="/san-pham/gong-kinh?sort=newest"><?= e(t('home.see_all')) ?></a>
        </div>
    </div>
</section>
<?php endif; ?>
