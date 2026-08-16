<?php

/**
 * admin/vouchers/index.php — mã giảm giá
 *
 * Cùng khuôn với các màn hình CRUD quản trị khác (danh mục, cơ sở, sự kiện):
 * bảng danh sách ở trên, form thêm/sửa neo ở #form bên dưới.
 */

$ed    = $editing;
$today = date('Y-m-d');

/** Số tiền hoặc phần trăm, tuỳ kiểu — cột "Giảm" của bảng. */
$amountText = static function (array $v): string {
    switch ($v['discount_type']) {
        case 'percent':
            $out = $v['discount_value'] . '%';
            if ($v['max_discount'] !== null) {
                $out .= ' (tối đa ' . money((int) $v['max_discount']) . ')';
            }
            return $out;

        case 'amount':
            return money((int) $v['discount_value']);

        case 'shipping':
            return 'Miễn phí ship';
    }

    return '—';
};

/**
 * Vì sao một mã không dùng được — trả về chuỗi rỗng nếu đang dùng được.
 *
 * Gộp ba lý do vào một chỗ để cột trạng thái nói ĐÚNG nguyên nhân, thay vì chỉ
 * hiện "tắt" cho cả ba. Người quản trị nhìn bảng là biết phải sửa gì.
 */
$whyOff = static function (array $v) use ($today): string {
    if ((int) $v['is_active'] !== 1) {
        return 'Đã tắt';
    }

    if ($v['expires_at'] !== null && $v['expires_at'] < $today) {
        return 'Hết hạn';
    }

    if ($v['max_uses'] !== null && (int) $v['used_count'] >= (int) $v['max_uses']) {
        return 'Hết lượt';
    }

    return '';
};
?>

<?php partial('admin/_layout/crud-head', [
    'title' => 'Mã giảm giá', 'lead' => count($vouchers) . ' mã',
    'base' => '/quan-tri/ma-giam-gia', 'canEdit' => $canEdit, 'editing' => $ed,
]); ?>

<div class="atable-wrap">
    <table class="atable atable--full">
        <thead>
            <tr>
                <th scope="col">Mã</th>
                <th scope="col">Chương trình</th>
                <th scope="col">Giảm</th>
                <th scope="col">Đơn tối thiểu</th>
                <th scope="col">Hạn dùng</th>
                <th scope="col">Đã dùng</th>
                <th scope="col">Trạng thái</th>
                <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($vouchers === []): ?>
                <tr>
                    <td colspan="<?= $canEdit ? 8 : 7 ?>">
                        Chưa có mã nào. Tạo mã đầu tiên bằng form bên dưới.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($vouchers as $v): ?>
                <?php $off = $whyOff($v); ?>
                <tr>
                    <td>
                        <code><?= e($v['code']) ?></code>
                        <span class="atable__sub"><?= e($v['tag']) ?></span>
                    </td>
                    <td class="atable__msg">
                        <?= e($v['title']) ?>
                        <?php if (!empty($v['condition_text'])): ?>
                            <span class="atable__sub"><?= e($v['condition_text']) ?></span>
                        <?php endif; ?>
                        <?php if ((int) $v['is_public'] !== 1): ?>
                            <span class="atable__sub">
                                Mã riêng · đã phát cho <?= (int) $v['holder_count'] ?> khách
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($amountText($v)) ?></td>
                    <td><?= (int) $v['min_order'] > 0 ? money((int) $v['min_order']) : '—' ?></td>
                    <td><?= $v['expires_at'] !== null ? e(formatDate($v['expires_at'])) : 'Không hạn' ?></td>
                    <td>
                        <?= (int) $v['used_count'] ?><?= $v['max_uses'] !== null ? ' / ' . (int) $v['max_uses'] : '' ?>
                        <?php if ((int) $v['order_count'] > 0): ?>
                            <span class="atable__sub"><?= (int) $v['order_count'] ?> đơn</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge--<?= $off === '' ? 'in_stock' : 'cancelled' ?>">
                            <?= $off === '' ? 'Đang chạy' : e($off) ?>
                        </span>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="arow-actions">
                            <a href="/quan-tri/ma-giam-gia?sua=<?= e($v['id']) ?>#form">Sửa</a>

                            <?php if ((int) $v['is_public'] !== 1): ?>
                                <form method="post" action="/quan-tri/ma-giam-gia/phat"
                                      onsubmit="return confirm('Phát mã &quot;<?= e($v['code']) ?>&quot; cho TẤT CẢ khách hàng?')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($v['id']) ?>">
                                    <button type="submit" class="arow-del arow-del--calm">Phát cho tất cả</button>
                                </form>
                            <?php endif; ?>

                            <?php if ((int) $v['order_count'] === 0): ?>
                                <form method="post" action="/quan-tri/ma-giam-gia/xoa"
                                      onsubmit="return confirm('Xoá mã &quot;<?= e($v['code']) ?>&quot;?')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($v['id']) ?>">
                                    <button type="submit" class="arow-del">Xoá</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($canEdit): ?>
    <section class="aform" id="form" aria-labelledby="form-title">
        <h2 id="form-title" class="apanel__title">
            <?= $ed !== null ? 'Sửa mã: ' . e($ed['code']) : 'Tạo mã giảm giá mới' ?>
        </h2>

        <form method="post" action="/quan-tri/ma-giam-gia/luu" class="aform__grid">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field">
                <label for="code">Mã *</label>
                <input type="text" id="code" name="code" required maxlength="40"
                       pattern="[A-Za-z0-9_]{3,40}" placeholder="VIN10"
                       value="<?= e($ed['code'] ?? '') ?>">
                <p class="field__hint">Chữ, số, gạch dưới. Tự chuyển thành IN HOA.</p>
            </div>

            <div class="field">
                <label for="tag">Nhãn ngắn *</label>
                <input type="text" id="tag" name="tag" required maxlength="16"
                       placeholder="-10%"
                       value="<?= e($ed['tag'] ?? '') ?>">
                <p class="field__hint">In trong ô vuông ở trang "Ưu đãi của tôi".</p>
            </div>

            <div class="field field--wide">
                <label for="title">Tên chương trình *</label>
                <input type="text" id="title" name="title" required maxlength="255"
                       placeholder="Giảm 10% gọng kính chính hãng"
                       value="<?= e($ed['title'] ?? '') ?>">
            </div>

            <div class="field field--wide">
                <label for="condition_text">Mô tả điều kiện</label>
                <input type="text" id="condition_text" name="condition_text" maxlength="255"
                       placeholder="Đơn tối thiểu 2.000.000₫ · Giảm tối đa 500.000₫"
                       value="<?= e($ed['condition_text'] ?? '') ?>">
                <p class="field__hint">
                    Câu này hiện cho khách đọc. Nó KHÔNG tự áp dụng — các con số
                    thật lấy từ những ô bên dưới.
                </p>
            </div>

            <div class="field">
                <label for="discount_type">Kiểu giảm *</label>
                <select id="discount_type" name="discount_type" required>
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?= e($key) ?>"
                                <?= ($ed['discount_type'] ?? 'percent') === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="discount_value">Giá trị giảm</label>
                <input type="number" id="discount_value" name="discount_value" min="0" step="1"
                       value="<?= e($ed['discount_value'] ?? '') ?>">
                <p class="field__hint">
                    Phần trăm thì nhập 1–90. Số tiền thì nhập đồng (vd 100000).
                    Miễn phí ship thì bỏ trống.
                </p>
            </div>

            <div class="field">
                <label for="min_order">Đơn tối thiểu (₫)</label>
                <input type="number" id="min_order" name="min_order" min="0" step="1000"
                       value="<?= e($ed['min_order'] ?? '0') ?>">
                <p class="field__hint">0 = không yêu cầu.</p>
            </div>

            <div class="field">
                <label for="max_discount">Giảm tối đa (₫)</label>
                <input type="number" id="max_discount" name="max_discount" min="0" step="1000"
                       value="<?= e($ed['max_discount'] ?? '') ?>">
                <p class="field__hint">Chỉ dùng cho kiểu phần trăm. Bỏ trống = không chặn trần.</p>
            </div>

            <div class="field">
                <label for="expires_at">Hạn dùng</label>
                <input type="date" id="expires_at" name="expires_at"
                       value="<?= e($ed['expires_at'] ?? '') ?>">
                <p class="field__hint">Dùng được tới hết ngày này. Bỏ trống = không hạn.</p>
            </div>

            <div class="field">
                <label for="max_uses">Giới hạn lượt dùng</label>
                <input type="number" id="max_uses" name="max_uses" min="1" step="1"
                       value="<?= e($ed['max_uses'] ?? '') ?>">
                <p class="field__hint">
                    Bỏ trống = không giới hạn.
                    <?php if ($ed !== null): ?>
                        Đã dùng <?= (int) $ed['used_count'] ?> lượt.
                    <?php endif; ?>
                </p>
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_active"
                           <?= ($ed === null || $ed['is_active']) ? 'checked' : '' ?>>
                    Đang bật
                </label>
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_public"
                           <?= ($ed === null || $ed['is_public']) ? 'checked' : '' ?>>
                    Mã công khai (ai gõ đúng cũng dùng được)
                </label>
                <p class="field__hint">
                    Bỏ tick = mã riêng, chỉ khách đã được phát mới dùng được.
                    Phát bằng nút "Phát cho tất cả" ở bảng trên.
                </p>
            </div>

            <button type="submit" class="astatus__save">
                <?= $ed !== null ? 'Lưu thay đổi' : 'Tạo mã' ?>
            </button>
        </form>
    </section>
<?php endif; ?>
