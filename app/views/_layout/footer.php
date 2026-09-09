<?php

/**
 * _layout/footer.php
 *
 * Dựng theo CHÂN TRANG CỦA FURNISH (`furnish-1.0.0/src/index.html`, khối
 * `<footer class="bg-dark pt-8 footer">`).
 *
 * Nhịp của bản mẫu, từ trên xuống:
 *   1. wordmark bên trái · danh sách liên kết nằm NGANG bên phải
 *   2. một câu tuyên ngôn cỡ `display-3` · cụm icon mạng xã hội vuông
 *   3. địa chỉ email cỡ lớn · nút "Contact us"
 *   4. một đường kẻ mảnh
 *   5. dải pháp lý: bản quyền trái · hai liên kết phải
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HAI CHỖ CỐ Ý LỆCH BẢN MẪU
 *
 * 1. GIỮ BỐN CỘT LIÊN KẾT THẬT. Furnish chỉ có một hàng năm liên kết nằm
 *    ngang — đủ cho một trang giới thiệu năm trang. Site này có danh mục sản
 *    phẩm đọc từ CSDL, và chân trang là LỐI VÀO DUY NHẤT trên desktop của
 *    "Giới thiệu", "Chính sách & FAQ", "Đặt lịch đo mắt" (thanh nav rút còn
 *    năm mục). Rút xuống một hàng là bỏ đường đi tới bốn trang.
 *
 * 2. GIỮ TÊN PHÁP NHÂN + MÃ SỐ THUẾ ở dải cuối. Bản mẫu chỉ có dòng bản
 *    quyền; bỏ hai thứ này là bỏ thông tin bắt buộc của một website thương
 *    mại điện tử.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Số điện thoại, email, giờ mở cửa đọc từ config/company.php.
 */

$company = config('company');

/*
 * Danh mục ĐỌC TỪ CSDL, không gõ cứng.
 *
 * Bảng xổ "Sản phẩm" trên header cũng đọc từ đó. Gõ cứng ba dòng ở đây thì
 * ngay lần admin thêm danh mục thứ tư, header có nó mà chân trang thì không —
 * một sai lệch không ai để ý cho tới khi khách hỏi.
 *
 * Cắt ở 6: cột này dài ra là ba cột kia trống một khoảng bằng đúng phần dôi.
 */
$products = array_map(
    static fn (array $c): array => [
        'label' => $c['name'],
        'url'   => danhMucUrl($c['slug']),
    ],
    array_slice(CategoryModel::visible(), 0, 6)
);

/*
 * "Thử kính ảo" theo cùng một cờ với thanh nav — xem config/ar.php. Một cờ,
 * ba chỗ; để footer tự quyết thì bật tính năng lên sẽ sót đúng chỗ này.
 */
$services = array_values(array_filter([
    ['label' => t('services.about'),    'url' => '/gioi-thieu'],
    ['label' => t('services.booking'),  'url' => '/dat-lich'],
    config('ar.nav_enabled')
        ? ['label' => t('services.ar'), 'url' => '/thu-ar']
        : null,
    ['label' => t('services.warranty'), 'url' => '/chinh-sach#doi-tra'],
    ['label' => t('services.policy'),   'url' => '/chinh-sach'],
]));

/**
 * Icon mạng xã hội — path dựng sẵn thay vì một thư viện icon.
 *
 * Furnish dùng font Bootstrap Icons cho bốn icon này (`bi-facebook`…), tức
 * kéo về ~100KB webfont để vẽ bốn hình. Site đã có nếp dùng inline SVG, và
 * bốn `<path>` dưới đây nặng chưa tới 1KB.
 */
$socialPaths = [
    'facebook'  => 'M14 8.5h2.2V5.6h-2.4c-2.5 0-3.9 1.5-3.9 3.9v1.6H8v3h1.9V21h3v-6.9h2.2l.4-3h-2.6V9.8c0-.9.3-1.3 1.1-1.3z',
    'instagram' => 'M12 7.6a4.4 4.4 0 100 8.8 4.4 4.4 0 000-8.8zm0 7.2a2.8 2.8 0 110-5.6 2.8 2.8 0 010 5.6zM17.4 7.4a1 1 0 11-2 0 1 1 0 012 0zM7.5 3.5h9A4 4 0 0120.5 7.5v9a4 4 0 01-4 4h-9a4 4 0 01-4-4v-9a4 4 0 014-4zm0 1.6a2.4 2.4 0 00-2.4 2.4v9a2.4 2.4 0 002.4 2.4h9a2.4 2.4 0 002.4-2.4v-9a2.4 2.4 0 00-2.4-2.4z',
    'youtube'   => 'M21.3 8.1a2.4 2.4 0 00-1.7-1.7C18.1 6 12 6 12 6s-6.1 0-7.6.4A2.4 2.4 0 002.7 8.1 25 25 0 002.3 12c0 1.3.1 2.6.4 3.9a2.4 2.4 0 001.7 1.7C5.9 18 12 18 12 18s6.1 0 7.6-.4a2.4 2.4 0 001.7-1.7c.3-1.3.4-2.6.4-3.9s-.1-2.6-.4-3.9zM10.2 14.6V9.4l5 2.6z',
    'tiktok'    => 'M16.6 5.82A4.28 4.28 0 0115.54 3h-3.09v12.4a2.59 2.59 0 11-2.59-2.59c.27 0 .53.04.77.12V9.77a5.76 5.76 0 00-.77-.05 5.66 5.66 0 105.66 5.66V9.01a7.35 7.35 0 004.3 1.38V7.3a4.28 4.28 0 01-3.22-1.48z',
];

/* "Điều khoản" chỉ hiện khi văn bản ĐÃ TỒN TẠI. Nó vốn trỏ cứng tới
   /chinh-sach#dieu-khoan, mà config/policy.php không có mục nào mang neo đó —
   bấm vào chỉ nhảy lên đầu trang. Một liên kết pháp lý dẫn tới không đâu thì
   tệ hơn là không có liên kết. Điền config/auth.php ['consent']['terms_url']
   là nó tự hiện lại ở đây, ở chân trang đăng nhập và trong câu đồng ý khi
   đăng ký. */
$termsUrl = (string) config('auth.consent.terms_url', '');
?>
<footer class="site-footer">
    <div class="fx-shell">

        <?php /* ── 1. Wordmark + bốn cột liên kết ─────────────────────── */ ?>
        <div class="row footer-top">

            <div class="col-12 col-lg-4 footer-brand">
                <p class="footer-logo">
                    <span class="header-logo__mark">Vin</span>
                    <span class="header-logo__sub">Eyewear</span>
                </p>
                <p class="footer-blurb"><?= e(t('footer.blurb')) ?></p>
            </div>

            <div class="col-6 col-lg-2 footer-col">
                <h2 class="footer-heading"><?= e(t('footer.products')) ?></h2>
                <ul class="footer-links" role="list">
                    <?php /* lang="vi": tên danh mục đọc thẳng từ CSDL và chỉ có
                             tiếng Việt, trong khi khung trang mặc định là
                             lang="en". Không đánh dấu thì trình đọc màn hình
                             phát âm "Gọng kính" theo quy tắc tiếng Anh. Cùng
                             lý do với tên sản phẩm — xem _layout/product-card.php. */ ?>
                    <?php foreach ($products as $item): ?>
                        <li><a href="<?= e($item['url']) ?>" lang="vi"><?= e($item['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-6 col-lg-3 footer-col">
                <h2 class="footer-heading"><?= e(t('footer.about')) ?></h2>
                <ul class="footer-links" role="list">
                    <?php foreach ($services as $item): ?>
                        <li><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="col-12 col-lg-3 footer-col">
                <h2 class="footer-heading"><?= e(t('footer.contact')) ?></h2>
                <ul class="footer-contact" role="list">
                    <li>
                        <?= e(t('footer.hotline')) ?>:
                        <a class="footer-phone" href="<?= e($company['hotline_href']) ?>"><?= e($company['hotline']) ?></a>
                    </li>
                    <li><?= e($company['open_hours'] ?? '8:30 – 21:30') ?></li>
                    <li><a class="footer-break" href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a></li>
                    <li><a href="/lien-he"><?= e(t('footer.stores')) ?></a></li>
                </ul>
            </div>
        </div>

        <?php
        /* ── 2. Câu tuyên ngôn + mạng xã hội ──────────────────────────
           Đây là mảnh đặc trưng nhất của chân trang Furnish: một câu cỡ rất
           lớn, nét mảnh, chiếm gần nửa chiều rộng — nó biến chân trang từ
           một bảng liên kết thành một phần của thiết kế. */
        ?>
        <div class="row footer-statement">
            <div class="col-12 col-lg-7">
                <p class="footer-statement__text"><?= e(t('footer.statement')) ?></p>
            </div>

            <?php
            /*
             * ─────────────────────────────────────────────────────────────────
             * CỤM "LIÊN HỆ" — GỘP TỪ HAI HÀNG CŨ THÀNH MỘT
             *
             * Trước đợt này chân trang có BỐN hàng, và hai trong số đó gần như
             * rỗng nửa bên phải:
             *
             *   hàng 2   tuyên ngôn (7 cột) | icon mạng xã hội trôi một mình
             *   hàng 3   nhãn EMAIL + email cỡ lớn | nút "Liên hệ" ở tít mép phải
             *
             * Ba thứ đó — email, nút liên hệ, mạng xã hội — đều trả lời đúng
             * MỘT câu hỏi: "làm sao gọi được cho các anh?". Tách chúng ra ba
             * góc màn hình biến một ý thành ba mảnh rời, và để lại hai dải
             * trống lớn mà mắt đọc ra là chân trang bị bỏ dở chứ không phải
             * khoảng thở có chủ ý.
             *
             * Nay chúng đứng cùng nhau ở cột phải của chính hàng tuyên ngôn.
             *
             * ĐÃ BỎ KHỐI EMAIL CỠ LỚN. Địa chỉ ấy đã có mặt ở cột "Liên hệ"
             * ngay phía trên, nên nó là bản sao thứ hai của cùng một chuỗi
             * trên cùng một màn hình — xoá nó cắt được trọn một hàng trống mà
             * không mất một thông tin nào.
             * ─────────────────────────────────────────────────────────────────
             */
            ?>
            <div class="col-12 col-lg-5 footer-statement__side">
                <a class="footer-reach__cta" href="/lien-he"><?= e(t('footer.cta')) ?></a>

                <ul class="footer-socials" role="list">
                    <?php foreach ($company['socials'] as $social): ?>
                        <?php $path = $socialPaths[$social['icon']] ?? null; ?>
                        <?php if ($path === null) continue; ?>
                        <li>
                            <a class="btn-icon" href="<?= e($social['href']) ?>"
                               target="_blank" rel="noreferrer noopener"
                               aria-label="<?= e($social['label']) ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="<?= e($path) ?>"/>
                                </svg>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <hr class="footer-rule">

        <?php /* ── 4. Dải pháp lý ──────────────────────────────────────── */ ?>
        <div class="row footer-legal">
            <div class="col-12 col-md-7">
                <p class="footer-legal__text">
                    © <?= date('Y') ?> Vin Eyewear · <?= e($company['name']) ?>
                    · <?= e(t('footer.tax')) ?> <?= e($company['tax_code']) ?>
                </p>
            </div>
            <nav class="col-12 col-md-5 footer-legal__nav" aria-label="<?= e(t('footer.legal_nav')) ?>">
                <a href="<?= e((string) config('auth.consent.privacy_url', '/chinh-sach#bao-mat')) ?>"><?= e(t('footer.privacy')) ?></a>
                <?php if ($termsUrl !== ''): ?>
                    <a href="<?= e($termsUrl) ?>"><?= e(t('footer.terms')) ?></a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</footer>
