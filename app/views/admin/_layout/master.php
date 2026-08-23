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

$navItems = [
    ['url' => '/quan-tri',           'label' => 'Tổng quan', 'icon' => 'award',     'exact' => true],
    ['url' => '/quan-tri/san-pham',  'label' => 'Sản phẩm',  'icon' => 'layers'],
    ['url' => '/quan-tri/ton-kho',   'label' => 'Tồn kho',   'icon' => 'truck'],
    ['url' => '/quan-tri/danh-muc',  'label' => 'Danh mục',  'icon' => 'filter'],
    ['url' => '/quan-tri/su-kien',   'label' => 'Sự kiện',   'icon' => 'newspaper'],
    ['url' => '/quan-tri/don-hang',  'label' => 'Đơn hàng',  'icon' => 'check'],
    ['url' => '/quan-tri/lich-hen',  'label' => 'Lịch hẹn',  'icon' => 'eye'],
    ['url' => '/quan-tri/lien-he',   'label' => 'Liên hệ',   'icon' => 'message',  'badge' => $pendingContacts],
    ['url' => '/quan-tri/danh-gia',   'label' => 'Đánh giá',  'icon' => 'quote', 'badge' => $pendingReviews],
    ['url' => '/quan-tri/ma-giam-gia', 'label' => 'Mã giảm giá', 'icon' => 'badge-check'],
    ['url' => '/quan-tri/gia-trong', 'label' => 'Giá tròng', 'icon' => 'glasses'],
    ['url' => '/quan-tri/co-so',     'label' => 'Cơ sở',     'icon' => 'map-pin'],
    // Chỉ hiện huy hiệu khi có yêu cầu chờ: trên hosting gửi được email thì
    // mục này gần như luôn rỗng, không nên gây chú ý vô cớ.
    ['url' => '/quan-tri/quen-mat-khau', 'label' => 'Quên mật khẩu', 'icon' => 'shield',
     'badge' => $pendingResets],
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
 * Muốn dùng lại: thêm dòng
 *   ['url' => '/quan-tri/bien-the', 'label' => 'Biến thể', 'icon' => 'layers'],
 * vào mảng trên, không phải dựng lại gì cả.
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
    $navItems[] = ['url' => '/quan-tri/nhan-vien', 'label' => 'Tài khoản nội bộ',
                   'icon' => 'badge-check'];
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
            <ul role="list">
                <?php foreach ($navItems as $item): ?>
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
                            <?= icon($item['icon'], 'asidebar__ico', 16) ?>
                            <span><?= e($item['label']) ?></span>
                            <?php if (!empty($item['badge'])): ?>
                                <span class="asidebar__badge"><?= (int) $item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="asidebar__foot">
            <p class="asidebar__user"><?= e($adminUser['full_name'] ?? $adminUser['email']) ?></p>
            <p class="asidebar__role"><?= e(implode(', ', $adminRoles)) ?></p>

            <?php /* Đường tới chỗ đổi mật khẩu của chính mình. Đặt cạnh tên và
                     nút Đăng xuất vì đây là cụm "tài khoản của tôi" — và vì
                     người được bàn giao sẽ tìm nó ở đúng chỗ này, chứ không
                     tìm trong danh sách nghiệp vụ phía trên. */ ?>
            <a class="asidebar__acct" href="/quan-tri/doi-mat-khau">Đổi mật khẩu</a>

            <form method="post" action="/auth/dang-xuat">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <button type="submit" class="asidebar__logout">Đăng xuất</button>
            </form>
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
