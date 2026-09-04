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

        <?php
        /*
         * TỈNH/THÀNH VÀ PHƯỜNG/XÃ — HAI Ô CHỮ, ĐƯỢC JAVASCRIPT NÂNG THÀNH DANH SÁCH
         *
         * Máy chủ in ra ô gõ tay; account.js đổi chúng thành <select> đổ dữ liệu
         * từ provinces.open-api.vn. Làm ngược lại (in sẵn <select> rỗng rồi chờ
         * JS đổ vào) thì khách tắt JavaScript — hoặc gặp lúc API chết — nhìn
         * thấy một ô chọn không có mục nào và không lưu nổi địa chỉ.
         *
         * Hai ô ẩn giữ MÃ hành chính. Chỉ JavaScript điền; không có nó thì địa
         * chỉ vẫn lưu được, chỉ là lần sau mở form sửa danh sách phải dò theo
         * tên thay vì chọn đúng mã.
         *
         * KHÔNG CÓ Ô QUẬN/HUYỆN: từ 01/07/2025 Việt Nam bỏ cấp huyện, địa chỉ
         * còn hai cấp tỉnh/thành -> phường/xã.
         */
        ?>
        <div class="acct-form__row" data-vnaddr>
            <label class="acct-field">
                <span class="acct-field__label">Tỉnh / Thành phố</span>
                <input class="acct-field__input" type="text" name="province_name" required
                       maxlength="120" autocomplete="address-level1"
                       placeholder="Thành phố Hà Nội"
                       data-vnaddr-field="province"
                       value="<?= e($form['province_name'] ?? '') ?>">
            </label>

            <label class="acct-field">
                <span class="acct-field__label">Phường / Xã</span>
                <input class="acct-field__input" type="text" name="ward_name" required
                       maxlength="120" autocomplete="address-level2"
                       placeholder="Phường Tây Hồ"
                       data-vnaddr-field="ward"
                       value="<?= e($form['ward_name'] ?? '') ?>">
            </label>

            <input type="hidden" name="province_code" data-vnaddr-code="province"
                   value="<?= e((string) ($form['province_code'] ?? '')) ?>">
            <input type="hidden" name="ward_code" data-vnaddr-code="ward"
                   value="<?= e((string) ($form['ward_code'] ?? '')) ?>">
        </div>

        <?php
        /* ─────────────────────────────────────────────────────────────────────
           GHI CHÚ GIAO HÀNG VÀ NHÃN — Q75.1, chốt 04/09/2026

           Ghi chú TÁCH khỏi ô "Địa chỉ" phía trên là cả điểm của việc này.
           Không có ô riêng thì khách gõ "gọi trước khi tới nhé" vào cuối ô địa
           chỉ, và đúng dòng ấy là dòng được in lên phiếu gửi hàng — người giao
           nhận một địa chỉ lẫn với lời dặn.

           Nhãn không bắt buộc: phần lớn địa chỉ là nhà riêng, và bắt chọn một
           thứ hiển nhiên ở mọi lần thêm là một bước thừa. Bỏ trống thì thẻ địa
           chỉ đơn giản không in nhãn nào.

           Cả khối CHỈ HIỆN khi CSDL đã có hai cột — chưa chạy migration mà vẫn
           vẽ ô thì khách gõ vào rồi bấm Lưu và không hiểu vì sao chữ biến mất.
           ───────────────────────────────────────────────────────────────────── */
        ?>
        <?php if (AddressModel::coTruongQ751()): ?>
            <div class="acct-form__row">
                <label class="acct-field">
                    <span class="acct-field__label">Ghi chú giao hàng</span>
                    <input class="acct-field__input" type="text" name="ghi_chu"
                           maxlength="255"
                           placeholder="Ví dụ: gọi trước 15 phút, gửi bảo vệ toà nhà"
                           value="<?= e((string) ($form['ghi_chu'] ?? '')) ?>">
                </label>

                <label class="acct-field">
                    <span class="acct-field__label">Loại địa chỉ</span>
                    <select class="acct-field__input" name="nhan">
                        <option value="">— Không ghi —</option>
                        <?php foreach (AddressModel::NHAN as $ma => $nhan): ?>
                            <option value="<?= e($ma) ?>"
                                <?= (string) ($form['nhan'] ?? '') === $ma ? 'selected' : '' ?>>
                                <?= e($nhan) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        <?php endif; ?>

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
                        <?php /* Nhãn Q75.1 — viên nhạt hơn viên "Mặc định":
                                 mặc định là thứ quyết định form thanh toán
                                 điền gì, nhãn chỉ là chú thích. Hai viên cùng
                                 đậm thì cái quan trọng không nổi lên nữa. */ ?>
                        <?php $tenNhan = AddressModel::tenNhan($ad); ?>
                        <?php if ($tenNhan !== ''): ?>
                            <span class="acct-tag acct-tag--soft"><?= e($tenNhan) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="acct-addr__lines">
                        <?= e($ad['line1']) ?>
                        <?php $area = AddressModel::areaText($ad); ?>
                        <?php if ($area !== ''): ?><br><?= e($area) ?><?php endif; ?>
                        <?php /* Ghi chú in NGHIÊNG và tách dòng: nó không phải
                                 một phần của địa chỉ, và người chép địa chỉ
                                 lên phiếu gửi hàng phải thấy ngay ranh giới. */ ?>
                        <?php if (trim((string) ($ad['ghi_chu'] ?? '')) !== ''): ?>
                            <br><em class="acct-addr__note"><?= e($ad['ghi_chu']) ?></em>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="acct-addr__side">
                    <div class="acct-addr__links">
                        <a href="/tai-khoan?muc=dia-chi&amp;sua=<?= e(rawurlencode($ad['id'])) ?>">Sửa</a>

                        <?php if (!$isDefault || count($addresses) === 1): ?>
                            <!-- Xoá là POST, không phải link: xem ghi chú ở
                                 config/routes.php. Bản thiết kế vẽ nó như một
                                 liên kết nên nút này mang đúng kiểu đó. -->
                            <?php
                            /* Gọi tên địa chỉ sắp xoá: hộp thoại nằm giữa màn
                               hình, che mất chính thẻ vừa bấm, nên "địa chỉ
                               này" không còn chỉ vào đâu khi sổ có ba thẻ. */
                            $hoiXoaDiaChi = sprintf(
                                'Xoá địa chỉ của %s — %s?',
                                $ad['recipient_name'],
                                $ad['line1']
                            );
                            ?>
                            <form method="post" action="/tai-khoan/dia-chi/xoa"
                                  data-confirm="<?= e($hoiXoaDiaChi) ?>"
                                  data-confirm-title="Xoá địa chỉ?"
                                  data-confirm-ok="Xoá"
                                  onsubmit="return confirm('<?= e($hoiXoaDiaChi) ?>')">
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
