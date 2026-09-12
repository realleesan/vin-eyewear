<?php

/**
 * admin/variants/index.php — biến thể sản phẩm
 *
 * Chọn mặt hàng ở ô trên cùng, bảng và form bên dưới đi theo mặt hàng đó.
 */

$ed   = $editing;
$base = '/quan-tri/bien-the';
?>

<header class="ahead ahead--row">
    <div>
        <h1 class="ahead__title">Biến thể sản phẩm</h1>
        <p class="ahead__lead">
            Phương án khách chọn khi mua — chiết suất tròng, màu gọng…
        </p>
    </div>

    <?php if ($canEdit): ?>
        <?php /* Chỉ có nút khi ĐÃ chọn được một mặt hàng: phương án luôn thuộc
                 về một sản phẩm cụ thể (product_id NOT NULL), nên "thêm phương
                 án" lúc chưa có sản phẩm nào là một cái nút không gắn vào đâu. */ ?>
        <?php if ($product !== null): ?>
            <a href="<?= e($base) ?>?sp=<?= e($product['id']) ?>&amp;them=1"
               class="astatus__save" data-modal>+ Thêm phương án</a>
        <?php endif; ?>
    <?php else: ?>
        <p class="ahead__note">Bạn chỉ có quyền xem. Cần quyền quản lý để chỉnh sửa.</p>
    <?php endif; ?>
</header>

<!-- Đổi mặt hàng bằng GET: không có JS thì vẫn còn nút "Xem". admin.js gửi
     form ngay khi đổi ô chọn, giống ô lọc trạng thái ở các trang khác.

     Ô TÌM VÀ Ô CHỌN ĐI CHUNG MỘT FORM. Gõ vào ô tìm rồi Enter là lọc lại danh
     sách trong ô chọn; ô chọn vẫn giữ mặt hàng đang xem (controller ghim nó
     vào đầu danh sách) nên lọc không làm đổi bảng bên dưới. Hai form riêng thì
     tìm xong là mất mặt hàng đang mở. -->
<form class="aform" method="get" action="<?= e($base) ?>">
    <div class="aform__grid">
        <div class="field">
            <label for="sp">Sản phẩm</label>
            <select id="sp" name="sp" data-autosubmit>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['id']) ?>"
                            <?= ($product['id'] ?? '') === $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?> (<?= e($p['sku']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <?php /* NÓI THẲNG KHI DANH SÁCH BỊ CẮT. Một ô chọn có đúng 50 dòng
                     trong khi cửa hàng có 312 mặt hàng thì trông y như đủ —
                     người dùng cuộn hết mà không thấy mặt hàng mình cần sẽ kết
                     luận là nó chưa được tạo. */ ?>
            <?php if ($tongSp > count($products)): ?>
                <p class="field__hint">
                    Đang hiện <?= count($products) ?> / <?= (int) $tongSp ?> mặt hàng
                    <?= $tim !== '' ? 'khớp từ khoá' : '' ?> — gõ vào ô tìm để thu hẹp.
                </p>
            <?php elseif ($tim !== ''): ?>
                <p class="field__hint">
                    <?= (int) $tongSp ?> mặt hàng khớp “<?= e($tim) ?>”<?php
                    if ($daGhim): ?>, cộng mặt hàng đang xem<?php
                    endif; ?>.
                </p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="sp-tim">Tìm mặt hàng</label>
            <input type="search" id="sp-tim" name="tim" value="<?= e($tim) ?>"
                   placeholder="Tên, mã SKU hoặc thương hiệu">
            <p class="field__hint">Gõ rồi Enter. Để trống là xem <?= (int) $tranChon ?> mặt hàng đầu theo tên.</p>
        </div>

        <button type="submit" class="astatus__save astatus__save--ghost">Xem</button>

        <?php if ($tim !== ''): ?>
            <a class="apanel__more"
               href="<?= e($base) ?><?= $product !== null ? '?sp=' . e($product['id']) : '' ?>">Xoá tìm</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($product === null): ?>
    <?php /* Phân biệt hai cảnh KHÁC HẲN nhau mà bản cũ nói chung một câu: cửa
             hàng chưa có mặt hàng nào, và ô tìm không khớp gì. Câu "Chưa có sản
             phẩm nào" cho cảnh thứ hai là sai và làm người dùng đi tạo lại một
             mặt hàng đã có. */ ?>
    <?php if ($tim !== ''): ?>
        <p class="ahead__note">Không có mặt hàng nào khớp “<?= e($tim) ?>”.</p>
    <?php else: ?>
        <p class="ahead__note">Chưa có sản phẩm nào.</p>
    <?php endif; ?>
<?php else: ?>

    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Phương án</th>
                    <th scope="col">Màu</th>
                    <th scope="col">Ghi chú</th>
                    <th scope="col">Chênh giá</th>
                    <th scope="col">Giá bán</th>
                    <th scope="col">Kho online</th>
                    <th scope="col">Thứ tự</th>
                    <th scope="col">Trạng thái</th>
                    <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($variants === []): ?>
                    <tr>
                        <td colspan="<?= $canEdit ? 9 : 8 ?>">
                            Mặt hàng này chưa có phương án nào — nó được bán như một sản phẩm đơn,
                            dùng kho online <?= (int) $product['stock_quantity'] ?> của chính nó.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($variants as $v): ?>
                    <?php $delta = (int) $v['price_delta']; ?>
                    <tr>
                        <td><code><?= e($v['label']) ?></code></td>

                        <?php /* IN RA ĐÚNG CHẤM MÀ KHÁCH SẼ THẤY, không phải mã
                                 trong CSDL — kể cả khi mã ấy để trống và hệ thống
                                 tự suy từ tên màu. Đây là cách duy nhất người
                                 nhập hàng biết chữ mình vừa gõ có được nhận ra
                                 hay không, trước khi ra xem trang bán hàng. */ ?>
                        <?php
                        $btMa = trim((string) ($v['swatch_hex'] ?? ''));
                        $btTu = false;

                        if ($btMa === '' || !preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $btMa)) {
                            $btMa = (string) ProductTaxonomy::colorHex((string) ($v['color'] ?? ''));
                            $btTu = $btMa !== '';
                        }
                        ?>
                        <td class="avcolor">
                            <?php if ($btMa !== ''): ?>
                                <span class="avdot" style="background: <?= e($btMa) ?>"
                                      title="<?= e($btTu ? 'Tự suy từ tên màu' : 'Mã màu tự đặt') ?>"></span>
                            <?php endif; ?>
                            <?php if (trim((string) ($v['color'] ?? '')) !== ''): ?>
                                <?= e($v['color']) ?>
                                <?php if ($btMa === ''): ?>
                                    <?php /* Gõ màu rồi mà không ra chấm = chữ ấy không
                                             khớp màu chuẩn nào. Nói ngay tại dòng, kèm
                                             cách chữa — dùng .badge--neutral như mọi
                                             nhãn trung tính khác của khu quản trị. */ ?>
                                    <span class="badge badge--neutral"
                                          title="Không khớp màu chuẩn nào — điền Mã màu để tự đặt">chưa có chấm</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="num">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="atable__msg"><?= e($v['note'] ?? '—') ?></td>
                        <td><?= $delta === 0 ? '—' : ($delta > 0 ? '+' : '−') . money(abs($delta)) ?></td>
                        <td><?= money(VariantModel::priceOf($product, $v)) ?></td>
                        <td><?= (int) $v['stock_quantity'] ?></td>
                        <td><?= (int) $v['position'] ?></td>
                        <td>
                            <span class="badge badge--<?= $v['is_active'] ? 'in_stock' : 'cancelled' ?>">
                                <?= $v['is_active'] ? 'Đang bán' : 'Đã tắt' ?>
                            </span>
                        </td>
                        <?php if ($canEdit): ?>
                            <td class="arow-actions">
                                <a href="<?= e($base) ?>?sp=<?= e($product['id']) ?>&amp;sua=<?= e($v['id']) ?>"
                                   data-modal>Sửa</a>
                                <?php $hoi = sprintf('Xoá phương án “%s”?', $v['label']); ?>
                                <form method="post" action="<?= e($base) ?>/xoa"
                                      data-confirm="<?= e($hoi) ?>"
                                      data-confirm-title="Xoá phương án?"
                                      data-confirm-ok="Xoá"
                                      onsubmit="return confirm('<?= e($hoi) ?>')">
                                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= e($v['id']) ?>">
                                    <button type="submit" class="arow-del">Xoá</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    /*
     * FORM THÊM/SỬA LÀ MỘT HỘP THOẠI NỔI — cùng lối với mọi màn CRUD khác của
     * khu quản trị (xem .amodal trong admin.css).
     *
     * Màn này KHÔNG có bản thiết kế riêng: nó đã bị gỡ khỏi thanh bên và chỉ
     * còn vào được bằng địa chỉ. Nhưng nó vẫn dùng chung mọi thành phần với các
     * màn kia, nên để lại một cái form dán cuối trang thì nó là chỗ DUY NHẤT
     * trong khu quản trị còn theo lối cũ — và người mở nó ra sẽ tưởng mình vừa
     * lạc sang một phần chưa làm xong.
     *
     * Địa chỉ đóng phải giữ ?sp=<id>: bỏ nó đi là bảng nhảy về mặt hàng đầu
     * tiên trong danh sách.
     */
    $moHop   = $canEdit && ($ed !== null || isset($_GET['them']));
    $dongUrl = $base . '?sp=' . rawurlencode((string) $product['id']);
    ?>
    <?php if ($moHop): ?>
        <?php partial('admin/_layout/modal-head', [
            'tieuDe'  => $ed !== null ? 'Sửa phương án' : 'Thêm phương án',
            'phu'     => $ed !== null ? $ed['label'] : $product['name'],
            'dongUrl' => $dongUrl,
            'rong'    => 'sm',
        ]); ?>

            <form method="post" action="<?= e($base) ?>/luu" class="aform__grid" id="bt-form">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">

                <div class="field">
                    <label for="label">Nhãn *</label>
                    <input type="text" id="label" name="label" required maxlength="60"
                           placeholder="1.61" value="<?= e($ed['label'] ?? '') ?>">
                    <p class="field__hint">Chữ in trên nút chọn ở trang sản phẩm.</p>
                </div>

                <div class="field">
                    <label for="note">Ghi chú</label>
                    <input type="text" id="note" name="note" maxlength="120"
                           placeholder="Cận 3.00 – 6.00" value="<?= e($ed['note'] ?? '') ?>">
                    <p class="field__hint">Dòng nhỏ dưới nhãn, giúp khách chọn đúng.</p>
                </div>

                <?php /* ┌─ Ô "MÀU" — THÊM 12/09/2026 ─────────────────────────
                         │ Cột `product_variants.color` có từ đầu nhưng form này
                         │ CHƯA BAO GIỜ ghi vào nó. Hậu quả kéo theo hai chỗ,
                         │ cả hai đều im lặng:
                         │
                         │   · thẻ sản phẩm không có chấm màu nào (nó đọc màu
                         │     từ đây);
                         │   · bộ lọc "Màu gọng" không có dữ liệu, phải lùi về
                         │     cột products.color của cả mặt hàng.
                         │
                         │ ⚠ Đây là ô quyết định cả hai thứ đó. Gõ tên màu bình
                         │ thường ("Đen", "Nâu havana") — KHÔNG phải mã hex. */ ?>
                <div class="field">
                    <label for="color">Màu <span class="field__opt">(chỉ phương án màu)</span></label>
                    <input type="text" id="color" name="color" maxlength="60"
                           placeholder="Đen / Nâu havana / Vàng gold"
                           value="<?= e($ed['color'] ?? '') ?>">
                    <p class="field__hint">
                        Chữ này vừa là tên màu khách đọc trên thẻ sản phẩm, vừa là thứ
                        bộ lọc “Màu gọng” gom nhóm. Phương án chiết suất tròng hay cỡ
                        gọng thì bỏ trống.
                    </p>
                </div>

                <?php if ($hasSwatch): ?>
                    <?php /* MÃ MÀU LÀ Ô ĐÈ, KHÔNG PHẢI Ô BẮT BUỘC.

                             Bỏ trống thì chấm màu vẫn hiện: hệ thống suy mã ra từ
                             tên màu ở ô trên (ProductTaxonomy::colorHex — mười lăm
                             màu chuẩn kèm đồng nghĩa). Chỉ điền khi phối màu thật
                             khác hẳn màu chuẩn, ví dụ nâu havana vân đồi mồi.

                             Hai ô cùng một tên `swatch_hex` là CỐ Ý: ô chọn màu của
                             trình duyệt cho bấm nhanh, ô chữ cho dán mã và cho XOÁ
                             (ô màu không có trạng thái "trống", nó luôn trả về một
                             mã). Ô chữ đứng SAU nên khi cả hai cùng gửi, giá trị
                             của nó thắng — PHP lấy giá trị cuối cùng của một tên.
                             Đừng đảo thứ tự: đảo là không bao giờ xoá mã đi được. */ ?>
                    <div class="field">
                        <label for="swatch_hex">Mã màu <span class="field__opt">(bỏ trống = suy từ tên màu)</span></label>
                        <div class="afrm-swatch">
                            <input type="color" id="swatch_hex_pick" name="swatch_hex"
                                   aria-label="Chọn mã màu"
                                   value="<?= e($ed['swatch_hex'] ?? '#888888') ?>">
                            <input type="text" id="swatch_hex" name="swatch_hex" maxlength="7"
                                   placeholder="bỏ trống để tự suy" value="<?= e($ed['swatch_hex'] ?? '') ?>">
                        </div>
                        <p class="field__hint">
                            Dạng <code>#rrggbb</code>. Sai dạng thì bị bỏ khi lưu. Chỉ cần
                            điền khi phối màu khác hẳn màu chuẩn — còn lại cứ để trống,
                            chấm màu vẫn hiện đúng theo tên màu ở ô trên.
                        </p>
                    </div>

                    <div class="field">
                        <label for="image">Ảnh phối màu <span class="field__opt">(đường dẫn)</span></label>
                        <input type="text" id="image" name="image" maxlength="500"
                               placeholder="/assets/images/…" value="<?= e($ed['image'] ?? '') ?>">
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="price_delta">Chênh giá (₫)</label>
                    <input type="number" id="price_delta" name="price_delta" step="1000"
                           value="<?= e($ed['price_delta'] ?? '0') ?>">
                    <p class="field__hint">
                        So với giá gốc <?= money((int) $product['price']) ?>.
                        Số âm = rẻ hơn. 0 = đúng giá gốc.
                    </p>
                </div>

                <div class="field">
                    <label for="stock_quantity">Kho online</label>
                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" step="1"
                           value="<?= e($ed['stock_quantity'] ?? '0') ?>">
                    <p class="field__hint">Riêng cho phương án này, không phải kho chung của sản phẩm.</p>
                </div>

                <div class="field">
                    <label for="position">Thứ tự</label>
                    <input type="number" id="position" name="position" min="0" step="1"
                           value="<?= e($ed['position'] ?? '0') ?>">
                    <p class="field__hint">Số nhỏ hiện trước.</p>
                </div>

                <div class="field field--check">
                    <label>
                        <input type="checkbox" name="is_active"
                               <?= ($ed === null || $ed['is_active']) ? 'checked' : '' ?>>
                        Đang bán
                    </label>
                </div>

            </form>

        <?php partial('admin/_layout/modal-foot', [
            'dongUrl' => $dongUrl,
            'luuNhan' => $ed !== null ? 'Lưu thay đổi' : 'Thêm phương án',
            'luuForm' => 'bt-form',
        ]); ?>
    <?php endif; ?>
<?php endif; ?>
