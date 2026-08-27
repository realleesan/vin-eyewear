<?php

/**
 * collection/detail.php — chi tiết một bộ sưu tập (/bo-suu-tap/{slug}).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TRANG NÀY KHÔNG PHẢI MỘT TRANG DANH MỤC THU NHỎ
 *
 * Nó kể về bộ sưu tập rồi GIAO người xem cho /san-pham?collection=<slug> —
 * nơi có bộ lọc, sắp xếp và phân trang thật. Lý do đầy đủ ở đầu
 * CollectionController.
 *
 * Hệ quả cho người sửa file này: đừng thêm phân trang, đừng thêm ô sắp xếp,
 * đừng nới số thẻ sản phẩm. Mỗi thứ thêm vào là một bước tiến tới bản sao
 * nghèo hơn của trang danh mục, và tới lúc nó đủ giống thì cả hai trang cùng
 * phải bảo trì.
 *
 * Nhận từ controller:
 *   $collection  dòng bảng `collections` (đã chắc chắn is_visible = 1)
 *   $products    tối đa 4 sản phẩm mẫu, hàng nổi bật trước
 *   $total       TỔNG số sản phẩm của bộ (không phải count($products))
 *   $minPrice    giá thấp nhất, hoặc null khi chưa hàng nào có giá
 *   $shapes      [['key','label','url'], …] cụm chip lọc nhanh
 *   $others      các bộ khác đang trưng bày
 * ─────────────────────────────────────────────────────────────────────────────
 */

$cover = CollectionModel::cover($collection);

/* Cùng luật với trang danh sách: chỉ THÁNG/NĂM. Bộ sưu tập là chuyện theo
   mùa, ngày đầy đủ gợi ý một sự kiện diễn ra đúng hôm đó — không có thật. */
$when = '';
if (!empty($collection['launched_at'])
    && preg_match('/^(\d{4})-(\d{2})-/', (string) $collection['launched_at'], $m)) {
    $when = 'Ra mắt ' . $m[2] . '/' . $m[1];
}

/* Đường về danh mục đã lọc sẵn — dựng MỘT LẦN rồi dùng lại ở cả ba nút.
   Ba chỗ tự nối chuỗi riêng là ba chỗ có thể lệch nhau về sau. */
$catalogUrl = '/san-pham?' . http_build_query(['collection' => $collection['slug']]);

/*
 * `story` tách thành đoạn theo DÒNG TRỐNG, không phải theo mỗi dấu xuống dòng.
 *
 * Ô nhập trong khu quản trị là <textarea>, nên người viết xuống dòng cả khi
 * chỉ muốn ngắt cho dễ đọc trong ô. Cắt theo từng dòng thì một đoạn văn bị
 * băm thành năm thẻ <p> có khoảng cách giữa chúng — trông như năm ý rời rạc.
 * Dòng trống là dấu hiệu duy nhất người viết thật sự muốn sang đoạn mới.
 */
$story = trim((string) ($collection['story'] ?? ''));
$paras = $story === '' ? [] : preg_split('/\R\s*\R/', $story);
?>

<?php partial('_layout/page-head', [
    'head_crumbs' => [
        ['label' => 'Bộ sưu tập', 'url' => '/bo-suu-tap'],
        ['label' => $collection['name']],
    ],
]); ?>

<?php /* Không truyền head_title: <h1> là tên bộ trong khối giới thiệu ngay
         dưới đây. Thêm một dải tiêu đề nữa là nói tên bộ hai lần và đẩy ảnh
         lookbook xuống khỏi màn hình đầu — cùng lý do trang chi tiết sản phẩm
         chỉ truyền breadcrumb. */ ?>

<article class="cdet">

    <!-- ── Khối giới thiệu: ảnh lookbook + phần chữ ─────────────────────── -->
    <section class="cdet__hero">
        <div class="cdet__media">
            <?php if ($cover !== ''): ?>
                <?php /* KHÔNG loading="lazy": ảnh này nằm ngay trong màn hình
                         đầu, hoãn tải nó chỉ làm trang trông chậm hơn. */ ?>
                <img class="cdet__img" src="<?= e(asset($cover)) ?>"
                     alt="<?= e($collection['name']) ?>"
                     width="880" height="600" decoding="async">
            <?php else: ?>
                <div class="cdet__img cdet__img--empty" aria-hidden="true">
                    <?= icon('glasses', 'cdet__ph', 56) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="cdet__body">
            <?php if ($when !== ''): ?>
                <p class="cdet__when"><?= e($when) ?></p>
            <?php endif; ?>

            <h1 class="cdet__name"><?= e($collection['name']) ?></h1>

            <?php if (!empty($collection['tagline'])): ?>
                <p class="cdet__tagline"><?= e($collection['tagline']) ?></p>
            <?php endif; ?>

            <?php if (!empty($collection['intro'])): ?>
                <p class="cdet__intro"><?= e($collection['intro']) ?></p>
            <?php endif; ?>

            <?php
            /*
             * Hai con số: bộ này có bao nhiêu món, rẻ nhất từ bao nhiêu.
             *
             * Đây là thứ trang danh mục chỉ trả lời được SAU khi người dùng đã
             * bấm vào — nên nó đứng ngay cạnh nút, để người ta biết mình sắp
             * bấm vào cái gì. Bộ chưa có hàng thì cả khối biến mất chứ không
             * in "0 sản phẩm": câu đó không mời ai bấm tiếp.
             */
            ?>
            <?php if ($total > 0): ?>
                <p class="cdet__stats">
                    <span class="cdet__stat"><?= (int) $total ?> sản phẩm</span>
                    <?php if ($minPrice !== null): ?>
                        <span class="cdet__dot" aria-hidden="true">·</span>
                        <span class="cdet__stat">từ <?= e(money($minPrice)) ?></span>
                    <?php endif; ?>
                </p>

                <a class="cdet__cta" href="<?= e($catalogUrl) ?>">
                    Xem <?= (int) $total ?> sản phẩm của bộ
                    <?= icon('arrow-right', 'cdet__cta-ico', 18) ?>
                </a>
            <?php else: ?>
                <?php /* Bộ đang trưng bày mà chưa gắn hàng nào — xảy ra thật
                         lúc cửa hàng dựng trước bộ sắp lên kệ. Nói ra, và
                         KHÔNG dẫn sang danh mục đã lọc: đường đó chỉ tới một
                         lưới trắng, còn tệ hơn là không có nút. */ ?>
                <p class="cdet__soon">
                    Bộ này chưa có sản phẩm nào đang bán. Mời bạn xem
                    <a href="/san-pham">toàn bộ sản phẩm</a>.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ── Câu chuyện của bộ ───────────────────────────────────────────── -->
    <?php if ($paras !== []): ?>
        <section class="cdet__story" aria-labelledby="cdet-story-title">
            <h2 id="cdet-story-title" class="cdet__h2">Về bộ sưu tập này</h2>
            <?php foreach ($paras as $para): ?>
                <p class="cdet__para"><?= e(trim($para)) ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- ── Vài món trong bộ ────────────────────────────────────────────── -->
    <?php if ($products !== []): ?>
        <section class="cdet__picks" aria-labelledby="cdet-picks-title">
            <div class="cdet__picks-head">
                <h2 id="cdet-picks-title" class="cdet__h2">Vài món trong bộ</h2>
                <?php /* Nói thẳng đây là hàng mẫu. Không có câu này thì bốn
                         thẻ trông như cả bộ, và nút bên dưới hoá ra thừa. */ ?>
                <p class="cdet__picks-note">
                    Vài mẫu tiêu biểu. Cả bộ nằm ở trang danh mục, nơi có bộ lọc
                    theo dáng gọng, chất liệu và tính năng tròng.
                </p>
            </div>

            <?php /* THẺ DÙNG CHUNG VỚI TRANG CHỦ VÀ TRANG DANH MỤC —
                     _layout/product-card.php in ra <li>, nên vỏ phải là <ul>. */ ?>
            <ul class="cdet__grid" role="list">
                <?php foreach ($products as $i => $item): ?>
                    <?php partial('_layout/product-card', [
                        'product' => $item,
                        'eager'   => $i < 2,
                    ]); ?>
                <?php endforeach; ?>
            </ul>

            <a class="cdet__more" href="<?= e($catalogUrl) ?>">
                Xem tất cả <?= (int) $total ?> sản phẩm
                <?= icon('arrow-right', 'cdet__more-ico', 18) ?>
            </a>
        </section>
    <?php endif; ?>

    <!-- ── Lọc nhanh trong bộ ──────────────────────────────────────────── -->
    <?php if ($shapes !== []): ?>
        <section class="cdet__ways" aria-labelledby="cdet-ways-title">
            <h2 id="cdet-ways-title" class="cdet__h3">Lọc nhanh trong bộ</h2>
            <?php /* Mỗi chip là danh mục ĐÃ bật SẴN hai tiêu chí: bộ này và
                     dáng gọng đó. Đây là việc duy nhất trang này làm mà một
                     đường /san-pham?collection= trơn không làm được — nó tiết
                     kiệm cho người dùng đúng một cú bấm mò trong cột lọc. */ ?>
            <ul class="cdet__chips" role="list">
                <?php foreach ($shapes as $shape): ?>
                    <li>
                        <a class="cdet__chip" href="<?= e($shape['url']) ?>">
                            <?= e($shape['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- ── Bộ sưu tập khác ─────────────────────────────────────────────── -->
    <?php if ($others !== []): ?>
        <section class="cdet__others" aria-labelledby="cdet-others-title">
            <h2 id="cdet-others-title" class="cdet__h3">Bộ sưu tập khác</h2>

            <ul class="cdet__olist" role="list">
                <?php foreach ($others as $o): ?>
                    <?php $oCover = CollectionModel::cover($o); ?>
                    <li class="cdet__ocard">
                        <a class="cdet__olink" href="/bo-suu-tap/<?= e(rawurlencode($o['slug'])) ?>">
                            <?php if ($oCover !== ''): ?>
                                <img class="cdet__oimg" src="<?= e(asset($oCover)) ?>" alt=""
                                     width="360" height="240" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="cdet__oimg cdet__oimg--empty" aria-hidden="true">
                                    <?= icon('glasses', '', 28) ?>
                                </span>
                            <?php endif; ?>

                            <span class="cdet__oname"><?= e($o['name']) ?></span>

                            <?php if (!empty($o['tagline'])): ?>
                                <span class="cdet__otag"><?= e($o['tagline']) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</article>
