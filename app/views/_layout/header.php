<?php
/*
 * ═══════════════════════════════════════════════════════════════════════════
 * THANH ĐẦU TRANG — dựng 1:1 từ mẫu "Eyewear Collection"
 *
 * BA CỘT, WORDMARK Ở GIỮA, CAO ĐÚNG 52px. Kiểu dáng nằm trong .oa-header của
 * assets/css/oa.css; file này chỉ lo dữ liệu và các móc JavaScript.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * BA THỨ CỦA BẢN CŨ ĐÃ BỎ HẲN, và đều là bỏ có lý do chứ không phải quên:
 *
 *   1. DẢI TIỆN ÍCH trên cùng (hotline + bộ chuyển ngôn ngữ). Mẫu không có
 *      dải nào phía trên thanh nav — thanh nav LÀ dòng đầu tiên của trang, và
 *      trên trang chủ nó nằm đè lên ảnh hero. Thêm một dải nữa là mất đúng cú
 *      đó. Hotline dời xuống cột "Cần tư vấn chọn kính?" ở chân trang; bộ
 *      chuyển ngôn ngữ dời xuống hàng cuối chân trang, cạnh dòng bản quyền —
 *      đúng chỗ mẫu đặt "Country : Vietnam".
 *
 *   2. WORDMARK HAI DÒNG ("Vin" trên "Eyewear"). Mẫu dùng một dòng, IN HOA,
 *      weight 700, giãn .12em. Hai dòng là dáng của thiết kế cũ.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * BA BẢNG XỔ — KHÁC MẪU, CÓ CHỦ Ý
 *
 * Mẫu chỉ có năm liên kết phẳng. Ở đây ba mục đầu (Gọng kính · Tròng kính ·
 * Bộ sưu tập) mở bảng xổ tràn bề ngang, vì cửa hàng này có hàng trăm mặt hàng
 * chia theo chất liệu, dáng, chiết suất — năm liên kết phẳng thì khách phải
 * vào trang danh mục rồi mới thấy có những nhánh nào.
 *
 * Bảng dựng theo ĐÚNG ngôn ngữ của mẫu (nền trắng, nhãn 9px IN HOA, kẻ mảnh);
 * kiểu dáng ở assets/css/components/mega-menu.css.
 *
 * PHẢI LÀ <ul class="header-nav__list"> VỚI <li>: cả ba partial đều emit <li>
 * làm phần tử gốc, và assets/js/header.js gọi
 * `megas[0].closest('.header-nav__list')` để gắn lớp .is-mega-live. Đổi sang
 * <a> phẳng là gãy cả ba chỗ cùng lúc, im lặng.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * MÓC JAVASCRIPT — GIỮ NGUYÊN TÊN, đổi tên là gãy im lặng:
 *
 *   #siteHeader · #navToggle · #mobileNav · .mobile-nav__panel   header.js
 *   .header-nav__list · .mega · .mega__trigger · .mega__panel    header.js
 *   [data-hpop] · [data-hpop-trigger] · [data-hpop-close]        header.js
 *   .hpop__panel · [data-cart] · [data-cart-close]               header.js
 *   #headerSearch · #headerSearchSuggest · .header-search__form  search-suggest.js
 *   [data-search-form] · [data-search-results] · [data-search-default]
 *   [data-recent-viewed] · [data-recent-list] · [data-recent-clear]
 *
 * Mỗi móc nay đi KÈM một lớp .oa-*: lớp oa lo kiểu dáng, móc lo hành vi. Đừng
 * gộp hai vai vào một tên — đó chính là thứ làm bản cũ không đổi được giao
 * diện mà không đụng vào JavaScript.
 * ═══════════════════════════════════════════════════════════════════════════
 */

$company = config('company');
$segment = currentSegment();

/* ┌─ HÀNG NAV: NĂM MỤC PHẲNG, ĐÚNG THỨ TỰ CỦA MẪU ────────────────────────
   │   Gọng kính · Tròng kính · Bộ sưu tập · Giới thiệu · Liên hệ
   │
   │ Hai mục hàng đứng trước, rồi tới bộ sưu tập, rồi hai trang tĩnh — mắt đi
   │ từ "xem hàng" sang "đọc về hãng", không xen kẽ.
   │
   │ "Thử kính ảo" KHÔNG có trong mẫu nên không lên hàng nav; nó vẫn sống ở
   │ /thu-ar, vào từ trang chi tiết sản phẩm và từ ngăn kéo mobile bên dưới.
   │
   │ Hai mục danh mục vẫn lọc theo danh mục ĐANG HIỆN: quản trị ẩn một danh
   │ mục thì mục nav của nó biến mất, không dẫn tới trang rỗng.
   └──────────────────────────────────────────────────────────────────────── */
$visibleCategorySlugs = array_column(CategoryModel::visible(), 'slug');

/* Bảng xổ "Bộ sưu tập" đọc hai biến này — xem _layout/collection-menu.php.
   KHÔNG tính /san-pham?collection=<slug> là đang ở bộ sưu tập: đó là trang
   danh sách đã lọc sẵn, không phải trang bộ sưu tập. */
$collectionsNav      = CollectionModel::visible();
$isCollectionActive  = $segment === 'bo-suu-tap';

/* ┌─ BA MỤC ĐẦU LÀ BẢNG XỔ, HAI MỤC CUỐI LÀ LIÊN KẾT PHẲNG ───────────────
   │ 'mega' => slug danh mục  → require _layout/mega-menu.php (dùng hai lần,
   │                            mỗi lần một slug qua biến $megaSlug)
   │ 'bst'  => true           → require _layout/collection-menu.php
   │ còn lại                  → một <a> thường
   └──────────────────────────────────────────────────────────────────────── */
$navItems = [
    ['mega'  => 'gong-kinh'],
    ['mega'  => 'trong-kinh'],
    ['bst'   => true],
    ['label' => t('nav.about'),   'url' => '/gioi-thieu', 'match' => ['gioi-thieu']],
    ['label' => t('nav.contact'), 'url' => '/lien-he',    'match' => ['lien-he']],
];

/* Quản trị ẩn một danh mục thì mục nav của nó biến mất, không dẫn tới trang
   rỗng. Lọc TRƯỚC khi in để bảng xổ cũng không dựng cho danh mục đã ẩn. */
$navItems = array_values(array_filter(
    $navItems,
    static fn (array $item): bool => !isset($item['mega'])
        || in_array($item['mega'], $visibleCategorySlugs, true)
));

/* Ngăn kéo mobile ghép thêm những lối vào mà hàng nav desktop không còn chỗ.
   KHÔNG lặp lại mục nào của $navItems — hai danh sách nối bằng array_merge. */
$mobileExtra = [
    ['label' => t('nav.booking'), 'url' => '/dat-lich',   'match' => ['dat-lich']],
    ['label' => t('nav.policy'),  'url' => '/chinh-sach', 'match' => ['chinh-sach']],
];

if ((bool) config('ar.nav_enabled')) {
    array_unshift($mobileExtra, ['label' => t('nav.ar'), 'url' => '/thu-ar', 'match' => ['thu-ar']]);
}

/* Mục nav sáng lên khi đang đứng ở nhánh của nó. Trang chi tiết
   /san-pham/{slug} cho ra slug sản phẩm — không khớp mục nào, đúng ý: thanh
   đầu trang không biết sản phẩm đó thuộc gọng hay tròng. */
$productSub = $segment === 'san-pham'
    ? (string) (explode('/', trim(currentPath(), '/'))[1] ?? '')
    : '';

$isActive = static fn (array $item): bool => in_array($segment, $item['match'] ?? [], true);

$isLoggedIn = AuthMiddleware::check();

/* ┌─ MỘT TRUY VẤN CHO LỚP PHỦ TÌM KIẾM ───────────────────────────────────
   │ Năm mẫu nổi bật hiện sẵn dưới ô tìm khi khách chưa gõ chữ nào ("Search
   │ Trends" của mẫu). Đây là truy vấn THỨ HAI của thanh đầu trang sau
   │ CategoryModel::visible() — bản cũ có ba, vì còn hai bảng mega.
   └──────────────────────────────────────────────────────────────────────── */
$searchTrends = ProductModel::featured(5);
?>
<a class="skip-link" href="#noi-dung-chinh"><?= e(t('a11y.skip')) ?></a>

<?php
/* ┌─ .oa-header--over : THANH TRONG SUỐT ĐÈ LÊN HERO ─────────────────────
   │ Chỉ trang chủ. Ảnh hero ở đó cao 100vh và kéo margin-top âm 52px để
   │ chạy lên dưới thanh này; thanh vì thế phải trong suốt, chữ trắng.
   │
   │ header.js gắn .is-scrolled khi cuộn qua hero, và lúc đó oa.css trả
   │ thanh về nền mờ + chữ đen. Tắt JavaScript thì thanh ở nguyên trạng
   │ thái trắng trên ảnh — vẫn đọc được, vì hero luôn là ảnh tối.
   │
   │ ĐỪNG đặt lớp này cho trang khác: trang nền trắng mà thanh trong suốt
   │ chữ trắng là chữ trắng trên nền trắng.
   └──────────────────────────────────────────────────────────────────────── */
$headerOver = ($viewName ?? '') === 'home/index';
?>
<header class="oa-header<?= $headerOver ? ' oa-header--over' : '' ?>" id="siteHeader">

    <nav class="oa-header__nav" aria-label="<?= e(t('nav.aria.main')) ?>">
        <ul class="header-nav__list" role="list">
            <?php foreach ($navItems as $item): ?>
                <?php if (!empty($item['mega'])): ?>
                    <?php $megaSlug = $item['mega']; require VIEWS_PATH . '/_layout/mega-menu.php'; ?>
                <?php elseif (!empty($item['bst'])): ?>
                    <?php require VIEWS_PATH . '/_layout/collection-menu.php'; ?>
                <?php else: ?>
                    <?php $on = $isActive($item); ?>
                    <li>
                        <a href="<?= e($item['url']) ?>"
                           <?= $on ? 'class="is-active" aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>

    <?php /* Nút hamburger thế chỗ hàng nav dưới 760px — xem @media cuối
             oa.css. Đặt ở ĐÂY, trong cùng ô lưới với hàng nav, để wordmark
             không xê dịch khi đổi bề ngang. */ ?>
    <button
        type="button"
        class="oa-header__burger"
        id="navToggle"
        aria-label="<?= e(t('menu.open')) ?>"
        aria-expanded="false"
        aria-controls="mobileNav"
    >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
            <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
    </button>

    <a href="/" class="oa-header__mark">Vin Eyewear</a>

    <div class="oa-header__actions">

        <?php
        /* ┌─ TÌM KIẾM — LỚP PHỦ TRÀN BỀ NGANG ────────────────────────────
           │ Bấm kính lúp là một bảng nền #f3f3f5 phủ từ dưới thanh đầu
           │ trang xuống; ô tìm lớn ở giữa, bên dưới là "Xu hướng" (5 mẫu
           │ nổi bật, máy chủ dựng sẵn) và "Đã xem gần đây" (localStorage,
           │ trình duyệt dựng). Gõ chữ là kết quả nạp ngầm từ /tim-kiem rồi
           │ hiện TẠI CHỖ — không rời trang.
           │
           │ MỞ BẰNG CÚ BẤM, KHÔNG BẰNG RÊ CHUỘT: một lớp phủ cả bề ngang
           │ bay ra vì con trỏ lướt qua icon là hành vi không ai muốn, và
           │ màn cảm ứng thì không có "rê chuột".
           │
           │ Tắt JavaScript: lớp phủ không mở được, nhưng trang /tim-kiem
           │ vẫn có ô tìm riêng và vẫn vào được từ chân trang.
           └──────────────────────────────────────────────────────────────── */
        ?>
        <div class="hpop hpop--search" data-hpop>
            <button
                type="button"
                class="hpop__trigger oa-header__btn"
                id="headerSearchToggle"
                data-hpop-trigger
                aria-label="<?= e(t('search.aria')) ?>"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="headerSearchPanel"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="M20 20l-4-4"/>
                </svg>
            </button>

            <?php /* Nền mờ phủ trang phía sau — bấm vào là đóng. Anh em với
                     bảng, không nằm trong nó. */ ?>
            <button type="button" class="hpop__scrim oa-scrim" data-hpop-close tabindex="-1" aria-hidden="true"></button>

            <div class="hpop__panel hpop__panel--search oa-panel" id="headerSearchPanel" role="dialog" aria-label="<?= e(t('search.title')) ?>">

                <button type="button" class="oa-close" data-hpop-close aria-label="<?= e(t('search.close')) ?>" style="align-self:flex-end">✕</button>

                <div class="oa-panel__inner">

                    <form class="header-search__form oa-searchbar" role="search" action="/tim-kiem" method="get" data-search-form>
                        <label class="sr-only" for="headerSearch"><?= e(t('search.title')) ?></label>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
                            <circle cx="11" cy="11" r="7"/>
                            <path d="M20 20l-4-4"/>
                        </svg>
                        <?php /* GỢI Ý TỪ KHOÁ qua <datalist> chứ không phải hộp thả
                                 tự vẽ: trình duyệt lo phần rơi xuống, điều hướng bàn
                                 phím và đọc màn hình — miễn phí và đúng chuẩn. */ ?>
                        <input
                            type="search"
                            id="headerSearch"
                            name="q"
                            class="header-search__input"
                            list="headerSearchSuggest"
                            autocomplete="off"
                            placeholder="<?= e(t('search.placeholder')) ?>"
                            value="<?= e($_GET['q'] ?? '') ?>"
                            data-suggest
                        >
                        <datalist id="headerSearchSuggest"></datalist>
                        <button type="submit" class="sr-only"><?= e(t('search.submit')) ?></button>
                    </form>

                    <?php /* Kết quả nạp ngầm đổ vào đây; ẩn cho tới khi có gì. */ ?>
                    <div class="srchov__results" data-search-results hidden></div>

                    <div class="srchov__default oa-stack" style="gap:48px" data-search-default>

                        <?php if ($searchTrends !== []): ?>
                            <section class="oa-stack" style="gap:28px">
                                <h3 class="oa-label"><?= e(t('search.trends')) ?></h3>
                                <ul class="oa-strip oa-plain" role="list">
                                    <?php foreach ($searchTrends as $p): ?>
                                        <li class="oa-strip__item">
                                            <a href="/san-pham/<?= e(rawurlencode((string) $p['slug'])) ?>">
                                                <span class="oa-slot oa-slot--contain">
                                                    <img src="<?= e(asset(ProductModel::image($p))) ?>" alt="" loading="lazy" decoding="async">
                                                </span>
                                                <span lang="vi"><?= e($p['name']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <?php /* Dựng hoàn toàn ở trình duyệt từ localStorage — máy chủ
                                 không biết khách đã xem gì, và không cần biết. Khối ẩn
                                 sẵn; search-suggest.js gỡ [hidden] khi có mục. */ ?>
                        <section class="oa-stack" style="gap:28px" data-recent-viewed hidden>
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                                <h3 class="oa-label"><?= e(t('search.recent')) ?></h3>
                                <button type="button" class="oa-btn-bare oa-label" data-recent-clear><?= e(t('search.clear_recent')) ?></button>
                            </div>
                            <ul class="oa-strip oa-plain" role="list" data-recent-list></ul>
                        </section>

                    </div>
                </div>
            </div>
        </div>

        <?php
        /* ┌─ TÀI KHOẢN — LIÊN KẾT THẲNG, KHÔNG PHẢI NGĂN KÉO ─────────────
           │ Mẫu mở một ngăn kéo bên phải chứa form đăng nhập/đăng ký cùng
           │ luồng Zalo OTP. Ở đây luồng ấy là trang thật /auth: nó có
           │ CSRF, có trạng thái lỗi, có bước OTP nhiều màn. Dựng lại nó
           │ lần thứ hai trong thanh đầu trang là hai bản sao của cùng một
           │ luồng đăng nhập, và bản trong header sẽ lệch dần.
           │
           │ Nên: icon dẫn thẳng tới /auth, còn TRANG /auth mang đúng dáng
           │ của ngăn kéo trong mẫu — cùng hai tab, cùng ô nhập cao 44 bo
           │ 6, cùng nút Zalo OTP, cùng nút Google. Xem app/views/auth/.
           │
           │ KHÔNG kèm ?redirect=: tham số đó dành cho khách BỊ CHẶN giữa
           │ chừng (AuthMiddleware::requireLogin đá về đây rồi trả lại đúng
           │ chỗ đang dở). Bấm icon là khách CHỦ ĐỘNG vào tài khoản mình.
           │
           │ KHÔNG CÓ NHÁNH NÀO CHO PHIÊN QUẢN TRỊ, và không thể có: trang
           │ bán hàng không nhận được cookie `vin_admin` (App::startSession),
           │ nên nó không biết — và không được biết — có ai đang đăng nhập
           │ khu quản trị hay không.
           └──────────────────────────────────────────────────────────────── */
        ?>
        <a href="<?= $isLoggedIn ? '/tai-khoan' : '/auth' ?>" class="oa-header__btn"
           aria-label="<?= e($isLoggedIn ? t('account.mine') : t('account.login')) ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>
            </svg>
        </a>

        <?php /* GIỎ HÀNG — icon, số món và ngăn kéo, ở _layout/header-cart.php.
                 Nằm riêng một file vì master.php cũng in đúng cụm đó khi trả
                 lời buy-flow.js ở chế độ mảnh. */ ?>
        <?php partial('_layout/header-cart'); ?>

    </div>
</header>

<?php
/* ┌─ NGĂN KÉO MOBILE ─────────────────────────────────────────────────────
   │ Nằm NGOÀI <header> để position:fixed không bị backdrop-filter của
   │ thanh đầu trang neo lại — thuộc tính đó tạo containing block mới, làm
   │ phần tử fixed bên trong bám vào header thay vì vào khung nhìn.
   │
   │ Dùng chung $navItems với hàng nav desktop nên bật/tắt một mục hay đổi
   │ thứ tự chỉ sửa một chỗ; hai danh sách song song là kiểu sai lệch dần
   │ mà không ai thấy.
   └──────────────────────────────────────────────────────────────────────── */
?>
<div class="mobile-nav" id="mobileNav" hidden>
    <div class="mobile-nav__backdrop" data-close-nav></div>

    <div class="mobile-nav__panel" role="dialog" aria-modal="true" aria-label="<?= e(t('menu.aria')) ?>">

        <div class="mobile-nav__head">
            <a href="/" class="oa-header__mark">Vin Eyewear</a>
            <button type="button" class="oa-close" data-close-nav aria-label="<?= e(t('menu.close')) ?>">✕</button>
        </div>

        <nav class="mobile-nav__links" aria-label="<?= e(t('nav.aria.main')) ?>">
            <?php foreach (array_merge($navItems, $mobileExtra) as $item): ?>
                <?php if (!empty($item['mega'])): ?>
                    <?php $megaSlug = $item['mega']; require VIEWS_PATH . '/_layout/mega-menu-mobile.php'; ?>
                <?php elseif (!empty($item['bst'])): ?>
                    <?php require VIEWS_PATH . '/_layout/collection-menu-mobile.php'; ?>
                <?php else: ?>
                    <a href="<?= e($item['url']) ?>"<?= $isActive($item) ? ' class="is-active" aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="mobile-nav__foot">
            <a href="/dat-lich" class="oa-btn oa-btn--solid"><?= e(t('cta.book')) ?></a>
            <a href="<?= e($company['hotline_href']) ?>" class="oa-btn"><?= e(t('cta.call', [':phone' => $company['hotline']])) ?></a>
        </div>
    </div>
</div>
