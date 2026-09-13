<?php

/**
 * auth/account/dia-chi.php — tab "Sổ địa chỉ" (/tai-khoan?muc=dia-chi)
 *
 * Màn "Addresses" của "Ho So Nguoi Dung.dc.html":
 *
 *                        SỔ ĐỊA CHỈ
 *   ĐỊA CHỈ ĐÃ LƯU                                      2
 *   ┌───────────────────────────────────────────────────────┐
 *   │ Tên người nhận  (MẶC ĐỊNH)            ĐẶT LÀM MẶC ĐỊNH │  viền đen = mặc định
 *   │ số nhà, đường                                    SỬA   │
 *   │ phường, tỉnh                                     XOÁ   │
 *   │ số điện thoại                                          │
 *   └───────────────────────────────────────────────────────┘
 *   [ THÊM ĐỊA CHỈ MỚI ]
 *
 * Tách khỏi trang Hồ sơ ngày 13/09/2026 theo bản thiết kế. Mọi luật (đúng chủ,
 * đủ trường, ai thay chân địa chỉ mặc định vừa xoá, trần số địa chỉ) nằm trong
 * AddressModel — view chỉ vẽ.
 *
 * TRẠNG THÁI MỞ BẰNG URL: ?them=1 mở form thêm ở cuối sổ, ?sua=<mã> mở form sửa
 * NGAY TẠI CHỖ thẻ đó.
 *
 * MỖI LƯỢT CHỈ MỘT FORM ĐỊA CHỈ: address-picker.js tìm khối [data-vnaddr] bằng
 * querySelector — MỘT khối cho cả trang. Controller lo (?sua= thắng ?them=);
 * đừng bỏ vế `$editing === null` bên dưới.
 */

$moThem = $themDiaChi && $editing === null;
$soDiaChi = count($addresses);
?>

<h1 class="acct-title">Sổ địa chỉ</h1>

<section class="acct-sec acct-sec--first" id="so-dia-chi" aria-labelledby="dc-tieu-de">
    <div class="acct-sec__head">
        <h2 class="acct-label" id="dc-tieu-de">Địa chỉ đã lưu</h2>
        <span class="acct-label acct-muted"><?= $soDiaChi ?></span>
    </div>

    <?php if ($addresses === [] && !$moThem): ?>
        <p class="acct-sec__text">Bạn chưa lưu địa chỉ nào.</p>
    <?php endif; ?>

    <?php if ($addresses !== []): ?>
        <div class="acct-addrs">
            <?php foreach ($addresses as $dc): ?>
                <?php if ($editing !== null && $editing['id'] === $dc['id']): ?>
                    <?php
                    /* Form sửa thay CHỖ của thẻ: khách đang nhìn thẻ nào thì form
                       hiện ra ở đó, không mở thêm ở cuối sổ. */
                    partial('auth/account/_dia-chi-form', [
                        'dc'      => $editing,
                        'nhanCua' => $nhanDiaChi,
                        'action'  => '/tai-khoan/dia-chi/sua',
                        'tieuDe'  => 'Sửa địa chỉ',
                        'nutLuu'  => 'Lưu địa chỉ',
                    ]);
                    ?>
                <?php else: ?>
                    <?php $macDinh = (int) $dc['is_default'] === 1; ?>
                    <article class="acct-addr<?= $macDinh ? ' is-default' : '' ?>">
                        <div class="acct-addr__body">
                            <div class="acct-addr__who">
                                <span class="acct-addr__name"><?= e($dc['recipient_name']) ?></span>
                                <?php if ($macDinh): ?>
                                    <span class="acct-pill">Mặc định</span>
                                <?php endif; ?>
                                <?php if (!empty($dc['nhan']) && isset($nhanDiaChi[$dc['nhan']])): ?>
                                    <span class="acct-pill acct-pill--quiet"><?= e($nhanDiaChi[$dc['nhan']]) ?></span>
                                <?php endif; ?>
                            </div>

                            <span><?= e($dc['line1']) ?></span>
                            <?php
                            /* Ghép bằng array_filter: địa chỉ nhập từ trước có thể
                               thiếu phường hoặc tỉnh, và "Phường , Hà Nội" trông
                               như dữ liệu hỏng. */
                            $vung = implode(', ', array_filter([
                                (string) ($dc['ward_name'] ?? ''),
                                (string) ($dc['province_name'] ?? ''),
                            ], static fn ($p) => trim($p) !== ''));
                            ?>
                            <?php if ($vung !== ''): ?>
                                <span><?= e($vung) ?></span>
                            <?php endif; ?>
                            <span><?= e(groupPhone((string) $dc['phone'])) ?></span>

                            <?php if (!empty($dc['ghi_chu'])): ?>
                                <span class="acct-muted">Ghi chú: <?= e($dc['ghi_chu']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="acct-addr__acts">
                            <?php if (!$macDinh): ?>
                                <form method="post" action="/tai-khoan/dia-chi/mac-dinh">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($dc['id']) ?>">
                                    <button type="submit" class="acct-addr__act">Đặt làm mặc định</button>
                                </form>
                            <?php endif; ?>

                            <a class="acct-addr__act" href="/tai-khoan?muc=dia-chi&amp;sua=<?= e(rawurlencode($dc['id'])) ?>#sua-dia-chi">Sửa</a>

                            <?php
                            /* XOÁ QUA POST + HỎI LẠI. GET thì một thẻ <img> trên
                               trang khác cũng xoá được địa chỉ của khách đang đăng
                               nhập. onsubmit là lớp dự phòng khi không có JS. */
                            $hoiXoa = sprintf('Xoá địa chỉ của %s?', $dc['recipient_name']);
                            ?>
                            <form method="post" action="/tai-khoan/dia-chi/xoa"
                                  data-confirm="<?= e($hoiXoa) ?>"
                                  data-confirm-title="Xoá địa chỉ?"
                                  data-confirm-ok="Xoá địa chỉ"
                                  data-confirm-cancel="Giữ lại"
                                  onsubmit="return confirm('<?= e($hoiXoa) ?>')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($dc['id']) ?>">
                                <button type="submit" class="acct-addr__act acct-addr__act--quiet">Xoá</button>
                            </form>
                        </div>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($moThem): ?>
        <?php
        partial('auth/account/_dia-chi-form', [
            'dc'      => null,
            'nhanCua' => $nhanDiaChi,
            'action'  => '/tai-khoan/dia-chi/them',
            'tieuDe'  => 'Địa chỉ mới',
            'nutLuu'  => 'Lưu địa chỉ',
        ]);
        ?>
    <?php elseif ($editing === null && $soDiaChi < $toiDaDiaChi): ?>
        <a class="acct-btn acct-sec__btn" href="/tai-khoan?muc=dia-chi&amp;them=1#them-dia-chi">Thêm địa chỉ mới</a>
    <?php elseif ($editing === null): ?>
        <?php /* Chạm trần thì NÓI RA thay vì chỉ giấu nút — khách đi tìm nút
                 "Thêm" mà không thấy sẽ tưởng trang hỏng. */ ?>
        <p class="acct-sec__text acct-muted">
            Sổ đã đủ <?= (int) $toiDaDiaChi ?> địa chỉ. Xoá bớt một địa chỉ để thêm địa chỉ mới.
        </p>
    <?php endif; ?>
</section>
