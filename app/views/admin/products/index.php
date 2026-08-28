<?php

/**
 * admin/products/index.php — sản phẩm
 * Port từ quan-tri/san-pham.tsx + admin-product-form.tsx.
 */

$ed = $editing;

// Cột JSON trong DB là chuỗi; đổi về dạng người nhập được.
// Ảnh giữ nguyên dạng MẢNG (mỗi ảnh một ô có hình xem trước), thông số thì
// vẫn là chuỗi nhiều dòng.
$edImages = [];
$edSpecs  = '';

if ($ed !== null) {
    $edImages = array_values(array_filter(
        (array) json_decode((string) $ed['images'], true),
        'is_string'
    ));

    foreach (json_decode((string) $ed['specs'], true) ?: [] as $label => $value) {
        $edSpecs .= $label . ': ' . $value . "\n";
    }
}
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Sản phẩm',
    'lead'  => $total . ' sản phẩm' . ($totalPages > 1 ? ' · trang ' . $page . '/' . $totalPages : ''),
    'base'  => '/quan-tri/san-pham', 'canEdit' => $canEdit, 'editing' => $ed,
    'addLabel' => '+ Thêm sản phẩm',
    /* Ô tìm kiếm đi CÙNG dòng tiêu đề, không còn là một dải riêng bên dưới —
       xem khối $search trong admin/_layout/crud-head.php. Câu gợi ý kê đúng ba
       thứ tìm được, vì đó là ba thứ khác nhau người ta gõ vào đây: khách hỏi
       theo tên, nhân viên kho hỏi theo SKU, nhà cung cấp hỏi theo thương hiệu. */
    'search' => [
        'name'        => 'q',
        'value'       => $q,
        'label'       => 'Tìm sản phẩm',
        'placeholder' => 'Tìm theo tên, SKU, thương hiệu…',
    ],
]); ?>

<?php
/*
 * DẢI VIÊN LỌC THEO DANH MỤC — theo bản thiết kế.
 *
 * Không dùng partial admin/_layout/filter-tabs: partial ấy bắt buộc có $counts
 * và in một con số trong mỗi viên. Ở đây bản thiết kế cố ý KHÔNG có số, và
 * đếm sản phẩm theo từng danh mục là thêm một truy vấn GROUP BY cho mỗi lần
 * mở trang — trả giá cho một con số mà bản vẽ đã quyết là không cần.
 *
 * Giữ ?q= khi bấm: gõ "titan" rồi bấm "Gọng kính" phải ra gọng titan, không
 * phải ra toàn bộ gọng kính.
 */
$giuQ = $q !== '' ? ['q' => $q] : [];

$duongDanCat = static function (string $id) use ($giuQ): string {
    $tham = $giuQ + ($id !== '' ? ['danh-muc' => $id] : []);

    return '/quan-tri/san-pham' . ($tham !== [] ? '?' . http_build_query($tham) : '');
};
?>
<nav class="atabs" aria-label="Lọc theo danh mục">
    <a class="atabs__item<?= $cat === '' ? ' is-active' : '' ?>"
       href="<?= e($duongDanCat('')) ?>"
       <?= $cat === '' ? 'aria-current="true"' : '' ?>>Tất cả</a>

    <?php foreach ($categories as $c): ?>
        <?php $id = (string) $c['id']; ?>
        <a class="atabs__item<?= $cat === $id ? ' is-active' : '' ?>"
           href="<?= e($duongDanCat($id)) ?>"
           <?= $cat === $id ? 'aria-current="true"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
</nav>

<div class="atable-wrap">
    <table class="atable aptable">
        <thead>
            <tr>
                <th scope="col">SKU</th>
                <th scope="col">Sản phẩm</th>
                <th scope="col">Danh mục</th>
                <th scope="col">Giá</th>
                <th scope="col">Tồn</th>
                <th scope="col">Trạng thái</th>
                <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><code><?= e($p['sku']) ?></code></td>
                    <td>
                        <?= e($p['name']) ?>
                        <span class="atable__sub"><?= e($p['brand'] ?? '—') ?> · <?= e($p['frame_shape'] ?? '—') ?></span>
                    </td>
                    <td><?= e($p['category_name'] ?? '—') ?></td>
                    <?php /* Giá CANH TRÁI, không canh phải — theo bản thiết kế, và
                             khác cột "Tồn" ngay bên cạnh (cột đó canh phải).

                             Canh phải chỉ đáng khi các con số cần so với nhau theo
                             cột dọc. Ở đây thì không: giá bán và giá gạch ngang là
                             hai dòng CHỒNG NHAU trong cùng một ô, và cái người ta so
                             là hai dòng ấy với nhau chứ không phải giá của hàng này
                             với hàng kia. Canh trái thì hai dòng thẳng lề, đọc ra
                             ngay là "giá này thay cho giá kia". */ ?>
                    <td class="apprice">
                        <span class="apprice__now"><?= money((int) $p['price']) ?></span>
                        <?php if (!empty($p['compare_at_price'])): ?>
                            <span class="apprice__was"><?= money((int) $p['compare_at_price']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num<?= (int) $p['stock_quantity'] <= 0 ? ' is-danger' : '' ?>"><?= (int) $p['stock_quantity'] ?></td>
                    <?php
                    /*
                     * BA MỨC, KHÔNG PHẢI HAI — theo bản thiết kế.
                     *
                     * Trước đây cột này chỉ đọc `status`: còn hàng hoặc hết hàng.
                     * Nhưng "còn 2 cái" và "còn 200 cái" cùng đeo một nhãn xanh,
                     * nên nhìn cả bảng không ra chỗ nào sắp cạn — muốn biết phải
                     * đọc sang cột Tồn từng dòng một.
                     *
                     * `status` VẪN LÀ CHÂN LÝ cho mức "Hết hàng": một sản phẩm có
                     * thể bị tắt bán dù kho còn hàng (hàng lỗi, hàng giữ cho khách
                     * đặt riêng). Chỉ mức giữa mới suy từ số tồn.
                     *
                     * Ngưỡng 5 lấy đúng của thẻ "Sắp hết hàng" ở trang Tổng quan
                     * (DashboardController) — hai chỗ nói về cùng một tập sản phẩm
                     * thì phải cùng một ngưỡng, nếu không bảng này bảo "sắp hết"
                     * mà bảng kia không kể tên.
                     */
                    $conBan = $p['status'] === 'in_stock';
                    $sapHet = $conBan && (int) $p['stock_quantity'] <= 5;
                    ?>
                    <td>
                        <span class="apstatus">
                        <?php if (!$conBan): ?>
                            <span class="badge badge--out_of_stock">Hết hàng</span>
                        <?php elseif ($sapHet): ?>
                            <span class="badge badge--low_stock">Sắp hết</span>
                        <?php else: ?>
                            <span class="badge badge--in_stock">Còn hàng</span>
                        <?php endif; ?>

                        <?php /* "Đang ẩn" là TRUNG TÍNH, không phải đỏ. Trước đây nó
                                 đeo .badge--cancelled — cùng sắc với "Đã huỷ" ở bảng
                                 đơn hàng, tức là đọc ra như một sự cố. Ẩn một sản
                                 phẩm là việc bình thường và cố ý: hàng chưa lên kệ,
                                 hàng theo mùa. Bản thiết kế cho nó màu xám. */ ?>
                        <?php if (!$p['is_visible']): ?>
                            <span class="badge badge--neutral">Đang ẩn</span>
                        <?php endif; ?>
                        <?php /* .badge--featured, KHÔNG phải .badge--new: xem khối
                                 "Nổi bật" trong admin.css. Đỏ thương hiệu đứng cạnh
                                 "Còn hàng" đọc ra như một việc mới cần xử lý. */ ?>
                        <?php if ($p['is_featured']): ?>
                            <span class="badge badge--featured">Nổi bật</span>
                        <?php endif; ?>
                        </span>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="arow-actions">
                            <a href="/quan-tri/san-pham?sua=<?= e($p['id']) ?>#form">Sửa</a>
                            <?php $hoi = sprintf('Xoá sản phẩm “%s”?', $p['name']); ?>
                            <form method="post" action="/quan-tri/san-pham/xoa"
                                  data-confirm="<?= e($hoi) ?>"
                                  data-confirm-title="Xoá sản phẩm?"
                                  data-confirm-ok="Xoá"
                                  onsubmit="return confirm('<?= e($hoi) ?>')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                <button type="submit" class="arow-del">Xoá</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="pager" aria-label="Phân trang">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $url = '/quan-tri/san-pham?' . http_build_query(array_filter(['q' => $q, 'page' => $i])); ?>
            <?php if ($i === $page): ?>
                <span class="pager__link is-current" aria-current="page"><?= $i ?></span>
            <?php else: ?>
                <a class="pager__link" href="<?= e($url) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php if ($canEdit): ?>
    <section class="aform" id="form" aria-labelledby="form-title">
        <h2 id="form-title" class="apanel__title">
            <?= $ed !== null ? 'Sửa sản phẩm: ' . e($ed['name']) : 'Thêm sản phẩm mới' ?>
        </h2>

        <?php /* enctype BẮT BUỘC: thiếu nó thì trình duyệt gửi mỗi TÊN file
                 dưới dạng text, $_FILES rỗng, và form "chạy" mà không ảnh nào
                 lên — không có lỗi nào để lần ra. */ ?>
        <form method="post" action="/quan-tri/san-pham/luu" class="aform__grid"
              enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field">
                <label for="name">Tên sản phẩm *</label>
                <input type="text" id="name" name="name" required maxlength="255" value="<?= e($ed['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="sku">Mã SKU *</label>
                <input type="text" id="sku" name="sku" required maxlength="64" value="<?= e($ed['sku'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="slug">Slug <span class="field__opt">(bỏ trống để tự sinh)</span></label>
                <input type="text" id="slug" name="slug" maxlength="160" value="<?= e($ed['slug'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="category_id">Danh mục</label>
                <select id="category_id" name="category_id">
                    <option value="">— Chưa phân loại —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c['id']) ?>"<?= ($ed['category_id'] ?? '') === $c['id'] ? ' selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php /* Ô CHỌN chứ không phải ô gõ tự do: gõ sai một ký tự thì mặt
                     hàng rơi ra ngoài mọi bộ sưu tập mà không có gì báo.

                     Danh sách lấy từ bảng `collections` (quản lý ở
                     /quan-tri/bo-suu-tap), KỂ CẢ bộ đang ẩn — gắn hàng vào một
                     bộ sắp ra mắt là việc làm trước khi bộ đó được hiện, nên
                     lọc bỏ bộ ẩn ở đây là chặn đúng lúc người ta cần dùng. */ ?>
            <div class="field">
                <label for="collection">Bộ sưu tập</label>
                <select id="collection" name="collection">
                    <option value="">— Không thuộc bộ sưu tập —</option>
                    <?php foreach ($collections as $col): ?>
                        <option value="<?= e($col['slug']) ?>"<?= ($ed['collection'] ?? '') === $col['slug'] ? ' selected' : '' ?>>
                            <?= e($col['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="brand">Thương hiệu</label>
                <input type="text" id="brand" name="brand" value="<?= e($ed['brand'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="frame_shape">Dáng gọng</label>
                <input type="text" id="frame_shape" name="frame_shape" list="shapes" value="<?= e($ed['frame_shape'] ?? '') ?>">
                <datalist id="shapes">
                    <?php foreach (['Square','Round','Cat-eye','Aviator','Geometric','Oval','Wayfarer'] as $s): ?>
                        <option value="<?= e($s) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="field">
                <label for="material">Chất liệu</label>
                <input type="text" id="material" name="material" value="<?= e($ed['material'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="color">Màu sắc</label>
                <input type="text" id="color" name="color" value="<?= e($ed['color'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="gender">Đối tượng</label>
                <select id="gender" name="gender">
                    <option value="">— Không xác định —</option>
                    <?php foreach (['male'=>'Nam','female'=>'Nữ','unisex'=>'Unisex','kids'=>'Trẻ em'] as $v=>$l): ?>
                        <option value="<?= e($v) ?>"<?= ($ed['gender'] ?? '') === $v ? ' selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="price">Giá bán (VND) *</label>
                <input type="number" id="price" name="price" required min="0" step="1000"
                       value="<?= (int) ($ed['price'] ?? 0) ?>">
            </div>

            <div class="field">
                <label for="compare_at_price">Giá gốc <span class="field__opt">(để trống nếu không giảm)</span></label>
                <input type="number" id="compare_at_price" name="compare_at_price" min="0" step="1000"
                       <?php /* ?? null trước rồi mới so sánh: lúc THÊM MỚI thì $ed
                                là null, truy cập thẳng $ed['...'] sẽ cảnh báo. */ ?>
                       value="<?= ($ed['compare_at_price'] ?? null) !== null ? (int) $ed['compare_at_price'] : '' ?>">
            </div>

            <div class="field">
                <label for="stock_quantity">Tồn kho *</label>
                <input type="number" id="stock_quantity" name="stock_quantity" required min="0" step="1"
                       value="<?= (int) ($ed['stock_quantity'] ?? 0) ?>">
                <p class="field__hint">Đặt 0 sẽ tự chuyển sang "hết hàng".</p>
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_visible" <?= ($ed === null || $ed['is_visible']) ? 'checked' : '' ?>>
                    Hiển thị trên trang bán hàng
                </label>
                <label>
                    <input type="checkbox" name="is_featured" <?= !empty($ed['is_featured']) ? 'checked' : '' ?>>
                    Sản phẩm nổi bật
                </label>
            </div>

            <div class="field field--wide">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" rows="3"><?= e($ed['description'] ?? '') ?></textarea>
            </div>

            <?php /* KHU VỰC TẢI ẢNH — SRS mục 3.C.1: "Form sản phẩm gồm các
                     trường thông tin và khu vực tải lên nhiều ảnh".

                     Trước đây chỗ này là một ô gõ ĐƯỜNG DẪN tay. Nó chỉ dùng
                     được khi ảnh đã nằm sẵn trong assets/images/ do lập trình
                     viên chép vào, nghĩa là cửa hàng không tự thêm được ảnh cho
                     mặt hàng mới — đúng thứ SRS đòi phải làm được.

                     KHÔNG CÓ JS NÀO Ở ĐÂY. Ô chọn file, ô tick giữ ảnh và nút
                     chọn ảnh đại diện đều là điều khiển form thuần: tắt JS thì
                     mọi thứ vẫn chạy nguyên vẹn qua một lần POST. */ ?>
            <div class="field field--wide">
                <span class="field__label">Ảnh sản phẩm</span>

                <?php if ($edImages !== []): ?>
                    <ul class="aimgs" role="list">
                        <?php foreach ($edImages as $i => $path): ?>
                            <li class="aimgs__item">
                                <?php
                                /* TICK SẴN nghĩa là GIỮ: mở form ra rồi bấm Lưu mà không
                                   đụng gì thì ảnh phải còn nguyên. Bấm × sẽ BỎ tick — tức
                                   là chiều ngược với ảnh bìa, nên truyền x_keep = true để
                                   CSS biết trạng thái nào là "đang đánh dấu xoá".
                                   Xem admin/_layout/image-x.php. */
                                partial('admin/_layout/image-x', [
                                    'x_id' => 'x-img-' . $i, 'x_name' => 'image_keep[]',
                                    'x_value' => $path, 'x_checked' => true, 'x_keep' => true,
                                    'x_label' => 'Xoá ảnh này khi lưu',
                                ]);
                                ?>
                                <?php /* Ảnh cũ hiện to bằng chính nó chứ không phải
                                         bản nhỏ: bản nhỏ chỉ có với ảnh của seed. */ ?>
                                <img class="aimgs__thumb" src="<?= e($path) ?>" alt="" loading="lazy">

                                <?php partial('admin/_layout/image-x-btn', [
                                    'x_id' => 'x-img-' . $i, 'x_label' => 'Xoá ảnh này khi lưu',
                                ]); ?>

                                <label class="aimgs__main">
                                    <input type="radio" name="image_main"
                                           value="<?= e($path) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                                    Ảnh đại diện
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <label class="aimgs__pick" for="image_files">
                    <?= $edImages !== [] ? 'Thêm ảnh từ máy' : 'Chọn ảnh từ máy' ?>
                </label>

                <?php /* MAX_FILE_SIZE phải đứng TRƯỚC ô file mới có tác dụng.
                         Nó chỉ là gợi ý để PHP dừng sớm một file quá nặng thay
                         vì nhận hết rồi mới báo; giá trị này do form gửi lên nên
                         sửa được, máy chủ vẫn đo lại trong ImageUploader. */ ?>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int) ProductImageStorage::MAX_BYTES ?>">
                <input type="file" id="image_files" name="image_files[]" multiple
                       accept="<?= e(ProductImageStorage::accept()) ?>">

                <p class="field__hint">
                    Định dạng <?= e(ProductImageStorage::formatLabel()) ?>, mỗi ảnh tối đa
                    <?= e(ProductImageStorage::limitLabel()) ?>, tối đa
                    <?= (int) ProductImageStorage::MAX_FILES ?> ảnh cho một sản phẩm.
                    Ảnh đại diện là ảnh khách thấy đầu tiên, ảnh thứ hai hiện khi khách rê chuột.
                    Ảnh mới xếp theo thứ tự chọn — lưu xong mở lại form này để đổi ảnh đại diện.
                </p>
            </div>

            <?php if ($hasSpecs): ?>
                <?php
                /*
                 * 27 Ô THÔNG SỐ KÍNH — nuôi bảng so sánh và ngăn kéo thông số
                 * trên trang chi tiết bộ sưu tập.
                 *
                 * Không ô nào bắt buộc. Ô trống thì DÒNG tương ứng biến mất
                 * khỏi trang chứ không in dấu gạch, nên nhập được tới đâu thì
                 * trang đẹp tới đó — xem EyewearSpecs.
                 *
                 * Ô "Thông số" tự do ở cuối form vẫn còn và vẫn dùng được cho
                 * những gì không có ô riêng ở đây (ví dụ chất liệu càng kính).
                 * Đừng gõ lại vào đó những thứ đã có ô riêng: hai chỗ cùng nói
                 * một điều là hai chỗ sẽ lệch nhau.
                 */
                $cs = static fn (string $cot, string $khoa): bool => in_array(
                    $khoa,
                    preg_split('/\s*,\s*/', (string) ($ed[$cot] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [],
                    true
                );
                ?>

                <?php /* Bốn nhóm dưới đây xếp ĐÚNG thứ tự ngăn kéo thông số trên
                         trang bộ sưu tập đọc chúng, để người nhập liệu thấy được cái
                         mình đang gõ sẽ hiện ra ở đâu và cạnh cái gì. */ ?>
                <div class="aform__sect">
                    <span class="aform__sect-name">Gọng</span>
                    <span class="aform__sect-note">sáu dòng đầu của ngăn kéo thông số</span>
                </div>

                <div class="field">
                    <label for="eyewear_type">Phân loại</label>
                    <select id="eyewear_type" name="eyewear_type">
                        <option value="">— Chưa xếp loại —</option>
                        <?php foreach ((array) config('eyewear.types') as $v => $l): ?>
                            <option value="<?= e($v) ?>"<?= ($ed['eyewear_type'] ?? '') === $v ? ' selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field__hint">
                        Quyết định cách đọc dòng giá: gọng cận bán RỜI tròng, kính râm
                        thì giá đã gồm tròng. Khác với Danh mục ở trên.
                    </p>
                </div>

                <div class="field">
                    <label for="frame_finish">Hoàn thiện bề mặt</label>
                    <input type="text" id="frame_finish" name="frame_finish" maxlength="120"
                           placeholder="Đánh bóng tay, cạnh vát ba lớp"
                           value="<?= e($ed['frame_finish'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="hinge_type">Loại bản lề</label>
                    <input type="text" id="hinge_type" name="hinge_type" maxlength="120"
                           placeholder="Lò xo giấu trong thân càng"
                           value="<?= e($ed['hinge_type'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="nose_pad">Đệm mũi</label>
                    <input type="text" id="nose_pad" name="nose_pad" maxlength="120"
                           placeholder="Silicone, chỉnh được"
                           value="<?= e($ed['nose_pad'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="weight_g">Trọng lượng (gram)</label>
                    <input type="number" id="weight_g" name="weight_g" min="0" max="500" step="1"
                           value="<?= !empty($ed['weight_g']) ? (int) $ed['weight_g'] : '' ?>">
                    <p class="field__hint">Cả tròng. Dưới 25g là mốc đeo được cả ngày.</p>
                </div>

                <div class="aform__sect">
                    <span class="aform__sect-name">Kích thước</span>
                    <span class="aform__sect-note">cột "Kích thước" của bảng so sánh, và bảng quy đổi cỡ S/M/L</span>
                </div>

                <div class="field">
                    <label for="lens_width_mm">Rộng tròng (mm)</label>
                    <input type="number" id="lens_width_mm" name="lens_width_mm" min="0" max="99" step="1"
                           value="<?= !empty($ed['lens_width_mm']) ? (int) $ed['lens_width_mm'] : '' ?>">
                    <p class="field__hint">Số ĐẦU của chuẩn ghi 52□18-145.</p>
                </div>

                <div class="field">
                    <label for="bridge_mm">Cầu kính (mm)</label>
                    <input type="number" id="bridge_mm" name="bridge_mm" min="0" max="99" step="1"
                           value="<?= !empty($ed['bridge_mm']) ? (int) $ed['bridge_mm'] : '' ?>">
                    <p class="field__hint">Số GIỮA.</p>
                </div>

                <div class="field">
                    <label for="temple_mm">Dài càng (mm)</label>
                    <input type="number" id="temple_mm" name="temple_mm" min="0" max="250" step="1"
                           value="<?= !empty($ed['temple_mm']) ? (int) $ed['temple_mm'] : '' ?>">
                    <p class="field__hint">Số CUỐI. Thiếu một trong ba thì trang không in chuẩn ghi.</p>
                </div>

                <div class="field">
                    <label for="frame_width_mm">Tổng rộng gọng (mm)</label>
                    <input type="number" id="frame_width_mm" name="frame_width_mm" min="0" max="250" step="1"
                           value="<?= !empty($ed['frame_width_mm']) ? (int) $ed['frame_width_mm'] : '' ?>">
                    <p class="field__hint">
                        Ô QUAN TRỌNG NHẤT trong nhóm: cỡ S/M/L trên trang quy ra từ đây,
                        không phải từ rộng tròng. Bỏ trống là mất huy hiệu cỡ và mất luôn
                        dòng của mẫu này trong bảng quy đổi.
                    </p>
                </div>

                <div class="field">
                    <label for="lens_height_mm">Cao tròng (mm)</label>
                    <input type="number" id="lens_height_mm" name="lens_height_mm" min="0" max="99" step="1"
                           value="<?= !empty($ed['lens_height_mm']) ? (int) $ed['lens_height_mm'] : '' ?>">
                </div>

                <div class="field field--wide">
                    <span class="field__label">Hợp dáng mặt</span>
                    <div class="afacets">
                        <?php foreach ((array) config('eyewear.face_shapes') as $v => $l): ?>
                            <label class="afacets__one">
                                <input type="checkbox" name="face_shapes[]" value="<?= e($v) ?>"
                                       <?= $cs('face_shapes', $v) ? 'checked' : '' ?>>
                                <?= e($l) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="field__hint">
                        Bảng "gọng nào hợp dáng mặt nào" trên trang bộ sưu tập dựng từ
                        đây, gộp của cả bộ. Không tick gì thì mẫu này không xuất hiện
                        trong bảng đó.
                    </p>
                </div>

                <div class="aform__sect">
                    <span class="aform__sect-name">Tròng kính</span>
                    <span class="aform__sect-note">cột "Tròng" của bảng so sánh, và nhóm thứ ba của ngăn kéo</span>
                </div>

                <div class="field">
                    <label for="lens_material">Chất liệu tròng</label>
                    <input type="text" id="lens_material" name="lens_material" maxlength="120"
                           placeholder="CR-39 phân cực" value="<?= e($ed['lens_material'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="lens_index">Chiết suất</label>
                    <input type="number" id="lens_index" name="lens_index" min="1" max="9.99" step="0.01"
                           placeholder="1.61"
                           value="<?= ($ed['lens_index'] ?? null) !== null ? e((string) $ed['lens_index']) : '' ?>">
                </div>

                <div class="field field--wide">
                    <span class="field__label">Lớp phủ và tính năng tròng</span>
                    <div class="afacets">
                        <?php foreach ((array) config('eyewear.coatings') as $v => $l): ?>
                            <label class="afacets__one">
                                <input type="checkbox" name="lens_coatings[]" value="<?= e($v) ?>"
                                       <?= $cs('lens_coatings', $v) ? 'checked' : '' ?>>
                                <?= e($l) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="field field--check">
                    <label>
                        <input type="checkbox" name="is_polarized" <?= !empty($ed['is_polarized']) ? 'checked' : '' ?>>
                        Tròng phân cực
                    </label>
                    <label>
                        <input type="checkbox" name="is_photochromic" <?= !empty($ed['is_photochromic']) ? 'checked' : '' ?>>
                        Tròng đổi màu
                    </label>
                    <p class="field__hint">
                        Hai dòng này in ra trang CẢ KHI không tick ("Không") — chúng là
                        câu người mua kính râm hỏi đầu tiên, bỏ trống thì im lặng bị đọc
                        thành "chắc là có".
                    </p>
                </div>

                <div class="field">
                    <label for="lens_vlt">Truyền sáng VLT</label>
                    <input type="text" id="lens_vlt" name="lens_vlt" maxlength="40"
                           placeholder="12% hoặc 18% → 62%" value="<?= e($ed['lens_vlt'] ?? '') ?>">
                    <p class="field__hint">Ô CHỮ chứ không phải số: tròng đổi màu có hai đầu.</p>
                </div>

                <div class="field">
                    <label for="lens_category">Cấp độ tối</label>
                    <select id="lens_category" name="lens_category">
                        <option value="">— Chưa xác định —</option>
                        <?php foreach ((array) config('eyewear.lens_categories') as $v => $l): ?>
                            <option value="<?= (int) $v ?>"<?= ($ed['lens_category'] ?? null) !== null && (int) $ed['lens_category'] === (int) $v ? ' selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="base_curve">Base curve</label>
                    <input type="text" id="base_curve" name="base_curve" maxlength="20"
                           value="<?= e($ed['base_curve'] ?? '') ?>">
                </div>

                <div class="field field--check">
                    <label>
                        <input type="checkbox" name="rx_ready" <?= !empty($ed['rx_ready']) ? 'checked' : '' ?>>
                        Lắp được độ
                    </label>
                </div>

                <div class="field field--wide">
                    <label for="rx_note">Lắp độ — nói rõ tới đâu</label>
                    <input type="text" id="rx_note" name="rx_note" maxlength="255"
                           placeholder="Cận và loạn tới -6.00; chưa lắp được đa tròng"
                           value="<?= e($ed['rx_note'] ?? '') ?>">
                    <p class="field__hint">
                        Câu này thay chỗ ô tick ở trên khi in ra trang. "Lắp được độ"
                        suông là câu khách sẽ hỏi lại qua Zalo — ghi thẳng con số.
                    </p>
                </div>

                <div class="aform__sect">
                    <span class="aform__sect-name">Thương mại và chứng nhận</span>
                    <span class="aform__sect-note">hai nhóm cuối của ngăn kéo — bốn ô chính sách để TRỐNG là đúng</span>
                </div>

                <div class="field">
                    <label for="price_with_lens">Giá kèm tròng đổi độ (VND)</label>
                    <input type="number" id="price_with_lens" name="price_with_lens" min="0" step="1000"
                           value="<?= !empty($ed['price_with_lens']) ? (int) $ed['price_with_lens'] : '' ?>">
                    <p class="field__hint">Chốt sẵn ở đây, KHÔNG cộng từ bảng giá tròng.</p>
                </div>

                <div class="field">
                    <label for="barcode">Barcode</label>
                    <input type="text" id="barcode" name="barcode" maxlength="40"
                           value="<?= e($ed['barcode'] ?? '') ?>">
                </div>

                <?php
                /*
                 * BỐN Ô CHÍNH SÁCH — để trống là ĐÚNG ở hầu hết mặt hàng.
                 *
                 * Trống nghĩa là "theo chính sách chung" trong config/eyewear.php,
                 * và trang in bản chung đó ra. Điền vào đây là tự tay tách mặt
                 * hàng này khỏi chính sách chung — sửa chính sách sau này sẽ
                 * không chạm tới nó nữa.
                 */
                ?>
                <div class="field field--wide">
                    <label for="accessories">Phụ kiện đi kèm <span class="field__opt">(trống = theo chính sách chung)</span></label>
                    <input type="text" id="accessories" name="accessories" maxlength="255"
                           placeholder="<?= e((string) config('eyewear.defaults.accessories')) ?>"
                           value="<?= e($ed['accessories'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="warranty">Bảo hành <span class="field__opt">(trống = chung)</span></label>
                    <input type="text" id="warranty" name="warranty" maxlength="255"
                           placeholder="<?= e((string) config('eyewear.defaults.warranty')) ?>"
                           value="<?= e($ed['warranty'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="return_policy">Đổi trả <span class="field__opt">(trống = chung)</span></label>
                    <input type="text" id="return_policy" name="return_policy" maxlength="255"
                           placeholder="<?= e((string) config('eyewear.defaults.return_policy')) ?>"
                           value="<?= e($ed['return_policy'] ?? '') ?>">
                    <p class="field__hint">
                        Điền khi mặt hàng phải nói KHÁC — ví dụ gọng cận đã cắt tròng
                        theo đơn thì không đổi trả được.
                    </p>
                </div>

                <div class="field field--wide">
                    <label for="certifications">Chứng nhận <span class="field__opt">(trống = chung)</span></label>
                    <input type="text" id="certifications" name="certifications" maxlength="255"
                           placeholder="<?= e((string) config('eyewear.defaults.certifications')) ?>"
                           value="<?= e($ed['certifications'] ?? '') ?>">
                    <p class="field__hint">Ngăn bằng dấu phẩy: CE, ISO 12312-1, ANSI Z87.1, CO/CQ.</p>
                </div>
            <?php endif; ?>

            <div class="field field--wide">
                <label for="specs">Thông số — mỗi dòng "Nhãn: giá trị"</label>
                <textarea id="specs" name="specs" rows="4"
                          placeholder="Vật liệu: Titan&#10;Kích thước: 52-18-140"><?= e($edSpecs) ?></textarea>
            </div>

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm sản phẩm' ?></button>
        </form>
    </section>
<?php endif; ?>
