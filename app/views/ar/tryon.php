<?php
/**
 * ar/tryon.php
 * Trang thử kính AR — overlay cố định tại tâm khung camera.
 * Biến nhận từ ArController::tryon():
 *   $glasses — mảng mẫu kính thử (id, name, overlay)
 */
?>

<!-- PAGE HEADER -->
<div class="page-header ar-page-header reveal">
    <div class="page-header-inner">
        <h1 class="page-title">Thử kính AR</h1>
    </div>
</div>

<!-- AR WORKSPACE -->
<section class="ar-section reveal">
    <div class="ar-layout">

        <!-- Camera viewer -->
        <div class="ar-viewer">
            <div class="camera-frame">
                <div class="camera-placeholder" id="camera-placeholder">
                    <!-- Loading State -->
                    <div class="camera-loading" id="camera-loading" aria-hidden="true">
                        <div class="loading-spinner"></div>
                        <p>Đang khởi động camera...</p>
                    </div>

                    <!-- Error State -->
                    <div class="camera-error" id="camera-error" aria-hidden="true">
                        <div class="error-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18.36 6.64a9 9 0 1112.72 12.72"/>
                                <line x1="12" y1="2" x2="12" y2="12"/>
                                <line x1="12" y1="18" x2="12.01" y2="18"/>
                            </svg>
                        </div>
                        <h3 id="error-title">Không thể truy cập camera</h3>
                        <p id="error-message">Vui lòng cấp quyền camera để sử dụng tính năng thử kính.</p>
                        <button class="btn-retry" id="btn-retry">Thử lại</button>
                    </div>

                    <!-- Permission Denied State -->
                    <div class="camera-permission-denied" id="camera-permission-denied" aria-hidden="true">
                        <div class="permission-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                        </div>
                        <h3>Quyền camera bị từ chối</h3>
                        <p>Bạn đã từ chối quyền truy cập camera. Để sử dụng tính năng thử kính, vui lòng:</p>
                        <ol class="permission-steps">
                            <li>Nhấn vào biểu tượng khóa hoặc thông tin trong thanh địa chỉ</li>
                            <li>Tìm phần "Camera" và chọn "Cho phép"</li>
                            <li>Tải lại trang để áp dụng thay đổi</li>
                        </ol>
                        <button class="btn-retry" id="btn-reload">Tải lại trang</button>
                    </div>

                    <!-- Active State -->
                    <div class="camera-active" id="camera-active" aria-hidden="true">
                        <div class="camera-status" id="camera-status" aria-live="polite">
                            <span class="status-dot" aria-hidden="true"></span>
                            <span class="status-text">Camera đang hoạt động</span>
                        </div>
                        <video id="video" autoplay playsinline muted></video>
                        <div class="face-guide" aria-hidden="true">
                            <span class="face-guide-oval"></span>
                        </div>
                        <img id="glass-overlay" src="<?= htmlspecialchars($glasses[0]['overlay']) ?>" alt="Kính AR">
                    </div>
                </div>

                <p class="camera-hint">
                    Đặt khuôn mặt vào khung oval
                </p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="ar-sidebar">
            <div class="ar-panel glasses-selector">
                <div class="ar-panel-header">
                    <h2 class="ar-panel-title">Chọn mẫu kính</h2>
                    <span class="ar-panel-count"><?= count($glasses) ?> mẫu</span>
                </div>

                <div class="glasses-list" role="listbox" aria-label="Danh sách kính thử">
                    <?php foreach ($glasses as $index => $glass): ?>
                    <button
                        type="button"
                        class="glasses-item<?= $index === 0 ? ' active' : '' ?>"
                        role="option"
                        aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                        data-glass="<?= htmlspecialchars($glass['id']) ?>"
                        data-overlay="<?= htmlspecialchars($glass['overlay']) ?>"
                    >
                        <span class="glasses-thumb">
                            <img
                                src="<?= htmlspecialchars($glass['overlay']) ?>"
                                alt=""
                                loading="lazy"
                            >
                        </span>
                        <span class="glass-name"><?= htmlspecialchars($glass['name']) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ar-cta">
                <a href="/product" class="btn-primary">Xem bộ sưu tập</a>
            </div>
        </aside>

    </div>
</section>
