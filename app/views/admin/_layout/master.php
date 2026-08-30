<?php

/**
 * admin/_layout/master.php — khung trang khu quản trị.
 *
 * Tách hẳn khỏi master.php của site bán hàng: khu quản trị không cần
 * header/mega-menu/giỏ hàng/footer, và có thanh bên riêng.
 *
 * noindex trong <meta robots>: trang quản trị không được lập chỉ mục. Nó đã
 * bị chặn bằng đăng nhập, nhưng thêm dòng này để địa chỉ không lọt vào kết
 * quả tìm kiếm qua các đường khác (thanh công cụ trình duyệt, referer…).
 */

$segment = currentPath();

/*
 * ĐIỀU HƯỚNG CHIA NHÓM — theo "Vin Eyewear Admin.dc.html".
 *
 * Trước đây là một danh sách phẳng 14 mục. Ở độ dài đó, tìm một mục là đọc
 * lần lượt từ trên xuống; chia thành bốn nhóm thì mắt nhảy tới nhóm trước,
 * chỉ còn đọc ba tới bốn dòng. Nhóm lấy đúng của bản thiết kế: việc hằng
 * ngày (Vận hành) — hàng hoá (Sản phẩm) — nội dung (Marketing) — cấu hình
 * (Hệ thống).
 *
 * KHÔNG CÒN ICON — bản thiết kế mới ("Tổng quan.dc.html") bỏ hẳn cột icon.
 *
 * Bản trước đeo cho mỗi mục một hình 17px ở đầu dòng. Ở một danh sách ĐÃ chia
 * nhóm thì chúng không còn việc gì để làm: người dùng tìm mục bằng cách nhảy
 * tới nhóm rồi đọc ba tới bốn chữ, chứ không nhận ra "Giá tròng" qua hình cặp
 * kính. Mà mười sáu hình nhỏ xếp dọc một cột lại là mười sáu vệt sáng cạnh
 * chữ, làm cái duy nhất đáng nhìn trong thanh bên — huy hiệu số ở mép phải —
 * khó bắt hơn hẳn.
 *
 * Chỗ 27px tiết kiệm được chuyển hết cho nhãn chữ, nên tên dài như "Tài khoản
 * nội bộ" không còn phải cắt.
 *
 * 'exact' => true chỉ dành cho Tổng quan: mọi mục khác khớp theo tiền tố để
 * trang con (vd /quan-tri/san-pham/sua) vẫn sáng đúng mục cha.
 *
 * 'badge' CHỈ ĐEO CHO HÀNG CHỜ — đơn mới, lịch hẹn chờ xác nhận, liên hệ chưa
 * xử lý, yêu cầu đặt lại mật khẩu, đánh giá chờ duyệt. Đó là những mục có
 * NGƯỜI ĐANG ĐỢI ở đầu bên kia. Sản phẩm hay danh mục thì có bao nhiêu dòng
 * cũng không ai phải làm gì; đeo số vào chỉ làm mắt quen với việc bỏ qua các
 * con số, và rồi bỏ qua luôn cả bốn cái đáng đọc. Số 0 tự ẩn — xem chỗ in
 * huy hiệu bên dưới.
 */
$navGroups = [
    ['label' => 'Vận hành', 'items' => [
        ['url' => '/quan-tri',          'label' => 'Tổng quan', 'exact' => true],
        ['url' => '/quan-tri/don-hang', 'label' => 'Đơn hàng',
         'badge' => $pendingOrders],
        ['url' => '/quan-tri/lich-hen', 'label' => 'Lịch hẹn',
         'badge' => $pendingAppointments],
        ['url' => '/quan-tri/lien-he',  'label' => 'Liên hệ', 'badge' => $pendingContacts],
        /* KHÔNG ĐEO HUY HIỆU — đúng luật đã ghi ở khối trên: huy hiệu chỉ dành
           cho hàng chờ có NGƯỜI ĐANG ĐỢI ở đầu bên kia. Có bao nhiêu khách
           hàng cũng không ai phải làm gì cả.

           Nằm ở "Vận hành" chứ không ở "Hệ thống" cạnh "Tài khoản nội bộ":
           tra hồ sơ khách là việc làm mỗi ngày ở quầy, còn cấp tài khoản cho
           nhân viên thì vài tháng một lần. Nhóm theo TẦN SUẤT DÙNG, không theo
           việc cả hai đều là "danh sách người". */
        ['url' => '/quan-tri/khach-hang', 'label' => 'Khách hàng'],
    ]],
    ['label' => 'Sản phẩm', 'items' => [
        ['url' => '/quan-tri/san-pham',  'label' => 'Sản phẩm'],
        ['url' => '/quan-tri/ton-kho',   'label' => 'Tồn kho'],
        /* Ngay dưới Tồn kho, không phải trong nhóm Marketing: người mở nó là
           người vừa nhập hàng xong, và câu hỏi tiếp theo của họ là "ai đang
           chờ món này". */
        ['url' => '/quan-tri/cho-hang',  'label' => 'Chờ hàng'],
        ['url' => '/quan-tri/danh-muc',  'label' => 'Danh mục'],
        ['url' => '/quan-tri/gia-trong', 'label' => 'Giá tròng'],
        /* Ngay dưới "Giá tròng": hai màn cùng nói về tròng, và người sửa bảng
           giá thường là người vừa thêm một lựa chọn mới ở đây. */
        ['url' => '/quan-tri/thuoc-tinh-trong', 'label' => 'Thuộc tính tròng'],
    ]],
    ['label' => 'Marketing', 'items' => [
        ['url' => '/quan-tri/bo-suu-tap',  'label' => 'Bộ sưu tập'],
        ['url' => '/quan-tri/ma-giam-gia', 'label' => 'Mã giảm giá'],
        ['url' => '/quan-tri/danh-gia',    'label' => 'Đánh giá', 'badge' => $pendingReviews],
    ]],
    ['label' => 'Hệ thống', 'items' => [
        ['url' => '/quan-tri/co-so', 'label' => 'Cơ sở'],
        // Chỉ hiện huy hiệu khi có yêu cầu chờ: trên hosting gửi được email thì
        // mục này gần như luôn rỗng, không nên gây chú ý vô cớ.
        ['url' => '/quan-tri/quen-mat-khau', 'label' => 'Quên mật khẩu',
         'badge' => $pendingResets],
    ]],
];

/*
 * KHÔNG CÓ MỤC "BIẾN THỂ" Ở ĐÂY LÀ CỐ Ý — bỏ khỏi thanh bên theo yêu cầu.
 *
 * Màn quản lý biến thể VẪN CÒN NGUYÊN ở /quan-tri/bien-the (route, controller,
 * view, dữ liệu đều không đụng tới), chỉ là không còn đường dẫn nào trỏ tới.
 * Không xoá hẳn vì biến thể là thứ trang bán hàng đang dùng thật: hộp mua
 * hàng chọn phương án theo biến thể, giỏ hàng và đơn hàng giữ variant_id, và
 * tồn kho khi đặt hàng trừ vào biến thể (VariantModel::reserve). Gỡ màn quản
 * lý đi là những dữ liệu đó vẫn chạy mà không ai sửa được nữa.
 *
 * Muốn dùng lại: thêm một dòng vào nhóm "Sản phẩm" ở trên, không phải dựng
 * lại gì cả.
 */

/*
 * MỤC "TÀI KHOẢN NỘI BỘ" CHỈ HIỆN VỚI VAI TRÒ 'admin'.
 *
 * Nhân viên và quản lý vẫn MỞ ĐƯỢC trang đó bằng cách gõ địa chỉ — nó cố ý
 * cho xem danh sách, vì biết ai đang có quyền vào khu quản trị là việc chính
 * đáng. Chỉ cái nút đặt lại mật khẩu là bị chặn, và chặn ở controller chứ
 * không ở đây.
 *
 * Giấu khỏi thanh bên là chuyện GỌN MẮT, không phải chuyện bảo mật: bày một
 * mục mà bấm vào chỉ để đọc dòng "bạn không có quyền" thì mỗi ngày mỗi nhân
 * viên đều thấy một cánh cửa khoá.
 */
if (in_array('admin', $adminRoles, true)) {
    // Chèn TRƯỚC "Quên mật khẩu" để hai mục về người dùng nằm cạnh nhau,
    // đúng thứ tự của bản thiết kế.
    array_splice($navGroups[3]['items'], 1, 0, [
        ['url' => '/quan-tri/nhan-vien', 'label' => 'Tài khoản nội bộ'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <?php /*
     * ĐÁNH DẤU "CÓ JAVASCRIPT" NGAY ĐẦU <head>, KHÔNG ĐỢI admin.js.
     *
     * Khu quản trị có một loạt luật CSS chỉ bật khi có JS, và tất cả đều để
     * ẩn thứ gì đó đi:
     *
     *     .js form:has(select[data-autosubmit]) .astatus__save   nút "Lưu" cạnh ô chọn tự gửi
     *     .js .aosave                                            nút lưu của từng dòng đơn hàng
     *     .js .aobulk__go                                        nút "Áp dụng" của thao tác hàng loạt
     *
     * Trước đây lớp `js` do admin.js đặt, mà file ấy nạp bằng `defer` ở cuối
     * <body> — tức là chỉ chạy SAU KHI trình duyệt đã dựng và vẽ xong cả
     * trang. Nên mỗi lần mở một màn quản trị, mấy cái nút ấy hiện ra thật rồi
     * mới biến mất: nhìn như trang đang tải nhầm một bản cũ, và trên đường
     * truyền chậm thì nó hiện đủ lâu để người dùng kịp bấm vào một cái nút
     * sắp không còn ở đó.
     *
     * Một dòng inline ở đây chạy TRƯỚC khi <body> được dựng, nên lớp `js` đã
     * có mặt ngay từ khung hình đầu tiên và không có gì nhấp nháy. Cùng cách
     * đã dùng ở khung trang bán hàng (app/views/_layout/master.php).
     *
     * Inline chứ không phải file rời: một file rời dù đặt ở <head> vẫn là một
     * lượt tải nữa, và trong quãng chờ đó thì vẫn nhấp nháy y như cũ.
     */ ?>
    <script>document.documentElement.classList.add('js');</script>

    <title><?= e($pageTitle ?? 'Quản trị — Vin Eyewear') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Be+Vietnam+Pro:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <?php /* asset() chứ KHÔNG phải đường dẫn trần — nó gắn ?v=<filemtime> vào
             sau tên file, tức là mỗi lần file đổi thì địa chỉ cũng đổi và trình
             duyệt buộc phải tải lại.

             Trang bán hàng (app/views/_layout/master.php) vẫn luôn dùng asset();
             riêng khung quản trị bị bỏ sót. Hậu quả không nhìn thấy ngay lúc
             sửa mã mà chỉ lộ ra sau khi deploy: người đã mở khu quản trị một
             lần sẽ còn giữ bản CSS cũ trong bộ nhớ đệm cho tới khi họ Ctrl+F5
             — và không ai nghĩ tới việc đó, nên kết luận đầu tiên luôn là
             "deploy hỏng". Đây đúng là chuyện đã xảy ra ngày 24/08/2026. */ ?>
    <link rel="stylesheet" href="<?= asset('assets/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
    <?php /* Hộp thoại "bạn có chắc không" — xem _layout/confirm-dialog.php.
             Nạp cho MỌI trang quản trị: gần như trang danh sách nào cũng có
             một nút xoá, và một file 2KB rẻ hơn nhiều so với việc nhớ thêm
             nó mỗi lần dựng trang mới. */ ?>
    <link rel="stylesheet" href="<?= asset('assets/css/components/confirm.css') ?>">

    <?php /* CSS RIÊNG CỦA TỪNG TRANG QUẢN TRỊ — cùng lối với $adminScripts ở
             cuối file: controller truyền thẳng mảng đường dẫn qua renderAdmin().

             Có nó thì trang nào cần nhiều luật riêng (bảng đơn hàng: ngăn kéo,
             thanh chọn hàng loạt, thanh hoàn tác) không phải nhồi tiếp vào
             admin.css — file đó đang là CSS DÙNG CHUNG cho mười tám trang, và
             mỗi lần thêm một khối chỉ một trang dùng là mười bảy trang kia
             phải tải theo.

             Trang nào không khai thì vòng lặp này chạy 0 vòng. */ ?>
    <?php foreach ($adminStyles ?? [] as $adminStyle): ?>
        <link rel="stylesheet" href="<?= asset($adminStyle) ?>">
    <?php endforeach; ?>
</head>

<body class="admin">

<a class="skip-link" href="#admin-main">Bỏ qua điều hướng</a>

<div class="admin__shell">

    <!-- ============ THANH BÊN ============ -->
    <aside class="asidebar" id="adminNav">
        <div class="asidebar__head">
            <?php /* Logo trỏ về Tổng quan quản trị, KHÔNG về trang bán hàng.
                     Người đang ngồi trong bảng quản trị bấm logo là muốn về
                     "trang chủ" của chính khu này; đá họ sang trang bán hàng
                     là mất hết ngữ cảnh và phải đi lại từ /quan-tri. Muốn xem
                     mặt tiền thì mở tab mới từ địa chỉ, không phải từ đây. */ ?>
            <a href="/quan-tri" class="asidebar__logo">Vin <em>Eyewear</em></a>
            <p class="asidebar__sub">Bảng quản trị</p>
        </div>

        <nav class="asidebar__nav" aria-label="Điều hướng quản trị">
            <?php foreach ($navGroups as $group): ?>
                <?php /* Nhãn nhóm là <p> chứ không phải <h2>: nó không mở ra một
                         vùng nội dung mà chỉ gom mấy đường dẫn cho dễ nhìn, và
                         một cấp tiêu đề giả làm rối cây tiêu đề của trang. Danh
                         sách bên dưới mới là thứ trình đọc màn hình cần. */ ?>
                <p class="asidebar__group"><?= e($group['label']) ?></p>
                <ul role="list">
                    <?php foreach ($group['items'] as $item): ?>
                        <?php
                        // Mục "Tổng quan" khớp chính xác, các mục khác khớp tiền tố
                        // để trang con (vd /quan-tri/san-pham/sua) vẫn sáng đúng mục.
                        $active = !empty($item['exact'])
                            ? $segment === $item['url']
                            : str_starts_with($segment, $item['url']);
                        ?>
                        <li>
                            <a href="<?= e($item['url']) ?>"
                               class="asidebar__link<?= $active ? ' is-active' : '' ?>"
                               <?= $active ? 'aria-current="page"' : '' ?>>
                                <span class="asidebar__label"><?= e($item['label']) ?></span>
                                <?php if (!empty($item['badge'])): ?>
                                    <span class="asidebar__badge"><?= (int) $item['badge'] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <?php
        /* Chữ cái đầu làm ảnh đại diện — bản thiết kế vẽ một vòng tròn "QT".
           Lấy chữ đầu của HAI từ cuối trong họ tên ("Phạm Duy Anh" -> "DA"),
           vì người Việt gọi nhau bằng tên chứ không bằng họ. Không có tên thì
           lấy hai ký tự đầu của email — vẫn hơn một vòng tròn trống.

           utf8Substr chứ không mb_substr: dự án cố ý không phụ thuộc extension
           mbstring (xem core/helpers.php), mà substr() trần thì cắt giữa một
           ký tự nhiều byte và ra dấu hỏi. Việc viết HOA để cho CSS
           (text-transform) làm: PHP không có sẵn hàm hoa cho chữ Việt có dấu,
           còn trình duyệt thì có. */
        $adminName = trim((string) ($adminUser['full_name'] ?? ''));
        $words     = $adminName !== '' ? preg_split('/\s+/', $adminName) : [];
        $initials  = count($words) >= 2
            ? utf8Substr($words[count($words) - 2], 0, 1) . utf8Substr($words[count($words) - 1], 0, 1)
            : ($adminName !== ''
                ? utf8Substr($adminName, 0, 2)
                : utf8Substr((string) ($adminUser['email'] ?? '?'), 0, 2));
        ?>
        <div class="asidebar__foot">
            <div class="asidebar__me">
                <span class="asidebar__avatar" aria-hidden="true"><?= e($initials) ?></span>
                <?php /* DÒNG DƯỚI LÀ EMAIL, KHÔNG PHẢI VAI TRÒ — theo bản thiết kế.
                         Vai trò thì người đang ngồi đây tự biết, và nó cũng lộ ra
                         ngay ở chỗ thanh bên có mục "Tài khoản nội bộ" hay không.
                         Cái họ không tự biết là ĐANG ĐĂNG NHẬP BẰNG TÀI KHOẢN NÀO
                         — câu hỏi có thật ở một cửa hàng dùng chung máy, và là câu
                         phải trả lời được trước khi bấm nút xoá sản phẩm.

                         Tên rỗng thì dòng trên đã in email; lúc đó không lặp lại
                         nó lần nữa, để khỏi thành hai dòng y hệt nhau. */ ?>
                <span class="asidebar__who">
                    <span class="asidebar__user"><?= e($adminName !== '' ? $adminName : ($adminUser['email'] ?? '')) ?></span>
                    <?php if ($adminName !== '' && ($adminUser['email'] ?? '') !== ''): ?>
                        <span class="asidebar__mail"><?= e($adminUser['email']) ?></span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="asidebar__acts">
                <?php /* Đường tới chỗ đổi mật khẩu của chính mình. Đặt cạnh tên và
                         nút Đăng xuất vì đây là cụm "tài khoản của tôi" — và vì
                         người được bàn giao sẽ tìm nó ở đúng chỗ này, chứ không
                         tìm trong danh sách nghiệp vụ phía trên. */ ?>
                <a class="asidebar__act" href="/quan-tri/doi-mat-khau">Đổi mật khẩu</a>

                <?php /* Đăng xuất là POST: một đường GET đăng xuất có thể bị kích
                         hoạt bằng một thẻ <img> trên trang bất kỳ. Form nằm gọn
                         trong cụm nút nên phải bỏ margin mặc định.

                         /quan-tri/dang-xuat chứ KHÔNG phải /auth/dang-xuat: khu
                         quản trị có đường ra của riêng nó, và nó đưa người bấm
                         về cổng quản trị thay vì trang chủ cửa hàng. Lý do đầy
                         đủ ở AdminAuthController::logout(). */ ?>
                <form method="post" action="/quan-tri/dang-xuat">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="asidebar__act asidebar__act--out">Đăng xuất</button>
                </form>
            </div>
        </div>
    </aside>

    <!-- ============ NỘI DUNG ============ -->
    <main class="admin__main" id="admin-main" tabindex="-1">
        <?php
        $flashOk  = flash('admin_success');
        $flashErr = flash('admin_error');
        ?>
        <?php
        /*
         * THÔNG BÁO SAU THAO TÁC LÀ TOAST NỔI Ở ĐÁY, không phải dải chữ chèn
         * vào đầu nội dung — theo bản thiết kế, và vì một lý do cụ thể.
         *
         * Từ khi form thêm/sửa và hồ sơ khách là HỘP THOẠI phủ kín màn hình,
         * một dải thông báo nằm trong dòng chảy của trang sẽ bị lớp phủ che
         * mất. Khoá một tài khoản khách xong, câu "Đã khoá tài khoản khách
         * hàng." in ra ở đúng chỗ không ai nhìn thấy.
         *
         * Toast nổi ở z-index cao hơn hộp thoại nên nó luôn đọc được, và nó
         * nằm ở đáy nên không che nhan đề hay nút của hộp.
         *
         * KHÔNG TỰ BIẾN MẤT: bản vẽ cho nó tắt sau 2,4 giây, nhưng việc đó cần
         * JavaScript, mà thông báo là thứ phải đọc được cả khi tắt JS. Nó ở lại
         * tới lượt tải trang kế tiếp — mà thao tác kế tiếp bất kỳ cũng sinh ra
         * một lượt tải. Đổi lại là không có câu nào biến mất trước khi người ta
         * kịp đọc.
         */
        ?>
        <?php if ($flashOk !== null): ?>
            <p class="atoast atoast--ok" role="status"><?= e($flashOk) ?></p>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <p class="atoast atoast--err" role="alert"><?= e($flashErr) ?></p>
        <?php endif; ?>

        <?php require VIEWS_PATH . '/' . $viewName . '.php'; ?>
    </main>
</div>

<?php partial('_layout/confirm-dialog'); ?>

<script src="<?= asset('assets/js/admin.js') ?>" defer></script>
<script src="<?= asset('assets/js/confirm-dialog.js') ?>" defer></script>

<?php /* Mở hộp thoại NGAY TẠI CHỖ thay vì tải lại trang. Nạp cho mọi trang
         quản trị vì gần như trang danh sách nào cũng có ít nhất một nút mở
         hộp, và một file 4KB rẻ hơn việc nhớ khai thêm nó mỗi lần dựng trang
         mới — cùng lẽ với confirm-dialog ở trên.

         Không có file này thì mọi nút ấy vẫn là thẻ <a> thường: bấm là tải
         lại trang và máy chủ dựng sẵn hộp trong HTML. Xem đầu file. */ ?>
<script src="<?= asset('assets/js/admin-modal.js') ?>" defer></script>

<?php /* SCRIPT RIÊNG CỦA TỪNG TRANG QUẢN TRỊ.

         Khung của site bán hàng có bảng $pageScripts tra theo tên route; ở đây
         thì controller truyền thẳng mảng đường dẫn qua renderAdmin(). Khác cách
         làm vì lý do khác: khu quản trị có ít trang cần JS riêng, mà một bảng
         tra cứu đặt ở khung thì mỗi lần thêm trang lại phải sửa hai file.

         Trang nào không khai thì vòng lặp này chạy 0 vòng. */ ?>
<?php foreach ($adminScripts ?? [] as $adminScript): ?>
    <script src="<?= asset($adminScript) ?>" defer></script>
<?php endforeach; ?>
</body>

</html>
