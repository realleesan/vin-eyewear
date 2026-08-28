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

        <?php /* CHỈ XEM, KHÔNG CÓ FORM — và đây là một quyết định, không phải
                 việc chưa làm xong.

                 Trước 2026-08-28 chỗ này là một form sửa đủ năm ô. Bỏ đi vì hai
                 trong năm ô đó (số điện thoại, email) LÀ THỨ KHÁCH DÙNG ĐỂ ĐĂNG
                 NHẬP: nhân viên gõ nhầm một chữ số là khách mất đường vào tài
                 khoản của chính mình, mà người gõ thì không thấy hậu quả gì ngay
                 lúc đó. Khách tự sửa được ở /tai-khoan?muc=ho-so, nơi họ đang
                 cầm sẵn quyền vào hòm thư và số điện thoại ấy.

                 Cần đổi hộ khách thì đường đi là: khách tự sửa, hoặc xoá tài
                 khoản rồi lập lại. Đừng thêm form vào đây mà không đổi cả dòng
                 "AI LÀM ĐƯỢC GÌ" ở đầu CustomerAdminController. */ ?>
        <dl class="acus__facts">
            <div>
                <dt>Họ và tên</dt>
                <dd><?= ($khach['full_name'] ?? '') !== ''
                        ? e((string) $khach['full_name'])
                        : '<span class="atable__sub">chưa có</span>' ?></dd>
            </div>
            <div>
                <dt>Số điện thoại</dt>
                <dd><?= ($khach['phone'] ?? '') !== ''
                        ? e((string) $khach['phone'])
                        : '<span class="atable__sub">chưa có</span>' ?></dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd>
                    <?php if ((string) ($khach['email'] ?? '') === ''): ?>
                        <span class="atable__sub">chưa có — không gửi được liên kết đặt lại mật khẩu</span>
                    <?php else: ?>
                        <?= e((string) $khach['email']) ?>
                        <span class="atable__sub">
                            <?= (int) $khach['email_verified'] === 1 ? 'đã xác minh' : 'chưa xác minh' ?>
                        </span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>Ngày sinh</dt>
                <dd><?= ($khach['date_of_birth'] ?? null) !== null && $khach['date_of_birth'] !== ''
                        ? e(formatDate($khach['date_of_birth']))
                        : '<span class="atable__sub">chưa có</span>' ?></dd>
            </div>
            <div>
                <dt>Giới tính</dt>
                <?php /* Nhãn lấy từ $genders (UserModel::GENDERS) chứ không gõ
                         lại: mã lạ còn sót trong CSDL thì in ra nguyên mã, đọc
                         vẫn hơn một ô trống. */ ?>
                <dd><?= ($khach['gender'] ?? '') !== ''
                        ? e($genders[$khach['gender']] ?? (string) $khach['gender'])
                        : '<span class="atable__sub">chưa chọn</span>' ?></dd>
            </div>
        </dl>

        <p class="field__hint">
            Khu quản trị chỉ XEM phần này. Số điện thoại và email là thứ khách
            dùng để đăng nhập, nên chỉ chính khách sửa được, ở trang
            <a href="/tai-khoan?muc=ho-so" target="_blank" rel="noopener">tài khoản</a>
            của họ.
        </p>
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
                Bạn xem được hồ sơ nhưng không khoá hay xoá tài khoản được —
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
                        <?php /* KHÔNG CÓ NÚT "GỬI EMAIL ĐẶT LẠI MẬT KHẨU" Ở ĐÂY
                                 — bỏ ngày 2026-08-28.

                                 Đường giúp khách lấy lại mật khẩu vẫn còn, nhưng
                                 chỉ còn MỘT đường và nó có bước xác minh:
                                 /quan-tri/quen-mat-khau, nơi nhân viên gọi điện
                                 cho khách rồi mới đọc liên kết. Nút cũ ở đây thì
                                 không xác minh gì cả — mở hồ sơ là bấm được. */ ?>
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

                    <p class="field__hint">
                        Khách quên mật khẩu thì xử lý ở
                        <a href="/quan-tri/quen-mat-khau">Quên mật khẩu</a> — ở đó có
                        bước gọi điện xác minh trước khi cấp liên kết.
                    </p>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </section>

</div>
