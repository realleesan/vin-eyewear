<?php

/**
 * _layout/header.php
 *
 * Dựng theo "Vin Eyewear Home.dc.html" (Claude Design).
 *
 * Bố cục 2 tầng thay cho 3 tầng cũ:
 *   1. Dải thông báo (nền brand) — ẩn dần khi cuộn xuống
 *   2. Một HÀNG DUY NHẤT: wordmark trái | điều hướng giữa | tác vụ phải
 *
 * Bản cũ đặt wordmark giữa và đẩy điều hướng xuống một thanh riêng bên dưới.
 * Thiết kế mới gom cả ba cụm vào một hàng cao 76px, nên thanh nav thứ hai
 * không còn. Bảng xổ thì có — xem phần dưới.
 *
 * GIỮ LẠI ngoài thiết kế (thiết kế chỉ vẽ bề ngang ≥1100px):
 *   - ô tìm kiếm bung ra, nút hamburger và menu trượt cho màn hình hẹp;
 *   - menu trượt vẫn liệt kê ĐỦ các trang (giới thiệu, chính sách, đặt lịch…)
 *     — năm mục của thanh nav không phủ hết site, bỏ hẳn thì những trang đó
 *     không còn lối vào nào trên điện thoại.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NĂM MỤC, MỘT TRONG SỐ ĐÓ LÀ BẢNG XỔ
 *
 *     Trang chủ · Sản phẩm ▾ · [Thử kính ảo] · Sự kiện · Liên hệ
 *
 * "Sản phẩm" không phải một liên kết thường: nó là _layout/mega-menu.php, tự
 * dựng <li> của mình. Ba mục danh mục cũ (Kính mát · Gọng kính · Tròng kính)
 * và "Thương hiệu" nay nằm TRONG bảng đó — và lấy thẳng từ CSDL, nên admin
 * thêm danh mục là tự có mặt, không phải sửa file này.
 *
 * "Thử kính ảo" chỉ hiện khi config('ar.nav_enabled') bật. Tính năng còn dở;
 * ẩn bằng cách KHÔNG in ra HTML chứ không phải display:none. Xem ghi chú dài
 * ở đầu config/ar.php.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$company = config('company');
$segment = currentSegment();

/*
 * Dùng ở CẢ HAI chỗ: bảng xổ desktop và khối <details> của menu trượt.
 *
 * withProductCounts() chứ không phải visible(): bảng xổ hiện số mặt hàng bên
 * cạnh mỗi danh mục. Đó là thông tin khách cần trước khi bấm — "Tròng kính 42"
 * nói rằng vào đó có gì để xem, còn một danh mục 0 món thì đừng dẫn người ta
 * vào ngõ cụt. Một câu truy vấn có GROUP BY, chạy một lần cho cả header.
 */
$categories = CategoryModel::withProductCounts();

/*
 * "Sản phẩm" đang mở khi đứng ở trang danh sách HOẶC trang chi tiết — cả hai
 * đều nằm dưới /san-pham, nên so đoạn đầu là đủ. Biến này do mega-menu.php và
 * mega-menu-mobile.php đọc.
 */
$isProductActive = $segment === 'san-pham';

/*
 * Thứ tự hiển thị của thanh nav, một mảng duy nhất. Mục mang 'mega' => true là
 * chỗ chèn bảng xổ; nó không có 'url' vì _layout/mega-menu.php tự dựng cả <li>
 * lẫn liên kết của mình. Để cái mốc đó NẰM TRONG danh sách (thay vì in riêng
 * trước/sau vòng lặp) nên đọc file là thấy ngay thứ tự thật của năm mục.
 */
$navItems = [
    ['label' => 'Trang chủ',   'url' => '/',           'match' => ['', 'home']],
    ['mega'  => true],
    // Ngay sau "Sản phẩm": thử kính là một cách xem hàng, không phải một
    // trang giới thiệu. Đứng cạnh thứ nó phục vụ.
    ['label' => 'Thử kính ảo', 'url' => '/thu-ar',     'match' => ['thu-ar'], 'feature' => 'ar'],
    ['label' => 'Giới thiệu',  'url' => '/gioi-thieu', 'match' => ['gioi-thieu']],
    ['label' => 'Sự kiện',     'url' => '/su-kien',    'match' => ['su-kien']],
    ['label' => 'Liên hệ',     'url' => '/lien-he',    'match' => ['lien-he']],
];

/*
 * Trang không có chỗ trên thanh nav. Chúng vẫn có lối vào ở chân trang (cột
 * "Về Vin Eyewear"); ở đây là lối vào cho điện thoại, nơi chân trang nằm sau
 * một quãng cuộn rất dài.
 *
 * "Giới thiệu" ĐÃ RA KHỎI danh sách này — nó lên thanh nav rồi, để lại thì
 * menu trượt hiện nó hai lần.
 */
$mobileExtra = [
    ['label' => 'Đặt lịch đo mắt',  'url' => '/dat-lich',   'match' => ['dat-lich']],
    ['label' => 'Chính sách & FAQ', 'url' => '/chinh-sach', 'match' => ['chinh-sach']],
];

/**
 * Mục này có được hiện không?
 *
 * Mục mang khoá 'feature' => 'x' chỉ hiện khi config('x.nav_enabled') bật.
 * Hiện chỉ "Thử kính ảo" ('ar') dùng tới, nhưng quy ước là chung: tính năng
 * làm dở sau này chỉ cần thêm 'nav_enabled' vào config của nó rồi gắn khoá
 * 'feature' vào mục nav, không phải sửa hàm này.
 */
$featureOn = static fn (array $item): bool =>
    !isset($item['feature']) || (bool) config($item['feature'] . '.nav_enabled');

$navItems    = array_values(array_filter($navItems, $featureOn));
$mobileExtra = array_values(array_filter($mobileExtra, $featureOn));

// Số lượng trong giỏ — giỏ hàng lưu ở session, chưa cần chạm DB
$cartCount = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));

// Giữ lại từ khoá đang tìm để ô tìm kiếm không bị xoá trắng sau khi submit
$keyword = $_GET['q'] ?? '';

/**
 * Mục đang mở? So theo đoạn đầu URL, để route con vẫn sáng đúng mục cha —
 * ví dụ /su-kien/{slug} vẫn làm sáng "Sự kiện".
 *
 * Riêng "Trang chủ" khớp chuỗi rỗng: currentSegment() trả '' cho đường dẫn '/'.
 */
$isActive = static fn (array $item): bool => in_array($segment, $item['match'] ?? [], true);
?>

<a class="skip-link" href="#noi-dung-chinh">Bỏ qua điều hướng, tới nội dung chính</a>

<header class="site-header" id="siteHeader">

    <!-- ============================================================
         1. DẢI THÔNG BÁO — thu về 0 chiều cao khi cuộn xuống
         ============================================================ -->
    <div class="header-announce">
        <p class="header-announce__text">
            Miễn phí giao hàng toàn quốc cho đơn từ 1.000.000₫
        </p>
    </div>

    <!-- ============================================================
         2. HÀNG CHÍNH — wordmark | điều hướng | tác vụ
         ============================================================ -->
    <div class="header-main">

        <a href="/" class="header-logo" aria-label="Vin Eyewear — về trang chủ">
            <span class="header-logo__text">Vin <em>Eyewear</em></span>
        </a>

        <nav class="header-nav" aria-label="Điều hướng chính">
            <ul class="header-nav__list" role="list">
                <?php foreach ($navItems as $item): ?>
                    <?php if (!empty($item['mega'])): ?>
                        <?php require VIEWS_PATH . '/_layout/mega-menu.php'; ?>
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

            <!-- Tìm kiếm — nút mở, ô nhập bung ra ngay dưới cụm tác vụ.
                 Thiết kế vẽ một icon kính lúp; ô nhập là phần bản thiết kế
                 không vẽ tới (nó chỉ có một trạng thái tĩnh). -->
            <div class="header-search">
                <button
                    type="button"
                    class="header-search__toggle header-action tap-target"
                    id="headerSearchToggle"
                    aria-label="Tìm kiếm sản phẩm"
                    aria-expanded="false"
                    aria-controls="headerSearchPanel"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </button>

                <form
                    class="header-search__panel"
                    id="headerSearchPanel"
                    role="search"
                    action="/san-pham"
                    method="get"
                    hidden
                >
                    <label class="sr-only" for="headerSearch">Tìm kiếm sản phẩm</label>
                    <svg class="header-search__ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                    <input
                        type="search"
                        id="headerSearch"
                        name="q"
                        class="header-search__input"
                        placeholder="Tìm gọng, tròng kính..."
                        value="<?= e($keyword) ?>"
                    >
                    <button type="submit" class="header-search__submit">Tìm</button>
                </form>
            </div>

            <?php
            /*
             * Icon tài khoản: vào thẳng /auth, KHÔNG kèm ?redirect=.
             *
             * ?redirect= dành riêng cho trường hợp khách BỊ CHẶN — đang muốn
             * tới /gio-hang hay /quan-tri thì AuthMiddleware::requireLogin()
             * đá về đây, và đăng nhập xong phải trả họ lại đúng chỗ đang dở.
             *
             * Bấm icon này là chuyện khác hẳn: khách CHỦ ĐỘNG muốn vào tài
             * khoản của mình. Gắn địa chỉ hiện tại vào thì đứng ở trang chủ
             * bấm vào đây, đăng nhập xong lại quay về trang chủ — đi một vòng
             * rồi về chỗ cũ, không tới được thứ vừa bấm để tới.
             * AuthController::login() mặc định là /tai-khoan, cứ để nó lo.
             */
            $isLoggedIn = AuthMiddleware::check();
            $accountUrl = $isLoggedIn ? '/tai-khoan' : '/auth';
            ?>
            <a href="<?= e($accountUrl) ?>" class="header-action tap-target"
               aria-label="<?= $isLoggedIn ? 'Tài khoản của tôi' : 'Đăng nhập' ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M4 20.5c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </a>

            <a href="/gio-hang" class="header-action tap-target" aria-label="Giỏ hàng, <?= (int) $cartCount ?> sản phẩm">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M5 8h14l-1.2 12H6.2L5 8z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M8.5 8V6.5a3.5 3.5 0 017 0V8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <!-- Thiết kế hiện huy hiệu cả khi giỏ trống (số 0). Ở đây chỉ
                     hiện khi có hàng: một chấm đỏ báo "0" là báo động giả. -->
                <?php if ($cartCount > 0): ?>
                    <span class="header-action__badge" aria-hidden="true"><?= (int) $cartCount ?></span>
                <?php endif; ?>
            </a>

            <!-- Hamburger — chỉ hiện dưới 1100px -->
            <button
                type="button"
                class="header-burger tap-target"
                id="navToggle"
                aria-label="Mở menu điều hướng"
                aria-expanded="false"
                aria-controls="mobileNav"
            >
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- ============================================================
     MENU TRƯỢT (mobile) — nằm NGOÀI <header> để position: fixed không bị
     ảnh hưởng bởi backdrop-filter của header (thuộc tính đó tạo containing
     block mới, khiến phần tử fixed bên trong bị neo vào header thay vì
     vào khung nhìn).
     ============================================================ -->
<div class="mobile-nav" id="mobileNav" hidden>
    <div class="mobile-nav__backdrop" data-close-nav></div>

    <div class="mobile-nav__panel" role="dialog" aria-modal="true" aria-label="Menu điều hướng">

        <div class="mobile-nav__head">
            <span class="mobile-nav__logo">Vin <em>Eyewear</em></span>
            <button type="button" class="mobile-nav__close tap-target" data-close-nav aria-label="Đóng menu">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <?php
        /*
         * Cùng một danh sách với thanh nav desktop, cùng thứ tự — chỗ nào là
         * bảng xổ thì thành khối <details> bung ra danh mục. Dùng chung
         * $navItems nên bật/tắt "Thử kính ảo" hay đổi thứ tự chỉ phải sửa một
         * chỗ; hai danh sách song song là kiểu sai lệch dần mà không ai thấy.
         *
         * $mobileExtra nối vào cuối: các trang không có chỗ trên thanh nav rút
         * gọn nhưng vẫn cần lối vào trên điện thoại.
         */
        ?>
        <nav class="mobile-nav__links" aria-label="Điều hướng chính">
            <?php foreach (array_merge($navItems, $mobileExtra) as $item): ?>
                <?php if (!empty($item['mega'])): ?>
                    <?php require VIEWS_PATH . '/_layout/mega-menu-mobile.php'; ?>
                <?php else: ?>
                    <a href="<?= e($item['url']) ?>"<?= $isActive($item) ? ' class="is-active" aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="mobile-nav__foot">
            <a href="/dat-lich" class="btn-primary">Đặt Lịch Đo Mắt</a>
            <a href="<?= e($company['hotline_href']) ?>" class="btn-outline">Gọi <?= e($company['hotline']) ?></a>
        </div>
    </div>
</div>
