<?php

/**
 * admin/lens-prices/packages.php — RUỘT hộp thoại danh mục gói chiết suất.
 *
 * Controller: Admin/LensPriceAdminController::packages()
 *
 * KHÔNG PHẢI MỘT TRANG. Từ 2026-08-29 danh mục gói là hộp thoại nổi trên bảng
 * giá, đúng bản vẽ "Giá tròng.dc.html": file này là RUỘT hộp, được
 * admin/lens-prices/index.php require vào ngay sau modal-head. Vì thế ở đây
 * không còn tiêu đề trang và không còn nút "+ Thêm gói" của crud-head — đầu
 * hộp đã mang cả nhan đề, số gói, nút "+ Thêm gói" lẫn nút ✕.
 *
 * Form thêm/sửa MỘT gói tách sang _goi-form.php và được index.php dựng như một
 * hộp thoại THỨ HAI, anh em với hộp này chứ không nằm trong nó — xem chú thích
 * ở chỗ gọi.
 *
 * $pkgRows là bản THÔ đọc thẳng từ `lens_packages` (có `description`,
 * `sort_order`), KHÁC $packages mà bảng giá dùng — LensModel::packages() đổi
 * tên khoá `description` thành `desc`. Hai tên vì hai hình dạng.
 */
?>
<?php /* SỐ GÓI VÀ NÚT "+ Thêm gói" KHÔNG Ở ĐÂY. Cả hai nằm trên nhan đề hộp —
         số gói thành dòng phụ, nút thành nút chính của modal-head — đúng bản
         vẽ "Giá tròng.dc.html". Xem chỗ gọi modal-head trong index.php.

         Bản trước dựng chúng thành một thanh riêng ở đầu ruột hộp vì modal-head
         chưa nhận nút phụ; nay nó nhận ($nutUrl/$nutNhan), nên thanh ấy chỉ còn
         là một dòng lặp lại thứ ở ngay phía trên. */ ?>
<div class="anote">
    <p>
        Đây là <strong>danh mục</strong>: mã, tên và mô tả. Còn <strong>giá</strong> thì
        nằm ở <a href="/quan-tri/gia-trong" data-modal-close>bảng giá tròng</a> — mỗi gói
        có một giá riêng cho từng loại tròng, nên không có ô giá nào trong hộp này.
    </p>
    <p>
        Thêm một gói ở đây là bảng giá mọc thêm một hàng, và khách thấy thêm một lựa chọn
        ở hộp mua hàng ngay lập tức. <strong>Hàng mới chưa có giá</strong> nên khách sẽ
        thấy “Báo giá sau khi tư vấn” cho tới khi bạn nhập giá.
    </p>
</div>

<?php if ($pkgRows === []): ?>
    <p class="apanel__empty">Chưa có gói chiết suất nào.</p>
<?php else: ?>
    <div class="atable-wrap">
        <table class="atable atable--full">
            <thead>
                <tr>
                    <th scope="col">Thứ tự</th>
                    <th scope="col">Mã</th>
                    <th scope="col">Tên gói</th>
                    <th scope="col">Mô tả</th>
                    <th scope="col">Đã định giá</th>
                    <?php if ($canEdit): ?>
                        <th scope="col">Thao tác</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php $soGoi = count($pkgRows); ?>
                <?php foreach ($pkgRows as $i => $p): ?>
                    <?php $soGia = $priceCounts[$p['id']] ?? 0; ?>
                    <tr>
                        <?php /* Thứ tự ở đây là thứ tự khách thấy ở bước "Chọn loại
                                 tròng kính", và gói đứng đầu là gói được chọn sẵn —
                                 nên nó quyết định gói nào bán chạy, không phải chuyện
                                 sắp cho gọn mắt. Con số sort_order thô không nói được
                                 điều đó (cả cột có thể cùng bằng 0), hai cái nút thì
                                 nói. */ ?>
                        <td>
                            <?php if ($canEdit): ?>
                                <?php partial('admin/_layout/thu-tu', [
                                    'base' => '/quan-tri/gia-trong/goi/thu-tu',
                                    'id'   => $p['id'],
                                    'dau'  => $i === 0,
                                    'cuoi' => $i === $soGoi - 1,
                                    'ten'  => $p['name'],
                                ]); ?>
                            <?php else: ?>
                                <span class="num"><?= $i + 1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($p['id']) ?></code></td>
                        <td><?= e($p['name']) ?></td>
                        <td>
                            <?php if (($p['description'] ?? '') !== ''): ?>
                                <?= e($p['description']) ?>
                            <?php else: ?>
                                <span class="atable__sub">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php
                            /* MẪU SỐ LÀ SỐ LOẠI TRÒNG CÓ BẢNG GIÁ ($types, tức
                               các CỘT của bảng giá) — theo bản vẽ, viên này đọc
                               "2/3 mức giá" chứ không phải "2 mức giá". Con số
                               trần không nói được là còn thiếu hay đã đủ, mà đó
                               chính là câu hỏi người ta mở hộp này để trả lời:
                               gói nào đang bày cho khách mà chưa định giá xong
                               thì khách chọn đúng nó sẽ thấy "Báo giá sau khi
                               tư vấn".

                               Xanh khi đủ, vàng khi thiếu. Mượn thẳng hai lớp
                               trạng thái kho: cùng cặp màu, cùng nghĩa "xong"
                               với "còn việc" — đặt thêm một cặp lớp riêng chỉ
                               để đổi tên là hai bảng màu phải nhớ giữ cho khớp. */
                            $duGia = $soGia >= $cols;
                            ?>
                            <span class="badge <?= $duGia ? 'badge--in_stock' : 'badge--low_stock' ?>">
                                <?= $duGia ? (int) $soGia . ' mức giá' : (int) $soGia . '/' . $cols . ' mức giá' ?>
                            </span>
                        </td>

                        <?php if ($canEdit): ?>
                            <td class="arow-actions">
                                <a href="/quan-tri/gia-trong/goi?sua=<?= e($p['id']) ?>" data-modal>Sửa</a>

                                <?php
                                /* Câu hỏi lại NÓI RA SỐ MỨC GIÁ SẼ MẤT THEO.
                                   Xoá gói là xoá luôn các ô giá của nó (không có
                                   khoá ngoại nào lo việc đó — xem
                                   LensModel::deletePackage), và một bảng giá đã gõ
                                   tay thì không lấy lại được. "Xoá gói này?" trần
                                   không nói được điều đó.

                                   Cùng một biến sinh ra cả data-confirm lẫn
                                   onsubmit nên hộp thoại của dự án và hộp
                                   confirm() của trình duyệt không thể nói hai câu
                                   khác nhau. */
                                $hoi = $soGia > 0
                                    ? sprintf('Xoá gói “%s”? %d mức giá đã nhập cho gói này cũng mất theo.', $p['name'], $soGia)
                                    : sprintf('Xoá gói “%s”?', $p['name']);
                                ?>
                                <form method="post" action="/quan-tri/gia-trong/goi/xoa"
                                      data-confirm="<?= e($hoi) ?>"
                                      data-confirm-title="Xoá gói chiết suất?"
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
<?php endif; ?>

