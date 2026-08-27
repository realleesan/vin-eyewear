<?php

/**
 * collection/_drawer.php — ngăn kéo thông số một mẫu, trượt vào từ mép phải.
 *
 * Nhận qua partial():
 *   $product   dòng `products` đã qua ProductModel::decode
 *   $variants  phương án của mẫu đó (VariantModel::forProduct)
 *   $offer     ưu đãi ra mắt của CẢ BỘ, hoặc null
 *   $dongUrl   địa chỉ khi ĐÓNG ngăn kéo (chính trang này, bỏ ?mau=)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÂY LÀ MỘT TRANG, KHÔNG PHẢI MỘT HỘP THOẠI
 *
 * Ngăn kéo hiện ra vì địa chỉ có ?mau=<slug>, nên nút ✕ và lớp nền mờ đều là
 * thẻ <a> trỏ về chính trang này khi bỏ tham số ấy — không phải nút gọi
 * JavaScript. Tắt JS thì đóng mở vẫn chạy, chỉ là mỗi lần tải lại trang.
 *
 * Chép đúng lối của admin/orders/_drawer.php, và vì cùng một lý do: chi tiết
 * một mẫu có địa chỉ riêng để gửi cho nhau, và nút Lùi của trình duyệt làm
 * đúng việc người dùng chờ đợi.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NGĂN KÉO KẾT THÚC BẰNG ĐƯỜNG SANG TRANG SẢN PHẨM
 *
 * Không có nút thêm-vào-giỏ ở đây, cố ý. Mặt hàng có phương án thì phải chọn
 * phương án trước, mà chỗ chọn phương án là trang sản phẩm — bày một nút mua
 * ở đây nghĩa là hoặc dựng lại cả khối chọn phương án (thành bản sao thứ hai
 * của trang sản phẩm), hoặc để khách bấm rồi bị từ chối. Xem CartController::add.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$size    = EyewearSpecs::size($product);
$coKey   = EyewearSpecs::sizeKey($product);
$loai    = EyewearSpecs::typeLabel($product);

$nhomGong  = EyewearSpecs::frameRows($product);
$nhomCo    = EyewearSpecs::sizeRows($product);
$nhomTrong = EyewearSpecs::lensRows($product);
$nhomGia   = EyewearSpecs::priceRows($product, $offer ?? null);
$chips     = EyewearSpecs::chips($product);

/* Phối màu: chỉ những phương án CÓ mã màu. Phương án chiết suất tròng cũng
   nằm trong bảng product_variants nhưng nó không phải một màu — vẽ nó thành
   ô màu xám là nói sai. */
$phoiMau = array_values(array_filter(
    $variants,
    static fn (array $v): bool => preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', (string) ($v['swatch_hex'] ?? '')) === 1
));

/*
 * Ảnh: tối đa 8 ô, và CHỈ ảnh có file thật.
 *
 * ProductModel::image() cố ý trả về một ảnh mặc định khi mặt hàng chưa có ảnh
 * nào — đúng cho thẻ sản phẩm, sai ở đây: lưới tám ô cùng một tấm ảnh mặc định
 * trông như lỗi hiển thị chứ không như "chưa chụp". Nên đọc thẳng cột và tự
 * lọc, giống cách CollectionModel::cover() làm với ảnh bìa.
 */
$anhs = [];

foreach ((array) ($product['images'] ?? []) as $duongDan) {
    if (is_string($duongDan) && $duongDan !== ''
        && is_file(ROOT_PATH . '/' . ltrim($duongDan, '/'))) {
        $anhs[] = $duongDan;
    }
}

$anhs = array_slice($anhs, 0, 8);

/** Một nhóm dòng nhãn => giá trị. Gọi bốn lần nên tách ra thay vì chép bốn lượt. */
$nhom = static function (string $tieuDe, array $rows): void {
    if ($rows === []) {
        return;
    }
    ?>
    <p class="cdraw__group"><?= e($tieuDe) ?></p>
    <dl class="cdraw__rows">
        <?php foreach ($rows as $nhan => $giaTri): ?>
            <div class="cdraw__row">
                <dt><?= e((string) $nhan) ?></dt>
                <dd><?= e((string) $giaTri) ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
    <?php
};
?>

<?php /* Lớp nền mờ là một LIÊN KẾT phủ kín trang: bấm ra ngoài để đóng, đúng
         thói quen của mọi ngăn kéo, mà không cần một dòng JavaScript nào. */ ?>
<a class="cdraw__dim" href="<?= e($dongUrl) ?>" aria-label="Đóng thông số <?= e($product['name']) ?>"></a>

<aside class="cdraw" aria-label="Thông số <?= e($product['name']) ?>">

    <header class="cdraw__head">
        <div class="cdraw__head-text">
            <p class="cdraw__sku"><?= e($product['sku']) ?></p>
            <h2 class="cdraw__name"><?= e($product['name']) ?></h2>
            <p class="cdraw__type">
                <?= $loai !== '' ? e($loai) : e((string) ($product['frame_shape'] ?? '')) ?>
                <?php if ($phoiMau !== []): ?>
                    · <?= count($phoiMau) ?> phối màu
                <?php endif; ?>
            </p>
        </div>
        <a class="cdraw__x" href="<?= e($dongUrl) ?>" aria-label="Đóng">&times;</a>
    </header>

    <div class="cdraw__body">

        <?php if ($anhs !== []): ?>
            <ul class="cdraw__shots" role="list">
                <?php foreach ($anhs as $anh): ?>
                    <li class="cdraw__shot">
                        <img src="<?= e(asset(ProductModel::thumbOf((string) $anh))) ?>" alt=""
                             width="120" height="90" loading="lazy" decoding="async">
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php $nhom('Gọng', $nhomGong); ?>

        <?php if ($size !== '' || $nhomCo !== []): ?>
            <p class="cdraw__group">Kích thước</p>
            <?php if ($size !== ''): ?>
                <p class="cdraw__size">
                    <span class="cdraw__size-num"><?= e($size) ?></span>
                    <?php if ($coKey !== null): ?>
                        <span class="cdraw__size-tag">Cỡ <?= e($coKey) ?></span>
                    <?php endif; ?>
                    <span class="cdraw__size-hint">rộng tròng – cầu – dài càng</span>
                </p>
            <?php endif; ?>
            <?php if ($nhomCo !== []): ?>
                <dl class="cdraw__rows">
                    <?php foreach ($nhomCo as $nhan => $giaTri): ?>
                        <div class="cdraw__row">
                            <dt><?= e((string) $nhan) ?></dt>
                            <dd><?= e((string) $giaTri) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        <?php endif; ?>

        <?php $nhom('Tròng kính', $nhomTrong); ?>

        <?php if ($phoiMau !== []): ?>
            <p class="cdraw__group">Phối màu</p>
            <ul class="cdraw__colors" role="list">
                <?php foreach ($phoiMau as $v): ?>
                    <li class="cdraw__color">
                        <?php /* Mã màu đã qua preg_match ở đầu file trước khi
                                 vào đây — cột này do form quản trị ghi, và một
                                 chuỗi tuỳ ý trong thuộc tính style là lối chèn
                                 CSS. Đừng bỏ phép kiểm đó đi. */ ?>
                        <span class="cdraw__dot" style="background: <?= e($v['swatch_hex']) ?>"></span>
                        <?= e($v['label']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php $nhom('Giá và ưu đãi', $nhomGia); ?>

        <?php if ($chips !== []): ?>
            <p class="cdraw__group">Đi kèm và chứng nhận</p>
            <ul class="cdraw__chips" role="list">
                <?php foreach ($chips as $c): ?>
                    <li class="cdraw__chip"><?= e($c) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <footer class="cdraw__foot">
        <a class="cdraw__go" href="/san-pham/<?= e(rawurlencode($product['slug'])) ?>">Xem trang sản phẩm</a>
        <a class="cdraw__alt" href="/dat-lich">Đặt lịch đo mắt</a>
    </footer>
</aside>
