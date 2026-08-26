<?php

/**
 * _tab-dia-chi.php — tab 2: sổ địa chỉ giao hàng.
 *
 * Biến: $khach, $addresses, $addrEditing, $duongDan (từ detail.php/controller).
 *
 * Sổ địa chỉ này là CHÍNH sổ khách thấy ở /tai-khoan?muc=dia-chi — cùng bảng,
 * cùng AddressModel, cùng bộ luật kiểm tra. Sửa ở đây là sửa cái khách sẽ thấy
 * ở lần đặt hàng tới, nên đừng coi nó như một bản nháp nội bộ.
 */

$sua      = $addrEditing;
$form     = $sua ?? [];
$veTab    = $duongDan . '?tab=dia-chi';
?>

<div class="apanel">
    <div class="apanel__head">
        <h2 class="apanel__title">Sổ địa chỉ (<?= count($addresses) ?>)</h2>
        <?php if ($sua !== null): ?>
            <a class="apanel__more" href="<?= e($veTab) ?>">Huỷ sửa</a>
        <?php endif; ?>
    </div>

    <?php if ($addresses === []): ?>
        <p class="apanel__empty">Khách chưa lưu địa chỉ nào.</p>
    <?php else: ?>
        <ul class="acus__addrs" role="list">
            <?php foreach ($addresses as $dc): ?>
                <?php $macDinh = (int) $dc['is_default'] === 1; ?>
                <li class="acus__addr<?= $macDinh ? ' is-default' : '' ?>">
                    <div class="acus__addr-body">
                        <p class="acus__addr-name">
                            <?= e($dc['recipient_name']) ?>
                            <?php if ($macDinh): ?>
                                <span class="atag">Mặc định</span>
                            <?php endif; ?>
                        </p>
                        <p class="acus__addr-line"><?= e(groupPhone($dc['phone'])) ?></p>
                        <p class="acus__addr-line">
                            <?= e($dc['line1']) ?><?php
                            /* areaText() ghép tỉnh và phường theo đúng cách
                               AddressModel quy định — hai cấp từ 01/07/2025,
                               không phải ba. Tự ghép ở view là chỗ thứ hai
                               phải nhớ luật đó. */
                            $khuVuc = AddressModel::areaText($dc);
                            echo $khuVuc !== '' ? ', ' . e($khuVuc) : ''; ?>
                        </p>
                    </div>

                    <div class="acus__addr-acts">
                        <a href="<?= e($veTab . '&sua=' . rawurlencode($dc['id'])) ?>">Sửa</a>

                        <?php if (!$macDinh): ?>
                            <form method="post" action="/quan-tri/khach-hang/dia-chi/mac-dinh">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                                <input type="hidden" name="dia_chi_id" value="<?= e($dc['id']) ?>">
                                <button type="submit" class="acus__linkbtn">Đặt làm mặc định</button>
                            </form>

                            <?php /* KHÔNG có nút xoá trên thẻ MẶC ĐỊNH khi vẫn
                                     còn thẻ khác — AddressModel::deleteOwned()
                                     từ chối việc đó, vì xoá xong thì khách
                                     không còn địa chỉ mặc định nào mà giao diện
                                     lại không có chỗ báo. Bày một nút chỉ để
                                     nhận về lời từ chối là dạy người dùng bỏ
                                     qua thông báo lỗi. */ ?>
                            <form method="post" action="/quan-tri/khach-hang/dia-chi/xoa"
                                  data-confirm="Xoá địa chỉ của <?= e($dc['recipient_name']) ?>?"
                                  data-confirm-title="Xoá địa chỉ?"
                                  data-confirm-ok="Xoá">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                                <input type="hidden" name="dia_chi_id" value="<?= e($dc['id']) ?>">
                                <button type="submit" class="arow-del">Xoá</button>
                            </form>
                        <?php elseif (count($addresses) === 1): ?>
                            <form method="post" action="/quan-tri/khach-hang/dia-chi/xoa"
                                  data-confirm="Xoá địa chỉ duy nhất của khách?"
                                  data-confirm-title="Xoá địa chỉ?"
                                  data-confirm-ok="Xoá">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
                                <input type="hidden" name="dia_chi_id" value="<?= e($dc['id']) ?>">
                                <button type="submit" class="arow-del">Xoá</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<section class="apanel" id="form-dia-chi">
    <div class="apanel__head">
        <h2 class="apanel__title"><?= $sua !== null ? 'Sửa địa chỉ' : 'Thêm địa chỉ' ?></h2>
    </div>

    <form class="aform" method="post" action="/quan-tri/khach-hang/dia-chi/luu">
        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="id" value="<?= e($khach['id']) ?>">
        <?php if ($sua !== null): ?>
            <input type="hidden" name="dia_chi_id" value="<?= e($sua['id']) ?>">
        <?php endif; ?>

        <div class="aform__grid" data-vnaddr>
            <div class="field">
                <label for="nguoi-nhan">Tên người nhận</label>
                <input type="text" id="nguoi-nhan" name="recipient_name" required maxlength="255"
                       value="<?= e((string) ($form['recipient_name'] ?? '')) ?>">
            </div>

            <div class="field">
                <label for="sdt-nhan">Số điện thoại người nhận</label>
                <input type="tel" id="sdt-nhan" name="phone" required
                       value="<?= e((string) ($form['phone'] ?? '')) ?>">
                <?php /* KHÁC số đăng nhập ở tab Hồ sơ: số này không cần là số
                         chưa ai dùng — hai người cùng nhà nhận hàng bằng một số
                         là chuyện bình thường. */ ?>
                <p class="field__hint">Có thể trùng với số của người khác.</p>
            </div>

            <div class="field field--wide">
                <label for="dia-chi">Địa chỉ</label>
                <input type="text" id="dia-chi" name="line1" required maxlength="255"
                       placeholder="Số nhà, ngõ, đường"
                       value="<?= e((string) ($form['line1'] ?? '')) ?>">
            </div>

            <?php /* HAI Ô GÕ TAY, KHÔNG PHẢI <select> IN SẴN.

                     address-picker.js nâng chúng thành danh sách chọn lấy từ
                     provinces.open-api.vn. Làm ngược lại — in sẵn <select> rỗng
                     rồi chờ JS đổ dữ liệu — thì API chết hay JS tắt là còn lại
                     một ô chọn trống không lưu nổi địa chỉ nào.

                     Hai ô mã đi kèm để form sửa chọn lại đúng mục. Hợp đồng đầy
                     đủ ghi ở đầu assets/js/address-picker.js. */ ?>
            <div class="field">
                <label for="tinh">Tỉnh / Thành phố</label>
                <input type="text" id="tinh" name="province_name" required maxlength="120"
                       data-vnaddr-field="province"
                       value="<?= e((string) ($form['province_name'] ?? '')) ?>">
            </div>

            <div class="field">
                <label for="phuong">Phường / Xã</label>
                <input type="text" id="phuong" name="ward_name" required maxlength="120"
                       data-vnaddr-field="ward"
                       value="<?= e((string) ($form['ward_name'] ?? '')) ?>">
            </div>

            <input type="hidden" name="province_code" data-vnaddr-code="province"
                   value="<?= e((string) ($form['province_code'] ?? '')) ?>">
            <input type="hidden" name="ward_code" data-vnaddr-code="ward"
                   value="<?= e((string) ($form['ward_code'] ?? '')) ?>">

            <div class="field field--check field--wide">
                <label>
                    <input type="checkbox" name="is_default" value="1"
                        <?= (int) ($form['is_default'] ?? 0) === 1 ? 'checked' : '' ?>>
                    Đặt làm địa chỉ mặc định
                </label>
                <?php /* Địa chỉ ĐẦU TIÊN luôn thành mặc định dù không tick —
                         AddressModel::create() lo việc đó, vì một sổ địa chỉ
                         không có cái mặc định nào thì trang thanh toán không
                         biết điền sẵn gì. */ ?>
                <p class="field__hint">Địa chỉ đầu tiên tự thành mặc định.</p>
            </div>

            <button type="submit" class="astatus__save">
                <?= $sua !== null ? 'Lưu địa chỉ' : 'Thêm địa chỉ' ?>
            </button>
        </div>
    </form>
</section>
