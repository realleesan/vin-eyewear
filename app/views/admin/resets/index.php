<?php

/**
 * admin/resets/index.php — yêu cầu đặt lại mật khẩu.
 *
 * $freshLink chỉ có giá trị trong ĐÚNG lượt tải ngay sau khi bấm tạo — nó đi
 * qua flash. Tải lại trang là mất, đúng như mong muốn: liên kết này là chìa
 * khoá đổi mật khẩu, không nên nằm mãi trên màn hình văn phòng.
 */

$statusLabel = [
    'pending' => 'Chờ xử lý',
    'sent'    => 'Đã tạo liên kết',
    'used'    => 'Đã dùng',
];
?>

<div class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Quên mật khẩu</h1>
        <p class="ahead__lead"><?= count($requests) ?> yêu cầu chưa hoàn tất</p>
    </div>
</div>

<?php if ($error !== null): ?>
    <p class="alert alert--err" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<?php if (!$available): ?>

    <p class="alert alert--err" role="alert">
        Bảng <code>password_resets</code> chưa tồn tại. Chạy file
        <code>database/migrations/2026-08-14-dang-nhap-sdt-ghi-nho-quen-mat-khau.sql</code>
        rồi tải lại trang.
    </p>

<?php else: ?>

    <?php if ($freshLink !== null): ?>
        <div class="resetlink" role="status">
            <p class="resetlink__title">Liên kết đặt lại — chỉ hiện MỘT lần</p>
            <p class="resetlink__note">
                Đọc cho khách qua điện thoại hoặc gửi Zalo. Tải lại trang là không xem
                lại được, khi đó phải tạo liên kết mới. Liên kết sống 60 phút và dùng
                được một lần.
            </p>
            <code class="resetlink__url"><?= e($freshLink) ?></code>
        </div>
    <?php endif; ?>

    <?php
    /*
     * Khách nhập email thì mã OTP đi bằng email, nhập số điện thoại thì đi
     * bằng Zalo. Hai kênh bật/tắt độc lập, nên nói gộp "gửi được / không gửi
     * được" là nói sai một nửa: nhân viên đọc xong tưởng hàng chờ trống là
     * mọi việc êm, trong khi mọi yêu cầu bằng số điện thoại vẫn đang kẹt.
     */
    $kenh = [
        ['Email',           $canDeliver, 'MAIL_DRIVER=' . $mailDriver],
        ['Zalo (số điện thoại)', $canSms, 'ZNS chưa khai đủ — config/zalo.php'],
    ];
    $tatCa = $canDeliver && $canSms;
    ?>

    <?php if ($tatCa): ?>
        <?php /* Mọi kênh đều chạy: một dòng .alert là đủ, vì đây là tin tốt và
                 nó không đòi ai làm gì. */ ?>
        <p class="alert alert--ok">
            Mã xác minh đang gửi tự động qua cả hai kênh. Yêu cầu chỉ rơi về đây
            khi gửi thất bại.
        </p>
    <?php else: ?>
        <?php /* .anote--alert chứ KHÔNG phải .alert — bản thiết kế vẽ chỗ này
                 thành một hộp chữ nhiều dòng, không phải một dải thông báo.
                 Khác nhau ở tuổi thọ: .alert là câu hiện sau một thao tác rồi
                 mất, còn khối này nằm lại mọi lần mở trang cho tới khi ai đó
                 sửa cấu hình — và mỗi lần ấy nhân viên phải đọc lại xem kênh
                 nào đang tắt trước khi gọi cho khách. Xem admin.css. */ ?>
        <div class="anote anote--alert" role="alert">
            <p>
                <strong>Kênh gửi mã chưa đủ</strong> — yêu cầu đi qua kênh đang tắt
                sẽ nằm lại trang này và phải xử lý tay:
            </p>
            <?php foreach ($kenh as [$ten, $bat, $vi]): ?>
                <p>
                    <?= $bat ? '✓' : '✗' ?> <?= e($ten) ?>
                    <?= $bat ? 'gửi được' : '<em>không gửi được</em>' ?>
                    (<code><?= e($vi) ?></code>).
                </p>
            <?php endforeach; ?>
            <p>
                Gọi xác minh đúng người <em>trước khi</em> tạo liên kết —
                đó là bước bảo mật duy nhất của cả luồng.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($requests === []): ?>
        <p class="apanel__empty">Chưa có yêu cầu nào.</p>
    <?php else: ?>

        <div class="atable-wrap">
            <table class="atable atable--full">
                <thead>
                    <tr>
                        <th>Lúc</th>
                        <th>Khách nhập</th>
                        <th>Tài khoản khớp</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><?= e(formatDate($r['created_at'], 'd/m/Y H:i')) ?></td>

                            <td><code><?= e($r['contact']) ?></code></td>

                            <td>
                                <?php if ($r['user_id'] === null): ?>
                                    <!-- Không khớp ai: khách gõ nhầm, hoặc có người đang
                                         dò xem địa chỉ nào có tài khoản. Không tạo được
                                         liên kết cho dòng này. -->
                                    <span class="badge badge--cancelled">Không khớp tài khoản nào</span>
                                <?php else: ?>
                                    <?= e($r['full_name'] ?: '(chưa đặt tên)') ?>
                                    <span class="atable__sub"><?= e($r['email']) ?></span>
                                    <?php if (!empty($r['phone'])): ?>
                                        <span class="atable__line"><?= e($r['phone']) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge badge--<?= e($r['status']) ?>">
                                    <?= e($statusLabel[$r['status']] ?? $r['status']) ?>
                                </span>
                                <?php if ($r['status'] === 'sent' && $r['expires_at'] !== null): ?>
                                    <span class="atable__sub">
                                        hạn <?= e(formatDate($r['expires_at'], 'H:i d/m')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="arow-actions">
                                <?php if ($r['user_id'] !== null && $canIssue): ?>
                                    <form method="post" action="/quan-tri/quen-mat-khau/tao">
                                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                                        <input type="hidden" name="contact" value="<?= e($r['contact']) ?>">
                                        <button type="submit" class="astatus__save">
                                            <?= $r['status'] === 'sent' ? 'Tạo liên kết mới' : 'Tạo liên kết' ?>
                                        </button>
                                    </form>
                                <?php elseif ($r['user_id'] !== null): ?>
                                    <span class="atable__sub">Cần quyền quản lý</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

<?php endif; ?>
