<?php

/**
 * _layout/collection-menu.php — bảng xổ của mục "Bộ sưu tập" trên thanh nav.
 *
 * File này được require BÊN TRONG <ul class="header-nav__list"> nên phần tử
 * gốc phải là <li>. Nhận từ header.php: $collectionsNav, $isCollectionActive.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * DÙNG LẠI NGUYÊN KHUNG CỦA MEGA MENU — CỐ Ý, KHÔNG PHẢI SAO CHÉP CẨU THẢ
 *
 * Bảng này mang chính các lớp .mega__* của _layout/mega-menu.php: cùng viên
 * thuốc, cùng mũi nhọn hình thoi, cùng bảng trải từ --page-edge bên này sang
 * --page-edge bên kia, cùng một lượt mờ dần 0.2s. Yêu cầu là "giống y hệt
 * dropdown Sản phẩm", nên cách đúng là DÙNG LẠI bộ lớp đó chứ không chép số
 * đo sang một bộ lớp mới — chép là hai bảng lệch nhau ngay lần đầu ai đó chỉnh
 * padding của một bên.
 *
 * Vì thế components/collection-menu.css nay chỉ còn đúng phần KHÁC: thẻ
 * "Tất cả bộ sưu tập" ở ô cuối. Mọi thứ còn lại nằm ở components/mega-menu.css.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * RUỘT BẢNG LÀ THẺ ẢNH, KHÔNG PHẢI CỘT CHỮ — VÀ ĐÂY LÀ CHỖ KHÁC MEGA
 *
 * Mega "Sản phẩm" có ba cột chữ, mỗi cột bốn liên kết lọc. Bảng này không lấp
 * được ba cột đó: dưới một bộ sưu tập thì bốn dòng ấy phải là các lát cắt CÓ
 * HÀNG THẬT trong chính bộ đó, mà bảng `products` hiện chưa có dòng nào và ba
 * cột mô tả của bộ sưu tập (`audience`, `palette`, `signature`) đều đang NULL.
 * Bịa bốn dòng cho đủ chỗ là dựng bốn ngõ cụt — đúng thứ mega-menu.php đã từ
 * chối làm khi bỏ bốn nhãn gõ cứng của bản thiết kế.
 *
 * Nên mỗi Ô của lưới là MỘT BỘ dạng thẻ ảnh, dùng lại .mega-feature — chính
 * cái thẻ đang nằm ở cột cuối của mega. Lưới không phải sửa gì: --mega-cols
 * đếm số thẻ bộ sưu tập, còn cột 1.15fr có sẵn ở cuối là chỗ của thẻ "Tất cả".
 *
 * Ngày ai đó nhập hàng và gắn vào bộ sưu tập, đổi sang cột chữ là việc của một
 * commit khác — lúc đó liên kết mới dẫn tới chỗ có thứ để xem.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * KHÔNG CÓ BỘ NÀO ĐANG HIỆN -> IN LIÊN KẾT TRƠN
 *
 * Cửa hàng có lúc ẩn hết để chuẩn bị mùa mới (trang /bo-suu-tap đã lường
 * trước — xem .colls__empty). Lúc ấy chevron mở ra một bảng rỗng là lời hứa
 * suông, nên mục này quay về đúng cái nó vốn là: một liên kết thường.
 */

/* Tự đọc được nếu nơi gọi không truyền — cùng lối phòng thủ của mega-menu.php,
   để file này không phụ thuộc ngầm vào đúng một nơi gọi. */
$collectionsNav = $collectionsNav ?? CollectionModel::visible();

/*
 * CẮT CÒN 3 THẺ — CỐ ĐỊNH, cộng thẻ "Tất cả" là 4 ô trên một hàng.
 *
 * ĐÃ TỪ 5 XUỐNG 3 (theo yêu cầu). Con số cũ chọn theo mức TỐI ĐA lưới chịu
 * được (6 ô ở 1101px trước khi ô hẹp hơn cái ảnh trong nó), tức là nhồi cho
 * đầy chỗ. Nay chọn theo thứ khách đọc được trong một cái liếc: ba bộ để cân
 * nhắc, một lối ra xem đủ.
 *
 * Số này ăn khớp với bề rộng trần của bảng trong components/collection-menu.css
 * — trần đó tính cho ĐÚNG bốn ô. Đổi số ở đây thì sửa cả công thức bên đó,
 * không thì bảng lại phình ra hoặc bị bóp.
 *
 * Thứ tự đã do cửa hàng sắp (sort_order) nên ba cái đầu đúng là ba cái muốn
 * khoe nhất; phần còn lại nằm sau thẻ "Tất cả".
 */
$bstToiDa = 3;
$bstDanhSach = array_slice($collectionsNav, 0, $bstToiDa);

/**
 * Dòng chữ nhỏ dưới tên bộ.
 *
 * Ưu tiên câu giới thiệu của cửa hàng; bộ chưa có câu nào thì lấy THÁNG/NĂM ra
 * mắt. Hai thứ này không thay thế nhau về nghĩa, nhưng ô nào cũng cần một dòng
 * thứ hai — thiếu nó thì thẻ đó thấp hơn hẳn mấy thẻ bên cạnh và cả hàng trông
 * như xếp hỏng.
 *
 * CHỈ tháng/năm, không ngày đầy đủ: bộ sưu tập là chuyện theo mùa, "Ra mắt
 * 03/2026" nói đúng nhịp cửa hàng làm việc, còn "14/03/2026" gợi ý một sự kiện
 * diễn ra đúng hôm đó — điều không có thật. Cùng luật với trang /bo-suu-tap
 * (xem $ngayRaMat trong collection/index.php).
 *
 * Cắt 30 ký tự vì thẻ chỉ rộng ~220px, giống thẻ ở mega.
 */
$bstDongPhu = static function (array $bst): string {
    $cau = excerpt($bst['tagline'] ?? '', 30);

    if ($cau !== '') {
        return $cau;
    }

    $ngay = (string) ($bst['launched_at'] ?? '');

    if (preg_match('/^(\d{4})-(\d{2})-/', $ngay, $m)) {
        return 'Ra mắt ' . $m[2] . '/' . $m[1];
    }

    return '';
};
?>
<?php if ($bstDanhSach === []): ?>
    <li>
        <a href="/bo-suu-tap"
           <?= $isCollectionActive ? 'class="is-active" aria-current="page"' : '' ?>><?= e(t('nav.collections')) ?></a>
    </li>
<?php else: ?>
<li class="mega mega--bst">

    <a href="/bo-suu-tap"
       class="mega__trigger<?= $isCollectionActive ? ' is-active' : '' ?>"
       <?= $isCollectionActive ? 'aria-current="page"' : '' ?>>
        <?= e(t('nav.collections')) ?>
        <svg class="mega__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <?php /* Mũi nhọn hình thoi cắm vào mép trên bảng — thứ trả lời câu
                 "cái nút này mở ra cái gì". Là <span> thật chứ không phải
                 ::after vì trigger đã dùng ::after cho vùng bắt chuột phủ
                 khoảng trống; xem mega-menu.css. */ ?>
        <span class="mega__caret" aria-hidden="true"></span>
    </a>

    <?php /* --mega-cols: số thẻ BỘ SƯU TẬP, chưa tính thẻ "Tất cả" — cột
             1.15fr cuối cùng của .mega__grid chính là chỗ của nó. */ ?>
    <div class="mega__panel">
        <div class="mega__grid" style="--mega-cols: <?= count($bstDanhSach) ?>">

            <?php foreach ($bstDanhSach as $bst): ?>
                <?php
                /* cover() đã tự kiểm file có thật hay không và trả '' khi không
                   có ảnh dùng được, nên ở đây chỉ cần hỏi chuỗi rỗng. Ô ảnh vẫn
                   giữ đúng khổ khi trống, để hàng thẻ không xô lệch. */
                $bstAnh = CollectionModel::cover($bst);
                $bstCau = $bstDongPhu($bst);
                ?>
                <a class="mega-feature" href="/bo-suu-tap/<?= e(rawurlencode($bst['slug'])) ?>">
                    <span class="mega-feature__media">
                        <?php if ($bstAnh !== ''): ?>
                            <img src="<?= e(asset($bstAnh)) ?>" alt=""
                                 width="400" height="280" loading="lazy" decoding="async">
                        <?php endif; ?>
                    </span>

                    <span class="mega-feature__body">
                        <span class="mega-feature__name"><?= e($bst['name']) ?></span>
                        <?php /* "· Xem ngay →" bọc riêng và cấm ngắt dòng, nếu
                                 không mũi tên hay rơi xuống một dòng của riêng
                                 nó khi câu giới thiệu dài. */ ?>
                        <span class="mega-feature__note">
                            <?= e($bstCau) ?>
                            <span class="mega-feature__more">· Xem ngay &rarr;</span>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>

            <?php
            /*
             * Ô CUỐI: THẺ "TẤT CẢ BỘ SƯU TẬP" — CỐ Ý KHÔNG CÓ ẢNH.
             *
             * Đã thử cho nó một tấm ảnh cho giống ba thẻ bên cạnh. Không có tấm
             * nào dùng được mà trung thực: ảnh duy nhất có thể lấy là ảnh của
             * một trong ba bộ đang đứng ngay cạnh, và cùng một tấm hiện hai lần
             * trên một hàng thì trông như lỗi kết xuất chứ không như chủ ý.
             *
             * Nên nó là một tấm nền brand đặc — vẫn đúng khổ ô, vẫn là một thẻ
             * bấm được, và nổi hẳn lên đúng như vai trò của nó: lối ra trang đầy
             * đủ, nơi có ảnh lớn, ngày ra mắt và phần FAQ mà bảng xổ không chứa
             * nổi.
             *
             * Số bộ ĐẾM CẢ phần đã bị cắt khỏi bảng, vì đó chính là thứ nó hứa:
             * bấm vào là thấy đủ.
             */
            ?>
            <a class="mega-feature mega-feature--all" href="/bo-suu-tap">
                <span class="mega-feature__body">
                    <span class="mega-feature__name"><?= e(t('nav.all_collections')) ?></span>
                    <span class="mega-feature__note">
                        <?= e(sprintf(t('nav.collections_count'), count($collectionsNav))) ?>
                        <span class="mega-feature__more">&rarr;</span>
                    </span>
                </span>
            </a>

        </div>
    </div>
</li>
<?php endif; ?>
