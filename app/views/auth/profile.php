<?php

/**
 * auth/profile.php — trang tài khoản (/tai-khoan)
 *
 * Dựng theo "Vin Eyewear Account.dc.html" (Claude Design):
 *
 *   hai cột — cột điều hướng 300px dính theo cuộn | vùng nội dung
 *   cột trái: thẻ khách + nhóm "Tài khoản của tôi" thu gọn được + ba mục
 *             cấp một + đăng xuất
 *   vùng phải: đúng MỘT mục hiện tại, chọn bằng ?muc=
 *
 * CSS: assets/css/account.css
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CHỖ CỐ Ý KHÁC BẢN THIẾT KẾ — VÀ VÌ SAO
 *
 * 1. ĐỔI MỤC BẰNG URL, KHÔNG BẰNG JAVASCRIPT.
 *    Bản thiết kế giữ mục đang xem trong state của trình duyệt. Ở đây mỗi mục
 *    là một URL riêng (?muc=don-hang…), nên F5 không mất chỗ, gửi link cho
 *    nhân viên hỗ trợ được, và trang chạy cả khi tắt JS. Nhóm "Tài khoản của
 *    tôi" đóng/mở bằng <details> — cũng là hành vi y hệt bản thiết kế nhưng
 *    của chính trình duyệt, không cần một dòng JS nào.
 *
 * 2. BẢY MỤC CHỨ KHÔNG SÁU — thêm "Lịch hẹn của tôi".
 *    Trang tài khoản cũ đã có khối lịch hẹn đo mắt và nó đang chạy thật
 *    (BookingModel::forUser). Bản thiết kế không vẽ mục này, nhưng cột điều
 *    hướng của nó là một danh sách lặp (`sc-for navItems`) chứ không phải ba ô
 *    vẽ cứng, nên thêm một mục KHÔNG phá bố cục. Bỏ nó đi thì khách không còn
 *    chỗ nào xem lịch hẹn của mình.
 *
 * 3. SÁU THẺ LỌC ĐƠN HÀNG THÀNH BẢY.
 *    Bản thiết kế liệt kê 5 trạng thái đơn; OrderModel::STATUSES có 6. Dải thẻ
 *    lọc cũng là danh sách lặp, nên nó dựng thẳng từ hằng đó — trạng thái là
 *    của hệ thống, danh sách trong file thiết kế chỉ là dữ liệu mẫu.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$name    = $profile['full_name'] ?: 'Khách hàng';
$initial = utf8Substr($name, 0, 1);

/* Ba mục trong nhóm thu gọn được, và ba mục cấp một còn lại. Thứ tự lấy
   nguyên từ bản thiết kế. */
$groupKeys = ['ho-so', 'dia-chi', 'mat-khau'];
$navKeys   = ['don-hang', 'do-mat', 'lich-hen'];

$inGroup = in_array($section, $groupKeys, true);
?>

<section class="acct">
    <div class="acct__grid">

        <!-- ══════════ CỘT ĐIỀU HƯỚNG ══════════ -->
        <aside class="acct-nav" aria-label="Mục tài khoản">

            <!--
                Thẻ khách kiêm luôn chỗ đổi ảnh đại diện: bấm thẳng vào hình
                tròn là mở hộp chọn file. Bản thiết kế đặt việc này ở một thẻ
                riêng bên phải mục Hồ sơ; gộp vào đây thì ảnh nằm đúng chỗ nó
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
                    <a class="acct-nav__edit" href="/tai-khoan?muc=ho-so">
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

            <!-- Nhóm thu gọn được. <details open> khi mục đang xem nằm trong
                 nhóm — mở trang ra là thấy ngay mình đang ở đâu. -->
            <details class="acct-nav__group"<?= $inGroup ? ' open' : '' ?>>
                <summary class="acct-nav__item<?= $inGroup ? ' is-current' : '' ?>">
                    <span class="acct-nav__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 21c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5"></path>
                        </svg>
                    </span>
                    <span class="acct-nav__label">Tài khoản của tôi</span>
                    <span class="acct-nav__chev" aria-hidden="true">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9l6 6 6-6"></path>
                        </svg>
                    </span>
                </summary>

                <div class="acct-nav__sub">
                    <?php foreach ($groupKeys as $key): ?>
                        <a class="acct-nav__subitem<?= $section === $key ? ' is-current' : '' ?>"
                           href="/tai-khoan?muc=<?= e($key) ?>"
                           <?= $section === $key ? 'aria-current="page"' : '' ?>>
                            <?= e($key === 'ho-so' ? 'Hồ sơ' : $sections[$key]) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>

            <?php foreach ($navKeys as $key): ?>
                <a class="acct-nav__item<?= $section === $key ? ' is-active' : '' ?>"
                   href="/tai-khoan?muc=<?= e($key) ?>"
                   <?= $section === $key ? 'aria-current="page"' : '' ?>>
                    <span class="acct-nav__icon" aria-hidden="true">
                        <?php partial('auth/_nav-icon', ['key' => $key]); ?>
                    </span>
                    <span class="acct-nav__label"><?= e($sections[$key]) ?></span>
                    <?php if (!empty($counts[$key])): ?>
                        <span class="acct-nav__count"><?= (int) $counts[$key] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <div class="acct-nav__foot">
                <?php if ($isStaff): ?>
                    <!-- Chỉ hiện với nhân viên. Bản thiết kế không có mục này
                         vì nó vẽ trang của khách hàng. -->
                    <a class="acct-nav__quiet" href="/quan-tri">
                        <span class="acct-nav__icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="9" rx="1"></rect>
                                <rect x="14" y="3" width="7" height="5" rx="1"></rect>
                                <rect x="14" y="12" width="7" height="9" rx="1"></rect>
                                <rect x="3" y="16" width="7" height="5" rx="1"></rect>
                            </svg>
                        </span>
                        <span>Khu quản trị</span>
                    </a>
                <?php endif; ?>

                <!-- Đăng xuất qua POST: một thẻ <img src="/auth/dang-xuat"> trên
                     trang khác cũng đủ để đá khách ra nếu dùng GET. -->
                <form method="post" action="/auth/dang-xuat">
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

            <?php require VIEWS_PATH . '/auth/account/' . $section . '.php'; ?>

        </div>
    </div>
</section>

<?php
/* Hộp thoại hỏi lại trước khi huỷ lịch hẹn / xoá địa chỉ. In MỘT LẦN ở khung
   ngoài chứ không trong từng mục: trang tài khoản chỉ dựng một mục mỗi lượt,
   nhưng để ở đây thì mục thứ ba cần hỏi lại sau này không phải nhớ thêm dòng
   require, và không bao giờ có hai hộp cùng id trên một trang. */
partial('_layout/confirm-dialog');
?>
