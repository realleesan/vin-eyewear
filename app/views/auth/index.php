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
 * 2. NÚT GOOGLE VẼ ĐÚNG NHƯNG KHOÁ LẠI.
 *    Dự án chưa có hạ tầng OAuth nào — không client ID, không route callback,
 *    bảng users không có cột nối tài khoản Google. Nút vẫn ở đúng chỗ và đúng
 *    hình dạng bản thiết kế, nhưng `disabled` kèm chữ "Sắp có", thay vì bấm
 *    vào rồi không có gì xảy ra.
 *
 * 3. Ô "DUY TRÌ ĐĂNG NHẬP" ĐỨNG TRƯỚC NÚT TRONG MÃ NGUỒN.
 *    Bản thiết kế xếp nó SAU nút "Đăng nhập" trên màn hình. Giữ đúng thứ tự
 *    ấy trong HTML thì người dùng bàn phím phải Tab QUA nút gửi mới tới được ô
 *    tick — tức là gặp nút gửi trước khi kịp chọn có duy trì đăng nhập hay
 *    không. Nên HTML để ô tick trước, còn CSS (`order`) đẩy nó xuống dưới nút.
 *    Nhìn giống hệt bản thiết kế, thứ tự Tab thì đúng.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$old        = $old ?? [];
$isRegister = $tab === 'dang-ky';
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
                        <input class="authfield__input" type="text" name="email" required
                               autocomplete="username" inputmode="email" autofocus
                               placeholder="Số điện thoại / Email"
                               value="<?= e($old['email'] ?? '') ?>">
                    </label>

                    <div class="authfield">
                        <div class="authfield__row">
                            <span class="authfield__label">Mật khẩu</span>
                            <a class="authfield__aside" href="/quen-mat-khau">Quên mật khẩu?</a>
                        </div>

                        <?php partial('auth/_password', [
                            'pw_name'     => 'password',
                            'pw_auto'     => 'current-password',
                            'pw_holder'   => '••••••••',
                            'pw_required' => true,
                        ]); ?>
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
                        <span class="authcheck__text">Duy trì đăng nhập</span>
                    </label>

                    <button type="submit" class="authbtn authbtn--primary">Đăng nhập</button>
                </form>

            <?php else: ?>

                <form class="authform" method="post" action="/auth/dang-ky">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                    <label class="authfield">
                        <span class="authfield__label">Họ và tên</span>
                        <input class="authfield__input" type="text" name="full_name" required
                               minlength="2" maxlength="120" autocomplete="name" autofocus
                               placeholder="Nguyễn Văn A"
                               value="<?= e($old['full_name'] ?? '') ?>">
                    </label>

                    <?php /* KHÔNG CÒN Ô EMAIL. Số điện thoại nay là thứ khách dùng
                             để đăng nhập, nên nó thành BẮT BUỘC — bỏ cả hai thì
                             tài khoản tạo xong không ai vào được nữa. Ai muốn
                             tài khoản có email thì bấm nút Google phía dưới, và
                             địa chỉ đó do Google xác nhận chứ không phải chữ gõ
                             vào ô. */ ?>
                    <label class="authfield">
                        <span class="authfield__label">Số điện thoại</span>
                        <input class="authfield__input" type="tel" name="phone" required
                               autocomplete="tel" placeholder="0912345678"
                               value="<?= e($old['phone'] ?? '') ?>">
                        <span class="authfield__hint">Dùng số này để đăng nhập.</span>
                    </label>

                    <label class="authfield">
                        <span class="authfield__label">Mật khẩu</span>
                        <?php partial('auth/_password', [
                            'pw_name'     => 'password',
                            'pw_auto'     => 'new-password',
                            'pw_holder'   => 'Tối thiểu 8 ký tự',
                            'pw_min'      => 8,
                            'pw_required' => true,
                        ]); ?>
                    </label>

                    <label class="authfield">
                        <span class="authfield__label">Nhập lại mật khẩu</span>
                        <?php partial('auth/_password', [
                            'pw_name'     => 'password_confirm',
                            'pw_auto'     => 'new-password',
                            'pw_holder'   => '••••••••',
                            'pw_min'      => 8,
                            'pw_required' => true,
                        ]); ?>
                    </label>

                    <button type="submit" class="authbtn authbtn--primary">Tạo tài khoản</button>
                </form>

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
             * Là thẻ <a> chứ không phải form: bước này chưa đổi gì cả, và nó
             * phải chạy khi không có JavaScript. Thứ chống giả mạo nằm ở tham
             * số `state` mà GoogleAuth sinh ra và cất trong session.
             */
            $googleOn = GoogleAuth::isConfigured();
            ?>
            <?php if ($googleOn): ?>
            <a class="authbtn authbtn--google"
               href="/auth/google<?= $redirect !== '' ? '?redirect=' . e(rawurlencode($redirect)) : '' ?>"
               rel="nofollow">
                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"></path>
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"></path>
                    <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"></path>
                    <path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"></path>
                </svg>
                Tiếp tục với Google
            </a>
            <?php else: ?>
            <button type="button" class="authbtn authbtn--google" disabled>
                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"></path>
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"></path>
                    <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"></path>
                    <path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C36.9 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"></path>
                </svg>
                Tiếp tục với Google
                <span class="authbtn__soon">Sắp có</span>
            </button>
            <?php endif; ?>

            <p class="authnote">
                Bằng việc <?= $isRegister ? 'tạo tài khoản' : 'đăng nhập' ?>, bạn đồng ý với
                <a href="/chinh-sach#dieu-khoan">Điều khoản dịch vụ</a> và
                <a href="/chinh-sach#bao-mat">Chính sách bảo mật</a> của Vin Eyewear.
            </p>

            <p class="authalt">
                <?php if ($isRegister): ?>
                    Đã có tài khoản? <a href="/auth">Đăng nhập</a>
                <?php else: ?>
                    Bạn mới biết đến Vin Eyewear? <a href="/auth?tab=dang-ky">Đăng ký</a>
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>
