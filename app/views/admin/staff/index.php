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
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th>Người</th>
                    <th>Vai trò</th>
                    <th>Đăng nhập gần nhất</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                    <?php $laToi = $a['id'] === $me; ?>
                    <tr>
                        <td>
                            <?= e($a['full_name'] ?: '(chưa đặt tên)') ?>
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
                        </td>

                        <td>
                            <?php foreach (explode(', ', (string) $a['roles']) as $vt): ?>
                                <?php /* Nền đặc, KHÔNG phải .badge--paid. Viên viền
                                         rỗng trong hệ này dành riêng cho trạng thái
                                         TIỀN (xem admin.css) — mượn nó cho vai trò là
                                         dạy sai một quy ước còn dùng ở bảng đơn hàng. */ ?>
                                <span class="badge badge--<?= $vt === 'admin' ? 'in_stock' : 'neutral' ?>">
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
                                <a class="astatus__save astatus__save--ghost" href="/quan-tri/doi-mat-khau">
                                    Đổi mật khẩu
                                </a>
                            <?php elseif ($canReset): ?>
                                <?php /* data-confirm: hộp xác nhận dùng chung của khu
                                         quản trị (assets/js/confirm-dialog.js). Không có
                                         JS thì form gửi thẳng — thao tác vẫn chạy, chỉ
                                         là không có bước hỏi lại. */ ?>
                                <form method="post" action="/quan-tri/nhan-vien/dat-lai"
                                      data-confirm="Đặt lại mật khẩu của <?= e($a['email'] ?: $a['full_name'] ?: 'tài khoản này') ?>? Mật khẩu cũ sẽ hết tác dụng ngay.">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                    <button type="submit" class="astatus__save">Đặt lại mật khẩu</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
