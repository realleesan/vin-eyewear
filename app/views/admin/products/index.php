<?php

/**
 * admin/products/index.php — sản phẩm
 * Port từ quan-tri/san-pham.tsx + admin-product-form.tsx.
 */

$ed = $editing;

/*
 * Hai cột JSON đổi về dạng PHP dùng được.
 *
 * `images` là mảng đường dẫn, thứ tự chính là ý nghĩa: phần tử đầu là ảnh
 * chính. `image_alts` là bản đồ đường-dẫn → chữ thay thế, tra theo đường dẫn
 * chứ không theo vị trí — đổi thứ tự ảnh thì alt phải đi theo đúng tấm của nó.
 *
 * `specs` KHÔNG còn đọc ở đây: bản vẽ bỏ ô nhập bảng thông số. Cột vẫn giữ
 * nguyên trong CSDL và trang bán hàng vẫn in nó ra — xem migration
 * 2026-08-29-san-pham-theo-ban-ve.sql.
 */
$edImages = [];
$edAlts   = [];

if ($ed !== null) {
    $edImages = array_values(array_filter(
        (array) json_decode((string) $ed['images'], true),
        'is_string'
    ));

    // Cột mới, có thể chưa tồn tại trên máy chưa chạy migration — ?? '' để
    // form vẫn dựng được thay vì ném lỗi khoá thiếu.
    $edAlts = array_filter(
        (array) json_decode((string) ($ed['image_alts'] ?? ''), true),
        'is_string'
    );
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
                            <a href="/quan-tri/san-pham?sua=<?= e($p['id']) ?>" data-modal>Sửa</a>
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

<?php
/*
 * FORM THÊM/SỬA LÀ MỘT HỘP THOẠI NỔI — theo bản thiết kế.
 *
 * Hộp mở ra theo ĐỊA CHỈ chứ không theo JavaScript: ?them=1 mở form trống,
 * ?sua=<id> mở form đã điền. Nút ✕, nút Huỷ và lớp nền mờ đều là <a> trỏ về
 * chính trang này. Lý do đầy đủ ở khối .amodal trong admin.css.
 *
 * Ruột hộp nằm ở _form.php — sáu tab, dài hơn bốn trăm dòng. Tách ra vì file
 * này còn phải dựng cả bảng danh sách, và hai việc đó không liên quan gì nhau.
 */
$moHop   = $canEdit && ($ed !== null || isset($_GET['them']));
$dongUrl = '/quan-tri/san-pham';
?>
<?php if ($moHop): ?>
    <?php partial('admin/_layout/modal-head', [
        'tieuDe'  => $ed !== null ? 'Sửa sản phẩm' : 'Thêm sản phẩm mới',
        'phu'     => $ed !== null
            ? $ed['name']
            : 'Điền tên, SKU và giá là lưu được — phần sau bổ sung dần.',
        'dongUrl' => $dongUrl,
        'rong'    => 'xxl',
    ]); ?>

        <?php /* require thẳng chứ không partial(): form cần rất nhiều biến của
                 file này ($ed, $categories, $collections, $variants, $edImages,
                 $edAlts, $brands). Liệt kê lại từng cái cho partial là một danh
                 sách sẽ lệch với thực tế ngay lần sửa đầu — cùng lý do đã ghi ở
                 admin/customers/index.php. */ ?>
        <?php require VIEWS_PATH . '/admin/products/_form.php'; ?>

    <?php partial('admin/_layout/modal-foot', [
        'dongUrl' => $dongUrl,
        'luuNhan' => $ed !== null ? 'Lưu thay đổi' : 'Thêm sản phẩm',
        'luuForm' => 'product-form',
        'ghiChu'  => 'Các ô để trống sẽ không hiển thị trên trang bán hàng.',
    ]); ?>
<?php endif; ?>
