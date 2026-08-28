<?php

/**
 * admin/vouchers/index.php — mã giảm giá
 *
 * Cùng khuôn với các màn hình CRUD quản trị khác (danh mục, cơ sở, bộ sưu tập):
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
    'title' => 'Mã giảm giá',
    /* Đếm luôn số ĐANG CHẠY — theo bản thiết kế. Tổng số mã một mình không nói
       được gì; con số đáng đọc là bao nhiêu chương trình khách đang dùng được
       ngay lúc này, vì mã hết hạn thì vẫn nằm trong bảng. */
    'lead' => count($vouchers) . ' mã · '
        . count(array_filter($vouchers, static fn (array $v): bool => $whyOff($v) === ''))
        . ' đang chạy',
    'base' => '/quan-tri/ma-giam-gia', 'canEdit' => $canEdit, 'editing' => $ed,
    'addLabel' => '+ Tạo mã mới',
]); ?>

<?php /*
 * CHƯA CÓ MÃ NÀO — khối riêng theo bản thiết kế (xem .aempty trong admin.css),
 * thay cho cái bảng rỗng bảy cột với một dòng <td colspan> ở giữa.
 *
 * Nút ở đây neo tới #form y như nút "+ Tạo mã mới" trên đầu trang, và cố ý
 * lặp lại nó: trang rỗng thì mọi thứ giữa hai cái nút đều biến mất, nên khối
 * này là chỗ mắt dừng lại. Bắt người ta ngước lên góc phải trên tìm nút là
 * thừa một bước ở đúng lần mở trang bỡ ngỡ nhất.
 *
 * Chỉ hiện nút khi có quyền sửa: người xem-thôi mà bấm vào chỉ tới một chỗ
 * trống, vì form bên dưới cũng nằm trong $canEdit.
 */ ?>
<?php if ($vouchers === []): ?>
    <div class="aempty">
        <p class="aempty__title">Chưa có mã giảm giá nào</p>
        <p class="aempty__note">Tạo mã đầu tiên để chạy chương trình ưu đãi cho khách.</p>
        <?php if ($canEdit): ?>
            <a href="/quan-tri/ma-giam-gia?them=1" class="astatus__save" data-modal>Tạo mã đầu tiên</a>
        <?php endif; ?>
    </div>
<?php else: ?>

<?php
/*
 * THẺ, KHÔNG PHẢI BẢNG — đổi theo bản thiết kế "Mã giảm giá.dc.html".
 *
 * Bảng cũ có tám cột, trong đó năm cột ("Giảm", "Đơn tối thiểu", "Hạn dùng",
 * "Đã dùng", "Trạng thái") đều là những mẩu ngắn thuộc về CÙNG MỘT chương
 * trình khuyến mãi. Xé chúng ra tám ô rời rồi bắt mắt ghép lại theo hàng
 * ngang là việc thừa: không ai so "đơn tối thiểu của VIN10" với "đơn tối
 * thiểu của FREESHIP" — người ta đọc trọn một mã rồi quyết định bật hay tắt.
 *
 * Thẻ gom trọn một mã vào một khối, mã in to bằng chữ đẳng khoảng ở góc trên
 * (đó là thứ khách gõ vào ô nhập, nên nó là danh tính của cả thẻ), và ba con
 * số vận hành xếp thành một dải dưới đường kẻ.
 *
 * Bộ lọc theo trạng thái LỌC NGAY TRONG PHP: cả bảng mã của một cửa hàng
 * kính chưa tới vài chục dòng và controller đã nạp hết, nên thêm một vòng
 * lặp rẻ hơn hẳn một truy vấn nữa — mà trạng thái "hết lượt" thì cũng chỉ
 * tính được sau khi đã có `used_count` trong tay.
 */
$loc = (string) ($_GET['loc'] ?? '');

if (!in_array($loc, ['chay', 'tat', 'het'], true)) {
    $loc = '';
}

$khop = static function (array $v) use ($whyOff, $loc): bool {
    $off = $whyOff($v);

    return match ($loc) {
        'chay' => $off === '',
        'tat'  => $off === 'Đã tắt',
        'het'  => $off === 'Hết hạn' || $off === 'Hết lượt',
        default => true,
    };
};

$dem = static function (string $key) use ($vouchers, $whyOff): int {
    $n = 0;

    foreach ($vouchers as $v) {
        $off = $whyOff($v);
        $n  += match ($key) {
            'chay' => $off === '' ? 1 : 0,
            'tat'  => $off === 'Đã tắt' ? 1 : 0,
            'het'  => ($off === 'Hết hạn' || $off === 'Hết lượt') ? 1 : 0,
            default => 1,
        };
    }

    return $n;
};

$hienThi = array_values(array_filter($vouchers, $khop));
?>
<nav class="atabs" aria-label="Lọc theo trạng thái mã">
    <?php foreach (['' => 'Tất cả', 'chay' => 'Đang chạy', 'tat' => 'Đang tắt', 'het' => 'Hết hạn / hết lượt'] as $key => $nhan): ?>
        <a class="atabs__item<?= $loc === $key ? ' is-active' : '' ?>"
           href="/quan-tri/ma-giam-gia<?= $key === '' ? '' : '?loc=' . e((string) $key) ?>"
           <?= $loc === $key ? 'aria-current="true"' : '' ?>>
            <?= e($nhan) ?> <span class="atabs__num"><?= $dem((string) $key) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($hienThi === []): ?>
    <p class="apanel__empty">Không có mã nào khớp bộ lọc.</p>
<?php else: ?>
<div class="avc">
    <?php foreach ($hienThi as $v): ?>
        <?php
        $off     = $whyOff($v);
        $dangChay = $off === '';
        ?>
        <article class="avc__card<?= $dangChay ? '' : ' is-off' ?>">
            <div class="avc__top">
                <div class="avc__id">
                    <div class="avc__codeline">
                        <span class="avc__code"><?= e($v['code']) ?></span>
                        <?php /* Nhãn ngắn ("-10%", "Free ship") là thứ khách nhìn thấy
                                 trong ví mã của họ — hổ phách để nó tách khỏi mã đỏ
                                 ngay bên trái mà vẫn không đọc ra là một trạng thái. */ ?>
                        <span class="avc__tag"><?= e($v['tag']) ?></span>
                    </div>

                    <p class="avc__title"><?= e($v['title']) ?></p>

                    <?php /* Câu điều kiện là CHỮ CHO KHÁCH ĐỌC; con số thật nằm ở dải
                             bên dưới và trong form sửa. Không có câu nào thì in gạch
                             ngang chứ không bỏ trống — một khoảng trống ở đây đọc ra
                             như "quên điền", mà "không có điều kiện" là hợp lệ. */ ?>
                    <p class="avc__cond"><?= e($v['condition_text'] ?: '—') ?></p>
                </div>

                <span class="badge badge--<?= $dangChay ? 'in_stock' : ($off === 'Đã tắt' ? 'neutral' : 'out_of_stock') ?>">
                    <?= $dangChay ? 'Đang chạy' : e($off) ?>
                </span>
            </div>

            <?php /* Ba con số vận hành: đã dùng bao nhiêu, còn hạn tới bao giờ, ai
                     dùng được. Đây là thứ người ta liếc trước khi quyết định tắt
                     hay gia hạn một chương trình. */ ?>
            <div class="avc__stats">
                <span>
                    <strong><?= (int) $v['used_count'] ?></strong> lượt dùng<?= $v['max_uses'] !== null ? ' / ' . (int) $v['max_uses'] : '' ?>
                </span>
                <span><?= $v['expires_at'] !== null ? 'HSD ' . e(formatDate($v['expires_at'])) : 'Không hạn' ?></span>
                <span><?= (int) $v['is_public'] === 1
                    ? 'Công khai'
                    : 'Mã riêng · đã phát cho ' . (int) $v['holder_count'] . ' khách' ?></span>
                <span><?= e($amountText($v)) ?><?= (int) $v['min_order'] > 0
                    ? ' · đơn từ ' . money((int) $v['min_order'])
                    : '' ?></span>
            </div>

            <?php if ($canEdit): ?>
                <div class="avc__acts arow-actions">
                    <?php /* Bật/tắt đứng đầu cụm: đó là thao tác thường gặp nhất ở
                             màn này (dừng một chương trình, chạy lại mã cũ theo mùa),
                             và nó là việc duy nhất ở đây lùi lại được bằng đúng cú
                             bấm vừa rồi. Sửa thì mở form, xoá thì mất hẳn.

                             Đọc `is_active` chứ không đọc $off: một mã HẾT HẠN vẫn
                             đang "bật", và nút phải nói đúng cái nó sắp làm. Tắt một
                             mã hết hạn nghe thừa nhưng có thật — người ta dọn bảng
                             trước khi gia hạn. */ ?>
                    <form method="post" action="/quan-tri/ma-giam-gia/bat-tat">
                        <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="id" value="<?= e($v['id']) ?>">
                        <button type="submit" class="atoggle<?= (int) $v['is_active'] === 1 ? '' : ' atoggle--on' ?>">
                            <?= (int) $v['is_active'] === 1 ? 'Tắt mã' : 'Bật mã' ?>
                        </button>
                    </form>

                    <a href="/quan-tri/ma-giam-gia?sua=<?= e($v['id']) ?>" data-modal>Sửa</a>

                    <?php if ((int) $v['is_public'] !== 1): ?>
                        <?php
                        /* KHÔNG phải thao tác xoá, nên nút đồng ý không ghi "Xoá"
                           mà ghi đúng việc sắp làm. Phát mã cho toàn bộ khách cũng
                           không lùi lại được — mã đã vào ví của hàng nghìn người. */
                        $hoiPhat = sprintf(
                            'Phát mã “%s” cho TẤT CẢ khách hàng? Việc này không thu hồi lại được.',
                            $v['code']
                        );
                        ?>
                        <form method="post" action="/quan-tri/ma-giam-gia/phat"
                              data-confirm="<?= e($hoiPhat) ?>"
                              data-confirm-title="Phát mã cho tất cả?"
                              data-confirm-ok="Phát mã"
                              data-confirm-cancel="Không phát"
                              onsubmit="return confirm('<?= e($hoiPhat) ?>')">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= e($v['id']) ?>">
                            <button type="submit" class="arow-del arow-del--calm">Phát cho tất cả</button>
                        </form>
                    <?php endif; ?>

                    <?php if ((int) $v['order_count'] === 0): ?>
                        <?php $hoiXoaMa = sprintf('Xoá mã “%s”?', $v['code']); ?>
                        <form method="post" action="/quan-tri/ma-giam-gia/xoa"
                              data-confirm="<?= e($hoiXoaMa) ?>"
                              data-confirm-title="Xoá mã giảm giá?"
                              data-confirm-ok="Xoá"
                              onsubmit="return confirm('<?= e($hoiXoaMa) ?>')">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= e($v['id']) ?>">
                            <button type="submit" class="arow-del">Xoá</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
/*
 * FORM THÊM/SỬA LÀ MỘT HỘP THOẠI NỔI — theo bản thiết kế.
 *
 * Hộp mở ra theo ĐỊA CHỈ chứ không theo JavaScript: ?them=1 mở form trống,
 * ?sua=<id> mở form đã điền. Nút ✕, nút Huỷ và lớp nền mờ đều là <a> trỏ về
 * chính trang này. Lý do đầy đủ ở khối .amodal trong admin.css.
 */
$moHop   = $canEdit && ($ed !== null || isset($_GET['them']));
$dongUrl = '/quan-tri/ma-giam-gia';
?>
<?php if ($moHop): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $ed !== null ? 'Sửa mã giảm giá' : 'Tạo mã giảm giá mới',
        'phu'     => $ed !== null ? $ed['code'] : 'Ba ô đầu là thứ khách nhìn thấy — điền xong ba ô là mã dùng được.',
        'dongUrl' => $dongUrl,
        'rong'    => 'lg',
    ]); ?>

        <form method="post" action="/quan-tri/ma-giam-gia/luu" class="aform__grid" id="voucher-form">
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

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_reward"
                           <?= ($ed !== null && !empty($ed['is_reward'])) ? 'checked' : '' ?>>
                    Tặng cho khách chuyển khoản đủ 100%
                </label>
                <p class="field__hint">
                    Đơn có cắt tròng được chọn chuyển 30% tiền cọc hoặc chuyển đủ.
                    Chọn chuyển đủ thì khách được tặng mã này ngay khi tiền về —
                    không cần phát tay. <strong>Chỉ một mã</strong> được bật:
                    tick vào đây là mã đang bật trước đó tự tắt.
                    Nên dùng với mã riêng (bỏ tick "công khai") để nó thật sự là quà.
                </p>
            </div>

            <button type="submit" class="astatus__save">
                <?= $ed !== null ? 'Lưu thay đổi' : 'Tạo mã' ?>
            </button>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $dongUrl,
        'luuNhan' => $ed !== null ? 'Lưu thay đổi' : 'Tạo mã',
        'luuForm' => 'voucher-form',
    ]); ?>
<?php endif; ?>
