<?php
$show_breadcrumb = true;
$breadcrumb_items = [
    ['label' => 'Trang chủ', 'url' => '/'],
    ['label' => 'Tin tức & Sự kiện', 'url' => '/event'],
    ['label' => 'Chi tiết'],
];
$show_page_header = false;
$show_cta = false;
$show_pusher = true;
?>

<section class="event-detail-section">
    <div class="container">
        <!-- Back Navigation -->
        <a href="/event" class="back-link">← Quay lại danh sách sự kiện</a>

        <article class="event-detail">
            <!-- Event Meta (Trên cùng 1 dòng, sát phía trên ảnh featured) -->
            <div class="event-meta">
                <!-- Cụm bên trái: Thời gian sự kiện & Danh mục -->
                <div class="event-meta-left">
                    <time class="event-date label-mono"><?= htmlspecialchars($event['date']) ?></time>
                    <span class="event-category"><?= htmlspecialchars($event['category']) ?></span>
                </div>

                <!-- Cụm bên phải: Thời gian đăng & Tác giả -->
                <div class="event-meta-right">
                    <time class="event-published-at label-mono">Đăng lúc: <?= htmlspecialchars($event['published_at']) ?></time>
                    <span class="meta-divider">•</span>
                    <span class="event-author">Bởi: <strong><?= htmlspecialchars($event['author']) ?></strong></span>
                </div>
            </div>

            <!-- Featured Image -->
            <div class="event-featured-image">
                <img src="<?= htmlspecialchars($event['image']) ?>" alt="<?= htmlspecialchars($event['title']) ?>">
            </div>

            <!-- Event Content -->
            <div class="event-body">
                <h1 class="event-headline"><?= htmlspecialchars($event['title']) ?></h1>
                
                <!-- Intro Block -->
                <div class="event-content-block">
                    <p><?= htmlspecialchars($event['intro']) ?></p>
                </div>

                <!-- Section 1 -->
                <div class="event-content-block">
                    <h3><?= htmlspecialchars($event['section1_title']) ?></h3>
                    <p><?= htmlspecialchars($event['section1_content']) ?></p>
                </div>

                <!-- Details List -->
                <?php if (!empty($event['details'])): ?>
                <div class="event-content-block">
                    <h3>Thông tin chi tiết</h3>
                    <ul class="event-details-list">
                        <?php foreach ($event['details'] as $detail): ?>
                        <li>
                            <span class="detail-label"><?= htmlspecialchars($detail['label']) ?>:</span>
                            <span class="detail-value"><?= htmlspecialchars($detail['val']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Steps List -->
                <?php if (!empty($event['steps'])): ?>
                <div class="event-content-block">
                    <h3>Cách tham gia</h3>
                    <ol class="event-steps-list">
                        <?php foreach ($event['steps'] as $index => $step): ?>
                        <li><?= htmlspecialchars($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <?php endif; ?>

                <!-- Note -->
                <?php if (!empty($event['note'])): ?>
                <div class="event-content-block event-note">
                    <h3>Lưu ý quan trọng</h3>
                    <p><?= htmlspecialchars($event['note']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </article>

        <!-- Related Events Section -->
        <?php if (!empty($relatedEvents)): ?>
        <section class="related-events-section">
            <h2 class="related-events-title">Bài viết liên quan</h2>
            <div class="related-events-grid">
                <?php foreach ($relatedEvents as $relatedEvent): ?>
                <article class="related-event-card">
                    <div class="related-event-image">
                        <a href="/event/<?= htmlspecialchars($relatedEvent['id']) ?>">
                            <img src="<?= htmlspecialchars($relatedEvent['image']) ?>" alt="<?= htmlspecialchars($relatedEvent['title']) ?>" loading="lazy">
                            <span class="event-badge"><?= htmlspecialchars($relatedEvent['category']) ?></span>
                        </a>
                    </div>
                    <div class="related-event-content">
                        <time class="event-date label-mono"><?= htmlspecialchars($relatedEvent['date']) ?></time>
                        <h3 class="related-event-title">
                            <a href="/event/<?= htmlspecialchars($relatedEvent['id']) ?>"><?= htmlspecialchars($relatedEvent['title']) ?></a>
                        </h3>
                        <p class="event-excerpt"><?= htmlspecialchars($relatedEvent['excerpt']) ?></p>
                        <a href="/event/<?= htmlspecialchars($relatedEvent['id']) ?>" class="event-link">Đọc tiếp →</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</section>
