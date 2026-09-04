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

/* Địa chỉ của MỘT trang, giữ nguyên cả ba bộ lọc đang bật.

   Trang 1 không nằm trên địa chỉ: ?page=1 và không có ?page là cùng một chỗ,
   mà địa chỉ ngắn thì dễ đọc và dễ gửi cho nhau hơn. */
$duongDanTrang = static function (int $so) use ($q, $coSo, $status): string {
    $tham = array_filter([
        'status' => $status,
        'co-so'  => $coSo,
        'q'      => $q,
        'page'   => $so > 1 ? (string) $so : '',
    ], static fn (string $v): bool => $v !== '');

    return '/quan-tri/lich-hen' . ($tham !== [] ? '?' . http_build_query($tham) : '');
};

/* Ô ẩn mang CHỖ ĐANG ĐỨNG theo mọi form POST của trang này, để lưu xong quay
   về đúng trang và đúng bộ lọc thay vì bị ném về đầu danh sách. Tên có đuôi
   `_loc` để không đụng ô `status` thật của form đổi trạng thái — xem
   AppointmentAdminController::veDanhSach(). */
$oAn = static function () use ($q, $coSo, $status, $page): string {
    $ra = '';

    foreach (['status_loc' => $status, 'co_so_loc' => $coSo, 'q_loc' => $q,
              'page' => $page > 1 ? (string) $page : ''] as $ten => $gt) {
        if ($gt !== '') {
            $ra .= '<input type="hidden" name="' . e($ten) . '" value="' . e((string) $gt) . '">';
        }
    }

    return $ra;
};
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
        <?php /* "12 lịch hẹn" khi bảng có 340 dòng là một câu SAI kể từ lúc có
                 phân trang — nó đọc như tổng số, mà thật ra là số dòng của một
                 trang. Nói cả hai con số thì không hiểu nhầm được. */ ?>
        <?= count($appointments) ?><?= $total > count($appointments) ? ' / ' . (int) $total : '' ?> lịch hẹn
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

        <?php /* Khách gọi điện đặt, hoặc đang đứng ở quầy hẹn hôm sau quay lại
                 lấy kính — hai đường vào không đi qua trang đặt lịch của khách.
                 Không có nút này thì nhân viên ghi ra giấy, hoặc tệ hơn là vào
                 trang khách đặt hộ bằng số điện thoại của chính mình. */ ?>
        <a href="/quan-tri/lich-hen?them=1" class="astatus__save" data-modal>+ Tạo lịch hẹn</a>
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
                        <td>
                            <?= e(formatDate($a['appointment_date'])) ?>

                            <?php
                            /* ─────────────────────────────────────────────────
                               DỜI NGÀY NGAY TẠI Ô NGÀY — X19, chốt 04/09/2026

                               Ô chọn ngày nằm ngay dưới con số nó sẽ thay, chứ
                               không nằm trong cột thao tác cuối bảng: người
                               đang nghe khách xin dời lịch đọc ngày cũ và gõ
                               ngày mới trong cùng một chỗ mắt đang nhìn.

                               KHÔNG tự gửi khi chọn (khác ô trạng thái ngay
                               bên): trượt tay trên một ô ngày là dời buổi hẹn
                               của khách sang một ngày ngẫu nhiên. Phải bấm
                               "Dời".

                               Ẩn với lịch ĐÃ HUỶ và ĐÃ HOÀN TẤT — cùng lý lẽ
                               với nút Huỷ ở cột bên. Máy chủ vẫn kiểm lại
                               (BookingModel::rescheduleAdmin). */
                            $doiDuoc = !in_array($a['status'], ['cancelled', 'done'], true);
                            ?>
                            <?php if ($doiDuoc): ?>
                                <form method="post" action="/quan-tri/lich-hen/doi-ngay" class="alhdoi">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                                    <?= $oAn() ?>
                                    <label class="sr-only" for="ng-<?= e($a['id']) ?>">
                                        Dời ngày lịch <?= e($a['code']) ?>
                                    </label>
                                    <?php /* min = HÔM NAY, không phải ngày mai: khách gọi
                                             buổi sáng xin dời xuống buổi chiều là chuyện
                                             thường ở quầy, và đường của nhân viên tồn tại
                                             chính là để làm được thứ khách không tự làm
                                             được nữa. max theo X16 — trần đặt trước 30
                                             ngày, áp cho cả dời lịch. */ ?>
                                    <input class="alhdoi__in" type="date" id="ng-<?= e($a['id']) ?>"
                                           name="appointment_date" required
                                           value="<?= e($a['appointment_date']) ?>"
                                           min="<?= e(date('Y-m-d')) ?>"
                                           max="<?= e(date('Y-m-d', strtotime('+' . BookingModel::DAT_TRUOC_TOI_DA . ' days'))) ?>">
                                    <button type="submit" class="alhdoi__btn">Dời</button>
                                </form>
                            <?php endif; ?>
                        </td>

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
                                <?= $oAn() ?>
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
                                    <?= $oAn() ?>
                                    <button type="submit" class="ahuy__btn">Huỷ lịch</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php /* Chân bảng nói RÕ CÁCH SẮP XẾP. Bảng này sắp theo ngày hẹn giảm
                 dần — buổi gần nhất lên đầu — chứ không theo lúc khách đặt, và
                 đó là trật tự không ai đoán ra nếu không nói. */ ?>
        <div class="aofoot">
            <p class="aofoot__count">
                <?php /* MẪU SỐ LÀ TỔNG CỦA VIÊN LỌC ĐANG CHỌN ($total), không
                         phải tổng cả bảng ($counts['']). Đứng ở viên "Chờ xác
                         nhận" mà chân bảng đọc "20 / 340" thì con số 340 nói về
                         một danh sách đang không hiện ra — đúng cái lẫn lộn mà
                         dải viên lọc sinh ra để tránh. */ ?>
                Đang hiện <?= count($appointments) ?> / <?= (int) $total ?> lịch hẹn<?php
                    if ($totalPages > 1): ?> · trang <?= (int) $page ?>/<?= (int) $totalPages ?><?php
                    endif; ?> · sắp theo ngày hẹn gần nhất trước
            </p>

            <?php if ($totalPages > 1): ?>
                <nav class="pager" aria-label="Phân trang">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pager__link" href="<?= e($duongDanTrang($i)) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php
/*
 * HỘP THOẠI TẠO LỊCH HẸN — theo bản thiết kế "Lịch hẹn.dc.html".
 *
 * Mở theo địa chỉ (?them=1), không theo JavaScript — xem .amodal trong
 * admin.css. Chỉ có đường TẠO, không có đường sửa: một lịch đã đặt thì thứ
 * nhân viên đổi là TRẠNG THÁI, và việc đó làm ngay trên dòng bằng ô chọn.
 * Sửa tên hay ngày của lịch khách tự đặt là sửa lời của người khác — nếu
 * khách muốn đổi ngày thì họ đổi ở trang tài khoản, và cột `updated_at` ghi
 * lại việc đó.
 */
$moHop   = isset($_GET['them']);
$dongUrl = '/quan-tri/lich-hen';
?>
<?php if ($moHop): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => 'Tạo lịch hẹn mới',
        'phu'     => 'Lịch tạo ở đây vẫn ở trạng thái chờ xác nhận — gọi lại chốt giờ rồi hãy đổi.',
        'dongUrl' => $dongUrl,
        'rong'    => '',
    ]); ?>

        <form method="post" action="/quan-tri/lich-hen/tao" class="aform__grid" id="lh-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

            <div class="field">
                <label for="lh-ten">Tên khách hàng *</label>
                <input type="text" id="lh-ten" name="full_name" required maxlength="255"
                       placeholder="Nguyễn Thu Trang">
            </div>

            <div class="field">
                <label for="lh-sdt">Số điện thoại *</label>
                <input type="tel" id="lh-sdt" name="phone" required maxlength="32"
                       placeholder="0901 234 567">
            </div>

            <div class="field">
                <label for="lh-ngay">Ngày hẹn *</label>
                <?php /* min = hôm nay: BookingModel::create() chặn ngày quá khứ ở
                         máy chủ, ô này chỉ nói trước để người dùng khỏi gõ xong
                         mới bị trả về. Hẹn cho hôm nay vẫn hợp lệ — cửa hàng mở
                         tới 21:00. */ ?>
                <input type="date" id="lh-ngay" name="appointment_date" required
                       min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>">
            </div>

            <div class="field">
                <label for="lh-coso">Cơ sở *</label>
                <select id="lh-coso" name="store_id" required>
                    <?php foreach ($stores as $st): ?>
                        <?php /* Cơ sở đang tạm đóng vẫn hiện nhưng ghi rõ: nó không
                                 nhận lịch được (BookingModel::create chặn), mà giấu
                                 hẳn đi thì người tạo không hiểu vì sao cơ sở quen
                                 thuộc biến mất khỏi danh sách. */ ?>
                        <option value="<?= e($st['id']) ?>"<?= empty($st['is_active']) ? ' disabled' : '' ?>>
                            <?= e($st['name']) ?><?= empty($st['is_active']) ? ' — đang tạm đóng' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field field--wide">
                <label for="lh-dv">Dịch vụ *</label>
                <select id="lh-dv" name="service_type" required>
                    <?php foreach ($services as $dv): ?>
                        <option value="<?= e($dv) ?>"><?= e($dv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field field--wide">
                <label for="lh-ghi">Ghi chú</label>
                <textarea id="lh-ghi" name="note" rows="3"
                          placeholder="Yêu cầu riêng của khách, tình trạng kính mang tới…"></textarea>
            </div>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $dongUrl,
        'luuNhan' => 'Tạo lịch hẹn',
        'luuForm' => 'lh-form',
    ]); ?>
<?php endif; ?>
