<section class="page-header">
    <div class="container">
        <h1><?php echo $pageTitle; ?></h1>
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
                <!-- VIDEO CAMERA -->
                <video id="video" autoplay playsinline></video>

                <!-- ẢNH KÍNH Ở TÂM -->
                <img id="glass-overlay" src="/assets/images/glasses.png" alt="Kính AR">
            </div>

            <div class="glasses-selector">
                <h3>Chọn mẫu kính</h3>
                <div class="glasses-list">
                    <div class="glasses-item active">
                        <img src="/assets/images/glasses1.jpg" alt="Kính 1">
                    </div>
                    <div class="glasses-item">
                        <img src="/assets/images/glasses2.jpg" alt="Kính 2">
                    </div>
                    <div class="glasses-item">
                        <img src="/assets/images/glasses3.jpg" alt="Kính 3">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CSS & JS -->
<link rel="stylesheet" href="/assets/css/ar.tryon.css">
<script src="/assets/js/ar-engine.js"></script>