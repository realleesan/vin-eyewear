<?php

/**
 * admin/contacts/index.php — yêu cầu liên hệ
 * Port từ src/routes/_authenticated/quan-tri/lien-he.tsx.
 */
?>
<header class="ahead">
    <h1 class="ahead__title">Yêu cầu liên hệ</h1>
    <p class="ahead__lead"><?= (int) $total ?> yêu cầu<?= $totalPages > 1 ? ' · trang ' . $page . '/' . $totalPages : '' ?></p>
</header>

<?php partial('admin/_layout/filter-tabs', [
    'base' => '/quan-tri/lien-he', 'statuses' => $statuses,
    'counts' => $counts, 'current' => $status,
]); ?>

<?php if ($contacts === []): ?>
    <p class="apanel__empty">Không có yêu cầu nào khớp bộ lọc.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Gửi lúc</th>
                    <th scope="col">Người gửi</th>
                    <th scope="col">Nội dung</th>
                    <th scope="col">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contacts as $c): ?>
                    <tr>
                        <td><?= e(formatDate($c['created_at'], 'd/m/Y H:i')) ?></td>
                        <td>
                            <?= e($c['full_name']) ?>
                            <span class="atable__sub"><a href="tel:<?= e(preg_replace('/\D/', '', $c['phone'])) ?>"><?= e($c['phone']) ?></a></span>
                            <?php if (!empty($c['email'])): ?>
                                <span class="atable__sub"><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></span>
                            <?php endif; ?>
                        </td>
                        <td class="atable__msg"><?= e($c['message']) ?></td>
                        <td>
                            <form method="post" action="/quan-tri/lien-he/trang-thai" class="astatus">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                <label class="sr-only" for="st-<?= e($c['id']) ?>">Trạng thái yêu cầu của <?= e($c['full_name']) ?></label>
                                <select id="st-<?= e($c['id']) ?>" name="status" data-autosubmit>
                                    <?php foreach ($statuses as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $c['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="astatus__save">Lưu</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
