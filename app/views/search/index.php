<?php

/**
 * search/index.php — kết quả tìm kiếm toàn site (/tim-kiem?q=...).
 *
 * Bốn nhóm, mỗi nhóm một dáng riêng vì bốn loại nội dung khác nhau:
 *
 *   sản phẩm   lưới thẻ .pcard dùng chung với trang chủ
 *   bài viết   danh sách ngang có ảnh bìa
 *   cơ sở      thẻ địa chỉ kèm nút chỉ đường
 *   chính sách hỏi–đáp, bấm vào nhảy đúng nhóm trong /chinh-sach
 *
 * Nhóm rỗng thì KHÔNG in ra — kể cả tiêu đề. Bốn tiêu đề với ba khối trống
 * bên dưới đọc như trang hỏng, trong khi thứ người dùng cần biết chỉ là "có
 * gì khớp không".
 *
 * Không phân trang: xem ghi chú ở đầu app/controllers/SearchController.php.
 */
?>

<?php partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Tìm kiếm']],
    'head_title'  => $q === '' ? 'Tìm kiếm' : 'Kết quả cho “' . $q . '”',
    'head_lead'   => $q === ''
        ? 'Nhập từ khoá để tìm sản phẩm, bài viết, cơ sở và chính sách.'
        : sprintf('%d kết quả trong sản phẩm, bài viết, cơ sở và chính sách.', $total),
]); ?>

<section class="srch">

    <?php /* Ô tìm lại đặt ngay đầu kết quả: sửa từ khoá là việc hay làm nhất
             trên trang này, bắt người ta cuộn ngược lên đầu trang là thừa. */ ?>
    <form class="srch__form" role="search" action="/tim-kiem" method="get">
        <label class="sr-only" for="srch-q">Từ khoá tìm kiếm</label>
        <svg class="srch__ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/>
            <path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <input class="srch__input" type="search" id="srch-q" name="q"
               value="<?= e($q) ?>" placeholder="Gọng titan, bảo hành, workshop…">
        <button type="submit" class="srch__go">Tìm</button>
    </form>

    <?php if ($q === ''): ?>
        <p class="srch__hint">Gõ tên sản phẩm, thương hiệu, hoặc một câu hỏi như “bảo hành trọn đời”.</p>

    <?php elseif ($total === 0): ?>
        <div class="srch__empty">
            <p class="srch__empty-title">Không tìm thấy gì cho “<?= e($q) ?>”.</p>
            <p class="srch__empty-note">
                Thử từ khoá ngắn hơn, hoặc xem
                <a href="/san-pham">toàn bộ sản phẩm</a> và
                <a href="/su-kien">bài viết mới nhất</a>.
            </p>
        </div>

    <?php else: ?>

        <!-- ── Sản phẩm ── -->
        <?php if ($products !== []): ?>
            <div class="srch__group">
                <div class="section-head">
                    <div>
                        <p class="eyebrow"><?= (int) $productTotal ?> sản phẩm</p>
                        <h2 class="section-h2 section-h2--plain">Sản phẩm</h2>
                    </div>
                    <?php /* Sang trang danh mục với ĐÚNG từ khoá đó — nơi có bộ lọc
                             và phân trang thật, thứ trang này cố ý không dựng lại. */ ?>
                    <a class="pill-link" href="/san-pham?<?= e(http_build_query(['q' => $q])) ?>">
                        Xem tất cả →
                    </a>
                </div>

                <ul class="pcard__grid" role="list">
                    <?php foreach ($products as $p): ?>
                        <?php partial('_layout/product-card', ['product' => $p]); ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- ── Bài viết & sự kiện ── -->
        <?php if ($articles !== []): ?>
            <div class="srch__group">
                <div class="section-head">
                    <div>
                        <p class="eyebrow"><?= count($articles) ?> bài viết</p>
                        <h2 class="section-h2 section-h2--plain">Bài viết &amp; sự kiện</h2>
                    </div>
                    <a class="pill-link" href="/su-kien">Xem tất cả →</a>
                </div>

                <ul class="srch__list" role="list">
                    <?php foreach ($articles as $a): ?>
                        <li class="srchitem">
                            <a class="srchitem__link" href="/su-kien/<?= e(rawurlencode($a['slug'])) ?>">
                                <?php if (!empty($a['cover_image'])): ?>
                                    <span class="srchitem__media">
                                        <img src="<?= e(asset($a['cover_image'])) ?>" alt=""
                                             width="200" height="140" loading="lazy" decoding="async">
                                    </span>
                                <?php endif; ?>

                                <span class="srchitem__body">
                                    <span class="srchitem__meta">
                                        <?php if (!empty($a['category'])): ?>
                                            <span class="srchitem__tag"><?= e($a['category']) ?></span>
                                        <?php endif; ?>
                                        <?= e(dateRange($a['starts_at'] ?? null, $a['ends_at'] ?? null)) ?>
                                    </span>
                                    <span class="srchitem__title"><?= e($a['title']) ?></span>
                                    <span class="srchitem__excerpt"><?= e(excerpt($a['excerpt'] ?? '', 140)) ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- ── Cơ sở ── -->
        <?php if ($stores !== []): ?>
            <div class="srch__group">
                <div class="section-head">
                    <div>
                        <p class="eyebrow"><?= count($stores) ?> cơ sở</p>
                        <h2 class="section-h2 section-h2--plain">Cơ sở</h2>
                    </div>
                    <a class="pill-link" href="/lien-he">Xem bản đồ →</a>
                </div>

                <ul class="srch__stores" role="list">
                    <?php foreach ($stores as $s): ?>
                        <li class="srchstore">
                            <p class="srchstore__name"><?= e($s['name']) ?></p>
                            <p class="srchstore__addr"><?= e($s['address']) ?></p>
                            <?php if (!empty($s['phone'])): ?>
                                <p class="srchstore__phone"><?= e($s['phone']) ?></p>
                            <?php endif; ?>
                            <a class="srchstore__go" href="/lien-he">Xem chi tiết →</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- ── Chính sách ── -->
        <?php if ($policies !== []): ?>
            <div class="srch__group">
                <div class="section-head">
                    <div>
                        <p class="eyebrow"><?= count($policies) ?> câu hỏi</p>
                        <h2 class="section-h2 section-h2--plain">Chính sách &amp; hỏi đáp</h2>
                    </div>
                    <a class="pill-link" href="/chinh-sach">Xem tất cả →</a>
                </div>

                <ul class="srch__list" role="list">
                    <?php foreach ($policies as $p): ?>
                        <li class="srchitem srchitem--faq">
                            <a class="srchitem__link" href="/chinh-sach#<?= e($p['groupId']) ?>">
                                <span class="srchitem__body">
                                    <span class="srchitem__meta"><?= e($p['group']) ?></span>
                                    <span class="srchitem__title"><?= e($p['question']) ?></span>
                                    <span class="srchitem__excerpt"><?= e(excerpt($p['answer'], 160)) ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</section>
