<?php

/**
 * admin/stores/index.php — cơ sở cửa hàng
 * Port từ quan-tri/co-so.tsx + admin-store-form.tsx.
 */

$ed = $editing;
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Cơ sở',
    /* "đang nhận lịch hẹn", không phải "cơ sở" trơn — theo bản thiết kế.
       Cơ sở tắt cờ hoạt động vẫn nằm trong bảng nhưng không hiện ở trang đặt
       lịch, nên con số đáng đọc là số cơ sở KHÁCH ĐẶT ĐƯỢC, không phải số
       dòng trong bảng. Hai số này lệch nhau đúng vào lúc một cơ sở đang sửa
       chữa — cũng là lúc người quản lý cần thấy sự lệch ấy. */
    'lead'  => count(array_filter($stores, static fn (array $s): bool => !empty($s['is_active'])))
               . ' cơ sở đang nhận lịch hẹn',
    'base' => '/quan-tri/co-so', 'canEdit' => $canEdit, 'editing' => $ed,
    'addLabel' => '+ Thêm cơ sở',
]); ?>

<?php
/*
 * THẺ, KHÔNG PHẢI BẢNG — đổi theo bản thiết kế "Cơ sở.dc.html".
 *
 * Cửa hàng có hai cơ sở. Một cái bảng sáu cột cho hai dòng là hình thức sai
 * ngay từ đầu: bảng dựng ra để SO SÁNH nhiều dòng theo cột, mà ở đây không ai
 * so địa chỉ cơ sở này với cơ sở kia — người ta ĐỌC một cơ sở, trọn vẹn, để
 * kiểm xem số điện thoại và giờ mở cửa in ra ngoài có đúng không.
 *
 * Thẻ cho phép mỗi trường có nhãn riêng đứng cạnh ("ĐỊA CHỈ", "GIỜ MỞ"), thứ
 * mà bảng phải nhét hết lên một hàng tiêu đề ở tận trên cùng — đọc tới dòng
 * thứ hai là đã phải nhớ lại cột nào là cột nào.
 *
 * auto-fill minmax(400px,1fr): hai cơ sở nằm cạnh nhau trên màn rộng, xếp dọc
 * trên màn hẹp, và thêm cơ sở thứ ba thứ tư thì lưới tự lo — không phải chỉnh
 * gì cả.
 */
?>
<div class="acs">
    <?php foreach ($stores as $s): ?>
        <?php /* is-off làm mờ cả thẻ. Cơ sở tạm đóng vẫn phải đọc được (đó là
                 chỗ người ta vào để mở lại), nhưng nó không nên tranh chú ý
                 với cơ sở đang nhận khách. */ ?>
        <article class="acs__card<?= $s['is_active'] ? '' : ' is-off' ?>">
            <div class="acs__top">
                <span class="acs__code"><?= e($s['code']) ?></span>
                <span class="badge badge--<?= $s['is_active'] ? 'in_stock' : 'neutral' ?>">
                    <?= $s['is_active'] ? 'Đang mở' : 'Tạm đóng' ?>
                </span>
            </div>

            <h2 class="acs__name"><?= e($s['name']) ?></h2>

            <dl class="acs__rows">
                <div class="acs__row">
                    <dt>Địa chỉ</dt>
                    <dd><?= e($s['address']) ?></dd>
                </div>

                <div class="acs__row">
                    <dt>Điện thoại</dt>
                    <dd>
                        <?php if (!empty($s['phone'])): ?>
                            <?php /* Bấm gọi thẳng từ máy ở quầy — cùng lối với cột
                                     điện thoại ở bảng yêu cầu liên hệ. */ ?>
                            <a class="acs__tel" href="tel:<?= e(preg_replace('/\D/', '', $s['phone'])) ?>"><?= e($s['phone']) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>
                </div>

                <div class="acs__row">
                    <dt>Giờ mở</dt>
                    <dd><?= e($s['open_hours'] ?: '—') ?></dd>
                </div>

                <?php /* DÒNG BẢN ĐỒ nói CÓ HAY KHÔNG, không in ra địa chỉ nhúng.
                         Chuỗi nhúng của Google dài vài trăm ký tự và không ai đọc
                         nó bằng mắt; thứ người ta cần biết ở đây chỉ là trang cơ sở
                         bên phía khách có bản đồ hay đang trống một mảng. */ ?>
                <div class="acs__row">
                    <dt>Bản đồ</dt>
                    <dd class="acs__map<?= !empty($s['map_url']) ? ' is-on' : '' ?>">
                        <?= !empty($s['map_url']) ? 'Đã nhúng Google Maps' : 'Chưa có bản đồ' ?>
                    </dd>
                </div>
            </dl>

            <?php if ($canEdit): ?>
                <div class="acs__acts arow-actions">
                    <?php /* NÚT ĐỔI TRẠNG THÁI ĐỨNG ĐẦU CỤM, trước cả "Sửa".

                             Đó là thao tác duy nhất ở màn này làm hằng tuần: cơ sở
                             sửa chữa vài hôm thì tạm đóng, xong thì mở lại. Sửa địa
                             chỉ hay số điện thoại thì vài năm một lần.

                             Dáng nút đảo theo trạng thái — xem .atoggle trong
                             admin.css: đang mở thì nút lặng (việc nó làm là ĐÓNG),
                             đang đóng thì nút đỏ (việc nó làm là MỞ LẠI, và đó gần
                             như luôn là thứ người mở trang đang tìm). */ ?>
                    <form method="post" action="/quan-tri/co-so/hoat-dong">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                        <button type="submit" class="atoggle<?= $s['is_active'] ? '' : ' atoggle--on' ?>">
                            <?= $s['is_active'] ? 'Tạm đóng' : 'Mở lại' ?>
                        </button>
                    </form>

                    <a href="/quan-tri/co-so?sua=<?= e($s['id']) ?>#form">Sửa</a>
                    <?php $hoi = sprintf('Xoá cơ sở “%s”?', $s['name']); ?>
                    <form method="post" action="/quan-tri/co-so/xoa"
                          data-confirm="<?= e($hoi) ?>"
                          data-confirm-title="Xoá cơ sở?"
                          data-confirm-ok="Xoá"
                          onsubmit="return confirm('<?= e($hoi) ?>')">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                        <button type="submit" class="arow-del">Xoá</button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($canEdit): ?>
    <section class="aform" id="form" aria-labelledby="form-title">
        <h2 id="form-title" class="apanel__title">
            <?= $ed !== null ? 'Sửa cơ sở: ' . e($ed['name']) : 'Thêm cơ sở mới' ?>
        </h2>

        <form method="post" action="/quan-tri/co-so/luu" class="aform__grid">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field">
                <label for="code">Mã cơ sở *</label>
                <input type="text" id="code" name="code" required maxlength="40"
                       pattern="[A-Za-z0-9_]{2,40}" placeholder="TAYHO"
                       value="<?= e($ed['code'] ?? '') ?>">
                <p class="field__hint">Chữ, số và gạch dưới. Tự chuyển thành IN HOA.</p>
            </div>

            <div class="field">
                <label for="name">Tên cơ sở *</label>
                <input type="text" id="name" name="name" required maxlength="255"
                       value="<?= e($ed['name'] ?? '') ?>">
            </div>

            <div class="field field--wide">
                <label for="address">Địa chỉ *</label>
                <input type="text" id="address" name="address" required
                       value="<?= e($ed['address'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="phone">Điện thoại</label>
                <input type="tel" id="phone" name="phone" value="<?= e($ed['phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="open_hours">Giờ mở cửa</label>
                <input type="text" id="open_hours" name="open_hours"
                       placeholder="08:00 - 21:00 hàng ngày"
                       value="<?= e($ed['open_hours'] ?? '') ?>">
            </div>

            <div class="field field--wide">
                <label for="map_url">Liên kết nhúng Google Maps</label>
                <input type="url" id="map_url" name="map_url"
                       value="<?= e($ed['map_url'] ?? '') ?>">
                <p class="field__hint">Chỉ nhận địa chỉ thuộc google.com.</p>
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_active" <?= ($ed === null || $ed['is_active']) ? 'checked' : '' ?>>
                    Đang hoạt động (nhận lịch hẹn)
                </label>
            </div>

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm cơ sở' ?></button>
        </form>
    </section>
<?php endif; ?>
