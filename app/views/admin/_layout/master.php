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
 * Mỗi mục một icon RIÊNG, cũng theo bản thiết kế. Trước đây "Tổng quan" và
 * "Sản phẩm" cùng đeo 'layers', nhìn lướt không phân biệt được.
 *
 * 'exact' => true chỉ dành cho Tổng quan: mọi mục khác khớp theo tiền tố để
 * trang con (vd /quan-tri/san-pham/sua) vẫn sáng đúng mục cha.
 */
$navGroups = [
    ['label' => 'Vận hành', 'items' => [
        ['url' => '/quan-tri',          'label' => 'Tổng quan', 'icon' => 'grid', 'exact' => true],
        ['url' => '/quan-tri/don-hang', 'label' => 'Đơn hàng',  'icon' => 'cart'],
        ['url' => '/quan-tri/lich-hen', 'label' => 'Lịch hẹn',  'icon' => 'clock'],
        ['url' => '/quan-tri/lien-he',  'label' => 'Liên hệ',   'icon' => 'chat', 'badge' => $pendingContacts],
    ]],
    ['label' => 'Sản phẩm', 'items' => [
        ['url' => '/quan-tri/san-pham',  'label' => 'Sản phẩm',  'icon' => 'box'],
        ['url' => '/quan-tri/ton-kho',   'label' => 'Tồn kho',   'icon' => 'crate'],
        ['url' => '/quan-tri/danh-muc',  'label' => 'Danh mục',  'icon' => 'tag'],
        ['url' => '/quan-tri/gia-trong', 'label' => 'Giá tròng', 'icon' => 'glasses'],
    ]],
    ['label' => 'Marketing', 'items' => [
        ['url' => '/quan-tri/su-kien',     'label' => 'Sự kiện',     'icon' => 'calendar'],
        ['url' => '/quan-tri/ma-giam-gia', 'label' => 'Mã giảm giá', 'icon' => 'percent'],
        ['url' => '/quan-tri/danh-gia',    'label' => 'Đánh giá',    'icon' => 'star', 'badge' => $pendingReviews],
    ]],
    ['label' => 'Hệ thống', 'items' => [
        ['url' => '/quan-tri/co-so', 'label' => 'Cơ sở', 'icon' => 'map-pin'],
        // Chỉ hiện huy hiệu khi có yêu cầu chờ: trên hosting gửi được email thì
        // mục này gần như luôn rỗng, không nên gây chú ý vô cớ.
        ['url' => '/quan-tri/quen-mat-khau', 'label' => 'Quên mật khẩu', 'icon' => 'key',
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
        ['url' => '/quan-tri/nhan-vien', 'label' => 'Tài khoản nội bộ', 'icon' => 'users'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($pageTitle ?? 'Quản trị — Vin Eyewear') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Be+Vietnam+Pro:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/layout.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <?php /* Hộp thoại "bạn có chắc không" — xem _layout/confirm-dialog.php.
             Nạp cho MỌI trang quản trị: gần như trang danh sách nào cũng có
             một nút xoá, và một file 2KB rẻ hơn nhiều so với việc nhớ thêm
             nó mỗi lần dựng trang mới. */ ?>
    <link rel="stylesheet" href="/assets/css/components/confirm.css">
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
                                <?= icon($item['icon'], 'asidebar__ico', 17) ?>
                                <span><?= e($item['label']) ?></span>
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
                <span class="asidebar__who">
                    <span class="asidebar__user"><?= e($adminName !== '' ? $adminName : ($adminUser['email'] ?? '')) ?></span>
                    <span class="asidebar__role"><?= e(implode(', ', $adminRoles)) ?></span>
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
                         trong cụm nút nên phải bỏ margin mặc định. */ ?>
                <form method="post" action="/auth/dang-xuat">
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
        <?php if ($flashOk !== null): ?>
            <p class="alert alert--ok" role="status"><?= e($flashOk) ?></p>
        <?php endif; ?>
        <?php if ($flashErr !== null): ?>
            <p class="alert alert--err" role="alert"><?= e($flashErr) ?></p>
        <?php endif; ?>

        <?php require VIEWS_PATH . '/' . $viewName . '.php'; ?>
    </main>
</div>

<?php partial('_layout/confirm-dialog'); ?>

<script src="/assets/js/admin.js" defer></script>
<script src="/assets/js/confirm-dialog.js" defer></script>
</body>

</html>
