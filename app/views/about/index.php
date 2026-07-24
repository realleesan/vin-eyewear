<link rel="stylesheet" href="/assets/css/about.css">

<?php
$breadcrumb_items = [
    ['label' => 'Trang chủ', 'url' => '/'],
    ['label' => 'Về chúng tôi'],
];
$show_breadcrumb = true;
require_once APP_PATH . '/views/_layout/breadcrumb.php';
?>

<section class="page-header">
    <div class="container">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p>Về Vin Eyewear</p>
    </div>
</section>

<section class="about-section">
    <div class="container">
        <!-- Hero Story Section -->
        <div class="hero-story">
            <div class="story-content">
                <span class="subheading-caps">CÂU CHUYỆN THƯƠNG HIỆU</span>
                <h2 class="headline">Từ Đam Mê Đến Đỉnh Cao</h2>
                <div class="story-text">
                    <p>Vin Eyewear bắt đầu từ một giấc mơ đơn giản của CEO Công Mạnh - mang lại tầm sáng và sự tự tin cho mọi người Việt Nam qua những chiếc kính mắt chất lượng. Với niềm đam mê bất tận về nghệ thuật chế tác kính và tầm nhìn xa về công nghệ, Công Mạnh đã xây dựng Vin Eyewear không chỉ là một cửa hàng bán kính, mà là một hành trình khám phá vẻ đẹp cá nhân.</p>
                    <p>"Mỗi chiếc kính không chỉ là một công cụ hỗ trợ thị lực, mà là một tuyên ngôn về phong cách và cá tính của người đeo. Tôi muốn Vin Eyewear trở thành nơi mọi người tìm thấy chính mình qua những lăng kính tinh tế nhất."</p>
                    <p>Từ một cửa hàng nhỏ tại Long Biên, Vin Eyewear đã phát triển mạnh mẽ nhờ sự tin yêu của khách hàng và cam kết không ngừng cải tiến. Chúng tôi tiên phong ứng dụng công nghệ AR và AI vào trải nghiệm mua sắm, giúp khách hàng thử kính trực tuyến và nhận tư vấn cá nhân hóa mọi lúc, mọi nơi.</p>
                </div>
            </div>
            <div class="story-image">
                <img src="https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=800&h=600&fit=crop" alt="Câu chuyện Vin Eyewear">
            </div>
        </div>

        <!-- Mission Section -->
        <div class="mission-section">
            <div class="mission-content">
                <span class="subheading-caps">SỨ MỆNH & TẦM NHÌN</span>
                <h2 class="headline">Nhìn Rõ Hơn - Sống Tự Tin Hơn</h2>
                <p>Vin Eyewear cam kết mang đến trải nghiệm mua sắm kính mắt đột phá thông qua công nghệ tiên tiến, dịch vụ tận tâm và sản phẩm chất lượng cao. Chúng tôi tin rằng mỗi người đều xứng đáng có một chiếc kính hoàn hảo phản ánh vẻ đẹp riêng.</p>
            </div>
        </div>

        <!-- Values Grid -->
        <div class="values-section">
            <span class="subheading-caps">GIÁ TRỊ CỐT LÕI</span>
            <h2 class="headline">Những Điều Chúng Tôi Tin</h2>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">✦</div>
                    <h3>CHẤT LƯỢNG ĐẦU TIÊN</h3>
                    <p>Mỗi sản phẩm đều được chọn lọc kỹ lưỡng từ các thương hiệu uy tín, đảm bảo nguồn gốc rõ ràng và kiểm định chất lượng nghiêm ngặt.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">⚡</div>
                    <h3>CÔNG NGHỆ TIÊN PHONG</h3>
                    <p>Ứng dụng AI và AR để mang lại trải nghiệm thử kính ảo đột phá, giúp khách hàng tìm được chiếc kính phù hợp nhất.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">♥</div>
                    <h3>DỊCH VỤ TẬN TÂM</h3>
                    <p>Đội ngũ tư vấn chuyên nghiệp, chính sách bảo hành minh bạch và hỗ trợ khách hàng mọi lúc, mọi nơi.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon">∞</div>
                    <h3>ĐỔI MỚI KHÔNG NGỪNG</h3>
                    <p>Luôn cập nhật xu hướng mới nhất, cải tiến dịch vụ và mở rộng danh mục sản phẩm để phục vụ khách hàng tốt hơn.</p>
                </div>
            </div>
        </div>

        <!-- Locations Section -->
        <div class="locations-section">
            <span class="subheading-caps">HỆ THỐNG CỬA HÀNG</span>
            <h2 class="headline">Ghé Thăm Chúng Tôi</h2>
            <div class="locations-grid">
                <div class="location-card primary">
                    <div class="location-badge">CỬA HÀNG CHÍNH</div>
                    <h3>Long Biên</h3>
                    <div class="location-address">
                        <p>261 Ngọc Lâm, P. Bồ Đề</p>
                        <p>TP. Hà Nội (ngã tư Hồng Tiến)</p>
                        <p>Hanoi, Vietnam, 100000</p>
                    </div>
                    <div class="location-hours">
                        <span class="label-mono">GIỜ MỞ CỬA</span>
                        <p>Thứ 2 - Chủ Nhật: 8:00 - 21:00</p>
                    </div>
                </div>
                <div class="location-card">
                    <div class="location-badge">MỚI MỞ</div>
                    <h3>Tây Hồ</h3>
                    <div class="location-address">
                        <p>46 Hoàng Hoa Thám</p>
                        <p>Phường Thụy Khuê, Quận Tây Hồ</p>
                        <p>Hanoi, Vietnam, 10000</p>
                    </div>
                    <div class="location-hours">
                        <span class="label-mono">GIỜ MỞ CỬA</span>
                        <p>Thứ 2 - Chủ Nhật: 9:00 - 20:00</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="cta-section">
            <div class="cta-content">
                <h2 class="headline">Sẵn Sàng Khám Phá?</h2>
                <p>Hãy để chúng tôi giúp bạn tìm thấy chiếc kính hoàn hảo phản ánh cá tính của bạn.</p>
                <div class="cta-buttons">
                    <a href="/product" class="btn btn-primary">XEM SẢN PHẨM</a>
                    <a href="/contact" class="btn btn-secondary">LIÊN HỆ TƯ VẤN</a>
                </div>
            </div>
        </div>
    </div>
</section>