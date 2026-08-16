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
 */

$tones = [
    'pending'   => 'wait',
    'confirmed' => 'prep',
    'done'      => 'done',
    'cancelled' => 'stop',
];

$today = date('Y-m-d');
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
                    <span class="acct-badge acct-badge--<?= e($tones[$a['status']] ?? 'wait') ?>">
                        <?= e($bookingStatuses[$a['status']] ?? $a['status']) ?>
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
                            <?= e(formatDate($a['appointment_date'])) ?> · <?= e($a['time_slot']) ?>
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

                <div class="acct-order__foot">
                    <?php if ($a['status'] === 'done' || $a['appointment_date'] < $today): ?>
                        <a class="acct-btn acct-btn--primary acct-btn--sm" href="/dat-lich">Đặt lại</a>
                    <?php endif; ?>
                    <!-- Đổi/huỷ lịch cần đối chiếu khung giờ còn trống của cơ
                         sở, nên làm qua tổng đài chứ không tự sửa tại đây. -->
                    <a class="acct-btn acct-btn--outline acct-btn--sm" href="/lien-he">Đổi hoặc huỷ lịch</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
