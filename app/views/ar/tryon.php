<?php
/**
 * ar/tryon.php
 * Trang thử kính AR — overlay cố định tại tâm khung camera.
 */
$glasses = [
    ['id' => 'classic-round',   'name' => 'Classic Round Gold',  'overlay' => '/assets/images/ar/classic-round.svg'],
    ['id' => 'aviator',         'name' => 'Aviator Silver',      'overlay' => '/assets/images/ar/aviator.svg'],
    ['id' => 'square-tortoise', 'name' => 'Square Tortoise',     'overlay' => '/assets/images/ar/square-tortoise.svg'],
    ['id' => 'cat-eye',         'name' => 'Cat Eye Black',       'overlay' => '/assets/images/ar/cat-eye.svg'],
];
?>

<!-- PAGE HEADER -->
<div class="page-header ar-page-header reveal">
    <div class="page-header-inner">
        <p class="page-eyebrow">Virtual Try-On</p>
        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="page-subtitle">
            Thử gọng kính trực tiếp trên khuôn mặt bạn — cho phép camera và chọn mẫu yêu thích.
        </p>
    </div>
</div>

<!-- AR WORKSPACE -->
<section class="ar-section reveal">
    <div class="ar-layout">

        <!-- Camera viewer -->
        <div class="ar-viewer">
            <div class="camera-frame">
                <div class="camera-placeholder" id="camera-placeholder">
                    <div class="camera-status" id="camera-status" aria-live="polite">
                        <span class="status-dot" aria-hidden="true"></span>
                        <span class="status-text">Đang khởi động camera…</span>
                    </div>

                    <div class="camera-loading" id="camera-loading" aria-hidden="true">
                        <span class="loading-ring"></span>
                        <p>Đang xin quyền camera</p>
                    </div>

                    <video id="video" autoplay playsinline muted></video>

                    <div class="face-guide" aria-hidden="true">
                        <span class="face-guide-oval"></span>
                    </div>

                    <img
                        id="glass-overlay"
                        src="<?= htmlspecialchars($glasses[0]['overlay']) ?>"
                        alt="Kính AR"
                    >
                </div>

                <p class="camera-hint">
                    Đặt khuôn mặt vào khung oval · Ảnh chỉ hiển thị trên thiết bị của bạn
                </p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="ar-sidebar">
            <div class="ar-panel ar-instructions">
                <h2 class="ar-panel-title">Hướng dẫn</h2>
                <ol class="ar-steps">
                    <li>
                        <span class="step-num">01</span>
                        <span class="step-text">Cho phép truy cập camera trên trình duyệt</span>
                    </li>
                    <li>
                        <span class="step-num">02</span>
                        <span class="step-text">Giữ khuôn mặt thẳng, đủ ánh sáng</span>
                    </li>
                    <li>
                        <span class="step-num">03</span>
                        <span class="step-text">Chọn mẫu kính bên dưới để xem thử</span>
                    </li>
                    <li>
                        <span class="step-num">04</span>
                        <span class="step-text">Khám phá bộ sưu tập và đặt hàng</span>
                    </li>
                </ol>
            </div>

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
                <a href="/contact" class="btn-outline">Tư vấn miễn phí</a>
            </div>
        </aside>

    </div>
</section>
