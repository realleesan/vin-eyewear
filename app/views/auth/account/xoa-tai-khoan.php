<?php

/**
 * auth/account/xoa-tai-khoan.php — mục "Xoá tài khoản"
 * (/tai-khoan?muc=xoa-tai-khoan).
 *
 * Mục này KHÔNG có trong "Vin Eyewear Account.dc.html". Nó là nghĩa vụ theo
 * luật bảo vệ dữ liệu cá nhân: khách phải có một đường tự rút lui, và đường
 * đó phải nằm ngay trong hồ sơ của họ chứ không phải sau một cuộc gọi hotline.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NÓI THẬT VỀ THỨ SẼ XẢY RA — KHỐI "ĐIỀU GÌ XẢY RA" LÀ BẮT BUỘC, KHÔNG PHẢI
 * TRANG TRÍ
 *
 * Hệ thống xoá MỀM: tài khoản biến mất khỏi phía khách, nhưng đơn hàng, lịch
 * hẹn và thông số đo mắt vẫn nằm lại trong sổ của cửa hàng để bảo hành và đối
 * chiếu. Giấu điều đó đi rồi chỉ viết "xoá vĩnh viễn" là nói sai sự thật với
 * người đang thực hiện quyền của họ. Nên khối vàng ở giữa trang ghi rõ CẢ HAI
 * VẾ, và ghi trước khi khách chạm vào bất kỳ ô nào của form.
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CHỐT TRƯỚC KHI NÚT ĐỎ BẤM ĐƯỢC, khác nhau về BẢN CHẤT chứ không phải ba
 * lần hỏi cùng một câu:
 *
 *   1. mật khẩu hiện tại   chứng minh đúng người đang ngồi trước máy — trừ
 *                          tài khoản đăng nhập bằng Google, họ không có mật
 *                          khẩu nào để mà gõ (xem $googleLinked)
 *   2. gõ XOA TAI KHOAN    chặn cú bấm nhầm
 *   3. tick ô "tôi hiểu"   xác nhận đã đọc hậu quả
 *
 * HAI CHỐT ĐẦU ĐƯỢC KIỂM LẠI Ở MÁY CHỦ (UserModel::requestDeletion), vì địa
 * chỉ này POST tay được và chúng là thứ giữ cho tài khoản không bị người khác
 * xoá. Ô tick thì không: nó là lời xác nhận đã đọc, không phải chốt bảo mật —
 * ai dựng được một request tay để bỏ qua nó thì đằng nào cũng đã vượt qua hai
 * chốt trên, mà bắt nó lại ở máy chủ chỉ thêm một tham số không đổi được gì.
 */

$hotline = config('company.hotline', '');
$orders  = (int) ($willLose['don-hang'] ?? 0);
$books   = (int) ($willLose['lich-hen'] ?? 0);
?>

<div class="acct-head">
    <h1 class="acct-head__title">Xoá tài khoản</h1>
    <p class="acct-head__lead">
        Bạn có quyền yêu cầu ngừng sử dụng tài khoản Vin Eyewear bất cứ lúc nào.
    </p>
</div>

<!-- ══════════ ĐIỀU GÌ XẢY RA ══════════ -->
<div class="acct-card acct-danger">

    <div class="acct-danger__note">
        <h2 class="acct-danger__h">Sau khi xoá, điều gì xảy ra?</h2>

        <ul class="acct-danger__list">
            <li>
                <strong>Tài khoản đóng lại ngay.</strong>
                Bạn sẽ được đăng xuất khỏi mọi thiết bị và không đăng nhập lại
                được — kể cả bằng Google hay bằng chức năng quên mật khẩu.
            </li>
            <li>
                <strong>Thông tin cá nhân được gỡ khỏi trang.</strong>
                Hồ sơ, sổ địa chỉ, thông số đo mắt và lịch sử mua hàng không
                còn hiển thị ở bất kỳ đâu trên website.
            </li>
            <li>
                <strong>Cửa hàng vẫn lưu hồ sơ giao dịch.</strong>
                Đơn hàng đã đặt và lịch hẹn đã đo được giữ lại trong sổ nội bộ
                để phục vụ bảo hành, đổi trả và nghĩa vụ lưu trữ chứng từ. Đây
                là dữ liệu của giao dịch giữa hai bên, không hiển thị công khai.
            </li>
            <li>
                <strong>Đổi ý thì vẫn mở lại được.</strong>
                Gọi <?= e($hotline) ?> và nhân viên sẽ khôi phục tài khoản cùng
                toàn bộ dữ liệu cũ cho bạn.
            </li>
        </ul>

        <?php if ($orders > 0 || $books > 0): ?>
            <!-- Con số CỦA CHÍNH KHÁCH, không phải một câu chung chung: "bạn
                 sẽ mất quyền xem" nghe rất nhẹ cho tới khi nó nói rõ là 12 đơn
                 hàng. Chỉ hiện khi có gì để mất. -->
            <p class="acct-danger__count">
                Tài khoản này đang có
                <?php if ($orders > 0): ?>
                    <strong><?= $orders ?> đơn hàng</strong><?= $books > 0 ? ' và ' : '' ?>
                <?php endif; ?>
                <?php if ($books > 0): ?>
                    <strong><?= $books ?> lịch hẹn</strong>
                <?php endif; ?>
                mà bạn sẽ không còn tra cứu được trên website.
            </p>
        <?php endif; ?>
    </div>

    <!-- ══════════ FORM XÁC NHẬN ══════════ -->
    <form class="acct-form acct-danger__form" method="post" action="/tai-khoan/xoa">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

        <label class="acct-field">
            <span class="acct-field__label">Vì sao bạn muốn rời đi? <em>(không bắt buộc)</em></span>
            <select class="acct-field__input" name="reason">
                <option value="">— Bạn không cần trả lời —</option>
                <?php foreach ($reasons as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="acct-field__hint">
                Câu trả lời giúp cửa hàng làm tốt hơn và không ảnh hưởng gì tới
                yêu cầu xoá của bạn.
            </span>
        </label>

        <label class="acct-field">
            <span class="acct-field__label">Bạn muốn nói thêm điều gì? <em>(không bắt buộc)</em></span>
            <textarea class="acct-field__input acct-field__area" name="reason_note"
                      rows="3" maxlength="400"
                      placeholder="Chúng tôi đọc từng góp ý một."></textarea>
        </label>

        <hr class="acct-form__rule">

        <label class="acct-field">
            <span class="acct-field__label">
                Mật khẩu hiện tại<?= $googleLinked ? ' <em>(nếu bạn đã đặt)</em>' : '' ?>
            </span>
            <!-- Ô email ẩn: trình quản lý mật khẩu cần biết ô bên dưới thuộc
                 tài khoản nào thì mới điền đúng bản ghi đã lưu. Giống mục
                 "Đổi mật khẩu". -->
            <input type="hidden" autocomplete="username" value="<?= e($profile['email'] ?? '') ?>">
            <input class="acct-field__input" type="password" name="current_password"
                   autocomplete="current-password" placeholder="••••••••"
                   <?= $googleLinked ? '' : 'required' ?>>
            <span class="acct-field__hint">
                <?php if ($googleLinked): ?>
                    Tài khoản của bạn đăng nhập bằng Google nên có thể chưa có
                    mật khẩu riêng — bỏ trống ô này cũng được.
                <?php else: ?>
                    Nhập lại để chắc chắn người đang ngồi trước máy là bạn.
                <?php endif; ?>
            </span>
        </label>

        <label class="acct-field">
            <span class="acct-field__label">
                Gõ <code class="acct-danger__code">XOA TAI KHOAN</code> để xác nhận
            </span>
            <!-- autocomplete/autocorrect tắt hết: đây là ô mà việc gõ tay
                 CHÍNH LÀ mục đích, một gợi ý điền sẵn sẽ vô hiệu hoá chốt. -->
            <input class="acct-field__input" type="text" name="confirm" required
                   autocomplete="off" autocapitalize="characters" spellcheck="false"
                   placeholder="XOA TAI KHOAN">
            <span class="acct-field__hint">Có dấu hay không dấu, hoa hay thường đều được.</span>
        </label>

        <label class="acct-check">
            <input type="checkbox" name="understood" required>
            <span>
                Tôi hiểu tài khoản sẽ bị đóng, tôi sẽ không đăng nhập lại được,
                và cửa hàng vẫn lưu hồ sơ giao dịch của tôi như nêu ở trên.
            </span>
        </label>

        <div class="acct-form__actions">
            <button type="submit" class="acct-btn acct-btn--danger">
                Xoá tài khoản của tôi
            </button>
            <a class="acct-btn acct-btn--quiet" href="/tai-khoan?muc=ho-so">Để nguyên, quay lại</a>
        </div>
    </form>
</div>
