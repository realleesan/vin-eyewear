<?php

/**
 * admin/_layout/crud-head.php — tiêu đề trang CRUD + nút thêm mới.
 *
 * Nhận qua partial():
 *   $title    — tiêu đề trang
 *   $lead     — dòng mô tả
 *   $base     — đường dẫn gốc, vd '/quan-tri/danh-muc'
 *   $canEdit  — có quyền sửa hay không
 *   $editing  — bản ghi đang sửa (null = đang thêm mới)
 *   $addLabel — nhãn nút thêm
 */
?>
<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title"><?= e($title) ?></h1>
        <p class="ahead__lead"><?= e($lead) ?></p>
    </div>

    <?php if ($canEdit): ?>
        <?php if ($editing !== null): ?>
            <a href="<?= e($base) ?>" class="astatus__save astatus__save--ghost">Huỷ sửa</a>
        <?php else: ?>
            <!-- Neo tới form bên dưới thay vì mở trang riêng: danh sách ở đây
                 ngắn, giữ cả hai trên một màn hình đỡ một lượt tải trang. -->
            <a href="#form" class="astatus__save">+ Thêm mới</a>
        <?php endif; ?>
    <?php else: ?>
        <p class="ahead__note">Bạn chỉ có quyền xem. Cần quyền quản lý để chỉnh sửa.</p>
    <?php endif; ?>
</header>
