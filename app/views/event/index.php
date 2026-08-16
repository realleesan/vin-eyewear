<?php

/**
 * event/index.php — sự kiện, tin tức & khuyến mãi (/su-kien)
 *
 * Dựng theo "Vin Eyewear News.dc.html" (Claude Design):
 *
 *   đầu trang hồng phấn (dùng chung _layout/page-head.php)
 *   → dải lọc nhóm + số bài
 *   → thẻ NỔI BẬT nền tối, hai cột
 *   → lưới 3 cột
 *   → phân trang → khối đăng ký nhận tin
 *
 * CSS: assets/css/event.css (khối .nw*)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CHỖ ĐÁNG GHI CHÚ
 *
 * 1. BÀI NỔI BẬT KHÔNG PHẢI MỘT CỜ TRONG CSDL — nó là bài mới nhất, và chỉ
 *    hiện khi đang xem "Tất cả" ở trang 1. Bản thiết kế làm đúng vậy, nên
 *    không cần thêm cột nào. Xem EventController::index().
 *
 * 2. LỌC VÀ PHÂN TRANG BẰNG URL, không bằng JavaScript — mỗi nhóm và mỗi
 *    trang là một địa chỉ riêng, gửi link được và F5 không mất chỗ. Cùng cách
 *    đã dùng ở trang danh sách sản phẩm.
 *
 * 3. NHÓM HIỆN ĐÚNG NHƯ CSDL LƯU. Cửa hàng đang lưu chữ IN HOA ("SỰ KIỆN"),
 *    còn bản thiết kế vẽ chữ thường ("Sự kiện"). Không tự đổi ở đây: viết hoa
 *    lại bằng code sẽ phá tên riêng, và đó là dữ liệu của cửa hàng chứ không
 *    phải chuyện trình bày.
 * ─────────────────────────────────────────────────────────────────────────────
 */

partial('_layout/page-head', [
    'head_crumbs' => [['label' => 'Sự kiện & Tin tức']],
    'head_title'  => 'Sự kiện & Tin tức',
    'head_lead'   => 'Workshop, triển lãm, ưu đãi và những câu chuyện mới nhất '
                   . 'từ hai cơ sở Vin Eyewear.',
]);

/** Đường dẫn giữ nguyên nhóm đang lọc, chỉ đổi số trang. */
$pageUrl = static function (int $n) use ($active): string {
    $q = array_filter(['category' => $active, 'page' => $n > 1 ? $n : null]);

    return '/su-kien' . ($q === [] ? '' : '?' . http_build_query($q));
};

/**
 * Ô ảnh trống khi bài chưa có ảnh bìa.
 *
 * KHÔNG mượn ảnh của bài khác cho đủ chỗ — người xem sẽ tưởng đó là ảnh của
 * bài đang đọc. Vẽ một dấu hiệu nhỏ để ô trống trông có chủ ý, thay vì một
 * mảng màu phẳng trông như ảnh hỏng. Cùng nguyên tắc với
 * ProductModel::hasImage().
 */
$blankCover = static fn (): string =>
    '<span class="nw__noimg" aria-hidden="true">'
    . '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
    . ' stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
    . '<circle cx="6.5" cy="12" r="4"></circle><circle cx="17.5" cy="12" r="4"></circle>'
    . '<path d="M10.5 12h3M3 11l2-4.5M21 11l-2-4.5"></path></svg></span>';

/** Ngày hiển thị: khoảng ngày nếu có ngày kết thúc, không thì một ngày. */
$whenOf = static fn (array $e): string =>
    !empty($e['ends_at']) ? dateRange($e['starts_at'], $e['ends_at']) : formatDate($e['starts_at']);
?>

<section class="nw">

    <!-- ══════════ DẢI LỌC ══════════ -->
    <div class="nw__bar">
        <div class="nw__filters">
            <a class="nw__pill<?= $active === '' ? ' is-on' : '' ?>" href="/su-kien"
               <?= $active === '' ? 'aria-current="page"' : '' ?>>
                Tất cả (<?= (int) ($counts[''] ?? 0) ?>)
            </a>

            <?php foreach ($categories as $category): ?>
                <a class="nw__pill<?= $active === $category ? ' is-on' : '' ?>"
                   href="/su-kien?category=<?= e(rawurlencode($category)) ?>"
                   <?= $active === $category ? 'aria-current="page"' : '' ?>>
                    <?= e($category) ?> (<?= (int) ($counts[$category] ?? 0) ?>)
                </a>
            <?php endforeach; ?>
        </div>

        <span class="nw__count">
            <?= (int) $total ?> bài viết<?= $totalPages > 1 ? ' · trang ' . (int) $page . '/' . (int) $totalPages : '' ?>
        </span>
    </div>

    <?php if ($featured === null && $events === []): ?>

        <div class="nw__empty">
            <span class="nw__empty-ring" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#b0736a"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 3v3M17 3v3"></path>
                    <path d="M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"></path>
                </svg>
            </span>
            <span class="nw__empty-title">Chưa có bài viết nào</span>
            <span class="nw__empty-lead">Nhóm này chưa có nội dung. Thử xem tất cả bài viết.</span>
            <a class="nw__empty-cta" href="/su-kien">Xem tất cả</a>
        </div>

    <?php else: ?>

        <!-- ══════════ BÀI NỔI BẬT ══════════ -->
        <?php if ($featured !== null): ?>
            <a class="nwbig" href="/su-kien/<?= e(rawurlencode($featured['slug'])) ?>">
                <span class="nwbig__media">
                    <?php if (!empty($featured['cover_image'])): ?>
                        <img src="<?= e($featured['cover_image']) ?>" alt=""
                             width="640" height="400" fetchpriority="high" decoding="async">
                    <?php else: ?>
                        <?= $blankCover() ?>
                    <?php endif; ?>
                </span>

                <span class="nwbig__body">
                    <span class="nwbig__top">
                        <?php if (!empty($featured['category'])): ?>
                            <span class="nwbig__tag"><?= e($featured['category']) ?></span>
                        <?php endif; ?>
                        <span class="nwbig__date">
                            <?= e($whenOf($featured)) ?><?php
                                if (!empty($featured['location'])): ?> · <?= e($featured['location']) ?><?php endif; ?>
                        </span>
                    </span>

                    <span class="nwbig__title"><?= e($featured['title']) ?></span>

                    <?php if (!empty($featured['excerpt'])): ?>
                        <span class="nwbig__excerpt"><?= e(excerpt($featured['excerpt'], 180)) ?></span>
                    <?php endif; ?>

                    <span class="nwbig__more">Đọc tiếp <span aria-hidden="true">→</span></span>
                </span>
            </a>
        <?php endif; ?>

        <!-- ══════════ LƯỚI ══════════ -->
        <?php if ($events !== []): ?>
            <div class="nw__grid">
                <?php foreach ($events as $e): ?>
                    <a class="nwcard" href="/su-kien/<?= e(rawurlencode($e['slug'])) ?>">
                        <span class="nwcard__media">
                            <?php if (!empty($e['cover_image'])): ?>
                                <img src="<?= e($e['cover_image']) ?>" alt=""
                                     width="420" height="220" loading="lazy" decoding="async">
                            <?php else: ?>
                                <?= $blankCover() ?>
                            <?php endif; ?>

                            <?php if (!empty($e['category'])): ?>
                                <span class="nwcard__tag"><?= e($e['category']) ?></span>
                            <?php endif; ?>
                        </span>

                        <span class="nwcard__body">
                            <span class="nwcard__date">
                                <?= e($whenOf($e)) ?><?php
                                    if (!empty($e['location'])): ?> · <?= e($e['location']) ?><?php endif; ?>
                            </span>

                            <span class="nwcard__title"><?= e($e['title']) ?></span>

                            <?php if (!empty($e['excerpt'])): ?>
                                <span class="nwcard__excerpt"><?= e(excerpt($e['excerpt'], 120)) ?></span>
                            <?php endif; ?>

                            <span class="nwcard__more">Đọc tiếp <span aria-hidden="true">→</span></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ══════════ PHÂN TRANG ══════════ -->
        <?php if ($totalPages > 1): ?>
            <nav class="nwpage" aria-label="Phân trang">
                <?php if ($page > 1): ?>
                    <a class="nwpage__nav" href="<?= e($pageUrl($page - 1)) ?>" rel="prev"
                       aria-label="Trang trước">←</a>
                <?php else: ?>
                    <!-- Ở trang đầu thì nút lùi là một <span> chứ không phải link
                         mờ: link không đi đâu vẫn bấm được và vẫn nằm trong thứ
                         tự Tab, chỉ tổ gây nhầm. -->
                    <span class="nwpage__nav is-off" aria-hidden="true">←</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="nwpage__num is-on" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a class="nwpage__num" href="<?= e($pageUrl($i)) ?>"
                           aria-label="Trang <?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="nwpage__nav" href="<?= e($pageUrl($page + 1)) ?>" rel="next"
                       aria-label="Trang sau">→</a>
                <?php else: ?>
                    <span class="nwpage__nav is-off" aria-hidden="true">→</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ══════════ ĐĂNG KÝ NHẬN TIN ══════════ -->
    <?php
    /* Đọc flash NGAY TẠI ĐÂY, không nhận qua controller — flash() đọc một lần
       là xoá, cùng lý do đã ghi ở _layout/home/newsletter.php. */
    $nlOk  = flash('newsletter_success');
    $nlErr = flash('newsletter_error');
    $nlOld = $_SESSION['_old_newsletter'] ?? '';
    unset($_SESSION['_old_newsletter']);
    ?>
    <div class="nwmail" id="dang-ky-nhan-tin">
        <div class="nwmail__text">
            <span class="nwmail__title">Đừng bỏ lỡ sự kiện nào</span>
            <span class="nwmail__lead">Nhận tin workshop, triển lãm và ưu đãi sớm nhất qua email.</span>

            <?php if ($nlOk !== null): ?>
                <span class="nwmail__msg is-ok" role="status"><?= e($nlOk) ?></span>
            <?php endif; ?>
            <?php if ($nlErr !== null): ?>
                <span class="nwmail__msg is-err" role="alert"><?= e($nlErr) ?></span>
            <?php endif; ?>
        </div>

        <form class="nwmail__form" method="post" action="/nhan-tin/dang-ky">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <!-- Quay lại ĐÚNG trang này sau khi gửi. Giá trị đi qua
                 safeRedirectPath() ở controller nên sửa tay cũng không dẫn ra
                 ngoài site được. -->
            <input type="hidden" name="redirect" value="/su-kien">
            <input type="hidden" name="source" value="su-kien">

            <label class="sr-only" for="nl-email">Email của bạn</label>
            <input class="nwmail__input" type="email" id="nl-email" name="email" required
                   autocomplete="email" placeholder="Email của bạn"
                   value="<?= e($nlOld) ?>">

            <button type="submit" class="nwmail__send">Đăng ký</button>
        </form>
    </div>
</section>
