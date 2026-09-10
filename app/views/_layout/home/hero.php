<?php

/**
 * _layout/home/hero.php — hero trang chủ (S01): BĂNG VIDEO KÉO ĐƯỢC BẰNG CHUỘT.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * ĐÃ ĐỔI TỪ "MỜ CHỒNG" SANG "BĂNG TRƯỢT" (10/09/2026)
 *
 * Bản trước: ba thẻ <video> chồng khít lên nhau, clip đang chạy để opacity 1,
 * hai clip kia 0; hết một clip thì clip sau mờ lên. Không kéo được, không có
 * nút, mỗi lúc chỉ MỘT clip được nạp và giải mã.
 *
 * Nay: ba clip nằm CẠNH NHAU trên một băng dài gấp ba bề ngang, kéo bằng
 * chuột / vuốt bằng ngón tay để sang tấm khác, mỗi tấm mang tên bộ sưu tập
 * và HAI nút — "Mua ngay" vào thẳng danh sách hàng của bộ ấy, "Khám phá bộ
 * sưu tập" sang trang giới thiệu của chính bộ ấy.
 *
 * ┌─ CÁI GIÁ CỦA BĂNG TRƯỢT, GHI LẠI ĐỂ NGƯỜI SAU KHÔNG PHẢI ĐO LẠI ─────────
 * │ Chú thích của bản trước đã nêu đúng: băng trượt bắt trình duyệt giữ BA
 * │ khung hình video sống cùng lúc, còn mờ chồng thì "chỉ đổi một con số".
 * │ Ba file cộng lại 24,2 MB và cả ba nay đều preload="auto".
 * │
 * │ Đây là lựa chọn CÓ CÂN NHẮC, không phải sơ suất: chủ dự án đã được báo
 * │ về chi phí này và chọn phương án "cả ba cùng phát" để giống đúng tham
 * │ chiếu. Phương án còn lại — chỉ tấm đang xem mới phát, hai tấm bên cạnh
 * │ đứng ở poster — vẫn kéo được y hệt mà giữ nguyên chi phí giải mã của bản
 * │ cũ. Muốn quay về nó thì sửa đúng ba chỗ: bỏ `autoplay` ở hai clip sau,
 * │ trả chúng về preload="none" + data-lazy-src, và trong home.js chỉ gọi
 * │ play() cho clip tại chỉ số đang hiện.
 * │
 * │ Trên máy yếu hoặc mạng chậm, triệu chứng sẽ là: hero đứng hình vài giây
 * │ rồi mới vào chuyển động, và cuộn trang khựng trong lúc ba luồng còn đang
 * │ giải mã. Nếu thấy đúng vậy thì đó là chỗ này, không phải content-visibility
 * │ ở components/home-sections.css.
 * └──────────────────────────────────────────────────────────────────────────
 *
 * KHÔNG JAVASCRIPT VẪN DÙNG ĐƯỢC. Băng không có transform nào từ máy chủ, mà
 * .vhero__viewport thì overflow:hidden — nên tấm đầu chiếm trọn khung và hai
 * tấm sau nằm ngoài mép, đúng như một hero tĩnh. Hai nút của tấm đầu là thẻ
 * <a> thật, bấm được. Chỉ là không sang được tấm hai.
 *
 * poster: khung hình đầu để lấp chỗ trong lúc video còn đang giải mã. Một thẻ
 * <video> chưa giải mã xong thì vẽ ra một ô ĐEN — chính là cú nháy đen mà
 * poster sinh ra để chặn.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/*
 * Ba clip theo thứ tự trượt.
 *
 * 'bst' là SLUG bộ sưu tập, và nó là thứ nối tấm hero với hai nút: nút thứ
 * nhất đi /san-pham?collection=<slug>, nút thứ hai đi /bo-suu-tap/<slug>.
 * Đổi thứ tự clip thì hai nút đi theo, không phải sửa chỗ nào khác.
 *
 * 'ten' viết thẳng ở đây chứ không truy vấn `collections`: hero là khối biên
 * tập, ba clip này được quay riêng cho ba bộ ấy nên cặp video–tên là cố định.
 * Nối vào CSDL thì đổi tên bộ trong trang quản trị sẽ làm lệch chữ khỏi hình.
 * Có lang="vi" vì đây là tên riêng tiếng Việt, không dịch — cùng quy ước với
 * tên sản phẩm ở _layout/product-card.php.
 */
$clips = [
    [
        'src'    => 'assets/video/main_pc_1920_990.mp4',
        'poster' => 'assets/images/hero-models.jpg',
        'bst'    => 'titan-sieu-nhe',
        'ten'    => 'Titan Siêu Nhẹ',
        'label'  => t('home.hero.cap3'),
    ],
    [
        'src'    => 'assets/video/main_global_pc_1920_990.mp4',
        'poster' => 'assets/images/showroom-frames.jpg',
        'bst'    => 'acetate-thu-cong',
        'ten'    => 'Acetate Thủ Công',
        'label'  => t('home.hero.cap1'),
    ],
    [
        'src'    => 'assets/video/main_0_pc_1920_990.mp4',
        'poster' => 'assets/images/hero-eyewear.jpg',
        'bst'    => 'phi-cong-co-dien',
        'ten'    => 'Phi Công Cổ Điển',
        'label'  => t('home.hero.cap2'),
    ],
];

$tong = count($clips);
?>

<section class="vhero" data-section="s01" data-video-hero
         aria-roledescription="carousel" aria-labelledby="hero-title">

    <?php
    /* <h1> ẨN BẰNG .sr-only, KHÔNG BỎ ĐI.
       Mỗi trang cần đúng một <h1>, và trang chủ thì <h1> ấy phải nói website
       này là gì — không phải tên bộ sưu tập đang tình cờ trượt tới. Bản trước
       in nó ra to giữa hero; nay hero theo lối tham chiếu (chỉ tên bộ + hai
       nút) nên câu ấy chuyển thành chữ dành cho trình đọc màn hình và cho
       công cụ tìm kiếm. aria-labelledby ở trên trỏ vào đây. */
    ?>
    <h1 id="hero-title" class="sr-only">
        <?= e(t('home.hero.title_1')) ?> <?= e(t('home.hero.title_2')) ?>
    </h1>

    <?php
    /* Khung cắt. overflow:hidden ở đây là thứ giữ hai tấm sau nằm ngoài mép —
       và cũng là thứ làm hero vẫn đúng khi tắt JavaScript. */
    ?>
    <div class="vhero__viewport">
        <div class="vhero__track" data-vhero-track>
            <?php foreach ($clips as $i => $clip): ?>
                <article class="vhero__slide<?= $i === 0 ? ' is-on' : '' ?>"
                         role="group" aria-roledescription="slide"
                         aria-label="<?= e(t('home.hero.slide_of', [
                             ':n'    => (string) ($i + 1),
                             ':tong' => (string) $tong,
                         ])) ?>"
                         <?= $i === 0 ? '' : 'inert' ?>>

                    <?php
                    /* aria-hidden: video là NỀN TRANG TRÍ, nghĩa của tấm nằm ở
                       chữ bên dưới. Cũng vì thế không có <track> phụ đề —
                       không có lời nào để chép ra.

                       CẢ BA đều autoplay + preload="auto": đây là phương án
                       "cả ba cùng phát" — xem khối chú thích đầu file. */
                    ?>
                    <video class="vhero__clip"
                           autoplay muted loop playsinline preload="auto"
                           poster="<?= e(asset($clip['poster'])) ?>"
                           aria-hidden="true" tabindex="-1"><source
                            src="<?= e(asset($clip['src'])) ?>" type="video/mp4"></video>

                    <?php /* Lớp phủ tối chuyển dần từ dưới lên: chữ trắng đặt
                             thẳng lên video thì độ đọc được đổi theo từng khung
                             hình. Dải này khoá sàn tương phản ở đúng vùng có chữ. */ ?>
                    <div class="vhero__veil" aria-hidden="true"></div>

                    <div class="vhero__copy">
                        <p class="vhero__eyebrow"><?= e($clip['label']) ?></p>
                        <p class="vhero__name" lang="vi"><?= e($clip['ten']) ?></p>

                        <div class="vhero__cta">
                            <?php
                            /* HAI ĐÍCH KHÁC NHAU, và đó là chủ ý:
                                 Mua ngay   → danh sách hàng ĐÃ LỌC theo bộ này
                                 Khám phá   → trang kể chuyện của chính bộ ấy
                               Cùng một bộ sưu tập, hai ý định mua khác nhau. */
                            ?>
                            <a class="vhero__btn vhero__btn--solid"
                               href="/san-pham?<?= e(http_build_query(['collection' => $clip['bst']])) ?>">
                                <?= e(t('home.hero.cta_buy')) ?>
                            </a>
                            <a class="vhero__btn"
                               href="/bo-suu-tap/<?= e(rawurlencode($clip['bst'])) ?>">
                                <?= e(t('home.hero.cta_shop')) ?>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    /* Vạch tiến độ — <button> thật, không phải <span>: chúng bấm được và Tab
       tới được, nên người không dùng chuột vẫn sang tấm khác được mà không
       cần kéo. home.js gắn hành vi; tắt JavaScript thì chúng bị ẩn hẳn bằng
       CSS (html:not(.js)) chứ không nằm đó làm nút chết. */
    ?>
    <div class="vhero__bars" data-vhero-bars role="tablist"
         aria-label="<?= e(t('home.hero.eyebrow')) ?>">
        <?php foreach ($clips as $i => $clip): ?>
            <button type="button" class="vhero__bar<?= $i === 0 ? ' is-on' : '' ?>"
                    role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    data-vhero-go="<?= $i ?>"
                    aria-label="<?= e(t('home.hero.slide_of', [
                        ':n'    => (string) ($i + 1),
                        ':tong' => (string) $tong,
                    ])) ?>"><span class="vhero__bar-fill"></span></button>
        <?php endforeach; ?>
    </div>
</section>
