<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <!-- Title động từ controller ($pageTitle được extract() từ $data trong BaseController) -->
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Vin Eyewear - Kính Mắt Cao Cấp' ?></title>

    <meta name="description" content="Vin Eyewear - Cửa hàng kính mắt cao cấp với công nghệ AR và AI">

    <!-- Google Fonts: Libre Caslon Text / Hanken Grotesk / JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Caslon+Text:ital,wght@0,400;0,700;1,400&family=Hanken+Grotesk:wght@400;500;600;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <!-- CSS dùng chung toàn site -->
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/layout.css">

    <!-- CSS RIÊNG CHO TỪNG MODULE (CHỈ LOAD KHI CẦN) -->
    <?php if (isset($viewName)): ?>
        <?php if (strpos($viewName, 'ar/') === 0): ?>
            <link rel="stylesheet" href="/assets/css/ar.tryon.css">
        <?php endif; ?>
        <?php if ($viewName === 'contact/index'): ?>
            <link rel="stylesheet" href="/assets/css/contact.css">
        <?php endif; ?>
    <?php endif; ?>
</head>

<body>

    <!-- Header & Navbar -->
    <?php require_once VIEWS_PATH . '/_layout/header.php'; ?>

    <!-- Nội dung trang con — $viewName được truyền từ BaseController::renderView() -->
    <main class="main-content">
        <?php require_once VIEWS_PATH . '/' . $viewName . '.php'; ?>
    </main>

    <!-- Footer -->
    <?php require_once VIEWS_PATH . '/_layout/footer.php'; ?>

    <!-- JS mobile menu dùng chung -->
    <script src="/assets/js/mobile-menu.js" defer></script>

    <!-- JS RIÊNG CHO TỪNG MODULE (CHỈ LOAD KHI CẦN) -->
    <?php if (isset($viewName)): ?>
        <?php if (strpos($viewName, 'ar/') === 0): ?>
            <script src="/assets/js/ar-engine.js" defer></script>
        <?php endif; ?>
        <?php if ($viewName === 'contact/index'): ?>
            <script src="/assets/js/contact.js" defer></script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Scroll reveal dùng chung toàn site: hiện dần phần tử .reveal khi cuộn tới -->
    <script>
        (function() {
            'use strict';
            var targets = document.querySelectorAll('.reveal');
            if (!targets.length) return;
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1
            });
            targets.forEach(function(el) {
                observer.observe(el);
            });
        })();
    </script>

</body>

</html>