<?php

/**
 * auth/_signup.php — luồng đăng ký nhiều chặng (/auth?tab=dang-ky[&buoc=…]).
 *
 * Dựng theo "Dang ky.dc.html" (Claude Design). Bản thiết kế vẽ SÁU màn; ở đây
 * cả sáu nằm trong đúng cái thẻ hai cột của trang đăng nhập, vì yêu cầu là
 * "giao diện lấy cái cũ, luồng nút bấm lấy theo design".
 *
 *   ''            Nhập số điện thoại      → POST /auth/dang-ky
 *   xac-minh      "Gửi mã qua Zalo?"      → POST /auth/dang-ky/gui-ma
 *   phuong-thuc   Zalo · SMS · Gọi thoại  → POST /auth/dang-ky/gui-ma
 *   ma            Nhập 6 số               → POST /auth/dang-ky/xac-minh
 *   da-dang-ky    Số đã có tài khoản      → lối ra: đăng nhập, hoặc đổi số
 *   mat-khau      Tạo mật khẩu            → POST /auth/dang-ky/mat-khau
 *   xong          Đăng ký thành công
 *
 * Bước nào được phép mở là do AuthController::signupStep() quyết, không phải
 * ?buoc= trên URL — xem chú thích ở đó.
 *
 * Nhận qua partial(): $step, $signup (mảng của signupView(), rỗng khi chưa
 * bắt đầu), $old.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MÃ XÁC MINH CHƯA GỬI ĐI ĐÂU ĐƯỢC — xem khối chú thích đầu core/Otp.php.
 * Ở chế độ phát triển, mã hiện lên chính dải .authflash phía trên.
 */

$signup = $signup ?? [];
$phone  = $signup['display'] ?? '';

/** Dải ba bước ở đầu bản thiết kế. Bước hiện tại quyết ba cái chấm sáng tới đâu. */
$stage = match ($step) {
    'mat-khau'   => 2,
    'xong'       => 3,
    default      => 1,
};

/** Nút lùi của các màn giữa — cùng dáng với nút "‹" của hộp thoại mua hàng. */
$backTo = static function (string $href): void { ?>
    <a class="aback" href="<?= e($href) ?>" aria-label="Quay lại">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 18l-6-6 6-6"></path>
        </svg>
    </a>
<?php };
?>

<?php if ($step !== ''): ?>
    <?php /* Ba chặng: Xác minh SĐT → Tạo mật khẩu → Hoàn tất. Màn nhập số
             chưa vào chặng nào nên không vẽ dải này. */ ?>
    <ol class="astage" role="list">
        <?php foreach (['Xác minh SĐT', 'Tạo mật khẩu', 'Hoàn tất'] as $i => $label): ?>
            <?php $n = $i + 1; ?>
            <li class="astage__item<?= $n < $stage ? ' is-done' : ($n === $stage ? ' is-now' : '') ?>">
                <span class="astage__dot" aria-hidden="true"><?= $n < $stage ? '✓' : $n ?></span>
                <span class="astage__label"><?= e($label) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php if ($step === ''): ?>

    <!-- ══════════ 1. NHẬP SỐ ĐIỆN THOẠI ══════════ -->
    <?php /* Chỉ MỘT ô. Bản thiết kế bỏ cả họ tên lẫn mật khẩu khỏi màn này:
             mật khẩu hỏi sau khi xác minh xong, còn tên thì không hỏi ở đâu
             cả — khách điền sau ở trang tài khoản nếu muốn. */ ?>
    <form class="authform" method="post" action="/auth/dang-ky">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <label class="authfield">
            <span class="authfield__label">Số điện thoại</span>
            <input class="authfield__input" type="tel" name="phone" required
                   autocomplete="tel" autofocus placeholder="0912345678"
                   value="<?= e($old['phone'] ?? '') ?>">
            <span class="authfield__hint">Dùng số này để đăng nhập.</span>
        </label>

        <!-- Ô tick đứng TRƯỚC nút trong HTML, CSS đẩy nó xuống dưới —
             xem ghi chú số 3 ở đầu auth/index.php. -->
        <label class="authcheck">
            <input type="checkbox" name="remember" value="1" checked>
            <span class="authcheck__box" aria-hidden="true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12.5l5.5 5.5L20 7"></path>
                </svg>
            </span>
            <span class="authcheck__text">Duy trì đăng nhập</span>
        </label>

        <button type="submit" class="authbtn authbtn--primary">Tiếp theo</button>
    </form>

<?php elseif ($step === 'xac-minh'): ?>

    <!-- ══════════ 2. XÁC NHẬN KÊNH GỬI ══════════ -->
    <?php /* Bản thiết kế vẽ màn này thành hộp thoại nổi đè lên form. Ở đây nó
             là một màn thật trong luồng: hộp thoại nổi cần JavaScript để mở,
             mà cả trang đăng ký phải chạy được khi tắt JavaScript. Ba nút vẫn
             y nguyên — Hủy · Phương thức khác · Gửi qua Zalo. */ ?>
    <div class="asay">
        <?php $backTo('/auth?tab=dang-ky'); ?>
        <p class="asay__title">Xác minh số điện thoại</p>
        <p class="asay__text">
            Chúng tôi sẽ gửi mã xác minh qua Zalo đến <strong><?= e($phone) ?></strong>
        </p>
    </div>

    <form class="authform" method="post" action="/auth/dang-ky/gui-ma">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="method" value="zalo">
        <button type="submit" class="authbtn authbtn--primary">Gửi qua Zalo</button>
    </form>

    <div class="aalt">
        <a class="authbtn authbtn--ghost" href="/auth?tab=dang-ky&amp;buoc=phuong-thuc">Phương thức khác</a>
        <a class="aalt__quiet" href="/auth?tab=dang-ky">Hủy</a>
    </div>

<?php elseif ($step === 'phuong-thuc'): ?>

    <!-- ══════════ 3. CHỌN PHƯƠNG THỨC ══════════ -->
    <div class="asay">
        <?php $backTo('/auth?tab=dang-ky&buoc=' . (($signup['wait'] ?? 0) > 0 ? 'ma' : 'xac-minh')); ?>
        <p class="asay__title">Chọn phương thức xác minh</p>
        <p class="asay__text">
            Chọn một trong các phương thức bên dưới để gửi mã xác minh đến
            <strong><?= e($phone) ?></strong>
        </p>
    </div>

    <?php /* Mỗi lựa chọn là một FORM riêng: chúng gửi cùng một địa chỉ, khác
             nhau đúng ở `method`, và HTML không cho lồng form vào nhau. */ ?>
    <div class="amethod">
        <?php foreach ([
            ['zalo',  'Zalo',      'Nhận mã trong ứng dụng Zalo'],
            ['sms',   'SMS',       'Tin nhắn tới số điện thoại'],
            ['voice', 'Gọi thoại', 'Tổng đài đọc mã cho bạn'],
        ] as [$key, $label, $note]): ?>
            <form method="post" action="/auth/dang-ky/gui-ma">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="method" value="<?= e($key) ?>">
                <button type="submit" class="amethod__item">
                    <span class="amethod__ico amethod__ico--<?= e($key) ?>" aria-hidden="true"></span>
                    <span class="amethod__body">
                        <span class="amethod__name"><?= e($label) ?></span>
                        <span class="amethod__note"><?= e($note) ?></span>
                    </span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>

<?php elseif ($step === 'ma'): ?>

    <!-- ══════════ 4. NHẬP MÃ ══════════ -->
    <div class="asay">
        <?php $backTo('/auth?tab=dang-ky'); ?>
        <p class="asay__title">Nhập mã xác minh</p>
        <p class="asay__text">
            <?= e($signup['sentVia'] ?? '') ?> <strong><?= e($phone) ?></strong>
        </p>
    </div>

    <form class="authform" method="post" action="/auth/dang-ky/xac-minh">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <?php /* SÁU Ô RỜI, không phải một ô sáu ký tự — đúng bản thiết kế.
                 Mỗi ô là một <input> thật nên tắt JavaScript vẫn gõ được đủ
                 sáu số rồi bấm Tiếp theo; auth.js chỉ thêm việc tự nhảy ô. */ ?>
        <fieldset class="aotp">
            <legend class="sr-only">Mã xác minh gồm <?= Otp::LENGTH ?> chữ số</legend>
            <?php for ($i = 0; $i < Otp::LENGTH; $i++): ?>
                <input class="aotp__box" type="text" name="ma[]" inputmode="numeric"
                       pattern="[0-9]*" maxlength="1" autocomplete="one-time-code"
                       aria-label="Chữ số thứ <?= $i + 1 ?>"
                       <?= $i === 0 ? 'autofocus' : '' ?>>
            <?php endfor; ?>
        </fieldset>

        <button type="submit" class="authbtn authbtn--primary">Tiếp theo</button>
    </form>

    <?php /* Đồng hồ đếm ngược do máy chủ phát ra con số đầu tiên, auth.js chỉ
             đếm lùi cho đỡ phải tải lại trang. Chốt thật nằm ở máy chủ —
             xem signupSend(). */ ?>
    <div class="aresend" data-wait="<?= (int) ($signup['wait'] ?? 0) ?>">
        <p class="aresend__wait"<?= ($signup['wait'] ?? 0) > 0 ? '' : ' hidden' ?>>
            Vui lòng chờ <span class="aresend__num"><?= (int) ($signup['wait'] ?? 0) ?></span> giây để gửi lại.
        </p>

        <div class="aresend__go"<?= ($signup['wait'] ?? 0) > 0 ? ' hidden' : '' ?>>
            <p class="aresend__ask">Bạn chưa nhận được mã?</p>
            <form method="post" action="/auth/dang-ky/gui-ma">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="method" value="<?= e($signup['method'] ?? 'zalo') ?>">
                <button type="submit" class="aresend__link">Gửi lại</button>
            </form>
            <span class="aresend__or">hoặc thử</span>
            <a class="aresend__link" href="/auth?tab=dang-ky&amp;buoc=phuong-thuc">Phương thức khác</a>
        </div>
    </div>

<?php elseif ($step === 'da-dang-ky'): ?>

    <!-- ══════════ 5. SỐ NÀY ĐÃ CÓ TÀI KHOẢN ══════════ -->
    <?php /* Ngõ cụt CÓ LỐI RA, đúng bản thiết kế: đăng nhập bằng số này, hoặc
             quay lại nhập số khác. Màn này chỉ hiện SAU khi mã đã đúng — xem
             chú thích trong signupVerify() về việc vì sao không hỏi sớm hơn. */ ?>
    <div class="asay">
        <p class="asay__title">Số điện thoại đã được đăng ký</p>
        <p class="asay__phone"><?= e($phone) ?></p>
        <p class="asay__text">
            Vui lòng đăng nhập nếu đây là tài khoản của bạn. Hoặc quay lại và
            dùng một số điện thoại khác để mở tài khoản mới.
        </p>
    </div>

    <a class="authbtn authbtn--primary" href="/auth">Đăng nhập</a>

    <div class="aalt">
        <a class="aalt__quiet" href="/auth?tab=dang-ky">Dùng số điện thoại khác</a>
    </div>

<?php elseif ($step === 'mat-khau'): ?>

    <!-- ══════════ 6. TẠO MẬT KHẨU ══════════ -->
    <div class="asay">
        <?php $backTo('/auth?tab=dang-ky'); ?>
        <p class="asay__title">Tạo mật khẩu</p>
        <p class="asay__text">
            Bước cuối cùng! Tạo mật khẩu cho tài khoản <strong><?= e($phone) ?></strong>
        </p>
    </div>

    <form class="authform" method="post" action="/auth/dang-ky/mat-khau" data-pw-rules>
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <label class="authfield">
            <span class="sr-only">Mật khẩu</span>
            <?php partial('auth/_password', [
                'pw_name'     => 'password',
                'pw_auto'     => 'new-password',
                'pw_holder'   => 'Mật khẩu',
                'pw_min'      => 8,
                'pw_required' => true,
            ]); ?>
        </label>

        <?php /* BỐN QUY TẮC, cùng bộ với signupFinish() ở máy chủ. auth.js
                 chấm xanh từng dòng ngay khi gõ; không có JavaScript thì bốn
                 dòng này vẫn là bản yêu cầu đọc được, và máy chủ vẫn từ chối
                 mật khẩu thiếu chuẩn. */ ?>
        <ul class="arule" role="list">
            <li class="arule__item" data-rule="len"><span class="arule__dot" aria-hidden="true">✓</span>Tối thiểu 8 ký tự</li>
            <li class="arule__item" data-rule="upper"><span class="arule__dot" aria-hidden="true">✓</span>Ít nhất một chữ hoa</li>
            <li class="arule__item" data-rule="lower"><span class="arule__dot" aria-hidden="true">✓</span>Ít nhất một chữ thường</li>
            <li class="arule__item" data-rule="digit"><span class="arule__dot" aria-hidden="true">✓</span>Ít nhất một chữ số</li>
        </ul>

        <button type="submit" class="authbtn authbtn--primary">Đăng ký</button>
    </form>

<?php elseif ($step === 'xong'): ?>

    <!-- ══════════ 7. XONG ══════════ -->
    <div class="adone">
        <span class="adone__mark" aria-hidden="true">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12.5l5.5 5.5L20 7"></path>
            </svg>
        </span>
        <p class="adone__title">Đăng ký thành công!</p>
        <p class="adone__text">Tài khoản của bạn đã sẵn sàng. Chào mừng bạn đến với Vin Eyewear.</p>
    </div>

    <a class="authbtn authbtn--primary" href="/san-pham">Bắt đầu mua sắm</a>

    <div class="aalt">
        <a class="aalt__quiet" href="/">Về trang chủ</a>
    </div>

<?php endif; ?>
