<?php

/**
 * _layout/mega-menu.php — bảng xổ của mục "Sản phẩm" trên thanh điều hướng.
 *
 * Dựng theo "Vin Eyewear Home.dc.html". Bảng có mặt trên MỌI trang vì header
 * dùng chung — trang /san-pham cũng vậy, không phải dựng riêng.
 *
 * File này được require BÊN TRONG <ul class="header-nav__list"> nên phần tử
 * gốc phải là <li>. Biến $isProductActive do header.php đặt sẵn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ĐÃ RÚT GỌN MẠNH so với bản trước — đây là thay đổi lớn nhất của lần dựng này
 *
 * Bản trước có 5 vùng: danh mục kèm số đếm · dáng gọng + đối tượng · chất liệu
 * + tính năng tròng · thương hiệu · dải 3 thẻ sản phẩm nổi bật; kèm sợi chỉ
 * crimson nối viên thuốc xuống bảng và hiệu ứng "vào nét" ba tầng.
 *
 * Bản thiết kế mới chỉ có BỐN cột: ba cột liên kết và một thẻ ảnh bộ sưu tập.
 * Không thương hiệu, không thẻ sản phẩm, không "Tất cả sản phẩm" (chính viên
 * thuốc "Sản phẩm" đã trỏ tới /san-pham). Mọi thứ bỏ đi đều còn đường khác:
 * lọc thương hiệu và lọc sâu nằm ở cột lọc trang /san-pham, rộng rãi hơn nhiều.
 * ─────────────────────────────────────────────────────────────────────────────
 * MỞ BẰNG CSS, KHÔNG BẰNG JAVASCRIPT
 *
 * :hover mở panel, :focus-within giữ nó mở khi người dùng Tab vào bên trong.
 * Thiếu :focus-within thì bảng này chỉ tồn tại với người dùng chuột — bấm Tab
 * là focus nhảy vào các liên kết đang bị ẩn, không ai thấy mình đang ở đâu.
 *
 * Bản thiết kế dùng state + hẹn giờ đóng 180ms; ở đây là CSS thuần nên vai trò
 * đó do một vùng bắt chuột vô hình đảm nhiệm (.mega__trigger::after).
 *
 * Mega chỉ chạy từ 1101px. Hẹp hơn thì cả .header-nav ẩn đi và menu trượt
 * (_layout/mega-menu-mobile.php) lo phần điều hướng.
 * ─────────────────────────────────────────────────────────────────────────────
 * BA CỘT CỦA BẢN THIẾT KẾ LÀ BA DANH MỤC — ĐỌC TỪ CSDL
 *
 * Bản thiết kế gõ cứng ba tiêu đề "Gọng kính · Kính mát · Tròng kính" và bốn
 * liên kết dưới mỗi cái. Ở đây tiêu đề lấy từ bảng `categories`, còn bốn liên
 * kết lấy từ config/taxonomy.php và ĐƯỢC RÀNG THEO danh mục của cột — bấm
 * "Titanium" dưới cột "Gọng kính" ra gọng titan, không ra cả kho.
 *
 * Vì sao không gõ cứng theo thiết kế: bốn nhãn của bản thiết kế ("Gọng không
 * viền", "Kính thể thao"…) không có giá trị tương ứng nào trong CSDL, bấm vào
 * là ra trang rỗng. Một liên kết dẫn tới chỗ trống tệ hơn là không có nó.
 *
 * Số cột chạy theo số danh mục CÓ THẬT trong bảng `categories` (xem
 * --mega-cols), nên cửa hàng thêm danh mục thứ tư là nó tự có chỗ, không bị
 * rơi ra ngoài như khi khoá cứng ba cột.
 */

$taxonomy = config('taxonomy');

/* header.php đã đọc danh mục để dùng chung với menu trượt và truyền xuống đây
   qua phạm vi của `require`. Vẫn tự đọc được nếu thiếu, để file này không phụ
   thuộc ngầm vào đúng một nơi gọi. */
$categories = $categories ?? CategoryModel::visible();

/** Số liên kết tối đa mỗi cột — bản thiết kế vẽ đúng 4. */
$maxLinks = 4;

/*
 * Lát cắt lọc hợp nghĩa cho từng danh mục.
 *
 * Ghép theo NGHĨA, không phải ghép cho đủ chỗ: người tìm gọng nghĩ theo chất
 * liệu (titan hay acetate), người tìm kính mát nghĩ theo dáng (phi công hay
 * mắt mèo), người tìm tròng nghĩ theo tính năng (chống ánh sáng xanh hay đổi
 * màu). Danh mục lạ chưa có trong bảng thì rơi về dáng gọng — lát cắt dùng
 * được cho mọi thứ đeo lên mặt.
 */
$sliceBySlug = [
    'gong-kinh'  => $taxonomy['materials'],
    'kinh-mat'   => $taxonomy['frame_styles'],
    'trong-kinh' => $taxonomy['lens_functions'],
];

/**
 * Dựng URL lọc, LUÔN kèm danh mục của cột:
 *   ['q' => 'titanium'] trong cột "Gọng kính" -> /san-pham?category=gong-kinh&q=titanium
 *
 * category đứng TRƯỚC để chuỗi truy vấn đọc được từ rộng tới hẹp.
 */
$filterUrl = static fn (string $slug, array $search): string =>
    '/san-pham?' . http_build_query(['category' => $slug] + $search);

/*
 * Thẻ ảnh ở cột cuối — ô "mega-featured" của bản thiết kế.
 *
 * Bản thiết kế ghi cứng "Bộ sưu tập 2026 / 10+ mẫu mới vừa về". Ở đây lấy bộ
 * sưu tập ĐẦU TIÊN theo thứ tự trưng bày: cùng bố cục, nhưng tên và câu
 * mô tả là thật, và liên kết ra đúng bộ lọc của nó. Treo "10+ mẫu" lên một cửa
 * hàng đang có 6 mặt hàng là nói sai ngay trên trang.
 *
 * Không có bộ sưu tập nào thì cả thẻ biến mất và lưới còn lại các cột chữ —
 * một khung ảnh trống trông như trang hỏng.
 */
/* Bộ đầu tiên theo thứ tự trưng bày của cửa hàng (sort_order), lấy từ CSDL
   thay cho config/collections.php. CollectionModel::cover() đã tự kiểm file có
   thật hay không, nên ở đây không cần is_file() nữa.

   DANH SÁCH DO header.php ĐỌC rồi truyền xuống — cùng danh sách mà bảng xổ
   "Bộ sưu tập" đang dùng, nên thẻ ảnh ở đây luôn là bộ đứng đầu bảng xổ đó,
   không phải hai lần truy vấn cho hai kết quả có thể lệch nhau. Vẫn tự đọc
   được nếu thiếu, giống $categories ngay trên. */
$collectionsNav = $collectionsNav ?? CollectionModel::visible();

$feature = $collectionsNav[0] ?? null;

if ($feature !== null) {
    $featureImage = CollectionModel::cover($feature);

    $featureImage = designImage('mega-featured', $featureImage);
}
?>
<li class="mega">

    <a href="/san-pham"
       class="mega__trigger<?= $isProductActive ? ' is-active' : '' ?>"
       <?= $isProductActive ? 'aria-current="page"' : '' ?>>
        <?= e(t('nav.products')) ?>
        <svg class="mega__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <?php /* Mũi nhọn hình thoi cắm vào mép trên bảng — thứ trả lời câu
                 "cái nút này mở ra cái gì". Thay cho SỢI CHỈ crimson của bản
                 trước. Là <span> thật chứ không phải ::after vì trigger đã
                 dùng ::after cho vùng bắt chuột phủ khoảng trống. */ ?>
        <span class="mega__caret" aria-hidden="true"></span>
    </a>

    <?php /* --mega-cols: số cột CHỮ, chưa tính thẻ ảnh. Bản thiết kế vẽ 3;
             CSDL hiện có 4 danh mục nên thực tế in ra 4. */ ?>
    <div class="mega__panel">
        <div class="mega__grid<?= $feature === null ? ' mega__grid--no-feature' : '' ?>"
             style="--mega-cols: <?= count($categories) ?>">

            <?php foreach ($categories as $cat): ?>
                <?php $slice = $sliceBySlug[$cat['slug']] ?? $taxonomy['frame_styles']; ?>
                <div class="mega__col">
                    <?php /* Tiêu đề cột bấm được: nó là lối vào cả danh mục, còn
                             bốn dòng dưới chỉ là lát cắt hẹp hơn của chính nó. */ ?>
                    <a class="mega__label" href="/san-pham?category=<?= e(rawurlencode($cat['slug'])) ?>">
                        <?= e($cat['name']) ?>
                    </a>

                    <ul class="mega__links" role="list">
                        <?php foreach (array_slice($slice, 0, $maxLinks) as $link): ?>
                            <li>
                                <a href="<?= e($filterUrl($cat['slug'], $link['search'])) ?>"><?= e($link['label']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <?php if ($feature !== null): ?>
                <a class="mega-feature" href="/san-pham?<?= e(http_build_query(['collection' => $feature['slug']])) ?>">
                    <span class="mega-feature__media">
                        <?php if ($featureImage !== ''): ?>
                            <img src="<?= e($featureImage) ?>" alt=""
                                 width="400" height="280" loading="lazy" decoding="async">
                        <?php endif; ?>
                    </span>

                    <span class="mega-feature__body">
                        <span class="mega-feature__name"><?= e($feature['name']) ?></span>
                        <?php /* "· Xem ngay →" bọc riêng và cấm ngắt dòng: câu
                                 mô tả do người nhập nội dung viết, dài ngắn tuỳ
                                 ý, nên nếu để chảy tự do thì mũi tên hay rơi
                                 xuống một dòng của riêng nó. Cắt tagline ngắn
                                 hơn ô này (30 ký tự) vì thẻ chỉ rộng ~220px. */ ?>
                        <span class="mega-feature__note">
                            <?= e(excerpt($feature['tagline'] ?? '', 30)) ?>
                            <span class="mega-feature__more">· Xem ngay →</span>
                        </span>
                    </span>
                </a>
            <?php endif; ?>

        </div>
    </div>
</li>
