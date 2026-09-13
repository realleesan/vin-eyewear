<?php

/**
 * auth/profile.php — trang tài khoản (/tai-khoan)
 *
 * Dựng theo "Ho So Nguoi Dung.dc.html" (Claude Design, 13/09/2026):
 *
 *   ( Tài khoản ) ( Đơn hàng ) ( Đã lưu ) ( Sổ địa chỉ ) ( Hồ sơ ) ( Lịch hẹn )   ĐĂNG XUẤT
 *
 *                              TIÊU ĐỀ IN HOA
 *                    một cột nội dung 725px, căn giữa trang
 *
 * CSS: assets/css/account.css · JS: assets/js/account.js
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỔI TAB BẰNG URL, KHÔNG BẰNG JAVASCRIPT
 *
 * Mỗi tab là một URL riêng (?muc=don-hang…), nên F5 không mất chỗ, gửi link cho
 * nhân viên hỗ trợ được, và trang chạy cả khi tắt JS. account.js chỉ chặn cú
 * bấm vào hàng tab để nạp ngầm — nó là tăng cường, không phải cơ chế.
 *
 * GIỮ BA TÊN LỚP .acct__grid / .acct-nav / .acct-main: account.js bám vào khung
 * ngoài, và thay ruột đúng hai khối .acct-nav và .acct-main sau mỗi cú đổi tab.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÃ GỠ CÙNG BẢN DỰNG NÀY (theo yêu cầu chủ dự án)
 *
 *   - cột điều hướng trái + thẻ khách có ảnh đại diện: bản thiết kế là một hàng
 *     tab ngang, không có ảnh. Đường POST /tai-khoan/anh vẫn còn, chỉ không
 *     còn nút nào gọi tới.
 *   - ô giới tính và ngày sinh trong form hồ sơ: dữ liệu cũ GIỮ NGUYÊN, xem
 *     AuthController::updateProfile().
 */
?>

<section class="acct">
    <div class="acct__grid">

        <!-- ══════════ HÀNG TAB ══════════ -->
        <nav class="acct-nav" aria-label="Mục tài khoản">
            <div class="acct-nav__tabs">
                <?php foreach ($sections as $key => $label): ?>
                    <a class="acct-nav__tab<?= $section === $key ? ' is-active' : '' ?>"
                       href="/tai-khoan?muc=<?= e($key) ?>"
                       <?= $section === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>

            <?php
            /* HỎI LẠI TRƯỚC KHI ĐĂNG XUẤT — BR-UC.USER.05-02. Hộp thoại thật do
               confirm-dialog.js mở từ ba thuộc tính data-confirm*; onsubmit là
               lớp dự phòng khi chưa có JS, chính file JS đó gỡ nó ra.

               Vẫn qua POST dù bản thiết kế vẽ một liên kết: một thẻ
               <img src="/auth/dang-xuat"> trên trang khác cũng đủ đá khách ra
               nếu dùng GET. Nút được CSS cho trông đúng như liên kết ấy. */
            $hoiThoat = 'Đăng xuất khỏi tài khoản Vin Eyewear?';
            ?>
            <form class="acct-nav__out" method="post" action="/auth/dang-xuat"
                  data-confirm="<?= e($hoiThoat) ?>"
                  data-confirm-title="Đăng xuất?"
                  data-confirm-ok="Đăng xuất"
                  data-confirm-cancel="Ở lại"
                  onsubmit="return confirm('<?= e($hoiThoat) ?>')">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <button type="submit" class="acct-nav__logout">Đăng xuất</button>
            </form>
        </nav>

        <!-- ══════════ NỘI DUNG CỦA TAB ══════════ -->
        <div class="acct-main">

            <?php if ($success !== null): ?>
                <p class="acct-flash acct-flash--ok" role="status"><?= e($success) ?></p>
            <?php endif; ?>

            <?php /* Lỗi của hộp thoại xoá tài khoản in NGAY TRONG hộp thoại (xem
                     account/ho-so.php) — in ở đây thì nó nằm dưới lớp phủ, khách
                     không đọc được. */ ?>
            <?php if ($error !== null && !($section === 'ho-so' && isset($_GET['xoa']))): ?>
                <p class="acct-flash acct-flash--err" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <?php
            /*
             * EF-01 — KHÔNG TRUY XUẤT ĐƯỢC HỒ SƠ.
             *
             * $profile là null khi UserModel::profile() không trả về dòng nào.
             * Hàng tab vẫn dựng như thường rồi mới báo ở vùng nội dung: "không
             * đọc được hồ sơ" không có nghĩa là đơn hàng và lịch hẹn cũng hỏng.
             * Câu báo KHÔNG kèm chi tiết kỹ thuật — BR-UC.USER.05-05; chi tiết đã
             * nằm trong error_log của AuthController::profile().
             */
            if ($profile === null) {
                echo '<p class="acct-flash acct-flash--err" role="alert">'
                   . 'Không thể tải thông tin hồ sơ. Vui lòng thử lại sau.</p>';
            } else {
                require VIEWS_PATH . '/auth/account/' . $section . '.php';
            }
            ?>

        </div>
    </div>
</section>

<?php
/* Hộp thoại hỏi lại trước khi đăng xuất / huỷ lịch hẹn / xoá địa chỉ. In MỘT
   LẦN ở khung ngoài chứ không trong từng tab, để không bao giờ có hai hộp cùng
   id trên một trang. */
partial('_layout/confirm-dialog');
?>
