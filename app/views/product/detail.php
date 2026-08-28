<?php

/**
 * product/detail.php — chi tiết sản phẩm (/san-pham/{slug})
 *
 * Dựng theo "Vin Eyewear Product.dc.html" (Claude Design):
 *
 *   breadcrumb → hai cột đều nhau, gap 48:
 *     trái  thư viện ảnh dính theo cuộn (ảnh lớn 520px + hàng ảnh nhỏ 92px)
 *     phải  thông tin, chọn phương án, mua hàng, 4 thẻ cam kết
 *   → hai thẻ ngang nhau: Thông số kỹ thuật | Đánh giá
 *
 * CSS: assets/css/product-detail.css
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐỔI ẢNH VÀ CHỌN PHƯƠNG ÁN — KHÔNG JAVASCRIPT
 *
 * Ảnh: mỗi ảnh lớn có id, ảnh nhỏ là <a href="#id">. CSS `:target` quyết định
 * ảnh nào hiện. Bấm ảnh nhỏ đổi ảnh lớn mà không tải lại trang, và địa chỉ
 * mang theo ảnh đang xem nên gửi link được.
 *
 * Phương án (chiết suất): ô radio thật + `:has()`, đúng cách đã dùng ở trang
 * thanh toán. Giá trị đi thẳng vào form "Thêm vào giỏ" nằm ngay dưới, nên
 * không có bước đồng bộ nào giữa hai nơi.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$price    = (int) $product['price'];
$compare  = $product['compare_at_price'] !== null ? (int) $product['compare_at_price'] : null;
$percent  = discount($price, $compare);
$rating   = (float) ($product['rating'] ?? 5);
$reviewN  = (int) ($product['review_count'] ?? 0);
$images   = $product['images'] ?: [ProductModel::image($product)];
$specs    = is_array($product['specs']) ? $product['specs'] : [];
$slug     = rawurlencode($product['slug']);

/* Biến thể đang được chọn sẵn. Ưu tiên cái khách vừa chọn hỏng (?pa=), rồi tới
   phương án còn hàng đầu tiên — chọn sẵn một phương án đã hết hàng là bẫy. */
$firstInStock = null;
foreach ($variants as $v) {
    if ((int) $v['stock_quantity'] > 0) { $firstInStock = $v['id']; break; }
}
$activeVariant = $pickedVariant !== '' ? $pickedVariant : ($firstInStock ?? ($variants[0]['id'] ?? ''));

/* Còn hàng hay không, tính cả biến thể: có biến thể thì chỉ cần MỘT phương án
   còn hàng là mặt hàng còn bán được. */
$inStock = $variants === []
    ? ProductModel::inStock($product)
    : ($product['status'] === 'in_stock' && $firstInStock !== null);

/*
 * SỐ LƯỢNG CÒN LẠI — con số thật, không chỉ chữ "Còn hàng".
 *
 * Trước bản này trang chỉ nói còn/hết. Khách muốn mua ba cái không có cách nào
 * biết cửa hàng còn mấy cái, và chỉ phát hiện ra ở trang giỏ khi máy chủ từ
 * chối tăng số lượng — đúng lúc họ tưởng việc mua đã xong.
 *
 * Mặt hàng CÓ biến thể thì cộng tồn của mọi phương án: mỗi phương án hiện tồn
 * riêng ngay trên nút chọn bên dưới, còn con số ở đây trả lời câu "cửa hàng
 * này còn bao nhiêu cái tất cả".
 */
$stockLeft = $variants === []
    ? (int) ($product['stock_quantity'] ?? 0)
    : array_sum(array_map(static fn ($v) => (int) $v['stock_quantity'], $variants));

/* Số nhỏ mới đáng nói. Còn 80 cái mà in "còn 80" thì con số đó không giúp ai
   quyết định gì, chỉ làm dòng thông tin dài thêm; còn 3 cái thì đó là thứ
   khách cần biết trước khi chọn số lượng. */
$stockLow = $inStock && $stockLeft > 0 && $stockLeft <= 10;

$commitments = [
    ['shield', 'Bảo hành 24 tháng',   'Lỗi nhà sản xuất, đổi mới trong 7 ngày đầu'],
    ['eye',    'Đo mắt miễn phí',     'Quy trình khúc xạ chuẩn, kể cả khi không mua'],
    ['truck',  'Giao hàng đồng kiểm', 'Mở hộp kiểm tra trước khi thanh toán'],
    ['wrench', 'Nắn chỉnh trọn đời',  'Miễn phí không giới hạn tại cả hai cơ sở'],
];

/** Năm ngôi sao đặc/rỗng theo điểm — dùng cho cả phần đầu và từng đánh giá. */
$stars = static function (float $score): string {
    $full = (int) round($score);
    return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full));
};
?>

<section class="pd">

    <?php
    /*
     * CHỈ breadcrumb — không tiêu đề, không mô tả.
     *
     * Tên sản phẩm đã là <h1> của trang (nằm trong cột thông tin bên phải),
     * nên một dải tiêu đề nữa vừa nói hai lần vừa đẩy thư viện ảnh xuống dưới
     * màn hình đầu tiên. Breadcrumb thì vẫn cần: trang này nằm sâu trong cây
     * và phần lớn người xem vào thẳng từ tìm kiếm.
     */
    $crumbs = [];
    if ($category !== null) {
        $crumbs[] = [
            'label' => $category['name'],
            'url'   => '/san-pham?category=' . rawurlencode($category['slug']),
        ];
    }
    $crumbs[] = ['label' => $product['name']];

    partial('_layout/page-head', ['head_crumbs' => $crumbs]);
    ?>

    <div class="pd__grid">

        <!-- ══════════ THƯ VIỆN ẢNH ══════════ -->
        <div class="pdgal">
            <div class="pdgal__stage">
                <?php foreach ($images as $i => $img): ?>
                    <!-- id là đích của ảnh nhỏ bên dưới; CSS :target chọn ảnh
                         nào hiện. Ảnh đầu hiện mặc định khi chưa có :target. -->
                    <img class="pdgal__img<?= $i === 0 ? ' is-first' : '' ?>"
                         id="anh-<?= $i ?>" src="<?= e($img) ?>"
                         alt="<?= e($product['name']) ?><?= $i > 0 ? ' — ảnh ' . ($i + 1) : '' ?>"
                         width="520" height="520"
                         <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                <?php endforeach; ?>

                <?php if ($percent !== null): ?>
                    <span class="pdgal__sale">-<?= (int) $percent ?>%</span>
                <?php endif; ?>
            </div>

            <?php if (count($images) > 1): ?>
                <div class="pdgal__thumbs">
                    <?php foreach ($images as $i => $img): ?>
                        <a class="pdgal__thumb" href="#anh-<?= $i ?>">
                            <img src="<?= e($img) ?>" alt="Xem ảnh <?= $i + 1 ?>"
                                 width="92" height="92" loading="lazy" decoding="async">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══════════ THÔNG TIN ══════════ -->
        <div class="pdinfo">

            <div class="pdinfo__head">
                <span class="pdinfo__eyebrow">
                    <?= e($product['brand'] ?? 'Vin Eyewear') ?> · SKU <?= e($product['sku']) ?>
                </span>
                <h1 class="pdinfo__title"><?= e($product['name']) ?></h1>

                <div class="pdinfo__rate">
                    <span class="pdstars" aria-hidden="true"><?= $stars($rating) ?></span>
                    <span class="pdinfo__score"><?= e(number_format($rating, 1)) ?></span>
                    <span class="pdinfo__dot" aria-hidden="true">·</span>
                    <a href="#danh-gia"><?= $reviewN ?> đánh giá</a>
                    <span class="pdinfo__dot" aria-hidden="true">·</span>
                    <span class="pdinfo__stock<?= $inStock ? '' : ' is-out' ?>">
                        <?php
                        /* Ba câu chứ không hai: hết hàng · còn ít (kèm số) · còn
                           hàng. Xem $stockLow ở đầu file. */
                        if (!$inStock) {
                            echo 'Tạm hết hàng';
                        } elseif ($stockLow) {
                            echo 'Chỉ còn ' . $stockLeft . ' sản phẩm';
                        } else {
                            echo 'Còn hàng · ' . $stockLeft . ' sản phẩm';
                        }
                        ?>
                    </span>
                </div>
            </div>

            <div class="pdinfo__price">
                <span class="pdinfo__now"><?= money($price) ?></span>
                <?php if ($compare !== null && $compare > $price): ?>
                    <span class="pdinfo__old"><?= money($compare) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($product['description'])): ?>
                <p class="pdinfo__desc"><?= e($product['description']) ?></p>
            <?php endif; ?>

            <form class="pdbuy" method="post" action="/gio-hang/them">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">

                <?php /* Nơi quay về sau khi chọn hình thức mua — hộp thoại
                         "Chọn hình thức mua" hiện đè lên chính trang này. */ ?>
                <input type="hidden" name="back" value="<?= e(currentUrlWithout(['mua', 'buoc'])) ?>">

                <?php if ($variants !== []): ?>
                    <div class="pdopts">
                        <span class="pdopts__label" id="nhan-pa">Chiết suất — chọn theo độ cận</span>

                        <div class="pdopts__row" role="radiogroup" aria-labelledby="nhan-pa">
                            <?php foreach ($variants as $v): ?>
                                <?php $vStock = (int) $v['stock_quantity']; ?>
                                <label class="pdopt<?= $vStock > 0 ? '' : ' is-out' ?>">
                                    <input type="radio" name="variant_id" value="<?= e($v['id']) ?>"
                                           required <?= $vStock > 0 ? '' : 'disabled' ?>
                                           <?= $activeVariant === $v['id'] && $vStock > 0 ? 'checked' : '' ?>>
                                    <span class="pdopt__body">
                                        <span class="pdopt__name"><?= e($v['label']) ?></span>
                                        <span class="pdopt__note">
                                            <?php
                                            /* Ghi chú của phương án, và khi tồn
                                               xuống thấp thì NHƯỜNG CHỖ cho con
                                               số: sắp hết là thứ quyết định
                                               khách bấm hay không, còn ghi chú
                                               ("dành cho độ cận dưới 4") thì
                                               đọc lúc nào cũng được. */
                                            if ($vStock <= 0) {
                                                echo 'Tạm hết';
                                            } elseif ($vStock <= 10) {
                                                echo 'Chỉ còn ' . $vStock;
                                            } else {
                                                echo e($v['note'] ?? '');
                                            }
                                            ?>
                                        </span>
                                    </span>
                                    <?php if ((int) $v['price_delta'] !== 0): ?>
                                        <!-- Chênh giá phải hiện ngay trên nút: bản thiết
                                             kế mẫu có ba phương án cùng giá, nhưng dữ liệu
                                             thật thì không, và đổi giá âm thầm khi khách
                                             bấm là điều tệ nhất một trang bán hàng làm được. -->
                                        <span class="pdopt__delta">
                                            <?= (int) $v['price_delta'] > 0 ? '+' : '−' ?><?= money(abs((int) $v['price_delta'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="pdbuy__row">
                    <div class="pdqty">
                        <!-- Không JS nên hai nút ± ở đây sẽ phải gửi form; thay
                             vào đó là một ô số thật. Trình duyệt cho mũi tên
                             lên/xuống và bàn phím, không cần thêm gì. -->
                        <label class="sr-only" for="so-luong">Số lượng</label>
                        <input class="pdqty__num" type="number" id="so-luong" name="quantity"
                               value="1" min="1" max="20" step="1" inputmode="numeric"
                               <?= $inStock ? '' : 'disabled' ?>>
                    </div>

                    <button type="submit" name="action" value="buy" class="pdbtn pdbtn--buy"
                            <?= $inStock ? '' : 'disabled' ?>>
                        <?= $inStock ? 'Mua ngay' : 'Tạm hết hàng' ?>
                    </button>

                    <button type="submit" class="pdbtn pdbtn--add" <?= $inStock ? '' : 'disabled' ?>>
                        Thêm vào giỏ
                    </button>
                </div>
            </form>

            <ul class="pdcommit" role="list">
                <?php foreach ($commitments as [$ico, $title, $desc]): ?>
                    <li class="pdcommit__item">
                        <?= icon($ico, 'pdcommit__ico', 17) ?>
                        <span class="pdcommit__text">
                            <span class="pdcommit__title"><?= e($title) ?></span>
                            <span class="pdcommit__desc"><?= e($desc) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- ══════════ THÔNG SỐ + ĐÁNH GIÁ ══════════ -->
    <div class="pdbottom">

        <div class="pdcard">
            <h2 class="pdcard__title">Thông số kỹ thuật</h2>

            <?php
            /* Ghép vài cột có sẵn vào bảng thông số để nó không quá lèo tèo khi
               cột `specs` chưa được điền — bản thiết kế vẽ sáu dòng. Cột nào
               trống thì bỏ, không in "—". */
            /* Lọc cả giá trị RỖNG lẫn dấu gạch: cửa hàng gõ "—" vào ô không áp
               dụng (tròng kính thì không có dáng gọng), và in nguyên dấu gạch
               ra bảng thông số trông như dữ liệu bị thiếu chứ không phải cố ý. */
            $blank = static fn ($v): bool =>
                $v === null || trim((string) $v) === ''
                || in_array(trim((string) $v), ['-', '–', '—', 'N/A', 'n/a'], true);

            $rows = array_filter([
                'Thương hiệu'  => $product['brand'] ?? null,
                'Chất liệu'    => $product['material'] ?? null,
                'Màu sắc'      => $product['color'] ?? null,
                'Dáng gọng'    => $product['frame_shape'] ?? null,
            ], static fn ($v) => !$blank($v)) + array_filter($specs, static fn ($v) => !$blank($v));

            if ($variants !== []) {
                $rows['Phương án'] = implode(' / ', array_column($variants, 'label'));
            }
            ?>

            <?php if ($rows === []): ?>
                <p class="pdcard__empty">Chưa cập nhật thông số cho sản phẩm này.</p>
            <?php else: ?>
                <dl class="pdspecs">
                    <?php foreach ($rows as $k => $v): ?>
                        <div class="pdspecs__row">
                            <dt><?= e((string) $k) ?></dt>
                            <dd><?= e((string) $v) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>

        <div class="pdcard" id="danh-gia">
            <div class="pdcard__head">
                <h2 class="pdcard__title">Đánh giá (<?= $reviewN ?>)</h2>
                <div class="pdcard__score">
                    <span class="pdcard__num"><?= e(number_format($rating, 1)) ?></span>
                    <span class="pdstars pdstars--sm" aria-hidden="true"><?= $stars($rating) ?></span>
                </div>
            </div>

            <?php if ($reviewMsg !== null): ?>
                <p class="pdreview__flash<?= $reviewOk ? ' is-ok' : ' is-err' ?>"
                   role="<?= $reviewOk ? 'status' : 'alert' ?>"><?= e($reviewMsg) ?></p>
            <?php endif; ?>

            <?php if ($reviews === []): ?>
                <p class="pdcard__empty">Chưa có đánh giá nào. Hãy là người đầu tiên.</p>
            <?php else: ?>
                <?php foreach ($reviews as $rv): ?>
                    <article class="pdreview">
                        <div class="pdreview__head">
                            <span class="pdreview__face" aria-hidden="true">
                                <?= e(utf8Substr($rv['author_name'], 0, 1)) ?>
                            </span>
                            <span class="pdreview__who">
                                <span class="pdreview__name"><?= e($rv['author_name']) ?></span>
                                <span class="pdreview__meta">
                                    <?php
                                    /* "Đã mua · Chiết suất 1.61 · 08/2026" — huy hiệu
                                       "Đã mua" chỉ có khi đánh giá gắn với một đơn thật. */
                                    $bits = [];
                                    if ($rv['order_id'] !== null)      { $bits[] = 'Đã mua'; }
                                    if (!empty($rv['variant_label']))  { $bits[] = $rv['variant_label']; }
                                    $bits[] = formatDate($rv['created_at'], 'm/Y');
                                    echo e(implode(' · ', $bits));
                                    ?>
                                </span>
                            </span>
                            <span class="pdstars pdstars--sm pdreview__stars" aria-label="<?= (int) $rv['rating'] ?> trên 5 sao">
                                <?= $stars((float) $rv['rating']) ?>
                            </span>
                        </div>
                        <p class="pdreview__body"><?= e($rv['body']) ?></p>

                        <?php /* PHẢN HỒI CỦA CỬA HÀNG — cột `reply`, do khu quản trị
                                 soạn (xem ReviewAdminController::reply()).

                                 Kiểm cả isset lẫn chuỗi rỗng: cột chỉ có từ migration
                                 2026-08-28-phan-hoi-danh-gia, và trên máy chưa chạy
                                 file đó thì khoá này không tồn tại — trang sản phẩm là
                                 trang khách xem, không được đổ lỗi vì một cột thiếu.
                                 Rỗng thì cũng không vẽ: nó nghĩa là đã trả lời rồi xoá
                                 chữ đi, không phải một khối trống cần bày ra. */ ?>
                        <?php if (trim((string) ($rv['reply'] ?? '')) !== ''): ?>
                            <div class="pdreply">
                                <p class="pdreply__from">
                                    Phản hồi của <?= e(config('company.short_name', 'cửa hàng')) ?>
                                    <?php if (!empty($rv['replied_at'])): ?>
                                        <span class="pdreply__when"><?= e(formatDate($rv['replied_at'])) ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="pdreply__body"><?= e($rv['reply']) ?></p>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!$showAll && $reviewN > count($reviews)): ?>
                <a class="pdcard__more" href="/san-pham/<?= $slug ?>?danh-gia=tat-ca#danh-gia">
                    Xem tất cả đánh giá
                </a>
            <?php endif; ?>

            <?php if ($canReview['ok']): ?>
                <form class="pdwrite" method="post" action="/san-pham/danh-gia">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">

                    <span class="pdwrite__label">Viết đánh giá của bạn</span>

                    <!-- Năm ô radio xếp NGƯỢC trong HTML rồi lật lại bằng CSS
                         (flex-direction: row-reverse). Nhờ vậy "tô sáng mọi sao
                         bên trái sao đang chọn" làm được bằng bộ chọn ~ của CSS,
                         vốn chỉ nhìn được về phía SAU trong cây DOM. -->
                    <div class="pdwrite__stars" role="radiogroup" aria-label="Chấm điểm">
                        <?php foreach ([5, 4, 3, 2, 1] as $n): ?>
                            <input type="radio" name="rating" id="sao-<?= $n ?>" value="<?= $n ?>"
                                   required <?= $n === 5 ? 'checked' : '' ?>>
                            <label for="sao-<?= $n ?>" title="<?= $n ?> sao">
                                <span class="sr-only"><?= $n ?> sao</span>
                                <span aria-hidden="true">★</span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <label class="sr-only" for="noi-dung">Nhận xét</label>
                    <textarea class="pdwrite__area" id="noi-dung" name="body" rows="3"
                              required minlength="10" maxlength="2000"
                              placeholder="Kính dùng có vừa ý không? Cắt lắp mất bao lâu?"></textarea>

                    <button type="submit" class="pdwrite__send">Gửi đánh giá</button>
                    <span class="pdwrite__note">Đánh giá hiển thị sau khi được duyệt.</span>
                </form>
            <?php elseif (!empty($canReview['reason'])): ?>
                <p class="pdcard__note"><?= e($canReview['reason']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($related !== []): ?>
        <section class="pdrelated" aria-labelledby="lien-quan">
            <h2 class="pdcard__title" id="lien-quan">Sản phẩm liên quan</h2>
            <ul class="pcard__grid" role="list">
                <?php foreach ($related as $item): ?>
                    <?php partial('_layout/product-card', ['product' => $item]); ?>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
