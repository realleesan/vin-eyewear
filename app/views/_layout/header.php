<?php
/**
 * _layout/header.php
 * - Không chứa logic PHP (BaseController đã truyền $pageTitle qua extract($data))
 * - Active state dựa vào $currentPage được truyền từ controller
 */

// Danh sách 6 nav items: [label, href, key nhận diện trang]
$navItems = [
    ['Trang chủ',  '/',        'home'],
    ['Sản phẩm',   '/product', 'product'],
    ['Giới thiệu', '/about',   'about'],
    ['Sự kiện',    '/event',   'event'],
    ['Thử kính AR','/ar',      'ar'],
    ['Liên hệ',    '/contact', 'contact'],
];

// Fallback nếu controller chưa truyền $currentPage
$currentPage = $currentPage ?? '';
?>

<header class="site-header">
    <nav class="main-nav" aria-label="Điều hướng chính">
        <div class="container">

            <!-- Logo / Thương hiệu -->
            <div class="nav-brand">
                <a href="/">Vin Eyewear</a>
            </div>

            <!-- Nút hamburger (mobile) -->
            <button
                class="nav-toggle"
                id="navToggle"
                aria-label="Mở/đóng menu"
                aria-expanded="false"
                aria-controls="navMenu"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Danh sách liên kết 6 trang chính -->
            <ul class="nav-menu" id="navMenu" role="list">
                <?php foreach ($navItems as [$label, $href, $key]): ?>
                <li>
                    <a
                        href="<?= $href ?>"
                        <?= $currentPage === $key ? 'class="active" aria-current="page"' : '' ?>
                    >
                        <?= $label ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

        </div>
    </nav>
</header>