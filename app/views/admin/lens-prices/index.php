<?php

/**
 * admin/lens-prices/index.php — bảng giá tròng (/quan-tri/gia-trong).
 *
 * MỘT LƯỚI, MỘT NÚT LƯU. Không có nút "+ Thêm mới" và không có cột "Thao tác"
 * như các màn quản trị khác: hình dạng lưới do hai danh mục quyết định (kiểu
 * tròng × gói chiết suất), nên không ai thêm hay xoá một Ô — người ta mở ra,
 * sửa vài con số, rồi lưu. Lý do đầy đủ ghi ở LensPriceAdminController.
 *
 * Thêm hay bớt một HÀNG thì có: đó là thêm/bớt một gói chiết suất, và nó có
 * trang riêng — nút "Quản lý gói" ở đầu trang, /quan-tri/gia-trong/goi. Kiểu
 * tròng (các CỘT) thì vẫn khai trong config/taxonomy.php.
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
        <?php /* GÓI CHIẾT SUẤT ĐỨNG TRƯỚC — theo bản thiết kế, và khớp với
                 chính cái bảng bên dưới: cột đầu tiên của bảng là gói chiết
                 suất, ba cột sau là ba loại tròng. Đọc dòng dẫn theo thứ tự
                 ngược lại rồi nhìn xuống bảng là phải đảo lại trong đầu. */ ?>
        <p class="ahead__lead">
            <?= count($packages) ?> gói chiết suất × <?= $cols ?> loại tròng
            = <?= $cols * count($packages) ?> mức giá.
            <?php if ($missing > 0): ?>
                <strong><?= $missing ?> ô chưa có giá.</strong>
            <?php endif; ?>
        </p>
    </div>

    <div class="ahead__tools">
        <?php /* Đường sang danh mục gói. Đặt ở đây chứ không thành một mục
                 riêng trên thanh bên: thêm một gói bắt đầu từ chỗ nhìn thấy
                 bảng giá thiếu hàng, và kết thúc bằng việc quay lại đúng bảng
                 này để điền giá cho hàng vừa mọc ra. Hai trang là một việc.

                 Thanh bên chỉ đeo thêm mục cho những thứ mở hằng ngày; danh mục
                 gói thì vài tháng một lần. */ ?>
        <a href="/quan-tri/gia-trong/goi" class="astatus__save astatus__save--ghost"
           data-modal>Quản lý gói</a>

        <?php if (!$canEdit): ?>
            <p class="ahead__note">Bạn chỉ có quyền xem. Cần quyền quản lý để chỉnh sửa.</p>
        <?php endif; ?>
    </div>
</header>

<?php if (!$pkgEditable): ?>
    <?php /* Danh mục đang đọc từ đường lùi trong config. Nói ra ở ĐÂY chứ không
             chỉ ở trang kia: người mở bảng giá cần biết vì sao nút "Quản lý gói"
             bấm vào lại ra một trang hướng dẫn chạy file nâng cấp. */ ?>
    <div class="anote anote--alert">
        <p>
            Danh mục gói đang đọc từ <code>config/taxonomy.php</code> vì bảng
            <code>lens_packages</code> chưa được tạo — <strong>bảng giá bên dưới vẫn sửa
            và lưu bình thường</strong>, chỉ chưa thêm/sửa/xoá gói được.
            <a href="/quan-tri/gia-trong/goi">Xem cách nâng cấp</a>.
        </p>
    </div>
<?php endif; ?>

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
                                        <span class="aprice__echo"><?= (int) $val === 0 ? 'miễn phí' : money((int) $val) ?></span>
                                    <?php else: ?>
                                        <?php /* Ô trống KHÔNG im lặng — theo bản thiết kế.
                                                 Nó đọc như "quên điền", nhưng nó có nghĩa
                                                 thật và khách nhìn thấy nghĩa ấy. Nói ra
                                                 thì người điền biết mình đang để lại điều
                                                 gì trên trang bán hàng, và phân biệt được
                                                 với ô điền số 0 (miễn phí thật). */ ?>
                                        <span class="aprice__hint">khách thấy “Báo giá sau khi tư vấn”</span>
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

<?php
/*
 * HAI HỘP THOẠI CHỒNG NHAU — theo bản thiết kế "Giá tròng.dc.html".
 *
 *   hộp 1  danh mục gói chiết suất, nổi trên bảng giá  (?showPackages)
 *   hộp 2  form thêm/sửa MỘT gói, nổi trên hộp 1       (?them=1 · ?sua=<mã>)
 *
 * Chúng là ANH EM trong DOM, không lồng nhau: hộp 2 đứng sau nên nó nằm trên
 * theo thứ tự xếp lớp tự nhiên, không phải bịa thêm một tầng z-index thứ ba.
 * admin-modal.js gắp cả hai cùng lúc (querySelectorAll), nên bấm "Sửa" trong
 * hộp 1 vẫn mở tại chỗ chứ không tải lại trang.
 *
 * $showPackages chỉ bật khi controller đi qua packages() — cùng một view dựng
 * cả hai cảnh, xem khối chú thích ở LensPriceAdminController::packages().
 */
?>
<?php if ($showPackages): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => 'Gói chiết suất',
        'phu'     => 'Thêm một gói là bảng giá mọc thêm một hàng — nhớ quay lại điền giá.',
        'dongUrl' => '/quan-tri/gia-trong',
        'rong'    => 'xxl',
    ]); ?>

        <?php
        /* require thẳng chứ không partial(): ruột hộp cần $packages,
           $priceCounts, $canEdit, $editing — liệt kê lại từng cái cho partial
           là một danh sách sẽ lệch với thực tế ngay lần sửa đầu. require giữ
           nguyên phạm vi biến của file này. */
        require VIEWS_PATH . '/admin/lens-prices/packages.php';
        ?>

        </div>

        <?php /* Chân hộp CHỈ có lối ra: mọi thao tác (thêm, sửa, xoá, đổi thứ
                 tự) đều có nút riêng trong bảng và tự gửi ngay, nên không có
                 gì để "lưu" ở cấp hộp. Dùng modal-foot sẽ đẻ ra một nút Lưu
                 không trỏ vào form nào. */ ?>
        <div class="amodal__foot">
            <a class="astatus__save astatus__save--ghost" href="/quan-tri/gia-trong"
               data-modal-close>Đóng</a>
        </div>
    </div>
</div>

    <?php require VIEWS_PATH . '/admin/lens-prices/_goi-form.php'; ?>
<?php endif; ?>
