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
 * Mục này nay có BA khối, theo thứ tự: form hồ sơ · sổ địa chỉ · xoá tài
 * khoản. Sổ địa chỉ trước đây là mục riêng ?muc=dia-chi — xem ghi chú ở
 * AuthController::SECTIONS.
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

    <button type="submit" class="acct-btn acct-btn--primary acct-btn--start">Lưu thay đổi</button>
</form>

<?php
/*
 * ─────────────────────────────────────────────────────────────────────────────
 * SỔ ĐỊA CHỈ — MỘT KHỐI CỦA MỤC NÀY, KHÔNG CÒN LÀ MỤC RIÊNG
 *
 * Địa chỉ nhận hàng cũng là "thông tin của tôi" y như họ tên và ngày sinh.
 * Tách thành mục thứ hai thì khách phải nhớ nó nằm ngoài hồ sơ, và người vừa
 * điền xong hồ sơ phải bấm thêm một lần nữa mới tới chỗ điền địa chỉ.
 *
 * ĐẶT GIỮA form hồ sơ và khối "Xoá tài khoản", không phải sau cùng: khối xoá
 * phải là thứ cuối trang (xem lý do ngay dưới), nên mọi khối thêm vào sau này
 * đều chèn vào đây.
 *
 * Truyền biến TƯỜNG MINH: partial() chạy trong phạm vi riêng nên nó không tự
 * thấy $addresses/$editing/$adding/$old của file này. Bốn biến ấy do
 * AuthController::sectionData() nhánh 'ho-so' dựng ra.
 * ─────────────────────────────────────────────────────────────────────────────
 */
partial('auth/account/dia-chi', [
    'addresses' => $addresses,
    'editing'   => $editing,
    'adding'    => $adding,
    'old'       => $old,
]);
?>

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
