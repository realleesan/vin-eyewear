<?php
/*
 * LỚP NGÔN NGỮ — nạp TRƯỚC MỌI THỨ trong file này.
 *
 * Phải đứng trên cả hai nhánh "chế độ mảnh" bên dưới: mảnh cũng chứa chữ
 * (hộp thoại mua hàng, dải báo, bảng xổ giỏ), nên t() phải có mặt trước khi
 * bất kỳ nhánh nào in ra byte đầu tiên.
 *
 * require_once chứ không require: BaseController::buyFragment() nạp thẳng
 * _layout/buy-fragment.php mà không đi qua file này, và file đó cũng tự nạp
 * i18n của nó.
 */
require_once CORE_PATH . '/i18n.php';

/* Cú bấm đổi ngôn ngữ: ghi cookie rồi quay lại chính trang này với URL sạch.
   Gọi ở đây vì đây là chỗ CHƯA in ra gì — setcookie() và header() đều cần thế.
   Không có ?lang= thì hàm trả về ngay, không tốn gì. */
i18nXuLyChuyenNgonNgu();

/*
 * ═══════════════════════════════════════════════════════════════════════════
 * CHẾ ĐỘ MẢNH — trả lời cú bấm "Mua ngay" / "Thêm vào giỏ"
 *
 * assets/js/buy-flow.js gửi form bằng fetch rồi lấy ĐÚNG BA MẢNH ra khỏi câu
 * trả lời: hộp thoại .bmodal, dải báo .toast, và cụm giỏ hàng [data-cart].
 * Cả trang còn lại — head, thanh nav, nội dung, chân trang — nó vứt đi.
 *
 * Trước đây máy chủ vẫn dựng cả trang cho mỗi cú bấm, và đó là chỗ mất thời
 * gian THẬT. Đo ở trang chủ (độ trễ mạng 150ms, CPU chậm 4 lần): câu trả lời
 * về sau 336ms, nhưng hộp thoại mãi 1020ms mới hiện — 680ms còn lại là trình
 * duyệt ngồi phân tích 120KB HTML bằng DOMParser để lấy ra ~8KB nó cần.
 *
 * Nên khi thấy header X-Buy-Flow, in đúng ba mảnh đó rồi dừng.
 *
 * TƯƠNG THÍCH NGƯỢC: bản JS cũ (hoặc máy chủ chưa cập nhật) không gửi header
 * này thì rơi xuống nhánh trang đầy đủ như xưa, và buy-flow.js vẫn lấy được
 * ba mảnh từ đó. Hai bên không buộc phải lên phiên bản cùng lúc.
 *
 * VẪN LÀ MÁY CHỦ DỰNG HTML. Đây không phải bước đầu chuyển sang trả JSON rồi
 * để trình duyệt tự vẽ hộp thoại — xem khối chú thích đầu buy-flow.js về lý
 * do không làm thế. Chỉ là thôi gửi kèm phần không ai dùng.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (($_SERVER['HTTP_X_BUY_FLOW'] ?? '') === '1') {
    /* Cùng một URL trả hai thứ khác nhau tuỳ header, nên phải nói cho mọi
       tầng đệm ở giữa biết — thiếu dòng này thì một proxy có thể đem mảnh
       phát cho người mở trang bằng đường dẫn thường, và họ nhận về một trang
       trắng chỉ có cái giỏ hàng. */
    header('Vary: X-Buy-Flow');

    /* Ba mảnh nằm ở file riêng vì BaseController::buyFragment() cũng in đúng
       chúng — xem chú thích đầu _layout/buy-fragment.php. */
    partial('_layout/buy-fragment', [
        'buyModal'  => $buyModal ?? null,
        'toast'     => $toast ?? null,
        'toastTone' => $toastTone ?? 'ok',
    ]);

    return;
}

/*
 * ═══════════════════════════════════════════════════════════════════════════
 * CHẾ ĐỘ MẢNH THỨ HAI — trả lời cú bấm vào bộ lọc trang /san-pham
 *
 * assets/js/catalog.js bấm một tiêu chí lọc thì nạp ngầm URL mới rồi thay ĐÚNG
 * HAI MẢNH của trang: cột lọc (.cfilter) và lưới kết quả (.catmain). Cả trang
 * không tải lại, nên vị trí cuộn còn nguyên và bảng bộ lọc trên điện thoại
 * không bị đóng sập sau mỗi lần tick.
 *
 * VÌ SAO KHÔNG ĐỂ NÓ NẠP CẢ TRANG RỒI TỰ BÓC HAI MẢNH RA: đo trên chính trang
 * này với 18 sản phẩm — cả trang 80.246 byte, mà hai mảnh cần dùng chỉ 38.917
 * byte, đúng 48%. Tức là hơn 41KB head, thanh nav, hai bảng mega và chân trang
 * bị kéo về rồi vứt đi trong MỖI cú bấm tiêu chí. Đây cũng là bài học đã rút
 * ở buy-flow (xem khối chú thích ngay trên).
 *
 * Ở đây in NGUYÊN VIEW, không phải hai mảnh riêng lẻ: view /san-pham vốn chỉ
 * gồm mẩu đầu trang cộng đúng hai khối ấy, nên cắt thêm nữa chỉ đổi lấy vài
 * trăm byte mà phải xẻ một file view 540 dòng thành ba mảnh — dễ vỡ hơn nhiều
 * so với thứ nó tiết kiệm được.
 *
 * TẮT JAVASCRIPT THÌ NHÁNH NÀY KHÔNG BAO GIỜ CHẠY: mọi tiêu chí lọc vẫn là
 * <a href> thật, bấm vào là điều hướng như xưa.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * NAY DÙNG CHUNG VỚI TRANG TÀI KHOẢN (2026-08-30)
 *
 * assets/js/account.js làm y hệt cho cột điều hướng /tai-khoan: bấm một mục là
 * nạp ngầm rồi thay hai khối .acct-nav và .acct-main. Nhu cầu giống nhau tới
 * từng chi tiết — in nguyên view, không in khung — nên hai bên dùng chung đúng
 * một nhánh này thay vì chép ra một khối thứ hai gần y hệt.
 *
 * HAI TÊN HEADER chứ không một: mỗi bên tự khai tên của mình, nên đọc log máy
 * chủ là biết mảnh nào của trang nào, và tắt một bên không đụng bên kia.
 * ═══════════════════════════════════════════════════════════════════════════
 */
$manhCua = null;

/* X-Search (09/09/2026): lớp phủ tìm kiếm ở đầu trang nạp ngầm /tim-kiem?q=
   rồi lấy khối .srch ra hiện TẠI CHỖ — assets/js/search-suggest.js. Cùng nhánh,
   cùng hợp đồng với hai mảnh kia: in nguyên view, không in khung. */
foreach (['X-Catalog' => 'HTTP_X_CATALOG', 'X-Account' => 'HTTP_X_ACCOUNT', 'X-Search' => 'HTTP_X_SEARCH'] as $ten => $bien) {
    if (($_SERVER[$bien] ?? '') === '1') {
        $manhCua = $ten;
        break;
    }
}

if ($manhCua !== null) {
    /* Cùng một URL trả hai thứ khác nhau tuỳ header — phải nói cho mọi tầng
       đệm ở giữa biết, không thì một proxy có thể đem mảnh phát cho người mở
       trang bằng đường dẫn thường và họ nhận về trang trắng không có nav. */
    header('Vary: ' . $manhCua);

    require VIEWS_PATH . '/' . $viewName . '.php';

    return;
}
?>
<!DOCTYPE html>
<?php /* Thuộc tính này khai ngôn ngữ của HTML MÁY CHỦ VỪA DỰNG RA, không phải
         ngôn ngữ khách mong muốn — trình đọc màn hình chọn giọng theo nó.

         Nay nó đi theo currentLang() vì máy chủ THẬT SỰ dựng ra hai bản khác
         nhau: chọn English là mọi nhãn giao diện in ra bằng tiếng Anh.

         MỘT NỬA SỰ THẬT PHẢI BIẾT: tên sản phẩm, tên danh mục và nội dung
         chính sách vẫn là tiếng Việt trong cả hai bản, vì CSDL chỉ có một
         ngôn ngữ. Trang tiếng Anh vì thế có những đoạn tiếng Việt, và trình
         đọc màn hình sẽ đọc chúng bằng giọng Anh. Cách chữa đúng là gắn
         lang="vi" lên chính những khối đó — việc của lúc dữ liệu có hai
         ngôn ngữ, không phải của lúc này. */ ?>
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
     * sinh ra vô số trang mỏng trùng nội dung với /san-pham.
     *
     * `follow` chứ không phải `nofollow`: đừng lập chỉ mục TRANG NÀY, nhưng cứ
     * đi theo các liên kết trong đó tới sản phẩm thật.
     */
    if (!empty($noindex)) {
        echo '    <meta name="robots" content="noindex, follow">' . "\n";
    }
    ?>

    <!-- ┌─ KHÔNG TẢI FONT NGOÀI ────────────────────────────────────────────
         │ Thiết kế mới (mẫu "Eyewear Collection") chạy trên stack font hệ
         │ thống: "Helvetica Neue", Helvetica, Arial. Xem --font trong
         │ assets/css/oa.css — đổi ở đó, không đổi ở đây.
         │
         │ ĐÃ GỠ ba family cũ cùng hai thẻ <link> Google Fonts: Be Vietnam Pro
         │ (thân bài), EB Garamond (wordmark), JetBrains Mono (mã đơn hàng).
         │ Không còn nơi nào gọi chúng — giao diện cũ đi cùng chúng.
         │
         │ Bỏ luôn hai thẻ <link rel="preconnect">: không còn origin nào ngoài
         │ site để mở sẵn kết nối, và đó là hai lượt DNS + TLS đứng chặn đường
         │ vẽ trang mỗi lượt truy cập.
         └──────────────────────────────────────────────────────────────────── -->

    <!-- ══════════════════════════════════════════════════════════════
         CSS — nạp cho MỌI trang.

         MỘT FILE NỀN DUY NHẤT: oa.css. Nó chứa token, reset, thang chữ và
         toàn bộ nguyên thể dùng chung (nút, ô nhập, chip, thẻ sản phẩm,
         đầu trang, chân trang, lớp phủ) — tức là phần việc mà trước đây
         chia cho layout.css + gm.css + ui.css + grid.css + năm file
         components/. Năm file ấy KHÔNG còn được nạp ở đâu.

         Bảng $pageStyles bên dưới vì thế chỉ còn CSS thật sự riêng của
         từng trang, và phần lớn trang không cần dòng nào.
         ══════════════════════════════════════════════════════════════ -->
    <link rel="stylesheet" href="<?= asset('assets/css/oa.css') ?>">

    <?php
    /* ┌─ BỐN COMPONENT NẠP CHO MỌI TRANG, KHÔNG THEO BẢNG $pageStyles ─────
       │ Cả bốn đều là thứ có thể bật ra TRÊN BẤT KỲ TRANG NÀO, nên xếp
       │ chúng vào bảng theo tên view là sai ngay từ tiền đề:
       │
       │   buy-modal  hộp "Chọn hình thức mua" — bật từ mọi nút thêm giỏ,
       │              tức là trang chủ, danh mục, tìm kiếm, bộ sưu tập, chi
       │              tiết. Một hộp thoại vẽ ra không có kiểu thì che cả trang.
       │   confirm    hộp xác nhận "Bỏ khỏi giỏ?" và anh em của nó.
       │   floating   cụm nút liên hệ nổi góc màn hình — có ở mọi trang
       │              khung đầy đủ.
       │   search     lớp phủ tìm kiếm ở đầu trang chèn khối .srch của trang
       │              /tim-kiem vào bất kỳ trang nào; thiếu file này thì kết
       │              quả hiện ra không có kiểu.
       └────────────────────────────────────────────────────────────────────*/
    ?>
    <link rel="stylesheet" href="<?= asset('assets/css/components/buy-modal.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/components/confirm.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/components/floating.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/search.css') ?>">

    <?php
    /* ┌─ CSS RIÊNG CỦA TỪNG TRANG ────────────────────────────────────────
       │ KHOÁ = ĐÚNG TÊN VIEW. Thêm file CSS mà quên dòng ở đây thì trang ra
       │ không có kiểu nào và không có gì báo lỗi — đây là chỗ dễ quên nhất
       │ trong cả khung.
       │
       │ Trang nào KHÔNG có dòng ở đây là trang dựng hoàn toàn bằng nguyên
       │ thể của oa.css. Đó là trạng thái mong muốn, không phải thiếu sót.
       └──────────────────────────────────────────────────────────────────*/
    $pageStyles = [
        'home/index'         => ['home.css'],

        'product/index'      => ['catalog.css'],
        'product/detail'     => ['product-detail.css'],
        'search/index'       => ['catalog.css'],

        'collection/index'   => ['collection.css'],
        'collection/detail'  => ['catalog.css', 'collection.css'],

        'cart/index'         => ['cart.css'],
        'order/checkout'     => ['cart.css', 'checkout.css'],
        'order/transfer'     => ['checkout.css'],
        'order/success'      => ['checkout.css'],
        'order/paid'         => ['checkout.css'],

        'auth/index'         => ['auth.css'],
        'auth/forgot'        => ['auth.css'],
        'auth/reset'         => ['auth.css'],
        'auth/google-signup' => ['auth.css'],
        'auth/profile'       => ['account.css'],

        'about/index'        => ['about.css'],
        'contact/index'      => ['contact.css'],
        'policy/index'       => ['policy.css'],
        'booking/index'      => ['booking.css'],
        'ar/tryon'           => ['ar.tryon.css'],

        /* Khu quản trị giữ nguyên bộ CSS riêng của nó — thiết kế mới chỉ
           phủ khu bán hàng. Xem khối chú thích đầu admin/_layout/master.php. */
        'admin/login'        => ['admin-login.css'],
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

/*
 * Chân trang của khung rút gọn cũng có hai bản, cùng lối chọn với đầu trang:
 *   auth-footer          bản quyền + hai liên kết pháp lý   (Login.dc.html)
 *   admin-login-footer   bản quyền + hòm thư cấp quyền      (Admin Login.dc.html)
 *
 * Thêm cái móc này khi dựng cổng quản trị: trang đó nền tối và chân trang của
 * nó nói chuyện với NHÂN VIÊN, không phải với khách — hai liên kết "Chính sách
 * bảo mật / Điều khoản" ở bản kia là thứ khách cần đọc trước khi tạo tài
 * khoản, còn nhân viên nội bộ thì không.
 *
 * Trước đó chân trang bị gõ cứng trong khi đầu trang đã có móc — một sự lệch
 * không có lý do, chỉ là chưa ai cần tới nửa còn lại.
 */
$bareFoot = $bareFooter ?? '_layout/auth-footer';
?>
<body class="<?= e($bodyClass) ?><?= $bare ? ' is-bare' : '' ?>">

    <?php partial($bare ? $bareHead : '_layout/header'); ?>

    <!-- id là đích của .skip-link trong header.php — đổi tên là link đó gãy -->
    <main class="main-content" id="noi-dung-chinh" tabindex="-1">
        <?php
        /* TRANG VẼ TRONG PHẠM VI RIÊNG — cùng lý do và cùng cách làm với khung
           quản trị (xem khối chú thích dài ở admin/_layout/master.php).

           Ở khung này biến rò ra là $css của vòng lặp nạp CSS phía trên, và
           $ten/$bien của vòng lặp header. Chưa trang nào trùng tên với chúng,
           nên đây là vá TRƯỚC KHI đau chứ không phải sau: khung quản trị đã
           dính đúng lỗi ấy với $group và nó chỉ lộ ra trên trang thật.

           Đã đối chiếu: không trang khách nào đọc biến của khung ($css ·
           $viewName · $pageStyles · $pageScripts · $bareFoot) — 0 file. */
        (static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        })(VIEWS_PATH . '/' . $viewName . '.php', $__pageData);
        ?>
    </main>

    <?php partial($bare ? $bareFoot : '_layout/footer'); ?>

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

    <?php /* Dải báo sau khi thêm vào giỏ — ở _layout/toast.php, vì chế độ
             mảnh ở đầu file cũng in nó. */ ?>
    <?php if (!empty($toast)): ?>
        <?php partial('_layout/toast', ['toast' => $toast, 'toastTone' => $toastTone ?? 'ok']); ?>
    <?php endif; ?>

    <?php if (!$bare): ?>
        <!-- S22 — cụm nút nổi hỗ trợ. Đặt sau footer để thứ tự đọc của trình đọc
             màn hình khớp với thứ tự trên màn hình: đây là tiện ích phụ, không
             phải nội dung chính. -->
        <?php partial('_layout/floating-actions'); ?>

        <?php
        /* ┌─ BOOTSTRAP: CHỈ JAVASCRIPT, KHÔNG BAO GIỜ CSS (09/09/2026) ─────────
           │ bootstrap.bundle.min.js — 79 KB, tự host trong assets/vendor (cả
           │ site tự phục vụ asset, chỉ font là ngoại lệ; thêm một origin vào
           │ đường vẽ trang là thêm một lượt DNS + TLS chặn render).
           │
           │ KHÔNG NẠP bootstrap.min.css. File 227 KB ấy vẫn nằm trong repo
           │ nhưng không nơi nào gọi: nó khai lại `:root`, reset, và mọi lớp
           │ .btn/.alert/.form-control trùng tên với gm.css — nạp vào là cả hệ
           │ token đổ. Khối chú thích ở đầu <head> ghi rõ lý do đã thay nó bằng
           │ assets/css/grid.css 120 dòng.
           │
           │ DÙNG ĐÚNG MỘT THỨ: Offcanvas cho ngăn kéo giỏ hàng — xem khối 2a
           │ trong assets/js/header.js về ba lỗi mà bản tự viết mắc phải (khoá
           │ cuộn không bù thanh cuộn, bẫy tiêu điểm chỉ bắt Tab, thiếu
           │ aria-modal). Những thứ còn lại (mega menu, lớp phủ tìm kiếm, hộp
           │ mua, ngăn kéo bộ lọc, mục gập) VẪN chạy bằng CSS/`<details>` thuần
           │ và KHÔNG được chuyển sang Bootstrap: chúng đã đúng, mà đổi là sinh
           │ ra hai hệ chuyển động cạnh tranh trong cùng một đầu trang.
           │
           │ ĐỨNG TRƯỚC header.js: file kia đọc window.bootstrap.Offcanvas ngay
           │ lúc chạy. Cả hai đều `defer` nên giữ đúng thứ tự viết ở đây.
           │
           │ Không có file này (mạng hỏng, chặn script) thì thẻ mở giỏ vẫn là
           │ <a href="/gio-hang"> thật — bấm là sang trang giỏ, không gãy gì.
           └──────────────────────────────────────────────────────────────────── */
        ?>
        <script src="<?= asset('assets/vendor/bootstrap.bundle.min.js') ?>" defer></script>
        <script src="<?= asset('assets/js/header.js') ?>" defer></script>
        <?php /* Gợi ý từ khoá cho ô tìm kiếm ở đầu trang — X29.

                 Nạp cạnh header.js vì ô tìm kiếm nằm trong chính đầu trang
                 ấy, tức là có mặt ở MỌI trang khung đầy đủ. Không xếp vào
                 $pageScripts: ô này không thuộc trang nào cả.

                 File tự thoát khi không tìm thấy ô hoặc <datalist>, nên trang
                 khung rút gọn (checkout) không cần loại trừ gì. */ ?>
        <script src="<?= asset('assets/js/search-suggest.js') ?>" defer></script>
        <script src="<?= asset('assets/js/floating.js') ?>" defer></script>
        <?php /* Mua hàng không tải lại trang. Nạp cho MỌI trang khung đầy đủ
                 chứ không theo $pageScripts: nút "Thêm vào giỏ" có mặt ở trang
                 chủ, danh mục, tìm kiếm và chi tiết sản phẩm — cùng chỗ mà
                 buy-modal.css đã được nạp sẵn ở đầu trang. */ ?>
        <script src="<?= asset('assets/js/buy-flow.js') ?>" defer></script>
        <?php /* Bảng số đo khúc xạ trong hộp thoại: khoá ô trục khi chưa có độ
                 trụ, và ô tóm tắt đọc số ra thành chữ. Nạp cùng chỗ với
                 buy-flow.js vì hộp thoại xuất hiện ở đúng những trang đó. */ ?>
        <script src="<?= asset('assets/js/buy-rx.js') ?>" defer></script>
    <?php endif; ?>

    <?php
    /*
     * JS riêng của từng trang — cùng cách làm với CSS ở trên, kể cả chỗ nhận
     * MẢNG: một trang có thể cần nhiều file, ví dụ trang tài khoản vừa có
     * account.js của riêng nó vừa dùng chung address-picker.js với trang
     * thanh toán. Thứ tự trong mảng là thứ tự thẻ <script>.
     *
     * 'home/index' -> home.js: băng ảnh hero, đồng hồ đếm ngược ưu đãi, hộp
     * thoại "kiểm tra 5 phút" và băng trượt khối đánh giá. Tất cả đều chỉ là
     * tăng cường — xem khối chú thích đầu assets/js/home.js.
     */
    $pageScripts = [
        'home/index'    => 'home.js',
        /* Trang đặt lịch dùng home.js CHỈ vì khối "Kiểm tra 5 phút" chuyển về
           đây (xem cuối app/views/booking/index.php). Bốn khối còn lại của file
           ấy — băng hero, hai băng sản phẩm, băng đánh giá — đều vào bằng một
           câu document.querySelector và thoát ngay khi không thấy phần tử, nên
           chúng im lặng ở trang này. */
        'booking/index' => 'home.js',
        'policy/index'  => 'policy.js',
        'ar/tryon'      => 'ar-tryon.js',
        // Chỉ là tăng cường: đổi ô sắp xếp là gửi form luôn, và lọc danh sách
        // thương hiệu ngay khi gõ. Không có file này trang vẫn lọc được.
        'product/index' => 'catalog.js',
        // Kẹp ô số lượng về đúng khoảng ngay khi gõ, và nói bằng tiếng Việt.
        // Thiếu file này thì min/max của ô vẫn còn nguyên tác dụng, chỉ là gõ
        // quá tồn sẽ gặp bong bóng mặc định của trình duyệt.
        'product/detail' => 'product-detail.js',
        // Cũng chỉ là tăng cường: đổi cơ sở trên bản đồ không cần tải lại trang.
        'contact/index' => 'contact.js',
        // Cũng chỉ là tăng cường: chọn ảnh đại diện xong là gửi luôn, khỏi
        // bấm thêm nút thứ hai.
        // account.js lo ảnh đại diện và thẻ đơn hàng; address-picker.js lo cụm
        // chọn tỉnh/phường trong sổ địa chỉ — cùng file mà trang thanh toán dùng.
        'auth/profile'  => ['account.js', 'address-picker.js', 'copy-btn.js', 'confirm-dialog.js'],
        // Nút con mắt hiện/ẩn mật khẩu. Không có file này thì nút tự ẩn đi và
        // ô mật khẩu vẫn dùng bình thường.
        'auth/index'    => 'auth.js',
        'auth/reset'    => 'auth.js',
        // Màn quên mật khẩu dùng CHUNG cụm sáu ô mã (.aotp__box) và cụm đếm
        // ngược gửi lại (.aresend) với luồng đăng ký — xem auth/forgot.php.
        // Trang này bị bỏ sót khỏi bảng: sáu ô mã vẫn gõ được từng ô nhưng
        // không tự nhảy ô, và nút "Gửi lại mã" nằm im ở trạng thái disabled
        // cho tới khi tải lại trang.
        'auth/forgot'   => 'auth.js',
        // Cùng nút hiện/ẩn mật khẩu ấy, dùng lại nguyên si — cổng quản trị
        // đặt đúng bộ lớp .authpw mà file này tìm. Thiếu nó thì nút tự ẩn và
        // ô mật khẩu vẫn gõ bình thường.
        'admin/login'   => 'auth.js',
        // Đếm ngược rồi tự sang mục "Đơn hàng của tôi". Không có file này thì
        // không có đếm ngược nào và nút "Xem đơn hàng của tôi" vẫn ở đó.
        'order/success' => 'order-success.js',
        // address-picker: hai ô tỉnh/phường vẫn gõ tay được khi thiếu nó.
        // checkout-deposit: đổi khối "đặt cọc" theo phương thức đang chọn.
        // Thiếu nó thì khối hiện ra là khối đúng lúc máy chủ vẽ trang, và máy
        // chủ mới là nơi chốt số tiền — xem checkout-deposit.js.
        'order/checkout' => ['address-picker.js', 'checkout-deposit.js'],
        // Nút "Sao chép" số tài khoản và nội dung chuyển khoản. Thiếu file này
        // thì hai chuỗi đó vẫn in ra dạng chữ để khách bôi đen chép tay.
        //
        // pay-watch.js hỏi máy chủ xem tiền về chưa rồi tự chuyển sang biên
        // nhận — thứ thay cho nút "Tôi đã chuyển khoản" đã bỏ. Thiếu nó thì
        // khối chờ vẫn đọc được và lối ra hiện sẵn ngay bên dưới.
        'order/transfer' => ['copy-btn.js', 'pay-watch.js'],
        // Biên nhận thanh toán. copy-btn.js: nút chép mã đơn — thiếu nó thì mã
        // vẫn in ra dạng chữ để bôi đen chép tay, y như ở màn QR.
        //
        // paid-receipt.js NGƯỢC với nếp chung: thiếu nó thì hai nút "Tải biên
        // nhận" / "Chia sẻ" KHÔNG hiện ra. Cả hai việc ấy chỉ làm được bằng JS
        // (window.print, navigator.share) nên không có đường lui nào — vẽ nút
        // bấm vào không có gì xảy ra còn tệ hơn không vẽ. Lý do đầy đủ ở đầu
        // assets/js/paid-receipt.js.
        'order/paid'     => ['copy-btn.js', 'paid-receipt.js'],
        // Hộp thoại hỏi lại trước khi xoá dòng giỏ hàng. Thiếu file này thì
        // mỗi nút xoá vẫn còn onclick="return confirm(...)" của trình duyệt —
        // xấu hơn, nhưng khách vẫn được hỏi. Xem confirm-dialog.js.
        'cart/index'     => 'confirm-dialog.js',
    ];

    foreach ((array) ($pageScripts[$viewName ?? ''] ?? []) as $js) {
        printf('    <script src="%s" defer></script>' . "\n", e(asset('assets/js/' . $js)));
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
