<?php

/**
 * event/detail.php — chi tiết sự kiện / khuyến mãi (/su-kien/{slug})
 *
 * Dựng theo "Vin Eyewear Article.dc.html" (Claude Design):
 *
 *   đầu trang hồng phấn có nhãn phân loại (dùng chung _layout/page-head.php)
 *   → hai cột: bài viết | cột phụ 360px dính theo cuộn
 *   → khối "Bài viết khác"
 *
 * CSS: assets/css/event.css (khối .art*)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THÂN BÀI VIẾT BẰNG MARKDOWN RÚT GỌN
 *
 * Bản thiết kế vẽ tiêu đề phụ, danh sách đánh số và gạch đầu dòng — cột
 * `events.content` là văn bản thuần nên không mang được. Nhân viên nay gõ
 * `## Tiêu đề`, `- mục`, `1. bước`, `**đậm**` và core/Markdown.php dựng ra HTML.
 *
 * KHÔNG nhận HTML của người dùng: chữ được escape TRƯỚC rồi mới bọc thẻ, nên
 * không có đường nào để thẻ lạ lọt ra — xem ghi chú đầu core/Markdown.php.
 * Bài cũ chỉ có văn bản thuần vẫn hiện y như trước.
 * ─────────────────────────────────────────────────────────────────────────────
 */

$body   = Markdown::render($event['content'] ?? '');
$shared = rtrim((string) config('app.url'), '/') . '/su-kien/' . rawurlencode($event['slug']);

/* Tiêu đề bài viết là <h1> DUY NHẤT của trang — thân bài chỉ có ## trở xuống
   (xem core/Markdown.php), nên nó phải nằm ở đây. Mô tả lấy từ excerpt: bài
   nào chưa có excerpt thì component tự bỏ phần đó, không in dòng rỗng. */
partial('_layout/page-head', [
    'head_crumbs' => [
        ['label' => 'Sự kiện & Tin tức', 'url' => '/su-kien'],
        ['label' => $event['title']],
    ],
    'head_badge'  => $event['category'] ?? 'SỰ KIỆN',
    'head_title'  => $event['title'],
    'head_lead'   => $event['excerpt'] ?? '',
]);
?>

<section class="art">
    <div class="art__grid">

        <!-- ══════════ BÀI VIẾT ══════════ -->
        <article class="artbody">
            <div class="artbody__cover">
                <?php if (!empty($event['cover_image'])): ?>
                    <img src="<?= e($event['cover_image']) ?>" alt="<?= e($event['title']) ?>"
                         width="900" height="420" fetchpriority="high" decoding="async">
                <?php else: ?>
                    <!-- Bài chưa có ảnh bìa. Vẫn giữ khối ảnh và vẽ một dấu
                         hiệu, KHÔNG bỏ hẳn: bỏ đi thì bài viết thiếu ảnh trông
                         như một trang khác hẳn so với bài có ảnh. Cùng cách xử
                         lý với thẻ ở trang danh sách. -->
                    <span class="nw__noimg" aria-hidden="true">
                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="6.5" cy="12" r="4"></circle>
                            <circle cx="17.5" cy="12" r="4"></circle>
                            <path d="M10.5 12h3M3 11l2-4.5M21 11l-2-4.5"></path>
                        </svg>
                    </span>
                <?php endif; ?>
            </div>

            <div class="artbody__inner">
                <?php if (!empty($event['excerpt'])): ?>
                    <!-- Đoạn mở đầu in nghiêng — bản thiết kế dùng chính phần
                         tóm tắt của bài làm câu dẫn, nên lấy thẳng `excerpt`
                         thay vì bắt nhân viên viết lại lần nữa. -->
                    <p class="artbody__lead"><?= e($event['excerpt']) ?></p>
                    <div class="artbody__rule"></div>
                <?php endif; ?>

                <?php if ($body !== ''): ?>
                    <div class="artbody__rich"><?= $body ?></div>
                <?php else: ?>
                    <p class="artbody__none">Nội dung chi tiết đang được cập nhật.</p>
                <?php endif; ?>

                <?php if (!Markdown::hasQuote($event['content'] ?? '')): ?>
                    <!-- Hộp mẹo mặc định, chỉ hiện khi bài KHÔNG tự viết lời
                         nhắc nào (`> …` trong nội dung) — hai hộp chồng nhau
                         thì cái nào cũng mất trọng lượng. -->
                    <div class="artnote">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 8v4.5M12 16h.01"></path>
                        </svg>
                        <p>
                            Mẹo: đặt lịch trước qua hotline
                            <strong><?= e(config('company.hotline')) ?></strong>
                            để được giữ chỗ và không phải chờ đo mắt.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <!-- ══════════ CỘT PHỤ ══════════ -->
        <aside class="artside">

            <div class="artside__card">
                <h2 class="artside__title">Thông tin chương trình</h2>

                <div class="artfacts">
                    <?php if (!empty($event['starts_at'])): ?>
                        <div class="artfact">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="16" rx="3"></rect>
                                <path d="M8 3v4M16 3v4M3 10h18"></path>
                            </svg>
                            <span class="artfact__body">
                                <span class="artfact__label">Thời gian</span>
                                <span class="artfact__value"><?= e(dateRange($event['starts_at'], $event['ends_at'])) ?></span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($event['location'])): ?>
                        <div class="artfact">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <span class="artfact__body">
                                <span class="artfact__label">Địa điểm</span>
                                <span class="artfact__value"><?= e($event['location']) ?></span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($event['category'])): ?>
                        <div class="artfact">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20.6 13.4L11 3H3v8l9.6 10.4a2 2 0 0 0 2.8 0l5.2-5.2a2 2 0 0 0 0-2.8z"></path>
                                <circle cx="7.5" cy="7.5" r="1"></circle>
                            </svg>
                            <span class="artfact__body">
                                <span class="artfact__label">Phân loại</span>
                                <span class="artfact__value"><?= e($event['category']) ?></span>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="artside__rule"></div>

                <?php /* Mang theo slug bài viết: trang đặt lịch tra lại sự kiện trong
                         CSDL rồi điền sẵn cơ sở, ngày, khung giờ và ghi chú —
                         xem BookingController::eventPrefill(). Chỉ gửi slug chứ
                         không gửi ngày/giờ, để sửa tay địa chỉ cũng không đặt
                         được ra ngoài lịch đang mở. */ ?>
                <a class="artside__cta" href="/dat-lich?su-kien=<?= e(rawurlencode($event['slug'])) ?>">Đặt lịch tham dự</a>
                <a class="artside__back" href="/su-kien">← Xem sự kiện khác</a>
            </div>

            <div class="artshare">
                <span class="artshare__label">Chia sẻ bài viết</span>

                <div class="artshare__row">
                    <!-- Liên kết chia sẻ THẬT, không cần JS. rel="noopener" vì
                         target=_blank cho trang đích quyền chạm vào window.opener. -->
                    <a class="artshare__btn"
                       href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($shared)) ?>"
                       target="_blank" rel="noopener noreferrer" aria-label="Chia sẻ lên Facebook">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M13.5 21.5v-7.2h2.42l.36-2.8h-2.78V9.7c0-.81.23-1.36 1.39-1.36h1.49V5.83c-.26-.03-1.14-.11-2.17-.11-2.15 0-3.62 1.31-3.62 3.72v2.06H8.16v2.8h2.43v7.2h2.91z"></path>
                        </svg>
                    </a>

                    <!-- Nút sao chép cần JS nên mặc định ẩn; assets/js/share.js
                         gỡ `hidden` ra. Một nút bấm mà không xảy ra gì thì tệ
                         hơn là không có nút — cùng cách đã làm với nút con mắt
                         ở ô mật khẩu. -->
                    <button type="button" class="artshare__btn" id="copy-link" hidden
                            data-url="<?= e($shared) ?>" aria-label="Sao chép liên kết">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"></path>
                            <path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- ══════════ BÀI VIẾT KHÁC ══════════ -->
    <?php if ($others !== []): ?>
        <section class="artmore" aria-labelledby="bai-khac">
            <div class="artmore__head">
                <h2 class="artmore__title" id="bai-khac">Bài viết khác</h2>
                <a class="artmore__all" href="/su-kien">Tất cả bài viết →</a>
            </div>

            <div class="artmore__grid">
                <?php foreach ($others as $other): ?>
                    <?php $url = '/su-kien/' . rawurlencode($other['slug']); ?>
                    <a class="artmore__card" href="<?= e($url) ?>">
                        <span class="artmore__media">
                            <?php if (!empty($other['cover_image'])): ?>
                                <img src="<?= e($other['cover_image']) ?>" alt=""
                                     width="420" height="200" loading="lazy" decoding="async">
                            <?php else: ?>
                                <span class="nw__noimg" aria-hidden="true">
                                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="1.5"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="6.5" cy="12" r="4"></circle>
                                        <circle cx="17.5" cy="12" r="4"></circle>
                                        <path d="M10.5 12h3M3 11l2-4.5M21 11l-2-4.5"></path>
                                    </svg>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($other['category'])): ?>
                                <span class="artmore__tag"><?= e($other['category']) ?></span>
                            <?php endif; ?>
                        </span>

                        <span class="artmore__body">
                            <span class="artmore__date">
                                <?= e(dateRange($other['starts_at'], $other['ends_at'])) ?>
                            </span>
                            <span class="artmore__cardtitle"><?= e($other['title']) ?></span>
                            <span class="artmore__more">Đọc tiếp <span aria-hidden="true">→</span></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
