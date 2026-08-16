<?php

/**
 * _layout/home/reviews.php — đánh giá khách hàng (S16).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": tiêu đề căn giữa, 3 thẻ viền mảnh,
 * mỗi thẻ gồm hàng sao, câu trích dẫn in nghiêng cỡ lớn, rồi tên khách và
 * món hàng đã mua.
 *
 * Dữ liệu từ config('taxonomy.google_reviews'). Thiết kế vẽ 3 thẻ nên lấy 3
 * đánh giá đầu — cắt ở view chứ không xoá bớt trong config, để chỗ khác vẫn
 * dùng được cả danh sách.
 */

$reviews = array_slice(config('taxonomy.google_reviews') ?? [], 0, 3);
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

        <ul class="hrev__grid" role="list">
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
</section>
<?php endif; ?>
