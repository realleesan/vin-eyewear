<?php

/**
 * home/index.php — trang chủ.
 *
 * Dựng theo "Vin Eyewear Home.dc.html" (Claude Design), CHỈ mười khối của bản
 * thiết kế:
 *
 *   hero → danh mục → bộ sưu tập → sản phẩm mới về → bán chạy
 *   → kiểm tra 5 phút → cắt lắp tròng → đo mắt → đánh giá → kêu gọi hành động
 *
 * BỐN KHỐI ĐẦU KHÔNG THEO THỨ TỰ CỦA BẢN THIẾT KẾ. Bản thiết kế xếp
 * bộ sưu tập → mới về → danh mục → bán chạy; ở đây danh mục lên trước.
 * Bốn khối này là bốn cách chọn hàng khác nhau, xếp từ RỘNG xuống HẸP:
 *
 *   danh mục     "bạn cần loại kính gì" — sáu lối vào phủ hết kho hàng
 *   bộ sưu tập   "theo phong cách nào"  — vài chủ đề đã tuyển sẵn
 *   mới về       hàng mới nhất
 *   bán chạy     hàng người khác đang mua
 *
 * Khách chưa biết mình muốn gì thì câu hỏi đầu tiên là loại kính, không phải
 * bộ sưu tập nào — nên danh mục đứng ngay dưới hero.
 *
 * ĐỔI SO VỚI BẢN TRƯỚC (tám khối): thêm "bộ sưu tập" và "sản phẩm mới về",
 * và khối "chọn theo khuôn mặt" (_layout/home/style-guide.php) nhường chỗ cho
 * "kiểm tra 5 phút" — cùng vị trí, nhưng từ ba tấm ảnh dẫn sang bộ lọc thành
 * ba việc khách tự làm được ngay tại đây.
 *
 * CHÍN PARTIAL NGOÀI BẢN THIẾT KẾ ĐÃ XOÁ HẲN (authority, brands, press,
 * newsletter, ar-tryon, lens-partners, lens-spotlight, face-shape-guide,
 * trust-bar) cùng CSS và config/brands.php của chúng. Trước đây chúng nằm lại
 * trong thư mục mà không nơi nào include — xem lịch sử git nếu cần dựng lại.
 *
 * Header và footer KHÔNG nằm trong phạm vi trang này — chúng là partial dùng
 * chung cho mọi trang (_layout/header.php, _layout/footer.php).
 */
?>

<?php partial('_layout/home/hero', ['promo' => $promo]); ?>

<?php partial('_layout/home/categories', ['categories' => $categories]); ?>

<?php partial('_layout/home/collections'); ?>

<?php partial('_layout/home/new-arrivals', ['products' => $newArrivals]); ?>

<?php partial('_layout/home/best-sellers', ['products' => $bestSellers]); ?>

<?php partial('_layout/home/quick-check'); ?>

<?php partial('_layout/home/lenses'); ?>

<?php partial('_layout/home/eye-exam'); ?>

<?php partial('_layout/home/reviews'); ?>

<?php partial('_layout/home/cta'); ?>
