<?php

/**
 * auth/account/da-luu.php — mục "Đã lưu" (/tai-khoan?muc=da-luu).
 *
 * Danh sách mặt hàng khách bấm dấu trang ở trang chi tiết sản phẩm. Mới lưu
 * nằm trước; mặt hàng cửa hàng đã ẩn thì rơi khỏi danh sách nhưng dòng lưu vẫn
 * còn trong CSDL — xem FavoriteModel::danhSach().
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO KHÔNG DÙNG _layout/product-card.php
 *
 * Thẻ sản phẩm dùng chung là thẻ để MUA: nó mang hai nút "Mua ngay / Thêm vào
 * giỏ" nổi trên ảnh, và không có chỗ nào để bỏ lưu. Mục này cần đúng một thao
 * tác khác hẳn — gỡ khỏi danh sách — nên nó có thẻ riêng, gọn hơn, cùng ngôn
 * ngữ với các thẻ khác của trang tài khoản.
 *
 * Đường sang trang chi tiết vẫn còn (bấm vào ảnh hoặc tên), và mọi việc mua
 * bán diễn ra ở đó.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NÚT "BỎ LƯU" LÀ FORM POST, KHÔNG PHẢI LIÊN KẾT
 *
 * Cùng đường /yeu-thich với nút ở trang chi tiết — một công tắc, một cửa. Ô ẩn
 * `back` đưa khách về đúng mục này thay vì nhảy sang trang sản phẩm vừa bỏ.
 *
 * KHÔNG hỏi lại trước khi bỏ: thao tác này không mất gì (bấm lại dấu trang ở
 * trang sản phẩm là có lại ngay), mà hộp thoại xác nhận cho một việc vô hại thì
 * lần thứ ba người ta bấm "Đồng ý" mà không đọc nữa — rồi tới lúc gặp hộp thoại
 * huỷ đơn hàng, họ cũng bấm như thế.
 *
 * Nhận qua sectionData():
 *   $saved   — mảng dòng sản phẩm đã giải mã (có thể rỗng)
 *   $luuDuoc — CSDL đã có bảng `favorites` chưa
 */

$saved   = $saved   ?? [];
$luuDuoc = $luuDuoc ?? false;

/* Đường về sau khi bấm "Bỏ lưu". Gõ thẳng chứ không currentUrlWithout(): mục
   này không có tham số phụ nào cần giữ, và một hằng thì không phụ thuộc vào
   việc khách tới đây bằng liên kết nào. */
$quayVe = '/tai-khoan?muc=da-luu';
?>

<div class="acct-head acct-head--row">
    <div>
        <h1 class="acct-head__title">Đã lưu</h1>
        <p class="acct-head__lead">Những mẫu bạn đánh dấu để xem lại.</p>
    </div>
    <a class="acct-btn" href="/san-pham">Xem thêm sản phẩm</a>
</div>

<?php if (!$luuDuoc): ?>

    <?php /* Bảng chưa dựng (máy chưa chạy migration). Nói thẳng là tính năng
             đang tạm ngưng — một danh sách rỗng ở đây sẽ đọc thành "bạn chưa
             lưu gì", và khách đã lưu vài món sẽ tưởng mình mất dữ liệu. */ ?>
    <div class="acct-empty">
        <span class="acct-empty__title">Tính năng đang tạm ngưng</span>
        <span class="acct-empty__lead">Danh sách đã lưu sẽ trở lại trong ít phút nữa. Không có mục nào của bạn bị mất.</span>
    </div>

<?php elseif ($saved === []): ?>

    <div class="acct-empty">
        <span class="acct-empty__ring" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#b0736a"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 3h12a1 1 0 0 1 1 1v17l-7-5-7 5V4a1 1 0 0 1 1-1z"></path>
            </svg>
        </span>
        <span class="acct-empty__title">Chưa lưu mẫu nào</span>
        <span class="acct-empty__lead">Mở một sản phẩm bạn thích rồi bấm “Lưu sản phẩm” — nó sẽ nằm ở đây.</span>
        <a class="acct-empty__cta" href="/san-pham">Xem sản phẩm</a>
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
                    <a class="acct-saved__name notranslate" translate="no" lang="vi"
                       href="<?= e($url) ?>"><?= e($p['name']) ?></a>

                    <span class="acct-saved__price">
                        <?php if ($gach !== null && $gach > $gia): ?>
                            <s><?= money($gach) ?></s>
                        <?php endif; ?>
                        <b><?= money($gia) ?></b>
                    </span>

                    <?php if (!$conHang): ?>
                        <span class="acct-saved__out">Tạm hết hàng</span>
                    <?php endif; ?>
                </div>

                <form method="post" action="/yeu-thich">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
                    <input type="hidden" name="back" value="<?= e($quayVe) ?>">

                    <?php /* Tên hàng nhắc lại trong .sr-only: cả danh sách có
                             nhiều nút giống hệt nhau, và trình đọc màn hình
                             duyệt riêng danh sách nút thì "Bỏ lưu · Bỏ lưu ·
                             Bỏ lưu" không chỉ được cái nào. */ ?>
                    <button type="submit" class="acct-saved__drop">
                        Bỏ lưu<span class="sr-only"> — <?= e($p['name']) ?></span>
                    </button>
                </form>

            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>
