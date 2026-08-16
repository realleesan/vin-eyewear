<?php

/**
 * admin/reviews/index.php — duyệt đánh giá
 *
 * Đánh giá chờ duyệt luôn nằm trên đầu (xem ReviewModel::paginateAdmin) — đó
 * là việc người quản trị mở trang này để làm.
 *
 * Ba nút trong CÙNG một <form>, phân biệt bằng `act`: HTML không cho lồng
 * <form>, mà cả ba đứng chung một ô của bảng.
 */
?>
<header class="ahead">
    <h1 class="ahead__title">Đánh giá</h1>
    <p class="ahead__lead"><?= (int) ($counts['pending'] ?? 0) ?> đánh giá đang chờ duyệt</p>
</header>

<?php partial('admin/_layout/filter-tabs', [
    'base' => '/quan-tri/danh-gia', 'statuses' => $statuses,
    'counts' => $counts, 'current' => $status,
]); ?>

<div class="atable-wrap">
    <table class="atable atable--full">
        <thead>
            <tr>
                <th scope="col">Sản phẩm</th>
                <th scope="col">Người viết</th>
                <th scope="col">Sao</th>
                <th scope="col">Nhận xét</th>
                <th scope="col">Trạng thái</th>
                <th scope="col">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reviews === []): ?>
                <tr><td colspan="6">Chưa có đánh giá nào ở trạng thái này.</td></tr>
            <?php endif; ?>

            <?php foreach ($reviews as $rv): ?>
                <tr>
                    <td>
                        <a href="/san-pham/<?= e(rawurlencode($rv['product_slug'])) ?>#danh-gia">
                            <?= e($rv['product_name']) ?>
                        </a>
                        <?php if (!empty($rv['variant_label'])): ?>
                            <span class="atable__sub"><?= e($rv['variant_label']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e($rv['author_name']) ?>
                        <span class="atable__sub">
                            <?= $rv['order_id'] !== null ? 'Đã mua' : 'Nhân viên nhập' ?>
                            · <?= e(formatDate($rv['created_at'])) ?>
                        </span>
                    </td>
                    <td><?= str_repeat('★', max(0, min(5, (int) $rv['rating']))) ?></td>
                    <td class="atable__msg"><?= e($rv['body']) ?></td>
                    <td>
                        <span class="badge badge--<?= $rv['status'] === 'published' ? 'in_stock'
                            : ($rv['status'] === 'rejected' ? 'cancelled' : 'pending') ?>">
                            <?= e($statuses[$rv['status']] ?? $rv['status']) ?>
                        </span>
                    </td>
                    <td class="arow-actions">
                        <form method="post" action="/quan-tri/danh-gia/sua">
                            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= e($rv['id']) ?>">

                            <?php if ($rv['status'] !== 'published'): ?>
                                <button type="submit" name="act" value="duyet" class="arow-del arow-del--calm">Duyệt</button>
                            <?php endif; ?>

                            <?php if ($rv['status'] !== 'rejected'): ?>
                                <button type="submit" name="act" value="tu-choi" class="arow-del arow-del--calm">Từ chối</button>
                            <?php endif; ?>

                            <!-- Xoá hẳn, không phải "từ chối": đánh giá spam hay
                                 bôi nhọ không có lý do gì phải giữ lại. -->
                            <button type="submit" name="act" value="xoa" class="arow-del"
                                    onclick="return confirm('Xoá hẳn đánh giá này?')">Xoá</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
