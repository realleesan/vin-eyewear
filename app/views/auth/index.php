<?php

/**
 * auth/index.php — đăng nhập & đăng ký (/auth và /auth?tab=dang-ky)
 *
 * Dựng theo "Vin Eyewear Login.dc.html" (Claude Design):
 *
 *   khung rút gọn (logo + hotline | thẻ | chân trang pháp lý)
 *   → thẻ 1060px bo 36px, chia hai: ảnh thương hiệu | form 460px
 *
 * CSS: assets/css/auth.css · JS: assets/js/auth.js
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CHỖ CỐ Ý KHÁC BẢN THIẾT KẾ — VÀ VÌ SAO
 *
 * 1. HAI TAB THÀNH HAI TRẠNG THÁI CỦA CÙNG MỘT THẺ.
 *    Bản cũ có dải tab "Đăng nhập | Tạo tài khoản" ở đầu trang. Bản thiết kế
 *    bỏ hẳn dải đó: trang chỉ có việc đăng nhập, còn đăng ký là một liên kết
 *    nhỏ ở cuối form. Nên /auth?tab=dang-ky nay là form đăng ký dựng trong
 *    ĐÚNG cái thẻ hai cột ấy — bản thiết kế không vẽ trang đăng ký, nhưng nó
 *    định nghĩa đủ nguyên thể (nhãn, ô nhập, nút, vạch ngăn) để dựng ra một
 *    trang cùng ngôn ngữ. Giữ nguyên URL cũ nên mọi liên kết và mọi lệnh
 *    redirect đang có vẫn trỏ đúng chỗ.
 *
 * 2. NÚT GOOGLE CHỈ SỐNG KHI ĐÃ CẤU HÌNH.
 *    Chưa điền GOOGLE_CLIENT_ID/SECRET thì nó là nút xám "Sắp có" —
 *    GoogleAuth::isConfigured() quyết. Đã cấu hình thì nó là thẻ <a> (GET) ở
 *    cả hai tab, chỉ khác tham số mang theo. Ở tab đăng ký nó KHÔNG tạo tài
 *    khoản mà dẫn sang màn "Hoàn tất tạo tài khoản"
 *    (auth/google-signup.php) — hai cách đăng ký, hai form, hai ô tick.
 *
 * 3. Ô "GHI NHỚ ĐĂNG NHẬP" ĐỨNG TRƯỚC NÚT TRONG MÃ NGUỒN.
 *    Bản thiết kế xếp nó SAU nút "Đăng nhập" trên màn hình. Giữ đúng thứ tự
 *    ấy trong HTML thì người dùng bàn phím phải Tab QUA nút gửi mới tới được ô
 *    tick — tức là gặp nút gửi trước khi kịp chọn có ghi nhớ đăng nhập hay
 *    không. Nên HTML để ô tick trước, còn CSS (`order`) đẩy nó xuống dưới nút.
 *    Nhìn giống hệt bản thiết kế, thứ tự Tab thì đúng.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$old        = $old ?? [];
$errors     = $errors ?? [];
$isRegister = $tab === 'dang-ky';
$signup     = $signup ?? [];

/* Hai liên kết đổi tab phải MANG THEO đích đang dở. Khách bị giỏ hàng đá về
   /auth?redirect=/thanh-toan rồi bấm "Đăng ký" mà mất tham số ấy thì đăng ký
   xong bị thả về trang chủ, không quay lại được việc đang làm —
   BR-UC.USER.01-09 nói rõ phải quay lại. */
$giuDich  = ($redirectRaw ?? '') !== '' ? 'redirect=' . rawurlencode($redirectRaw) : '';
$urlLogin = '/auth' . ($giuDich !== '' ? '?' . $giuDich : '');
$urlSignup = '/auth?tab=dang-ky' . ($giuDich !== '' ? '&' . $giuDich : '');

/*
 * ─────────────────────────────────────────────────────────────────────────────
 * LỖI THEO TỪNG Ô — DỰNG Ở ĐÂY, DÙNG CHO CẢ HAI TAB
 *
 * Hai màn không bao giờ hiện cùng lúc nên chúng chia nhau một mảng $errors và
 * một khoá phiên ('_auth_errors'). Ba cái đóng gói dưới đây đi kèm nhau và
 * được truyền sang auth/_signup.php qua partial(), để hai màn không trôi thành
 * hai cách vẽ lỗi khác nhau.
 *
 * Màn ĐĂNG NHẬP chỉ dùng chúng cho EF-01…EF-04 (những lỗi nhìn chuỗi đã gõ là
 * biết). EF-05…EF-07 cố tình KHÔNG gắn vào ô nào — xem BR-UC.USER.02-03 và
 * khối chú thích trong AuthController::login().
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
?>

<section class="authwrap">
    <div class="authcard">

        <!-- ══════════ CỘT ẢNH THƯƠNG HIỆU ══════════ -->
        <div class="authcard__brand">
            <?php
            /*
             * Ô ảnh `login-photo` của bản thiết kế. designImage() dùng ảnh
             * thiết kế nếu đã tải về assets/images/home/login-photo.(webp|jpg|png),
             * chưa có thì lấy tạm ảnh người mẫu của trang chủ — xem
             * assets/images/home/README.md.
             */
            ?>
            <img class="authcard__photo"
                 src="<?= designImage('login-photo', 'assets/images/hero-models.jpg') ?>"
                 alt="" width="600" height="620">

            <!-- pointer-events:none trong CSS: lớp chữ phủ lên ảnh nhưng không
                 được nuốt cú bấm nào — dưới nó không có gì bấm được, và chuột
                 vẫn phải chọn được chữ. -->
            <div class="authcard__caption">
                <p class="authcard__quote">Kính đẹp là kính hợp với chính bạn.</p>
                <p class="authcard__sub">Hơn 50 thương hiệu quốc tế · Đo mắt chuẩn phòng khám</p>
            </div>
        </div>

        <!-- ══════════ CỘT FORM ══════════ -->
        <div class="authcard__panel">

            <div class="authhead">
                <h1 class="authhead__title"><?= $isRegister ? 'Tạo tài khoản' : 'Đăng nhập' ?></h1>
                <p class="authhead__lead">
                    <?= $isRegister
                        ? 'Mở tài khoản để theo dõi đơn hàng và lịch hẹn.'
                        : 'Chào mừng bạn quay lại Vin Eyewear.' ?>
                </p>
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

            <?php if (!$isRegister): ?>

                <form class="authform" method="post" action="/auth/dang-nhap">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                    <label class="authfield">
                        <span class="authfield__label">Số điện thoại hoặc email</span>
                        <!--
                            type="text" chứ KHÔNG phải type="email": ô này nhận cả
                            số điện thoại, mà trình duyệt sẽ chặn "0912345678" ngay
                            tại chỗ nếu để type="email", kèm thông báo khó hiểu.
                            Việc kiểm tính hợp lệ do máy chủ làm.

                            autocomplete="username" là giá trị đúng cho một ô nhận
                            nhiều dạng định danh; để "email" thì trình quản lý mật
                            khẩu sẽ không gợi ý mục đã lưu bằng số điện thoại.
                        -->
                        <input class="authfield__input<?= $xau('email') ?>" type="text" name="email"
                               required autocomplete="username" inputmode="email" autofocus
                               placeholder="Số điện thoại / Email"
                               value="<?= e($old['email'] ?? '') ?>">
                        <?php /* EF-01, EF-03, EF-04 — cả ba đều nói về ô này. */ ?>
                        <?php $loi('email'); ?>
                    </label>

                    <div class="authfield">
                        <div class="authfield__row">
                            <?php /* <label for> chứ không <span>: ô mật khẩu ở màn này KHÔNG
                                     nằm trong một <label> bọc ngoài như mọi ô khác của luồng
                                     tài khoản, vì hàng nhãn còn có liên kết "Quên mật khẩu?"
                                     và <a> lồng trong <label> là HTML sai.

                                     Trước khi có dòng này, ô mật khẩu là trường DUY NHẤT trên
                                     toàn site không có tên cho trình đọc màn hình — placeholder
                                     của nó là tám dấu chấm nên không đọc ra được gì. */ ?>
                            <label class="authfield__label" for="loginPassword">Mật khẩu</label>
                            <a class="authfield__aside" href="/quen-mat-khau">Quên mật khẩu?</a>
                        </div>

                        <?php partial('auth/_password', [
                            'pw_name'     => 'password',
                            'pw_id'       => 'loginPassword',
                            'pw_auto'     => 'current-password',
                            'pw_holder'   => '••••••••',
                            'pw_required' => true,
                            'pw_err'      => $hong('password'),
                        ]); ?>
                        <?php /* EF-02, và CHỈ EF-02: "sai mật khẩu" (EF-07)
                                 không được gắn vào đây — gắn vào là đã nói ô
                                 trên đúng, tức là địa chỉ ấy có tài khoản.
                                 Xem BR-UC.USER.02-03. */ ?>
                        <?php $loi('password'); ?>
                    </div>

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

            <?php else: ?>

                <?php /* ĐĂNG KÝ LÀ MỘT FORM, MỘT LƯỢT GỬI — theo UC-USER-01.
                         Luồng sáu chặng nối bằng ?buoc= đã gỡ; khâu xác minh
                         bằng mã nay là một hàng trong chính form ấy. Xem khối
                         chú thích đầu auth/_signup.php. */ ?>
                <?php partial('auth/_signup', [
                    'signup'   => $signup,
                    'old'      => $old,
                    'errors'   => $errors,
                    'redirect' => $redirect,
                    // Ba cái đóng gói dựng ở đầu file này — xem khối chú thích ở đó.
                    'hong'     => $hong,
                    'loi'      => $loi,
                    'xau'      => $xau,
                ]); ?>

            <?php endif; ?>

            <div class="author" aria-hidden="true">
                <span class="author__line"></span>
                <span class="author__word">HOẶC</span>
                <span class="author__line"></span>
            </div>

            <?php
            /*
             * NÚT GOOGLE CHỈ SỐNG KHI ĐÃ CẤU HÌNH.
             *
             * Chưa điền GOOGLE_CLIENT_ID/SECRET trong .env thì nó vẫn là cái
             * nút xám "Sắp có" như trước — nút bấm được mà ra trang lỗi của
             * Google còn tệ hơn nút không bấm được, vì khách không biết lỗi ở
             * phía họ hay phía site.
             *
             * ─────────────────────────────────────────────────────────────
             * MỘT HÌNH DẠNG CHO CẢ HAI TAB: THẺ <a>, GET.
             *
             * Ở tab đăng ký, nút này từng là nút submit của CHÍNH form đăng ký
             * (form="signupform", POST) — cách duy nhất để cú bấm mang theo ô
             * tick Điều khoản của form ấy, vì hồi đó callback của Google tạo
             * tài khoản ngay khi quay về.
             *
             * Nay không còn: đăng ký bằng Google có màn "Hoàn tất tạo tài
             * khoản" riêng (/auth/dang-ky/google), và ô tick BR-UC.USER.01-05
             * nằm ở đó — đúng chỗ tài khoản thật sự ra đời. Nút này lại chỉ là
             * một cú chuyển hướng sang Google, không đổi gì cả, nên GET là
             * đúng. Thứ chống giả mạo của luồng OAuth là tham số `state` mà
             * GoogleAuth sinh ra và cất trong session.
             * ─────────────────────────────────────────────────────────────
             */
            $googleOn = GoogleAuth::isConfigured();
            ?>
            <?php
            /* Địa chỉ mang theo HAI thứ: `tab` để googleStart() biết trả khách
               về màn nào khi có lỗi và chọn đúng đích mặc định của
               BR-UC.USER.01-09 (đăng ký xong về trang chủ, đăng nhập xong về
               /tai-khoan), và `redirect` để cả hai quay lại được nghiệp vụ
               đang dở. */
            $urlGoogle = '/auth/google'
                . ($isRegister ? '?tab=dang-ky' : '')
                . ($redirect !== ''
                    ? ($isRegister ? '&' : '?') . 'redirect=' . rawurlencode($redirect)
                    : '');
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

            <?php
            /*
             * ĐỒNG Ý NGẦM — CHỈ CÒN Ở MÀN ĐĂNG NHẬP.
             *
             * Người đã có tài khoản thì đã tick lúc đăng ký; bắt tick lại mỗi
             * lần vào là thêm ma sát mà không thêm giá trị pháp lý nào.
             *
             * Màn ĐĂNG KÝ thì KHÔNG dùng câu này nữa — kể cả cho nút Google.
             * BR-UC.USER.01-05 đòi một hành vi đồng ý TƯỜNG MINH cho cả hai
             * phương thức, nên ở đó có đúng một ô tick thật (auth/_signup.php)
             * và nút Google gửi đi cùng ô tick ấy. Một dòng chữ "bằng việc tạo
             * tài khoản, bạn đồng ý…" đứng cạnh một ô tick nói cùng chuyện chỉ
             * làm người đọc hoang mang xem cái nào mới tính.
             *
             * Vế "Điều khoản dịch vụ" chỉ hiện khi văn bản đã tồn tại. Trước đây
             * nó trỏ cứng tới /chinh-sach#dieu-khoan — một neo KHÔNG có trong
             * config/policy.php, nên bấm vào chỉ nhảy lên đầu trang. Xem
             * config/auth.php.
             */
            $consent  = (array) config('auth.consent', []);
            $termsUrl = (string) ($consent['terms_url'] ?? '');
            ?>
            <?php if (!$isRegister): ?>
            <p class="authnote">
                Bằng việc đăng nhập, bạn đồng ý với
                <?php if ($termsUrl !== ''): ?>
                    <a href="<?= e($termsUrl) ?>">Điều khoản dịch vụ</a> và
                <?php endif; ?>
                <a href="<?= e((string) ($consent['privacy_url'] ?? '/chinh-sach#bao-mat')) ?>">Chính sách bảo mật</a>
                của Vin Eyewear.
            </p>
            <?php endif; ?>

            <p class="authalt">
                <?php if ($isRegister): ?>
                    Đã có tài khoản? <a href="<?= e($urlLogin) ?>">Đăng nhập</a>
                <?php else: ?>
                    Chưa có tài khoản? <a href="<?= e($urlSignup) ?>">Đăng ký</a>
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>
