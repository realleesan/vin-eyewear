<?php

/**
 * booking/index.php — đặt lịch đo mắt (/dat-lich)
 *
 * Dựng theo "Vin Eyewear Booking.dc.html" (Claude Design):
 *
 *   đầu trang hồng phấn (dùng chung _layout/page-head.php)
 *   → hai cột: bốn thẻ đánh số 1·2·3·4 | cột tóm tắt 380px dính theo cuộn
 *
 * CSS: assets/css/booking.css
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TOÀN BỘ TRANG NÀY KHÔNG CÓ MỘT DÒNG JAVASCRIPT NÀO
 *
 * Bản thiết kế cho phép đổi cơ sở / ngày / dịch vụ / giờ và thấy cột tóm tắt
 * bên phải đổi theo NGAY. Ở đây làm bằng ba thứ có sẵn của HTML và CSS:
 *
 *   1. Mỗi lựa chọn là một nút radio thật, ẩn đi và vẽ lại bằng CSS. Bàn phím
 *      và trình đọc màn hình vẫn hiểu đúng "chọn một trong nhiều".
 *
 *   2. Lưới giờ của MỌI tổ hợp (cơ sở × ngày) được in sẵn; `:has()` chọn ra
 *      đúng một lưới để hiện. Đổi ngày không phải tải lại trang, nên tên và
 *      số điện thoại khách đang gõ dở không bị mất — đúng chỗ mà bản cũ
 *      (mỗi lần đổi ngày là một lượt GET) làm khách khó chịu nhất.
 *
 *   3. Cột tóm tắt in sẵn mọi giá trị có thể, `:has()` cho hiện đúng cái đang
 *      được chọn. Không có bước "đọc trạng thái rồi ghi chữ" nào cả.
 *
 * Ô giờ mang giá trị "chỉ số ngày|chỉ số khung" (ví dụ "3|5") chứ không phải
 * ngày giờ thật — nhờ vậy các luật CSS ở booking.css chỉ phụ thuộc vào CHỈ SỐ
 * nên viết cố định được, dù danh sách khung giờ trong config có đổi hay ngày
 * hôm nay là ngày nào. Server dựng lại ngày giờ từ chỉ số.
 *
 * KHÔNG đặt `required` cho nhóm ô giờ: những lưới đang ẩn cũng nằm trong cùng
 * một nhóm radio, và trình duyệt sẽ cố đưa con trỏ tới một ô vô hình rồi chặn
 * gửi form mà không báo gì. Việc kiểm do server làm.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$company = config('company');
$old     = $old ?? [];

/* Đến từ nút "Đặt lịch tham dự" của một bài sự kiện. Ngày giờ ghép ở đây chứ
   không dùng thẳng dateRange(): chương trình có giờ khai mạc thì giờ đó mới là
   thứ khách cần đối chiếu với khung giờ vừa được chọn hộ bên dưới. */
$event      = $event ?? null;
$eventWhen  = $eventWhen ?? '';   // 'fit' | 'later' | 'over', do controller tính
$eventDates = '';

if ($event !== null) {
    $eventDates = dateRange($event['starts_at'] ?? null, $event['ends_at'] ?? null);
    $eventAt    = formatDate($event['starts_at'] ?? null, 'H:i');

    // Nửa đêm nghĩa là bài chỉ ghi NGÀY, giờ 00:00 là do DATETIME phải có đủ
    // phần giờ chứ không phải chương trình mở lúc nửa đêm — in ra là sai.
    if ($eventAt !== '' && $eventAt !== '00:00') {
        $eventDates = $eventDates === '' ? $eventAt : $eventDates . ' · ' . $eventAt;
    }
}

partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Đặt lịch đo mắt']],
    'head_title'  => 'Đặt lịch đo mắt',
    'head_lead'   => 'Đo khúc xạ miễn phí với kỹ thuật viên nhiều năm kinh nghiệm — '
                   . 'kể cả khi bạn chưa mua kính.',
]);

/** Năm bước của quy trình, đúng chữ trong bản thiết kế. */
$steps = [
    'Tiếp nhận & khai thác tiền sử thị lực',
    'Đo khúc xạ tự động + thử kính chủ quan',
    'Thử tròng phù hợp trong 10–15 phút',
    'Tư vấn dáng gọng theo khuôn mặt',
    'Lắp tròng, căn chỉnh và hướng dẫn bảo quản',
];
?>

<section class="bk">

    <?php if ($stores === []): ?>

        <div class="bkempty">
            <h2 class="bkempty__title">Hiện chưa nhận đặt lịch trực tuyến</h2>
            <p class="bkempty__lead">
                Chưa có cơ sở nào mở lịch hẹn. Bạn vẫn có thể gọi
                <a href="<?= e($company['hotline_href']) ?>"><?= e($company['hotline']) ?></a>
                để được đặt trực tiếp.
            </p>
        </div>

    <?php else: ?>

        <form class="bk__grid" method="post" action="/dat-lich/gui">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

            <?php if ($event !== null): ?>
                <?php /* Slug đi cùng form để lần gửi hụt còn quay về đúng bài sự
                         kiện. Server KHÔNG lưu gì từ ô này — nội dung đăng ký nằm
                         ở ô ghi chú đã điền sẵn. */ ?>
                <input type="hidden" name="event_slug" value="<?= e($event['slug']) ?>">
            <?php endif; ?>

            <!-- ══════════ CỘT TRÁI: BỐN BƯỚC ══════════ -->
            <div class="bk__steps">

                <?php if ($error !== null): ?>
                    <p class="bkalert" role="alert">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 7.5V13M12 16.5h.01"></path>
                        </svg>
                        <?= e($error) ?>
                    </p>
                <?php endif; ?>

                <?php if ($event !== null): ?>
                    <p class="bknotice">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="3"></rect>
                            <path d="M8 3v4M16 3v4M3 10h18M9 15l2 2 4-4"></path>
                        </svg>
                        <span>
                            Đang đặt lịch tham dự <strong><?= e($event['title']) ?></strong><?=
                                $eventDates === '' ? '' : ' · ' . e($eventDates) ?>.
                            <?php if ($eventWhen === 'fit'): ?>
                                Cơ sở, ngày và khung giờ bên dưới đã chọn sẵn theo chương trình —
                                bạn đổi lại nếu muốn đến vào lúc khác.
                            <?php elseif ($eventWhen === 'later'): ?>
                                Chương trình nằm ngoài 7 ngày đang mở lịch nên chưa chọn ngày hộ được.
                                Bạn chọn giúp một ngày bên dưới, hoặc gọi
                                <a href="<?= e($company['hotline_href']) ?>"><?= e($company['hotline']) ?></a>
                                để được giữ chỗ đúng hôm diễn ra.
                            <?php else: ?>
                                Chương trình này đã kết thúc, nhưng bạn vẫn đặt được lịch đo mắt
                                miễn phí bên dưới. Cần hỏi về chương trình tương tự, gọi
                                <a href="<?= e($company['hotline_href']) ?>"><?= e($company['hotline']) ?></a>.
                            <?php endif; ?>
                        </span>
                    </p>
                <?php endif; ?>

                <!-- ────── 1. CƠ SỞ ────── -->
                <div class="bkcard">
                    <div class="bkcard__head">
                        <span class="bkcard__num" aria-hidden="true">1</span>
                        <h2 class="bkcard__title">Chọn cơ sở</h2>
                    </div>

                    <div class="bkstores">
                        <?php foreach ($stores as $i => $store): ?>
                            <label class="bkstore">
                                <input class="bkstore__dot" type="radio" name="store_id"
                                       id="bk-st-<?= $i ?>" value="<?= e($store['id']) ?>"
                                       <?= $i === $pick['store'] ? 'checked' : '' ?>>
                                <span class="bkstore__text">
                                    <span class="bkstore__name"><?= e($store['name']) ?></span>
                                    <span class="bkstore__addr"><?= e($store['address']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ────── 2. DỊCH VỤ ────── -->
                <div class="bkcard">
                    <div class="bkcard__head">
                        <span class="bkcard__num" aria-hidden="true">2</span>
                        <h2 class="bkcard__title">Chọn dịch vụ</h2>
                    </div>

                    <div class="bkpills">
                        <?php foreach ($services as $i => $service): ?>
                            <label class="bkpill">
                                <input class="bk__radio" type="radio" name="service_type"
                                       id="bk-sv-<?= $i ?>" value="<?= e($service) ?>"
                                       <?= $i === $pick['service'] ? 'checked' : '' ?>>
                                <span><?= e($service) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ────── 3. NGÀY & GIỜ ────── -->
                <div class="bkcard">
                    <div class="bkcard__head">
                        <span class="bkcard__num" aria-hidden="true">3</span>
                        <h2 class="bkcard__title">Chọn ngày &amp; giờ</h2>
                    </div>

                    <?php /* NÓI RA RẰNG ĐÂY LÀ NGUYỆN VỌNG, KHÔNG PHẢI CHỖ ĐÃ GIỮ.
                             Cửa hàng bỏ giới hạn số người trên một khung giờ, nên
                             mọi khung đều bấm được và không khung nào "hết chỗ".
                             Không nói thì khách mặc định hiểu là đã đặt xong chỗ
                             lúc 15:00 và cứ thế đến — trong khi cái chốt thật là
                             cuộc gọi xác nhận của cửa hàng. */ ?>
                    <p class="bkcard__note">
                        Chọn khung giờ bạn thấy tiện nhất. Cửa hàng sẽ gọi lại để
                        xác nhận và sắp xếp, nên bạn không cần lo khung giờ hết chỗ.
                    </p>

                    <div class="bkdays">
                        <?php foreach ($days as $i => $day): ?>
                            <!-- Nhóm radio này CHỈ để chọn lưới giờ nào hiện ra; server
                                 bỏ qua nó. Ngày thật nằm trong giá trị của ô giờ, nên
                                 hai thứ không thể lệch nhau. -->
                            <label class="bkday">
                                <input class="bk__radio" type="radio" name="bk_day"
                                       id="bk-d-<?= $i ?>" value="<?= $i ?>"
                                       <?= $i === $pick['day'] ? 'checked' : '' ?>>
                                <span class="bkday__box">
                                    <span class="bkday__wd"><?= e($day['weekday']) ?></span>
                                    <span class="bkday__dm"><?= e($day['dm']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    /*
                     * MỘT LƯỚI CHO MỖI NGÀY, KHÔNG NHÂN THEO CƠ SỞ.
                     *
                     * Bản trước lồng thêm một vòng cơ sở ở ngoài, vì mỗi cơ sở
                     * có tập khung đã kín riêng. Nay không khung nào bị kín —
                     * cửa hàng bỏ giới hạn số người trên một khung giờ — nên
                     * lưới giống hệt nhau ở mọi cơ sở và vòng ngoài chỉ còn
                     * nhân bản đúng một thứ lên vài lần.
                     *
                     * Ô duy nhất bị khoá là giờ ĐÃ TRÔI QUA của hôm nay.
                     */
                    ?>
                    <?php foreach ($grid as $di => $cells): ?>
                        <?php $free = array_filter($cells, static fn (array $c): bool => $c['free']); ?>
                        <div class="bkdpane bkdpane--<?= $di ?>">
                            <span class="bkslots__label">
                                Chọn khung giờ — <?= e($days[$di]['dm']) ?>
                            </span>

                            <div class="bkslots">
                                <?php foreach ($cells as $ti => $cell): ?>
                                    <label class="bkpill<?= $cell['free'] ? '' : ' bkpill--off' ?>">
                                        <input class="bk__radio" type="radio" name="time_slot"
                                               value="<?= $di ?>|<?= $ti ?>"
                                               <?= $cell['free'] ? '' : 'disabled' ?>
                                               <?= ($pick['day'] === $di && $pick['time'] === $ti && $cell['free'])
                                                   ? 'checked' : '' ?>>
                                        <span><?= e($cell['label']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($free === []): ?>
                                <?php /* Chỉ xảy ra với HÔM NAY, sau khung cuối cùng
                                         trong ngày. Không còn trường hợp "kín lịch". */ ?>
                                <p class="bkslots__none">
                                    Hôm nay đã hết giờ nhận khách. Bạn chọn giúp một ngày khác nhé.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ────── 4. THÔNG TIN ────── -->
                <div class="bkcard">
                    <div class="bkcard__head">
                        <span class="bkcard__num" aria-hidden="true">4</span>
                        <h2 class="bkcard__title">Thông tin của bạn</h2>
                    </div>

                    <div class="bkfields">
                        <label class="bkfield">
                            <span class="bkfield__label">Họ và tên *</span>
                            <input class="bkfield__input" type="text" name="full_name" required
                                   minlength="2" maxlength="120" autocomplete="name"
                                   placeholder="Nguyễn Văn A"
                                   value="<?= e($old['fullName'] ?? '') ?>">
                        </label>

                        <label class="bkfield">
                            <span class="bkfield__label">Số điện thoại *</span>
                            <input class="bkfield__input" type="tel" name="phone" required
                                   autocomplete="tel" inputmode="tel"
                                   placeholder="09xx xxx xxx"
                                   value="<?= e($old['phone'] ?? '') ?>">
                        </label>
                    </div>

                    <label class="bkfield">
                        <span class="bkfield__label">
                            Ghi chú <em class="bkfield__opt">(không bắt buộc)</em>
                        </span>
                        <textarea class="bkfield__area" name="note" rows="3" maxlength="500"
                                  placeholder="Ví dụ: đang đeo kính cận 2 độ, muốn tư vấn tròng chống ánh sáng xanh…"><?= e($old['note'] ?? '') ?></textarea>
                    </label>
                </div>
            </div>

            <!-- ══════════ CỘT PHẢI ══════════ -->
            <aside class="bkside">

                <div class="bkside__card">
                    <h2 class="bkside__title">Lịch hẹn của bạn</h2>

                    <div class="bksum">
                        <div class="bksum__row">
                            <span class="bksum__k">Cơ sở</span>
                            <span class="bksum__v">
                                <?php foreach ($stores as $i => $store): ?><span
                                    class="bksum-st-<?= $i ?>"><?= e($store['name']) ?></span><?php endforeach; ?>
                            </span>
                        </div>

                        <div class="bksum__row">
                            <span class="bksum__k">Dịch vụ</span>
                            <span class="bksum__v">
                                <?php foreach ($services as $i => $service): ?><span
                                    class="bksum-sv-<?= $i ?>"><?= e($service) ?></span><?php endforeach; ?>
                            </span>
                        </div>

                        <div class="bksum__row">
                            <span class="bksum__k">Thời gian</span>
                            <span class="bksum__v">
                                <!-- "Chưa chọn giờ" biến mất ngay khi có một ô giờ được
                                     chọn; giờ và ngày đều đọc từ CHÍNH ô giờ đó nên
                                     không bao giờ lệch nhau. -->
                                <span class="bksum__none">Chưa chọn giờ</span
                                ><?php foreach ($slots as $i => $slot): ?><span
                                    class="bksum-t-<?= $i ?>"><?= e($slot) ?></span><?php endforeach; ?><span
                                    class="bksum__sep"> · </span
                                ><?php foreach ($days as $i => $day): ?><span
                                    class="bksum-d-<?= $i ?>"><?= e($day['dm']) ?></span><?php endforeach; ?>
                            </span>
                        </div>

                        <div class="bksum__row">
                            <span class="bksum__k">Chi phí</span>
                            <span class="bksum__free">Miễn phí</span>
                        </div>
                    </div>

                    <button type="submit" class="bkside__cta">Xác nhận đặt lịch</button>

                    <?php if ($success !== null): ?>
                        <div class="bkok" role="status">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20 6L9 17l-5-5"></path>
                            </svg>
                            <span>
                                Đã ghi nhận! Mã lịch hẹn <strong><?= e($success) ?></strong>.
                                Chúng tôi sẽ gọi xác nhận trong 15 phút.
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bkflow">
                    <h2 class="bkflow__title">Quy trình đo mắt</h2>

                    <?php foreach ($steps as $i => $step): ?>
                        <div class="bkflow__step">
                            <span class="bkflow__n"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="bkflow__text"><?= e($step) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <div class="bkflow__rule"></div>

                    <p class="bkflow__note">
                        Toàn bộ quy trình 25–40 phút, <strong>hoàn toàn miễn phí</strong>
                        kể cả khi bạn chưa mua kính. Cần hỗ trợ?
                        <a href="<?= e($company['hotline_href']) ?>"><?= e($company['hotline']) ?></a>
                    </p>
                </div>
            </aside>
        </form>

    <?php endif; ?>
</section>
