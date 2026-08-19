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
                            <form method="post" action="/quan-tri/danh-muc/xoa"
                                  onsubmit="return confirm('Xoá danh mục &quot;<?= e($c['name']) ?>&quot;?')">
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

        <form id="categoryForm" method="post" action="/quan-tri/danh-muc/luu" class="aform__grid">
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

        <button type="button" id="btnSaveCategory" class="btn-save">
            Lưu thay đổi
        </button>
    </section>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('categoryForm');
        const btn = document.getElementById('btnSaveCategory');
        if (!form || !btn) return;

        btn.addEventListener('click', async function () {
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Đang lưu...';

            try {
                const formData = new FormData(form);
                const response = await fetch('/admin/category/save', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: formData,
                    credentials: 'same-origin'
                });
                let payload = null;
                try { payload = await response.json(); } catch (e) { throw new Error('Phản hồi từ máy chủ không phải JSON hợp lệ.'); }
                if (!response.ok || !payload.success) throw new Error(payload.message || 'Lưu danh mục thất bại.');
                alert(payload.message || 'Lưu thành công!');
                window.location.href = payload.redirect || '/quan-tri/danh-muc';
            } catch (error) {
                alert(error.message || 'Lỗi khi lưu danh mục.');
                console.error(error);
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });
</script>
