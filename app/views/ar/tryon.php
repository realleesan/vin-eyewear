<section class="page-header">
    <div class="container">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p>Thử kính ảo với công nghệ AR</p>
    </div>
</section>

<section class="ar-section">
    <div class="container">
        <div class="ar-instructions">
            <h2>Hướng dẫn sử dụng</h2>
            <ol>
                <li>Cho phép truy cập camera của trình duyệt</li>
                <li>Đặt khuôn mặt vào khung hình</li>
                <li>Chọn mẫu kính bạn muốn thử</li>
                <li>Xem kết quả và điều chỉnh</li>
            </ol>
        </div>

        <div class="ar-viewer">
            <div class="camera-placeholder">
                <video id="video" autoplay playsinline></video>
                <img id="glass-overlay" src="/assets/images/glasses.png" alt="Kính AR">
            </div>

            <div class="glasses-selector">
                <h3>Chọn mẫu kính</h3>
                <div class="glasses-list">
                    <div class="glasses-item active" data-glass="default">
                        <img src="/assets/images/glasses.png" alt="Kính mặc định">
                        <span class="glass-name">Mặc định</span>
                    </div>
                    <div class="glasses-item" data-glass="1">
                        <img src="https://placehold.co/200x100/2c3e50/white?text=K%C3%ADnh+A" alt="Kính A">
                        <span class="glass-name">Kính A</span>
                    </div>
                    <div class="glasses-item" data-glass="2">
                        <img src="https://placehold.co/200x100/e94560/white?text=K%C3%ADnh+B" alt="Kính B">
                        <span class="glass-name">Kính B</span>
                    </div>
                    <div class="glasses-item" data-glass="3">
                        <img src="https://placehold.co/200x100/3498db/white?text=K%C3%ADnh+C" alt="Kính C">
                        <span class="glass-name">Kính C</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>