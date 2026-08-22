<?php

/**
 * admin/variants/index.php — biến thể sản phẩm
 *
 * Chọn mặt hàng ở ô trên cùng, bảng và form bên dưới đi theo mặt hàng đó.
 */

$ed   = $editing;
$base = '/quan-tri/bien-the';
?>

<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Biến thể sản phẩm</h1>
        <p class="ahead__lead">
            Phương án khách chọn khi mua — chiết suất tròng, màu gọng…
        </p>
    </div>

    <?php if (!$canEdit): ?>
        <p class="ahead__note">Bạn chỉ có quyền xem. Cần quyền quản lý để chỉnh sửa.</p>
    <?php endif; ?>
</header>

<!-- Đổi mặt hàng bằng GET: không có JS thì vẫn còn nút "Xem". admin.js gửi
     form ngay khi đổi ô chọn, giống ô lọc trạng thái ở các trang khác. -->
<form class="aform" method="get" action="<?= e($base) ?>">
    <div class="aform__grid">
        <div class="field">
            <label for="sp">Sản phẩm</label>
            <select id="sp" name="sp" data-autosubmit>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['id']) ?>"
                            <?= ($product['id'] ?? '') === $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="astatus__save astatus__save--ghost">Xem</button>
    </div>
</form>

<?php if ($product === null): ?>
    <p class="ahead__note">Chưa có sản phẩm nào.</p>
<?php else: ?>

    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Phương án</th>
                    <th scope="col">Ghi chú</th>
                    <th scope="col">Chênh giá</th>
                    <th scope="col">Giá bán</th>
                    <th scope="col">Tồn kho</th>
                    <th scope="col">Thứ tự</th>
                    <th scope="col">Trạng thái</th>
                    <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($variants === []): ?>
                    <tr>
                        <td colspan="<?= $canEdit ? 8 : 7 ?>">
                            Mặt hàng này chưa có phương án nào — nó được bán như một sản phẩm đơn,
                            dùng tồn kho <?= (int) $product['stock_quantity'] ?> của chính nó.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($variants as $v): ?>
                    <?php $delta = (int) $v['price_delta']; ?>
                    <tr>
                        <td><code><?= e($v['label']) ?></code></td>
                        <td class="atable__msg"><?= e($v['note'] ?? '—') ?></td>
                        <td><?= $delta === 0 ? '—' : ($delta > 0 ? '+' : '−') . money(abs($delta)) ?></td>
                        <td><?= money(VariantModel::priceOf($product, $v)) ?></td>
                        <td><?= (int) $v['stock_quantity'] ?></td>
                        <td><?= (int) $v['position'] ?></td>
                        <td>
                            <span class="badge badge--<?= $v['is_active'] ? 'in_stock' : 'cancelled' ?>">
                                <?= $v['is_active'] ? 'Đang bán' : 'Đã tắt' ?>
                            </span>
                        </td>
                        <?php if ($canEdit): ?>
                            <td class="arow-actions">
                                <a href="<?= e($base) ?>?sp=<?= e($product['id']) ?>&amp;sua=<?= e($v['id']) ?>#form">Sửa</a>
                                <?php $hoi = sprintf('Xoá phương án “%s”?', $v['label']); ?>
                                <form method="post" action="<?= e($base) ?>/xoa"
                                      data-confirm="<?= e($hoi) ?>"
                                      data-confirm-title="Xoá phương án?"
                                      data-confirm-ok="Xoá"
                                      onsubmit="return confirm('<?= e($hoi) ?>')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($v['id']) ?>">
                                    <button type="submit" class="arow-del">Xoá</button>
                                </form>
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
                <?= $ed !== null ? 'Sửa phương án: ' . e($ed['label']) : 'Thêm phương án cho ' . e($product['name']) ?>
            </h2>

            <form method="post" action="<?= e($base) ?>/luu" class="aform__grid">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">

                <div class="field">
                    <label for="label">Nhãn *</label>
                    <input type="text" id="label" name="label" required maxlength="60"
                           placeholder="1.61" value="<?= e($ed['label'] ?? '') ?>">
                    <p class="field__hint">Chữ in trên nút chọn ở trang sản phẩm.</p>
                </div>

                <div class="field">
                    <label for="note">Ghi chú</label>
                    <input type="text" id="note" name="note" maxlength="120"
                           placeholder="Cận 3.00 – 6.00" value="<?= e($ed['note'] ?? '') ?>">
                    <p class="field__hint">Dòng nhỏ dưới nhãn, giúp khách chọn đúng.</p>
                </div>

                <div class="field">
                    <label for="price_delta">Chênh giá (₫)</label>
                    <input type="number" id="price_delta" name="price_delta" step="1000"
                           value="<?= e($ed['price_delta'] ?? '0') ?>">
                    <p class="field__hint">
                        So với giá gốc <?= money((int) $product['price']) ?>.
                        Số âm = rẻ hơn. 0 = đúng giá gốc.
                    </p>
                </div>

                <div class="field">
                    <label for="stock_quantity">Tồn kho</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" step="1"
                           value="<?= e($ed['stock_quantity'] ?? '0') ?>">
                    <p class="field__hint">Riêng cho phương án này, không phải tồn kho chung.</p>
                </div>

                <div class="field">
                    <label for="position">Thứ tự</label>
                    <input type="number" id="position" name="position" min="0" step="1"
                           value="<?= e($ed['position'] ?? '0') ?>">
                    <p class="field__hint">Số nhỏ hiện trước.</p>
                </div>

                <div class="field field--check">
                    <label>
                        <input type="checkbox" name="is_active"
                               <?= ($ed === null || $ed['is_active']) ? 'checked' : '' ?>>
                        Đang bán
                    </label>
                </div>

                <button type="submit" class="astatus__save">
                    <?= $ed !== null ? 'Lưu thay đổi' : 'Thêm phương án' ?>
                </button>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
