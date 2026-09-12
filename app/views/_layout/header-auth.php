<?php

/**
 * _layout/header-auth.php — icon tài khoản trên thanh đầu trang.
 *
 * Nhận từ header.php: $isLoggedIn (AuthMiddleware::check()).
 *
 * ═════════════════════════════════════════════════════════════════════════
 * HAI TRẠNG THÁI, VÀ CHỈ MỘT TRONG HAI CÓ NGĂN KÉO
 *
 *   đã đăng nhập  → <a href="/tai-khoan"> trơn. Ngăn kéo không có việc gì
 *                   làm: khách đã vào được rồi.
 *   chưa đăng nhập → <a href="/auth" data-authov-open> mở NGĂN KÉO chứa đúng
 *                   trang /auth, nạp ngầm.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * FILE NÀY CHỈ CÒN THẺ MỞ — TẤM Ở CHỖ KHÁC (12/09/2026)
 *
 * Trước đây cả ngăn kéo (nền mờ + tấm + ruột) nằm ngay trong file này, tức
 * NẰM TRONG <header>. Nay chỉ còn cái nút; khuôn của tấm in ở cấp <body> qua
 * _layout/auth-drawer.php, và JS nhân bản nó vào #modal-root khi mở.
 *
 * VÌ SAO PHẢI DỜI: thanh đầu trang là `position: sticky` và có lúc mang
 * `transform`. Một tổ tiên có transform biến mọi `position: fixed` con cháu
 * thành "fixed so với tổ tiên đó" — tấm neo vào thanh nav thay vì vào khung
 * nhìn. Thanh còn có `overflow` ở vài nhánh, đủ để cắt cụt tấm. Lý do đầy đủ
 * ở đầu _layout/auth-drawer.php.
 *
 * ĐỪNG gõ một <form> đăng nhập vào file này, cũng đừng gõ vào khuôn kia:
 * ruột ngăn kéo LÀ trang /auth nạp ngầm (cùng view, cùng controller, cùng
 * token, cùng mọi bước). Muốn đổi form thì sửa app/views/auth/index.php.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * TẮT JAVASCRIPT VẪN ĐI ĐƯỢC
 *
 * Thẻ mở là <a href="/auth"> THẬT. Không có JS thì bấm vào là sang trang
 * /auth — không mất lối nào. Thuộc tính [data-authov-open] chỉ là chỗ bám
 * cho auth-drawer.js.
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

    <a href="/auth"
       class="oa-header__btn"
       data-authov-open
       aria-haspopup="dialog"
       aria-label="<?= e(t('account.login')) ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true" focusable="false">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>
        </svg>
    </a>

<?php endif; ?>
