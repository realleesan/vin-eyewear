<?php

/**
 * auth/account/lich-hen.php — tab "Lịch hẹn" (/tai-khoan?muc=lich-hen).
 *
 * Màn "Appointments" của "Ho So Nguoi Dung.dc.html":
 *
 *                     LỊCH HẸN CỦA TÔI
 *   SẮP TỚI                                                 1
 *   [ thẻ lịch hẹn ]                               xem _the-lich.php
 *   [ ĐẶT LỊCH HẸN ]  -> ?dat=1 mở form "Lịch hẹn mới" ngay tại chỗ
 *
 * HAI CHỖ KHÁC BẢN VẼ, đều do chủ dự án chốt:
 *
 *   - form đặt lịch KHÔNG có ô Giờ — giả định A5 (xem BookingModel): khách
 *     chỉ chọn ngày, cửa hàng gọi điện chốt giờ. Ô Ngày chiếm cả hàng.
 *   - thêm khối "ĐÃ QUA" dưới cùng cho lịch đã đo, đã huỷ, quá ngày — bản vẽ
 *     chỉ có lịch sắp tới, nhưng đây là chỗ duy nhất khách xem lại lịch cũ.
 *
 * Form gửi POST /tai-khoan/lich-hen/dat — AuthController::bookAppointment() lấy
 * họ tên và số điện thoại từ hồ sơ rồi đi qua BookingModel::create(), cùng cửa
 * với form /dat-lich.
 *
 * Nhận qua sectionData(): $sapToi · $daQua · $blockers · $editing · $datLich ·
 * $stores · $services · $bookingStatuses
 */

$minNgay = date('Y-m-d');
$maxNgay = date('Y-m-d', strtotime('+' . BookingModel::DAT_TRUOC_TOI_DA . ' days'));
$sdt     = trim((string) ($profile['phone'] ?? ''));

$theLich = static function (array $a) use ($blockers, $editing, $bookingStatuses): void {
    partial('auth/account/_the-lich', [
        'a'             => $a,
        'blocker'       => $blockers[$a['code']] ?? null,
        'dangDoi'       => $editing !== null && $editing['code'] === $a['code'],
        'nhanTrangThai' => $bookingStatuses,
    ]);
};

$chevron = '<svg class="acct-field__chev" width="16" height="16" viewBox="0 0 16 16" fill="none"'
         . ' stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">'
         . '<path d="M3 6l5 5 5-5"></path></svg>';
?>

<h1 class="acct-title">Lịch hẹn của tôi</h1>

<section class="acct-sec acct-sec--first" id="sap-toi" aria-labelledby="lh-sap-toi">
    <div class="acct-head">
        <h2 class="acct-head__label" id="lh-sap-toi">Sắp tới</h2>
        <span class="acct-mute"><?= count($sapToi) ?></span>
    </div>

    <?php if ($sapToi === []): ?>
        <p class="acct-text">Bạn chưa có lịch hẹn sắp tới.</p>
    <?php else: ?>
        <div class="acct-cards">
            <?php foreach ($sapToi as $a) { $theLich($a); } ?>
        </div>
    <?php endif; ?>

    <?php if (!$datLich): ?>
        <a class="acct-btn acct-btn--gap" href="/tai-khoan?muc=lich-hen&amp;dat=1#dat-lich">Đặt lịch hẹn</a>
    <?php elseif ($stores === []): ?>
        <p class="acct-text acct-mute" id="dat-lich">Hiện chưa có cơ sở nào nhận lịch hẹn. Vui lòng liên hệ cửa hàng.</p>
    <?php else: ?>
        <form class="acct-form" id="dat-lich" method="post" action="/tai-khoan/lich-hen/dat">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

            <h3 class="acct-label">Lịch hẹn mới</h3>

            <div class="acct-grid2">
                <label class="acct-field acct-full">
                    <span class="acct-field__cap">Dịch vụ</span>
                    <select class="acct-field__ctl" name="service_type">
                        <?php foreach ($services as $dv): ?>
                            <option value="<?= e($dv) ?>"><?= e($dv) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $chevron ?>
                </label>

                <label class="acct-field acct-full">
                    <span class="acct-field__cap">Cơ sở</span>
                    <select class="acct-field__ctl" name="store_id">
                        <?php foreach ($stores as $cs): ?>
                            <option value="<?= e($cs['id']) ?>"><?= e($cs['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $chevron ?>
                </label>

                <?php /* Cận dưới HÔM NAY, cận trên DAT_TRUOC_TOI_DA — đúng hai luật
                         BookingModel::create() kiểm lại ở máy chủ. `required` để nút
                         xác nhận xám cho tới khi chọn ngày, như bản vẽ. */ ?>
                <label class="acct-field acct-full">
                    <span class="acct-field__cap">Ngày</span>
                    <input class="acct-field__ctl" type="date" name="date" required
                           min="<?= e($minNgay) ?>" max="<?= e($maxNgay) ?>">
                </label>
            </div>

            <p class="acct-note">
                <?php if ($sdt !== ''): ?>
                    Cửa hàng sẽ gọi số <?= e(groupPhone($sdt)) ?> để chốt giờ hẹn.
                <?php else: ?>
                    Hồ sơ của bạn chưa có số điện thoại — hãy thêm ở tab Hồ sơ để cửa hàng gọi chốt giờ hẹn.
                <?php endif; ?>
            </p>

            <div class="acct-pair">
                <a class="acct-btn" href="/tai-khoan?muc=lich-hen#sap-toi">Huỷ</a>
                <button type="submit" class="acct-btn acct-btn--solid acct-btn--save">Xác nhận đặt lịch</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php if ($daQua !== []): ?>
    <section class="acct-sec" id="da-qua" aria-labelledby="lh-da-qua">
        <div class="acct-head">
            <h2 class="acct-head__label" id="lh-da-qua">Đã qua</h2>
            <span class="acct-mute"><?= count($daQua) ?></span>
        </div>

        <div class="acct-cards">
            <?php foreach ($daQua as $a) { $theLich($a); } ?>
        </div>
    </section>
<?php endif; ?>
