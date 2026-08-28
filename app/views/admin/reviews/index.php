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
    <?php /* Điểm trung bình chỉ tính đánh giá ĐANG HIỆN, và nói rõ điều đó ngay
             trong câu. Gộp cả bài chờ duyệt vào thì con số này nhảy mỗi lần có
             khách viết bài mới — trong khi thứ nó phải mô tả là điểm mà người
             lạ vào trang bán hàng NHÌN THẤY. */ ?>
    <p class="ahead__lead">
        <?= (int) ($counts['pending'] ?? 0) ?> đánh giá đang chờ duyệt
        <?php if ($diemTrungBinh !== null): ?>
            · điểm trung bình <?= e(number_format($diemTrungBinh, 1)) ?>★ (chỉ tính đánh giá đang hiện)
        <?php endif; ?>
    </p>
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

<?php
/*
 * THẺ XẾP DỌC, KHÔNG PHẢI BẢNG — đổi theo bản thiết kế "Đánh giá.dc.html".
 *
 * Bảng cũ nhét cả bài nhận xét vào một ô của cột "Nhận xét". Đó là chỗ hỏng:
 * nội dung đánh giá là thứ DUY NHẤT trên trang này cần đọc hết mới quyết định
 * được duyệt hay từ chối, mà một ô bảng thì hoặc bóp nó lại còn hai dòng,
 * hoặc kéo cao cả hàng gấp bốn lần hàng bên cạnh.
 *
 * Thẻ cho bài viết trọn bề ngang và xuống dòng thoải mái, còn năm sao, tên
 * khách và ngày dồn lên một dòng đầu — đúng thứ tự người ta đọc: nhìn sao
 * trước, đọc bài sau, rồi mới bấm.
 *
 * Xếp DỌC một cột (không phải lưới nhiều cột như thẻ cơ sở hay mã giảm giá):
 * mấy thẻ kia mang dữ liệu ngắn, thẻ này mang văn xuôi — hai thẻ cạnh nhau
 * thì mắt phải nhảy ngang giữa hai bài đọc dở.
 */
?>
<div class="arv">
    <?php foreach ($reviews as $rv): ?>
        <?php $sao = max(0, min(5, (int) $rv['rating'])); ?>
        <article class="arv__card">
            <div class="arv__body">
                <div class="arv__meta">
                    <?php /* Năm sao LUÔN in đủ năm ký tự, sao trống dùng ☆ — chỉ in
                             số sao đã cho thì "★★★" và "★★★★★" khác nhau về BỀ NGANG
                             chứ không về hình, nên liếc cả cột không so được. */ ?>
                    <span class="arv__stars" aria-label="<?= $sao ?> trên 5 sao">
                        <?= str_repeat('★', $sao) . str_repeat('☆', 5 - $sao) ?>
                    </span>
                    <span class="arv__who"><?= e($rv['author_name']) ?></span>
                    <span class="arv__when">· <?= e(formatDate($rv['created_at'])) ?></span>

                    <?php /* "Đã mua hàng" là thứ quyết định trọng lượng của một đánh
                             giá — nên nó là viên nhãn, không phải một dòng chữ nhỏ.
                             Đánh giá do nhân viên nhập hộ thì không có viên này. */ ?>
                    <?php if ($rv['order_id'] !== null): ?>
                        <span class="badge badge--in_stock">Đã mua hàng</span>
                    <?php endif; ?>
                </div>

                <a class="arv__product" href="/san-pham/<?= e(rawurlencode($rv['product_slug'])) ?>#danh-gia">
                    <?= e($rv['product_name']) ?><?php if (!empty($rv['variant_label'])): ?>
                        · <?= e($rv['variant_label']) ?>
                    <?php endif; ?>
                </a>

                <p class="arv__text"><?= e($rv['body']) ?></p>
            </div>

            <div class="arv__side">
                <span class="badge badge--<?= $rv['status'] === 'published' ? 'in_stock'
                    : ($rv['status'] === 'rejected' ? 'cancelled' : 'pending') ?>">
                    <?= e($statuses[$rv['status']] ?? $rv['status']) ?>
                </span>

                <?php /* Ba nút trong CÙNG một <form>, phân biệt bằng `act`: HTML
                         không cho lồng <form>. */ ?>
                <form class="arv__acts" method="post" action="/quan-tri/danh-gia/sua">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="id" value="<?= e($rv['id']) ?>">

                    <?php /* "Duyệt" là NÚT ĐẶC — nó là việc chính của trang này, và
                             là việc duy nhất ở đây làm nội dung hiện ra với khách.
                             Hai nút kia viền mảnh: chúng chỉ giữ nguyên hiện trạng
                             hoặc lùi lại. */ ?>
                    <?php if ($rv['status'] !== 'published'): ?>
                        <button type="submit" name="act" value="duyet" class="arv__go">Duyệt</button>
                    <?php endif; ?>

                    <?php if ($rv['status'] !== 'rejected'): ?>
                        <button type="submit" name="act" value="tu-choi" class="arv__btn">Từ chối</button>
                    <?php endif; ?>

                    <!-- Xoá hẳn, không phải "từ chối": đánh giá spam hay bôi nhọ
                         không có lý do gì phải giữ lại. -->
                    <?php
                    /* data-confirm đặt trên NÚT chứ không trên form: form này còn
                       hai nút khác (duyệt, từ chối) và chúng không cần hỏi lại.
                       Đặt trên form là hỏi cả ba. */
                    $hoiXoaDG = sprintf(
                        'Xoá hẳn đánh giá của %s? Việc này không lùi lại được.',
                        trim((string) ($rv['author_name'] ?? '')) !== '' ? $rv['author_name'] : 'khách'
                    );
                    ?>
                    <button type="submit" name="act" value="xoa" class="arv__btn arv__btn--del"
                            data-confirm="<?= e($hoiXoaDG) ?>"
                            data-confirm-title="Xoá đánh giá?"
                            data-confirm-ok="Xoá"
                            onclick="return confirm('<?= e($hoiXoaDG) ?>')">Xoá</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php endif; ?>
