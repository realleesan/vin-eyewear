<?php
/**
 * master.php — Khung layout chung toàn site
 *
 * Nhận 2 biến từ Controller:
 *   $content (string) — HTML nội dung trang con
 *   $title   (string) — Tiêu đề trang, mặc định "VREyewear"
 *
 * Cách Controller dùng file này:
 *   ob_start();
 *   require APP_PATH . '/views/home/index.php';
 *   $content = ob_get_clean();
 *   $title   = 'Trang chủ';
 *   require APP_PATH . '/views/_layout/master.php';
 */
$title = isset($title) ? $title : 'VREyewear';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="VREyewear — Kính mắt chính hãng, phong cách tối giản.">
  <title><?= htmlspecialchars($title) ?> | VREyewear</title>

  <!-- Design system — load trước tất cả CSS trang con -->
  <link rel="stylesheet" href="/assets/css/global.css">
  <link rel="stylesheet" href="/assets/css/layout.css">
</head>
<body>

  <?php require __DIR__ . '/header.php'; ?>

  <main id="main-content">
    <?= $content ?>
  </main>

  <?php require __DIR__ . '/footer.php'; ?>

</body>
</html>