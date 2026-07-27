<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <!-- Cổng an toàn cho scroll reveal: .reveal chỉ bị ẩn (opacity 0) khi
         <html> có class .js-reveal (xem layout.css). Đặt inline trong <head>
         để không nháy nội dung, và tự gỡ ra nếu assets/js/reveal.js không chạy
         được (404, bị chặn, lỗi JS) — nội dung KHÔNG BAO GIỜ ẩn vĩnh viễn vì JS. -->
    <script>
        (function () {
            var root = document.documentElement;
            root.classList.add('js-reveal');
            setTimeout(function () {
                if (typeof window.initReveal !== 'function') root.classList.remove('js-reveal');
            }, 2000);
        })();
    </script>

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
        <?php if ($viewName === 'home/index'): ?>
            <link rel="stylesheet" href="/assets/css/home.css">
        <?php endif; ?>
        <?php if ($viewName === 'contact/index'): ?>
            <link rel="stylesheet" href="/assets/css/contact.css">
        <?php endif; ?>
        <?php if ($viewName === 'product/index'): ?>
            <link rel="stylesheet" href="/assets/css/product.css">
        <?php endif; ?>
        <?php if ($viewName === 'product/detail'): ?>
            <link rel="stylesheet" href="/assets/css/product.detail.css">
        <?php endif; ?>
        <?php if (strpos($viewName, 'errors/') === 0): ?>
            <link rel="stylesheet" href="/assets/css/errors.css">
        <?php endif; ?>
    <?php endif; ?>

    <!-- CSS RIÊNG CHO ABOUT (CHỈ LOAD KHI CẦN) -->
    <?php if (isset($viewName) && $viewName === 'about/index'): ?>
        <link rel="stylesheet" href="/assets/css/about.css">
    <?php endif; ?>

    <!-- CSS RIÊNG CHO EVENT (CHỈ LOAD KHI CẦN) -->
    <?php if (isset($viewName) && (strpos($viewName, 'event/') === 0 || $viewName === 'event/index')): ?>
        <link rel="stylesheet" href="/assets/css/event.css">
    <?php endif; ?>
</head>

<body>

    <!-- Header & Navbar -->
    <?php require_once VIEWS_PATH . '/_layout/header.php'; ?>

    <!-- Nội dung trang con — $viewName được truyền từ BaseController::renderView() -->
    <main class="main-content">
        <?php
        // Capture nội dung view để đọc biến bật/tắt layout component
        ob_start();
        require_once VIEWS_PATH . '/' . $viewName . '.php';
        $viewContent = ob_get_clean();
        ?>

        <!-- Breadcrumb -->
        <?php if (isset($show_breadcrumb) && $show_breadcrumb && !empty($breadcrumb_items)): ?>
            <?php require_once VIEWS_PATH . '/_layout/breadcrumb.php'; ?>
        <?php endif; ?>

        <!-- Page Header -->
        <?php if (isset($show_page_header) && $show_page_header): ?>
            <?php require_once VIEWS_PATH . '/_layout/page-header.php'; ?>
        <?php endif; ?>

        <!-- Nội dung trang -->
        <?= $viewContent ?>

        <!-- CTA -->
        <?php if (isset($show_cta) && $show_cta && !empty($cta_buttons)): ?>
            <?php require_once VIEWS_PATH . '/_layout/cta.php'; ?>
        <?php endif; ?>

        <!-- Pusher (Scroll to Top) -->
        <?php if (!isset($show_pusher) || $show_pusher): ?>
            <?php require_once VIEWS_PATH . '/_layout/pusher.php'; ?>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php require_once VIEWS_PATH . '/_layout/footer.php'; ?>

    <!-- JS mobile menu dùng chung -->
    <script src="/assets/js/mobile-menu.js" defer></script>

    <!-- Scroll reveal dùng chung toàn site — window.initReveal(container), gọi lại
         được sau mỗi lần render động (xem assets/js/reveal.js).
         Nạp defer & đặt TRƯỚC các JS module: script defer chạy đúng thứ tự tài
         liệu nên window.initReveal chắc chắn có sẵn cho product.detail.js. -->
    <script src="/assets/js/reveal.js" defer></script>

    <!-- JS RIÊNG CHO TỪNG MODULE (CHỈ LOAD KHI CẦN) -->
    <?php if (isset($viewName)): ?>
        <?php if (strpos($viewName, 'ar/') === 0): ?>
            <script src="/assets/js/ar-engine.js" defer></script>
        <?php endif; ?>
        <?php if ($viewName === 'product/detail'): ?>
            <script src="/assets/js/product.detail.js" defer></script>
        <?php endif; ?>
        <?php if ($viewName === 'home/index'): ?>
            <script src="/assets/js/home.js" defer></script>
        <?php endif; ?>
        <?php if ($viewName === 'contact/index'): ?>
            <script src="/assets/js/contact.js" defer></script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- JS RIÊNG CHO EVENT (CHỈ LOAD KHI CẦN) -->
    <?php if (isset($viewName) && $viewName === 'event/index'): ?>
        <script src="/assets/js/event.js" defer></script>
    <?php endif; ?>

</body>

</html>