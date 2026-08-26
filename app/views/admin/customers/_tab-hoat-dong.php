<?php

/**
 * _tab-hoat-dong.php — tab 4: bốn danh sách CHỈ ĐỌC.
 *
 * Biến: $activity, $orderStatuses, $paymentStatuses, $apptStatuses,
 *       $contactStatuses, $reviewStatuses, $khach.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG CÓ MỘT NÚT SỬA NÀO TRONG FILE NÀY, VÀ ĐỪNG THÊM.
 *
 * Bốn khối dưới đây thuộc về bốn module khác. Module Khách hàng chỉ hỏi "khách
 * này gần đây làm gì" rồi chỉ đường sang chỗ xử lý. Thêm một ô chọn trạng thái
 * đơn vào đây là tạo chỗ thứ hai làm cùng một việc — và hai chỗ sẽ lệch nhau,
 * mà lệch ở dữ liệu đơn hàng thì phát hiện được lúc đã giao nhầm.
 *
 * Mỗi khối giới hạn 10 dòng gần nhất (CustomerModel::activity). Đây không phải
 * màn quản lý đơn hàng, chỉ là một cái liếc mắt — xem đủ thì bấm sang module gốc.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$khong = '<p class="apanel__empty">Chưa có.</p>';
?>

<div class="acus__cols">

    <!-- ======================= ĐƠN HÀNG ======================= -->
    <section class="apanel">
        <div class="apanel__head">
            <h2 class="apanel__title">Đơn hàng (<?= count($activity['orders']) ?>)</h2>
            <a class="apanel__more" href="/quan-tri/don-hang">Mở module Đơn hàng →</a>
        </div>

        <?php if ($activity['orders'] === []): ?>
            <?= $khong ?>
        <?php else: ?>
            <table class="atable">
                <thead>
                    <tr>
                        <th scope="col">Mã đơn</th>
                        <th scope="col">Tổng</th>
                        <th scope="col">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity['orders'] as $d): ?>
                        <tr>
                            <td>
                                <code><?= e($d['code']) ?></code>
                                <span class="atable__sub"><?= e(formatDate($d['created_at'], 'd/m/Y')) ?></span>
                            </td>
                            <td>
                                <span class="num"><?= e(money((int) $d['total'])) ?></span>
                                <span class="atable__sub">
                                    <?= e($paymentStatuses[$d['payment_status']] ?? $d['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge--<?= e($d['status']) ?>">
                                    <?= e($orderStatuses[$d['status']] ?? $d['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- ======================= LỊCH HẸN ======================= -->
    <section class="apanel">
        <div class="apanel__head">
            <h2 class="apanel__title">Lịch hẹn (<?= count($activity['appointments']) ?>)</h2>
            <a class="apanel__more" href="/quan-tri/lich-hen">Mở module Lịch hẹn →</a>
        </div>

        <?php if ($activity['appointments'] === []): ?>
            <?= $khong ?>
        <?php else: ?>
            <table class="atable">
                <thead>
                    <tr>
                        <th scope="col">Mã / Ngày</th>
                        <th scope="col">Dịch vụ</th>
                        <th scope="col">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activity['appointments'] as $lh): ?>
                        <tr>
                            <td>
                                <code><?= e($lh['code']) ?></code>
                                <span class="atable__sub"><?= e(formatDate($lh['appointment_date'])) ?></span>
                            </td>
                            <td>
                                <?= e($lh['service_type']) ?>
                                <?php if ($lh['store_name'] !== null): ?>
                                    <span class="atable__sub"><?= e($lh['store_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge--<?= e($lh['status']) ?>">
                                    <?= e($apptStatuses[$lh['status']] ?? $lh['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- ======================= LIÊN HỆ ======================= -->
    <section class="apanel">
        <div class="apanel__head">
            <h2 class="apanel__title">Liên hệ đã gửi (<?= count($activity['contacts']) ?>)</h2>
            <a class="apanel__more" href="/quan-tri/lien-he">Mở module Liên hệ →</a>
        </div>

        <?php if ($activity['contacts'] === []): ?>
            <?php /* Khối này khuyết cả khi cột `contact_requests`.`user_id` chưa
                     tồn tại (CSDL chưa chạy migration) — xem
                     CustomerModel::activity(). Không phân biệt hai ca ở đây:
                     trang chưa-nâng-cấp đã chặn từ trước, nên tới được đây thì
                     cột đã có. */ ?>
            <?= $khong ?>
        <?php else: ?>
            <ul class="acus__feed" role="list">
                <?php foreach ($activity['contacts'] as $lh): ?>
                    <li>
                        <p class="acus__feed-meta">
                            <?= e(formatDate($lh['created_at'], 'd/m/Y H:i')) ?>
                            <span class="badge badge--<?= e($lh['status']) ?>">
                                <?= e($contactStatuses[$lh['status']] ?? $lh['status']) ?>
                            </span>
                        </p>
                        <p class="acus__feed-body"><?= e(excerpt($lh['message'], 160)) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- ======================= ĐÁNH GIÁ ======================= -->
    <section class="apanel">
        <div class="apanel__head">
            <h2 class="apanel__title">Đánh giá đã viết (<?= count($activity['reviews']) ?>)</h2>
            <a class="apanel__more" href="/quan-tri/danh-gia">Mở module Đánh giá →</a>
        </div>

        <?php if ($activity['reviews'] === []): ?>
            <?= $khong ?>
        <?php else: ?>
            <ul class="acus__feed" role="list">
                <?php foreach ($activity['reviews'] as $dg): ?>
                    <li>
                        <p class="acus__feed-meta">
                            <?php /* starIcon() thay vì gõ ký tự ★: cùng bộ icon
                                     với phần còn lại của trang, và nó phân biệt
                                     sao sáng với sao mờ bằng nét chứ không bằng
                                     màu — đọc được cả khi in đen trắng. */ ?>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?= starIcon($i <= (int) $dg['rating'], '', 14) ?>
                            <?php endfor; ?>
                            <?= e($dg['product_name'] ?? '(sản phẩm đã gỡ)') ?>
                            <?php /* Ánh xạ trạng thái -> tên viên MÀU giống hệt
                                     admin/reviews/index.php. Không có lớp
                                     .badge--published hay .badge--rejected trong
                                     admin.css; bảng màu ở đó xếp theo Ý NGHĨA
                                     (ok · cảnh báo · nguy) chứ không theo tên
                                     trạng thái của từng module. */ ?>
                            <span class="badge badge--<?= $dg['status'] === 'published' ? 'in_stock'
                                : ($dg['status'] === 'rejected' ? 'cancelled' : 'pending') ?>">
                                <?= e($reviewStatuses[$dg['status']] ?? $dg['status']) ?>
                            </span>
                        </p>
                        <p class="acus__feed-body"><?= e(excerpt($dg['body'], 160)) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</div>
