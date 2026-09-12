<?php

/**
 * _layout/header-auth.php — icon tài khoản trên thanh đầu trang.
 *
 * Nhận từ header.php: $isLoggedIn (AuthMiddleware::check()).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * HAI TRẠNG THÁI, VÀ CHỈ MỘT TRONG HAI CÓ NGĂN KÉO
 *
 *   đã đăng nhập  → <a href="/tai-khoan"> trơn, y như trước. Ngăn kéo không
 *                   có việc gì làm: khách đã vào được rồi.
 *   chưa đăng nhập → <a href="/auth"> mở NGĂN KÉO BÊN PHẢI chứa đúng trang
 *                   /auth, nạp ngầm.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * NGĂN KÉO KHÔNG CHỨA FORM NÀO CỦA RIÊNG NÓ — ĐỌC KỸ TRƯỚC KHI SỬA
 *
 * Khối chú thích ở _layout/header.php từ chối dựng ngăn kéo đăng nhập, với
 * lý do: bản mẫu đặt form đăng nhập/đăng ký cùng luồng Zalo OTP vào trong
 * ngăn kéo, mà luồng ấy ở đây đã là trang thật /auth — có CSRF, có trạng
 * thái lỗi, có bước OTP nhiều màn; dựng lại là hai bản sao sẽ lệch dần.
 *
 * Lý do ấy VẪN ĐÚNG, và cách làm dưới đây không vi phạm nó: ruột ngăn kéo
 * để TRỐNG trong HTML, rồi assets/js/auth-drawer.js nạp ngầm chính /auth
 * (header `X-Auth: 1`, xem nhánh trả mảnh ở _layout/master.php) và đổ vào.
 * Cùng view, cùng controller, cùng token, cùng mọi bước — không có bản thứ
 * hai nào để lệch.
 *
 * ĐỪNG gõ một <form> đăng nhập vào file này. Muốn đổi form thì sửa
 * app/views/auth/index.php; ngăn kéo tự ăn theo.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * TẮT JAVASCRIPT VẪN ĐI ĐƯỢC
 *
 * Thẻ mở là <a href="/auth"> THẬT. Không có JS thì bấm vào là sang trang
 * /auth như trước khi có file này — không mất lối nào. header.js chặn cú
 * bấm nhờ [data-hpop-link]; auth-drawer.js lo phần nạp.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * KHÔNG DÙNG .is-overlay
 *
 * Ô tìm kiếm và ngăn giỏ là tấm TRÀN BỀ NGANG xổ từ dưới thanh đầu trang
 * xuống, nên thanh phải đổi sang nền của tấm để hai khối liền nhau. Ngăn
 * kéo này trượt từ MÉP PHẢI và không giáp thanh theo chiều ngang — cho thanh
 * đổi màu ở đây là đổi một thứ chẳng dính gì tới nó. Vì thế header.js không
 * khai cờ nào cho .hpop--auth.
 */
?>
<?php if ($isLoggedIn): ?>

    <?php /* Đã đăng nhập — liên kết trơn, không ngăn kéo, không JS. */ ?>
    <a href="/tai-khoan" class="oa-header__btn"
       aria-label="<?= e(t('account.mine')) ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>
        </svg>
    </a>

<?php else: ?>

<div class="hpop hpop--auth" data-hpop data-auth-drawer>

    <?php /* [data-hpop-link] nói với header.js "trigger này là <a>, chặn cú
             bấm giùm". Thiếu nó thì vòng lặp hpop bỏ qua (nó chỉ nhận
             <button>) và ngăn kéo không bao giờ mở. */ ?>
    <a href="/auth"
       class="hpop__trigger oa-header__btn"
       data-hpop-trigger
       data-hpop-link
       aria-label="<?= e(t('account.login')) ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>
        </svg>
    </a>

    <?php /* Nền mờ — anh em với tấm, không nằm trong nó. Cùng lối ô tìm. */ ?>
    <button type="button" class="hpop__scrim oa-scrim" data-hpop-close tabindex="-1" aria-hidden="true"></button>

    <div class="hpop__panel authdrawer" role="dialog" aria-modal="true"
         aria-label="<?= e(t('account.login')) ?>">

        <button type="button" class="oa-close authdrawer__x" data-hpop-close
                aria-label="<?= e(t('ui.close')) ?>">✕</button>

        <?php
        /* RUỘT TRỐNG LÀ CỐ Ý — auth-drawer.js đổ /auth vào đây ở lần mở đầu
           tiên rồi giữ lại cho các lần sau.

           `aria-live="polite"` để trình đọc màn hình biết có nội dung vừa
           tới; `aria-busy` do JS bật/tắt quanh lượt nạp. */
        ?>
        <div class="authdrawer__body" data-auth-body aria-live="polite" aria-busy="false">
            <p class="authdrawer__wait"><?= e(t('ui.loading')) ?></p>
        </div>
    </div>
</div>

<?php endif; ?>
