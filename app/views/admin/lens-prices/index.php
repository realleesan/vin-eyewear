<?php

/**
 * admin/lens-prices/index.php — bảng giá tròng (/quan-tri/gia-trong).
 *
 * MỘT LƯỚI, MỘT NÚT LƯU. Không có nút "+ Thêm mới" và không có cột "Thao tác"
 * như các màn quản trị khác: lưới này có hình dạng cố định (kiểu tròng × gói
 * chiết suất, cả hai khai trong config/taxonomy.php), nên không ai thêm hay
 * xoá một ô — người ta mở ra, sửa vài con số, rồi lưu. Lý do đầy đủ ghi ở
 * LensPriceAdminController.
 *
 * Vì thế trang này KHÔNG dùng partial crud-head: nó không có $editing, không
 * có nút thêm mới, và dòng dẫn phải nói một chuyện khác hẳn.
 */

$cols = count($types);

/* Đếm ô chưa có giá để nói thẳng ở đầu trang. Một ô trống không phải lỗi —
   nó nghĩa là lựa chọn đó chưa bán được và khách sẽ thấy "Báo giá sau" — nhưng
   nó gần như luôn là thứ bị bỏ quên chứ không phải chủ ý. */
$missing = 0;

foreach ($types as $t) {
    foreach ($packages as $p) {
        if (!isset($prices[$t['id']][$p['id']])) {
            $missing++;
        }
    }
}
?>

<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Bảng giá tròng</h1>
        <p class="ahead__lead">
            <?= $cols ?> loại tròng × <?= count($packages) ?> gói chiết suất
            = <?= $cols * count($packages) ?> mức giá.
            <?php if ($missing > 0): ?>
                <strong><?= $missing ?> ô chưa có giá.</strong>
            <?php endif; ?>
        </p>
    </div>

    <?php if (!$canEdit): ?>
        <p class="ahead__note">Bạn chỉ có quyền xem. Cần quyền quản lý để chỉnh sửa.</p>
    <?php endif; ?>
</header>

<div class="anote">
    <p>
        Giá ở đây là <strong>tiền tròng cộng thêm</strong> vào giá gọng khi khách chọn
        cắt tròng — không phải giá bán lẻ của cả cặp kính.
    </p>
    <p>
        Ô để trống nghĩa là <em>chưa định giá</em>: khách chọn đúng lựa chọn đó sẽ thấy
        “Báo giá sau khi tư vấn” và phần tròng không cộng tiền vào đơn. Muốn miễn phí
        thật thì điền số <code>0</code>.
    </p>
    <?php foreach ($quotedTypes as $qt): ?>
        <p>
            <strong><?= e($qt['name']) ?></strong> không có trong bảng này —
            <?= e(lcfirst($qt['desc'])) ?>.
        </p>
    <?php endforeach; ?>
</div>

<form method="post" action="/quan-tri/gia-trong/luu">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">

    <div class="atable-wrap">
        <table class="atable atable--full aprice">
            <caption class="sr-only">Giá tròng theo loại tròng và gói chiết suất</caption>
            <thead>
                <tr>
                    <th scope="col">Gói chiết suất</th>
                    <?php foreach ($types as $t): ?>
                        <th scope="col" title="<?= e($t['desc']) ?>"><?= e($t['name']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $p): ?>
                    <tr>
                        <th scope="row">
                            <?= e($p['name']) ?>
                            <span class="atable__sub"><?= e($p['desc']) ?></span>
                        </th>

                        <?php foreach ($types as $t): ?>
                            <?php
                            $val = $prices[$t['id']][$p['id']] ?? null;
                            $id  = 'gia-' . $t['id'] . '-' . $p['id'];
                            ?>
                            <td class="aprice__cell<?= $val === null ? ' is-empty' : '' ?>">
                                <?php /* Nhãn ẩn ghép đủ hai chiều: người dùng trình đọc
                                         màn hình nhảy thẳng vào ô số thì <th> hàng và
                                         <th> cột không phải lúc nào cũng được đọc kèm,
                                         mà "1400000" đứng một mình thì không biết là
                                         giá của lựa chọn nào. */ ?>
                                <label class="sr-only" for="<?= e($id) ?>">
                                    <?= e($t['name']) ?> — <?= e($p['name']) ?>
                                </label>

                                <?php if ($canEdit): ?>
                                    <input type="number" id="<?= e($id) ?>"
                                           name="gia[<?= e($t['id']) ?>][<?= e($p['id']) ?>]"
                                           min="0" step="1000" inputmode="numeric"
                                           placeholder="chưa định giá"
                                           value="<?= $val === null ? '' : (int) $val ?>">
                                    <?php /* Số đã định dạng ngay dưới ô: "1400000" gõ vào
                                             ô số không có dấu phân cách, mà thiếu hay thừa
                                             một số 0 trong bảng giá là chuyện phải thấy
                                             ngay. Chỉ hiện khi đã có giá đã lưu. */ ?>
                                    <?php if ($val !== null): ?>
                                        <span class="aprice__echo"><?= money((int) $val) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="aprice__ro">
                                        <?= $val === null ? '—' : money((int) $val) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canEdit): ?>
        <?php /* Nút này KHÔNG nằm cạnh ô chọn tự gửi nào, nên luật
                 `.js form:has(select[data-autosubmit]) .astatus__save` không
                 chạm tới — xem khối chú thích ở assets/css/admin.css. */ ?>
        <div class="aprice__actions">
            <button type="submit" class="astatus__save">Lưu bảng giá</button>
            <span class="ainv__hint">Sửa bao nhiêu ô cũng được, một lần lưu là xong cả bảng.</span>
        </div>
    <?php endif; ?>
</form>
