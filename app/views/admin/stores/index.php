<?php

/**
 * admin/stores/index.php — cơ sở cửa hàng
 * Port từ quan-tri/co-so.tsx + admin-store-form.tsx.
 */

$ed = $editing;
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Cơ sở', 'lead' => count($stores) . ' cơ sở',
    'base' => '/quan-tri/co-so', 'canEdit' => $canEdit, 'editing' => $ed,
]); ?>

<div class="atable-wrap">
    <table class="atable atable--full">
        <thead>
            <tr>
                <th scope="col">Mã</th>
                <th scope="col">Tên</th>
                <th scope="col">Địa chỉ</th>
                <th scope="col">Liên hệ</th>
                <th scope="col">Hoạt động</th>
                <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stores as $s): ?>
                <tr>
                    <td><code><?= e($s['code']) ?></code></td>
                    <td><?= e($s['name']) ?></td>
                    <td class="atable__msg"><?= e($s['address']) ?></td>
                    <td>
                        <?= e($s['phone'] ?? '—') ?>
                        <span class="atable__sub"><?= e($s['open_hours'] ?? '') ?></span>
                    </td>
                    <td>
                        <span class="badge badge--<?= $s['is_active'] ? 'in_stock' : 'cancelled' ?>">
                            <?= $s['is_active'] ? 'Đang mở' : 'Đã đóng' ?>
                        </span>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="arow-actions">
                            <a href="/quan-tri/co-so?sua=<?= e($s['id']) ?>#form">Sửa</a>
                            <form method="post" action="/quan-tri/co-so/xoa"
                                  onsubmit="return confirm('Xoá cơ sở &quot;<?= e($s['name']) ?>&quot;?')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($s['id']) ?>">
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
            <?= $ed !== null ? 'Sửa cơ sở: ' . e($ed['name']) : 'Thêm cơ sở mới' ?>
        </h2>

        <form id="storeForm" method="post" action="/quan-tri/co-so/luu" class="aform__grid">
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

        <button type="button" id="btnSaveStore" class="btn-save">
            Lưu thay đổi
        </button>
    </section>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('storeForm');
        const btn = document.getElementById('btnSaveStore');
        if (!form || !btn) return;

        btn.addEventListener('click', async function () {
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Đang lưu...';

            try {
                const formData = new FormData(form);
                const response = await fetch('/admin/store/save', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: formData,
                    credentials: 'same-origin'
                });
                let payload = null;
                try { payload = await response.json(); } catch (e) { throw new Error('Phản hồi từ máy chủ không phải JSON hợp lệ.'); }
                if (!response.ok || !payload.success) throw new Error(payload.message || 'Lưu cơ sở thất bại.');
                alert(payload.message || 'Lưu thành công!');
                window.location.href = payload.redirect || '/quan-tri/co-so';
            } catch (error) {
                alert(error.message || 'Lỗi khi lưu cơ sở.');
                console.error(error);
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    });
</script>
