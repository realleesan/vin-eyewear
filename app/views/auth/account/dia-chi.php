<?php

/**
 * auth/account/dia-chi.php — mục "Sổ địa chỉ" (/tai-khoan?muc=dia-chi).
 *
 * Bản thiết kế vẽ danh sách thẻ địa chỉ + nút "+ Thêm địa chỉ mới", nhưng
 * KHÔNG vẽ form thêm/sửa. Form dưới đây dựng bằng đúng những nguyên thể của
 * mục Hồ sơ (.acct-field / .acct-btn) nên nó nằm trong cùng một ngôn ngữ hình
 * ảnh, và hiện ngay trên danh sách khi ?them=1 hoặc ?sua=<id>.
 */

/* $editing là dòng địa chỉ đang sửa (đã kiểm chủ sở hữu ở controller),
   null khi đang thêm mới. $old là dữ liệu vừa gõ hỏng, ưu tiên hơn cả hai. */
$form = $old ?: ($editing ?? []);
$open = $adding || $editing !== null;
?>

<div class="acct-head acct-head--row">
    <div>
        <h1 class="acct-head__title">Sổ địa chỉ</h1>
        <p class="acct-head__lead">Địa chỉ nhận hàng của bạn.</p>
    </div>
    <?php if (!$open): ?>
        <a class="acct-btn acct-btn--primary" href="/tai-khoan?muc=dia-chi&amp;them=1">+ Thêm địa chỉ mới</a>
    <?php endif; ?>
</div>

<?php if ($open): ?>
    <form class="acct-card acct-form" method="post" action="/tai-khoan/dia-chi/luu">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <!-- Ô rỗng = thêm mới. Cùng một action lo cả hai việc — xem
             AuthController::saveAddress. -->
        <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">

        <h2 class="acct-form__title"><?= $editing !== null ? 'Sửa địa chỉ' : 'Thêm địa chỉ mới' ?></h2>

        <div class="acct-form__row">
            <label class="acct-field">
                <span class="acct-field__label">Tên người nhận</span>
                <input class="acct-field__input" type="text" name="recipient_name" required
                       maxlength="255" autocomplete="name"
                       value="<?= e($form['recipient_name'] ?? '') ?>">
            </label>

            <label class="acct-field">
                <span class="acct-field__label">Số điện thoại</span>
                <input class="acct-field__input" type="tel" name="phone" required
                       autocomplete="tel"
                       value="<?= e($form['phone'] ?? '') ?>">
            </label>
        </div>

        <label class="acct-field">
            <span class="acct-field__label">Địa chỉ</span>
            <input class="acct-field__input" type="text" name="line1" required
                   maxlength="255" autocomplete="address-line1"
                   placeholder="Số nhà, ngách, ngõ, đường"
                   value="<?= e($form['line1'] ?? '') ?>">
        </label>

        <label class="acct-field">
            <span class="acct-field__label">Phường / xã, tỉnh / thành phố</span>
            <input class="acct-field__input" type="text" name="line2"
                   maxlength="255" autocomplete="address-level2"
                   placeholder="Phường Tây Hồ, Thành phố Hà Nội"
                   value="<?= e($form['line2'] ?? '') ?>">
        </label>

        <?php
        /* Địa chỉ mặc định đang sửa thì không cho bỏ tick: bỏ đi nghĩa là
           khách không còn địa chỉ mặc định nào, mà không có chỗ nào chọn lại
           trong chính form này. Muốn đổi thì bấm "Đặt làm mặc định" ở thẻ khác. */
        $lockedDefault = ($editing['is_default'] ?? 0) == 1;
        ?>
        <label class="acct-check">
            <input type="checkbox" name="is_default" value="1"
                   <?= $lockedDefault || !empty($form['is_default']) ? 'checked' : '' ?>
                   <?= $lockedDefault ? 'disabled' : '' ?>>
            <span>Đặt làm địa chỉ mặc định</span>
        </label>
        <?php if ($lockedDefault): ?>
            <input type="hidden" name="is_default" value="1">
        <?php endif; ?>

        <div class="acct-form__actions">
            <button type="submit" class="acct-btn acct-btn--primary">
                <?= $editing !== null ? 'Lưu thay đổi' : 'Thêm địa chỉ' ?>
            </button>
            <a class="acct-btn acct-btn--outline" href="/tai-khoan?muc=dia-chi">Huỷ</a>
        </div>
    </form>
<?php endif; ?>

<?php if ($addresses === []): ?>
    <div class="acct-empty">
        <span class="acct-empty__ring" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#b0736a"
                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 21s-6.5-5.6-6.5-10a6.5 6.5 0 1 1 13 0c0 4.4-6.5 10-6.5 10z"></path>
                <circle cx="12" cy="11" r="2.4"></circle>
            </svg>
        </span>
        <span class="acct-empty__title">Chưa có địa chỉ nào</span>
        <span class="acct-empty__lead">Thêm địa chỉ để đặt hàng nhanh hơn ở lần sau.</span>
        <a class="acct-empty__cta" href="/tai-khoan?muc=dia-chi&amp;them=1">Thêm địa chỉ mới</a>
    </div>
<?php else: ?>
    <div class="acct-list">
        <?php foreach ($addresses as $ad): ?>
            <?php $isDefault = (int) $ad['is_default'] === 1; ?>
            <div class="acct-card acct-addr">
                <div class="acct-addr__body">
                    <div class="acct-addr__who">
                        <span class="acct-addr__name"><?= e($ad['recipient_name']) ?></span>
                        <span class="acct-addr__bar" aria-hidden="true"></span>
                        <span class="acct-addr__phone"><?= e($ad['phone']) ?></span>
                        <?php if ($isDefault): ?>
                            <span class="acct-tag">Mặc định</span>
                        <?php endif; ?>
                    </div>
                    <span class="acct-addr__lines">
                        <?= e($ad['line1']) ?>
                        <?php if (!empty($ad['line2'])): ?><br><?= e($ad['line2']) ?><?php endif; ?>
                    </span>
                </div>

                <div class="acct-addr__side">
                    <div class="acct-addr__links">
                        <a href="/tai-khoan?muc=dia-chi&amp;sua=<?= e(rawurlencode($ad['id'])) ?>">Sửa</a>

                        <?php if (!$isDefault || count($addresses) === 1): ?>
                            <!-- Xoá là POST, không phải link: xem ghi chú ở
                                 config/routes.php. Bản thiết kế vẽ nó như một
                                 liên kết nên nút này mang đúng kiểu đó. -->
                            <form method="post" action="/tai-khoan/dia-chi/xoa"
                                  onsubmit="return confirm('Xoá địa chỉ này?')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($ad['id']) ?>">
                                <button type="submit" class="acct-addr__del">Xoá</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isDefault): ?>
                        <form method="post" action="/tai-khoan/dia-chi/mac-dinh">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= e($ad['id']) ?>">
                            <button type="submit" class="acct-btn acct-btn--outline acct-btn--sm">
                                Đặt làm mặc định
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
