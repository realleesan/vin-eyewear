<?php

/**
 * auth/account/_the-lich.php — MỘT thẻ lịch hẹn, dáng thẻ "Appointments" của
 * "Ho So Nguoi Dung.dc.html":
 *
 *   ┌───────────────────────────────────────────────────────┐
 *   │ Đo mắt cận/loạn (15px)                 ( ĐÃ XÁC NHẬN ) │
 *   │ Thứ 7, 20/09/2026                             ĐỔI NGÀY │
 *   │ Cơ sở Quận 1 (xám)                                HUỶ  │
 *   └───────────────────────────────────────────────────────┘
 *
 * Một file cho cả hai khối "Sắp tới" và "Đã qua" của lich-hen.php.
 *
 * NÚT CHỈ HIỆN KHI LỊCH THẬT SỰ ĐỔI/HUỶ ĐƯỢC, và câu trả lời do
 * BookingModel::changeBlocker() đưa ra — chính hàm controller gọi lại trước khi
 * ghi, nên nút hiện ra và phép kiểm lúc ghi không thể lệch. Lịch còn hiệu lực mà
 * không tự sửa được thì IN LÝ DO kèm lối gọi tổng đài, không ẩn đi im lặng.
 *
 * Nhận qua partial(): $a · $blocker (?string) · $dangDoi (bool) · $nhanTrangThai
 */

/* LỊCH QUÁ NGÀY ĐỌC LÀ "ĐÃ QUA", KHÔNG PHẢI "CHỜ XÁC NHẬN": không ai đổi trạng
   thái một lịch đã qua ngày, nên trong CSDL nó vẫn 'pending' mãi — xem
   BookingModel::isExpired(). */
$quaHan  = BookingModel::isExpired($a);
$goiDuoc = $blocker !== null && !$quaHan
    && in_array($a['status'], ['pending', 'confirmed'], true);
$datLai  = $a['status'] === 'done' || $a['appointment_date'] < date('Y-m-d');
$ngay    = BookingModel::nhanNgay($a['appointment_date']);
$hoiHuy  = sprintf('Huỷ lịch hẹn %s?', $ngay);
?>
<article class="acct-card" id="lich-<?= e($a['code']) ?>">
    <div class="acct-card__body">
        <span class="acct-card__lead"><?= e($a['service_type']) ?></span>
        <span><?= e($ngay) ?></span>
        <span class="acct-mute"><?= e($a['store_name'] ?? 'Cơ sở Vin Eyewear') ?></span>
        <?php if (!empty($a['note'])): ?>
            <span class="acct-mute">Ghi chú: <?= e($a['note']) ?></span>
        <?php endif; ?>
        <?php if ($goiDuoc): ?>
            <span class="acct-mute"><?= e($blocker) ?></span>
        <?php endif; ?>
    </div>

    <div class="acct-card__acts">
        <span class="acct-pill"><?= $quaHan ? 'Đã qua' : e($nhanTrangThai[$a['status']] ?? $a['status']) ?></span>

        <?php if ($blocker === null && !$dangDoi): ?>
            <a class="acct-act" href="/tai-khoan?muc=lich-hen&amp;doi=<?= e(rawurlencode($a['code'])) ?>#lich-<?= e($a['code']) ?>">Đổi ngày</a>
        <?php endif; ?>

        <?php if ($blocker === null): ?>
            <?php /* POST có CSRF + hỏi lại: huỷ là thao tác không lấy lại được.
                     onsubmit là lớp dự phòng khi không có JS — confirm-dialog.js
                     gỡ nó ra khi đã sẵn sàng mở hộp thoại trên trang. */ ?>
            <form method="post" action="/tai-khoan/lich-hen/huy"
                  data-confirm="<?= e($hoiHuy) ?>"
                  data-confirm-title="Huỷ lịch hẹn?"
                  data-confirm-ok="Huỷ lịch"
                  data-confirm-cancel="Giữ lịch"
                  onsubmit="return confirm('<?= e($hoiHuy) ?>')">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="code" value="<?= e($a['code']) ?>">
                <button type="submit" class="acct-act acct-act--mute">Huỷ</button>
            </form>
        <?php elseif ($goiDuoc): ?>
            <?php /* Ngoài hạn tự sửa: tổng đài còn kịp xếp lại, form thì không. */ ?>
            <a class="acct-act" href="/lien-he">Gọi tổng đài</a>
        <?php endif; ?>

        <?php if ($datLai): ?>
            <a class="acct-act" href="/tai-khoan?muc=lich-hen&amp;dat=1#dat-lich">Đặt lại</a>
        <?php endif; ?>
    </div>

    <?php if ($blocker === null && $dangDoi): ?>
        <?php partial('auth/account/_doi-lich', ['appointment' => $a]); ?>
    <?php endif; ?>
</article>
