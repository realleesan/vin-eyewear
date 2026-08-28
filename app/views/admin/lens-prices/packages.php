<?php

/**
 * admin/lens-prices/packages.php — danh mục gói chiết suất
 * (/quan-tri/gia-trong/goi).
 *
 * Controller: Admin/LensPriceAdminController::packages()
 *
 * Danh sách + form thêm/sửa theo đúng nếp các màn CRUD khác của khu quản trị
 * (cơ sở · danh mục · bộ sưu tập): nút "+ Thêm gói" neo xuống #form ở cuối
 * trang, sửa thì mở lại chính form đó với ?sua=<mã>.
 */
?>
<?php partial('admin/_layout/crud-head', [
    'title'    => 'Gói chiết suất',
    'lead'     => count($packages) . ' gói đang bán · khách chọn ở bước "Chọn loại tròng kính"',
    'base'     => '/quan-tri/gia-trong/goi',
    'canEdit'  => $canEdit,
    'editing'  => $editing,
    'addLabel' => '+ Thêm gói',
]); ?>

<div class="anote">
    <p>
        Đây là <strong>danh mục</strong>: mã, tên và mô tả. Còn <strong>giá</strong> thì
        nằm ở <a href="/quan-tri/gia-trong">bảng giá tròng</a> — mỗi gói có một giá riêng
        cho từng loại tròng, nên không có ô giá nào ở trang này.
    </p>
    <p>
        Thêm một gói ở đây là bảng giá mọc thêm một hàng, và khách thấy thêm một lựa chọn
        ở hộp mua hàng ngay lập tức. <strong>Hàng mới chưa có giá</strong> nên khách sẽ
        thấy “Báo giá sau khi tư vấn” cho tới khi bạn nhập giá.
    </p>
</div>

<?php if ($packages === []): ?>
    <p class="apanel__empty">Chưa có gói chiết suất nào.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Thứ tự</th>
                    <th scope="col">Mã</th>
                    <th scope="col">Tên gói</th>
                    <th scope="col">Mô tả</th>
                    <th scope="col">Đã định giá</th>
                    <?php if ($canEdit): ?>
                        <th scope="col">Thao tác</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php $soGoi = count($packages); ?>
                <?php foreach ($packages as $i => $p): ?>
                    <?php $soGia = $priceCounts[$p['id']] ?? 0; ?>
                    <tr>
                        <?php /* Thứ tự ở đây là thứ tự khách thấy ở bước "Chọn loại
                                 tròng kính", và gói đứng đầu là gói được chọn sẵn —
                                 nên nó quyết định gói nào bán chạy, không phải chuyện
                                 sắp cho gọn mắt. Con số sort_order thô không nói được
                                 điều đó (cả cột có thể cùng bằng 0), hai cái nút thì
                                 nói. */ ?>
                        <td>
                            <?php if ($canEdit): ?>
                                <?php partial('admin/_layout/thu-tu', [
                                    'base' => '/quan-tri/gia-trong/goi/thu-tu',
                                    'id'   => $p['id'],
                                    'dau'  => $i === 0,
                                    'cuoi' => $i === $soGoi - 1,
                                    'ten'  => $p['name'],
                                ]); ?>
                            <?php else: ?>
                                <span class="num"><?= $i + 1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($p['id']) ?></code></td>
                        <td><?= e($p['name']) ?></td>
                        <td>
                            <?php if (($p['description'] ?? '') !== ''): ?>
                                <?= e($p['description']) ?>
                            <?php else: ?>
                                <span class="atable__sub">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php /* Gói chưa có ô giá nào thì đeo nhãn, không in
                                     số 0: nhìn lướt cả cột là thấy ngay gói nào
                                     đang bày ra cho khách mà chưa định giá — khách
                                     chọn đúng nó sẽ thấy "Báo giá sau khi tư vấn". */ ?>
                            <?php if ($soGia === 0): ?>
                                <span class="badge badge--cancelled">Chưa có giá</span>
                            <?php else: ?>
                                <?= (int) $soGia ?> mức giá
                            <?php endif; ?>
                        </td>

                        <?php if ($canEdit): ?>
                            <td class="arow-actions">
                                <a href="/quan-tri/gia-trong/goi?sua=<?= e($p['id']) ?>">Sửa</a>

                                <?php
                                /* Câu hỏi lại NÓI RA SỐ MỨC GIÁ SẼ MẤT THEO.
                                   Xoá gói là xoá luôn các ô giá của nó (không có
                                   khoá ngoại nào lo việc đó — xem
                                   LensModel::deletePackage), và một bảng giá đã gõ
                                   tay thì không lấy lại được. "Xoá gói này?" trần
                                   không nói được điều đó.

                                   Cùng một biến sinh ra cả data-confirm lẫn
                                   onsubmit nên hộp thoại của dự án và hộp
                                   confirm() của trình duyệt không thể nói hai câu
                                   khác nhau. */
                                $hoi = $soGia > 0
                                    ? sprintf('Xoá gói “%s”? %d mức giá đã nhập cho gói này cũng mất theo.', $p['name'], $soGia)
                                    : sprintf('Xoá gói “%s”?', $p['name']);
                                ?>
                                <form method="post" action="/quan-tri/gia-trong/goi/xoa"
                                      data-confirm="<?= e($hoi) ?>"
                                      data-confirm-title="Xoá gói chiết suất?"
                                      data-confirm-ok="Xoá"
                                      onsubmit="return confirm('<?= e($hoi) ?>')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                    <button type="submit" class="arow-del">Xoá</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
/*
 * FORM THÊM/SỬA LÀ MỘT HỘP THOẠI NỔI — theo bản thiết kế.
 *
 * Hộp mở ra theo ĐỊA CHỈ chứ không theo JavaScript: ?them=1 mở form trống,
 * ?sua=<id> mở form đã điền. Nút ✕, nút Huỷ và lớp nền mờ đều là <a> trỏ về
 * chính trang này. Lý do đầy đủ ở khối .amodal trong admin.css.
 */
$moHop   = $canEdit && ($editing !== null || isset($_GET['them']));
$dongUrl = '/quan-tri/gia-trong/goi';
?>
<?php if ($moHop): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $editing !== null ? 'Sửa gói chiết suất' : 'Thêm gói chiết suất',
        'phu'     => $editing !== null ? $editing['name'] : 'Gói mới đứng cuối danh sách — đổi vị trí bằng nút ↑↓ trên bảng.',
        'dongUrl' => $dongUrl,
        'rong'    => 'sm',
    ]); ?>

        <form method="post" action="/quan-tri/gia-trong/goi/luu" class="aform__grid" id="pkg-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <?php /* Ô này phân biệt SỬA với THÊM, và controller chốt ở nó chứ
                     không ở ô `id` bên dưới — readonly là chuyện của trình duyệt,
                     ai cũng gửi POST khác đi được. */ ?>
            <input type="hidden" name="cu" value="<?= e($editing['id'] ?? '') ?>">

            <div class="field">
                <label for="pkg-id">Mã gói *</label>
                <?php if ($editing !== null): ?>
                    <?php /* KHOÁ LẠI KHI SỬA. Mã là thứ order_items.lens_id của mọi
                             đơn đã bán và lens_prices.lens_package đang trỏ vào; đổi
                             nó là làm mồ côi bảng giá và cắt đứt đường lần về gói của
                             những đơn ấy, mà không có gì báo cho ai biết. Xem
                             LensModel::updatePackage(). */ ?>
                    <input type="text" id="pkg-id" value="<?= e($editing['id']) ?>" readonly>
                    <p class="field__hint">Mã không đổi được — đơn hàng cũ và bảng giá đang trỏ vào nó.</p>
                <?php else: ?>
                    <input type="text" id="pkg-id" name="id" required
                           maxlength="40" pattern="[a-z0-9][a-z0-9\-]*"
                           placeholder="clear-174">
                    <p class="field__hint">Chữ thường không dấu, số và gạch nối. Đặt xong không sửa được.</p>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="pkg-name">Tên gói *</label>
                <input type="text" id="pkg-name" name="name" required maxlength="160"
                       value="<?= e($editing['name'] ?? '') ?>"
                       placeholder="Tròng trắng 1.74">
                <p class="field__hint">Tên này được chép vào hoá đơn lúc khách đặt.</p>
            </div>

            <div class="field">
                <label for="pkg-sort">Thứ tự</label>
                <input type="number" id="pkg-sort" name="sort_order" min="0" max="32767"
                       value="<?= (int) ($editing['sort_order'] ?? $nextSort) ?>">
                <p class="field__hint">Số nhỏ đứng trước. Cách nhau 10 để sau này chèn vào giữa.</p>
            </div>

            <div class="field field--wide">
                <label for="pkg-desc">Mô tả</label>
                <input type="text" id="pkg-desc" name="description" maxlength="255"
                       value="<?= e($editing['description'] ?? '') ?>"
                       placeholder="Siêu mỏng, dành cho cận rất nặng (trên -8.00)">
                <?php /* Gợi ý viết theo DẢI ĐỘ chứ không theo thông số kỹ thuật:
                         khách đứng ở bước này vừa nhập xong số đo, nên câu hỏi
                         trong đầu họ là "độ của mình thì chọn cái nào", không phải
                         "chiết suất 1.74 nghĩa là gì". Cả năm câu mô tả đang có
                         đều viết theo lối đó. */ ?>
                <p class="field__hint">
                    Hiện dưới tên gói ở hộp mua hàng. Nên nói theo dải độ phù hợp —
                    đó là thứ khách đang cần biết ở bước đó. Để trống cũng được.
                </p>
            </div>

            <button type="submit" class="astatus__save">
                <?= $editing !== null ? 'Lưu thay đổi' : 'Thêm gói' ?>
            </button>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $dongUrl,
        'luuNhan' => $editing !== null ? 'Lưu thay đổi' : 'Thêm gói',
        'luuForm' => 'pkg-form',
    ]); ?>
<?php endif; ?>
