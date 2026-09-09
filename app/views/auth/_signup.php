<?php

/**
 * auth/_signup.php — màn "Tạo tài khoản" (/auth?tab=dang-ky).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT MÀN, MỘT LƯỢT GỬI — theo UC-USER-01 (mục Giao diện và Luồng sự kiện
 * chính). Bản trước chia luồng thành sáu chặng nối nhau bằng ?buoc= (nhập số →
 * hỏi kênh gửi → chọn kênh → nhập mã → tạo mật khẩu → xong); đặc tả chốt lại
 * chỉ còn MỘT màn hỏi đủ:
 *
 *     Họ tên * · Số điện thoại * · Email (không bắt buộc)
 *     Mật khẩu * · Xác nhận mật khẩu * · ô tick Điều khoản/Chính sách
 *     nút "Đăng ký" · vạch "HOẶC" · "Tiếp tục với Google" · "Đã có tài khoản?"
 *
 * Ba mục cuối nằm ở auth/index.php vì màn đăng nhập dùng chung.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÃ XÁC MINH KHÔNG CÒN LÀ MỘT CHẶNG RIÊNG — NÓ LÀ MỘT HÀNG TRONG FORM NÀY
 *
 * BR-UC.USER.01-02 nói "Không yêu cầu OTP ở Phase 1". Nhưng gỡ hẳn khâu xác
 * minh thì ngày cắm xong Zalo phải dựng lại từ đầu, nên nó ở lại — chỉ đổi chỗ
 * đứng: một hàng "Mã xác minh" ngay dưới ô số điện thoại, kèm nút "Gửi mã" gọi
 * ngầm (assets/js/auth.js) để khách không rời trang và không mất chữ đã gõ.
 *
 * VÀ HÀNG ẤY CHỈ HIỆN KHI MÃ THẬT SỰ GỬI ĐƯỢC. Otp::bypass() đang mở (Zalo OA
 * chưa khai xong) thì cả hàng biến mất và form đúng nguyên văn đặc tả Phase 1.
 * Cắm xong Zalo là nó tự hiện lại, không phải sửa gì ở đây.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LỖI HIỆN DƯỚI TỪNG Ô, KHÔNG GOM VÀO MỘT DẢI ĐỎ
 *
 * EF-02…EF-12 đều chỉ đích danh một trường và một câu. Gom cả vào dải
 * .authflash ở đầu thẻ thì khách phải tự dò xem câu ấy nói về ô nào — mà form
 * này có sáu ô. $errors là mảng khoá-theo-tên-trường do
 * AuthController::signupSubmit() dựng; dải đỏ ở trên chỉ còn dành cho lỗi
 * KHÔNG thuộc ô nào: EF-01 (Google) và EF-13 (lỗi hệ thống).
 *
 * Nhận qua partial(): $signup (mảng của signupView()), $old, $errors.
 */

$signup = $signup ?? [];
$old    = $old    ?? [];
$errors = $errors ?? [];

/** Có bắt xác minh bằng mã không — xem khối chú thích trên. */
$otpOn = !Otp::bypass();

/** Ô này có lỗi không. */
$hong = static fn (string $field): bool =>
    isset($errors[$field]) && $errors[$field] !== '' && $errors[$field] !== [];

/**
 * In dòng lỗi của một trường, hoặc không in gì.
 *
 * Giá trị trong $errors thường là một chuỗi, nhưng CÓ THỂ là mảng
 * ['msg', 'href', 'text'] khi câu báo phải kèm một liên kết — EF-10 đòi câu
 * "Số điện thoại này đã được đăng ký" đi cùng link Đăng nhập, và một địa chỉ
 * nằm giữa câu chữ thuần thì khách phải tự đi tìm. Xem
 * AuthController::loiCoLink().
 */
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

/** Lớp tô viền đỏ cho ô đang có lỗi. */
$xau = static fn (string $field): string => $hong($field) ? ' is-err' : '';

/*
 * VĂN BẢN ĐỒNG Ý — chỉ nói về thứ CÓ THẬT.
 *
 * Trang Điều khoản dịch vụ chưa tồn tại (xem config/auth.php), nên vế đó chỉ
 * hiện khi 'terms_url' đã được điền. Xin đồng ý cho một văn bản không ở đâu cả
 * thì tệ hơn là không xin.
 */
$consent  = (array) config('auth.consent', []);
$termsUrl = (string) ($consent['terms_url'] ?? '');
?>

<?php
/*
 * id="signupform" KHÔNG PHẢI ĐỂ TRANG TRÍ.
 *
 * Nút "Tiếp tục với Google" nằm ngoài form này (nó ở auth/index.php, dùng chung
 * với màn đăng nhập) nhưng phải GỬI ĐI cùng ô tick đồng ý của form này —
 * BR-UC.USER.01-05 bắt cả hai phương thức đăng ký đều phải tick. Thuộc tính
 * form="signupform" trên nút đó nối nó vào đây; xem chú thích tại chỗ.
 */
?>
<form class="authform" id="signupform" method="post" action="/auth/dang-ky"
      data-pw-rules data-pw-focus>
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= e($redirect ?? '') ?>">

    <?php
    /*
     * NÚT MẶC ĐỊNH CỦA FORM — vô hình, và bắt buộc phải có.
     *
     * Bấm Enter trong một ô nhập là kích hoạt nút submit ĐẦU TIÊN theo thứ tự
     * tài liệu. Form này có ba nút submit, và hai trong số đó không phải là
     * "Đăng ký": nút "Gửi mã" (đứng ngay dưới ô số điện thoại) và nút "Tiếp
     * tục với Google" (nối vào form qua form="signupform"). Không có nút này
     * thì khách gõ xong bấm Enter là đi xin mã xác minh, hoặc bị đẩy sang
     * Google — mãi không đăng ký được.
     *
     * tabindex="-1" và aria-hidden để nó không xuất hiện với bàn phím lẫn
     * trình đọc màn hình: nút "Đăng ký" thật ở cuối form mới là nút họ cần
     * gặp. Ẩn bằng .sr-only chứ không phải hidden/display:none — nút bị ẩn
     * hẳn thì trình duyệt không coi nó là nút mặc định nữa.
     */
    ?>
    <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Đăng ký</button>

    <!-- ══════════ HỌ TÊN ══════════ -->
    <label class="authfield">
        <span class="authfield__label">Họ và tên</span>
        <input class="authfield__input<?= $xau('full_name') ?>" type="text" name="full_name"
               required maxlength="120" autocomplete="name" autofocus
               placeholder="Nguyễn Văn A"
               value="<?= e($old['full_name'] ?? '') ?>">
        <?php $loi('full_name'); ?>
    </label>

    <!-- ══════════ SỐ ĐIỆN THOẠI ══════════ -->
    <?php /* type="tel" chứ không phải type="number": ô số nuốt mất số 0 dẫn
             đầu ở vài trình duyệt, mà "0912345678" thì số 0 ấy là một phần
             của số. */ ?>
    <label class="authfield">
        <span class="authfield__label">Số điện thoại</span>
        <input class="authfield__input<?= $xau('phone') ?>" type="tel" name="phone" required
               autocomplete="tel" inputmode="tel" maxlength="15"
               placeholder="0912345678"
               value="<?= e($old['phone'] ?? '') ?>">
        <span class="authfield__hint">Dùng số này để đăng nhập.</span>
        <?php $loi('phone'); ?>
    </label>

    <?php if ($otpOn): ?>
    <!-- ══════════ MÃ XÁC MINH (chỉ khi Zalo đã cắm) ══════════ -->
    <?php
    /*
     * NÚT "GỬI MÃ" LÀ MỘT NÚT SUBMIT THẬT, không phải <button type="button">.
     *
     * Có JavaScript: auth.js chặn cú submit lại, gọi ngầm /auth/dang-ky/gui-ma
     * rồi đếm ngược ngay trên nhãn nút — khách không rời trang.
     *
     * Không có JavaScript: nó submit thật sang chính địa chỉ ấy (formaction),
     * máy chủ gửi mã, cất mọi chữ đã gõ vào phiên rồi trả khách về đúng form
     * này với dữ liệu còn nguyên. Chậm hơn một nhịp tải trang, nhưng không kẹt.
     *
     * formnovalidate: cú bấm này chưa phải lúc kiểm cả form — mật khẩu còn
     * trống là chuyện bình thường ở thời điểm xin mã.
     */
    ?>
    <div class="authfield acode" data-code data-wait="<?= (int) ($signup['wait'] ?? 0) ?>">
        <label class="authfield__label" for="signup-ma">Mã xác minh</label>

        <div class="acode__row">
            <input class="authfield__input acode__input<?= $xau('ma') ?>" id="signup-ma"
                   type="text" name="ma" inputmode="numeric" pattern="[0-9]*"
                   maxlength="<?= Otp::LENGTH ?>" autocomplete="one-time-code"
                   placeholder="<?= str_repeat('•', Otp::LENGTH) ?>">

            <button type="submit" class="acode__send" data-send-code
                    formaction="/auth/dang-ky/gui-ma" formmethod="post" formnovalidate
                    <?= ($signup['wait'] ?? 0) > 0 ? 'disabled' : '' ?>>
                <span data-send-label>Gửi mã</span><span class="acode__num"<?= ($signup['wait'] ?? 0) > 0 ? '' : ' hidden' ?>>
                    (<?= (int) ($signup['wait'] ?? 0) ?>s)</span>
            </button>
        </div>

        <span class="authfield__hint" data-code-note>
            <?= !empty($signup['sent'])
                ? 'Mã đã gửi qua Zalo. Mã có hiệu lực ' . Otp::TTL . ' giây.'
                : 'Bấm "Gửi mã" để nhận mã xác minh qua Zalo.' ?>
        </span>
        <?php $loi('ma'); ?>
    </div>
    <?php endif; ?>

    <!-- ══════════ EMAIL (KHÔNG BẮT BUỘC) ══════════ -->
    <?php /* type="email" ở đây thì hợp lệ: ô này chỉ nhận email, khác ô đăng
             nhập vốn nhận cả số điện thoại. */ ?>
    <label class="authfield">
        <span class="authfield__label">Email <em class="authfield__opt">(không bắt buộc)</em></span>
        <input class="authfield__input<?= $xau('email') ?>" type="email" name="email"
               autocomplete="email" maxlength="255"
               placeholder="ban@vidu.com"
               value="<?= e($old['email'] ?? '') ?>">
        <span class="authfield__hint">
            Dùng để đăng nhập và lấy lại mật khẩu khi bạn đổi số điện thoại.
        </span>
        <?php $loi('email'); ?>
    </label>

    <!-- ══════════ MẬT KHẨU ══════════ -->
    <label class="authfield">
        <span class="authfield__label">Mật khẩu</span>
        <?php partial('auth/_password', [
            'pw_name'     => 'password',
            'pw_auto'     => 'new-password',
            'pw_holder'   => 'Mật khẩu',
            'pw_min'      => 8,
            'pw_required' => true,
            'pw_err'      => ($errors['password'] ?? '') !== '',
        ]); ?>
        <?php $loi('password'); ?>
    </label>

    <!-- ══════════ XÁC NHẬN MẬT KHẨU ══════════ -->
    <?php /* Nhãn hiện rõ ở CẢ HAI ô: một mình ô mật khẩu thì chữ mờ trong ô là
             đủ, nhưng hai ô giống hệt nhau nằm sát nhau mà chữ mờ lại biến mất
             ngay khi gõ ký tự đầu thì không còn gì phân biệt ô trên với ô dưới. */ ?>
    <label class="authfield">
        <span class="authfield__label">Xác nhận mật khẩu</span>
        <?php partial('auth/_password', [
            'pw_name'     => 'password_confirm',
            'pw_auto'     => 'new-password',
            'pw_holder'   => '••••••••',
            'pw_min'      => 8,
            'pw_required' => true,
            'pw_err'      => ($errors['password_confirm'] ?? '') !== '',
        ]); ?>
        <?php $loi('password_confirm'); ?>
    </label>

    <?php /* Năm dòng quy tắc đứng SAU cả hai ô: chúng nói về mật khẩu nói
             chung chứ không riêng ô nào, mà kẹp vào giữa thì trông như chỉ
             ràng buộc ô phía trên. auth.js chấm xanh theo ô ĐẦU TIÊN. */ ?>
    <?php partial('auth/_password-rules'); ?>

    <?php
    /*
     * Ô ĐỒNG Ý — BR-UC.USER.01-05.
     *
     * `required` là lớp thứ nhất, trình duyệt tự chặn. Lớp thật nằm ở máy chủ:
     * signupSubmit() kiểm lại trước khi gọi register(), vì tắt JavaScript hay
     * gọi thẳng POST /auth/dang-ky đều bỏ qua được thuộc tính này.
     *
     * Ô này CŨNG là ô đồng ý của nút "Tiếp tục với Google" — nút đó gửi đi
     * cùng form này qua thuộc tính form="signupform". Vì thế không có ô tick
     * thứ hai ở dưới, và câu chữ nói về "tạo tài khoản" nói chung.
     */
    ?>
    <?php /* Ô tick và câu báo của nó nằm trong MỘT khối riêng: .authform giãn
             các phần tử con 22px, mà một câu lỗi cách ô nó nói tới 22px thì
             đọc như đang nói về nút "Đăng ký" ở dưới. */ ?>
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
        <?php /* Lỗi của ô tick đứng NGAY CẠNH ô tick — EF-12 nói rõ vị trí. */ ?>
        <?php $loi('dong_y'); ?>
    </div>

    <button type="submit" class="authbtn authbtn--primary">Đăng ký</button>
</form>
