<?php
$show_breadcrumb = true;
$breadcrumb_items = [
    ['label' => 'Trang chủ', 'url' => '/'],
    ['label' => 'Tin tức & Sự kiện'],
];
$show_page_header = true;
$page_eyebrow = 'JOURNAL';
$page_subtitle = 'Khám phá những câu chuyện độc bản và các cột mốc đáng nhớ trong hành trình của Vin Eyewear.';
$show_cta = false;
$show_pusher = true;
?>

<script src="/assets/js/event.js" defer></script>

<section class="events-section">
    <div class="container">
        <div class="events-layout">
            <!-- Filter Sidebar -->
            <?php
            $filter_type = 'event';
            require_once APP_PATH . '/views/_layout/filter-sidebar.php';
            ?>

            <!-- Events Grid -->
            <div class="events-main">
                <div class="events-grid">
                    <?php foreach ($eventsData as $event): ?>
                    <article class="event-card" data-event-category="<?= strtolower(str_replace(' ', '-', $event['category'])) ?>">
                        <div class="event-image">
                            <img src="<?= htmlspecialchars($event['image']) ?>" alt="<?= htmlspecialchars($event['title']) ?>" loading="lazy">
                            <span class="event-badge"><?= htmlspecialchars($event['category']) ?></span>
                        </div>
                        <div class="event-content">
                            <time class="event-date label-mono"><?= htmlspecialchars($event['date']) ?></time>
                            <h3 class="event-title">
                                <a href="/event/<?= htmlspecialchars($event['id']) ?>"><?= htmlspecialchars($event['title']) ?></a>
                            </h3>
                            <p class="event-excerpt"><?= htmlspecialchars($event['excerpt']) ?></p>
                            <a href="/event/<?= htmlspecialchars($event['id']) ?>" class="event-link">Đọc tiếp →</a>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Image Modal -->
<div class="image-modal" id="imageModal">
    <div class="modal-backdrop"></div>
    <button class="modal-close" aria-label="Close modal">&times;</button>
    <div class="modal-content">
        <img id="modalImage" src="" alt="" class="modal-image">
    </div>
</div>