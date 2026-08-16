<?php

/**
 * _layout/mega-menu.php — bảng xổ của mục "Sản phẩm" trên thanh điều hướng.
 *
 * File này được require BÊN TRONG <ul class="header-nav__list"> nên phần tử
 * gốc phải là <li>. Biến $isProductActive do header.php đặt sẵn.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * MỞ BẰNG CSS, KHÔNG BẰNG JAVASCRIPT
 *
 * :hover mở panel, :focus-within giữ nó mở khi người dùng Tab vào bên trong.
 * Thiếu :focus-within thì bảng này chỉ tồn tại với người dùng chuột — bấm Tab
 * là focus nhảy vào các liên kết đang bị ẩn, không ai thấy mình đang ở đâu.
 *
 * Mega chỉ chạy từ 1101px. Hẹp hơn thì cả .header-nav ẩn đi và menu trượt
 * (_layout/mega-menu-mobile.php) lo phần điều hướng.
 * ─────────────────────────────────────────────────────────────────────────────
 * BỐ CỤC PHẢI ĐỨNG YÊN KHI KHO LỚN LÊN
 *
 * Admin sẽ thêm sản phẩm và danh mục về sau, nên mọi thứ ở đây đều có TRẦN:
 *
 *   1. Dải "Nổi bật" LUÔN đúng 3 thẻ. Kho có 3 hay 300 mặt hàng thì bảng vẫn
 *      cao y hệt. Chưa gắn cờ "nổi bật" cho mặt hàng nào thì lấy hàng mới về
 *      bù vào — bảng không bao giờ có ô trống.
 *   2. Cột danh mục cắt ở MAX_CATS mục; phần dôi ra do liên kết "Tất cả sản
 *      phẩm" gánh. Không cắt thì thêm 20 danh mục là bảng dài quá màn hình.
 *   3. Bốn cột còn lại đọc từ config/taxonomy.php — danh sách cố định, người
 *      sửa file tự thấy nó dài bao nhiêu.
 *
 * Kho rỗng hoàn toàn (chưa có sản phẩm nào) thì dải "Nổi bật" biến mất cả
 * tiêu đề, và lưới còn 4 cột. Khung ảnh trống trông như trang hỏng.
 * ─────────────────────────────────────────────────────────────────────────────
 */

/** Số danh mục hiện tối đa trong cột đầu. */
$maxCats = 8;

/** Số thẻ sản phẩm của dải "Nổi bật" — cố định, xem ghi chú trên. */
$tileCount = 3;

$taxonomy = config('taxonomy');

/* header.php đã đọc danh mục để dùng chung với menu trượt và truyền xuống đây
   qua phạm vi của `require`. Vẫn tự đọc được nếu thiếu, để file này không phụ
   thuộc ngầm vào đúng một nơi gọi. */
$categories = $categories ?? CategoryModel::visible();

/*
 * Ưu tiên hàng đã gắn cờ "nổi bật"; thiếu thì bù bằng hàng mới về. Lọc trùng
 * theo id vì một mặt hàng vừa nổi bật vừa mới về sẽ có mặt ở cả hai danh sách.
 */
$tiles = ProductModel::featured($tileCount);

if (count($tiles) < $tileCount) {
    $have = array_column($tiles, 'id');

    foreach (ProductModel::newest($tileCount * 2) as $p) {
        if (count($tiles) >= $tileCount) {
            break;
        }
        if (!in_array($p['id'], $have, true)) {
            $tiles[] = $p;
        }
    }
}

/** Dựng URL lọc: ['shape' => 'Square'] -> /san-pham?shape=Square */
$filterUrl = static fn (array $search): string => '/san-pham?' . http_build_query($search);

/*
 * Hai cột chữ ở giữa. Năm nhóm phân loại chỉ xếp vào HAI cột chứ không phải
 * năm: cộng cả cột danh mục và dải nổi bật thì bảng đã sáu vùng, mà mega chỉ
 * chạy từ 1101px — trừ 56px đệm mỗi bên còn 989px, chia sáu là mỗi vùng ~150px,
 * đủ hẹp để "Chống ánh sáng xanh" gãy làm ba dòng. Bốn vùng thì mỗi nhãn nằm
 * gọn một dòng.
 *
 * Ghép theo nghĩa chứ không phải ghép cho đủ chỗ: dáng gọng đi với đối tượng
 * (cùng nói về cái gọng đeo lên mặt ai), chất liệu đi với tính năng tròng
 * (cùng nói về thứ làm nên cặp kính).
 */
$columns = [
    ['title' => 'Dáng gọng', 'groups' => [
        ['title' => null,        'links' => $taxonomy['frame_styles']],
        ['title' => 'Đối tượng', 'links' => $taxonomy['audiences']],
    ]],
    ['title' => 'Chất liệu', 'groups' => [
        ['title' => null,              'links' => $taxonomy['materials']],
        ['title' => 'Tính năng tròng', 'links' => $taxonomy['lens_functions']],
    ]],
];
?>
<li class="mega">

    <a href="/san-pham"
       class="mega__trigger<?= $isProductActive ? ' is-active' : '' ?>"
       <?= $isProductActive ? 'aria-current="page"' : '' ?>>
        Sản phẩm
        <svg class="mega__chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?php /* SỢI CHỈ nối viên thuốc xuống mép trên của bảng — thứ trả lời
                 câu "cái nút này mở ra cái gì". Nó tự vẽ xuống khi mở. Để là
                 <span> thật chứ không phải ::after vì trigger đã dùng ::after
                 cho vùng bắt chuột phủ khoảng trống. */ ?>
        <span class="mega__thread" aria-hidden="true"></span>
    </a>

    <div class="mega__panel">
        <div class="mega__grid<?= $tiles === [] ? ' mega__grid--no-tiles' : '' ?>">

            <!-- ══════════ CỘT 1 — DANH MỤC (từ CSDL) ══════════
                 Trục chính của bảng, nên nó khác các cột lọc: chữ to hơn một
                 bậc và mỗi dòng mang SỐ MẶT HÀNG đang bán. Con số là thứ giúp
                 khách chọn trước khi bấm, không phải hoạ tiết. -->
            <div class="mega__col mega__col--primary">
                <p class="mega__label">Danh mục</p>
                <ul class="mega__cats" role="list">
                    <?php foreach (array_slice($categories, 0, $maxCats) as $cat): ?>
                        <?php $n = (int) ($cat['product_count'] ?? 0); ?>
                        <li>
                            <?php /* Danh mục 0 món vẫn hiện — admin vừa tạo nó phải thấy nó ở
                                     đây — nhưng làm nhạt đi, vì bấm vào là ra trang rỗng. Nhạt
                                     chứ không ẩn: ẩn thì admin tưởng mình tạo hỏng. */ ?>
                            <a href="/san-pham?category=<?= e(rawurlencode($cat['slug'])) ?>"
                               <?= $n === 0 ? 'class="is-empty"' : '' ?>>
                                <span class="mega__cat-name"><?= e($cat['name']) ?></span>
                                <span class="mega__cat-count"><?= $n ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a class="mega__all" href="/san-pham">
                    Tất cả sản phẩm
                    <span class="mega__all-arrow" aria-hidden="true">→</span>
                </a>
            </div>

            <!-- ══════════ CỘT 2–3 — CÁC LÁT CẮT LỌC ══════════ -->
            <?php foreach ($columns as $col): ?>
                <div class="mega__col">
                    <p class="mega__label"><?= e($col['title']) ?></p>

                    <?php foreach ($col['groups'] as $group): ?>
                        <?php if ($group['title'] !== null): ?>
                            <p class="mega__label mega__label--sub"><?= e($group['title']) ?></p>
                        <?php endif; ?>

                        <ul class="mega__links" role="list">
                            <?php foreach ($group['links'] as $link): ?>
                                <li>
                                    <a href="<?= e($filterUrl($link['search'])) ?>"><?= e($link['label']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <!-- ══════════ CỘT 4 — THƯƠNG HIỆU ══════════ -->
            <div class="mega__col">
                <p class="mega__label">Thương hiệu</p>
                <ul class="mega__links mega__links--2col" role="list">
                    <?php foreach ($taxonomy['top_brands'] as $brand): ?>
                        <li><a href="<?= e($filterUrl(['brand' => $brand])) ?>"><?= e($brand) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- ══════════ DẢI NỔI BẬT — luôn 3 thẻ, hoặc không có gì ══════════ -->
            <?php if ($tiles !== []): ?>
                <div class="mega__feature">
                    <p class="mega__label">Nổi bật</p>

                    <ul class="mega__tiles" role="list">
                        <?php foreach ($tiles as $p): ?>
                            <li>
                                <a class="mega-tile" href="/san-pham/<?= e(rawurlencode($p['slug'])) ?>">
                                    <span class="mega-tile__frame">
                                        <img src="<?= e(ProductModel::image($p)) ?>" alt=""
                                             width="120" height="90" loading="lazy" decoding="async">
                                    </span>
                                    <span class="mega-tile__name"><?= e($p['name']) ?></span>
                                    <span class="mega-tile__price"><?= money((int) $p['price']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

        </div>
    </div>
</li>
