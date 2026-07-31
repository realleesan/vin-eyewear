<?php
/**
 * event/index.php
 * Biến nhận từ EventController::index():
 *   $eventsData — mảng bài viết/sự kiện (id, title, category, date, image, excerpt, ...)
 * Filter: khung UI dùng component chung _layout/filter-sidebar.php ("Vỏ"),
 * logic lọc client-side nằm ở assets/js/event.js (đọc data-event-category trên card).
 */

/* Slug hoá nhãn danh mục để làm giá trị lọc.
   LƯU Ý: strtolower() là byte-based nên KHÔNG hạ được chữ tiếng Việt có dấu
   ("TIN ƯU ĐÃI" -> "tin-Ưu-ĐÃi"), phải bỏ dấu trước rồi mới hạ chữ.
   Không dùng mb_* vì mbstring có thể không bật trên môi trường chạy thật;
   preg_split('//u') chỉ cần PCRE có UTF-8. */
$vnAsciiMap = [
    'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'A' => 'ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ',
    'e' => 'èéẹẻẽêềếệểễ',       'E' => 'ÈÉẸẺẼÊỀẾỆỂỄ',
    'i' => 'ìíịỉĩ',             'I' => 'ÌÍỊỈĨ',
    'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'O' => 'ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ',
    'u' => 'ùúụủũưừứựửữ',       'U' => 'ÙÚỤỦŨƯỪỨỰỬỮ',
    'y' => 'ỳýỵỷỹ',             'Y' => 'ỲÝỴỶỸ',
    'd' => 'đ',                 'D' => 'Đ',
];

$vnTranslit = [];
foreach ($vnAsciiMap as $ascii => $chars) {
    foreach (preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        $vnTranslit[$ch] = $ascii;
    }
}

$eventSlug = static function (string $text) use ($vnTranslit): string {
    $slug = strtolower(strtr($text, $vnTranslit));
    return trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
};

/* Danh mục cho filter sidebar — suy ra từ $eventsData, KHÔNG hard-code:
   danh sách chip luôn khớp đúng dữ liệu controller đang trả về. */
$filter_event_categories = [];
foreach ($eventsData as $event) {
    $label = trim($event['category'] ?? '');
    if ($label === '') {
        continue;
    }
    $slug = $eventSlug($label);
    if ($slug !== '' && !isset($filter_event_categories[$slug])) {
        $filter_event_categories[$slug] = $label;
    }
}

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
            require VIEWS_PATH . '/_layout/filter-sidebar.php';
            ?>

            <!-- Events Grid -->
            <div class="events-main">
                <div class="events-grid">
                    <?php foreach ($eventsData as $event): ?>
                    <article class="event-card" data-event-category="<?= htmlspecialchars($eventSlug($event['category'])) ?>">
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