<?php

/**
 * admin/lens-prices/_goi-form.php — hộp thoại thêm/sửa MỘT gói chiết suất.
 *
 * Tách khỏi packages.php 2026-08-29, khi danh mục gói thành hộp thoại nổi trên
 * bảng giá. Hai hộp lồng nhau về mặt thị giác nhưng là ANH EM trong DOM: hộp
 * này đứng SAU hộp danh mục trong index.php nên nó nằm trên theo thứ tự xếp
 * lớp tự nhiên, không phải bịa thêm z-index cho một tầng thứ ba.
 *
 * Địa chỉ đóng là /quan-tri/gia-trong/goi — tức là quay về hộp danh mục, không
 * về bảng giá. Đóng form gói mà văng thẳng ra bảng giá thì thêm hai gói liên
 * tiếp phải mở lại danh mục mỗi lần.
 *
 * Biến cần có: $editing, $canEdit, $nextSort.
 */
?>
<?php
/*
 * Hộp mở ra theo ĐỊA CHỈ chứ không theo JavaScript: ?them=1 mở form trống,
 * ?sua=<id> mở form đã điền. Nút ✕, nút Huỷ và lớp nền mờ đều là <a> trỏ về
 * chính địa chỉ danh mục. Lý do đầy đủ ở khối .amodal trong admin.css.
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

        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $dongUrl,
        'luuNhan' => $editing !== null ? 'Lưu thay đổi' : 'Thêm gói',
        'luuForm' => 'pkg-form',
    ]); ?>
<?php endif; ?>
