<?php

/**
 * auth/account/ho-so.php — tab "Hồ sơ" (/tai-khoan?muc=ho-so)
 *
 * Hai màn "Profile" và "Edit Profile" của "Ho So Nguoi Dung.dc.html":
 *
 *   HỒ SƠ                               SỬA HỒ SƠ  (?sua-ho-so=1)
 *     họ tên · email · số điện thoại      *Bắt buộc
 *     [ SỬA HỒ SƠ ]                       [ Email            SỬA ]
 *   TÀI KHOẢN LIÊN KẾT                    [ Họ và tên*           ]
 *     [ G  GOOGLE                ⌁ ]      [ Số điện thoại        ]
 *   ĐỔI MẬT KHẨU                          [        LƯU           ]
 *     [ ĐỔI MẬT KHẨU ]
 *   ───────────────
 *   XOÁ TÀI KHOẢN
 *     XOÁ TÀI KHOẢN CỦA TÔI  -> hộp thoại (?xoa=1)
 *
 * KHỐI "MARKETING PREFERENCES" CỦA BẢN THIẾT KẾ KHÔNG DỰNG — theo yêu cầu chủ
 * dự án: hệ thống chưa có chỗ lưu hai công tắc ấy, và một công tắc bấm được mà
 * không lưu gì là lời hứa suông.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỌI TRẠNG THÁI MỞ BẰNG URL, KHÔNG BẰNG JAVASCRIPT
 *
 *     ?sua-ho-so=1            form sửa hồ sơ (thay cả tab, đúng như bản vẽ)
 *     &sua-email=1            mở ô email trong form đó
 *     ?doi-mat-khau=1         mở form đổi mật khẩu
 *     ?go-google=1            hỏi mật khẩu trước khi gỡ liên kết Google
 *     ?xoa=1 (&canh-bao=1)    hộp thoại xoá tài khoản
 *
 * F5 không mất chỗ, và máy chủ gửi lỗi về đúng trạng thái còn mở — xem các
 * redirect trong AuthController.
 */

$email   = trim((string) ($profile['email'] ?? ''));
$daNoi   = !empty($google['linked']);
$googleOn = GoogleAuth::isConfigured();
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
    ?>
    <h1 class="acct-title">Sửa hồ sơ</h1>

    <form class="acct-edit" method="post" action="/tai-khoan/ho-so">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <p class="acct-edit__req">*Bắt buộc</p>

        <div class="acct-boxes">
            <?php if (!$suaEmail): ?>
                <div class="acct-box acct-box--row">
                    <span class="acct-box__main">
                        <span class="acct-box__label">Email</span>
                        <span class="acct-box__value"><?= e($email) ?></span>
                    </span>
                    <a class="acct-box__act" href="/tai-khoan?muc=ho-so&amp;sua-ho-so=1&amp;sua-email=1">Sửa</a>
                    <input type="hidden" name="email" value="<?= e($email) ?>">
                </div>
            <?php else: ?>
                <label class="acct-box">
                    <span class="acct-box__label">Email</span>
                    <?php /* Không bắt buộc: tài khoản đăng ký bằng số điện thoại
                             ra đời không có email. Địa chỉ gõ ở đây CHƯA XÁC MINH
                             — updateEmail() đặt email_verified về 0. */ ?>
                    <input class="acct-box__input" type="email" name="email" maxlength="255"
                           autocomplete="email" placeholder="ban@vidu.com" value="<?= e($email) ?>">
                </label>
            <?php endif; ?>

            <label class="acct-box">
                <span class="acct-box__label">Họ và tên*</span>
                <input class="acct-box__input" type="text" name="full_name" required
                       maxlength="120" autocomplete="name"
                       value="<?= e($profile['full_name'] ?? '') ?>">
            </label>

            <label class="acct-box">
                <span class="acct-box__label">Số điện thoại</span>
                <input class="acct-box__input" type="tel" name="phone" autocomplete="tel"
                       value="<?= e($profile['phone'] ?? '') ?>">
            </label>
        </div>

        <button type="submit" class="acct-btn acct-btn--solid acct-edit__save">Lưu</button>
    </form>

<?php else: ?>

    <h1 class="acct-title">Hồ sơ</h1>

    <section class="acct-sec acct-sec--first" id="thong-tin">
        <?php partial('auth/account/_toi', ['profile' => $profile]); ?>
        <a class="acct-btn acct-sec__btn" href="/tai-khoan?muc=ho-so&amp;sua-ho-so=1">Sửa hồ sơ</a>
    </section>

    <?php
    /* ═════════════════════════ TÀI KHOẢN LIÊN KẾT ═════════════════════════
       Chỗ nối Google mà AF-03 hứa: đăng nhập Google bằng email trùng tài khoản
       có sẵn thì câu báo bảo khách "đăng nhập rồi liên kết" — và đây là chỗ ấy.

       KHÔNG IN EMAIL GOOGLE như bản thiết kế: bảng `users` chỉ giữ google_id
       (chuỗi `sub`), và email Google chưa chắc trùng email tài khoản. Xem
       UserModel::googleLink(). */
    $moGoGoogle = $daNoi && isset($_GET['go-google']);
    ?>
    <section class="acct-sec" id="tai-khoan-google" aria-labelledby="hs-google">
        <h2 class="acct-label" id="hs-google">Tài khoản liên kết</h2>
        <p class="acct-sec__text">Liên kết tài khoản mạng xã hội để đăng nhập dễ và nhanh hơn.</p>

        <div class="acct-conn">
            <span class="acct-conn__who">
                <?php partial('auth/_google-icon'); ?>
                <span class="acct-conn__text">
                    <span class="acct-conn__name">Google</span>
                    <span class="acct-conn__sub">
                        <?= $daNoi ? 'Đã liên kết' : ($googleOn ? 'Chưa liên kết' : 'Sắp có') ?>
                    </span>
                </span>
            </span>

            <?php if ($daNoi && !$moGoGoogle): ?>
                <a class="acct-conn__act" href="/tai-khoan?muc=ho-so&amp;go-google=1#tai-khoan-google"
                   aria-label="Gỡ liên kết Google" title="Gỡ liên kết">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" aria-hidden="true" focusable="false">
                        <path d="M10 14a4 4 0 0 0 5.6.4l2.8-2.8a4 4 0 0 0-5.6-5.6l-1 1"></path>
                        <path d="M14 10a4 4 0 0 0-5.6-.4l-2.8 2.8a4 4 0 0 0 5.6 5.6l1-1"></path>
                        <line x1="4" y1="4" x2="20" y2="20"></line>
                    </svg>
                </a>
            <?php elseif (!$daNoi && $googleOn): ?>
                <?php /* FORM POST, không phải <a>: để /tai-khoan/google mở bằng GET
                         thì một <img> ở trang khác cũng đẩy được khách sang
                         Google và nối nhầm một tài khoản. */ ?>
                <form method="post" action="/tai-khoan/google">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="acct-conn__link">Liên kết</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($moGoGoogle): ?>
            <?php /* HỎI MẬT KHẨU: gỡ Google là tháo một lối vào, nên câu hỏi thật
                     là "gỡ xong còn lối nào không". Gõ đúng được mật khẩu nghĩa là
                     khách còn đăng nhập được. Lý do đầy đủ ở
                     UserModel::unlinkGoogle(). */ ?>
            <form class="acct-stack" method="post" action="/tai-khoan/google/go">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <p class="acct-muted">
                    Sau khi gỡ, bạn chỉ còn đăng nhập bằng số điện thoại hoặc email và mật
                    khẩu. Nhập mật khẩu hiện tại để xác nhận.
                </p>
                <label class="sr-only" for="go-google-mk">Mật khẩu hiện tại</label>
                <input class="acct-input" type="password" id="go-google-mk" name="mat_khau"
                       required autocomplete="current-password" placeholder="Mật khẩu hiện tại">
                <div class="acct-pair">
                    <a class="acct-btn" href="/tai-khoan?muc=ho-so#tai-khoan-google">Để nguyên</a>
                    <button type="submit" class="acct-btn acct-btn--solid">Gỡ liên kết</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

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
            <p class="acct-sec__text"><?= e($quyTacMatKhau) ?></p>
            <a class="acct-btn acct-sec__btn" href="/tai-khoan?muc=ho-so&amp;doi-mat-khau=1#doi-mat-khau">Đổi mật khẩu</a>
        <?php else: ?>
            <form class="acct-stack acct-stack--gap" method="post" action="/tai-khoan/mat-khau">
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

                <p class="acct-muted" id="mk-luat">
                    <?= e($quyTacMatKhau) ?> Đổi mật khẩu sẽ đăng xuất mọi thiết bị khác đang ghi nhớ đăng nhập.
                </p>

                <div class="acct-pair acct-pair--gap">
                    <a class="acct-btn" href="/tai-khoan?muc=ho-so#doi-mat-khau">Huỷ</a>
                    <button type="submit" class="acct-btn acct-btn--solid">Cập nhật mật khẩu</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <?php
    /* ═════════════════════════ XOÁ TÀI KHOẢN — UC-01 · FR-TK-17 ═════════════════════════
       Mở bằng ?xoa=1 thành một HỘP THOẠI như bản thiết kế. Khác bản vẽ ở đúng
       một ô: MẬT KHẨU HIỆN TẠI — AuthController::deleteAccount() đòi nó, và một
       thao tác không lùi lại được thì không được chạy chỉ bằng một cú tích.

       Nói rõ chuyện đơn hàng TRƯỚC khi hỏi: "đơn tôi đang đặt thì sao" là nỗi
       lo thật của người sắp bấm. */
    $moXoa   = isset($_GET['xoa']);
    $canhBao = isset($_GET['canh-bao']);
    ?>
    <section class="acct-sec acct-sec--rule" id="xoa-tai-khoan" aria-labelledby="hs-xoa">
        <h2 class="acct-label" id="hs-xoa">Xoá tài khoản</h2>
        <p class="acct-sec__text acct-muted">
            Xoá vĩnh viễn tài khoản cùng hồ sơ và dữ liệu đã lưu. Không thể hoàn tác.
            Đơn hàng đã đặt vẫn được cửa hàng xử lý bình thường.
        </p>
        <a class="acct-link acct-sec__btn" href="/tai-khoan?muc=ho-so&amp;xoa=1#xoa-tai-khoan">Xoá tài khoản của tôi</a>
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
                    Tài khoản và mọi dữ liệu đi kèm sẽ bị xoá vĩnh viễn, và bạn sẽ được đăng xuất
                    ngay. Bạn không đăng nhập được nữa, kể cả bằng Google.
                </p>

                <label class="sr-only" for="xoa-mk">Mật khẩu hiện tại</label>
                <input class="acct-input" type="password" id="xoa-mk" name="mat_khau"
                       required autocomplete="current-password" placeholder="Mật khẩu hiện tại">

                <label class="acct-check">
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
