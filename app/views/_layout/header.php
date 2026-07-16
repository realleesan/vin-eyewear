<!-- ============================================================
     ANNOUNCEMENT BAR — nền đen, chữ trắng (mọi trang)
     ============================================================ -->
<div class="announcement-bar">
    <button class="announcement-arrow" type="button" aria-label="Thông báo trước">&#8249;</button>

    <p class="announcement-msg">MIỄN PHÍ VẬN CHUYỂN TOÀN QUỐC</p>

    <button class="announcement-arrow" type="button" aria-label="Thông báo sau">&#8250;</button>

    <div class="announcement-meta">
        <span>Tiếng Việt <span class="caret" aria-hidden="true">&#9662;</span></span>
        <span>Việt Nam (VND ₫) <span class="caret" aria-hidden="true">&#9662;</span></span>
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
            <li><a href="/ar">Thử kính AR</a></li>
            <li><a href="/contact">Liên hệ</a></li>
        </ul>
    </nav>

    <!-- Bên phải: icon tài khoản/tìm kiếm/giỏ + CTA + hamburger (mobile) -->
    <div class="header-actions">
        <a href="/account" class="header-icon" aria-label="Tài khoản">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <circle cx="12" cy="8" r="3.6"/>
                <path d="M4.8 20c0-3.6 3.2-6 7.2-6s7.2 2.4 7.2 6"/>
            </svg>
        </a>
        <a href="/product" class="header-icon" aria-label="Tìm kiếm">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <circle cx="10.5" cy="10.5" r="6"/>
                <path d="M15 15l4.5 4.5"/>
            </svg>
        </a>
        <a href="/cart" class="header-icon" aria-label="Giỏ hàng">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M3 6h3l2.4 10.4h9.6L20.4 9H7"/>
                <circle cx="10" cy="19.5" r="1.3"/>
                <circle cx="17.5" cy="19.5" r="1.3"/>
            </svg>
        </a>

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
