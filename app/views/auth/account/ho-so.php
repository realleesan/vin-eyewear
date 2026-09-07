<?php

/**
 * auth/account/ho-so.php — mục "Hồ sơ của tôi" (/tai-khoan?muc=ho-so).
 *
 * Bản thiết kế vẽ MỘT thẻ form ở đây và một thẻ ảnh đại diện 280px bên phải.
 * Thẻ bên phải đã BỎ: việc đổi ảnh chuyển thẳng vào hình tròn ở cột trái
 * (app/views/auth/profile.php) — ảnh nằm đúng chỗ nó hiện ra, và đổi được từ
 * bất kỳ mục nào chứ không phải quay về mục này trước. Nhờ vậy thẻ form ở đây
 * chiếm trọn bề ngang.
 *
 * Mục này có HAI khối: form hồ sơ và khối "Xoá tài khoản".
 *
 * ĐỊA CHỈ NẰM TRONG CHÍNH FORM HỒ SƠ, ba ô ở cuối. Nó từng là một SỔ nhiều
 * địa chỉ ở mục riêng ?muc=dia-chi (bảng `addresses`); từ 2026-09-12 mỗi khách
 * có đúng một địa chỉ và nó là bốn cột của `profiles` — xem migration
 * 2026-09-12-dia-chi-vao-ho-so.sql.
 */

$gender = $profile['gender'] ?? null;
?>

<div class="acct-head">
    <h1 class="acct-head__title">Hồ sơ của tôi</h1>
    <p class="acct-head__lead">Quản lý thông tin để bảo mật tài khoản.</p>
</div>

<form class="acct-card acct-form" method="post" action="/tai-khoan/ho-so">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

    <div class="acct-form__row">
        <label class="acct-field">
            <span class="acct-field__label">Họ và tên</span>
            <input class="acct-field__input" type="text" name="full_name" required
                   maxlength="120" autocomplete="name"
                   value="<?= e($profile['full_name'] ?? '') ?>">
        </label>

        <label class="acct-field">
            <span class="acct-field__label">Số điện thoại</span>
            <input class="acct-field__input" type="tel" name="phone" autocomplete="tel"
                   value="<?= e($profile['phone'] ?? '') ?>">
        </label>
    </div>

    <label class="acct-field">
        <span class="acct-field__label">Email</span>
        <!-- SỬA ĐƯỢC, và không bắt buộc. Tài khoản đăng ký bằng số điện thoại
             ra đời không có email; để ô này khoá thì họ không bao giờ thêm
             được, tức mất luôn lối đăng nhập bằng email và lối nhận liên kết
             đặt lại mật khẩu khi đã đổi số.

             Địa chỉ gõ ở đây CHƯA XÁC MINH — UserModel::updateEmail() đặt
             email_verified về 0, nên nó không dùng để nối tài khoản Google. -->
        <input class="acct-field__input" type="email" name="email" maxlength="255"
               autocomplete="email" placeholder="ban@vidu.com"
               value="<?= e($profile['email'] ?? '') ?>">
        <span class="acct-field__hint">
            Dùng để đăng nhập và lấy lại mật khẩu. Bỏ trống nếu bạn chỉ muốn
            dùng số điện thoại.
        </span>
    </label>

    <div class="acct-form__row">
        <div class="acct-field">
            <span class="acct-field__label" id="nhan-gioi-tinh">Giới tính</span>
            <!-- Ba nút của bản thiết kế là ba ô radio thật, nhãn phủ lên
                 trên. Nút <button> như bản thiết kế thì bàn phím và trình
                 đọc màn hình không biết cái nào đang được chọn. -->
            <div class="acct-choice" role="radiogroup" aria-labelledby="nhan-gioi-tinh">
                <?php foreach ($genders as $key => $label): ?>
                    <label class="acct-choice__opt">
                        <input type="radio" name="gender" value="<?= e($key) ?>"
                               <?= $gender === $key ? 'checked' : '' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <label class="acct-field">
            <span class="acct-field__label">Ngày sinh</span>
            <!-- type=date chứ không phải ô chữ "09/06/1996" như bản thiết
                 kế: cột date_of_birth là kiểu DATE, và ô chọn ngày của
                 trình duyệt tự hiện đúng định dạng dd/mm/yyyy cho máy
                 đang đặt tiếng Việt. -->
            <input class="acct-field__input" type="date" name="date_of_birth"
                   max="<?= e(date('Y-m-d')) ?>"
                   value="<?= e($profile['date_of_birth'] ?? '') ?>">
        </label>
    </div>

    <?php
    /* ─────────────────────────────────────────────────────────────────────────
       ĐỊA CHỈ — MỘT ĐỊA CHỈ, NẰM TRONG CHÍNH FORM NÀY

       Trước 2026-09-12 đây là một SỔ nhiều địa chỉ ở mục riêng ?muc=dia-chi,
       lưu ở bảng `addresses`. Nay mỗi khách có đúng một địa chỉ và nó là bốn
       cột của `profiles` — xem migration 2026-09-12-dia-chi-vao-ho-so.sql.

       KHÔNG CÓ Ô QUẬN/HUYỆN. Từ 01/07/2025 Việt Nam bỏ cấp huyện, địa chỉ còn
       hai cấp tỉnh/thành -> phường/xã, và provinces.open-api.vn v2 cũng chỉ
       trả hai cấp. Thêm một ô "Quận/Huyện" ở đây thì không có nguồn nào đổ dữ
       liệu vào nó.

       HỢP ĐỒNG VỚI address-picker.js nằm ở các thuộc tính data-vnaddr* dưới
       đây, không ở tên ô — đọc khối chú thích đầu assets/js/address-picker.js
       trước khi đổi bất cứ thứ gì trong khối này. File ấy đã được nạp sẵn cho
       trang tài khoản (xem $pageScripts trong _layout/master.php).

       KHÔNG `required`: hồ sơ phải lưu được khi khách chưa muốn khai địa chỉ.
       Trang thanh toán mới là nơi đòi đủ — ở đó không có địa chỉ thì không
       giao hàng đi đâu được, và ô ở đó vẫn `required` như cũ.
       ───────────────────────────────────────────────────────────────────────── */
    ?>
    <div class="acct-form__row" data-vnaddr>
        <label class="acct-field">
            <span class="acct-field__label">Tỉnh / Thành phố</span>
            <input class="acct-field__input" type="text" name="province_name"
                   maxlength="120" autocomplete="address-level1"
                   placeholder="Thành phố Hà Nội"
                   data-vnaddr-field="province"
                   value="<?= e((string) ($profile['province_name'] ?? '')) ?>">
        </label>

        <label class="acct-field">
            <span class="acct-field__label">Phường / Xã</span>
            <input class="acct-field__input" type="text" name="ward_name"
                   maxlength="120" autocomplete="address-level2"
                   placeholder="Phường Tây Hồ"
                   data-vnaddr-field="ward"
                   value="<?= e((string) ($profile['ward_name'] ?? '')) ?>">
        </label>

        <?php /* Mã chỉ để address-picker.js chọn lại đúng mục khi mở form.
                 Mang `name` nên chúng ĐƯỢC gửi lên và lưu — khác trang thanh
                 toán, nơi đơn hàng chỉ lưu chữ nên hai ô mã ở đó không có
                 `name`. UserModel::updateProfile() bỏ mã nào không đi kèm
                 tên. */ ?>
        <input type="hidden" name="province_code" data-vnaddr-code="province"
               value="<?= e((string) ($profile['province_code'] ?? '')) ?>">
        <input type="hidden" name="ward_code" data-vnaddr-code="ward"
               value="<?= e((string) ($profile['ward_code'] ?? '')) ?>">
    </div>

    <label class="acct-field">
        <span class="acct-field__label">Địa chỉ chi tiết</span>
        <?php /* CHỈ số nhà và tên đường. Phường và tỉnh đã có hai ô trên —
                 gõ lại vào đây thì phiếu gửi hàng in chúng hai lần. */ ?>
        <input class="acct-field__input" type="text" name="address"
               maxlength="255" autocomplete="address-line1"
               placeholder="Số 12, ngõ 5 Đội Cấn"
               value="<?= e((string) ($profile['address'] ?? '')) ?>">
        <span class="acct-field__hint">
            Số nhà và tên đường. Dùng để điền sẵn khi bạn đặt hàng.
        </span>
    </label>

    <button type="submit" class="acct-btn acct-btn--primary acct-btn--start">Lưu thay đổi</button>
</form>

<?php
/*
 * ─────────────────────────────────────────────────────────────────────────────
 * XOÁ TÀI KHOẢN — SRS v2.1.0, UC-01 · FR-TK-17
 *
 * ĐẶT CUỐI TRANG, TÁCH KHỎI FORM HỒ SƠ, VÀ ĐÓNG SẴN.
 *
 * Ba quyết định trình bày, đều có lý do:
 *
 *   cuối trang    đây là việc làm một lần trong đời tài khoản, không phải một
 *                 ô người ta sửa hằng tháng. Đặt nó cạnh ô "Ngày sinh" là mời
 *                 một cú bấm nhầm.
 *   form riêng    gộp vào form hồ sơ thì mỗi lần lưu ngày sinh cũng gửi kèm
 *                 trường xoá, và chỉ cần một lỗi ở tầng đọc là mất tài khoản.
 *   đóng sẵn      mở bằng ?xoa=1 trên địa chỉ, cùng lối với ?sua= của sổ địa
 *                 chỉ. Không có JS thì vẫn mở được.
 * ─────────────────────────────────────────────────────────────────────────────
 */
$moXoa   = isset($_GET['xoa']);
$canhBao = isset($_GET['canh-bao']);
?>

<section class="acct-card acct-danger">
    <h2 class="acct-form__title">Xoá tài khoản</h2>

    <?php if (!$moXoa): ?>
        <p class="acct-danger__lead">
            Bạn có thể yêu cầu xoá tài khoản Vin Eyewear của mình. Đơn hàng đã đặt
            vẫn được cửa hàng xử lý bình thường.
        </p>
        <a class="acct-btn acct-btn--outline acct-btn--sm"
           href="/tai-khoan?muc=ho-so&amp;xoa=1">Tôi muốn xoá tài khoản</a>
    <?php else: ?>
        <?php
        /* NÊU RÕ HẬU QUẢ TRƯỚC KHI HỎI MẬT KHẨU — bước 2 của UC-01.
 
           Cả ba dòng đều là điều khách sẽ chỉ phát hiện SAU khi xoá nếu không
           nói trước, và dòng thứ ba là dòng quan trọng nhất: nó trả lời câu
           "đơn tôi đang đặt thì sao", tức nỗi lo thật sự của người sắp bấm. */
        ?>
        <p class="acct-danger__lead">Sau khi xoá, bạn sẽ:</p>
        <ul class="acct-danger__list">
            <li>Không đăng nhập được nữa, kể cả bằng Google.</li>
            <li>Mất quyền tra cứu đơn hàng và hồ sơ đo mắt trên website.</li>
            <li>
                <strong>Đơn hàng đã đặt vẫn được cửa hàng xử lý bình thường</strong> —
                cửa hàng liên hệ với bạn qua số điện thoại đã đăng ký.
            </li>
        </ul>

        <form class="acct-form" method="post" action="/tai-khoan/xoa">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

            <?php if ($canhBao): ?>
                <?php /* E4 — khách đã đọc cảnh báo về lịch hẹn ở dòng flash phía
                         trên; ô ẩn này là "đã xác nhận lần hai". Chỉ xuất hiện
                         sau lần gửi bị chặn, nên không có đường nào tích sẵn nó
                         từ lần bấm đầu. */ ?>
                <input type="hidden" name="xac_nhan_lich" value="1">
            <?php endif; ?>

            <label class="acct-field">
                <span class="acct-field__label">Mật khẩu hiện tại <span aria-hidden="true">*</span></span>
                <input class="acct-field__input" type="password" name="mat_khau"
                       required autocomplete="current-password">
            </label>

            <label class="acct-check">
                <input type="checkbox" name="dong_y" value="1" required>
                <span>Tôi hiểu và đồng ý xoá tài khoản của mình.</span>
            </label>

            <div class="acct-danger__acts">
                <button type="submit" class="acct-btn acct-btn--danger acct-btn--sm">
                    <?= $canhBao ? 'Vẫn xoá tài khoản' : 'Xoá tài khoản' ?>
                </button>
                <a class="acct-btn acct-btn--quiet acct-btn--sm"
                   href="/tai-khoan?muc=ho-so">Không xoá nữa</a>
            </div>
        </form>
    <?php endif; ?>
</section>
