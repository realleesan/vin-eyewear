<?php

/**
 * collection/index.php — Bộ sưu tập (/bo-suu-tap).
 *
 * Một thẻ cho mỗi bộ: ảnh bìa · ngày ra mắt · tên · giới thiệu · nút
 * "Xem chi tiết" dẫn sang /bo-suu-tap/<slug>.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NÚT ĐÃ ĐỔI ĐÍCH (2026-08-27)
 *
 * Trước đó nó dẫn thẳng sang /san-pham?collection=<slug>, tức là bỏ qua hẳn
 * trang chi tiết. Nay có trang chi tiết thật — lý do vì sao nó đáng tồn tại
 * ghi ở đầu CollectionController; chính trang đó mới là nơi đặt nút sang danh
 * mục đã lọc sẵn.
 *
 * `slug` đi vào ĐƯỜNG DẪN chứ không còn vào chuỗi truy vấn, nên mã hoá bằng
 * rawurlencode() (đúng như trang chi tiết sản phẩm làm), không phải
 * http_build_query().
 * ─────────────────────────────────────────────────────────────────────────────
 */

/*
 * Ngày ra mắt: chỉ hiện khi CÓ, và chỉ hiện THÁNG/NĂM.
 *
 * Bộ sưu tập là chuyện theo mùa — "Ra mắt 03/2026" nói đúng nhịp cửa hàng làm
 * việc, còn "Ra mắt 14/03/2026" gợi ý một sự kiện diễn ra đúng hôm đó, điều
 * không có thật. Ngày đầy đủ vẫn nằm trong CSDL cho khu quản trị.
 */
$ngayRaMat = static function (?string $date): string {
    if (empty($date) || !preg_match('/^(\d{4})-(\d{2})-/', (string) $date, $m)) {
        return '';
    }

    return 'Ra mắt ' . $m[2] . '/' . $m[1];
};
?>

<?php partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Bộ sưu tập']],
    'head_title'  => 'Bộ sưu tập',
    'head_lead'   => 'Mỗi bộ là một cách chọn gọng và tròng cho một kiểu ngày. '
                   . 'Mở bộ nào nghe hợp với bạn để xem kỹ, rồi lọc thẳng sang danh mục.',
]); ?>

<section class="colls">
    <?php if ($collections === []): ?>
        <?php /* Chưa có bộ nào đang hiện: nói ra thay vì để trang trắng. Xảy
                 ra thật khi cửa hàng ẩn hết để chuẩn bị mùa mới. */ ?>
        <p class="colls__empty">
            Chưa có bộ sưu tập nào đang trưng bày. Mời bạn xem
            <a href="/san-pham">toàn bộ sản phẩm</a>.
        </p>
    <?php else: ?>
        <div class="colls__list">
            <?php foreach ($collections as $i => $c): ?>
                <?php
                $cover = CollectionModel::cover($c);
                $when  = $ngayRaMat($c['launched_at'] ?? null);
                ?>
                <article class="coll<?= $i % 2 ? ' coll--flip' : '' ?>">
                    <?php
                    /*
                     * Ảnh và chữ ĐỔI BÊN so le nhau (.coll--flip đảo thứ tự
                     * trong CSS, không đảo thứ tự trong HTML — thứ tự đọc phải
                     * giữ nguyên ảnh-rồi-chữ cho trình đọc màn hình).
                     *
                     * Ba thẻ cùng một bố cục thì mắt trượt thẳng xuống và các
                     * bộ trông như một danh sách. So le buộc mắt dừng lại ở đầu
                     * mỗi thẻ — đây là trang trưng bày, không phải bảng dữ liệu.
                     */
                    ?>
                    <div class="coll__media">
                        <?php if ($cover !== ''): ?>
                            <?php /* loading="lazy" từ thẻ THỨ HAI trở đi: thẻ đầu
                                     nằm ngay trong màn hình đầu, hoãn tải nó chỉ
                                     làm trang trông chậm hơn. */ ?>
                            <img class="coll__img" src="<?= e(asset($cover)) ?>"
                                 alt="<?= e($c['name']) ?>"
                                 width="720" height="480"
                                 <?= $i === 0 ? '' : 'loading="lazy"' ?>>
                        <?php else: ?>
                            <?php /* Bộ vừa tạo chưa kịp có ảnh. Vẽ ô giữ chỗ đúng
                                     tỷ lệ để bố cục không sập, thay vì để một
                                     khoảng trắng cao 0px. */ ?>
                            <div class="coll__img coll__img--empty" aria-hidden="true">
                                <?= icon('glasses', 'coll__ph', 48) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="coll__body">
                        <?php if ($when !== ''): ?>
                            <p class="coll__when"><?= e($when) ?></p>
                        <?php endif; ?>

                        <h2 class="coll__name"><?= e($c['name']) ?></h2>

                        <?php if (!empty($c['tagline'])): ?>
                            <p class="coll__tagline"><?= e($c['tagline']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($c['intro'])): ?>
                            <p class="coll__intro"><?= e($c['intro']) ?></p>
                        <?php endif; ?>

                        <a class="coll__cta" href="/bo-suu-tap/<?= e(rawurlencode($c['slug'])) ?>">
                            Xem chi tiết
                            <?= icon('arrow-right', 'coll__cta-ico', 18) ?>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
