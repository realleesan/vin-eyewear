<!-- ============================================================
     ANNOUNCEMENT BAR — 3 vung: social | thong bao | tai khoan
     ============================================================ -->
<div class="announcement-bar">

    <!-- VUNG TRAI: social / e-commerce -->
    <div class="announcement-social">
        <a href="#" class="announcement-ico" aria-label="Facebook">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M14 8.5h2.2V5.6h-2.4c-2.5 0-3.9 1.5-3.9 3.9v1.6H8v3h1.9V21h3v-6.9h2.2l.4-3h-2.6V9.8c0-.9.3-1.3 1.1-1.3z"/>
            </svg>
        </a>
        <a href="#" class="announcement-ico" aria-label="Shopee">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M8.5 7a3.5 3.5 0 017 0M4.5 7h15l-1 12.5a1.5 1.5 0 01-1.5 1.4H7a1.5 1.5 0 01-1.5-1.4z"/>
                <path d="M10 15.2c.5.7 1.3 1 2.1 1 1.2 0 2-.6 2-1.5 0-2-3.8-1.2-3.8-3.1 0-.8.8-1.4 1.8-1.4.8 0 1.4.3 1.8.8"/>
            </svg>
        </a>
        <a href="#" class="announcement-ico" aria-label="TikTok">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M16.5 3h-2.8v11.4a2.3 2.3 0 11-1.9-2.3V9.2a5.2 5.2 0 105 5.2V9.1a6.3 6.3 0 003.4 1V7.4a3.6 3.6 0 01-3.7-3.7z"/>
            </svg>
        </a>
    </div>

    <!-- VUNG PHAI: Gio hang / Tim kiem / Tai khoan -->
    <div class="announcement-actions">
        <a href="/cart" class="announcement-action" aria-label="Giỏ hàng">
            <span class="announcement-action__ico">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M3 6h3l2.4 10.4h9.6L20.4 9H7"/>
                    <circle cx="10" cy="19.5" r="1.3"/><circle cx="17.5" cy="19.5" r="1.3"/>
                </svg>
            </span>
        </a>
        <a href="/product" class="announcement-action" aria-label="Tìm kiếm">
            <span class="announcement-action__ico">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="10.5" cy="10.5" r="6"/><path d="M15 15l4.5 4.5"/>
                </svg>
            </span>
        </a>
        <a href="/account" class="announcement-action" aria-label="Tài khoản">
            <span class="announcement-action__ico">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="8" r="3.6"/><path d="M4.8 20c0-3.6 3.2-6 7.2-6s7.2 2.4 7.2 6"/>
                </svg>
            </span>
        </a>
    </div>
</div>

<!-- ============================================================
     MAIN HEADER (sticky) — nền vàng #FFCC00, chữ đen (mọi trang)
     ============================================================ -->
<header class="site-header">

    <!-- Logo / Thương hiệu -->
    <a href="/" class="site-logo">VIN EYEWEAR</a>

    <!-- Điều hướng 6 trang chính -->
    <nav class="main-nav" aria-label="Điều hướng chính">
        <ul class="nav-menu" id="navMenu" role="list">
            <li><a href="/">Trang chủ</a></li>
            <li><a href="/product">Sản phẩm</a></li>
            <li><a href="/about">Giới thiệu</a></li>
            <li><a href="/event">Sự kiện</a></li>
            <li><a href="/contact">Liên hệ</a></li>
        </ul>
    </nav>

    <!-- Bên phải: CTA + hamburger (mobile) -->
    <div class="header-actions">
        <a href="/ar" class="header-cta">Thử AR</a>

        <button
            class="nav-toggle"
            id="navToggle"
            type="button"
            aria-label="Mở/đóng menu"
            aria-expanded="false"
            aria-controls="navMenu"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

</header>
