<link rel="stylesheet" href="/assets/css/event.css">
<script src="/assets/js/event.js" defer></script>

<?php
$breadcrumb_items = [
    ['label' => 'Trang chủ', 'url' => '/'],
    ['label' => 'Tin tức & Sự kiện'],
];
$show_breadcrumb = true;
require_once APP_PATH . '/views/_layout/breadcrumb.php';
?>

<section class="page-header">
    <div class="container">
        <div class="header-content">
            <span class="header-label">JOURNAL</span>
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="header-tagline">Khám phá những câu chuyện độc bản và các cột mốc đáng nhớ trong hành trình của Vin Eyewear.</p>
        </div>
    </div>
</section>

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
                    <!-- Event 1 -->
                    <article class="event-card" data-event-category="tin-uu-dai">
                        <div class="event-image">
                            <img src="https://moscot.com/cdn/shop/files/1920x860_-HP_BANNER-DESKTOP-CUSTOM_MADE_TINT_2_1.jpg?v=1783603402&width=800" alt="The Heritage Collection Summer Sale">
                            <span class="event-badge">TIN ƯU ĐÃI</span>
                        </div>
                        <div class="event-content">
                            <time class="event-date label-mono">15/07/2026</time>
                            <h3 class="event-title">The Heritage Collection Summer Sale</h3>
                            <p class="event-excerpt">Giảm giá 20% cho toàn bộ bộ sưu tập Heritage Collection - cơ hội sở hữu những thiết kế kinh điển với mức giá đặc biệt.</p>
                            <a href="/event/detail" class="event-link">Đọc tiếp →</a>
                        </div>
                    </article>

                    <!-- Event 2 -->
                    <article class="event-card" data-event-category="su-kien">
                        <div class="event-image">
                            <img src="https://moscot.com/cdn/shop/files/1400x900_-FLOW-CMT_2_1.jpg?v=1783603988&width=800" alt="Custom Made Tints™ Fall Winter Launch">
                            <span class="event-badge">SỰ KIỆN</span>
                        </div>
                        <div class="event-content">
                            <time class="event-date label-mono">20/08/2026</time>
                            <h3 class="event-title">Custom Made Tints™ Fall Winter Launch</h3>
                            <p class="event-excerpt">Ra mắt bộ sưu tập Thu Đông 2026 với công nghệ Custom Made Tints™ độc quyền - màu sắc cá nhân hóa, phong cách không trùng lặp.</p>
                            <a href="/event/detail" class="event-link">Đọc tiếp →</a>
                        </div>
                    </article>

                    <!-- Event 3 -->
                    <article class="event-card" data-event-category="meo-cham-soc">
                        <div class="event-image">
                            <img src="https://moscot.com/cdn/shop/files/1080x1080_CRAFTSMANSHIP_-_HOMEPAGE_BANNER_MOBILE_1.jpg?v=1742324666&width=800" alt="Lower East Side Craftsmanship Workshop">
                            <span class="event-badge">MẸO CHĂM SÓC</span>
                        </div>
                        <div class="event-content">
                            <time class="event-date label-mono">01/09/2026</time>
                            <h3 class="event-title">Lower East Side Craftsmanship Workshop</h3>
                            <p class="event-excerpt">Hội thảo chuyên sâu về nghệ thuật chế tác kính - từ quy trình thủ công truyền thống đến công nghệ hiện đại.</p>
                            <a href="/event/detail" class="event-link">Đọc tiếp →</a>
                        </div>
                    </article>

                    <!-- Event 4 -->
                    <article class="event-card" data-event-category="tin-uu-dai">
                        <div class="event-image">
                            <img src="https://moscot.com/cdn/shop/files/920x920_-PROMO_GRID-EYEGLASSES-02_2_1.jpg?v=1782759499&width=800" alt="Eyewear Care Month">
                            <span class="event-badge">TIN ƯU ĐÃI</span>
                        </div>
                        <div class="event-content">
                            <time class="event-date label-mono">10/10/2026</time>
                            <h3 class="event-title">Eyewear Care Month</h3>
                            <p class="event-excerpt">Tháng chăm sóc kính miễn phí - vệ sinh, thay ốc, chỉnh khung miễn phí cho tất cả sản phẩm Vin Eyewear.</p>
                            <a href="/event/detail" class="event-link">Đọc tiếp →</a>
                        </div>
                    </article>

                    <!-- Event 5 -->
                    <article class="event-card" data-event-category="su-kien">
                        <div class="event-image">
                            <img src="https://moscot.com/cdn/shop/files/920x920_-PROMO_GRID-SUNGLASSES-02_2_1.jpg?v=1782759503&width=800" alt="New York Fashion Week Collaboration">
                            <span class="event-badge">SỰ KIỆN</span>
                        </div>
                        <div class="event-content">
                            <time class="event-date label-mono">15/11/2026</time>
                            <h3 class="event-title">New York Fashion Week Collaboration</h3>
                            <p class="event-excerpt">Hợp tác đặc biệt với New York Fashion Week - ra mắt bộ sưu tập giới hạn lấy cảm hứng từ đường phố Lower East Side.</p>
                            <a href="/event/detail" class="event-link">Đọc tiếp →</a>
                        </div>
                    </article>

                    <!-- Event 6 -->
                    <article class="event-card" data-event-category="meo-cham-soc">
                        <div class="event-image">
                            <img src="https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&h=600&fit=crop" alt="Lens Technology Guide">
                            <span class="event-badge">MẸO CHĂM SÓC</span>
                        </div>
                        <div class="event-content">
                            <time class="event-date label-mono">01/12/2026</time>
                            <h3 class="event-title">Lens Technology Guide</h3>
                            <p class="event-excerpt">Hướng dẫn chi tiết về các loại tròng kính - từ chống tia UV, chống ánh sáng xanh đến tròng đổi màu thông minh.</p>
                            <a href="/event/detail" class="event-link">Đọc tiếp →</a>
                        </div>
                    </article>
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