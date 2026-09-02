<?php

/**
 * admin/staff/index.php — tài khoản nội bộ.
 *
 * Controller: Admin/StaffAdminController
 *
 * Dựng lại 2026-08-29 theo "Tài khoản nội bộ.dc.html". Bản trước chỉ có MỘT
 * thao tác — đặt lại mật khẩu — nên trang này xem được ai có quyền nhưng không
 * cấp, không sửa và không thu hồi được quyền của ai. Bản vẽ có cả bốn.
 *
 * $freshPass chỉ có giá trị trong ĐÚNG lượt tải ngay sau khi tạo tài khoản hoặc
 * bấm đặt lại — nó đi qua flash. Tải lại trang là mất, đúng như mong muốn: đây
 * là chìa khoá vào khu quản trị. Cùng cách làm với liên kết đặt lại ở
 * admin/resets/index.php.
 */

$ed   = $editing;
$base = '/quan-tri/nhan-vien';

/*
 * Vai trò CAO NHẤT của một dòng.
 *
 * `roles` là chuỗi GROUP_CONCAT ('admin, staff') vì `user_roles` cho phép một
 * người mang nhiều vai trò — make-admin.php gán THÊM chứ không thay. Bản vẽ
 * hiện đúng một viên nhãn, và nó phải là vai trò cao nhất: in "Nhân viên" cho
 * một tài khoản vẫn còn vai trò admin là nói sai về quyền nó đang có.
 */
$vaiTroCao = static function (string $roles): string {
    foreach (['admin', 'manager', 'staff'] as $vt) {
        if (str_contains($roles, $vt)) {
            return $vt;
        }
    }

    return 'staff';
};

/*
 * VÒNG TRÒN CHỮ CÁI ĐẦU — theo bản thiết kế.
 *
 * Cùng cách dựng với ảnh đại diện ở chân thanh bên: lấy chữ đầu của HAI TỪ
 * CUỐI trong họ tên ("Phạm Duy Anh" -> "DA"), vì người Việt gọi nhau bằng tên
 * chứ không bằng họ. Viết hoa để CSS lo — strtoupper() làm việc trên byte nên
 * hỏng chữ Việt có dấu.
 */
$chuDauCua = static function (array $a): string {
    $ten = trim((string) ($a['full_name'] ?? ''));
    $tu  = $ten !== '' ? preg_split('/\s+/', $ten) : [];

    if (count($tu) >= 2) {
        return utf8Substr($tu[count($tu) - 2], 0, 1) . utf8Substr($tu[count($tu) - 1], 0, 1);
    }

    return $ten !== ''
        ? utf8Substr($ten, 0, 2)
        : utf8Substr((string) ($a['email'] ?? '?'), 0, 2);
};
?>

<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Tài khoản nội bộ</h1>
        <p class="ahead__lead"><?= count($accounts) ?> tài khoản có quyền vào khu quản trị</p>
    </div>

    <?php if ($canReset): ?>
        <div class="ahead__tools">
            <?php /* ?them=1 mở hộp thoại theo ĐỊA CHỈ, không theo JavaScript —
                     xem khối .amodal trong admin.css. */ ?>
            <a href="<?= e($base) ?>?them=1" class="astatus__save" data-modal>+ Thêm tài khoản</a>
        </div>
    <?php endif; ?>
</header>

<?php if ($freshPass !== null): ?>
    <div class="resetlink" role="status">
        <p class="resetlink__title">Mật khẩu tạm — chỉ hiện MỘT lần</p>
        <p class="resetlink__note">
            Của <strong><?= e($freshFor ?? '') ?></strong>. Đọc cho người đó rồi bảo họ
            vào mục <strong>Đổi mật khẩu</strong> đặt lại ngay. Tải lại trang là không
            xem lại được — khi đó phải đặt lại lần nữa.
        </p>
        <code class="resetlink__url"><?= e($freshPass) ?></code>
    </div>
<?php endif; ?>

<?php if (!$canReset): ?>
    <?php /* Nói trước, thay vì để người ta đi tìm mấy cái nút không có. Quản lý
             và nhân viên vẫn xem được danh sách — biết ai đang có quyền là việc
             chính đáng, còn cấp và thu hồi quyền thì không. */ ?>
    <p class="alert alert--err">
        Bạn xem được danh sách nhưng không tạo, sửa hay khoá được tài khoản —
        những thao tác đó cần vai trò <strong>Quản trị</strong>.
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
                    <th scope="col">Trạng thái</th>
                    <?php /* Cột cuối CÓ TÊN, không để trống — trình đọc màn hình đọc
                             một ô trống thành khoảng lặng, và người mới nhận bàn giao
                             không biết mấy cái nút ấy thuộc về việc gì. */ ?>
                    <th scope="col">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                    <?php
                    $laToi     = $a['id'] === $me;
                    $vt        = $vaiTroCao((string) $a['roles']);
                    $laQuanTri = $vt === 'admin';
                    $daKhoa    = ($a['status'] ?? 'active') === 'locked';
                    ?>
                    <tr>
                        <td class="anperson">
                            <?php /* Vòng tròn ĐỎ cho quản trị, ngà cho người khác:
                                     bảng này thường chỉ ba tới năm dòng, và câu hỏi
                                     duy nhất khi mở nó ra là "ai đang có toàn quyền"
                                     — màu trả lời trước cả khi đọc tới cột vai trò. */ ?>
                            <span class="anavatar<?= $laQuanTri ? ' is-admin' : '' ?>"
                                  aria-hidden="true"><?= e($chuDauCua($a)) ?></span>

                            <span class="anwho">
                                <span class="anname"><?= e($a['full_name'] ?: '(chưa đặt tên)') ?></span>

                                <?php if ($laToi): ?>
                                    <?php /* .atag chứ không phải .badge: xem admin.css.
                                             Nó không nói dòng này đang ở trạng thái gì,
                                             chỉ trỏ tay "dòng này là bạn" — mà hai cột
                                             bên cạnh đã có viên nhãn thật (vai trò và
                                             trạng thái), ba thứ cùng dáng thì đọc nhầm. */ ?>
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
                            <?php /* .anrole — LỚP RIÊNG, không mượn .badge--* của
                                     trạng thái. Vai trò không phải một bước trong
                                     vòng đời nào cả: nó không đổi theo thời gian và
                                     không phải việc phải làm. Lý do đầy đủ ở khối
                                     .anrole trong admin.css. */ ?>
                            <span class="anrole<?= $laQuanTri ? ' anrole--admin' : '' ?>">
                                <?= e($roles[$vt] ?? $vt) ?>
                            </span>
                        </td>

                        <td>
                            <?= $a['last_login_at'] !== null
                                ? e(formatDate($a['last_login_at'], 'd/m/Y H:i'))
                                : '<span class="atable__sub">chưa bao giờ</span>' ?>
                        </td>

                        <td>
                            <?php if ($daKhoa): ?>
                                <span class="badge badge--out_of_stock">Đã khoá</span>
                                <?php if ($a['locked_at'] !== null): ?>
                                    <span class="atable__sub">
                                        từ <?= e(formatDate($a['locked_at'], 'd/m/Y')) ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge--in_stock">Hoạt động</span>
                            <?php endif; ?>
                        </td>

                        <td class="arow-actions">
                            <?php if ($laToi): ?>
                                <?php /* Của chính mình thì chỉ có đổi mật khẩu, và nó
                                         đi qua trang riêng vì ở đó phải gõ mật khẩu
                                         hiện tại — xem StaffAdminController. */ ?>
                                <a href="/quan-tri/doi-mat-khau">Đổi mật khẩu</a>
                            <?php endif; ?>

                            <?php if ($canReset): ?>
                                <?php if (!$laToi): ?>
                                    <a href="<?= e($base) ?>?sua=<?= e($a['id']) ?>" data-modal>Sửa</a>
                                <?php endif; ?>

                                <?php /* Đặt lại mật khẩu KHÔNG có trong bản vẽ, giữ lại
                                         cố ý: đó là lý do trang này ra đời (xem khối
                                         "VÌ SAO CÓ TRANG NÀY" trong controller). Bỏ đi
                                         thì cấp lại mật khẩu cho một đồng nghiệp lại
                                         phải quay về dòng lệnh hoặc phpMyAdmin.

                                         Của chính mình thì không — đổi mật khẩu bản
                                         thân phải gõ mật khẩu hiện tại. */ ?>
                                <?php if (!$laToi): ?>
                                    <?php $hoiDatLai = sprintf(
                                        'Đặt lại mật khẩu của %s? Mật khẩu cũ sẽ hết tác dụng ngay.',
                                        $a['email'] ?: $a['full_name'] ?: 'tài khoản này'
                                    ); ?>
                                    <form method="post" action="<?= e($base) ?>/dat-lai"
                                          data-confirm="<?= e($hoiDatLai) ?>"
                                          data-confirm-title="Đặt lại mật khẩu?"
                                          data-confirm-ok="Đặt lại">
                                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                        <?php /* .arow-btn — CÙNG DÁNG với nút "Sửa" bên
                                                 cạnh, không phải nút đỏ đặc.

                                                 Trước đây dùng .aqgo (dáng nút chính),
                                                 mượn của trang Quên mật khẩu. Ở đó nó
                                                 đúng: "Tạo liên kết" là việc duy nhất
                                                 của dòng. Ở đây thì không — một nút đỏ
                                                 đặc là thứ to tiếng nhất trong dòng, mà
                                                 đặt lại mật khẩu của đồng nghiệp không
                                                 phải việc người ta vào trang này để làm. */ ?>
                                        <button type="submit" class="arow-btn">Đặt lại mật khẩu</button>
                                    </form>

                                    <?php
                                    /* KHOÁ / MỞ KHOÁ.

                                       Hướng đi kèm trong ô ẩn `khoa` chứ không để máy
                                       chủ tự lật trạng thái đang có: form dựng lúc
                                       trang tải, và giữa lúc dựng với lúc bấm có thể
                                       một quản trị khác đã đổi rồi. Tự lật thì cú bấm
                                       làm ngược hẳn thứ nhãn trên nút đang hứa. */
                                    $tenHien = $a['full_name'] ?: $a['email'] ?: 'tài khoản này';
                                    $hoiKhoa = $daKhoa
                                        ? sprintf('Mở khoá cho %s? Họ đăng nhập lại được ngay.', $tenHien)
                                        : sprintf(
                                            '%s sẽ không đăng nhập được vào khu quản trị nữa cho tới khi được mở khoá.',
                                            $tenHien
                                        );
                                    ?>
                                    <form method="post" action="<?= e($base) ?>/khoa"
                                          data-confirm="<?= e($hoiKhoa) ?>"
                                          data-confirm-title="<?= $daKhoa ? 'Mở khoá tài khoản?' : 'Khoá tài khoản?' ?>"
                                          data-confirm-ok="<?= $daKhoa ? 'Mở khoá' : 'Khoá tài khoản' ?>">
                                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                        <input type="hidden" name="khoa" value="<?= $daKhoa ? '0' : '1' ?>">
                                        <?php if ($daKhoa): ?>
                                            <button type="submit" class="aqgo">Mở khoá</button>
                                        <?php else: ?>
                                            <button type="submit" class="arow-del">Khoá</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
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

<?php
/*
 * HỘP THOẠI THÊM / SỬA — theo bản thiết kế.
 *
 * Một hộp cho cả hai việc, khác nhau ở ba chỗ đúng như bản vẽ: nhan đề, ô email
 * (khoá lại khi sửa), và ô mật khẩu tạm (chỉ có khi thêm mới).
 */
$moHop = $canReset && ($ed !== null || isset($_GET['them']));
?>
<?php if ($moHop): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $ed !== null ? 'Sửa tài khoản' : 'Thêm tài khoản nội bộ',
        'phu'     => $ed !== null
            ? (string) ($ed['email'] ?? '')
            : 'Tài khoản chỉ vào được khu quản trị, không mua hàng được.',
        'dongUrl' => $base,
        'rong'    => 'sm',
    ]); ?>

        <form method="post" action="<?= e($base) ?>/luu" class="aform__grid" id="nv-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field">
                <label for="nv-ten">Tên hiển thị *</label>
                <input type="text" id="nv-ten" name="full_name" required maxlength="120"
                       placeholder="Nguyễn Thu Trang"
                       value="<?= e((string) ($ed['full_name'] ?? '')) ?>">
            </div>

            <div class="field">
                <label for="nv-vaitro">Vai trò</label>
                <?php
                /* Vai trò hiện tại của dòng đang sửa — lấy cái CAO NHẤT, cùng
                   phép với cột Vai trò ở bảng trên. Thêm mới thì mặc định
                   'staff': quyền thấp nhất là mặc định an toàn, nâng lên thì
                   phải cố ý chọn. */
                $vtHienTai = $ed !== null ? $vaiTroCao((string) $ed['roles']) : 'staff';
                ?>
                <select id="nv-vaitro" name="role">
                    <?php foreach ($roles as $ma => $nhan): ?>
                        <option value="<?= e($ma) ?>" <?= $vtHienTai === $ma ? 'selected' : '' ?>>
                            <?= e($nhan) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field field--wide">
                <label for="nv-email">Email đăng nhập *</label>
                <?php if ($ed !== null): ?>
                    <?php /* KHOÁ Ô EMAIL KHI SỬA — theo bản vẽ, và có lý do thật:
                             email là thứ người ta dùng để đăng nhập, nên đổi nó ở
                             đây nghĩa là một người ngồi trước máy bỏ quên có thể
                             lặng lẽ chuyển tài khoản quản trị sang hòm thư khác.

                             `disabled` chứ không `readonly`: ô disabled KHÔNG được
                             gửi lên, nên máy chủ không phải tin vào việc ô này có
                             bị sửa hay không. updateStaff() cũng không đọc email. */ ?>
                    <input type="email" id="nv-email" disabled
                           value="<?= e((string) ($ed['email'] ?? '')) ?>">
                    <p class="field__hint">
                        Email đăng nhập không đổi được. Cần đổi thì tạo tài khoản mới
                        rồi khoá tài khoản này.
                    </p>
                <?php else: ?>
                    <input type="email" id="nv-email" name="email" required maxlength="255"
                           placeholder="trang@vineyewear.vn">
                <?php endif; ?>
            </div>

            <?php if ($ed === null): ?>
                <div class="field field--wide">
                    <label for="nv-mk">Mật khẩu tạm *</label>
                    <?php /* IN SẴN MỘT CHUỖI NGẪU NHIÊN, vẫn sửa được — đúng bản vẽ.
                             Để trống thì người ta gõ "123456" cho nhanh, và chuỗi ấy
                             mở thẳng khu quản trị. Chuỗi do controller sinh, không
                             phải view. */ ?>
                    <?php /* maxlength 32, KHÔNG PHẢI 72. Con số cũ là trần byte của
                             bcrypt; nay createStaff() kiểm bằng passwordProblem() và
                             hàm đó chốt ở 32 KÝ TỰ theo SNFR-09. Để 72 thì trình
                             duyệt cho gõ một chuỗi mà máy chủ sẽ đá về — form hứa
                             nhẹ hơn thứ máy chủ thật sự đòi là cách chắc nhất để
                             người dùng bị từ chối mà không hiểu vì sao. */ ?>
                    <input type="text" id="nv-mk" name="password" required minlength="8"
                           maxlength="32" class="amono" value="<?= e($newPass) ?>">
                    <p class="field__hint">
                        8–32 ký tự, có chữ hoa, chữ thường, chữ số và ký tự đặc biệt.
                        Gửi riêng cho nhân viên — bảo họ vào mục <strong>Đổi mật khẩu</strong>
                        đặt lại ngay ở lần đăng nhập đầu.
                    </p>
                </div>
            <?php endif; ?>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $base,
        'luuNhan' => $ed !== null ? 'Lưu thay đổi' : 'Tạo tài khoản',
        'luuForm' => 'nv-form',
    ]); ?>
<?php endif; ?>
