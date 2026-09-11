<?php
/*
 * ═══════════════════════════════════════════════════════════════════════════
 * TRANG CHỦ — dựng 1:1 từ mẫu "Eyewear Collection"
 *
 * Sáu khối, đúng thứ tự của mẫu:
 *
 *   1. HERO tràn màn hình, chạy lên dưới thanh đầu trang   _layout/home/hero
 *   2. băng "Sản phẩm mới về"      nền #f4f4f4, lưới 4 thẻ
 *   3. băng "Sản phẩm bán chạy"    nền #f4f4f4, lưới 4 thẻ
 *   4. băng "Gọng kính"            nền #f4f4f4, lưới 4 thẻ
 *   5. băng "Tròng kính"           nền #f4f4f4, lưới 4 thẻ
 *   6. khối "Ghé thăm cửa hàng"    nền trắng, chữ trái + ảnh phải
 *
 * BỐN BĂNG DÙNG CHUNG MỘT HÌNH: tiêu đề 13px IN HOA, ngay dưới là một liên
 * kết "More" gạch chân, rồi lưới. Nên chúng dùng chung một vòng lặp thay vì
 * bốn khối chép đi chép lại — bốn bản sao gần-giống-nhau là kiểu lệch dần mà
 * không ai thấy.
 *
 * ───────────────────────────────────────────────────────────────────────────
 * NĂM KHỐI CỦA BẢN CŨ ĐÃ RA KHỎI TRANG CHỦ
 *
 * Năm file partial VẪN NẰM NGUYÊN trên đĩa (_layout/home/collections.php,
 * categories.php, lenses.php, eye-exam.php, quick-check.php, reviews.php) —
 * không file nào bị xoá, nhưng chúng mang markup và lớp CSS của thiết kế cũ
 * nên bật lại thì phải dựng lại theo ngôn ngữ của oa.css trước.
 *
 * LỐI ĐI KHÔNG MẤT THEO: bộ sưu tập vẫn ở hàng nav và /bo-suu-tap; tròng kính
 * ở /san-pham/trong-kinh và băng số 5 ngay dưới đây; đo mắt và kiểm tra nhanh
 * ở /dat-lich; đánh giá ở trang chi tiết từng sản phẩm.
 * ═══════════════════════════════════════════════════════════════════════════
 */
?>
<?php partial('_layout/home/hero'); ?>

<?php
/* Khoá 'items' rỗng thì băng đó không in ra dòng nào — cửa hàng chưa nhập
   tròng kính không phải nhìn một tiêu đề treo trên khoảng trống. */
$bands = [
    ['title' => t('home.band.new'),    'more' => '/san-pham?sap-xep=moi-nhat',  'items' => $newArrivals, 'tone' => 'new'],
    ['title' => t('home.band.best'),   'more' => '/san-pham?sap-xep=ban-chay',  'items' => $bestSellers, 'tone' => 'sale'],
    ['title' => t('nav.frames'),       'more' => '/san-pham/gong-kinh',         'items' => $frames,      'tone' => 'sale'],
    ['title' => t('nav.lenses'),       'more' => '/san-pham/trong-kinh',        'items' => $lenses,      'tone' => 'sale'],
];
?>

<?php foreach ($bands as $bandIndex => $band): ?>
    <?php if ($band['items'] === []) { continue; } ?>

    <section class="oa-band">
        <div class="oa-band__head">
            <h2 class="oa-band-title"><?= e($band['title']) ?></h2>
            <a class="oa-band__more" href="<?= e($band['more']) ?>"><?= e(t('home.band.more')) ?></a>
        </div>

        <?php /* <ul role="list"> chứ không <div>: đây là một DANH SÁCH hàng hoá,
                 và trình đọc màn hình báo "danh sách 4 mục" trước khi đọc mục
                 đầu. role="list" là bắt buộc vì oa.css bỏ list-style, mà
                 Safari/VoiceOver thôi coi <ul> là danh sách khi mất bullet. */ ?>
        <ul class="oa-grid oa-plain" role="list">
            <?php foreach ($band['items'] as $i => $item): ?>
                <?php partial('_layout/product-card', [
                    'product'   => $item,
                    'i'         => $i,
                    'badgeTone' => $band['tone'],
                    /* Biến thể đã gom sẵn MỘT câu ở HomeController — tra mảng,
                       không hỏi CSDL trong vòng lặp. */
                    'variants'  => $variants[$item['id']] ?? [],
                    /* Băng đầu tiên nằm ngay dưới hero: bỏ loading="lazy" cho
                       bốn thẻ ấy, chúng thuộc màn hình đầu ở laptop.

                       So theo CHỈ SỐ, không so cả mảng: hai băng hoàn toàn có
                       thể trùng nội dung (một mặt hàng vừa mới về vừa bán chạy
                       thì hai băng bốn thẻ có thể giống hệt nhau), và lúc đó
                       `$band === $bands[0]` đúng cho cả hai. */
                    'eager'     => $bandIndex === 0,
                ]); ?>
            <?php endforeach; ?>
        </ul>
    </section>

<?php endforeach; ?>

<?php
/* ┌─ GHÉ THĂM CỬA HÀNG ───────────────────────────────────────────────────
   │ Hai cột co giãn: chữ bên trái tối đa 420px, ảnh 4:3 bên phải. Dưới
   │ 600px hai cột tự xếp chồng — `repeat(auto-fit, minmax(300px,1fr))` lo
   │ việc đó, không cần @media nào.
   └──────────────────────────────────────────────────────────────────────── */
?>
<section class="oa-split">
    <div class="oa-split__copy">
        <span class="oa-eyebrow"><?= e(t('home.store.eyebrow')) ?></span>
        <h2 class="oa-title"><?= e(t('home.store.title')) ?></h2>
        <p class="oa-muted"><?= e(t('home.store.lead')) ?></p>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
            <a class="oa-btn oa-btn--solid" href="/dat-lich"><?= e(t('cta.book')) ?></a>
            <a class="oa-btn" href="/lien-he"><?= e(t('home.store.branches')) ?></a>
        </div>
    </div>

    <div class="oa-slot oa-slot--4x3">
        <img src="<?= e(asset('assets/images/showroom-storefront.jpg')) ?>"
             alt="<?= e(t('home.store.alt')) ?>"
             width="1200" height="900" loading="lazy" decoding="async">
    </div>
</section>
