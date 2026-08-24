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

<?php /*
 * TRANG CHƯA CÓ ĐÁNH GIÁ NÀO — bản thiết kế vẽ hẳn một khối riêng, xem .aempty
 * trong admin.css. Trước đây chỗ này in cái bảng rỗng kèm một dòng
 * <td colspan>: sáu tên cột không có dòng nào bên dưới, đọc ra như trang bị
 * hỏng mất phần nội dung.
 *
 * Phân biệt hai cảnh RỖNG khác nhau, vì hai câu trả lời khác nhau:
 *   - còn bộ lọc  → "ở trạng thái này không có" → bấm sang viên lọc khác;
 *   - lọc "Tất cả" → chưa từng có đánh giá nào → không phải lỗi, chỉ là khách
 *     chưa viết. Câu thứ hai nói luôn đánh giá từ đâu tới, vì người mới nhận
 *     bàn giao không có cách nào tự đoán ra.
 */ ?>
<?php if ($reviews === []): ?>
    <div class="aempty">
        <p class="aempty__mark" aria-hidden="true">★ ★ ★</p>
        <?php if ($status !== ''): ?>
            <p class="aempty__title">Không có đánh giá nào ở trạng thái này</p>
            <p class="aempty__note">Chọn một trạng thái khác ở dải lọc bên trên để xem tiếp.</p>
        <?php else: ?>
            <p class="aempty__title">Chưa có đánh giá nào</p>
            <p class="aempty__note">
                Đánh giá khách viết trên trang bán hàng sẽ chờ duyệt tại đây trước khi
                hiển thị công khai.
            </p>
        <?php endif; ?>
    </div>
<?php else: ?>

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
                            <?php
                            /* data-confirm đặt trên NÚT chứ không trên form: form
                               này còn hai nút khác (duyệt, ẩn) và chúng không cần
                               hỏi lại. Đặt trên form là hỏi cả ba. */
                            $hoiXoaDG = sprintf(
                                'Xoá hẳn đánh giá của %s? Việc này không lùi lại được.',
                                trim((string) ($rv['author_name'] ?? '')) !== '' ? $rv['author_name'] : 'khách'
                            );
                            ?>
                            <button type="submit" name="act" value="xoa" class="arow-del"
                                    data-confirm="<?= e($hoiXoaDG) ?>"
                                    data-confirm-title="Xoá đánh giá?"
                                    data-confirm-ok="Xoá"
                                    onclick="return confirm('<?= e($hoiXoaDG) ?>')">Xoá</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
