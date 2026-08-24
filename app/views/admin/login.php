<?php

/**
 * admin/login.php — CỔNG QUẢN TRỊ (/quan-tri/dang-nhap)
 *
 * Dựng theo "Admin Login.dc.html" (Claude Design):
 *
 *   khung rút gọn nền TỐI (tên hiệu + huy hiệu ADMIN | chỉ báo môi trường)
 *   → cột 400px giữa màn: tiêu đề, form email/mật khẩu, khối cảnh báo
 *   → chân trang bản quyền + hòm thư cấp quyền
 *
 * CSS: assets/css/admin-login.css · JS: assets/js/auth.js (nút hiện/ẩn)
 * Controller: app/controllers/Admin/AdminAuthController.php
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO CÓ CỔNG RIÊNG, KHÔNG DÙNG /auth NHƯ TRƯỚC
 *
 * Trước bản này, /quan-tri đá người chưa đăng nhập sang /auth — trang đăng
 * nhập của KHÁCH, nền be, có nút "Tạo tài khoản" và "Đăng nhập bằng Google".
 * Nhân viên nhìn thấy đúng cái màn hình mà khách mua kính nhìn thấy, và không
 * có gì trên đó nói rằng họ đang bước vào khu quản trị.
 *
 * Nền tối là chủ ý của bản thiết kế, không phải trang trí: nó là thứ báo cho
 * người đang gõ biết mình đã rời khu bán hàng. Cùng lý do mà admin.css để
 * thanh bên tối — xem khối chú thích đầu file đó.
 *
 * /auth VẪN CHẠY NHƯ CŨ và vẫn đăng nhập được bằng tài khoản quản trị. Cổng
 * này không thay thế nó, chỉ là cửa riêng cho khu riêng.
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CHỖ CỐ Ý KHÁC BẢN THIẾT KẾ — VÀ VÌ SAO
 *
 * 1. KHÔNG CÓ BƯỚC "XÁC THỰC HAI LỚP".
 *    Bản thiết kế vẽ hai màn: nhập mật khẩu rồi nhập 6 số từ ứng dụng xác
 *    thực. Dự án KHÔNG có TOTP — không cột lưu khoá bí mật, không màn ghi
 *    danh, không mã dự phòng. Mã OTP duy nhất đang có là mã gửi qua
 *    email/Zalo cho luồng đăng ký và quên mật khẩu, một thứ hoàn toàn khác.
 *
 *    Dựng màn OTP cho đẹp rồi cho gõ 6 số bất kỳ cũng qua thì tệ hơn là không
 *    dựng: cổng trông như có hai lớp khoá trong khi chỉ có một, và người đọc
 *    mã sau này rất dễ tin rằng lớp thứ hai có thật. Nên bỏ hẳn màn đó, và
 *    nút cuối form đổi từ "Tiếp tục" (sang bước 2) thành "Đăng nhập" — nó
 *    phải hứa đúng việc nó làm.
 *
 *    Cửa hàng đã chốt như vậy. Khi nào cắm TOTP thì màn thứ hai ghép vào
 *    được ngay, phần nhìn của nó nằm sẵn trong bản thiết kế.
 *
 * 2. THÊM KHỐI BÁO LỖI.
 *    Bản thiết kế không vẽ trạng thái sai mật khẩu — bản vẽ nào cũng vẽ lúc
 *    mọi thứ suôn sẻ. Mà đây là một form đăng nhập: gõ sai là chuyện thường
 *    xuyên nhất xảy ra trên nó. Khối lỗi dựng bằng đúng ngôn ngữ hình khối
 *    của khối cảnh báo ngay dưới form (viền mảnh, bo 6px, chữ nhỏ), đổi sang
 *    tông đỏ.
 *
 * 3. NÚT HIỆN/ẨN MẬT KHẨU MẶC ĐỊNH BỊ ẨN.
 *    Bản thiết kế luôn vẽ nó vì trong trình sửa thì JavaScript luôn chạy.
 *    Ở đây nút mang `hidden` và assets/js/auth.js gỡ ra — cùng cách làm với
 *    auth/_password.php, và cùng lý do: một cái nút bấm mà không xảy ra gì
 *    còn khó hiểu hơn là không có nút.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Nhận qua renderView(): $error · $redirect · $old
 */

$old = $old ?? [];
?>

<div class="alog">
    <h1 class="alog__title">Cổng quản trị</h1>
    <p class="alog__lead">Đăng nhập bằng tài khoản nội bộ được cấp quyền.</p>

    <?php if (!empty($error)): ?>
        <?php /* role="alert" để trình đọc màn hình đọc ngay khi trang hiện ra:
                 người dùng bàn phím vừa bấm Đăng nhập và con trỏ đang ở đâu đó
                 giữa form, không tự nhiên đi qua chỗ này. */ ?>
        <p class="alog__error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="alogform" method="post" action="/quan-tri/dang-nhap/xac-thuc">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

        <div class="alogfield">
            <label class="alogfield__label" for="adm-email">Email nội bộ</label>
            <?php /*
                type="email" CHỨ KHÔNG phải type="text" như ô bên /auth.

                Ô bên đó nhận cả số điện thoại nên buộc phải là text. Ở đây thì
                không: tài khoản quản trị do database/make-admin.php tạo, mà
                script đó bắt buộc một email hợp lệ — không có admin nào đăng
                nhập bằng số. Bản thiết kế cũng ghi rõ "Email nội bộ" và chữ mờ
                là một địa chỉ thư.

                autocomplete="username" chứ không "email": đây là ô định danh
                của một form đăng nhập, và đó là giá trị trình quản lý mật khẩu
                dùng để ghép cặp với ô mật khẩu ngay dưới.
            */ ?>
            <input class="alogfield__input" type="email" name="email" id="adm-email"
                   required autocomplete="username" autofocus
                   placeholder="ten@vineyewear.vn"
                   value="<?= e($old['email'] ?? '') ?>">
        </div>

        <div class="alogfield">
            <div class="alogfield__row">
                <label class="alogfield__label" for="adm-pass">Mật khẩu</label>
                <?php /* KHÔNG trỏ sang /quen-mat-khau nữa — đó là luồng OTP CỦA
                         KHÁCH, và từ khi tách hai khu vực thì nó cố tình coi mọi
                         email nội bộ là "không khớp tài khoản nào". Bấm vào là
                         nhận mã, nhập mã là báo sai, thử mãi cũng thế. Nay mở
                         phần trợ giúp ngay tại cổng này. */ ?>
                <a class="alogfield__aside" href="/quan-tri/dang-nhap?quen=1#quen">Quên mật khẩu?</a>
            </div>

            <?php /*
                .authpw / .authpw__eye là bộ lớp mà assets/js/auth.js đi tìm —
                giữ đúng tên để dùng lại nguyên si file đó thay vì viết một
                nút hiện/ẩn thứ hai. Nút phải là ANH EM của <input> trong cùng
                .authpw: auth.js lấy ô nhập bằng btn.parentNode.querySelector.
            */ ?>
            <span class="authpw alogpw">
                <input class="alogfield__input alogpw__input" type="password"
                       name="password" id="adm-pass" required
                       autocomplete="current-password" placeholder="••••••••••">

                <?php /*
                    HAI CHỮ CHỒNG NHAU, CSS ẨN MỘT CÁI THEO aria-pressed.

                    Bản thiết kế đổi chữ trên nút giữa "Hiện" và "Ẩn" (không
                    phải hình con mắt như ô mật khẩu bên /auth). auth.js chỉ
                    đảo aria-pressed chứ không viết lại chữ, nên phần đổi chữ
                    do CSS lo — cùng cách mà auth/_password.php đảo hai hình.
                    Nhờ vậy dùng lại được auth.js mà không sửa một dòng nào.
                */ ?>
                <button type="button" class="authpw__eye alogpw__eye" hidden
                        aria-label="Hiện mật khẩu" aria-pressed="false">
                    <span class="alogpw__on">Hiện</span>
                    <span class="alogpw__off">Ẩn</span>
                </button>
            </span>
        </div>

        <button type="submit" class="alogform__submit">Đăng nhập</button>
    </form>

    <?php
    /*
     * ─────────────────────────────────────────────────────────────────────
     * "QUÊN MẬT KHẨU?" — BA ĐƯỜNG THẬT, KHÔNG PHẢI MỘT Ô NHẬP EMAIL
     *
     * Bản thiết kế vẽ liên kết "Quên mật khẩu?" nhưng không vẽ trang nó dẫn
     * tới, và mục 3.A của SRS chỉ ghi một dòng: "Gửi link đặt lại qua email
     * nội bộ — cần xác nhận lại với BA nếu nhân viên không có email công ty."
     * Tức là luồng ấy CHƯA CHỐT.
     *
     * Trong lúc chờ, khối này nói đúng hiện trạng thay vì dựng một ô nhập
     * email không gửi được gì. Hosting đang để MAIL_DRIVER=log và Zalo ZNS
     * chưa khai đủ, nên kể cả có làm luồng tự động thì mã cũng không tới tay
     * ai — đúng cái cảnh trang /quan-tri/quen-mat-khau đang phải dọn tay.
     *
     * Ba đường xếp theo thứ tự nên thử: nhờ đồng nghiệp (nhanh nhất, không
     * cần ai biết dòng lệnh), rồi tới người giữ máy chủ. Người đọc dừng ở
     * dòng đầu tiên áp dụng được cho mình.
     * ─────────────────────────────────────────────────────────────────────
     */
    ?>
    <?php if (!empty($showHelp)): ?>
        <div class="aloghelp" id="quen" role="note">
            <p class="aloghelp__title">Quên mật khẩu tài khoản nội bộ</p>

            <p class="aloghelp__text">
                Cổng này không tự gửi được mã đặt lại — tài khoản nội bộ cố ý
                không đi qua luồng "Quên mật khẩu" của khách. Ba cách lấy lại,
                xếp theo thứ tự nên thử:
            </p>

            <ol class="aloghelp__list">
                <li>
                    Nhờ một <strong>quản trị viên</strong> khác cấp lại giúp ở
                    mục <em>Tài khoản nội bộ</em> trong khu quản trị. Mật khẩu
                    mới hiện đúng một lần trên màn hình của họ.
                </li>
                <li>
                    Không liên lạc được ai, nhưng có người giữ mã nguồn: chạy
                    <code class="aloghelp__code">php database/make-admin.php --reset-password &lt;email&gt;</code>
                </li>
                <li>
                    Không còn quản trị viên nào đăng nhập được nữa: phải sửa
                    thẳng trong cơ sở dữ liệu qua phpMyAdmin của hosting.
                </li>
            </ol>

            <p class="aloghelp__back">
                <a href="/quan-tri/dang-nhap">← Quay lại đăng nhập</a>
            </p>
        </div>
    <?php endif; ?>

    <?php /*
        CHỖ KHÁC BẢN THIẾT KẾ THỨ TƯ — CÂU THỨ HAI TRONG KHỐI NÀY.

        Bản thiết kế viết: "Mọi phiên đăng nhập được ghi lại cùng địa chỉ IP
        và thiết bị." Câu đó KHÔNG ĐÚNG với dự án này. Không có bảng nhật ký
        đăng nhập nào, và địa chỉ IP không được lưu ở bất cứ đâu — chỗ duy
        nhất trong cả mã nguồn ghi User-Agent là remember_tokens, và nó chỉ
        ghi khi khách tick "duy trì đăng nhập", một ô mà cổng này không có.

        In nguyên câu ấy ra là dán một lời hứa bảo mật sai lên cửa khu quản
        trị. Nó rơi đúng vào loại việc mà cửa hàng vừa gạt đi ở bước xác thực
        hai lớp: trông như có bảo vệ trong khi không có gì, và người đọc mã
        sau này tin vào cái nhãn thay vì đi kiểm.

        Nên câu thứ hai đổi sang thứ ĐÚNG với hiện trạng — phiên gắn với tài
        khoản, và lời nhắc đừng dùng chung mật khẩu. Vẫn giữ được sức răn đe
        của khối này mà không nói điều không có.

        Muốn câu gốc thành sự thật thì phải thêm bảng nhật ký đăng nhập (ai,
        lúc nào, từ IP nào, bằng thiết bị gì) và ghi vào đó ở
        AdminAuthController::login(). Việc đó nằm ngoài phạm vi "dựng màn
        đăng nhập" nên chưa làm ở đây.
    */ ?>
    <p class="alognote">
        <?php /*
            CHỖ KHÁC BẢN THIẾT KẾ THỨ NĂM — HÌNH KHOÁ.

            Bản thiết kế đặt ở đây ký tự ⚿ (U+26BF, "squared key"). Không font
            nào site đang nạp có glyph đó — Be Vietnam Pro, Lora và JetBrains
            Mono đều không — nên trình duyệt vẽ ra một Ô VUÔNG RỖNG. Đã thấy
            tận mắt khi chụp màn hình lần dựng đầu.

            Thay bằng icon SVG có sẵn của dự án: nó vẽ giống nhau trên mọi máy,
            ăn màu theo currentColor, và đã mang sẵn aria-hidden.
        */ ?>
        <?= icon('shield', 'alognote__icon', 15) ?>
        <span>Khu vực dành riêng cho nhân viên được cấp quyền. Phiên đăng nhập
              gắn với tài khoản của bạn — đừng dùng chung mật khẩu.</span>
    </p>
</div>
