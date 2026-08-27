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
                    <th scope="col">Ngày</th>
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
                        <td><?= e(formatDate($a['appointment_date'])) ?></td>

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
                            <?php
                            /*
                             * Ô CHỌN CHỈ CÓ HAI VIỆC NHÂN VIÊN LÀM ĐƯỢC.
                             *
                             * Bốn trạng thái của vòng đời vẫn còn nguyên trong
                             * dữ liệu và trong dải viên lọc phía trên; chỉ ô
                             * chọn này rút xuống còn "Đã xác nhận" và "Đã hoàn
                             * tất". Vì sao — xem BookingModel::STAFF_STATUSES.
                             *
                             * MỘT LỊCH ĐANG 'pending' HAY 'cancelled' THÌ SAO?
                             *
                             * Nó vẫn phải ĐỌC RA đúng trạng thái đang có, nếu
                             * không thì mọi lịch chờ xác nhận đều hiện chữ "Đã
                             * xác nhận" (giá trị đầu danh sách) trong khi CSDL
                             * nói ngược lại — bảng nói dối, và đó là kiểu sai
                             * không ai phát hiện ra cho tới lúc gọi nhầm khách.
                             *
                             * Nên trạng thái hiện tại được chèn thành một
                             * <option> ĐÃ KHOÁ đứng đầu: hiện đúng chữ, mà bấm
                             * vào không chọn lại được. Bỏ `disabled` đi thì nó
                             * thành tuỳ chọn thứ ba và luật vừa chốt hỏng ngay.
                             */
                            $khopDatDuoc = in_array($a['status'], $staffStatuses, true);
                            ?>
                            <form method="post" action="/quan-tri/lich-hen/trang-thai" class="astatus">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                <label class="sr-only" for="st-<?= e($a['id']) ?>">Trạng thái lịch <?= e($a['code']) ?></label>
                                <select id="st-<?= e($a['id']) ?>" name="status" data-autosubmit>
                                    <?php if (!$khopDatDuoc): ?>
                                        <option value="" selected disabled><?= e($statuses[$a['status']] ?? $a['status']) ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($staffStatuses as $key): ?>
                                        <option value="<?= e($key) ?>"<?= $a['status'] === $key ? ' selected' : '' ?>><?= e($statuses[$key]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="astatus__save">Lưu</button>
                            </form>

                            <?php if ($a['status'] !== 'cancelled'): ?>
                                <?php
                                /* Câu hỏi lại do CÙNG một biến sinh ra cho cả
                                   data-confirm lẫn onsubmit, nên hộp thoại của
                                   dự án và hộp confirm() của trình duyệt không
                                   thể nói hai câu khác nhau. confirm-dialog.js
                                   gỡ onsubmit khi nó sẵn sàng thay thế — xem
                                   khối chú thích đầu file đó.

                                   Đây là chỗ hiếm hoi "tăng cường" không được
                                   phép làm mất một lớp bảo vệ: huỷ lịch là việc
                                   khách hàng ở đầu bên kia chịu hậu quả. */
                                $hoiHuy = sprintf('Huỷ lịch hẹn %s của %s?', $a['code'], $a['full_name']);
                                ?>
                                <form method="post" action="/quan-tri/lich-hen/huy" class="ahuy"
                                      data-confirm="<?= e($hoiHuy) ?>"
                                      data-confirm-title="Huỷ lịch hẹn?"
                                      data-confirm-ok="Huỷ lịch"
                                      onsubmit="return confirm('<?= e($hoiHuy) ?>')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                    <button type="submit" class="ahuy__btn">Huỷ lịch</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
