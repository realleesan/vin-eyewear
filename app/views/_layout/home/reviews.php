<?php

/**
 * _layout/home/reviews.php — đánh giá khách hàng (S16).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": tiêu đề căn giữa, dưới là một BĂNG
 * TRƯỢT, hai mũi tên nổi ở hai mép.
 *
 * HAI CHỖ CỐ Ý KHÁC BẢN THIẾT KẾ, theo yêu cầu:
 *   • NĂM thẻ một khung nhìn, không phải bốn (kéo theo khoảng cột và đệm thẻ
 *     thu lại — xem .rcard trong home-sections.css)
 *   • băng TỰ CHẠY và chạy nhanh; bản thiết kế thì đứng yên chờ bấm mũi tên
 *
 * Bản trước là lưới ba thẻ tĩnh và chỉ lấy 3 đánh giá đầu. Nay in ra CẢ danh
 * sách: băng trượt sống được là nhờ có nhiều hơn số thẻ nhìn thấy.
 *
 * TẮT JS VẪN ĐỌC ĐƯỢC: không có JS thì băng đứng yên ở bốn thẻ đầu, hai mũi
 * tên không làm gì. Đó là lý do khối này KHÔNG ẩn các thẻ ngoài khung nhìn
 * bằng display:none — chúng chỉ nằm ngoài vùng cắt của .hrev__window, nên
 * trình đọc màn hình và công cụ tìm kiếm vẫn thấy đủ.
 *
 * Dữ liệu từ config('taxonomy.google_reviews').
 */

$reviews = config('taxonomy.google_reviews') ?? [];
?>

<?php if ($reviews !== []): ?>
<section class="hrev" data-section="s16" aria-labelledby="hrev-title">
    <div class="hrev__inner">

        <div class="hrev__head">
            <p class="eyebrow">Đánh giá</p>
            <h2 id="hrev-title" class="section-h2 section-h2--plain">
                Khách hàng nói về Vin Eyewear
            </h2>
        </div>

        <?php /* data-autoplay: số mili-giây giữa hai lần tự trượt. Có thuộc tính
                 này thì assets/js/home.js bật chế độ tự chạy; bỏ đi là băng chỉ
                 trượt khi bấm mũi tên (đúng bản thiết kế — nó KHÔNG tự chạy).

                 1600ms là CỐ Ý NHANH theo yêu cầu. Cùng với 0.3s chuyển động ở
                 .hrev__track, mỗi thẻ đứng yên khoảng 1.3 giây. Muốn chậm lại thì
                 tăng con số này, không cần sửa JS.

                 Máy đặt "giảm chuyển động" (prefers-reduced-motion) thì home.js
                 KHÔNG bật tự chạy, dù có thuộc tính này. */ ?>
        <div class="hrev__carousel" data-review-carousel data-autoplay="1600">
            <?php /* Hai mũi tên chỉ in ra khi có nhiều hơn một khung nhìn (5 thẻ) —
                     nút bấm vào không đi đâu là một lời hứa suông. Cùng ngưỡng đó
                     cũng quyết định băng có tự chạy hay không. */ ?>
            <?php if (count($reviews) > 5): ?>
                <button type="button" class="hrev__arrow hrev__arrow--prev"
                        data-review="prev" aria-label="Đánh giá trước" disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </button>
                <button type="button" class="hrev__arrow hrev__arrow--next"
                        data-review="next" aria-label="Đánh giá sau">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M9 6l6 6-6 6"/>
                    </svg>
                </button>
            <?php endif; ?>

            <div class="hrev__window">
                <ul class="hrev__track" role="list">
                    <?php foreach ($reviews as $r): ?>
                        <li class="rcard">
                            <span class="rating-stars" aria-label="<?= (int) $r['rating'] ?> trên 5 sao">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <?= starIcon(
                                        $i < $r['rating'],
                                        'rating-star ' . ($i < $r['rating'] ? 'rating-star--on' : 'rating-star--off')
                                    ) ?>
                                <?php endfor; ?>
                            </span>

                            <blockquote class="rcard__quote">&ldquo;<?= e($r['text']) ?>&rdquo;</blockquote>

                            <div class="rcard__foot">
                                <p class="rcard__name"><?= e($r['name']) ?></p>
                                <p class="rcard__item"><?= e($r['store']) ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
