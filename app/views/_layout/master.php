<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <!-- Title động từ controller ($title được extract() từ $data trong BaseController) -->
    <title><?= isset($title) ? htmlspecialchars($title) : 'Vin Eyewear - Kính Mắt Cao Cấp' ?></title>

    <meta name="description" content="Vin Eyewear - Cửa hàng kính mắt cao cấp với công nghệ AR và AI">

    <!-- CSS dùng chung toàn site -->
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/layout.css">
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

</body>
</html>
