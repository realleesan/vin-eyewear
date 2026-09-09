<?php

/**
 * _layout/home/hero.php — hero trang chủ (S01): BĂNG VIDEO TRÀN MÀN HÌNH.
 *
 * ĐÃ THAY HẲN BĂNG ẢNH CŨ (09/09/2026).
 *
 * Bản trước là bố cục hai cột: nửa trái chữ + hai nút + bộ điều khiển (số thứ
 * tự, hai mũi tên, ba vạch tiến độ), nửa phải băng ba ẢNH trượt ngang, dưới
 * cùng là dải bốn cam kết. Nay hero là MỘT khối video tràn cạnh, không một nút
 * điều khiển nào — đúng lối của trang nhà mốt: thứ đầu tiên chạm vào mắt là
 * HÌNH ẢNH ĐỘNG, không phải một bảng điều khiển.
 *
 * ĐÃ BỎ khỏi khối này: hai mũi tên, ba vạch tiến độ, ô đếm "01 / 03", thẻ chú
 * thích, hai nút CTA cạnh nhau, và dải bốn cam kết. Bộ lớp .hero__* cũ vẫn còn
 * nguyên trong components/home-sections.css — không xoá ở đợt này để còn đối
 * chiếu; nó chỉ thôi được dùng.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * BA VIDEO NỐI NHAU, CHUYỂN BẰNG MỜ CHỒNG
 *
 * Không phải "trượt": ba thẻ <video> chồng khít lên nhau, thẻ đang chạy để
 * opacity 1, hai thẻ kia 0. Hết một clip thì clip sau mờ lên đè lên clip
 * trước. Trượt ngang cần một băng dài gấp ba bề ngang và làm trình duyệt phải
 * giữ ba khung hình video sống cùng lúc; mờ chồng chỉ đổi một con số.
 *
 * KHÔNG `loop` trên từng thẻ: vòng lặp nằm ở cấp danh sách (clip cuối quay về
 * clip đầu), và JS biết được điều đó nhờ sự kiện `ended` — thứ mà thẻ có
 * `loop` không bao giờ bắn ra.
 *
 * TẢI: chỉ clip ĐẦU mang preload="auto"; hai clip sau là preload="none" cho
 * tới khi tới lượt. Ba file cộng lại 24,2 MB — kéo hết ngay khi mở trang là
 * giết trang chủ trên 4G. assets/js/home.js gọi load() cho clip kế tiếp NGAY
 * KHI clip hiện tại bắt đầu chạy, nên nó có trọn thời lượng của clip trước để
 * về kịp.
 *
 * poster: khung hình đầu để lấp chỗ trong lúc video còn đang giải mã, và là
 * thứ DUY NHẤT hiện ra khi máy đặt "giảm chuyển động" — xem khối reduced
 * motion trong components/video-hero.css và nhánh tương ứng trong home.js.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/*
 * Ba clip theo thứ tự chạy. 'poster' dùng ảnh có sẵn trong repo: video chưa có
 * ảnh khung hình đầu riêng, mà một thẻ <video> chưa giải mã xong thì vẽ ra một
 * ô ĐEN — chính là cú nháy đen mà poster sinh ra để chặn.
 *
 * 'label' là chữ hiện ở góc dưới, đổi theo clip. Dùng lại đúng ba khoá caption
 * của băng ảnh cũ nên không phải thêm chuỗi dịch mới.
 */
$clips = [
    [
        'src'    => 'assets/video/main_pc_1920_990.mp4',
        'poster' => 'assets/images/hero-models.jpg',
        'label'  => t('home.hero.cap1'),
    ],
    [
        'src'    => 'assets/video/main_global_pc_1920_990.mp4',
        'poster' => 'assets/images/showroom-frames.jpg',
        'label'  => t('home.hero.cap2'),
    ],
    [
        'src'    => 'assets/video/main_0_pc_1920_990.mp4',
        'poster' => 'assets/images/hero-eyewear.jpg',
        'label'  => t('home.hero.cap3'),
    ],
];
?>

<section class="vhero" data-section="s01" data-video-hero aria-labelledby="hero-title">

    <?php /* aria-hidden trên cả sân khấu: ba video là NỀN TRANG TRÍ, không mang
             thông tin nào mà chữ bên dưới chưa nói. Không có nó thì trình đọc
             màn hình phải lội qua ba điều khiển media vô danh trước khi tới
             tiêu đề trang. Cũng vì thế chúng không có <track> phụ đề: không có
             tiếng, không có lời nào để chép lại. */ ?>
    <div class="vhero__stage" aria-hidden="true">
        <?php foreach ($clips as $i => $clip): ?>
            <?php
            /* muted + playsinline là ĐIỀU KIỆN BẮT BUỘC để autoplay chạy trên
               iOS và trên Chrome — thiếu một trong hai là trình duyệt chặn, và
               chặn im lặng. disablepictureinpicture + controlslist chặn nốt
               những lối mà trình duyệt tự mọc ra một nút điều khiển. */
            ?>
            <video
                class="vhero__clip<?= $i === 0 ? ' is-on' : '' ?>"
                <?= $i === 0 ? 'autoplay preload="auto"' : 'preload="none"' ?>
                muted
                playsinline
                disablepictureinpicture
                controlslist="nodownload noplaybackrate noremoteplayback"
                poster="<?= e(asset($clip['poster'])) ?>"
                width="1920" height="990"
                <?= $i === 0 ? '' : 'data-lazy-src="' . e(asset($clip['src'])) . '"' ?>
            ><?php if ($i === 0): ?><source src="<?= e(asset($clip['src'])) ?>" type="video/mp4"><?php endif; ?></video>
        <?php endforeach; ?>
    </div>

    <?php /* Lớp phủ tối chuyển dần từ dưới lên. Chữ trắng đặt thẳng lên video
             thì độ tương phản đổi theo từng khung hình — có khung đọc được, có
             khung không. Lớp này khoá sàn tương phản lại ở vùng có chữ mà không
             làm tối cả tấm hình. */ ?>
    <div class="vhero__veil" aria-hidden="true"></div>

    <?php /* .reveal — cụm chữ mờ lên SAU khi hero đã vào khung nhìn, không hiện
             sẵn từ khung hình đầu. Cặp .reveal/.visible do đoạn IntersectionObserver
             dùng chung ở cuối _layout/master.php lo; nó cũng tự bỏ qua khi máy đặt
             giảm chuyển động, và chỉ ẩn khi <html> có lớp .js nên tắt JavaScript
             thì chữ vẫn hiện. */ ?>
    <div class="vhero__copy reveal">
        <p class="vhero__eyebrow"><?= e(t('home.hero.eyebrow')) ?></p>

        <?php /* <h1> Ở LẠI, dù bản GM gần như không có chữ trên hero. Mỗi trang
                 phải có đúng một h1: đó là thứ trình đọc màn hình và máy tìm
                 kiếm dùng để biết trang này nói về cái gì. Bỏ nó đi thì trang
                 chủ mất tiêu đề — cái giá không đáng cho một khoảng trống. */ ?>
        <h1 id="hero-title" class="vhero__title">
            <?= e(t('home.hero.title_1')) ?><br><em><?= e(t('home.hero.title_2')) ?></em>
        </h1>

        <a class="vhero__link" href="/san-pham"><?= e(t('home.hero.cta_shop')) ?></a>
    </div>

    <?php /* Nhãn của clip đang chạy. aria-hidden vì nó đổi theo video nền —
             đọc lại mỗi 10 giây là quấy rối, và nó không nói gì thêm ngoài thứ
             đang thấy trên hình.

             ANH EM VỚI .vhero__copy, KHÔNG NẰM TRONG NÓ. Nó neo `right` vào
             mép phải của HERO; đặt lồng bên trong thì mốc neo thành cái hộp
             chữ ở góc trái, và nhãn rơi đè lên chính tiêu đề. */ ?>
    <p class="vhero__label" data-vhero-label aria-hidden="true"><?= e($clips[0]['label']) ?></p>

    <?php /* Nhãn của từng clip, đọc bởi home.js. Để trong DOM chứ không nhúng
             vào JSON trong thẻ <script>: máy chủ đã dịch sẵn chuỗi rồi, và một
             danh sách ẩn thì không cần cú phân tích nào. */ ?>
    <div hidden data-vhero-labels>
        <?php foreach ($clips as $clip): ?>
            <span><?= e($clip['label']) ?></span>
        <?php endforeach; ?>
    </div>
</section>
