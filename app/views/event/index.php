<section class="page-header">
    <div class="container">
        <h1><?php echo $pageTitle; ?></h1>
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
                        <img src="/assets/images/event/slide-1-thumb.jpg" alt="Khuyến mãi mùa hè">
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
                        <img src="/assets/images/event/slide-2-thumb.jpg" alt="Bộ sưu tập mới">
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
                        <img src="/assets/images/event/slide-3-thumb.jpg" alt="Tư vấn miễn phí">
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
                                        <img src="/assets/images/products/product-1.jpg" alt="Product 1">
                                        <h4>The Lemtosh</h4>
                                        <span class="label-mono">3,500,000₫</span>
                                    </div>
                                    <div class="product-card">
                                        <img src="/assets/images/products/product-2.jpg" alt="Product 2">
                                        <h4>Miltzen</h4>
                                        <span class="label-mono">2,800,000₫</span>
                                    </div>
                                    <div class="product-card">
                                        <img src="/assets/images/products/product-3.jpg" alt="Product 3">
                                        <h4>Lemtosh Crystal</h4>
                                        <span class="label-mono">4,200,000₫</span>
                                    </div>
                                    <div class="product-card">
                                        <img src="/assets/images/products/product-4.jpg" alt="Product 4">
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

<script>
// Event data
const eventData = {
    1: {
        date: '15/07/2026',
        title: 'Khuyến Mãi Mùa Hè',
        description: `
            <p>Chào đón mùa hè sôi động, Vin Eyewear mang đến chương trình khuyến mãi đặc biệt với giảm giá 20% cho tất cả sản phẩm kính mắt. Đây là cơ hội tuyệt vời để bạn sở hữu những chiếc kính chất lượng cao với mức giá ưu đãi.</p>
            <p>Chương trình áp dụng cho cả gọng kính thời trang và kính thuốc từ các thương hiệu uy tín. Đặc biệt, khi mua trọn bộ (gọng + tròng), bạn sẽ được tặng thêm bộ vệ sinh kính cao cấp.</p>
            <p><strong>Thời gian áp dụng:</strong> 15/07/2026 - 15/08/2026</p>
            <p><strong>Địa điểm áp dụng:</strong> Cả hai cơ sở Long Biên và Tây Hồ</p>
        `,
        image: '/assets/images/event/slide-1-full.jpg'
    },
    2: {
        date: '20/08/2026',
        title: 'Ra Mắt BST Thu Đông 2026',
        description: `
            <p>Vin Eyewear hân hoan giới thiệu bộ sưu tập kính mắt Thu Đông 2026 với những thiết kế đột phá, kết hợp giữa nét cổ điển và hiện đại. BST mới mang đến những lựa chọn phong cách đa dạng cho mọi dịp sử dụng.</p>
            <p>Nổi bật trong bộ sưu tập là dòng gọng kính acetate với màu sắc trầm ấm, phù hợp với trang phục thu đông, cùng các tròng kính chống ánh sáng xanh thế hệ mới bảo vệ mắt tối ưu.</p>
            <p><strong>Sự kiện ra mắt:</strong> 20/08/2026 tại cơ sở Long Biên</p>
            <p><strong>Ưu đãi đặc biệt:</strong> Giảm 15% cho BST mới trong tuần đầu ra mắt</p>
        `,
        image: '/assets/images/event/slide-2-full.jpg'
    },
    3: {
        date: '01/09/2026',
        title: 'Tư Vấn Miễn Phí',
        description: `
            <p>Dịch vụ tư vấn chuyên nghiệp tại Vin Eyewear giúp bạn tìm được chiếc kính hoàn hảo nhất. Đội ngũ kỹ thuật viên có kinh nghiệm sẽ đo mắt và tư vấn miễn phí, đảm bảo bạn chọn được sản phẩm phù hợp nhất.</p>
            <p>Dịch vụ bao gồm: đo khúc xạ, đo khoảng cách đồng tử (PD), tư vấn chọn gọng phù hợp khuôn mặt, và hướng dẫn sử dụng kính đúng cách.</p>
            <p><strong>Thời gian:</strong> 01/09/2026 - 30/09/2026</p>
            <p><strong>Địa điểm:</strong> Cả hai cơ sở Long Biên và Tây Hồ</p>
            <p><strong>Đăng ký trước:</strong> Ưu tiên phục vụ khách hàng đã đặt lịch</p>
        `,
        image: '/assets/images/event/slide-3-full.jpg'
    }
};

// Slideshow functionality
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.event-slide');
    const dots = document.querySelectorAll('.nav-dot');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const modal = document.getElementById('eventDetailModal');
    let currentSlide = 0;
    const totalSlides = slides.length;

    function showSlide(index) {
        slides.forEach((slide, i) => {
            slide.classList.remove('active');
            dots[i].classList.remove('active');
        });
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        currentSlide = index;
    }

    function nextSlide() {
        showSlide((currentSlide + 1) % totalSlides);
    }

    function prevSlide() {
        showSlide((currentSlide - 1 + totalSlides) % totalSlides);
    }

    // Navigation buttons
    nextBtn.addEventListener('click', nextSlide);
    prevBtn.addEventListener('click', prevSlide);

    // Dot navigation
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => showSlide(index));
    });

    // Auto-play slideshow
    let autoPlay = setInterval(nextSlide, 5000);

    // Pause on hover
    const slideshowContainer = document.querySelector('.slideshow-container');
    slideshowContainer.addEventListener('mouseenter', () => clearInterval(autoPlay));
    slideshowContainer.addEventListener('mouseleave', () => autoPlay = setInterval(nextSlide, 5000));

    // Click on slide to open modal
    slides.forEach(slide => {
        slide.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            const event = eventData[eventId];
            
            if (event) {
                document.getElementById('modalEventDate').textContent = event.date;
                document.getElementById('modalEventTitle').textContent = event.title;
                document.getElementById('modalEventDescription').innerHTML = event.description;
                document.getElementById('modalEventImage').src = event.image;
                document.getElementById('modalEventImage').alt = event.title;
                
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    // Close modal
    document.querySelector('.modal-close').addEventListener('click', function() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Product carousel
    const carouselTrack = document.querySelector('.carousel-track');
    const carouselPrev = document.querySelector('.carousel-prev');
    const carouselNext = document.querySelector('.carousel-next');
    let carouselPosition = 0;
    const cardWidth = 280;
    const cardsVisible = Math.floor(carouselTrack.parentElement.offsetWidth / cardWidth);
    const maxScroll = (carouselTrack.children.length - cardsVisible) * cardWidth;

    carouselNext.addEventListener('click', function() {
        if (carouselPosition < maxScroll) {
            carouselPosition += cardWidth;
            carouselTrack.style.transform = `translateX(-${carouselPosition}px)`;
        }
    });

    carouselPrev.addEventListener('click', function() {
        if (carouselPosition > 0) {
            carouselPosition -= cardWidth;
            carouselTrack.style.transform = `translateX(-${carouselPosition}px)`;
        }
    });
});
</script>