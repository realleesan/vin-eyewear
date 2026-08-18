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

        <div class="hsec-head">
            <p class="eyebrow">Vừa lên kệ</p>
            <h2 id="hnew-title" class="section-h2 section-h2--plain">Sản phẩm mới về</h2>
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
                    <?php foreach ($products as $i => $p): ?>
                        <?php partial('_layout/product-card', [
                            'product'     => $p,
                            'badgeTone'   => 'new',
                            'showCompare' => false,
                            // CHỈ bốn thẻ đầu: chúng nằm trong khung nhìn ngay
                            // khi trang mở ra. Bốn thẻ sau nằm ngoài vùng cắt,
                            // phải bấm mũi tên mới thấy -> để lazy như thường.
                            'eager'       => $i < 4,
                        ]); ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="hsec-all">
            <a class="hsec-all__link" href="/san-pham?sort=newest">Xem tất cả →</a>
        </div>
    </div>
</section>
<?php endif; ?>
