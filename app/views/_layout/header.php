<?php

/**
 * _layout/header.php
 *
 * Dựng theo NAVBAR CỦA FURNISH (`furnish-1.0.0/src/index.html`, khối
 * `.navbar-custom`), chuyển sang ngữ cảnh kính mắt.
 *
 * Furnish xếp: wordmark hai dòng IN HOA bên trái · điều hướng chữ hoa nhỏ ở
 * giữa · số điện thoại + nút mở menu bên phải. Nền trắng, không bo góc, phân
 * cách với nội dung bằng một đường kẻ mảnh chứ không phải bóng đổ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NHỮNG THỨ FURNISH KHÔNG CÓ MÀ SITE NÀY BẮT BUỘC PHẢI GIỮ
 *
 * Navbar của Furnish chỉ có 5 liên kết và một cái hamburger — nó là trang giới
 * thiệu, không phải cửa hàng. Bốn cụm dưới đây không có trong bản mẫu và được
 * dựng bằng chính ngôn ngữ hình khối của nó (vuông, viền mảnh, chữ hoa nhỏ):
 *
 *   · ô tìm kiếm bung ra          .hpop--search
 *   · bảng tài khoản              .hpop
 *   · giỏ hàng + huy hiệu số      _layout/header-cart.php
 *   · bộ chuyển ngôn ngữ EN|VI    trên dải thông báo
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI SELECTOR DƯỚI ĐÂY LÀ HỢP ĐỒNG VỚI JAVASCRIPT — ĐỪNG ĐỔI TÊN
 *
 *   #siteHeader                    header.js: đổ bóng khi cuộn
 *   [data-hpop] / [data-hpop-trigger] / .hpop__panel
 *                                  header.js: bảng xổ; buy-flow.js còn chép
 *                                  ruột .hpop__panel của giỏ hàng sang
 *   #navToggle / #mobileNav / [data-close-nav]
 *                                  header.js: ngăn kéo trên màn hẹp
 *   [data-suggest] + <datalist id="headerSearchSuggest">
 *                                  search-suggest.js: gợi ý từ khoá
 *   [data-cart]                    buy-flow.js: thay cả cụm giỏ sau khi thêm hàng
 *   .skip-link -> #noi-dung-chinh  lối tắt bàn phím
 *
 * KHÔNG dùng offcanvas của Bootstrap cho ngăn kéo: header.js đã làm đúng việc
 * đó từ trước, và nạp Bootstrap JS chỉ để lặp lại nó sẽ kéo theo cả Popper.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * NĂM MỤC, HAI TRONG SỐ ĐÓ LÀ BẢNG XỔ
 *
 *     Trang chủ · Sản phẩm ▾ · [Thử kính ảo] · Giới thiệu · Bộ sưu tập ▾ · Liên hệ
 *
 * Hai mục bảng xổ tự dựng <li> của mình (mega-menu.php, collection-menu.php)
 * và đọc danh mục / bộ sưu tập thẳng từ CSDL, nên admin thêm một danh mục là
 * nó có mặt ngay, không phải sửa file này.
 *
 * "Thử kính ảo" chỉ hiện khi config('ar.nav_enabled') bật — ẩn bằng cách KHÔNG
 * in ra HTML chứ không phải display:none. Xem đầu config/ar.php.
 */

$company = config('company');
$segment = currentSegment();

/*
 * Dùng ở CẢ HAI chỗ: bảng xổ desktop và khối <details> của ngăn kéo.
 *
 * withProductCounts() chứ không phải visible(): bảng xổ hiện số mặt hàng bên
 * cạnh mỗi danh mục — thông tin khách cần TRƯỚC khi bấm. Một câu truy vấn có
 * GROUP BY, chạy một lần cho cả header.
 */
$categories = CategoryModel::withProductCounts();

/*
 * Bộ sưu tập đang trưng bày — ĐỌC MỘT LẦN, dùng ở BA chỗ: bảng xổ "Bộ sưu
 * tập" (desktop và ngăn kéo) và thẻ ảnh ở cột cuối của mega menu.
 */
$collectionsNav = CollectionModel::visible();

/* "Sản phẩm" đang mở khi đứng ở trang danh sách HOẶC trang chi tiết — cả hai
   nằm dưới /san-pham nên so đoạn đầu là đủ. mega-menu.php đọc biến này. */
$isProductActive = $segment === 'san-pham';

/* "Bộ sưu tập" sáng ở cả /bo-suu-tap lẫn /bo-suu-tap/{slug}.
   KHÔNG tính /san-pham?collection=<slug>: đó là trang danh sách đã lọc sẵn,
   mục đang mở ở đấy phải là "Sản phẩm". */
$isCollectionActive = $segment === 'bo-suu-tap';

/*
 * Thứ tự hiển thị của thanh nav. Mục mang 'mega' => true hoặc 'bst' => true là
 * chỗ chèn một bảng xổ; chúng không có 'url' vì partial tương ứng tự dựng cả
 * <li> lẫn liên kết của mình. Để hai cái mốc đó NẰM TRONG danh sách (thay vì
 * in riêng trước/sau vòng lặp) nên đọc file là thấy ngay thứ tự thật.
 */
$navItems = [
    ['label' => t('nav.home'),   'url' => '/',           'match' => ['', 'home']],
    ['mega'  => true],
    // Ngay sau "Sản phẩm": thử kính là một cách xem hàng, không phải một trang
    // giới thiệu. Đứng cạnh thứ nó phục vụ.
    ['label' => t('nav.ar'),      'url' => '/thu-ar',     'match' => ['thu-ar'], 'feature' => 'ar'],
    ['label' => t('nav.about'),   'url' => '/gioi-thieu', 'match' => ['gioi-thieu']],
    ['bst'   => true],
    ['label' => t('nav.contact'), 'url' => '/lien-he',    'match' => ['lien-he']],
];

/*
 * Trang không có chỗ trên thanh nav. Chúng vẫn có lối vào ở chân trang; ở đây
 * là lối vào cho màn hẹp, nơi chân trang nằm sau một quãng cuộn rất dài.
 */
$mobileExtra = [
    ['label' => t('nav.booking'), 'url' => '/dat-lich',   'match' => ['dat-lich']],
    ['label' => t('nav.policy'),  'url' => '/chinh-sach', 'match' => ['chinh-sach']],
];

/**
 * Mục này có được hiện không?
 *
 * Mục mang 'feature' => 'x' chỉ hiện khi config('x.nav_enabled') bật. Hiện chỉ
 * "Thử kính ảo" dùng tới, nhưng quy ước là chung.
 */
$featureOn = static fn (array $item): bool =>
    !isset($item['feature']) || (bool) config($item['feature'] . '.nav_enabled');

$navItems    = array_values(array_filter($navItems, $featureOn));
$mobileExtra = array_values(array_filter($mobileExtra, $featureOn));

// Giữ lại từ khoá đang tìm để ô tìm kiếm không bị xoá trắng sau khi submit
$keyword = $_GET['q'] ?? '';

/**
 * Mục đang mở? So theo đoạn đầu URL để route con vẫn sáng đúng mục cha —
 * /san-pham/{slug} vẫn làm sáng "Sản phẩm". "Trang chủ" khớp chuỗi rỗng vì
 * currentSegment() trả '' cho đường dẫn '/'.
 */
$isActive = static fn (array $item): bool => in_array($segment, $item['match'] ?? [], true);
?>

<a class="skip-link" href="#noi-dung-chinh"><?= e(t('a11y.skip')) ?></a>

<?php
/* ============================================================
   1. DẢI TIỆN ÍCH — NẰM NGOÀI <header>, VÀ ĐÓ LÀ CHỦ Ý

   Nó không dính theo cuộn: cuộn xuống là nó khuất đi như mọi nội dung khác,
   còn thanh nav ở lại với chiều cao KHÔNG ĐỔI. Trước đây dải này nằm trong
   header và thu về 0 khi cuộn, làm cả trang đổi cao 40px mỗi lần — đúng cú
   "zoom lên zoom xuống" người dùng nhìn thấy.

   Furnish không có dải này. Nó được dựng theo đúng ngôn ngữ của theme (nền
   tối như chân trang, chữ IN HOA rất nhỏ) và mang thêm bộ chuyển ngôn ngữ —
   chỗ quy ước của một thanh tiện ích, và là chỗ giữ cho hàng nav chính sạch.
   ============================================================ */
?>
<div class="header-announce">
    <div class="header-announce__inner">
        <p class="header-announce__text"><?= e(t('announce.shipping')) ?></p>

        <?php
        /* BỘ CHUYỂN NGÔN NGỮ — hai liên kết thật, không phải <select> + JS.

           Mỗi liên kết là một URL đầy đủ tới chính trang đang đứng, nên nó
           chạy khi tắt JavaScript, mở được ở tab mới, và máy tìm kiếm đi
           theo được. core/i18n.php nhận ?lang=, ghi cookie rồi chuyển hướng
           về URL sạch — xem lý do ở đó.

           aria-current="true" chứ không phải "page": ngôn ngữ đang chọn
           không phải là "trang hiện tại". */
        ?>
        <nav class="langsw" aria-label="<?= e(t('lang.label')) ?>">
            <?php foreach (I18N_NGON_NGU as $ma => $ten): ?>
                <?php $dang = $ma === currentLang(); ?>
                <a class="langsw__item<?= $dang ? ' is-active' : '' ?>"
                   href="<?= e(langUrl($ma)) ?>"
                   lang="<?= e($ma) ?>"
                   <?= $dang ? 'aria-current="true"' : '' ?>><?= e(strtoupper($ma)) ?><span class="sr-only"> — <?= e($ten) ?></span></a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<header class="site-header" id="siteHeader">
    <div class="header-main">

        <?php
        /* WORDMARK HAI DÒNG — đúng khuôn của Furnish:
           `<span class="d-flex flex-column text-uppercase text-xs fw-bold lh-sm">`
           với dòng trên giãn chữ .12rem. Hai dòng chứ không một: nó cân với
           chiều cao của hàng nav và cho wordmark một khối đặc, không phải một
           dòng chữ trôi. */
        ?>
        <a href="/" class="header-logo" aria-label="Vin Eyewear">
            <span class="header-logo__mark">Vin</span>
            <span class="header-logo__sub">Eyewear</span>
        </a>

        <nav class="header-nav" aria-label="<?= e(t('nav.aria.main')) ?>">
            <ul class="header-nav__list" role="list">
                <?php foreach ($navItems as $item): ?>
                    <?php if (!empty($item['mega'])): ?>
                        <?php require VIEWS_PATH . '/_layout/mega-menu.php'; ?>
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

        <div class="header-actions">

            <?php
            /* SỐ ĐIỆN THOẠI — Furnish đặt nó ngay cạnh nút menu và in đậm.
               Với một cửa hàng kính thì đó là lối liên hệ được dùng nhiều nhất
               (đặt lịch đo mắt, hỏi còn hàng), nên nó xứng đáng chỗ ấy.
               Ẩn dưới 1101px: ở đó chỗ dành cho ba nút tác vụ và hamburger. */
            ?>
            <a class="header-phone" href="<?= e($company['hotline_href']) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M6.6 3.5h3l1.5 3.7-2 1.4a11 11 0 005.3 5.3l1.4-2 3.7 1.5v3a1.6 1.6 0 01-1.7 1.6A14.4 14.4 0 015 5.2 1.6 1.6 0 016.6 3.5z"
                          fill="none" stroke="currentColor" stroke-width="1.6"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span><?= e($company['hotline']) ?></span>
            </a>

            <?php
            /* TÌM KIẾM — bảng xổ chứa nguyên ô nhập.

               Là <button> chứ không <a>: màn cảm ứng không có "rê chuột", nên
               header.js bắt cú bấm và bật lớp .is-open. Không có JS thì trang
               danh sách sản phẩm vẫn còn ô tìm kiếm riêng. */
            ?>
            <div class="hpop hpop--search" data-hpop>
                <button
                    type="button"
                    class="hpop__trigger header-action"
                    id="headerSearchToggle"
                    data-hpop-trigger
                    aria-label="<?= e(t('search.aria')) ?>"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="headerSearchPanel"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>

                <div class="hpop__panel hpop__panel--search" id="headerSearchPanel">
                    <p class="hpop__head"><?= e(t('search.title')) ?></p>
                    <form class="header-search__form" role="search" action="/tim-kiem" method="get">
                        <label class="sr-only" for="headerSearch"><?= e(t('search.title')) ?></label>
                        <svg class="header-search__ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/>
                            <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                        <?php /* GỢI Ý TỪ KHOÁ — <datalist> chứ không phải hộp thả
                                 tự vẽ: trình duyệt lo phần khó (bàn phím lên/xuống,
                                 trình đọc màn hình, chạm ra ngoài để đóng, vị trí
                                 hộp khi ô sát mép màn). Danh sách RỖNG lúc máy chủ
                                 vẽ trang; search-suggest.js đổ vào sau mỗi lần gõ. */ ?>
                        <input
                            type="search"
                            id="headerSearch"
                            name="q"
                            class="header-search__input"
                            placeholder="<?= e(t('search.placeholder')) ?>"
                            list="headerSearchSuggest"
                            autocomplete="off"
                            data-suggest="/tim-kiem/goi-y"
                            value="<?= e($keyword) ?>"
                        >
                        <datalist id="headerSearchSuggest"></datalist>
                        <button type="submit" class="header-search__submit"><?= e(t('search.submit')) ?></button>
                    </form>
                </div>
            </div>

            <?php
            /*
             * TÀI KHOẢN — icon vào thẳng /auth, KHÔNG kèm ?redirect=.
             *
             * ?redirect= dành riêng cho trường hợp khách BỊ CHẶN: đang muốn tới
             * /gio-hang thì AuthMiddleware::requireLogin() đá về đây, và đăng
             * nhập xong phải trả họ lại đúng chỗ đang dở. Bấm icon này là
             * chuyện khác — khách CHỦ ĐỘNG vào tài khoản của mình. Gắn địa chỉ
             * hiện tại vào thì đứng ở trang chủ bấm vào đây, đăng nhập xong
             * lại quay về trang chủ.
             *
             * KHÔNG CÓ NHÁNH NÀO CHO PHIÊN QUẢN TRỊ, và không thể có: trang cửa
             * hàng không nhận được cookie `vin_admin` (xem App::startSession),
             * nên nó không biết — và không được biết — có ai đang đăng nhập khu
             * quản trị hay không.
             */
            $isLoggedIn = AuthMiddleware::check();
            $accountUrl = $isLoggedIn ? '/tai-khoan' : '/auth';
            ?>
            <div class="hpop" data-hpop>
                <a href="<?= e($accountUrl) ?>" class="hpop__trigger header-action"
                   data-hpop-trigger
                   aria-label="<?= e($isLoggedIn ? t('account.mine') : t('account.login')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M4 20.5c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </a>

                <div class="hpop__panel">
                    <?php /* Luôn là "Tài khoản", kể cả khi chưa đăng nhập: lấy
                             "Đăng nhập" làm nhãn đầu bảng thì nó lặp lại đúng
                             chữ của mục ngay bên dưới. */ ?>
                    <p class="hpop__head"><?= e(t('account.title')) ?></p>
                    <ul class="hpop__list" role="list">
                        <?php if ($isLoggedIn): ?>
                            <li><a class="hpop__item" href="/tai-khoan"><?= e(t('account.info')) ?></a></li>
                            <li><a class="hpop__item" href="/tai-khoan?muc=don-hang"><?= e(t('account.orders')) ?></a></li>
                            <li><a class="hpop__item" href="/tai-khoan?muc=lich-hen"><?= e(t('account.appointments')) ?></a></li>
                            <li>
                                <?php /* Đăng xuất qua POST: một thẻ <img src="/auth/dang-xuat">
                                         trên trang khác cũng đủ để đá khách ra nếu dùng GET. */ ?>
                                <form method="post" action="/auth/dang-xuat">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <button type="submit" class="hpop__item hpop__item--btn"><?= e(t('account.logout')) ?></button>
                                </form>
                            </li>
                        <?php else: ?>
                            <li><a class="hpop__item" href="/auth"><?= e(t('account.login')) ?></a></li>
                            <li><a class="hpop__item" href="/auth?tab=dang-ky"><?= e(t('account.register')) ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <?php /* GIỎ HÀNG — huy hiệu và bảng xổ, ở _layout/header-cart.php.
                     Nằm riêng một file vì master.php cũng in nó khi trả lời
                     buy-flow.js ở chế độ mảnh. */ ?>
            <?php partial('_layout/header-cart'); ?>

            <?php /* Hamburger — chỉ hiện dưới 1101px. Furnish để nút này hiện ở
                     MỌI bề ngang vì navbar của nó không có gì khác để bấm; ở đây
                     thanh nav đầy đủ đã hiện từ 1101px nên nút là thừa. */ ?>
            <button
                type="button"
                class="header-burger tap-target"
                id="navToggle"
                aria-label="<?= e(t('menu.open')) ?>"
                aria-expanded="false"
                aria-controls="mobileNav"
            >
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<?php
/* ============================================================
   NGĂN KÉO (màn hẹp) — nằm NGOÀI <header> để position:fixed không bị
   backdrop-filter của header neo lại (thuộc tính đó tạo containing block
   mới, khiến phần tử fixed bên trong bám vào header thay vì vào khung nhìn).
   ============================================================ */
?>
<div class="mobile-nav" id="mobileNav" hidden>
    <div class="mobile-nav__backdrop" data-close-nav></div>

    <div class="mobile-nav__panel" role="dialog" aria-modal="true" aria-label="<?= e(t('menu.aria')) ?>">

        <div class="mobile-nav__head">
            <span class="mobile-nav__logo">
                <span class="header-logo__mark">Vin</span>
                <span class="header-logo__sub">Eyewear</span>
            </span>
            <button type="button" class="mobile-nav__close tap-target" data-close-nav aria-label="<?= e(t('menu.close')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <?php
        /* Cùng một danh sách với thanh nav desktop, cùng thứ tự — chỗ nào là
           bảng xổ thì thành khối <details> bung ra danh mục. Dùng chung
           $navItems nên bật/tắt "Thử kính ảo" hay đổi thứ tự chỉ sửa một chỗ;
           hai danh sách song song là kiểu sai lệch dần mà không ai thấy. */
        ?>
        <nav class="mobile-nav__links" aria-label="<?= e(t('nav.aria.main')) ?>">
            <?php foreach (array_merge($navItems, $mobileExtra) as $item): ?>
                <?php if (!empty($item['mega'])): ?>
                    <?php require VIEWS_PATH . '/_layout/mega-menu-mobile.php'; ?>
                <?php elseif (!empty($item['bst'])): ?>
                    <?php require VIEWS_PATH . '/_layout/collection-menu-mobile.php'; ?>
                <?php else: ?>
                    <a href="<?= e($item['url']) ?>"<?= $isActive($item) ? ' class="is-active" aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="mobile-nav__foot">
            <a href="/dat-lich" class="btn-primary"><?= e(t('cta.book')) ?></a>
            <a href="<?= e($company['hotline_href']) ?>" class="btn-outline"><?= e(t('cta.call', [':phone' => $company['hotline']])) ?></a>
        </div>
    </div>
</div>
