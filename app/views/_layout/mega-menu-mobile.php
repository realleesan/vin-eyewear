<?php

/**
 * _layout/mega-menu-mobile.php — khối "Gọng kính" hoặc "Tròng kính" trong
 * menu trượt.
 *
 * Dưới 1101px thanh điều hướng ẩn hẳn, nên bảng xổ desktop không tới được. Ở
 * đây thay bằng một <details> bung ra đúng các liên kết của bảng xổ đó.
 *
 * DÙNG <details>, KHÔNG DÙNG JAVASCRIPT: đóng/mở là hành vi sẵn có của trình
 * duyệt, có luôn phần trợ năng (nút biết trạng thái đóng/mở, đọc được bằng
 * trình đọc màn hình).
 *
 * header.php require file này HAI LẦN (mỗi mục một lần), đặt sẵn $megaSlug và
 * $productSub. Dữ liệu lấy từ _layout/mega-data.php — cùng nguồn với bản
 * desktop, nên hai bản không lệch nhau được.
 *
 * Mặc định ĐÓNG (trừ khi đang đứng ở chính trang con đó): hai khối cộng lại
 * khoảng hai chục dòng, mở sẵn thì "Liên hệ" bị đẩy xuống quá xa.
 */

$mega = require VIEWS_PATH . '/_layout/mega-data.php';
?>
<details class="mobile-nav__group"<?= $mega['on'] ? ' open' : '' ?>>
    <?php /* Đứng ở trang con thì hàng này sáng lên như mọi mục khác của menu trượt —
             xem .mobile-nav__links > details > summary.is-active trong header.css. */ ?>
    <summary<?= $mega['on'] ? ' class="is-active"' : '' ?>>
        <span><?= e($mega['label']) ?></span>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </summary>

    <div class="mobile-nav__sub">
        <a href="<?= e($mega['base']) ?>"><?= e($mega['all']) ?></a>
        <?php foreach ($mega['cols'] as [$head, $links]): ?>
            <?php /* Tiêu đề nhóm là <p>, không bấm được — nó chỉ gọi tên nhóm.
                     Kiểu ở .mobile-nav__subhead trong components/header.css. */ ?>
            <p class="mobile-nav__subhead"><?= e($head) ?></p>
            <?php foreach ($links as [$nhan, $url]): ?>
                <a href="<?= e($url) ?>" lang="vi"><?= e($nhan) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</details>
