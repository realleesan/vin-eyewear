<?php

/**
 * contact/index.php — Liên hệ / Hệ thống cửa hàng.
 *
 * Giao diện bám theo design-reference/contact. Dữ liệu cửa hàng, form POST,
 * CSRF, flash message và các móc data-* cho contact.js vẫn là dữ liệu thật
 * của ứng dụng; code.html chỉ được dùng làm tham chiếu trình bày.
 */

$directionsUrl = static fn (string $address): string =>
    'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($address);

$embedUrl = static function (array $store): string {
    if (!empty($store['map_url'])) {
        return $store['map_url'];
    }

    return 'https://maps.google.com/maps?q=' . rawurlencode($store['address']) . '&z=16&output=embed';
};

$openStatus = static function (?string $hours): array {
    if (!preg_match('/(\d{1,2}):(\d{2})\s*[-–]\s*(\d{1,2}):(\d{2})/', (string) $hours, $m)) {
        return ['open' => true, 'range' => (string) $hours];
    }

    $now   = (int) date('G') * 60 + (int) date('i');
    $open  = (int) $m[1] * 60 + (int) $m[2];
    $close = (int) $m[3] * 60 + (int) $m[4];

    return [
        'open'  => $now >= $open && $now < $close,
        'range' => sprintf('%d:%s – %d:%s', (int) $m[1], $m[2], (int) $m[3], $m[4]),
    ];
};

?>

<?php partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Liên hệ']],
    'head_title'  => 'Liên hệ',
    'head_lead'   => 'Hai cơ sở tại Hà Nội · Mở cửa 8:00 – 21:00 hằng ngày',
]); ?>

<div class="contact-page">
    <section class="ccontact" id="form" aria-labelledby="contact-form-title">
        <div class="ccontact__grid">
            <aside class="cpromo" aria-labelledby="contact-promo-title">
                <div class="cpromo__copy">
                    <span class="cpromo__eyebrow">Ưu đãi đặc quyền</span>
                    <h2 class="cpromo__title" id="contact-promo-title">Tư vấn thị lực<br>miễn phí</h2>
                    <p class="cpromo__lead">Đặt lịch ngay để được các chuyên viên đo thị lực chuẩn xác và tư vấn kiểu dáng tròng kính hài hòa nhất.</p>
                    <a class="cpromo__cta" href="/dat-lich">Đặt lịch ngay!</a>

                    <ul class="cpromo__notes" role="list">
                        <li><?= icon('check', 'cpromo__note-icon', 14) ?><span>Thiết bị quang học kỹ thuật số chuẩn quốc tế</span></li>
                        <li><?= icon('check', 'cpromo__note-icon', 14) ?><span>Chuyên viên khúc xạ giàu kinh nghiệm tư vấn</span></li>
                    </ul>
                </div>
                <img class="cpromo__image" src="/assets/images/showroom-exam-room.jpg"
                     alt="Chuyên viên Vin Eyewear đang đo mắt cho khách hàng">
            </aside>

            <section class="cform" aria-labelledby="contact-form-title">
                <header class="cform__head">
                    <div>
                        <h2 class="cform__title" id="contact-form-title">Gửi tin nhắn &amp; đặt lịch</h2>
                        <p class="cform__lead">Phản hồi ngay trong ngày làm việc</p>
                    </div>
                    <span class="cform__hours"><span aria-hidden="true"></span>8:30 – 21:00</span>
                </header>

                <?php if ($success !== null): ?>
                    <p class="alert alert--ok" role="status"><?= e($success) ?></p>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <p class="alert alert--err" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <form class="cform__body" method="post" action="/lien-he/gui">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                    <div class="cform__split">
                        <label class="cfield" for="contact-full-name">
                            <span class="cfield__label">Họ và tên <span aria-hidden="true">*</span></span>
                            <input class="cfield__input" id="contact-full-name" type="text" name="full_name" required
                                   minlength="2" maxlength="120" autocomplete="name"
                                   placeholder="Nguyễn Văn A"
                                   value="<?= e($old['fullName'] ?? '') ?>">
                        </label>

                        <label class="cfield" for="contact-phone">
                            <span class="cfield__label">Số điện thoại <span aria-hidden="true">*</span></span>
                            <input class="cfield__input" id="contact-phone" type="tel" name="phone" required
                                   autocomplete="tel" inputmode="tel"
                                   placeholder="09xx xxx xxx"
                                   value="<?= e($old['phone'] ?? '') ?>">
                        </label>
                    </div>

                    <label class="cfield" for="contact-email">
                        <span class="cfield__label">Email</span>
                        <input class="cfield__input" id="contact-email" type="email" name="email" autocomplete="email"
                               placeholder="ban@email.com"
                               value="<?= e($old['email'] ?? '') ?>">
                    </label>

                    <label class="cfield" for="contact-message">
                        <span class="cfield__label">Nội dung thắc mắc hoặc đặt lịch hẹn</span>
                        <textarea class="cfield__input cfield__input--area" id="contact-message" name="message" rows="3"
                                  required minlength="5" maxlength="1000"
                                  placeholder="Bạn cần tư vấn về gọng kính, tròng kính hay đặt lịch đo mắt?"><?= e($old['message'] ?? '') ?></textarea>
                    </label>

                    <button type="submit" class="cform__submit">
                        <span>Gửi tin nhắn &amp; đặt lịch</span>
                        <?= icon('arrow-right', 'cform__submit-icon', 17) ?>
                    </button>

                    <footer class="cform__foot">
                        <a class="cform__hotline" href="<?= e($company['hotline_href']) ?>">
                            <?= icon('phone', '', 15) ?> <span>Hotline: <strong><?= e($company['hotline']) ?></strong></span>
                        </a>
                        <a class="cform__zalo" href="<?= e($company['channels']['zalo']) ?>" target="_blank" rel="noreferrer noopener">Zalo tư vấn ↗</a>
                    </footer>
                </form>
            </section>
        </div>
    </section>

    <?php if ($selected !== null): ?>
        <section class="cstores" id="store-locator" aria-labelledby="store-locator-title">
            <header class="cstores__head">
                <div>
                    <span class="cstores__eyebrow">Store locator</span>
                    <h2 class="cstores__title" id="store-locator-title">Hệ thống cửa hàng Vin Eyewear</h2>
                    <p class="cstores__lead">Trải nghiệm không gian đo mắt chuyên nghiệp &amp; thử trực tiếp hơn 1000+ mẫu kính.</p>
                </div>
                <p class="cstores__amenity">
                    <?= icon('check', 'cstores__amenity-icon', 14) ?>
                    <span>Bãi đỗ ô tô &amp; xe máy thuận tiện cả <?= count($stores) ?> cơ sở</span>
                </p>
            </header>

            <div class="cstores__shell">
                <div class="cstores__list">
                    <?php foreach ($stores as $store): ?>
                        <?php
                        $status = $openStatus($store['open_hours']);
                        $on     = $store['code'] === $selected['code'];
                        ?>
                        <article class="cstore<?= $on ? ' is-on' : '' ?>"
                                 data-store="<?= e($store['code']) ?>"
                                 data-map="<?= e($embedUrl($store)) ?>"
                                 data-name="<?= e($store['name']) ?>"
                                 data-address="<?= e($store['address']) ?>"
                                 data-directions="<?= e($directionsUrl($store['address'])) ?>">
                            <a class="cstore__select" href="?cs=<?= e(rawurlencode($store['code'])) ?>"
                               <?= $on ? 'aria-current="true"' : '' ?>>
                                <span class="cstore__head">
                                    <span class="cstore__identity">
                                        <span class="cstore__dot" aria-hidden="true"></span>
                                        <span class="cstore__name"><?= e($store['name']) ?></span>
                                    </span>
                                    <span class="cstore__hours<?= $status['open'] ? '' : ' is-closed' ?>">
                                        <?= $status['open'] ? 'Mở cửa' : 'Đã đóng' ?> <?= e($status['range']) ?>
                                    </span>
                                </span>

                                <span class="cstore__details">
                                    <span class="cstore__row">
                                        <?= icon('map-pin', 'cstore__ico', 15) ?>
                                        <span class="cstore__address"><?= e($store['address']) ?></span>
                                    </span>
                                    <?php if (!empty($store['phone'])): ?>
                                        <span class="cstore__row">
                                            <?= icon('phone', 'cstore__ico', 15) ?>
                                            <span>Hotline: <strong class="cstore__phone"><?= e($store['phone']) ?></strong> <small>(Hỗ trợ 24/7)</small></span>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </a>

                            <div class="cstore__actions">
                                <a class="cstore__directions" href="<?= e($directionsUrl($store['address'])) ?>"
                                   target="_blank" rel="noreferrer noopener">
                                    <span>Chỉ đường</span>
                                </a>
                                <a class="cstore__book" href="/dat-lich">Đặt lịch đo mắt</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="cmap">
                    <iframe class="cmap__frame"
                            id="storeMap"
                            src="<?= e($embedUrl($selected)) ?>"
                            title="Bản đồ <?= e($selected['name']) ?>"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen></iframe>

                    <div class="cmap__card" aria-live="polite">
                        <div class="cmap__card-head">
                            <div>
                                <span class="cmap__status"><span aria-hidden="true"></span>Đang phục vụ</span>
                                <p class="cmap__name" data-map-name><?= e($selected['name']) ?></p>
                                <p class="cmap__address" data-map-address><?= e($selected['address']) ?></p>
                            </div>
                            <span class="cmap__pin" aria-hidden="true"><?= icon('map-pin', '', 18) ?></span>
                        </div>
                        <div class="cmap__card-foot">
                            <span>Đo mắt miễn phí</span>
                            <a class="cmap__link" data-map-link
                               href="<?= e($directionsUrl($selected['address'])) ?>"
                               target="_blank" rel="noreferrer noopener">Mở Google Maps ↗</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
