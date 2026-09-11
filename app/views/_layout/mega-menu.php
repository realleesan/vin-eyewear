<?php

/**
 * _layout/mega-menu.php — bảng xổ của MỘT mục sản phẩm trên thanh nav:
 * "Gọng kính" hoặc "Tròng kính".
 *
 * File này được require BÊN TRONG <ul class="header-nav__list"> nên phần tử
 * gốc phải là <li>. header.php require nó HAI LẦN, mỗi lần đặt sẵn:
 *   $megaSlug   'gong-kinh' | 'trong-kinh' — trang con mà mục này dẫn tới
 *   $productSub đoạn thứ hai của URL khi đang đứng dưới /san-pham/…
 *
 * Vì bị require hai lần trong cùng một phạm vi, file này KHÔNG được khai
 * function/class nào — khai lần hai là lỗi fatal. Chỉ dùng closure gán biến.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * VÌ SAO KHÔNG CÒN MỤC "SẢN PHẨM" (10/09/2026, theo yêu cầu chủ dự án)
 *
 * Trước: một mục "Sản phẩm" trỏ về /san-pham — trang gom cả kho — kèm bảng xổ
 * liệt kê chín danh mục. Cửa hàng bán hai thứ khác hẳn nhau về cách chọn
 * (gọng chọn theo dáng/chất liệu, tròng chọn theo loại/chiết suất/lớp phủ),
 * nên nay thanh nav có HAI mục ngang hàng, mỗi mục dẫn thẳng tới trang con của
 * mình: /san-pham/gong-kinh · /san-pham/trong-kinh. Trang gom /san-pham trần
 * thì chuyển 301 về Gọng kính — xem ProductController::index().
 *
 * Dữ liệu (nhãn, liên kết từng cột) dựng ở _layout/mega-data.php — dùng chung
 * với ngăn kéo mobile. File này chỉ VẼ.
 *
 * Mở/đóng vẫn bằng CSS (:hover / :focus-within) + assets/js/header.js như cũ;
 * header.js vốn đã duyệt MỌI .mega trên trang nên không phải sửa gì bên đó.
 */

$mega = require VIEWS_PATH . '/_layout/mega-data.php';
?>
<li class="mega mega--<?= e($mega['slug']) ?>">

    <a href="<?= e($mega['base']) ?>"
       class="mega__trigger<?= $mega['on'] ? ' is-active' : '' ?>"
       <?= $mega['on'] ? 'aria-current="page"' : '' ?>>
        <?= e($mega['label']) ?>
        <svg class="mega__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="mega__caret" aria-hidden="true"></span>
    </a>

    <div class="mega__panel" id="megaPanel-<?= e($mega['slug']) ?>">
        <?php
        /* MỘT CỘT PHẲNG. "Xem tất cả" là <li> ĐẦU TIÊN của chính danh sách,
           không phải một khối riêng bên trên — theo tham chiếu, nó trông y hệt
           các mục còn lại, chỉ khác chỗ đứng. Xem khối chú thích trong
           _layout/mega-data.php về lý do mỗi bảng chỉ giữ một trục phân loại. */
        ?>
        <ul class="mega__links" role="list">
            <li><a href="<?= e($mega['base']) ?>"><?= e($mega['all']) ?></a></li>

            <?php foreach ($mega['links'] as [$nhan, $url]): ?>
                <?php /* lang="vi" — nhãn lấy từ config/CSDL chỉ có tiếng Việt;
                         xem quy ước ở _layout/product-card.php. */ ?>
                <li><a href="<?= e($url) ?>" lang="vi"><?= e($nhan) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</li>
