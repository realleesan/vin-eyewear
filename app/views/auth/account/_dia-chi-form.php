<?php

/**
 * auth/account/_dia-chi-form.php — form thêm/sửa một địa chỉ trong sổ.
 *
 * Dáng "NEW ADDRESS" của "Ho So Nguoi Dung.dc.html": ô nhập cao 48px, viền
 * #d9d9d9, chữ gợi ý nằm trong ô; hai nút HUỶ · LƯU chia đôi ở cuối.
 *
 * MỘT FILE CHO CẢ HAI VIỆC. Thêm và sửa hỏi đúng cùng bộ câu hỏi; khác nhau ở
 * đường gửi, chữ trên nút, và một ô ẩn `id`. Hai file thì sớm muộn có một bản
 * quên thêm ô mới.
 *
 * Nhận qua partial():
 *   $dc       mảng địa chỉ đang sửa, hoặc null khi đang thêm mới
 *   $nhanCua  bảng nhãn (AddressModel::NHAN)
 *   $action   đường POST
 *   $tieuDe   tiêu đề khối
 *   $nutLuu   chữ trên nút gửi
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NHÃN ẨN, KHÔNG BỎ. Bản thiết kế chỉ có chữ gợi ý trong ô; chữ gợi ý biến mất
 * khi gõ và trình đọc màn hình không coi nó là nhãn. Mỗi ô vẫn có <label> thật,
 * chỉ ẩn khỏi mắt (.sr-only).
 *
 * HỢP ĐỒNG VỚI address-picker.js nằm ở các thuộc tính data-vnaddr* — đọc khối
 * chú thích đầu file JS ấy trước khi đổi gì trong khối [data-vnaddr]. CẢ TRANG
 * CHỈ ĐƯỢC CÓ MỘT KHỐI như thế.
 *
 * KHÔNG CÓ Ô QUẬN/HUYỆN như bản thiết kế ("District, City"): từ 01/07/2025 Việt
 * Nam bỏ cấp huyện, địa chỉ còn tỉnh/thành -> phường/xã.
 */

$laSua = $dc !== null;
$neo   = $laSua ? 'sua-dia-chi' : 'them-dia-chi';
?>

<form class="acct-addr-form" id="<?= e($neo) ?>" method="post" action="<?= e($action) ?>">
    <h3 class="acct-label"><?= e($tieuDe) ?></h3>

    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

    <?php if ($laSua): ?>
        <input type="hidden" name="id" value="<?= e($dc['id']) ?>">
    <?php endif; ?>

    <div class="acct-grid2">
        <label class="sr-only" for="<?= e($neo) ?>-ten">Người nhận</label>
        <?php /* KHÔNG điền sẵn họ tên chủ tài khoản khi thêm mới: phần lớn địa chỉ
                 thứ hai trở đi là gửi cho người khác, và một ô đã có sẵn tên
                 đúng của mình là ô người ta lướt qua không đọc. */ ?>
        <input class="acct-input acct-grid2__full" type="text" id="<?= e($neo) ?>-ten"
               name="recipient_name" required maxlength="120" autocomplete="name"
               placeholder="Họ tên người nhận"
               value="<?= e((string) ($dc['recipient_name'] ?? '')) ?>">

        <label class="sr-only" for="<?= e($neo) ?>-dc">Địa chỉ chi tiết</label>
        <?php /* CHỈ số nhà và tên đường — phường và tỉnh có ô riêng ngay dưới,
                 gõ lại vào đây thì phiếu gửi hàng in chúng hai lần. */ ?>
        <input class="acct-input acct-grid2__full" type="text" id="<?= e($neo) ?>-dc"
               name="line1" required maxlength="255" autocomplete="address-line1"
               placeholder="Số nhà, tên đường"
               value="<?= e((string) ($dc['line1'] ?? '')) ?>">

        <div class="acct-grid2 acct-grid2__full" data-vnaddr>
            <label class="sr-only" for="<?= e($neo) ?>-tinh">Tỉnh / Thành phố</label>
            <input class="acct-input" type="text" id="<?= e($neo) ?>-tinh" name="province_name"
                   required maxlength="120" autocomplete="address-level1"
                   placeholder="Tỉnh / Thành phố" data-vnaddr-field="province"
                   value="<?= e((string) ($dc['province_name'] ?? '')) ?>">

            <label class="sr-only" for="<?= e($neo) ?>-phuong">Phường / Xã</label>
            <input class="acct-input" type="text" id="<?= e($neo) ?>-phuong" name="ward_name"
                   required maxlength="120" autocomplete="address-level2"
                   placeholder="Phường / Xã" data-vnaddr-field="ward"
                   value="<?= e((string) ($dc['ward_name'] ?? '')) ?>">

            <?php /* Mã chỉ để address-picker.js chọn lại đúng mục khi mở form.
                     AddressModel bỏ mã nào không phải chữ số. */ ?>
            <input type="hidden" name="province_code" data-vnaddr-code="province"
                   value="<?= e((string) ($dc['province_code'] ?? '')) ?>">
            <input type="hidden" name="ward_code" data-vnaddr-code="ward"
                   value="<?= e((string) ($dc['ward_code'] ?? '')) ?>">
        </div>

        <label class="sr-only" for="<?= e($neo) ?>-sdt">Số điện thoại</label>
        <input class="acct-input" type="tel" id="<?= e($neo) ?>-sdt" name="phone" required
               autocomplete="tel" placeholder="Số điện thoại"
               value="<?= e((string) ($dc['phone'] ?? '')) ?>">

        <label class="sr-only" for="<?= e($neo) ?>-ghi-chu">Ghi chú cho người giao</label>
        <?php /* Q75.1 — "Gọi trước 15 phút", "cổng sau". Không có ô này thì khách
                 nhét chúng vào địa chỉ chi tiết, hỏng dòng in lên phiếu gửi. */ ?>
        <input class="acct-input" type="text" id="<?= e($neo) ?>-ghi-chu" name="ghi_chu"
               maxlength="255" placeholder="Ghi chú cho người giao (không bắt buộc)"
               value="<?= e((string) ($dc['ghi_chu'] ?? '')) ?>">
    </div>

    <?php /* Loại địa chỉ: không bắt buộc, có "Không đặt" — ép chọn là bịa dữ liệu
             cho người chỉ có đúng một nơi nhận hàng. */ ?>
    <div class="acct-choice" role="radiogroup" aria-label="Loại địa chỉ">
        <label class="acct-choice__opt">
            <input type="radio" name="nhan" value="" <?= empty($dc['nhan']) ? 'checked' : '' ?>>
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

    <?php if (!$laSua): ?>
        <?php /* Ô tick này KHÔNG có ở form sửa: đặt mặc định là thao tác riêng có
                 nút riêng trên từng thẻ. Gộp vào form sửa thì mỗi lần sửa số nhà
                 là một lần âm thầm đổi nơi nhận hàng mặc định. */ ?>
        <label class="acct-check">
            <input type="checkbox" name="is_default" value="1">
            <span>Đặt làm địa chỉ mặc định</span>
        </label>
    <?php endif; ?>

    <div class="acct-pair acct-pair--gap">
        <a class="acct-btn" href="/tai-khoan?muc=dia-chi#so-dia-chi">Huỷ</a>
        <button type="submit" class="acct-btn acct-btn--solid"><?= e($nutLuu) ?></button>
    </div>
</form>
