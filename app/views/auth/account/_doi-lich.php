<?php

/**
 * auth/account/_doi-lich.php — form đổi NGÀY hẹn, mở ngay trong thẻ lịch hẹn.
 *
 * Mở bằng ?doi=<mã lịch> trên /tai-khoan?muc=lich-hen — xem ghi chú đầu
 * app/views/auth/account/lich-hen.php.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TỪ HAI FORM CÒN MỘT — 2026-08-25
 *
 * Bản trước có hai form đứng cạnh nhau (HTML không cho lồng <form>):
 *
 *   1. form GET đổi NGÀY đang xem, gửi lại /tai-khoan kèm ?ngay=… để máy chủ
 *      dựng lại danh sách giờ trống của ngày đó;
 *   2. form POST chốt GIỜ đã chọn.
 *
 * Cả vòng đi-về ấy chỉ tồn tại để lấy danh sách giờ trống. Khách không chọn
 * giờ nữa (giả định A5 — xem BookingModel), nên không còn gì phải hỏi máy chủ
 * giữa chừng: chọn ngày rồi bấm là xong, một form POST duy nhất.
 *
 * Kéo theo: $slotDate và $freeSlots không cần nữa, và khối tự-gửi-khi-đổi-ngày
 * trong assets/js/account.js cũng vậy — nó bỏ đi cái bấm thứ hai của một luồng
 * nay chỉ còn một cái bấm.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Nhận qua partial():
 *   $appointment — lịch đang đổi (đã kiểm đúng chủ ở BookingModel::findOwned)
 */

/* Giới hạn ngày chọn được: từ NGÀY MAI tới 60 ngày tới.
 *
 * Cận dưới là ngày mai chứ không phải hôm nay, khớp với luật ở
 * BookingModel::rescheduleOwned(): sang tới ngày hẹn là khách thôi tự đổi, gọi
 * tổng đài. Để min là hôm nay thì trình duyệt cho chọn một ngày mà máy chủ
 * chắc chắn từ chối.
 *
 * Cận trên giữ nguyên ý cũ — nhận lịch xa hơn hai tháng thì cửa hàng không giữ
 * nổi cam kết. */
$minDate = date('Y-m-d', strtotime('+1 day'));
$maxDate = date('Y-m-d', strtotime('+60 days'));
?>

<div class="acct-resched" id="doi-<?= e($appointment['code']) ?>">

    <span class="acct-order__eyebrow">Đổi ngày hẹn</span>

    <p class="acct-resched__now">
        Hiện tại: <strong><?= e(formatDate($appointment['appointment_date'])) ?><?php
            /* Lịch cũ (đặt khi form còn ô giờ) vẫn có time_slot, và cả lịch mà
               nhân viên đã xếp giờ hộ cũng vậy. Có thì in ra — khách cần thấy
               đúng cái đang được đổi. Không có thì im lặng bỏ qua, đừng in một
               dấu gạch trống trông như dữ liệu hỏng. */
            if (!empty($appointment['time_slot'])):
        ?> · <?= e($appointment['time_slot']) ?><?php endif; ?></strong>
        tại <?= e($appointment['store_name'] ?? 'Cơ sở Vin Eyewear') ?>
    </p>

    <?php /* Cơ sở KHÔNG đổi được ở đây — đổi cơ sở là đổi gần hết thông tin của
             lần hẹn, việc đó nên là một lịch mới. Nói thẳng ra để khách không đi
             tìm ô chọn cơ sở. */ ?>
    <p class="acct-resched__hint">
        Muốn đổi sang cơ sở khác thì <a href="/dat-lich">đặt lịch mới</a> rồi huỷ lịch này.
    </p>

    <form method="post" action="/tai-khoan/lich-hen/doi">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="code" value="<?= e($appointment['code']) ?>">

        <label class="acct-resched__label" for="ngay-<?= e($appointment['code']) ?>">
            Chọn ngày mới
        </label>
        <?php /* KHÔNG điền sẵn ngày hẹn hiện tại: điền sẵn rồi bấm "Xác nhận" là
                 gửi đi đúng ngày cũ, và câu trả lời duy nhất máy chủ có thể đưa
                 ra là một lỗi ("đang chọn đúng ngày hẹn hiện tại"). Để trống thì
                 `required` buộc khách chọn một ngày KHÁC — đúng việc họ vào đây
                 để làm. */ ?>
        <input type="date" id="ngay-<?= e($appointment['code']) ?>" name="date"
               min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>"
               class="acct-resched__date" required>

        <div class="acct-resched__acts">
            <button type="submit" class="acct-btn acct-btn--primary acct-btn--sm">
                Xác nhận ngày mới
            </button>
            <?php /* Đóng form = về đúng mục lịch hẹn, cuộn tới thẻ vừa mở */ ?>
            <a class="acct-btn acct-btn--quiet acct-btn--sm"
               href="/tai-khoan?muc=lich-hen">Để nguyên</a>
        </div>

        <p class="acct-resched__hint">
            Đổi ngày xong lịch quay về <strong>Chờ xác nhận</strong> — cửa hàng
            sẽ gọi để thống nhất giờ cho ngày mới.
        </p>
    </form>
</div>
