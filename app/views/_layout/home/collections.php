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

        <?php /* "Xem tất cả" trong đầu khối — cùng lý do đã ghi ở
                 _layout/home/new-arrivals.php. Đích là /bo-suu-tap (trang danh
                 sách đầy đủ) chứ không phải /san-pham: khối này bày BỘ, nên
                 "xem tất cả" phải ra tất cả các bộ, không ra cả kho hàng. */ ?>
        <div class="hsec-head reveal">
            <p class="eyebrow"><?= e(t('home.coll.eyebrow')) ?></p>
            <h2 id="hcoll-title" class="section-h2 section-h2--plain"><?= e(t('home.coll.title')) ?></h2>

            <div class="hsec-all">
                <a class="hsec-all__link" href="/bo-suu-tap"><?= e(t('home.coll.all')) ?></a>
            </div>
        </div>

        <?php /* `.pgrid` — MỘT lưới thẻ cho cả site (Phase 2). `.hcoll__grid` là lưới
         thứ tư làm cùng một việc: nó khai `auto-fit, minmax(260px, 1fr)`, nên
         trên màn 1920 nó tự chia thành SÁU cột trong khi lưới sản phẩm ngay
         dưới chỉ có bốn. Xem components/product.css. */ ?>
<ul class="pgrid" role="list">
            <?php foreach ($collections as $i => $c): ?>
                <?php
                /* cover() trả rỗng khi đường dẫn trỏ tới file không tồn tại,
                   nên thẻ tự bỏ ảnh thay vì vẽ icon ảnh vỡ. */
                $img = CollectionModel::cover($c);

                /* SANG TRANG CỦA CHÍNH BỘ ẤY, không sang lưới hàng đã lọc.
                   Khối này bày BỘ SƯU TẬP, và cái khách bấm vào là một tấm ảnh
                   chiến dịch kèm một câu mô tả — thứ nó hứa là "kể cho tôi
                   nghe về bộ này", không phải "đổ hàng ra cho tôi xem". Trang
                   chi tiết mở đầu bằng đúng tấm ảnh ấy rồi mới dẫn sang
                   /san-pham?collection=<slug>, nên lối cũ không mất, chỉ lùi
                   lại một bước và có thêm phần kể chuyện. Cùng cặp đích với
                   hai nút trên hero — xem _layout/home/hero.php. */
                $url = '/bo-suu-tap/' . rawurlencode((string) $c['slug']);
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
                            <span class="ccard__tagline"><?= e($c['tagline']) ?> <?= e(t('home.coll.explore')) ?></span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="hsec-all">
            <?php /* "Tất cả bộ sưu tập" trỏ /bo-suu-tap — trước đây trỏ nhầm /san-pham. */ ?>
            <a class="hsec-all__link" href="/bo-suu-tap"><?= e(t('home.coll.all')) ?></a>
        </div>
    </div>
</section>
<?php endif; ?>
