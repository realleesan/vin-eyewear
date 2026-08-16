<?php

/**
 * product/index.php — danh sách sản phẩm.
 *
 * Dựng theo "Vin Eyewear Category.dc.html" (Claude Design):
 *
 *   đầu trang nền hồng phấn (breadcrumb · tiêu đề · mô tả)
 *   → hai cột: cột lọc 280px dính theo cuộn | lưới sản phẩm 3 cột
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÁC BẢN THIẾT KẾ: LỌC BẰNG LIÊN KẾT, KHÔNG BẰNG JAVASCRIPT
 *
 * Bản thiết kế giữ trạng thái lọc trong `this.state` và lọc ngay trong trình
 * duyệt. Ở đây mỗi huy hiệu lọc là một <a> trỏ tới chính trang này với query
 * string đã thêm/bớt giá trị đó:
 *   - Không có JavaScript vẫn lọc được.
 *   - Mỗi trạng thái lọc là một URL chia sẻ được và quay lại được bằng nút Back.
 *   - Server chỉ trả về đúng 12 sản phẩm của trang, không đẩy cả kho về máy khách.
 *
 * Nhìn thì không phân biệt được: <a> ở đây mang đúng bộ style của <button>
 * trong bản thiết kế.
 * ─────────────────────────────────────────────────────────────────────────────
 */

/**
 * Nhãn tiếng Việt cho cột `gender` — DB lưu male/female/unisex/kids.
 *
 * Thứ tự các khoá ở đây cũng là thứ tự huy hiệu hiện ra. Facet lấy từ DB
 * sắp theo bảng chữ cái nên nó cho ra "female, male, unisex" -> "Nữ, Nam,
 * Unisex"; bản thiết kế xếp "Nam, Nữ, Unisex".
 */
$genderLabels = ['male' => 'Nam', 'female' => 'Nữ', 'unisex' => 'Unisex', 'kids' => 'Trẻ em'];

// Giữ đúng thứ tự của $genderLabels, và giữ lại cả giá trị lạ chưa có nhãn
// (nếu ai đó thêm gender mới vào DB) bằng cách nối chúng vào cuối.
$genderFacet = array_values(array_intersect(array_keys($genderLabels), $facets['genders']));
$genderFacet = array_merge($genderFacet, array_diff($facets['genders'], $genderFacet));

/**
 * Dựng URL giữ nguyên bộ lọc hiện tại, chỉ đổi/bỏ vài tham số.
 *
 * So sánh với '' và null chứ KHÔNG dùng empty(): 'price' => 0 là khoảng giá
 * đầu tiên ("Dưới 2 triệu"), empty(0) là true nên nó sẽ bị vứt khỏi URL và ô
 * đó không bao giờ chọn được.
 */
$buildUrl = static function (array $patch = []) use ($filters): string {
    $clean = [];

    foreach (array_merge($filters, $patch) as $key => $value) {
        if (is_array($value)) {
            if ($value !== []) {
                $clean[$key] = array_values($value);
            }
            continue;
        }

        if ($value === null || $value === '') {
            continue;
        }

        $clean[$key] = $value;
    }

    // 'newest' là mặc định -> bỏ khỏi URL cho gọn
    if (($clean['sort'] ?? '') === 'newest') {
        unset($clean['sort']);
    }

    return '/san-pham' . ($clean === [] ? '' : '?' . http_build_query($clean));
};

/** URL bật/tắt một giá trị trong nhóm lọc chọn-nhiều. Luôn về trang 1. */
$toggleUrl = static function (string $key, string $value) use ($filters, $buildUrl): string {
    $current = $filters[$key];

    $next = in_array($value, $current, true)
        ? array_values(array_diff($current, [$value]))
        : array_merge($current, [$value]);

    return $buildUrl([$key => $next, 'page' => null]);
};

/**
 * In các <input type="hidden"> mang bộ lọc hiện tại qua một form GET.
 *
 * Hai form trên trang (ô sắp xếp, ô tìm thương hiệu) chỉ đổi ĐÚNG MỘT tham
 * số. Không mang phần còn lại theo thì bấm vào là mất sạch bộ lọc đang bật mà
 * chẳng có gì báo.
 */
$hiddenFilters = static function (array $except = []) use ($filters): void {
    foreach ($filters as $key => $value) {
        if (in_array($key, $except, true)) {
            continue;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                printf('<input type="hidden" name="%s[]" value="%s">', e($key), e((string) $item));
            }
            continue;
        }

        if ($value === null || $value === '') {
            continue;
        }

        printf('<input type="hidden" name="%s" value="%s">', e($key), e((string) $value));
    }
};

/** Một nhóm huy hiệu lọc (Dáng gọng · Chất liệu · Đối tượng). */
$chipGroup = static function (string $key, string $legend, array $options, array $labels = []) use ($filters, $toggleUrl): void {
    if ($options === []) {
        return;
    }
    ?>
    <div class="pfacet">
        <p class="pfacet__legend"><?= e($legend) ?></p>
        <div class="pfacet__chips">
            <?php foreach ($options as $value): ?>
                <?php $on = in_array($value, $filters[$key], true); ?>
                <?php /* aria-current chứ không phải aria-pressed: aria-pressed
                         chỉ hợp lệ trên nút, còn đây là <a>. Kèm một câu chỉ
                         trình đọc màn hình nghe được — không có nó thì trạng
                         thái "đang chọn" chỉ nằm ở màu nền, người không nhìn
                         thấy màu sẽ nghe hai huy hiệu bật và tắt giống hệt nhau. */ ?>
                <a class="pchip<?= $on ? ' is-on' : '' ?>"
                   href="<?= e($toggleUrl($key, (string) $value)) ?>"
                   <?= $on ? 'aria-current="true"' : '' ?>
                   rel="nofollow"><?= e($labels[$value] ?? $value) ?><?php
                    if ($on): ?><span class="sr-only"> — đang lọc, bấm để bỏ</span><?php endif;
                ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
};

$hasFacetFilter = $filters['brand'] || $filters['shape'] || $filters['material']
                || $filters['gender'] || $filters['price'] !== null;

// Danh sách thương hiệu đã lọc theo ô "Tìm thương hiệu".
// mb_stripos để gõ thường vẫn khớp tên viết hoa.
$brandList = $facets['brands'];
if ($filters['bq'] !== '') {
    $brandList = array_values(array_filter(
        $brandList,
        static fn ($b) => mb_stripos((string) $b, $filters['bq']) !== false
    ));
}
?>

<?php
/*
 * Đầu trang nền hồng phấn — dùng chung với trang Liên hệ, vì hai bản thiết kế
 * vẽ khối này giống hệt nhau. Đủ cả ba phần: breadcrumb, tiêu đề, mô tả.
 *
 * $lead ở đây CÙNG một chuỗi với thẻ <meta description> (xem ProductController)
 * — đừng viết lại một câu khác cho đẹp, hai chỗ lệch nhau thì kết quả tìm kiếm
 * hứa một đằng, trang mở ra một nẻo.
 */
partial('_layout/page-head', [
    'head_crumbs' => $crumbs,
    'head_title'  => $heading,
    'head_lead'   => $lead,
]);
?>

<!-- ============================================================
     THÂN TRANG — cột lọc + lưới kết quả
     ============================================================ -->
<section class="catbody">
    <div class="catbody__grid">

        <!-- ────────────────────────────────────────────────────
             CỘT LỌC
             <details> để màn hình hẹp thu gọn được mà không cần
             JavaScript; từ 1101px CSS ép luôn mở và bỏ nút bấm.
             ──────────────────────────────────────────────────── -->
        <details class="cfilter" <?= $activeCount > 0 ? 'open' : '' ?>>
            <summary class="cfilter__toggle">
                <?= icon('filter', '', 16) ?>
                Bộ lọc
                <?php if ($activeCount > 0): ?>
                    <span class="cfilter__count"><?= $activeCount ?></span>
                <?php endif; ?>
            </summary>

            <div class="cfilter__panel">

                <div class="cfilter__head">
                    <p class="cfilter__title">Bộ lọc</p>
                    <?php /* Giữ nguyên danh mục và từ khoá: danh mục là CHÍNH
                             trang này (tiêu đề trên kia đọc từ nó), xoá nó đi
                             thì "xoá tất cả" hoá ra chuyển sang trang khác.

                             Chưa lọc gì thì làm mờ và tắt hẳn thay vì giấu đi:
                             bản thiết kế luôn vẽ nút này, mà giấu rồi hiện lại
                             sẽ khiến hàng tiêu đề nhảy chiều cao mỗi lần bấm
                             tiêu chí lọc đầu tiên. */ ?>
                    <?php if ($hasFacetFilter): ?>
                        <a class="cfilter__clear" rel="nofollow" href="<?= e($buildUrl([
                            'brand' => [], 'shape' => [], 'material' => [],
                            'gender' => [], 'price' => null, 'page' => null,
                        ])) ?>">Xoá tất cả</a>
                    <?php else: ?>
                        <span class="cfilter__clear is-off" aria-hidden="true">Xoá tất cả</span>
                    <?php endif; ?>
                </div>

                <?php $chipGroup('shape',    'Dáng gọng', $facets['shapes']); ?>
                <?php $chipGroup('material', 'Chất liệu', $facets['materials']); ?>

                <!-- Thương hiệu — ô tìm + danh sách tick -->
                <div class="pfacet">
                    <p class="pfacet__legend" id="lg-brand">Thương hiệu</p>

                    <form class="pfacet__search" method="get" action="/san-pham" data-brand-filter>
                        <?php $hiddenFilters(['bq', 'page']); ?>
                        <label class="sr-only" for="f-bq">Tìm trong danh sách thương hiệu</label>
                        <input class="pfacet__input" type="text" id="f-bq" name="bq"
                               value="<?= e($filters['bq']) ?>" placeholder="Tìm thương hiệu"
                               autocomplete="off">
                        <button type="submit" class="pfacet__go">Lọc</button>
                    </form>

                    <div class="pfacet__list" role="group" aria-labelledby="lg-brand">
                        <?php foreach ($brandList as $brand): ?>
                            <?php $on = in_array($brand, $filters['brand'], true); ?>
                            <a class="pcheck<?= $on ? ' is-on' : '' ?>"
                               href="<?= e($toggleUrl('brand', (string) $brand)) ?>"
                               <?= $on ? 'aria-current="true"' : '' ?>
                               rel="nofollow"
                               data-brand="<?= e(mb_strtolower((string) $brand)) ?>">
                                <span class="pcheck__box" aria-hidden="true"><?= $on ? '✓' : '' ?></span>
                                <span class="pcheck__label"><?= e($brand) ?></span>
                                <span class="pcheck__count">
                                    <span class="sr-only">có </span><?= (int) ($facets['brandCounts'][$brand] ?? 0) ?><span class="sr-only"> sản phẩm</span>
                                </span>
                                <?php if ($on): ?><span class="sr-only"> — đang lọc, bấm để bỏ</span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>

                        <?php if ($brandList === []): ?>
                            <p class="pfacet__none">Không có thương hiệu nào khớp.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Khoảng giá — chọn một -->
                <div class="pfacet">
                    <p class="pfacet__legend">Khoảng giá</p>
                    <div class="pfacet__list">
                        <?php foreach ($priceRanges as $i => $range): ?>
                            <?php $on = $filters['price'] === $i; ?>
                            <a class="pradio<?= $on ? ' is-on' : '' ?>" rel="nofollow"
                               <?= $on ? 'aria-current="true"' : '' ?>
                               href="<?= e($buildUrl(['price' => $on ? null : $i, 'page' => null])) ?>">
                                <span class="pradio__dot" aria-hidden="true"></span>
                                <span class="pradio__label"><?= e($range['label']) ?></span>
                                <?php if ($on): ?><span class="sr-only"> — đang lọc, bấm để bỏ</span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php $chipGroup('gender', 'Đối tượng', $genderFacet, $genderLabels); ?>
            </div>
        </details>

        <!-- ────────────────────────────────────────────────────
             KẾT QUẢ
             ──────────────────────────────────────────────────── -->
        <div class="catmain">

            <div class="catbar">
                <p class="catbar__count" aria-live="polite">
                    Hiển thị <strong><?= (int) $total ?></strong> sản phẩm<?php
                    /* Hai mẩu dưới đây KHÔNG có trong bản thiết kế, và phải có:
                       ô tìm trên header đổ người dùng vào đúng trang này kèm
                       ?q=…, mà cột lọc bên trái không có ô tìm nào để soi lại
                       mình vừa gõ gì. Không in ra thì kết quả trông như bị
                       lọc ngẫu nhiên. */
                    ?><?php if ($filters['q'] !== ''): ?>
                        cho “<strong><?= e($filters['q']) ?></strong>”
                        <a class="catbar__drop" rel="nofollow" href="<?= e($buildUrl(['q' => null, 'page' => null])) ?>">bỏ từ khoá</a>
                    <?php endif; ?>
                    <?php if ($filters['collection'] !== ''): ?>
                        <?php
                        // Tên đẹp của bộ sưu tập; slug lạ thì hiện thẳng slug còn hơn để trống
                        $collectionName = $filters['collection'];
                        foreach (config('collections') as $c) {
                            if ($c['slug'] === $filters['collection']) {
                                $collectionName = $c['name'];
                                break;
                            }
                        }
                        ?>
                        trong bộ sưu tập <strong><?= e($collectionName) ?></strong>
                        <a class="catbar__drop" rel="nofollow" href="<?= e($buildUrl(['collection' => null, 'page' => null])) ?>">bỏ lọc này</a>
                    <?php endif; ?>
                </p>

                <form class="catsort" method="get" action="/san-pham">
                    <?php $hiddenFilters(['sort', 'page']); ?>
                    <label class="catsort__label" for="f-sort">Sắp xếp theo</label>
                    <span class="catsort__field">
                        <select class="catsort__select" id="f-sort" name="sort">
                            <?php foreach ([
                                'newest'     => 'Mới nhất',
                                'popular'    => 'Bán chạy',
                                'price-asc'  => 'Giá thấp → cao',
                                'price-desc' => 'Giá cao → thấp',
                            ] as $value => $text): ?>
                                <option value="<?= e($value) ?>"<?= $filters['sort'] === $value ? ' selected' : '' ?>><?= e($text) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="catsort__caret" aria-hidden="true">▼</span>
                    </span>
                    <?php /* Ẩn khi có JavaScript (catalog.js đổi ô chọn là gửi
                             luôn). Không có JS thì đây là cách duy nhất để
                             chốt lựa chọn, nên không được bỏ. */ ?>
                    <button type="submit" class="catsort__go">Áp dụng</button>
                </form>
            </div>

            <?php if ($total === 0): ?>
                <div class="catempty">
                    <p class="catempty__title">Chưa có sản phẩm phù hợp</p>
                    <p class="catempty__text">Thử bỏ bớt tiêu chí lọc hoặc xoá tất cả bộ lọc.</p>
                    <a class="catempty__btn" href="<?= e($buildUrl([
                        'brand' => [], 'shape' => [], 'material' => [],
                        'gender' => [], 'price' => null, 'page' => null,
                    ])) ?>">Xoá bộ lọc</a>
                </div>
            <?php else: ?>
                <div class="catgrid">
                    <?php foreach ($products as $p): ?>
                        <?php partial('_layout/product-tile', ['product' => $p]); ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <?php
                    /*
                     * Cửa sổ 5 số quanh trang hiện tại. Bản thiết kế vẽ đúng
                     * ba số (1 2 3) vì dữ liệu mẫu chỉ có 9 sản phẩm; kho thật
                     * có thể hàng chục trang, in hết ra thì dải nút dài hơn cả
                     * lưới sản phẩm.
                     */
                    $from = max(1, min($page - 2, $totalPages - 4));
                    $to   = min($totalPages, max($page + 2, 5));
                    ?>
                    <nav class="catpager" aria-label="Phân trang">
                        <?php if ($page > 1): ?>
                            <a class="catpager__btn" rel="prev nofollow"
                               href="<?= e($buildUrl(['page' => $page - 1])) ?>"
                               aria-label="Trang trước">←</a>
                        <?php endif; ?>

                        <?php for ($i = $from; $i <= $to; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="catpager__btn is-current" aria-current="page"><?= $i ?></span>
                            <?php else: ?>
                                <a class="catpager__btn" rel="nofollow"
                                   href="<?= e($buildUrl(['page' => $i])) ?>"
                                   aria-label="Trang <?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="catpager__btn" rel="next nofollow"
                               href="<?= e($buildUrl(['page' => $page + 1])) ?>"
                               aria-label="Trang sau">→</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
