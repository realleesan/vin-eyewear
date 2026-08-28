<?php

/**
 * _tab-dia-chi.php — tab 2: sổ địa chỉ giao hàng. CHỈ XEM.
 *
 * Biến: $khach, $addresses (từ detail.php/controller).
 *
 * Sổ địa chỉ này là CHÍNH sổ khách thấy ở /tai-khoan?muc=dia-chi — cùng bảng,
 * cùng AddressModel. Chính vì thế khu quản trị KHÔNG sửa: một cú gõ nhầm ở
 * đây là gói hàng tới đã đi sai nhà, mà người gõ thì không bao giờ thấy hậu
 * quả. Khách tự sửa sổ của mình; nhân viên đọc để đối chiếu khi khách gọi
 * điện hỏi "đơn của tôi giao tới đâu".
 *
 * Bỏ thêm/sửa/xoá/đặt-mặc-định ngày 2026-08-28 — cùng đợt với hồ sơ. Muốn mở
 * lại thì xem đầu CustomerAdminController trước, ở đó nói vì sao đóng.
 */
?>

<div class="apanel">
    <div class="apanel__head">
        <h2 class="apanel__title">Sổ địa chỉ (<?= count($addresses) ?>)</h2>
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

                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<p class="field__hint">
    Khu quản trị chỉ XEM sổ địa chỉ. Khách tự thêm, sửa, xoá và chọn địa chỉ
    mặc định ở trang <a href="/tai-khoan?muc=dia-chi" target="_blank"
    rel="noopener">tài khoản</a> của họ.
</p>
