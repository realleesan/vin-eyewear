<?php

/**
 * _layout/collection-menu-mobile.php — khối "Bộ sưu tập" trong menu trượt.
 *
 * Dưới 1101px thanh điều hướng ẩn hẳn nên bảng xổ desktop không tới được. Ở
 * đây thay bằng một <details> bung ra danh sách bộ sưu tập — cùng khuôn với
 * khối "Sản phẩm" ngay trên nó (xem _layout/mega-menu-mobile.php), dùng lại
 * nguyên .mobile-nav__group / .mobile-nav__sub trong components/mega-menu.css.
 *
 * CHỈ TÊN, không ảnh và không câu giới thiệu. Bản desktop có ảnh vì ở đó bảng
 * xổ phải cạnh tranh với cả trang phía sau; trong menu trượt thì mỗi mục đã
 * chiếm trọn bề ngang, thêm ảnh chỉ làm người dùng phải cuộn xa hơn mới tới
 * "Liên hệ".
 *
 * Nhận từ header.php: $collectionsNav, $isCollectionActive.
 */

$collectionsNav = $collectionsNav ?? CollectionModel::visible();
?>
<?php if ($collectionsNav === []): ?>
    <?php /* Không có bộ nào đang hiện: một mục thường, không phải cái nút bung
             ra khoảng trắng. Cùng lý do đã ghi ở collection-menu.php. */ ?>
    <a href="/bo-suu-tap"<?= $isCollectionActive ? ' class="is-active" aria-current="page"' : '' ?>>Bộ sưu tập</a>
<?php else: ?>
<details class="mobile-nav__group"<?= $isCollectionActive ? ' open' : '' ?>>
    <?php /* Đứng ở /bo-suu-tap thì hàng này sáng lên như mọi mục khác của menu trượt —
             xem .mobile-nav__links > details > summary.is-active trong header.css. */ ?>
    <summary<?= $isCollectionActive ? ' class="is-active"' : '' ?>>
        <span>Bộ sưu tập</span>
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </summary>

    <div class="mobile-nav__sub">
        <a href="/bo-suu-tap">Tất cả bộ sưu tập</a>
        <?php foreach ($collectionsNav as $bst): ?>
            <a href="/bo-suu-tap/<?= e(rawurlencode($bst['slug'])) ?>"><?= e($bst['name']) ?></a>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>
