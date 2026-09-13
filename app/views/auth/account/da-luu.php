<?php

/**
 * auth/account/da-luu.php — LƯỚI YÊU THÍCH, dùng chung cho hai màn.
 *
 * Dựng 1:1 từ "Wishlist.dc.html" — bản thiết kế THỨ HAI, dự án
 * aac1974c-accf-4a00-ba8f-97da249ca461 (13/09/2026). Bản trước
 * (68da5130-…) đã bị thay hẳn: lưới bốn cột nay là những TẤM THẺ NỀN XÁM
 * dính nhau bằng khe 2px, ảnh nằm ngang 3/2 ở giữa thẻ, chữ dồn xuống chân.
 *
 *   nền trang   #f4f4f4      thẻ       #f7f7f7     khe lưới   2px
 *   ảnh         80% × 3/2, contain, đệm 40px 24px 24px
 *   chữ         13px / 1.4, đệm 8px 24px 40px 28px
 *   nút mua     #e6e6e6, bo 3px, 11px, đệm 8px 10px, rê → nền #111 chữ trắng
 *   dấu trang   tam giác ĐẶC 14×20
 *   nút lật ảnh nền trắng, bo 4px, 13px, đệm 10px 14px, đổ bóng nhạt
 *
 * ═════════════════════════════════════════════════════════════════════════
 * BỐN CHỖ CỐ Ý KHÔNG CHÉP NGUYÊN MẪU. Đọc trước khi "sửa cho giống":
 *
 *   1. NÚT HÀNH ĐỘNG không phải lúc nào cũng là "Thêm vào giỏ".
 *      Mẫu chỉ có hai cảnh: còn hàng → Add to Bag, hết hàng → không nút.
 *      Kho này có cảnh thứ ba: mặt hàng CÓ PHƯƠNG ÁN (màu gọng, chiết suất).
 *      CartController::add() từ chối một mặt hàng có phương án mà không kèm
 *      phương án nào, nên một nút "Thêm vào giỏ" ở đây sẽ đá khách sang
 *      trang chi tiết kèm dòng báo lỗi — trông y như trang hỏng. Nhãn nay
 *      nói đúng việc sẽ xảy ra, và nút vẫn ĐÚNG DÁNG của mẫu. Thẻ sản phẩm
 *      dùng chung đã gặp đúng lỗi ấy và đã sửa theo lối này.
 *
 *   2. HẾT HÀNG: mẫu in "Sold out" thay chỗ giá rồi bỏ trống ô nút. Làm
 *      đúng thế — nhưng như vậy lối đăng ký CHỜ HÀNG biến khỏi màn này. Nó
 *      vẫn còn ở trang chi tiết (bấm tên hàng là tới). Bản trước để một nút
 *      "Báo khi có hàng" ở đây; mẫu mới không có, và ô nút cao 52px của mẫu
 *      cố ý để trống.
 *
 *   3. TIỀN: mẫu viết "đ 8,451,400" — ký hiệu ĐỨNG TRƯỚC, cỡ nhỏ, và nhóm
 *      số bằng dấu phẩy. Ở đây lấy DÁNG của mẫu (ký hiệu nhỏ đứng trước)
 *      nhưng CHỮ SỐ thì vẫn của money(): "8.451.400". Vì sao không chép cả
 *      dấu phẩy — cùng một chiếc kính sẽ hiện "8,451,400" ở đây và
 *      "8.451.400" ở giỏ hàng, trang chi tiết, hoá đơn. Hai con số trông
 *      khác nhau cho cùng một món là thứ khách đếm lại bằng tay.
 *      Phần chữ số CẮT RA TỪ money() chứ không gọi number_format lần nữa:
 *      money() là nguồn duy nhất, đổi cách nhóm số ở đó thì chỗ này đi theo.
 *
 *   4. NÚT LẬT ẢNH chỉ hiện khi THẬT SỰ có ảnh thứ hai. Cột `images` là một
 *      dãy ảnh cửa hàng tải lên — ảnh thứ hai có thể là ảnh người mẫu, cũng
 *      có thể là ảnh cận. Nút vì thế nói "ảnh khác" chứ không hứa là ảnh
 *      người mẫu, và nó VẮNG MẶT khi cả lưới không mặt hàng nào có ảnh thứ
 *      hai — thà thiếu một cái nút còn hơn một cái nút bấm vào không đổi gì.
 *
 * ĐÃ GỠ so với bản trước: dãy VẠCH MÀU (.wl__sw) — mẫu mới không có; dòng
 * "Sắp có hàng lại" (.wl__note) — thay bằng chữ "Hết hàng" ở chỗ giá.
 * ═════════════════════════════════════════════════════════════════════════
 *
 * Mặt hàng cửa hàng đã ẩn thì rơi khỏi danh sách nhưng dòng lưu vẫn còn —
 * xem Wishlist::danhSach().
 *
 * THẺ RIÊNG, KHÔNG DÙNG _layout/product-card.php: thẻ dùng chung là thẻ để
 * MUA (hai nút nổi trên ảnh) và không có chỗ cho dấu trang.
 *
 * Nhận vào: $saved, $luuDuoc, $variants, $anhPhu, $tabUrl, $wlTieuDe
 */

$saved    = $saved    ?? [];
$luuDuoc  = $luuDuoc  ?? false;
$variants = $variants ?? [];
$anhPhu   = (bool) ($anhPhu ?? false);

/* ┌─ ĐƯỜNG VỀ CHÍNH MÀN NÀY — nút dấu trang và nút lật ảnh đều cần ───────
   │ NHẬN TỪ NGOÀI, có mặc định (13/09/2026). Cùng một khối markup nay dựng
   │ ở HAI cửa:
   │
   │   /tai-khoan?muc=da-luu   tab trong trang tài khoản (phải đăng nhập)
   │   /yeu-thich              trang riêng, mở được khi CHƯA đăng nhập
   │
   │ Hai cửa vì khách vãng lai nay cũng lưu được nhưng không vào được trang
   │ tài khoản. Dựng bản thứ hai của lưới này là chắc chắn hai bên lệch nhau
   │ sau vài lượt sửa, nên WishlistController chỉ gọi lại đúng file này.
   │
   │ $noi: /yeu-thich KHÔNG có dấu ? sẵn, còn /tai-khoan?muc=da-luu thì có.
   │ Nối cứng bằng '&' như bản cũ sẽ ra '/yeu-thich&anh=nguoi-mau' — một
   │ đường dẫn không tồn tại, và nút lật ảnh thành nút hỏng.
   └──────────────────────────────────────────────────────────────────────── */
$tabUrl = $tabUrl ?? '/tai-khoan?muc=da-luu';
$noi    = str_contains($tabUrl, '?') ? '&' : '?';

/*
 * Dòng phối màu của MỘT mặt hàng — "Bạc / Nâu", đúng dòng thứ hai của mẫu.
 *
 * CHỈ CÒN CHỮ. Bản trước còn trả về cả một dãy MÃ MÀU để vẽ vạch màu dưới
 * ảnh; bản thiết kế mới không có dãy vạch ấy nên phần dựng mã màu đã gỡ —
 * giữ lại một mảng không ai đọc là mời người sau tưởng nó còn dùng.
 *
 * Gộp theo MÃ MÀU chứ không theo biến thể: "Đen bóng" và "Đen nhám" cho ra
 * cùng một mã, in ra "Đen / Đen" là đọc thành hai màu khác nhau.
 */
$mauCua = static function (array $p) use ($variants): string {
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

        $ma[strtolower($hex)] = true;
        $t = trim((string) ($v['color'] ?? ''));

        if ($t !== '') {
            $ten[] = $t;
        }
    }

    /* Lấy hai màu đầu, ngăn bằng " / ". Chưa khai biến thể nào thì lùi về
       cột màu của chính mặt hàng, và vẫn trống thì trả chuỗi rỗng — dòng
       vẫn được in ra để bốn thẻ không so le, xem chú thích tại chỗ. */
    return $ten !== []
        ? implode(' / ', array_slice($ten, 0, 2))
        : trim((string) ($p['color'] ?? ''));
};

/*
 * TIỀN THEO DÁNG CỦA MẪU, CHỮ SỐ CỦA money() — xem điểm 3 ở đầu file.
 *
 * Cắt ký hiệu ra khỏi chuỗi money() trả về thay vì gọi number_format lần
 * nữa: money() là chỗ duy nhất biết cách nhóm số, đổi ở đó thì chỗ này đi
 * theo. money() đổi ký hiệu mà quên chỗ này thì str_ends_with không khớp và
 * hàm trả về NGUYÊN chuỗi — giá vẫn đúng, chỉ mất cái dáng nhỏ ở trước.
 * Hỏng thì hỏng về phía an toàn.
 */
$tienCua = static function ($gia): array {
    $chuoi = money($gia);
    $kyHieu = '₫';

    return str_ends_with($chuoi, $kyHieu)
        ? ['ky' => $kyHieu, 'so' => mb_substr($chuoi, 0, -mb_strlen($kyHieu))]
        : ['ky' => '', 'so' => $chuoi];
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

<?php
/* ┌─ TIÊU ĐỀ CHỈ Ở TRANG TÀI KHOẢN ───────────────────────────────────────
   │ Mẫu mới KHÔNG có dòng tiêu đề: chỗ của nó là hàng tab BAG / WISHLIST,
   │ mà hàng tab ấy chỉ có nghĩa ở /yeu-thich (WishlistController in nó ra —
   │ xem app/views/wishlist/index.php). Trong trang tài khoản thì ngược lại:
   │ mục nào cũng có tiêu đề riêng, bỏ đi là tab "Đã lưu" mở ra không biết
   │ mình đang ở đâu.
   │
   │ Nên tiêu đề nhận từ ngoài, mặc định CÓ. ⚠ Đừng gỡ hẳn "cho giống mẫu":
   │ mẫu vẽ một màn hình đứng riêng, không vẽ một tab trong trang tài khoản.
   └──────────────────────────────────────────────────────────────────────── */
?>
<?php if ($wlTieuDe ?? true): ?>
    <h1 class="wl__title"><?= e(t('wl.title')) ?><sup class="wl__sup"><?= count($saved) ?></sup></h1>
<?php endif; ?>

<?php if (!$luuDuoc): ?>

    <?php /* Bảng chưa dựng: nói thẳng là tạm ngưng — một danh sách rỗng ở đây
             đọc thành "bạn chưa lưu gì", khách đã lưu sẽ tưởng mất dữ liệu. */ ?>
    <div class="acct-empty">
        <p class="acct-empty__text acct-empty__text--luu">Danh sách đã lưu đang tạm ngưng — không mục nào của bạn bị mất.</p>
    </div>

<?php elseif ($saved === []): ?>

    <div class="acct-empty">
        <p class="acct-empty__text acct-empty__text--luu">Bạn chưa có sản phẩm nào trong danh sách yêu thích.</p>
        <a class="acct-btn acct-empty__btn" href="/san-pham/gong-kinh">Tiếp tục mua sắm</a>
    </div>

<?php else: ?>

    <ul class="wl" role="list">
        <?php foreach ($saved as $p): ?>
            <?php
            $url     = '/san-pham/' . rawurlencode($p['slug']);
            $conHang = ProductModel::inStock($p);
            $coPa    = VariantModel::hasVariants($p['id']);
            $mau     = $mauCua($p);
            $tien    = $tienCua(ProductPricing::giaBan($p));

            /* Ảnh: cảnh thường là ảnh đại diện, cảnh "ảnh khác" là ảnh thứ hai
               NẾU mặt hàng này có — không có thì giữ nguyên ảnh đầu, chứ không
               để trống một ô trong lưới. */
            $anh = $p['images'][0] ?? '';

            if ($anhPhu && ($p['images'][1] ?? '') !== '') {
                $anh = $p['images'][1];
            }
            ?>
            <li class="wl__item">

                <?php /* Ô ảnh chiếm HẾT phần trên của thẻ (flex:1) và căn giữa
                         tấm ảnh trong đó — đúng mẫu: ảnh nổi giữa một khoảng
                         xám rộng, không dính mép trên. */ ?>
                <a class="wl__shot" href="<?= e($url) ?>" aria-hidden="true" tabindex="-1">
                    <span class="wl__slot">
                        <?php if ($anh !== ''): ?>
                            <img src="<?= e(asset($anh)) ?>" alt=""
                                 width="600" height="400" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="wl__noimg"><?= e(t('product.no_image')) ?></span>
                        <?php endif; ?>
                    </span>
                </a>

                <div class="wl__body">

                    <div class="wl__top">
                        <div class="wl__info">

                            <a class="wl__name notranslate" translate="no" lang="vi" href="<?= e($url) ?>"><?= e($p['name']) ?></a>

                            <?php /* LUÔN IN RA kể cả khi trống: mặt hàng chưa khai
                                     màu nào mà bỏ hẳn dòng này thì thẻ đó ngắn hơn
                                     ba thẻ bên cạnh một dòng, và bốn cái nút ở chân
                                     thẻ không còn thẳng hàng. Phép đo bắt được đúng
                                     lỗi ấy ở bản trước. */ ?>
                            <span class="wl__color"><?= e($mau) ?></span>

                            <?php if ($conHang): ?>
                                <span class="wl__price">
                                    <?php if ($tien['ky'] !== ''): ?><span class="wl__cur"><?= e($tien['ky']) ?></span> <?php endif; ?><?= e($tien['so']) ?>
                                </span>
                            <?php else: ?>
                                <?php /* Mẫu thay GIÁ bằng chữ "Sold out", không phải
                                         thêm một dòng nữa — xem điểm 2 đầu file. */ ?>
                                <span class="wl__price wl__price--off"><?= e(t('wl.sold_out')) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php /* Dấu trang đang BẬT ở mọi thẻ — cả lưới này là danh
                                 sách đã lưu. Bấm là bỏ lưu. KHÔNG hỏi lại: bỏ lưu
                                 không mất gì, mà hộp thoại cho việc vô hại thì lần
                                 thứ ba người ta bấm "Đồng ý" không đọc. */ ?>
                        <form method="post" action="/yeu-thich/luu">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
                            <input type="hidden" name="back" value="<?= e($tabUrl . ($anhPhu ? $noi . 'anh=nguoi-mau' : '')) ?>">
                            <button type="submit" class="wl__mark" aria-pressed="true">
                                <?php /* Tam giác ĐẶC 14×20 của mẫu, không viền. */ ?>
                                <svg width="14" height="20" viewBox="0 0 14 20" fill="currentColor" aria-hidden="true">
                                    <path d="M0 0h14v20l-7-5-7 5z"></path>
                                </svg>
                                <span class="sr-only"><?= e(t('wl.drop')) ?> — <?= e($p['name']) ?></span>
                            </button>
                        </form>
                    </div>

                    <?php /* Ô NÚT CAO 52px CỐ ĐỊNH, nút dính ĐÁY ô — đúng mẫu.
                             Cao cố định kể cả khi rỗng (hết hàng): thiếu nó thì
                             thẻ hết hàng ngắn hơn ba thẻ bên cạnh, và chân bốn
                             thẻ không còn một đường. */ ?>
                    <div class="wl__act">
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
                        <?php elseif ($conHang): ?>
                            <?php /* Có phương án — xem điểm 1 ở đầu file. Cùng dáng
                                     nút, khác nhãn và khác đích. */ ?>
                            <a class="wl__cta" href="<?= e($url) ?>">
                                <?= e(t('wl.pick')) ?><span class="sr-only"> — <?= e($p['name']) ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($coAnhPhu): ?>
        <div class="wl__foot">
            <a class="wl__view" href="<?= e($tabUrl . ($anhPhu ? '' : $noi . 'anh=nguoi-mau')) ?>">
                <span><?= e($anhPhu ? t('wl.view_main') : t('wl.view_alt')) ?></span>
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor"
                     stroke-width="1.3" aria-hidden="true">
                    <path d="M2 7a5 5 0 0 1 8.5-3.6M12 7a5 5 0 0 1-8.5 3.6"></path>
                    <path d="M10.5 1v2.6H8M3.5 13v-2.6H6"></path>
                </svg>
            </a>
        </div>
    <?php endif; ?>

<?php endif; ?>
