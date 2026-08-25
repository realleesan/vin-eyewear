<?php

/**
 * auth/account/lich-hen.php — mục "Lịch hẹn của tôi" (/tai-khoan?muc=lich-hen).
 *
 * KHÔNG có trong "Vin Eyewear Account.dc.html" — xem ghi chú số 2 ở đầu
 * app/views/auth/profile.php để biết vì sao mục này vẫn ở đây.
 *
 * Dựng bằng ĐÚNG những nguyên thể mà bản thiết kế đã định nghĩa cho mục đơn
 * hàng (thẻ .acct-card, đầu thẻ mã + huy hiệu, chân thẻ nút hành động), nên
 * hai mục nhìn như một bộ chứ không phải hai trang khác nhau ghép lại.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỔI VÀ HUỶ LỊCH
 *
 * Nút chỉ hiện khi lịch THẬT SỰ đổi/huỷ được, và câu trả lời đó do
 * BookingModel::changeBlocker() đưa ra — chính hàm mà controller gọi lại trước
 * khi ghi. Một nguồn cho cả hai bên, nên không có cảnh nút hiện ra rồi bấm vào
 * bị chặn (hoặc ngược lại: nút ẩn nhưng POST tay vẫn ghi được).
 *
 * Không đổi/huỷ được thì KHÔNG ẩn đi im lặng: in luôn lý do (đã qua giờ hẹn,
 * còn dưới hạn cho phép…) kèm lối gọi tổng đài. Một thẻ lịch hẹn không có nút
 * nào và không nói vì sao là chỗ khách sẽ gọi điện để hỏi.
 *
 * Form chọn giờ mới mở bằng ?doi=<mã> NGAY TRONG thẻ đó — cùng lối với ?sua=
 * của sổ địa chỉ. Đổi ngày trong form là một GET (?ngay=…) để máy chủ dựng lại
 * danh sách giờ trống, nên không cần một dòng JavaScript nào.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$tones = [
    'pending'   => 'wait',
    'confirmed' => 'sure',
    'done'      => 'done',
    'cancelled' => 'stop',
];

$today = date('Y-m-d');

/* Lịch đang mở form đổi ngày, và bảng "vì sao lịch này không sửa được nữa" —
   controller dựng sẵn hết. View KHÔNG tự gọi BookingModel::changeBlocker: giữ
   đúng lối của trang này, mọi luật nghiệp vụ đi qua controller. */
$blockers = $blockers ?? [];
$editing  = $editing  ?? null;

/** Đường dẫn mở form đổi ngày của một lịch. */
$editHref = static fn (string $code): string =>
    '/tai-khoan?muc=lich-hen&doi=' . rawurlencode($code);

/**
 * Ngày hẹn, kèm giờ NẾU CÓ.
 *
 * Lịch đặt từ 2026-08-25 trở đi không có giờ: khách chỉ chọn ngày, cửa hàng
 * chốt giờ qua điện thoại (giả định A5). Lịch cũ thì vẫn có. Nối bằng " · "
 * chỉ khi thật sự có vế thứ hai — không thì thẻ hiện "25/08 · " cụt đuôi,
 * trông như dữ liệu hỏng chứ không như một lịch chưa xếp giờ.
 */
$khiNao = static function (array $a): string {
    $ngay = formatDate($a['appointment_date']);

    return empty($a['time_slot']) ? $ngay : $ngay . ' · ' . $a['time_slot'];
};
?>

<?php
/* Đầu mục NẰM NGOÀI nhánh rỗng/không-rỗng, cố ý: nút "Đặt lịch mới" phải ở đúng
   một chỗ dù khách có lịch hay chưa. Đưa nó vào trong nhánh thì lúc chưa có lịch
   nào, hành động chính của cả mục lại nằm ở giữa thẻ trạng thái rỗng và nhảy chỗ
   ngay khi khách đặt lịch đầu tiên. ĐỪNG di chuyển khối này vào trong. */
?>
<div class="acct-head acct-head--row">
    <div>
        <h1 class="acct-head__title">Lịch hẹn của tôi</h1>
        <p class="acct-head__lead">Lịch đo mắt và tư vấn tại cửa hàng.</p>
    </div>
    <a class="acct-btn acct-btn--primary" href="/dat-lich">Đặt lịch mới</a>
</div>

<?php if ($appointments === []): ?>
    <div class="acct-empty">
        <span class="acct-empty__ring" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#b0736a"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M7 3v3M17 3v3"></path>
                <path d="M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"></path>
            </svg>
        </span>
        <span class="acct-empty__title">Chưa có lịch hẹn nào</span>
        <span class="acct-empty__lead">Đo khúc xạ tại Vin Eyewear miễn phí, chỉ mất khoảng 20 phút.</span>
        <a class="acct-empty__cta" href="/dat-lich">Đặt lịch đo mắt</a>
    </div>
<?php else: ?>
    <div class="acct-list">
        <?php foreach ($appointments as $a): ?>
            <div class="acct-card acct-order">

                <div class="acct-order__top">
                    <div class="acct-order__id">
                        <span class="acct-order__code"><?= e($a['code']) ?></span>
                        <span class="acct-order__when">Đặt ngày <?= e(formatDate($a['created_at'])) ?></span>
                    </div>
                    <?php
                    /* ─────────────────────────────────────────────────────
                       LỊCH QUÁ NGÀY ĐỌC LÀ "ĐÃ QUA", KHÔNG PHẢI "CHỜ XÁC NHẬN".

                       Không ai và không tiến trình nền nào đổi trạng thái một
                       lịch đã qua ngày, nên trong CSDL nó vẫn là 'pending'
                       hoặc 'confirmed' mãi mãi. Hiện nguyên chữ đó thì khách
                       mở trang thấy "Chờ xác nhận" cho một buổi hẹn của tháng
                       trước — và ngồi chờ một cuộc gọi sẽ không bao giờ tới.

                       "Đã qua" là trạng thái SUY RA lúc vẽ, xem
                       BookingModel::isExpired(). Cùng ngưỡng ngày với
                       countUpcoming(), nên con số trên huy hiệu ở cột trái và
                       chữ trên thẻ này không bao giờ nói hai điều khác nhau.
                       ───────────────────────────────────────────────────── */
                    $quaHan = BookingModel::isExpired($a);
                    ?>
                    <span class="acct-badge acct-badge--<?= $quaHan ? 'gone' : e($tones[$a['status']] ?? 'wait') ?>">
                        <?= $quaHan ? 'Đã qua' : e($bookingStatuses[$a['status']] ?? $a['status']) ?>
                    </span>
                </div>

                <div class="acct-order__line">
                    <div class="acct-order__thumb acct-order__thumb--icon" aria-hidden="true">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#c9a79b"
                             stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="6.5" cy="12" r="4"></circle>
                            <circle cx="17.5" cy="12" r="4"></circle>
                            <path d="M10.5 12h3M2.5 10.5L1 9M21.5 10.5L23 9"></path>
                        </svg>
                    </div>

                    <div class="acct-order__what">
                        <span class="acct-order__brand"><?= e($a['service_type']) ?></span>
                        <span class="acct-order__name">
                            <?= e($khiNao($a)) ?>
                        </span>
                        <span class="acct-order__variant">
                            <?= e($a['store_name'] ?? 'Cơ sở Vin Eyewear') ?>
                            <?php if (!empty($a['store_address'])): ?>
                                · <?= e($a['store_address']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php if (!empty($a['note'])): ?>
                    <p class="acct-order__note">Ghi chú: <?= e($a['note']) ?></p>
                <?php endif; ?>

                <?php
                $blocker   = $blockers[$a['code']] ?? null;
                $isEditing = $editing !== null && $editing['code'] === $a['code'];

                /* Lịch CÒN HIỆU LỰC nhưng đã ngoài hạn tự sửa — chỉ đúng trường
                   hợp này thì gọi tổng đài mới có nghĩa, và chỉ ở đây mới cần in
                   lý do. Lịch đã huỷ / đã hoàn tất thì huy hiệu ở đầu thẻ đã nói
                   rồi, nhắc lại thành hai lần cùng một câu, mà tổng đài cũng
                   không làm được gì.

                   Lịch ĐÃ QUÁ NGÀY cũng vậy kể từ khi huy hiệu biết nói "Đã
                   qua": in thêm câu "Giờ hẹn đã qua." ngay dưới là lặp lại
                   đúng điều vừa đọc. Nút "Đặt lại" bên cạnh mới là thứ khách
                   cần ở đó. */
                $callable = $blocker !== null
                    && !$quaHan
                    && in_array($a['status'], ['pending', 'confirmed'], true);
                ?>

                <?php if ($blocker === null && $isEditing): ?>
                    <?php partial('auth/account/_doi-lich', ['appointment' => $a]); ?>
                <?php endif; ?>

                <div class="acct-order__foot">
                    <?php if ($callable): ?>
                        <span class="acct-order__footnote"><?= e($blocker) ?></span>
                    <?php endif; ?>

                    <div class="acct-order__acts">
                        <?php if ($a['status'] === 'done' || $a['appointment_date'] < $today): ?>
                            <a class="acct-btn acct-btn--primary acct-btn--sm" href="/dat-lich">Đặt lại</a>
                        <?php endif; ?>

                        <?php if ($blocker === null): ?>
                            <?php if (!$isEditing): ?>
                                <a class="acct-btn acct-btn--primary acct-btn--sm"
                                   href="<?= e($editHref($a['code'])) ?>">
                                    Đổi ngày hẹn
                                </a>
                            <?php endif; ?>

                            <?php
                            /* Huỷ là thao tác KHÔNG lấy lại được, nên nó là <form>
                               POST có CSRF, và có một lớp hỏi lại. onsubmit chỉ là
                               lớp thứ hai: tắt JS thì vẫn huỷ được, nhưng nút nằm
                               ở dạng lặng nhất trong chân thẻ. */
                            ?>
                            <?php
                            /* MỘT câu hỏi, hai đường dùng — xem chú thích ở
                               assets/js/confirm-dialog.js. onsubmit là lớp dự
                               phòng khi không có JS; chính file JS đó gỡ nó ra
                               khi đã sẵn sàng mở hộp thoại trên trang. */
                            $hoiHuyLich = sprintf(
                                'Huỷ lịch đo mắt %s?',
                                $khiNao($a)
                            );
                            ?>
                            <form method="post" action="/tai-khoan/lich-hen/huy"
                                  data-confirm="<?= e($hoiHuyLich) ?>"
                                  data-confirm-title="Huỷ lịch hẹn?"
                                  data-confirm-ok="Huỷ lịch"
                                  data-confirm-cancel="Giữ lịch"
                                  onsubmit="return confirm('<?= e($hoiHuyLich) ?>')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="code" value="<?= e($a['code']) ?>">
                                <button type="submit" class="acct-btn acct-btn--quiet acct-btn--sm">
                                    Huỷ lịch
                                </button>
                            </form>
                        <?php elseif ($callable): ?>
                            <!-- Ngoài hạn tự sửa: tổng đài còn kịp gọi người trong
                                 danh sách chờ, còn form thì không. -->
                            <a class="acct-btn acct-btn--outline acct-btn--sm" href="/lien-he">Gọi tổng đài</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
