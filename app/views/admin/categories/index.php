<?php

/**
 * admin/categories/index.php — danh mục
 * Port từ quan-tri/danh-muc.tsx + admin-category-form.tsx.
 */

$ed = $editing;
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Danh mục', 'lead' => count($categories) . ' danh mục',
    'base' => '/quan-tri/danh-muc', 'canEdit' => $canEdit, 'editing' => $ed,
]); ?>

<div class="atable-wrap">
    <table class="atable atable--full">
        <thead>
            <tr>
                <th scope="col">Thứ tự</th>
                <th scope="col">Tên</th>
                <th scope="col">Slug</th>
                <th scope="col">Mô tả</th>
                <th scope="col">Hiển thị</th>
                <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td class="num"><?= (int) $c['sort_order'] ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><code><?= e($c['slug']) ?></code></td>
                    <td class="atable__msg"><?= e(excerpt($c['description'] ?? '', 60)) ?></td>
                    <td>
                        <span class="badge badge--<?= $c['is_visible'] ? 'in_stock' : 'cancelled' ?>">
                            <?= $c['is_visible'] ? 'Hiện' : 'Ẩn' ?>
                        </span>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="arow-actions">
                            <a href="/quan-tri/danh-muc?sua=<?= e($c['id']) ?>#form">Sửa</a>
                            <?php $hoi = sprintf('Xoá danh mục “%s”?', $c['name']); ?>
                            <form method="post" action="/quan-tri/danh-muc/xoa"
                                  data-confirm="<?= e($hoi) ?>"
                                  data-confirm-title="Xoá danh mục?"
                                  data-confirm-ok="Xoá"
                                  onsubmit="return confirm('<?= e($hoi) ?>')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
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
            <?= $ed !== null ? 'Sửa danh mục: ' . e($ed['name']) : 'Thêm danh mục mới' ?>
        </h2>

        <form method="post" action="/quan-tri/danh-muc/luu" class="aform__grid">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field">
                <label for="name">Tên danh mục *</label>
                <input type="text" id="name" name="name" required maxlength="255"
                       value="<?= e($ed['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="slug">Slug <span class="field__opt">(bỏ trống để tự sinh)</span></label>
                <input type="text" id="slug" name="slug" maxlength="160"
                       value="<?= e($ed['slug'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="sort_order">Thứ tự sắp xếp</label>
                <input type="number" id="sort_order" name="sort_order" step="1"
                       value="<?= (int) ($ed['sort_order'] ?? 0) ?>">
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_visible" <?= ($ed === null || $ed['is_visible']) ? 'checked' : '' ?>>
                    Hiển thị trên trang bán hàng
                </label>
            </div>

            <div class="field field--wide">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" rows="2"><?= e($ed['description'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm danh mục' ?></button>
        </form>
    </section>
<?php endif; ?>
