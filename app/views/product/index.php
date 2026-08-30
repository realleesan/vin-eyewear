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
 * LỌC BẰNG LIÊN KẾT, KHÔNG BẰNG JAVASCRIPT
 *
 * Mỗi huy hiệu lọc là một <a> trỏ tới chính trang này với query string đã
 * thêm/bớt giá trị đó:
 *   - Không có JavaScript vẫn lọc được.
 *   - Mỗi trạng thái lọc là một URL chia sẻ được và quay lại được bằng Back.
 *   - Server chỉ trả về đúng 12 sản phẩm của trang.
 *
 * Nhìn thì không phân biệt được: <a> ở đây mang đúng bộ style của <button>
 * trong bản thiết kế.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG CÒN DANH SÁCH LỰA CHỌN NÀO GÕ TRONG FILE NÀY
 *
 * Bảy nhóm lọc đều nhận nguyên mảng $groups[...] mà ProductModel::catalog()
 * dựng từ hàng đang bán, mỗi mục đã kèm nhãn và số đếm. View chỉ còn việc vẽ.
 * Kho thêm một dáng gọng là cột lọc có thêm huy hiệu; hãng nào bán hết hàng
 * thì huy hiệu của hãng đó tự biến mất — không phải sửa file này.
 */

/**
 * Trạng thái lọc dưới dạng THAM SỐ URL.
 *
 * Khác $filters ở đúng một chỗ: 'price' ở đây là một SỐ (hoặc null) chứ không
 * phải mảng. Trong model khoảng giá là một nhóm như mọi nhóm khác để dùng
 * chung phép đếm động, nhưng trên URL nó vẫn là ?price=2 — đổi thành price[]=2
 * là làm hỏng mọi liên kết đã có người lưu.
 */
$state = $filters;
$state['price'] = $priceIndex;

/**
 * Dựng URL giữ nguyên bộ lọc hiện tại, chỉ đổi/bỏ vài tham số.
 *
 * So sánh với '' và null chứ KHÔNG dùng empty(): 'price' => 0 là khoảng giá
 * đầu tiên ("Dưới 2 triệu"), empty(0) là true nên nó sẽ bị vứt khỏi URL và ô
 * đó không bao giờ chọn được.
 */
$buildUrl = static function (array $patch = []) use ($state): string {
    $clean = [];

    foreach (array_merge($state, $patch) as $key => $value) {
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
$toggleUrl = static function (string $key, string $value) use ($state, $buildUrl): string {
    $current = $state[$key] ?? [];

    $next = in_array($value, $current, true)
        ? array_values(array_diff($current, [$value]))
        : array_merge($current, [$value]);

    return $buildUrl([$key => $next, 'page' => null]);
};

/**
 * Bộ tham số đưa MỌI nhóm lọc về rỗng — dùng cho "Xoá tất cả".
 *
 * Dựng bằng vòng lặp qua ProductFacets::GROUPS chứ không liệt kê tay: thêm một
 * nhóm lọc mới mà quên thêm vào đây thì "Xoá tất cả" xoá gần hết, chừa lại
 * đúng nhóm mới — kiểu lỗi không ai để ý cho tới lúc có người khiếu nại rằng
 * lưới vẫn trống sau khi đã xoá bộ lọc.
 *
 * KHÔNG đụng tới 'category' và 'q': danh mục là CHÍNH trang này (tiêu đề trên
 * kia đọc từ nó), xoá nó đi thì "xoá tất cả" hoá ra chuyển sang trang khác.
 * 'bq' thì có xoá — nó là chữ đang gõ trong ô tìm thương hiệu, để lại thì
 * danh sách hãng vẫn cụt sau khi đã xoá bộ lọc.
 */
$resetPatch = ['bq' => null, 'page' => null];

foreach (ProductFacets::GROUPS as $group) {
    $resetPatch[$group] = [];
}

$resetPatch['price'] = null;

/**
 * In các <input type="hidden"> mang bộ lọc hiện tại qua một form GET.
 *
 * Hai form trên trang (ô sắp xếp, ô tìm thương hiệu) chỉ đổi ĐÚNG MỘT tham
 * số. Không mang phần còn lại theo thì bấm vào là mất sạch bộ lọc đang bật mà
 * chẳng có gì báo.
 */
$hiddenFilters = static function (array $except = []) use ($state): void {
    foreach ($state as $key => $value) {
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

/**
 * MỘT huy hiệu lọc.
 *
 * Tách riêng khỏi $chipGroup vì có hai nơi cần nó: các nhóm huy hiệu bên
 * dưới, và chip "Chất liệu tái chế / bio" đi kèm ô chọn chất liệu — chip đó
 * thuộc nhóm lọc riêng (?eco[]=recycled) nhưng người dùng đọc nó như một chất
 * liệu nữa, nên nó ở chung khối với ô chọn chứ không thành một khối có tiêu đề
 * riêng (thừa một dòng chữ hoa cho đúng một mục).
 */
$chip = static function (string $group, array $opt) use ($toggleUrl): void {
    /*
     * Mục lọc ra 0 sản phẩm: vẫn in nhưng bỏ liên kết và làm mờ. Giấu hẳn thì
     * cột lọc co giãn sau mỗi cú bấm và người dùng mất dấu tiêu chí vừa nhìn
     * thấy ở đó một giây trước.
     *
     * Mục ĐANG BẬT luôn còn liên kết dù đếm ra bao nhiêu — nếu không sẽ không
     * còn cách nào tắt nó đi.
     *
     * ─────────────────────────────────────────────────────────────────────
     * SỐ ĐẾM VẪN TÍNH, NHƯNG KHÔNG CÒN IN RA (2026-08-29)
     *
     * Trước đây mỗi tiêu chí kèm một con số: "Acetate 2". Cửa hàng thấy nó
     * không giúp gì cho việc chọn — người ta lọc theo thứ mình cần, không
     * theo chỗ nào đông hàng — mà lại làm mỗi dòng thêm một cụm số nhấp nháy
     * đổi sau mỗi cú bấm. Nay bỏ khỏi giao diện, ở cả ba nhóm: huy hiệu, danh
     * sách tick, và khoảng giá.
     *
     * `count` thì VẪN PHẢI TÍNH, vì nó là thứ quyết định mục nào bị làm mờ.
     * Đừng thấy "không ai in ra nữa" mà bỏ luôn phép đếm — bỏ là mọi tiêu chí
     * đều bấm được, kể cả những cái dẫn tới lưới rỗng.
     *
     * Mục bị mờ nay mang thêm một câu sr-only "không có sản phẩm nào": số 0
     * từng là dấu hiệu duy nhất cho người dùng trình đọc màn hình, bỏ nó đi
     * mà không thay gì là lấy mất thông tin của đúng nhóm người không nhìn
     * thấy màu mờ.
     * ─────────────────────────────────────────────────────────────────────
     */
    $dead = $opt['count'] === 0 && !$opt['on'];
    ?>
    <?php if ($dead): ?>
        <span class="pchip is-off" aria-disabled="true"><?= e($opt['label']) ?><span
            class="sr-only"> — không có sản phẩm nào</span></span>
    <?php else: ?>
        <?php /* aria-current chứ không phải aria-pressed: aria-pressed chỉ hợp
                 lệ trên nút, còn đây là <a>. Kèm một câu chỉ trình đọc màn hình
                 nghe được — không có nó thì trạng thái "đang chọn" chỉ nằm ở màu
                 nền, người không nhìn thấy màu sẽ nghe hai huy hiệu bật và tắt
                 giống hệt nhau. */ ?>
        <a class="pchip<?= $opt['on'] ? ' is-on' : '' ?>"
           href="<?= e($toggleUrl($group, $opt['key'])) ?>"
           <?= $opt['on'] ? 'aria-current="true"' : '' ?>
           rel="nofollow"><?= e($opt['label']) ?><?php
            if ($opt['on']): ?><span class="sr-only"> — đang lọc, bấm để bỏ</span><?php endif;
        ?></a>
    <?php endif; ?>
    <?php
};

/**
 * Một nhóm huy hiệu (Dáng gọng · Tính năng tròng · Đối tượng).
 *
 * Chất liệu KHÔNG còn dùng hàm này — nó là ô chọn xổ xuống, xem khối "CHẤT
 * LIỆU" trong cột lọc bên dưới.
 */
$chipGroup = static function (string $key, string $legend, array $options) use ($chip): void {
    if ($options === []) {
        return;
    }
    ?>
    <div class="pfacet">
        <p class="pfacet__legend"><?= e($legend) ?></p>
        <div class="pfacet__chips">
            <?php foreach ($options as $opt): ?>
                <?php $chip($key, $opt); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
};

/**
 * Một nhóm dạng danh sách tick (Thương hiệu · Bộ sưu tập hợp tác · Bộ sưu tập).
 *
 * $search = true thì có thêm ô "Tìm thương hiệu" phía trên.
 */
$checkGroup = static function (string $key, string $legend, array $options, bool $search = false)
    use ($state, $filters, $hiddenFilters, $toggleUrl): void {
    if ($options === []) {
        return;
    }

    /*
     * Lọc danh sách theo chữ đang gõ trong ô tìm — KHÔNG PHÂN BIỆT HOA THƯỜNG
     * LẪN DẤU, nên gõ "gioi" ra "Giới", gõ "SAINT" ra "Saint Laurent".
     *
     * Hai phép so chứ không một: utf8Lower() giữ dấu (khớp người gõ đủ dấu),
     * slugify() bỏ dấu (khớp người gõ không dấu — cách gõ nhanh phổ biến hơn
     * hẳn trên điện thoại).
     *
     * utf8Lower() chứ không mb_strtolower(): dự án cố ý KHÔNG phụ thuộc
     * extension mbstring (xem chú thích ở VN_ACCENT_MAP trong core/helpers.php),
     * mà máy dev đang thiếu đúng extension đó — mb_strtolower() làm cả trang
     * này chết với "Call to undefined function", không chỉ hỏng bộ lọc.
     */
    $needle = $search ? trim($filters['bq']) : '';

    if ($needle !== '') {
        $lower = utf8Lower($needle);
        $plain = slugify($needle);

        $options = array_values(array_filter($options, static function (array $o) use ($lower, $plain) {
            return str_contains(utf8Lower($o['label']), $lower)
                || ($plain !== '' && str_contains(slugify($o['label']), $plain));
        }));
    }

    $legendId = 'lg-' . $key;
    ?>
    <div class="pfacet">
        <p class="pfacet__legend" id="<?= e($legendId) ?>"><?= e($legend) ?></p>

        <?php if ($search): ?>
            <form class="pfacet__search" method="get" action="/san-pham" data-brand-filter>
                <?php $hiddenFilters(['bq', 'page']); ?>
                <label class="sr-only" for="f-bq">Tìm trong danh sách thương hiệu</label>
                <input class="pfacet__input" type="text" id="f-bq" name="bq"
                       value="<?= e($filters['bq']) ?>" placeholder="Tìm thương hiệu"
                       autocomplete="off">
                <button type="submit" class="pfacet__go">Lọc</button>
            </form>
        <?php endif; ?>

        <div class="pfacet__list" role="group" aria-labelledby="<?= e($legendId) ?>">
            <?php foreach ($options as $opt): ?>
                <?php $dead = $opt['count'] === 0 && !$opt['on']; ?>
                <?php if ($dead): ?>
                    <span class="pcheck is-off" aria-disabled="true"
                          <?php if ($search): ?>data-brand="<?= e(utf8Lower($opt['label'])) ?>"
                          data-brand-plain="<?= e(slugify($opt['label'])) ?>"<?php endif; ?>>
                        <span class="pcheck__box" aria-hidden="true"></span>
                        <span class="pcheck__label"><?= e($opt['label']) ?></span>
                        <span class="sr-only"> — không có sản phẩm nào</span>
                    </span>
                <?php else: ?>
                    <a class="pcheck<?= $opt['on'] ? ' is-on' : '' ?>"
                       href="<?= e($toggleUrl($key, $opt['key'])) ?>"
                       <?= $opt['on'] ? 'aria-current="true"' : '' ?>
                       rel="nofollow"
                       <?php /* Hai thuộc tính cho hai cách gõ, khớp đúng hai phép so
                                ở trên và ở assets/js/catalog.js. GIỮ NGUYÊN DẤU ở
                                data-brand — nó so với String.toLowerCase() bên JS,
                                vốn không bỏ dấu. */ ?>
                       <?php if ($search): ?>data-brand="<?= e(utf8Lower($opt['label'])) ?>"
                       data-brand-plain="<?= e(slugify($opt['label'])) ?>"<?php endif; ?>>
                        <span class="pcheck__box" aria-hidden="true"><?= $opt['on'] ? '✓' : '' ?></span>
                        <span class="pcheck__label"><?= e($opt['label']) ?></span>
                        <?php if ($opt['on']): ?><span class="sr-only"> — đang lọc, bấm để bỏ</span><?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($options === []): ?>
                <p class="pfacet__none">Không có thương hiệu nào khớp.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
};

/*
 * Có tiêu chí nào đang bật không — quyết định "Xoá tất cả" sáng hay mờ.
 *
 * Dùng lại $activeCount của controller thay vì cộng lại một lần nữa ở đây:
 * hai phép đếm song song là hai chỗ để quên khi thêm nhóm lọc mới. Trừ đi
 * danh mục vì "Xoá tất cả" cố ý không đụng tới nó.
 */
$hasFacetFilter = ($activeCount - ($filters['category'] !== '' ? 1 : 0)) > 0;

/* Tách bộ sưu tập hợp tác khỏi bộ sưu tập theo mùa — hai nhóm khác nhau về
   bản chất (một là hàng bắt tay với nhà thiết kế, một là chủ đề bán theo mùa)
   nên đứng chung một danh sách thì không đọc ra được cái nào là cái nào. */
$collabOptions = $groups['collab'];

/*
 * BỘ SƯU TẬP NAY LUÔN HIỆN — 2026-08-25.
 *
 * Trước đây nhóm này chỉ vẽ khi URL đã có ?collection=, với lý lẽ: cột lọc
 * chốt ở BẢY nhóm theo bản thiết kế, còn bộ sưu tập theo mùa là cách BÀY HÀNG
 * ngoài trang chủ chứ không phải một thuộc tính của gọng kính, chen vào giữa
 * thì làm loãng đúng chỗ người ta đang dò thương hiệu.
 *
 * Cửa hàng quyết định ngược lại, và lý do đủ mạnh: bộ sưu tập nay có TRANG
 * RIÊNG (/bo-suu-tap) và một ô trên thanh điều hướng chính. Từ lúc nó là một
 * lối duyệt hàng chính thức thì việc người ta muốn bật/tắt nó ngay trong cột
 * lọc là chuyện đương nhiên — giấu đi thành ra bắt họ quay ra trang kia rồi
 * bấm vào lại.
 *
 * $groups['collection'] chỉ chứa những bộ CÓ HÀNG trong kết quả hiện tại, nên
 * không cần lọc thêm: bộ rỗng tự vắng mặt thay vì thành một dòng bấm vào ra
 * lưới trắng. Bộ đang ẩn trong khu quản trị cũng không lọt vào đây chừng nào
 * không còn sản phẩm nào gắn nó.
 */
$collectionOptions = $groups['collection'];
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
             Dưới 1101px, CSS biến .cfilter__panel thành bottom-sheet.
             ──────────────────────────────────────────────────── -->
        <details class="cfilter" data-filter-sheet>
            <summary class="cfilter__toggle">
                <?= icon('filter', '', 16) ?>
                Bộ lọc
                <?php if ($activeCount > 0): ?>
                    <span class="cfilter__count"><?= $activeCount ?><span class="sr-only"> tiêu chí đang bật</span></span>
                <?php endif; ?>
            </summary>

            <?php /* Nền mờ sau bottom-sheet. Bấm vào là đóng — nhưng đó là
                     việc của JavaScript; không có JS thì nó chỉ là một lớp
                     màu, và người dùng đóng sheet bằng chính nút "Bộ lọc"
                     phía trên. Ẩn hoàn toàn ở desktop. */ ?>
            <div class="cfilter__scrim" data-sheet-close hidden></div>

            <div class="cfilter__panel">

                <div class="cfilter__head">
                    <p class="cfilter__title">Bộ lọc</p>
                    <?php /* Chưa lọc gì thì làm mờ và tắt hẳn thay vì giấu đi:
                             bản thiết kế luôn vẽ nút này, mà giấu rồi hiện lại
                             sẽ khiến hàng tiêu đề nhảy chiều cao mỗi lần bấm
                             tiêu chí lọc đầu tiên. */ ?>
                    <?php if ($hasFacetFilter): ?>
                        <a class="cfilter__clear" rel="nofollow" href="<?= e($buildUrl($resetPatch)) ?>">Xoá tất cả</a>
                    <?php else: ?>
                        <span class="cfilter__clear is-off" aria-hidden="true">Xoá tất cả</span>
                    <?php endif; ?>
                </div>

                <?php $chipGroup('shape', 'Dáng gọng', $groups['shape']); ?>

                <?php
                /*
                 * ─────────────────────────────────────────────────────────
                 * CHẤT LIỆU — Ô CHỌN XỔ XUỐNG, KHÔNG PHẢI HÀNG HUY HIỆU
                 *
                 * Tám chất liệu vẽ thành huy hiệu thì chiếm ba, bốn dòng của
                 * một cột chỉ rộng 280px — nhóm dài nhất cột lọc, mà lại là
                 * nhóm người ta ít đổi nhất. Ô chọn thu nó về một dòng, và
                 * dùng ĐÚNG bộ lớp .catpick của ô "Sắp xếp theo" ở cột kết
                 * quả: hai ô nhìn thấy cùng lúc trên một màn hình nên phải
                 * giống hệt nhau.
                 *
                 * CHỌN-MỘT, ĐỔI TỪ CHỌN-NHIỀU (2026-08-30, theo yêu cầu).
                 * Máy chủ thì KHÔNG đổi: 'material' vẫn nằm trong
                 * ProductFacets::MULTI và controller vẫn nhận mảng, nên
                 * name="material[]" gửi lên một phần tử là khớp sẵn. Muốn
                 * quay lại chọn-nhiều thì chỉ phải dựng lại giao diện ở đây,
                 * không đụng tới tầng lọc.
                 *
                 * MỘT ĐƯỜNG CŨ CÒN SỐNG: liên kết đã lưu từ hồi chọn-nhiều
                 * (?material[]=titanium&material[]=acetate) vẫn lọc đúng cả
                 * hai, vì controller không đổi — lưới ra hai sản phẩm. Ô chọn
                 * không diễn tả nổi trạng thái đó, nên $daChon chỉ cho đánh
                 * dấu MỘT mục: cái đứng trước trong DANH SÁCH (không phải cái
                 * đứng trước trong URL — thứ tự URL không tới được view). Bỏ
                 * $daChon đi thì hai <option> cùng mang selected và trình
                 * duyệt lặng lẽ lấy cái cuối, tức là vẫn một giá trị nhưng
                 * không ai đoán được là cái nào. Đụng vào ô một lần là trạng
                 * thái tự về đúng một giá trị.
                 *
                 * CHIP "TÁI CHẾ / BIO" Ở NGAY DƯỚI, trong cùng khối. Nó là
                 * nhóm lọc riêng (?eco[]=recycled) nên không nhét vào ô chọn
                 * được, nhưng người dùng đọc nó như một chất liệu nữa — tách
                 * thành khối có tiêu đề riêng là thừa một dòng chữ hoa cho
                 * đúng một mục.
                 * ─────────────────────────────────────────────────────────
                 */
                $daChon = false;
                ?>
                <?php if ($groups['material'] !== [] || $groups['eco'] !== []): ?>
                    <div class="pfacet">
                        <?php if ($groups['material'] !== []): ?>
                            <?php /* <label> chứ không <p> như các nhóm khác: ở
                                     đây tiêu đề nhóm CHÍNH LÀ nhãn của ô chọn,
                                     nên bấm vào chữ "CHẤT LIỆU" là mở được danh
                                     sách. */ ?>
                            <label class="pfacet__legend" for="f-material">Chất liệu</label>
                        <?php else: ?>
                            <?php /* Kho chỉ còn hàng "tái chế / bio" thì không có
                                     ô chọn nào để trỏ tới — một <label for> nhắm
                                     vào id không tồn tại là thứ trình đọc màn
                                     hình đọc ra rồi bỏ lửng. */ ?>
                            <p class="pfacet__legend">Chất liệu</p>
                        <?php endif; ?>

                        <?php if ($groups['material'] !== []): ?>
                            <form class="pfacet__pick" method="get" action="/san-pham">
                                <?php $hiddenFilters(['material', 'page']); ?>
                                <span class="catpick">
                                    <select class="catpick__select" id="f-material"
                                            name="material[]" data-pick="material[]">
                                        <?php /* value rỗng = bỏ lọc. multi() ở
                                                 controller slugify rồi loại chuỗi
                                                 rỗng, nên không cần nhánh riêng. */ ?>
                                        <option value="">Tất cả chất liệu</option>
                                        <?php foreach ($groups['material'] as $opt): ?>
                                            <?php
                                            /* disabled thay cho lớp .is-off của huy
                                               hiệu: <option> vô hiệu hoá được thật,
                                               và trình đọc màn hình tự nói ra —
                                               không phải chêm câu sr-only như bên
                                               kia. Mục ĐANG BẬT không bao giờ bị
                                               tắt, cùng luật với huy hiệu. */
                                            $tat  = $opt['count'] === 0 && !$opt['on'];
                                            $chon = $opt['on'] && !$daChon;

                                            if ($chon) {
                                                $daChon = true;
                                            }
                                            ?>
                                            <option value="<?= e($opt['key']) ?>"
                                                <?= $chon ? ' selected' : '' ?><?= $tat ? ' disabled' : '' ?>><?= e($opt['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php /* Cùng chữ V với ô "Sắp xếp theo" — xem
                                             chú thích ở khối đó. */ ?>
                                    <svg class="catpick__caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"
                                              stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <?php /* Ẩn khi có JavaScript (catalog.js đổi ô chọn
                                         là lọc luôn). Không có JS thì đây là cách
                                         duy nhất để chốt lựa chọn, nên không được
                                         bỏ. */ ?>
                                <button type="submit" class="catpick__go">Áp dụng</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($groups['eco'] !== []): ?>
                            <div class="pfacet__chips">
                                <?php foreach ($groups['eco'] as $opt): ?>
                                    <?php $chip('eco', $opt); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php $checkGroup('brand', 'Thương hiệu', $groups['brand'], true); ?>
                <?php $checkGroup('collab', 'Bộ sưu tập hợp tác', $collabOptions); ?>

                <?php /* Đứng ở đây chứ không dưới cùng để "Đối tượng" luôn là
                         nhóm chốt cột lọc. */ ?>
                <?php $checkGroup('collection', 'Bộ sưu tập', $collectionOptions); ?>

                <?php $chipGroup('lens', 'Tính năng tròng', $groups['lens']); ?>

                <?php /* Khoảng giá — chọn MỘT, và chỉ hiện khi kho đã có giá.
                         Bốn ô tròn không lọc ra được gì thì thà đừng vẽ: người
                         dùng bấm rồi thấy lưới không đổi sẽ tưởng trang hỏng. */ ?>
                <?php if ($hasPrices && $groups['price'] !== []): ?>
                    <div class="pfacet">
                        <p class="pfacet__legend">Khoảng giá</p>
                        <div class="pfacet__list">
                            <?php foreach ($groups['price'] as $opt): ?>
                                <?php $dead = $opt['count'] === 0 && !$opt['on']; ?>
                                <?php if ($dead): ?>
                                    <span class="pradio is-off" aria-disabled="true">
                                        <span class="pradio__dot" aria-hidden="true"></span>
                                        <span class="pradio__label"><?= e($opt['label']) ?></span>
                                        <span class="sr-only"> — không có sản phẩm nào</span>
                                    </span>
                                <?php else: ?>
                                    <?php /* Bấm lại chính ô đang chọn là BỎ chọn: nhóm này
                                             không có ô "tất cả", không cho bỏ thì chọn nhầm
                                             một khoảng giá là mắc kẹt trong đó. */ ?>
                                    <a class="pradio<?= $opt['on'] ? ' is-on' : '' ?>" rel="nofollow"
                                       <?= $opt['on'] ? 'aria-current="true"' : '' ?>
                                       href="<?= e($buildUrl(['price' => $opt['on'] ? null : (int) $opt['key'], 'page' => null])) ?>">
                                        <span class="pradio__dot" aria-hidden="true"></span>
                                        <span class="pradio__label"><?= e($opt['label']) ?></span>
                                        <?php if ($opt['on']): ?><span class="sr-only"> — đang lọc, bấm để bỏ</span><?php endif; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php $chipGroup('gender', 'Đối tượng', $groups['gender']); ?>

                <?php /* Chốt của bottom-sheet trên màn hình hẹp: sheet che gần
                         hết màn hình nên nút đóng phải nằm TRONG nó, không thể
                         bắt người dùng cuộn ngược lên tìm lại chữ "Bộ lọc".
                         CSS giấu nút này từ 1101px trở lên. */ ?>
                <button type="button" class="cfilter__done" data-sheet-close>
                    Xem <?= (int) $total ?> sản phẩm
                </button>
            </div>
        </details>

        <!-- ────────────────────────────────────────────────────
             KẾT QUẢ
             ──────────────────────────────────────────────────── -->
        <div class="catmain">

            <div class="catbar">
                <p class="catbar__count" aria-live="polite">
                    Hiển thị <strong><?= (int) $total ?></strong> sản phẩm<?php
                    /* Mẩu dưới đây KHÔNG có trong bản thiết kế, và phải có: ô
                       tìm trên header đổ người dùng vào đúng trang này kèm
                       ?q=…, mà cột lọc bên trái không có ô tìm nào để soi lại
                       mình vừa gõ gì. Không in ra thì kết quả trông như bị lọc
                       ngẫu nhiên. */
                    ?><?php if ($filters['q'] !== ''): ?>
                        cho “<strong><?= e($filters['q']) ?></strong>”
                        <a class="catbar__drop" rel="nofollow" href="<?= e($buildUrl(['q' => null, 'page' => null])) ?>">bỏ từ khoá</a>
                    <?php endif; ?>
                </p>

                <form class="catsort" method="get" action="/san-pham">
                    <?php $hiddenFilters(['sort', 'page']); ?>
                    <label class="catsort__label" for="f-sort">Sắp xếp theo</label>
                    <span class="catpick">
                        <select class="catpick__select" id="f-sort" name="sort" data-pick="sort">
                            <?php foreach ([
                                'newest'     => 'Mới nhất',
                                'popular'    => 'Bán chạy',
                                'price-asc'  => 'Giá thấp → cao',
                                'price-desc' => 'Giá cao → thấp',
                            ] as $value => $text): ?>
                                <option value="<?= e($value) ?>"<?= $filters['sort'] === $value ? ' selected' : '' ?>><?= e($text) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        /* SVG chữ V vẽ tay. Trước đây là ký tự ▼ — mà ký tự thì
                           mỗi hệ điều hành vẽ một kiểu: Windows ra tam giác đặc
                           nhỏ xíu, Android ra một hình khác hẳn, và không chỉnh
                           được độ dày nét cho khớp phần còn lại của site. */
                        ?>
                        <svg class="catpick__caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <?php /* Ẩn khi có JavaScript (catalog.js đổi ô chọn là gửi
                             luôn). Không có JS thì đây là cách duy nhất để
                             chốt lựa chọn, nên không được bỏ. */ ?>
                    <button type="submit" class="catpick__go">Áp dụng</button>
                </form>
            </div>

            <?php if ($total === 0): ?>
                <div class="catempty">
                    <p class="catempty__title">Chưa có sản phẩm phù hợp</p>
                    <p class="catempty__text">Thử bỏ bớt tiêu chí lọc hoặc xoá tất cả bộ lọc.</p>
                    <a class="catempty__btn" href="<?= e($buildUrl($resetPatch)) ?>">Xoá bộ lọc</a>
                </div>
            <?php else: ?>
                <?php /* THẺ DÙNG CHUNG VỚI TRANG CHỦ — _layout/product-card.php.
                         Trước đây trang này có thẻ riêng (_layout/product-tile.php,
                         cả thẻ là một liên kết, ảnh 220px, không nút nào), nên cùng
                         một sản phẩm hiện ra hai dáng tuỳ khách đi vào từ đâu. File
                         đó đã bỏ; xem chú thích ở .catgrid trong assets/css/category.css.

                         <ul> chứ không <div>: product-card.php in ra <li>. */ ?>
                <ul class="catgrid" role="list">
                    <?php foreach ($products as $i => $p): ?>
                        <?php partial('_layout/product-card', [
                            'product'     => $p,
                            'showCompare' => true,
                            // Hàng thẻ ĐẦU TIÊN (ba cột) nằm trong khung nhìn ngay
                            // khi trang mở, lazy-load chúng chỉ làm ảnh tới chậm hơn.
                            'eager'       => $i < 3,
                        ]); ?>
                    <?php endforeach; ?>
                </ul>

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
