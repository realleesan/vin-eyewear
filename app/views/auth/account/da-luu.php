<?php

/**
 * auth/account/da-luu.php — tab "Đã lưu" (/tai-khoan?muc=da-luu).
 *
 * Màn "Wishlist" của "Ho So Nguoi Dung.dc.html": tiêu đề kèm số mũ, chưa có gì
 * thì một câu + nút TIẾP TỤC MUA SẮM. Có hàng thì lưới thẻ gọn bên dưới tiêu
 * đề — bản thiết kế không vẽ trạng thái này.
 *
 * Mặt hàng cửa hàng đã ẩn thì rơi khỏi danh sách nhưng dòng lưu vẫn còn trong
 * CSDL — xem FavoriteModel::danhSach().
 *
 * THẺ RIÊNG, KHÔNG DÙNG _layout/product-card.php: thẻ dùng chung là thẻ để MUA
 * (hai nút nổi trên ảnh) và không có chỗ bỏ lưu. Mọi việc mua diễn ra ở trang
 * chi tiết — bấm ảnh hoặc tên là tới đó.
 *
 * NÚT "BỎ LƯU" LÀ FORM POST tới /yeu-thich — một công tắc, một cửa với nút ở
 * trang chi tiết. KHÔNG hỏi lại: bỏ lưu không mất gì (bấm dấu trang lại là có),
 * mà hộp thoại cho việc vô hại thì lần thứ ba người ta bấm "Đồng ý" không đọc.
 *
 * Nhận qua sectionData(): $saved, $luuDuoc
 */

$saved   = $saved   ?? [];
$luuDuoc = $luuDuoc ?? false;
?>

<h1 class="acct-title">Đã lưu<sup class="acct-title__sup"><?= count($saved) ?></sup></h1>

<?php if (!$luuDuoc): ?>

    <?php /* Bảng chưa dựng: nói thẳng là tạm ngưng — một danh sách rỗng ở đây
             đọc thành "bạn chưa lưu gì", khách đã lưu sẽ tưởng mất dữ liệu. */ ?>
    <div class="acct-empty">
        <p class="acct-empty__text acct-empty__text--luu">Danh sách đã lưu đang tạm ngưng — không mục nào của bạn bị mất.</p>
    </div>

<?php elseif ($saved === []): ?>

    <div class="acct-empty">
        <p class="acct-empty__text acct-empty__text--luu">Bạn chưa có sản phẩm nào trong danh sách đã lưu.</p>
        <a class="acct-btn acct-empty__btn" href="/san-pham/gong-kinh">Tiếp tục mua sắm</a>
    </div>

<?php else: ?>

    <ul class="acct-saved" role="list">
        <?php foreach ($saved as $p): ?>
            <?php
            $url     = '/san-pham/' . rawurlencode($p['slug']);
            $gia     = ProductPricing::giaBan($p);
            $gach    = ProductPricing::giaGach($p);
            $conHang = ProductModel::inStock($p);
            ?>
            <li class="acct-saved__item">
                <a class="acct-saved__shot" href="<?= e($url) ?>" aria-hidden="true" tabindex="-1">
                    <?php if (ProductModel::hasImage($p)): ?>
                        <img src="<?= e(asset(ProductModel::image($p))) ?>" alt=""
                             width="300" height="300" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="acct-saved__noimg">Chưa có ảnh</span>
                    <?php endif; ?>
                </a>

                <div class="acct-saved__body">
                    <div>
                        <a class="acct-saved__name notranslate" translate="no" lang="vi"
                           href="<?= e($url) ?>"><?= e($p['name']) ?></a>
                        <span class="acct-saved__price">
                            <?= money($gia) ?>
                            <?php if ($gach !== null && $gach > $gia): ?>
                                <s><?= money($gach) ?></s>
                            <?php endif; ?>
                        </span>
                        <?php if (!$conHang): ?>
                            <span class="acct-saved__out">Tạm hết hàng</span>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="/yeu-thich">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
                        <input type="hidden" name="back" value="/tai-khoan?muc=da-luu">
                        <?php /* Tên hàng trong .sr-only: cả danh sách nhiều nút giống
                                 hệt nhau, "Bỏ lưu · Bỏ lưu" không chỉ được cái nào. */ ?>
                        <button type="submit" class="acct-saved__drop">
                            Bỏ lưu<span class="sr-only"> — <?= e($p['name']) ?></span>
                        </button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>
