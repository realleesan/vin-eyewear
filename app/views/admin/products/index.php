<?php

/**
 * admin/products/index.php — sản phẩm
 * Port từ quan-tri/san-pham.tsx + admin-product-form.tsx.
 */

$ed = $editing;

// Cột JSON trong DB là chuỗi; đổi về dạng người nhập được (mỗi dòng một mục)
$edImages = '';
$edSpecs  = '';

if ($ed !== null) {
    $imgs = json_decode((string) $ed['images'], true) ?: [];
    $edImages = implode("\n", $imgs);

    foreach (json_decode((string) $ed['specs'], true) ?: [] as $label => $value) {
        $edSpecs .= $label . ': ' . $value . "\n";
    }
}
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Sản phẩm',
    'lead'  => $total . ' sản phẩm' . ($totalPages > 1 ? ' · trang ' . $page . '/' . $totalPages : ''),
    'base'  => '/quan-tri/san-pham', 'canEdit' => $canEdit, 'editing' => $ed,
]); ?>

<form class="asearch" method="get" action="/quan-tri/san-pham">
    <label class="sr-only" for="q">Tìm sản phẩm</label>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Tên, SKU hoặc thương hiệu">
    <button type="submit" class="astatus__save">Tìm</button>
    <?php if ($q !== ''): ?>
        <a href="/quan-tri/san-pham" class="apanel__more">Xoá tìm kiếm</a>
    <?php endif; ?>
</form>

<div class="atable-wrap">
    <table class="atable atable--full">
        <thead>
            <tr>
                <th scope="col">SKU</th>
                <th scope="col">Sản phẩm</th>
                <th scope="col">Danh mục</th>
                <th scope="col">Giá</th>
                <th scope="col">Tồn</th>
                <th scope="col">Trạng thái</th>
                <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><code><?= e($p['sku']) ?></code></td>
                    <td>
                        <?= e($p['name']) ?>
                        <span class="atable__sub"><?= e($p['brand'] ?? '—') ?> · <?= e($p['frame_shape'] ?? '—') ?></span>
                    </td>
                    <td><?= e($p['category_name'] ?? '—') ?></td>
                    <td class="num">
                        <?= money((int) $p['price']) ?>
                        <?php if (!empty($p['compare_at_price'])): ?>
                            <span class="atable__sub"><?= money((int) $p['compare_at_price']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num<?= (int) $p['stock_quantity'] <= 0 ? ' is-danger' : '' ?>"><?= (int) $p['stock_quantity'] ?></td>
                    <td>
                        <span class="badge badge--<?= e($p['status']) ?>"><?= $p['status'] === 'in_stock' ? 'Còn hàng' : 'Hết hàng' ?></span>
                        <?php if (!$p['is_visible']): ?>
                            <span class="badge badge--cancelled">Đang ẩn</span>
                        <?php endif; ?>
                        <?php if ($p['is_featured']): ?>
                            <span class="badge badge--new">Nổi bật</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="arow-actions">
                            <a href="/quan-tri/san-pham?sua=<?= e($p['id']) ?>#form">Sửa</a>
                            <form method="post" action="/quan-tri/san-pham/xoa"
                                  onsubmit="return confirm('Xoá sản phẩm &quot;<?= e($p['name']) ?>&quot;?')">
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

<?php if ($totalPages > 1): ?>
    <nav class="pager" aria-label="Phân trang">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $url = '/quan-tri/san-pham?' . http_build_query(array_filter(['q' => $q, 'page' => $i])); ?>
            <?php if ($i === $page): ?>
                <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a class="pager__link" href="<?= e($url) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php if ($canEdit): ?>
    <section class="aform" id="form" aria-labelledby="form-title">
        <h2 id="form-title" class="apanel__title">
            <?= $ed !== null ? 'Sửa sản phẩm: ' . e($ed['name']) : 'Thêm sản phẩm mới' ?>
        </h2>

        <form method="post" action="/quan-tri/san-pham/luu" class="aform__grid">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field">
                <label for="name">Tên sản phẩm *</label>
                <input type="text" id="name" name="name" required maxlength="255" value="<?= e($ed['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="sku">Mã SKU *</label>
                <input type="text" id="sku" name="sku" required maxlength="64" value="<?= e($ed['sku'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="slug">Slug <span class="field__opt">(bỏ trống để tự sinh)</span></label>
                <input type="text" id="slug" name="slug" maxlength="160" value="<?= e($ed['slug'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="category_id">Danh mục</label>
                <select id="category_id" name="category_id">
                    <option value="">— Chưa phân loại —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['id']) ?>"<?= ($ed['category_id'] ?? '') === $c['id'] ? ' selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="brand">Thương hiệu</label>
                <input type="text" id="brand" name="brand" value="<?= e($ed['brand'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="frame_shape">Dáng gọng</label>
                <input type="text" id="frame_shape" name="frame_shape" list="shapes" value="<?= e($ed['frame_shape'] ?? '') ?>">
                <datalist id="shapes">
                    <?php foreach (['Square','Round','Cat-eye','Aviator','Geometric','Oval','Wayfarer'] as $s): ?>
                        <option value="<?= e($s) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="field">
                <label for="material">Chất liệu</label>
                <input type="text" id="material" name="material" value="<?= e($ed['material'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="color">Màu sắc</label>
                <input type="text" id="color" name="color" value="<?= e($ed['color'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="gender">Đối tượng</label>
                <select id="gender" name="gender">
                    <option value="">— Không xác định —</option>
                    <?php foreach (['male'=>'Nam','female'=>'Nữ','unisex'=>'Unisex','kids'=>'Trẻ em'] as $v=>$l): ?>
                        <option value="<?= e($v) ?>"<?= ($ed['gender'] ?? '') === $v ? ' selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="price">Giá bán (VND) *</label>
                <input type="number" id="price" name="price" required min="0" step="1000"
                       value="<?= (int) ($ed['price'] ?? 0) ?>">
            </div>

            <div class="field">
                <label for="compare_at_price">Giá gốc <span class="field__opt">(để trống nếu không giảm)</span></label>
                <input type="number" id="compare_at_price" name="compare_at_price" min="0" step="1000"
                       <?php /* ?? null trước rồi mới so sánh: lúc THÊM MỚI thì $ed
                                là null, truy cập thẳng $ed['...'] sẽ cảnh báo. */ ?>
                       value="<?= ($ed['compare_at_price'] ?? null) !== null ? (int) $ed['compare_at_price'] : '' ?>">
            </div>

            <div class="field">
                <label for="stock_quantity">Tồn kho *</label>
                <input type="number" id="stock_quantity" name="stock_quantity" required min="0" step="1"
                       value="<?= (int) ($ed['stock_quantity'] ?? 0) ?>">
                <p class="field__hint">Đặt 0 sẽ tự chuyển sang "hết hàng".</p>
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_visible" <?= ($ed === null || $ed['is_visible']) ? 'checked' : '' ?>>
                    Hiển thị trên trang bán hàng
                </label>
                <label>
                    <input type="checkbox" name="is_featured" <?= !empty($ed['is_featured']) ? 'checked' : '' ?>>
                    Sản phẩm nổi bật
                </label>
            </div>

            <div class="field field--wide">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" rows="3"><?= e($ed['description'] ?? '') ?></textarea>
            </div>

            <div class="field field--wide">
                <label for="images">Ảnh — mỗi dòng một đường dẫn</label>
                <textarea id="images" name="images" rows="3"
                          placeholder="/assets/images/product-1.jpg"><?= e($edImages) ?></textarea>
                <p class="field__hint">Dòng đầu là ảnh đại diện, dòng thứ hai hiện khi rê chuột.</p>
            </div>

            <div class="field field--wide">
                <label for="specs">Thông số — mỗi dòng "Nhãn: giá trị"</label>
                <textarea id="specs" name="specs" rows="4"
                          placeholder="Vật liệu: Titan&#10;Kích thước: 52-18-140"><?= e($edSpecs) ?></textarea>
            </div>

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm sản phẩm' ?></button>
        </form>
    </section>
<?php endif; ?>
