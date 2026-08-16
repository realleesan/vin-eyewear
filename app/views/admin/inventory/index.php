<?php

/**
 * admin/inventory/index.php — tồn kho
 * Port từ src/routes/_authenticated/quan-tri/ton-kho.tsx.
 *
 * Mỗi dòng là một form riêng để sửa được từng sản phẩm mà không phải gửi cả
 * bảng. Đổi một dòng chỉ ảnh hưởng dòng đó — hai người cùng nhập hàng không
 * ghi đè lên nhau.
 */

$tabs = [
    ''    => ['Tất cả',       (int) $counts['total']],
    'low' => ['Sắp hết (≤' . $low . ')', (int) $counts['low_stock']],
    'out' => ['Hết hàng',     (int) $counts['out_stock']],
];
?>
<header class="ahead">
    <h1 class="ahead__title">Tồn kho</h1>
    <p class="ahead__lead">Cập nhật số lượng sau khi nhập hàng hoặc kiểm kê.</p>
</header>

<nav class="atabs" aria-label="Lọc tồn kho">
    <?php foreach ($tabs as $key => [$label, $count]): ?>
        <a class="atabs__item<?= $filter === $key ? ' is-active' : '' ?>"
           href="/quan-tri/ton-kho<?= $key === '' ? '' : '?loc=' . e($key) ?>"
           <?= $filter === $key ? 'aria-current="true"' : '' ?>>
            <?= e($label) ?> <span class="atabs__num"><?= $count ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($products === []): ?>
    <p class="apanel__empty">Không có sản phẩm nào khớp bộ lọc.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">SKU</th>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col">Giá</th>
                    <th scope="col">Tồn hiện tại</th>
                    <th scope="col">Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <?php
                    $qty     = (int) $p['stock_quantity'];
                    $rowFlag = $qty <= 0 ? ' is-out' : ($qty <= $low ? ' is-low' : '');
                    ?>
                    <tr class="ainv<?= $rowFlag ?>">
                        <td><code><?= e($p['sku']) ?></code></td>
                        <td>
                            <a href="/san-pham/<?= e(rawurlencode($p['slug'])) ?>" target="_blank" rel="noopener"><?= e($p['name']) ?></a>
                            <span class="atable__sub"><?= e($p['brand'] ?? '—') ?></span>
                        </td>
                        <td class="num"><?= money((int) $p['price']) ?></td>
                        <td class="num<?= $qty <= 0 ? ' is-danger' : '' ?>">
                            <?= $qty ?>
                            <?php if ($qty <= 0): ?>
                                <span class="atable__sub">Hết hàng</span>
                            <?php elseif ($qty <= $low): ?>
                                <span class="atable__sub">Sắp hết</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="/quan-tri/ton-kho/cap-nhat" class="ainv__form">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                <input type="hidden" name="loc" value="<?= e($filter) ?>">
                                <label class="sr-only" for="q-<?= e($p['id']) ?>">Tồn kho mới cho <?= e($p['name']) ?></label>
                                <input type="number" id="q-<?= e($p['id']) ?>" name="stock_quantity"
                                       value="<?= $qty ?>" min="0" max="99999" step="1">
                                <button type="submit" class="astatus__save">Lưu</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="ainv__hint">
        Đặt tồn về <strong>0</strong> sẽ tự chuyển sản phẩm sang trạng thái
        <em>hết hàng</em> và ẩn nút mua ở trang bán hàng.
    </p>
<?php endif; ?>
