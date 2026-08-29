<?php

/**
 * admin/customers/index.php — danh sách khách hàng.
 *
 * Controller: Admin/CustomerAdminController::index()
 *
 * KHÔNG có nút "Thêm khách hàng" — cố ý, nên trang này dùng .ahead--row tự
 * dựng chứ không dùng partial crud-head. Tài khoản khách chỉ sinh ra từ việc
 * khách tự đăng ký ở mặt tiền; tạo hộ một tài khoản ở đây là tạo ra một hồ sơ
 * không có mật khẩu nào ai biết, không có email đã xác minh, và không có cú
 * tick đồng ý điều khoản — thứ mà cột `terms_accepted_at` cố ý để NULL thay vì
 * bịa (xem schema.sql).
 */
?>
<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Khách hàng</h1>
        <p class="ahead__lead">
            <?= (int) $total ?> khách<?= $totalPages > 1 ? ' · trang ' . (int) $page . '/' . (int) $totalPages : '' ?>
        </p>
    </div>

    <div class="ahead__tools">
        <?php /* Form GET, không JS: gõ rồi Enter là tải lại với ?q=… trên địa
                 chỉ — chia sẻ được, quay lại được, F5 không hỏi gửi lại. Ô ẩn
                 giữ lại tab trạng thái đang đứng, nếu không thì tìm kiếm sẽ
                 lặng lẽ nhảy về "Tất cả". */ ?>
        <form class="asearch" method="get" action="/quan-tri/khach-hang" role="search">
            <label class="sr-only" for="q">Tìm khách hàng</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>"
                   placeholder="Tên, email hoặc số điện thoại…">
            <?php if ($filter !== ''): ?>
                <input type="hidden" name="status" value="<?= e($filter) ?>">
            <?php endif; ?>
            <button type="submit" class="astatus__save astatus__save--ghost">Tìm</button>
            <?php if ($q !== ''): ?>
                <a href="/quan-tri/khach-hang<?= $filter !== '' ? '?status=' . e(rawurlencode($filter)) : '' ?>"
                   class="apanel__more">Xoá tìm kiếm</a>
            <?php endif; ?>
        </form>

        <?php if ($canManage): ?>
            <?php /* Đường dẫn mang theo ĐÚNG bộ lọc đang xem: người bấm "Xuất"
                     mong nhận về cái họ đang nhìn thấy, không phải toàn bộ cơ
                     sở dữ liệu. Đây cũng là cách giảm bớt lượng dữ liệu cá nhân
                     bị tải xuống máy cá nhân mỗi lần. */ ?>
            <a class="astatus__save"
               href="/quan-tri/khach-hang/xuat<?= ($x = http_build_query(array_filter(['q' => $q, 'status' => $filter]))) !== '' ? '?' . e($x) : '' ?>">
                Xuất Excel
            </a>
        <?php endif; ?>
    </div>
</header>

<?php partial('admin/_layout/filter-tabs', [
    'base'     => '/quan-tri/khach-hang',
    'statuses' => $filters,
    'counts'   => $counts,
    'current'  => $filter,
    // Giữ từ khoá khi bấm sang tab khác — xem chú thích trong chính partial đó.
    'keep'     => ['q' => $q],
]); ?>

<?php if ($customers === []): ?>
    <p class="apanel__empty">
        <?= $q !== ''
            ? 'Không tìm thấy khách hàng nào khớp "' . e($q) . '".'
            : 'Chưa có khách hàng nào.' ?>
    </p>
<?php else: ?>

    <div class="atable-wrap">
        <table class="atable acustable">
            <thead>
                <tr>
                    <th scope="col">Khách hàng</th>
                    <th scope="col">Liên hệ</th>
                    <th scope="col">Ngày đăng ký</th>
                    <th scope="col" class="acus__num-col">Số đơn</th>
                    <th scope="col" class="acus__num-col">Tổng chi tiêu</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $kh): ?>
                    <?php
                    $daXoa  = $kh['deleted_at'] !== null;
                    $chiTiet = '/quan-tri/khach-hang/' . rawurlencode($kh['id']);
                    ?>
                    <tr>
                        <td>
                            <?php /* Cả ô tên là một đường dẫn: đây là thao tác
                                     người ta làm nhiều nhất trên bảng này, nên
                                     đích bấm phải to chứ không phải một chữ
                                     "Xem" bé ở cột cuối. Cột cuối vẫn có nút
                                     cho người quen tìm nút ở đó. */ ?>
                            <a href="<?= e($chiTiet) ?>" data-modal>
                                <?= e($kh['full_name'] ?: '(chưa đặt tên)') ?>
                            </a>
                        </td>

                        <td>
                            <?php /* SỐ ĐIỆN THOẠI ĐỨNG TRÊN EMAIL. Ở cửa hàng
                                     kính, liên lạc với khách là gọi điện —
                                     email chỉ dùng để gửi liên kết đặt lại mật
                                     khẩu. Xếp thứ tự theo việc người ta thật
                                     sự làm với hai dòng này. */ ?>
                            <?= $kh['phone'] !== null && $kh['phone'] !== ''
                                ? e(groupPhone($kh['phone']))
                                : '<span class="atable__sub">chưa có số</span>' ?>
                            <?php if ($kh['email'] !== null && $kh['email'] !== ''): ?>
                                <span class="atable__sub"><?= e($kh['email']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e(formatDate($kh['created_at'], 'd/m/Y')) ?>
                            <span class="atable__sub">
                                <?= $kh['last_login_at'] !== null
                                    ? 'vào lần cuối ' . e(formatDate($kh['last_login_at'], 'd/m/Y'))
                                    : 'chưa đăng nhập lần nào' ?>
                            </span>
                        </td>

                        <td class="acus__num-col"><?= (int) $kh['so_don'] ?></td>

                        <td class="acus__num-col">
                            <?php /* .num để lấy phông chữ số đều bề ngang —
                                     cột tiền xếp chồng nhau mà chữ số so le
                                     thì mắt không so được hàng nào lớn hơn. */ ?>
                            <span class="num"><?= e(money((int) $kh['tong_tien'])) ?></span>
                        </td>

                        <td>
                            <?php if ($daXoa): ?>
                                <span class="badge badge--cancelled">Đã xoá</span>
                            <?php elseif ($kh['status'] === 'locked'): ?>
                                <span class="badge badge--out_of_stock">Đã khoá</span>
                            <?php else: ?>
                                <span class="badge badge--in_stock">Hoạt động</span>
                            <?php endif; ?>
                        </td>

                        <td class="arow-actions">
                            <a href="<?= e($chiTiet) ?>" data-modal>Xem chi tiết</a>

                            <?php /* NÚT KHOÁ / MỞ KHOÁ KHÔNG NẰM Ở ĐÂY, cố ý.

                                     Khoá tài khoản BẮT BUỘC nhập lý do (xem
                                     CustomerModel::lock), mà một dòng bảng
                                     không có chỗ cho ô nhập đó. Nhét vào thì
                                     hoặc phải bỏ ràng buộc lý do, hoặc phải
                                     dựng thêm một hộp thoại — cả hai đều để
                                     đổi lấy việc tiết kiệm đúng một cú bấm.

                                     Cả hai nút nằm trong tab Hồ sơ của trang
                                     chi tiết, nơi có sẵn ô lý do và nơi người
                                     bấm đang nhìn đủ thông tin về người mình
                                     sắp khoá. */ ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php /* Chân bảng nằm TRONG khung, cùng lối với mọi bảng khác của khu
                 quản trị — xem .aofoot trong admin.css. */ ?>
        <div class="aofoot">
            <p class="aofoot__count">
                Đang hiện <?= count($customers) ?> / <?= (int) $total ?> khách
                <?php if ($totalPages > 1): ?>· trang <?= (int) $page ?>/<?= (int) $totalPages ?><?php endif; ?>
            </p>

            <?php if ($totalPages > 1): ?>
                <nav class="pager" aria-label="Phân trang">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $url = '/quan-tri/khach-hang?' . http_build_query(
                            array_filter(['q' => $q, 'status' => $filter, 'page' => $i])
                        ); ?>
                        <?php if ($i === $page): ?>
                            <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pager__link" href="<?= e($url) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php
/*
 * HỘP THOẠI HỒ SƠ KHÁCH — theo bản thiết kế "Khách hàng.dc.html".
 *
 * Trước đây hồ sơ là một TRANG RIÊNG: bấm "Xem chi tiết" là rời bảng, xem
 * xong phải bấm quay lại và mất chỗ đang đứng (trang mấy, đang lọc gì, vừa
 * gõ tìm gì). Bản vẽ để nó nổi lên trên chính bảng ấy.
 *
 * $khach chỉ tồn tại khi controller đi qua show() — cùng một view dựng ra cả
 * hai cảnh, nên với JavaScript thì admin-modal.js fetch địa chỉ
 * /quan-tri/khach-hang/<id> rồi bóc riêng phần .amodal ra gắn tại chỗ, còn
 * không có JavaScript thì trình duyệt tải cả trang và thấy hộp nằm sẵn trên
 * bảng. Cùng một HTML, hai đường tới.
 *
 * KHÔNG dùng modal-foot: chân hộp đó có nút Lưu, mà hồ sơ khách không có gì
 * để lưu ở cấp hộp — mọi thao tác (khoá, xoá, sửa đơn thuốc) nằm trong tab và
 * có nút riêng của nó. Ở đây chỉ cần một lối ra.
 */
?>
<?php if (isset($khach) && $khach !== null): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $khach['full_name'] ?: '(chưa đặt tên)',
        'phu'     => $khach['email'] ?: ($khach['phone'] ?: ''),
        'dongUrl' => '/quan-tri/khach-hang',
        'rong'    => 'xxl',
        /* Bốn tab = bốn lần dựng CÙNG một hộp. Khoá này để đổi tab chỉ thay
           ruột chứ không dựng lại khung — xem modal-head.php. Lấy theo id
           khách: mở người khác là hộp khác, dựng lại là đúng. */
        'khoa'    => 'khach-' . $khach['id'],
        /* Bốn tab dài ngắn rất khác nhau — Hồ sơ vài dòng, Hoạt động vài chục.
           Không khoá chiều cao thì mỗi lần đổi tab hộp lại nhảy một cỡ, và cái
           thanh tab người ta đang nhắm bắn cũng chạy theo. */
        'cao'     => true,
    ]); ?>

        <?php
        /* require thẳng chứ không partial(): hồ sơ cần rất nhiều biến khác
           nhau tuỳ tab ($addresses, $rxRecords, $activity…). Liệt kê lại từng
           cái cho partial là một danh sách sẽ lệch với thực tế ngay lần sửa
           đầu. require giữ nguyên phạm vi biến của file này. */
        require VIEWS_PATH . '/admin/customers/detail.php';
        ?>

        </div>

        <div class="amodal__foot">
            <a class="astatus__save astatus__save--ghost" href="/quan-tri/khach-hang" data-modal-close>Đóng</a>
        </div>
    </div>
</div>
<?php endif; ?>
