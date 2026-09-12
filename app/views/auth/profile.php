<?php

/**
 * auth/profile.php — trang tài khoản (/tai-khoan)
 *
 * Hai cột: cột điều hướng 300px dính theo cuộn | vùng nội dung.
 * Cột trái: thẻ khách (avatar + họ tên) rồi các mục; vùng phải: đúng MỘT mục,
 * chọn bằng ?muc=.
 *
 * CSS: assets/css/account.css
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CỘT ĐIỀU HƯỚNG — BR-UC.USER.05-02
 *
 * Đặc tả chốt danh sách phẳng, không cấp lồng:
 *
 *     Hồ sơ cá nhân    (icon user)          — highlight khi đang ở trang này
 *     Đơn hàng         (icon shopping-bag)
 *     Đã lưu           (icon bookmark)      — NGOÀI đặc tả, thêm 12/09/2026
 *     Lịch hẹn của tôi (icon calendar)      — NGOÀI đặc tả, xem bên dưới
 *     Đăng xuất        (icon sign-out)      — hỏi lại trước khi thoát
 *
 * NHÓM THU GỌN "TÀI KHOẢN CỦA TÔI" ĐÃ GỠ (2026-09-10). Nó bọc hai mục "Hồ sơ"
 * và "Đổi mật khẩu" trong một <details>. Cả hai lý do tồn tại của nó đều mất:
 * đặc tả đòi một danh sách phẳng, và "Đổi mật khẩu" không còn là một mục nữa —
 * nó là khu vực thứ ba NGAY TRONG trang Hồ sơ (BR-UC.USER.05-03).
 *
 * "LỊCH HẸN CỦA TÔI" GIỮ LẠI dù đặc tả không vẽ. Khối lịch hẹn đo mắt đang
 * chạy thật (BookingModel::forUser) và đây là chỗ DUY NHẤT khách xem được lịch
 * của mình; gỡ khỏi cột này là gỡ luôn chức năng. Danh sách mục vốn là một vòng
 * lặp chứ không phải ba ô vẽ cứng, nên thêm một mục không phá bố cục.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỔI MỤC BẰNG URL, KHÔNG BẰNG JAVASCRIPT
 *
 * Mỗi mục là một URL riêng (?muc=don-hang…), nên F5 không mất chỗ, gửi link cho
 * nhân viên hỗ trợ được, và trang chạy cả khi tắt JS. assets/js/account.js chỉ
 * chặn cú bấm để nạp ngầm — nó là tăng cường, không phải cơ chế.
 */

/* `?? ''` chứ không `?:` trần: $profile là NULL khi không đọc được hồ sơ (EF-01,
   xử lý ở vùng nội dung bên dưới), và thẻ khách ở cột trái vẫn phải vẽ ra được
   — cột điều hướng không được biến mất chỉ vì một câu truy vấn hỏng. */
$name    = ($profile['full_name'] ?? '') ?: 'Khách hàng';
$initial = utf8Substr($name, 0, 1);
?>

<section class="acct">
    <div class="acct__grid">

        <!-- ══════════ CỘT ĐIỀU HƯỚNG ══════════ -->
        <aside class="acct-nav" aria-label="Mục tài khoản">

            <!--
                Thẻ khách kiêm luôn chỗ đổi ảnh đại diện: bấm thẳng vào hình
                tròn là mở hộp chọn file. Gộp vào đây thì ảnh nằm đúng chỗ nó
                hiện ra, và đổi được từ BẤT KỲ mục nào chứ không phải quay về
                mục Hồ sơ trước.

                Ô <input type="file"> thật nằm trong <label>, phủ kín hình tròn
                và trong suốt — bàn phím và trình đọc màn hình vẫn tới được.
            -->
            <form class="acct-nav__me" method="post" action="/tai-khoan/anh"
                  enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <!-- Trình duyệt tự chặn file quá cỡ trước khi tải lên;
                     AvatarStorage kiểm lại ở máy chủ vì giá trị này sửa được. -->
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= AvatarStorage::MAX_BYTES ?>">

                <label class="acct-nav__face" title="Đổi ảnh đại diện — tối đa 1 MB, JPEG hoặc PNG">
                    <span class="acct-nav__facein">
                        <?php if (!empty($profile['avatar_path'])): ?>
                            <img src="<?= e(asset($profile['avatar_path'])) ?>" alt=""
                                 width="54" height="54">
                        <?php else: ?>
                            <!-- Chưa có ảnh thì dùng chữ cái đầu của tên, đúng như
                                 bản thiết kế vẽ (ô tròn hồng phấn, chữ Lora 20px). -->
                            <span class="acct-nav__initial" aria-hidden="true"><?= e($initial) ?></span>
                        <?php endif; ?>
                    </span>

                    <!-- Huy hiệu máy ảnh hiện SẴN, không đợi rê chuột: trên
                         điện thoại không có trạng thái rê chuột nào để mà đợi,
                         mà đây lại là thứ duy nhất cho biết hình tròn bấm được. -->
                    <span class="acct-nav__cam" aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 8.5h3.5L8 6h8l1.5 2.5H21v11H3z"></path>
                            <circle cx="12" cy="13.5" r="3.2"></circle>
                        </svg>
                    </span>

                    <input type="file" name="avatar" accept="image/jpeg,image/png" required
                           aria-label="Đổi ảnh đại diện. Dung lượng tối đa 1 MB, định dạng JPEG hoặc PNG.">
                </label>

                <span class="acct-nav__who">
                    <span class="acct-nav__name"><?= e($name) ?></span>
                    <a class="acct-nav__edit" href="/tai-khoan?muc=ho-so&amp;sua-ho-so=1#thong-tin">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                        </svg>
                        Chỉnh sửa hồ sơ
                    </a>
                </span>

                <!-- Có JS thì chọn ảnh xong gửi luôn (account.js), nên nút này
                     ẩn đi. Không có JS thì nó là cách duy nhất để gửi form. -->
                <button type="submit" class="acct-nav__send">Tải ảnh lên</button>
            </form>

            <?php
            /* Các mục dựng từ chính bảng SECTIONS của controller. Vòng lặp chứ
               không mấy khối vẽ tay: thêm/bớt một mục thì chỉ có một chỗ để
               sửa, và thứ tự hiện ra luôn là thứ tự khai trong SECTIONS.
               (Mục "Đã lưu" thêm 12/09/2026 đúng bằng cách ấy: một dòng trong
               SECTIONS, một case trong sectionData, một file view.) */
            foreach ($sections as $key => $label): ?>
                <a class="acct-nav__item<?= $section === $key ? ' is-active' : '' ?>"
                   href="/tai-khoan?muc=<?= e($key) ?>"
                   <?= $section === $key ? 'aria-current="page"' : '' ?>>
                    <span class="acct-nav__icon" aria-hidden="true">
                        <?php partial('auth/_nav-icon', ['key' => $key]); ?>
                    </span>
                    <span class="acct-nav__label"><?= e($label) ?></span>
                    <?php if (!empty($counts[$key])): ?>
                        <span class="acct-nav__count"><?= (int) $counts[$key] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <div class="acct-nav__foot">
                <?php
                /*
                 * Ở ĐÂY TỪNG CÓ MỘT MỤC "KHU QUẢN TRỊ" — đã gỡ, và không được
                 * đưa lại.
                 *
                 * Nó bọc trong `if ($isStaff)`, tức là chỉ hiện với tài khoản
                 * nội bộ đang xem trang tài khoản khách. Từ khi hai khu vực có
                 * PHIÊN RIÊNG (xem App::startSession), cảnh đó không tồn tại
                 * nữa: trang này chạy trên cookie `vin_session`, nên nó không
                 * có cách nào biết người đang xem cũng đang mở khu quản trị —
                 * nhánh ấy vĩnh viễn không chạy.
                 *
                 * Nhân viên muốn vào khu quản trị thì đi cửa của họ:
                 * /quan-tri/dang-nhap.
                 */

                /* HỎI LẠI TRƯỚC KHI ĐĂNG XUẤT — BR-UC.USER.05-02 và mục Giao
                   diện đều nói "Click vào Đăng xuất: hiển thị hộp thoại xác
                   nhận". Hộp thoại thật do confirm-dialog.js mở, đọc chữ từ ba
                   thuộc tính data-confirm* dưới đây; onsubmit là lớp dự phòng
                   khi chưa có JS, và chính file JS đó gỡ nó ra khi đã sẵn sàng.

                   Đăng xuất vẫn qua POST: một thẻ <img src="/auth/dang-xuat">
                   trên trang khác cũng đủ để đá khách ra nếu dùng GET. Hộp thoại
                   không thay thế được điều đó — nó hỏi người thật, còn CSRF
                   token chặn request giả. */
                $hoiThoat = 'Đăng xuất khỏi tài khoản Vin Eyewear?';
                ?>
                <form method="post" action="/auth/dang-xuat"
                      data-confirm="<?= e($hoiThoat) ?>"
                      data-confirm-title="Đăng xuất?"
                      data-confirm-ok="Đăng xuất"
                      data-confirm-cancel="Ở lại"
                      onsubmit="return confirm('<?= e($hoiThoat) ?>')">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="acct-nav__quiet">
                        <span class="acct-nav__icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <path d="M16 17l5-5-5-5"></path>
                                <path d="M21 12H9"></path>
                            </svg>
                        </span>
                        <span>Đăng xuất</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- ══════════ VÙNG NỘI DUNG ══════════ -->
        <div class="acct-main">

            <?php if ($success !== null): ?>
                <p class="acct-flash acct-flash--ok" role="status"><?= e($success) ?></p>
            <?php endif; ?>
            <?php if ($error !== null): ?>
                <p class="acct-flash acct-flash--err" role="alert"><?= e($error) ?></p>
            <?php endif; ?>

            <?php
            /*
             * EF-01 — KHÔNG TRUY XUẤT ĐƯỢC HỒ SƠ.
             *
             * $profile là null khi UserModel::profile() không trả về dòng nào:
             * bảng `profiles` thiếu dòng của tài khoản này (dữ liệu lệch), hoặc
             * câu truy vấn hỏng. Trước 2026-09-10 nhánh này không tồn tại và
             * view đọc thẳng $profile['full_name'] — khách nhận nguyên một trang
             * 500 thay vì câu báo mà đặc tả viết sẵn.
             *
             * Dựng cột điều hướng NHƯ THƯỜNG rồi mới báo lỗi ở vùng nội dung:
             * "không đọc được hồ sơ" không có nghĩa là đơn hàng và lịch hẹn cũng
             * hỏng, nên đừng khoá luôn hai lối đó. Câu báo KHÔNG kèm chi tiết kỹ
             * thuật — BR-UC.USER.05-05 cấm in stack trace hay lỗi SQL ra giao
             * diện; chi tiết đã nằm trong error_log của AuthController::profile().
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
   LẦN ở khung ngoài chứ không trong từng mục: nhờ vậy thao tác cần hỏi lại
   tiếp theo chỉ phải thêm thuộc tính data-confirm, không phải nhớ thêm dòng
   require, và không bao giờ có hai hộp cùng id trên một trang. */
partial('_layout/confirm-dialog');
?>
