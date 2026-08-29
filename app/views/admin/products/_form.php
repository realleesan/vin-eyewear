<?php

/**
 * admin/products/_form.php — RUỘT hộp thoại thêm/sửa sản phẩm, sáu tab.
 *
 * Dựng lại 2026-08-29 theo "Quản lý sản phẩm.dc.html". Bản cũ là một cột dài
 * 45 ô trôi tuột qua ba màn hình cuộn; bản vẽ chia làm sáu tab: Thông tin ·
 * Giá & kho · Thuộc tính kính · Biến thể · Hình ảnh · Đơn kính.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SÁU TAB CHẠY BẰNG CSS, KHÔNG BẰNG JAVASCRIPT
 *
 * Sáu ô radio ẩn đứng ngay đầu <form>, sáu <label> là mặt tab, và admin.css
 * dùng `#apf-t2:checked ~ … .apf__pane--t2 { display:block }` để chọn tấm nào
 * hiện. Không có dòng JS nào dính vào.
 *
 * Vì sao không phải nút JS: tắt JavaScript thì năm trong sáu tab biến mất và
 * form chỉ còn nhập được một phần sáu — vi phạm thẳng quy ước "JS chỉ là tăng
 * cường" của dự án.
 *
 * Vì sao không phải sáu địa chỉ (?sua=<id>&tab=gia-kho): mỗi lần đổi tab là
 * một lượt tải, và mọi thứ vừa gõ ở tab trước chưa lưu sẽ mất trắng. Cách này
 * giữ CẢ SÁU TAB trong cùng một <form> — người dùng điền tab nào trước cũng
 * được, bấm Lưu một lần là gửi hết.
 *
 * Sáu ô radio ấy CÓ bị gửi lên cùng form (name="apf_tab"); save() không đọc
 * tới nên chúng rơi vào im lặng. Đổi lấy việc không cần JS thì rẻ.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TÊN Ô = TÊN CỘT
 *
 * Mọi name="…" trùng đúng tên cột trong `products`, trừ ba nhóm ô tick gửi
 * dạng mảng (face_shapes[], lens_types[], lens_indexes[]) và lưới biến thể
 * (variant_*[]). Nếp này để đọc save() là biết ngay ô nào đi đâu, không phải
 * tra một bảng ánh xạ.
 *
 * Biến cần có: $ed (bản ghi đang sửa hoặc null), $categories, $collections,
 * $variants, $edImages, $edAlts, $brands.
 */

$ky = static fn (string $cot, string $macDinh = ''): string
    => (string) ($ed[$cot] ?? $macDinh);

/* Ô tick đọc từ cột 0/1. Sản phẩm MỚI thì mặc định do đối số quyết định — chỉ
   "Hiện" là bật sẵn, còn lại tắt: một ô tick bật sẵn mà người nhập không để ý
   là một lời khẳng định họ chưa từng đưa ra. */
$tick = static function (string $cot, bool $macDinh = false) use ($ed): bool {
    return $ed === null ? $macDinh : (bool) ($ed[$cot] ?? 0);
};

/* Cột CSV → mảng khoá, để tô ô tick. Trống hoặc NULL đều ra mảng rỗng. */
$csv = static function (string $cot) use ($ed): array {
    $raw = trim((string) ($ed[$cot] ?? ''));

    return $raw === '' ? [] : array_map('trim', explode(',', $raw));
};

$daChon      = $csv('face_shapes');
$daChonTrong = $csv('lens_types');
$daChonIndex = $csv('lens_indexes');

$eyewear      = config('eyewear');
$dangGocs     = $eyewear['frame_shapes'];
$chatLieus    = $eyewear['frame_materials'];
$kieuVienList = $eyewear['rim_types'];
$gioiTinhs    = $eyewear['genders'];
$cos          = $eyewear['size_classes'];
$trangThais   = $eyewear['publish_statuses'];
$capTrongs    = $eyewear['lens_categories'];
$loaiTrongs   = $eyewear['rx_lens_types'];
$chietSuats   = $eyewear['rx_indexes'];

/* Bốn dáng mặt bản vẽ hỏi. config/eyewear.php còn ba khoá nữa (chữ nhật, tam
   giác, mặt dài) — dữ liệu cũ mang chúng vẫn đọc được ở trang bán hàng, form
   chỉ thôi đề nghị. */
$dangMats = ['tron', 'vuong', 'trai-xoan', 'tim'];

/*
 * ĐỔ <option> CHO MỘT Ô CHỌN, GIỮ ĐƯỢC GIÁ TRỊ CŨ KHÔNG KHỚP KHOÁ.
 *
 * Từ 2026-08-29 mấy cột này lưu KHOÁ ('titanium') chứ không lưu nhãn hiển thị
 * ('Titanium') — xem khối chú thích trong config/eyewear.php. Nhưng dòng nhập
 * trước ngày đó có thể đang giữ nguyên văn thứ người ta gõ.
 *
 * Không xử lý thì thành mất dữ liệu ÂM THẦM: mở hộp ra thấy "— Chọn —" vì
 * không khớp option nào, bấm Lưu vì lý do khác hẳn (sửa giá chẳng hạn), và
 * chất liệu gọng bị ghi thành NULL mà không có gì báo.
 *
 * Nên: giá trị lạ được thêm vào làm một option riêng, có nhãn nói rõ nó là
 * giá trị cũ. Nó vẫn được chọn sẵn, vẫn gửi lên, và save() nhận lại đúng nó
 * (xem tham số $cu của ProductAdminController::khoa). Người nhập thấy nó,
 * chọn lại được, mà không chọn thì cũng không mất.
 */
$doOption = static function (array $bang, string $hienTai) {
    foreach ($bang as $ma => $nhan) {
        printf(
            '<option value="%s"%s>%s</option>',
            e((string) $ma),
            (string) $ma === $hienTai ? ' selected' : '',
            e((string) $nhan)
        );
    }

    if ($hienTai !== '' && !isset($bang[$hienTai])) {
        printf('<option value="%s" selected>%s (giá trị cũ)</option>',
            e($hienTai), e($hienTai));
    }
};

/* Trạng thái xuất bản của bản ghi cũ.
   Cột `publish_status` ra đời 2026-08-29; dòng nhập trước đó không có nó, nên
   suy ngược từ `is_visible` thay vì mặc định "Hiện" cho cả hàng đang ẩn. */
$trangThai = $ky('publish_status') !== ''
    ? $ky('publish_status')
    : (($ed !== null && !$ed['is_visible']) ? 'hidden' : 'visible');

/* Ba dòng biến thể trống luôn có sẵn — thêm dòng là việc của JS, mà không có
   JS thì vẫn phải nhập được vài biến thể trong một lần lưu. Ba là con số vừa:
   đủ cho "đen / nâu / trong", không dài tới mức tấm nào cũng đầy ô rỗng. */
$soDongTrong = 3;
?>

<?php /* enctype BẮT BUỘC: thiếu nó thì trình duyệt gửi mỗi TÊN file dưới dạng
         text, $_FILES rỗng, và form "chạy" mà không ảnh nào lên — không có lỗi
         nào để lần ra. */ ?>
<form method="post" action="/quan-tri/san-pham/luu" class="apf" id="product-form"
      enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= e($ky('id')) ?>">

    <?php
    /* Sáu ô radio điều khiển sáu tấm — xem khối chú thích đầu file.
       Chúng phải đứng TRƯỚC .apf__tabs và .apf__body vì bộ chọn CSS là `~`
       (anh em phía sau), không có bộ chọn nào đi ngược lên. */
    $tabs = [
        't1' => 'Thông tin',
        't2' => 'Giá & kho',
        't3' => 'Thuộc tính kính',
        't4' => 'Biến thể',
        't5' => 'Hình ảnh',
        't6' => 'Đơn kính',
    ];
    ?>
    <?php foreach (array_keys($tabs) as $i => $ma): ?>
        <input type="radio" name="apf_tab" id="apf-<?= e($ma) ?>" class="apf__radio"
               <?= $i === 0 ? 'checked' : '' ?>>
    <?php endforeach; ?>

    <div class="apf__tabs" role="tablist">
        <?php foreach ($tabs as $ma => $nhan): ?>
            <label class="apf__tab" for="apf-<?= e($ma) ?>"><?= e($nhan) ?></label>
        <?php endforeach; ?>
    </div>

    <div class="apf__body">

        <!-- ══ TAB 1 · THÔNG TIN ══════════════════════════════════════════ -->
        <div class="apf__pane apf__pane--t1">
            <div class="apf__row apf__row--2-1">
                <div class="field">
                    <label for="name">Tên sản phẩm *</label>
                    <input type="text" id="name" name="name" required maxlength="255"
                           value="<?= e($ky('name')) ?>">
                </div>

                <div class="field">
                    <label for="publish_status">Trạng thái</label>
                    <select id="publish_status" name="publish_status">
                        <?php $doOption($trangThais, $trangThai); ?>
                    </select>
                </div>
            </div>

            <div class="apf__row apf__row--3">
                <div class="field">
                    <label for="sku">Mã SKU *</label>
                    <?php /* .apf__pair: chừa chỗ cho nút "Tự sinh" mà
                             admin-product-form.js chèn vào. Không có JS thì ô
                             nhập chiếm trọn chiều ngang và người dùng tự gõ —
                             quy tắc đặt mã ghi ngay dưới. */ ?>
                    <div class="apf__pair" data-sku-pair>
                        <input type="text" id="sku" name="sku" required maxlength="64"
                               class="amono" value="<?= e($ky('sku')) ?>">
                    </div>
                    <p class="field__hint">
                        Quy tắc: THƯƠNG HIỆU-MẪU-MÀU-SIZE, ví dụ RAYB-2140-BLK-52.
                    </p>
                </div>

                <div class="field">
                    <label for="slug">Slug URL</label>
                    <input type="text" id="slug" name="slug" maxlength="160"
                           value="<?= e($ky('slug')) ?>">
                    <p class="field__hint">Tự sinh từ tên, sửa được.</p>
                </div>

                <div class="field">
                    <label for="brand">Thương hiệu *</label>
                    <?php /* Ô NHẬP KÈM GỢI Ý, không phải <select> như bản vẽ.

                             Bản vẽ vẽ một select đổ từ danh sách thương hiệu có
                             sẵn. Nhưng danh sách ấy chỉ có thể dựng từ chính
                             cột `brand` của những sản phẩm đã nhập — nên thương
                             hiệu ĐẦU TIÊN của một hãng mới sẽ không có trong
                             danh sách, và select thì không cho gõ thêm. Lần
                             nhập hàng của hãng mới sẽ tắc, mà đó là lúc người
                             ta cần nhập nhất.

                             <input list> giữ được cả hai: bấm ra danh sách như
                             select, gõ thẳng vẫn được. */ ?>
                    <input type="text" id="brand" name="brand" maxlength="120"
                           list="apf-brands" value="<?= e($ky('brand')) ?>"
                           placeholder="— Chọn hoặc gõ thương hiệu —">
                    <datalist id="apf-brands">
                        <?php foreach ($brands as $th): ?>
                            <option value="<?= e($th) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="apf__row apf__row--3">
                <div class="field">
                    <label for="category_id">Danh mục *</label>
                    <select id="category_id" name="category_id">
                        <option value="">— Chọn danh mục —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= e($c['id']) ?>"
                                <?= $ky('category_id') === (string) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field__hint">
                        Danh sách lấy từ mục <strong>Danh mục</strong> — thêm/sửa ở đó.
                    </p>
                </div>

                <div class="field">
                    <label for="collection">Bộ sưu tập</label>
                    <select id="collection" name="collection">
                        <option value="">— Không thuộc bộ nào —</option>
                        <?php foreach ($collections as $bst): ?>
                            <option value="<?= e($bst['slug']) ?>"
                                <?= $ky('collection') === $bst['slug'] ? 'selected' : '' ?>>
                                <?= e($bst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field__hint">
                        Danh sách lấy từ mục <strong>Bộ sưu tập</strong> — thêm/sửa ở đó.
                    </p>
                </div>

                <div class="field">
                    <label for="tags">Tags</label>
                    <input type="text" id="tags" name="tags" maxlength="255"
                           placeholder="bestseller, mùa hè, quà tặng"
                           value="<?= e($ky('tags')) ?>">
                    <p class="field__hint">Ngăn bằng dấu phẩy.</p>
                </div>
            </div>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="is_featured" value="1"
                           <?= $tick('is_featured') ? 'checked' : '' ?>>
                    Đánh dấu sản phẩm nổi bật
                </label>
            </div>

            <div class="field">
                <label for="description_short">Mô tả ngắn</label>
                <textarea id="description_short" name="description_short" rows="2"
                          maxlength="500"><?= e($ky('description_short')) ?></textarea>
                <p class="field__hint">Một hai câu, hiện trên thẻ sản phẩm ngoài danh sách.</p>
            </div>

            <div class="field">
                <label for="description">Mô tả chi tiết</label>
                <?php /* Ô SOẠN THẢO THƯỜNG, KHÔNG PHẢI RICH TEXT.

                         Bản vẽ có thanh B / I / U / danh sách trên một vùng
                         contentEditable. Tôi cố ý không làm, vì hai lý do độc
                         lập nhau — sửa được một cái vẫn còn cái kia:

                         1. Dự án không có template engine tự escape; mọi thứ in
                            ra HTML đều đi qua e() (quy ước bắt buộc trong
                            CLAUDE.md). Muốn đoạn mô tả hiện ra ĐẬM chứ không
                            hiện ra chữ "<b>" thì trang bán hàng phải in thô —
                            và lúc đó cột này thành một lỗ XSS, ai sửa được sản
                            phẩm là chạy được script trên trình duyệt khách. Lọc
                            HTML cho an toàn cần một thư viện, mà dự án không có
                            phụ thuộc ngoài nào.

                         2. contentEditable là JS thuần. Tắt JS thì ô mô tả biến
                            mất hẳn — không phải xấu đi, mà là không nhập được.

                         Xuống dòng vẫn giữ nguyên khi hiện ra trang. */ ?>
                <textarea id="description" name="description" rows="6"><?= e($ky('description')) ?></textarea>
                <p class="field__hint">
                    Chất liệu, cảm giác đeo, hợp với ai. Xuống dòng được giữ nguyên.
                </p>
            </div>
        </div>

        <!-- ══ TAB 2 · GIÁ & KHO ══════════════════════════════════════════ -->
        <div class="apf__pane apf__pane--t2">
            <div class="apf__row apf__row--3">
                <div class="field">
                    <label for="compare_at_price">Giá gốc (₫)</label>
                    <input type="number" id="compare_at_price" name="compare_at_price"
                           min="0" step="1000" value="<?= e($ky('compare_at_price')) ?>">
                    <p class="field__hint">Giá gạch ngang. Phải cao hơn giá bán, hoặc để trống.</p>
                </div>

                <div class="field">
                    <label for="price">Giá bán (₫) *</label>
                    <input type="number" id="price" name="price" required min="0" step="1000"
                           value="<?= e($ky('price', '0')) ?>">
                </div>

                <div class="field">
                    <label for="cost_price">Giá vốn (₫)</label>
                    <input type="number" id="cost_price" name="cost_price" min="0" step="1000"
                           value="<?= e($ky('cost_price')) ?>">
                    <p class="field__hint">
                        Để tính lợi nhuận — chỉ admin thấy, không hiện ra trang.
                    </p>
                </div>
            </div>

            <div class="apf__row apf__row--3">
                <div class="field">
                    <label for="sale_price">Giá khuyến mãi (₫)</label>
                    <input type="number" id="sale_price" name="sale_price" min="0" step="1000"
                           value="<?= e($ky('sale_price')) ?>">
                </div>

                <div class="field">
                    <label for="sale_from">Áp dụng từ</label>
                    <input type="date" id="sale_from" name="sale_from"
                           value="<?= e($ky('sale_from')) ?>">
                </div>

                <div class="field">
                    <label for="sale_to">Đến hết ngày</label>
                    <input type="date" id="sale_to" name="sale_to"
                           value="<?= e($ky('sale_to')) ?>">
                </div>
            </div>

            <div class="apf__row apf__row--3">
                <div class="field">
                    <label for="stock_quantity">Số lượng tồn *</label>
                    <input type="number" id="stock_quantity" name="stock_quantity"
                           required min="0" step="1" value="<?= e($ky('stock_quantity', '0')) ?>">
                    <p class="field__hint">Đặt 0 sẽ tự chuyển sang "hết hàng".</p>
                </div>

                <div class="field">
                    <label for="low_stock_at">Ngưỡng cảnh báo hết hàng</label>
                    <input type="number" id="low_stock_at" name="low_stock_at" min="0" step="1"
                           value="<?= e($ky('low_stock_at')) ?>">
                    <p class="field__hint">Tồn dưới mức này sẽ gắn nhãn "Sắp hết".</p>
                </div>

                <?php
                /*
                 * Ở ĐÂY TỪNG CÓ Ô "Cho phép đặt khi hết hàng" — đã gỡ 2026-08-29.
                 *
                 * Ô đó lưu xuống cột `products.allow_backorder`, nhưng KHÔNG một
                 * chỗ nào ở trang bán hàng đọc cột ấy: tick hay không tick thì
                 * khách vẫn bị chặn y hệt khi mua quá tồn (kiểm ở CartController
                 * lúc thêm/sửa giỏ và ở OrderModel::place lúc ghi đơn). Nó là một
                 * cái nút hứa suông, và người nhập hàng không có cách nào biết.
                 *
                 * CỘT TRONG CSDL GIỮ NGUYÊN, cố ý: mặt hàng nào đã tick vẫn còn
                 * dấu vết đó, không mất dữ liệu vì một lần dọn giao diện. Việc
                 * ghi cột này ở ProductAdminController cũng đã gỡ — để lại thì
                 * mỗi lần lưu sản phẩm là đặt về 0 cho tất cả.
                 *
                 * Bán đặt trước là một tính năng thật, không phải một ô tick: nó
                 * kéo theo ngày giao dự kiến, tiền cọc và trạng thái đơn riêng.
                 * Làm thì làm đủ, và phải hỏi BA trước.
                 */
                ?>
            </div>
        </div>

        <!-- ══ TAB 3 · THUỘC TÍNH KÍNH ════════════════════════════════════ -->
        <div class="apf__pane apf__pane--t3">
            <p class="apf__lead">
                Đây là các thuộc tính khách dùng để lọc khi mua — điền càng đủ,
                sản phẩm càng dễ được tìm thấy.
            </p>

            <div class="apf__row apf__row--3">
                <div class="field">
                    <label for="material">Chất liệu gọng</label>
                    <select id="material" name="material">
                        <option value="">— Chọn —</option>
                        <?php $doOption($chatLieus, $ky('material')); ?>
                    </select>
                </div>

                <div class="field">
                    <label for="rim_type">Kiểu gọng</label>
                    <select id="rim_type" name="rim_type">
                        <option value="">— Chọn —</option>
                        <?php $doOption($kieuVienList, $ky('rim_type')); ?>
                    </select>
                </div>

                <div class="field">
                    <label for="frame_shape">Hình dáng</label>
                    <select id="frame_shape" name="frame_shape">
                        <option value="">— Chọn —</option>
                        <?php $doOption($dangGocs, $ky('frame_shape')); ?>
                    </select>
                </div>
            </div>

            <div class="apf__row apf__row--3">
                <div class="field">
                    <label for="color">Màu gọng</label>
                    <input type="text" id="color" name="color" maxlength="120"
                           placeholder="Đen nhám" value="<?= e($ky('color')) ?>">
                </div>

                <div class="field">
                    <label for="lens_color">Màu tròng</label>
                    <input type="text" id="lens_color" name="lens_color" maxlength="120"
                           placeholder="Xám khói" value="<?= e($ky('lens_color')) ?>">
                </div>

                <div class="field">
                    <label for="gender">Giới tính</label>
                    <select id="gender" name="gender">
                        <?php $doOption($gioiTinhs, $ky('gender', 'unisex')); ?>
                    </select>
                </div>
            </div>

            <div class="apf__row apf__row--2-1">
                <div class="field">
                    <label for="lens_width_mm">
                        Kích thước *
                        <span class="field__opt">— rộng tròng · cầu kính · dài càng (mm)</span>
                    </label>
                    <?php /* BA Ô SỐ RIÊNG, không phải một ô "52□18-145".

                             Chuỗi ấy là cách in trên càng kính, và cũng là cách
                             người ta đọc nó — nhưng lưu nguyên chuỗi thì không
                             lọc được "gọng rộng tròng dưới 50", câu hỏi thật sự
                             của người mua. Ba cột số tách rời cho phép so sánh
                             bằng SQL; dòng xem trước bên cạnh trả lại cách đọc
                             quen thuộc. */ ?>
                    <div class="apf__size" data-size-preview>
                        <input type="number" id="lens_width_mm" name="lens_width_mm"
                               min="0" max="255" step="1" placeholder="52"
                               value="<?= e($ky('lens_width_mm')) ?>" aria-label="Rộng tròng (mm)">
                        <span class="apf__size-sep">–</span>
                        <input type="number" name="bridge_mm" min="0" max="255" step="1"
                               placeholder="18" value="<?= e($ky('bridge_mm')) ?>"
                               aria-label="Cầu kính (mm)">
                        <span class="apf__size-sep">–</span>
                        <input type="number" name="temple_mm" min="0" max="255" step="1"
                               placeholder="140" value="<?= e($ky('temple_mm')) ?>"
                               aria-label="Dài càng (mm)">
                    </div>
                    <p class="field__hint">Tách 3 ô số riêng để lọc được theo từng thông số.</p>
                </div>

                <div class="apf__row apf__row--2">
                    <div class="field">
                        <label for="size_class">Size tổng quát</label>
                        <select id="size_class" name="size_class">
                        <option value="">—</option>
                        <?php $doOption($cos, $ky('size_class')); ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="weight_g">Trọng lượng (g)</label>
                        <input type="number" id="weight_g" name="weight_g" min="0" max="65535"
                               step="1" value="<?= e($ky('weight_g')) ?>">
                    </div>
                </div>
            </div>

            <fieldset class="apf__set">
                <legend>Dáng mặt phù hợp <span class="field__opt">(chọn nhiều)</span></legend>
                <div class="apf__ticks">
                    <?php foreach ($dangMats as $ma): ?>
                        <label>
                            <input type="checkbox" name="face_shapes[]" value="<?= e($ma) ?>"
                                   <?= in_array($ma, $daChon, true) ? 'checked' : '' ?>>
                            <?= e($eyewear['face_shapes'][$ma]) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="field field--check">
                <label>
                    <input type="checkbox" name="rx_ready" value="1"
                           <?= $tick('rx_ready') ? 'checked' : '' ?>>
                    Gọng lắp được tròng cận
                </label>
            </div>

            <fieldset class="apf__set apf__set--sun">
                <legend>Riêng kính râm</legend>
                <div class="apf__ticks">
                    <label>
                        <input type="checkbox" name="is_uv400" value="1"
                               <?= $tick('is_uv400') ? 'checked' : '' ?>>
                        Chống UV400
                    </label>
                    <label>
                        <input type="checkbox" name="is_polarized" value="1"
                               <?= $tick('is_polarized') ? 'checked' : '' ?>>
                        Phân cực (polarized)
                    </label>
                </div>

                <div class="field">
                    <label for="lens_category">Độ đậm tròng</label>
                    <select id="lens_category" name="lens_category">
                        <option value="">— Chưa xác định —</option>
                        <?php $doOption($capTrongs, $ky('lens_category')); ?>
                    </select>
                </div>
            </fieldset>
        </div>

        <!-- ══ TAB 4 · BIẾN THỂ ═══════════════════════════════════════════ -->
        <div class="apf__pane apf__pane--t4">
            <p class="apf__lead">
                Kính thường bán theo màu và size — mỗi biến thể có SKU, giá, tồn kho
                và ảnh riêng. Không nhét nhiều màu vào cùng một sản phẩm đơn.
            </p>

            <?php if ($ed === null): ?>
                <?php /* Biến thể phải có product_id, mà sản phẩm mới thì chưa có
                         id nào cho tới lúc bấm Lưu. Nói thẳng ra ở đây thay vì
                         hiện một lưới nhập rồi lặng lẽ vứt đi khi lưu. */ ?>
                <p class="apf__empty">
                    Lưu sản phẩm trước đã, rồi mở lại để thêm biến thể — mỗi biến thể
                    phải gắn vào một sản phẩm có thật.
                </p>
            <?php else: ?>
                <div class="apf__vgrid" data-variant-grid>
                    <div class="apf__vhead">
                        <span>Màu</span>
                        <span>Size</span>
                        <span>SKU riêng</span>
                        <span>Giá riêng (₫)</span>
                        <span>Tồn</span>
                        <span>Ảnh</span>
                        <span></span>
                    </div>

                    <?php
                    /* Dòng đã có + ba dòng trống. Chỉ số chạy liên tục qua cả
                       hai để save() gom được bằng một vòng lặp duy nhất. */
                    $dong = 0;
                    ?>
                    <?php foreach ($variants as $v): ?>
                        <div class="apf__vrow">
                            <input type="hidden" name="variant_id[<?= $dong ?>]" value="<?= e($v['id']) ?>">
                            <input type="text" name="variant_color[<?= $dong ?>]" maxlength="60"
                                   placeholder="Đen nhám" value="<?= e((string) ($v['color'] ?? $v['label'])) ?>"
                                   aria-label="Màu">
                            <select name="variant_size[<?= $dong ?>]" aria-label="Size">
                                <option value="">—</option>
                                <?php foreach ($cos as $ma => $nhan): ?>
                                    <option value="<?= e($ma) ?>"
                                        <?= (string) ($v['size'] ?? '') === $ma ? 'selected' : '' ?>>
                                        <?= e($nhan) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="variant_sku[<?= $dong ?>]" maxlength="64"
                                   class="amono" value="<?= e((string) ($v['sku'] ?? '')) ?>"
                                   aria-label="SKU riêng">
                            <input type="number" name="variant_price[<?= $dong ?>]" min="0" step="1000"
                                   value="<?= e((string) ($v['price'] ?? '')) ?>" aria-label="Giá riêng">
                            <input type="number" name="variant_stock[<?= $dong ?>]" min="0" step="1"
                                   value="<?= (int) $v['stock_quantity'] ?>" aria-label="Tồn">
                            <input type="file" name="variant_image[<?= $dong ?>]" accept="image/*"
                                   aria-label="Ảnh biến thể">
                            <?php /* Xoá bằng ô tick chứ không bằng nút ✕: nút ✕
                                     phải là JavaScript mới gỡ được dòng khỏi
                                     DOM, còn ô tick thì gửi lên được và save()
                                     tự xoá. Có JS thì admin-product-form.js
                                     biến nó thành nút ✕ đúng như bản vẽ. */ ?>
                            <label class="apf__vdel" title="Đánh dấu để xoá khi lưu">
                                <input type="checkbox" name="variant_del[<?= $dong ?>]" value="1">
                                <span>Xoá</span>
                            </label>
                        </div>
                        <?php $dong++; ?>
                    <?php endforeach; ?>

                    <?php for ($i = 0; $i < $soDongTrong; $i++): ?>
                        <div class="apf__vrow apf__vrow--new">
                            <input type="text" name="variant_color[<?= $dong ?>]" maxlength="60"
                                   placeholder="Đen nhám" aria-label="Màu">
                            <select name="variant_size[<?= $dong ?>]" aria-label="Size">
                                <option value="">—</option>
                                <?php foreach ($cos as $ma => $nhan): ?>
                                    <option value="<?= e($ma) ?>"><?= e($nhan) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="variant_sku[<?= $dong ?>]" maxlength="64"
                                   class="amono" aria-label="SKU riêng">
                            <input type="number" name="variant_price[<?= $dong ?>]" min="0" step="1000"
                                   aria-label="Giá riêng">
                            <input type="number" name="variant_stock[<?= $dong ?>]" min="0" step="1"
                                   placeholder="0" aria-label="Tồn">
                            <input type="file" name="variant_image[<?= $dong ?>]" accept="image/*"
                                   aria-label="Ảnh biến thể">
                            <span></span>
                        </div>
                        <?php $dong++; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($variants === []): ?>
                    <p class="apf__empty">
                        Chưa có biến thể nào. Sản phẩm đang bán như một mặt hàng đơn,
                        dùng tồn kho ở tab "Giá &amp; kho".
                    </p>
                <?php endif; ?>

                <p class="field__hint">
                    Bỏ trống cả dòng thì dòng đó không được tạo. Để trống "Giá riêng"
                    nghĩa là bán đúng giá sản phẩm.
                </p>
            <?php endif; ?>
        </div>

        <!-- ══ TAB 5 · HÌNH ẢNH ═══════════════════════════════════════════ -->
        <div class="apf__pane apf__pane--t5">
            <?php
            /*
             * HAI KHỐI, ĐÚNG BẢN VẼ "Quản lý sản phẩm.dc.html".
             *
             * Bản trước gộp làm một danh sách phẳng kèm nút radio "Ảnh chính",
             * với lý do: ảnh chính KHÔNG phải một cột riêng trong CSDL, nó là
             * ảnh ĐỨNG ĐẦU mảng `images`. Lý do ấy đúng về dữ liệu nhưng không
             * bắt buộc về giao diện — hai khối vẫn là hai LÁT CẮT của cùng một
             * mảng, không cần bịa thêm cột nào:
             *
             *     $anhChinh = images[0]
             *     $boAnh    = images[1..]
             *
             * Nên vẽ đúng bản vẽ mà dữ liệu bên dưới không đổi một chữ.
             */
            $anhChinh = $edImages[0] ?? null;
            $boAnh    = array_slice($edImages, 1);
            ?>

            <div class="apfimg">
                <p class="apfimg__head">Ảnh chính của kính *</p>

                <?php if ($anhChinh === null): ?>
                    <?php /* Vùng thả: `data-apf-drop` là móc cho JS kéo-thả.
                             Không có JS thì nó chỉ là một cái khung có nút chọn
                             file bên trong — vẫn tải ảnh lên được đủ. */ ?>
                    <div class="apfimg__drop" data-apf-drop>
                        <p class="apfimg__drop-title">Kéo thả 1 ảnh vào đây</p>
                        <p class="apfimg__or">hoặc</p>

                        <label class="apfimg__pick">
                            Chọn ảnh từ máy
                            <input type="file" name="image_main_file" accept="image/*">
                        </label>

                        <p class="apfimg__hint">
                            Ảnh giới thiệu kính — hiện to nhất trên trang sản phẩm và ngoài
                            danh sách. Nền sạch, chụp nghiêng nhẹ. JPEG, PNG hoặc WEBP ·
                            tối đa <?= (int) (ProductImageStorage::MAX_BYTES / 1048576) ?> MB.
                        </p>
                    </div>
                <?php else: ?>
                    <?php /* Ô tick đứng TRƯỚC ảnh: CSS dùng bộ chọn anh-em `~`
                             để làm mờ ảnh và đổi nhãn nút khi đã đánh dấu xoá.
                             Xem admin/_layout/image-x.php. */ ?>
                    <div class="apfimg__main">
                        <?php partial('admin/_layout/image-x', [
                            'x_id'      => 'apf-keep-chinh',
                            'x_name'    => 'image_keep[]',
                            'x_value'   => $anhChinh,
                            'x_checked' => true,
                            'x_keep'    => true,
                            'x_label'   => 'Xoá ảnh chính khi lưu',
                        ]); ?>

                        <figure class="apfimg__thumb">
                            <img src="<?= e($anhChinh) ?>" alt="" loading="lazy">
                            <span class="apfimg__badge">Ảnh chính</span>
                        </figure>

                        <div class="apfimg__acts">
                            <label class="apfimg__btn">
                                Đổi ảnh
                                <input type="file" name="image_main_file" accept="image/*">
                            </label>

                            <?php /* "Xoá ảnh" là NHÃN của ô tick ngay trên, không
                                     phải nút JS: bấm nó là bỏ tick "giữ", và ảnh
                                     chỉ thật sự mất khi bấm Lưu. Bấm lần nữa là
                                     hoàn tác. */ ?>
                            <label class="apfimg__btn apfimg__btn--xoa" for="apf-keep-chinh">
                                <span class="apfimg__btn-xoa">Xoá ảnh</span>
                                <span class="apfimg__btn-hoan">Hoàn tác</span>
                            </label>

                            <span class="aimgx__flag">Sẽ xoá khi lưu</span>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="apfimg__head apfimg__head--sep">Bộ ảnh của kính</p>

                <div class="apfimg__drop" data-apf-drop>
                    <p class="apfimg__drop-title">
                        Kéo thả nhiều ảnh vào đây <span class="apfimg__or-in">hoặc</span>
                    </p>

                    <label class="apfimg__pick apfimg__pick--ghost">
                        Chọn ảnh từ máy
                        <input type="file" name="image_files[]" accept="image/*" multiple>
                    </label>

                    <p class="apfimg__hint">
                        Bộ ảnh hiện thành dãy thumbnail dưới ảnh chính: các góc chụp khác và
                        ảnh chi tiết — nghiêng, gấp lại, bản lề, đệm mũi, đeo trên người.
                        Tối đa <?= (int) ProductImageStorage::MAX_FILES ?> ảnh, kéo thả được
                        nhiều ảnh cùng lúc.
                    </p>
                </div>

                <?php if ($boAnh !== []): ?>
                    <div class="apfimg__grid">
                        <?php foreach ($boAnh as $k => $duongDan): ?>
                            <div class="apfimg__cell">
                                <?php partial('admin/_layout/image-x', [
                                    'x_id'      => 'apf-keep-' . $k,
                                    'x_name'    => 'image_keep[]',
                                    'x_value'   => $duongDan,
                                    'x_checked' => true,
                                    'x_keep'    => true,
                                    'x_label'   => 'Xoá ảnh này khi lưu',
                                ]); ?>

                                <div class="apfimg__cell-anh">
                                    <img src="<?= e($duongDan) ?>" alt="" loading="lazy">
                                </div>

                                <?php partial('admin/_layout/image-x-btn', [
                                    'x_id'    => 'apf-keep-' . $k,
                                    'x_label' => 'Xoá ảnh này khi lưu',
                                ]); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php /* ALT TEXT ĐI KÈM NGẦM, KHÔNG CÓ Ô NHẬP.

                         Bản vẽ không vẽ ô alt nào, nên bỏ khỏi giao diện. Nhưng
                         controller lưu cột `image_alts` từ CHÍNH $_POST — không
                         gửi lên là xoá sạch alt của mọi ảnh ngay lần lưu đầu.
                         Mấy ô ẩn này giữ nguyên giá trị đang có, để việc bỏ ô
                         nhập chỉ mất chỗ SỬA chứ không mất DỮ LIỆU. */ ?>
                <?php foreach ($edImages as $duongDan): ?>
                    <?php if (($edAlts[$duongDan] ?? '') !== ''): ?>
                        <input type="hidden" name="image_alts[<?= e($duongDan) ?>]"
                               value="<?= e((string) $edAlts[$duongDan]) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <?php /* Ô này KHÔNG có trong bản vẽ, nhưng giữ lại: cột `video_url`
                     đang có dữ liệu thật và trang sản phẩm đang đọc nó. Bỏ ô đi
                     là người dùng không còn đường sửa một thứ vẫn hiện với
                     khách. Bản vẽ cũng còn khoá `video` trong state của nó,
                     chỉ là không vẽ ra ô nào. */ ?>
            <div class="field">
                <label for="video_url">Ảnh 360 hoặc video <span class="field__opt">(tuỳ chọn)</span></label>
                <input type="url" id="video_url" name="video_url" maxlength="500"
                       placeholder="Dán link YouTube hoặc file 360…"
                       value="<?= e($ky('video_url')) ?>">
            </div>
        </div>

        <!-- ══ TAB 6 · ĐƠN KÍNH ═══════════════════════════════════════════ -->
        <div class="apf__pane apf__pane--t6">
            <div class="field field--check">
                <label>
                    <input type="checkbox" name="rx_order_enabled" value="1"
                           <?= $tick('rx_order_enabled') ? 'checked' : '' ?>>
                    Cho phép đặt kèm tròng theo đơn
                </label>
                <p class="field__hint">
                    Khác ô "Gọng lắp được tròng cận" ở tab Thuộc tính: ô kia nói gọng
                    có lắp được không, ô này nói cửa hàng có nhận đặt cho mẫu này không.
                </p>
            </div>

            <fieldset class="apf__set">
                <legend>Loại tròng áp dụng được</legend>
                <div class="apf__ticks">
                    <?php foreach ($loaiTrongs as $ma => $nhan): ?>
                        <label>
                            <input type="checkbox" name="lens_types[]" value="<?= e($ma) ?>"
                                   <?= in_array($ma, $daChonTrong, true) ? 'checked' : '' ?>>
                            <?= e($nhan) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="apf__set">
                <legend>Chiết suất hỗ trợ</legend>
                <div class="apf__ticks">
                    <?php foreach ($chietSuats as $ma => $nhan): ?>
                        <label>
                            <input type="checkbox" name="lens_indexes[]" value="<?= e($ma) ?>"
                                   <?= in_array($ma, $daChonIndex, true) ? 'checked' : '' ?>>
                            <?= e($nhan) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="apf__row apf__row--2">
                <div class="field">
                    <label for="sph_max">SPH tối đa</label>
                    <input type="text" id="sph_max" name="sph_max" maxlength="10"
                           placeholder="-8.00" value="<?= e($ky('sph_max')) ?>">
                    <p class="field__hint">Độ cận/viễn cao nhất gọng này lắp được.</p>
                </div>

                <div class="field">
                    <label for="cyl_max">CYL tối đa</label>
                    <input type="text" id="cyl_max" name="cyl_max" maxlength="10"
                           placeholder="-4.00" value="<?= e($ky('cyl_max')) ?>">
                    <p class="field__hint">Độ loạn cao nhất.</p>
                </div>
            </div>
        </div>

    </div>
</form>
