<?php

/**
 * auth/account/tong-quan.php — tab "Tài khoản" (/tai-khoan, tab mở sẵn).
 *
 * Màn "Account" của "Ho So Nguoi Dung.dc.html":
 *
 *                     XIN CHÀO, <TÊN>
 *   MUA GẦN ĐÂY
 *   ĐÃ LƯU
 *   LỊCH HẸN CỦA TÔI                              XEM THÊM
 *   HỒ SƠ                                         XEM THÊM
 *     họ tên · email · số điện thoại (chữ lớn)
 *     [ SỬA HỒ SƠ ]
 *
 * Bản vẽ vẽ hai khối đầu ở trạng thái CHƯA CÓ GÌ, và vẽ khối lịch hẹn ở CẢ HAI
 * trạng thái: rỗng thì một câu, có thì ba dòng (dịch vụ 15px · ngày · cơ sở chữ
 * xám). Hai khối đầu khi có dữ liệu mượn đúng khuôn ba dòng ấy kèm "XEM THÊM"
 * như hai khối dưới — không thêm hình khối nào bản vẽ không có.
 *
 * Nhận qua AuthController::sectionData():
 *   $donMoi   đơn mới nhất hoặc null · $donMoiHang dòng hàng của nó
 *   $luuMoi   mẫu vừa lưu hoặc null · $soLuu · $luuDuoc
 *   $henToi   lịch hẹn sắp tới gần nhất hoặc null
 */

/* Lời chào dùng TÊN (bản vẽ: "HELLO, DUY ANH"), tách từ cột họ tên duy nhất —
   xem UserModel::tachHoTen(). */
$tenGoi = UserModel::tachHoTen($profile['full_name'] ?? '')['ten'];
?>

<h1 class="acct-title">Xin chào, <?= e($tenGoi !== '' ? $tenGoi : 'bạn') ?></h1>

<section class="acct-sec acct-sec--first" aria-labelledby="tq-don">
    <div class="acct-head">
        <h2 class="acct-head__label" id="tq-don">Mua gần đây</h2>
        <?php if ($donMoi !== null): ?>
            <a class="acct-head__more" href="/tai-khoan?muc=don-hang">Xem thêm</a>
        <?php endif; ?>
    </div>

    <?php if ($donMoi === null): ?>
        <p class="acct-blank">Bạn chưa có lịch sử mua hàng.</p>
    <?php else: ?>
        <?php
        $dau  = $donMoiHang[0] ?? null;
        $them = max(0, count($donMoiHang) - 1);
        ?>
        <a class="acct-brief" href="/tai-khoan?muc=don-hang&amp;don=<?= e(rawurlencode($donMoi['code'])) ?>#<?= e($donMoi['code']) ?>">
            <span class="acct-brief__lead notranslate" translate="no"><?= e($dau['product_name'] ?? 'Sản phẩm đã gỡ khỏi cửa hàng') ?><?= $them > 0 ? ' +' . $them : '' ?></span>
            <span><?= e($donMoi['code']) ?> · Đặt ngày <?= e(formatDate($donMoi['created_at'])) ?></span>
            <span class="acct-mute"><?= e(OrderModel::nhanTrangThai($donMoi['status'], $donMoi['delivery_method'] ?? null)) ?> · <?= money((int) $donMoi['total']) ?></span>
        </a>
    <?php endif; ?>
</section>

<section class="acct-sec acct-sec--wide" aria-labelledby="tq-luu">
    <div class="acct-head">
        <h2 class="acct-head__label" id="tq-luu">Đã lưu</h2>
        <?php if ($luuMoi !== null): ?>
            <a class="acct-head__more" href="/tai-khoan?muc=da-luu">Xem thêm</a>
        <?php endif; ?>
    </div>

    <?php if (!$luuDuoc): ?>
        <?php /* Bảng chưa dựng: nói thẳng là tạm ngưng — câu "chưa có gì" ở đây
                 khiến khách đã lưu tưởng mất dữ liệu. */ ?>
        <p class="acct-blank">Danh sách đã lưu đang tạm ngưng — không mục nào của bạn bị mất.</p>
    <?php elseif ($luuMoi === null): ?>
        <p class="acct-blank">Bạn chưa có sản phẩm nào trong danh sách đã lưu.</p>
    <?php else: ?>
        <a class="acct-brief" href="/san-pham/<?= e(rawurlencode($luuMoi['slug'])) ?>">
            <span class="acct-brief__lead notranslate" translate="no" lang="vi"><?= e($luuMoi['name']) ?></span>
            <span><?= money(ProductPricing::giaBan($luuMoi)) ?></span>
            <?php if ($soLuu > 1): ?>
                <span class="acct-mute">và <?= $soLuu - 1 ?> sản phẩm khác</span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
</section>

<section class="acct-sec acct-sec--wide" aria-labelledby="tq-hen">
    <div class="acct-head">
        <h2 class="acct-head__label" id="tq-hen">Lịch hẹn của tôi</h2>
        <a class="acct-head__more" href="/tai-khoan?muc=lich-hen">Xem thêm</a>
    </div>

    <?php if ($henToi === null): ?>
        <p class="acct-blank">Bạn chưa có lịch hẹn sắp tới.</p>
    <?php else: ?>
        <div class="acct-brief">
            <span class="acct-brief__lead"><?= e($henToi['service_type']) ?></span>
            <span><?= e(BookingModel::nhanNgay($henToi['appointment_date'])) ?></span>
            <span class="acct-mute"><?= e($henToi['store_name'] ?? 'Cơ sở Vin Eyewear') ?></span>
        </div>
    <?php endif; ?>
</section>

<section class="acct-sec acct-sec--wide" aria-labelledby="tq-ho-so">
    <div class="acct-head">
        <h2 class="acct-head__label" id="tq-ho-so">Hồ sơ</h2>
        <a class="acct-head__more" href="/tai-khoan?muc=ho-so">Xem thêm</a>
    </div>

    <?php partial('auth/account/_toi', ['profile' => $profile]); ?>

    <a class="acct-btn acct-btn--gap" href="/tai-khoan?muc=ho-so&amp;sua-ho-so=1">Sửa hồ sơ</a>
</section>
