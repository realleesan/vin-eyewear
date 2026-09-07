<?php

/**
 * _tab-dia-chi.php — tab 2: địa chỉ giao hàng. CHỈ XEM.
 *
 * Biến: $khach, $diaChi (từ detail.php/controller).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỘT ĐỊA CHỈ, KHÔNG CÒN SỔ — 2026-09-12
 *
 * Tab này từng liệt kê cả một sổ nhiều địa chỉ (bảng `addresses`, lớp
 * AddressModel). Cả hai đã gỡ: mỗi khách nay có đúng một địa chỉ, nằm ở năm
 * cột của `profiles` và sửa ngay trong form Hồ sơ của họ. Xem migration
 * 2026-09-12-dia-chi-vao-ho-so.sql.
 *
 * Ba thứ biến mất theo sổ, và không phải lỗi khi nhân viên không thấy chúng
 * nữa: tên người nhận và số điện thoại giao hàng riêng (nay dùng chính họ tên
 * và số của tài khoản), ghi chú cho shipper, và nhãn "Nhà riêng / Công ty".
 * Nhãn là mất mát thật với người sắp lịch giao — toà nhà văn phòng không nhận
 * hàng lúc bảy giờ tối — nhưng một nhãn chỉ có nghĩa khi có NHIỀU địa chỉ để
 * phân biệt. Khách cần dặn gì thì dặn ở ô ghi chú của chính đơn hàng.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VẪN CHỈ XEM, KHÔNG SỬA
 *
 * Địa chỉ này là CHÍNH địa chỉ khách thấy ở /tai-khoan?muc=ho-so. Khu quản trị
 * không sửa: một cú gõ nhầm ở đây là gói hàng tới đã đi sai nhà, mà người gõ
 * thì không bao giờ thấy hậu quả. Khách tự sửa; nhân viên đọc để đối chiếu khi
 * khách gọi hỏi "đơn của tôi giao tới đâu".
 *
 * Bỏ thêm/sửa/xoá/đặt-mặc-định ngày 2026-08-28 — cùng đợt với hồ sơ. Muốn mở
 * lại thì xem đầu CustomerAdminController trước, ở đó nói vì sao đóng.
 */

/* UserModel::diaChi() trả null khi khách chưa khai địa chỉ chi tiết — nó đo
   bằng chính ô đó chứ không bằng tỉnh/phường, vì chọn mỗi tỉnh rồi bỏ dở thì
   chưa giao được tới đâu cả. */
$khuVuc = $diaChi === null ? '' : implode(', ', array_filter([
    trim((string) $diaChi['ward_name']),
    trim((string) $diaChi['province_name']),
], static fn (string $p): bool => $p !== ''));
?>

<div class="apanel">
    <div class="apanel__head">
        <h2 class="apanel__title">Địa chỉ giao hàng</h2>
    </div>

    <?php if ($diaChi === null): ?>
        <p class="apanel__empty">Khách chưa lưu địa chỉ nào.</p>
    <?php else: ?>
        <ul class="acus__addrs" role="list">
            <li class="acus__addr is-default">
                <div class="acus__addr-body">
                    <?php /* Người nhận và số điện thoại nay LẤY TỪ HỒ SƠ — địa
                             chỉ thuộc về tài khoản, nên chủ tài khoản là người
                             nhận. Khách gửi cho người khác thì gõ tên ấy ở
                             trang thanh toán, và đơn hàng giữ bản chép riêng
                             của nó. */ ?>
                    <p class="acus__addr-name"><?= e($diaChi['recipient_name']) ?></p>

                    <?php if (trim($diaChi['phone']) !== ''): ?>
                        <p class="acus__addr-line"><?= e(groupPhone($diaChi['phone'])) ?></p>
                    <?php endif; ?>

                    <p class="acus__addr-line">
                        <?= e($diaChi['line1']) ?><?= $khuVuc !== '' ? ', ' . e($khuVuc) : '' ?>
                    </p>
                </div>
            </li>
        </ul>
    <?php endif; ?>
</div>

<p class="field__hint">
    Khu quản trị chỉ XEM địa chỉ. Khách tự sửa trong mục Hồ sơ ở trang
    <a href="/tai-khoan?muc=ho-so" target="_blank" rel="noopener">tài khoản</a>
    của họ.
</p>
