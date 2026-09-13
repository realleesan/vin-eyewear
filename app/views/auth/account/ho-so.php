<?php

/**
 * auth/account/ho-so.php — tab "Hồ sơ" (/tai-khoan?muc=ho-so)
 *
 * Hai màn "Profile" và "Edit Profile" của "Ho So Nguoi Dung.dc.html":
 *
 *   HỒ SƠ                               SỬA HỒ SƠ  (?sua-ho-so=1)
 *     họ tên · email · số điện thoại      *Bắt buộc
 *     [ SỬA HỒ SƠ ]                       [ Email            SỬA ]
 *   TÀI KHOẢN LIÊN KẾT                    [ Tên*                 ]
 *     [ G  GOOGLE                ⌁ ]      [ Họ*                  ]
 *   ĐỔI MẬT KHẨU                          [ Số điện thoại        ]
 *     [ ĐỔI MẬT KHẨU ]                    [        LƯU           ]
 *   ───────────────
 *   XOÁ TÀI KHOẢN
 *     XOÁ TÀI KHOẢN CỦA TÔI  -> hộp thoại (?xoa=1)
 *
 * KHỐI "MARKETING PREFERENCES" KHÔNG DỰNG — chủ dự án chốt 13/09/2026: hệ
 * thống chưa có chỗ lưu hai công tắc ấy, và một công tắc bấm được mà không lưu
 * gì là lời hứa suông.
 *
 * HAI Ô Tên* · Họ* GHI VÀO MỘT CỘT full_name — xem UserModel::tachHoTen() và
 * AuthController::updateProfile(). Dấu * do oa.css sinh từ `required`.
 *
 * HAI CHỖ HỎI MẬT KHẨU mà bản vẽ không có — gỡ liên kết Google và xoá tài
 * khoản. Cả hai tháo một lối vào không lấy lại được bằng một cú bấm; lý do đầy
 * đủ ở UserModel::unlinkGoogle() và CustomerModel::khachTuXoa().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI TRẠNG THÁI MỞ BẰNG URL, KHÔNG BẰNG JAVASCRIPT
 *
 *     ?sua-ho-so=1            form sửa hồ sơ (thay cả tab, đúng như bản vẽ)
 *     &sua-email=1            mở ô email trong form đó
 *     ?doi-mat-khau=1         mở form đổi mật khẩu
 *     ?xoa=1 (&canh-bao=1)    hộp thoại xoá tài khoản
 *
 * F5 không mất chỗ, và máy chủ gửi lỗi về đúng trạng thái còn mở — xem các
 * redirect trong AuthController.
 */

$email = trim((string) ($profile['email'] ?? ''));

/* $daNoi và $googleOn đã gỡ cùng khối "Tài khoản liên kết" — không còn chỗ
   nào trong file này đọc chúng. Controller VẪN truyền $google xuống (xem
   AuthController::profile); để nguyên bên đó vì hai đường nối/gỡ Google chưa
   gỡ, và một biến thừa thì rẻ hơn một lần dựng lại. */
?>

<?php if ($suaHoSo): ?>
    <?php
    /* ═════════════════════════ SỬA HỒ SƠ ═════════════════════════
       EMAIL: bản thiết kế vẽ nó thành một dòng chỉ-đọc kèm chữ "SỬA". Bấm
       mới mở ô nhập — đổi email là đổi một lối đăng nhập, không nên nằm sẵn
       dưới con trỏ như ô họ tên.

       Ô email VẪN LUÔN ĐƯỢC GỬI (ẩn khi chưa bấm Sửa): updateProfile() gọi
       UserModel::updateEmail() với giá trị này, và thiếu nó là xoá email.
       Tài khoản chưa có email thì mở sẵn ô — không có gì để "sửa". */
    $suaEmail = isset($_GET['sua-email']) || $email === '';
    $hoTen    = UserModel::tachHoTen($profile['full_name'] ?? '');
    ?>
    <h1 class="acct-title">Sửa hồ sơ</h1>

    <form class="acct-edit" id="thong-tin" method="post" action="/tai-khoan/ho-so">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <p class="acct-req">*Bắt buộc</p>

        <div class="acct-boxes">
            <?php if (!$suaEmail): ?>
                <div class="acct-field acct-field--bare acct-field--row">
                    <span class="acct-field__main">
                        <span class="acct-field__cap">Địa chỉ email</span>
                        <span class="acct-field__value"><?= e($email) ?></span>
                    </span>
                    <a class="acct-field__act" href="/tai-khoan?muc=ho-so&amp;sua-ho-so=1&amp;sua-email=1">Sửa</a>
                    <input type="hidden" name="email" value="<?= e($email) ?>">
                </div>
            <?php else: ?>
                <label class="acct-field acct-field--bare">
                    <span class="acct-field__label">Địa chỉ email</span>
                    <?php /* Không bắt buộc: tài khoản đăng ký bằng số điện thoại
                             ra đời không có email. Địa chỉ gõ ở đây CHƯA XÁC MINH
                             — updateEmail() đặt email_verified về 0. */ ?>
                    <input class="acct-field__ctl" type="email" name="email" maxlength="255"
                           autocomplete="email" placeholder="ban@vidu.com" value="<?= e($email) ?>">
                </label>
            <?php endif; ?>

            <label class="acct-field acct-field--bare">
                <span class="acct-field__label">Tên</span>
                <input class="acct-field__ctl" type="text" name="first_name" required
                       maxlength="120" autocomplete="given-name" value="<?= e($hoTen['ten']) ?>">
            </label>

            <label class="acct-field acct-field--bare">
                <span class="acct-field__label">Họ</span>
                <input class="acct-field__ctl" type="text" name="last_name" required
                       maxlength="60" autocomplete="family-name" value="<?= e($hoTen['ho']) ?>">
            </label>

            <label class="acct-field acct-field--bare">
                <span class="acct-field__label">Số điện thoại</span>
                <input class="acct-field__ctl" type="tel" name="phone" autocomplete="tel"
                       value="<?= e($profile['phone'] ?? '') ?>">
            </label>
        </div>

        <?php /* Nút xám, không bấm được cho tới khi đủ Tên và Họ — đúng bản vẽ.
                 Máy chủ vẫn kiểm lại (AuthController::updateProfile). */ ?>
        <button type="submit" class="acct-btn acct-btn--solid acct-btn--save acct-edit__save">Lưu</button>
    </form>

<?php else: ?>

    <h1 class="acct-title">Hồ sơ</h1>

    <section class="acct-sec acct-sec--first" id="thong-tin">
        <?php partial('auth/account/_toi', ['profile' => $profile]); ?>
        <a class="acct-btn acct-btn--gap" href="/tai-khoan?muc=ho-so&amp;sua-ho-so=1">Sửa hồ sơ</a>
    </section>

    <?php
    /* ┌─ ĐÃ GỠ: KHỐI "TÀI KHOẢN LIÊN KẾT" (13/09/2026, yêu cầu chủ dự án) ────
       │ Nó là ô nối/gỡ tài khoản Google trong trang hồ sơ.
       │
       │ ĐĂNG NHẬP BẰNG GOOGLE KHÔNG BỊ ĐỘNG TỚI: nút Google ở ngăn kéo đăng
       │ nhập và màn đăng ký vẫn nguyên, tài khoản đã nối vẫn đăng nhập được
       │ như cũ. Thứ mất đi là hai thao tác THỦ CÔNG trong trang hồ sơ:
       │
       │   · nối Google vào một tài khoản đã lập bằng số điện thoại
       │   · gỡ liên kết Google ra khỏi tài khoản
       │
       │ Hai đường POST /tai-khoan/google và /tai-khoan/google/go vẫn còn
       │ trong config/routes.php và AuthController — CỐ Ý giữ: gỡ chúng đi là
       │ ngày nào chủ dự án muốn khối này quay lại thì phải dựng lại cả phần
       │ máy chủ, chứ không phải chỉ dán lại đoạn HTML.
       └──────────────────────────────────────────────────────────────────── */
    ?>

    <?php
    /* ═════════════════════════ ĐỔI MẬT KHẨU ═════════════════════════
       Luật mật khẩu nói bằng một câu, khớp passwordProblem() trong
       core/helpers.php — đó mới là nơi quyết định. Sửa luật thì sửa cả câu. */
    $quyTacMatKhau = 'Từ 8 đến 32 ký tự, gồm chữ hoa, chữ thường, chữ số và ký tự đặc biệt.';
    $moMatKhau     = isset($_GET['doi-mat-khau']);
    ?>
    <section class="acct-sec" id="doi-mat-khau" aria-labelledby="hs-mk">
        <h2 class="acct-label" id="hs-mk">Đổi mật khẩu</h2>

        <?php if (!$moMatKhau): ?>
            <p class="acct-text"><?= e($quyTacMatKhau) ?></p>
            <a class="acct-btn acct-btn--gap" href="/tai-khoan?muc=ho-so&amp;doi-mat-khau=1#doi-mat-khau">Đổi mật khẩu</a>
        <?php else: ?>
            <form class="acct-form" method="post" action="/tai-khoan/mat-khau">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <!-- Ô email ẩn: trình quản lý mật khẩu cần biết mục này thuộc tài
                     khoản nào thì mới đề nghị cập nhật đúng bản ghi đã lưu. -->
                <input type="hidden" autocomplete="username" value="<?= e($email) ?>">

                <label class="sr-only" for="mk-hien-tai">Mật khẩu hiện tại</label>
                <input class="acct-input" type="password" id="mk-hien-tai" name="current_password"
                       required autocomplete="current-password" placeholder="Mật khẩu hiện tại">

                <label class="sr-only" for="mk-moi">Mật khẩu mới</label>
                <input class="acct-input" type="password" id="mk-moi" name="new_password"
                       required minlength="8" maxlength="32" autocomplete="new-password"
                       placeholder="Mật khẩu mới" aria-describedby="mk-luat">

                <?php /* Ô nhập lại vẫn được so ở máy chủ — AuthController::changePassword()
                         so hai chuỗi trước khi gọi model; `required` chỉ là gợi ý. */ ?>
                <label class="sr-only" for="mk-xac-nhan">Nhập lại mật khẩu mới</label>
                <input class="acct-input" type="password" id="mk-xac-nhan" name="new_password_confirm"
                       required minlength="8" maxlength="32" autocomplete="new-password"
                       placeholder="Nhập lại mật khẩu mới">

                <p class="acct-note" id="mk-luat">
                    <?= e($quyTacMatKhau) ?> Đổi mật khẩu sẽ đăng xuất mọi thiết bị khác đang ghi nhớ đăng nhập.
                </p>

                <div class="acct-pair">
                    <a class="acct-btn" href="/tai-khoan?muc=ho-so#doi-mat-khau">Huỷ</a>
                    <button type="submit" class="acct-btn acct-btn--solid">Cập nhật mật khẩu</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <?php
    /* ═════════════════════════ XOÁ TÀI KHOẢN — UC-01 · FR-TK-17 ═════════════════════════
       Xoá MỀM (CustomerModel::khachTuXoa): đơn hàng đã đặt vẫn nằm lại cho cửa
       hàng xử lý, nên câu chữ KHÔNG hứa xoá "lịch sử mua hàng" như bản vẽ. */
    $moXoa   = isset($_GET['xoa']);
    $canhBao = isset($_GET['canh-bao']);
    ?>
    <section class="acct-sec acct-sec--rule" id="xoa-tai-khoan" aria-labelledby="hs-xoa">
        <h2 class="acct-label" id="hs-xoa">Xoá tài khoản</h2>
        <p class="acct-text acct-mute">
            Xoá vĩnh viễn tài khoản, hồ sơ và dữ liệu đã lưu. Không thể hoàn tác.
        </p>
        <a class="acct-link" href="/tai-khoan?muc=ho-so&amp;xoa=1#xoa-tai-khoan">Xoá tài khoản của tôi</a>
    </section>

    <?php if ($moXoa): ?>
        <div class="acct-modal">
            <?php /* Bấm ra ngoài hộp = đóng, như bản thiết kế. Là một liên kết
                     thật nên chạy cả khi tắt JS; tabindex -1 vì nút "Huỷ" trong
                     hộp đã là lối đóng cho bàn phím. */ ?>
            <a class="acct-modal__scrim" href="/tai-khoan?muc=ho-so#xoa-tai-khoan" tabindex="-1" aria-hidden="true"></a>

            <form class="acct-modal__box" role="dialog" aria-modal="true" aria-labelledby="xoa-tieu-de"
                  method="post" action="/tai-khoan/xoa">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                <?php if ($canhBao): ?>
                    <?php /* E4 — khách đã đọc cảnh báo về lịch hẹn sắp tới; ô ẩn này
                             là "đã xác nhận lần hai". Chỉ có sau lần gửi bị chặn. */ ?>
                    <input type="hidden" name="xac_nhan_lich" value="1">
                <?php endif; ?>

                <h2 class="acct-modal__title" id="xoa-tieu-de">Xoá tài khoản?</h2>

                <?php if ($error !== null): ?>
                    <p class="acct-modal__err" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <p class="acct-modal__text">
                    Tài khoản và mọi dữ liệu đi kèm sẽ bị xoá vĩnh viễn. Bạn sẽ được đăng xuất ngay.
                </p>

                <label class="sr-only" for="xoa-mk">Mật khẩu hiện tại</label>
                <input class="acct-input" type="password" id="xoa-mk" name="mat_khau"
                       required autocomplete="current-password" placeholder="Mật khẩu hiện tại">

                <label class="acct-check acct-check--small">
                    <input type="checkbox" name="dong_y" value="1" required>
                    <span>Tôi hiểu thao tác này không thể hoàn tác.</span>
                </label>

                <div class="acct-pair">
                    <a class="acct-btn" href="/tai-khoan?muc=ho-so#xoa-tai-khoan">Huỷ</a>
                    <button type="submit" class="acct-btn acct-btn--solid acct-modal__del">
                        <?= $canhBao ? 'Vẫn xoá' : 'Xoá' ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

<?php endif; ?>
