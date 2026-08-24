<?php

/**
 * _layout/header.php
 *
 * Dựng theo "Vin Eyewear Home.dc.html" (Claude Design).
 *
 * Bố cục 2 tầng thay cho 3 tầng cũ:
 *   1. Dải thông báo (nền brand) — nằm NGOÀI <header>, cuộn là khuất đi
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
    ['label' => t('nav.home'),   'url' => '/',           'match' => ['', 'home']],
    ['mega'  => true],
    // Ngay sau "Sản phẩm": thử kính là một cách xem hàng, không phải một
    // trang giới thiệu. Đứng cạnh thứ nó phục vụ.
    ['label' => t('nav.tryon'),  'url' => '/thu-ar',     'match' => ['thu-ar'], 'feature' => 'ar'],
    ['label' => t('nav.about'),  'url' => '/gioi-thieu', 'match' => ['gioi-thieu']],
    ['label' => t('nav.events'), 'url' => '/su-kien',    'match' => ['su-kien']],
    ['label' => t('nav.contact'),'url' => '/lien-he',    'match' => ['lien-he']],
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
    ['label' => t('nav.booking'), 'url' => '/dat-lich',   'match' => ['dat-lich']],
    ['label' => t('nav.policy'),  'url' => '/chinh-sach', 'match' => ['chinh-sach']],
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

<a class="skip-link" href="#noi-dung-chinh"><?= e(t('action.skip')) ?></a>

<!-- ============================================================
     1. DẢI THÔNG BÁO — NẰM NGOÀI <header>, VÀ ĐÓ LÀ CHỦ Ý

     Trước đây dải này nằm trong header và thu về 0 chiều cao khi cuộn, kèm
     wordmark co từ 24px xuống 21px. Header dính theo cuộn nhưng VẪN NẰM
     TRONG LUỒNG, nên mỗi lần thu/bung là cả trang đổi cao 40px (đo được:
     123px <-> 83px): chữ to nhỏ, nội dung bên dưới trượt lên rồi trượt
     xuống — đúng cú "zoom lên zoom xuống" người dùng nhìn thấy.

     Nay dải là ANH EM của <header> và không dính: cuộn xuống thì nó khuất
     đi như mọi nội dung khác, thanh nav ở lại với chiều cao KHÔNG ĐỔI. Vẫn
     được đúng thứ hiệu ứng cũ nhắm tới — nhường chỗ khi đọc — mà không có
     gì đổi kích thước.

     VÌ SAO KHÔNG ĐỂ TRONG <header> RỒI CHO .header-main STICKY: phần tử
     sticky chỉ dính được trong phạm vi hộp cha. Để nguyên chỗ cũ thì
     .header-main chỉ bám được hết chiều cao <header> (123px) rồi trôi mất
     khỏi màn hình.
     ============================================================ -->
<div class="header-announce">
    <p class="header-announce__text"><?= e(t('announce')) ?></p>
</div>

<header class="site-header" id="siteHeader">

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

        <?php
        /*
         * ─────────────────────────────────────────────────────────────────────
         * BỐN NÚT TÁC VỤ — MỘT KHUÔN DUY NHẤT (.hpop)
         *
         * Ngôn ngữ · Tìm kiếm · Tài khoản · Giỏ hàng. Cả bốn nay KHÔNG còn
         * khung viền trắng bao quanh — chúng nằm thẳng trên nền của thanh điều
         * hướng, chỉ đổi màu khi rê chuột, đúng như các mục nav ngay bên cạnh.
         *
         * Cả bốn cùng bung một bảng xổ khi rê chuột (và khi tiêu điểm bàn phím
         * đi vào cụm, nhờ :focus-within). Trước đây chỉ bảng ngôn ngữ có bảng
         * xổ, mà lại mở bằng cách bấm.
         *
         * VÌ SAO KHÔNG CÒN <details> Ở NÚT NGÔN NGỮ
         *   <details> mở/đóng bằng thuộc tính `open`, CSS không với tới được,
         *   nên không thể cho nó bung ra khi rê chuột mà không viết JS điều
         *   khiển state. Nay bảng xổ hiện bằng :hover/:focus-within — thuần
         *   CSS, chạy cả khi tắt JavaScript.
         *
         * MÀN HÌNH CẢM ỨNG không có "rê chuột". Nên:
         *   - Tài khoản và Giỏ hàng vẫn là <a> thật: chạm là đi thẳng tới
         *     /tai-khoan và /gio-hang, y như trước.
         *   - Ngôn ngữ và Tìm kiếm là <button>: assets/js/header.js bắt cú bấm
         *     và bật lớp .is-open. Không có JS thì nút ngôn ngữ vẫn mở được
         *     bằng phím Tab (focus-within), còn ô tìm kiếm luôn có lối khác ở
         *     trang danh sách sản phẩm.
         * ─────────────────────────────────────────────────────────────────────
         */
        ?>
        <div class="header-actions">

            <?php
            /*
             * ĐỔI NGÔN NGỮ (VI / EN)
             *
             * Hai lựa chọn là LIÊN KẾT THẬT tới /ngon-ngu, nên tắt JS vẫn đổi
             * được. rel="nofollow": máy tìm kiếm không cần đi theo, và đi theo
             * thì cùng một trang bị lập chỉ mục hai lần.
             *
             * PHẠM VI DỊCH rất hẹp — chỉ khung giao diện. Xem ghi chú dài ở
             * đầu config/lang/vi.php trước khi hứa với ai là site có tiếng Anh.
             */
            $lang = currentLang();

            /*
             * Đường quay lại GIỮ CẢ chuỗi truy vấn: đang lọc
             * /san-pham?category=gong-kinh&material=Titanium mà đổi ngôn ngữ
             * thì phải về đúng danh sách đó, không phải về cả kho.
             *
             * Ghép tay từ currentPath() + QUERY_STRING chứ không lấy thẳng
             * REQUEST_URI: chuỗi kia đến từ trình duyệt và còn mang cả fragment
             * lẫn ký tự lạ, mà giá trị này đi vào một tham số chuyển hướng.
             */
            $back = currentPath();
            $query = (string) ($_SERVER['QUERY_STRING'] ?? '');

            if ($query !== '') {
                $back .= '?' . $query;
            }
            ?>
            <div class="hpop hpop--lang" data-hpop>
                <button type="button"
                        class="hpop__trigger hpop__trigger--text"
                        data-hpop-trigger
                        aria-haspopup="true"
                        aria-expanded="false"
                        title="<?= e(t('lang.label')) ?>">
                    <?= e(strtoupper($lang)) ?>
                    <svg class="hpop__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>

                <div class="hpop__panel">
                    <p class="hpop__head"><?= e(t('lang.label')) ?></p>
                    <ul class="hpop__list" role="list">
                        <?php foreach (['vi' => t('lang.vi'), 'en' => t('lang.en')] as $code => $label): ?>
                            <li>
                                <a class="hpop__item<?= $lang === $code ? ' is-on' : '' ?>"
                                   lang="<?= e($code) ?>"
                                   rel="nofollow"
                                   <?= $lang === $code ? 'aria-current="true"' : '' ?>
                                   href="/ngon-ngu?<?= e(http_build_query(['lang' => $code, 'redirect' => $back])) ?>"><?= e($label) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php
            /*
             * TÌM KIẾM — bảng xổ chứa nguyên ô nhập, không phải một khay riêng
             * nữa. Bản thiết kế chỉ vẽ một icon kính lúp; ô nhập là phần bản
             * thiết kế không vẽ tới (nó chỉ có một trạng thái tĩnh).
             */
            ?>
            <div class="hpop hpop--search" data-hpop>
                <button
                    type="button"
                    class="hpop__trigger header-action"
                    id="headerSearchToggle"
                    data-hpop-trigger
                    aria-label="<?= e(t('action.search')) ?>"
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
                    <p class="hpop__head"><?= e(t('action.search')) ?></p>
                    <form class="header-search__form" role="search" action="/tim-kiem" method="get">
                        <label class="sr-only" for="headerSearch"><?= e(t('action.search')) ?></label>
                        <svg class="header-search__ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/>
                            <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                        </svg>
                        <input
                            type="search"
                            id="headerSearch"
                            name="q"
                            class="header-search__input"
                            placeholder="<?= e(t('search.placeholder')) ?>"
                            value="<?= e($keyword) ?>"
                        >
                        <button type="submit" class="header-search__submit"><?= e(t('search.submit')) ?></button>
                    </form>
                </div>
            </div>

            <?php
            /*
             * TÀI KHOẢN — icon vào thẳng /auth, KHÔNG kèm ?redirect=.
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

            /*
             * PHIÊN NỘI BỘ THÌ ICON NÀY DẪN VỀ /quan-tri, KHÔNG PHẢI /tai-khoan.
             *
             * check() đã trả false cho tài khoản nội bộ (xem
             * AuthMiddleware::customerId), nên không có dòng này thì người
             * đang mở khu quản trị nhìn thấy đúng cái header của khách chưa
             * đăng nhập: bấm icon là tới /auth, gõ mật khẩu nội bộ vào đó thì
             * bị từ chối. Đủ để họ tưởng mình vừa bị đăng xuất.
             *
             * Đây chỉ là chuyện HIỆN RA CÁI GÌ. Việc chặn nằm ở
             * AuthMiddleware::requireLogin(); đổi mấy dòng này không mở thêm
             * cửa nào.
             */
            $isStaff    = AuthMiddleware::isStaffSession();
            $accountUrl = $isStaff ? '/quan-tri' : ($isLoggedIn ? '/tai-khoan' : '/auth');
            ?>
            <div class="hpop" data-hpop>
                <a href="<?= e($accountUrl) ?>" class="hpop__trigger header-action"
                   data-hpop-trigger
                   aria-label="<?= e($isStaff ? t('pop.admin') : ($isLoggedIn ? t('action.account') : t('action.login'))) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M4 20.5c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </a>

                <div class="hpop__panel">
                    <?php /* Luôn là "Tài khoản", kể cả khi chưa đăng nhập: lấy
                             t('action.login') làm nhãn đầu bảng thì nó lặp lại
                             đúng chữ của mục ngay bên dưới. */ ?>
                    <p class="hpop__head"><?= e(t('pop.account')) ?></p>
                    <ul class="hpop__list" role="list">
                        <?php if ($isStaff): ?>
                            <?php /* Không bày "Đơn hàng của tôi" hay "Lịch hẹn đo mắt":
                                     tài khoản nội bộ không có hai thứ đó, và bấm vào là
                                     bị requireLogin() đá ngược về /quan-tri. */ ?>
                            <li><a class="hpop__item" href="/quan-tri"><?= e(t('pop.admin')) ?></a></li>
                            <li>
                                <form method="post" action="/auth/dang-xuat">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <button type="submit" class="hpop__item hpop__item--btn"><?= e(t('pop.logout')) ?></button>
                                </form>
                            </li>
                        <?php elseif ($isLoggedIn): ?>
                            <li><a class="hpop__item" href="/tai-khoan"><?= e(t('pop.profile')) ?></a></li>
                            <li><a class="hpop__item" href="/tai-khoan?muc=don-hang"><?= e(t('pop.orders')) ?></a></li>
                            <li><a class="hpop__item" href="/tai-khoan?muc=lich-hen"><?= e(t('pop.bookings')) ?></a></li>
                            <li>
                                <?php /* Đăng xuất qua POST: một thẻ <img src="/auth/dang-xuat"> trên
                                         trang khác cũng đủ để đá khách ra nếu dùng GET. */ ?>
                                <form method="post" action="/auth/dang-xuat">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <button type="submit" class="hpop__item hpop__item--btn"><?= e(t('pop.logout')) ?></button>
                                </form>
                            </li>
                        <?php else: ?>
                            <li><a class="hpop__item" href="/auth"><?= e(t('action.login')) ?></a></li>
                            <li><a class="hpop__item" href="/auth?tab=dang-ky"><?= e(t('pop.register')) ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <?php /* GIỎ HÀNG — huy hiệu và bảng xổ, ở _layout/header-cart.php.
                     Nằm riêng một file vì master.php cũng in nó khi trả lời
                     buy-flow.js ở chế độ mảnh; chi tiết ghi trong file đó. */ ?>
            <?php partial('_layout/header-cart'); ?>

            <!-- Hamburger — chỉ hiện dưới 1100px -->
            <button
                type="button"
                class="header-burger tap-target"
                id="navToggle"
                aria-label="<?= e(t('action.open_menu')) ?>"
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
            <button type="button" class="mobile-nav__close tap-target" data-close-nav aria-label="<?= e(t('action.close_menu')) ?>">
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
            <?php
            /*
             * ĐỔI NGÔN NGỮ — bản cho menu trượt.
             *
             * Dưới 700px nút "VI" ở đầu trang bị ẩn: cụm tác vụ rộng cố định
             * 247px, wordmark chỉ còn phần thừa lại nên bị cắt còn "Vin E…"
             * (xem khối cuối components/header.css). Nút ngôn ngữ là thứ DUY
             * NHẤT trong cụm chưa có lối đi khác, nên nó chuyển vào đây chứ
             * không bị bỏ đi.
             *
             * $lang và $back tính ở cụm .header-actions phía trên — cùng một
             * file nên vẫn còn trong phạm vi.
             */
            ?>
            <div class="mobile-nav__lang">
                <span class="mobile-nav__langlabel"><?= e(t('lang.label')) ?></span>
                <div class="mobile-nav__langopts">
                    <?php foreach (['vi' => t('lang.vi'), 'en' => t('lang.en')] as $code => $label): ?>
                        <a class="mobile-nav__langopt<?= $lang === $code ? ' is-on' : '' ?>"
                           lang="<?= e($code) ?>"
                           rel="nofollow"
                           <?= $lang === $code ? 'aria-current="true"' : '' ?>
                           href="/ngon-ngu?<?= e(http_build_query(['lang' => $code, 'redirect' => $back])) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <a href="/dat-lich" class="btn-primary">Đặt Lịch Đo Mắt</a>
            <a href="<?= e($company['hotline_href']) ?>" class="btn-outline">Gọi <?= e($company['hotline']) ?></a>
        </div>
    </div>
</div>
