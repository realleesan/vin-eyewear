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
 * BA CỘT, LUÔN LUÔN — và quá ba danh mục thì trượt chứ không xuống hàng.
 *
 * Trước đây số cột chạy theo số danh mục (chia hết cho 4 thì 4 cột, còn lại
 * 3) để tránh thẻ thứ tư rơi xuống một hàng riêng bỏ trống hai phần ba chiều
 * ngang. Cách đó vá được đúng con số 4: lên 5 danh mục là thẻ mồ côi quay lại,
 * và cỡ thẻ thì nhảy giữa hai lần vào trang chỉ vì quản trị viên vừa thêm một
 * danh mục — cùng một trang chủ mà mỗi lúc một dáng.
 *
 * Nay khoá cứng 3 cột như bản thiết kế, và thừa ra bao nhiêu thì đẩy sang
 * BĂNG TRƯỢT với hai mũi tên tới/lui, dùng chung makeStrip() của
 * assets/js/home.js với hai khối sản phẩm và khối đánh giá. Thêm danh mục thứ
 * tư, thứ năm, thứ mười — bố cục không đổi một li nào.
 *
 * TỪ BA DANH MỤC TRỞ XUỐNG THÌ VẪN LÀ LƯỚI TĨNH, không phải băng trượt có hai
 * mũi tên mờ. Lý do không chỉ là gọn mắt: trên màn hẹp băng trượt chỉ hiện
 * một thẻ một lúc, nên nếu ba thẻ mà dựng thành băng thì hai thẻ kia phải bấm
 * mũi tên mới thấy — trong khi lưới tĩnh xuống một cột là đủ cả ba, cuộn dọc
 * là hết. Đây là lý do khối này KHÔNG bắt chước .pstrip (nơi hai mũi tên luôn
 * in ra rồi mờ đi): thẻ sản phẩm thì tám cái là chuyện thường, còn danh mục
 * thì ba hay bốn.
 *
 * TẮT JS THÌ SAO: băng đứng yên ở ba thẻ đầu và hai mũi tên không làm gì. Các
 * thẻ sau vẫn nằm trong DOM (chỉ ngoài vùng cắt của .hcat__window) nên trình
 * đọc màn hình và máy tìm kiếm vẫn thấy đủ, và mỗi thẻ là một liên kết thật
 * tới trang danh mục. Cùng cách xuống thang với _layout/home/new-arrivals.php.
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
];

/*
 * Ba thẻ vừa khít một khung nhìn. Thừa ra thì mới cần mũi tên — xem khối chú
 * thích trên. Con số 3 này đi cặp với .hcat__track > * trong
 * assets/css/components/home-sections.css: đổi một chỗ mà quên chỗ kia thì
 * mũi tên hiện ra trong khi mọi thẻ đã nằm sẵn trong khung, bấm không thấy gì
 * đổi.
 */
$truot = count($categories) > 3;
?>

<?php if ($categories !== []): ?>
<section class="hcat" data-section="s05" aria-labelledby="hcat-title">
    <div class="hcat__inner">

        <div class="hsec-head">
            <p class="eyebrow">Khám phá</p>
            <h2 id="hcat-title" class="section-h2 section-h2--plain">Danh mục</h2>
        </div>

        <?php if ($truot): ?>
        <div class="hcat__strip" data-category-strip>
            <?php /* Hai mũi tên CHỈ in ra khi thật sự có thứ để trượt, nên
                     không cần trạng thái mờ ban đầu như .pstrip — ở đây
                     makeStrip() luôn tìm thấy đủ thẻ để bật chúng lên.

                     Vẫn để makeStrip() tự đặt `disabled`: trên màn rất rộng
                     hay khi người dùng phóng to chữ, số thẻ lọt trong khung có
                     thể vượt số thẻ có thật, và lúc đó mờ đi là nói đúng sự
                     thật. */ ?>
            <button type="button" class="hcat__arrow hcat__arrow--prev"
                    data-category="prev" aria-label="Danh mục trước">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
            </button>
            <button type="button" class="hcat__arrow hcat__arrow--next"
                    data-category="next" aria-label="Danh mục sau">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M9 6l6 6-6 6"/>
                </svg>
            </button>

            <div class="hcat__window">
        <?php endif; ?>

        <ul class="<?= $truot ? 'hcat__track' : 'hcat__grid' ?>" role="list">
            <?php foreach ($categories as $i => $c): ?>
                <?php
                [$slot, $fallback] = $covers[$c['slug']] ?? ['', 'assets/images/product-1.jpg'];
                $cover = designImage($slot, $fallback);
                $count = (int) ($c['product_count'] ?? 0);
                ?>
                <li class="ccat">
                    <a class="ccat__link" href="<?= e(danhMucUrl($c['slug'])) ?>">
                        <span class="ccat__media">
                            <img src="<?= e($cover) ?>" alt=""
                                 width="600" height="600"
                                 <?php /* Ba thẻ đầu nằm trong khung nhìn ngay khi
                                          trang mở ra; các thẻ sau phải bấm mũi
                                          tên mới thấy nên để lazy. makeStrip()
                                          tự gỡ lazy ở cú trượt đầu tiên — xem
                                          wakeLazyImages() trong home.js. */ ?>
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

        <?php if ($truot): ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="hsec-all">
            <a class="hsec-all__link" href="/san-pham">Tất cả sản phẩm →</a>
        </div>
    </div>
</section>
<?php endif; ?>
