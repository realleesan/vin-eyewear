<?php

/**
 * auth/account/_dia-chi-form.php — form thêm/sửa một địa chỉ trong sổ.
 *
 * MỘT FILE CHO CẢ HAI VIỆC. Thêm và sửa hỏi đúng cùng bộ câu hỏi; khác nhau ở
 * đường gửi, chữ trên nút, và một ô ẩn `id`. Hai file thì sớm muộn có một bản
 * quên thêm ô mới — và ô quên ấy sẽ là ô người ta chỉ phát hiện khi hàng đi
 * nhầm chỗ.
 *
 * Nhận qua partial():
 *   $dc       mảng địa chỉ đang sửa, hoặc null khi đang thêm mới
 *   $nhanCua  bảng nhãn (AddressModel::NHAN)
 *   $action   đường POST
 *   $tieuDe   tiêu đề khối
 *   $nutLuu   chữ trên nút gửi
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HỢP ĐỒNG VỚI address-picker.js
 *
 * Nằm ở các thuộc tính data-vnaddr* dưới đây, KHÔNG ở tên ô — đọc khối chú
 * thích đầu assets/js/address-picker.js trước khi đổi bất cứ thứ gì trong khối
 * [data-vnaddr]. File ấy đã được nạp sẵn cho trang tài khoản (xem $pageScripts
 * trong _layout/master.php).
 *
 * ⚠ CẢ TRANG CHỈ ĐƯỢC CÓ MỘT KHỐI [data-vnaddr]: cụm kia tìm bằng
 * querySelector, không phải querySelectorAll. Vì thế trang hồ sơ chỉ mở MỘT
 * form địa chỉ mỗi lượt — AuthController::sectionData() lo phần đó (?sua=
 * thắng ?them=). Mở hai cái thì cái thứ hai còn trơ hai ô gõ tay.
 *
 * KHÔNG CÓ Ô QUẬN/HUYỆN, dù UC-USER-05 có liệt kê. Từ 01/07/2025 Việt Nam bỏ
 * cấp huyện, địa chỉ còn hai cấp tỉnh/thành -> phường/xã, và
 * provinces.open-api.vn v2 cũng chỉ trả hai cấp — không có nguồn nào đổ dữ
 * liệu cho một ô như thế.
 */

$laSua = $dc !== null;
$neo   = $laSua ? 'sua-dia-chi' : 'them-dia-chi';
?>

<?php /* MỘT thẻ mang cả ba lớp, không phải <section> bọc <form>: .acct-card cho
         nền và bo góc, .acct-form cho cột dọc + đệm 36/40, .acct-addr-form cho
         viền nhấn. Lồng hai lớp .acct-form vào nhau là đệm cộng đôi — form thụt
         vào 80px so với thẻ địa chỉ ngay trên nó. */ ?>
    <form class="acct-card acct-form acct-addr-form" id="<?= e($neo) ?>"
          method="post" action="<?= e($action) ?>">
        <h3 class="acct-form__title"><?= e($tieuDe) ?></h3>

        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <?php if ($laSua): ?>
            <input type="hidden" name="id" value="<?= e($dc['id']) ?>">
        <?php endif; ?>

        <div class="acct-form__row">
            <label class="acct-field">
                <span class="acct-field__label">Người nhận <span aria-hidden="true">*</span></span>
                <?php /* KHÔNG điền sẵn họ tên chủ tài khoản khi thêm mới: phần
                         lớn địa chỉ thứ hai trở đi là gửi cho người khác (nhà bố
                         mẹ, cơ quan), và một ô đã có sẵn tên đúng của mình là ô
                         người ta lướt qua không đọc. */ ?>
                <input class="acct-field__input" type="text" name="recipient_name" required
                       maxlength="120" autocomplete="name"
                       value="<?= e((string) ($dc['recipient_name'] ?? '')) ?>">
            </label>

            <label class="acct-field">
                <span class="acct-field__label">Số điện thoại <span aria-hidden="true">*</span></span>
                <input class="acct-field__input" type="tel" name="phone" required
                       autocomplete="tel"
                       value="<?= e((string) ($dc['phone'] ?? '')) ?>">
                <span class="acct-field__hint">Số người giao hàng sẽ gọi khi tới nơi.</span>
            </label>
        </div>

        <div class="acct-form__row" data-vnaddr>
            <label class="acct-field">
                <span class="acct-field__label">Tỉnh / Thành phố <span aria-hidden="true">*</span></span>
                <input class="acct-field__input" type="text" name="province_name" required
                       maxlength="120" autocomplete="address-level1"
                       placeholder="Thành phố Hà Nội"
                       data-vnaddr-field="province"
                       value="<?= e((string) ($dc['province_name'] ?? '')) ?>">
            </label>

            <label class="acct-field">
                <span class="acct-field__label">Phường / Xã <span aria-hidden="true">*</span></span>
                <input class="acct-field__input" type="text" name="ward_name" required
                       maxlength="120" autocomplete="address-level2"
                       placeholder="Phường Tây Hồ"
                       data-vnaddr-field="ward"
                       value="<?= e((string) ($dc['ward_name'] ?? '')) ?>">
            </label>

            <?php /* Mã chỉ để address-picker.js chọn lại đúng mục khi mở form.
                     Mang `name` nên chúng ĐƯỢC gửi lên và lưu — khác trang thanh
                     toán, nơi đơn hàng chỉ lưu chữ nên hai ô mã ở đó không có
                     `name`. AddressModel bỏ mã nào không phải chữ số. */ ?>
            <input type="hidden" name="province_code" data-vnaddr-code="province"
                   value="<?= e((string) ($dc['province_code'] ?? '')) ?>">
            <input type="hidden" name="ward_code" data-vnaddr-code="ward"
                   value="<?= e((string) ($dc['ward_code'] ?? '')) ?>">
        </div>

        <label class="acct-field">
            <span class="acct-field__label">Địa chỉ chi tiết <span aria-hidden="true">*</span></span>
            <?php /* CHỈ số nhà và tên đường. Phường và tỉnh đã có hai ô trên —
                     gõ lại vào đây thì phiếu gửi hàng in chúng hai lần. */ ?>
            <input class="acct-field__input" type="text" name="line1" required
                   maxlength="255" autocomplete="address-line1"
                   placeholder="Số 12, ngõ 5 Đội Cấn"
                   value="<?= e((string) ($dc['line1'] ?? '')) ?>">
        </label>

        <div class="acct-form__row">
            <div class="acct-field">
                <span class="acct-field__label" id="nhan-<?= e($neo) ?>">Loại địa chỉ</span>
                <?php /* Không bắt buộc, và có nút bỏ chọn — khác cụm giới tính ở
                         form hồ sơ. Nhãn ở đây là thứ chỉ có nghĩa khi sổ có
                         nhiều địa chỉ; ép chọn một trong hai là bịa ra dữ liệu
                         cho người chỉ có đúng một nơi nhận hàng. */ ?>
                <div class="acct-choice" role="radiogroup" aria-labelledby="nhan-<?= e($neo) ?>">
                    <label class="acct-choice__opt">
                        <input type="radio" name="nhan" value=""
                               <?= empty($dc['nhan']) ? 'checked' : '' ?>>
                        <span>Không đặt</span>
                    </label>
                    <?php foreach ($nhanCua as $ma => $nhan): ?>
                        <label class="acct-choice__opt">
                            <input type="radio" name="nhan" value="<?= e($ma) ?>"
                                   <?= ($dc['nhan'] ?? null) === $ma ? 'checked' : '' ?>>
                            <span><?= e($nhan) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="acct-field">
                <span class="acct-field__label">Ghi chú cho người giao</span>
                <?php /* Q75.1 — "Gọi trước 15 phút", "cổng sau", "bảo vệ nhận
                         giúp". Không có ô này thì khách nhét chúng vào địa chỉ
                         chi tiết, làm hỏng đúng cái dòng được in lên phiếu gửi
                         hàng. */ ?>
                <input class="acct-field__input" type="text" name="ghi_chu" maxlength="255"
                       placeholder="Gọi trước khi giao"
                       value="<?= e((string) ($dc['ghi_chu'] ?? '')) ?>">
            </label>
        </div>

        <?php if (!$laSua): ?>
            <label class="acct-check">
                <input type="checkbox" name="is_default" value="1">
                <span>Đặt làm địa chỉ mặc định</span>
            </label>
            <?php /* Ô tick này KHÔNG có ở form sửa: đặt mặc định là thao tác
                     riêng có nút riêng trên từng thẻ (UC-USER-05, Khu vực 2).
                     Gộp vào form sửa thì mỗi lần sửa số nhà cũng là một lần âm
                     thầm đổi nơi nhận hàng mặc định — xem AddressModel::sua(). */ ?>
        <?php endif; ?>

        <div class="acct-form__actions">
            <button type="submit" class="acct-btn acct-btn--primary"><?= e($nutLuu) ?></button>
            <a class="acct-btn acct-btn--quiet acct-btn--sm"
               href="/tai-khoan?muc=ho-so#so-dia-chi">Huỷ</a>
        </div>
    </form>
