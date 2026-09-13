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

$channels = [
    [
        'brand' => 'hotline',
        'label' => 'Hotline tư vấn',
        'value' => $company['hotline'],
        'href'  => $company['hotline_href'],
        'blank' => false,
    ],
    [
        'brand' => 'zalo',
        'label' => 'Zalo chat',
        'value' => 'Nhắn tin tư vấn',
        'href'  => $company['channels']['zalo'],
        'blank' => true,
    ],
    [
        'brand' => 'messenger',
        'label' => 'Messenger',
        'value' => 'Chat Facebook',
        'href'  => $company['channels']['messenger'],
        'blank' => true,
    ],
    [
        'brand' => 'gmail',
        'label' => 'Thư điện tử',
        'value' => $company['email'],
        'href'  => 'mailto:' . $company['email'],
        'blank' => false,
    ],
];

$servicePromises = [
    'Đo khám thị lực chuẩn khúc xạ',
    'Tư vấn dáng kính hợp khuôn mặt',
    'Căn chỉnh gọng, vệ sinh trọn đời',
    'Bảo hành chính hãng toàn diện',
];
?>

<?php partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Liên hệ']],
    'head_badge'  => 'Chăm sóc khách hàng & Dịch vụ khúc xạ',
    'head_title'  => 'Ghé thăm Vin Eyewear',
    'head_lead'   => 'Hai cơ sở tại Hà Nội, mở cửa cả tuần. Đo khúc xạ miễn phí kể cả '
                   . 'khi bạn chưa mua kính.',
]); ?>

<div class="contact-page">
    <section class="ccontact" id="form" aria-labelledby="contact-support-title">
        <div class="ccontact__grid">
            <aside class="cquick">
                <div class="cquick__main">
                    <header class="cquick__head">
                        <div>
                            <h2 class="cquick__title" id="contact-support-title">Cần hỗ trợ ngay?</h2>
                            <p class="cquick__lead">Chọn kênh bạn thấy tiện nhất, 8:30 – 21:00 mỗi ngày.</p>
                        </div>
                        <span class="cstatus cstatus--online">
                            <span class="cstatus__dot" aria-hidden="true"></span>
                            Đang trực tuyến
                        </span>
                    </header>

                    <div class="cchannels">
                        <?php foreach ($channels as $ch): ?>
                            <a class="cchan" href="<?= e($ch['href']) ?>"
                               <?= $ch['blank'] ? 'target="_blank" rel="noreferrer noopener"' : '' ?>>
                                <span class="cchan__mark cchan__mark--<?= e($ch['brand']) ?>" aria-hidden="true">
                                    <?php if ($ch['brand'] === 'hotline'): ?>
                                        <?= icon('phone', 'cchan__logo cchan__logo--hotline', 22) ?>
                                    <?php elseif ($ch['brand'] === 'gmail'): ?>
                                        <?= icon('mail', 'cchan__logo cchan__logo--gmail', 22) ?>
                                    <?php else: ?>
                                        <?= brandIcon($ch['brand'], 'cchan__logo cchan__logo--' . $ch['brand'], 28) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="cchan__text">
                                    <span class="cchan__label"><?= e($ch['label']) ?></span>
                                    <span class="cchan__value"><?= e($ch['value']) ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cservice">
                    <h3 class="cservice__title">Cam kết dịch vụ Vin Eyewear</h3>
                    <ul class="cservice-list" role="list">
                        <?php foreach ($servicePromises as $promise): ?>
                            <li><?= icon('check', 'cservice__icon', 14) ?><span><?= e($promise) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>

            <section class="cform" aria-labelledby="contact-form-title">
                <header class="cform__head">
                    <span class="cform__eyebrow">Trợ giúp khách hàng</span>
                    <h2 class="cform__title" id="contact-form-title">Gửi câu hỏi cho chúng tôi</h2>
                    <p class="cform__lead">
                        Điền thông tin bên dưới, đội ngũ tư vấn sẽ liên hệ lại trong ngày làm việc.
                    </p>
                </header>

                <?php if ($success !== null): ?>
                    <p class="alert alert--ok" role="status"><?= e($success) ?></p>
                <?php endif; ?>
                <?php if ($error !== null): ?>
                    <p class="alert alert--err" role="alert"><?= e($error) ?></p>
                <?php endif; ?>

                <form class="cform__body" method="post" action="/lien-he/gui">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

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
                        <span>Gửi câu hỏi</span>
                        <?= icon('arrow-right', 'cform__submit-icon', 17) ?>
                    </button>

                    <p class="cform__privacy">
                        <?= icon('shield', 'cform__privacy-icon', 14) ?>
                        <span>Bảo mật thông tin khách hàng 100%</span>
                    </p>
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
