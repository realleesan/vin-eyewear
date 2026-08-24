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
 *   $addLabel — nhãn nút thêm, vd '+ Thêm sản phẩm'
 */

/*
 * NHÃN NÚT NÓI RÕ THÊM CÁI GÌ — theo "Vin Eyewear Admin.dc.html".
 *
 * $addLabel vốn đã được khai trong khối tài liệu trên từ lâu, nhưng thân file
 * lại in cứng "+ Thêm mới" nên không nơi gọi nào truyền nó. Bản thiết kế đặt
 * cho mỗi trang một nhãn riêng: "+ Thêm sản phẩm", "+ Thêm danh mục",
 * "+ Thêm cơ sở", "+ Thêm sự kiện", "+ Tạo mã mới".
 *
 * Không phải chuyện chữ nghĩa: nút này neo xuống #form ở cuối trang, tức là
 * bấm xong màn hình nhảy tới một biểu mẫu nằm ngoài tầm nhìn. Nhãn cụ thể là
 * thứ duy nhất nói trước biểu mẫu ấy sẽ hỏi gì.
 *
 * Vẫn để mặc định "+ Thêm mới" cho trang nào chưa kịp khai — thiếu nhãn thì
 * nút vẫn chạy, chỉ là kém rõ.
 */
$addLabel = $addLabel ?? '+ Thêm mới';
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
            <a href="#form" class="astatus__save"><?= e($addLabel) ?></a>
        <?php endif; ?>
    <?php else: ?>
        <p class="ahead__note">Bạn chỉ có quyền xem. Cần quyền quản lý để chỉnh sửa.</p>
    <?php endif; ?>
</header>
