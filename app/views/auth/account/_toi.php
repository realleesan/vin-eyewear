<?php

/**
 * auth/account/_toi.php — ba dòng chữ lớn "họ tên · email · số điện thoại".
 *
 * Bản thiết kế vẽ đúng khối này ở HAI chỗ: tab Tài khoản và tab Hồ sơ. Một
 * file cho cả hai, để ngày thêm một dòng không phải nhớ sửa hai nơi.
 *
 * Dòng trống thì BỎ, không in "Chưa cập nhật": tài khoản đăng ký bằng số điện
 * thoại không có email, và một dòng chữ xám ở giữa ba dòng chữ lớn đọc như lỗi.
 * Còn trống cả ba thì nói một câu thay cho khoảng trắng.
 *
 * Nhận qua partial(): $profile
 */

$dongToi = array_values(array_filter([
    trim((string) ($profile['full_name'] ?? '')),
    trim((string) ($profile['email'] ?? '')),
    !empty($profile['phone']) ? groupPhone((string) $profile['phone']) : '',
], static fn (string $s): bool => $s !== ''));
?>

<?php if ($dongToi === []): ?>
    <p class="acct-sec__text">Bạn chưa cập nhật họ tên và thông tin liên hệ.</p>
<?php else: ?>
    <div class="acct-me">
        <?php foreach ($dongToi as $dong): ?>
            <p><?= e($dong) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
