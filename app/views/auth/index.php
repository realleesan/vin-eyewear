<?php

/**
 * auth/index.php — bốn màn của /auth.
 *
 * Dựng theo "Đăng ký Đăng nhập.dc.html" (Claude Design, 12/09/2026):
 *
 *   dinh-danh    Đăng nhập hoặc tạo tài khoản   một ô, nút "Tiếp tục"
 *   mat-khau     Nhập mật khẩu                  "Đăng nhập với … · Đổi"
 *   dang-ky      Tạo tài khoản                  auth/_signup.php
 *   dang-ky-ma   Xác minh mã OTP                auth/_signup-otp.php
 *
 * Màn thứ năm của bản vẽ — "Quên mật khẩu" — có địa chỉ riêng từ trước:
 * /quen-mat-khau, xem auth/forgot.php.
 *
 * CSS: assets/css/auth.css · JS: assets/js/auth.js
 * Trong ngăn kéo: assets/css/components/auth-drawer.css · assets/js/auth-drawer.js
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CHIA ĐÔI MÀN ĐĂNG NHẬP — VÀ CÁI GIÁ PHẢI TRẢ ĐỂ ĐƯỢC PHÉP CHIA
 *
 * Hỏi định danh trước rồi mới hỏi mật khẩu là lối dễ rò rỉ nhất trong cả luồng:
 * chỉ cần bước một trả lời khác nhau cho một địa chỉ CÓ và một địa chỉ KHÔNG có
 * tài khoản, thì bất kỳ ai cũng dò được danh sách khách hàng bằng một vòng lặp.
 *
 * Nên AuthController::identify() kiểm ĐÚNG HÌNH DẠNG chuỗi rồi đi tiếp, không
 * tra bảng nào cả. Mọi chuỗi hợp lệ đều sang màn mật khẩu, và câu "Thông tin
 * đăng nhập không đúng" vẫn nằm nguyên ở màn ấy — BR-UC.USER.02-03 giữ nguyên.
 *
 * ⚠ ĐỪNG thêm một phép tra ở màn một để hiện câu "Số này chưa có tài khoản, tạo
 * mới nhé?". Nó đúng là thân thiện hơn, và nó đúng là thứ luật trên cấm.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CHỖ CỐ Ý KHÁC BẢN THIẾT KẾ — VÀ VÌ SAO
 *
 * 1. KHÔNG CÓ DẢI TAB "ĐĂNG NHẬP | ĐĂNG KÝ" ở đầu tấm. Dải viên thuốc trong
 *    file thiết kế là bộ CHUYỂN MÀN của khung vẽ (nhãn "Bước" đứng trước nó),
 *    không phải một thành phần giao diện. Lối sang màn đăng ký là dòng "Chưa có
 *    tài khoản? Đăng ký" ở cuối màn một, đúng như bản vẽ.
 *
 * 2. NÚT GOOGLE CHỈ SỐNG KHI ĐÃ CẤU HÌNH. Chưa điền GOOGLE_CLIENT_ID/SECRET thì
 *    nó là nút xám "Sắp có" — GoogleAuth::isConfigured() quyết. Một nút bấm được
 *    mà ra trang lỗi của Google còn tệ hơn nút không bấm được, vì khách không
 *    biết lỗi ở phía họ hay phía site.
 *
 * 3. Ô "GHI NHỚ ĐĂNG NHẬP" ĐỨNG TRƯỚC NÚT TRONG MÃ NGUỒN. Trên màn hình nó nằm
 *    dưới nút (CSS `order`). Giữ đúng thứ tự ấy trong HTML thì người dùng bàn
 *    phím phải Tab QUA nút gửi mới tới được ô tick — tức là gặp nút gửi trước
 *    khi kịp chọn có ghi nhớ đăng nhập hay không.
 */

$old        = $old ?? [];
$errors     = $errors ?? [];
$signup     = $signup ?? [];
$buoc       = (string) ($buoc ?? 'dinh-danh');
$dinhDanh   = trim((string) ($dinhDanh ?? ''));

/* Hai liên kết đổi màn phải MANG THEO đích đang dở. Khách bị giỏ hàng đá về
   /auth?redirect=/thanh-toan rồi bấm "Đăng ký" mà mất tham số ấy thì đăng ký
   xong bị thả về trang chủ, không quay lại được việc đang làm —
   BR-UC.USER.01-09 nói rõ phải quay lại. */
$giuDich   = ($redirectRaw ?? '') !== '' ? 'redirect=' . rawurlencode($redirectRaw) : '';
$urlLogin  = '/auth' . ($giuDich !== '' ? '?' . $giuDich : '');
$urlSignup = '/auth?tab=dang-ky' . ($giuDich !== '' ? '&' . $giuDich : '');

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * LỖI THEO TỪNG Ô — DỰNG Ở ĐÂY, DÙNG CHO CẢ BỐN MÀN
 *
 * Bốn màn không bao giờ hiện cùng lúc nên chúng chia nhau một mảng $errors và
 * một khoá phiên ('_auth_errors'). Ba cái đóng gói dưới đây đi kèm nhau và được
 * truyền sang auth/_signup.php qua partial(), để các màn không trôi thành mấy
 * cách vẽ lỗi khác nhau.
 *
 * Màn ĐĂNG NHẬP chỉ dùng chúng cho EF-01…EF-04 (những lỗi nhìn chuỗi đã gõ là
 * biết). EF-05…EF-07 cố tình KHÔNG gắn vào ô nào — xem BR-UC.USER.02-03 và khối
 * chú thích trong AuthController::login().
 */

/** Ô này có lỗi không. */
$hong = static fn (string $field): bool =>
    isset($errors[$field]) && $errors[$field] !== '' && $errors[$field] !== [];

/**
 * In dòng lỗi của một trường, hoặc không in gì.
 *
 * Giá trị trong $errors thường là một chuỗi, nhưng CÓ THỂ là mảng
 * ['msg', 'href', 'text'] khi câu báo phải kèm một liên kết — EF-10 của màn
 * đăng ký đòi câu "Số điện thoại này đã được đăng ký" đi cùng link Đăng nhập.
 * Xem AuthController::loiCoLink().
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

/* Tiêu đề và dòng dẫn của từng màn — gom một chỗ để bốn nhánh bên dưới chỉ còn
   phần KHÁC nhau thật sự là form. */
/* Cột thứ ba là TÊN RÚT GỌN cho thanh đầu ngăn kéo. Thanh ấy rộng chưa tới
   nửa tấm, mà tiêu đề đầy đủ của màn một dài 28 ký tự — in nguyên vào đó là
   tràn hoặc cắt cụt. Trang /auth mở bằng đường dẫn thường không có thanh nào
   nên thuộc tính này vô hại ở đó; xem datTenMan() trong assets/js/auth-drawer.js. */
[$tuaDe, $dongDan, $tuaNgan] = match ($buoc) {
    'mat-khau'   => ['Nhập mật khẩu', '', 'Đăng nhập'],
    'dang-ky'    => ['Tạo tài khoản', 'Hoàn tất thông tin để theo dõi đơn hàng và lịch hẹn của bạn.', 'Đăng ký'],
    'dang-ky-ma' => ['Xác minh mã OTP', '', 'Xác minh'],
    default      => ['Đăng nhập hoặc tạo tài khoản', 'Nhập email hoặc số điện thoại để tiếp tục.', 'Đăng nhập'],
};

/* Chuỗi khách gõ lần trước (controller cất trong $_SESSION['_old_auth']) —
   điền lại để họ không phải gõ hai lần sau một lượt sai. */
$dinhDanhCu = (string) ($old['email'] ?? $dinhDanh);
?>

<section class="authwrap">
    <div class="authcard">

        <?php
        /*
         * CỘT ẢNH THƯƠNG HIỆU ĐÃ GỠ (12/09/2026).
         *
         * Thẻ này từng chia hai: ảnh chiến dịch bên trái, form bên phải. Bản
         * thiết kế "Đăng ký Đăng nhập.dc.html" vẽ MỘT CỘT ở cả khổ máy tính
         * lẫn điện thoại, nên cột ảnh không còn chỗ đứng.
         *
         * ⚠ Nó không chỉ "bị ẩn": auth.css vốn đã có `.authcard__photo {
         * display: none }` từ đợt trước, nhưng luật ấy chỉ giấu TẤM ẢNH — hai
         * dòng chữ phủ lên nó (.authcard__quote, .authcard__sub) vẫn hiện, và
         * chúng nổi lên đầu trang /auth như một câu quảng cáo lạc chỗ. Gỡ hẳn
         * khối markup là cách duy nhất hết chuyện đó.
         */
        ?>

        <!-- ══════════ CỘT FORM ══════════ -->
        <div class="authcard__panel">

            <div class="authhead">
                <?php /* id="authovTitle" — ngăn kéo trỏ aria-labelledby vào
                         đây. Trên trang /auth thường thì id ấy vô hại. */ ?>
                <h1 class="authhead__title" id="authovTitle"
                    data-short="<?= e($tuaNgan) ?>"><?= e($tuaDe) ?></h1>

                <?php if ($buoc === 'mat-khau'): ?>
                    <?php
                    /* "Đăng nhập với <định danh> · Đổi" — bản thiết kế đặt lối
                       quay lại ngay trong dòng dẫn, không phải một mũi tên ở
                       góc. Liên kết trỏ về /auth (màn một) và mang theo đích
                       đang dở như mọi liên kết khác trong tấm này. */
                    ?>
                    <p class="authhead__lead">
                        Đăng nhập với <span class="authwho"><?= e($dinhDanhCu) ?></span>
                        · <a href="<?= e($urlLogin) ?>">Đổi</a>
                    </p>
                <?php elseif ($buoc === 'dang-ky-ma'): ?>
                    <p class="authhead__lead">
                        Mã gồm <?= Otp::LENGTH ?> số đã được gửi đến
                        <span class="authwho"><?= e((string) ($signup['where'] ?? '')) ?></span>.
                    </p>
                <?php elseif ($dongDan !== ''): ?>
                    <p class="authhead__lead"><?= e($dongDan) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($success !== null): ?>
                <p class="authflash authflash--ok" role="status"><?= e($success) ?></p>
            <?php endif; ?>
            <?php if ($error !== null): ?>
                <p class="authflash authflash--err" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <?php
            /*
             * ĐỨNG NHẦM CỔNG — KHÔNG PHẢI GÕ SAI MẬT KHẨU.
             *
             * Khối riêng chứ không dùng dải đỏ .authflash--err ở trên, vì hai
             * chuyện khác hẳn nhau: người này gõ ĐÚNG mật khẩu, chỉ là tài
             * khoản của họ thuộc khu quản trị. Một dòng chữ đỏ sẽ đẩy họ đi
             * kiểm lại mật khẩu — đúng cái việc vô ích duy nhất ở đây.
             *
             * Và liên kết phải là LIÊN KẾT BẤM ĐƯỢC. Bản trước nhét
             * "/quan-tri/dang-nhap" vào giữa câu chữ, người đọc phải tự bôi
             * đen chép sang thanh địa chỉ; trên điện thoại thì gần như không
             * chép nổi.
             */
            ?>
            <?php if (!empty($staffGate)): ?>
                <div class="authgate" role="alert">
                    <span class="authgate__icon" aria-hidden="true"><?= icon('shield', '', 18) ?></span>

                    <div>
                        <p class="authgate__title">Đây là tài khoản nội bộ</p>
                        <p class="authgate__text">
                            Tài khoản nhân viên và quản trị đăng nhập ở cổng riêng,
                            không dùng chung với tài khoản mua hàng.
                        </p>
                        <a class="authgate__link" href="/quan-tri/dang-nhap">
                            Tới cổng quản trị
                            <?= icon('arrow-right', 'authgate__arrow', 15) ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($buoc === 'dinh-danh'): ?>

                <!-- ══════════ 1. NHẬP ĐỊNH DANH ══════════ -->
                <?php
                /*
                 * type="text" chứ KHÔNG phải type="email": ô này nhận cả số
                 * điện thoại, mà trình duyệt sẽ chặn "0912345678" ngay tại chỗ
                 * nếu để type="email", kèm thông báo khó hiểu. Việc kiểm định
                 * dạng do máy chủ làm — xem AuthController::loiDinhDanh().
                 *
                 * autocomplete="username" là giá trị đúng cho một ô nhận nhiều
                 * dạng định danh; để "email" thì trình quản lý mật khẩu không
                 * gợi ý mục đã lưu bằng số điện thoại.
                 */
                ?>
                <form class="authform" method="post" action="/auth/tiep-tuc">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                    <label class="authfield">
                        <span class="authfield__label">Số điện thoại hoặc email</span>
                        <input class="authfield__input<?= $xau('email') ?>" type="text"
                               name="email" required autocomplete="username"
                               inputmode="email" autofocus
                               placeholder="Số điện thoại / Email"
                               value="<?= e($dinhDanhCu) ?>">
                        <?php /* EF-01, EF-03, EF-04 — cả ba đều nói về ô này. */ ?>
                        <?php $loi('email'); ?>
                    </label>

                    <button type="submit" class="authbtn authbtn--primary">Tiếp tục</button>
                </form>

            <?php elseif ($buoc === 'mat-khau'): ?>

                <!-- ══════════ 2. NHẬP MẬT KHẨU ══════════ -->
                <form class="authform" method="post" action="/auth/dang-nhap">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                    <?php
                    /* Ô ẨN mang định danh sang cùng cú gửi.
                       login() vẫn đọc $_POST['email'] y như khi màn này còn là
                       một form hai ô — không có đường nào cho một cú POST
                       thiếu định danh lọt vào, dù phiên có hết hạn giữa chừng.
                       Sửa được ô ẩn cũng không tới đâu: nó chỉ là chuỗi đăng
                       nhập, và mật khẩu vẫn phải khớp. */
                    ?>
                    <input type="hidden" name="email" value="<?= e($dinhDanhCu) ?>">

                    <label class="authfield">
                        <span class="authfield__label">Mật khẩu</span>
                        <?php partial('auth/_password', [
                            'pw_name'      => 'password',
                            'pw_auto'      => 'current-password',
                            'pw_holder'    => 'Nhập mật khẩu',
                            'pw_required'  => true,
                            'pw_autofocus' => true,
                            'pw_err'       => $hong('password'),
                        ]); ?>
                        <?php /* EF-02, và CHỈ EF-02: "sai mật khẩu" (EF-07)
                                 không được gắn vào đây — gắn vào là đã nói ô
                                 định danh đúng, tức là địa chỉ ấy có tài khoản.
                                 Xem BR-UC.USER.02-03. */ ?>
                        <?php $loi('password'); ?>
                    </label>

                    <!-- Ô tick đứng TRƯỚC nút trong HTML, CSS đẩy nó xuống dưới —
                         xem ghi chú số 3 ở đầu file. -->
                    <label class="authcheck">
                        <input type="checkbox" name="remember" value="1"
                               <?= !empty($old['remember']) ? 'checked' : '' ?>>
                        <span class="authcheck__box" aria-hidden="true">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 12.5l5.5 5.5L20 7"></path>
                            </svg>
                        </span>
                        <span class="authcheck__text">Ghi nhớ đăng nhập</span>
                    </label>

                    <button type="submit" class="authbtn authbtn--primary">Đăng nhập</button>
                </form>

                <?php /* "Quên mật khẩu?" — bản thiết kế đặt nó CĂN GIỮA ngay
                         dưới nút, không phải cạnh nhãn ô. */ ?>
                <a class="authforgot" href="/quen-mat-khau">Quên mật khẩu?</a>

            <?php elseif ($buoc === 'dang-ky-ma'): ?>

                <!-- ══════════ 4. XÁC MINH MÃ ══════════ -->
                <?php partial('auth/_signup-otp', [
                    'signup'   => $signup,
                    'errors'   => $errors,
                    'redirect' => $redirect,
                    'loi'      => $loi,
                ]); ?>

            <?php else: ?>

                <!-- ══════════ 3. TẠO TÀI KHOẢN ══════════ -->
                <?php partial('auth/_signup', [
                    'old'      => $old,
                    'errors'   => $errors,
                    'redirect' => $redirect,
                    'dinhDanh' => $dinhDanh,
                    // Ba cái đóng gói dựng ở đầu file này — xem khối chú thích ở đó.
                    'hong'     => $hong,
                    'loi'      => $loi,
                    'xau'      => $xau,
                ]); ?>

            <?php endif; ?>

            <?php
            /*
             * VẠCH "HOẶC" VÀ NÚT GOOGLE — CHỈ Ở MÀN MỘT.
             *
             * Bản thiết kế vẽ chúng đúng một lần, ở màn nhập định danh, và đó
             * là chỗ duy nhất chúng có nghĩa: màn mật khẩu là của một người đã
             * chọn xong cách đăng nhập, còn hai màn đăng ký thì đã đi được nửa
             * đường bằng số điện thoại — mời họ rẽ sang Google ở đó là bỏ dở
             * mọi thứ vừa gõ.
             */
            ?>
            <?php if ($buoc === 'dinh-danh'): ?>

                <div class="author" aria-hidden="true">
                    <span class="author__line"></span>
                    <span class="author__word">Hoặc</span>
                    <span class="author__line"></span>
                </div>

                <?php
                /*
                 * NÚT GOOGLE CHỈ SỐNG KHI ĐÃ CẤU HÌNH — xem ghi chú 2 đầu file.
                 *
                 * Là thẻ <a> (GET) vì bước này KHÔNG ĐỔI GÌ CẢ: nó chỉ sinh một
                 * chuỗi `state` rồi chuyển hướng. Thứ chống giả mạo của luồng
                 * OAuth là chính `state` ấy — lưu trong phiên, Google trả lại
                 * nguyên văn ở bước sau, và GoogleAuth::exchange() so bằng
                 * hash_equals.
                 *
                 * Đăng ký bằng Google KHÔNG tạo tài khoản ngay khi quay về: nó
                 * dẫn sang màn "Hoàn tất tạo tài khoản" (auth/google-signup.php),
                 * nơi có ô tick Điều khoản của chính nó — BR-UC.USER.01-05.
                 */
                $googleOn = GoogleAuth::isConfigured();
                $urlGoogle = '/auth/google'
                    . ($redirect !== '' ? '?redirect=' . rawurlencode($redirect) : '');
                ?>
                <?php if ($googleOn): ?>
                <a class="authbtn authbtn--google" href="<?= e($urlGoogle) ?>" rel="nofollow">
                    <?php partial('auth/_google-icon'); ?>
                    Tiếp tục với Google
                </a>
                <?php else: ?>
                <button type="button" class="authbtn authbtn--google" disabled>
                    <?php partial('auth/_google-icon'); ?>
                    Tiếp tục với Google
                    <span class="authbtn__soon">Sắp có</span>
                </button>
                <?php endif; ?>

            <?php endif; ?>

            <?php
            /*
             * ĐỒNG Ý NGẦM — CHỈ Ở MÀN MỘT.
             *
             * Người đã có tài khoản thì đã tick lúc đăng ký; bắt tick lại mỗi
             * lần vào là thêm ma sát mà không thêm giá trị pháp lý nào.
             *
             * Hai màn ĐĂNG KÝ thì KHÔNG dùng câu này: BR-UC.USER.01-05 đòi một
             * hành vi đồng ý TƯỜNG MINH, nên ở đó có đúng một ô tick thật
             * (auth/_signup.php). Một dòng chữ "bằng việc tạo tài khoản, bạn
             * đồng ý…" đứng cạnh một ô tick nói cùng chuyện chỉ làm người đọc
             * hoang mang xem cái nào mới tính.
             *
             * Vế "Điều khoản dịch vụ" chỉ hiện khi văn bản đã tồn tại. Trước đây
             * nó trỏ cứng tới /chinh-sach#dieu-khoan — một neo KHÔNG có trong
             * config/policy.php, nên bấm vào chỉ nhảy lên đầu trang. Xem
             * config/auth.php.
             */
            $consent  = (array) config('auth.consent', []);
            $termsUrl = (string) ($consent['terms_url'] ?? '');
            ?>
            <?php if ($buoc === 'dinh-danh'): ?>
            <p class="authnote">
                Bằng việc đăng nhập, bạn đồng ý với
                <?php if ($termsUrl !== ''): ?>
                    <a href="<?= e($termsUrl) ?>">Điều khoản dịch vụ</a> và
                <?php endif; ?>
                <a href="<?= e((string) ($consent['privacy_url'] ?? '/chinh-sach#bao-mat')) ?>">Chính sách bảo mật</a>
                của Vin Eyewear.
            </p>
            <?php endif; ?>

            <?php /* Lối sang màn kia. Màn nhập mã có đường lùi riêng ("Sửa lại
                     thông tin") nên không in thêm ở đây — hai lối quay lại cạnh
                     nhau thì khách phải đoán cái nào giữ được thứ đã gõ. */ ?>
            <?php if ($buoc === 'dinh-danh'): ?>
                <p class="authalt">Chưa có tài khoản? <a href="<?= e($urlSignup) ?>">Đăng ký</a></p>
            <?php elseif ($buoc === 'dang-ky'): ?>
                <p class="authalt">Đã có tài khoản? <a href="<?= e($urlLogin) ?>">Đăng nhập</a></p>
            <?php endif; ?>

        </div>
    </div>
</section>
