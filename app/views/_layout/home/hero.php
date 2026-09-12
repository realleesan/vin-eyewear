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
/* ┌─ BA TẤM, CẢ BA LÀ VIDEO — KHÔNG CÒN ẢNH TĨNH NÀO (10/09/2026) ──────────
   │ Trước đợt này mỗi tấm mang một `poster` là ảnh chụp của Vin
   │ (hero-models · showroom-frames · hero-eyewear). Poster sinh ra để lấp chỗ
   │ trong lúc video giải mã, nhưng ba ảnh ấy KHÔNG PHẢI khung hình của video
   │ nào cả — nên mỗi lần băng trượt sang tấm mới, mắt bắt được một tấm ảnh
   │ lạ nháy lên rồi biến mất. Đó là "cái ảnh tĩnh" cần bỏ.
   │
   │ Nay chỉ TẤM ĐẦU còn poster, và nó là đúng khung hình đầu của chính video
   │ tấm ấy, nên không ai nhận ra đó là một tấm ảnh. Hai tấm sau bỏ hẳn
   │ poster: chúng nằm ngoài mép màn hình, tới lúc trượt tới thì video đã tải
   │ xong từ lâu (cả ba đều preload="auto"), không có chỗ nào để lấp.
   │
   │ Bỏ poster cho tấm đầu luôn thì được cái sạch nhưng mất cái quan trọng
   │ hơn: đó là hình đầu tiên người xem thấy khi vào trang, và trong quãng
   │ video chưa giải mã thì chỗ ấy là một ô đen.
   │
   │ Ô THỨ TƯ ĐÃ BỎ. Nó là chỗ giữ sẵn cho một video chưa có file; nay chốt
   │ đúng ba tấm nên không cần nữa. Khối lọc "bỏ qua clip thiếu file" bên dưới
   │ thì GIỮ — nó vẫn là thứ chặn một <video> trỏ vào 404 dựng ra ô đen câm.
   └──────────────────────────────────────────────────────────────────────── */
$clips = [
    [
        'src'    => 'assets/video/main_pc_1920_990.mp4',
        'poster' => 'assets/images/hero-poster.jpg',
        'label'  => t('home.hero.cap3'),
    ],
    [
        'src'    => 'assets/video/main_global_pc_1920_990.mp4',
        'poster' => null,
        'label'  => t('home.hero.cap1'),
    ],
    [
        'src'    => 'assets/video/main_0_pc_1920_990.mp4',
        'poster' => null,
        'label'  => t('home.hero.cap2'),
    ],
];

/* ┌─ BỎ QUA CLIP THIẾU FILE ────────────────────────────────────────────────
   │ Một <video> trỏ vào đường dẫn 404 không báo lỗi gì cả: nó dựng ra một ô
   │ đen câm nằm giữa băng trượt, và vạch tiến độ vẫn đếm nó như một tấm thật.
   │ Lọc ở đây thì danh sách bên dưới, home.js và vạch tiến độ tự khớp nhau.
   └──────────────────────────────────────────────────────────────────────── */
$clips = array_values(array_filter(
    $clips,
    static fn (array $c): bool => is_file(ROOT_PATH . '/' . $c['src'])
));

/* ┌─ SLUG BỘ SƯU TẬP LẤY TỪ CSDL, KHÔNG VIẾT CỨNG NỮA (10/09/2026) ─────────
   │ Bản trước viết cứng 'titan-sieu-nhe' / 'acetate-thu-cong' /
   │ 'phi-cong-co-dien' ngay trong mảng trên. Không bộ nào trong ba cái đó tồn
   │ tại: bảng `collections` đang có 'nang-he', 'co-dien-tro-lai',
   │ 'nhe-ca-ngay'. Hậu quả đo được trên trang thật:
   │     "Khám phá bộ sưu tập" → /bo-suu-tap/titan-sieu-nhe   → 404
   │     "Mua ngay"            → /san-pham?collection=…       → lọc rỗng
   │ Cả hai nút của cả ba tấm đều hỏng, và hỏng im lặng.
   │
   │ Nay ghép lần lượt clip thứ i với bộ đang hiển thị thứ i. Chú thích cũ lo
   │ rằng nối vào CSDL thì "đổi tên bộ trong quản trị sẽ làm lệch chữ khỏi
   │ hình" — đúng, nhưng một cái tên lệch vẫn hơn một cái nút 404, và tên nay
   │ cũng lấy từ CSDL nên nó luôn nói đúng bộ mà nút sẽ dẫn tới.
   │
   │ Clip nào không còn bộ để ghép (nhiều clip hơn bộ) thì hai nút lùi về
   │ trang tổng /bo-suu-tap — vẫn là một đích có thật.
   └──────────────────────────────────────────────────────────────────────── */
$bsts = CollectionModel::visible();

foreach ($clips as $i => &$clip) {
    $bo = $bsts[$i] ?? null;
    $clip['bst'] = $bo['slug'] ?? null;
    $clip['ten'] = $bo['name'] ?? '';
}
unset($clip);

$tong = count($clips);
/* ┌─ TIÊU ĐỀ HERO ────────────────────────────────────────────────────────
   │ Mẫu in đúng một dòng chữ giữa đáy ảnh — "2026 Collection", font Georgia
   │ serif, 22px, IN HOA, giãn .06em. Đây là NGOẠI LỆ DUY NHẤT với luật "cả
   │ site một family sans" của oa.css; xem --font-hero ở đó.
   │
   │ Lấy tên bộ sưu tập của tấm đang hiện, không viết cứng: tấm nào thì tên
   │ ấy, và nút bên dưới dẫn đúng tới bộ đó. Bộ chưa ghép được thì lùi về
   │ nhãn chung, không in một dòng trống đẩy hai nút tụt xuống.
   └──────────────────────────────────────────────────────────────────────── */
?>

<section class="oa-hero oa-hero--full vhero" data-section="s01" data-video-hero
         aria-roledescription="carousel" aria-labelledby="hero-title">

    <?php
    /* Mỗi trang cần đúng một <h1>, và <h1> của trang chủ phải nói website này
       là gì — không phải tên bộ sưu tập đang tình cờ trượt tới. Mẫu vẽ chữ to
       giữa hero là TÊN BỘ, nên câu định danh chuyển thành chữ chỉ dành cho
       trình đọc màn hình và cho máy tìm kiếm. aria-labelledby ở trên trỏ vào
       đây. */
    ?>
    <h1 id="hero-title" class="sr-only">
        <?= e(t('home.hero.title_1')) ?> <?= e(t('home.hero.title_2')) ?>
    </h1>

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
                    /* Video là NỀN TRANG TRÍ — nghĩa của tấm nằm ở chữ bên dưới,
                       nên aria-hidden và không có <track> phụ đề: không có lời
                       nào để chép ra.

                       poster CHỈ in khi tấm ấy có. `poster=""` rỗng không phải
                       là "không có poster": trình duyệt coi chuỗi rỗng là một
                       URL, đi tải chính trang hiện tại rồi bỏ vì không phải ảnh. */
                    ?>
                    <video class="vhero__clip"
                           autoplay muted loop playsinline preload="auto"
                           <?= $clip['poster'] ? 'poster="' . e(asset($clip['poster'])) . '"' : '' ?>
                           aria-hidden="true" tabindex="-1"><source
                            src="<?= e(asset($clip['src'])) ?>" type="video/mp4"></video>

                    <?php /* Lớp phủ tối chuyển dần từ dưới lên. Chữ trắng đặt
                             thẳng lên video thì độ đọc được đổi theo từng khung
                             hình; dải này khoá sàn tương phản ở đúng vùng có chữ.
                             Mẫu không vẽ nó vì ảnh mẫu là ảnh tĩnh đã tối sẵn. */ ?>
                    <div class="vhero__veil" aria-hidden="true"></div>

                    <div class="oa-hero__center vhero__copy">
                        <p class="oa-hero__title" lang="vi">
                            <?= e($clip['ten'] !== '' ? $clip['ten'] : t('home.hero.eyebrow')) ?>
                        </p>

                        <div class="oa-hero__actions">
                            <?php
                            /* Hai đích KHÁC NHAU cho cùng một bộ sưu tập:
                                 Mua ngay  → danh sách hàng ĐÃ LỌC theo bộ này
                                 Chi tiết  → trang kể chuyện của chính bộ ấy */
                            ?>
                            <a class="oa-btn oa-btn--sm oa-btn--strong oa-btn--onimage-solid"
                               href="<?= e($clip['bst']
                                   ? '/san-pham?' . http_build_query(['collection' => $clip['bst']])
                                   : '/san-pham') ?>">
                                <?= e(t('home.hero.cta_buy')) ?>
                            </a>
                            <a class="oa-btn oa-btn--sm oa-btn--strong oa-btn--onimage"
                               href="<?= e($clip['bst']
                                   ? '/bo-suu-tap/' . rawurlencode($clip['bst'])
                                   : '/bo-suu-tap') ?>">
                                <?= e(t('home.hero.cta_shop')) ?>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    /* VẠCH CHỈ TẤM — mẫu vẽ năm vạch 90×1px dưới đáy, vạch đang chạy màu
       trắng đặc, còn lại trắng 40%. Ở đây số vạch bằng số clip THẬT có file,
       nên nó luôn khớp với băng trượt.

       Là <button> thật để bàn phím tới được. home.js gắn hành vi; tắt
       JavaScript thì CSS ẩn hẳn chúng (html:not(.js)) chứ không để lại một
       hàng nút chết. */
    ?>
    <div class="oa-hero__dots vhero__bars" data-vhero-bars role="tablist"
         aria-label="<?= e(t('home.hero.eyebrow')) ?>">
        <?php foreach ($clips as $i => $clip): ?>
            <button type="button" class="oa-hero__dot vhero__bar<?= $i === 0 ? ' is-active is-on' : '' ?>"
                    role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    data-vhero-go="<?= $i ?>"
                    aria-label="<?= e(t('home.hero.slide_of', [
                        ':n'    => (string) ($i + 1),
                        ':tong' => (string) $tong,
                    ])) ?>"></button>
        <?php endforeach; ?>
    </div>
</section>
