<?php

/**
 * auth/_signup.php — màn "Tạo tài khoản" (/auth?tab=dang-ky).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA Ô, RỒI MỘT MÀN NHẬP MÃ — theo "Đăng ký Đăng nhập.dc.html" (Claude Design,
 * 12/09/2026). Bản vẽ đặt đúng ba ô trên màn này:
 *
 *     Họ và tên * · Số điện thoại * · Mật khẩu *
 *     nút "Tạo tài khoản" -> màn nhập mã (auth/_signup-otp.php)
 *
 * Vạch "HOẶC", nút Google và dòng "Đã có tài khoản?" nằm ở auth/index.php vì
 * màn đăng nhập dùng chung.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA THỨ CỦA BẢN CŨ KHÔNG CÒN Ở ĐÂY — VÀ KHÔNG THỨ NÀO BỊ VỨT ĐI
 *
 * 1. Ô "XÁC NHẬN MẬT KHẨU" gỡ hẳn. Thay chỗ nó là nút "Hiện" ngay trong ô mật
 *    khẩu: khách ĐỌC được chuỗi mình vừa gõ, thay vì gõ mù hai lần rồi so. Đó
 *    là cách bản thiết kế giải quyết đúng vấn đề mà ô thứ hai sinh ra để giải
 *    quyết, bằng một ô ít hơn.
 *
 * 2. HÀNG "MÃ XÁC MINH" chuyển sang màn riêng. Ở bản cũ nó nằm giữa form, nên
 *    khách phải bấm "Gửi mã" TRƯỚC khi biết mình có gõ hỏng ô nào khác không —
 *    mỗi lượt gõ sai mật khẩu là một tin nhắn mất tiền đã bay đi vô ích. Nay
 *    máy chủ kiểm hết form rồi mới gửi mã; xem AuthController::signupSubmit().
 *
 * 3. Ô "EMAIL (không bắt buộc)" không còn là một ô. Nó nay là một ô ẨN mang
 *    theo chuỗi khách đã gõ ở MÀN MỘT khi chuỗi ấy là một địa chỉ email — gõ
 *    rồi thì không ai phải gõ lại. Không gõ email ở màn một thì tài khoản đơn
 *    giản là chưa có email, đúng như trước: cột ấy vốn không bắt buộc.
 *
 * ⚠ Ô TICK ĐIỀU KHOẢN THÌ Ở LẠI, dù bản thiết kế không vẽ nó.
 * BR-UC.USER.01-05 đòi một hành vi đồng ý TƯỜNG MINH cho mọi cách đăng ký. Một
 * bản vẽ giao diện không bãi bỏ được một ràng buộc nghiệp vụ; bỏ ô ấy là lặng
 * lẽ mở một lối tạo tài khoản không có vết đồng ý nào.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LỖI HIỆN DƯỚI TỪNG Ô, KHÔNG GOM VÀO MỘT DẢI ĐỎ
 *
 * EF-02…EF-12 đều chỉ đích danh một trường và một câu. $errors là mảng
 * khoá-theo-tên-trường do AuthController::signupSubmit() dựng; dải đỏ ở đầu
 * thẻ chỉ còn dành cho lỗi KHÔNG thuộc ô nào: EF-01 (Google) và EF-13.
 *
 * Nhận qua partial(): $old, $errors, $redirect, $dinhDanh, và ba cái đóng gói
 * vẽ lỗi $hong/$loi/$xau — xem ngay dưới.
 */

$old      = $old      ?? [];
$errors   = $errors   ?? [];
$dinhDanh = trim((string) ($dinhDanh ?? ''));

/*
 * BA CÁI ĐÓNG GÓI VẼ LỖI ĐẾN TỪ auth/index.php, không dựng lại ở đây.
 *
 *   $hong('ten_o')  ô này có lỗi không
 *   $loi('ten_o')   in dòng lỗi dưới ô, hoặc không in gì
 *   $xau('ten_o')   lớp tô viền đỏ cho ô
 *
 * Chép một bản thứ hai vào đây thì sớm muộn hai màn cũng vẽ lỗi hai kiểu.
 * Vế `?? fn` chỉ là lưới đỡ cho ngày có ai đó nhúng file này từ chỗ khác.
 */
$hong = $hong ?? static fn (string $f): bool => false;
$loi  = $loi  ?? static function (string $f): void {};
$xau  = $xau  ?? static fn (string $f): string => '';

/*
 * HAI THỨ MANG TỪ MÀN MỘT SANG — và chúng loại trừ nhau.
 *
 * Ô "Số điện thoại hoặc email" ở màn một nhận cả hai dạng, nên chuỗi khách đã
 * gõ hoặc là số (điền sẵn vào ô Số điện thoại bên dưới), hoặc là email (đi
 * theo ô ẩn). looksLikePhone() phân loại theo ĐÚNG cách máy chủ phân loại —
 * xem AuthController::loiDinhDanh().
 */
$idLaSo    = $dinhDanh !== '' && looksLikePhone($dinhDanh);
$soDienSan = (string) ($old['phone'] ?? ($idLaSo ? $dinhDanh : ''));
$emailMang = strtolower((string) ($old['email'] ?? (!$idLaSo ? $dinhDanh : '')));

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

<form class="authform" method="post" action="/auth/dang-ky">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= e($redirect ?? '') ?>">

    <?php
    /*
     * EMAIL ĐI THEO Ô ẨN — xem ghi chú 3 ở đầu file.
     *
     * Ô ẩn thì khách sửa được bằng công cụ nhà phát triển, và điều đó KHÔNG
     * sao: signupErrors() vẫn chạy đủ EF-05 (sai định dạng) và EF-11 (đã có
     * người dùng) trên chuỗi này y như khi nó còn là một ô thật. Ô ẩn ở đây
     * chỉ để khỏi bắt gõ lại, không phải để tin tưởng.
     */
    ?>
    <?php if ($emailMang !== ''): ?>
        <input type="hidden" name="email" value="<?= e($emailMang) ?>">
    <?php endif; ?>

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
    <?php
    /*
     * NHÃN LÀ "SỐ ĐIỆN THOẠI", KHÔNG PHẢI "SỐ ĐIỆN THOẠI HOẶC EMAIL".
     *
     * Bản thiết kế ghi cả hai, nhưng ở ĐÂY thì chỉ số điện thoại là thật: mã
     * xác minh của màn sau đi qua Zalo (Otp::send), và số điện thoại là thứ
     * UserModel::register() bắt buộc phải có vì nó là một trong hai cách đăng
     * nhập. Ghi "hoặc email" rồi từ chối một địa chỉ email là để khách gõ xong
     * mới biết mình gõ nhầm thứ.
     *
     * type="tel" chứ không phải type="number": ô số nuốt mất số 0 dẫn đầu ở
     * vài trình duyệt, mà "0912345678" thì số 0 ấy là một phần của số.
     */
    ?>
    <label class="authfield">
        <span class="authfield__label">Số điện thoại</span>
        <input class="authfield__input<?= $xau('phone') ?>" type="tel" name="phone" required
               autocomplete="tel" inputmode="tel" maxlength="15"
               placeholder="0912345678"
               value="<?= e($soDienSan) ?>">
        <span class="authfield__hint">Dùng số này để đăng nhập và nhận mã xác minh.</span>
        <?php $loi('phone'); ?>
    </label>

    <!-- ══════════ MẬT KHẨU ══════════ -->
    <label class="authfield">
        <span class="authfield__label">Mật khẩu</span>
        <?php partial('auth/_password', [
            'pw_name'     => 'password',
            'pw_auto'     => 'new-password',
            'pw_holder'   => 'Tối thiểu 8 ký tự',
            'pw_min'      => 8,
            'pw_required' => true,
            'pw_err'      => ($errors['password'] ?? '') !== '',
        ]); ?>
        <?php /* MỘT DÒNG THAY CHO DANH SÁCH NĂM DÒNG.

                 auth/_password-rules.php (bản chấm xanh từng dòng khi gõ) vẫn
                 dùng ở hai màn đặt lại mật khẩu, nơi khách tới thẳng để đặt mật
                 khẩu nên bản chi tiết là thứ đầu tiên họ cần đọc.

                 Nhưng KHÔNG BỎ TRẮNG: passwordProblem() trong core/helpers.php
                 vẫn từ chối đúng năm điều kiện ấy, nên không nói gì thì khách
                 chỉ biết luật sau khi đã bị từ chối một lần. Câu này phải KHỚP
                 hàm đó — sửa hàm thì sửa cả đây. */ ?>
        <span class="authfield__hint">
            8–32 ký tự, có chữ hoa, chữ thường, chữ số và ký tự đặc biệt.
        </span>
        <?php $loi('password'); ?>
    </label>

    <?php
    /*
     * Ô "NHẬN THÔNG TIN ƯU ĐÃI" — CHỈ HIỆN KHI CÓ CHỖ ĐỂ GHI.
     *
     * Bản thiết kế vẽ ô này. Nó ghi vào bảng `newsletter_subscribers`, mà bảng
     * ấy khoá theo EMAIL — không có email thì cú tick không đi đâu cả.
     *
     * Nên nó chỉ hiện khi khách đã gõ một email ở màn một. Hiện nó mọi lúc rồi
     * lặng lẽ không làm gì là hứa với khách một thứ không xảy ra: họ tick,
     * đóng trang, và tin rằng mình đã đăng ký nhận tin.
     */
    ?>
    <?php if ($emailMang !== ''): ?>
        <label class="authcheck">
            <input type="checkbox" name="tin_tuc" value="1"
                   <?= !empty($old['tin_tuc']) ? 'checked' : '' ?>>
            <span class="authcheck__box" aria-hidden="true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12.5l5.5 5.5L20 7"></path>
                </svg>
            </span>
            <span class="authcheck__text">
                Nhận thông tin ưu đãi và bộ sưu tập mới qua <?= e($emailMang) ?>.
            </span>
        </label>
    <?php endif; ?>

    <?php
    /*
     * Ô ĐỒNG Ý — BR-UC.USER.01-05.
     *
     * `required` là lớp thứ nhất, trình duyệt tự chặn. Lớp thật nằm ở máy chủ:
     * signupErrors() kiểm lại trước khi gửi mã, vì tắt JavaScript hay gọi thẳng
     * POST /auth/dang-ky đều bỏ qua được thuộc tính này.
     *
     * Ô này CHỈ thuộc về form này. Đăng ký bằng Google có màn "Hoàn tất tạo tài
     * khoản" riêng với ô tick của chính nó (auth/google-signup.php). Hai cách,
     * hai ô tick.
     */
    ?>
    <?php /* Ô tick và câu báo của nó nằm trong MỘT khối riêng: .authform giãn
             các phần tử con, mà một câu lỗi cách ô nó nói tới cả quãng giãn ấy
             thì đọc như đang nói về nút bên dưới. */ ?>
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

    <button type="submit" class="authbtn authbtn--primary">Tạo tài khoản</button>
</form>
