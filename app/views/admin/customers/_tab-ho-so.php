<?php

/**
 * _tab-ho-so.php — tab 1: hồ sơ khách hàng và các thao tác trên tài khoản.
 *
 * Biến dùng ở đây do detail.php và controller cung cấp: $khach, $genders,
 * $canManage, $duongDan, $daKhoa, $daXoa.
 */
?>

<?php if ($daXoa): ?>
    <div class="anote">
        <p>
            <strong>Tài khoản này đã bị xoá</strong> lúc
            <?= e(formatDate($khach['deleted_at'], 'd/m/Y H:i')) ?>. Khách không đăng
            nhập được nữa, nhưng đơn hàng cũ vẫn giữ nguyên chủ — đó là lý do dữ liệu
            còn nằm đây thay vì bị xoá hẳn.
        </p>
        <?php if (($khach['deletion_reason'] ?? null) !== null && $khach['deletion_reason'] !== ''): ?>
            <p>Lý do: <?= e($khach['deletion_reason']) ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="acus__cols">

    <!-- ======================= HỒ SƠ ======================= -->
    <section class="apanel">
        <div class="apanel__head">
            <h2 class="apanel__title">Thông tin cá nhân</h2>
        </div>

        <form class="aform" method="post" action="/quan-tri/khach-hang/ho-so">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($khach['id']) ?>">

            <div class="aform__grid">
                <div class="field field--wide">
                    <label for="ten">Họ và tên</label>
                    <input type="text" id="ten" name="full_name" maxlength="255"
                           value="<?= e((string) ($khach['full_name'] ?? '')) ?>">
                </div>

                <div class="field">
                    <label for="sdt">Số điện thoại</label>
                    <input type="tel" id="sdt" name="phone"
                           value="<?= e((string) ($khach['phone'] ?? '')) ?>">
                    <?php /* Số điện thoại là MỘT trong hai cách khách đăng nhập
                             và cột `profiles`.`phone` có khoá duy nhất. Nói ra ở
                             đây vì người sửa hộ khách không có cách nào tự biết
                             điều đó, và họ sẽ gặp lỗi "số đã gắn với tài khoản
                             khác" mà không hiểu vì sao. */ ?>
                    <p class="field__hint">Khách dùng số này để đăng nhập. Mỗi số chỉ thuộc một tài khoản.</p>
                </div>

                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= e((string) ($khach['email'] ?? '')) ?>">
                    <p class="field__hint">
                        <?php if ((string) ($khach['email'] ?? '') === ''): ?>
                            Chưa có — không gửi được liên kết đặt lại mật khẩu.
                        <?php elseif ((int) $khach['email_verified'] === 1): ?>
                            Đã xác minh. Đổi địa chỉ sẽ đưa nó về trạng thái chưa xác minh.
                        <?php else: ?>
                            Chưa xác minh.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="field">
                    <label for="ngay-sinh">Ngày sinh</label>
                    <input type="date" id="ngay-sinh" name="date_of_birth"
                           max="<?= e(date('Y-m-d')) ?>"
                           value="<?= e((string) ($khach['date_of_birth'] ?? '')) ?>">
                </div>

                <div class="field">
                    <label for="gioi-tinh">Giới tính</label>
                    <select id="gioi-tinh" name="gender">
                        <?php /* Lựa chọn rỗng là BẮT BUỘC PHẢI CÓ: ba nút giới
                                 tính không có nút "bỏ chọn", nên đây là đường duy
                                 nhất quay về trạng thái chưa chọn. */ ?>
                        <option value="">— chưa chọn —</option>
                        <?php foreach ($genders as $ma => $nhan): ?>
                            <option value="<?= e($ma) ?>"
                                <?= ($khach['gender'] ?? '') === $ma ? 'selected' : '' ?>>
                                <?= e($nhan) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="astatus__save">Lưu hồ sơ</button>
            </div>
        </form>
    </section>

    <!-- ======================= TÀI KHOẢN ======================= -->
    <section class="apanel">
        <div class="apanel__head">
            <h2 class="apanel__title">Tài khoản</h2>
        </div>

        <dl class="acus__facts">
            <div>
                <dt>Ngày đăng ký</dt>
                <dd><?= e(formatDate($khach['created_at'], 'd/m/Y H:i')) ?></dd>
            </div>
            <div>
                <dt>Đăng nhập gần nhất</dt>
                <dd><?= $khach['last_login_at'] !== null
                        ? e(formatDate($khach['last_login_at'], 'd/m/Y H:i'))
                        : 'chưa bao giờ' ?></dd>
            </div>
            <div>
                <dt>Cách đăng nhập</dt>
                <dd><?= $khach['google_id'] !== null ? 'Google' : 'Mật khẩu' ?></dd>
            </div>
            <div>
                <dt>Đồng ý điều khoản</dt>
                <dd>
                    <?php /* NULL đọc đúng là "không biết", không phải "chưa đồng
                             ý": tài khoản tạo trước 25/08/2026 không có dữ liệu
                             này và schema.sql cố ý không bịa ra. */ ?>
                    <?= $khach['terms_accepted_at'] !== null
                        ? e(formatDate($khach['terms_accepted_at'], 'd/m/Y'))
                            . ' · bản ' . e((string) $khach['terms_version'])
                        : '<span class="atable__sub">không có dữ liệu (tài khoản cũ)</span>' ?>
                </dd>
            </div>
        </dl>

        <?php if (!$canManage): ?>
            <p class="ahead__note">
                Bạn sửa được hồ sơ nhưng không khoá / xoá / gửi lại mật khẩu được —
                những việc đó cần vai trò <strong>Quản lý</strong> trở lên.
            </p>
        <?php else: ?>

            <div class="acus__acts">

                <?php if ($daXoa): ?>
                    <form method="post" action="/quan-tri/khach-hang/khoi-phuc">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                        <button type="submit" class="astatus__save">Khôi phục tài khoản</button>
                    </form>

                <?php elseif ($daKhoa): ?>
                    <form method="post" action="/quan-tri/khach-hang/mo-khoa"
                          data-confirm="Mở khoá tài khoản của <?= e($ten) ?>? Khách đăng nhập lại được ngay."
                          data-confirm-title="Mở khoá tài khoản?"
                          data-confirm-ok="Mở khoá">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                        <button type="submit" class="astatus__save">Mở khoá tài khoản</button>
                    </form>

                <?php else: ?>
                    <?php /* Ô LÝ DO NẰM NGAY TRONG FORM, không phải trong một hộp
                             thoại bật lên sau khi bấm.

                             CustomerModel::lock() từ chối lý do rỗng, nên nếu
                             giấu ô này đi thì người bấm nút sẽ nhận về một thông
                             báo lỗi cho thao tác mà họ tưởng đã xong. Bày ra
                             trước thì họ biết mình phải viết gì trước khi bấm.

                             Không có data-confirm ở form này: ô lý do đã đủ làm
                             người ta dừng lại nghĩ, thêm một hộp hỏi nữa chỉ dạy
                             người dùng bấm "Đồng ý" theo phản xạ. */ ?>
                    <form class="acus__lock" method="post" action="/quan-tri/khach-hang/khoa">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= e($khach['id']) ?>">

                        <label for="ly-do">Lý do khoá <span class="field__opt">(bắt buộc)</span></label>
                        <input type="text" id="ly-do" name="ly_do" maxlength="255" required
                               placeholder="Ví dụ: đặt hàng ảo nhiều lần, không nhận máy">
                        <p class="field__hint">
                            Khách không đọc được lý do này. Người đọc nó là đồng nghiệp của bạn,
                            ba tháng sau, khi khách gọi điện hỏi vì sao không đăng nhập được.
                        </p>
                        <button type="submit" class="astatus__save">Khoá tài khoản</button>
                    </form>
                <?php endif; ?>

                <?php if (!$daXoa): ?>
                    <div class="acus__act-row">
                        <?php /* GỬI EMAIL, KHÔNG PHẢI ĐẶT MẬT KHẨU.

                                 Nhân viên không được biết mật khẩu của khách, kể
                                 cả một chuỗi tạm do máy sinh — nó vẫn mở được tài
                                 khoản và nó sẽ đi qua một tin nhắn hay một mẩu
                                 giấy. Liên kết gửi thẳng vào hòm thư thì chỉ
                                 người cầm hòm thư dùng được. Lý do đầy đủ ở
                                 PasswordResetModel::issueForUser(). */ ?>
                        <form method="post" action="/quan-tri/khach-hang/dat-lai"
                              data-confirm="Gửi email đặt lại mật khẩu tới <?= e((string) ($khach['email'] ?? '')) ?>?"
                              data-confirm-title="Gửi email đặt lại mật khẩu?"
                              data-confirm-ok="Gửi">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                            <button type="submit" class="astatus__save astatus__save--ghost"
                                <?= (string) ($khach['email'] ?? '') === '' ? 'disabled' : '' ?>>
                                Gửi email đặt lại mật khẩu
                            </button>
                        </form>

                        <form class="acus__del" method="post" action="/quan-tri/khach-hang/xoa"
                              data-confirm="Xoá tài khoản của <?= e($ten) ?>? Khách không đăng nhập được nữa. Đơn hàng cũ vẫn giữ nguyên, và khôi phục lại được ở tab &quot;Đã xoá&quot;."
                              data-confirm-title="Xoá tài khoản khách hàng?"
                              data-confirm-ok="Xoá">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                            <?php /* KHÔNG bắt buộc, khác hẳn ô lý do khoá ngay
                                     trên — lý do đầy đủ ở CustomerModel::softDelete().
                                     Ô ngắn, không nhãn riêng: nó đứng cạnh một
                                     nút đã tự nói mình làm gì. */ ?>
                            <input type="text" name="ly_do_xoa" maxlength="500"
                                   placeholder="Lý do xoá (không bắt buộc)"
                                   aria-label="Lý do xoá tài khoản">
                            <button type="submit" class="arow-del">Xoá tài khoản</button>
                        </form>
                    </div>

                    <?php if ((string) ($khach['email'] ?? '') === ''): ?>
                        <p class="field__hint">
                            Khách chưa có email nên không gửi được liên kết. Đường xử lý qua
                            điện thoại nằm ở <a href="/quan-tri/quen-mat-khau">Quên mật khẩu</a>.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </section>

</div>
