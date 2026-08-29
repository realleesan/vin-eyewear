<?php

/**
 * _layout/collection-menu.php — bảng xổ của mục "Bộ sưu tập" trên thanh nav.
 *
 * File này được require BÊN TRONG <ul class="header-nav__list"> nên phần tử
 * gốc phải là <li>. Nhận từ header.php: $collectionsNav, $isCollectionActive.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DROPDOWN HẸP, KHÔNG PHẢI MEGA MENU THỨ HAI
 *
 * "Sản phẩm" cần bảng trải hết bề ngang vì nó có bốn cột lát cắt lọc và một
 * thẻ ảnh. Bộ sưu tập thì chỉ có một danh sách phẳng — ba, bốn mục, mỗi mục
 * một cái tên và một câu. Trải nó ra 1400px là một bảng khổng lồ chứa ba dòng
 * chữ nằm lệch một góc.
 *
 * Nên đây là bảng hẹp neo ngay dưới viên thuốc, cùng khuôn hình với bảng xổ
 * của cụm icon bên phải (.hpop trong components/header.css): viền một nét,
 * bo --radius-sm, bóng đổ nhẹ. Khác một điểm: mỗi dòng có ảnh nhỏ, vì bộ sưu
 * tập là thứ người ta nhận ra bằng mắt chứ không bằng tên.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỞ BẰNG CSS, KHÔNG BẰNG JAVASCRIPT — y hệt mega-menu.php
 *
 * :hover mở, :focus-within giữ mở khi Tab vào trong. Viên thuốc VẪN là <a>
 * thật tới /bo-suu-tap, nên màn hình cảm ứng (không có "rê chuột") chạm vào
 * là đi thẳng tới trang danh sách đầy đủ — không có ngõ cụt nào.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG CÓ BỘ NÀO ĐANG HIỆN -> IN LIÊN KẾT TRƠN
 *
 * Cửa hàng có lúc ẩn hết để chuẩn bị mùa mới (trang /bo-suu-tap đã lường
 * trước chuyện đó — xem .colls__empty). Lúc ấy mũi tên chevron mở ra một
 * bảng rỗng là lời hứa suông, nên mục này quay về đúng cái nó vốn là: một
 * liên kết thường.
 */

/* Tự đọc được nếu nơi gọi không truyền — cùng lối phòng thủ của mega-menu.php,
   để file này không phụ thuộc ngầm vào đúng một nơi gọi. */
$collectionsNav = $collectionsNav ?? CollectionModel::visible();

/*
 * CẮT CÒN 6 MỤC.
 *
 * Bảng xổ không phải trang danh sách: quá số này thì nó dài hơn cả thanh nav
 * và người dùng phải cuộn trong một cái hộp đang mở bằng hover — cuộn là rời
 * chuột, rời chuột là bảng đóng. Dòng "Tất cả bộ sưu tập" ở chân bảng lo phần
 * còn lại. Sáu mục vì thứ tự đã do cửa hàng sắp (sort_order), nên sáu cái đầu
 * đúng là sáu cái cửa hàng muốn khoe nhất.
 */
$bstToiDa = 6;
$bstDanhSach = array_slice($collectionsNav, 0, $bstToiDa);
?>
<?php if ($bstDanhSach === []): ?>
    <li>
        <a href="/bo-suu-tap"
           <?= $isCollectionActive ? 'class="is-active" aria-current="page"' : '' ?>><?= e(t('nav.collections')) ?></a>
    </li>
<?php else: ?>
<li class="bstm">

    <a href="/bo-suu-tap"
       class="bstm__trigger<?= $isCollectionActive ? ' is-active' : '' ?>"
       <?= $isCollectionActive ? 'aria-current="page"' : '' ?>>
        <?= e(t('nav.collections')) ?>
        <svg class="bstm__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <?php /* Mũi nhọn hình thoi cắm vào mép trên bảng — cùng ngôn ngữ hình
                 khối với mega menu, để hai bảng xổ trên cùng một thanh nav
                 không nói hai thứ tiếng khác nhau. */ ?>
        <span class="bstm__caret" aria-hidden="true"></span>
    </a>

    <div class="bstm__panel">
        <p class="bstm__head"><?= e(t('nav.collections')) ?></p>

        <ul class="bstm__list" role="list">
            <?php foreach ($bstDanhSach as $bst): ?>
                <?php
                /* cover() đã tự kiểm file có thật hay không và trả '' khi
                   không có ảnh dùng được, nên ở đây chỉ cần hỏi chuỗi rỗng. */
                $bstAnh = CollectionModel::cover($bst);
                ?>
                <li>
                    <a class="bstm__item" href="/bo-suu-tap/<?= e(rawurlencode($bst['slug'])) ?>">
                        <span class="bstm__thumb">
                            <?php if ($bstAnh !== ''): ?>
                                <img src="<?= e(asset($bstAnh)) ?>" alt=""
                                     width="48" height="48" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </span>

                        <span class="bstm__text">
                            <span class="bstm__name"><?= e($bst['name']) ?></span>
                            <?php /* Câu giới thiệu do người nhập nội dung viết,
                                     dài ngắn tuỳ ý; cắt 48 ký tự để dòng thứ hai
                                     không đẩy hàng cao gấp đôi. Bộ chưa có câu
                                     nào thì bỏ hẳn thẻ, không in dòng rỗng. */ ?>
                            <?php $bstCau = excerpt($bst['tagline'] ?? '', 48); ?>
                            <?php if ($bstCau !== ''): ?>
                                <span class="bstm__note"><?= e($bstCau) ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php /* Chân bảng: lối ra trang đầy đủ. Cần thiết cả khi chưa cắt bớt
                 mục nào — trang /bo-suu-tap có ảnh lớn, ngày ra mắt và phần
                 FAQ, tức là nhiều hơn hẳn cái danh sách này. */ ?>
        <a class="bstm__all" href="/bo-suu-tap"><?= e(t('nav.all_collections')) ?> &rarr;</a>
    </div>
</li>
<?php endif; ?>
