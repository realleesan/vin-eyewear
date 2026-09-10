<?php

/**
 * about/index.php — Giới thiệu
 *
 * Dựng theo "Gioi thieu v2.dc.html": bốn khối đánh số 01→04 — Về chúng tôi ·
 * Giá trị cốt lõi · Dịch vụ đo mắt · Bắt đầu. Kiểu dáng ở assets/css/about.css,
 * bảng màu lấy từ token cũ của site (ghi chú ánh xạ nằm ở đầu file CSS đó).
 *
 * Khối nền hồng đầu trang mà bản vẽ để trên cùng chính là .pagehead dùng
 * chung — cùng nền, cùng cách xếp breadcrumb / tiêu đề trái / câu dẫn phải —
 * nên gọi lại partial thay vì vẽ lại một bản riêng cho trang này.
 *
 * Class .reveal là hiệu ứng hiện dần khi cuộn tới, do đoạn script dùng chung
 * ở cuối master.php xử lý.
 */

partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Giới thiệu']],
    'head_title'  => 'Giới thiệu',
    'head_lead'   => 'Câu chuyện, giá trị và cách Vin Eyewear chăm sóc thị lực của bạn.',
]);
?>

<?php /* Các biến --about-* (đệm khối, khe cột, độ lệch khung ảnh) khai trên
         thẻ bọc này để bốn khối bên dưới dùng chung một bộ số. */ ?>
<div class="about">

    <!-- ============================================================
         01 — VỀ CHÚNG TÔI
         ============================================================ -->
    <section class="about-story" aria-labelledby="about-story-title">
        <div class="about-story__grid">

            <figure class="about-frame reveal">
                <div class="about-frame__media">
                    <img
                        src="<?= asset('assets/images/hero-models.jpg') ?>"
                        alt="Khách hàng đeo kính Vin Eyewear"
                        width="1431" height="1352"
                        loading="lazy" decoding="async"
                    >
                </div>
            </figure>

            <div class="about-story__text reveal">
                <p class="about-eyebrow">01 — Về chúng tôi</p>

                <h2 id="about-story-title" class="about-h2">
                    Nhìn rõ hơn,<br><em>sống đẹp hơn.</em>
                </h2>

                <div class="about-prose">
                    <p>
                        Vin Eyewear ra đời với mong muốn giúp mọi người tiếp cận dịch vụ chăm sóc
                        thị lực chuẩn xác và sản phẩm kính mắt ở mức giá minh bạch.
                    </p>
                    <p>
                        Từ hai cơ sở tại Hà Nội, chúng tôi phục vụ từng khách hàng theo cùng một
                        cách: lắng nghe nhu cầu, đo mắt cẩn thận và tư vấn đúng với ngân sách
                        của bạn.
                    </p>
                </div>

                <p class="about-sign">Vin Eyewear — Hà Nội</p>
            </div>
        </div>
    </section>

    <!-- ============================================================
         02 — GIÁ TRỊ CỐT LÕI
         ============================================================ -->
    <section class="about-values" aria-labelledby="about-values-title">
        <div class="about-values__inner">

            <?php
            /* ─────────────────────────────────────────────────────────────
               DỰNG THEO KHỐI "Our Core Values" CỦA about.html (Furnish):

                   <section class="py-lg-9 py-5 bg-light">
                     <div class="container text-center">
                       <h2 class="mb-5">Our Core Values</h2>
                       <div class="row g-4">
                         <div class="col-md-4">
                           <div class="card rounded-0 border-0 shadow-sm h-100 p-4">

               ĐÃ BỎ LƯỚI SO LE CÓ HAI Ô ẢNH của bản cũ. Nó là bố cục kiểu
               masonry — mỗi hàng một ô ảnh lệch nhau — và đó đúng là thứ làm
               trang này không đọc ra là Furnish: bản mẫu xếp các giá trị
               thành một hàng thẻ ĐỀU NHAU, căn giữa, trên một dải nền xám.

               Hai tấm ảnh bỏ đi không mất chỗ nào khác: cả hai đã có mặt ở
               khối 01 và khối 03 của chính trang này.
               ───────────────────────────────────────────────────────────── */
            ?>
            <div class="about-values__head">
                <p class="about-eyebrow about-eyebrow--center">02 — Giá trị cốt lõi</p>

                <h2 id="about-values-title" class="about-vlead__title">
                    Giá trị cốt lõi<br><em>tạo nên một thương hiệu.</em>
                </h2>

                <p class="about-values__note">
                    Không phải khẩu hiệu — đây là thước đo chúng tôi dùng cho từng lần tiếp
                    khách, từng chiếc kính bàn giao.
                </p>
            </div>

            <ul class="about-values__grid" role="list">
                <?php foreach ($values as $i => $value): ?>
                    <li class="about-vcard">
                        <span class="about-vcard__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <h3 class="about-vcard__title"><?= e($value) ?></h3>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <!-- ============================================================
         03 — DỊCH VỤ ĐO MẮT
         ============================================================ -->
    <section class="about-exam" aria-labelledby="about-exam-title">
        <div class="about-exam__grid">

            <div class="about-exam__text reveal">
                <p class="about-eyebrow">03 — Dịch vụ đo mắt</p>

                <h2 id="about-exam-title" class="about-h2 about-exam__h2">
                    Đo mắt cẩn thận —<br><em>chuẩn xác trong từng chi tiết.</em>
                </h2>

                <p class="about-exam__lead">
                    Chúng tôi dùng thiết bị đo mắt chuyên dụng, bảo dưỡng định kỳ, kết hợp thử
                    kính trực tiếp để mỗi kết quả tư vấn đều phù hợp với từng người.
                </p>

                <ol class="about-exam__list">
                    <?php foreach ($exam as $i => $item): ?>
                        <li class="about-exam__item">
                            <span class="about-exam__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <div>
                                <h3 class="about-exam__title"><?= e($item['title']) ?></h3>
                                <p class="about-exam__desc"><?= e($item['desc']) ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <a href="/dat-lich" class="btn-primary btn-inline about-exam__cta">Đặt lịch đo mắt</a>
            </div>

            <figure class="about-frame about-frame--left reveal">
                <div class="about-frame__media">
                    <img
                        src="<?= asset('assets/images/showroom-exam-room.jpg') ?>"
                        alt="Phòng đo khúc xạ tại Vin Eyewear"
                        width="1280" height="960"
                        loading="lazy" decoding="async"
                    >
                </div>
            </figure>
        </div>
    </section>

    <!-- ============================================================
         04 — BẮT ĐẦU
         ============================================================ -->
    <section class="about-start" aria-labelledby="about-start-title">
        <p class="about-eyebrow about-eyebrow--center">04 — Bắt đầu</p>

        <h2 id="about-start-title" class="about-h2 about-start__h2">
            Hành trình nhìn rõ hơn bắt đầu<br><em>từ một lựa chọn phù hợp.</em>
        </h2>

        <p class="about-start__quote">“Your journey to clearer vision starts with the right choice.”</p>

        <div class="about-start__actions">
            <a href="/san-pham/gong-kinh" class="btn-primary btn-inline">Khám phá gọng kính</a>
            <a href="/dat-lich" class="btn-outline btn-inline btn-lg about-start__alt">Đặt lịch tư vấn</a>
        </div>
    </section>

</div>
