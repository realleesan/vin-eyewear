<?php

/**
 * contact/index.php — Liên hệ / Hệ thống cửa hàng.
 *
 * Dựng theo "Vin Eyewear Contact.dc.html" (Claude Design):
 *
 *   đầu trang nền hồng phấn
 *   → danh sách cơ sở bên trái (chọn được) | bản đồ lớn bên phải
 *   → form gửi câu hỏi | cột kênh liên hệ nhanh nền hồng, dính theo cuộn
 *
 * ĐÃ BỎ khối "tiện ích" (bãi đỗ xe, phòng đo khúc xạ, thanh toán, nắn chỉnh).
 * Bản thiết kế không có nó; dữ liệu vẫn còn trong ContactController nếu muốn
 * dựng lại.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÁC BẢN THIẾT KẾ: CHỌN CƠ SỞ BẰNG LIÊN KẾT
 *
 * Bản thiết kế giữ cơ sở đang chọn trong `this.state.selected` và đổi bản đồ
 * ngay trong trình duyệt. Ở đây mỗi thẻ cơ sở là một <a> trỏ về chính trang
 * kèm ?cs=<mã cơ sở>:
 *   - Không có JavaScript vẫn đổi được bản đồ.
 *   - Gửi link "cửa hàng Tây Hồ" cho người khác thì họ mở ra đúng cửa hàng đó.
 * contact.js chỉ tăng cường: đổi ngay tại chỗ, không tải lại trang.
 *
 * Nó cũng bắt hai nút "Chỉ đường" (trên thẻ cơ sở và trên thẻ nổi của bản đồ)
 * để xin vị trí hiện tại rồi mở Google Maps với lộ trình từ chỗ khách đang
 * đứng tới cơ sở. Không có JS, hoặc khách từ chối chia sẻ vị trí, thì vẫn ra
 * Google Maps đúng cơ sở — chỉ thiếu điểm xuất phát và Google sẽ tự hỏi.
 * ─────────────────────────────────────────────────────────────────────────────
 */

/**
 * Đường dẫn chỉ đường Google Maps tới một địa chỉ.
 *
 * CỐ Ý KHÔNG có tham số `origin`: đây là bản dùng khi không có JavaScript
 * (hoặc khách từ chối chia sẻ vị trí), lúc đó Google Maps tự hỏi điểm xuất
 * phát. Khi có JS, contact.js xin vị trí hiện tại rồi nối thêm
 * `&origin=<vĩ độ>,<kinh độ>` vào chính đường dẫn này — xem hàm withOrigin.
 */
$directionsUrl = static fn (string $address): string =>
    'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($address);

/**
 * Bản đồ nhúng của một cơ sở.
 *
 * Ưu tiên map_url đã lưu trong DB (quản trị viên có thể dán link đã ghim đúng
 * vị trí); chưa có thì dựng từ địa chỉ như bản thiết kế làm.
 */
$embedUrl = static function (array $store): string {
    if (!empty($store['map_url'])) {
        return $store['map_url'];
    }

    return 'https://maps.google.com/maps?q=' . rawurlencode($store['address']) . '&z=16&output=embed';
};

/**
 * Đang trong giờ mở cửa hay không, đọc từ chuỗi kiểu "08:00 - 21:00 hàng ngày".
 * Không đọc được thì coi như đang mở — thà hiện nhầm "đang mở" còn hơn đuổi
 * khách về vì một chuỗi giờ ghi khác định dạng.
 */
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

/*
 * Bốn kênh liên hệ nhanh.
 *
 * 'glyph' là một ký tự đặt trong vòng tròn, đúng như bản thiết kế. Riêng
 * hotline và email dùng icon SVG của site thay cho ☎ và ✉: hai ký tự đó bị
 * nhiều hệ điều hành vẽ thành emoji màu, phá hẳn vòng tròn đơn sắc. Zalo và
 * Messenger giữ chữ cái Z / M — chính vì thế mà bản thiết kế dùng chữ chứ
 * không dùng icon: hai kênh nhắn tin mà cùng một hình bong bóng thì không
 * phân biệt được kênh nào với kênh nào.
 */
$channels = [
    [
        'icon'  => 'phone',
        'label' => 'Hotline',
        'value' => $company['hotline'],
        'href'  => $company['hotline_href'],
        'blank' => false,
    ],
    [
        'glyph' => 'Z',
        'label' => 'Zalo',
        'value' => 'Nhắn tin tư vấn',
        'href'  => $company['channels']['zalo'],
        'blank' => true,
    ],
    [
        'glyph' => 'M',
        'label' => 'Messenger',
        'value' => 'Chat trực tiếp',
        'href'  => $company['channels']['messenger'],
        'blank' => true,
    ],
    [
        'icon'  => 'mail',
        'label' => 'Email',
        'value' => $company['email'],
        'href'  => 'mailto:' . $company['email'],
        'blank' => false,
    ],
];
?>

<?php partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Liên hệ']],
    'head_title'  => 'Ghé thăm Vin Eyewear',
    'head_lead'   => 'Hai cơ sở tại Hà Nội, mở cửa cả tuần. Đo khúc xạ miễn phí kể cả '
                   . 'khi bạn chưa mua kính.',
]); ?>

<!-- ============================================================
     CƠ SỞ + BẢN ĐỒ

     Bỏ hẳn khối này khi bảng `stores` chưa có cơ sở nào đang hoạt động:
     một khung bản đồ rỗng kèm thẻ thông tin trống trông như trang hỏng, mà
     phần form bên dưới thì vẫn dùng được bình thường.
     ============================================================ -->
<?php if ($selected !== null): ?>
<section class="cstores">
    <div class="cstores__grid">

        <div class="cstores__list">
            <p class="cstores__count"><?= count($stores) ?> cơ sở tại Hà Nội</p>

            <?php foreach ($stores as $store): ?>
                <?php
                $status = $openStatus($store['open_hours']);
                $on     = $store['code'] === $selected['code'];
                ?>
                <a class="cstore<?= $on ? ' is-on' : '' ?>"
                   href="?cs=<?= e(rawurlencode($store['code'])) ?>"
                   <?= $on ? 'aria-current="true"' : '' ?>
                   data-store="<?= e($store['code']) ?>"
                   data-map="<?= e($embedUrl($store)) ?>"
                   data-name="<?= e($store['name']) ?>"
                   data-address="<?= e($store['address']) ?>"
                   data-directions="<?= e($directionsUrl($store['address'])) ?>">

                    <span class="cstore__head">
                        <span class="cstore__name"><?= e($store['name']) ?></span>
                        <?php /* Chấm tròn = cơ sở đang xem trên bản đồ. Trạng
                                 thái này chỉ nằm ở hình dạng chấm, nên phải có
                                 thêm một câu cho trình đọc màn hình. */ ?>
                        <span class="cstore__dot" aria-hidden="true"></span>
                        <?php if ($on): ?>
                            <span class="sr-only"> — đang xem trên bản đồ</span>
                        <?php endif; ?>
                    </span>

                    <span class="cstore__row">
                        <?= icon('map-pin', 'cstore__ico', 14) ?>
                        <span class="cstore__address"><?= e($store['address']) ?></span>
                    </span>

                    <?php if (!empty($store['phone'])): ?>
                        <span class="cstore__row">
                            <?= icon('phone', 'cstore__ico', 14) ?>
                            <span class="cstore__phone"><?= e($store['phone']) ?></span>
                        </span>
                    <?php endif; ?>

                    <span class="cstore__foot">
                        <span class="cstore__hours<?= $status['open'] ? '' : ' is-closed' ?>">
                            <?= $status['open'] ? 'Mở cửa' : 'Đã đóng' ?> · <?= e($status['range']) ?>
                        </span>
                        <?php /* Không thể là <a> riêng vì cả thẻ cơ sở đã là
                                 một <a> rồi, mà HTML cấm <a> lồng <a>.
                                 contact.js bắt cú bấm vào đây để xin vị trí và
                                 mở chỉ đường; không có JS thì bấm vào đây bằng
                                 bấm vào thẻ — mở cơ sở này lên bản đồ, và nút
                                 chỉ đường thật nằm ngay trên thẻ bản đồ đó. */ ?>
                        <span class="cstore__go"
                              title="Chỉ đường từ vị trí của bạn tới <?= e($store['name']) ?>">Chỉ đường</span>
                    </span>
                </a>
            <?php endforeach; ?>

            <a class="cstores__cta" href="/dat-lich">Đặt lịch đo mắt</a>
        </div>

        <div class="cmap">
            <?php /* title bắt buộc cho trình đọc màn hình. Không đặt
                     loading="lazy": bản đồ nằm ngay đầu trang, hoãn tải chỉ
                     làm nó hiện ra muộn sau khi người dùng đã nhìn vào đó. */ ?>
            <iframe class="cmap__frame"
                    id="storeMap"
                    src="<?= e($embedUrl($selected)) ?>"
                    title="Bản đồ <?= e($selected['name']) ?>"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen></iframe>

            <div class="cmap__card">
                <p class="cmap__name" data-map-name><?= e($selected['name']) ?></p>
                <p class="cmap__address" data-map-address><?= e($selected['address']) ?></p>
                <a class="cmap__link" data-map-link
                   href="<?= e($directionsUrl($selected['address'])) ?>"
                   target="_blank" rel="noreferrer noopener">Chỉ đường từ vị trí của tôi ↗</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================
     FORM + KÊNH LIÊN HỆ NHANH
     ============================================================ -->
<section class="cbottom" id="form">
    <div class="cbottom__grid">

        <div class="cform">
            <div class="cform__head">
                <h2 class="cform__title">Gửi câu hỏi cho chúng tôi</h2>
                <p class="cform__lead">
                    Điền thông tin bên dưới, đội ngũ tư vấn sẽ liên hệ lại trong ngày làm việc.
                </p>
            </div>

            <?php if ($success !== null): ?>
                <p class="alert alert--ok" role="status"><?= e($success) ?></p>
            <?php endif; ?>
            <?php if ($error !== null): ?>
                <p class="alert alert--err" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <form class="cform__body" method="post" action="/lien-he/gui">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                <div class="cform__pair">
                    <label class="cfield">
                        <span class="cfield__label">Họ và tên *</span>
                        <input class="cfield__input" type="text" name="full_name" required
                               minlength="2" maxlength="120" autocomplete="name"
                               placeholder="Nguyễn Văn A"
                               value="<?= e($old['fullName'] ?? '') ?>">
                    </label>

                    <label class="cfield">
                        <span class="cfield__label">Số điện thoại *</span>
                        <input class="cfield__input" type="tel" name="phone" required
                               autocomplete="tel" inputmode="tel"
                               placeholder="09xx xxx xxx"
                               value="<?= e($old['phone'] ?? '') ?>">
                    </label>
                </div>

                <label class="cfield">
                    <span class="cfield__label">
                        Email <em class="cfield__opt">(không bắt buộc)</em>
                    </span>
                    <input class="cfield__input" type="email" name="email" autocomplete="email"
                           placeholder="ban@email.com"
                           value="<?= e($old['email'] ?? '') ?>">
                </label>

                <label class="cfield">
                    <span class="cfield__label">Nội dung *</span>
                    <textarea class="cfield__input cfield__input--area" name="message" rows="5"
                              required minlength="5" maxlength="1000"
                              placeholder="Bạn cần tư vấn về gọng kính, tròng kính hay đặt lịch đo mắt?"><?= e($old['message'] ?? '') ?></textarea>
                </label>

                <button type="submit" class="cform__submit">Gửi câu hỏi</button>
            </form>
        </div>

        <aside class="cquick">
            <h2 class="cquick__title">Cần hỗ trợ ngay?</h2>
            <p class="cquick__lead">Chọn kênh bạn thấy tiện nhất, 8:30 – 21:00 mỗi ngày.</p>

            <?php foreach ($channels as $ch): ?>
                <a class="cchan" href="<?= e($ch['href']) ?>"
                   <?= $ch['blank'] ? 'target="_blank" rel="noreferrer noopener"' : '' ?>>
                    <span class="cchan__mark" aria-hidden="true">
                        <?= isset($ch['icon']) ? icon($ch['icon'], '', 18) : e($ch['glyph']) ?>
                    </span>
                    <span class="cchan__text">
                        <span class="cchan__label"><?= e($ch['label']) ?></span>
                        <span class="cchan__value"><?= e($ch['value']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </aside>
    </div>
</section>
