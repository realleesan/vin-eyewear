<link rel="stylesheet" href="/assets/css/contact.css">
<script src="/assets/js/contact.js" defer></script>

<section class="page-header">
    <div class="container">
        <p class="page-eyebrow">VIN EYEWEAR &middot; DI SẢN &amp; CÔNG NGHỆ</p>
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p class="page-desc">Kết nối với chúng tôi qua hệ thống cửa hàng vật lý và các kênh tư vấn trực tuyến.</p>
    </div>
</section>

<section class="contact-section">
    <div class="container">
        <!-- Interactive Store System -->
        <h2 class="section-title reveal">Hệ thống cửa hàng</h2>
        <div class="store-locator-wrapper reveal">
            <!-- Left: Store Cards List -->
            <div class="store-list">
                <?php foreach ($stores as $index => $store): ?>
                <div class="store-card <?= $index === 0 ? 'active' : '' ?>" data-store-id="<?= htmlspecialchars($store['id']) ?>" role="button" tabindex="0">
                    <div class="store-image-container">
                        <img src="<?= htmlspecialchars($store['image']) ?>" alt="<?= htmlspecialchars($store['name']) ?>" class="store-img">
                        <span class="store-badge"><?= htmlspecialchars($store['tagline']) ?></span>
                    </div>
                    <div class="store-card-content">
                        <h3 class="store-card-title"><?= htmlspecialchars($store['name']) ?></h3>
                        <div class="store-details">
                            <p class="store-detail-item">
                                <span class="label-mono">ĐỊA CHỈ:</span>
                                <span class="detail-value"><?= htmlspecialchars($store['address']) ?></span>
                            </p>
                            <p class="store-detail-item">
                                <span class="label-mono">HOTLINE:</span>
                                <span class="detail-value">
                                    <a href="tel:<?= str_replace(' ', '', $store['phone']) ?>" class="store-phone-link">
                                        <?= htmlspecialchars($store['phone']) ?>
                                    </a>
                                </span>
                            </p>
                            <p class="store-detail-item">
                                <span class="label-mono">GIỜ MỞ CỬA:</span>
                                <span class="detail-value"><?= htmlspecialchars($store['hours']) ?></span>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Right: Interactive Google Map Frame -->
            <div class="store-map-viewport">
                <?php foreach ($stores as $index => $store): ?>
                <div id="map-<?= htmlspecialchars($store['id']) ?>" class="map-container <?= $index === 0 ? 'active' : '' ?>">
                    <iframe 
                        src="<?= $store['map_url'] ?>" 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        allowfullscreen="" 
                        loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Contact Form Section -->
        <div class="contact-form-section reveal">
            <div class="contact-form-grid">
                <div class="form-intro">
                    <h2 class="form-title">Gửi phản hồi cho chúng tôi</h2>
                    <p class="form-text">Bạn có thắc mắc về các gọng kính di sản, đặt lịch đo mắt hay chế độ bảo hành? Hãy điền thông tin bên dưới, đội ngũ hỗ trợ của Vin Eyewear sẽ liên hệ lại trong thời gian sớm nhất.</p>
                    
                    <div class="direct-contact-channels">
                        <div class="channel-card">
                            <span class="channel-label label-mono">EMAIL HỖ TRỢ</span>
                            <span class="channel-value">support@vineyewear.com</span>
                        </div>
                        <div class="channel-card">
                            <span class="channel-label label-mono">HOTLINE TOÀN QUỐC</span>
                            <span class="channel-value">1800 6789</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-wrapper">
                    <form action="#" method="POST" class="vin-form">
                        <div class="form-row-2">
                            <div class="form-group">
                                <label for="name" class="label-mono">HỌ TÊN <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required placeholder="Họ và tên của bạn">
                            </div>
                            <div class="form-group">
                                <label for="email" class="label-mono">EMAIL <span class="required">*</span></label>
                                <input type="email" id="email" name="email" required placeholder="vi_du@email.com">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="phone" class="label-mono">SỐ ĐIỆN THOẠI</label>
                            <input type="tel" id="phone" name="phone" placeholder="Số điện thoại liên hệ">
                        </div>
                        <div class="form-group">
                            <label for="message" class="label-mono">NỘI DUNG YÊU CẦU <span class="required">*</span></label>
                            <textarea id="message" name="message" rows="5" required placeholder="Nhập nội dung bạn cần chúng tôi tư vấn hoặc phản hồi..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-submit">GỬI TIN NHẮN</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>