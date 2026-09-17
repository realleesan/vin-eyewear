<?php

/**
 * about/index.php — Giới thiệu
 *
 * Dựng theo design-reference/introduction: năm khối —
 *   01 — Về chúng tôi (hero full-width, chữ trắng bên trái giữa)
 *   02 — Giá trị cốt lõi (4 thẻ viền, có mô tả và nhãn phụ)
 *   03 — Dịch vụ đo mắt (grid ảnh trái + 4 mục text phải)
 *   Cam kết dịch vụ (4 thẻ icon)
 *   04 — Bắt đầu (CTA căn giữa)
 *
 * Kiểu dáng ở assets/css/about.css, bảng màu lấy từ token của site.
 * Class .reveal là hiệu ứng hiện dần khi cuộn tới, do đoạn script dùng chung
 * ở cuối master.php xử lý.
 */

partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Giới thiệu']],
    'head_title'  => 'Giới thiệu',
    'head_lead'   => 'Câu chuyện, giá trị và cách Vin Eyewear chăm sóc thị lực của bạn.',
]);
?>

<div class="about">

    <!-- ============================================================
         01 — VỀ CHÚNG TÔI
         ============================================================ -->
    <section class="about-story" aria-labelledby="about-story-title">
        <div class="about-story__grid">

            <figure class="about-frame reveal">
                <div class="about-frame__media">
                    <img
                        src="<?= asset('assets/images/store-interior.jpg') ?>"
                        alt="Không gian trưng bày tối giản hiện đại của Vin Eyewear"
                        width="1920" height="1080"
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
                        Tự hào tại Hà Nội với 2 cơ sở, chúng tôi phục vụ từng khách hàng theo cùng
                        một cách: lắng nghe nhu cầu, đo mắt cẩn thận và tư vấn đúng với ngân sách
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

            <div class="about-values__head">
                <p class="about-eyebrow about-eyebrow--center">02 — Giá trị cốt lõi</p>

                <h2 id="about-values-title" class="about-vlead__title">
                    Tạo nên một thương hiệu.
                </h2>

                <p class="about-values__note">
                    Không phải khẩu hiệu — đây là thước đo chúng tôi dùng cho từng lần tiếp
                    khách, từng chiếc kính bàn giao.
                </p>
            </div>

            <ul class="about-values__grid" role="list">
                <?php foreach ($values as $i => $value): ?>
                    <li class="about-vcard">
                        <div class="about-vcard__top">
                            <span class="about-vcard__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <h3 class="about-vcard__title"><?= e($value['title']) ?></h3>
                            <p class="about-vcard__desc"><?= e($value['desc']) ?></p>
                        </div>
                        <div class="about-vcard__bottom">
                            <span class="about-vcard__tag"><?= e($value['tag']) ?></span>
                        </div>
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

            <!-- Cột trái: grid ảnh -->
            <div class="about-exam__visuals reveal">
                <div class="about-exam__photo-main">
                    <img
                        src="<?= asset('assets/images/showroom-exam-room.jpg') ?>"
                        alt="Phòng đo khám thị lực kỹ thuật số chuyên sâu Vin Eyewear"
                        width="1280" height="960"
                        loading="lazy" decoding="async"
                    >
                    <div class="about-exam__photo-caption">
                        <span class="about-exam__photo-label">Trang thiết bị khúc xạ tự động hiện đại</span>
                        <span class="about-exam__photo-tag">Phòng khám chuyên khoa</span>
                    </div>
                </div>
                <div class="about-exam__photo-row">
                    <div class="about-exam__photo-sub">
                        <img
                            src="<?= asset('assets/images/showroom-frames.jpg') ?>"
                            alt="Kỹ thuật cân chỉnh và mài lắp tròng kính thủ công tinh xảo"
                            width="640" height="480"
                            loading="lazy" decoding="async"
                        >
                    </div>
                    <div class="about-exam__stat">
                        <span class="about-exam__stat-num">100%</span>
                        <span class="about-exam__stat-text">Tròng kính quang học nhập khẩu chính hãng từ Essilor, Chemi &amp; Hoya.</span>
                    </div>
                </div>
            </div>

            <!-- Cột phải: nội dung text -->
            <div class="about-exam__text reveal">
                <p class="about-eyebrow">03 — Dịch vụ đo mắt & khác biệt</p>

                <h2 id="about-exam-title" class="about-h2 about-exam__h2">
                    Đo mắt cẩn thận —<br><em>chuẩn xác trong từng chi tiết.</em>
                </h2>

                <p class="about-exam__lead">
                    Chúng tôi dùng thiết bị đo mắt chuyên dụng, bảo dưỡng định kỳ, kết hợp thử
                    kính thực tế để mỗi kết quả tư vấn đều phù hợp với từng người.
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
        </div>
    </section>

    <!-- ============================================================
         CAM KẾT DỊCH VỤ
         ============================================================ -->
    <section class="about-commit" aria-labelledby="about-commit-title">
        <div class="about-commit__head">
            <p class="about-eyebrow about-eyebrow--center">Cam kết dịch vụ</p>
            <h2 id="about-commit-title" class="about-commit__title">
                An tâm trong từng lần trải nghiệm
            </h2>
        </div>

        <div class="about-commit__grid">
            <?php foreach ($commitments as $item): ?>
                <div class="about-commit__card">
                    <div class="about-commit__icon">
                        <?php if ($item['icon'] === 'check'): ?>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
                        <?php elseif ($item['icon'] === 'refresh'): ?>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
                        <?php elseif ($item['icon'] === 'eye'): ?>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
                        <?php elseif ($item['icon'] === 'clock'): ?>
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"></path></svg>
                        <?php endif; ?>
                    </div>
                    <h3 class="about-commit__card-title"><?= e($item['title']) ?></h3>
                    <p class="about-commit__card-desc"><?= e($item['desc']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============================================================
         04 — BẮT ĐẦU
         ============================================================ -->
    <section class="about-start" aria-labelledby="about-start-title">
        <p class="about-eyebrow about-eyebrow--center">04 — Bắt đầu</p>

        <h2 id="about-start-title" class="about-h2 about-start__h2">
            Hành trình<br>nhìn rõ hơn<br>bắt đầu từ một<br><em>lựa chọn phù hợp.</em>
        </h2>

        <p class="about-start__quote">"Your journey to clearer vision starts with the right choice."</p>

        <div class="about-start__actions">
            <a href="/san-pham/gong-kinh" class="btn-primary btn-inline">Khám phá gọng kính</a>
            <a href="/dat-lich" class="btn-outline btn-inline btn-lg about-start__alt">Đặt lịch tư vấn</a>
        </div>
    </section>

</div>
