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
]); ?>

<form class="asearch" method="get" action="/quan-tri/san-pham">
    <label class="sr-only" for="q">Tìm sản phẩm</label>
    <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Tên, SKU hoặc thương hiệu">
    <button type="submit" class="astatus__save">Tìm</button>
    <?php if ($q !== ''): ?>
        <a href="/quan-tri/san-pham" class="apanel__more">Xoá tìm kiếm</a>
    <?php endif; ?>
</form>

<div class="atable-wrap">
    <table class="atable atable--full">
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
                    <td class="num">
                        <?= money((int) $p['price']) ?>
                        <?php if (!empty($p['compare_at_price'])): ?>
                            <span class="atable__sub"><?= money((int) $p['compare_at_price']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="num<?= (int) $p['stock_quantity'] <= 0 ? ' is-danger' : '' ?>"><?= (int) $p['stock_quantity'] ?></td>
                    <td>
                        <span class="badge badge--<?= e($p['status']) ?>"><?= $p['status'] === 'in_stock' ? 'Còn hàng' : 'Hết hàng' ?></span>
                        <?php if (!$p['is_visible']): ?>
                            <span class="badge badge--cancelled">Đang ẩn</span>
                        <?php endif; ?>
                        <?php if ($p['is_featured']): ?>
                            <span class="badge badge--new">Nổi bật</span>
                        <?php endif; ?>
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

            <?php /* Bộ sưu tập là nội dung THEO MÙA khai ở config/collections.php
                     (khối S09 trang chủ), không phải một bảng trong DB — nên đây
                     là ô CHỌN chứ không phải ô gõ tự do: gõ sai một ký tự thì
                     mặt hàng rơi ra ngoài mọi bộ sưu tập mà không có gì báo.

                     Trước đây cột này chỉ gán được bằng câu UPDATE trong seed;
                     seed sản phẩm đã bỏ nên không còn đường nào khác ngoài ô này. */ ?>
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
                                <?php /* Ảnh cũ hiện to bằng chính nó chứ không phải
                                         bản nhỏ: bản nhỏ chỉ có với ảnh của seed. */ ?>
                                <img class="aimgs__thumb" src="<?= e($path) ?>" alt="" loading="lazy">

                                <label class="aimgs__keep">
                                    <?php /* Mặc định TICK SẴN: mở form ra rồi bấm Lưu mà
                                             không đụng gì thì ảnh phải còn nguyên. Bỏ tick
                                             mới là hành động xoá. */ ?>
                                    <input type="checkbox" name="image_keep[]"
                                           value="<?= e($path) ?>" checked>
                                    Giữ ảnh
                                </label>

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

            <div class="field field--wide">
                <label for="specs">Thông số — mỗi dòng "Nhãn: giá trị"</label>
                <textarea id="specs" name="specs" rows="4"
                          placeholder="Vật liệu: Titan&#10;Kích thước: 52-18-140"><?= e($edSpecs) ?></textarea>
            </div>

            <button type="submit" class="astatus__save"><?= $ed !== null ? 'Lưu thay đổi' : 'Thêm sản phẩm' ?></button>
        </form>
    </section>
<?php endif; ?>
