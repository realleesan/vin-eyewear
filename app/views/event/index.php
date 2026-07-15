<section class="page-header">
    <div class="container">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p>Sự kiện và khuyến mãi</p>
    </div>
</section>

<section class="events-section">
    <div class="container">
        <!-- Event Slideshow -->
        <div class="event-slideshow">
            <div class="slideshow-container">
                <!-- Slide 1 -->
                <div class="event-slide active" data-event-id="1">
                    <div class="slide-thumbnail">
                        <img src="https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=600&h=340&fit=crop" alt="Khuyến mãi mùa hè">
                        <div class="slide-overlay">
                            <span class="slide-date">15/07/2026</span>
                            <h3 class="slide-title">Khuyến Mãi Mùa Hè</h3>
                            <p class="slide-preview">Giảm giá 20% tất cả sản phẩm kính mắt</p>
                        </div>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="event-slide" data-event-id="2">
                    <div class="slide-thumbnail">
                        <img src="https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=600&h=340&fit=crop" alt="Bộ sưu tập mới">
                        <div class="slide-overlay">
                            <span class="slide-date">20/08/2026</span>
                            <h3 class="slide-title">Ra Mắt BST Thu Đông 2026</h3>
                            <p class="slide-preview">Giới thiệu bộ sưu tập kính mắt thu đông 2026</p>
                        </div>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="event-slide" data-event-id="3">
                    <div class="slide-thumbnail">
                        <img src="https://images.unsplash.com/photo-1556306535-0f09a537f0a3?w=600&h=340&fit=crop" alt="Tư vấn miễn phí">
                        <div class="slide-overlay">
                            <span class="slide-date">01/09/2026</span>
                            <h3 class="slide-title">Tư Vấn Miễn Phí</h3>
                            <p class="slide-preview">Đo mắt và tư vấn chọn kính miễn phí tại cửa hàng</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slideshow Navigation -->
            <div class="slideshow-nav">
                <button class="nav-btn prev-btn" aria-label="Previous slide">‹</button>
                <div class="nav-dots">
                    <button class="nav-dot active" data-slide="0"></button>
                    <button class="nav-dot" data-slide="1"></button>
                    <button class="nav-dot" data-slide="2"></button>
                </div>
                <button class="nav-btn next-btn" aria-label="Next slide">›</button>
            </div>
        </div>

        <!-- Event Detail Modal (Hidden by default) -->
        <div class="event-detail-modal" id="eventDetailModal">
            <div class="modal-content">
                <button class="modal-close" aria-label="Close modal">×</button>
                <div class="modal-body">
                    <div class="event-detail-image">
                        <img id="modalEventImage" src="" alt="">
                    </div>
                    <div class="event-detail-info">
                        <span class="label-mono" id="modalEventDate"></span>
                        <h2 class="headline" id="modalEventTitle"></h2>
                        <div class="event-detail-description" id="modalEventDescription"></div>
                        
                        <!-- Product Carousel -->
                        <div class="product-carousel-section">
                            <span class="subheading-caps">SẢN PHẨM ÁP DỤNG</span>
                            <div class="product-carousel">
                                <div class="carousel-track">
                                    <div class="product-card">
                                        <img src="https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=300&h=225&fit=crop" alt="Product 1">
                                        <h4>The Lemtosh</h4>
                                        <span class="label-mono">3,500,000₫</span>
                                    </div>
                                    <div class="product-card">
                                        <img src="https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=300&h=225&fit=crop" alt="Product 2">
                                        <h4>Miltzen</h4>
                                        <span class="label-mono">2,800,000₫</span>
                                    </div>
                                    <div class="product-card">
                                        <img src="https://images.unsplash.com/photo-1556306535-0f09a537f0a3?w=300&h=225&fit=crop" alt="Product 3">
                                        <h4>Lemtosh Crystal</h4>
                                        <span class="label-mono">4,200,000₫</span>
                                    </div>
                                    <div class="product-card">
                                        <img src="https://images.unsplash.com/photo-1577803645773-f96470509666?w=300&h=225&fit=crop" alt="Product 4">
                                        <h4>MOSCOT Original</h4>
                                        <span class="label-mono">3,100,000₫</span>
                                    </div>
                                </div>
                                <div class="carousel-nav">
                                    <button class="carousel-btn carousel-prev">‹</button>
                                    <button class="carousel-btn carousel-next">›</button>
                                </div>
                            </div>
                        </div>

                        <div class="event-cta">
                            <a href="/product" class="btn btn-primary">XEM TẤT CẢ SẢN PHẨM</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pass event data to JavaScript -->
<script>
window.eventData = <?php echo isset($eventData) ? json_encode($eventData) : '{}'; ?>;
</script>