<?php

/**
 * auth/account/_doi-lich.php — form đổi NGÀY hẹn, mở ngay trong thẻ lịch hẹn.
 *
 * Mở bằng ?doi=<mã lịch> trên /tai-khoan?muc=lich-hen — xem _the-lich.php.
 * Vẽ bằng nét form của bản thiết kế "Ho So Nguoi Dung": ô có nhãn nhỏ nằm
 * trong, hai nút HUỶ · XÁC NHẬN chia đôi, nút xác nhận xám cho tới khi chọn
 * ngày.
 *
 * MỘT FORM POST DUY NHẤT: khách không chọn giờ (giả định A5 — xem
 * BookingModel), nên không còn gì phải hỏi máy chủ giữa chừng.
 *
 * Nhận qua partial():
 *   $appointment — lịch đang đổi (đã kiểm đúng chủ ở BookingModel::findOwned)
 */

/* Giới hạn ngày chọn được: từ NGÀY MAI tới trần đặt trước.
 *
 * Cận dưới là ngày mai, khớp với BookingModel::rescheduleOwned(): sang tới ngày
 * hẹn là khách thôi tự đổi, gọi tổng đài. Để min là hôm nay thì trình duyệt cho
 * chọn một ngày mà máy chủ chắc chắn từ chối.
 *
 * Cận trên đọc từ BookingModel::DAT_TRUOC_TOI_DA chứ không gõ lại 30: một con số
 * nghiệp vụ nằm ở hai chỗ là hai chỗ phải nhớ sửa cùng lúc. */
$minDate = date('Y-m-d', strtotime('+1 day'));
$maxDate = date('Y-m-d', strtotime('+' . BookingModel::DAT_TRUOC_TOI_DA . ' days'));
?>

<form class="acct-card__more" id="doi-<?= e($appointment['code']) ?>" method="post" action="/tai-khoan/lich-hen/doi">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="code" value="<?= e($appointment['code']) ?>">

    <h3 class="acct-label">Đổi ngày hẹn</h3>

    <?php /* Cơ sở KHÔNG đổi được ở đây — đổi cơ sở là đổi gần hết thông tin của
             lần hẹn, việc đó nên là một lịch mới. Nói thẳng ra để khách không đi
             tìm ô chọn cơ sở. */ ?>
    <p class="acct-note">
        Muốn đổi sang cơ sở khác thì <a href="/tai-khoan?muc=lich-hen&amp;dat=1#dat-lich">đặt lịch mới</a> rồi huỷ lịch này.
    </p>

    <?php /* KHÔNG điền sẵn ngày hẹn hiện tại: điền sẵn rồi bấm xác nhận là gửi đi
             đúng ngày cũ, và máy chủ chỉ trả lời được bằng một lỗi. */ ?>
    <label class="acct-field">
        <span class="acct-field__cap">Ngày mới</span>
        <input class="acct-field__ctl" type="date" name="date" required
               min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>">
    </label>

    <p class="acct-note">
        Đổi ngày xong lịch quay về Chờ xác nhận — cửa hàng sẽ gọi lại để thống nhất giờ cho ngày mới.
    </p>

    <div class="acct-pair">
        <a class="acct-btn" href="/tai-khoan?muc=lich-hen#lich-<?= e($appointment['code']) ?>">Để nguyên</a>
        <button type="submit" class="acct-btn acct-btn--solid acct-btn--save">Xác nhận ngày mới</button>
    </div>
</form>
