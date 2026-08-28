<?php

/**
 * admin/categories/index.php — danh mục
 * Port từ quan-tri/danh-muc.tsx + admin-category-form.tsx.
 */

$ed = $editing;
?>
<?php partial('admin/_layout/crud-head', [
    'title' => 'Danh mục',
    /* Dòng dẫn nói ra Ý NGHĨA CỦA CỘT THỨ TỰ — theo bản thiết kế. Con số ở cột
       ấy trông như một mã nội bộ; thật ra nó quyết định thứ tự các mục trên
       menu trang bán hàng, và đó là thứ duy nhất trên bảng này mà khách hàng
       nhìn thấy. Không nói ra thì không ai đoán được. */
    'lead' => count($categories) . ' danh mục · thứ tự trên bảng chính là thứ tự hiện trên menu trang bán hàng',
    'base' => '/quan-tri/danh-muc', 'canEdit' => $canEdit, 'editing' => $ed,
    'addLabel' => '+ Thêm danh mục',
]); ?>

<div class="atable-wrap">
    <table class="atable admtable">
        <thead>
            <tr>
                <th scope="col">Thứ tự</th>
                <th scope="col">Tên</th>
                <th scope="col">Slug</th>
                <th scope="col">Mô tả</th>
                <th scope="col">Sản phẩm</th>
                <th scope="col">Hiển thị</th>
                <?php if ($canEdit): ?><th scope="col">Thao tác</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php $soDong = count($categories); ?>
            <?php foreach ($categories as $i => $c): ?>
                <tr>
                    <?php /* CỘT THỨ TỰ LÀ HAI CÁI NÚT, không phải con số.

                             Con số `sort_order` không nói được gì cho người
                             đọc: nó có thể là 0 ở cả bốn dòng (giá trị mặc
                             định của cột) trong khi bảng vẫn đang sắp theo
                             tên. Mà thứ người ta muốn làm với cột này không
                             phải đọc — là ĐỔI. Hai cái nút trả lời đúng nhu
                             cầu ấy và bỏ luôn một con số gây hiểu nhầm. */ ?>
                    <td>
                        <?php if ($canEdit): ?>
                            <?php partial('admin/_layout/thu-tu', [
                                'base' => '/quan-tri/danh-muc/thu-tu',
                                'id'   => $c['id'],
                                'dau'  => $i === 0,
                                'cuoi' => $i === $soDong - 1,
                                'ten'  => $c['name'],
                            ]); ?>
                        <?php else: ?>
                            <span class="num"><?= $i + 1 ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="admname"><?= e($c['name']) ?></td>
                    <td><code><?= e($c['slug']) ?></code></td>
                    <td class="atable__msg" title="<?= e($c['description'] ?? '') ?>"><?= e(excerpt($c['description'] ?? '', 60)) ?></td>
                    <?php /* Số sản phẩm đang thuộc danh mục — câu người ta hỏi ngay
                             trước khi bấm Xoá. */ ?>
                    <td class="num"><?= (int) ($c['product_count'] ?? 0) ?></td>
                    <td>
                        <?php /* VIÊN NHÃN BẤM ĐƯỢC, không phải nhãn chỉ-đọc.

                                 Ẩn/hiện một danh mục là thao tác một-bit và làm
                                 thường xuyên (mục theo mùa, mục chưa đủ hàng).
                                 Bắt mở form sửa ở cuối trang, tick một ô rồi bấm
                                 Lưu là bốn bước cho một cú bấm.

                                 Giữ nguyên HÌNH VIÊN THUỐC để cột không đổi dáng
                                 — xem .atoggle--pill trong admin.css. "Đang ẩn"
                                 vẫn trung tính chứ không đỏ: ẩn là việc bình
                                 thường và cố ý, không phải sự cố. */ ?>
                        <?php if ($canEdit): ?>
                            <form method="post" action="/quan-tri/danh-muc/hien">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                <button type="submit"
                                        class="atoggle atoggle--pill<?= $c['is_visible'] ? '' : ' is-off' ?>"
                                        title="<?= $c['is_visible'] ? 'Bấm để ẩn khỏi trang bán hàng' : 'Bấm để hiện trên trang bán hàng' ?>">
                                    <?= $c['is_visible'] ? 'Đang hiện' : 'Đang ẩn' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="badge badge--<?= $c['is_visible'] ? 'in_stock' : 'neutral' ?>">
                                <?= $c['is_visible'] ? 'Đang hiện' : 'Đang ẩn' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <?php if ($canEdit): ?>
                        <td class="arow-actions">
                            <a href="/quan-tri/danh-muc?sua=<?= e($c['id']) ?>" data-modal>Sửa</a>
                            <?php $hoi = sprintf('Xoá danh mục “%s”?', $c['name']); ?>
                            <form method="post" action="/quan-tri/danh-muc/xoa"
                                  data-confirm="<?= e($hoi) ?>"
                                  data-confirm-title="Xoá danh mục?"
                                  data-confirm-ok="Xoá"
                                  onsubmit="return confirm('<?= e($hoi) ?>')">
                                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
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
 * FORM THÊM/SỬA LÀ MỘT HỘP THOẠI NỔI — theo bản thiết kế "Danh mục.dc.html".
 *
 * Trước đây nó nằm cuối trang, bấm "+ Thêm" là màn hình nhảy xuống một biểu
 * mẫu ngoài tầm nhìn. Với bảng bốn dòng thì còn đọc được; với bảng hai mươi
 * dòng thì người bấm mất luôn ngữ cảnh, và sửa xong không biết mình vừa sửa
 * dòng nào.
 *
 * Hộp mở ra theo ĐỊA CHỈ, không theo JavaScript: ?them=1 mở form trống,
 * ?sua=<id> mở form đã điền. Nút ✕, nút Huỷ và lớp nền mờ đều là <a> trỏ về
 * chính trang này. Xem khối .amodal trong admin.css.
 */
$moHop  = $canEdit && ($ed !== null || isset($_GET['them']));
$dongUrl = '/quan-tri/danh-muc';
?>
<?php if ($moHop): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $ed !== null ? 'Sửa danh mục' : 'Thêm danh mục mới',
        'phu'     => $ed !== null
            ? $ed['name']
            : 'Danh mục mới đứng cuối menu — đổi vị trí bằng nút ↑↓ trên bảng.',
        'dongUrl' => $dongUrl,
        'rong'    => 'sm',
    ]); ?>

        <?php /* id="cat-form" để nút Lưu ở chân hộp trỏ tới bằng thuộc tính
                 form= — nút ấy nằm ngoài vùng cuộn của ruột hộp. */ ?>
        <form method="post" action="/quan-tri/danh-muc/luu" class="aform__grid" id="cat-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= e($ed['id'] ?? '') ?>">

            <div class="field">
                <label for="name">Tên danh mục *</label>
                <input type="text" id="name" name="name" required maxlength="255"
                       value="<?= e($ed['name'] ?? '') ?>">
            </div>

            <div class="field">
                <label for="slug">Slug <span class="field__opt">(bỏ trống để tự sinh)</span></label>
                <input type="text" id="slug" name="slug" maxlength="160"
                       value="<?= e($ed['slug'] ?? '') ?>">
            </div>

            <?php /* Ô "Thứ tự sắp xếp" ĐÃ BỎ khỏi form: cột ấy nay do cặp nút ↑↓
                     trên bảng ghi, và ghi lại cho CẢ bảng mỗi lần bấm (xem
                     ThuTuService). Để lại một ô cho người ta gõ tay vào thì hai
                     cơ chế cùng viết một cột — gõ số 5 vào đây rồi bấm ↑ một
                     lần là con số ấy bị ghi đè, không dấu vết. */ ?>

            <div class="field field--wide">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" rows="3"><?= e($ed['description'] ?? '') ?></textarea>
            </div>

            <div class="field field--check field--wide">
                <label>
                    <input type="checkbox" name="is_visible" <?= ($ed === null || $ed['is_visible']) ? 'checked' : '' ?>>
                    Hiển thị trên trang bán hàng
                </label>
            </div>
        </form>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $dongUrl,
        'luuNhan' => $ed !== null ? 'Lưu thay đổi' : 'Thêm danh mục',
        'luuForm' => 'cat-form',
    ]); ?>
<?php endif; ?>
