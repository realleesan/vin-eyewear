<?php

/**
 * about/index.php — Giới thiệu
 * Port từ src/routes/gioi-thieu.tsx.
 *
 * Ba phần: Câu chuyện (kèm số liệu + ảnh) · Cam kết · Quy trình 5 bước.
 *
 * Class .reveal là hiệu ứng hiện dần khi cuộn tới, do đoạn script dùng chung
 * ở cuối master.php xử lý — thay cho component <Reveal> của bản React.
 */

partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Giới thiệu']],
    'head_title'  => 'Nhìn rõ hơn, sống đẹp hơn',
    'head_lead'   => 'Vin Eyewear ra đời với mong muốn giúp mọi người tiếp cận dịch vụ chăm sóc '
                   . 'thị lực chuẩn xác và sản phẩm kính mắt chính hãng ở mức giá minh bạch.',
]);
?>

<!-- ============================================================
     01 — CÂU CHUYỆN
     ============================================================ -->
<section class="about-story" aria-labelledby="story-title">
    <div class="about-story__grid">

        <div class="about-story__text reveal">
            <p class="micro-label">Câu chuyện</p>
            <h2 id="story-title" class="about-h2">Một cửa hàng kính vận hành như một phòng khám</h2>

            <div class="about-prose">
                <p>
                    Chúng tôi kết hợp thiết bị đo khúc xạ hiện đại với đội ngũ kỹ thuật viên được
                    đào tạo bài bản, đồng thời tuyển chọn gọng kính và tròng kính từ các thương
                    hiệu uy tín.
                </p>
                <p>
                    Mỗi khách hàng đều được đo khúc xạ miễn phí, tư vấn dáng gọng theo khuôn mặt
                    và bàn giao kính sau khi cân chỉnh hoàn chỉnh.
                </p>
            </div>

            <dl class="about-stats">
                <?php foreach ($stats as $stat): ?>
                    <div class="about-stat">
                        <dt class="about-stat__value"><?= e($stat['value']) ?></dt>
                        <dd class="about-stat__label"><?= e($stat['label']) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>

        <figure class="about-figure reveal">
            <img
                src="<?= asset('assets/images/store-interior.jpg') ?>"
                alt="Không gian trưng bày gọng kính tại showroom Vin Eyewear"
                width="1200" height="900"
                loading="lazy" decoding="async"
            >
            <figcaption>Showroom Tây Hồ — Hà Nội</figcaption>
        </figure>
    </div>
</section>

<!-- ============================================================
     02 — CAM KẾT
     ============================================================ -->
<section class="about-values" aria-labelledby="values-title">
    <div class="about-values__inner">
        <p class="micro-label">02 — Cam kết</p>
        <h2 id="values-title" class="about-h2 about-h2--narrow">Ba điều chúng tôi không thoả hiệp</h2>

        <!-- Lưới dùng gap 1px trên nền --line để tạo đường kẻ ngăn ô, thay
             cho border từng ô (border kề nhau sẽ dày gấp đôi ở chỗ giáp ranh) -->
        <ul class="about-values__list" role="list">
            <?php foreach ($values as $i => $value): ?>
                <li class="about-value">
                    <span class="about-value__index"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <?= icon($value['icon'], 'about-value__ico', 20) ?>
                    <h3 class="about-value__title"><?= e($value['title']) ?></h3>
                    <p class="about-value__desc"><?= e($value['desc']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<!-- ============================================================
     03 — QUY TRÌNH
     ============================================================ -->
<section class="about-process" aria-labelledby="process-title">
    <div class="about-process__grid">

        <div class="about-process__intro">
            <p class="micro-label">03 — Quy trình</p>
            <h2 id="process-title" class="about-h2">Đo mắt 5 bước</h2>
            <p class="about-lead">
                Toàn bộ quy trình diễn ra trong 25–40 phút, hoàn toàn miễn phí kể cả khi bạn
                chưa mua kính.
            </p>
            <a href="/dat-lich" class="btn-primary btn-inline about-process__cta">Đặt lịch đo mắt</a>
        </div>

        <ol class="about-steps">
            <?php foreach ($steps as $i => $step): ?>
                <!-- style --d: độ trễ hiện dần, mỗi bước chậm hơn bước trước 60ms
                     để 5 bước xuất hiện nối tiếp chứ không cùng lúc -->
                <li class="about-step reveal" style="--d: <?= $i * 60 ?>ms">
                    <span class="about-step__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                    <div class="about-step__body">
                        <h3 class="about-step__title"><?= e($step['title']) ?></h3>
                        <p class="about-step__desc"><?= e($step['desc']) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
