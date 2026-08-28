<?php

/**
 * admin/appointments/index.php — lịch hẹn
 * Port từ src/routes/_authenticated/quan-tri/lich-hen.tsx.
 */
?>
<?php
/* Giữ mọi bộ lọc đang bật khi bấm sang viên lọc khác — ba bộ lọc (trạng thái ·
   cơ sở · từ khoá) cộng được với nhau, nên bấm một cái không được xoá hai cái
   kia. */
$giuLoc = array_filter(['q' => $q, 'co-so' => $coSo]);
?>
<header class="ahead ahead--row">
    <div>
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
    </div>

    <?php /* Ô LỌC CƠ SỞ VÀ Ô TÌM NẰM CÙNG DÒNG TIÊU ĐỀ — theo bản thiết kế.

             Cả hai đi trong MỘT form GET: chúng cộng được với nhau, và người
             trực quầy hay dùng đúng cặp ấy ("hôm nay Tây Hồ, khách tên Hằng").
             Hai form riêng thì chọn cái này là mất cái kia. */ ?>
    <div class="ahead__tools">
        <form class="asearch" method="get" action="/quan-tri/lich-hen" role="search">
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>

            <label class="sr-only" for="lhCoSo">Lọc theo cơ sở</label>
            <select class="asearch__pick" id="lhCoSo" name="co-so" data-autosubmit>
                <option value="">Tất cả cơ sở</option>
                <?php foreach ($stores as $st): ?>
                    <option value="<?= e($st['id']) ?>"<?= $coSo === (string) $st['id'] ? ' selected' : '' ?>><?= e($st['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="sr-only" for="lhQ">Tìm lịch hẹn</label>
            <input type="search" id="lhQ" name="q" value="<?= e($q) ?>"
                   placeholder="Tìm mã lịch, tên khách, SĐT…">

            <button type="submit" class="astatus__save astatus__save--ghost">Tìm</button>

            <?php if ($q !== '' || $coSo !== ''): ?>
                <a class="apanel__more"
                   href="/quan-tri/lich-hen<?= $status !== '' ? '?status=' . e($status) : '' ?>">Xoá lọc</a>
            <?php endif; ?>
        </form>
    </div>
</header>

<?php partial('admin/_layout/filter-tabs', [
    'base' => '/quan-tri/lich-hen', 'statuses' => $statuses,
    'counts' => $counts, 'current' => $status,
    'keep' => $giuLoc,
]); ?>

<?php if ($appointments === []): ?>
    <p class="apanel__empty">Không có lịch hẹn nào khớp bộ lọc.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable alhtable">
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
                            <span class="alhname"><?= e($a['full_name']) ?></span>
                            <span class="atable__sub"><?= e($a['phone']) ?></span>
                            <?php /* Ghi chú của khách để trong NGOẶC KÉP và in nghiêng —
                                     theo bản thiết kế. Nó là lời của người khác, không
                                     phải một trường dữ liệu của cửa hàng; không đánh dấu
                                     thì "abc" nằm dưới số điện thoại đọc ra như một mã
                                     nội bộ nào đó. */ ?>
                            <?php if (!empty($a['note'])): ?>
                                <span class="alhnote">“<?= e(excerpt($a['note'], 48)) ?>”</span>
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
                                <select class="astatus__pick astatus__pick--<?= e($a['status']) ?>"
                                        id="st-<?= e($a['id']) ?>" name="status" data-autosubmit>
                                    <?php if (!$khopDatDuoc): ?>
                                        <option value="" selected disabled><?= e($statuses[$a['status']] ?? $a['status']) ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($staffStatuses as $key): ?>
                                        <option value="<?= e($key) ?>"<?= $a['status'] === $key ? ' selected' : '' ?>><?= e($statuses[$key]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="astatus__save">Lưu</button>
                            </form>

                            <?php /* KHÔNG hiện nút huỷ cho lịch ĐÃ HUỶ lẫn ĐÃ HOÀN TẤT
                                     — theo bản thiết kế. Lịch đã huỷ thì huỷ nữa là vô
                                     nghĩa; lịch đã hoàn tất thì khách đã đến và đã được
                                     phục vụ xong, "huỷ" nó không mô tả bất kỳ việc gì
                                     có thật ở cửa hàng, chỉ làm hỏng một dòng sổ sách.
                                     Sửa nhầm một lịch đã hoàn tất vẫn làm được bằng ô
                                     chọn trạng thái ngay trên. */ ?>
                            <?php if (!in_array($a['status'], ['cancelled', 'done'], true)): ?>
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

        <div class="aofoot">
            <p class="aofoot__count">
                Đang hiện <?= count($appointments) ?> / <?= (int) $counts[''] ?> lịch hẹn
            </p>
        </div>
    </div>
<?php endif; ?>
