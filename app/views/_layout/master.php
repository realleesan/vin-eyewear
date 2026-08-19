<!DOCTYPE html>
<?php /* lang động: trình đọc màn hình chọn giọng đọc theo thuộc tính này,
         và trình duyệt dùng nó để gợi ý dịch trang. Khoá cứng "vi" thì khách
         chọn English vẫn bị đọc bằng giọng tiếng Việt. */ ?>
<html lang="<?= e(currentLang()) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Đánh dấu "JS đang chạy" TRƯỚC khi trang vẽ ra.
         Hiệu ứng .reveal chỉ được phép ẩn nội dung khi có dòng này, vì nội
         dung đã ẩn thì phải có JS mới hiện lại được. Không có nó, tắt JS là
         mất hẳn 8 khối nội dung — xem khối .reveal trong components/ui.css.
         Để ngay đầu <head>, không phải cuối <body>: chạy sau khi vẽ thì
         người dùng thấy nội dung nhấp nháy hiện rồi ẩn. -->
    <script>document.documentElement.classList.add('js');</script>

    <title><?= e($pageTitle ?? 'Vin Eyewear — Kính mắt cao cấp') ?></title>

    <!-- Mỗi controller có thể truyền $metaDesc riêng; thiếu thì dùng mô tả chung -->
    <meta name="description" content="<?= e($metaDesc ?? 'Vin Eyewear — cửa hàng kính mắt chính hãng tại Hà Nội, đo khúc xạ miễn phí và thử kính AR trực tuyến.') ?>">

    <?php
    /*
     * Trang nào truyền 'noindex' => true thì xin máy tìm kiếm bỏ qua. Hiện chỉ
     * trang kết quả tìm kiếm dùng: mỗi từ khoá là một URL, lập chỉ mục hết thì
     * sinh ra vô số trang mỏng trùng nội dung với /san-pham và /su-kien.
     *
     * `follow` chứ không phải `nofollow`: đừng lập chỉ mục TRANG NÀY, nhưng cứ
     * đi theo các liên kết trong đó tới sản phẩm và bài viết thật.
     */
    if (!empty($noindex)) {
        echo '    <meta name="robots" content="noindex, follow">' . "\n";
    }
    ?>

    <!-- Google Fonts: Lora (tiêu đề) / Be Vietnam Pro (chữ chạy) / JetBrains Mono.
         Trùng với --font-serif / --font-sans / --font-mono trong layout.css —
         đổi ở đây thì phải đổi cả bên đó, và ngược lại.

         LORA chứ không phải Playfair Display: "Vin Eyewear Home.dc.html" gọi
         đúng dòng này (`family=Lora:ital,wght@0,400..700;1,400..600`). Hai font
         không thay được cho nhau — Playfair có nét thanh/đậm chênh nhau rất
         mạnh và chân chữ vuông, Lora mềm và đều nét hơn, nên cùng một cỡ chữ
         mà nhìn nhẹ hơn hẳn. Giữ Playfair thì tiêu đề trang chủ không bao giờ
         khớp bản thiết kế dù mọi con số px đều đúng. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400..700;1,400..600&family=Be+Vietnam+Pro:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <!-- ══════════════════════════════════════════════════════════════
         CSS DÙNG CHUNG — nạp cho MỌI trang.

         THỨ TỰ QUAN TRỌNG:
           1. layout.css  khai :root chứa toàn bộ token
           2. ui.css      nguyên thể dùng chung (nút, ô nhập, thông báo…)
           3. còn lại     component, chỉ tiêu thụ hai file trên

         Trước đây các file trang phải nạp chéo nhau — trang thanh toán nạp
         contact.css chỉ để mượn .field, nạp cart.css chỉ để mượn .alert.
         Nay mọi mẩu dùng chung nằm trong ui.css, nên bảng bên dưới chỉ còn
         đúng CSS riêng của từng trang.
         ══════════════════════════════════════════════════════════════ -->
    <link rel="stylesheet" href="<?= asset('assets/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/components/ui.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/components/header.css') ?>">
    <!-- Phải đứng SAU header.css: .mega__trigger chỉnh lại .header-nav__list > li > a -->
    <link rel="stylesheet" href="<?= asset('assets/css/components/mega-menu.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/components/footer.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/components/page-head.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/components/product.css') ?>">
    <?php /* Hộp thoại "Chọn hình thức mua". Nạp cho MỌI trang chứ không theo
             bảng $pageStyles bên dưới: nó hiện đè lên bất kỳ trang nào có nút
             thêm giỏ — trang chủ, danh mục, tìm kiếm, chi tiết — và một hộp
             thoại vẽ ra không có kiểu thì che mất cả trang. */ ?>
    <link rel="stylesheet" href="<?= asset('assets/css/components/buy-modal.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/components/floating.css') ?>">

    <?php
    /*
     * CSS riêng của từng trang.
     *
     * Dùng bảng thay cho một chuỗi if: thêm trang mới chỉ cần thêm một dòng,
     * và không thể quên đóng thẻ điều kiện.
     */
    $pageStyles = [
        'home/index'     => ['components/home-sections.css'],
        // Trang danh sách nay dựng theo "Vin Eyewear Category.dc.html" và có
        // bộ lớp riêng; nó không còn mượn .section-head/.eyebrow của trang chủ
        // nên cũng không nạp home-sections.css nữa.
        'search/index'   => ['search.css'],
        'product/index'  => ['category.css'],
        // Trang chi tiết nay dựng theo "Vin Eyewear Product.dc.html" và có bộ
        // lớp riêng (.pd*); nó không còn mượn .section-h2 của trang chủ nữa.
        // Thẻ "sản phẩm liên quan" dùng .pcard trong components/product.css
        // vốn đã nạp cho mọi trang.
        'product/detail' => ['product-detail.css'],
        // Trang danh sách nay dựng theo "Vin Eyewear News.dc.html" và có bộ lớp
        // riêng (.nw*); nó không còn mượn .section-h2/.eyebrow của trang chủ.
        // Trang CHI TIẾT thì vẫn theo bản Lovable nên giữ nguyên.
        'event/index'    => ['event.css'],
        // Trang chi tiết nay dựng theo "Vin Eyewear Article.dc.html" và có bộ
        // lớp riêng (.art*); nó không còn mượn .section-h2 của trang chủ.
        'event/detail'   => ['event.css'],
        'about/index'    => ['about.css'],
        // Trang liên hệ nay dựng theo "Vin Eyewear Contact.dc.html" và có bộ
        // lớp riêng; nó không còn mượn .section-h2/.eyebrow của trang chủ.
        'contact/index'  => ['contact.css'],
        'policy/index'   => ['policy.css'],
        'cart/index'     => ['cart.css'],
        // Trang thanh toán dựng theo "Vin Eyewear Checkout.dc.html": khung rút
        // gọn (bare-shell) + khối tóm tắt dùng chung với giỏ hàng (cart.css)
        // + bộ lớp riêng của nó (checkout.css).
        'order/checkout' => ['components/bare-shell.css', 'cart.css', 'checkout.css'],
        // Màn "Thanh toán QR" — màn thứ hai của cùng bản thiết kế, nên dùng
        // chung checkout.css (bộ lớp .coqr*). Không cần cart.css: màn này
        // không có khối tóm tắt .csum*.
        'order/transfer' => ['components/bare-shell.css', 'checkout.css'],
        // Trang xác nhận đơn nay dựng theo "Vin Eyewear Order Complete.dc.html"
        // và có bộ lớp riêng (.ocomp*); nó không còn mượn .section-h2 của trang
        // chủ hay .edetail__cta của trang sự kiện. order.css đã bỏ — trang đặt
        // lịch (bộ lớp .b* trong file đó) từ lâu đã có booking.css riêng.
        'order/success'  => ['order-complete.css'],
        // Trang đặt lịch nay dựng theo "Vin Eyewear Booking.dc.html" và có bộ
        // lớp riêng (.bk*).
        'booking/index'  => ['booking.css'],
        // Ba trang tài khoản-chưa-đăng-nhập dựng theo "Vin Eyewear Login.dc.html".
        // bare-shell.css phải đứng TRƯỚC auth.css: nó giữ khung rút gọn dùng
        // chung với trang thanh toán, auth.css giữ phần riêng (.authcard/.authform*).
        'auth/index'     => ['components/bare-shell.css', 'auth.css'],
        'auth/forgot'    => ['components/bare-shell.css', 'auth.css'],
        'auth/reset'     => ['components/bare-shell.css', 'auth.css'],
        // Trang tài khoản nay dựng theo "Vin Eyewear Account.dc.html" và có bộ
        // lớp riêng (.acct*); nó không còn mượn .acard/.otable của auth.css.
        'auth/profile'   => ['account.css'],
        'ar/tryon'       => ['ar.tryon.css'],
        // errors.css giữ .error-page* mà 404 và 500 dùng; auth.css giữ .errpage*
        // mà 403 dùng. Ba trang lỗi hiện dựng theo HAI bộ lớp khác nhau —
        // gộp về một bộ là việc riêng, chưa làm ở đây. Trước đó bảng này chỉ
        // trỏ auth.css nên 404 và 500 ra trang KHÔNG có kiểu nào cả.
        'errors/403'     => ['auth.css'],
        'errors/404'     => ['errors.css'],
        'errors/500'     => ['errors.css'],
    ];

    foreach ($pageStyles[$viewName ?? ''] ?? [] as $css) {
        printf('    <link rel="stylesheet" href="%s">' . "\n", e(asset('assets/css/' . $css)));
    }
    ?>
</head>

<?php
/*
 * Class trên <body> theo tên view: 'product/index' -> 'page-product-index'.
 *
 * Dùng khi CSS của MỘT trang cần đổi một mẩu dùng chung nằm NGOÀI vùng nội
 * dung của nó — mà footer là ví dụ duy nhất tới giờ: nó mang sẵn
 * margin-top:88px lấy từ bản thiết kế trang chủ, nhưng hai trang dựng theo
 * bản Category/Contact đã có sẵn 96px đệm dưới ở khối cuối, cộng lại thành
 * 184px trống trước chân trang.
 *
 * Đây KHÔNG phải lối đi tắt để viết CSS riêng cho từng trang: cái đó cứ dùng
 * bảng $pageStyles ở trên. Chỉ dùng khi thật sự phải với ra ngoài <main>.
 */
$bodyClass = 'page-' . str_replace(['/', '_'], '-', (string) ($viewName ?? ''));

/*
 * KHUNG RÚT GỌN — controller truyền 'bareLayout' => true.
 *
 * Dùng cho luồng tài khoản khi CHƯA đăng nhập (đăng nhập · đăng ký · quên và
 * đặt lại mật khẩu) VÀ cho trang thanh toán. "Vin Eyewear Login.dc.html" và
 * "Vin Eyewear Checkout.dc.html" đều vẽ những trang này với đầu và chân trang
 * riêng, tối giản: không thanh điều hướng, không mega menu, không giỏ hàng,
 * không cụm nút nổi.
 *
 * Đó là chủ ý chứ không phải thiếu sót của bản thiết kế — mỗi liên kết thêm ở
 * đầu trang đăng nhập là một lối để người dùng bỏ dở việc họ đang làm.
 *
 * Là một CỜ trong file này chứ không phải một file master thứ hai: bảng
 * $pageStyles/$pageScripts ở trên phục vụ cả hai khung, tách file là phải chép
 * đôi hai bảng đó rồi quên đồng bộ.
 */
$bare = !empty($bareLayout);

/*
 * Đầu trang của khung rút gọn có HAI bản, khác nhau đúng nửa bên phải:
 *   auth-header      "Bạn cần hỗ trợ? 1900 6868"          (Login.dc.html)
 *   checkout-header  ổ khoá + "Thanh toán an toàn" + hotline (Checkout.dc.html)
 *
 * Controller chọn bằng 'bareHeader'. Không truyền thì lấy bản của luồng tài
 * khoản, vì đó là ba trong bốn trang đang dùng khung này.
 */
$bareHead = $bareHeader ?? '_layout/auth-header';
?>
<body class="<?= e($bodyClass) ?><?= $bare ? ' is-bare' : '' ?>">

    <?php partial($bare ? $bareHead : '_layout/header'); ?>

    <!-- id là đích của .skip-link trong header.php — đổi tên là link đó gãy -->
    <main class="main-content" id="noi-dung-chinh" tabindex="-1">
        <?php require VIEWS_PATH . '/' . $viewName . '.php'; ?>
    </main>

    <?php partial($bare ? '_layout/auth-footer' : '_layout/footer'); ?>

    <?php
    /*
     * Hộp thoại "Chọn hình thức mua" — chỉ khi ?mua=<id> trỏ tới một chiếc
     * gọng hoặc kính mát đang bán. BaseController::renderView dựng $buyModal.
     *
     * Đặt CUỐI <body>, sau chân trang: nó nổi lên bằng position:fixed nên vị
     * trí trong luồng không ảnh hưởng gì tới hình ảnh, mà để cuối thì trình
     * đọc màn hình và phím Tab đi hết nội dung trang rồi mới tới nó — đúng thứ
     * tự với một lớp phủ.
     */
    if (!empty($buyModal)) {
        partial('_layout/buy-modal', ['buyModal' => $buyModal]);
    }
    ?>

    <?php if (!empty($toast)): ?>
        <?php /* Dải báo sau khi thêm vào giỏ. role="status" để trình đọc màn
                 hình đọc lên mà không cắt ngang việc đang làm — aria-live
                 "polite", đúng với một lời xác nhận.

                 Tự mờ đi sau vài giây bằng CSS animation, không phải setTimeout:
                 xem .toast trong components/ui.css. */ ?>
        <p class="toast toast--<?= e($toastTone ?? 'ok') ?>"
           role="<?= ($toastTone ?? 'ok') === 'err' ? 'alert' : 'status' ?>"><?= e($toast) ?></p>
    <?php endif; ?>

    <?php if (!$bare): ?>
        <!-- S22 — cụm nút nổi hỗ trợ. Đặt sau footer để thứ tự đọc của trình đọc
             màn hình khớp với thứ tự trên màn hình: đây là tiện ích phụ, không
             phải nội dung chính. -->
        <?php partial('_layout/floating-actions'); ?>

        <script src="<?= asset('assets/js/header.js') ?>" defer></script>
        <script src="<?= asset('assets/js/floating.js') ?>" defer></script>
    <?php endif; ?>

    <?php
    /*
     * JS riêng của từng trang — cùng cách làm với CSS ở trên.
     *
     * 'home/index' -> home.js: băng ảnh hero, đồng hồ đếm ngược ưu đãi, hộp
     * thoại "kiểm tra 5 phút" và băng trượt khối đánh giá. Tất cả đều chỉ là
     * tăng cường — xem khối chú thích đầu assets/js/home.js.
     */
    $pageScripts = [
        'home/index'    => 'home.js',
        'policy/index'  => 'policy.js',
        'ar/tryon'      => 'ar-tryon.js',
        // Chỉ là tăng cường: đổi ô sắp xếp là gửi form luôn, và lọc danh sách
        // thương hiệu ngay khi gõ. Không có file này trang vẫn lọc được.
        'product/index' => 'catalog.js',
        // Cũng chỉ là tăng cường: đổi cơ sở trên bản đồ không cần tải lại trang.
        'contact/index' => 'contact.js',
        // Cũng chỉ là tăng cường: chọn ảnh đại diện xong là gửi luôn, khỏi
        // bấm thêm nút thứ hai.
        'auth/profile'  => 'account.js',
        // Nút con mắt hiện/ẩn mật khẩu. Không có file này thì nút tự ẩn đi và
        // ô mật khẩu vẫn dùng bình thường.
        'auth/index'    => 'auth.js',
        'auth/reset'    => 'auth.js',
        // Cũng chỉ là tăng cường: nút "sao chép liên kết" ở cột chia sẻ.
        // Không có file này thì nút tự ẩn, nút Facebook bên cạnh vẫn chạy.
        'event/detail'  => 'share.js',
        // Đếm ngược rồi tự sang mục "Đơn hàng của tôi". Không có file này thì
        // không có đếm ngược nào và nút "Xem đơn hàng của tôi" vẫn ở đó.
        'order/success' => 'order-success.js',
    ];

    if (isset($pageScripts[$viewName ?? ''])) {
        printf('    <script src="%s" defer></script>' . "\n", e(asset('assets/js/' . $pageScripts[$viewName])));
    }
    ?>

    <!-- Hiện dần phần tử .reveal khi cuộn tới. Dùng chung toàn site nên để
         nội tuyến: một request mạng cho 15 dòng là không đáng. -->
    <script>
        (function () {
            'use strict';

            var targets = document.querySelectorAll('.reveal');
            if (!targets.length) return;

            // Người dùng đã tắt hiệu ứng chuyển động -> hiện thẳng, không quan sát
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                targets.forEach(function (el) { el.classList.add('visible'); });
                return;
            }

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('visible');
                    // Bỏ theo dõi sau lần đầu: hiệu ứng chỉ chạy một lượt,
                    // giữ lại chỉ tốn công tính mỗi lần cuộn.
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.1 });

            targets.forEach(function (el) { observer.observe(el); });
        })();
    </script>

</body>

</html>
