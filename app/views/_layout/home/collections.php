<?php

/**
 * _layout/home/collections.php — "Bộ sưu tập mới" (S09).
 *
 * Dựng theo "Vin Eyewear Home.dc.html": lưới khảm 3 ô — ô lớn bên trái cao
 * bằng hai hàng, hai ô nhỏ xếp chồng bên phải. Mỗi ô là ảnh tràn khung, tên
 * bộ sưu tập nằm trên một tấm biển nền nâu sẫm dính vào MÉP TRÁI ảnh (bo góc
 * chỉ ở hai cạnh phải, như một cái nhãn dán thò ra).
 *
 * Với kính thời trang đây là kênh bán chính chứ không phải khối trang trí:
 * khách chọn theo "trông thế nào" trước khi chọn theo thông số.
 *
 * Nội dung ở bảng `collections` (quản lý tại /quan-tri/bo-suu-tap), đường lọc
 * dùng lại ProductController (/san-pham?collection=<slug>).
 *
 * CẮT Ở BA: lưới khảm của bản thiết kế có đúng ba ô và ô đầu chiếm hai hàng.
 * Cửa hàng thêm bộ thứ tư thì nó sẽ phá vỡ hình khảm chứ không tự xuống hàng
 * cho đẹp, nên chặn ngay ở đây — ba bộ ĐẦU theo thứ tự trưng bày. Muốn xem đủ
 * thì có trang riêng: /bo-suu-tap.
 */

$collections = array_slice(CollectionModel::visible(), 0, 3);
?>

<?php if ($collections !== []): ?>
<section class="hcoll" data-section="s09" aria-labelledby="hcoll-title">
    <div class="hcoll__inner">

        <div class="hsec-head">
            <p class="eyebrow">Tuyển chọn theo chủ đề</p>
            <h2 id="hcoll-title" class="section-h2 section-h2--plain">Bộ sưu tập mới</h2>
        </div>

        <ul class="hcoll__grid" role="list">
            <?php foreach ($collections as $i => $c): ?>
                <?php
                /* cover() trả rỗng khi đường dẫn trỏ tới file không tồn tại,
                   nên thẻ tự bỏ ảnh thay vì vẽ icon ảnh vỡ. */
                $img = CollectionModel::cover($c);

                $url = '/san-pham?' . http_build_query(['collection' => $c['slug']]);
                ?>
                <li class="ccard<?= $i === 0 ? ' ccard--lead' : '' ?>">
                    <a class="ccard__link" href="<?= e($url) ?>">
                        <?php if ($img !== ''): ?>
                            <img class="ccard__img" src="<?= e(asset($img)) ?>" alt=""
                                 width="800" height="1000"
                                 <?= $i === 0 ? '' : 'loading="lazy"' ?> decoding="async">
                        <?php endif; ?>

                        <?php /* Tấm biển nằm NGOÀI ảnh để không bị ảnh phóng to
                                 khi rê chuột kéo theo; pointer-events tắt để
                                 không chắn cú bấm vào thẻ. */ ?>
                        <span class="ccard__plate">
                            <span class="ccard__name"><?= e($c['name']) ?></span>
                            <span class="ccard__tagline"><?= e($c['tagline']) ?> · Khám phá →</span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="hsec-all">
            <a class="hsec-all__link" href="/san-pham">Tất cả bộ sưu tập →</a>
        </div>
    </div>
</section>
<?php endif; ?>
