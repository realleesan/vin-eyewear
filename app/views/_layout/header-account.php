<?php

/**
 * _layout/header-account.php — ngăn kéo TÀI KHOẢN trên thanh đầu trang.
 *
 * Nhận từ header.php: $isLoggedIn (AuthMiddleware::check()).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * ĐÂY LÀ MENU, KHÔNG PHẢI FORM ĐĂNG NHẬP THỨ HAI — đọc trước khi thêm gì
 *
 * Khối chú thích ở _layout/header.php (mục "TÀI KHOẢN") từ chối dựng ngăn
 * kéo này, và lý do nó đưa ra vẫn đúng nguyên: bản mẫu mở một ngăn kéo chứa
 * form đăng nhập/đăng ký cùng luồng Zalo OTP, mà luồng ấy ở đây đã là TRANG
 * THẬT /auth — có CSRF, có trạng thái lỗi, có bước OTP nhiều màn. Dựng lại
 * nó lần thứ hai trong thanh đầu trang là hai bản sao của cùng một luồng, và
 * bản trong header sẽ lệch dần.
 *
 * Ngăn kéo này (theo yêu cầu chủ dự án, 12/09/2026) đi đường khác: nó KHÔNG
 * chứa ô nhập nào. Chưa đăng nhập thì nó là hai lối vào /auth; đã đăng nhập
 * thì nó là ba mục của trang tài khoản cộng nút thoát. Không có gì để lệch,
 * vì không có gì bị chép lại.
 *
 * ĐỪNG thêm <input> vào file này. Cần một form thì chỗ của nó là /auth.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * BA MỤC LẤY TỪ AuthController::SECTIONS, KHÔNG GÕ LẠI
 *
 * Cột điều hướng ở auth/profile.php đã dựng từ chính bảng ấy bằng vòng lặp,
 * cố ý để "thêm/bớt một mục thì chỉ có một chỗ để sửa". Chép ba nhãn sang
 * đây là dựng đúng chỗ thứ hai mà lập luận đó muốn tránh. Hằng được mở
 * `public` cùng lượt này — xem khối chú thích của nó trong AuthController.
 *
 * Icon dùng lại auth/_nav-icon.php nên hai nơi không bao giờ lệch nét vẽ.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * KHÔNG TRUY VẤN GÌ THÊM
 *
 * Ngăn kéo không in tên người dùng, và đó là lựa chọn chứ không phải thiếu
 * sót: lấy tên nghĩa là một lượt xuống CSDL trên MỌI trang của khu bán hàng,
 * chỉ để điền một dòng chữ trong tấm mà phần lớn khách không mở. Thanh đầu
 * trang hiện có đúng hai truy vấn (danh mục hiện + 5 mẫu nổi bật) và khối
 * chú thích ở header.php đếm chúng ra — đừng làm nó thành ba.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * TẮT JAVASCRIPT VẪN ĐI ĐƯỢC
 *
 * Thẻ mở là <a href> THẬT, cùng lối với giỏ hàng: không có JS thì bấm vào là
 * sang /tai-khoan (hoặc /auth), đúng hành vi trước khi có file này. header.js
 * chặn cú bấm nhờ [data-hpop-link] và mở tấm thay thế.
 */

$taiKhoanUrl = $isLoggedIn ? '/tai-khoan' : '/auth';
?>
<div class="hpop hpop--account" data-hpop>

    <?php /* [data-hpop-link] là thứ nói với header.js "trigger này là <a>,
             chặn cú bấm giùm". Thiếu nó thì vòng lặp hpop bỏ qua thẻ này
             (nó chỉ nhận <button>) và ngăn kéo không bao giờ mở. */ ?>
    <a href="<?= e($taiKhoanUrl) ?>"
       class="hpop__trigger oa-header__btn"
       data-hpop-trigger
       data-hpop-link
       aria-label="<?= e($isLoggedIn ? t('account.mine') : t('account.login')) ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>
        </svg>
    </a>

    <?php /* Nền mờ — anh em với tấm, không nằm trong nó. Cùng lối ô tìm. */ ?>
    <button type="button" class="hpop__scrim oa-scrim" data-hpop-close tabindex="-1" aria-hidden="true"></button>

    <div class="hpop__panel hpop__panel--account oa-panel" role="dialog"
         aria-label="<?= e($isLoggedIn ? t('account.mine') : t('account.login')) ?>">

        <button type="button" class="oa-close" data-hpop-close
                aria-label="<?= e(t('ui.close')) ?>" style="align-self:flex-end">✕</button>

        <div class="oa-panel__inner">

            <?php if ($isLoggedIn): ?>

                <nav class="acctpop" aria-label="<?= e(t('account.mine')) ?>">
                    <?php foreach (AuthController::SECTIONS as $key => $nhan): ?>
                        <a class="acctpop__item" href="/tai-khoan?muc=<?= e($key) ?>">
                            <span class="acctpop__icon" aria-hidden="true">
                                <?php partial('auth/_nav-icon', ['key' => $key]); ?>
                            </span>
                            <span><?= e($nhan) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <?php
                /* ĐĂNG XUẤT VẪN LÀ POST + CSRF + HỎI LẠI — ba thứ này chép
                   nguyên từ auth/profile.php và không được rút gọn:

                   POST vì một thẻ <img src="/auth/dang-xuat"> trên trang bất
                   kỳ cũng đủ đá khách ra nếu tuyến này nhận GET.

                   data-confirm* là chữ cho hộp thoại thật của confirm-dialog.js;
                   onsubmit là lớp dự phòng khi JS chưa sẵn sàng, và chính file
                   JS đó gỡ nó ra khi đã nạp xong. */
                $hoiThoat = 'Đăng xuất khỏi tài khoản Vin Eyewear?';
                ?>
                <form method="post" action="/auth/dang-xuat" class="acctpop__out"
                      data-confirm="<?= e($hoiThoat) ?>"
                      data-confirm-title="Đăng xuất?"
                      data-confirm-ok="Đăng xuất"
                      data-confirm-cancel="Ở lại"
                      onsubmit="return confirm('<?= e($hoiThoat) ?>')">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="acctpop__item acctpop__item--btn">
                        <span class="acctpop__icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <path d="M16 17l5-5-5-5"/>
                                <path d="M21 12H9"/>
                            </svg>
                        </span>
                        <span>Đăng xuất</span>
                    </button>
                </form>

            <?php else: ?>

                <?php /* HAI LỐI VÀO /auth, không phải một form. Xem khối chú
                         thích đầu file về lý do. Đường thứ hai mang ?tab=dang-ky
                         — đúng tham số auth/index.php đọc. */ ?>
                <nav class="acctpop" aria-label="<?= e(t('account.login')) ?>">
                    <a class="acctpop__item" href="/auth">
                        <span class="acctpop__icon" aria-hidden="true">
                            <?php partial('auth/_nav-icon', ['key' => 'ho-so']); ?>
                        </span>
                        <span><?= e(t('account.login')) ?></span>
                    </a>
                    <a class="acctpop__item" href="/auth?tab=dang-ky">
                        <span class="acctpop__icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="10" cy="8" r="4"/>
                                <path d="M2 21c1.3-3.5 4-5 8-5"/>
                                <path d="M17 14v6M14 17h6"/>
                            </svg>
                        </span>
                        <span><?= e(t('account.register')) ?></span>
                    </a>
                </nav>

            <?php endif; ?>

        </div>
    </div>
</div>
