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
 */
?>
<nav class="atabs" aria-label="Lọc theo trạng thái">
    <a class="atabs__item<?= $current === '' ? ' is-active' : '' ?>"
       href="<?= e($base) ?>"
       <?= $current === '' ? 'aria-current="true"' : '' ?>>
        Tất cả <span class="atabs__num"><?= (int) ($counts[''] ?? 0) ?></span>
    </a>

    <?php foreach ($statuses as $key => $label): ?>
        <a class="atabs__item<?= $current === $key ? ' is-active' : '' ?>"
           href="<?= e($base) ?>?status=<?= e(rawurlencode($key)) ?>"
           <?= $current === $key ? 'aria-current="true"' : '' ?>>
            <?= e($label) ?> <span class="atabs__num"><?= (int) ($counts[$key] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
