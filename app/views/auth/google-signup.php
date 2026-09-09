<?php

/**
 * auth/google-signup.php — "Hoàn tất tạo tài khoản" (/auth/dang-ky/google).
 *
 * Chặng 3 của cách đăng ký bằng Google: khách đã chọn xong tài khoản ở phía
 * Google, chưa có tài khoản Vin Eyewear nào, và đây là chỗ tài khoản ấy ra
 * đời. Xem khối "ĐĂNG KÝ BẰNG GOOGLE — MỘT CÁCH RIÊNG, BA CHẶNG" trong
 * AuthController.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO LÀ MỘT MÀN RIÊNG, KHÔNG PHẢI MỘT TRẠNG THÁI CỦA auth/index.php
 *
 * Thẻ hai cột của màn đăng nhập/đăng ký có ảnh thương hiệu bên trái, hai liên
 * kết đổi tab ở cuối, và nút "Tiếp tục với Google" ở giữa. Không thứ nào trong
 * ba thứ ấy có nghĩa ở đây: khách đang ở GIỮA một luồng, đi tiếp hoặc bỏ dở,
 * chứ không đứng trước một ngã ba để chọn cách đăng ký. Một màn hẹp, một cột,
 * một việc.
 *
 * Vẫn nạp auth.css và dùng nguyên bộ lớp .authfield/.authbtn/.authcheck của
 * màn kia — cùng ngôn ngữ hình ảnh, chỉ khác khung.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BỐN Ô, HAI Ô BẮT BUỘC
 *
 *   Email             điền sẵn từ Google, KHOÁ (disabled + không có `name`).
 *                     Nó là thứ nối tài khoản này với Google, và máy chủ lấy
 *                     email từ PHIÊN chứ không từ form — xem ghi chú tại chỗ.
 *   Họ và tên         bắt buộc. Điền sẵn tên Google trả về, sửa được.
 *   Số điện thoại     KHÔNG bắt buộc. Google đã bảo chứng danh tính rồi.
 *   Đồng ý Điều khoản bắt buộc — BR-UC.USER.01-05.
 *
 * KHÔNG CÓ Ô MẬT KHẨU và không có ô mã xác minh: đó là hai thứ của cách đăng
 * ký bằng số điện thoại, và trộn chúng vào đây là gộp lại đúng hai cách vừa
 * tách ra.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$old    = $old ?? [];
$errors = $errors ?? [];

$emailGoogle = (string) ($pending['email'] ?? '');

/** Ô này có lỗi không. */
$hong = static fn (string $field): bool =>
    isset($errors[$field]) && $errors[$field] !== '' && $errors[$field] !== [];

/** Câu lỗi dưới ô — nhận cả dạng chuỗi lẫn dạng có kèm liên kết (loiCoLink). */
$loi = static function (string $field) use ($errors, $hong): void {
    if (!$hong($field)) {
        return;
    }

    $v = $errors[$field];
    ?>
    <span class="authfield__err" role="alert">
        <?php if (is_array($v)): ?>
            <?= e((string) ($v['msg'] ?? '')) ?>
            <a href="<?= e((string) ($v['href'] ?? '/auth')) ?>"><?= e((string) ($v['text'] ?? '')) ?></a>
        <?php else: ?>
            <?= e((string) $v) ?>
        <?php endif; ?>
    </span>
<?php };

$xau = static fn (string $field): string => $hong($field) ? ' is-err' : '';

/* Ô họ tên: ưu tiên thứ khách vừa gõ (quay về vì một ô khác sai), rồi mới tới
   tên Google trả về. Không có cái nào thì để trống — đoán bừa một cái tên là
   khách bấm "Đăng ký" mà không đọc, rồi mang cái tên ấy đi suốt. */
$tenSan = (string) ($old['full_name'] ?? ($pending['name'] ?? ''));

$consent  = (array) config('auth.consent', []);
$termsUrl = (string) ($consent['terms_url'] ?? '');
?>

<section class="authwrap">
    <div class="authcard authcard--solo">
        <div class="authcard__panel">

            <div class="authhead">
                <h1 class="authhead__title">Hoàn tất tạo tài khoản</h1>
                <p class="authhead__lead">
                    Chỉ còn một bước nữa. Thông tin này giúp cửa hàng liên hệ khi
                    đơn hàng và lịch hẹn của bạn có thay đổi.
                </p>
            </div>

            <?php if (($error ?? null) !== null): ?>
                <p class="authflash authflash--err" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form class="authform" method="post" action="/auth/dang-ky/google/tao">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                <?php
                /*
                 * Ô EMAIL KHOÁ, VÀ KHÔNG MANG `name`.
                 *
                 * `disabled` là lớp cho mắt nhìn; việc ô không có `name` mới là
                 * lớp thật — trình duyệt không gửi nó lên, và googleSignupSubmit()
                 * đọc email từ $_SESSION['_google_pending'] chứ không đọc $_POST.
                 * Nên gỡ `disabled` bằng devtools rồi gõ một địa chỉ khác cũng
                 * không đổi được gì: thứ đi vào cột `email` vẫn là địa chỉ Google
                 * vừa xác nhận, và `email_verified` mới có quyền là 1.
                 */
                ?>
                <label class="authfield">
                    <span class="authfield__label">Email</span>
                    <input class="authfield__input authfield__input--locked" type="email"
                           value="<?= e($emailGoogle) ?>" disabled
                           autocomplete="email">
                    <span class="authfield__hint">
                        <?= $emailGoogle !== ''
                            ? 'Lấy từ tài khoản Google bạn vừa chọn. Đây cũng là email đăng nhập.'
                            : 'Tài khoản Google này không chia sẻ email. Bạn có thể bổ sung sau ở trang Hồ sơ.' ?>
                    </span>
                </label>

                <label class="authfield">
                    <span class="authfield__label">Họ và tên</span>
                    <input class="authfield__input<?= $xau('full_name') ?>" type="text"
                           name="full_name" required maxlength="120" autocomplete="name"
                           placeholder="Nguyễn Văn A"
                           value="<?= e($tenSan) ?>"
                           <?= $tenSan === '' ? 'autofocus' : '' ?>>
                    <?php $loi('full_name'); ?>
                </label>

                <label class="authfield">
                    <span class="authfield__label">
                        Số điện thoại <em class="authfield__opt">(không bắt buộc)</em>
                    </span>
                    <?php /* type="tel" + inputmode: bàn phím điện thoại mở thẳng
                             bàn số. maxlength 15 để lọt cả dạng +84… —
                             normalizePhone() ở máy chủ mới là nơi giữ luật. */ ?>
                    <input class="authfield__input<?= $xau('phone') ?>" type="tel"
                           name="phone" inputmode="tel" maxlength="15" autocomplete="tel"
                           placeholder="0912345678"
                           value="<?= e((string) ($old['phone'] ?? '')) ?>">
                    <span class="authfield__hint">
                        Dùng để cửa hàng gọi xác nhận đơn và nhắc lịch hẹn.
                    </span>
                    <?php $loi('phone'); ?>
                </label>

                <?php
                /*
                 * Ô ĐỒNG Ý — BR-UC.USER.01-05.
                 *
                 * Ô tick THẬT của cách đăng ký này. Trước đây nút "Tiếp tục với
                 * Google" mượn ô tick của form đăng ký bằng số điện thoại
                 * (form="signupform"), nên hai cách dính vào nhau ở đúng chỗ khó
                 * thấy nhất — bỏ ô tick khỏi form kia là lặng lẽ mở một lối tạo
                 * tài khoản không có đồng ý.
                 *
                 * `required` là lớp thứ nhất, trình duyệt tự chặn. Lớp thật nằm
                 * ở googleSignupSubmit(): gọi thẳng POST thì bỏ qua được thuộc
                 * tính này.
                 */
                ?>
                <div class="authagree">
                    <label class="authcheck authcheck--agree">
                        <input type="checkbox" name="dong_y" value="1" required
                               <?= !empty($old['dong_y']) ? 'checked' : '' ?>>
                        <span class="authcheck__box" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 12.5l5.5 5.5L20 7"></path>
                            </svg>
                        </span>
                        <span class="authcheck__text">
                            Tôi đã đọc và đồng ý với
                            <?php if ($termsUrl !== ''): ?>
                                <a href="<?= e($termsUrl) ?>" target="_blank" rel="noopener">Điều khoản dịch vụ</a> và
                            <?php endif; ?>
                            <a href="<?= e((string) ($consent['privacy_url'] ?? '/chinh-sach#bao-mat')) ?>"
                               target="_blank" rel="noopener">Chính sách bảo mật</a>
                            của Vin Eyewear.
                        </span>
                    </label>
                    <?php $loi('dong_y'); ?>
                </div>

                <button type="submit" class="authbtn authbtn--primary">Đăng ký</button>
            </form>

            <?php /* LỐI RÚT LUI. Khách đổi ý giữa chừng thì phải có đường ra
                     thấy được — không có nó thì cách duy nhất là bấm Back, mà
                     Back từ đây rơi vào chính địa chỉ callback của Google. */ ?>
            <p class="authalt">
                Muốn đăng ký bằng số điện thoại?
                <a href="/auth?tab=dang-ky">Quay lại</a>
            </p>
        </div>
    </div>
</section>
