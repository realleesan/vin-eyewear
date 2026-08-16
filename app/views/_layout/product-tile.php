<?php

/**
 * _layout/product-tile.php — thẻ sản phẩm của LƯỚI DANH MỤC.
 *
 * Dựng theo "Vin Eyewear Category.dc.html": cả thẻ là MỘT liên kết, ảnh cao
 * 220px đặt lọt khung trên nền hồng nhạt, dưới ảnh là thương hiệu · tên · giá.
 * Huy hiệu nổi ở góc trái trên. Rê chuột thì thẻ nhấc 4px.
 *
 * KHÔNG dùng chung _layout/product-card.php, và cũng không thay thế nó:
 *   - product-card.php là thẻ có nút "Mua ngay" / "Chi tiết" / "Thử AR", vẫn
 *     đang dùng ở dải "sản phẩm liên quan" cuối trang chi tiết;
 *   - thẻ ở đây không có nút nào — bản thiết kế cho bấm vào cả thẻ để sang
 *     trang chi tiết, mua bán diễn ra ở đó.
 * Hai bản thiết kế khác nhau cho hai chỗ khác nhau, nên hai file. Cùng lý do
 * mà _layout/home/best-sellers.php đã tự dựng thẻ ngang riêng của nó.
 *
 * Nhận qua partial():
 *   $product — một dòng sản phẩm ĐÃ qua ProductModel (images/specs đã giải mã)
 */

$price   = (int) $product['price'];
$compare = $product['compare_at_price'] !== null ? (int) $product['compare_at_price'] : null;
$percent = discount($price, $compare);
$inStock = ProductModel::inStock($product);
$url     = '/san-pham/' . rawurlencode($product['slug']);

/*
 * Huy hiệu: ưu tiên tình trạng kho, rồi tới mức giảm giá, rồi tới hàng nổi
 * bật. Cùng thứ tự với thẻ ở trang chủ, để một sản phẩm không mang hai nhãn
 * khác nhau ở hai trang. Thẻ không có gì đáng nói thì để trống, đúng như bản
 * thiết kế (`<sc-if value="{{ p.badge }}">`).
 */
$badge = null;
if (!$inStock) {
    $badge = 'Hết hàng';
} elseif ($percent !== null) {
    $badge = '-' . $percent . '%';
} elseif (!empty($product['is_featured'])) {
    $badge = 'Bán chạy';
}
?>

<a class="ptile" href="<?= e($url) ?>">
    <span class="ptile__media">
        <?php if (ProductModel::hasImage($product)): ?>
            <img src="<?= e(ProductModel::image($product)) ?>" alt=""
                 loading="lazy" decoding="async">
        <?php else: ?>
            <?php /* Ô trống thật thà, không mượn ảnh của mặt hàng khác —
                     xem chú thích ở ProductModel::hasImage(). */ ?>
            <span class="ptile__noimg">Chưa có ảnh</span>
        <?php endif; ?>
    </span>

    <?php if ($badge !== null): ?>
        <span class="ptile__badge"><?= e($badge) ?></span>
    <?php endif; ?>

    <span class="ptile__body">
        <span class="ptile__brand"><?= e($product['brand'] ?? 'Vin Eyewear') ?></span>

        <?php /* <h3> trong <a> là hợp lệ (nội dung của <a> "trong suốt") và
                 cần thiết: lưới này là một danh sách sản phẩm, trình đọc màn
                 hình phải nhảy được giữa các mặt hàng bằng phím tiêu đề. */ ?>
        <h3 class="ptile__name"><?= e($product['name']) ?></h3>

        <span class="ptile__price">
            <span class="sr-only">Giá bán </span><?= money($price) ?>
        </span>
    </span>
</a>
