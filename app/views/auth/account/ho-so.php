<?php

/**
 * auth/account/ho-so.php — TRANG "HỒ SƠ CÁ NHÂN" (/tai-khoan?muc=ho-so)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA KHU VỰC, MỘT TRANG, KHÔNG TAB — BR-UC.USER.05-03
 *
 * Trước 2026-09-10 mỗi khu vực là một mục riêng chọn bằng ?muc=, tức mỗi lần
 * đổi khu vực là một lần tải trang khác. UC-USER-05 cấm đúng điều đó: *"Không
 * sử dụng tab hoặc chuyển trang giữa các khu vực. Khách hàng có thể scroll để
 * xem đầy đủ."*
 *
 * Thứ tự trên trang:
 *
 *     Khu vực 1  Thông tin cá nhân      #thong-tin
 *     ├─ Tài khoản Google               (ngoài UC — xem ghi chú bên dưới)
 *     └─ Xoá tài khoản                  (ngoài UC)
 *     Khu vực 2  Sổ địa chỉ             #so-dia-chi
 *     Khu vực 3  Đổi mật khẩu           #doi-mat-khau
 *
 * HAI KHỐI NGOÀI UC ĐỨNG GIỮA, KHÔNG PHẢI CUỐI TRANG. Cả hai là thiết lập của
 * chính TÀI KHOẢN (một lối đăng nhập, và việc đóng tài khoản), nên chúng thuộc
 * về khu vực 1 chứ không phải một phần thứ tư. Đẩy xuống dưới cùng thì "Xoá
 * tài khoản" nằm ngay sau form đổi mật khẩu — hai khối cùng hỏi mật khẩu hiện
 * tại, đặt cạnh nhau là mời một cú bấm nhầm.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA TRẠNG THÁI MỞ BẰNG URL, KHÔNG BẰNG JAVASCRIPT
 *
 *     ?sua-ho-so=1        mở form sửa Thông tin cá nhân (mặc định là chế độ xem)
 *     ?them=1             mở form thêm địa chỉ ở cuối Sổ địa chỉ
 *     ?sua=<mã địa chỉ>   mở form sửa NGAY TẠI CHỖ thẻ địa chỉ đó
 *
 * Cùng lối với ?xoa=1 của khối Xoá tài khoản và ?don= của mục Đơn hàng: F5
 * không mất chỗ, gửi link cho nhân viên hỗ trợ được, và trang chạy cả khi tắt
 * JavaScript. Mỗi liên kết đều mang theo #neo để trình duyệt nhảy thẳng tới
 * khu vực vừa mở — trang này dài, mở một form ở giữa mà màn hình vẫn ở đầu
 * trang thì không ai biết vừa có gì xảy ra.
 *
 * MỖI LƯỢT CHỈ MỘT FORM ĐỊA CHỈ ĐƯỢC MỞ, và đó không phải chuyện thẩm mỹ:
 * address-picker.js tìm khối chọn tỉnh/phường bằng querySelector('[data-vnaddr]')
 * — MỘT khối cho cả trang. Hai form cùng mở thì form thứ hai còn trơ hai ô gõ
 * tay. Controller đã lo phần này (?sua= thắng ?them=); đừng bỏ vế `!$editing`
 * ở cuối file.
 */

$gender = $profile['gender'] ?? null;

/* Chữ hiện ở chế độ XEM khi một ô còn trống — UC-USER-05, Giao diện Khu vực 1.
   Một hàm chứ không phải `?:` rải khắp nơi: "0" là một giá trị thật (không ai
   có tên như thế, nhưng có thể có địa chỉ chi tiết là "0"), mà `?:` coi nó là
   rỗng. */
$hienHoac = static function (mixed $v): array {
    $s = trim((string) ($v ?? ''));

    return $s !== ''
        ? ['text' => $s,             'empty' => false]
        : ['text' => 'Chưa cập nhật', 'empty' => true];
};
?>

<div class="acct-head">
    <h1 class="acct-head__title">Hồ sơ cá nhân</h1>
    <p class="acct-head__lead">
        Thông tin cá nhân, sổ địa chỉ và mật khẩu của bạn — tất cả trên trang này.
    </p>
</div>

<?php
/*
 * ═════════════════════════════════════════════════════════════════════════════
 * KHU VỰC 1 · THÔNG TIN CÁ NHÂN
 *
 * XEM TRƯỚC, BẤM MỚI SỬA. Đặc tả mô tả các trường "ở chế độ xem (read-only),
 * không thể chỉnh sửa trực tiếp" nhưng cũng đòi một nút "Lưu thay đổi" và cho
 * phép "Chỉnh sửa thông tin cá nhân" ở Main Flow — hai vế chỉ đứng chung được
 * khi có một bước bấm ở giữa. Nút "Chỉnh sửa" là bước đó.
 *
 * Ở chế độ xem KHÔNG in <input disabled>: một ô nhập xám vẫn là một ô nhập,
 * người dùng bấm vào rồi ngồi đợi con trỏ. Chế độ xem là <dl> chữ thường.
 * ═════════════════════════════════════════════════════════════════════════════
 */
?>
<section id="thong-tin">
    <div class="acct-head acct-head--sub acct-head--row">
        <div>
            <h2 class="acct-head__title">Thông tin cá nhân</h2>
            <p class="acct-head__lead">Quản lý thông tin để bảo mật tài khoản.</p>
        </div>

        <?php if (!$suaHoSo): ?>
            <a class="acct-btn acct-btn--outline acct-btn--sm"
               href="/tai-khoan?muc=ho-so&amp;sua-ho-so=1#thong-tin">Chỉnh sửa</a>
        <?php endif; ?>
    </div>

    <?php if (!$suaHoSo): ?>
        <?php
        /* Năm dòng, đúng thứ tự mục Giao diện liệt kê. Email đứng thứ ba dù nó
           nằm ở bảng `users` chứ không phải `profiles` — thứ tự này là thứ tự
           người đọc, không phải thứ tự lược đồ. */
        $dong = [
            'Họ và tên'      => $profile['full_name']     ?? '',
            'Số điện thoại'  => $profile['phone']         ?? '',
            'Email'          => $profile['email']         ?? '',
            'Ngày sinh'      => !empty($profile['date_of_birth'])
                                ? formatDate($profile['date_of_birth']) : '',
            'Giới tính'      => $genders[$gender] ?? '',
        ];
        ?>
        <dl class="acct-card acct-info">
            <?php foreach ($dong as $nhan => $giaTri): ?>
                <?php $o = $hienHoac($giaTri); ?>
                <div class="acct-info__row">
                    <dt class="acct-info__label"><?= e($nhan) ?></dt>
                    <dd class="acct-info__value<?= $o['empty'] ? ' acct-info__value--empty' : '' ?>">
                        <?= e($o['text']) ?>
                    </dd>
                </div>
            <?php endforeach; ?>
        </dl>

    <?php else: ?>
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
                <!-- SỬA ĐƯỢC, và không bắt buộc. Tài khoản đăng ký bằng số điện
                     thoại ra đời không có email; để ô này khoá thì họ không bao
                     giờ thêm được, tức mất luôn lối đăng nhập bằng email và lối
                     nhận liên kết đặt lại mật khẩu khi đã đổi số.

                     Địa chỉ gõ ở đây CHƯA XÁC MINH — UserModel::updateEmail()
                     đặt email_verified về 0, nên nó không dùng để nối tài khoản
                     Google. -->
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
            /* KHÔNG CÒN Ô ĐỊA CHỈ Ở ĐÂY. Từ 2026-09-10 địa chỉ trở lại thành một
               SỔ riêng ngay dưới khu vực này (UC-USER-05, Khu vực 2) — mỗi địa
               chỉ có người nhận và số điện thoại của riêng nó, thứ mà một form
               hồ sơ không chỗ nào đặt được. UserModel::updateProfile() vẫn nhận
               năm cột địa chỉ, nhưng nay chỉ AddressModel::dongBoHoSo() gọi tới
               chúng. */
            ?>
            <div class="acct-form__actions">
                <button type="submit" class="acct-btn acct-btn--primary">Lưu thay đổi</button>
                <a class="acct-btn acct-btn--quiet acct-btn--sm"
                   href="/tai-khoan?muc=ho-so#thong-tin">Huỷ</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php
/*
 * ─────────────────────────────────────────────────────────────────────────────
 * TÀI KHOẢN GOOGLE — CHỖ NỐI MÀ AF-03 HỨA HẸN
 *
 * UserModel::findOrCreateGoogle() không tự nối Google vào tài khoản trùng
 * email nữa (BR-UC.USER.01-07: khoá xác định liên kết là Google User ID, không
 * phải email). Khi khách bấm "Tiếp tục với Google" bằng một email đã có tài
 * khoản, câu báo bảo họ "đăng nhập bằng Số điện thoại/Mật khẩu để liên kết" —
 * và đây là chỗ ấy. Không có khối này thì câu báo đó chỉ vào một chỗ trống.
 *
 * KHÔNG NÓI ĐANG NỐI VỚI TÀI KHOẢN GOOGLE NÀO. Bảng `users` chỉ giữ
 * `google_id` — chuỗi `sub` của Google, một dãy số không có nghĩa với người
 * đọc — và email của Google chưa chắc trùng email tài khoản nên không mượn
 * $profile['email'] hiện thay được. Hiện tên tài khoản Google lên đây là việc
 * của một cột mới cộng một migration; xem UserModel::googleLink().
 * ─────────────────────────────────────────────────────────────────────────────
 */
$googleOn  = GoogleAuth::isConfigured();
$daNoi     = !empty($google['linked']);
$moGoGoogle = isset($_GET['go-google']);
?>

<section class="acct-card acct-link" id="tai-khoan-google">
    <h2 class="acct-form__title">Tài khoản Google</h2>

    <?php if ($daNoi): ?>
        <p class="acct-link__state">
            <span class="acct-link__dot" aria-hidden="true"></span>
            Đã liên kết. Bạn có thể đăng nhập bằng <strong>“Tiếp tục với Google”</strong>
            hoặc bằng số điện thoại/email và mật khẩu — cả hai vào cùng tài khoản này.
        </p>

        <?php if (!$moGoGoogle): ?>
            <a class="acct-btn acct-btn--quiet acct-btn--sm acct-btn--start"
               href="/tai-khoan?muc=ho-so&amp;go-google=1#tai-khoan-google">Gỡ liên kết</a>
        <?php else: ?>
            <?php
            /* HỎI MẬT KHẨU — VÀ KHÔNG PHẢI CHỈ ĐỂ NHẬN MẶT KHÁCH.

               Gỡ liên kết là tháo một lối vào, nên câu hỏi thật là "gỡ xong
               còn lối nào không". Tài khoản đăng ký bằng Google có
               password_hash là một chuỗi bcrypt thật băm từ 32 byte ngẫu
               nhiên không ai biết, nên không phép kiểm nào trên cột ấy phân
               biệt được "mật khẩu khách tự đặt" với "mật khẩu không ai biết".
               Gõ đúng được thì mật khẩu có thật và khách biết nó — đó chính
               là câu trả lời. Lý do đầy đủ ở UserModel::unlinkGoogle(). */
            ?>
            <form class="acct-form acct-link__form" method="post" action="/tai-khoan/google/go">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

                <p class="acct-form__note">
                    Sau khi gỡ, bạn chỉ còn đăng nhập bằng số điện thoại hoặc email
                    và mật khẩu. Nhập mật khẩu hiện tại để xác nhận.
                </p>

                <label class="acct-field">
                    <span class="acct-field__label">Mật khẩu hiện tại <span aria-hidden="true">*</span></span>
                    <input class="acct-field__input" type="password" name="mat_khau"
                           required autocomplete="current-password">
                </label>

                <div class="acct-link__acts">
                    <button type="submit" class="acct-btn acct-btn--outline acct-btn--sm">
                        Gỡ liên kết
                    </button>
                    <a class="acct-btn acct-btn--quiet acct-btn--sm"
                       href="/tai-khoan?muc=ho-so#tai-khoan-google">Để nguyên</a>
                </div>
            </form>
        <?php endif; ?>

    <?php elseif ($googleOn): ?>
        <p class="acct-link__lead">
            Liên kết để đăng nhập nhanh bằng <strong>“Tiếp tục với Google”</strong>.
            Tài khoản, đơn hàng và lịch hẹn của bạn giữ nguyên — đây chỉ là thêm
            một cách đăng nhập, không phải một tài khoản thứ hai.
        </p>

        <?php
        /* NÚT LÀ MỘT FORM POST, không phải thẻ <a>.

           Đường /tai-khoan/google khai POST ở config/routes.php vì Router
           không lọc theo phương thức: để nó mở với GET thì một trang bất kỳ
           gắn <img src="/tai-khoan/google"> cũng đẩy được khách đang đăng
           nhập sang màn hình Google, và cú bấm "Đồng ý" ở đó nối một tài khoản
           họ không định nối. Ô _token chặn nốt đường POST giả. */
        ?>
        <form method="post" action="/tai-khoan/google">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <button type="submit" class="acct-btn acct-btn--outline acct-btn--sm acct-btn--google">
                <?php partial('auth/_google-icon'); ?>
                Liên kết tài khoản Google
            </button>
        </form>

    <?php else: ?>
        <?php /* Chưa khai GOOGLE_CLIENT_ID/SECRET. Vẫn vẽ khối chứ không giấu
                 đi: màn đăng nhập cũng để nút Google ở đó kèm nhãn "Sắp có"
                 (auth/index.php), và hai trang nói hai chuyện khác nhau về
                 cùng một tính năng thì khách tưởng mình làm sai ở đâu. */ ?>
        <p class="acct-link__lead">
            Liên kết để đăng nhập nhanh bằng “Tiếp tục với Google”.
        </p>
        <button type="button" class="acct-btn acct-btn--outline acct-btn--sm acct-btn--google"
                disabled>
            <?php partial('auth/_google-icon'); ?>
            Liên kết tài khoản Google
            <span class="acct-link__soon">Sắp có</span>
        </button>
    <?php endif; ?>
</section>

<?php
/*
 * ─────────────────────────────────────────────────────────────────────────────
 * XOÁ TÀI KHOẢN — SRS v2.1.0, UC-01 · FR-TK-17
 *
 * TÁCH KHỎI FORM HỒ SƠ, VÀ ĐÓNG SẴN.
 *
 *   form riêng    gộp vào form hồ sơ thì mỗi lần lưu ngày sinh cũng gửi kèm
 *                 trường xoá, và chỉ cần một lỗi ở tầng đọc là mất tài khoản.
 *   đóng sẵn      mở bằng ?xoa=1 trên địa chỉ, cùng lối với ?sua= của sổ địa
 *                 chỉ. Không có JS thì vẫn mở được.
 * ─────────────────────────────────────────────────────────────────────────────
 */
$moXoa   = isset($_GET['xoa']);
$canhBao = isset($_GET['canh-bao']);
?>

<section class="acct-card acct-danger" id="xoa-tai-khoan">
    <h2 class="acct-form__title">Xoá tài khoản</h2>

    <?php if (!$moXoa): ?>
        <p class="acct-danger__lead">
            Bạn có thể yêu cầu xoá tài khoản Vin Eyewear của mình. Đơn hàng đã đặt
            vẫn được cửa hàng xử lý bình thường.
        </p>
        <a class="acct-btn acct-btn--outline acct-btn--sm"
           href="/tai-khoan?muc=ho-so&amp;xoa=1#xoa-tai-khoan">Tôi muốn xoá tài khoản</a>
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
                   href="/tai-khoan?muc=ho-so#xoa-tai-khoan">Không xoá nữa</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php
/*
 * ═════════════════════════════════════════════════════════════════════════════
 * KHU VỰC 2 · SỔ ĐỊA CHỈ — UC-USER-05, Giao diện Khu vực 2
 *
 * Dựng lại 2026-09-10 sau khi bị thu về một địa chỉ hồi 12/09; bảng `addresses`
 * chưa bao giờ bị xoá nên dữ liệu cũ còn nguyên. Xem khối đầu app/models/AddressModel.php.
 * ═════════════════════════════════════════════════════════════════════════════
 */
$moThem = $themDiaChi && $editing === null;
?>
<section id="so-dia-chi">
    <div class="acct-head acct-head--sub acct-head--row">
        <div>
            <h2 class="acct-head__title">Sổ địa chỉ</h2>
            <p class="acct-head__lead">
                Nơi nhận hàng của bạn. Địa chỉ mặc định được điền sẵn khi đặt hàng.
            </p>
        </div>

        <?php if (!$moThem && $editing === null && count($addresses) < $toiDaDiaChi): ?>
            <a class="acct-btn acct-btn--outline acct-btn--sm"
               href="/tai-khoan?muc=ho-so&amp;them=1#them-dia-chi">Thêm địa chỉ mới</a>
        <?php endif; ?>
    </div>

    <?php if ($addresses === [] && !$moThem): ?>
        <div class="acct-empty">
            <span class="acct-empty__ring" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#b0736a"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"></path>
                    <circle cx="12" cy="10" r="2.6"></circle>
                </svg>
            </span>
            <span class="acct-empty__title">Bạn chưa có địa chỉ nào</span>
            <span class="acct-empty__lead">Hãy thêm địa chỉ đầu tiên để đặt hàng nhanh hơn.</span>
            <a class="acct-empty__cta" href="/tai-khoan?muc=ho-so&amp;them=1#them-dia-chi">
                Thêm địa chỉ mới
            </a>
        </div>
    <?php else: ?>
        <div class="acct-list">
            <?php foreach ($addresses as $dc): ?>
                <?php if ($editing !== null && $editing['id'] === $dc['id']): ?>
                    <?php
                    /* Form sửa thay CHỖ của thẻ, không mở thêm ở cuối danh sách:
                       khách đang nhìn vào thẻ nào thì form phải hiện ra ở đó. */
                    partial('auth/account/_dia-chi-form', [
                        'dc'      => $editing,
                        'nhanCua' => $nhanDiaChi,
                        'action'  => '/tai-khoan/dia-chi/sua',
                        'tieuDe'  => 'Sửa địa chỉ',
                        'nutLuu'  => 'Lưu địa chỉ',
                    ]);
                    ?>
                <?php else: ?>
                    <article class="acct-card acct-addr">
                        <div class="acct-addr__body">
                            <div class="acct-addr__who">
                                <span class="acct-addr__name"><?= e($dc['recipient_name']) ?></span>
                                <span class="acct-addr__bar" aria-hidden="true"></span>
                                <span class="acct-addr__phone"><?= e($dc['phone']) ?></span>

                                <?php if (!empty($dc['nhan']) && isset($nhanDiaChi[$dc['nhan']])): ?>
                                    <span class="acct-tag acct-tag--quiet"><?= e($nhanDiaChi[$dc['nhan']]) ?></span>
                                <?php endif; ?>

                                <?php if ((int) $dc['is_default'] === 1): ?>
                                    <span class="acct-tag">Mặc định</span>
                                <?php endif; ?>
                            </div>

                            <p class="acct-addr__lines">
                                <?php
                                /* Ghép bằng array_filter: địa chỉ nhập từ trước
                                   bản này có thể thiếu phường hoặc tỉnh, và một
                                   chuỗi "Số 5, , Hà Nội" trông như dữ liệu hỏng. */
                                echo e(implode(', ', array_filter([
                                    (string) $dc['line1'],
                                    (string) ($dc['ward_name'] ?? ''),
                                    (string) ($dc['province_name'] ?? ''),
                                ], static fn ($p) => trim($p) !== '')));
                                ?>
                            </p>

                            <?php if (!empty($dc['ghi_chu'])): ?>
                                <p class="acct-addr__note">Ghi chú: <?= e($dc['ghi_chu']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="acct-addr__side">
                            <div class="acct-addr__links">
                                <a href="/tai-khoan?muc=ho-so&amp;sua=<?= e($dc['id']) ?>#so-dia-chi">Sửa</a>

                                <?php
                                /* XOÁ QUA POST + HỎI LẠI. GET thì một thẻ
                                   <img src="/tai-khoan/dia-chi/xoa?id=…"> trên
                                   trang khác cũng xoá được địa chỉ của khách
                                   đang đăng nhập. onsubmit là lớp dự phòng khi
                                   không có JS; confirm-dialog.js gỡ nó ra khi đã
                                   sẵn sàng mở hộp thoại thật. */
                                $hoiXoa = sprintf('Xoá địa chỉ của %s?', $dc['recipient_name']);
                                ?>
                                <form method="post" action="/tai-khoan/dia-chi/xoa"
                                      data-confirm="<?= e($hoiXoa) ?>"
                                      data-confirm-title="Xoá địa chỉ?"
                                      data-confirm-ok="Xoá địa chỉ"
                                      data-confirm-cancel="Giữ lại"
                                      onsubmit="return confirm('<?= e($hoiXoa) ?>')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($dc['id']) ?>">
                                    <button type="submit" class="acct-addr__del">Xoá</button>
                                </form>
                            </div>

                            <?php if ((int) $dc['is_default'] !== 1): ?>
                                <form method="post" action="/tai-khoan/dia-chi/mac-dinh">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($dc['id']) ?>">
                                    <button type="submit" class="acct-btn acct-btn--quiet acct-btn--sm">
                                        Đặt làm mặc định
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($moThem): ?>
                <?php
                partial('auth/account/_dia-chi-form', [
                    'dc'      => null,
                    'nhanCua' => $nhanDiaChi,
                    'action'  => '/tai-khoan/dia-chi/them',
                    'tieuDe'  => 'Thêm địa chỉ mới',
                    'nutLuu'  => 'Thêm địa chỉ',
                ]);
                ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php
/*
 * ═════════════════════════════════════════════════════════════════════════════
 * KHU VỰC 3 · ĐỔI MẬT KHẨU
 *
 * Nội dung nằm ở auth/account/mat-khau.php. File ấy TỪNG là một mục riêng
 * (?muc=mat-khau) mà profile.php require thẳng; nay nó là một mảnh của trang
 * này. Giữ nguyên đường dẫn file để mọi liên kết cũ trong tài liệu còn đúng.
 * ═════════════════════════════════════════════════════════════════════════════
 */
partial('auth/account/mat-khau', ['profile' => $profile]);
?>
