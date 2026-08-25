<?php

/**
 * admin/appointments/index.php — lịch hẹn
 * Port từ src/routes/_authenticated/quan-tri/lich-hen.tsx.
 */
?>
<header class="ahead">
    <h1 class="ahead__title">Lịch hẹn</h1>
    <?php /* Kèm luôn số ĐANG CHỜ XÁC NHẬN — theo bản thiết kế.
             Tổng số lịch hẹn một mình thì không nói được gì để làm: con số
             đáng đọc là bao nhiêu buổi còn treo chờ người gọi xác nhận. Lấy
             từ $counts mà thanh lọc bên dưới vẫn đang dùng, không thêm truy
             vấn nào. */ ?>
    <p class="ahead__lead">
        <?= count($appointments) ?> lịch hẹn
        <?php if (!empty($counts['pending'])): ?>
            · <?= (int) $counts['pending'] ?> đang chờ xác nhận
        <?php endif; ?>
    </p>
</header>

<?php partial('admin/_layout/filter-tabs', [
    'base' => '/quan-tri/lich-hen', 'statuses' => $statuses,
    'counts' => $counts, 'current' => $status,
]); ?>

<?php if ($appointments === []): ?>
    <p class="apanel__empty">Không có lịch hẹn nào khớp bộ lọc.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Mã</th>
                    <th scope="col">Ngày / Giờ</th>
                    <th scope="col">Cơ sở</th>
                    <th scope="col">Khách hàng</th>
                    <th scope="col">Dịch vụ</th>
                    <th scope="col">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><code><?= e($a['code']) ?></code></td>
                        <td>
                            <?= e(formatDate($a['appointment_date'])) ?>

                            <?php
                            /*
                             * Ô GIỜ SỬA ĐƯỢC TẠI CHỖ.
                             *
                             * Khách chỉ chọn ngày (giả định A5); giờ được thống
                             * nhất trong cuộc gọi xác nhận. Đây là chỗ nhân viên
                             * ghi lại cái giờ vừa hẹn — không có nó thì cột
                             * time_slot nằm NULL vĩnh viễn và thoả thuận qua điện
                             * thoại không để lại vết nào.
                             *
                             * KHÔNG có `data-autosubmit` như ô trạng thái bên
                             * cạnh: ô giờ là thứ gõ dở dang, tự gửi khi vừa gõ
                             * "1" của "15:00" là gửi đi một giờ sai. Nút "Lưu"
                             * luôn hiện, khác ô trạng thái vốn ẩn nút khi có JS.
                             *
                             * Để TRỐNG rồi Lưu = xoá giờ đã chốt.
                             */
                            ?>
                            <form method="post" action="/quan-tri/lich-hen/gio" class="atime">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                <label class="sr-only" for="gio-<?= e($a['id']) ?>">
                                    Giờ hẹn lịch <?= e($a['code']) ?>
                                </label>
                                <input class="atime__input" type="time" id="gio-<?= e($a['id']) ?>"
                                       name="time_slot" value="<?= e($a['time_slot'] ?? '') ?>">
                                <button type="submit" class="atime__save">Lưu</button>
                            </form>

                            <?php if (empty($a['time_slot'])): ?>
                                <?php /* Nói hẳn ra thay vì để ô trống: ô trống trong
                                         bảng đọc như dữ liệu lỗi, còn đây là việc
                                         cần làm. */ ?>
                                <span class="atable__sub">Chưa chốt giờ</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($a['store_name']) ?></td>
                        <td>
                            <?= e($a['full_name']) ?>
                            <span class="atable__sub"><?= e($a['phone']) ?></span>
                            <?php if (!empty($a['note'])): ?>
                                <span class="atable__sub"><?= e(excerpt($a['note'], 48)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($a['service_type']) ?></td>
                        <td>
                            <form method="post" action="/quan-tri/lich-hen/trang-thai" class="astatus">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                <label class="sr-only" for="st-<?= e($a['id']) ?>">Trạng thái lịch <?= e($a['code']) ?></label>
                                <select id="st-<?= e($a['id']) ?>" name="status" data-autosubmit>
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $a['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="astatus__save">Lưu</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
