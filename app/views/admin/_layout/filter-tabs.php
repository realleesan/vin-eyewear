<?php

/**
 * admin/_layout/filter-tabs.php — dải lọc theo trạng thái, dùng chung cho
 * các trang đơn hàng / lịch hẹn / liên hệ.
 *
 * Nhận qua partial():
 *   $base     — đường dẫn gốc, vd '/quan-tri/don-hang'
 *   $statuses — ['khoá' => 'Nhãn hiển thị']
 *   $counts   — ['khoá' => số lượng], khoá '' là tổng
 *   $current  — trạng thái đang lọc ('' = tất cả)
 *   $keep     — (tuỳ chọn) tham số khác cần giữ lại trên địa chỉ, vd
 *               ['q' => 'nguyen']. Xem khối ngay dưới.
 */

/*
 * VÌ SAO PHẢI GIỮ LẠI THAM SỐ KHÁC.
 *
 * Ba trang đầu tiên dùng partial này (đơn hàng · lịch hẹn · liên hệ) không có
 * ô tìm kiếm, nên đường dẫn '?status=…' trần là đủ. Trang khách hàng thì có —
 * và ở đó, gõ "nguyễn" rồi bấm tab "Đã khoá" sẽ mất luôn từ vừa gõ, trả về
 * toàn bộ tài khoản bị khoá của cửa hàng. Người dùng đọc ra là "bộ lọc hỏng"
 * chứ không đọc ra là "hai bộ lọc không cộng được với nhau".
 *
 * Trang nào không truyền $keep thì địa chỉ sinh ra y hệt như trước.
 */
$keep = array_filter($keep ?? [], static fn ($v): bool => $v !== '' && $v !== null);

$duongDan = static function (string $status) use ($base, $keep): string {
    $tham = $keep + ($status !== '' ? ['status' => $status] : []);

    return $base . ($tham !== [] ? '?' . http_build_query($tham) : '');
};
?>
<nav class="atabs" aria-label="Lọc theo trạng thái">
    <a class="atabs__item<?= $current === '' ? ' is-active' : '' ?>"
       href="<?= e($duongDan('')) ?>"
       <?= $current === '' ? 'aria-current="true"' : '' ?>>
        Tất cả <span class="atabs__num"><?= (int) ($counts[''] ?? 0) ?></span>
    </a>

    <?php foreach ($statuses as $key => $label): ?>
        <a class="atabs__item<?= $current === $key ? ' is-active' : '' ?>"
           href="<?= e($duongDan((string) $key)) ?>"
           <?= $current === $key ? 'aria-current="true"' : '' ?>>
            <?= e($label) ?> <span class="atabs__num"><?= (int) ($counts[$key] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
