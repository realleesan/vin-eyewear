<?php

/**
 * _layout/footer.php
 *
 * Dựng theo "Vin Eyewear Home.dc.html" (Claude Design).
 *
 * Lưới 4 cột 1.4fr/1fr/1fr/1fr — Thương hiệu | Sản phẩm | Dịch vụ | Liên hệ —
 * rồi một dải dưới cùng: bản quyền bên trái, hai liên kết pháp lý bên phải.
 *
 * HAI CHỖ CỐ Ý KHÁC BẢN THIẾT KẾ, đều vì lý do ngoài thẩm mỹ:
 *   1. Dải dưới cùng giữ TÊN PHÁP NHÂN + MÃ SỐ THUẾ. Bản thiết kế chỉ có
 *      "© 2026 Vin Eyewear. Bảo lưu mọi quyền." — bỏ hẳn hai thứ này là bỏ
 *      thông tin bắt buộc của một website thương mại điện tử.
 *   2. Giữ hàng icon mạng xã hội dưới đoạn giới thiệu. Thiết kế không vẽ,
 *      nhưng bỏ đi thì Facebook/Instagram/YouTube không còn lối vào nào trên
 *      site — mà với một shop kính thời trang thì đó là kênh chính.
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
 * Cắt ở 6: chân trang là bốn cột ngang nhau, cột này dài ra là ba cột kia
 * trống một khoảng bằng đúng phần dôi.
 */
$products = array_map(
    static fn (array $c): array => [
        'label' => $c['name'],
        'url'   => danhMucUrl($c['slug']),
    ],
    array_slice(CategoryModel::visible(), 0, 6)
);

/*
 * Cột 3 mang tiêu đề "Về Vin Eyewear" chứ không phải "Dịch vụ" như bản
 * Home.dc.html. CỐ Ý lệch: thanh nav rút còn năm mục nên "Giới thiệu" và
 * "Chính sách & FAQ" rơi khỏi đó, và chân trang là lối vào còn lại của chúng
 * trên desktop. Nhét hai trang ấy vào một cột tên "Dịch vụ" thì tiêu đề nói
 * sai về thứ nằm dưới nó.
 *
 * "Thử kính ảo" theo cùng một cờ với thanh nav — xem config/ar.php. Một cờ,
 * ba chỗ; để footer tự quyết thì bật tính năng lên sẽ sót đúng chỗ này.
 */
$services = array_values(array_filter([
    ['label' => 'Giới thiệu',       'url' => '/gioi-thieu'],
    ['label' => 'Đặt lịch đo mắt',     'url' => '/dat-lich'],
    config('ar.nav_enabled')
        ? ['label' => 'Thử kính ảo', 'url' => '/thu-ar']
        : null,
    ['label' => 'Bảo hành & đổi trả', 'url' => '/chinh-sach#doi-tra'],
    ['label' => 'Chính sách & FAQ',      'url' => '/chinh-sach'],
]));

/**
 * Icon mạng xã hội. Dùng path dựng sẵn thay vì thư viện icon: bản Lovable
 * kéo lucide-react (một gói npm), còn ở đây chỉ cần 4 hình cố định.
 */
$socialPaths = [
    'facebook'  => 'M14 8.5h2.2V5.6h-2.4c-2.5 0-3.9 1.5-3.9 3.9v1.6H8v3h1.9V21h3v-6.9h2.2l.4-3h-2.6V9.8c0-.9.3-1.3 1.1-1.3z',
    'instagram' => 'M12 7.6a4.4 4.4 0 100 8.8 4.4 4.4 0 000-8.8zm0 7.2a2.8 2.8 0 110-5.6 2.8 2.8 0 010 5.6zM17.4 7.4a1 1 0 11-2 0 1 1 0 012 0zM7.5 3.5h9A4 4 0 0120.5 7.5v9a4 4 0 01-4 4h-9a4 4 0 01-4-4v-9a4 4 0 014-4zm0 1.6a2.4 2.4 0 00-2.4 2.4v9a2.4 2.4 0 002.4 2.4h9a2.4 2.4 0 002.4-2.4v-9a2.4 2.4 0 00-2.4-2.4z',
    'youtube'   => 'M21.3 8.1a2.4 2.4 0 00-1.7-1.7C18.1 6 12 6 12 6s-6.1 0-7.6.4A2.4 2.4 0 002.7 8.1 25 25 0 002.3 12c0 1.3.1 2.6.4 3.9a2.4 2.4 0 001.7 1.7C5.9 18 12 18 12 18s6.1 0 7.6-.4a2.4 2.4 0 001.7-1.7c.3-1.3.4-2.6.4-3.9s-.1-2.6-.4-3.9zM10.2 14.6V9.4l5 2.6z',
    // Path lấy nguyên từ bản thiết kế — bốn icon của thiết kế là
    // Facebook · Instagram · YouTube · TikTok (không phải Messenger).
    'tiktok'    => 'M16.6 5.82A4.28 4.28 0 0115.54 3h-3.09v12.4a2.59 2.59 0 11-2.59-2.59c.27 0 .53.04.77.12V9.77a5.76 5.76 0 00-.77-.05 5.66 5.66 0 105.66 5.66V9.01a7.35 7.35 0 004.3 1.38V7.3a4.28 4.28 0 01-3.22-1.48z',
];
?>
<footer class="site-footer">

    <div class="footer-grid">

        <!-- Cột 1: Thương hiệu -->
        <div class="footer-brand">
            <p class="footer-logo">Vin <em>Eyewear</em></p>

            <p class="footer-blurb">Kính thời trang và tròng kính chính hãng. Đo mắt miễn phí tại hệ thống cửa hàng.</p>

            <ul class="footer-socials" role="list">
                <?php foreach ($company['socials'] as $social): ?>
                    <?php $path = $socialPaths[$social['icon']] ?? null; ?>
                    <?php if ($path === null) continue; ?>
                    <li>
                        <a href="<?= e($social['href']) ?>"
                           target="_blank"
                           rel="noreferrer noopener"
                           aria-label="<?= e($social['label']) ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path d="<?= e($path) ?>"/>
                            </svg>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Cột 2: Sản phẩm -->
        <div class="footer-col">
            <h2 class="footer-heading">Sản phẩm</h2>
            <ul class="footer-links" role="list">
                <?php foreach ($products as $item): ?>
                    <li><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Cột 3: Về Vin Eyewear — xem ghi chú ở $services đầu file -->
        <div class="footer-col">
            <h2 class="footer-heading">Về Vin Eyewear</h2>
            <ul class="footer-links" role="list">
                <?php foreach ($services as $item): ?>
                    <li><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Cột 4: Liên hệ -->
        <div class="footer-col">
            <h2 class="footer-heading">Liên hệ</h2>
            <ul class="footer-contact" role="list">
                <li>
                    Hotline:
                    <a class="footer-phone" href="<?= e($company['hotline_href']) ?>"><?= e($company['hotline']) ?></a>
                </li>
                <li><?= e($company['open_hours'] ?? '8:30 – 21:30, cả tuần') ?></li>
                <li><a class="footer-break" href="mailto:<?= e($company['email']) ?>"><?= e($company['email']) ?></a></li>
                <li><a href="/lien-he">Hệ thống cửa hàng</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="footer-bottom__inner">
            <p class="footer-bottom__legal">
                © <?= date('Y') ?> Vin Eyewear · <?= e($company['name']) ?>
                · MST <?= e($company['tax_code']) ?>
            </p>
            <?php
            /* "Điều khoản" chỉ hiện khi văn bản ĐÃ TỒN TẠI.
               Nó vốn trỏ cứng tới /chinh-sach#dieu-khoan, mà config/policy.php
               không có mục nào mang neo đó — bấm vào chỉ nhảy lên đầu trang
               chính sách. Một liên kết pháp lý dẫn tới không đâu thì tệ hơn là
               không có liên kết.

               Điền config/auth.php ['consent']['terms_url'] là nó tự hiện lại
               ở đây, ở chân trang đăng nhập và trong câu đồng ý khi đăng ký. */
            $termsUrl = (string) config('auth.consent.terms_url', '');
            ?>
            <nav aria-label="Liên kết pháp lý" class="footer-bottom__nav">
                <a href="<?= e((string) config('auth.consent.privacy_url', '/chinh-sach#bao-mat')) ?>">Chính sách bảo mật</a>
                <?php if ($termsUrl !== ''): ?>
                    <a href="<?= e($termsUrl) ?>">Điều khoản</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</footer>
