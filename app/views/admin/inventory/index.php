<?php

/**
 * admin/inventory/index.php — tồn kho
 * Dựng theo "Tồn kho.dc.html" (Claude Design).
 *
 * Mỗi dòng là một form riêng để sửa được từng sản phẩm mà không phải gửi cả
 * bảng. Đổi một dòng chỉ ảnh hưởng dòng đó — hai người cùng nhập hàng không
 * ghi đè lên nhau.
 */

$tabs = [
    ''    => ['Tất cả',       (int) $counts['total']],
    'low' => ['Sắp hết (≤' . $low . ')', (int) $counts['low_stock']],
    'out' => ['Hết hàng',     (int) $counts['out_stock']],
];

/* Giữ từ khoá khi bấm sang viên lọc khác — gõ "titan" rồi bấm "Sắp hết" phải
   ra gọng titan sắp hết, không phải toàn bộ hàng sắp hết của cửa hàng. */
$giuQ = $q !== '' ? ['q' => $q] : [];

$duongDanLoc = static function (string $key) use ($giuQ): string {
    /* KHÔNG mang `page` sang: đổi viên lọc là đổi hẳn tập đang xem, và "trang
       3 của Sắp hết" không liên quan gì tới trang 3 của Tất cả. Giữ lại số
       trang thì bấm sang một viên chỉ có hai trang là rơi vào trang rỗng. */
    $tham = $giuQ + ($key !== '' ? ['loc' => $key] : []);

    return '/quan-tri/ton-kho' . ($tham !== [] ? '?' . http_build_query($tham) : '');
};

/* Địa chỉ của MỘT trang, giữ nguyên viên lọc và từ khoá đang xem. */
$duongDanTrang = static function (int $so) use ($q, $filter): string {
    $tham = array_filter([
        'q'    => $q,
        'loc'  => $filter,
        'page' => $so > 1 ? (string) $so : '',
    ], static fn (string $v): bool => $v !== '');

    return '/quan-tri/ton-kho' . ($tham !== [] ? '?' . http_build_query($tham) : '');
};
?>
<?php /* CÂU CẢNH BÁO VỀ SỐ 0 NẰM NGAY DÒNG DẪN, không còn là một dòng chữ
         dưới đáy bảng.

         Ở đáy bảng nó chỉ được đọc bởi người đã cuộn hết trang — tức là gần
         như không ai, vì thao tác ở đây là sửa một dòng rồi gửi luôn. Mà điều
         nó nói là một HẬU QUẢ KHÔNG LÙI ĐƯỢC bằng chính cái nút bên cạnh: đặt
         0 là sản phẩm biến mất khỏi trang bán hàng. Câu đó phải đọc được
         TRƯỚC khi gõ, không phải sau. */ ?>
<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Tồn kho</h1>
        <p class="ahead__lead">
            Cập nhật số lượng sau khi nhập hàng hoặc kiểm kê. Đặt tồn về
            <strong>0</strong> sẽ tự chuyển sang <em>hết hàng</em> và ẩn nút mua
            ở trang bán hàng.
        </p>
    </div>

    <div class="ahead__tools">
        <?php /* Form GET, không JS: gõ rồi Enter là trang tải lại với ?q=… trên
                 địa chỉ — chia sẻ được, quay lại được, F5 không hỏi gửi lại. */ ?>
        <form class="asearch" method="get" action="/quan-tri/ton-kho" role="search">
            <?php if ($filter !== ''): ?>
                <input type="hidden" name="loc" value="<?= e($filter) ?>">
            <?php endif; ?>
            <label class="sr-only" for="invQ">Tìm sản phẩm trong kho</label>
            <input type="search" id="invQ" name="q" value="<?= e($q) ?>"
                   placeholder="Tìm theo tên, SKU, thương hiệu…">
            <button type="submit" class="astatus__save astatus__save--ghost">Tìm</button>
            <?php if ($q !== ''): ?>
                <a href="<?= e($duongDanLoc($filter)) ?>" class="apanel__more">Xoá tìm kiếm</a>
            <?php endif; ?>
        </form>
    </div>
</header>

<nav class="atabs" aria-label="Lọc tồn kho">
    <?php foreach ($tabs as $key => [$label, $count]): ?>
        <a class="atabs__item<?= $filter === $key ? ' is-active' : '' ?>"
           href="<?= e($duongDanLoc((string) $key)) ?>"
           <?= $filter === $key ? 'aria-current="true"' : '' ?>>
            <?= e($label) ?> <span class="atabs__num"><?= $count ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($products === []): ?>
    <p class="apanel__empty">
        <?= $q !== '' || $filter !== ''
            ? 'Không có sản phẩm nào khớp bộ lọc.'
            : 'Chưa có sản phẩm nào.' ?>
    </p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable aitable">
            <thead>
                <tr>
                    <th scope="col">SKU</th>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col">Giá</th>
                    <th scope="col">Tồn hiện tại</th>
                    <th scope="col">Cập nhật</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <?php
                    $qty = (int) $p['stock_quantity'];

                    /* BA MỨC, cùng ngưỡng với dải viên lọc ngay trên bảng và với
                       thẻ "Sắp hết hàng" ở trang Tổng quan. Đọc số tồn chứ không
                       đọc cột `status`: ở ĐÂY câu hỏi là "còn bao nhiêu cái",
                       còn `status` trả lời câu khác ("có đang bán không") và
                       chính cái nút Lưu bên cạnh mới là thứ đặt lại nó. */
                    [$nhan, $lopNhan] = $qty <= 0
                        ? ['Hết hàng', 'out_of_stock']
                        : ($qty <= $low ? ['Sắp hết', 'low_stock'] : ['Còn hàng', 'in_stock']);
                    ?>
                    <tr class="ainv<?= $qty <= 0 ? ' is-out' : ($qty <= $low ? ' is-low' : '') ?>">
                        <td><code><?= e($p['sku']) ?></code></td>
                        <td>
                            <?php /* Mở TAB MỚI sang trang bán hàng: người đang sửa
                                     tồn của mười dòng không muốn rời bảng. Kiểm tra
                                     xem khách nhìn thấy gì là việc phụ, làm xong thì
                                     đóng tab và bảng vẫn còn nguyên chỗ cũ. */ ?>
                            <a class="ainame" href="/san-pham/<?= e(rawurlencode($p['slug'])) ?>"
                               target="_blank" rel="noopener"><?= e($p['name']) ?></a>
                            <span class="atable__sub"><?= e($p['brand'] ?? '—') ?></span>
                        </td>
                        <td class="num"><?= money((int) $p['price']) ?></td>
                        <td>
                            <?php /* Con số VÀ viên nhãn cạnh nhau, không phải con số
                                     với một dòng chữ nhỏ bên dưới — theo bản thiết
                                     kế. Số trả lời "còn mấy cái", viên nhãn trả lời
                                     "thế là nhiều hay ít"; hai câu khác nhau nên
                                     đứng cạnh nhau chứ không chồng lên nhau. */ ?>
                            <span class="aistock">
                                <span class="aistock__num"><?= $qty ?></span>
                                <span class="badge badge--<?= e($lopNhan) ?>"><?= e($nhan) ?></span>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="/quan-tri/ton-kho/cap-nhat" class="ainv__form">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                <input type="hidden" name="loc" value="<?= e($filter) ?>">
                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                <?php /* Giữ số trang qua lượt lưu — xem chú thích ở
                                         InventoryAdminController::updateStock, kể cả
                                         chuyện dòng vừa sửa có thể nhảy sang trang
                                         khác vì bảng sắp theo tồn. */ ?>
                                <input type="hidden" name="page" value="<?= (int) $page ?>">

                                <?php
                                /*
                                 * BỘ ĐẾM − / + CHỈ MỌC RA KHI CÓ JAVASCRIPT.
                                 *
                                 * Bản thiết kế vẽ ô nhập nằm giữa hai nút trừ và
                                 * cộng. Hai nút ấy không làm được bằng HTML thuần:
                                 * chúng phải sửa giá trị ô nhập tại chỗ, không gửi
                                 * form. Cho chúng là nút submit thì mỗi lần bấm là
                                 * một lượt tải trang — nhập một thùng 24 cái thành
                                 * 24 lượt tải.
                                 *
                                 * Nên chúng ra đời `hidden` và admin-inventory.js
                                 * mở ra, đúng nếp đã ghi ở assets/js/admin.js: thà
                                 * không có gì còn hơn để lại một ô bấm vào không
                                 * làm gì. Tắt JS thì còn đúng ô số với nút tăng
                                 * giảm sẵn có của trình duyệt — vẫn nhập được.
                                 */
                                ?>
                                <div class="aistep">
                                    <button class="aistep__btn" type="button" data-step="-1"
                                            hidden aria-label="Giảm một">−</button>

                                    <label class="sr-only" for="q-<?= e($p['id']) ?>">Tồn kho mới cho <?= e($p['name']) ?></label>
                                    <input class="aistep__input" type="number" id="q-<?= e($p['id']) ?>"
                                           name="stock_quantity" value="<?= $qty ?>"
                                           min="0" max="99999" step="1" inputmode="numeric">

                                    <button class="aistep__btn" type="button" data-step="1"
                                            hidden aria-label="Tăng một">+</button>
                                </div>

                                <button type="submit" class="astatus__save aisave">Lưu</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php /* Chân bảng nói RÕ CÁCH SẮP XẾP. Bảng này không sắp theo tên hay
                 theo mã mà theo tồn thấp nhất trước — trật tự duy nhất có ích ở
                 đây, nhưng cũng là trật tự không ai đoán ra nếu không nói. */ ?>
        <div class="aofoot">
            <p class="aofoot__count">
                Đang hiện <?= count($products) ?> / <?= (int) $total ?> sản phẩm<?php
                    if ($totalPages > 1): ?> · trang <?= (int) $page ?>/<?= (int) $totalPages ?><?php
                    endif; ?> · sắp theo tồn thấp nhất trước
            </p>

            <?php if ($totalPages > 1): ?>
                <nav class="pager" aria-label="Phân trang">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pager__link" href="<?= e($duongDanTrang($i)) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
