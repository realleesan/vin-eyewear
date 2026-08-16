<?php

/**
 * home/index.php — trang chủ.
 *
 * Dựng đúng theo "Vin Eyewear Home.dc.html" (Claude Design), CHỈ tám khối
 * của bản thiết kế, theo đúng thứ tự của nó:
 *
 *   hero → danh mục → bán chạy → chọn theo khuôn mặt → cắt lắp tròng
 *   → đo mắt → đánh giá → kêu gọi hành động
 *
 * Khối "số liệu" (_layout/home/authority.php) đã gỡ khỏi đây: bản thiết kế
 * không có nó. File partial và CSS của nó vẫn còn nhưng không được include.
 *
 * Header và footer KHÔNG nằm trong phạm vi trang này — chúng là partial dùng
 * chung cho mọi trang (_layout/header.php, _layout/footer.php) và đã sẵn đúng
 * dáng của bản thiết kế.
 */
?>

<?php partial('_layout/home/hero'); ?>

<?php partial('_layout/home/categories', ['categories' => $categories]); ?>

<?php partial('_layout/home/best-sellers', ['products' => $bestSellers]); ?>

<?php partial('_layout/home/style-guide'); ?>

<?php partial('_layout/home/lenses'); ?>

<?php partial('_layout/home/eye-exam'); ?>

<?php partial('_layout/home/reviews'); ?>

<?php partial('_layout/home/cta'); ?>
