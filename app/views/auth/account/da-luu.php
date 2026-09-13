<?php

/**
 * auth/account/da-luu.php — tab "Đã lưu" (/tai-khoan?muc=da-luu).
 *
 * Dựng 1:1 từ "Wishlist.dc.html" (Claude Design, 13/09/2026): tiêu đề WISHLIST
 * kèm số mũ, lưới BỐN CỘT, mỗi thẻ là ô ảnh 5/6.4 nền #f9f9f9 rồi dãy vạch màu,
 * tên, phối màu, giá, dòng trạng thái, một nút hành động và dấu trang bên phải.
 * Dưới lưới là nút lật chế độ ảnh.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * BA CHỖ CỐ Ý KHÔNG CHÉP NGUYÊN MẪU — cả ba đều vì mẫu hứa thứ trang này không
 * làm được thật. Đọc trước khi "sửa cho giống":
 *
 *   1. NÚT HÀNH ĐỘNG không phải lúc nào cũng là "Add to Bag".
 *      Mẫu chỉ có hai cảnh: còn hàng → Add to Bag, hết hàng → Notify Me. Kho
 *      này có cảnh thứ ba: mặt hàng CÓ PHƯƠNG ÁN (màu gọng, chiết suất tròng).
 *      CartController::add() từ chối một mặt hàng có phương án mà không kèm
 *      phương án nào, nên một nút "Thêm vào giỏ" ở đây sẽ đá khách sang trang
 *      chi tiết kèm một dòng báo lỗi — trông y như trang hỏng. Thẻ sản phẩm
 *      dùng chung đã gặp đúng lỗi ấy và đã sửa theo lối này.
 *
 *      Hết hàng cũng vậy: /san-pham/cho-hang luôn trả khách về TRANG CHI TIẾT,
 *      và nó đòi variant_id khi mặt hàng có phương án. Nên nút ấy là một liên
 *      kết tới đúng khối đăng ký chờ hàng, không phải một POST sẽ bị dội lại.
 *
 *   2. NÚT LẬT ẢNH chỉ hiện khi THẬT SỰ có ảnh thứ hai.
 *      Mẫu gọi hai cảnh là "Product View" / "Model View". Cột `images` của mặt
 *      hàng là một dãy ảnh do cửa hàng tải lên — ảnh thứ hai có thể là ảnh
 *      người mẫu, mà cũng có thể là ảnh cận. Nút vì thế nói "ảnh khác" chứ
 *      không hứa là ảnh người mẫu, và nó VẮNG MẶT khi cả lưới không mặt hàng
 *      nào có ảnh thứ hai — thà thiếu một cái nút còn hơn một cái nút bấm vào
 *      không có gì đổi.
 *
 *   3. DÃY VẠCH MÀU dựng từ biến thể THẬT, không phải năm vạch xám của mẫu.
 *      Mã màu suy ra như ở thẻ sản phẩm (ProductTaxonomy::colorHex, có
 *      swatch_hex thì mã ấy thắng). Mặt hàng chưa khai màu nào thì dãy vắng
 *      mặt — chỗ của nó vẫn được giữ để bốn thẻ không so le nhau.
 * ═════════════════════════════════════════════════════════════════════════════
 *
 * Mặt hàng cửa hàng đã ẩn thì rơi khỏi danh sách nhưng dòng lưu vẫn còn trong
 * CSDL — xem FavoriteModel::danhSach().
 *
 * THẺ RIÊNG, KHÔNG DÙNG _layout/product-card.php: thẻ dùng chung là thẻ để MUA
 * (hai nút nổi trên ảnh) và không có chỗ cho dấu trang lẫn dãy vạch màu.
 *
 * Nhận qua sectionData(): $saved, $luuDuoc, $variants, $anhPhu
 */

$saved    = $saved    ?? [];
$luuDuoc  = $luuDuoc  ?? false;
$variants = $variants ?? [];
$anhPhu   = (bool) ($anhPhu ?? false);

/* Đường về chính tab này — nút dấu trang và nút lật ảnh đều cần. */
$tabUrl = '/tai-khoan?muc=da-luu';

/*
 * Dãy vạch màu + dòng phối màu của MỘT mặt hàng, dựng từ biến thể.
 *
 * Gộp theo MÃ MÀU chứ không theo biến thể: "Đen bóng" và "Đen nhám" cho ra
 * cùng một mã, in hai vạch đen sát nhau trông như lỗi lặp — cùng luật với
 * hàng chấm màu ở thẻ sản phẩm.
 */
$mauCua = static function (array $p) use ($variants): array {
    $ma  = [];
    $ten = [];

    foreach ($variants[$p['id']] ?? [] as $v) {
        $hex = trim((string) ($v['swatch_hex'] ?? ''));

        if ($hex === '' || !preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $hex)) {
            $hex = (string) ProductTaxonomy::colorHex((string) ($v['color'] ?? ''));
        }

        if ($hex === '' || isset($ma[strtolower($hex)])) {
            continue;
        }

        $ma[strtolower($hex)] = $hex;

        $t = trim((string) ($v['color'] ?? ''));

        if ($t !== '') {
            $ten[] = $t;
        }
    }

    return [
        'hex' => array_values($ma),
        /* Dòng dưới tên hàng: mẫu ghi "Silver / Brown". Lấy hai màu đầu, ngăn
           bằng " / ". Chưa khai biến thể nào thì lùi về cột màu của chính mặt
           hàng, và vẫn trống thì bỏ hẳn dòng. */
        'chu' => $ten !== []
            ? implode(' / ', array_slice($ten, 0, 2))
            : trim((string) ($p['color'] ?? '')),
    ];
};

/* Có mặt hàng nào có ảnh thứ hai không — quyết định nút lật ảnh có mặt hay
   không. Xem điểm 2 ở khối chú thích đầu file. */
$coAnhPhu = false;

foreach ($saved as $p) {
    if (count($p['images'] ?? []) > 1) {
        $coAnhPhu = true;
        break;
    }
}
?>

<h1 class="wl__title"><?= e(t('wl.title')) ?><sup class="wl__sup"><?= count($saved) ?></sup></h1>

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

    <ul class="wl" role="list">
        <?php foreach ($saved as $p): ?>
            <?php
            $url     = '/san-pham/' . rawurlencode($p['slug']);
            $gia     = ProductPricing::giaBan($p);
            $conHang = ProductModel::inStock($p);
            $coPa    = VariantModel::hasVariants($p['id']);
            $mau     = $mauCua($p);

            /* Ảnh: cảnh thường là ảnh đại diện, cảnh "ảnh khác" là ảnh thứ hai
               NẾU mặt hàng này có — không có thì giữ nguyên ảnh đầu, chứ không
               để trống một ô trong lưới. */
            $anh = $p['images'][0] ?? '';

            if ($anhPhu && ($p['images'][1] ?? '') !== '') {
                $anh = $p['images'][1];
            }
            ?>
            <li class="wl__item">

                <a class="wl__shot" href="<?= e($url) ?>" aria-hidden="true" tabindex="-1">
                    <?php if ($anh !== ''): ?>
                        <img src="<?= e(asset($anh)) ?>" alt=""
                             width="500" height="640" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="wl__noimg"><?= e(t('product.no_image')) ?></span>
                    <?php endif; ?>
                </a>

                <div class="wl__body">
                    <div class="wl__info">

                        <?php /* Dãy vạch màu. Khối bọc LUÔN có mặt kể cả khi rỗng:
                                 nó cao 8px cố định, bỏ đi thì thẻ không có màu bị
                                 kéo lên cao hơn ba thẻ bên cạnh. */ ?>
                        <div class="wl__sw" aria-hidden="true">
                            <?php foreach (array_slice($mau['hex'], 0, 7) as $hex): ?>
                                <span style="background: <?= e($hex) ?>"></span>
                            <?php endforeach; ?>
                        </div>

                        <a class="wl__name notranslate" translate="no" lang="vi" href="<?= e($url) ?>"><?= e($p['name']) ?></a>

                        <?php /* LUÔN IN RA kể cả khi trống — cùng lý do với dòng
                                 trạng thái bên dưới: mặt hàng chưa khai màu nào mà
                                 bỏ hẳn dòng này thì thẻ đó ngắn hơn ba thẻ bên
                                 cạnh một dòng, và bốn cái nút ở chân thẻ không còn
                                 thẳng hàng. Phép đo bắt được đúng lỗi ấy. */ ?>
                        <span class="wl__color"><?= e($mau['chu']) ?></span>

                        <span class="wl__price<?= $conHang ? '' : ' is-off' ?>"><?= money($gia) ?></span>

                        <?php /* Dòng trạng thái giữ chỗ CẢ KHI TRỐNG (min-height
                                 trong CSS) — đúng `min-height:15px` của mẫu, để
                                 nút bên dưới của bốn thẻ thẳng hàng nhau. */ ?>
                        <span class="wl__note"><?= $conHang ? '' : e(t('wl.restock')) ?></span>

                        <?php if ($conHang && !$coPa): ?>
                            <?php /* Mua thẳng được: không phương án nào để chọn. */ ?>
                            <form method="post" action="/gio-hang/them">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                                <input type="hidden" name="back" value="<?= e($tabUrl) ?>">
                                <button type="submit" class="wl__cta">
                                    <?= e(t('wl.add')) ?><span class="sr-only"> — <?= e($p['name']) ?></span>
                                </button>
                            </form>
                        <?php else: ?>
                            <?php /* Hai cảnh còn lại đều dẫn sang trang chi tiết —
                                     xem điểm 1 ở khối chú thích đầu file. */ ?>
                            <a class="wl__cta" href="<?= e($url) ?><?= $conHang ? '' : '#cho-hang' ?>">
                                <?= e($conHang ? t('wl.pick') : t('wl.notify')) ?><span class="sr-only"> — <?= e($p['name']) ?></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php /* Dấu trang đang BẬT ở mọi thẻ — cả lưới này là danh
                             sách đã lưu. Bấm là bỏ lưu. KHÔNG hỏi lại: bỏ lưu
                             không mất gì, mà hộp thoại cho việc vô hại thì lần
                             thứ ba người ta bấm "Đồng ý" không đọc. */ ?>
                    <form method="post" action="/yeu-thich">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
                        <input type="hidden" name="back" value="<?= e($tabUrl . ($anhPhu ? '&anh=nguoi-mau' : '')) ?>">
                        <button type="submit" class="wl__mark" aria-pressed="true">
                            <svg width="16" height="22" viewBox="0 0 16 22" aria-hidden="true">
                                <path d="M0 0h16v22l-8-6-8 6z" fill="currentColor"
                                      stroke="currentColor" stroke-width="1.5"></path>
                            </svg>
                            <span class="sr-only"><?= e(t('wl.drop')) ?> — <?= e($p['name']) ?></span>
                        </button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($coAnhPhu): ?>
        <div class="wl__foot">
            <a class="wl__view" href="<?= e($tabUrl . ($anhPhu ? '' : '&anh=nguoi-mau')) ?>">
                <span><?= e($anhPhu ? t('wl.view_main') : t('wl.view_alt')) ?></span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                     stroke-width="1.5" aria-hidden="true">
                    <path d="M13 6.5A5.5 5.5 0 0 0 3.3 4.6"></path>
                    <path d="M3 9.5a5.5 5.5 0 0 0 9.7 1.9"></path>
                    <path d="M3 2v3h3"></path>
                    <path d="M13 14v-3h-3"></path>
                </svg>
            </a>
        </div>
    <?php endif; ?>

<?php endif; ?>
