<?php

/**
 * _layout/mega-menu-mobile.php — khối "Sản phẩm" trong menu trượt.
 *
 * Dưới 1101px thanh điều hướng ẩn hẳn, nên bảng xổ desktop không tới được.
 * Ở đây thay bằng một <details> bung ra danh mục.
 *
 * DÙNG <details>, KHÔNG DÙNG JAVASCRIPT: đóng/mở là hành vi sẵn có của trình
 * duyệt, có luôn phần trợ năng (nút biết trạng thái đóng/mở, đọc được bằng
 * trình đọc màn hình). Cùng cách mà nhóm "Tài khoản của tôi" ở
 * app/views/auth/profile.php đang dùng.
 *
 * CHỈ danh mục, không có các lát cắt lọc (dáng gọng, chất liệu, thương hiệu)
 * và không có thẻ sản phẩm. Menu trượt là để ĐI TỚI một trang, không phải để
 * duyệt kho — nhồi cả năm cột của bản desktop vào đây thì người dùng phải
 * cuộn qua bốn chục dòng mới tới "Liên hệ". Lọc sâu đã có cột lọc ở chính
 * trang /san-pham, rộng rãi hơn nhiều.
 *
 * Nhận từ header.php: $categories, $isProductActive.
 */
?>
<details class="mobile-nav__group"<?= $isProductActive ? ' open' : '' ?>>
    <?php /* Đứng ở /san-pham thì hàng này sáng lên như mọi mục khác của menu trượt —
             xem .mobile-nav__links > details > summary.is-active trong header.css. */ ?>
    <summary<?= $isProductActive ? ' class="is-active"' : '' ?>>
        <span>Sản phẩm</span>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </summary>

    <div class="mobile-nav__sub">
        <a href="/san-pham">Tất cả sản phẩm</a>
        <?php foreach ($categories as $cat): ?>
            <a href="/san-pham?category=<?= e(rawurlencode($cat['slug'])) ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
    </div>
</details>
