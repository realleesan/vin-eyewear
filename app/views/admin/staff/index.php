<?php

/**
 * admin/staff/index.php — tài khoản nội bộ.
 *
 * Controller: Admin/StaffAdminController
 *
 * $freshPass chỉ có giá trị trong ĐÚNG lượt tải ngay sau khi bấm đặt lại — nó
 * đi qua flash. Tải lại trang là mất, đúng như mong muốn: đây là chìa khoá vào
 * khu quản trị. Cùng cách làm với liên kết đặt lại ở admin/resets/index.php.
 */

/* Nhãn tiếng Việt của vai trò. GROUP_CONCAT trả về chuỗi 'admin, manager' nên
   phải tách ra rồi dịch từng cái — in thẳng thì bảng đầy chữ tiếng Anh giữa
   một trang tiếng Việt. */
$nhanVaiTro = [
    'admin'   => 'Quản trị',
    'manager' => 'Quản lý',
    'staff'   => 'Nhân viên',
];
?>

<div class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Tài khoản nội bộ</h1>
        <p class="ahead__lead"><?= count($accounts) ?> tài khoản có quyền vào khu quản trị</p>
    </div>
</div>

<?php if ($freshPass !== null): ?>
    <div class="resetlink" role="status">
        <p class="resetlink__title">Mật khẩu mới — chỉ hiện MỘT lần</p>
        <p class="resetlink__note">
            Của <strong><?= e($freshFor ?? '') ?></strong>. Đọc cho người đó rồi bảo họ
            vào mục <strong>Đổi mật khẩu</strong> đặt lại ngay. Tải lại trang là không
            xem lại được — khi đó phải đặt lại lần nữa.
        </p>
        <code class="resetlink__url"><?= e($freshPass) ?></code>
    </div>
<?php endif; ?>

<?php if (!$canReset): ?>
    <?php /* Nói trước, thay vì để người ta đi tìm cái nút không có. Quản lý và
             nhân viên vẫn xem được danh sách — biết ai đang có quyền là việc
             chính đáng, còn cấp lại chìa khoá thì không. */ ?>
    <p class="alert alert--err">
        Bạn xem được danh sách nhưng không đặt lại được mật khẩu — thao tác đó cần
        vai trò <strong>Quản trị</strong>.
    </p>
<?php endif; ?>

<?php if ($accounts === []): ?>
    <p class="apanel__empty">Chưa có tài khoản nội bộ nào.</p>
<?php else: ?>

    <div class="atable-wrap">
        <table class="atable antable">
            <thead>
                <tr>
                    <th scope="col">Người</th>
                    <th scope="col">Vai trò</th>
                    <th scope="col">Đăng nhập gần nhất</th>
                    <?php /* Cột cuối CÓ TÊN, không để trống — trình đọc màn hình đọc
                             một ô trống thành khoảng lặng, và người mới nhận bàn giao
                             không biết mấy cái nút ấy thuộc về việc gì. */ ?>
                    <th scope="col">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                    <?php $laToi = $a['id'] === $me; ?>
                    <?php
                    /*
                     * VÒNG TRÒN CHỮ CÁI ĐẦU — theo bản thiết kế.
                     *
                     * Cùng cách dựng với ảnh đại diện ở chân thanh bên: lấy chữ đầu
                     * của HAI TỪ CUỐI trong họ tên ("Phạm Duy Anh" -> "DA"), vì người
                     * Việt gọi nhau bằng tên chứ không bằng họ. Viết hoa để CSS lo —
                     * strtoupper() làm việc trên byte nên hỏng chữ Việt có dấu.
                     *
                     * Vòng tròn ĐỎ cho quản trị, ngà cho nhân viên: bảng này thường
                     * chỉ ba tới năm dòng, và câu hỏi duy nhất khi mở nó ra là "ai
                     * đang có toàn quyền" — màu trả lời trước cả khi đọc tới cột vai
                     * trò.
                     */
                    $tenDayDu = trim((string) ($a['full_name'] ?? ''));
                    $tu       = $tenDayDu !== '' ? preg_split('/\s+/', $tenDayDu) : [];
                    $chuDau   = count($tu) >= 2
                        ? utf8Substr($tu[count($tu) - 2], 0, 1) . utf8Substr($tu[count($tu) - 1], 0, 1)
                        : ($tenDayDu !== ''
                            ? utf8Substr($tenDayDu, 0, 2)
                            : utf8Substr((string) ($a['email'] ?? '?'), 0, 2));
                    $laQuanTri = str_contains((string) $a['roles'], 'admin');
                    ?>
                    <tr>
                        <td class="anperson">
                            <span class="anavatar<?= $laQuanTri ? ' is-admin' : '' ?>" aria-hidden="true"><?= e($chuDau) ?></span>
                            <span class="anwho">
                            <span class="anname"><?= e($a['full_name'] ?: '(chưa đặt tên)') ?></span>
                            <?php if ($laToi): ?>
                                <?php /* .atag chứ không phải .badge: xem admin.css.
                                         Nó không nói dòng này đang ở trạng thái gì,
                                         chỉ trỏ tay "dòng này là bạn" — mà cột bên
                                         cạnh đã có một viên thuốc trạng thái thật
                                         (vai trò), hai thứ cùng dáng thì đọc nhầm. */ ?>
                                <span class="atag">Bạn</span>
                            <?php endif; ?>
                            <?php /* Email là thứ dùng để đăng nhập ở cổng quản trị,
                                     nên nó phải hiện — không có email thì tài khoản
                                     đó KHÔNG vào được bằng cổng đó, và người soát
                                     cần nhìn ra ngay. */ ?>
                            <span class="atable__sub">
                                <?= $a['email'] !== null && $a['email'] !== ''
                                    ? e($a['email'])
                                    : '<em>không có email — không đăng nhập được ở /quan-tri/dang-nhap</em>' ?>
                            </span>
                            </span>
                        </td>

                        <td>
                            <?php foreach (explode(', ', (string) $a['roles']) as $vt): ?>
                                <?php /* .anrole — LỚP RIÊNG, không mượn .badge--* của
                                         trạng thái. Vai trò không phải một bước trong
                                         vòng đời nào cả: nó không đổi theo thời gian và
                                         không phải việc phải làm. Lý do đầy đủ ở khối
                                         .anrole trong admin.css. */ ?>
                                <span class="anrole<?= $vt === 'admin' ? ' anrole--admin' : '' ?>">
                                    <?= e($nhanVaiTro[$vt] ?? $vt) ?>
                                </span>
                            <?php endforeach; ?>
                        </td>

                        <td>
                            <?= $a['last_login_at'] !== null
                                ? e(formatDate($a['last_login_at'], 'd/m/Y H:i'))
                                : '<span class="atable__sub">chưa bao giờ</span>' ?>
                        </td>

                        <td class="arow-actions">
                            <?php if ($laToi): ?>
                                <a href="/quan-tri/doi-mat-khau">Đổi mật khẩu</a>
                            <?php elseif ($canReset): ?>
                                <?php /* data-confirm: hộp xác nhận dùng chung của khu
                                         quản trị (assets/js/confirm-dialog.js). Không có
                                         JS thì form gửi thẳng — thao tác vẫn chạy, chỉ
                                         là không có bước hỏi lại. */ ?>
                                <form method="post" action="/quan-tri/nhan-vien/dat-lai"
                                      data-confirm="Đặt lại mật khẩu của <?= e($a['email'] ?: $a['full_name'] ?: 'tài khoản này') ?>? Mật khẩu cũ sẽ hết tác dụng ngay.">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                    <?php /* .aqgo — dáng nút chính nhưng nhỏ hơn một
                                             nấc, cùng lớp với nút "Tạo liên kết" ở
                                             trang Quên mật khẩu: cả hai đều cấp lại
                                             chìa khoá cho một tài khoản, và cả hai đều
                                             nằm trong một ô bảng. */ ?>
                                    <button type="submit" class="aqgo">Đặt lại mật khẩu</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php /* GHI CHÚ VAI TRÒ Ở CHÂN TRANG — theo bản thiết kế.

         Cột "Vai trò" in ra hai chữ "Quản trị" / "Nhân viên" và dừng ở đó, nhưng
         câu người ta thật sự hỏi khi cấp tài khoản là "cho vai trò này thì nó
         làm được gì". Không trả lời ở đây thì câu trả lời nằm trong ma trận
         quyền của tài liệu đặc tả — chỗ mà người trực quầy không mở bao giờ. */ ?>
<p class="annote">
    Tài khoản nội bộ chỉ vào được khu quản trị, không mua hàng được. Vai trò
    <strong>Quản trị</strong> làm được mọi thứ kể cả trang này;
    <strong>Nhân viên</strong> xử lý đơn, lịch hẹn và liên hệ nhưng không sửa
    được giá, tài khoản hay cơ sở.
</p>

<?php endif; ?>
