<?php

/**
 * auth/account/dia-chi.php — tab "Sổ địa chỉ" (/tai-khoan?muc=dia-chi)
 *
 * Màn "Addresses" của "Ho So Nguoi Dung.dc.html":
 *
 *                        SỔ ĐỊA CHỈ
 *   ĐỊA CHỈ ĐÃ LƯU                                          2
 *   ┌───────────────────────────────────────────────────────┐
 *   │ Tên người nhận  (MẶC ĐỊNH)            ĐẶT LÀM MẶC ĐỊNH │  viền đen = mặc định
 *   │ số nhà, đường                                     SỬA  │
 *   │ phường, tỉnh                                      XOÁ  │
 *   │ số điện thoại                                          │
 *   └───────────────────────────────────────────────────────┘
 *   [ THÊM ĐỊA CHỈ MỚI ]
 *
 * "SỬA" không có trong bản vẽ — giữ theo chủ dự án, cùng dáng liên kết với hai
 * lối kia. Mọi luật (đúng chủ, đủ trường, ai thay chân địa chỉ mặc định vừa xoá,
 * trần số địa chỉ) nằm trong AddressModel — view chỉ vẽ.
 *
 * TRẠNG THÁI MỞ BẰNG URL: ?them=1 mở form thêm ở cuối sổ, ?sua=<mã> mở form sửa
 * NGAY TẠI CHỖ thẻ đó.
 *
 * MỖI LƯỢT CHỈ MỘT FORM ĐỊA CHỈ: address-picker.js tìm khối [data-vnaddr] bằng
 * querySelector — MỘT khối cho cả trang. Controller lo (?sua= thắng ?them=);
 * đừng bỏ vế `$editing === null` bên dưới.
 */

$moThem   = $themDiaChi && $editing === null;
$soDiaChi = count($addresses);
?>

<h1 class="acct-title">Sổ địa chỉ</h1>

<section class="acct-sec acct-sec--first" id="so-dia-chi" aria-labelledby="dc-tieu-de">
    <div class="acct-head">
        <h2 class="acct-head__label" id="dc-tieu-de">Địa chỉ đã lưu</h2>
        <span class="acct-mute"><?= $soDiaChi ?></span>
    </div>

    <?php if ($addresses === []): ?>
        <p class="acct-text">Bạn chưa lưu địa chỉ nào.</p>
    <?php else: ?>
        <div class="acct-cards">
            <?php foreach ($addresses as $dc): ?>
                <?php if ($editing !== null && $editing['id'] === $dc['id']): ?>
                    <?php
                    /* Form sửa thay CHỖ của thẻ: khách đang nhìn thẻ nào thì form
                       hiện ra ở đó, không mở thêm ở cuối sổ. */
                    partial('auth/account/_dia-chi-form', [
                        'dc'     => $editing,
                        'action' => '/tai-khoan/dia-chi/sua',
                        'tieuDe' => 'Sửa địa chỉ',
                        'nutLuu' => 'Lưu địa chỉ',
                    ]);
                    ?>
                <?php else: ?>
                    <?php
                    $macDinh = (int) $dc['is_default'] === 1;

                    /* Ghép bằng array_filter: địa chỉ nhập từ trước có thể thiếu
                       phường hoặc tỉnh, và "Phường , Hà Nội" trông như dữ liệu hỏng. */
                    $vung = implode(', ', array_filter([
                        (string) ($dc['ward_name'] ?? ''),
                        (string) ($dc['province_name'] ?? ''),
                    ], static fn ($p) => trim($p) !== ''));

                    $hoiXoa = sprintf('Xoá địa chỉ của %s?', $dc['recipient_name']);
                    ?>
                    <article class="acct-card<?= $macDinh ? ' is-default' : '' ?>">
                        <div class="acct-card__body">
                            <div class="acct-card__who">
                                <span class="acct-card__lead"><?= e($dc['recipient_name']) ?></span>
                                <?php if ($macDinh): ?>
                                    <span class="acct-pill">Mặc định</span>
                                <?php endif; ?>
                            </div>
                            <span><?= e($dc['line1']) ?></span>
                            <?php if ($vung !== ''): ?>
                                <span><?= e($vung) ?></span>
                            <?php endif; ?>
                            <span><?= e(groupPhone((string) $dc['phone'])) ?></span>
                            <?php if (!empty($dc['ghi_chu'])): ?>
                                <span class="acct-mute">Ghi chú: <?= e($dc['ghi_chu']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="acct-card__acts">
                            <?php if (!$macDinh): ?>
                                <form method="post" action="/tai-khoan/dia-chi/mac-dinh">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($dc['id']) ?>">
                                    <button type="submit" class="acct-act">Đặt làm mặc định</button>
                                </form>
                            <?php endif; ?>

                            <a class="acct-act" href="/tai-khoan?muc=dia-chi&amp;sua=<?= e(rawurlencode($dc['id'])) ?>#sua-dia-chi">Sửa</a>

                            <?php /* XOÁ QUA POST + HỎI LẠI. GET thì một thẻ <img> trên
                                     trang khác cũng xoá được địa chỉ của khách đang
                                     đăng nhập. onsubmit là lớp dự phòng khi không có JS. */ ?>
                            <form method="post" action="/tai-khoan/dia-chi/xoa"
                                  data-confirm="<?= e($hoiXoa) ?>"
                                  data-confirm-title="Xoá địa chỉ?"
                                  data-confirm-ok="Xoá địa chỉ"
                                  data-confirm-cancel="Giữ lại"
                                  onsubmit="return confirm('<?= e($hoiXoa) ?>')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($dc['id']) ?>">
                                <button type="submit" class="acct-act acct-act--mute">Xoá</button>
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
            'dc'     => null,
            'action' => '/tai-khoan/dia-chi/them',
            'tieuDe' => 'Địa chỉ mới',
            'nutLuu' => 'Lưu địa chỉ',
        ]);
        ?>
    <?php elseif ($editing === null && $soDiaChi < $toiDaDiaChi): ?>
        <a class="acct-btn acct-btn--gap" href="/tai-khoan?muc=dia-chi&amp;them=1#them-dia-chi">Thêm địa chỉ mới</a>
    <?php elseif ($editing === null): ?>
        <?php /* Chạm trần thì NÓI RA thay vì chỉ giấu nút — khách đi tìm nút
                 "Thêm" mà không thấy sẽ tưởng trang hỏng. */ ?>
        <p class="acct-text acct-mute">
            Sổ đã đủ <?= (int) $toiDaDiaChi ?> địa chỉ. Xoá bớt một địa chỉ để thêm địa chỉ mới.
        </p>
    <?php endif; ?>
</section>
