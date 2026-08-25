<?php

/**
 * admin/_layout/image-x-btn.php — phần NHÌN THẤY của nút × xoá ảnh.
 *
 * Tách khỏi image-x.php vì hai mẩu phải nằm ở hai vị trí khác nhau trong DOM:
 * ô tick đứng TRƯỚC ảnh (để CSS dùng bộ chọn anh-em `~`), còn dấu × thì nằm
 * SAU ảnh (để nổi lên trên nó). Xem giải thích đầy đủ ở image-x.php.
 *
 * Nhận qua partial():
 *   $x_id    — trùng id của ô tick tương ứng
 *   $x_label — câu cho trình đọc màn hình
 */
?>

<label class="aimgx" for="<?= e($x_id) ?>" title="<?= e($x_label) ?>">
    <?php /* Dấu × và ↺ vẽ bằng CSS content, không gõ vào HTML: trạng thái
             "đang đánh dấu xoá" phải đổi ký tự, mà đổi nội dung theo trạng
             thái ô tick thì chỉ CSS làm được nếu không có JS. */ ?>
    <span class="aimgx__mark" aria-hidden="true"></span>
    <span class="sr-only"><?= e($x_label) ?></span>
</label>

<?php /* Chữ này chỉ hiện khi ảnh đã bị đánh dấu. Dấu × đổi thành ↺ đã là một
         tín hiệu, nhưng nó nhỏ và nằm ở góc — cần một câu nói thẳng ra rằng
         ảnh CHƯA mất, chỉ mất khi bấm Lưu. */ ?>
<span class="aimgx__flag">Sẽ xoá khi lưu</span>
