<?php

/**
 * auth/account/tong-quan.php — tab "Tài khoản" (/tai-khoan, tab mở sẵn).
 *
 * Màn "Account" của "Ho So Nguoi Dung.dc.html":
 *
 *                     XIN CHÀO, <TÊN>
 *   MUA GẦN ĐÂY                                  XEM THÊM
 *   ĐÃ LƯU                                       XEM THÊM
 *   HỒ SƠ                                        XEM THÊM
 *     họ tên · email · số điện thoại (chữ lớn)
 *     [ SỬA HỒ SƠ ]
 *
 * Bản thiết kế chỉ vẽ trạng thái chưa có gì ("You have no purchase history").
 * Khi CÓ đơn và có mẫu đã lưu thì hai khối đầu liệt kê gọn — ba đơn mới nhất,
 * bốn mẫu vừa lưu — và "Xem thêm" dẫn sang tab đầy đủ.
 *
 * Nhận qua AuthController::sectionData():
 *   $recent       ≤ 3 đơn mới nhất
 *   $recentItems  dòng hàng của các đơn đó, theo order id
 *   $saved        ≤ 4 mặt hàng đã lưu
 *   $luuDuoc      bảng `favorites` đã có chưa
 */

/* TÊN GỌI = CHỮ CUỐI của họ tên. Tên Việt đặt tên riêng ở cuối ("Phạm Duy Anh"
   -> "Anh"); hệ thống chỉ lưu một ô họ tên nên đây là cách duy nhất ra được lời
   chào như bản thiết kế mà không bịa thêm cột. */
$hoTen  = trim((string) ($profile['full_name'] ?? ''));
$phan   = $hoTen === '' ? [] : preg_split('/\s+/u', $hoTen);
$tenGoi = $phan === [] ? 'bạn' : (string) end($phan);
?>

<h1 class="acct-title">Xin chào, <?= e($tenGoi) ?></h1>

<section class="acct-sec acct-sec--first" aria-labelledby="tq-don">
    <div class="acct-sec__head">
        <h2 class="acct-label" id="tq-don">Mua gần đây</h2>
        <?php if ($recent !== []): ?>
            <a class="acct-label" href="/tai-khoan?muc=don-hang">Xem thêm</a>
        <?php endif; ?>
    </div>

    <?php if ($recent === []): ?>
        <p class="acct-sec__empty">Bạn chưa có lịch sử mua hàng.</p>
    <?php else: ?>
        <ul class="acct-rows" role="list">
            <?php foreach ($recent as $o): ?>
                <?php
                $dong = $recentItems[$o['id']] ?? [];
                $dau  = $dong[0] ?? null;
                $anhs = $dau && $dau['images'] ? json_decode($dau['images'], true) : null;
                $anh  = is_array($anhs) ? ($anhs[0] ?? null) : null;
                $them = max(0, count($dong) - 1);
                ?>
                <li>
                    <a class="acct-row" href="/tai-khoan?muc=don-hang&amp;don=<?= e(rawurlencode($o['code'])) ?>#<?= e($o['code']) ?>">
                        <span class="acct-row__pic" aria-hidden="true">
                            <?php if ($anh !== null): ?>
                                <img src="<?= e(asset($anh)) ?>" alt="" width="64" height="64" loading="lazy">
                            <?php endif; ?>
                        </span>
                        <span class="acct-row__body">
                            <span class="acct-row__name notranslate" translate="no">
                                <?= e($dau['product_name'] ?? 'Sản phẩm đã gỡ khỏi cửa hàng') ?><?= $them > 0 ? ' +' . $them : '' ?>
                            </span>
                            <span class="acct-row__meta">
                                <?= e($o['code']) ?> · <?= e(formatDate($o['created_at'])) ?> ·
                                <?= e(OrderModel::nhanTrangThai($o['status'], $o['delivery_method'] ?? null)) ?>
                            </span>
                        </span>
                        <span class="acct-row__num"><?= money((int) $o['total']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="acct-sec" aria-labelledby="tq-luu">
    <div class="acct-sec__head">
        <h2 class="acct-label" id="tq-luu">Đã lưu</h2>
        <?php if ($saved !== []): ?>
            <a class="acct-label" href="/tai-khoan?muc=da-luu">Xem thêm</a>
        <?php endif; ?>
    </div>

    <?php if (!$luuDuoc): ?>
        <p class="acct-sec__empty">Danh sách đã lưu đang tạm ngưng — không mục nào của bạn bị mất.</p>
    <?php elseif ($saved === []): ?>
        <p class="acct-sec__empty">Bạn chưa lưu sản phẩm nào.</p>
    <?php else: ?>
        <ul class="acct-mini" role="list">
            <?php foreach ($saved as $p): ?>
                <li>
                    <a class="acct-mini__item" href="/san-pham/<?= e(rawurlencode($p['slug'])) ?>">
                        <span class="acct-mini__shot" aria-hidden="true">
                            <?php if (ProductModel::hasImage($p)): ?>
                                <img src="<?= e(asset(ProductModel::image($p))) ?>" alt=""
                                     width="300" height="300" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </span>
                        <span class="acct-mini__name notranslate" translate="no" lang="vi"><?= e($p['name']) ?></span>
                        <span class="acct-mini__price"><?= money(ProductPricing::giaBan($p)) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="acct-sec" aria-labelledby="tq-ho-so">
    <div class="acct-sec__head">
        <h2 class="acct-label" id="tq-ho-so">Hồ sơ</h2>
        <a class="acct-label" href="/tai-khoan?muc=ho-so">Xem thêm</a>
    </div>

    <?php partial('auth/account/_toi', ['profile' => $profile]); ?>

    <a class="acct-btn acct-sec__btn" href="/tai-khoan?muc=ho-so&amp;sua-ho-so=1">Sửa hồ sơ</a>
</section>
