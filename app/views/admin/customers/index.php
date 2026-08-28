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
                            <a href="<?= e($chiTiet) ?>">
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
                            <a href="<?= e($chiTiet) ?>">Xem chi tiết</a>

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
