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
            <table class="atable aqtable">
                <thead>
                    <tr>
                        <th scope="col">Lúc</th>
                        <th scope="col">Khách nhập</th>
                        <th scope="col">Tài khoản khớp</th>
                        <th scope="col">Trạng thái</th>
                        <?php /* Cột cuối CÓ TÊN ("Xử lý"), không để trống như trước —
                                 theo bản thiết kế. Một cột không tên thì trình đọc màn
                                 hình đọc lên là khoảng lặng, và người mới nhận bàn giao
                                 không biết mấy cái nút ấy thuộc về việc gì. */ ?>
                        <th scope="col">Xử lý</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td class="aqwhen"><?= e(formatDate($r['created_at'], 'd/m/Y H:i')) ?></td>

                            <td><code><?= e($r['contact']) ?></code></td>

                            <td>
                                <?php if ($r['user_id'] === null): ?>
                                    <!-- Không khớp ai: khách gõ nhầm, hoặc có người đang
                                         dò xem địa chỉ nào có tài khoản. Không tạo được
                                         liên kết cho dòng này. -->
                                    <span class="badge badge--cancelled">Không khớp tài khoản nào</span>
                                <?php else: ?>
                                    <span class="aqname"><?= e($r['full_name'] ?: '(chưa đặt tên)') ?></span>
                                    <span class="atable__sub"><?= e($r['email']) ?></span>
                                    <?php /* Số điện thoại BẤM GỌI ĐƯỢC — theo bản thiết kế.
                                             Gọi xác minh đúng người là bước bảo mật DUY NHẤT
                                             của cả luồng này (xem dải cảnh báo trên đầu
                                             trang), nên nó phải là một cú bấm chứ không
                                             phải một dãy số phải chép tay sang máy. */ ?>
                                    <?php if (!empty($r['phone'])): ?>
                                        <span class="atable__line">
                                            <a href="tel:<?= e(preg_replace('/\D/', '', $r['phone'])) ?>"><?= e($r['phone']) ?></a>
                                        </span>
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
                                    <?php /* MỞ HỘP THOẠI, không gửi thẳng — theo bản thiết
                                             kế. Cấp liên kết là thao tác nguy hiểm nhất
                                             của cả khu quản trị: ai cầm được nó là đổi
                                             được mật khẩu của khách. Hộp bắt tick "đã gọi
                                             xác minh", và controller kiểm lại ô tick ấy ở
                                             máy chủ.

                                             .aqgo chứ không .astatus__save: nút chính cao
                                             38px và có quầng bóng, quá nặng cho một ô
                                             bảng — bản thiết kế cho nó nhỏ hơn một nấc. */ ?>
                                    <a class="aqgo" href="/quan-tri/quen-mat-khau?tao=<?= e($r['id']) ?>">
                                        <?= $r['status'] === 'sent' ? 'Tạo liên kết mới' : 'Tạo liên kết' ?>
                                    </a>
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

<?php
/*
 * HỘP THOẠI CẤP LIÊN KẾT — theo bản thiết kế "Quên mật khẩu.dc.html".
 *
 * Đây là thao tác nguy hiểm nhất của cả khu quản trị: liên kết nó sinh ra đổi
 * được mật khẩu của khách, mà yêu cầu đặt lại thì bất kỳ ai gõ đúng email của
 * người khác cũng tạo ra được. Bước gọi điện hỏi thông tin chỉ chủ tài khoản
 * biết là chốt chặn DUY NHẤT của cả luồng.
 *
 * Nên hộp này không chỉ hỏi "có chắc không" — nó bày ra SỐ ĐIỆN THOẠI để gọi
 * ngay tại chỗ, và bắt tick một câu khẳng định đã gọi. Ô tick được kiểm LẠI ở
 * PasswordResetAdminController::issue(), không chỉ ở đây: ai gửi thẳng POST
 * vẫn bỏ qua được ô tick, mà ẩn nút trên giao diện thì không phải là chốt
 * chặn (CLAUDE.md mục 4).
 */
$dangTao = null;

if (isset($_GET['tao']) && $available && $canIssue) {
    foreach ($requests as $r) {
        if ((string) $r['id'] === (string) $_GET['tao'] && $r['user_id'] !== null) {
            $dangTao = $r;
            break;
        }
    }
}
?>
<?php if ($dangTao !== null): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => 'Tạo liên kết đặt lại mật khẩu',
        'phu'     => trim(($dangTao['full_name'] ?: '(chưa đặt tên)') . ' · ' . ($dangTao['email'] ?? '')),
        'dongUrl' => '/quan-tri/quen-mat-khau',
        'rong'    => 'sm',
    ]); ?>

        <div class="anote anote--alert" role="alert">
            <p>
                <strong>Đã gọi xác minh chưa?</strong>
                <?php if (!empty($dangTao['phone'])): ?>
                    Gọi <a href="tel:<?= e(preg_replace('/\D/', '', $dangTao['phone'])) ?>"><?= e($dangTao['phone']) ?></a>
                <?php else: ?>
                    Tài khoản này chưa có số điện thoại — liên hệ qua email
                <?php endif; ?>
                và hỏi thông tin chỉ chủ tài khoản biết (đơn gần nhất, địa chỉ giao)
                trước khi tạo. Ai cầm được liên kết này là đổi được mật khẩu.
            </p>
        </div>

        <form method="post" action="/quan-tri/quen-mat-khau/tao" class="aform__grid" id="reset-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($dangTao['id']) ?>">
            <input type="hidden" name="contact" value="<?= e($dangTao['contact']) ?>">

            <div class="field field--check field--wide">
                <label>
                    <?php /* required: trình duyệt chặn ngay tại chỗ, khỏi phải gửi
                             đi rồi mới biết. Máy chủ vẫn kiểm lại — xem issue(). */ ?>
                    <input type="checkbox" name="da_xac_minh" value="1" required>
                    Tôi đã gọi và xác minh đúng chủ tài khoản
                </label>
            </div>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => '/quan-tri/quen-mat-khau',
        'luuNhan' => 'Tạo liên kết',
        'luuForm' => 'reset-form',
        'ghiChu'  => 'Liên kết sống 60 phút và chỉ dùng được một lần.',
    ]); ?>
<?php endif; ?>
