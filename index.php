<section class="page-header">
    <div class="container">
        <h1><?php echo $pageTitle; ?></h1>
        <p>Sự kiện và khuyến mãi</p>
    </div>
</section>

<section class="events-section">
    <div class="container">
        <div class="events-list">
            <div class="event-card">
                <div class="event-date">
                    <span class="day">15</span>
                    <span class="month">07</span>
                </div>
                <div class="event-content">
                    <h3>Khuyến mãi mùa hè</h3>
                    <p>Giảm giá 20% tất cả sản phẩm kính mắt</p>
                    <a href="#" class="btn btn-primary">Xem chi tiết</a>
                </div>
            </div>
            <div class="event-card">
                <div class="event-date">
                    <span class="day">20</span>
                    <span class="month">08</span>
                </div>
                <div class="event-content">
                    <h3>Buổi ra mắt bộ sưu tập mới</h3>
                    <p>Giới thiệu bộ sưu tập kính mắt thu đông 2026</p>
                    <a href="#" class="btn btn-primary">Đăng ký tham dự</a>
                </div>
            </div>
            <div class="event-card">
                <div class="event-date">
                    <span class="day">01</span>
                    <span class="month">09</span>
                </div>
                <div class="event-content">
                    <h3>Tư vấn miễn phí</h3>
                    <p>Đo mắt và tư vấn chọn kính miễn phí tại cửa hàng</p>
                    <a href="#" class="btn btn-primary">Đặt lịch</a>
                </div>
            </div>
        </div>
    </div>
</section>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sự Kiện & Ưu Đãi | VIN EYEWEAR</title>
    
    <!-- Nhúng file CSS -->
    <link rel="stylesheet" href="/assets/css/event.css">
</head>
<body>

    <!-- Giả lập Global Header dựa theo sơ đồ sitemap -->
    <header style="text-align: center; padding: 30px 20px; border-bottom: 1px solid #e0e0e0;">
        <h2 style="letter-spacing: 3px; margin-bottom: 20px;">VIN EYEWEAR</h2>
        <nav>
            <a href="/" style="margin: 0 15px; color: #000; text-decoration: none; text-transform: uppercase; font-size: 0.9rem;">Home</a>
            <a href="/product" style="margin: 0 15px; color: #000; text-decoration: none; text-transform: uppercase; font-size: 0.9rem;">Product</a>
            <a href="/about" style="margin: 0 15px; color: #000; text-decoration: none; text-transform: uppercase; font-size: 0.9rem;">About</a>
            <!-- Đánh dấu trang hiện tại (Active) -->
            <a href="/event" style="margin: 0 15px; color: #000; text-decoration: underline; text-transform: uppercase; font-size: 0.9rem; font-weight: bold;">Event</a>
            <a href="/ar" style="margin: 0 15px; color: #000; text-decoration: none; text-transform: uppercase; font-size: 0.9rem;">AR</a>
            <a href="/contact" style="margin: 0 15px; color: #000; text-decoration: none; text-transform: uppercase; font-size: 0.9rem;">Contact</a>
        </nav>
    </header>

    <!-- Nội dung chính của trang Sự kiện -->
    <main class="event-container">
        <h1 class="page-title">Tin Tức & Ưu Đãi</h1>
        
        <div class="banner-grid">
            
            <!-- Khối Banner Khuyến Mãi 1 -->
            <a href="#" class="promo-banner">
                <!-- Vị trí chèn ảnh banner khuyến mãi thật -->
                <img src="/assets/images/event-banner-1.jpg" alt="Bộ Sưu Tập Gọng Cổ Điển" class="banner-image">
                <div class="banner-content">
                    <h2 class="banner-title">Giảm 20% Dòng Kính Cổ Điển</h2>
                    <p class="banner-desc">Tôn vinh vẻ đẹp vượt thời gian. Ưu đãi độc quyền áp dụng cho các thiết kế nguyên bản trong tháng này.</p>
                </div>
            </a>

            <!-- Khối Banner Khuyến Mãi 2 -->
            <a href="#" class="promo-banner">
                <!-- Vị trí chèn ảnh banner khuyến mãi thật -->
                <img src="/assets/images/event-banner-2.jpg" alt="Tròng Kính Chống Ánh Sáng Xanh" class="banner-image">
                <div class="banner-content">
                    <h2 class="banner-title">Nâng Cấp Tròng Kính Miễn Phí</h2>
                    <p class="banner-desc">Tặng ngay lớp phủ chống ánh sáng xanh bảo vệ mắt cao cấp khi mua gọng kính bất kỳ trên 2 triệu đồng.</p>
                </div>
            </a>
            
        </div>
    </main>

</body>
</html>