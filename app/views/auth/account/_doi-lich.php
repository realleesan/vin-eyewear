<?php

/**
 * auth/account/_doi-lich.php — form đổi giờ hẹn, mở ngay trong thẻ lịch hẹn.
 *
 * Mở bằng ?doi=<mã lịch> trên /tai-khoan?muc=lich-hen — xem ghi chú đầu
 * app/views/auth/account/lich-hen.php.
 *
 * HAI FORM LỒNG CẠNH NHAU, KHÔNG LỒNG VÀO NHAU (HTML không cho lồng <form>):
 *
 *   1. form GET — đổi NGÀY đang xem. Gửi lại chính /tai-khoan kèm ?ngay=…, máy
 *      chủ dựng lại danh sách giờ trống của ngày đó. Không cần JavaScript; có JS
 *      thì account.js gửi luôn khi đổi ngày (data-autosubmit).
 *   2. form POST — chốt giờ đã chọn.
 *
 * Vì sao không nạp giờ trống bằng fetch(): danh sách giờ trống là dữ liệu có thể
 * đổi trong lúc khách đang chọn, và dù nạp bằng cách nào thì chốt chặn cuối vẫn
 * là ràng buộc ở CSDL. Một vòng tải lại trang đơn giản hơn hẳn và không có
 * trạng thái nào để lệch.
 *
 * Nhận qua partial():
 *   $appointment — lịch đang đổi (đã kiểm đúng chủ ở BookingModel::findOwned)
 *   $slotDate    — ngày đang xem giờ trống (YYYY-MM-DD)
 *   $freeSlots   — khung giờ còn trống của cơ sở đó trong ngày đó
 */

/* Giới hạn ngày chọn được: từ hôm nay tới 60 ngày tới. Cùng ý với trang đặt lịch
   — nhận lịch xa hơn hai tháng thì cửa hàng không giữ nổi cam kết. */
$minDate = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+60 days'));

/* Giờ hẹn hiện tại vẫn nằm trong danh sách nếu khách xem đúng ngày cũ:
   availableSlots() coi nó là "đã có người đặt" (chính khách này), nên phải thêm
   lại — nếu không, khách đổi ngày rồi đổi ý quay về ngày cũ sẽ thấy giờ của
   CHÍNH MÌNH biến mất. */
$sameDay = $slotDate === $appointment['appointment_date'];
$slots   = $freeSlots;

if ($sameDay && !in_array($appointment['time_slot'], $slots, true)) {
    $slots[] = $appointment['time_slot'];
    sort($slots);
}
?>

<div class="acct-resched" id="doi-<?= e($appointment['code']) ?>">

    <span class="acct-order__eyebrow">Đổi giờ hẹn</span>

    <p class="acct-resched__now">
        Hiện tại: <strong><?= e(formatDate($appointment['appointment_date'])) ?>
        · <?= e($appointment['time_slot']) ?></strong>
        tại <?= e($appointment['store_name'] ?? 'Cơ sở Vin Eyewear') ?>
    </p>

    <?php /* Cơ sở KHÔNG đổi được ở đây — đổi cơ sở là đổi gần hết thông tin của
             lần hẹn, việc đó nên là một lịch mới. Nói thẳng ra để khách không đi
             tìm ô chọn cơ sở. */ ?>
    <p class="acct-resched__hint">
        Muốn đổi sang cơ sở khác thì <a href="/dat-lich">đặt lịch mới</a> rồi huỷ lịch này.
    </p>

    <form class="acct-resched__day" method="get" action="/tai-khoan">
        <input type="hidden" name="muc" value="lich-hen">
        <input type="hidden" name="doi" value="<?= e($appointment['code']) ?>">

        <label class="acct-resched__label" for="ngay-<?= e($appointment['code']) ?>">Chọn ngày</label>
        <input type="date" id="ngay-<?= e($appointment['code']) ?>" name="ngay"
               value="<?= e($slotDate) ?>" min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>"
               class="acct-resched__date" data-autosubmit>

        <?php /* Có JS thì account.js gửi form ngay khi đổi ngày và nút này ẩn đi
                 (cùng cách làm với ô chọn trạng thái trong khu quản trị). */ ?>
        <button type="submit" class="acct-btn acct-btn--outline acct-btn--sm acct-resched__go">
            Xem giờ trống
        </button>
    </form>

    <?php if ($slots === []): ?>
        <p class="acct-resched__empty">
            Ngày <?= e(formatDate($slotDate)) ?> đã hết giờ trống ở cơ sở này. Hãy chọn ngày khác.
        </p>
    <?php else: ?>
        <form method="post" action="/tai-khoan/lich-hen/doi">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="code" value="<?= e($appointment['code']) ?>">
            <input type="hidden" name="date" value="<?= e($slotDate) ?>">

            <fieldset class="acct-resched__slots">
                <legend class="acct-resched__label">
                    Giờ trống ngày <?= e(formatDate($slotDate)) ?>
                </legend>

                <?php foreach ($slots as $slot): ?>
                    <?php $isNow = $sameDay && $slot === $appointment['time_slot']; ?>
                    <?php
                    /* KHÔNG chọn sẵn giờ hiện tại: chọn sẵn rồi bấm "Xác nhận"
                       là gửi đi đúng giờ cũ, và câu trả lời duy nhất máy chủ có
                       thể đưa ra là một lỗi ("đang chọn đúng giờ hẹn hiện tại").
                       Để trống thì `required` buộc khách chọn một giờ KHÁC — đúng
                       việc họ vào đây để làm. */
                    ?>
                    <label class="acct-slot<?= $isNow ? ' is-now' : '' ?>">
                        <input type="radio" name="slot" value="<?= e($slot) ?>" required>
                        <span><?= e($slot) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="acct-resched__acts">
                <button type="submit" class="acct-btn acct-btn--primary acct-btn--sm">
                    Xác nhận giờ mới
                </button>
                <?php /* Đóng form = về đúng mục lịch hẹn, cuộn tới thẻ vừa mở */ ?>
                <a class="acct-btn acct-btn--quiet acct-btn--sm"
                   href="/tai-khoan?muc=lich-hen">Để nguyên</a>
            </div>

            <p class="acct-resched__hint">
                Đổi giờ xong lịch quay về <strong>Chờ xác nhận</strong> — cửa hàng
                sẽ gọi xác nhận lại giờ mới.
            </p>
        </form>
    <?php endif; ?>
</div>
