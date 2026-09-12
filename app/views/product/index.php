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
 * Kho thêm một kiểu dáng là ô chọn "Kiểu dáng" có thêm một dòng; hãng nào bán
 * hết hàng thì huy hiệu của hãng đó tự biến mất — không phải sửa file này.
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

/*
 * ĐƯỜNG GỐC CỦA TRANG — '/san-pham' hoặc '/san-pham/gong-kinh'.
 * Controller luôn truyền vào; cái ?? chỉ để một chỗ gọi view này quên truyền
 * thì ra trang cả kho chứ không phải một chuỗi rỗng làm mọi liên kết lọc trỏ
 * về gốc site.
 */
$catalogBase = $catalogBase ?? '/san-pham';

/*
 * TRANG TRÒNG KÍNH DÙNG MỘT BỘ NHÓM LỌC KHÁC HẲN.
 *
 * Kiểu dáng · Chất liệu · Giới tính là thuộc tính của GỌNG; đặt chúng trên
 * trang tròng kính là mời khách lọc theo những tiêu chí mà không tròng nào có,
 * và mỗi nhóm ấy sẽ hiện ra rỗng hoặc gần rỗng. Đổi lại, tròng có bốn thuộc
 * tính riêng mà gọng không có: loại tròng, chiết suất, lớp phủ, màu tròng.
 *
 * Hai bộ nhóm, một cột lọc — xem khối rẽ nhánh trong .cfilter__panel bên dưới.
 * Thương hiệu và Khoảng giá thì CHUNG cho cả hai: chúng không thuộc về gọng
 * hay tròng, chúng thuộc về việc mua hàng.
 */
$laTrongKinh = ($catalogSlug ?? '') === 'trong-kinh';

/**
 * Dựng URL giữ nguyên bộ lọc hiện tại, chỉ đổi/bỏ vài tham số.
 *
 * So sánh với '' và null chứ KHÔNG dùng empty(): 'price' => 0 là khoảng giá
 * đầu tiên ("Dưới 2 triệu"), empty(0) là true nên nó sẽ bị vứt khỏi URL và ô
 * đó không bao giờ chọn được.
 */
$buildUrl = static function (array $patch = []) use ($state, $catalogBase): string {
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

    /*
     * TRÊN TRANG CON, 'category' KHÔNG ĐI VÀO QUERY — đường dẫn đã mang nó
     * rồi. In cả hai thì ra /san-pham/gong-kinh?category=gong-kinh: thừa, xấu,
     * và tệ hơn là nó khiến cùng một lưới có hai địa chỉ.
     *
     * Ở /san-pham (cả kho hoặc danh mục chưa có trang con) thì giữ nguyên như
     * cũ, vì lúc đó query là chỗ DUY NHẤT mang được danh mục.
     */
    if ($catalogBase !== '/san-pham') {
        unset($clean['category']);
    }

    return $catalogBase . ($clean === [] ? '' : '?' . http_build_query($clean));
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



/*
 * ĐÃ BỎ $chipGroup — 2026-08-30.
 *
 * Hàm này vẽ cả một nhóm thành hàng huy hiệu. Ba nhóm từng dùng nó — Kiểu dáng,
 * Chất liệu, Giới tính — nay là ô chọn xổ xuống, còn nhóm thứ tư ("Tính năng
 * tròng") thì cửa hàng bỏ hẳn khỏi cột lọc, nên không còn ai gọi.
 *
 * $chip (một huy hiệu lẻ) thì GIỮ: chip "Chất liệu tái chế / bio" vẫn dùng.
 *
 * TÍNH NĂNG TRÒNG VẪN LỌC ĐƯỢC BẰNG URL. Chỉ giao diện bị bỏ; 'lens' vẫn nằm
 * trong ProductFacets::GROUPS và MULTI, controller vẫn nhận ?lens[]=blue-light.
 * Bắt buộc phải thế, vì HAI chỗ ngoài trang này còn trỏ vào đó:
 *   _layout/mega-menu.php        cột "Tròng kính" (5 liên kết, qua
 *                                config/taxonomy.php -> 'lens_functions')
 *   _layout/home/quick-check.php ba lựa chọn của thẻ "Chọn tròng"
 *
 * HỆ QUẢ ĐÃ BIẾT, chưa xử lý vì cửa hàng chưa quyết: khách đi từ hai lối đó
 * tới đây sẽ có một tiêu chí đang bật mà KHÔNG nhìn thấy nó ở đâu. Trên điện
 * thoại còn huy hiệu số trên nút "Bộ lọc"; từ 1101px trở lên nút đó bị ẩn nên
 * dấu hiệu duy nhất là chữ "Xoá tất cả" sáng lên. Muốn dứt điểm thì hoặc đổi
 * hai lối kia sang tiêu chí khác, hoặc in một chip "đang lọc: …" ở đầu cột kết
 * quả — cả hai đều là việc riêng, không phải phần của lần bỏ này.
 */



/*
 * Có tiêu chí nào đang bật không — quyết định "Xoá tất cả" sáng hay mờ.
 *
 * Dùng lại $activeCount của controller thay vì cộng lại một lần nữa ở đây:
 * hai phép đếm song song là hai chỗ để quên khi thêm nhóm lọc mới. Trừ đi
 * danh mục vì "Xoá tất cả" cố ý không đụng tới nó.
 */
$hasFacetFilter = ($activeCount - ($filters['category'] !== '' ? 1 : 0)) > 0;


?>

<?php
/*
 * ════════════════════════════════════════════════════════════════════════════
 * TẤM LỌC NGANG — thay cho cột lọc trái 280px (12/09/2026)
 *
 * Cột trái cũ là thứ đã ép bốn nhóm lọc phải gói vào ô xổ xuống; chú thích của
 * chính nó nói thẳng: "một nhóm ăn ba, bốn dòng của một cột chỉ rộng 280px".
 * Tấm ngang gỡ đúng cái ép ấy — bề ngang cả trang đủ cho bảy cột đứng cạnh
 * nhau, nên mỗi nhóm bày thẳng ra hết lựa chọn.
 *
 * Cả ba trang có lưới sản phẩm (gọng · tròng · bộ sưu tập) dùng CHUNG một tấm
 * — _layout/filter-bar.php. Trang nào hiện nhóm nào thì do $fbCols dưới đây
 * quyết, không phải do tấm biết trước.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * NHÓM NÀO HIỆN RA — SỬA Ở ĐÚNG MỘT CHỖ
 *
 * $thuTuNhom bên dưới là danh sách DUY NHẤT quyết định có những cột nào và xếp
 * theo thứ tự nào. Thêm/bớt một nhóm là sửa một dòng ở đó; đừng đi tìm trong
 * phần dựng HTML, ở đó không còn tên nhóm nào cả.
 * ════════════════════════════════════════════════════════════════════════════
 */

/* Nhãn của từng nhóm. Nhóm nào không có mục nào trong kết quả hiện tại thì tự
   vắng mặt — tấm lọc tự bỏ qua cột rỗng. */
$nhanNhom = [
    'color'      => 'Màu gọng',
    'shape'      => 'Kiểu dáng',
    'material'   => 'Chất liệu',
    'gender'     => 'Giới tính',
    'brand'      => 'Thương hiệu',
    'collab'     => 'Bộ sưu tập hợp tác',
    'collection' => 'Bộ sưu tập',
    'lens_type'  => 'Loại tròng',
    'lens_index' => 'Chiết suất',
    'lens_coat'  => 'Tính năng / lớp phủ',
    'lens_color' => 'Màu tròng',
];

/* Trang tròng kính và trang gọng hỏi hai bộ tiêu chí khác nhau: kiểu dáng và
   chất liệu là thuộc tính của GỌNG, chiết suất và lớp phủ là của TRÒNG. Thương
   hiệu, bộ sưu tập và khoảng giá thì chung — chúng không thuộc về gọng hay
   tròng, chúng thuộc về việc mua hàng. */
$thuTuNhom = $laTrongKinh
    ? ['lens_type', 'lens_index', 'lens_coat', 'lens_color', 'brand']
    : ['color', 'shape', 'material', 'gender', 'brand', 'collab'];

/*
 * 'collection' KHÔNG CÒN LÀ MỘT CỘT TRONG TẤM — nó lên HÀNG CHIP phía trên
 * (12/09/2026, theo yêu cầu chủ dự án).
 *
 * ⚠ Đừng thêm nó lại vào $thuTuNhom. Lúc ấy cùng một tham số ?collection= có
 * hai chỗ điều khiển, cách nhau một khoảng — và chúng sẽ không khớp nhau, vì
 * hàng chip chọn MỘT bộ còn cột lọc tick được NHIỀU. Đây đúng là thứ vừa dọn
 * xong với ô "Sắp xếp theo".
 */

$fbCols = [];

foreach ($thuTuNhom as $khoa) {
    if (empty($groups[$khoa])) {
        continue;
    }

    $muc = [];

    foreach ($groups[$khoa] as $opt) {
        $muc[] = [
            'label' => $opt['label'],
            'url'   => $toggleUrl($khoa, $opt['key']),
            'on'    => (bool) $opt['on'],
            /* count 0 = bấm vào sẽ ra lưới rỗng. Mục ĐANG BẬT không bao giờ bị
               tắt, nếu không thì không còn cách nào bỏ lọc nó. */
            'off'   => $opt['count'] === 0,
        ];
    }

    $fbCols[] = ['label' => $nhanNhom[$khoa] ?? $khoa, 'options' => $muc];
}

/* KHOẢNG GIÁ là nhóm chọn-MỘT và không lên URL dạng mảng — địa chỉ của nó là
   ?price=2, một chỉ số trong PRICE_RANGES. Đổi sang price[]=2 cho giống mấy
   nhóm kia sẽ làm hỏng mọi liên kết đã có người lưu. */
if ($hasPrices && $groups['price'] !== []) {
    $mucGia = [[
        'label' => 'Tất cả mức giá',
        'url'   => $buildUrl(['price' => null, 'page' => null]),
        'on'    => $priceIndex === null,
        'off'   => false,
    ]];

    foreach ($groups['price'] as $opt) {
        $mucGia[] = [
            'label' => $opt['label'],
            'url'   => $buildUrl(['price' => $opt['key'], 'page' => null]),
            'on'    => (bool) $opt['on'],
            'off'   => $opt['count'] === 0,
        ];
    }

    $fbCols[] = ['label' => 'Khoảng giá', 'single' => true, 'options' => $mucGia];
}

/*
 * SẮP XẾP NẰM TRONG TẤM LỌC (12/09/2026, theo yêu cầu chủ dự án).
 *
 * Trước đây nó là một ô <select> đứng riêng ở thanh trên lưới. Hai chỗ điều
 * khiển cùng một lưới, cách nhau nửa màn hình, mà cái nào cũng đổi đúng một
 * tham số trên URL. Gom vào đây thì mọi thứ tác động lên lưới nằm chung một
 * tấm — và ô sắp xếp cũ đã gỡ khỏi .catbar.
 *
 * 'newest' là mặc định nên $buildUrl tự bỏ nó khỏi URL; mục này vì thế vừa là
 * "Mới nhất" vừa là "về mặc định".
 */
$fbCols[] = [
    'label'   => 'Sắp xếp',
    'single'  => true,
    'options' => array_map(
        static fn (string $gt, string $nhan): array => [
            'label' => $nhan,
            'url'   => $buildUrl(['sort' => $gt, 'page' => null]),
            'on'    => ($filters['sort'] ?: 'newest') === $gt,
            'off'   => false,
        ],
        array_keys($sapXepMuc = [
            'newest'     => t('cat.sort_newest'),
            'popular'    => t('cat.sort_popular'),
            'price-asc'  => t('cat.sort_price_asc'),
            'price-desc' => t('cat.sort_price_desc'),
        ]),
        array_values($sapXepMuc)
    ),
];


/*
 * ════════════════════════════════════════════════════════════════════════════
 * HÀNG CHIP BỘ SƯU TẬP — NGANG HÀNG VỚI NÚT "BỘ LỌC" (12/09/2026)
 *
 * Chọn MỘT bộ, không phải tick nhiều: chip đầu "Tất cả" là lối về, đúng dáng
 * bản thiết kế chủ dự án đưa và cũng đúng cách người ta dùng nó — xem hàng
 * mẫu của một bộ chứ không hỏi "gộp bộ A với bộ B".
 *
 * Bấm lại chip ĐANG BẬT thì bỏ chọn, y như mọi tiêu chí khác trong tấm lọc.
 * Nó dẫn về cùng địa chỉ với "Tất cả" — hai lối cho cùng một việc là cố ý:
 * lối hiển nhiên cho người đọc kỹ, lối theo phản xạ cho người bấm nhanh.
 *
 * Hàng tự vắng mặt khi kho chưa có bộ sưu tập nào — lúc ấy chỉ còn mỗi chip
 * "Tất cả", mà một lựa chọn duy nhất thì không phải một lựa chọn.
 */
$fbChips = [];

if (!empty($groups['collection'])) {
    $dangLocBo = false;

    foreach ($groups['collection'] as $o) {
        if (!empty($o['on'])) {
            $dangLocBo = true;
            break;
        }
    }

    $veTatCa = $buildUrl(['collection' => [], 'page' => null]);

    $fbChips[] = ['label' => 'Tất cả', 'url' => $veTatCa, 'on' => !$dangLocBo, 'off' => false];

    foreach ($groups['collection'] as $o) {
        $fbChips[] = [
            'label' => $o['label'],
            /* Đang bật -> bấm là BỎ chọn (về "Tất cả"). Chưa bật -> thay hẳn
               bộ đang chọn, không cộng thêm: hàng này chọn một. */
            'url'   => !empty($o['on']) ? $veTatCa
                                        : $buildUrl(['collection' => [$o['key']], 'page' => null]),
            'on'    => (bool) $o['on'],
            'off'   => $o['count'] === 0,
        ];
    }
}
?>

<!-- ============================================================
     THÂN TRANG — hàng chip + tấm lọc + tiêu đề + lưới kết quả
     ============================================================ -->
<section class="catbody">

    <?php
    /* TẤM LỌC ĐỨNG TRÊN TIÊU ĐỀ "Gọng kính" (12/09/2026, yêu cầu chủ dự án) —
       vì thế _layout/page-head nằm DƯỚI lời gọi này chứ không còn ở ngoài
       .catbody như trước.

       NẰM TRONG .catbody, và phải thế: assets/js/catalog.js thay ruột hai khối
       `.fbar` và `.catmain` sau mỗi cú lọc, mà nó tìm cả hai BÊN TRONG
       .catbody. Đặt tấm ra ngoài thẻ này thì mỗi cú bấm tiêu chí lại tải lại
       cả trang thay vì đổi tại chỗ — chạy đúng, nhưng chậm hơn hẳn.

       ⚠ .fbar phải là em LIỀN KỀ của .fbarbar (CSS dùng `+` để bung tấm ra).
       Cả hai do partial này in ra cạnh nhau — đừng chèn gì vào giữa. */
    partial('_layout/filter-bar', [
        'fbCols'  => $fbCols,
        'fbTotal' => (int) $total,
        /* "Xoá tất cả" trỏ về trang này KHÔNG mang tiêu chí nào. Giữ nguyên
           ?q= nếu khách tới từ ô tìm kiếm: xoá bộ lọc khác với xoá từ khoá
           đang tìm. */
        'fbClear' => $hasFacetFilter ? $buildUrl($resetPatch) : '',
        /* Số tiêu chí đang bật — in lên nút mở, và là thứ quyết định tấm có
           bung sẵn hay không. Trừ 'category' ra: danh mục là CHÍNH trang này
           chứ không phải một tiêu chí khách vừa bấm, nên đếm nó vào là tấm
           luôn bung sẵn ở mọi trang con. Cùng phép với $hasFacetFilter. */
        'fbCount' => max(0, $activeCount - ($filters['category'] !== '' ? 1 : 0)),
        'fbChips' => $fbChips,
    ]);

    /* Tiêu đề trang — ĐÃ CHUYỂN XUỐNG ĐÂY, dưới tấm lọc.
       $lead cùng một chuỗi với thẻ <meta description> (xem ProductController):
       đừng viết lại một câu khác cho đẹp, hai chỗ lệch nhau thì kết quả tìm
       kiếm hứa một đằng, trang mở ra một nẻo. */
    partial('_layout/page-head', [
        'head_crumbs' => $crumbs,
        'head_title'  => $heading,
        'head_lead'   => $lead,
    ]);
    ?>

    <div class="catbody__grid">

        <!-- ────────────────────────────────────────────────────
             KẾT QUẢ
             ──────────────────────────────────────────────────── -->
        <div class="catmain">

            <div class="catbar">
                <p class="catbar__count" aria-live="polite">
                    <?= strtr(
                        e(t('cat.showing', [':n' => '{{n}}'])),
                        ['{{n}}' => '<strong>' . (int) $total . '</strong>']
                    ) ?><?php
                    /* Mẩu dưới đây KHÔNG có trong bản thiết kế, và phải có: ô
                       tìm trên header đổ người dùng vào đúng trang này kèm
                       ?q=…, mà cột lọc bên trái không có ô tìm nào để soi lại
                       mình vừa gõ gì. Không in ra thì kết quả trông như bị lọc
                       ngẫu nhiên. */
                    ?><?php if ($filters['q'] !== ''): ?>
                        <?= e(t('cat.for_query')) ?> “<strong><?= e($filters['q']) ?></strong>”
                        <a class="catbar__drop" rel="nofollow" href="<?= e($buildUrl(['q' => null, 'page' => null])) ?>"><?= e(t('cat.drop_query')) ?></a>
                    <?php endif; ?>
                </p>

                <?php /* Ô "Sắp xếp theo" ĐÃ GỠ KHỎI ĐÂY (12/09/2026).
                         Nó nay là một cột trong tấm lọc phía trên — xem khối
                         "SẮP XẾP NẰM TRONG TẤM LỌC". Hai chỗ điều khiển cùng
                         một lưới, cách nhau nửa màn hình, là thứ vừa dọn. */ ?>
            </div>

            <?php if ($total === 0): ?>
                <div class="catempty">
                    <p class="catempty__title"><?= e(t('cat.empty_title')) ?></p>
                    <p class="catempty__text"><?= e(t('cat.empty_text')) ?></p>
                    <a class="catempty__btn" href="<?= e($buildUrl($resetPatch)) ?>"><?= e(t('cat.empty_btn')) ?></a>
                </div>
            <?php else: ?>
                <?php /* THẺ DÙNG CHUNG VỚI TRANG CHỦ — _layout/product-card.php.
                         Trước đây trang này có thẻ riêng (_layout/product-tile.php,
                         cả thẻ là một liên kết, ảnh 220px, không nút nào), nên cùng
                         một sản phẩm hiện ra hai dáng tuỳ khách đi vào từ đâu.

                         LƯỚI CŨNG DÙNG CHUNG NỐT — `.pgrid` thay cho `.catgrid`
                         (Phase 2). `.catgrid` là một lưới thứ hai làm đúng việc của
                         `.pgrid` nhưng khai số cột và máng khác, nên cùng một thẻ
                         hiện ra hai cỡ tuỳ khách vào từ trang chủ hay từ danh mục —
                         đúng thứ mà việc gộp thẻ ở trên vừa dẹp xong. Lớp đó đã bỏ
                         khỏi assets/css/category.css.

                         KHÔNG phải hợp đồng với JavaScript: catalog.js thay ruột của
                         `.catmain` (khối bọc ngoài) chứ không tìm tên lưới này.

                         <ul> chứ không <div>: product-card.php in ra <li>. */ ?>
                <ul class="pgrid" role="list">
                    <?php foreach ($products as $i => $p): ?>
                        <?php partial('_layout/product-card', [
                            'product'     => $p,
                            'showCompare' => true,
                            /* Hàng chấm màu dưới giá. Controller gom SẴN biến thể
                               của cả trang trong một câu — tra mảng ở đây, không
                               hỏi CSDL trong vòng lặp. */
                            'variants'    => $variants[$p['id']] ?? [],
                            // Hàng thẻ ĐẦU TIÊN nằm trong khung nhìn ngay khi trang
                            // mở, lazy-load chúng chỉ làm ảnh tới chậm hơn. BỐN chứ
                            // không ba: .pgrid xếp 4 cột ở khổ máy tính
                            // (assets/css/catalog.css).
                            'eager'       => $i < 4,
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
                    <nav class="catpager" aria-label="<?= e(t('cat.pager')) ?>">
                        <?php if ($page > 1): ?>
                            <a class="catpager__btn" rel="prev nofollow"
                               href="<?= e($buildUrl(['page' => $page - 1])) ?>"
                               aria-label="<?= e(t('cat.prev')) ?>">←</a>
                        <?php endif; ?>

                        <?php for ($i = $from; $i <= $to; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="catpager__btn is-current" aria-current="page"><?= $i ?></span>
                            <?php else: ?>
                                <a class="catpager__btn" rel="nofollow"
                                   href="<?= e($buildUrl(['page' => $i])) ?>"
                                   aria-label="<?= e(t('cat.page_n', [':n' => (string) $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="catpager__btn" rel="next nofollow"
                               href="<?= e($buildUrl(['page' => $page + 1])) ?>"
                               aria-label="<?= e(t('cat.next')) ?>">→</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
