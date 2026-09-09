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
 * $truot ĐÃ BỎ (Phase 2). Nó quyết định khối này vẽ ra băng trượt hay lưới —
 * mà nay luôn là lưới `.pgrid`, nên không còn hai nhánh để chọn. Xem khối chú
 * thích ở chỗ <ul> bên dưới.
 */
?>

<?php if ($categories !== []): ?>
<section class="hcat" data-section="s05" aria-labelledby="hcat-title">
    <div class="hcat__inner">

        <div class="hsec-head">
            <p class="eyebrow"><?= e(t('home.cat.eyebrow')) ?></p>
            <h2 id="hcat-title" class="section-h2 section-h2--plain"><?= e(t('home.cat.title')) ?></h2>
        </div>

        <?php
        /*
         * ─────────────────────────────────────────────────────────────────────
         * BĂNG TRƯỢT NGANG → LƯỚI (Phase 2) — VÀ ĐÂY LÀ CHỖ SỬA MỘT LỖI THẬT
         *
         * Khối này TRƯỚC ĐÂY VỠ HẲN trên desktop, và vỡ ở cả hai nhánh:
         *
         *   Luật dùng chung của băng trượt trong home-sections.css liệt kê
         *   `.pstrip__track, .hcat__strip, .hrev__track, .qface__track` —
         *   nhưng `.hcat__strip` là cái <div> BỌC NGOÀI (chứa hai mũi tên và
         *   .hcat__window), còn đường ray thật là <ul class="hcat__track">.
         *   Nên `display:flex` rơi vào nhầm phần tử: <ul> giữ nguyên
         *   `display:block`, và mỗi <li class="ccat"> rộng `min(320px, 72vw)`
         *   xếp CHỒNG thành MỘT CỘT 320px giữa một khung 1920px.
         *
         *   Nhánh còn lại (≤3 danh mục) dùng `.hcat__grid` — một lớp KHÔNG
         *   được khai ở đâu trong toàn bộ assets/css. Cùng kết quả.
         *
         * Đó là khối "một cột mỏng sát lề trái, bỏ trống 70% bên phải" ngay
         * dưới hero. Lỗi có từ trước đợt Gentle Monster, không phải do nó.
         *
         * Nay khối dùng `.pgrid` — cùng lớp lưới với mọi lưới thẻ khác của
         * site, nên không còn tên lớp nào chỉ tồn tại ở đúng một chỗ để lệch
         * đi mà không ai thấy.
         *
         * $truot, hai mũi tên và `data-category-strip` bỏ theo. home.js dò
         * băng này bằng `document.querySelector('[data-category-strip]')` rồi
         * thoát ngay nếu không thấy — không có lỗi nào phát sinh.
         * ─────────────────────────────────────────────────────────────────────
         */
        ?>
        <ul class="pgrid" role="list">
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
                                 <?php /* Hàng ĐẦU của lưới (4 ô ở desktop) nằm
                                          trong khung nhìn ngay khi trang mở ra;
                                          phần còn lại để lazy.

                                          BỐN chứ không ba: con số cũ đi cặp với
                                          băng trượt 3 thẻ đã bỏ, nay nó đi cặp
                                          với số cột của .pgrid từ 1101px — xem
                                          components/product.css. */ ?>
                                 <?= $i < 4 ? '' : 'loading="lazy"' ?> decoding="async">

                            <?php /* Huy hiệu nằm trong .ccat__media nhưng KHÔNG bị
                                     bo theo ảnh vì khung media không cắt góc;
                                     pointer-events tắt để không chắn cú bấm. */ ?>
                            <span class="ccat__count">
                                <?= $count > 0
                                    ? e(t('home.cat.count', [':n' => (string) $count]))
                                    : e(t('home.cat.soon')) ?>
                            </span>
                        </span>

                        <span class="ccat__body">
                            <span class="ccat__name"><?= e($c['name']) ?></span>
                            <span class="ccat__note">
                                <?= e(excerpt($c['description'] ?? '', 56)) ?>
                            </span>
                        </span>

                        <span class="ccat__foot">
                            <span class="ccat__more"><?= e(t('home.cat.view')) ?></span>
                            <span class="ccat__arrow" aria-hidden="true">→</span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="hsec-all">
            <a class="hsec-all__link" href="/san-pham"><?= e(t('home.cat.all')) ?></a>
        </div>
    </div>
</section>
<?php endif; ?>
