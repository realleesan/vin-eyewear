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

<?php /* Thiếu cột thì nói rõ phải chạy file nào, thay vì để phần phản hồi
         lặng lẽ biến mất và người dùng tưởng tính năng chưa làm. Trang vẫn
         duyệt và từ chối đánh giá bình thường. */ ?>
<?php if (!$coCotReply): ?>
    <div class="anote anote--alert">
        <p>
            <strong>Chưa phản hồi đánh giá được.</strong> Bảng <code>reviews</code>
            còn thiếu cột <code>reply</code>.
        </p>
        <p>Chạy <code>database/migrations/2026-08-28-phan-hoi-danh-gia.sql</code>.</p>
    </div>
<?php endif; ?>

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

                <?php /* Phản hồi đã lưu hiện ngay tại đây, đúng chỗ khách sẽ thấy
                         nó ở trang sản phẩm — người soạn nhìn được kết quả mà
                         không phải mở trang bán hàng ra đối chiếu. */ ?>
                <?php if ($coCotReply && trim((string) ($rv['reply'] ?? '')) !== ''): ?>
                    <div class="arvrep">
                        <p class="arvrep__label">Phản hồi của cửa hàng</p>
                        <p class="arvrep__text"><?= e($rv['reply']) ?></p>
                    </div>
                <?php endif; ?>
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

                <?php /* NGOÀI <form> ở trên — nó là một đường dẫn mở hộp soạn
                         phản hồi, không phải một nút gửi của form duyệt/xoá.
                         Nhãn đổi theo việc đã có phản hồi hay chưa: "Phản hồi"
                         và "Sửa phản hồi" dẫn tới hai kỳ vọng khác nhau. */ ?>
                <?php if ($coCotReply): ?>
                    <a class="arv__btn" href="/quan-tri/danh-gia?tra-loi=<?= e($rv['id']) ?>" data-modal>
                        <?= trim((string) ($rv['reply'] ?? '')) !== '' ? 'Sửa phản hồi' : 'Phản hồi' ?>
                    </a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php
/*
 * HỘP SOẠN PHẢN HỒI — theo bản thiết kế "Đánh giá.dc.html".
 *
 * Bày lại nguyên văn đánh giá ngay trên ô soạn: người viết trả lời cần đọc lại
 * câu khách nói trong lúc gõ, mà hộp thoại thì che mất thẻ đánh giá phía sau.
 *
 * Ô soạn để TRỐNG rồi lưu là gỡ phản hồi khỏi trang sản phẩm — nói ra ở câu
 * nhắc dưới chân hộp, vì không ai đoán được điều đó.
 */
$traLoi = ($coCotReply && ($dangTraLoi ?? null) !== null) ? $dangTraLoi : null;
?>
<?php if ($traLoi !== null): ?>
    <?php $saoTL = max(0, min(5, (int) $traLoi['rating'])); ?>

    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => 'Phản hồi đánh giá',
        'phu'     => $traLoi['author_name'] . ' · ' . $traLoi['product_name'],
        'dongUrl' => '/quan-tri/danh-gia',
        'rong'    => 'sm',
    ]); ?>

        <p class="arvquote">
            <span class="arv__stars" aria-label="<?= $saoTL ?> trên 5 sao">
                <?= str_repeat('★', $saoTL) . str_repeat('☆', 5 - $saoTL) ?>
            </span>
            <?= e($traLoi['body']) ?>
        </p>

        <form method="post" action="/quan-tri/danh-gia/phan-hoi" class="aform__grid" id="rv-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($traLoi['id']) ?>">

            <div class="field field--wide">
                <label for="rv-reply">Câu trả lời của cửa hàng</label>
                <textarea id="rv-reply" name="reply" rows="4"
                          placeholder="Cảm ơn anh/chị đã tin tưởng Vin Eyewear…"><?= e($traLoi['reply'] ?? '') ?></textarea>
            </div>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => '/quan-tri/danh-gia',
        'luuNhan' => 'Gửi phản hồi',
        'luuForm' => 'rv-form',
        'ghiChu'  => 'Hiện công khai dưới đánh giá. Để trống rồi lưu là gỡ phản hồi đi.',
    ]); ?>
<?php endif; ?>
