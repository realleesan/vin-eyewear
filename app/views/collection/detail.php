<?php

/**
 * collection/detail.php — chi tiết một bộ sưu tập (/bo-suu-tap/{slug}).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TRANG NÀY KHÔNG PHẢI MỘT TRANG DANH MỤC THU NHỎ
 *
 * Nó kể về bộ, cho SO SÁNH các mẫu, rồi giao người xem cho
 * /san-pham?collection=<slug> hoặc thẳng sang trang một sản phẩm. Ranh giới ba
 * lớp thông tin và lý do trang này đáng tồn tại: xem đầu CollectionController.
 *
 * Hệ quả cho người sửa file này: KHÔNG thêm nút thêm-vào-giỏ, không ô chọn
 * phương án, không phân trang, không ô sắp xếp. Bảng so sánh trả lời câu "mẫu
 * nào hợp tôi"; trang sản phẩm trả lời câu "tôi mua cái này". Trộn hai câu vào
 * một trang là cách chắc chắn để cả hai cùng trả lời dở.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI KHỐI ĐỀU TỰ BIẾN MẤT KHI CHƯA CÓ DỮ LIỆU
 *
 * Kho hàng thật thì phần lớn cột thông số còn trống, và một bộ mới tạo thì
 * chưa có câu chuyện, chưa có bảng màu, chưa có FAQ. Không khối nào ở đây in
 * tiêu đề rồi để trống bên dưới: thiếu dữ liệu thì cả khối không ra đời. Nhờ
 * vậy bộ nhập đủ và bộ mới tạo dùng chung một file mà cả hai đều trông xong.
 *
 * Nhận từ controller: xem danh sách khoá ở CollectionController::show().
 * ─────────────────────────────────────────────────────────────────────────────
 */

$cover = CollectionModel::cover($collection);

/* Cùng luật với trang danh sách: chỉ THÁNG/NĂM. Bộ sưu tập là chuyện theo
   mùa, ngày đầy đủ gợi ý một sự kiện diễn ra đúng hôm đó — không có thật. */
$when = '';
if (!empty($collection['launched_at'])
    && preg_match('/^(\d{4})-(\d{2})-/', (string) $collection['launched_at'], $m)) {
    $when = 'Lên kệ ' . $m[2] . '/' . $m[1];
}

/* Đường về danh mục đã lọc sẵn — dựng MỘT LẦN rồi dùng lại ở ba nút. Ba chỗ
   tự nối chuỗi riêng là ba chỗ có thể lệch nhau về sau. */
$catalogUrl = '/san-pham?' . http_build_query(['collection' => $collection['slug']]);

/* Địa chỉ ĐÓNG ngăn kéo: chính trang này, bỏ ?mau=. Nút ✕ và lớp nền mờ đều
   trỏ vào đây — không có JavaScript nào tham gia. */
$dongUrl = currentUrlWithout(['mau']);

/*
 * `story` tách thành đoạn theo DÒNG TRỐNG, không phải theo mỗi dấu xuống dòng.
 *
 * Ô nhập trong khu quản trị là <textarea>, nên người viết xuống dòng cả khi
 * chỉ muốn ngắt cho dễ đọc trong ô. Cắt theo từng dòng thì một đoạn văn bị
 * băm thành năm thẻ <p> — trông như năm ý rời rạc.
 */
$story = trim((string) ($collection['story'] ?? ''));
$paras = $story === '' ? [] : preg_split('/\R\s*\R/', $story);

/* Dòng xuất xứ: bốn ô, ô nào trống thì bỏ hẳn chứ không in nhãn suông. */
$meta = array_filter([
    'Thương hiệu' => $collection['brand'] ?? null,
    'Dòng'        => $collection['product_line'] ?? null,
    'Thiết kế'    => $collection['designed_in'] ?? null,
    'Sản xuất'    => $collection['made_in'] ?? null,
], static fn ($v) => trim((string) $v) !== '');

/* Khoảng giá: một số khi cả bộ cùng giá, hai số khi khác nhau. In "từ X – X"
   với hai số bằng nhau là thừa và trông như lỗi làm tròn. */
$giaText = '';
if ($minPrice !== null) {
    $giaText = ($maxPrice !== null && $maxPrice > $minPrice)
        ? money($minPrice) . ' – ' . money($maxPrice)
        : 'từ ' . money($minPrice);
}
?>

<?php partial('_layout/page-head', [
    'head_crumbs' => [
        ['label' => 'Bộ sưu tập', 'url' => '/bo-suu-tap'],
        ['label' => $collection['name']],
    ],
]); ?>

<?php /* Không truyền head_title: <h1> là tên bộ trong khối giới thiệu ngay
         dưới. Thêm một dải tiêu đề nữa là nói tên bộ hai lần và đẩy ảnh
         lookbook xuống khỏi màn hình đầu. */ ?>

<article class="cdet">

    <!-- ══════════ LỚP 1 · GIỚI THIỆU BỘ ══════════ -->
    <section class="cdet__hero">
        <div class="cdet__media">
            <?php if ($cover !== ''): ?>
                <?php /* KHÔNG loading="lazy": ảnh nằm ngay trong màn hình đầu,
                         hoãn tải nó chỉ làm trang trông chậm hơn. */ ?>
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
            <?php if (!empty($collection['season_code']) || $when !== '' || !empty($collection['season_label'])): ?>
                <p class="cdet__season">
                    <?php if (!empty($collection['season_code'])): ?>
                        <span class="cdet__season-tag"><?= e($collection['season_code']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($collection['season_label'])): ?>
                        <span><?= e($collection['season_label']) ?></span>
                    <?php endif; ?>
                    <?php if ($when !== ''): ?>
                        <?php if (!empty($collection['season_label'])): ?>
                            <span class="cdet__dot" aria-hidden="true">·</span>
                        <?php endif; ?>
                        <span><?= e($when) ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <h1 class="cdet__name"><?= e($collection['name']) ?></h1>

            <?php if (!empty($collection['tagline'])): ?>
                <p class="cdet__tagline"><?= e($collection['tagline']) ?></p>
            <?php endif; ?>

            <?php if (!empty($collection['intro'])): ?>
                <p class="cdet__intro"><?= e($collection['intro']) ?></p>
            <?php endif; ?>

            <?php if ($meta !== []): ?>
                <dl class="cdet__meta">
                    <?php foreach ($meta as $nhan => $giaTri): ?>
                        <div class="cdet__meta-row">
                            <dt><?= e($nhan) ?></dt>
                            <dd><?= e((string) $giaTri) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <?php
            /*
             * Quy mô và khoảng giá — thứ trang danh mục chỉ trả lời được SAU
             * khi người dùng đã bấm vào. Nó đứng ngay cạnh nút để người ta
             * biết mình sắp bấm vào cái gì.
             *
             * Bộ chưa có hàng thì cả khối biến mất chứ không in "0 sản phẩm":
             * câu đó không mời ai bấm tiếp.
             */
            ?>
            <?php if ($total > 0): ?>
                <p class="cdet__stats">
                    <span class="cdet__stat"><?= (int) $total ?> mẫu</span>
                    <?php if ($skuCount > $total): ?>
                        <span class="cdet__dot" aria-hidden="true">·</span>
                        <span class="cdet__stat"><?= (int) $skuCount ?> phối màu</span>
                    <?php endif; ?>
                    <?php if ($giaText !== ''): ?>
                        <span class="cdet__dot" aria-hidden="true">·</span>
                        <span class="cdet__stat"><?= e($giaText) ?></span>
                    <?php endif; ?>
                </p>

                <?php if (!empty($collection['launch_offer'])): ?>
                    <p class="cdet__offer">
                        <?= icon('percent', 'cdet__offer-ico', 16) ?>
                        <?= e($collection['launch_offer']) ?>
                    </p>
                <?php endif; ?>

                <div class="cdet__cta-row">
                    <a class="cdet__cta" href="<?= e($catalogUrl) ?>">
                        Xem <?= (int) $total ?> sản phẩm của bộ
                        <?= icon('arrow-right', 'cdet__cta-ico', 18) ?>
                    </a>
                    <a class="cdet__cta cdet__cta--ghost" href="/thu-ar">
                        <?= icon('scan-eye', 'cdet__cta-ico', 18) ?>
                        Thử ảo trên khuôn mặt
                    </a>
                </div>
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

    <!-- ══════════ LỚP 1 · DẢI LOOKBOOK ══════════ -->
    <?php if ($gallery !== []): ?>
        <?php
        /*
         * Ảnh SAU ảnh đại diện. Ảnh đại diện đã đứng ở khối giới thiệu ngay
         * trên, nên in lại nó ở đây là cùng một tấm hiện hai lần cách nhau một
         * màn hình — CollectionModel::gallery() cắt sẵn phần tử đầu.
         *
         * Lưới tự giãn theo số ảnh chứ không cố định số cột: một bộ có ba ảnh
         * và một bộ có mười lăm ảnh dùng chung khối này, mà ép ba cột thì bộ
         * mười lăm ảnh thành năm hàng cao ngoằng.
         */
        ?>
        <section class="cdet__look" aria-label="Ảnh bộ sưu tập <?= e($collection['name']) ?>">
            <ul class="cdet__look-grid" role="list">
                <?php foreach ($gallery as $i => $anh): ?>
                    <li class="cdet__look-item">
                        <?php /* loading="lazy" cho MỌI ảnh ở đây: cả dải nằm dưới
                                 màn hình đầu, khác ảnh đại diện phía trên. */ ?>
                        <img class="cdet__look-img" src="<?= e(asset($anh)) ?>"
                             alt="<?= e($collection['name']) ?> — ảnh <?= $i + 2 ?>"
                             width="600" height="450" loading="lazy" decoding="async">
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- ══════════ LỚP 1 · CÂU CHUYỆN, BẢNG MÀU, NHẬN DIỆN ══════════ -->
    <?php if ($paras !== [] || $palette !== [] || $signature !== []): ?>
        <section class="cdet__story-wrap" aria-labelledby="cdet-story-title">
            <?php if ($paras !== []): ?>
                <div class="cdet__story">
                    <p class="cdet__eyebrow">Nguồn cảm hứng</p>
                    <h2 id="cdet-story-title" class="cdet__h2">Về bộ sưu tập này</h2>
                    <?php foreach ($paras as $para): ?>
                        <p class="cdet__para"><?= e(trim($para)) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($palette !== [] || $signature !== [] || !empty($collection['design_style'])): ?>
                <aside class="cdet__aside">
                    <?php if (!empty($collection['design_style'])): ?>
                        <p class="cdet__eyebrow">Ngôn ngữ thiết kế</p>
                        <p class="cdet__aside-lead"><?= e($collection['design_style']) ?></p>
                    <?php endif; ?>

                    <?php if ($palette !== []): ?>
                        <p class="cdet__eyebrow">Bảng màu chủ đạo</p>
                        <ul class="cdet__palette" role="list">
                            <?php foreach ($palette as $mau): ?>
                                <?php
                                /* Mã màu đi THẲNG vào style, nên phải chặn ở
                                   đây: cột do form quản trị ghi, mà một chuỗi
                                   như "red; background-image:url(...)" lọt vào
                                   thuộc tính style là một lối chèn CSS. Chỉ
                                   nhận đúng dạng #rgb hoặc #rrggbb. */
                                $ma = (string) ($mau['ma_mau'] ?? '');
                                if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $ma)) {
                                    continue;
                                }
                                ?>
                                <li class="cdet__swatch">
                                    <span class="cdet__chip-color" style="background: <?= e($ma) ?>"></span>
                                    <span class="cdet__swatch-name"><?= e((string) ($mau['ten'] ?? $ma)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($signature !== []): ?>
                        <p class="cdet__eyebrow">Chi tiết nhận diện</p>
                        <ul class="cdet__sign" role="list">
                            <?php foreach ($signature as $cau): ?>
                                <li class="cdet__sign-item">
                                    <?= icon('check', 'cdet__sign-ico', 18) ?>
                                    <span><?= e((string) $cau) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </aside>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- ══════════ LỚP 1 · BỘ NÀY HỢP VỚI AI ══════════ -->
    <?php if ($audience !== []): ?>
        <section class="cdet__aud-wrap" aria-labelledby="cdet-aud-title">
            <p class="cdet__eyebrow" id="cdet-aud-title">Bộ này hợp với ai</p>
            <ul class="cdet__aud" role="list">
                <?php foreach ($audience as $o): ?>
                    <li class="cdet__aud-card">
                        <?php if (!empty($o['tieu_de'])): ?>
                            <p class="cdet__aud-label"><?= e((string) $o['tieu_de']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($o['gia_tri'])): ?>
                            <p class="cdet__aud-value"><?= e((string) $o['gia_tri']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($o['ghi_chu'])): ?>
                            <p class="cdet__aud-note"><?= e((string) $o['ghi_chu']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- ══════════ LỚP 2 · BẢNG SO SÁNH ══════════ -->
    <?php if ($products !== []): ?>
        <section class="cdet__cmp" aria-labelledby="cdet-cmp-title">
            <div class="cdet__cmp-head">
                <div>
                    <h2 id="cdet-cmp-title" class="cdet__h2">So sánh <?= count($products) ?> mẫu trong bộ</h2>
                    <p class="cdet__cmp-note">
                        Kích thước ghi theo chuẩn rộng tròng – cầu kính – dài càng.
                        Bấm một dòng để mở đầy đủ thông số, phối màu và giá kèm tròng.
                    </p>
                </div>
                <a class="cdet__cmp-all" href="<?= e($catalogUrl) ?>">
                    Mở trang danh mục có bộ lọc
                    <?= icon('arrow-right', '', 16) ?>
                </a>
            </div>

            <?php
            /*
             * BẢNG THẬT (<table>), không phải lưới thẻ giả dạng bảng.
             *
             * Đây là dữ liệu hai chiều: sáu mẫu × tám thuộc tính, và cả điểm
             * của trang là đọc DỌC theo một cột để so. Trình đọc màn hình cần
             * <th scope> để đọc được như thế, và người dùng bàn phím cần thứ
             * tự ô đúng. Một mớ <div> có display:table chỉ trông giống bảng.
             */
            ?>
            <div class="cdet__table-wrap">
                <table class="cdet__table">
                    <caption class="cdet__caption">
                        Thông số so sánh <?= count($products) ?> mẫu của bộ <?= e($collection['name']) ?>
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Mẫu</th>
                            <th scope="col">Phân loại</th>
                            <th scope="col">Kích thước</th>
                            <th scope="col">Dáng</th>
                            <th scope="col">Chất liệu</th>
                            <th scope="col" class="num">Nặng</th>
                            <th scope="col">Tròng</th>
                            <th scope="col" class="num">Giá</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <?php
                            $size    = EyewearSpecs::size($p);
                            $coKey   = EyewearSpecs::sizeKey($p);
                            $loai    = EyewearSpecs::typeLabel($p);
                            $phu     = EyewearSpecs::coatings($p);
                            $dangMo  = $open !== null && $open['slug'] === $p['slug'];
                            $mauUrl  = currentUrlWithout(['mau'])
                                     . (str_contains(currentUrlWithout(['mau']), '?') ? '&' : '?')
                                     . 'mau=' . rawurlencode((string) $p['slug']);

                            /* Tròng: hai tính năng người mua hỏi trước, rồi mới
                               tới lớp phủ. Cắt ở hai mục để cột không dài gấp
                               ba các cột khác — bản đầy đủ nằm trong ngăn kéo. */
                            $trong = [];
                            if (!empty($p['is_polarized']))     { $trong[] = 'Phân cực'; }
                            if (!empty($p['is_photochromic']))  { $trong[] = 'Đổi màu'; }
                            foreach (array_slice($phu, 0, 2) as $nhan) { $trong[] = $nhan; }
                            ?>
                            <tr class="cdet__row<?= $dangMo ? ' cdet__row--on' : '' ?>">
                                <th scope="row" class="cdet__cell-name">
                                    <?php
                                    /* CẢ Ô là liên kết mở ngăn kéo, không phải
                                       riêng cái tên: dòng bảng cao 68px mà đích
                                       bấm chỉ rộng bằng chữ thì trên máy tính
                                       bảng gần như không trúng. */
                                    ?>
                                    <a class="cdet__cell-link" href="<?= e($mauUrl) ?>">
                                        <span class="cdet__thumb">
                                            <?php $anh = ProductModel::thumb($p); ?>
                                            <?php if ($anh !== ''): ?>
                                                <img src="<?= e(asset($anh)) ?>" alt="" width="54" height="38" loading="lazy">
                                            <?php else: ?>
                                                <?= icon('glasses', '', 22) ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="cdet__cell-text">
                                            <span class="cdet__cell-title"><?= e($p['name']) ?></span>
                                            <span class="cdet__cell-sku"><?= e($p['sku']) ?></span>
                                        </span>
                                    </a>
                                </th>
                                <td><?= $loai !== '' ? e($loai) : '' ?></td>
                                <td>
                                    <?php if ($size !== ''): ?>
                                        <span class="cdet__size"><?= e($size) ?></span>
                                    <?php endif; ?>
                                    <?php if ($coKey !== null): ?>
                                        <span class="cdet__badge"><?= e($coKey) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e((string) ($p['frame_shape'] ?? '')) ?></td>
                                <td><?= e((string) ($p['material'] ?? '')) ?></td>
                                <td class="num"><?= !empty($p['weight_g']) ? e($p['weight_g'] . ' g') : '' ?></td>
                                <td class="cdet__cell-lens"><?= e(implode(' · ', $trong)) ?></td>
                                <td class="num">
                                    <a class="cdet__price" href="<?= e($mauUrl) ?>">
                                        <?= e(money((int) $p['price'])) ?>
                                        <?= icon('chevron-down', 'cdet__price-ico', 16) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($shapes !== []): ?>
                <div class="cdet__ways">
                    <span class="cdet__ways-label">Lọc nhanh trong bộ</span>
                    <ul class="cdet__chips" role="list">
                        <?php foreach ($shapes as $shape): ?>
                            <li><a class="cdet__chip" href="<?= e($shape['url']) ?>"><?= e($shape['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- ══════════ LỚP 3 · CHỌN ĐÚNG CỠ ══════════ -->
    <?php if ($sizeGuide !== [] && $products !== []): ?>
        <section class="cdet__size-wrap" aria-labelledby="cdet-size-title">
            <div class="cdet__guide">
                <p class="cdet__eyebrow">Trước khi đặt</p>
                <h2 id="cdet-size-title" class="cdet__h2">Chọn đúng cỡ gọng</h2>
                <p class="cdet__guide-lead">
                    Cách nhanh nhất là lật càng cặp kính bạn đang đeo lên: ba con số
                    in bên trong chính là chuẩn dùng trong bảng so sánh phía trên.
                </p>

                <ol class="cdet__steps" role="list">
                    <?php foreach ($sizeGuide as $i => $buoc): ?>
                        <li class="cdet__step">
                            <span class="cdet__step-n"><?= $i + 1 ?></span>
                            <span class="cdet__step-body">
                                <span class="cdet__step-title"><?= e((string) $buoc['title']) ?></span>
                                <span class="cdet__step-text"><?= e((string) $buoc['body']) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <div class="cdet__panel">
                <p class="cdet__panel-head">Quy đổi cỡ và gợi ý dáng mặt</p>
                <table class="cdet__table cdet__table--sm">
                    <thead>
                        <tr>
                            <th scope="col">Cỡ</th>
                            <th scope="col">Tổng rộng gọng</th>
                            <th scope="col">Hợp dáng mặt</th>
                            <th scope="col">Mẫu trong bộ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sizeTable as $dong): ?>
                            <tr>
                                <th scope="row"><span class="cdet__badge"><?= e($dong['key']) ?></span></th>
                                <td><span class="cdet__size"><?= e($dong['range']) ?></span></td>
                                <td><?= e($dong['faces']) ?></td>
                                <td>
                                    <?php if ($dong['models'] === []): ?>
                                        <?php /* Cỡ này bộ không có mẫu nào. Nói ra chứ
                                                 không để trống: "bộ này không có cỡ S"
                                                 cũng là thông tin, và là thông tin cần
                                                 biết TRƯỚC khi đặt. */ ?>
                                        <span class="cdet__none">bộ này không có</span>
                                    <?php else: ?>
                                        <?php foreach ($dong['models'] as $k => $mau): ?>
                                            <?= $k > 0 ? ', ' : '' ?><a href="/san-pham/<?= e(rawurlencode($mau['slug'])) ?>"><?= e($mau['name']) ?></a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="cdet__panel-foot">
                    Không chắc dáng mặt mình thuộc nhóm nào?
                    <a href="/thu-ar">Thử ảo trên khuôn mặt</a> đo giúp bạn ngay trên
                    trình duyệt, không cần cài gì.
                </p>
            </div>
        </section>
    <?php endif; ?>

    <!-- ══════════ LỚP 3 · GỌNG THEO DÁNG MẶT ══════════ -->
    <?php if ($faceTable !== []): ?>
        <section class="cdet__face" aria-labelledby="cdet-face-title">
            <h2 id="cdet-face-title" class="cdet__h3">Gọng nào hợp dáng mặt nào</h2>
            <ul class="cdet__face-list" role="list">
                <?php foreach ($faceTable as $dong): ?>
                    <li class="cdet__face-row">
                        <span class="cdet__face-name"><?= e($dong['label']) ?></span>
                        <span class="cdet__face-models">
                            <?php foreach ($dong['models'] as $k => $mau): ?>
                                <?= $k > 0 ? ', ' : '' ?><a href="/san-pham/<?= e(rawurlencode($mau['slug'])) ?>"><?= e($mau['name']) ?></a>
                            <?php endforeach; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <!-- ══════════ LỚP 3 · BẢO QUẢN + CÂU HỎI THƯỜNG GẶP ══════════ -->
    <?php if ($care !== [] || $faqs !== []): ?>
        <section class="cdet__help">
            <?php if ($care !== []): ?>
                <div class="cdet__care">
                    <p class="cdet__eyebrow">Giữ kính bền</p>
                    <ul class="cdet__care-list" role="list">
                        <?php foreach ($care as $cau): ?>
                            <li class="cdet__care-item">
                                <?= icon('shield', 'cdet__care-ico', 18) ?>
                                <span><?= e((string) $cau) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($faqs !== []): ?>
                <div class="cdet__faq-wrap">
                    <p class="cdet__eyebrow">Câu hỏi thường gặp</p>
                    <?php
                    /*
                     * <details> chứ không phải nút + JavaScript.
                     *
                     * Trình duyệt đã đóng mở sẵn, đọc được bằng bàn phím và
                     * trình đọc màn hình, và Ctrl+F của trình duyệt tìm thấy
                     * cả chữ đang gấp lại. Không file JS nào của dự án phải
                     * biết tới khối này.
                     *
                     * Câu ĐẦU mở sẵn: nó thường là câu quyết định (lắp được
                     * độ cận không), và một cột toàn tiêu đề gấp kín trông
                     * như trang chưa tải xong.
                     */
                    ?>
                    <div class="cdet__faq">
                        <?php foreach ($faqs as $i => $faq): ?>
                            <details class="cdet__q"<?= $i === 0 ? ' open' : '' ?>>
                                <summary class="cdet__q-head">
                                    <span><?= e($faq['question']) ?></span>
                                    <?= icon('chevron-down', 'cdet__q-ico', 16) ?>
                                </summary>
                                <p class="cdet__q-body"><?= nl2br(e($faq['answer'])) ?></p>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- ══════════ CTA CUỐI ══════════ -->
    <?php if ($total > 0): ?>
        <section class="cdet__end">
            <div class="cdet__end-text">
                <h2 class="cdet__end-title">Chốt một mẫu, hay ra cửa hàng thử?</h2>
                <p class="cdet__end-lead">
                    Thử tại chỗ thì đo mắt và cân gọng luôn trong một buổi.
                    <?php if (!empty($collection['channels'])): ?>
                        <?= e($collection['channels']) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="cdet__end-btns">
                <a class="cdet__end-btn cdet__end-btn--main" href="<?= e($catalogUrl) ?>">Xem <?= (int) $total ?> sản phẩm</a>
                <a class="cdet__end-btn" href="/thu-ar">Thử ảo</a>
                <a class="cdet__end-btn" href="/gioi-thieu#co-so">Tìm cơ sở gần bạn</a>
            </div>
        </section>
    <?php endif; ?>

    <!-- ══════════ BỘ SƯU TẬP KHÁC ══════════ -->
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

<?php if ($open !== null): ?>
    <?php partial('collection/_drawer', [
        'product'  => $open,
        'variants' => $openVariants,
        'offer'    => $collection['launch_offer'] ?? null,
        'dongUrl'  => $dongUrl,
    ]); ?>
<?php endif; ?>
